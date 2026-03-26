<?php

namespace App\Services;

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
        $hashString = $this->merchantKey() . '|'
            . trim((string) ($params['txnid'] ?? '')) . '|'
            . trim((string) ($params['amount'] ?? '')) . '|'
            . trim((string) ($params['productinfo'] ?? '')) . '|'
            . trim((string) ($params['firstname'] ?? '')) . '|'
            . trim((string) ($params['email'] ?? '')) . '|'
            . trim((string) ($params['udf1'] ?? '')) . '|'
            . trim((string) ($params['udf2'] ?? '')) . '|'
            . trim((string) ($params['udf3'] ?? '')) . '|'
            . trim((string) ($params['udf4'] ?? '')) . '|'
            . trim((string) ($params['udf5'] ?? '')) . '|'
            . '|||||' . $salt;

        return strtolower(hash('sha512', $hashString));
    }

    public function verifyHash(array $response): bool
    {
        $salt = (string) env('PAYU_SALT', '');
        $receivedHash = strtolower(trim((string) ($response['hash'] ?? '')));
        $hashString = $salt . '|'
            . trim((string) ($response['status'] ?? '')) . '|'
            . '|||||'
            . trim((string) ($response['udf5'] ?? '')) . '|'
            . trim((string) ($response['udf4'] ?? '')) . '|'
            . trim((string) ($response['udf3'] ?? '')) . '|'
            . trim((string) ($response['udf2'] ?? '')) . '|'
            . trim((string) ($response['udf1'] ?? '')) . '|'
            . trim((string) ($response['email'] ?? '')) . '|'
            . trim((string) ($response['firstname'] ?? '')) . '|'
            . trim((string) ($response['productinfo'] ?? '')) . '|'
            . trim((string) ($response['amount'] ?? '')) . '|'
            . trim((string) ($response['txnid'] ?? '')) . '|'
            . $this->merchantKey();

        return strtolower(hash('sha512', $hashString)) === $receivedHash;
    }
}
