<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to Payment Gateway...</title>
    <style>
        body { font-family: Arial, sans-serif; display:flex; justify-content:center; align-items:center; min-height:100vh; background:#f5f5f5; margin:0; }
        .loader { text-align:center; }
        .spinner { border:4px solid #f3f3f3; border-top:4px solid #1a237e; border-radius:50%; width:48px; height:48px; animation:spin 1s linear infinite; margin:0 auto 15px; }
        @keyframes spin { from { transform: rotate(0deg);} to { transform: rotate(360deg);} }
    </style>
</head>
<body>
<div class="loader">
    <div class="spinner"></div>
    <p>Redirecting to payment gateway...</p>
    @if(str_starts_with((string) ($paymentData['udf1'] ?? ''), 'REGISTRATION'))
        <p style="font-size:13px;color:#c62828;max-width:420px;margin:8px auto 0;">Registration fee is non-refundable. Please proceed only if you agree.</p>
    @elseif(str_starts_with((string) ($paymentData['udf1'] ?? ''), 'PARTICIPATION'))
        <p style="font-size:13px;color:#c62828;max-width:420px;margin:8px auto 0;">Participation fee is non-refundable. Please proceed only if you agree.</p>
    @elseif(str_starts_with((string) ($paymentData['udf1'] ?? ''), 'BID_PREAUTH'))
        <p style="font-size:13px;color:#1565c0;max-width:420px;margin:8px auto 0;">Bid security uses a <strong>credit card pre-authorization</strong> (hold). Use a supported credit card — net banking or debit will not hold funds the same way.</p>
    @endif
    <p style="font-size:12px;color:#666;">Please do not close this page</p>
</div>
<form id="payuForm" method="POST" action="{{ $paymentUrl }}">
    @foreach($paymentData as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>
<script>document.getElementById('payuForm').submit();</script>
</body>
</html>
