@extends('user.layout')

@section('title', 'My Bids - Auction Portal')

@section('content')
<div class="card"><h2>My Bids</h2></div>
<div class="card">
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:10px;align-items:end;">
        <div>
            <label class="theme-label">Search</label>
            <input class="theme-control" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Auction title">
        </div>
        <div>
            <label class="theme-label">Status</label>
            <select class="theme-control" name="status">
                <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="closed" {{ ($filters['status'] ?? '') === 'closed' ? 'selected' : '' }}>Closed</option>
                <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div>
            <label class="theme-label">From Date</label>
            <input class="theme-control" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div>
            <label class="theme-label">To Date</label>
            <input class="theme-control" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div>
            <label class="theme-label">Per Page</label>
            <select class="theme-control" name="per_page">
                @foreach(['10','20','50','100','all'] as $size)
                    <option value="{{ $size }}" {{ ($filters['per_page'] ?? '20') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn" type="submit">Apply</button>
    </form>
</div>
@if ($myBids->isEmpty())
    <div class="card"><p>You haven't placed any bids yet.</p></div>
@else
    <div class="card">
        <div style="overflow:auto;">
        <table>
            <thead><tr><th>Auction</th><th>Status</th><th>My Bid</th><th>Highest</th><th>Total</th><th>Winning</th><th>Action</th></tr></thead>
            <tbody>
            @foreach ($myBids as $bid)
                @php $winning = ((float)$bid->my_highest_bid === (float)$bid->highest_bid); @endphp
                <tr>
                    <td>{{ $bid->title }}</td>
                    <td>{{ strtoupper($bid->status) }}</td>
                    <td>₹{{ number_format((float) $bid->my_highest_bid, 2) }}</td>
                    <td>₹{{ number_format((float) $bid->highest_bid, 2) }}</td>
                    <td>{{ $bid->total_bids }}</td>
                    <td>{{ $bid->status === 'active' ? ($winning ? '✓ Winning' : '✗ Outbid') : '-' }}</td>
                    <td>@if($bid->status === 'active')<a class="btn" href="{{ route('user.auctions.show', $bid->id) }}">View</a>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @include('partials.grid-pagination', ['rows' => $myBids])
    </div>
@endif
@endsection
