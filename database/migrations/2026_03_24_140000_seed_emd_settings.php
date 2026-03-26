<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $updatedBy = (int) (DB::table('users')->orderBy('id')->value('id') ?? 1);

        $rows = [
            [
                'setting_key' => 'emd_default_amount',
                'setting_value' => '10000.00',
                'description' => 'Default EMD amount in INR',
            ],
            [
                'setting_key' => 'emd_penalty_percentage',
                'setting_value' => '25.00',
                'description' => 'Penalty percentage charged on default',
            ],
            [
                'setting_key' => 'emd_payment_window_hours',
                'setting_value' => '24',
                'description' => 'Payment time window in hours for current winner',
            ],
            [
                'setting_key' => 'emd_max_default_before_block',
                'setting_value' => '3',
                'description' => 'User gets blocked after this many defaults',
            ],
            [
                'setting_key' => 'emd_default_multiplier',
                'setting_value' => '1.00',
                'description' => 'Default multiplier applied on required EMD',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('settings')->updateOrInsert(
                ['setting_key' => $row['setting_key']],
                [
                    'setting_value' => $row['setting_value'],
                    'description' => $row['description'],
                    'updated_by' => $updatedBy,
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->whereIn('setting_key', [
                'emd_default_amount',
                'emd_penalty_percentage',
                'emd_payment_window_hours',
                'emd_max_default_before_block',
                'emd_default_multiplier',
            ])
            ->delete();
    }
};

