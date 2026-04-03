<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auctions')) {
            Schema::table('auctions', function (Blueprint $table): void {
                if (! Schema::hasColumn('auctions', 'auction_outcome')) {
                    $table->string('auction_outcome', 20)->nullable()->after('status'); // normal, failed, cancelled
                }
                if (! Schema::hasColumn('auctions', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('auction_outcome');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('auctions')) {
            Schema::table('auctions', function (Blueprint $table): void {
                foreach (['auction_outcome', 'cancelled_at'] as $column) {
                    if (Schema::hasColumn('auctions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

