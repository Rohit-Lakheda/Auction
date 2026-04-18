<?php

namespace App\Http\Controllers;

use App\Services\AppSettingsService;
use App\Services\BlacklistService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    public function __construct(
        private readonly AppSettingsService $settingsService,
        private readonly BlacklistService $blacklistService,
    ) {
    }

    public function dashboard(Request $request)
    {
        $totalAuctions = DB::table('auctions')->count();
        $activeAuctions = DB::table('auctions')->where('status', 'active')->count();
        $closedAuctions = DB::table('auctions')->whereIn('status', ['closed', 'completed', 'failed'])->count();
        $upcomingAuctions = DB::table('auctions')->where('status', 'upcoming')->count();
        $pendingPayments = DB::table('auctions')
            ->where('status', 'closed')
            ->whereNotNull('winner_user_id')
            ->where('payment_status', 'pending')
            ->count();
        $failedAuctions = DB::table('auctions as a')
            ->where('a.status', 'closed')
            ->where(function ($q): void {
                $q->where('a.auction_outcome', 'failed')
                ->orWhereNotExists(function ($sq): void {
                    $sq->select(DB::raw(1))
                        ->from('bids as b')
                        ->whereColumn('b.auction_id', 'a.id');
                })->orWhere(function ($sq): void {
                    $sq->whereNotNull('a.winner_user_id')
                        ->where(function ($x): void {
                            $x->whereNull('a.payment_status')
                                ->orWhere('a.payment_status', '<>', 'paid');
                        });
                });
            })
            ->count();
        $totalUsers = DB::table('users')->where('role', 'user')->count();
        $blockedUsers = DB::table('users')->where('role', 'user')->where('is_blocked', 1)->count();
        $totalBids = DB::table('bids')->count();
        $filter = (string) $request->query('filter', 'all');
        $allowedFilters = ['all', 'active', 'upcoming', 'closed', 'pending_payment', 'failed'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $auctionsQuery = DB::table('auctions as a')
            ->leftJoin('users as u', 'a.created_by', '=', 'u.id')
            ->selectRaw('a.*, u.name as creator_name, (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count');

        $filterLabel = 'All Auctions';
        if ($filter === 'active') {
            $auctionsQuery->where('a.status', 'active');
            $filterLabel = 'Live Auctions';
        } elseif ($filter === 'upcoming') {
            $auctionsQuery->where('a.status', 'upcoming');
            $filterLabel = 'Upcoming Auctions';
        } elseif ($filter === 'closed') {
            $auctionsQuery->whereIn('a.status', ['closed', 'completed', 'failed']);
            $filterLabel = 'Closed / Completed Auctions';
        } elseif ($filter === 'pending_payment') {
            $auctionsQuery->whereIn('a.status', ['closed', 'completed'])->where('a.payment_status', 'pending');
            $filterLabel = 'Pending Winner Payments';
        } elseif ($filter === 'failed') {
            $auctionsQuery
                ->where('a.status', 'closed')
                ->where(function ($q): void {
                    $q->where('a.auction_outcome', 'failed')
                    ->orWhereNotExists(function ($sq): void {
                        $sq->select(DB::raw(1))
                            ->from('bids as b')
                            ->whereColumn('b.auction_id', 'a.id');
                    })->orWhere(function ($sq): void {
                        $sq->whereNotNull('a.winner_user_id')
                            ->where(function ($x): void {
                                $x->whereNull('a.payment_status')
                                    ->orWhere('a.payment_status', '<>', 'paid');
                            });
                    });
                });
            $filterLabel = 'Failed Auctions';
        }

        $perPage = $this->resolvePerPage($request, 20);
        $auctions = $this->paginateQuery($auctionsQuery->orderByDesc('a.created_at'), $perPage);

        return view('admin.dashboard', [
            'totalAuctions' => $totalAuctions,
            'activeAuctions' => $activeAuctions,
            'closedAuctions' => $closedAuctions,
            'upcomingAuctions' => $upcomingAuctions,
            'pendingPayments' => $pendingPayments,
            'failedAuctions' => $failedAuctions,
            'totalUsers' => $totalUsers,
            'blockedUsers' => $blockedUsers,
            'totalBids' => $totalBids,
            'auctions' => $auctions,
            'filter' => $filter,
            'filterLabel' => $filterLabel,
            'perPage' => (string) $request->query('per_page', (string) $perPage),
            'adminName' => (string) $request->session()->get('name', 'Admin'),
        ]);
    }

    public function auctionsIndex(Request $request)
    {
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $payment = (string) $request->query('payment', 'all');
        $perPage = $this->resolvePerPage($request, 20);

        $auctionsQuery = DB::table('auctions as a')
            ->leftJoin('users as u', 'a.created_by', '=', 'u.id')
            ->selectRaw('a.*, u.name as creator_name, (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count')
            ->when($status !== 'all', function ($q) use ($status): void {
                if ($status === 'failed') {
                    $q->where('a.status', 'closed')
                        ->where(function ($sq): void {
                            $sq->where('a.auction_outcome', 'failed')
                            ->orWhereNotExists(function ($s): void {
                                $s->select(DB::raw(1))
                                    ->from('bids as b')
                                    ->whereColumn('b.auction_id', 'a.id');
                            })->orWhere(function ($x): void {
                                $x->whereNotNull('a.winner_user_id')
                                    ->where(function ($z): void {
                                        $z->whereNull('a.payment_status')
                                            ->orWhere('a.payment_status', '<>', 'paid');
                                    });
                            });
                        });
                    return;
                }
                $q->where('a.status', $status);
            })
            ->when($payment !== 'all', function ($q) use ($payment): void {
                $q->where('a.payment_status', $payment);
                if ($payment === 'pending') {
                    $q->where('a.status', 'closed')->whereNotNull('a.winner_user_id');
                }
            })
            ->when($search !== '', fn ($q) => $q->where('a.title', 'like', '%' . $search . '%'))
            ->orderByDesc('a.created_at');

        $auctions = $this->paginateQuery($auctionsQuery, $perPage);

        return view('admin.auctions-index', [
            'auctions' => $auctions,
            'filters' => ['status' => $status, 'payment' => $payment, 'search' => $search],
            'perPage' => (string) $request->query('per_page', (string) $perPage),
        ]);
    }

    public function bidsIndex(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $perPage = $this->resolvePerPage($request, 10);

        $bidsQuery = DB::table('bids as b')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->join('auctions as a', 'a.id', '=', 'b.auction_id')
            ->when($search !== '', fn ($q) => $q->where(function ($sq) use ($search): void {
                $sq->where('u.name', 'like', '%' . $search . '%')
                    ->orWhere('u.email', 'like', '%' . $search . '%')
                    ->orWhere('a.title', 'like', '%' . $search . '%');
            }))
            ->when($status !== 'all', fn ($q) => $q->where('a.status', $status))
            ->orderByDesc('b.created_at')
            ->select([
                'b.id as bid_id',
                'b.amount',
                'b.created_at',
                'u.id as user_id',
                'u.name as user_name',
                'u.email as user_email',
                'a.id as auction_id',
                'a.title as auction_title',
                'a.status as auction_status',
            ]);

        $bids = $this->paginateQuery($bidsQuery, $perPage);

        return view('admin.bids-index', [
            'bids' => $bids,
            'filters' => ['search' => $search, 'status' => $status],
            'perPage' => (string) $request->query('per_page', (string) $perPage),
        ]);
    }

    public function operations(Request $request)
    {
        $pendingWinnerPayments = DB::table('auctions')
            ->leftJoin('users as u', 'u.id', '=', 'auctions.winner_user_id')
            ->where('status', 'closed')
            ->where('payment_status', 'pending')
            ->orderBy('payment_window_expires_at')
            ->limit(20)
            ->get([
                'auctions.*',
                'u.name as winner_name',
                'u.email as winner_email',
            ]);

        // [EMD/WALLET DISABLED] Wallet top-ups query removed
        $recentWalletTopups = collect();

        $recentRegistrations = DB::table('registration as r')
            ->leftJoin('users as u', 'r.email', '=', 'u.email')
            ->orderByDesc('r.created_at')
            ->limit(20)
            ->get([
                'r.registration_id',
                'r.full_name',
                'r.email',
                'r.mobile',
                'r.payment_status',
                'r.created_at',
                'u.id as user_id',
            ]);

        return view('admin.operations', [
            'pendingWinnerPayments' => $pendingWinnerPayments,
            'recentWalletTopups' => $recentWalletTopups,
            'recentRegistrations' => $recentRegistrations,
        ]);
    }

    public function supportTickets(Request $request)
    {
        if (! Schema::hasTable('support_tickets')) {
            return view('admin.support-tickets', ['tickets' => collect(), 'setupMissing' => true]);
        }

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'ticket_id' => ['required', 'integer', 'min:1'],
                'status' => ['required', 'in:open,in_progress,resolved,closed'],
                'admin_reply' => ['nullable', 'string', 'max:5000'],
            ]);

            $update = [
                'status' => $validated['status'],
                'admin_reply' => trim((string) ($validated['admin_reply'] ?? '')),
                'updated_at' => now(),
            ];
            if ($validated['status'] === 'resolved') {
                $update['resolved_at'] = now();
            }

            DB::table('support_tickets')
                ->where('id', (int) $validated['ticket_id'])
                ->update($update);

            return redirect()->route('admin.support.tickets')->with('success', 'Ticket updated.');
        }

        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $tickets = DB::table('support_tickets as t')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->when($status !== 'all', fn ($q) => $q->where('t.status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($sq) use ($search): void {
                $sq->where('t.subject', 'like', '%' . $search . '%')
                    ->orWhere('t.message', 'like', '%' . $search . '%')
                    ->orWhere('u.name', 'like', '%' . $search . '%')
                    ->orWhere('u.email', 'like', '%' . $search . '%');
            }))
            ->orderByRaw("FIELD(t.status, 'open','in_progress','resolved','closed')")
            ->orderByDesc('t.created_at')
            ->select('t.*', 'u.name as user_name', 'u.email as user_email')
            ->limit(200)
            ->get();

        return view('admin.support-tickets', [
            'tickets' => $tickets,
            'status' => $status,
            'search' => $search,
            'setupMissing' => false,
        ]);
    }

    public function blacklist(Request $request)
    {
        if (! Schema::hasTable('blacklisted_users')) {
            return view('admin.blacklist', ['rows' => collect(), 'setupMissing' => true, 'search' => '', 'status' => 'all']);
        }

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $rows = DB::table('blacklisted_users as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->when($status === 'active', fn ($q) => $q->where('b.is_active', 1))
            ->when($status === 'inactive', fn ($q) => $q->where('b.is_active', 0))
            ->when($search !== '', fn ($q) => $q->where(function ($sq) use ($search): void {
                $sq->where('b.email', 'like', '%' . $search . '%')
                    ->orWhere('b.mobile', 'like', '%' . $search . '%')
                    ->orWhere('b.pan_card_number', 'like', '%' . $search . '%')
                    ->orWhere('u.name', 'like', '%' . $search . '%');
            }))
            ->orderByDesc('b.blacklisted_at')
            ->select('b.*', 'u.name as user_name', 'u.email as user_email')
            ->limit(300)
            ->get();

        return view('admin.blacklist', compact('rows', 'search', 'status') + ['setupMissing' => false]);
    }

    public function toggleBlacklist(Request $request, int $id)
    {
        if (! Schema::hasTable('blacklisted_users')) {
            return redirect()->route('admin.blacklist')->withErrors(['blacklist' => 'Blacklist table missing.']);
        }
        $row = DB::table('blacklisted_users')->where('id', $id)->first();
        if (! $row) {
            return redirect()->route('admin.blacklist')->withErrors(['blacklist' => 'Blacklist record not found.']);
        }
        $newState = (int) ($row->is_active ?? 0) === 1 ? 0 : 1;
        DB::table('blacklisted_users')->where('id', $id)->update(['is_active' => $newState, 'updated_at' => now()]);
        $this->logAudit('blacklist_toggled', 'blacklisted_users', (string) $id, ['is_active' => $newState], $request);
        return redirect()->route('admin.blacklist')->with('success', $newState ? 'Blacklist activated.' : 'Blacklist deactivated.');
    }

    public function auditLogs(Request $request)
    {
        if (! Schema::hasTable('audit_logs')) {
            return view('admin.audit-logs', ['logs' => collect(), 'setupMissing' => true, 'search' => '', 'action' => 'all']);
        }

        $search = trim((string) $request->query('search', ''));
        $action = (string) $request->query('action', 'all');
        $logs = DB::table('audit_logs')
            ->when($action !== 'all', fn ($q) => $q->where('action', $action))
            ->when($search !== '', fn ($q) => $q->where(function ($sq) use ($search): void {
                $sq->where('entity_type', 'like', '%' . $search . '%')
                    ->orWhere('entity_id', 'like', '%' . $search . '%')
                    ->orWhere('action', 'like', '%' . $search . '%')
                    ->orWhere('ip_address', 'like', '%' . $search . '%');
            }))
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $actions = DB::table('audit_logs')->select('action')->distinct()->orderBy('action')->pluck('action');
        return view('admin.audit-logs', compact('logs', 'search', 'action', 'actions') + ['setupMissing' => false]);
    }

    public function addAuction(Request $request)
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'base_price' => ['required', 'numeric', 'gt:0'],
                'min_increment' => ['required', 'numeric', 'gt:0'],
                'emd_amount' => ['required', 'numeric', 'gte:0'],
                'start_datetime' => ['required', 'date'],
                'end_datetime' => ['required', 'date', 'after:start_datetime'],
            ]);
            $data = $this->normalizeAuctionDateInputs($data);
            DB::table('auctions')->insert([
                ...$data,
                'created_by' => (int) $request->session()->get('user_id'),
                'status' => 'upcoming',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return back()->with('success', 'Auction created successfully!');
        }
        return view('admin.add-auction');
    }

    public function editAuction(Request $request, int $id)
    {
        $bidCount = DB::table('bids')->where('auction_id', $id)->count();
        if ($bidCount > 0) {
            return redirect()->route('admin.dashboard');
        }
        $auction = DB::table('auctions')->where('id', $id)->first();
        if (! $auction) {
            return redirect()->route('admin.dashboard');
        }
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'base_price' => ['required', 'numeric', 'gt:0'],
                'min_increment' => ['required', 'numeric', 'gt:0'],
                'emd_amount' => ['required', 'numeric', 'gte:0'],
                'start_datetime' => ['required', 'date'],
                'end_datetime' => ['required', 'date', 'after:start_datetime'],
            ]);
            $data = $this->normalizeAuctionDateInputs($data);
            DB::table('auctions')->where('id', $id)->update([...$data, 'updated_at' => now()]);
            return back()->with('success', 'Auction updated successfully!');
        }
        return view('admin.edit-auction', ['auction' => $auction]);
    }

    public function deleteAuction(Request $request, int $id)
    {
        if (DB::table('bids')->where('auction_id', $id)->count() === 0) {
            DB::table('auctions')->where('id', $id)->delete();
        }
        return redirect()->route('admin.dashboard');
    }

    public function closeAuction(Request $request, int $id)
    {
        $auction = DB::table('auctions')->where('id', $id)->first();
        if (! $auction) {
            return redirect()->route('admin.auctions.index')->withErrors(['auction' => 'Auction not found.']);
        }

        $topBidders = DB::table('bids')
            ->where('auction_id', $id)
            ->selectRaw('user_id, MAX(amount) as amount')
            ->groupBy('user_id')
            ->orderByDesc('amount')
            ->limit(3)
            ->get();
        if ($topBidders->isNotEmpty()) {
            $highest = $topBidders->first();
            $paymentWindowHours = $this->settingsService->getInt('emd_payment_window_hours', (int) config('emd.payment_window_hours', 24));
            $paymentWindowExpires = now()->addHours($paymentWindowHours);
            $auctionTitle = (string) (DB::table('auctions')->where('id', $id)->value('title') ?? '');
            DB::table('auctions')->where('id', $id)->update([
                'status' => 'closed',
                'auction_outcome' => null,
                'winner_user_id' => $highest->user_id,
                'winner_rank' => 1,
                'final_price' => $highest->amount,
                'payment_status' => 'pending',
                'payment_window_expires_at' => $paymentWindowExpires,
                'top_bidders_json' => json_encode($topBidders->values()->all()),
                'updated_at' => now(),
            ]);
            $this->sendWinnerNotificationEmail(
                (int) $highest->user_id,
                $auctionTitle,
                (float) $highest->amount,
                $paymentWindowExpires->format('d-M-Y h:i A')
            );
        } else {
            DB::table('auctions')->where('id', $id)->update(['status' => 'closed', 'auction_outcome' => 'failed', 'updated_at' => now()]);
        }
        $this->logAudit('auction_force_closed', 'auction', (string) $id, null, $request);
        return redirect()->route('admin.auctions.index')->with('success', 'Auction closed successfully.');
    }

    public function reopenAuction(Request $request, int $id)
    {
        $auction = DB::table('auctions')->where('id', $id)->first();
        if (! $auction) {
            return redirect()->route('admin.auctions.index')->withErrors(['auction' => 'Auction not found.']);
        }

        $now = now();
        $newStart = Carbon::parse((string) $auction->start_datetime);
        $newEnd = Carbon::parse((string) $auction->end_datetime);

        if ($newStart->greaterThan($now)) {
            $newStart = $now->copy();
        }

        // If auction already ended, extend window to allow real reopening.
        if ($newEnd->lessThanOrEqualTo($now)) {
            $newEnd = $now->copy()->addDay();
        }

        DB::table('auctions')->where('id', $id)->update([
            'status' => 'active',
            'auction_outcome' => null,
            'cancelled_at' => null,
            'winner_user_id' => null,
            'winner_rank' => null,
            'final_price' => null,
            'payment_status' => null,
            'payment_window_expires_at' => null,
            'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            'end_datetime' => $newEnd->format('Y-m-d H:i:s'),
            'updated_at' => now(),
        ]);

        $this->logAudit('auction_reopened', 'auction', (string) $id, ['new_end' => $newEnd->format('Y-m-d H:i:s')], $request);
        return redirect()->route('admin.auctions.index')->with('success', 'Auction reopened successfully.');
    }

    public function cancelAuction(Request $request, int $id)
    {
        $auction = DB::table('auctions')->where('id', $id)->first();
        if (! $auction) {
            return redirect()->route('admin.auctions.index')->withErrors(['auction' => 'Auction not found.']);
        }

        DB::table('auctions')->where('id', $id)->update([
            'status' => 'closed',
            'auction_outcome' => 'cancelled',
            'cancelled_at' => now(),
            'winner_user_id' => null,
            'winner_rank' => null,
            'final_price' => null,
            'payment_status' => null,
            'payment_window_expires_at' => null,
            'updated_at' => now(),
        ]);
        $this->logAudit('auction_cancelled', 'auction', (string) $id, null, $request);

        return redirect()->route('admin.auctions.index')->with('success', 'Auction cancelled successfully.');
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
            \Illuminate\Support\Facades\Log::warning('Winner notification email failed.', [
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

    public function viewBids(Request $request, int $id)
    {
        $auction = DB::table('auctions')->where('id', $id)->first();
        if (! $auction) {
            return redirect()->route('admin.dashboard');
        }
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->resolvePerPage($request, 20);

        $bidsQuery = DB::table('bids as b')
            ->join('users as u', 'b.user_id', '=', 'u.id')
            ->where('b.auction_id', $id)
            ->when($search !== '', fn ($q) => $q->where(function ($sq) use ($search): void {
                $sq->where('u.name', 'like', '%' . $search . '%')
                    ->orWhere('u.email', 'like', '%' . $search . '%');
            }))
            ->orderByDesc('b.amount')
            ->orderBy('b.created_at')
            ->select(['b.*', 'u.name as bidder_name', 'u.email as bidder_email']);

        $bids = $this->paginateQuery($bidsQuery, $perPage);

        return view('admin.view-bids', compact('auction', 'bids', 'search', 'perPage'));
    }

    public function completed(Request $request)
    {
        if ($request->isMethod('post')) {
            DB::table('auctions')->where('id', (int) $request->input('auction_id'))
                ->update(['payment_status' => (string) $request->input('payment_status', 'pending')]);
        }
        $status = (string) $request->query('status', 'all');
        $payment = (string) $request->query('payment', 'all');
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->resolvePerPage($request, 20);

        $completedQuery = DB::table('auctions as a')
            ->leftJoin('users as u', 'a.winner_user_id', '=', 'u.id')
            ->whereIn('a.status', ['closed', 'completed', 'failed'])
            ->when($status !== 'all', fn ($q) => $q->where('a.status', $status))
            ->when($payment !== 'all', fn ($q) => $q->where('a.payment_status', $payment))
            ->when($search !== '', fn ($q) => $q->where('a.title', 'like', '%' . $search . '%'))
            ->orderByDesc('a.end_datetime')
            ->select(['a.*', 'u.name as winner_name', 'u.email as winner_email']);

        $completedAuctions = $this->paginateQuery($completedQuery, $perPage);
        return view('admin.completed', [
            'completedAuctions' => $completedAuctions,
            'filters' => ['status' => $status, 'payment' => $payment, 'search' => $search],
            'perPage' => (string) $request->query('per_page', (string) $perPage),
        ]);
    }

    public function manageUsers(Request $request)
    {
        if ($request->isMethod('post') && $request->input('action') === 'send_credentials') {
            $userId = (int) $request->input('user_id');
            $user = DB::table('users')->where('id', $userId)->first();
            if ($user) {
                $tempPassword = bin2hex(random_bytes(8));
                $resetToken = bin2hex(random_bytes(32));
                DB::table('users')->where('id', $userId)->update([
                    'password' => Hash::make($tempPassword),
                    'password_reset_token' => $resetToken,
                    'password_reset_expires' => now()->addDays(7),
                    'updated_at' => now(),
                ]);
                try {
                    Mail::raw("Temporary Password: {$tempPassword}\nReset token: {$resetToken}", function ($m) use ($user): void {
                        $m->to($user->email)->subject('Account Credentials Reset');
                    });
                    session()->flash('success', 'Credentials sent successfully to ' . $user->email);
                } catch (\Throwable) {
                    session()->flash('success', 'Credentials generated. Temporary Password: ' . $tempPassword);
                }
            }
            return redirect()->route('admin.manage-users', ['id' => $userId]);
        }
        $viewUserId = (int) $request->query('id', 0);
        if ($viewUserId > 0) {
            return redirect()->route('admin.users.show', ['id' => $viewUserId]);
        }
        $selectedUser = null;
        if ($viewUserId > 0) {
            $selectedUser = DB::selectOne("SELECT u.*, r.registration_id, r.registration_type, r.pan_card_number, r.mobile as reg_mobile, r.date_of_birth, r.payment_status, r.payment_date, r.payment_amount, r.payment_transaction_id
                FROM users u LEFT JOIN registration r ON u.email = r.email WHERE u.id = ?", [$viewUserId]);
        }
        $search = trim((string) $request->query('search', ''));
        $userFilter = (string) $request->query('user_filter', 'all');
        if (! in_array($userFilter, ['all', 'blocked', 'defaulted'], true)) {
            $userFilter = 'all';
        }
        $perPage = $this->resolvePerPage($request, 20);
        $allUsersCount = DB::table('users')->where('role', 'user')->count();
        $blockedUsersCount = DB::table('users')->where('role', 'user')->where('is_blocked', 1)->count();
        $defaultedUsersCount = DB::table('users')->where('role', 'user')->where('default_count', '>', 0)->count();
        $usersQuery = DB::table('users as u')
            ->leftJoin('registration as r', 'u.email', '=', 'r.email')
            ->where('u.role', 'user')
            ->when($userFilter === 'blocked', fn ($q) => $q->where('u.is_blocked', 1))
            ->when($userFilter === 'defaulted', fn ($q) => $q->where('u.default_count', '>', 0))
            ->when($search !== '', fn ($q) => $q->where(function ($sq) use ($search): void {
                $sq->where('u.name', 'like', '%' . $search . '%')
                    ->orWhere('u.email', 'like', '%' . $search . '%')
                    ->orWhere('r.registration_id', 'like', '%' . $search . '%');
            }))
            ->selectRaw("u.id, u.name, u.email, u.role, u.created_at, u.is_blocked, r.registration_id, r.registration_type, r.payment_status,
                (SELECT COUNT(*) FROM bids WHERE user_id = u.id) as total_bids,
                (SELECT COUNT(*) FROM auctions WHERE winner_user_id = u.id) as won_auctions")
            ->orderByDesc('u.created_at');

        $users = $this->paginateQuery($usersQuery, $perPage);
        return view('admin.manage-users', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'search' => $search,
            'userFilter' => $userFilter,
            'allUsersCount' => $allUsersCount,
            'blockedUsersCount' => $blockedUsersCount,
            'defaultedUsersCount' => $defaultedUsersCount,
            'perPage' => (string) $request->query('per_page', (string) $perPage),
        ]);
    }

    public function userDetails(Request $request, int $id)
    {
        $user = DB::table('users')->where('id', $id)->first();
        if (! $user) {
            return redirect()->route('admin.manage-users')->withErrors(['user' => 'User not found.']);
        }

        $registration = DB::table('registration')->where('email', $user->email)->first();
        $stats = [
            'total_bids' => DB::table('bids')->where('user_id', $id)->count(),
            'won_auctions' => DB::table('auctions')->where('winner_user_id', $id)->count(),
            // [EMD DISABLED] joined_auctions and wallet_balance removed from stats
        ];

        $recentBidsPerPage = $this->resolvePerPageValue((string) $request->query('bids_per_page', '10'), 10);
        $recentBids = DB::table('bids as b')
            ->join('auctions as a', 'a.id', '=', 'b.auction_id')
            ->where('b.user_id', $id)
            ->orderByDesc('b.created_at')
            ->select(['b.amount', 'b.created_at', 'a.id as auction_id', 'a.title as auction_title', 'a.status as auction_status'])
            ->paginate($recentBidsPerPage, ['*'], 'bids_page')
            ->withQueryString();

        $wonPerPage = $this->resolvePerPageValue((string) $request->query('won_per_page', '10'), 10);
        $wonAuctions = DB::table('auctions')
            ->where('winner_user_id', $id)
            ->orderByDesc('end_datetime')
            ->select(['id', 'title', 'status', 'final_price', 'payment_status', 'end_datetime'])
            ->paginate($wonPerPage, ['*'], 'won_page')
            ->withQueryString();

        // [EMD DISABLED] Participation (EMD) query commented out
        $participationPerPage = $this->resolvePerPageValue((string) $request->query('participation_per_page', '10'), 10);
        $participations = new LengthAwarePaginator([], 0, $participationPerPage, 1);

        // [EMD/WALLET DISABLED] Wallet transactions query commented out
        $walletTxnPerPage = $this->resolvePerPageValue((string) $request->query('wallet_txn_per_page', '10'), 10);
        $walletTransactions = new LengthAwarePaginator([], 0, $walletTxnPerPage, 1);

        // [EMD/WALLET DISABLED] Wallet top-ups query commented out
        $walletTopupPerPage = $this->resolvePerPageValue((string) $request->query('wallet_topup_per_page', '10'), 10);
        $walletTopups = new LengthAwarePaginator([], 0, $walletTopupPerPage, 1);

        $auctionPaymentPerPage = $this->resolvePerPageValue((string) $request->query('auction_payment_per_page', '10'), 10);
        $auctionPayments = new LengthAwarePaginator([], 0, $auctionPaymentPerPage, 1);
        if (Schema::hasTable('payment_transactions')) {
            $auctionPayments = DB::table('payment_transactions as pt')
                ->leftJoin('auctions as a', 'a.id', '=', 'pt.auction_id')
                ->where('pt.user_id', $id)
                ->orderByDesc('pt.created_at')
                ->select(['pt.*', 'a.title as auction_title'])
                ->paginate($auctionPaymentPerPage, ['*'], 'auction_payment_page')
                ->withQueryString();
        }

        $registrationPaymentPerPage = $this->resolvePerPageValue((string) $request->query('registration_payment_per_page', '10'), 10);
        $registrationPayments = new LengthAwarePaginator([], 0, $registrationPaymentPerPage, 1);
        if (Schema::hasTable('registration_payments')) {
            $registrationPayments = DB::table('registration_payments')
                ->where(function ($q) use ($registration): void {
                    $q->where('registration_id', $registration->registration_id ?? '')
                        ->orWhere('transaction_id', $registration->payment_transaction_id ?? '');
                })
                ->orderByDesc('created_at')
                ->paginate($registrationPaymentPerPage, ['*'], 'registration_payment_page')
                ->withQueryString();
        }

        return view('admin.user-details', compact(
            'user',
            'registration',
            'stats',
            'recentBids',
            'wonAuctions',
            'participations',
            'walletTransactions',
            'walletTopups',
            'auctionPayments',
            'registrationPayments'
        ) + [
            'perPage' => [
                'bids' => (string) $recentBidsPerPage,
                'won' => (string) $wonPerPage,
                'participation' => (string) $participationPerPage, // [EMD DISABLED] always empty
                'wallet_txn' => (string) $walletTxnPerPage,         // [EMD/WALLET DISABLED] always empty
                'wallet_topup' => (string) $walletTopupPerPage,      // [EMD/WALLET DISABLED] always empty
                'auction_payment' => (string) $auctionPaymentPerPage,
                'registration_payment' => (string) $registrationPaymentPerPage,
            ],
        ]);
    }

    public function toggleUserBlock(Request $request, int $id)
    {
        $user = DB::table('users')->where('id', $id)->where('role', 'user')->first();
        if (! $user) {
            return redirect()->route('admin.manage-users')->withErrors(['user' => 'User not found.']);
        }
        $newState = (int) ($user->is_blocked ?? 0) === 1 ? 0 : 1;
        DB::table('users')->where('id', $id)->update(['is_blocked' => $newState, 'updated_at' => now()]);
        if ($newState === 1) {
            $this->blacklistService->blacklistAllKnownIdentitiesForUser($id, 'Admin blocked user');
        } else {
            $this->blacklistService->deactivateBlacklistForUserId($id);
        }
        return back()->with('success', $newState === 1 ? 'User blocked successfully.' : 'User unblocked successfully.');
    }

    public function settings(Request $request)
    {
        $activeTab = (string) $request->query('tab', 'registration');

        if ($request->isMethod('post')) {
            $formType = (string) $request->input('form_type', '');
            if ($formType === 'registration_amount') {
                $amount = (float) $request->input('registration_amount', 0);
                if ($amount <= 0) {
                    return back()->withErrors(['registration_amount' => 'Registration amount must be greater than zero']);
                }
                DB::table('settings')->updateOrInsert(
                    ['setting_key' => 'registration_amount'],
                    [
                        'setting_value' => number_format($amount, 2, '.', ''),
                        'description' => 'Registration fee amount in INR',
                        'updated_by' => (int) $request->session()->get('user_id'),
                        'updated_at' => now(),
                    ]
                );
                return redirect()->route('admin.settings', ['tab' => 'registration'])->with('success', 'Registration amount updated successfully!');
            }

            if ($formType === 'participation_fee') {
                $amount = (float) $request->input('bid_participation_fee', 0);
                if ($amount < 0) {
                    return back()->withErrors(['bid_participation_fee' => 'Participation fee cannot be negative']);
                }
                DB::table('settings')->updateOrInsert(
                    ['setting_key' => 'bid_participation_fee'],
                    [
                        'setting_value' => number_format($amount, 2, '.', ''),
                        'description' => 'Default participation fee in INR for auctions with no custom fee',
                        'updated_by' => (int) $request->session()->get('user_id'),
                        'updated_at' => now(),
                    ]
                );
                return redirect()->route('admin.settings', ['tab' => 'registration'])->with('success', 'Default participation fee updated successfully!');
            }

            // [EMD DISABLED] emd_settings form handler commented out
            // if ($formType === 'emd_settings') { ... }

            if ($formType === 'email_settings') {
                DB::statement("CREATE TABLE IF NOT EXISTS email_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    smtp_host VARCHAR(255) NOT NULL DEFAULT 'smtp.elasticemail.com',
                    smtp_port INT NOT NULL DEFAULT 587,
                    smtp_username VARCHAR(255) NOT NULL,
                    smtp_password VARCHAR(255) NOT NULL,
                    from_email VARCHAR(255) NOT NULL,
                    from_name VARCHAR(255) NOT NULL DEFAULT 'NIXI Auction Portal',
                    encryption VARCHAR(20) DEFAULT 'tls',
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    updated_by INT NULL
                )");
                $host = trim((string) $request->input('smtp_host', ''));
                $port = (int) $request->input('smtp_port', 587);
                $username = trim((string) $request->input('smtp_username', ''));
                $password = trim((string) $request->input('smtp_password', ''));
                $fromEmail = trim((string) $request->input('from_email', ''));
                $fromName = trim((string) $request->input('from_name', 'NIXI Auction Portal'));
                $encryption = trim((string) $request->input('encryption', 'tls'));
                $isActive = $request->boolean('is_active') ? 1 : 0;
                if ($host === '' || $username === '' || $fromEmail === '') {
                    return back()->withErrors(['email_settings' => 'Please fill required email fields.']);
                }
                $existing = DB::table('email_settings')->where('is_active', 1)->latest('updated_at')->first();
                if ($password === '' && $existing) {
                    $password = (string) $existing->smtp_password;
                }
                if ($password === '') {
                    return back()->withErrors(['email_settings' => 'Password is required for new email configuration.']);
                }
                if ($existing) {
                    DB::table('email_settings')->where('id', $existing->id)->update([
                        'smtp_host' => $host, 'smtp_port' => $port, 'smtp_username' => $username, 'smtp_password' => $password,
                        'from_email' => $fromEmail, 'from_name' => $fromName, 'encryption' => $encryption, 'is_active' => $isActive,
                        'updated_by' => (int) $request->session()->get('user_id'), 'updated_at' => now(),
                    ]);
                } else {
                    DB::table('email_settings')->insert([
                        'smtp_host' => $host, 'smtp_port' => $port, 'smtp_username' => $username, 'smtp_password' => $password,
                        'from_email' => $fromEmail, 'from_name' => $fromName, 'encryption' => $encryption, 'is_active' => $isActive,
                        'updated_by' => (int) $request->session()->get('user_id'), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                return redirect()->route('admin.settings', ['tab' => 'email'])->with('success', 'Email settings updated successfully!');
            }

            if ($formType === 'test_email') {
                $to = trim((string) $request->input('test_email_to', ''));
                if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    return back()->withErrors(['test_email' => 'Please enter a valid test email address.']);
                }
                try {
                    Mail::raw('This is a test email from Auction Portal admin settings.', static function ($m) use ($to): void {
                        $m->to($to)->subject('Auction Portal Test Email');
                    });
                    return redirect()->route('admin.settings', ['tab' => 'email'])->with('success', 'Test email sent successfully!');
                } catch (\Throwable $e) {
                    return back()->withErrors(['test_email' => 'Failed to send test email: ' . $e->getMessage()]);
                }
            }
        }

        $registrationAmount = (float) (DB::table('settings')->where('setting_key', 'registration_amount')->value('setting_value') ?? 500.00);
        $defaultParticipationFee = (float) (DB::table('settings')->where('setting_key', 'bid_participation_fee')->value('setting_value') ?? 0.00);
        $emdSettings = [
            'emd_default_amount' => $this->settingsService->getFloat('emd_default_amount', (float) config('emd.default_emd_amount', 10000)),
            'emd_penalty_percentage' => $this->settingsService->getFloat('emd_penalty_percentage', (float) config('emd.penalty_percentage', 25)),
            'emd_payment_window_hours' => $this->settingsService->getInt('emd_payment_window_hours', (int) config('emd.payment_window_hours', 24)),
            'emd_max_default_before_block' => $this->settingsService->getInt('emd_max_default_before_block', (int) config('emd.max_default_before_block', 3)),
            'emd_default_multiplier' => $this->settingsService->getFloat('emd_default_multiplier', (float) config('emd.default_emd_multiplier', 1)),
        ];
        $row = DB::table('email_settings')->where('is_active', 1)->latest('updated_at')->first();
        $emailSettings = [
            'smtp_host' => $row->smtp_host ?? '',
            'smtp_port' => $row->smtp_port ?? 587,
            'smtp_username' => $row->smtp_username ?? '',
            'smtp_password' => $row->smtp_password ?? '',
            'from_email' => $row->from_email ?? '',
            'from_name' => $row->from_name ?? 'NIXI Auction Portal',
            'encryption' => $row->encryption ?? 'tls',
            'is_active' => $row->is_active ?? 1,
        ];

        return view('admin.settings', compact('registrationAmount', 'defaultParticipationFee', 'emdSettings', 'emailSettings', 'activeTab'));
    }

    public function uploadExcel(Request $request)
    {
        $preview = session('import_preview', []);

        if ($request->isMethod('post')) {
            if ($request->boolean('confirm_import') && is_array(session('import_data'))) {
                $data = session('import_data');
                DB::transaction(function () use ($data, $request): void {
                    foreach ($data as $row) {
                        DB::table('auctions')->insert([
                            'title' => $row['title'],
                            'description' => $row['description'],
                            'base_price' => $row['base_price'],
                            'min_increment' => $row['min_increment'],
                            'emd_amount' => isset($row['emd_amount']) ? (float) $row['emd_amount'] : $this->settingsService->getFloat('bid_participation_fee', 0),
                            'start_datetime' => Carbon::parse($row['start_datetime'])->startOfDay()->format('Y-m-d H:i:s'),
                            'end_datetime' => Carbon::parse($row['end_datetime'])->startOfDay()->format('Y-m-d H:i:s'),
                            'created_by' => (int) $request->session()->get('user_id'),
                            'status' => 'upcoming',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
                $count = count($data);
                $request->session()->forget(['import_data', 'import_preview']);
                return back()->with('success', $count . ' auctions imported successfully!');
            }

            if ($request->hasFile('excel_file')) {
                $file = $request->file('excel_file');
                $ext = strtolower((string) $file->getClientOriginalExtension());
                if (! in_array($ext, ['csv', 'xlsx'], true)) {
                    return back()->withErrors(['excel_file' => 'Only CSV and XLSX files are allowed']);
                }
                if ($ext === 'xlsx') {
                    return back()->withErrors(['excel_file' => 'XLSX support requires PHPSpreadsheet library. Please use CSV format.']);
                }

                $rows = [];
                $handle = fopen($file->getRealPath(), 'r');
                if ($handle !== false) {
                    fgetcsv($handle);
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) >= 6) {
                            $participationFee = isset($row[6]) && trim((string) $row[6]) !== ''
                                ? (float) $row[6]
                                : $this->settingsService->getFloat('bid_participation_fee', 0);
                            $rows[] = [
                                'title' => (string) $row[0],
                                'description' => (string) $row[1],
                                'base_price' => (float) $row[2],
                                'min_increment' => (float) $row[3],
                                'start_datetime' => (string) $row[4],
                                'end_datetime' => (string) $row[5],
                                'emd_amount' => max(0, $participationFee),
                            ];
                        }
                    }
                    fclose($handle);
                }
                if (empty($rows)) {
                    return back()->withErrors(['excel_file' => 'No valid data found in file']);
                }
                $request->session()->put('import_data', $rows);
                $request->session()->put('import_preview', $rows);
                $preview = $rows;
            }
        }

        return view('admin.upload-excel', ['preview' => $preview]);
    }

    private function normalizeAuctionDateInputs(array $data): array
    {
        $data['start_datetime'] = Carbon::parse((string) $data['start_datetime'])->startOfDay()->format('Y-m-d H:i:s');
        $data['end_datetime'] = Carbon::parse((string) $data['end_datetime'])->startOfDay()->format('Y-m-d H:i:s');

        return $data;
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

    private function resolvePerPageValue(string $raw, int $default = 10): int
    {
        $v = strtolower(trim($raw));
        if ($v === 'all') {
            return 100000;
        }
        $i = (int) $v;
        return in_array($i, [10, 20, 50, 100], true) ? $i : $default;
    }

    private function logAudit(string $action, string $entityType, string $entityId, ?array $meta, Request $request): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }
        DB::table('audit_logs')->insert([
            'actor_user_id' => (int) $request->session()->get('user_id'),
            'actor_role' => 'admin',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => (string) $request->ip(),
            'meta' => $meta ? json_encode($meta, JSON_THROW_ON_ERROR) : null,
            'created_at' => now(),
        ]);
    }
}
