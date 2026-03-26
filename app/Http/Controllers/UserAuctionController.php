<?php

namespace App\Http\Controllers;

use App\Services\EmdAuctionService;
use App\Services\AppSettingsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserAuctionController extends Controller
{
    public function __construct(
        private readonly EmdAuctionService $emdAuctionService,
        private readonly AppSettingsService $settingsService
    )
    {
    }

    public function index(Request $request)
    {
        $this->updateAuctionStatuses();
        $userId = (int) $request->session()->get('user_id');
        $watchlistEnabled = Schema::hasTable('watchlists');
        $sort = (string) $request->query('sort', 'ending_soon');
        $search = trim((string) $request->query('search', ''));
        $emdFilter = (string) $request->query('emd', 'all');
        $perPage = $this->resolvePerPage($request, 12);

        $watchSelect = $watchlistEnabled
            ? "(SELECT COUNT(*) FROM watchlists w WHERE w.auction_id = a.id AND w.user_id = ?) as watchlisted_count"
            : "0 as watchlisted_count";

        $query = DB::table('auctions as a')
            ->selectRaw("a.*, (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as current_bid, (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count, (SELECT COUNT(*) FROM auction_participants ap WHERE ap.auction_id = a.id AND ap.user_id = ? AND ap.emd_locked = 1) as joined_count, {$watchSelect}", $watchlistEnabled ? [$userId, $userId] : [$userId])
            ->where('a.status', 'active')
            ->when($search !== '', fn ($q) => $q->where('a.title', 'like', '%' . $search . '%'))
            ->when($emdFilter === 'low', fn ($q) => $q->where('a.emd_amount', '<=', 5000))
            ->when($emdFilter === 'mid', fn ($q) => $q->whereBetween('a.emd_amount', [5001, 25000]))
            ->when($emdFilter === 'high', fn ($q) => $q->where('a.emd_amount', '>', 25000));

        if ($sort === 'highest_bid') {
            $query->orderByDesc('current_bid');
        } elseif ($sort === 'newest') {
            $query->orderByDesc('a.id');
        } else {
            $query->orderBy('a.end_datetime');
        }

        $auctions = $this->paginateQuery($query, $perPage);

        $recentlyViewedIds = collect($request->session()->get('recently_viewed_auctions', []))
            ->take(5)
            ->all();
        $recentlyViewed = empty($recentlyViewedIds)
            ? collect()
            : DB::table('auctions')
                ->whereIn('id', $recentlyViewedIds)
                ->get()
                ->sortBy(fn ($a) => array_search((int) $a->id, $recentlyViewedIds, true))
                ->values();

        $watchlistAuctions = collect();
        if ($watchlistEnabled) {
            $watchlistAuctions = DB::table('watchlists as w')
                ->join('auctions as a', 'a.id', '=', 'w.auction_id')
                ->select('a.id', 'a.title', 'a.status', 'a.end_datetime', 'a.emd_amount')
                ->where('w.user_id', $userId)
                ->orderByDesc('w.created_at')
                ->limit(8)
                ->get();
        }

        return view('user.auctions.index', [
            'auctions' => $auctions,
            'sort' => $sort,
            'search' => $search,
            'emdFilter' => $emdFilter,
            'perPage' => (string) $request->query('per_page', (string) $perPage),
            'recentlyViewed' => $recentlyViewed,
            'watchlistAuctions' => $watchlistAuctions,
        ]);
    }

    public function show(Request $request, int $auctionId)
    {
        $this->updateAuctionStatuses();

        $auction = DB::table('auctions')->where('id', $auctionId)->first();
        if (! $auction || $auction->status !== 'active') {
            return redirect()->route('user.auctions.index');
        }

        $currentBid = (float) $this->getCurrentHighestBid($auctionId);
        if ($currentBid <= 0) {
            $currentBid = (float) $auction->base_price;
        }
        $minNextBid = $currentBid + (float) $auction->min_increment;

        $recentBids = DB::table('bids as b')
            ->join('users as u', 'b.user_id', '=', 'u.id')
            ->where('b.auction_id', $auctionId)
            ->orderByDesc('b.created_at')
            ->limit(10)
            ->get(['b.amount', 'b.created_at', 'u.name']);

        $userId = (int) $request->session()->get('user_id');
        $recentlyViewed = array_values(array_unique(array_merge([$auctionId], (array) $request->session()->get('recently_viewed_auctions', []))));
        $request->session()->put('recently_viewed_auctions', array_slice($recentlyViewed, 0, 20));

        $userHasBid = DB::table('bids')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->exists();
        $isParticipant = DB::table('auction_participants')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->where('emd_locked', 1)
            ->exists();
        $walletBalance = (float) (DB::table('users')->where('id', $userId)->value('wallet_balance') ?? 0);
        $requiredEmd = (float) ($auction->emd_amount ?? 0);
        $walletShortfall = max(0, $requiredEmd - $walletBalance);
        $isWatchlisted = false;
        if (Schema::hasTable('watchlists')) {
            $isWatchlisted = DB::table('watchlists')
                ->where('auction_id', $auctionId)
                ->where('user_id', $userId)
                ->exists();
        }
        $myHighestBid = (float) (DB::table('bids')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->max('amount') ?? 0);

        return view('user.auctions.show', [
            'auction' => $auction,
            'currentBid' => $currentBid,
            'minNextBid' => $minNextBid,
            'recentBids' => $recentBids,
            'userHasBid' => $userHasBid,
            'isParticipant' => $isParticipant,
            'walletBalance' => $walletBalance,
            'walletShortfall' => $walletShortfall,
            'requiredEmd' => $requiredEmd,
            'isWatchlisted' => $isWatchlisted,
            'myHighestBid' => $myHighestBid,
        ]);
    }

    public function placeBid(Request $request, int $auctionId)
    {
        $request->validate([
            'bid_amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $attempts = $request->session()->get('bid_attempts', []);
        $now = time();
        $attempts = array_values(array_filter($attempts, static fn ($ts) => ($now - (int) $ts) < 60));
        if (count($attempts) >= 10) {
            return back()->with('bid_error', 'Too many bid attempts. Please wait a moment.');
        }
        $attempts[] = $now;
        $request->session()->put('bid_attempts', $attempts);

        $bidAmount = (float) $request->input('bid_amount');
        try {
            $this->emdAuctionService->placeBid($auctionId, (int) $request->session()->get('user_id'), $bidAmount);
            $this->applyAntiSniping((int) $auctionId);
        } catch (\RuntimeException $e) {
            return back()->with('bid_error', $e->getMessage());
        }

        return back()->with('bid_success', 'Bid placed successfully for ' . $this->formatInr($bidAmount) . '!');
    }

    public function joinAuction(Request $request, int $auctionId)
    {
        $userId = (int) $request->session()->get('user_id');
        try {
            $result = $this->emdAuctionService->joinAuction($auctionId, $userId);
        } catch (\RuntimeException $e) {
            return back()->with('bid_error', $e->getMessage());
        }

        if (($result['already_joined'] ?? false) === true) {
            return back()->with('bid_success', 'You have already joined this auction.');
        }

        return back()->with('bid_success', 'Auction joined. EMD locked: ' . $this->formatInr((float) $result['locked_emd_amount']));
    }

    public function auctionStatus(Request $request, int $auctionId)
    {
        $userId = (int) $request->session()->get('user_id');
        try {
            $status = $this->emdAuctionService->getAuctionStatus($auctionId, $userId);
            return response()->json(['success' => true, 'data' => $status]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function completePayment(Request $request, int $auctionId)
    {
        $userId = (int) $request->session()->get('user_id');
        try {
            $this->emdAuctionService->completeWinnerPayment($auctionId, $userId);
            return back()->with('payment', 'success');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function releaseEmd(Request $request, int $auctionId)
    {
        $userId = (int) $request->session()->get('user_id');
        try {
            $result = $this->emdAuctionService->releaseUserEmdIfEligible($auctionId, $userId);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function toggleWatchlist(Request $request, int $auctionId)
    {
        if (! Schema::hasTable('watchlists')) {
            return back()->with('bid_error', 'Watchlist feature is not available yet. Please run migrations.');
        }

        $userId = (int) $request->session()->get('user_id');
        $exists = DB::table('watchlists')->where('user_id', $userId)->where('auction_id', $auctionId)->exists();

        if ($exists) {
            DB::table('watchlists')->where('user_id', $userId)->where('auction_id', $auctionId)->delete();
            return back()->with('bid_success', 'Removed from watchlist.');
        }

        DB::table('watchlists')->insert([
            'user_id' => $userId,
            'auction_id' => $auctionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('bid_success', 'Added to watchlist.');
    }

    public function myBids(Request $request)
    {
        $this->updateAuctionStatuses();
        $userId = (int) $request->session()->get('user_id');
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $perPage = $this->resolvePerPage($request, 20);
        $myBidsQuery = DB::table('auctions as a')
            ->join('bids as b', 'a.id', '=', 'b.auction_id')
            ->where('b.user_id', $userId)
            ->when($status !== 'all', fn ($q) => $q->where('a.status', $status))
            ->when($search !== '', fn ($q) => $q->where('a.title', 'like', '%' . $search . '%'))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('a.end_datetime', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('a.end_datetime', '<=', $dateTo))
            ->selectRaw("DISTINCT a.id, a.title, a.status, a.end_datetime,
                (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as highest_bid,
                (SELECT amount FROM bids WHERE auction_id = a.id AND user_id = {$userId} ORDER BY amount DESC LIMIT 1) as my_highest_bid,
                (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as total_bids")
            ->orderByDesc('a.end_datetime');
        $myBids = $this->paginateQuery($myBidsQuery, $perPage);

        return view('user.my-bids', [
            'myBids' => $myBids,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => (string) $request->query('per_page', (string) $perPage),
            ],
        ]);
    }

    public function wonAuctions(Request $request)
    {
        $this->updateAuctionStatuses();
        $userId = (int) $request->session()->get('user_id');
        $payment = (string) $request->query('payment', 'all');
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $perPage = $this->resolvePerPage($request, 20);
        $wonAuctionsQuery = DB::table('auctions')
            ->where('winner_user_id', $userId)
            ->whereIn('status', ['closed', 'completed', 'failed'])
            ->when($payment !== 'all', fn ($q) => $q->where('payment_status', $payment))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%' . $search . '%'))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('end_datetime', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('end_datetime', '<=', $dateTo))
            ->orderByDesc('end_datetime');
        $wonAuctions = $this->paginateQuery($wonAuctionsQuery, $perPage);

        return view('user.won-auctions', [
            'wonAuctions' => $wonAuctions,
            'filters' => [
                'payment' => $payment,
                'status' => $status,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => (string) $request->query('per_page', (string) $perPage),
            ],
        ]);
    }

    public function notifications(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $threads = collect();
        $selectedThread = null;
        $selectedReplies = collect();
        $unreadCount = 0;

        if (Schema::hasTable('admin_messages') && Schema::hasTable('admin_message_recipients')) {
            $threads = DB::table('admin_message_recipients as r')
                ->join('admin_messages as m', 'm.id', '=', 'r.message_id')
                ->leftJoin('users as cu', 'cu.id', '=', 'm.created_by')
                ->where('r.user_id', $userId)
                ->orderByDesc('m.created_at')
                ->get([
                    'm.id',
                    'm.subject',
                    'm.message',
                    'm.attachment_path',
                    'm.created_at',
                    'm.created_by',
                    'cu.name as created_by_name',
                    'cu.role as created_by_role',
                    'r.is_read',
                    'r.last_read_at',
                ]);

            $unreadCount = DB::table('admin_message_recipients')
                ->where('user_id', $userId)
                ->where('is_read', 0)
                ->count();

            if ($request->query('mark_read')) {
                DB::table('admin_message_recipients')
                    ->where('user_id', $userId)
                    ->update(['is_read' => 1, 'last_read_at' => now(), 'updated_at' => now()]);
                return redirect()->route('user.notifications');
            }

            $selectedThreadId = (int) $request->query('thread', 0);
            if ($selectedThreadId > 0) {
                $selectedThread = $threads->firstWhere('id', $selectedThreadId);
                if ($selectedThread) {
                    DB::table('admin_message_recipients')
                        ->where('message_id', $selectedThreadId)
                        ->where('user_id', $userId)
                        ->update(['is_read' => 1, 'last_read_at' => now(), 'updated_at' => now()]);

                    $selectedReplies = Schema::hasTable('admin_message_replies')
                        ? DB::table('admin_message_replies')
                            ->where('message_id', $selectedThreadId)
                            ->orderBy('created_at')
                            ->get()
                        : collect();
                }
            }
        }

        return view('user.notifications', compact('threads', 'selectedThread', 'selectedReplies', 'unreadCount'));
    }

    public function composeAdminMessage(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        if (! Schema::hasTable('admin_messages') || ! Schema::hasTable('admin_message_recipients')) {
            return back()->withErrors(['message' => 'Messaging feature is not ready.']);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('user-queries', 'public');
        }

        $messageId = DB::table('admin_messages')->insertGetId([
            'subject' => trim((string) ($request->input('subject') ?: 'User Query')),
            'message' => (string) $request->input('message'),
            'attachment_path' => $attachmentPath,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_message_recipients')->insert([
            'message_id' => $messageId,
            'user_id' => $userId,
            'email_sent_at' => null,
            'is_read' => 1,
            'last_read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('user.notifications', ['thread' => $messageId])->with('success', 'Message sent to admin.');
    }

    public function replyToAdminMessage(Request $request, int $id)
    {
        $userId = (int) $request->session()->get('user_id');
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        if (! Schema::hasTable('admin_messages') || ! Schema::hasTable('admin_message_recipients') || ! Schema::hasTable('admin_message_replies')) {
            return back()->withErrors(['reply' => 'Messaging feature is not ready.']);
        }

        $recipient = DB::table('admin_message_recipients')
            ->where('message_id', $id)
            ->where('user_id', $userId)
            ->first();
        if (! $recipient) {
            return back()->withErrors(['reply' => 'Message thread not found.']);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('user-message-replies', 'public');
        }

        DB::table('admin_message_replies')->insert([
            'message_id' => $id,
            'sender_role' => 'user',
            'sender_user_id' => $userId,
            'message' => (string) $request->input('message'),
            'attachment_path' => $attachmentPath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_message_recipients')
            ->where('message_id', $id)
            ->where('user_id', $userId)
            ->update(['is_read' => 1, 'last_read_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Reply sent to admin.');
    }

    public function profile(Request $request)
    {
        $this->updateAuctionStatuses();
        $userId = (int) $request->session()->get('user_id');

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'current_password' => ['required'],
                'new_password' => ['required', 'min:8', 'same:confirm_password'],
                'confirm_password' => ['required'],
            ]);

            $userPwd = DB::table('users')->where('id', $userId)->value('password');
            if (! Hash::check($validated['current_password'], (string) $userPwd)) {
                return back()->with('error', 'Current password is incorrect.');
            }

            DB::table('users')->where('id', $userId)->update(['password' => Hash::make($validated['new_password'])]);
            return back()->with('success', 'Password updated successfully!');
        }

        $user = DB::selectOne(
            "SELECT u.*, r.registration_id, r.registration_type, r.pan_card_number, r.mobile as reg_mobile, r.date_of_birth, r.payment_status, r.payment_date, r.payment_amount, r.payment_transaction_id
            FROM users u LEFT JOIN registration r ON u.email = r.email WHERE u.id = ?",
            [$userId]
        );
        if (! $user) {
            return redirect()->route('user.auctions.index');
        }

        $stats = [
            'total_bids' => DB::table('bids')->where('user_id', $userId)->count(),
            'won_auctions' => DB::table('auctions')->where('winner_user_id', $userId)->whereIn('status', ['closed', 'completed'])->count(),
            'auctions_bid_on' => DB::table('bids')->where('user_id', $userId)->distinct('auction_id')->count('auction_id'),
        ];
        return view('user.profile', ['user' => $user, 'stats' => $stats]);
    }

    private function getCurrentHighestBid(int $auctionId): float
    {
        return (float) (DB::table('bids')
            ->where('auction_id', $auctionId)
            ->max('amount') ?? 0);
    }

    private function formatInr(float $amount): string
    {
        return '₹' . number_format($amount, 2);
    }

    private function updateAuctionStatuses(): void
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('auctions')
            ->where('status', 'upcoming')
            ->where('start_datetime', '<=', $now)
            ->update(['status' => 'active']);

        $expired = DB::table('auctions')
            ->where('status', 'active')
            ->where('end_datetime', '<=', $now)
            ->pluck('id');

        foreach ($expired as $auctionId) {
            $topBidders = DB::table('bids')
                ->where('auction_id', $auctionId)
                ->selectRaw('user_id, MAX(amount) as amount')
                ->groupBy('user_id')
                ->orderByDesc('amount')
                ->limit(3)
                ->get();

            if ($topBidders->isNotEmpty()) {
                $highestBid = $topBidders->first();
                DB::table('auctions')
                    ->where('id', $auctionId)
                    ->update([
                        'status' => 'closed',
                        'winner_user_id' => $highestBid->user_id,
                        'winner_rank' => 1,
                        'final_price' => $highestBid->amount,
                        'payment_status' => 'pending',
                        'payment_window_expires_at' => now()->addHours(
                            $this->settingsService->getInt('emd_payment_window_hours', (int) config('emd.payment_window_hours', 24))
                        ),
                        'top_bidders_json' => json_encode($topBidders->values()->all()),
                    ]);
            } else {
                DB::table('auctions')
                    ->where('id', $auctionId)
                    ->update(['status' => 'failed', 'payment_status' => 'failed']);
            }
        }
    }

    private function applyAntiSniping(int $auctionId): void
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

    private function resolvePerPage(Request $request, int $default = 20): int|string
    {
        $raw = strtolower((string) $request->query('per_page', (string) $default));
        if ($raw === 'all') {
            return 'all';
        }
        $value = (int) $raw;
        return in_array($value, [10, 20, 50, 100], true) ? $value : $default;
    }

    private function paginateQuery($query, int|string $perPage): LengthAwarePaginator
    {
        if ($perPage === 'all') {
            $total = (clone $query)->count();
            $perPage = max(1, $total);
        }
        return $query->paginate((int) $perPage)->withQueryString();
    }
}
