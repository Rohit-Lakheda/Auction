<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Services\PayuService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentApiController extends Controller
{
    use ApiResponse;

    public function initiateAuctionPayment(Request $request, int $auctionId, PayuService $payu)
    {
        $userId = (int) $request->session()->get('user_id');
        if (! $userId) {
            return $this->fail('Unauthenticated.', 401);
        }

        $auction = DB::table('auctions')
            ->where('id', $auctionId)
            ->where('winner_user_id', $userId)
            ->where('status', 'closed')
            ->first();
        if (! $auction) {
            return $this->fail('Auction not eligible for payment.', 404);
        }
        if (($auction->payment_status ?? 'pending') === 'paid') {
            return $this->fail('Payment already completed.', 409);
        }

        $transactionId = 'TXN' . time() . random_int(1000, 9999);
        DB::table('payment_transactions')->insert([
            'transaction_id' => $transactionId,
            'auction_id' => $auctionId,
            'user_id' => $userId,
            'amount' => (float) ($auction->final_price ?? 0),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('users')->where('id', $userId)->first();
        $payload = [
            'key' => env('PAYU_MERCHANT_KEY'),
            'txnid' => $transactionId,
            'amount' => number_format((float) ($auction->final_price ?? 0), 2, '.', ''),
            'productinfo' => 'Auction Payment - ' . $auction->title,
            'firstname' => $user->name ?? 'User',
            'email' => $user->email ?? '',
            'phone' => DB::table('registration')->where('email', $user->email ?? '')->value('mobile') ?? '9999999999',
            'surl' => route('payu.auction.success'),
            'furl' => route('payu.auction.failure'),
            'udf1' => 'AUCTION_' . $auctionId,
            'udf2' => (string) $userId,
            'udf3' => '',
            'udf4' => '',
            'udf5' => '',
        ];
        $payload['hash'] = $payu->generateHash($payload);

        return $this->ok([
            'payment_url' => $payu->paymentUrl(),
            'payment_data' => $payload,
            'transaction_id' => $transactionId,
        ]);
    }

    public function transactionStatus(string $transactionId)
    {
        if (! Schema::hasTable('payment_transactions')) {
            return $this->fail('Payment transactions table missing.', 500);
        }
        $tx = DB::table('payment_transactions')->where('transaction_id', $transactionId)->first();
        if (! $tx) {
            return $this->fail('Transaction not found.', 404);
        }
        return $this->ok($tx);
    }
}

