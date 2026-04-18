<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_preauth_holds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('auction_id');
            $table->unsignedBigInteger('user_id');
            $table->string('transaction_id')->unique();
            $table->string('payu_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('status', 32)->default('pending_redirect');
            $table->json('response_data')->nullable();
            $table->timestamps();

            $table->index(['auction_id', 'user_id']);
            $table->index(['auction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_preauth_holds');
    }
};
