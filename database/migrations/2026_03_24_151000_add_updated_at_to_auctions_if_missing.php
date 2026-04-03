<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auctions')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table): void {
            if (! Schema::hasColumn('auctions', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('auctions')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table): void {
            if (Schema::hasColumn('auctions', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};

