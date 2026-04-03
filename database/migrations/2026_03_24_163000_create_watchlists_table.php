<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('watchlists')) {
            return;
        }

        Schema::create('watchlists', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('auction_id');
            $table->timestamps();
            $table->unique(['user_id', 'auction_id']);
            $table->index(['auction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlists');
    }
};

