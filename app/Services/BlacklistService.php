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

        $matchers = [];
        foreach (['user_id', 'email', 'mobile', 'pan_card_number', 'device_fingerprint', 'ip_address'] as $key) {
            $value = $identity[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $matchers[$key] = $key === 'email'
                ? strtolower(trim((string) $value))
                : $value;
        }

        if ($matchers === []) {
            return false;
        }

        $query->where(function ($q) use ($matchers): void {
            foreach ($matchers as $key => $value) {
                $q->orWhere($key, $value);
            }
        });

        return $query->exists();
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

    /**
     * @return array{emails: list<string>, mobiles: list<string>, pans: list<string>}
     */
    public function collectKnownIdentitiesForUser(int $userId): array
    {
        $emails = [];
        $mobiles = [];
        $pans = [];

        $user = DB::table('users')->where('id', $userId)->first();
        if ($user) {
            if (! empty($user->email)) {
                $emails[] = strtolower(trim((string) $user->email));
            }
            if (Schema::hasColumn('users', 'mobile') && ! empty($user->mobile)) {
                $mobiles[] = preg_replace('/[^0-9]/', '', (string) $user->mobile);
            }
        }

        if ($user && Schema::hasTable('registration')) {
            $reg = null;
            if (Schema::hasColumn('registration', 'user_id')) {
                $reg = DB::table('registration')->where('user_id', $userId)->first();
            }
            if (! $reg && ! empty($user->email)) {
                $reg = DB::table('registration')->where('email', $user->email)->first();
            }
            if ($reg) {
                if (! empty($reg->email)) {
                    $emails[] = strtolower(trim((string) $reg->email));
                }
                if (! empty($reg->mobile)) {
                    $mobiles[] = preg_replace('/[^0-9]/', '', (string) $reg->mobile);
                }
                if (! empty($reg->pan_card_number)) {
                    $pans[] = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $reg->pan_card_number));
                }
            }
        }

        if (Schema::hasTable('user_identity_change_logs')) {
            $logs = DB::table('user_identity_change_logs')->where('user_id', $userId)->get();
            foreach ($logs as $log) {
                $field = (string) $log->field_name;
                if ($field === 'email') {
                    if (! empty($log->old_value)) {
                        $emails[] = strtolower(trim((string) $log->old_value));
                    }
                    if (! empty($log->new_value)) {
                        $emails[] = strtolower(trim((string) $log->new_value));
                    }
                }
                if ($field === 'mobile') {
                    if (! empty($log->old_value)) {
                        $mobiles[] = preg_replace('/[^0-9]/', '', (string) $log->old_value);
                    }
                    if (! empty($log->new_value)) {
                        $mobiles[] = preg_replace('/[^0-9]/', '', (string) $log->new_value);
                    }
                }
            }
        }

        $emails = array_values(array_unique(array_filter($emails)));
        $mobiles = array_values(array_unique(array_filter($mobiles)));
        $pans = array_values(array_unique(array_filter($pans)));

        return ['emails' => $emails, 'mobiles' => $mobiles, 'pans' => $pans];
    }

    public function blacklistAllKnownIdentitiesForUser(int $userId, string $reason): void
    {
        if (! Schema::hasTable('blacklisted_users')) {
            return;
        }

        $ids = $this->collectKnownIdentitiesForUser($userId);
        $now = now();

        foreach ($ids['emails'] as $email) {
            DB::table('blacklisted_users')->insert([
                'user_id' => $userId,
                'email' => $email,
                'mobile' => null,
                'pan_card_number' => null,
                'device_fingerprint' => null,
                'ip_address' => null,
                'reason' => $reason,
                'blacklisted_at' => $now,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        foreach ($ids['mobiles'] as $mobile) {
            DB::table('blacklisted_users')->insert([
                'user_id' => $userId,
                'email' => null,
                'mobile' => $mobile,
                'pan_card_number' => null,
                'device_fingerprint' => null,
                'ip_address' => null,
                'reason' => $reason,
                'blacklisted_at' => $now,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        foreach ($ids['pans'] as $pan) {
            DB::table('blacklisted_users')->insert([
                'user_id' => $userId,
                'email' => null,
                'mobile' => null,
                'pan_card_number' => $pan,
                'device_fingerprint' => null,
                'ip_address' => null,
                'reason' => $reason,
                'blacklisted_at' => $now,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function deactivateBlacklistForUserId(int $userId): void
    {
        if (! Schema::hasTable('blacklisted_users')) {
            return;
        }

        DB::table('blacklisted_users')->where('user_id', $userId)->update(['is_active' => 0, 'updated_at' => now()]);
    }
}

