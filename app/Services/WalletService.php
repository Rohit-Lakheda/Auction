<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WalletService
{
    public function creditDeposit(int $userId, float $amount, string $referenceId, string $status = 'success'): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::table('users')->where('id', $userId)->increment('wallet_balance', $amount);
        $this->record($userId, 'Deposit', $amount, 'wallet_topup', $referenceId, $status, 'Wallet top-up');
    }

    public function lockEmd(int $userId, int $auctionId, float $amount): void
    {
        DB::table('users')->where('id', $userId)->decrement('wallet_balance', $amount);
        $this->record($userId, 'Deduction', $amount, 'auction', (string) $auctionId, 'success', 'EMD locked for auction');
    }

    public function refundEmd(int $userId, int $auctionId, float $amount, string $remarks = 'EMD refund'): void
    {
        if ($amount <= 0) {
            return;
        }
        DB::table('users')->where('id', $userId)->increment('wallet_balance', $amount);
        $this->record($userId, 'Refund', $amount, 'auction', (string) $auctionId, 'success', $remarks);
    }

    public function applyPenalty(int $userId, int $auctionId, float $amount): void
    {
        $this->record($userId, 'Penalty', $amount, 'auction', (string) $auctionId, 'success', 'Penalty on payment default');
    }

    public function record(
        int $userId,
        string $type,
        float $amount,
        ?string $referenceType = null,
        ?string $referenceId = null,
        string $status = 'success',
        ?string $remarks = null
    ): void {
        DB::table('transactions')->insert([
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'status' => $status,
            'remarks' => $remarks,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

