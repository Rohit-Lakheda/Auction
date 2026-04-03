@extends('user.layout')

@section('title', 'Lost Auctions - Auction Portal')

@section('content')
<div class="card">
    <h2>Lost Auctions</h2>
    <p style="color:#6c757d;">Auctions where you bid but did not win.</p>
</div>

<div class="card">
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr auto;gap:10px;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Search</label>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Auction title">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Per Page</label>
            <select name="per_page">
                @foreach(['10','20','50','100','all'] as $size)
                    <option value="{{ $size }}" {{ ($perPage ?? '20') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn">Apply</button>
    </form>
</div>

<div class="card">
    @if($lostAuctions->isEmpty())
        <p style="color:#6c757d;">No lost auctions found.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Your Highest Bid</th>
                    <th>Winning Bid</th>
                    <th>Ended At</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lostAuctions as $auction)
                    <tr>
                        <td>{{ $auction->title }}</td>
                        <td>₹{{ number_format((float) ($auction->my_highest_bid ?? 0), 2) }}</td>
                        <td>₹{{ number_format((float) ($auction->winning_bid ?? 0), 2) }}</td>
                        <td>{{ \Carbon\Carbon::parse($auction->end_datetime)->format('d-M-Y H:i') }}</td>
                        <td>{{ strtoupper((string) $auction->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('partials.grid-pagination', ['rows' => $lostAuctions])
    @endif
</div>
@endsection

