@extends('user.layout')

@section('title', 'Won Auctions - Auction Portal')

@section('content')
<div class="card"><h2>Won Auctions</h2></div>
<div class="card">
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr auto;gap:10px;align-items:end;">
        <div>
            <label class="theme-label">Search</label>
            <input class="theme-control" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Auction title">
        </div>
        <div>
            <label class="theme-label">Payment</label>
            <select class="theme-control" name="payment">
                <option value="all" {{ ($filters['payment'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                <option value="pending" {{ ($filters['payment'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="paid" {{ ($filters['payment'] ?? '') === 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="failed" {{ ($filters['payment'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div>
            <label class="theme-label">Auction Status</label>
            <select class="theme-control" name="status">
                <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
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
@if ($wonAuctions->isEmpty())
    <div class="card"><p>You haven't won any auctions yet.</p></div>
@else
    <div class="card">
        <div style="overflow:auto;">
        <table>
            <thead><tr><th>Title</th><th>Winning Amount</th><th>Payment</th><th>Won Date</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach ($wonAuctions as $auction)
                <tr>
                    <td>{{ $auction->title }}</td>
                    <td>₹{{ number_format((float) $auction->final_price, 2) }}</td>
                    <td>{{ strtoupper($auction->payment_status ?? 'pending') }}</td>
                    <td>{{ \Carbon\Carbon::parse($auction->end_datetime)->format('d-M-Y') }}</td>
                    <td>
                        @if (($auction->payment_status ?? 'pending') === 'pending')
                            <a class="btn" href="{{ route('payments.auction.initiate', $auction->id) }}">Pay Now</a>
                        @endif
                        @if (strtolower((string)($auction->payment_status ?? 'pending')) === 'paid')
                            <a class="btn btn-secondary" href="{{ route('invoices.auction', $auction->id) }}">Invoice</a>
                        @else
                            <span style="color:#6c757d;font-size:12px;">Invoice after payment</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @include('partials.grid-pagination', ['rows' => $wonAuctions])
    </div>
@endif
@endsection
