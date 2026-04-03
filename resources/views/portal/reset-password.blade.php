<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Auction Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <style>
        body{font-family:'Varela Round',sans-serif;background:linear-gradient(135deg,#1a237e 0%,#283593 100%);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}
        input,select,textarea,button{font-family:'Varela Round',sans-serif}
        input::placeholder,textarea::placeholder{font-family:'Varela Round',sans-serif}
        .logo-header{text-align:center;margin-bottom:28px}
        .logo-header img{height:65px;background:#fff;padding:5px;border-radius:12px}
        .logo-header h1{color:#fff;font-size:28px;font-weight:400}
        .card{background:#fff;padding:30px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);max-width:520px;width:100%}
        input{width:100%;padding:12px;border:2px solid #e9ecef;border-radius:8px;margin-bottom:12px}
        .btn{padding:12px 16px;background:#1a237e;color:#fff;border:none;border-radius:8px;cursor:pointer}
        .err{background:#ffebee;color:#c62828;padding:10px;border-radius:8px;margin-bottom:10px}
        .ok{background:#e8f5e9;color:#2e7d32;padding:10px;border-radius:8px;margin-bottom:10px}
    </style>
</head>
<body>
<div class="logo-header"><img src="{{ asset('images/nixi_logo1.jpg') }}" alt="NIXI Logo"><h1>Auction Portal</h1></div>
<div class="card">
    <h2>Update Password</h2>
    @if(session('status'))<div class="ok">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    @if(!$tokenValid)
        <div class="err">Invalid or expired password reset link.</div>
        <a href="{{ route('password.forgot') }}">Request new reset link</a>
    @else
        <form method="POST" action="{{ route('password.reset.submit', ['token' => $token]) }}">@csrf
            <input type="password" name="password" placeholder="New password (min 8 chars)" required>
            <input type="password" name="confirm_password" placeholder="Confirm new password" required>
            <button class="btn" type="submit">Update Password</button>
        </form>
    @endif
</div>
</body>
</html>
