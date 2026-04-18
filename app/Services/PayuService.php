<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayuService
{
    public function paymentUrl(): string
    {
        return (env('PAYU_MODE', 'test') === 'live')
            ? 'https://secure.payu.in/_payment'
            : 'https://test.payu.in/_payment';
    }

    public function merchantKey(): string
    {
        return (string) env('PAYU_MERCHANT_KEY', '');
    }

    public function generateHash(array $params): string
    {
        $salt = (string) env('PAYU_SALT', '');
        $hashString = $this->merchantKey().'|'
            .trim((string) ($params['txnid'] ?? '')).'|'
            .trim((string) ($params['amount'] ?? '')).'|'
            .trim((string) ($params['productinfo'] ?? '')).'|'
            .trim((string) ($params['firstname'] ?? '')).'|'
            .trim((string) ($params['email'] ?? '')).'|'
            .trim((string) ($params['udf1'] ?? '')).'|'
            .trim((string) ($params['udf2'] ?? '')).'|'
            .trim((string) ($params['udf3'] ?? '')).'|'
            .trim((string) ($params['udf4'] ?? '')).'|'
            .trim((string) ($params['udf5'] ?? '')).'|'
            .'|||||'.$salt;

        return strtolower(hash('sha512', $hashString));
    }

    public function verifyHash(array $response): bool
    {
        $salt = (string) env('PAYU_SALT', '');
        $receivedHash = strtolower(trim((string) ($response['hash'] ?? '')));
        $hashString = $salt.'|'
            .trim((string) ($response['status'] ?? '')).'|'
            .'|||||'
            .trim((string) ($response['udf5'] ?? '')).'|'
            .trim((string) ($response['udf4'] ?? '')).'|'
            .trim((string) ($response['udf3'] ?? '')).'|'
            .trim((string) ($response['udf2'] ?? '')).'|'
            .trim((string) ($response['udf1'] ?? '')).'|'
            .trim((string) ($response['email'] ?? '')).'|'
            .trim((string) ($response['firstname'] ?? '')).'|'
            .trim((string) ($response['productinfo'] ?? '')).'|'
            .trim((string) ($response['amount'] ?? '')).'|'
            .trim((string) ($response['txnid'] ?? '')).'|'
            .$this->merchantKey();

        return strtolower(hash('sha512', $hashString)) === $receivedHash;
    }

    /**
     * PayU merchant postservice (cancel / capture / verify commands).
     */
    public function postServiceUrl(): string
    {
        return (env('PAYU_MODE', 'test') === 'live')
            ? 'https://info.payu.in/merchant/postservice.php?form=2'
            : 'https://test.payu.in/merchant/postservice.php?form=2';
    }

    /**
     * Hash for commands where PayU expects: sha512(key|command|var1|salt)
     * Used by cancel_transaction and capture_transaction (per PayU docs).
     */
    public function hashForCommand(string $command, string $var1): string
    {
        $salt = (string) env('PAYU_SALT', '');
        $hashString = $this->merchantKey().'|'
            .trim($command).'|'
            .trim($var1).'|'
            .$salt;

        return strtolower(hash('sha512', $hashString));
    }

    /**
     * @return array{success: bool, status: ?int, message: string, raw: string, data?: array}
     */
    public function cancelTransaction(string $payuId, string $merchantTxnId): array
    {
        $command = 'cancel_transaction';
        $hash = $this->hashForCommand($command, $payuId);

        return $this->postPostService([
            'key' => $this->merchantKey(),
            'command' => $command,
            'hash' => $hash,
            'var1' => trim($payuId),
            'var2' => trim($merchantTxnId),
        ]);
    }

    /**
     * Capture a pre-authorized payment. Amount as decimal string (e.g. "10.00").
     *
     * @return array{success: bool, status: ?int, message: string, raw: string, data?: array}
     */
    public function captureTransaction(string $payuId, string $merchantTxnId, string $amount): array
    {
        $command = 'capture_transaction';
        $hash = $this->hashForCommand($command, $payuId);

        return $this->postPostService([
            'key' => $this->merchantKey(),
            'command' => $command,
            'hash' => $hash,
            'var1' => trim($payuId),
            'var2' => trim($merchantTxnId),
            'var3' => trim($amount),
        ]);
    }

    /**
     * @param  array<string, string>  $form
     * @return array{success: bool, status: ?int, message: string, raw: string, data?: array}
     */
    public function postPostService(array $form): array
    {
        try {
            $response = Http::asForm()
                ->timeout(60)
                ->post($this->postServiceUrl(), $form);

            $raw = $response->body();
            $data = null;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }

            $status = null;
            if (is_array($data) && isset($data['status'])) {
                $status = (int) $data['status'];
            }

            $message = '';
            if (is_array($data)) {
                $message = (string) ($data['msg'] ?? $data['message'] ?? '');
            }

            $success = $response->successful()
                && is_array($data)
                && isset($data['status'])
                && (int) $data['status'] === 1;

            if (! $success) {
                Log::warning('PayU postservice non-success.', [
                    'command' => $form['command'] ?? '',
                    'http_status' => $response->status(),
                    'body' => $raw,
                ]);
            }

            return [
                'success' => $success,
                'status' => $status,
                'message' => $message !== '' ? $message : ($raw !== '' ? $raw : 'Empty response'),
                'raw' => $raw,
                'data' => $data ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('PayU postservice exception.', ['error' => $e->getMessage(), 'command' => $form['command'] ?? '']);

            return [
                'success' => false,
                'status' => null,
                'message' => $e->getMessage(),
                'raw' => '',
            ];
        }
    }
}
