<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmdAuctionService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly AppSettingsService $settingsService,
        private readonly BlacklistService $blacklistService
    )
    {
    }

    public function joinAuction(int $auctionId, int $userId): array
    {
        return DB::transaction(function () use ($auctionId, $userId): array {
            $auction = DB::table('auctions')->where('id', $auctionId)->lockForUpdate()->first();
            if (! $auction || $auction->status !== 'active') {
                throw new RuntimeException('Auction is not active.');
            }

            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            if (! $user) {
                throw new RuntimeException('User not found.');
            }
            if ((int) ($user->is_blocked ?? 0) === 1) {
                throw new RuntimeException('Your account is blocked from bidding.');
            }

            $existing = DB::table('auction_participants')
                ->where('auction_id', $auctionId)
                ->where('user_id', $userId)
                ->first();
            if ($existing) {
                return ['already_joined' => true, 'locked_emd_amount' => (float) $existing->locked_emd_amount];
            }

            $requiredEmd = $this->requiredEmd($auction, $user);
            $walletBalance = (float) ($user->wallet_balance ?? 0);
            if ($walletBalance < $requiredEmd) {
                throw new RuntimeException('Insufficient wallet balance for EMD.');
            }

            $this->walletService->lockEmd($userId, $auctionId, $requiredEmd);
            DB::table('auction_participants')->insert([
                'auction_id' => $auctionId,
                'user_id' => $userId,
                'emd_locked' => 1,
                'locked_emd_amount' => $requiredEmd,
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['already_joined' => false, 'locked_emd_amount' => $requiredEmd];
        });
    }

    public function placeBid(int $auctionId, int $userId, float $bidAmount): array
    {
        return DB::transaction(function () use ($auctionId, $userId, $bidAmount): array {
            $auction = DB::table('auctions')->where('id', $auctionId)->lockForUpdate()->first();
            if (! $auction || $auction->status !== 'active') {
                throw new RuntimeException('Auction not found or not active.');
            }

            $isParticipant = DB::table('auction_participants')
                ->where('auction_id', $auctionId)
                ->where('user_id', $userId)
                ->where('emd_locked', 1)
                ->where('status', 'active')
                ->exists();
            if (! $isParticipant) {
                throw new RuntimeException('Join auction and lock EMD before bidding.');
            }

            $currentBid = (float) (DB::table('bids')->where('auction_id', $auctionId)->max('amount') ?? 0);
            if ($currentBid <= 0) {
                $currentBid = (float) $auction->base_price;
            }
            $minNextBid = $currentBid + (float) $auction->min_increment;
            if ($bidAmount < $minNextBid) {
                throw new RuntimeException('Bid must be at least ₹' . number_format($minNextBid, 2));
            }

            DB::table('bids')->insert([
                'auction_id' => $auctionId,
                'user_id' => $userId,
                'amount' => $bidAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $top = $this->getTopBidders($auctionId);
            DB::table('auctions')->where('id', $auctionId)->update([
                'top_bidders_json' => json_encode($top, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return ['min_next_bid' => $bidAmount + (float) $auction->min_increment, 'top_bidders' => $top];
        });
    }

    public function getAuctionStatus(int $auctionId, int $userId): array
    {
        $auction = DB::table('auctions')->where('id', $auctionId)->first();
        if (! $auction) {
            throw new RuntimeException('Auction not found.');
        }

        $topBidders = $this->getTopBidders($auctionId);
        $rank = null;
        foreach ($topBidders as $idx => $entry) {
            if ((int) $entry['user_id'] === $userId) {
                $rank = $idx + 1;
                break;
            }
        }

        $statusMessage = match ($rank) {
            1 => 'You are highest bidder',
            2 => 'You are next in line (H2), please wait',
            3 => 'You are next in line (H3), please wait',
            default => 'You are participating in this auction',
        };

        return [
            'auction_id' => $auctionId,
            'auction_status' => $auction->status,
            'payment_status' => $auction->payment_status ?? 'pending',
            'payment_window_expires_at' => $auction->payment_window_expires_at,
            'top_bidders' => $topBidders,
            'your_rank' => $rank,
            'message' => $statusMessage,
        ];
    }

    public function completeWinnerPayment(int $auctionId, int $userId): void
    {
        DB::transaction(function () use ($auctionId, $userId): void {
            $auction = DB::table('auctions')->where('id', $auctionId)->lockForUpdate()->first();
            if (! $auction) {
                throw new RuntimeException('Auction not found.');
            }

            $top = $this->getTopBidders($auctionId);
            if (empty($top)) {
                throw new RuntimeException('No bids found.');
            }

            $winner = $top[0];
            if ((int) $winner['user_id'] !== $userId) {
                throw new RuntimeException('Only current top bidder can complete payment.');
            }

            DB::table('auctions')->where('id', $auctionId)->update([
                'status' => 'completed',
                'winner_user_id' => $userId,
                'winner_rank' => 1,
                'final_price' => (float) $winner['amount'],
                'payment_status' => 'paid',
                'updated_at' => now(),
            ]);

            $this->releaseRefundsForNonDefaulters($auctionId, [$userId]);
        });
    }

    public function releaseUserEmdIfEligible(int $auctionId, int $userId): array
    {
        return DB::transaction(function () use ($auctionId, $userId): array {
            $auction = DB::table('auctions')->where('id', $auctionId)->lockForUpdate()->first();
            if (! $auction) {
                throw new RuntimeException('Auction not found.');
            }

            $participant = DB::table('auction_participants')
                ->where('auction_id', $auctionId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();
            if (! $participant) {
                throw new RuntimeException('You are not a participant in this auction.');
            }
            if ((int) $participant->emd_locked !== 1) {
                return ['released' => false, 'message' => 'EMD is already released.'];
            }
            if ((int) ($auction->winner_user_id ?? 0) === $userId && ($auction->payment_status ?? '') === 'paid') {
                DB::table('auction_participants')->where('id', $participant->id)->update([
                    'emd_locked' => 0,
                    'status' => 'winner',
                    'updated_at' => now(),
                ]);
                return ['released' => false, 'message' => 'Winner EMD is adjusted against final payment.'];
            }

            $ended = in_array((string) $auction->status, ['closed', 'completed'], true)
                || now()->greaterThanOrEqualTo($auction->end_datetime);
            if (! $ended) {
                throw new RuntimeException('Auction has not ended yet.');
            }

            $amount = (float) $participant->locked_emd_amount;
            $this->walletService->refundEmd($userId, $auctionId, $amount);
            DB::table('auction_participants')->where('id', $participant->id)->update([
                'emd_locked' => 0,
                'status' => 'lost',
                'updated_at' => now(),
            ]);
            return ['released' => true, 'amount' => $amount, 'message' => 'EMD refunded successfully.'];
        });
    }

    public function markTopBidderDefaultAndPromote(int $auctionId): void
    {
        DB::transaction(function () use ($auctionId): void {
            $auction = DB::table('auctions')->where('id', $auctionId)->lockForUpdate()->first();
            if (! $auction) {
                throw new RuntimeException('Auction not found.');
            }

            $top = $this->getTopBidders($auctionId);
            if (empty($top)) {
                DB::table('auctions')->where('id', $auctionId)->update([
                    'status' => 'closed',
                    'auction_outcome' => 'failed',
                    'winner_user_id' => null,
                    'winner_rank' => null,
                    'updated_at' => now(),
                ]);
                return;
            }

            $defaulter = $top[0];
            $defaulterId = (int) $defaulter['user_id'];

            // [EMD DISABLED] Skipping auction_participants lookup, wallet refund, and penalty —
            // no EMD was locked so there is nothing to refund or penalise financially.

            // Increment default count and block if threshold reached (default: 3)
            DB::table('users')->where('id', $defaulterId)->increment('default_count', 1);
            $defaults = (int) (DB::table('users')->where('id', $defaulterId)->value('default_count') ?? 0);
            if ($defaults >= 3) {
                DB::table('users')->where('id', $defaulterId)->update(['is_blocked' => 1, 'updated_at' => now()]);
                $identity = [
                    'user_id' => $defaulterId,
                    'email' => (string) (DB::table('users')->where('id', $defaulterId)->value('email') ?? ''),
                    'ip_address' => null,
                    'device_fingerprint' => null,
                ];
                $this->blacklistService->blacklistIdentity($identity, 'Auction payment default threshold reached');
            }

            // Promote next eligible bidder
            $remaining = array_values(array_filter(
                $top,
                static fn (array $row): bool => (int) $row['user_id'] !== $defaulterId
            ));

            if (empty($remaining)) {
                DB::table('auctions')->where('id', $auctionId)->update([
                    'status' => 'closed',
                    'auction_outcome' => 'failed',
                    'winner_user_id' => null,
                    'winner_rank' => null,
                    'updated_at' => now(),
                ]);
                return;
            }

            DB::table('auctions')->where('id', $auctionId)->update([
                'winner_user_id' => (int) $remaining[0]['user_id'],
                'winner_rank' => (int) ($auction->winner_rank ?? 1) + 1,
                'final_price' => (float) $remaining[0]['amount'],
                'payment_window_expires_at' => now()->addHours(
                    $this->settingsService->getInt('emd_payment_window_hours', (int) config('emd.payment_window_hours', 24))
                ),
                'payment_status' => 'pending',
                'top_bidders_json' => json_encode($remaining, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }

    private function requiredEmd(object $auction, object $user): float
    {
        $baseEmd = (float) (($auction->emd_amount ?? 0) ?: $this->settingsService->getFloat('emd_default_amount', (float) config('emd.default_emd_amount', 10000)));
        $multiplier = max(
            1,
            (float) ($user->emd_multiplier ?? $this->settingsService->getFloat('emd_default_multiplier', (float) config('emd.default_emd_multiplier', 1)))
        );
        return round($baseEmd * $multiplier, 2);
    }

    private function getTopBidders(int $auctionId): array
    {
        return DB::table('bids')
            ->join('users', 'users.id', '=', 'bids.user_id')
            ->where('auction_id', $auctionId)
            ->selectRaw('bids.user_id, users.name as bidder_name, MAX(amount) as amount')
            ->groupBy('bids.user_id', 'users.name')
            ->orderByDesc('amount')
            ->limit(3)
            ->get()
            ->map(fn (object $row): array => [
                'user_id' => (int) $row->user_id,
                'bidder_name' => (string) ($row->bidder_name ?? 'Unknown'),
                'amount' => (float) $row->amount,
            ])
            ->values()
            ->all();
    }

    private function releaseRefundsForNonDefaulters(int $auctionId, array $excludeUserIds = []): void
    {
        $participants = DB::table('auction_participants')
            ->where('auction_id', $auctionId)
            ->where('emd_locked', 1)
            ->when(! empty($excludeUserIds), fn ($q) => $q->whereNotIn('user_id', $excludeUserIds))
            ->get();

        foreach ($participants as $participant) {
            $this->walletService->refundEmd((int) $participant->user_id, $auctionId, (float) $participant->locked_emd_amount);
            DB::table('auction_participants')->where('id', $participant->id)->update([
                'emd_locked' => 0,
                'status' => 'lost',
                'updated_at' => now(),
            ]);
        }
    }
}

