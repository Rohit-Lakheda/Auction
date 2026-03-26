<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_messages')) {
            Schema::create('admin_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('subject', 255)->nullable();
                $table->text('message');
                $table->string('attachment_path', 500)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_message_recipients')) {
            Schema::create('admin_message_recipients', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('message_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('email_sent_at')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();
                $table->unique(['message_id', 'user_id']);
                $table->index(['user_id', 'is_read']);
            });
        }

        if (! Schema::hasTable('admin_message_replies')) {
            Schema::create('admin_message_replies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('message_id');
                $table->string('sender_role', 20);
                $table->unsignedBigInteger('sender_user_id')->nullable();
                $table->text('message');
                $table->string('attachment_path', 500)->nullable();
                $table->timestamps();
                $table->index(['message_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_message_replies')) {
            Schema::drop('admin_message_replies');
        }
        if (Schema::hasTable('admin_message_recipients')) {
            Schema::drop('admin_message_recipients');
        }
        if (Schema::hasTable('admin_messages')) {
            Schema::drop('admin_messages');
        }
    }
};

