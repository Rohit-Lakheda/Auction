<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Services\BidPreauthService;
use App\Services\PaymentAuditService;
use App\Services\PayuService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuctionApiController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'active');
        $search = trim((string) $request->query('search', ''));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));

        $rows = DB::table('auctions')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->ok($rows);
    }

    public function show(int $id)
    {
        $auction = DB::table('auctions')->where('id', $id)->first();
        if (! $auction) {
            return $this->fail('Auction not found.', 404);
        }
        $currentBid = (float) (DB::table('bids')->where('auction_id', $id)->max('amount') ?? 0);
        $bidCount = (int) DB::table('bids')->where('auction_id', $id)->count();

        return $this->ok([
            'auction' => $auction,
            'current_bid' => $currentBid,
            'bid_count' => $bidCount,
        ]);
    }

    public function placeBid(Request $request, int $id, BidPreauthService $bidPreauthService, PayuService $payuService, PaymentAuditService $paymentAuditService)
    {
        $validated = $request->validate(['bid_amount' => ['required', 'numeric', 'min:0.01']]);
        $userId = (int) $request->session()->get('user_id');
        $amount = (float) $validated['bid_amount'];

        if ($bidPreauthService->isEnabled()) {
            try {
                DB::transaction(function () use ($id, $userId, $amount): void {
                    $auction = DB::table('auctions')->where('id', $id)->lockForUpdate()->first();
                    if (! $auction || $auction->status !== 'active') {
                        throw new \RuntimeException('Auction not active.');
                    }
                    if (Schema::hasTable('auction_participants')) {
                        $joined = DB::table('auction_participants')
                            ->where('auction_id', $id)
                            ->where('user_id', $userId)
                            ->where('status', 'active')
                            ->exists();
                        if (! $joined) {
                            throw new \RuntimeException('Participation payment required.');
                        }
                    }

                    $currentBid = (float) (DB::table('bids')->where('auction_id', $id)->max('amount') ?? 0);
                    $baseLine = $currentBid > 0 ? $currentBid : (float) $auction->base_price;
                    $minNext = $baseLine + (float) $auction->min_increment;
                    if ($amount < $minNext) {
                        throw new \RuntimeException('Bid is below minimum next bid.');
                    }
                });
            } catch (\RuntimeException $e) {
                return $this->fail($e->getMessage(), 422);
            }

            $cancelErr = $bidPreauthService->cancelLatestHoldForUser($id, $userId, $payuService);
            if ($cancelErr !== null) {
                return $this->fail($cancelErr, 422);
            }

            $transactionId = 'BPA'.time().random_int(1000, 9999);
            DB::table('bid_preauth_holds')->insert([
                'auction_id' => $id,
                'user_id' => $userId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'status' => 'pending_redirect',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $paymentAuditService->recordBidPreauthPending($transactionId, $id, $userId, $amount);

            $auction = DB::table('auctions')->where('id', $id)->first();
            $user = DB::table('users')->where('id', $userId)->first();
            $payload = [
                'key' => env('PAYU_MERCHANT_KEY'),
                'txnid' => $transactionId,
                'amount' => number_format($amount, 2, '.', ''),
                'productinfo' => 'Bid hold - '.($auction->title ?? 'Auction'),
                'firstname' => $user->name ?? 'User',
                'email' => $user->email ?? '',
                'phone' => DB::table('registration')->where('email', $user->email ?? '')->value('mobile') ?? '9999999999',
                'surl' => route('payu.bid-preauth.success'),
                'furl' => route('payu.bid-preauth.failure'),
                'udf1' => 'BID_PREAUTH_'.$id,
                'udf2' => (string) $userId,
                'udf3' => '',
                'udf4' => '',
                'udf5' => '',
            ];
            $payload['hash'] = $payuService->generateHash($payload);
            $payload = $payuService->applyBidPreauthHostedFields($payload);

            return $this->ok([
                'payment_url' => $payuService->paymentUrl(),
                'payment_data' => $payload,
                'transaction_id' => $transactionId,
            ]);
        }

        try {
            DB::transaction(function () use ($id, $userId, $amount): void {
                $auction = DB::table('auctions')->where('id', $id)->lockForUpdate()->first();
                if (! $auction || $auction->status !== 'active') {
                    throw new \RuntimeException('Auction not active.');
                }
                if (Schema::hasTable('auction_participants')) {
                    $joined = DB::table('auction_participants')
                        ->where('auction_id', $id)
                        ->where('user_id', $userId)
                        ->where('status', 'active')
                        ->exists();
                    if (! $joined) {
                        throw new \RuntimeException('Participation payment required.');
                    }
                }

                $currentBid = (float) (DB::table('bids')->where('auction_id', $id)->max('amount') ?? 0);
                $baseLine = $currentBid > 0 ? $currentBid : (float) $auction->base_price;
                $minNext = $baseLine + (float) $auction->min_increment;
                if ($amount < $minNext) {
                    throw new \RuntimeException('Bid is below minimum next bid.');
                }

                DB::table('bids')->insert([
                    'auction_id' => $id,
                    'user_id' => $userId,
                    'amount' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok(null, 'Bid placed successfully.');
    }

    public function notifications(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        if (! Schema::hasTable('admin_message_recipients') || ! Schema::hasTable('admin_messages')) {
            return $this->ok([]);
        }
        $rows = DB::table('admin_message_recipients as r')
            ->join('admin_messages as m', 'm.id', '=', 'r.message_id')
            ->where('r.user_id', $userId)
            ->orderByDesc('m.created_at')
            ->limit(50)
            ->get(['m.id', 'm.subject', 'm.message', 'm.created_at', 'r.is_read']);

        return $this->ok($rows);
    }
}
