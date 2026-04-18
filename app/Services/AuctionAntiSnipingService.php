<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuctionAntiSnipingService
{
    public function extendEndIfWindowApplies(int $auctionId): void
    {
        $auction = DB::table('auctions')->where('id', $auctionId)->first();
        if (! $auction || $auction->status !== 'active') {
            return;
        }

        $end = strtotime((string) $auction->end_datetime);
        $now = time();
        $remainingSeconds = $end - $now;
        if ($remainingSeconds > 0 && $remainingSeconds <= 120) {
            DB::table('auctions')
                ->where('id', $auctionId)
                ->update([
                    'end_datetime' => date('Y-m-d H:i:s', $end + 120),
                    'updated_at' => now(),
                ]);
        }
    }
}
