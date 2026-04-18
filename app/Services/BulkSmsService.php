<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BulkSmsService
{
    /**
     * Send OTP SMS to a 10-digit Indian mobile number (no country prefix).
     */
    public function sendOtp(string $mobile10Digits, string $otp): bool
    {
        $mobile10Digits = preg_replace('/[^0-9]/', '', $mobile10Digits) ?? '';
        if (strlen($mobile10Digits) !== 10) {
            Log::warning('BulkSms: invalid mobile length', ['mobile' => $mobile10Digits]);

            return false;
        }

        if (! config('sms.enabled')) {
            return false;
        }

        $feedId = config('sms.feed_id');
        $username = config('sms.username');
        $password = config('sms.password');
        $templateId = config('sms.template_id');
        $senderId = config('sms.sender_id');

        if ($feedId === null || $feedId === '' || $username === null || $username === '' || $password === null || $password === '') {
            Log::warning('BulkSms: missing feed_id, username, or password in config');

            return false;
        }

        if ($templateId === null || $templateId === '' || $senderId === null || $senderId === '') {
            Log::warning('BulkSms: template_id or sender_id missing in config');

            return false;
        }

        $text = str_replace('{otp}', $otp, (string) config('sms.text_template'));

        // MyToday: "Indian mobile number must be prefix with 91" (see bulkpush.mytoday.com/BulkSms/)
        $to = $this->formatIndianMsisdnForMyToday($mobile10Digits);

        $payload = [
            'feedid' => (string) $feedId,
            'username' => (string) $username,
            'password' => (string) $password,
            'To' => $to,
            'Text' => $text,
            'templateid' => (string) $templateId,
            'senderid' => (string) $senderId,
        ];

        $entityId = config('sms.entity_id');
        if ($entityId !== null && $entityId !== '') {
            $payload['entityid'] = (string) $entityId;
        }

        $async = config('sms.async');
        if ($async !== null && $async !== '') {
            $payload['async'] = (string) $async;
        }

        $url = (string) config('sms.url');

        try {
            $response = Http::timeout(45)->asForm()->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('BulkSms HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $body = $response->body();
            Log::info('BulkSms response', [
                'to_suffix' => substr($to, -4),
                'body' => substr($body, 0, 500),
            ]);

            if ($this->bulkSmsResponseLooksLikeFailure($body)) {
                Log::warning('BulkSms: gateway returned success HTTP but body suggests failure', [
                    'body' => substr($body, 0, 1000),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('BulkSms exception', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * MyToday expects Indian subscribers as 91 + 10 digits (not bare 10 digits).
     */
    private function formatIndianMsisdnForMyToday(string $digits10): string
    {
        $d = preg_replace('/\D/', '', $digits10) ?? '';
        if (strlen($d) === 10) {
            return '91'.$d;
        }
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            return $d;
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            return '91'.substr($d, 1);
        }

        return $d;
    }

    /**
     * Best-effort: some gateways return HTTP 200 with an error payload in XML.
     */
    private function bulkSmsResponseLooksLikeFailure(string $body): bool
    {
        $u = strtoupper($body);

        return str_contains($u, '<ERROR')
            || str_contains($u, '</ERROR>')
            || str_contains($u, 'STATUS=\'FAILED\'')
            || str_contains($u, 'STATUS="FAILED"')
            || preg_match('/\bERR(CODE|OR)\s*=/i', $body) === 1;
    }
}
