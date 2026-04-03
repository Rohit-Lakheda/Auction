<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\Controller;

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
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%' . $search . '%'))
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

    public function placeBid(Request $request, int $id)
    {
        $validated = $request->validate(['bid_amount' => ['required', 'numeric', 'min:0.01']]);
        $userId = (int) $request->session()->get('user_id');
        $amount = (float) $validated['bid_amount'];

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

