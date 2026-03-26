@extends('user.layout')

@section('title', 'Auction Details - Auction Portal')

@section('content')
    <div style="margin-bottom:14px;color:#6c757d;">
        <a href="{{ route('user.auctions.index') }}" style="color:#1a237e;text-decoration:none;">Auctions</a> / <strong>{{ $auction->title }}</strong>
    </div>
    @if (session('bid_success'))
        <div class="alert alert-success">✓ {{ session('bid_success') }}</div>
    @endif

    @if (session('bid_error'))
        <div class="alert alert-error">✗ {{ session('bid_error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <a href="{{ route('user.auctions.index') }}" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Auctions</a>
        <form method="POST" action="{{ route('user.auctions.watch', $auction->id) }}" style="display:inline-block;margin-left:8px;">
            @csrf
            <button type="submit" class="btn btn-secondary">{{ $isWatchlisted ? '★ Remove Watchlist' : '☆ Save Auction' }}</button>
        </form>

        <h2>{{ $auction->title }}</h2>
        <p style="color: #6c757d; margin-bottom: 20px;">Review auction details and place your bid. Make sure your bid is at least the minimum next bid amount.</p>

        <div style="background: #f7fafc; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="margin-bottom: 15px;">Description</h3>
            <p style="color: #2d3748; line-height: 1.6;">{!! nl2br(e($auction->description)) !!}</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="background: #e6fffa; padding: 15px; border-radius: 5px;">
                <p style="color: #234e52; font-size: 14px; margin-bottom: 5px;">Base Price</p>
                <p style="color: #234e52; font-size: 24px; font-weight: bold;">₹{{ number_format((float) $auction->base_price, 2) }}</p>
            </div>
            <div style="background: #e0e7ff; padding: 15px; border-radius: 5px;">
                <p style="color: #3730a3; font-size: 14px; margin-bottom: 5px;">Current Highest Bid</p>
                <p style="color: #3730a3; font-size: 24px; font-weight: bold;">₹{{ number_format($currentBid, 2) }}</p>
            </div>
            <div style="background: #fef3c7; padding: 15px; border-radius: 5px;">
                <p style="color: #78350f; font-size: 14px; margin-bottom: 5px;">Minimum Next Bid</p>
                <p style="color: #78350f; font-size: 24px; font-weight: bold;">₹{{ number_format($minNextBid, 2) }}</p>
            </div>
            <div style="background: #fee2e2; padding: 15px; border-radius: 5px;">
                <p style="color: #7f1d1d; font-size: 14px; margin-bottom: 5px;">Auction Ends</p>
                <p style="color: #7f1d1d; font-size: 16px; font-weight: bold;">{{ \Carbon\Carbon::parse($auction->end_datetime)->format('d-M-Y') }}</p>
                <p id="detailCountdown" style="margin-top:8px;font-weight:700;"></p>
            </div>
            <div style="background: #f0f9ff; padding: 15px; border-radius: 5px;">
                <p style="color: #0c4a6e; font-size: 14px; margin-bottom: 5px;">Required EMD</p>
                <p style="color: #0c4a6e; font-size: 24px; font-weight: bold;">₹{{ number_format((float) ($auction->emd_amount ?? 0), 2) }}</p>
            </div>
        </div>

        <div id="auctionStatusBox" class="alert alert-info" style="margin-top: 10px;">Loading your rank/status...</div>
        @if(!$isParticipant && $walletShortfall > 0)
            <div class="alert alert-error">You need ₹{{ number_format((float)$walletShortfall, 2) }} more to join this auction.</div>
        @endif
        @if($isParticipant)
            <div class="alert alert-success">Your EMD is locked for this auction.</div>
        @endif
    </div>

    <div class="card" style="position:sticky;top:20px;z-index:2;">
        <h3>Place Your Bid</h3>
        <p style="color: #6c757d; margin-bottom: 20px;">Enter your bid amount. Your bid must be at least ₹{{ number_format($minNextBid, 2) }} to be valid.</p>
        @if($myHighestBid > 0 && $myHighestBid < $currentBid)
            <div class="alert alert-error">You are currently outbid. Place a higher bid to regain top position.</div>
        @endif

        @if ($userHasBid)
            <div class="alert alert-info">ℹ️ <strong>You have already placed a bid on this auction.</strong> You can place a higher bid to increase your chances of winning.</div>
        @else
            <div class="alert alert-info">ℹ️ <strong>First time bidding?</strong> Make sure your bid meets the minimum requirement.</div>
        @endif

        @if (! $isParticipant)
            <div class="alert alert-info">Join this auction first. EMD will be locked from your wallet before bidding is enabled.</div>
            <form action="{{ route('user.auctions.join', $auction->id) }}" method="POST">
                @csrf
                <button type="submit" class="btn">Join Auction & Lock EMD</button>
            </form>
        @else
            <form id="bidForm" action="{{ route('user.auctions.bid', $auction->id) }}" method="POST">
                @csrf
                <div class="form-group">
                    <label style="display:block; margin-bottom:8px;">Your Bid Amount (₹) *</label>
                    <input id="bidAmountInput" type="number" name="bid_amount" step="0.01" min="{{ $minNextBid }}" value="{{ $minNextBid }}" required style="font-size: 18px; font-weight: bold;">
                    <small style="display:block;margin-top:8px;color:#6c757d;">Next valid bid starts from ₹{{ number_format($minNextBid, 2) }}</small>
                </div>
                <button id="bidSubmitBtn" type="submit" class="btn btn-success" style="font-size: 16px; padding: 15px 30px;">🎯 Place Bid</button>
            </form>
        @endif
    </div>

    <div class="card">
        <h3>Recent Bids ({{ $recentBids->count() }})</h3>
        @if ($recentBids->isEmpty())
            <p style="color: #718096;">No bids yet. Be the first to bid!</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Bidder</th>
                    <th>Amount</th>
                    <th>Time</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($recentBids as $bid)
                    <tr>
                        <td>{{ $bid->name }}</td>
                        <td><strong>₹{{ number_format((float) $bid->amount, 2) }}</strong></td>
                        <td>{{ \Carbon\Carbon::parse($bid->created_at)->format('d-M-Y H:i:s') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <script>
        const statusBox = document.getElementById('auctionStatusBox');
        const statusUrl = '{{ route('user.auctions.status', $auction->id) }}';
        const detailCountdown = document.getElementById('detailCountdown');
        const endTs = {{ \Carbon\Carbon::parse($auction->end_datetime)->timestamp }};
        const bidForm = document.getElementById('bidForm');
        const bidSubmitBtn = document.getElementById('bidSubmitBtn');
        const bidAmountInput = document.getElementById('bidAmountInput');
        const minNextBid = {{ (float) $minNextBid }};
        const updateAuctionStatus = async () => {
            try {
                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!data.success) {
                    statusBox.textContent = data.message || 'Unable to load status';
                    return;
                }
                const msg = data.data.message || 'Status available';
                const top = (data.data.top_bidders || []).map((b, i) => `H${i + 1}: ${b.bidder_name || ('User #' + b.user_id)} (₹${Number(b.amount).toFixed(2)})`).join(' | ');
                statusBox.innerHTML = `<strong>${msg}</strong><br>${top || 'No top bidders yet'}`;
            } catch (e) {
                statusBox.textContent = 'Unable to load status right now.';
            }
        };
        const tickCountdown = () => {
            const now = Math.floor(Date.now() / 1000);
            const diff = endTs - now;
            if (diff <= 0) {
                detailCountdown.textContent = 'Auction ending shortly';
                return;
            }
            const h = Math.floor(diff / 3600);
            const m = Math.floor((diff % 3600) / 60);
            const s = diff % 60;
            detailCountdown.textContent = `Time left: ${h}h ${m}m ${s}s`;
        };
        if (bidForm) {
            bidForm.addEventListener('submit', (e) => {
                const entered = Number(bidAmountInput.value || 0);
                if (entered >= (minNextBid * 1.2)) {
                    const ok = window.confirm('Your bid is significantly higher than minimum. Do you want to continue?');
                    if (!ok) {
                        e.preventDefault();
                        return;
                    }
                }
                bidSubmitBtn.disabled = true;
                bidSubmitBtn.textContent = 'Placing Bid...';
            });
        }
        tickCountdown();
        setInterval(tickCountdown, 1000);
        updateAuctionStatus();
        setInterval(updateAuctionStatus, 15000);
    </script>
@endsection
