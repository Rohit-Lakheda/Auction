<?php

namespace App\Http\Controllers;

use App\Services\AuctionAntiSnipingService;
use App\Services\BidPreauthService;
use App\Services\PaymentAuditService;
use App\Services\PayuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BidPreauthController extends Controller
{
    public function success(Request $request, PayuService $payu, BidPreauthService $bidPreauthService, AuctionAntiSnipingService $antiSniping, PaymentAuditService $paymentAudit): RedirectResponse
    {
        $data = array_merge($request->query(), $request->post());
        Log::info('bid_preauth.payu_success_url_callback', $payu->scrubPayuPayloadForLogging($data));

        $userId = (int) $request->session()->get('user_id');
        if (! $userId) {
            Log::warning('bid_preauth.success_route_no_session', ['txnid' => $data['txnid'] ?? '']);

            return redirect()->route('login');
        }

        $txnid = (string) ($data['txnid'] ?? '');
        $auctionId = $this->parseAuctionIdFromUdf((string) ($data['udf1'] ?? ''));
        $udfUid = (int) ($data['udf2'] ?? 0);

        if ($auctionId <= 0 || $udfUid !== $userId) {
            Log::warning('bid_preauth.success_route_session_mismatch', [
                'auction_id_parsed' => $auctionId,
                'udf_uid' => $udfUid,
                'session_uid' => $userId,
                'txnid' => $txnid,
            ]);

            return redirect()->route('user.auctions.index')->with('bid_error', 'Payment response could not be matched to your session.');
        }

        if (! $payu->verifyHash($data)) {
            Log::warning('bid_preauth.callback_hash_mismatch', ['txnid' => $txnid]);
            $this->persistPreauthDeclined($bidPreauthService, $payu, $paymentAudit, $txnid, $auctionId, $userId, $data, 'payu_hash_failed', 'payu_preauth_hash_failed');

            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', 'Payment could not be verified. If PayU charged a hold, contact support with your PayU transaction ID.');
        }

        if (($data['status'] ?? '') !== 'success') {
            $this->persistPreauthDeclined($bidPreauthService, $payu, $paymentAudit, $txnid, $auctionId, $userId, $data, 'payu_status_not_success', 'payu_preauth_status_failed');
            $msg = $payu->bidPreauthExplanationForUser($data).$this->payuReferenceSuffix($data, $txnid);

            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', $msg);
        }

        $unmapped = strtolower((string) ($data['unmappedstatus'] ?? $data['unamappedstatus'] ?? ''));
        if ($unmapped !== 'auth') {
            Log::notice('bid_preauth.callback_not_preauth_hold', [
                'txnid' => $txnid,
                'unmappedstatus' => $data['unmappedstatus'] ?? $data['unamappedstatus'] ?? '',
            ]);
            $this->persistPreauthDeclined($bidPreauthService, $payu, $paymentAudit, $txnid, $auctionId, $userId, $data, 'payu_hold_not_created', 'payu_preauth_not_auth');
            $msg = $payu->bidPreauthExplanationForUser($data).$this->payuReferenceSuffix($data, $txnid);

            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', $msg);
        }

        $payuTxnId = (string) ($data['mihpayid'] ?? '');

        try {
            DB::transaction(function () use ($txnid, $payuTxnId, $data, $auctionId, $userId, $paymentAudit): void {
                $hold = DB::table('bid_preauth_holds')
                    ->where('transaction_id', $txnid)
                    ->lockForUpdate()
                    ->first();

                if (! $hold || (int) $hold->user_id !== $userId || (int) $hold->auction_id !== $auctionId) {
                    Log::warning('bid_preauth.unknown_or_mismatched_hold', ['txnid' => $txnid]);

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
                        'meta' => json_encode(['source' => 'bid_preauth', 'txnid' => $txnid, 'mihpayid' => $payuTxnId], JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                    ]);
                }

                DB::table('bid_preauth_holds')->where('id', $hold->id)->update([
                    'payu_id' => $payuTxnId !== '' ? $payuTxnId : null,
                    'status' => 'bid_recorded',
                    'response_data' => json_encode($data, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

                $paymentAudit->markBidPreauthAuthorized($txnid, $payuTxnId, $bidAmount, $data);

                Log::info('bid_preauth.hold_recorded_and_bid_placed', [
                    'auction_id' => $auctionId,
                    'user_id' => $userId,
                    'txnid' => $txnid,
                    'mihpayid' => $payuTxnId,
                    'amount' => $bidAmount,
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
            Log::error('bid_preauth.transaction_failed', ['exception' => $e->getMessage(), 'txnid' => $txnid]);

            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId > 0 ? $auctionId : 0])->with('bid_error', 'Could not complete your bid.');
        }

        $antiSniping->extendEndIfWindowApplies($auctionId);

        return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_success', 'Bid placed successfully after card authorization.');
    }

    public function failure(Request $request, PayuService $payu, BidPreauthService $bidPreauthService, PaymentAuditService $paymentAudit): RedirectResponse
    {
        $data = array_merge($request->query(), $request->post());
        Log::info('bid_preauth.payu_failure_url_callback', $payu->scrubPayuPayloadForLogging($data));

        $txnid = (string) ($data['txnid'] ?? '');
        $auctionId = $this->parseAuctionIdFromUdf((string) ($data['udf1'] ?? ''));
        $userIdFromUdf = (int) ($data['udf2'] ?? 0);

        if ($txnid !== '' && Schema::hasTable('bid_preauth_holds')) {
            $payuId = isset($data['mihpayid']) ? trim((string) $data['mihpayid']) : '';
            DB::table('bid_preauth_holds')->where('transaction_id', $txnid)->update([
                'payu_id' => $payuId !== '' ? $payuId : null,
                'status' => 'failed',
                'response_data' => json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                'updated_at' => now(),
            ]);
        }

        if ($txnid !== '') {
            $paymentAudit->markBidPreauthFailed(
                $txnid,
                'failed',
                $payu->bidPreauthExplanationForUser($data),
                $data
            );
        }

        if ($auctionId > 0 && $userIdFromUdf > 0 && Schema::hasTable('bid_logs')) {
            $hold = $txnid !== '' ? DB::table('bid_preauth_holds')->where('transaction_id', $txnid)->first() : null;
            $amount = $hold ? (float) $hold->amount : null;
            $bidPreauthService->logPreauthCallbackEvent($auctionId, $userIdFromUdf, $amount, 'payu_preauth_failure_callback', [
                'txnid' => $txnid,
                'payu' => $payu->scrubPayuPayloadForLogging($data),
            ]);
        }

        $msg = $payu->bidPreauthExplanationForUser($data).$this->payuReferenceSuffix($data, $txnid);

        if ($auctionId > 0) {
            return redirect()->route('user.auctions.show', ['auctionId' => $auctionId])->with('bid_error', $msg);
        }

        return redirect()->route('user.auctions.index')->with('bid_error', $msg);
    }

    private function persistPreauthDeclined(
        BidPreauthService $bidPreauthService,
        PayuService $payu,
        PaymentAuditService $paymentAudit,
        string $txnid,
        int $auctionId,
        int $userId,
        array $data,
        string $holdStatus,
        string $bidLogEvent,
    ): void {
        if ($txnid === '') {
            return;
        }

        $bidPreauthService->recordHoldPayuPayload($txnid, $data, $holdStatus);
        $hold = DB::table('bid_preauth_holds')->where('transaction_id', $txnid)->first();
        $amount = $hold ? (float) $hold->amount : null;
        $bidPreauthService->logPreauthCallbackEvent($auctionId, $userId, $amount, $bidLogEvent, [
            'txnid' => $txnid,
            'payu' => $payu->scrubPayuPayloadForLogging($data),
        ]);

        $paymentAudit->markBidPreauthFailed(
            $txnid,
            $this->mapHoldDeclineToPaymentStatus($holdStatus),
            $payu->bidPreauthExplanationForUser($data),
            $data
        );
    }

    private function mapHoldDeclineToPaymentStatus(string $holdStatus): string
    {
        return match ($holdStatus) {
            'payu_hash_failed' => 'verification_failed',
            'payu_status_not_success' => 'failed',
            'payu_hold_not_created' => 'declined',
            default => 'failed',
        };
    }

    /**
     * Shown to users for support correlation (PayU dashboard "Bounced", etc.).
     *
     * @param  array<string, mixed>  $data
     */
    private function payuReferenceSuffix(array $data, string $txnid): string
    {
        $parts = [];
        if (! empty($data['mihpayid'])) {
            $parts[] = 'PayU ID '.(string) $data['mihpayid'];
        }
        if ($txnid !== '') {
            $parts[] = 'Txn '.$txnid;
        }

        return $parts === [] ? '' : ' ('.implode(' · ', $parts).').';
    }

    private function parseAuctionIdFromUdf(string $udf1): int
    {
        if (preg_match('/^BID_PREAUTH_(\d+)/', $udf1, $m)) {
            return (int) $m[1];
        }

        return 0;
    }
}
