<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;

class PaymentAuditService
{
    /**
     * Row in payment_transactions when user is sent to PayU for bid pre-auth (same txn as bid_preauth_holds).
     */
    public function recordBidPreauthPending(string $transactionId, int $auctionId, int $userId, float $amount): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        if (DB::table('payment_transactions')->where('transaction_id', $transactionId)->exists()) {
            return;
        }

        $row = [
            'transaction_id' => $transactionId,
            'auction_id' => $auctionId,
            'user_id' => $userId,
            'amount' => $amount,
            'status' => 'pending',
            'updated_at' => now(),
            'created_at' => now(),
        ];
        if (Schema::hasColumn('payment_transactions', 'payment_kind')) {
            $row['payment_kind'] = 'bid_preauth';
        }

        DB::table('payment_transactions')->insert($row);

        $this->logEvent('bid_preauth_initiated', $transactionId, $auctionId, $userId, $amount, [
            'note' => 'Redirect to PayU hosted checkout for card pre-authorization',
        ]);
    }

    /**
     * PayU confirmed pre-auth hold; amount on hold matches bid — align payment row with bid.
     */
    public function markBidPreauthAuthorized(string $transactionId, string $payuId, float $amount, array $payuPayload): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        $payload = $this->encodeJson($payuPayload);
        $update = [
            'status' => 'authorized',
            'payu_transaction_id' => $payuId !== '' ? $payuId : null,
            'response_message' => 'Card pre-authorization hold active (bid amount secured)',
            'response_data' => $payload,
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('payment_transactions', 'amount')) {
            $update['amount'] = $amount;
        }

        DB::table('payment_transactions')->where('transaction_id', $transactionId)->update($update);

        $tx = DB::table('payment_transactions')->where('transaction_id', $transactionId)->first();
        $auctionId = $tx ? (int) ($tx->auction_id ?? 0) : 0;
        $userId = $tx ? (int) ($tx->user_id ?? 0) : 0;

        $this->logEvent('bid_preauth_authorized', $transactionId, $auctionId > 0 ? $auctionId : null, $userId > 0 ? $userId : null, $amount, [
            'mihpayid' => $payuId,
            'unmappedstatus' => $payuPayload['unmappedstatus'] ?? $payuPayload['unamappedstatus'] ?? null,
        ]);
    }

    public function markBidPreauthFailed(string $transactionId, string $status, ?string $message, array $payuPayload): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        $update = [
            'status' => $status,
            'response_message' => $message,
            'response_data' => $this->encodeJson($payuPayload),
            'updated_at' => now(),
        ];

        DB::table('payment_transactions')->where('transaction_id', $transactionId)->update($update);

        $tx = DB::table('payment_transactions')->where('transaction_id', $transactionId)->first();
        $auctionId = $tx ? (int) ($tx->auction_id ?? 0) : 0;
        $userId = $tx ? (int) ($tx->user_id ?? 0) : 0;
        $amount = $tx ? (float) ($tx->amount ?? 0) : null;

        $this->logEvent('bid_preauth_failed', $transactionId, $auctionId > 0 ? $auctionId : null, $userId > 0 ? $userId : null, $amount, [
            'payment_status' => $status,
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function logEvent(string $event, string $transactionId, ?int $auctionId, ?int $userId, ?float $amount, array $payload = []): void
    {
        if (! Schema::hasTable('payment_logs')) {
            return;
        }

        DB::table('payment_logs')->insert([
            'transaction_id' => $transactionId,
            'auction_id' => $auctionId,
            'user_id' => $userId,
            'event' => $event,
            'amount' => $amount,
            'payload' => $this->encodeJson($payload),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encodeJson(array $data): ?string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (JsonException) {
            return null;
        }
    }
}
