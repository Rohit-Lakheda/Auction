@extends('user.layout')

@section('title', 'Active Auctions - Auction Portal')

@section('content')
    <style>
        .filters-panel {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }
        .filter-label {
            display: block;
            margin-bottom: 6px;
            color: #1a237e;
            font-size: 14px;
        }
        .filter-control {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #d8dfeb;
            border-radius: 10px;
            background: #fff;
            color: #2c3e50;
            transition: border-color .2s, box-shadow .2s;
        }
        .filter-control:focus {
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26,35,126,.12);
            outline: none;
        }
        .search-wrap { position: relative; }
        .search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #7b8794;
            pointer-events: none;
        }
        .search-wrap .filter-control { padding-left: 36px; }
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .section-link { color: #1a237e; text-decoration: none; font-size: 14px; }
        .watchlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 12px;
        }
        .watch-item {
            border: 1px solid #e6ebf2;
            border-radius: 10px;
            padding: 12px;
            background: #fbfcff;
        }
        .watch-item h4 { font-size: 15px; color: #1a237e; margin-bottom: 8px; }
        .watch-meta { color: #6c757d; font-size: 13px; margin-bottom: 8px; }
        .auction-card { padding: 18px; }
        @media (max-width: 900px) {
            .filters-panel { grid-template-columns: 1fr; }
        }
    </style>
    <div class="card">
        <h2>Active Auctions</h2>
        <p style="color: #6c757d; margin-bottom: 20px;">Browse, watch, and join active auctions quickly.</p>
        <form method="GET" class="filters-panel">
            <div>
                <label class="filter-label">Search</label>
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input class="filter-control" type="text" name="search" value="{{ $search }}" placeholder="Search by auction title">
                </div>
            </div>
            {{-- [EMD DISABLED] EMD Filter removed --}}
            <div>
                <label class="filter-label">Sort By</label>
                <select class="filter-control" name="sort">
                    <option value="ending_soon" {{ $sort==='ending_soon' ? 'selected' : '' }}>Ending Soon</option>
                    <option value="highest_bid" {{ $sort==='highest_bid' ? 'selected' : '' }}>Highest Bid</option>
                    <option value="newest" {{ $sort==='newest' ? 'selected' : '' }}>Newest</option>
                </select>
            </div>
            <div>
                <label class="filter-label">Per Page</label>
                <select class="filter-control" name="per_page">
                    @foreach(['10','20','50','100','all'] as $size)
                        <option value="{{ $size }}" {{ ($perPage ?? '12') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn" style="height:46px;">Apply</button>
        </form>
    </div>

    @if ($auctions->isEmpty())
        <div class="card">
            <p style="color: #718096; text-align: center;">No auctions match your filters right now.</p>
        </div>
    @else
        @if($watchlistAuctions->isNotEmpty())
            <div class="card">
                <div class="section-title">
                    <h3 style="color:#1a237e;">Your Watchlist</h3>
                    <a class="section-link" href="{{ route('user.dashboard') }}">View in Dashboard</a>
                </div>
                <div class="watchlist-grid">
                    @foreach($watchlistAuctions as $w)
                        <div class="watch-item">
                            <h4>{{ $w->title }}</h4>
                            <div class="watch-meta">Status: {{ strtoupper($w->status) }}</div>
                            {{-- [EMD DISABLED] EMD amount removed from watchlist card --}}
                            <a href="{{ route('user.auctions.show', $w->id) }}" class="btn btn-secondary" style="padding:7px 10px;font-size:13px;">View</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        @if($recentlyViewed->isNotEmpty())
            <div class="card">
                <div class="section-title">
                    <h3 style="color:#1a237e;">Recently Viewed</h3>
                    <a class="section-link" href="{{ route('user.dashboard') }}">Go Dashboard</a>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    @foreach($recentlyViewed as $rv)
                        <a href="{{ route('user.auctions.show', $rv->id) }}" class="btn btn-secondary" style="padding:8px 12px;">{{ $rv->title }}</a>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="grid">
            @foreach ($auctions as $auction)
                @php
                    $currentBid = $auction->current_bid ?? $auction->base_price;
                    $minNextBid = (float) $currentBid + (float) $auction->min_increment;
                    $hoursLeft = floor((strtotime($auction->end_datetime) - time()) / 3600);
                    // [EMD DISABLED] $joined and EMD lock check removed
                    $watchlisted = (int)($auction->watchlisted_count ?? 0) > 0;
                @endphp
                <div class="auction-card">
                    <h3 style="color: #1a237e; margin-bottom: 12px; font-weight: 400;">{{ $auction->title }}</h3>
                    <p style="color: #6c757d; margin-bottom: 12px;">{{ \Illuminate\Support\Str::limit($auction->description, 100) }}</p>

                    <div style="border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 15px 0; margin: 15px 0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #718096;">Current Bid:</span>
                            <strong style="color: #667eea; font-size: 18px;">₹{{ number_format((float) $currentBid, 2) }}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #718096;">Min Next Bid:</span>
                            <strong>₹{{ number_format($minNextBid, 2) }}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #718096;">Total Bids:</span>
                            <strong>{{ $auction->bid_count }}</strong>
                        </div>
                        {{-- [EMD DISABLED] EMD amount row removed --}}
                    </div>

                    <p style="font-size: 13px; color: #718096; margin-bottom: 10px;">
                        ⏰ Ends: {{ \Carbon\Carbon::parse($auction->end_datetime)->format('d-M-Y') }}
                        @if ($hoursLeft > 0 && $hoursLeft < 24)
                            <br><strong style="color: #e53e3e;">⚠️ Ending in {{ $hoursLeft }} hours!</strong>
                        @endif
                    </p>
                    <p class="countdown" data-end="{{ \Carbon\Carbon::parse($auction->end_datetime)->timestamp }}" style="font-size:13px;color:#c62828;font-weight:700;margin-bottom:12px;"></p>
                    {{-- [EMD DISABLED] Join button and EMD locked alert removed --}}
                    <div style="display:flex;gap:8px;">
                        <a href="{{ route('user.auctions.show', $auction->id) }}" class="btn" style="flex:1;text-align:center;">View Details</a>
                    </div>
                    <form method="POST" action="{{ route('user.auctions.watch', $auction->id) }}" style="margin-top:8px;">
                        @csrf
                        <button type="submit" class="btn btn-secondary" style="width:100%;">{{ $watchlisted ? '★ Remove Watchlist' : '☆ Save Auction' }}</button>
                    </form>
                </div>
            @endforeach
        </div>
        <div class="card">
            @include('partials.grid-pagination', ['rows' => $auctions])
        </div>
    @endif
    <script>
        const rows = document.querySelectorAll('.countdown');
        const tick = () => {
            const now = Math.floor(Date.now() / 1000);
            rows.forEach((el) => {
                const end = Number(el.dataset.end || 0);
                const diff = end - now;
                if (diff <= 0) {
                    el.textContent = 'Auction closing shortly...';
                    return;
                }
                const h = Math.floor(diff / 3600);
                const m = Math.floor((diff % 3600) / 60);
                const s = diff % 60;
                el.textContent = `Time left: ${h}h ${m}m ${s}s`;
            });
        };
        tick();
        setInterval(tick, 1000);
    </script>
@endsection
