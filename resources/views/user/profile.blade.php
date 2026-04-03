@extends('user.layout')

@section('title', 'My Profile - Auction Portal')

@section('content')
<div class="card"><h2>My Profile</h2></div>
@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error')) <div class="alert alert-error">{{ session('error') }}</div> @endif
@if($errors->any()) <div class="alert alert-error">{{ $errors->first() }}</div> @endif

<div class="grid" style="margin-bottom: 20px;">
    <a href="{{ route('user.my-bids') }}" class="card" style="text-align:center;text-decoration:none;color:inherit;transition:transform .2s,box-shadow .2s;">
        <h3>{{ $stats['total_bids'] }}</h3>
        <p>Total Bids</p>
    </a>
    <a href="{{ route('user.won-auctions') }}" class="card" style="text-align:center;text-decoration:none;color:inherit;transition:transform .2s,box-shadow .2s;">
        <h3>{{ $stats['won_auctions'] }}</h3>
        <p>Won Auctions</p>
    </a>
    <a href="{{ route('user.my-bids') }}" class="card" style="text-align:center;text-decoration:none;color:inherit;transition:transform .2s,box-shadow .2s;">
        <h3>{{ $stats['auctions_bid_on'] }}</h3>
        <p>Auctions Participated</p>
    </a>
</div>

<div class="card">
    <h3 style="color:#1a237e;margin-bottom:12px;">Profile Details</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;">
        <div style="background:#f8fafc;border:1px solid #e7edf4;border-radius:10px;padding:12px;">
            <div style="font-size:12px;color:#6c757d;">Name</div>
            <div style="font-weight:700;color:#1a237e;">{{ $user->name }}</div>
        </div>
        <div style="background:#f8fafc;border:1px solid #e7edf4;border-radius:10px;padding:12px;">
            <div style="font-size:12px;color:#6c757d;">Email</div>
            <div style="font-weight:700;color:#1a237e;">{{ $user->email }}</div>
        </div>
        <div style="background:#f8fafc;border:1px solid #e7edf4;border-radius:10px;padding:12px;">
            <div style="font-size:12px;color:#6c757d;">Registration ID</div>
            <div style="font-weight:700;color:#1a237e;">{{ $user->registration_id ?? '-' }}</div>
        </div>
        <div style="background:#f8fafc;border:1px solid #e7edf4;border-radius:10px;padding:12px;">
            <div style="font-size:12px;color:#6c757d;">Mobile</div>
            <div style="font-weight:700;color:#1a237e;">{{ $user->reg_mobile ?? '-' }}</div>
        </div>
        <div style="background:#f8fafc;border:1px solid #e7edf4;border-radius:10px;padding:12px;">
            <div style="font-size:12px;color:#6c757d;">PAN</div>
            <div style="font-weight:700;color:#1a237e;">{{ $user->pan_card_number ?? '-' }}</div>
        </div>
        <div style="background:#f8fafc;border:1px solid #e7edf4;border-radius:10px;padding:12px;">
            <div style="font-size:12px;color:#6c757d;">Joined</div>
            <div style="font-weight:700;color:#1a237e;">{{ \Carbon\Carbon::parse($user->created_at)->format('d-M-Y H:i') }}</div>
        </div>
    </div>
    @if(($user->payment_status ?? null) === 'success')
        <p style="margin-top:14px;"><a class="btn btn-secondary" href="{{ route('invoices.registration') }}">Download Registration Invoice</a></p>
    @endif
</div>

<div class="card">
    <h3>Change Password</h3>
    <form method="POST" action="{{ route('user.profile') }}">
        @csrf
        <div class="form-group"><input name="current_password" type="password" class="input" placeholder="Current password" required></div>
        <div class="form-group"><input name="new_password" type="password" class="input" placeholder="New password (min 8 chars)" required></div>
        <div class="form-group"><input name="confirm_password" type="password" class="input" placeholder="Confirm password" required></div>
        <button class="btn" type="submit">Update Password</button>
    </form>
</div>
@endsection
