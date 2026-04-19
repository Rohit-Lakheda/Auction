<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_logs')) {
            Schema::create('payment_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('transaction_id')->index();
                $table->unsignedBigInteger('auction_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('event', 80);
                $table->decimal('amount', 14, 2)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table): void {
                $table->id();
                $table->string('transaction_id')->unique();
                $table->unsignedBigInteger('auction_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->decimal('amount', 14, 2);
                $table->string('status', 40)->default('pending');
                $table->string('payment_kind', 40)->nullable()->index();
                $table->string('payu_transaction_id')->nullable();
                $table->string('response_message')->nullable();
                $table->json('response_data')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('payment_transactions', 'payment_kind')) {
                    $table->string('payment_kind', 40)->nullable();
                }
                if (! Schema::hasColumn('payment_transactions', 'payu_transaction_id')) {
                    $table->string('payu_transaction_id')->nullable();
                }
                if (! Schema::hasColumn('payment_transactions', 'response_message')) {
                    $table->string('response_message')->nullable();
                }
                if (! Schema::hasColumn('payment_transactions', 'response_data')) {
                    $table->json('response_data')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
        // Do not drop payment_transactions — may pre-exist from external DDL.
    }
};
