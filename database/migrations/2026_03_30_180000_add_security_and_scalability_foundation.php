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
                if (! Schema::hasColumn('users', 'must_reset_password')) {
                    $table->boolean('must_reset_password')->default(false);
                }
                if (! Schema::hasColumn('users', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable();
                }
                if (! Schema::hasColumn('users', 'last_login_ip')) {
                    $table->string('last_login_ip', 45)->nullable();
                }
            });
        }

        if (! Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->decimal('available_balance', 14, 2)->default(0);
                $table->decimal('locked_balance', 14, 2)->default(0);
                $table->timestamps();
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('blacklisted_users')) {
            Schema::create('blacklisted_users', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('email')->nullable();
                $table->string('mobile', 20)->nullable();
                $table->string('pan_card_number', 20)->nullable();
                $table->string('device_fingerprint', 128)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('reason', 255)->nullable();
                $table->timestamp('blacklisted_at')->useCurrent();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['user_id', 'is_active']);
                $table->index(['email', 'is_active']);
                $table->index(['mobile', 'is_active']);
                $table->index(['pan_card_number', 'is_active']);
                $table->index(['device_fingerprint', 'is_active']);
                $table->index(['ip_address', 'is_active']);
            });
        }

        if (! Schema::hasTable('bid_logs')) {
            Schema::create('bid_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('auction_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->decimal('amount', 14, 2)->nullable();
                $table->string('event_type', 50); // placed, rejected, auto_extended, etc.
                $table->string('ip_address', 45)->nullable();
                $table->string('device_fingerprint', 128)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['auction_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
                $table->index('event_type');
            });
        }

        if (! Schema::hasTable('notification_logs')) {
            Schema::create('notification_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('channel', 20); // email, in_app, sms
                $table->string('type', 50); // winner_alert, payment_reminder, etc.
                $table->string('status', 30)->default('queued');
                $table->string('subject')->nullable();
                $table->text('message')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index(['channel', 'status']);
                $table->index('type');
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('actor_role', 30)->nullable();
                $table->string('action', 100);
                $table->string('entity_type', 60)->nullable();
                $table->string('entity_id', 60)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('device_fingerprint', 128)->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['actor_user_id', 'created_at']);
                $table->index(['entity_type', 'entity_id']);
                $table->index('action');
            });
        }
    }

    public function down(): void
    {
        foreach (['audit_logs', 'notification_logs', 'bid_logs', 'blacklisted_users', 'wallets'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                foreach (['must_reset_password', 'last_login_at', 'last_login_ip'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

