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

    /**
     * Bid pre-authorization on hosted checkout: append fields after generateHash().
     *
     * PayU expects enforce_paymethod (not enforced_payment) to restrict rails.
     * Net banking / UPI / wallets settle immediately and do not behave like card auth holds.
     *
     * @see https://docs.payu.in/v2/docs/enforce-pay-method-or-remove-category
     * @see https://docs.payu.in/docs/payu-hosted-integration-pre-authorize-payments
     */
    public function applyBidPreauthHostedFields(array $paymentData): array
    {
        $paymentData['pre_authorize'] = '1';
        $paymentData['enforce_paymethod'] = 'creditcard';
        $paymentData['pg'] = 'cc';
        $paymentData['bankcode'] = 'CC';
        $paymentData['drop_category'] = 'NB|DC|NEFTRTGS|EMI|CASH|BNPL|SODEXO';

        return $paymentData;
    }

    /**
     * Safe fields for logs (omit card numbers, hashes, CVV-related keys).
     *
     * @return array<string, mixed|string>
     */
    public function scrubPayuPayloadForLogging(array $data): array
    {
        $allow = [
            'txnid', 'mihpayid', 'status', 'unmappedstatus', 'unamappedstatus',
            'error', 'error_Message', 'field9', 'addedon', 'mode', 'bankcode',
            'PG_TYPE', 'amount', 'productinfo', 'firstname', 'email',
            'udf1', 'udf2', 'udf3', 'udf4', 'udf5', 'net_amount_debit',
            'payment_source',
        ];

        $out = [];
        foreach ($allow as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }

        return $out;
    }

    /**
     * User-visible explanation when pre-auth did not result in a card hold / bid.
     */
    public function bidPreauthExplanationForUser(array $data): string
    {
        $bank = trim((string) ($data['error_Message'] ?? ''));
        if ($bank !== '' && strcasecmp($bank, 'No Error') !== 0) {
            return $bank;
        }

        $field9 = trim((string) ($data['field9'] ?? ''));
        if ($field9 !== '' && stripos($field9, 'transaction completed successfully') === false) {
            return $field9;
        }

        $status = strtolower(trim((string) ($data['status'] ?? '')));
        $unmapped = strtolower(trim((string) ($data['unmappedstatus'] ?? $data['unamappedstatus'] ?? '')));

        if ($status === 'success' && $unmapped !== '' && $unmapped !== 'auth') {
            return 'Your card issuer or PayU did not create a pre-authorization hold (issuer status: '
                .($data['unmappedstatus'] ?? $data['unamappedstatus'] ?? $unmapped)
                .'). If PayU shows Bounced or Declined, the hold was refused — try another card or contact your bank. No bid was recorded and no amount should stay blocked.';
        }

        if ($status !== '' && $status !== 'success') {
            return 'Authorization did not succeed (PayU status: '.($data['status'] ?? 'unknown')
                .'). No bid was recorded.';
        }

        return 'Card pre-authorization did not complete. No bid was recorded.';
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
