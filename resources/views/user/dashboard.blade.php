@extends('user.layout')

@section('title', 'Dashboard - Auction Portal')

@section('content')
<style>
    .card-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }
    .card-header-row h3 { margin: 0; }
    .view-all-link {
        color: #1a237e;
        text-decoration: none;
        font-size: 14px;
        white-space: nowrap;
    }
    .table-wrap { width: 100%; overflow: auto; }
    .compact-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px; }
</style>
<div class="card">
    <h2>Dashboard</h2>
    <p style="color:#6c757d;">Welcome back! Here's an overview of your auction activity and available opportunities.</p>
</div>

<div class="card">
    <h3 style="color:#1a237e;">Profile Summary</h3>
    @if($profile)
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
            <div><strong>Name:</strong> {{ $profile->name }}</div>
            <div><strong>Email:</strong> {{ $profile->email }}</div>
            <div><strong>Registration ID:</strong> {{ $profile->registration_id ?? '-' }}</div>
            <div><strong>Registration Type:</strong> {{ strtoupper($profile->registration_type ?? '-') }}</div>
            <div><strong>Mobile:</strong> {{ $profile->mobile ?? '-' }}</div>
            <div><strong>PAN:</strong> {{ $profile->pan_card_number ?? '-' }}</div>
        </div>
    @endif
    <div style="margin-top:16px;">
        <a href="{{ route('user.profile') }}" class="btn btn-secondary">View Full Profile</a>
    </div>
</div>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(420px,1fr));">
    <div class="card">
        <div class="card-header-row">
            <h3 style="color:#1a237e;">Currently Winning</h3>
            <a class="view-all-link" href="{{ route('user.my-bids') }}">View all</a>
        </div>
        @if($winningBids->isEmpty())
            <p style="color:#6c757d;">You are not highest bidder in any active auction right now.</p>
        @else
            <div class="table-wrap">
                <table><thead><tr><th>Auction Title</th><th>Your Bid</th><th>Ends At</th><th>Status</th><th>Action</th></tr></thead><tbody>
                @foreach($winningBids as $bid)
                    @php $hoursLeft=floor((strtotime($bid->end_datetime)-time())/3600); @endphp
                    <tr style="background:#e8f5e9;"><td><strong>{{ $bid->title }}</strong></td><td><strong style="color:#2e7d32;">₹{{ number_format((float)$bid->my_bid,2) }}</strong></td><td>{{ \Carbon\Carbon::parse($bid->end_datetime)->format('d-M-Y') }}</td><td>{!! ($hoursLeft>0&&$hoursLeft<24)?'<span style="color:#c62828;">Ending Soon</span>':'<span style="color:#2e7d32;">Leading</span>' !!}</td><td><a href="{{ route('user.auctions.show',$bid->id) }}" class="btn" style="padding:6px 12px;font-size:13px;">View</a></td></tr>
                @endforeach
                </tbody></table>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header-row">
            <h3 style="color:#1a237e;">Active Auctions</h3>
            <a class="view-all-link" href="{{ route('user.auctions.index') }}">View all</a>
        </div>
        @if($activeAuctions->isEmpty())
            <p style="color:#6c757d;text-align:center;padding:30px;">No active auctions at the moment. Check back later!</p>
        @else
        <div class="compact-grid">
            @foreach($activeAuctions as $auction)
                @php $currentBid=$auction->current_bid ?? $auction->base_price; $minNext=(float)$currentBid+(float)$auction->min_increment; @endphp
                <div class="auction-card"><h3>{{ $auction->title }}</h3><p>{{ \Illuminate\Support\Str::limit($auction->description,80) }}</p><div style="border-top:1px solid #e9ecef;border-bottom:1px solid #e9ecef;padding:12px 0;margin:15px 0;"><div style="display:flex;justify-content:space-between;"><span>Current Bid:</span><strong>₹{{ number_format((float)$currentBid,2) }}</strong></div><div style="display:flex;justify-content:space-between;"><span>Min Next:</span><strong>₹{{ number_format($minNext,2) }}</strong></div></div><a href="{{ route('user.auctions.show',$auction->id) }}" class="btn" style="width:100%;text-align:center;">View</a></div>
            @endforeach
        </div>
        @endif
    </div>
</div>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(420px,1fr));">
    <div class="card">
        <div class="card-header-row">
            <h3 style="color:#1a237e;">Watchlist</h3>
            <a class="view-all-link" href="{{ route('user.auctions.index') }}">View all</a>
        </div>
        @if($watchlistAuctions->isEmpty())
            <p style="color:#6c757d;">No auctions in watchlist.</p>
        @else
            <div class="table-wrap">
                <table><thead><tr><th>Auction</th><th>Current Bid</th><th>EMD</th><th>Status</th><th>Action</th></tr></thead><tbody>
                @foreach($watchlistAuctions as $auction)
                    <tr>
                        <td>{{ $auction->title }}</td>
                        <td>₹{{ number_format((float)($auction->current_bid ?? 0), 2) }}</td>
                        <td>₹{{ number_format((float)($auction->emd_amount ?? 0), 2) }}</td>
                        <td>{{ strtoupper($auction->status) }}</td>
                        <td>
                            @if($auction->status === 'active')
                                <a href="{{ route('user.auctions.show', $auction->id) }}" class="btn" style="padding:6px 12px;font-size:13px;">View</a>
                            @else
                                <span style="color:#6c757d;">Closed</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody></table>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header-row">
            <h3 style="color:#1a237e;">Recent Bids</h3>
            <a class="view-all-link" href="{{ route('user.my-bids') }}">View all</a>
        </div>
        @if($recentBids->isEmpty())
            <p style="color:#6c757d;">No recent bids.</p>
        @else
            <div class="table-wrap">
                <table><thead><tr><th>Auction Title</th><th>Bid Amount</th><th>Status</th><th>Bid Time</th><th>Action</th></tr></thead><tbody>
                @foreach($recentBids as $bid)
                    <tr><td>{{ $bid->title }}</td><td><strong>₹{{ number_format((float)$bid->amount,2) }}</strong></td><td>{{ strtoupper($bid->status) }}</td><td>{{ \Carbon\Carbon::parse($bid->created_at)->format('d-M-Y H:i') }}</td><td>@if($bid->status==='active')<a href="{{ route('user.auctions.show',$bid->auction_id) }}" class="btn" style="padding:6px 12px;font-size:13px;">View</a>@else<span style="color:#6c757d;">Closed</span>@endif</td></tr>
                @endforeach
                </tbody></table>
            </div>
        @endif
    </div>
</div>
@endsection
