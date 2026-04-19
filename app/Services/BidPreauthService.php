<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;

class BidPreauthService
{
    public function isEnabled(): bool
    {
        if (! config('payu.bid_preauth_enabled')) {
            return false;
        }

        return Schema::hasTable('bid_preauth_holds');
    }

    /**
     * Cancel the user's latest successful pre-auth hold for this auction (before placing a higher bid).
     *
     * @return string|null Error message if cancel failed and bid should not proceed.
     */
    public function cancelLatestHoldForUser(int $auctionId, int $userId, PayuService $payu): ?string
    {
        $hold = DB::table('bid_preauth_holds')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->where('status', 'bid_recorded')
            ->whereNotNull('payu_id')
            ->orderByDesc('id')
            ->first();

        if (! $hold) {
            return null;
        }

        $result = $payu->cancelTransaction((string) $hold->payu_id, (string) $hold->transaction_id);
        if (! $result['success']) {
            return 'Could not release your previous payment hold: '.($result['message'] ?: 'PayU error');
        }

        DB::table('bid_preauth_holds')->where('id', $hold->id)->update([
            'status' => 'cancelled',
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('payment_transactions')) {
            DB::table('payment_transactions')->where('transaction_id', $hold->transaction_id)->update([
                'status' => 'cancelled',
                'response_message' => 'Pre-auth released for higher bid',
                'updated_at' => now(),
            ]);
        }

        app(PaymentAuditService::class)->logEvent(
            'bid_preauth_superseded',
            (string) $hold->transaction_id,
            (int) $hold->auction_id,
            (int) $hold->user_id,
            (float) $hold->amount,
            ['reason' => 'User placed a higher bid; previous hold cancelled at PayU']
        );

        return null;
    }

    /**
     * After PayU authorization callback, auction moved — release the new hold.
     *
     * @return string|null Error message if cancel failed (caller may still surface failure).
     */
    public function cancelHoldRow(object $hold, PayuService $payu): ?string
    {
        if (empty($hold->payu_id)) {
            return null;
        }

        $result = $payu->cancelTransaction((string) $hold->payu_id, (string) $hold->transaction_id);
        if (! $result['success']) {
            return $result['message'] ?: 'PayU cancel failed';
        }

        DB::table('bid_preauth_holds')->where('id', $hold->id)->update([
            'status' => 'cancelled_stale',
            'updated_at' => now(),
        ]);

        return null;
    }

    /**
     * Store PayU POST body on the pending hold row (hash failures, declines, wrong unmappedstatus).
     */
    public function recordHoldPayuPayload(string $transactionId, array $payuData, string $holdStatus): void
    {
        if ($transactionId === '' || ! Schema::hasTable('bid_preauth_holds')) {
            return;
        }

        $payuId = isset($payuData['mihpayid']) ? trim((string) $payuData['mihpayid']) : null;

        DB::table('bid_preauth_holds')->where('transaction_id', $transactionId)->update([
            'payu_id' => $payuId !== '' && $payuId !== null ? $payuId : null,
            'status' => $holdStatus,
            'response_data' => $this->encodeJson($payuData),
            'updated_at' => now(),
        ]);
    }

    /**
     * Audit trail when pre-auth does not create a bid (always logged when bid_logs exists).
     *
     * @param  array<string, mixed>  $meta
     */
    public function logPreauthCallbackEvent(int $auctionId, int $userId, ?float $amount, string $eventType, array $meta): void
    {
        if (! Schema::hasTable('bid_logs')) {
            return;
        }

        DB::table('bid_logs')->insert([
            'auction_id' => $auctionId,
            'user_id' => $userId,
            'amount' => $amount,
            'event_type' => $eventType,
            'meta' => $this->encodeJson($meta),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '{}';
        }
    }
}
