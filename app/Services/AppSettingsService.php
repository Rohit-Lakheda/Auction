<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AppSettingsService
{
    public function getFloat(string $key, float $fallback): float
    {
        $value = DB::table('settings')->where('setting_key', $key)->value('setting_value');
        if ($value === null || $value === '') {
            return $fallback;
        }
        return (float) $value;
    }

    public function getInt(string $key, int $fallback): int
    {
        $value = DB::table('settings')->where('setting_key', $key)->value('setting_value');
        if ($value === null || $value === '') {
            return $fallback;
        }
        return (int) $value;
    }

    public function set(string $key, string $value, string $description, int $updatedBy): void
    {
        DB::table('settings')->updateOrInsert(
            ['setting_key' => $key],
            [
                'setting_value' => $value,
                'description' => $description,
                'updated_by' => $updatedBy,
                'updated_at' => now(),
            ]
        );
    }
}

