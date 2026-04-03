<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BlacklistService
{
    public function isIdentityBlocked(array $identity): bool
    {
        if (! Schema::hasTable('blacklisted_users')) {
            return false;
        }

        $query = DB::table('blacklisted_users')
            ->where('is_active', 1)
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        $hasAny = false;
        foreach (['user_id', 'email', 'mobile', 'pan_card_number', 'device_fingerprint', 'ip_address'] as $key) {
            $value = $identity[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $hasAny = true;
            $query->orWhere($key, $value);
        }

        return $hasAny ? $query->exists() : false;
    }

    public function blacklistIdentity(array $identity, string $reason): void
    {
        if (! Schema::hasTable('blacklisted_users')) {
            return;
        }

        DB::table('blacklisted_users')->insert([
            'user_id' => $identity['user_id'] ?? null,
            'email' => $identity['email'] ?? null,
            'mobile' => $identity['mobile'] ?? null,
            'pan_card_number' => $identity['pan_card_number'] ?? null,
            'device_fingerprint' => $identity['device_fingerprint'] ?? null,
            'ip_address' => $identity['ip_address'] ?? null,
            'reason' => $reason,
            'blacklisted_at' => now(),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function getFingerprint(Request $request): string
    {
        $explicit = trim((string) $request->input('device_fingerprint', ''));
        if ($explicit !== '') {
            return substr($explicit, 0, 128);
        }

        // Fallback fingerprint from headers for non-JS flows.
        return substr(hash('sha256', implode('|', [
            (string) $request->userAgent(),
            (string) $request->ip(),
            (string) $request->header('accept-language'),
        ])), 0, 64);
    }
}

