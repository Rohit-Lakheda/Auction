@extends('user.layout')

@section('title', 'My Profile - Auction Portal')

@section('content')
<style>
    .profile-page { max-width: 720px; margin: 0 auto; padding-bottom: 32px; }
    .profile-hero {
        display: flex; align-items: center; gap: 14px;
        border-radius: 12px; padding: 18px 20px; margin-bottom: 20px;
        background: #f8fafc; border: 1px solid #e8ecf0;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .profile-hero-icon {
        width: 44px; height: 44px; border-radius: 10px; background: #eef2f6; color: #64748b;
        display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
    }
    .profile-hero h1 { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.02em; color: #1e293b; }
    .profile-card {
        background: #fff; border-radius: 12px; border: 1px solid #e8ecf2;
        padding: 22px 20px 24px; margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }
    .profile-card h2 { margin: 0 0 14px; font-size: 17px; font-weight: 700; color: #1a237e; }
    .profile-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;
    }
    .profile-field {
        background: #f8fafc; border: 1px solid #e7edf4; border-radius: 10px; padding: 12px 14px;
    }
    .profile-field .k { font-size: 12px; color: #64748b; margin-bottom: 4px; }
    .profile-field .v { font-weight: 600; color: #1e293b; word-break: break-word; }
    .profile-details { margin: 0; list-style: none; }
    .profile-details > summary {
        cursor: pointer; font-weight: 700; font-size: 15px; color: #1a237e;
        padding: 12px 14px; border-radius: 10px; border: 1px solid #e2e8f0;
        background: #fff; display: flex; align-items: center; gap: 10px;
        list-style: none;
    }
    .profile-details > summary::-webkit-details-marker { display: none; }
    .profile-details > summary::before { content: '\f054'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 11px; color: #94a3b8; transition: transform .2s; }
    .profile-details[open] > summary::before { transform: rotate(90deg); }
    .profile-details .inner { padding: 16px 4px 4px; }
    .profile-details .hint { font-size: 13px; color: #64748b; margin-bottom: 14px; line-height: 1.45; }
    .profile-btn {
        padding: 10px 18px; border-radius: 10px; border: none; background: #1a237e; color: #fff !important;
        font-size: 14px; font-weight: 600; cursor: pointer;
    }
    .profile-btn:hover { filter: brightness(1.06); }
    .profile-btn.secondary { background: #f1f5f9; color: #334155 !important; border: 1px solid #cbd5e1; }
    .profile-btn.secondary:hover { background: #e2e8f0; }
    .profile-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 12px; }
    details.profile-details { scroll-margin-top: 88px; }
</style>

@php
    $profileUid = (int) session('user_id');
    $pendingMobileRaw = session('profile_mobile_pending_' . $profileUid);
    $pendingMobileNorm = is_string($pendingMobileRaw) ? preg_replace('/\D/', '', $pendingMobileRaw) : '';
    $pendingEmailRaw = session('profile_email_pending_' . $profileUid);
    $hasPwdOtp = $profileUid > 0 && session()->has('profile_pwd_otp_' . $profileUid);

    /* Open section only when user started a flow (profile_ui_section) or validation failed — not from stale pending alone */
    $focus = session('profile_ui_section');
    if ($focus !== 'password' && $focus !== 'email' && $focus !== 'mobile') {
        $focus = null;
    }
    if ($focus === null && $errors->any()) {
        if ($errors->has('new_mobile')) {
            $focus = 'mobile';
        } elseif ($errors->has('new_email')) {
            $focus = 'email';
        } elseif ($errors->has('current_password')) {
            $focus = 'password';
        } elseif ($errors->has('new_password') || $errors->has('confirm_password')) {
            $focus = 'password';
        } elseif ($errors->has('otp')) {
            if ($hasPwdOtp) {
                $focus = 'password';
            } elseif (strlen($pendingMobileNorm) === 10) {
                $focus = 'mobile';
            } elseif (is_string($pendingEmailRaw) && filter_var($pendingEmailRaw, FILTER_VALIDATE_EMAIL)) {
                $focus = 'email';
            }
        }
    }

    $openPassword = $focus === 'password';
    $openEmail = $focus === 'email';
    $openMobile = $focus === 'mobile';

    $newMobileDisplay = old('new_mobile', strlen($pendingMobileNorm) === 10 ? $pendingMobileNorm : '');
    $newEmailDisplay = old('new_email', (is_string($pendingEmailRaw) && filter_var($pendingEmailRaw, FILTER_VALIDATE_EMAIL)) ? $pendingEmailRaw : '');
@endphp

<div class="profile-page">
    <div class="profile-hero">
        <div class="profile-hero-icon" aria-hidden="true"><i class="fas fa-user"></i></div>
        <h1>Profile</h1>
    </div>

    @if(session('success'))
        <div class="alert-show alert-show-success" style="margin-bottom:16px;">
            <span class="alert-keyword">Success.</span><span class="alert-body">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="alert-show alert-show-error" style="margin-bottom:16px;">
            <span class="alert-keyword">Error.</span><span class="alert-body">{{ session('error') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="alert-show alert-show-error" style="margin-bottom:16px;">
            <span class="alert-keyword">Error.</span><span class="alert-body">{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="profile-card">
        <h2>Your details</h2>
        <div class="profile-grid">
            <div class="profile-field"><div class="k">Name</div><div class="v">{{ $user->name }}</div></div>
            <div class="profile-field"><div class="k">Email</div><div class="v">{{ $user->email }}</div></div>
            <div class="profile-field"><div class="k">Registration ID</div><div class="v">{{ $user->registration_id ?? '—' }}</div></div>
            <div class="profile-field"><div class="k">Mobile</div><div class="v">{{ $user->reg_mobile ?? ($user->mobile ?? '—') }}</div></div>
            <div class="profile-field"><div class="k">PAN</div><div class="v">{{ $user->pan_card_number ?? '—' }}</div></div>
            <div class="profile-field"><div class="k">Joined</div><div class="v">{{ \Carbon\Carbon::parse($user->created_at)->format('d-M-Y H:i') }}</div></div>
        </div>
        @if(($user->payment_status ?? null) === 'success')
            <p style="margin-top:16px;margin-bottom:0;">
                <a class="btn btn-secondary" href="{{ route('invoices.registration') }}">Download registration invoice</a>
            </p>
        @endif
    </div>

    <details class="profile-details profile-card" id="profile-section-password" @if($openPassword) open @endif>
        <summary><i class="fas fa-key" style="opacity:.85;"></i> Change password</summary>
        <div class="inner">
            <p class="hint">First confirm it’s you with your current password; we’ll email a 6-digit code. Then enter that code and your new password once.</p>
            <form method="POST" action="{{ route('user.profile.password.send-otp') }}" style="margin-bottom:18px;">
                @csrf
                <div class="form-group">
                    <label class="theme-label">Current password</label>
                    <input class="theme-control" type="password" name="current_password" required autocomplete="current-password">
                </div>
                <button class="profile-btn secondary" type="submit">Send verification code to email</button>
            </form>
            <form method="POST" action="{{ route('user.profile.password.update') }}">
                @csrf
                <div class="form-group">
                    <label class="theme-label">Verification code</label>
                    <input class="theme-control" type="text" name="otp" value="{{ old('otp') }}" inputmode="numeric" maxlength="6" placeholder="6-digit code" required autocomplete="one-time-code">
                </div>
                <div class="form-group">
                    <label class="theme-label">New password (min 8 characters)</label>
                    <input class="theme-control" type="password" name="new_password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="theme-label">Confirm new password</label>
                    <input class="theme-control" type="password" name="confirm_password" required autocomplete="new-password">
                </div>
                <button class="profile-btn" type="submit">Update password</button>
            </form>
        </div>
    </details>

    <details class="profile-details profile-card" id="profile-section-email" @if($openEmail) open @endif>
        <summary><i class="fas fa-envelope" style="opacity:.85;"></i> Update email</summary>
        <div class="inner">
            <p class="hint">Enter the new address and request a code. We send the OTP to that inbox so only you can confirm.</p>
            <form method="POST" action="{{ route('user.profile.email.send-otp') }}" style="margin-bottom:18px;">
                @csrf
                <div class="form-group">
                    <label class="theme-label">New email address</label>
                    <input class="theme-control" type="email" name="new_email" value="{{ $newEmailDisplay }}" required autocomplete="email">
                </div>
                <button class="profile-btn secondary" type="submit">Send verification code</button>
            </form>
            <form method="POST" action="{{ route('user.profile.email.update') }}">
                @csrf
                <div class="form-group">
                    <label class="theme-label">Verification code</label>
                    <input class="theme-control" type="text" name="otp" value="{{ old('otp') }}" inputmode="numeric" maxlength="6" placeholder="6-digit code" required>
                </div>
                <button class="profile-btn" type="submit">Confirm new email</button>
            </form>
        </div>
    </details>

    <details class="profile-details profile-card" id="profile-section-mobile" @if($openMobile) open @endif>
        <summary><i class="fas fa-mobile-screen" style="opacity:.85;"></i> Update mobile</summary>
        <div class="inner">
            <p class="hint">Request a code for your new 10-digit number. When SMS is configured, you receive the OTP by text (same gateway as registration).</p>
            <form method="POST" action="{{ route('user.profile.mobile.send-otp') }}" style="margin-bottom:18px;">
                @csrf
                <div class="form-group">
                    <label class="theme-label">New mobile number</label>
                    <input class="theme-control" type="tel" name="new_mobile" value="{{ $newMobileDisplay }}" maxlength="10" inputmode="numeric" placeholder="10 digits" required autocomplete="tel">
                </div>
                <button class="profile-btn secondary" type="submit">Send verification code</button>
            </form>
            <form method="POST" action="{{ route('user.profile.mobile.update') }}">
                @csrf
                <div class="form-group">
                    <label class="theme-label">Verification code</label>
                    <input class="theme-control" type="text" name="otp" value="{{ old('otp') }}" inputmode="numeric" maxlength="6" placeholder="6-digit code" required>
                </div>
                <button class="profile-btn" type="submit">Confirm new mobile</button>
            </form>
        </div>
    </details>
</div>
@if($focus !== null)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('profile-section-{{ $focus }}');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
</script>
@endif
@endsection
