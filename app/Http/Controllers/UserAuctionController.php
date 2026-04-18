<?php

namespace App\Http\Controllers;

use App\Services\BulkSmsService;
use App\Services\BlacklistService;
use App\Services\EmdAuctionService;
use App\Services\AppSettingsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class UserAuctionController extends Controller
{
    public function __construct(
        private readonly EmdAuctionService $emdAuctionService,
        private readonly AppSettingsService $settingsService,
        private readonly BlacklistService $blacklistService,
        private readonly BulkSmsService $bulkSmsService,
    ) {
    }

    public function index(Request $request)
    {
        $this->updateAuctionStatuses();
        $userId = (int) $request->session()->get('user_id');
        $watchlistEnabled = Schema::hasTable('watchlists');
        $sort = (string) $request->query('sort', 'ending_soon');
        $search = trim((string) $request->query('search', ''));
        $view = (string) $request->query('view', 'all');
        if (! in_array($view, ['all', 'live', 'bidding', 'watchlist', 'won'], true)) {
            $view = 'all';
        }
        // [EMD DISABLED] $emdFilter = (string) $request->query('emd', 'all');
        $emdFilter = 'all';
        $perPage = $this->resolvePerPage($request, 12);

        $watchSelect = $watchlistEnabled
            ? "(SELECT COUNT(*) FROM watchlists w WHERE w.auction_id = a.id AND w.user_id = ?) as watchlisted_count"
            : "0 as watchlisted_count";

        $now = now();

        if ($view === 'won') {
            $query = DB::table('auctions as a')
                ->selectRaw('a.*, (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as current_bid, (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count, 0 as watchlisted_count')
                ->where('a.winner_user_id', $userId)
                ->whereIn('a.status', ['closed', 'completed'])
                ->when($search !== '', fn ($q) => $q->where('a.title', 'like', '%' . $search . '%'));
        } elseif ($view === 'watchlist' && $watchlistEnabled) {
            $query = DB::table('watchlists as w')
                ->join('auctions as a', 'a.id', '=', 'w.auction_id')
                ->selectRaw('a.*, (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as current_bid, (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count, 1 as watchlisted_count')
                ->where('w.user_id', $userId)
                ->where('a.status', 'active')
                ->when($search !== '', fn ($q) => $q->where('a.title', 'like', '%' . $search . '%'));
        } elseif ($view === 'live') {
            $query = DB::table('auctions as a')
                ->selectRaw("a.*, (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as current_bid, (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count, {$watchSelect}", $watchlistEnabled ? [$userId] : [])
                ->where('a.status', 'active')
                ->where('a.end_datetime', '>', $now)
                ->where('a.end_datetime', '<=', $now->copy()->addDays(7))
                ->when($search !== '', fn ($q) => $q->where('a.title', 'like', '%' . $search . '%'));
        } elseif ($view === 'bidding') {
            $query = DB::table('auctions as a')
                ->selectRaw("a.*, (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as current_bid, (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count, {$watchSelect}", $watchlistEnabled ? [$userId] : [])
                ->where('a.status', 'active')
                ->where('a.end_datetime', '>', $now)
                ->whereExists(function ($q) use ($userId): void {
                    $q->select(DB::raw(1))
                        ->from('bids as b')
                        ->whereColumn('b.auction_id', 'a.id')
                        ->where('b.user_id', $userId);
                })
                ->when($search !== '', fn ($q) => $q->where('a.title', 'like', '%' . $search . '%'));
        } elseif ($view === 'watchlist' && ! $watchlistEnabled) {
            $query = DB::table('auctions as a')
                ->selectRaw('a.*, 0 as current_bid, 0 as bid_count, 0 as watchlisted_count')
                ->whereRaw('0 = 1');
        } else {
            $query = DB::table('auctions as a')
                ->selectRaw("a.*, (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as current_bid, (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count, {$watchSelect}", $watchlistEnabled ? [$userId] : [])
                ->where('a.status', 'active')
                ->when($search !== '', fn ($q) => $q->where('a.title', 'like', '%' . $search . '%'));
        }

        if ($view === 'won') {
            if ($sort === 'highest_bid') {
                $query->orderByDesc('a.final_price');
            } elseif ($sort === 'newest') {
                $query->orderByDesc('a.id');
            } else {
                $query->orderByDesc('a.end_datetime');
            }
        } elseif ($sort === 'highest_bid') {
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

        $tabCountAll = (int) DB::table('auctions')->where('status', 'active')->count();
        $tabCountLive = (int) DB::table('auctions')
            ->where('status', 'active')
            ->where('end_datetime', '>', $now)
            ->where('end_datetime', '<=', $now->copy()->addDays(7))
            ->count();
        $tabCountWatchlist = $watchlistEnabled
            ? (int) DB::table('watchlists as w')
                ->join('auctions as a', 'a.id', '=', 'w.auction_id')
                ->where('w.user_id', $userId)
                ->where('a.status', 'active')
                ->count()
            : 0;
        $tabCountWon = (int) DB::table('auctions')
            ->where('winner_user_id', $userId)
            ->whereIn('status', ['closed', 'completed'])
            ->count();
        $tabCountBidding = (int) DB::table('auctions as a')
            ->where('a.status', 'active')
            ->where('a.end_datetime', '>', $now)
            ->whereExists(function ($q) use ($userId): void {
                $q->select(DB::raw(1))
                    ->from('bids as b')
                    ->whereColumn('b.auction_id', 'a.id')
                    ->where('b.user_id', $userId);
            })
            ->count();

        return view('user.auctions.index', [
            'auctions' => $auctions,
            'view' => $view,
            'sort' => $sort,
            'search' => $search,
            'emdFilter' => $emdFilter, // [EMD DISABLED] always 'all'
            'perPage' => (string) $request->query('per_page', (string) $perPage),
            'recentlyViewed' => $recentlyViewed,
            'watchlistAuctions' => $watchlistAuctions,
            'tabCounts' => [
                'all' => $tabCountAll,
                'live' => $tabCountLive,
                'bidding' => $tabCountBidding,
                'watchlist' => $tabCountWatchlist,
                'won' => $tabCountWon,
            ],
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
            ->get(['b.amount', 'b.created_at', 'u.name', 'b.user_id']);

        $userId = (int) $request->session()->get('user_id');
        $myBidHistory = DB::table('bids')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get(['amount', 'created_at']);
        $recentlyViewed = array_values(array_unique(array_merge([$auctionId], (array) $request->session()->get('recently_viewed_auctions', []))));
        $request->session()->put('recently_viewed_auctions', array_slice($recentlyViewed, 0, 20));

        $userHasBid = DB::table('bids')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->exists();
        $requiredEmd = $this->resolveParticipationFee($auction);
        $walletBalance = 0.0;
        $isParticipant = ! Schema::hasTable('auction_participants') || DB::table('auction_participants')
            ->where('auction_id', $auctionId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
        $walletShortfall = 0.0;
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
            'myBidHistory' => $myBidHistory,
            'userHasBid' => $userHasBid,
            'isParticipant' => $isParticipant,
            'walletBalance' => $walletBalance,
            'walletShortfall' => $walletShortfall,
            'requiredEmd' => $requiredEmd,
            'isWatchlisted' => $isWatchlisted,
            'myHighestBid' => $myHighestBid,
            'viewerUserId' => $userId,
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
        $userId = (int) $request->session()->get('user_id');

        // [EMD DISABLED] Bypass EmdAuctionService (which requires EMD lock). Bid directly.
        try {
            DB::transaction(function () use ($auctionId, $userId, $bidAmount): void {
                $auction = DB::table('auctions')->where('id', $auctionId)->lockForUpdate()->first();
                if (! $auction || $auction->status !== 'active') {
                    throw new \RuntimeException('This auction is not active.');
                }

                if (Schema::hasTable('auction_participants')) {
                    $isParticipant = DB::table('auction_participants')
                        ->where('auction_id', $auctionId)
                        ->where('user_id', $userId)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->exists();
                    if (! $isParticipant) {
                        throw new \RuntimeException('Please pay participation fee before bidding.');
                    }
                }

                $currentBid = (float) (DB::table('bids')->where('auction_id', $auctionId)->max('amount') ?? 0);
                $baseLine = $currentBid > 0 ? $currentBid : (float) $auction->base_price;
                $minNextBid = $baseLine + (float) $auction->min_increment;

                if ($bidAmount < $minNextBid) {
                    throw new \RuntimeException('Bid must be at least ₹' . number_format($minNextBid, 2) . '.');
                }

                DB::table('bids')->insert([
                    'auction_id' => $auctionId,
                    'user_id'    => $userId,
                    'amount'     => $bidAmount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Keep top_bidders_json up to date for status polling
                $top = DB::table('bids')
                    ->where('auction_id', $auctionId)
                    ->selectRaw('user_id, MAX(amount) as amount')
                    ->groupBy('user_id')
                    ->orderByDesc('amount')
                    ->limit(3)
                    ->get()
                    ->map(fn ($r) => ['user_id' => $r->user_id, 'amount' => $r->amount,
                                     'bidder_name' => DB::table('users')->where('id', $r->user_id)->value('name') ?? 'Unknown'])
                    ->all();
                DB::table('auctions')->where('id', $auctionId)->update([
                    'top_bidders_json' => json_encode($top, JSON_THROW_ON_ERROR),
                    'updated_at'       => now(),
                ]);

                if (Schema::hasTable('bid_logs')) {
                    DB::table('bid_logs')->insert([
                        'auction_id' => $auctionId,
                        'user_id' => $userId,
                        'amount' => $bidAmount,
                        'event_type' => 'placed',
                        'meta' => json_encode(['source' => 'user_bid'], JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                    ]);
                }
            });
            $this->applyAntiSniping($auctionId);
        } catch (\RuntimeException $e) {
            if (Schema::hasTable('bid_logs')) {
                DB::table('bid_logs')->insert([
                    'auction_id' => $auctionId,
                    'user_id' => $userId,
                    'amount' => $bidAmount,
                    'event_type' => 'rejected',
                    'meta' => json_encode(['reason' => $e->getMessage()], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
            return back()->with('bid_error', $e->getMessage());
        }

        return back()->with('bid_success', 'Bid placed successfully for ' . $this->formatInr($bidAmount) . '!');
    }

    public function joinAuction(Request $request, int $auctionId)
    {
        if (! Schema::hasTable('auction_participants')) {
            return back()->with('bid_error', 'Participation feature is not available yet.');
        }
        return redirect()->route('payments.auction.participation.initiate', ['auctionId' => $auctionId]);
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

    // [EMD DISABLED] releaseEmd — no EMD is locked, route is commented out
    public function releaseEmd(Request $request, int $auctionId)
    {
        return response()->json(['success' => false, 'message' => 'EMD feature is currently disabled.'], 422);
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

    public function notifications(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $threads = collect();
        $unreadCount = 0;
        $notificationGroups = [];
        $totalMessages = 0;
        $filter = (string) $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'unread', 'auctions', 'payments', 'system'], true)) {
            $filter = 'all';
        }
        $searchQ = trim((string) $request->query('q', ''));

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
            $totalMessages = $threads->count();

            $unreadCount = DB::table('admin_message_recipients')
                ->where('user_id', $userId)
                ->where('is_read', 0)
                ->count();

            if ($request->query('mark_read')) {
                DB::table('admin_message_recipients')
                    ->where('user_id', $userId)
                    ->update(['is_read' => 1, 'last_read_at' => now(), 'updated_at' => now()]);

                return redirect()->route('user.notifications', $request->except('mark_read'));
            }

            $threads->transform(function ($t) {
                $t->notif_kind = $this->inferNotificationKind($t);

                return $t;
            });

            $threads = $threads->filter(function ($t) use ($filter, $searchQ) {
                $combined = strtolower((string) ($t->subject ?? '') . ' ' . ($t->message ?? ''));
                if ($searchQ !== '' && ! str_contains($combined, strtolower($searchQ))) {
                    return false;
                }
                $kind = $t->notif_kind ?? 'system';

                return match ($filter) {
                    'unread' => (int) $t->is_read === 0,
                    'auctions' => in_array($kind, ['auction', 'outbid', 'winning'], true),
                    'payments' => $kind === 'payment',
                    'system' => $kind === 'system',
                    default => true,
                };
            })->values();

            $notificationGroups = $this->groupNotificationsByDay($threads);
        }

        return view('user.notifications', compact(
            'unreadCount',
            'notificationGroups',
            'filter',
            'searchQ',
            'totalMessages'
        ));
    }

    public function notificationShow(Request $request, int $id)
    {
        $userId = (int) $request->session()->get('user_id');

        if (! Schema::hasTable('admin_messages') || ! Schema::hasTable('admin_message_recipients')) {
            return redirect()->route('user.notifications')->withErrors(['message' => 'Messaging feature is not ready.']);
        }

        $hasAccess = DB::table('admin_message_recipients')
            ->where('message_id', $id)
            ->where('user_id', $userId)
            ->exists();
        if (! $hasAccess) {
            abort(404);
        }

        $selectedThread = DB::table('admin_messages as m')
            ->leftJoin('users as cu', 'cu.id', '=', 'm.created_by')
            ->where('m.id', $id)
            ->select([
                'm.id',
                'm.subject',
                'm.message',
                'm.attachment_path',
                'm.created_at',
                'm.created_by',
                'cu.name as created_by_name',
                'cu.role as created_by_role',
            ])
            ->first();

        if (! $selectedThread) {
            abort(404);
        }

        DB::table('admin_message_recipients')
            ->where('message_id', $id)
            ->where('user_id', $userId)
            ->update(['is_read' => 1, 'last_read_at' => now(), 'updated_at' => now()]);

        $selectedReplies = Schema::hasTable('admin_message_replies')
            ? DB::table('admin_message_replies')
                ->where('message_id', $id)
                ->orderBy('created_at')
                ->get()
            : collect();

        return view('user.notifications.show', [
            'selectedThread' => $selectedThread,
            'selectedReplies' => $selectedReplies,
        ]);
    }

    public function notificationComposeForm()
    {
        return view('user.notifications.compose');
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

        return redirect()->route('user.notifications.show', $messageId)->with('success', 'Message sent to admin.');
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

        return redirect()->route('user.notifications.show', $id)->with('success', 'Reply sent to admin.');
    }

    public function profile(Request $request)
    {
        $this->updateAuctionStatuses();
        $userId = (int) $request->session()->get('user_id');

        $user = DB::selectOne(
            "SELECT u.*, r.registration_id, r.registration_type, r.pan_card_number, r.mobile as reg_mobile, r.date_of_birth, r.payment_status, r.payment_date, r.payment_amount, r.payment_transaction_id
            FROM users u LEFT JOIN registration r ON u.email = r.email WHERE u.id = ?",
            [$userId]
        );
        if (! $user) {
            return redirect()->route('user.auctions.index');
        }

        $this->syncProfileUiSectionWithPendingState($request, $userId);

        return view('user.profile', ['user' => $user]);
    }

    public function profilePasswordSendOtp(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        $userPwd = DB::table('users')->where('id', $userId)->value('password');
        if (! Hash::check($validated['current_password'], (string) $userPwd)) {
            $this->setProfileUiSection($request, 'password');

            return back()->with('error', 'Current password is incorrect.')->withInput();
        }

        $user = DB::table('users')->where('id', $userId)->first();
        if (! $user || empty($user->email)) {
            $this->setProfileUiSection($request, 'password');

            return back()->with('error', 'No email on file for OTP.');
        }

        $otp = (string) random_int(100000, 999999);
        $request->session()->put('profile_pwd_otp_' . $userId, $otp);
        $request->session()->put('profile_pwd_otp_time_' . $userId, time());

        $this->sendProfileOtpEmail((string) $user->email, $otp, 'password change');

        $this->setProfileUiSection($request, 'password');

        return back()->with('success', 'A verification code was sent to your registered email.');
    }

    public function profilePasswordUpdate(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
            'new_password' => ['required', 'string', 'min:8', 'same:confirm_password'],
            'confirm_password' => ['required', 'string'],
        ]);

        $storedOtp = (string) $request->session()->get('profile_pwd_otp_' . $userId, '');
        $otpTime = (int) $request->session()->get('profile_pwd_otp_time_' . $userId, 0);
        if (! $storedOtp || $storedOtp !== $validated['otp'] || (time() - $otpTime) > 600) {
            $this->setProfileUiSection($request, 'password');

            return back()->with('error', 'Invalid or expired verification code.')->withInput();
        }

        DB::table('users')->where('id', $userId)->update([
            'password' => Hash::make($validated['new_password']),
            'updated_at' => now(),
        ]);

        $request->session()->forget(['profile_pwd_otp_' . $userId, 'profile_pwd_otp_time_' . $userId]);
        $this->clearProfileUiSection($request);
        $this->logUserIdentityChange($userId, 'password', '[redacted]', '[password_changed]', $request);

        return back()->with('success', 'Password updated successfully.');
    }

    public function profileEmailSendOtp(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $validated = $request->validate([
            'new_email' => ['required', 'email'],
        ]);
        $newEmail = strtolower(trim($validated['new_email']));
        $user = DB::table('users')->where('id', $userId)->first();
        if (! $user) {
            $this->setProfileUiSection($request, 'email');

            return back()->with('error', 'User not found.');
        }
        $oldEmail = strtolower(trim((string) $user->email));
        if ($newEmail === $oldEmail) {
            $this->setProfileUiSection($request, 'email');

            return back()->with('error', 'That is already your email address.')->withInput();
        }

        if ($this->blacklistService->isIdentityBlocked([
            'email' => $newEmail,
            'ip_address' => $request->ip(),
            'device_fingerprint' => $this->blacklistService->getFingerprint($request),
        ])) {
            $this->setProfileUiSection($request, 'email');

            return back()->with('error', 'This email cannot be used.')->withInput();
        }

        if (! $this->emailAvailableForProfile($newEmail, $userId)) {
            $this->setProfileUiSection($request, 'email');

            return back()->with('error', 'This email is already registered.')->withInput();
        }

        $otp = (string) random_int(100000, 999999);
        $request->session()->put('profile_email_otp_' . md5($newEmail), $otp);
        $request->session()->put('profile_email_otp_time_' . md5($newEmail), time());
        $request->session()->put('profile_email_pending_' . $userId, $newEmail);

        $this->sendProfileOtpEmail($newEmail, $otp, 'email change');

        $this->setProfileUiSection($request, 'email');

        return back()->with('success', 'A verification code was sent to the new email address.')->withInput();
    }

    public function profileEmailUpdate(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $pending = strtolower(trim((string) $request->session()->get('profile_email_pending_' . $userId, '')));
        if ($pending === '' || ! filter_var($pending, FILTER_VALIDATE_EMAIL)) {
            $this->setProfileUiSection($request, 'email');

            return back()->with('error', 'No pending email change. Request a new code first.');
        }

        $storedOtp = (string) $request->session()->get('profile_email_otp_' . md5($pending), '');
        $otpTime = (int) $request->session()->get('profile_email_otp_time_' . md5($pending), 0);
        if (! $storedOtp || $storedOtp !== $validated['otp'] || (time() - $otpTime) > 600) {
            $this->setProfileUiSection($request, 'email');

            return back()->with('error', 'Invalid or expired verification code.')->withInput();
        }

        $user = DB::table('users')->where('id', $userId)->first();
        if (! $user) {
            $this->setProfileUiSection($request, 'email');

            return back()->with('error', 'User not found.');
        }
        $oldEmail = strtolower(trim((string) $user->email));

        if (! $this->emailAvailableForProfile($pending, $userId)) {
            $this->setProfileUiSection($request, 'email');

            return back()->with('error', 'This email is no longer available.');
        }

        DB::table('users')->where('id', $userId)->update([
            'email' => $pending,
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('registration')) {
            DB::table('registration')->where('email', $oldEmail)->update([
                'email' => $pending,
                'updated_at' => now(),
            ]);
        }

        $this->logUserIdentityChange($userId, 'email', $oldEmail, $pending, $request);
        $request->session()->put('email', $pending);
        $request->session()->forget([
            'profile_email_otp_' . md5($pending),
            'profile_email_otp_time_' . md5($pending),
            'profile_email_pending_' . $userId,
        ]);
        $this->clearProfileUiSection($request);

        return back()->with('success', 'Email address updated successfully.');
    }

    public function profileMobileSendOtp(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $validated = $request->validate([
            'new_mobile' => ['required', 'digits:10'],
        ]);
        $newMobile = $this->normalizeMobileDigits((string) $validated['new_mobile']);

        $user = DB::table('users')->where('id', $userId)->first();
        if (! $user) {
            $this->setProfileUiSection($request, 'mobile');

            return back()->with('error', 'User not found.');
        }

        $oldMobile = null;
        if (Schema::hasColumn('users', 'mobile') && ! empty($user->mobile)) {
            $oldMobile = $this->normalizeMobileDigits((string) $user->mobile);
        }
        $reg = Schema::hasTable('registration')
            ? DB::table('registration')->where('email', $user->email)->first()
            : null;
        if ($oldMobile === null && $reg && ! empty($reg->mobile)) {
            $oldMobile = $this->normalizeMobileDigits((string) $reg->mobile);
        }

        if ($newMobile === $oldMobile) {
            $this->setProfileUiSection($request, 'mobile');

            return back()->with('error', 'That is already your mobile number.')->withInput();
        }

        if ($this->blacklistService->isIdentityBlocked([
            'mobile' => $newMobile,
            'ip_address' => $request->ip(),
            'device_fingerprint' => $this->blacklistService->getFingerprint($request),
        ])) {
            $this->setProfileUiSection($request, 'mobile');

            return back()->with('error', 'This mobile number cannot be used.')->withInput();
        }

        if (! $this->mobileAvailableForProfile($newMobile, $userId, $oldMobile)) {
            $this->setProfileUiSection($request, 'mobile');

            return back()->with('error', 'This mobile number is already registered.')->withInput();
        }

        $otp = (string) random_int(100000, 999999);
        $request->session()->put('profile_mobile_otp_' . md5($newMobile), $otp);
        $request->session()->put('profile_mobile_otp_time_' . md5($newMobile), time());
        $request->session()->put('profile_mobile_pending_' . $userId, $newMobile);

        if (config('sms.enabled')) {
            if (! $this->bulkSmsService->sendOtp($newMobile, $otp)) {
                Log::warning('Profile mobile OTP: SMS send failed (see BulkSms logs above for HTTP/config details)', [
                    'user_id' => $userId,
                    'mobile_last4' => substr($newMobile, -4),
                ]);
                $this->setProfileUiSection($request, 'mobile');

                return back()->with('error', 'Could not send SMS. Please try again later.')->withInput();
            }

            $this->setProfileUiSection($request, 'mobile');

            return back()->with('success', 'Verification code sent to your mobile.')->withInput();
        }

        $msg = 'SMS gateway is disabled. ';
        if (config('app.debug')) {
            $msg .= 'Dev OTP: ' . $otp;
        } else {
            $msg .= 'Configure SMS_BULK_* in .env to send real SMS.';
        }

        $this->setProfileUiSection($request, 'mobile');

        return back()->with('success', $msg)->withInput();
    }

    public function profileMobileUpdate(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $pending = (string) $request->session()->get('profile_mobile_pending_' . $userId, '');
        $pending = $this->normalizeMobileDigits($pending);
        if (strlen($pending) !== 10) {
            $this->setProfileUiSection($request, 'mobile');

            return back()->with('error', 'No pending mobile change. Request a new code first.');
        }

        $storedOtp = (string) $request->session()->get('profile_mobile_otp_' . md5($pending), '');
        $otpTime = (int) $request->session()->get('profile_mobile_otp_time_' . md5($pending), 0);
        if (! $storedOtp || $storedOtp !== $validated['otp'] || (time() - $otpTime) > 600) {
            $this->setProfileUiSection($request, 'mobile');

            return back()->with('error', 'Invalid or expired verification code.')->withInput();
        }

        $user = DB::table('users')->where('id', $userId)->first();
        if (! $user) {
            $this->setProfileUiSection($request, 'mobile');

            return back()->with('error', 'User not found.');
        }

        $oldMobile = null;
        if (Schema::hasColumn('users', 'mobile') && ! empty($user->mobile)) {
            $oldMobile = $this->normalizeMobileDigits((string) $user->mobile);
        }
        $reg = Schema::hasTable('registration')
            ? DB::table('registration')->where('email', $user->email)->first()
            : null;
        if ($oldMobile === null && $reg && ! empty($reg->mobile)) {
            $oldMobile = $this->normalizeMobileDigits((string) $reg->mobile);
        }

        if (! $this->mobileAvailableForProfile($pending, $userId, $oldMobile)) {
            $this->setProfileUiSection($request, 'mobile');

            return back()->with('error', 'This mobile number is no longer available.');
        }

        if (Schema::hasColumn('users', 'mobile')) {
            DB::table('users')->where('id', $userId)->update([
                'mobile' => $pending,
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('registration')) {
            DB::table('registration')->where('email', $user->email)->update([
                'mobile' => $pending,
                'updated_at' => now(),
            ]);
        }

        $this->logUserIdentityChange($userId, 'mobile', $oldMobile ?? '', $pending, $request);
        $request->session()->forget([
            'profile_mobile_otp_' . md5($pending),
            'profile_mobile_otp_time_' . md5($pending),
            'profile_mobile_pending_' . $userId,
        ]);
        $this->clearProfileUiSection($request);

        return back()->with('success', 'Mobile number updated successfully.');
    }

    private function setProfileUiSection(Request $request, string $section): void
    {
        $request->session()->put('profile_ui_section', $section);
    }

    private function clearProfileUiSection(Request $request): void
    {
        $request->session()->forget('profile_ui_section');
    }

    /**
     * Collapse profile accordion when stored UI section no longer matches session (e.g. stale pending mobile).
     */
    private function syncProfileUiSectionWithPendingState(Request $request, int $userId): void
    {
        $ui = $request->session()->get('profile_ui_section');
        if ($ui !== 'password' && $ui !== 'email' && $ui !== 'mobile') {
            if ($ui !== null) {
                $request->session()->forget('profile_ui_section');
            }

            return;
        }
        if ($ui === 'mobile') {
            $raw = $request->session()->get('profile_mobile_pending_' . $userId);
            $norm = preg_replace('/\D/', '', (string) $raw);
            if (strlen($norm) !== 10) {
                $request->session()->forget('profile_ui_section');
            }
        } elseif ($ui === 'email') {
            $e = $request->session()->get('profile_email_pending_' . $userId);
            if (! is_string($e) || ! filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $request->session()->forget('profile_ui_section');
            }
        } elseif ($ui === 'password') {
            if (! $request->session()->has('profile_pwd_otp_' . $userId)) {
                $request->session()->forget('profile_ui_section');
            }
        }
    }

    private function logUserIdentityChange(int $userId, string $field, ?string $old, ?string $new, Request $request): void
    {
        if (! Schema::hasTable('user_identity_change_logs')) {
            return;
        }
        DB::table('user_identity_change_logs')->insert([
            'user_id' => $userId,
            'field_name' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    private function emailAvailableForProfile(string $newEmail, int $userId): bool
    {
        if (DB::table('users')->where('email', $newEmail)->where('id', '!=', $userId)->exists()) {
            return false;
        }
        if (Schema::hasTable('registration') && DB::table('registration')->where('email', $newEmail)->exists()) {
            return false;
        }

        return true;
    }

    private function mobileAvailableForProfile(string $newMobile, int $userId, ?string $oldMobile): bool
    {
        if (Schema::hasColumn('users', 'mobile')) {
            if (DB::table('users')->where('mobile', $newMobile)->where('id', '!=', $userId)->exists()) {
                return false;
            }
        }
        if (Schema::hasTable('registration')) {
            $user = DB::table('users')->where('id', $userId)->first();
            if (! $user) {
                return false;
            }
            $conflict = DB::table('registration')
                ->where('mobile', $newMobile)
                ->where('email', '!=', $user->email)
                ->exists();
            if ($conflict) {
                return false;
            }
        }

        return true;
    }

    private function normalizeMobileDigits(string $mobile): string
    {
        return preg_replace('/[^0-9]/', '', trim($mobile)) ?? '';
    }

    private function sendProfileOtpEmail(string $email, string $otp, string $purpose): void
    {
        try {
            $this->applyActiveAdminEmailSettingsForProfileOtp();
            Mail::mailer('smtp')->raw("Your OTP for {$purpose} is: {$otp}\n\nThis code expires in 10 minutes.", static function ($message) use ($email, $purpose): void {
                $message->to($email)->subject('Profile verification OTP');
            });
        } catch (\Throwable) {
            // Mail failure ignored; dev may rely on flash for mobile OTP
        }
    }

    private function applyActiveAdminEmailSettingsForProfileOtp(): void
    {
        try {
            if (! Schema::hasTable('email_settings')) {
                return;
            }

            $settings = DB::table('email_settings')
                ->where('is_active', 1)
                ->latest('updated_at')
                ->first();

            if (! $settings) {
                return;
            }

            $encryption = strtolower(trim((string) ($settings->encryption ?? '')));
            $smtpScheme = match ($encryption) {
                'ssl' => 'smtps',
                'tls', '' => 'smtp',
                default => 'smtp',
            };

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => (string) ($settings->smtp_host ?? config('mail.mailers.smtp.host')),
                'mail.mailers.smtp.port' => (int) ($settings->smtp_port ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.username' => (string) ($settings->smtp_username ?? config('mail.mailers.smtp.username')),
                'mail.mailers.smtp.password' => (string) ($settings->smtp_password ?? config('mail.mailers.smtp.password')),
                'mail.mailers.smtp.scheme' => $smtpScheme,
                'mail.mailers.smtp.timeout' => 10,
                'mail.from.address' => (string) ($settings->from_email ?? config('mail.from.address')),
                'mail.from.name' => (string) ($settings->from_name ?? config('mail.from.name')),
            ]);

            Mail::purge('smtp');
        } catch (\Throwable) {
            // keep defaults
        }
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

        $expiredAuctions = DB::table('auctions')
            ->where('status', 'active')
            ->where('end_datetime', '<=', $now)
            ->get(['id', 'title']);

        foreach ($expiredAuctions as $auction) {
            $auctionId = (int) $auction->id;
            $topBidders = DB::table('bids')
                ->where('auction_id', $auctionId)
                ->selectRaw('user_id, MAX(amount) as amount')
                ->groupBy('user_id')
                ->orderByDesc('amount')
                ->limit(3)
                ->get();

            if ($topBidders->isNotEmpty()) {
                $highestBid = $topBidders->first();
                $paymentWindowHours = $this->settingsService->getInt('emd_payment_window_hours', (int) config('emd.payment_window_hours', 24));
                $paymentWindowExpires = now()->addHours($paymentWindowHours);
                DB::table('auctions')
                    ->where('id', $auctionId)
                    ->update([
                        'status' => 'closed',
                        'winner_user_id' => $highestBid->user_id,
                        'winner_rank' => 1,
                        'final_price' => $highestBid->amount,
                        'payment_status' => 'pending',
                        'payment_window_expires_at' => $paymentWindowExpires,
                        'top_bidders_json' => json_encode($topBidders->values()->all()),
                    ]);
                $this->sendWinnerNotificationEmail(
                    (int) $highestBid->user_id,
                    (string) $auction->title,
                    (float) $highestBid->amount,
                    $paymentWindowExpires->format('d-M-Y h:i A')
                );
            } else {
                DB::table('auctions')
                    ->where('id', $auctionId)
                    ->update(['status' => 'closed', 'auction_outcome' => 'failed']);
            }
        }

        // Auto-promote H2/H3 when H1's payment window has expired without payment
        $expiredPaymentWindows = DB::table('auctions')
            ->where('status', 'closed')
            ->where('payment_status', 'pending')
            ->where('payment_window_expires_at', '<=', $now)
            ->pluck('id');

        foreach ($expiredPaymentWindows as $auctionId) {
            try {
                $this->emdAuctionService->markTopBidderDefaultAndPromote((int) $auctionId);
                // Send notification to the newly promoted winner if auction is still active
                $updated = DB::table('auctions')->where('id', $auctionId)->first();
                if ($updated && $updated->status === 'closed' && $updated->winner_user_id) {
                    $deadline = $updated->payment_window_expires_at
                        ? \Carbon\Carbon::parse($updated->payment_window_expires_at)->format('d-M-Y h:i A')
                        : '-';
                    $this->sendWinnerNotificationEmail(
                        (int) $updated->winner_user_id,
                        (string) $updated->title,
                        (float) $updated->final_price,
                        $deadline
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to process payment window expiry.', [
                    'auction_id' => $auctionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendWinnerNotificationEmail(int $userId, string $auctionTitle, float $finalPrice, string $paymentDeadline): void
    {
        try {
            $user = DB::table('users')->where('id', $userId)->first();
            if (! $user || empty($user->email)) {
                return;
            }
            $this->applyAuctionEmailSettings();
            $siteUrl = rtrim((string) config('app.url', url('/')), '/');
            $body = "Dear {$user->name},\n\n"
                . "Congratulations! You have won the auction: {$auctionTitle}\n\n"
                . "Winning Amount: \u20b9" . number_format($finalPrice, 2) . "\n"
                . "Payment Deadline: {$paymentDeadline}\n\n"
                . "Please log in to the portal and go to \"Won Auctions\" to complete your payment before the deadline.\n"
                . "Failing to pay within the deadline will be recorded as a default on your account.\n\n"
                . "Login here: {$siteUrl}/login\n\n"
                . "Regards,\nAuction Portal Team";
            Mail::mailer('smtp')->raw($body, static function ($message) use ($user, $auctionTitle): void {
                $message->to((string) $user->email)
                    ->subject('Congratulations! You have won the auction \u2013 ' . $auctionTitle);
            });
        } catch (\Throwable $e) {
            Log::warning('Winner notification email failed.', [
                'user_id' => $userId,
                'auction' => $auctionTitle,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function applyAuctionEmailSettings(): void
    {
        try {
            if (! Schema::hasTable('email_settings')) {
                return;
            }
            $settings = DB::table('email_settings')->where('is_active', 1)->latest('updated_at')->first();
            if (! $settings) {
                return;
            }
            $encryption = strtolower(trim((string) ($settings->encryption ?? '')));
            $smtpScheme = match ($encryption) {
                'ssl' => 'smtps',
                'tls', '' => 'smtp',
                default => 'smtp',
            };
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => (string) ($settings->smtp_host ?? config('mail.mailers.smtp.host')),
                'mail.mailers.smtp.port' => (int) ($settings->smtp_port ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.username' => (string) ($settings->smtp_username ?? config('mail.mailers.smtp.username')),
                'mail.mailers.smtp.password' => (string) ($settings->smtp_password ?? config('mail.mailers.smtp.password')),
                'mail.mailers.smtp.scheme' => $smtpScheme,
                'mail.mailers.smtp.timeout' => 10,
                'mail.from.address' => (string) ($settings->from_email ?? config('mail.from.address')),
                'mail.from.name' => (string) ($settings->from_name ?? config('mail.from.name')),
            ]);
            Mail::purge('smtp');
        } catch (\Throwable) {
            // Keep default mail configuration.
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

    private function resolveParticipationFee(object $auction): float
    {
        $perAuction = isset($auction->emd_amount) ? (float) $auction->emd_amount : 0.0;
        if ($perAuction > 0) {
            return $perAuction;
        }
        return (float) $this->settingsService->getFloat('bid_participation_fee', 0);
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

    private function inferNotificationKind(object $thread): string
    {
        $s = strtolower((string) ($thread->subject ?? '') . ' ' . ($thread->message ?? ''));
        if (preg_match('/outbid|out-bid|out bid|been outbid/i', $s)) {
            return 'outbid';
        }
        if (preg_match('/\b(you won|you\'?re winning|winner|congratulations|winning bid)\b/i', $s)) {
            return 'winning';
        }
        if (preg_match('/\b(payment|invoice|fee due|pay now|payable|reminder.*pay|complete payment)\b/i', $s)) {
            return 'payment';
        }
        if (preg_match('/\b(auction|bid on|place bid|domain|lot\b|highest bid)\b/i', $s)) {
            return 'auction';
        }

        return 'system';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $threads
     * @return array<int, array{label: string, items: \Illuminate\Support\Collection}>
     */
    private function groupNotificationsByDay($threads): array
    {
        $groups = [];
        $byDate = $threads->groupBy(fn ($t) => Carbon::parse($t->created_at)->format('Y-m-d'));
        foreach ($byDate->sortKeysDesc() as $dateKey => $items) {
            $d = Carbon::parse($dateKey);
            if ($d->isToday()) {
                $label = 'Today';
            } elseif ($d->isYesterday()) {
                $label = 'Yesterday';
            } else {
                $label = $d->format('d M Y');
            }
            $groups[] = ['label' => $label, 'items' => $items];
        }

        return $groups;
    }
}
