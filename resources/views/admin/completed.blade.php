@extends('admin.layout')
@section('title','Completed Auctions')
@section('content')
<div class="card">
    <h2>Completed Auctions</h2>
    <p style="color:#6c757d;">Monitor winners, payment status, and completed outcomes.</p>
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:end;margin:14px 0 8px;">
        <div class="form-group" style="margin:0;">
            <label>Search</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Auction title">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Status</label>
            <select name="status">
                <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                <option value="closed" {{ ($filters['status'] ?? '') === 'closed' ? 'selected' : '' }}>Closed</option>
                <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Payment</label>
            <select name="payment">
                <option value="all" {{ ($filters['payment'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                <option value="pending" {{ ($filters['payment'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="paid" {{ ($filters['payment'] ?? '') === 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="failed" {{ ($filters['payment'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Per Page</label>
            <select name="per_page">
                @foreach(['10','20','50','100','all'] as $size)
                    <option value="{{ $size }}" {{ ($perPage ?? '20') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn" type="submit">Apply</button>
    </form>
    @if($completedAuctions->isEmpty())
        <p>No completed auctions yet.</p>
    @else
        <div style="overflow:auto;">
        <table>
            <thead><tr><th>ID</th><th>Title</th><th>Winner</th><th>Final Price</th><th>Payment Status</th><th>End Date</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($completedAuctions as $auction)
                <tr>
                    <td>{{ $auction->id }}</td>
                    <td>{{ $auction->title }}</td>
                    <td>
                        @if($auction->winner_name)
                            <a href="{{ route('admin.users.show', $auction->winner_user_id) }}" style="color:#1a237e;text-decoration:none;">{{ $auction->winner_name }}</a><br><small>{{ $auction->winner_email }}</small>
                        @else
                            <em>No winner</em>
                        @endif
                    </td>
                    <td>{{ $auction->final_price ? '₹'.number_format((float)$auction->final_price,2) : '-' }}</td>
                    <td>
                        @if($auction->winner_user_id)
                        <form method="POST">@csrf
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <select name="payment_status" onchange="this.form.submit()">
                                <option value="pending" {{ $auction->payment_status==='pending' ? 'selected' : '' }}>Pending</option>
                                <option value="paid" {{ $auction->payment_status==='paid' ? 'selected' : '' }}>Paid</option>
                            </select>
                        </form>
                        @else - @endif
                    </td>
                    <td>{{ \Carbon\Carbon::parse($auction->end_datetime)->format('d-M-Y') }}</td>
                    <td><a class="btn btn-secondary" style="padding:5px 10px;font-size:12px;" href="{{ route('admin.auctions.bids', $auction->id) }}">View Bids</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @include('partials.grid-pagination', ['rows' => $completedAuctions])
    @endif
</div>
@endsection
