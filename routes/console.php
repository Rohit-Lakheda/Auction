<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Services\EmdAuctionService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('emd:process-defaults', function (): void {
    $service = app(EmdAuctionService::class);
    $auctions = DB::table('auctions')
        ->where('status', 'closed')
        ->where('payment_status', 'pending')
        ->whereNotNull('payment_window_expires_at')
        ->where('payment_window_expires_at', '<=', now())
        ->pluck('id');

    $processed = 0;
    foreach ($auctions as $auctionId) {
        try {
            $service->markTopBidderDefaultAndPromote((int) $auctionId);
            $processed++;
        } catch (\Throwable $e) {
            $this->error("Auction {$auctionId} failed: {$e->getMessage()}");
        }
    }

    $this->info("Processed {$processed} auction(s).");
})->purpose('Promote H2/H3 when payment window expires');
