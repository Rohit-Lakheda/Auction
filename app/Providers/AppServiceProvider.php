<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyAdminEmailSettings();
    }

    private function applyAdminEmailSettings(): void
    {
        try {
            if (! Schema::hasTable('email_settings')) {
                return;
            }

            $settings = DB::table('email_settings')
                ->where('is_active', 1)
                ->latest('updated_at')
                ->first();

            if (! $settings) {
                return;
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => (string) ($settings->smtp_host ?? config('mail.mailers.smtp.host')),
                'mail.mailers.smtp.port' => (int) ($settings->smtp_port ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.username' => (string) ($settings->smtp_username ?? config('mail.mailers.smtp.username')),
                'mail.mailers.smtp.password' => (string) ($settings->smtp_password ?? config('mail.mailers.smtp.password')),
                'mail.mailers.smtp.scheme' => (string) ($settings->encryption ?? config('mail.mailers.smtp.scheme')),
                'mail.from.address' => (string) ($settings->from_email ?? config('mail.from.address')),
                'mail.from.name' => (string) ($settings->from_name ?? config('mail.from.name')),
            ]);
        } catch (\Throwable) {
            // Keep default .env mail settings if DB settings are unavailable.
        }
    }
}
