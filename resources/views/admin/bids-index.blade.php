@extends('admin.layout')
@section('title', 'All Bids')
@section('content')
<div class="card">
    <h2>All Bids</h2>
    <p style="color:#6c757d;">Browse all bids with user and auction drill-down links.</p>
</div>

<div class="card">
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Search</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="User, email or auction title">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Auction Status</label>
            <select name="status">
                <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                <option value="upcoming" {{ ($filters['status'] ?? '') === 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="closed" {{ ($filters['status'] ?? '') === 'closed' ? 'selected' : '' }}>Closed</option>
                <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Per Page</label>
            <select name="per_page">
                @foreach(['10','20','50','100','all'] as $size)
                    <option value="{{ $size }}" {{ ($perPage ?? '10') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn" type="submit">Apply</button>
    </form>
</div>

<div class="card">
    @if($bids->isEmpty())
        <p>No bids found for selected filters.</p>
    @else
        <div style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Bid ID</th>
                        <th>User</th>
                        <th>Auction</th>
                        <th>Auction Status</th>
                        <th>Bid Amount</th>
                        <th>Bid Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($bids as $bid)
                    <tr>
                        <td>{{ $bid->bid_id }}</td>
                        <td>
                            <a href="{{ route('admin.users.show', $bid->user_id) }}" style="color:#1a237e;text-decoration:none;">{{ $bid->user_name }}</a>
                            <br><small>{{ $bid->user_email }}</small>
                        </td>
                        <td>
                            <a href="{{ route('admin.auctions.bids', $bid->auction_id) }}" style="color:#1a237e;text-decoration:none;">{{ $bid->auction_title }}</a>
                        </td>
                        <td>{{ strtoupper($bid->auction_status) }}</td>
                        <td><strong>₹{{ number_format((float)$bid->amount,2) }}</strong></td>
                        <td>{{ \Carbon\Carbon::parse($bid->created_at)->format('d-M-Y H:i:s') }}</td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                            <a class="btn btn-secondary" style="padding:5px 10px;font-size:12px;" href="{{ route('admin.users.show', $bid->user_id) }}">User Details</a>
                            <a class="btn" style="padding:5px 10px;font-size:12px;" href="{{ route('admin.auctions.bids', $bid->auction_id) }}">Auction Bids</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.grid-pagination', ['rows' => $bids])
    @endif
</div>
@endsection
