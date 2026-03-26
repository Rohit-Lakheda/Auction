<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
        .header { border-bottom: 2px solid #1a237e; margin-bottom: 18px; padding-bottom: 10px; }
        .title { font-size: 20px; color: #1a237e; margin: 0; }
        .sub { color: #666; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        td, th { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f7ff; }
        .right { text-align: right; }
        .total { font-weight: bold; background: #f9fafc; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Registration Invoice</h1>
        <div class="sub">Invoice No: {{ $data['invoice_no'] }} | Issued: {{ $data['issued_at'] }}</div>
    </div>

    <p><strong>Customer:</strong> {{ $data['customer_name'] }}</p>
    <p><strong>Email:</strong> {{ $data['customer_email'] }}</p>
    <p><strong>Registration ID:</strong> {{ $data['registration_id'] }}</p>
    <p><strong>Registration Type:</strong> {{ $data['registration_type'] }}</p>
    <p><strong>Transaction:</strong> {{ $data['transaction_id'] }}</p>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="right">Qty</th>
                <th class="right">Amount (INR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Registration Fee</td>
                <td class="right">1</td>
                <td class="right">{{ number_format((float) $data['amount'], 2) }}</td>
            </tr>
            <tr class="total">
                <td colspan="2" class="right">Total</td>
                <td class="right">{{ number_format((float) $data['amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
