<?php

namespace App\Http\Controllers;

use App\Services\AuctionAntiSnipingService;
use App\Services\BidPreauthService;
use App\Services\PayuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BidPreauthController extends Controller
{
    public function success(Request $request, PayuService $payu, BidPreauthService $bidPreauthService, AuctionAntiSnipingService $antiSniping): RedirectResponse
    {
        $userId = (int) $request->session()->get('user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $data = array_merge($request->query(), $request->post());

        $txnid = (string) ($data['txnid'] ?? '');
        $auctionId = $this->parseAuctionIdFromUdf((string) ($data['udf1'] ?? ''));
        $udfUid = (int) ($data['udf2'] ?? 0);

        if ($auctionId <= 0 || $udfUid !== $userId) {
            return redirect()->route('user.auctions.index')->with('bid_error', 'Payment response could not be matched to your session.');
        }

        if (! $payu->verifyHash($data)) {
            Log::warning('Bid pre-auth callback hash mismatch.', ['txnid' => $txnid]);

            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'Payment could not be verified.');
        }

        if (($data['status'] ?? '') !== 'success') {
            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'Authorization was not successful.');
        }

        $unmapped = strtolower((string) ($data['unmappedstatus'] ?? $data['unamappedstatus'] ?? ''));
        if ($unmapped !== 'auth') {
            Log::notice('Bid pre-auth callback unexpected unmappedstatus.', ['txnid' => $txnid, 'unmapped' => $unmapped]);

            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'This payment was not a card pre-authorization. Try again using a supported credit card.');
        }

        $payuTxnId = (string) ($data['mihpayid'] ?? '');

        try {
            DB::transaction(function () use ($txnid, $payuTxnId, $data, $auctionId, $userId): void {
                $hold = DB::table('bid_preauth_holds')
                    ->where('transaction_id', $txnid)
                    ->lockForUpdate()
                    ->first();

                if (! $hold || (int) $hold->user_id !== $userId || (int) $hold->auction_id !== $auctionId) {
                    Log::warning('Bid pre-auth unknown or mismatched hold.', ['txnid' => $txnid]);

                    throw new \RuntimeException('missing_hold');
                }

                if ($hold->status === 'bid_recorded') {
                    throw new \RuntimeException('idempotent_ok');
                }

                if ($hold->status !== 'pending_redirect') {
                    throw new \RuntimeException('bad_hold_state');
                }

                $auction = DB::table('auctions')->where('id', $auctionId)->lockForUpdate()->first();
                if (! $auction || $auction->status !== 'active') {
                    throw new \RuntimeException('auction_not_active');
                }

                if (Schema::hasTable('auction_participants')) {
                    $isParticipant = DB::table('auction_participants')
                        ->where('auction_id', $auctionId)
                        ->where('user_id', $userId)
                        ->where('status', 'active')
                        ->exists();
                    if (! $isParticipant) {
                        throw new \RuntimeException('not_participant');
                    }
                }

                $bidAmount = (float) $hold->amount;
                $currentBid = (float) (DB::table('bids')->where('auction_id', $auctionId)->max('amount') ?? 0);
                $baseLine = $currentBid > 0 ? $currentBid : (float) $auction->base_price;
                $minNextBid = $baseLine + (float) $auction->min_increment;

                if ($bidAmount < $minNextBid) {
                    throw new \RuntimeException('below_min');
                }

                DB::table('bids')->insert([
                    'auction_id' => $auctionId,
                    'user_id' => $userId,
                    'amount' => $bidAmount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $top = DB::table('bids')
                    ->where('auction_id', $auctionId)
                    ->selectRaw('user_id, MAX(amount) as amount')
                    ->groupBy('user_id')
                    ->orderByDesc('amount')
                    ->limit(3)
                    ->get()
                    ->map(fn ($r) => ['user_id' => $r->user_id, 'amount' => $r->amount,
                        'bidder_name' => DB::table('users')->where('id', $r->user_id)->value('name') ?? 'Unknown'])
                    ->all();
                DB::table('auctions')->where('id', $auctionId)->update([
                    'top_bidders_json' => json_encode($top, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

                if (Schema::hasTable('bid_logs')) {
                    DB::table('bid_logs')->insert([
                        'auction_id' => $auctionId,
                        'user_id' => $userId,
                        'amount' => $bidAmount,
                        'event_type' => 'placed',
                        'meta' => json_encode(['source' => 'bid_preauth', 'txnid' => $txnid], JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                    ]);
                }

                DB::table('bid_preauth_holds')->where('id', $hold->id)->update([
                    'payu_id' => $payuTxnId !== '' ? $payuTxnId : null,
                    'status' => 'bid_recorded',
                    'response_data' => json_encode($data, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            $code = $e->getMessage();
            if ($code === 'idempotent_ok') {
                return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_success', 'Bid is already recorded.');
            }
            if ($code === 'missing_hold') {
                return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'Authorization could not be linked. Contact support if your bank shows a hold.');
            }
            if ($code === 'bad_hold_state') {
                return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'This authorization was already processed.');
            }

            if ($payuTxnId !== '' && in_array($code, ['auction_not_active', 'not_participant', 'below_min'], true)) {
                $holdFresh = DB::table('bid_preauth_holds')->where('transaction_id', $txnid)->first();
                if ($holdFresh) {
                    $bidPreauthService->cancelHoldRow((object) array_merge((array) $holdFresh, ['payu_id' => $payuTxnId]), $payu);
                }
            }

            return match ($code) {
                'auction_not_active' => redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'This auction is no longer active; the authorization was released.'),
                'not_participant' => redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'You are not authorized to bid; the hold was released.'),
                'below_min' => redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'Bid is no longer sufficient (auction moved while you paid). The hold was released.'),
                default => redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'Could not complete your bid.'),
            };
        } catch (\Throwable $e) {
            Log::error('Bid pre-auth callback failed.', ['exception' => $e->getMessage(), 'txnid' => $txnid]);

            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId > 0 ? $auctionId : 0])->with('bid_error', 'Could not complete your bid.');
        }

        $antiSniping->extendEndIfWindowApplies($auctionId);

        return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_success', 'Bid placed successfully after card authorization.');
    }

    public function failure(Request $request): RedirectResponse
    {
        $data = array_merge($request->query(), $request->post());
        $txnid = (string) ($data['txnid'] ?? '');
        $auctionId = $this->parseAuctionIdFromUdf((string) ($data['udf1'] ?? ''));

        if ($txnid !== '' && Schema::hasTable('bid_preauth_holds')) {
            DB::table('bid_preauth_holds')->where('transaction_id', $txnid)->update([
                'status' => 'failed',
                'response_data' => json_encode($data, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }

        if ($auctionId > 0) {
            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', (string) ($data['error_Message'] ?? $data['error'] ?? 'Authorization failed.'));
        }

        return redirect()->route('user.auctions.index')->with('bid_error', 'Authorization failed.');
    }

    private function parseAuctionIdFromUdf(string $udf1): int
    {
        if (preg_match('/^BID_PREAUTH_(\d+)/', $udf1, $m)) {
            return (int) $m[1];
        }

        return 0;
    }
}
