<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) $request->session()->get('user_id');
        $filter = (string) $request->query('filter', 'all');
        $walletBalance = (float) (DB::table('users')->where('id', $userId)->value('wallet_balance') ?? 0);
        $lockedBalance = (float) (DB::table('auction_participants')
            ->where('user_id', $userId)
            ->where('emd_locked', 1)
            ->sum('locked_emd_amount') ?? 0);
        $query = DB::table('transactions')->where('user_id', $userId);
        $this->applyFilter($query, $filter);

        $transactions = $query
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();
        $transactions = $this->transformTransactions($transactions);

        return view('user.wallet', [
            'walletBalance' => $walletBalance,
            'lockedBalance' => $lockedBalance,
            'transactions' => $transactions,
            'activeFilter' => $filter,
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $userId = (int) $request->session()->get('user_id');
        $walletBalance = (float) (DB::table('users')->where('id', $userId)->value('wallet_balance') ?? 0);

        return response()->json([
            'success' => true,
            'wallet_balance' => $walletBalance,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $userId = (int) $request->session()->get('user_id');
        $filter = (string) $request->query('filter', 'all');
        $query = DB::table('transactions')->where('user_id', $userId);
        $this->applyFilter($query, $filter);

        $transactions = $query
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        $transactions = $this->transformTransactions($transactions);

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $userId = (int) $request->session()->get('user_id');
        $filter = (string) $request->query('filter', 'all');
        $query = DB::table('transactions')->where('user_id', $userId);
        $this->applyFilter($query, $filter);

        $transactions = $this->transformTransactions(
            $query->orderByDesc('created_at')->limit(1000)->get()
        );

        $fileName = 'wallet_history_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($transactions): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['Date', 'Type', 'Direction', 'Amount', 'Status', 'Reference', 'Remarks']);
            foreach ($transactions as $tx) {
                fputcsv($out, [
                    $tx->created_at,
                    $tx->type,
                    $tx->direction,
                    number_format((float) $tx->amount, 2, '.', ''),
                    strtoupper((string) $tx->status),
                    $tx->reference_display,
                    $tx->remarks ?? '',
                ]);
            }
            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function applyFilter($query, string $filter): void
    {
        $typeMap = [
            'credit' => ['Deposit', 'Refund'],
            'debit' => ['Deduction', 'Penalty'],
            'deposit' => ['Deposit'],
            'refund' => ['Refund'],
            'deduction' => ['Deduction'],
            'penalty' => ['Penalty'],
        ];

        if (isset($typeMap[$filter])) {
            $query->whereIn('type', $typeMap[$filter]);
        }
    }

    private function transformTransactions(Collection $transactions): Collection
    {
        $auctionIds = $transactions
            ->filter(fn ($t) => ($t->reference_type ?? null) === 'auction' && is_numeric($t->reference_id))
            ->pluck('reference_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $auctionTitles = empty($auctionIds)
            ? collect()
            : DB::table('auctions')->whereIn('id', $auctionIds)->pluck('title', 'id');

        return $transactions->map(function ($tx) use ($auctionTitles) {
            $isCredit = in_array((string) $tx->type, ['Deposit', 'Refund'], true);
            $direction = $isCredit ? 'Credit' : 'Debit';

            $referenceDisplay = '-';
            if (($tx->reference_type ?? null) === 'auction' && is_numeric($tx->reference_id)) {
                $auctionId = (int) $tx->reference_id;
                $auctionTitle = (string) ($auctionTitles[$auctionId] ?? '');
                $referenceDisplay = 'Auction #' . $auctionId . ($auctionTitle !== '' ? ' - ' . $auctionTitle : '');
            } elseif (($tx->reference_type ?? null) === 'wallet_topup') {
                $referenceDisplay = 'Wallet Top-up - ' . (string) ($tx->reference_id ?? '-');
            } elseif (! empty($tx->reference_type) || ! empty($tx->reference_id)) {
                $referenceDisplay = trim((string) ($tx->reference_type . ' ' . $tx->reference_id));
            }

            $tx->direction = $direction;
            $tx->reference_display = $referenceDisplay;
            $tx->amount_sign = $isCredit ? '+' : '-';
            $tx->amount_color = $isCredit ? '#2e7d32' : '#c62828';

            return $tx;
        });
    }
}

