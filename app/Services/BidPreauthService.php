<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
}
