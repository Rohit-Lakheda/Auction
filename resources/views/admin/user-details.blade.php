@extends('admin.layout')
@section('title','User Details')
@section('content')
@php($query = request()->query())
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0;">User Details</h2>
            <p style="color:#6c757d;margin-top:6px;">Complete profile, registration, auction activity, invoices/payments, and wallet details.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('admin.manage-users') }}" class="btn btn-secondary">Back to Manage Users</a>
            <form method="POST" action="{{ route('admin.users.toggle-block', $user->id) }}">
                @csrf
                <button type="submit" class="btn {{ (int)($user->is_blocked ?? 0) === 1 ? 'btn-success' : 'btn-danger' }}">
                    {{ (int)($user->is_blocked ?? 0) === 1 ? 'Unblock User' : 'Block User' }}
                </button>
            </form>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;">
    <div class="card" style="padding:18px;"><div style="color:#6c757d;">Total Bids</div><div style="font-size:24px;color:#1a237e;">{{ $stats['total_bids'] }}</div></div>
    <div class="card" style="padding:18px;"><div style="color:#6c757d;">Won Auctions</div><div style="font-size:24px;color:#1a237e;">{{ $stats['won_auctions'] }}</div></div>
    {{-- [EMD/WALLET DISABLED] Joined Auctions and Wallet Balance stat cards removed --}}
</div>

<div class="card">
    <h3 style="color:#1a237e;">Profile & Registration</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
        <div><strong>Name:</strong> {{ $user->name }}</div>
        <div><strong>Email:</strong> {{ $user->email }}</div>
        <div><strong>Role:</strong> {{ strtoupper((string)$user->role) }}</div>
        <div><strong>Blocked:</strong> {{ (int)($user->is_blocked ?? 0) === 1 ? 'Yes' : 'No' }}</div>
        <div><strong>Default Count:</strong> {{ (int)($user->default_count ?? 0) }}</div>
        <div><strong>Registered:</strong> {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('d-M-Y H:i') : '-' }}</div>
        <div><strong>Registration ID:</strong> {{ $registration->registration_id ?? '-' }}</div>
        <div><strong>Registration Type:</strong> {{ strtoupper((string)($registration->registration_type ?? '-')) }}</div>
        <div><strong>PAN:</strong> {{ $registration->pan_card_number ?? '-' }}</div>
        <div><strong>Mobile:</strong> {{ $registration->mobile ?? ($user->mobile ?? '-') }}</div>
        <div><strong>Registration Payment:</strong> {{ strtoupper((string)($registration->payment_status ?? '-')) }}</div>
        <div><strong>Registration Amount:</strong> {{ isset($registration->payment_amount) ? '₹'.number_format((float)$registration->payment_amount,2) : '-' }}</div>
        <div><strong>Registration Invoice:</strong>
            <a href="{{ route('admin.invoices.registration', $user->id) }}" style="color:#1a237e;text-decoration:none;">Download</a>
        </div>
    </div>
</div>

<div class="card">
    <form method="GET" style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
        @foreach($query as $k => $v)
            @if(! in_array($k, ['bids_per_page', 'bids_page'], true))
                <input type="hidden" name="{{ $k }}" value="{{ is_array($v) ? json_encode($v) : $v }}">
            @endif
        @endforeach
        <h3 style="color:#1a237e;margin:0;">Recent Bids</h3>
        <div class="form-group" style="margin:0;">
            <label>Per Page</label>
            <select name="bids_per_page" onchange="this.form.submit()">@foreach(['10','20','50','100','all'] as $size)<option value="{{ $size }}" {{ ($perPage['bids'] ?? '10') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>@endforeach</select>
        </div>
    </form>
    @if($recentBids->isEmpty()) <p>No bids found.</p> @else
    <div style="overflow:auto;"><table>
        <thead><tr><th>Auction</th><th>Status</th><th>Amount</th><th>Bid Time</th><th>Action</th></tr></thead>
        <tbody>@foreach($recentBids as $bid)<tr>
            <td>{{ $bid->auction_title }}</td><td>{{ strtoupper($bid->auction_status) }}</td>
            <td>₹{{ number_format((float)$bid->amount,2) }}</td><td>{{ \Carbon\Carbon::parse($bid->created_at)->format('d-M-Y H:i') }}</td>
            <td><a class="btn btn-secondary" style="padding:5px 10px;font-size:12px;" href="{{ route('admin.auctions.bids', $bid->auction_id) }}">View Bids</a></td>
        </tr>@endforeach</tbody>
    </table></div>@include('partials.grid-pagination', ['rows' => $recentBids])@endif
</div>

<div class="card">
    <form method="GET" style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
        @foreach($query as $k => $v)
            @if(! in_array($k, ['won_per_page', 'won_page'], true))
                <input type="hidden" name="{{ $k }}" value="{{ is_array($v) ? json_encode($v) : $v }}">
            @endif
        @endforeach
        <h3 style="color:#1a237e;margin:0;">Won Auctions</h3>
        <div class="form-group" style="margin:0;">
            <label>Per Page</label>
            <select name="won_per_page" onchange="this.form.submit()">@foreach(['10','20','50','100','all'] as $size)<option value="{{ $size }}" {{ ($perPage['won'] ?? '10') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>@endforeach</select>
        </div>
    </form>
    @if($wonAuctions->isEmpty()) <p>No won auctions found.</p> @else
    <div style="overflow:auto;"><table>
        <thead><tr><th>Auction</th><th>Status</th><th>Final Price</th><th>Payment</th><th>End Date</th><th>Actions</th></tr></thead>
        <tbody>@foreach($wonAuctions as $auction)<tr>
            <td>{{ $auction->title }}</td><td>{{ strtoupper($auction->status) }}</td><td>{{ $auction->final_price ? '₹'.number_format((float)$auction->final_price,2) : '-' }}</td>
            <td>{{ strtoupper((string)($auction->payment_status ?? '-')) }}</td><td>{{ \Carbon\Carbon::parse($auction->end_datetime)->format('d-M-Y') }}</td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                <a class="btn btn-secondary" style="padding:5px 10px;font-size:12px;" href="{{ route('admin.auctions.bids', $auction->id) }}">View Bids</a>
                @if(strtolower((string)($auction->payment_status ?? 'pending')) === 'paid')
                    <a class="btn" style="padding:5px 10px;font-size:12px;" href="{{ route('admin.invoices.auction', ['id' => $user->id, 'auctionId' => $auction->id]) }}">Download Invoice</a>
                @else
                    <span style="color:#6c757d;font-size:12px;">Invoice after payment</span>
                @endif
            </td>
        </tr>@endforeach</tbody>
    </table></div>@include('partials.grid-pagination', ['rows' => $wonAuctions])@endif
</div>

{{-- [EMD DISABLED] Auction Participation (EMD) section hidden
<div class="card">
    ... participation table ...
</div>
--}}

{{-- [EMD/WALLET DISABLED] Wallet Transactions section hidden
<div class="card">
    ... wallet transactions table...
</div>
--}}

{{-- [EMD/WALLET DISABLED] Wallet Top-ups section hidden
<div class="card">
    ... wallet topups table ...
</div>
--}}

<div class="card">
    <form method="GET" style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
        @foreach($query as $k => $v)
            @if(! in_array($k, ['auction_payment_per_page', 'auction_payment_page'], true))
                <input type="hidden" name="{{ $k }}" value="{{ is_array($v) ? json_encode($v) : $v }}">
            @endif
        @endforeach
        <h3 style="color:#1a237e;margin:0;">Auction Payments</h3>
        <div class="form-group" style="margin:0;">
            <label>Per Page</label>
            <select name="auction_payment_per_page" onchange="this.form.submit()">@foreach(['10','20','50','100','all'] as $size)<option value="{{ $size }}" {{ ($perPage['auction_payment'] ?? '10') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>@endforeach</select>
        </div>
    </form>
    @if($auctionPayments->isEmpty()) <p>No auction payment records found.</p> @else
    <div style="overflow:auto;"><table>
        <thead><tr><th>Auction</th><th>Txn ID</th><th>Amount</th><th>Status</th><th>Gateway Txn</th><th>Date</th></tr></thead>
        <tbody>@foreach($auctionPayments as $p)<tr>
            <td>{{ $p->auction_title ?? ('Auction #'.$p->auction_id) }}</td><td>{{ $p->transaction_id }}</td><td>₹{{ number_format((float)$p->amount,2) }}</td>
            <td>{{ strtoupper((string)$p->status) }}</td><td>{{ $p->payu_transaction_id ?? '-' }}</td><td>{{ \Carbon\Carbon::parse($p->created_at)->format('d-M-Y H:i') }}</td>
        </tr>@endforeach</tbody>
    </table></div>@include('partials.grid-pagination', ['rows' => $auctionPayments])@endif
</div>

<div class="card">
    <form method="GET" style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
        @foreach($query as $k => $v)
            @if(! in_array($k, ['registration_payment_per_page', 'registration_payment_page'], true))
                <input type="hidden" name="{{ $k }}" value="{{ is_array($v) ? json_encode($v) : $v }}">
            @endif
        @endforeach
        <h3 style="color:#1a237e;margin:0;">Registration Payments</h3>
        <div class="form-group" style="margin:0;">
            <label>Per Page</label>
            <select name="registration_payment_per_page" onchange="this.form.submit()">@foreach(['10','20','50','100','all'] as $size)<option value="{{ $size }}" {{ ($perPage['registration_payment'] ?? '10') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>@endforeach</select>
        </div>
    </form>
    @if($registrationPayments->isEmpty()) <p>No registration payment records found.</p> @else
    <div style="overflow:auto;"><table>
        <thead><tr><th>Registration ID</th><th>Txn ID</th><th>Amount</th><th>Status</th><th>Gateway Txn</th><th>Date</th></tr></thead>
        <tbody>@foreach($registrationPayments as $p)<tr>
            <td>{{ $p->registration_id }}</td><td>{{ $p->transaction_id }}</td><td>₹{{ number_format((float)$p->amount,2) }}</td>
            <td>{{ strtoupper((string)$p->status) }}</td><td>{{ $p->payu_transaction_id ?? '-' }}</td><td>{{ \Carbon\Carbon::parse($p->created_at)->format('d-M-Y H:i') }}</td>
        </tr>@endforeach</tbody>
    </table></div>@include('partials.grid-pagination', ['rows' => $registrationPayments])@endif
</div>
@endsection
