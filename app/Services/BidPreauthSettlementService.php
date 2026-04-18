<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BidPreauthSettlementService
{
    public function settleAfterAuctionClosed(int $auctionId): void
    {
        if (! Schema::hasTable('bid_preauth_holds')) {
            return;
        }

        $auction = DB::table('auctions')->where('id', $auctionId)->first();
        if (! $auction || (int) ($auction->winner_user_id ?? 0) <= 0) {
            $this->cancelAllOpenHoldsForAuction($auctionId);

            return;
        }

        $winnerId = (int) $auction->winner_user_id;
        $payu = app(PayuService::class);

        $winnerHold = DB::table('bid_preauth_holds')
            ->where('auction_id', $auctionId)
            ->where('user_id', $winnerId)
            ->where('status', 'bid_recorded')
            ->whereNotNull('payu_id')
            ->orderByDesc('id')
            ->first();

        $others = DB::table('bid_preauth_holds')
            ->where('auction_id', $auctionId)
            ->where('status', 'bid_recorded')
            ->whereNotNull('payu_id')
            ->when($winnerHold, fn ($q) => $q->where('id', '<>', $winnerHold->id))
            ->orderBy('id')
            ->get();

        foreach ($others as $hold) {
            $r = $payu->cancelTransaction((string) $hold->payu_id, (string) $hold->transaction_id);
            DB::table('bid_preauth_holds')->where('id', $hold->id)->update([
                'status' => $r['success'] ? 'cancelled' : 'cancel_failed',
                'response_data' => json_encode(['settlement_cancel' => $r], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            if (! $r['success']) {
                Log::warning('Bid preauth settlement: cancel failed.', ['hold_id' => $hold->id, 'auction_id' => $auctionId]);
            }
        }

        if ($winnerHold) {
            $amount = number_format((float) $winnerHold->amount, 2, '.', '');
            $capture = $payu->captureTransaction((string) $winnerHold->payu_id, (string) $winnerHold->transaction_id, $amount);
            DB::table('bid_preauth_holds')->where('id', $winnerHold->id)->update([
                'status' => $capture['success'] ? 'captured' : 'capture_failed',
                'response_data' => json_encode(['settlement_capture' => $capture], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            if ($capture['success']) {
                $update = ['payment_status' => 'paid', 'updated_at' => now()];
                if (Schema::hasColumn('auctions', 'payment_date')) {
                    $update['payment_date'] = now();
                }
                DB::table('auctions')->where('id', $auctionId)->update($update);

                $this->syncWinnerPaymentTransaction($auctionId, $winnerId, $winnerHold, $capture);
            } else {
                Log::error('Bid preauth settlement: capture failed.', [
                    'auction_id' => $auctionId,
                    'hold_id' => $winnerHold->id,
                    'message' => $capture['message'],
                ]);
            }

            return;
        }

        Log::warning('Bid preauth settlement: no winner hold found.', ['auction_id' => $auctionId, 'winner_id' => $winnerId]);
    }

    public function cancelAllOpenHoldsForAuction(int $auctionId): void
    {
        if (! Schema::hasTable('bid_preauth_holds')) {
            return;
        }

        $payu = app(PayuService::class);
        $holds = DB::table('bid_preauth_holds')
            ->where('auction_id', $auctionId)
            ->where('status', 'bid_recorded')
            ->whereNotNull('payu_id')
            ->orderBy('id')
            ->get();

        foreach ($holds as $hold) {
            $r = $payu->cancelTransaction((string) $hold->payu_id, (string) $hold->transaction_id);
            DB::table('bid_preauth_holds')->where('id', $hold->id)->update([
                'status' => $r['success'] ? 'cancelled' : 'cancel_failed',
                'response_data' => json_encode(['auction_cancelled' => $r], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    private function syncWinnerPaymentTransaction(int $auctionId, int $winnerUserId, object $hold, array $capture): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        $existing = DB::table('payment_transactions')->where('transaction_id', $hold->transaction_id)->first();
        $payload = json_encode([
            'bid_preauth_capture' => $capture,
            'payu_hold_id' => $hold->id,
        ], JSON_THROW_ON_ERROR);

        if ($existing) {
            DB::table('payment_transactions')->where('id', $existing->id)->update([
                'status' => 'success',
                'payu_transaction_id' => $hold->payu_id,
                'response_message' => 'Captured from bid pre-authorization',
                'response_data' => $payload,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('payment_transactions')->insert([
            'transaction_id' => $hold->transaction_id,
            'auction_id' => $auctionId,
            'user_id' => $winnerUserId,
            'amount' => (float) $hold->amount,
            'status' => 'success',
            'payu_transaction_id' => $hold->payu_id,
            'response_message' => 'Captured from bid pre-authorization',
            'response_data' => $payload,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
