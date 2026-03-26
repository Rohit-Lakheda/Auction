@extends('admin.layout')
@section('title','View Bids')
@section('content')
<div class="card">
    <h2>Bids for: {{ $auction->title }}</h2>
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr auto;gap:10px;align-items:end;margin-bottom:16px;">
        <div class="form-group" style="margin:0;">
            <label>Search Bidder</label>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Name or email">
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
    @if($bids->isEmpty())
        <p>No bids placed yet for this auction.</p>
    @else
        <div style="overflow:auto;">
            <table>
                <thead><tr><th>Rank</th><th>Bidder Name</th><th>Bidder Email</th><th>Bid Amount</th><th>Bid Time</th></tr></thead>
                <tbody>
                @foreach($bids as $i => $bid)
                    <tr style="{{ ($bids->firstItem() + $i)===1 ? 'background:#c6f6d5;' : '' }}">
                        <td>{{ $bids->firstItem() + $i }} {!! ($bids->firstItem() + $i)===1 ? '🏆' : '' !!}</td>
                        <td><a href="{{ route('admin.users.show', $bid->user_id) }}" style="color:#1a237e;text-decoration:none;">{{ $bid->bidder_name }}</a></td>
                        <td>{{ $bid->bidder_email }}</td>
                        <td><strong>₹{{ number_format((float)$bid->amount,2) }}</strong></td>
                        <td>{{ \Carbon\Carbon::parse($bid->created_at)->format('d-M-Y H:i:s') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.grid-pagination', ['rows' => $bids])
    @endif
    <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="{{ route('admin.auctions.index') }}" class="btn btn-secondary">Back to Auction Details</a>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>
@endsection
