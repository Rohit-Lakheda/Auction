<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('subject');
                $table->text('message');
                $table->string('status', 30)->default('open'); // open, in_progress, resolved, closed
                $table->string('priority', 20)->default('normal');
                $table->string('category', 50)->nullable();
                $table->string('attachment_path')->nullable();
                $table->text('admin_reply')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index(['status', 'priority']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('support_tickets')) {
            Schema::drop('support_tickets');
        }
    }
};

