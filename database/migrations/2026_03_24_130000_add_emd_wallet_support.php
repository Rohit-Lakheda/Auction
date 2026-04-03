<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'mobile')) {
                    $table->string('mobile', 20)->nullable();
                }
                if (! Schema::hasColumn('users', 'wallet_balance')) {
                    $table->decimal('wallet_balance', 14, 2)->default(0);
                }
                if (! Schema::hasColumn('users', 'is_blocked')) {
                    $table->boolean('is_blocked')->default(false);
                }
                if (! Schema::hasColumn('users', 'default_count')) {
                    $table->unsignedInteger('default_count')->default(0);
                }
                if (! Schema::hasColumn('users', 'emd_multiplier')) {
                    $table->decimal('emd_multiplier', 6, 2)->default(1);
                }
            });
        }

        if (Schema::hasTable('auctions')) {
            Schema::table('auctions', function (Blueprint $table): void {
                if (! Schema::hasColumn('auctions', 'emd_amount')) {
                    $table->decimal('emd_amount', 14, 2)->default(0);
                }
                if (! Schema::hasColumn('auctions', 'payment_window_expires_at')) {
                    $table->timestamp('payment_window_expires_at')->nullable();
                }
                if (! Schema::hasColumn('auctions', 'winner_rank')) {
                    $table->unsignedTinyInteger('winner_rank')->nullable();
                }
                if (! Schema::hasColumn('auctions', 'top_bidders_json')) {
                    $table->json('top_bidders_json')->nullable();
                }
            });
        }

        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('type', 32);
                $table->decimal('amount', 14, 2);
                $table->string('reference_type', 50)->nullable();
                $table->string('reference_id', 100)->nullable();
                $table->string('status', 30)->default('success');
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (! Schema::hasTable('auction_participants')) {
            Schema::create('auction_participants', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('auction_id');
                $table->unsignedBigInteger('user_id');
                $table->boolean('emd_locked')->default(true);
                $table->decimal('locked_emd_amount', 14, 2)->default(0);
                $table->string('status', 30)->default('active');
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();
                $table->unique(['auction_id', 'user_id']);
                $table->index(['auction_id', 'status']);
            });
        }

        if (! Schema::hasTable('wallet_topups')) {
            Schema::create('wallet_topups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('transaction_id', 100)->unique();
                $table->decimal('amount', 14, 2);
                $table->string('status', 30)->default('pending');
                $table->string('gateway_transaction_id', 100)->nullable();
                $table->json('gateway_response')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_topups')) {
            Schema::drop('wallet_topups');
        }
        if (Schema::hasTable('auction_participants')) {
            Schema::drop('auction_participants');
        }
        if (Schema::hasTable('transactions')) {
            Schema::drop('transactions');
        }

        if (Schema::hasTable('auctions')) {
            Schema::table('auctions', function (Blueprint $table): void {
                foreach (['emd_amount', 'payment_window_expires_at', 'winner_rank', 'top_bidders_json'] as $col) {
                    if (Schema::hasColumn('auctions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                foreach (['mobile', 'wallet_balance', 'is_blocked', 'default_count', 'emd_multiplier'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

