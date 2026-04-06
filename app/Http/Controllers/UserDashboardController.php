<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserDashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $now = now();

        $stats = [
            'total_bids' => DB::table('bids')->where('user_id', $userId)->count(),
            'won_auctions' => DB::table('auctions')->where('winner_user_id', $userId)->whereIn('status', ['closed', 'completed'])->count(),
            'active_bidding' => (int) DB::table('auctions as a')
                ->where('a.status', 'active')
                ->where('a.end_datetime', '>', $now)
                ->whereExists(function ($q) use ($userId): void {
                    $q->select(DB::raw(1))
                        ->from('bids as b')
                        ->whereColumn('b.auction_id', 'a.id')
                        ->where('b.user_id', $userId);
                })
                ->count(),
        ];

        $watchlistTotal = 0;
        if (Schema::hasTable('watchlists')) {
            $watchlistTotal = (int) DB::table('watchlists as w')
                ->join('auctions as a', 'a.id', '=', 'w.auction_id')
                ->where('w.user_id', $userId)
                ->where('a.status', 'active')
                ->count();
        }

        $winningBids = collect(DB::select(
            "SELECT a.id, a.title, a.end_datetime, b.amount as my_bid,
             (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as highest_bid
             FROM auctions a JOIN bids b ON a.id = b.auction_id
             WHERE b.user_id = ? AND a.status = 'active'
             AND b.amount = (SELECT MAX(amount) FROM bids WHERE auction_id = a.id)
             ORDER BY a.end_datetime ASC LIMIT 5",
            [$userId]
        ));

        $recentBids = collect(DB::select(
            "SELECT b.*, a.title, a.status FROM bids b JOIN auctions a ON b.auction_id = a.id
             WHERE b.user_id = ? ORDER BY b.created_at DESC LIMIT 5",
            [$userId]
        ));

        $activeAuctions = DB::table('auctions as a')
            ->selectRaw("a.*, (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as current_bid, (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count")
            ->where('a.status', 'active')
            ->orderBy('a.end_datetime')
            ->limit(5)
            ->get();

        $profile = DB::selectOne(
            "SELECT u.name, u.email, u.created_at, r.registration_id, r.registration_type, r.pan_card_number, r.mobile
             FROM users u
             LEFT JOIN registration r ON u.email = r.email
             WHERE u.id = ? LIMIT 1",
            [$userId]
        );

        $watchlistAuctions = collect();
        if (Schema::hasTable('watchlists')) {
            $watchlistAuctions = DB::table('watchlists as w')
                ->join('auctions as a', 'a.id', '=', 'w.auction_id')
                ->leftJoin('auction_participants as ap', function ($join) use ($userId): void {
                    $join->on('ap.auction_id', '=', 'a.id')
                        ->where('ap.user_id', '=', $userId)
                        ->where('ap.emd_locked', '=', 1);
                })
                ->selectRaw("a.id, a.title, a.status, a.end_datetime, a.emd_amount, (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as current_bid, COUNT(ap.id) as joined_count, MAX(w.created_at) as watchlisted_at")
                ->where('w.user_id', $userId)
                ->groupBy('a.id', 'a.title', 'a.status', 'a.end_datetime', 'a.emd_amount')
                ->orderByDesc('watchlisted_at')
                ->limit(6)
                ->get();
        }

        return view('user.dashboard', compact('stats', 'winningBids', 'recentBids', 'activeAuctions', 'profile', 'watchlistAuctions', 'watchlistTotal'));
    }
}
