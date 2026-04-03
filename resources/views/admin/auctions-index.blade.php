@extends('admin.layout')
@section('title', 'Auction Details')
@section('content')
<div class="card">
    <h2>Auction Details</h2>
    <p style="color:#6c757d;">Filter auctions and open bids/user details directly from the grid.</p>
</div>

<div class="card">
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Search</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Auction title">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Status</label>
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
</div>

<div class="card">
    @if(session('success'))<div class="alert alert-success" style="margin-bottom:12px;">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error" style="margin-bottom:12px;">{{ $errors->first() }}</div>@endif
    @if($auctions->isEmpty())
        <p>No auctions found for selected filters.</p>
    @else
        <div style="overflow:auto;">
            <table>
                <thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Payment</th><th>Bids</th><th>Start</th><th>End</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($auctions as $auction)
                    <tr>
                        <td>{{ $auction->id }}</td>
                        <td>{{ $auction->title }}</td>
                        <td>{{ strtoupper($auction->status) }}</td>
                        <td>{{ strtoupper((string)($auction->payment_status ?? '-')) }}</td>
                        <td><a href="{{ route('admin.auctions.bids', $auction->id) }}" class="btn btn-secondary" style="padding:5px 10px;font-size:12px;">{{ $auction->bid_count }} Bids</a></td>
                        <td>{{ \Carbon\Carbon::parse($auction->start_datetime)->format('d-M-Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($auction->end_datetime)->format('d-M-Y') }}</td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                            @if((int)$auction->bid_count===0)
                                <a href="{{ route('admin.auctions.edit', $auction->id) }}" class="btn" style="padding:5px 10px;font-size:12px;">Edit</a>
                            @endif
                            @if($auction->status === 'active')
                                <a href="{{ route('admin.auctions.close', $auction->id) }}"
                                   class="btn btn-danger"
                                   style="padding:5px 10px;font-size:12px;"
                                   onclick="return confirm('Force close this auction now?');">Force Close</a>
                                <a href="{{ route('admin.auctions.cancel', $auction->id) }}"
                                   class="btn btn-secondary"
                                   style="padding:5px 10px;font-size:12px;"
                                   onclick="return confirm('Cancel this auction? This will clear winner/payment state.');">Cancel</a>
                            @else
                                <a href="{{ route('admin.auctions.reopen', $auction->id) }}"
                                   class="btn btn-success"
                                   style="padding:5px 10px;font-size:12px;"
                                   onclick="return confirm('Reopen this auction? Winner/payment state will be reset.');">Reopen</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.grid-pagination', ['rows' => $auctions])
    @endif
</div>
@endsection
