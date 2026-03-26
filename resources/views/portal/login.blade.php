<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet"><title>Login - Auction Portal</title>
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Varela Round',sans-serif;background:linear-gradient(135deg,#1a237e 0%,#283593 100%);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}.logo-header{text-align:center;margin-bottom:35px}.logo-header img{height:65px;background:#fff;padding:5px;border-radius:12px}.logo-header h1{color:#fff;font-size:28px;font-weight:400}.container{background:#fff;padding:45px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.15);width:100%;max-width:420px}h2{color:#1a237e;margin-bottom:30px;text-align:center;font-weight:400}.form-group{margin-bottom:20px}label{display:block;color:#1a237e;margin-bottom:8px}input,button,select,textarea{font-family:'Varela Round',sans-serif}input::placeholder,textarea::placeholder{font-family:'Varela Round',sans-serif}input{width:100%;padding:12px 16px;border:2px solid #e9ecef;border-radius:8px}.btn{width:100%;padding:14px;background:#1a237e;color:#fff;border:none;border-radius:8px;cursor:pointer}.links{text-align:center;margin-top:20px}.links a{color:#1a237e;text-decoration:none}</style></head>
<body>
<div class="logo-header"><img src="{{ asset('images/nixi_logo1.jpg') }}" alt="NIXI Logo"><h1>Auction Portal</h1></div>
<div class="container">
    <h2>Login</h2>
    @if(session('status'))<div style="background:#e8f5e9;color:#1b5e20;padding:12px;border-radius:8px;margin-bottom:14px;">{{ session('status') }}</div>@endif
    @if($errors->any())<div style="background:#ffebee;color:#c62828;padding:12px;border-radius:8px;margin-bottom:14px;">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('login.submit') }}">@csrf
        <div class="form-group"><label>Email</label><input type="email" name="email" required value="{{ old('email') }}"></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
        <div style="text-align:right;margin-bottom:10px;"><a href="{{ route('password.forgot') }}" style="font-size:14px;">Forgot password?</a></div>
        <button type="submit" class="btn">Login</button>
    </form>
    <div class="links">Don't have an account? <a href="{{ route('register') }}">Register here</a><br><br><a href="{{ route('home') }}">Back to Home</a></div>
</div>
</body></html>
