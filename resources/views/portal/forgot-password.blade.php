<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Auction Portal</title>
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
    </style>
</head>
<body>
<div class="logo-header"><img src="{{ asset('images/nixi_logo1.jpg') }}" alt="NIXI Logo"><h1>Auction Portal</h1></div>
<div class="card">
    <h2>Forgot Password</h2>
    <p>Enter your email and verify OTP to reset password.</p>
    <input id="email" type="email" placeholder="Enter your email">
    <button class="btn" type="button" onclick="sendOtp()">Send OTP</button>
    <div id="otpSection" style="display:none;margin-top:12px;">
        <input id="otp" maxlength="6" placeholder="Enter 6-digit OTP">
        <button class="btn" type="button" onclick="verifyOtp()">Verify OTP</button>
    </div>
    <div id="status" style="margin-top:12px;color:#1a237e;"></div>
    <div style="margin-top:16px;"><a href="{{ route('login') }}">Back to Login</a></div>
</div>
<script>
const csrf='{{ csrf_token() }}';
const postJson=async(url,payload)=>{const fd=new FormData();Object.entries(payload).forEach(([k,v])=>fd.append(k,v));const r=await fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf},body:fd});return r.json();};
async function sendOtp(){const email=document.getElementById('email').value.trim();const data=await postJson('{{ route('password.forgot.send-otp') }}',{email});document.getElementById('status').textContent=data.message+(data.otp?` (Dev OTP: ${data.otp})`:'');if(data.success)document.getElementById('otpSection').style.display='block';}
async function verifyOtp(){const email=document.getElementById('email').value.trim();const otp=document.getElementById('otp').value.trim();const data=await postJson('{{ route('password.forgot.verify-otp') }}',{email,otp});if(data.success&&data.redirect_url){window.location.href=data.redirect_url;return;}document.getElementById('status').textContent=data.message||'Verification failed';}
</script>
</body>
</html>
