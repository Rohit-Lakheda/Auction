@extends('user.layout')

@section('title', 'Dashboard - Auction Portal')

@section('content')
@php
    $displayName = $profile->name ?? (string) session('name', 'User');
@endphp
<style>
    .ux-shell { display: grid; gap: 16px; }
    .ux-card { background: #fff; border: 1px solid #e6ebf2; border-radius: 14px; box-shadow: 0 2px 8px rgba(15, 23, 42, .06); }
    .ux-head { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:18px 20px; }
    .ux-head h2 { margin:0; color:#111; font-size:28px; letter-spacing:-0.02em; }
    .ux-sub { color:#444; margin-top:4px; font-size:14px; }
    .accent-blue { color:#1565c0; font-weight:600; }
    .accent-green { color:#2e7d32; font-weight:600; }
    .accent-orange { color:#ef6c00; font-weight:600; }
    .accent-purple { color:#7b1fa2; font-weight:600; }
    .ux-head-right { display:flex; align-items:center; gap:12px; color:#111; }
    .ux-avatar { width:44px; height:44px; border-radius:50%; background:#fff3e0; color:#ef6c00; display:flex; align-items:center; justify-content:center; font-size:18px; }
    .kpi-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; padding: 0 20px 20px; }
    .kpi-item { border:1px solid #e6ebf2; border-radius:12px; padding:14px; background:#fbfcff; }
    .kpi-link { text-decoration:none; color:inherit; display:block; }
    .kpi-link:hover .kpi-item { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(16, 24, 40, .08); }
    .kpi-item { transition: transform .18s ease, box-shadow .18s ease; }
    .kpi-item.kpi-bids { background:#eef6ff; border-color:#d6e8ff; }
    .kpi-item.kpi-won { background:#edf9f1; border-color:#d4efd9; }
    .kpi-item.kpi-active { background:#fff7ea; border-color:#ffe3b8; }
    .kpi-item.kpi-watch { background:#f6f0ff; border-color:#e3d5ff; }
    .kpi-top { display:flex; justify-content:space-between; align-items:center; color:#111; font-size:13px; margin-bottom:8px; font-weight:500; }
    .kpi-icon { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; }
    .kpi-num { font-size:28px; color:#111; font-weight:700; line-height:1; }
    .kpi-sub { margin-top:6px; color:#555; font-size:13px; }
    .dash-grid { display:grid; grid-template-columns: 1.3fr 1fr; gap:16px; }
    .panel-title { padding:16px 18px; border-bottom:1px solid #edf1f7; display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .panel-title h3 { margin:0; color:#111; font-size:20px; font-weight:600; }
    .panel-title h3 i { margin-right:6px; color:#1565c0; }
    .panel-body { padding:16px 18px; }
    .bar-wrap { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; align-items:end; min-height:140px; margin-bottom:12px; }
    .bar-col { text-align:center; }
    .bar { border-radius:10px 10px 0 0; min-height:20px; }
    .bar-wrap .bar-col:nth-child(1) .bar { background:linear-gradient(180deg,#4fa6ff,#8cc8ff); }
    .bar-wrap .bar-col:nth-child(2) .bar { background:linear-gradient(180deg,#ffb357,#ffd08d); }
    .bar-wrap .bar-col:nth-child(3) .bar { background:linear-gradient(180deg,#4ecb89,#8ce2b4); }
    .bar-label { margin-top:6px; font-size:12px; color:#333; font-weight:500; }
    .chart-tip {
        position: fixed;
        z-index: 1200;
        background: #1d2f53;
        color: #fff;
        font-size: 12px;
        padding: 6px 8px;
        border-radius: 8px;
        pointer-events: none;
        opacity: 0;
        transform: translateY(6px);
        transition: opacity .12s ease, transform .12s ease;
    }
    .chart-tip.show { opacity: 1; transform: translateY(0); }
    .activity-list { display:grid; gap:10px; }
    .activity-item { display:flex; align-items:center; justify-content:space-between; gap:10px; border:1px solid #edf1f7; border-radius:10px; padding:10px 12px; }
    .activity-left { display:flex; align-items:center; gap:10px; min-width:0; }
    .activity-icon { width:32px; height:32px; border-radius:50%; background:#edf4ff; color:#1a237e; display:flex; align-items:center; justify-content:center; font-size:14px; }
    .activity-item:nth-child(2) .activity-icon { background:#fff4e8; color:#ef6c00; }
    .activity-item:nth-child(3) .activity-icon { background:#edf9f1; color:#2e7d32; }
    .activity-item:nth-child(4) .activity-icon { background:#f6f0ff; color:#7b1fa2; }
    .activity-main { min-width:0; }
    .activity-title { font-size:14px; color:#111; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:500; }
    .activity-sub { font-size:12px; color:#555; }
    .activity-amount { color:#2e7d32; font-weight:700; white-space:nowrap; }
    .quick-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px; }
    .quick-box { border:1px solid #e6ebf2; border-radius:12px; padding:14px; }
    .quick-box h4 { margin:0 0 8px; color:#111; font-size:16px; font-weight:600; }
    .quick-box h4 i { color:#7b1fa2; margin-right:6px; }
    .quick-item { display:flex; justify-content:space-between; gap:8px; font-size:13px; padding:6px 0; border-bottom:1px dashed #edf1f7; color:#111; }
    .quick-item:last-child { border-bottom:none; }
    .link-btn { color:#0d47a1; text-decoration:none; font-size:13px; font-weight:500; }
    .link-btn:hover { color:#111; text-decoration: underline; }
    .btn-soft { border-radius:10px; padding:8px 12px; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; border:1px solid transparent; }
    .btn-soft-blue { background:#e8f2ff; color:#1255a8; border-color:#c9defa; }
    .btn-soft-blue:hover { background:#d8eaff; }
    .btn-soft-green { background:#e9f8ef; color:#1f7a43; border-color:#ccead7; }
    .btn-soft-green:hover { background:#dcf4e6; }
    .btn-soft-orange { background:#fff3e5; color:#c96a00; border-color:#f6ddbc; }
    .btn-soft-orange:hover { background:#ffe9cc; }
    .btn-soft-purple { background:#f4edff; color:#6835ad; border-color:#e1d2fb; }
    .btn-soft-purple:hover { background:#ebddff; }
    .active-auction-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
    .active-auction-card { border:1px solid #e8edf4; border-radius:12px; padding:12px; background:linear-gradient(180deg,#ffffff 0%,#f9fbff 100%); }
    .active-auction-card:nth-child(2n) { background:linear-gradient(180deg,#ffffff 0%,#fffaf3 100%); }
    .active-auction-card:nth-child(3n) { background:linear-gradient(180deg,#ffffff 0%,#f5fcf8 100%); }
    .active-auction-title { color:#1a237e; font-size:14px; font-weight:700; margin-bottom:8px; }
    .active-auction-meta { display:flex; justify-content:space-between; font-size:12px; color:#5f6b7a; margin-bottom:8px; }
    .active-auction-price { font-size:18px; color:#1f7a43; font-weight:700; margin-bottom:10px; }
    @media (max-width: 1080px) { .dash-grid { grid-template-columns: 1fr; } }
</style>

<div class="ux-shell">
    <div class="ux-card">
        <div class="ux-head">
            <div>
                <h2>Welcome Back, {{ $displayName }}!</h2>
                <div class="ux-sub">Here is a quick snapshot of your <span class="accent-blue">auction activity</span> and <span class="accent-green">status today</span>.</div>
            </div>
            <div class="ux-head-right">
                <div class="ux-avatar"><i class="fas fa-user"></i></div>
                <strong>{{ $displayName }}</strong>
            </div>
        </div>
        <div class="kpi-grid">
            <a class="kpi-link" href="{{ route('user.my-bids') }}">
                <div class="kpi-item kpi-bids">
                    <div class="kpi-top"><span>Total Bids</span><span class="kpi-icon" style="background:#e3f2fd;color:#1565c0;"><i class="fas fa-gavel"></i></span></div>
                    <div class="kpi-num">{{ (int)($stats['total_bids'] ?? 0) }}</div>
                    <div class="kpi-sub">All-time <span class="accent-blue">bids placed</span></div>
                </div>
            </a>
            <a class="kpi-link" href="{{ route('user.auctions.index', ['view' => 'won']) }}">
                <div class="kpi-item kpi-won">
                    <div class="kpi-top"><span>Won Auctions</span><span class="kpi-icon" style="background:#e8f5e9;color:#2e7d32;"><i class="fas fa-trophy"></i></span></div>
                    <div class="kpi-num">{{ (int)($stats['won_auctions'] ?? 0) }}</div>
                    <div class="kpi-sub"><span class="accent-green">Auctions won</span> by you</div>
                </div>
            </a>
            <a class="kpi-link" href="{{ route('user.auctions.index', ['view' => 'bidding']) }}">
                <div class="kpi-item kpi-active">
                    <div class="kpi-top"><span>Active Bidding</span><span class="kpi-icon" style="background:#fff3e0;color:#ef6c00;"><i class="fas fa-bolt"></i></span></div>
                    <div class="kpi-num">{{ (int)($stats['active_bidding'] ?? 0) }}</div>
                    <div class="kpi-sub"><span class="accent-orange">Open auctions</span> you’ve bid on</div>
                </div>
            </a>
            <a class="kpi-link" href="{{ route('user.auctions.index', ['view' => 'watchlist']) }}">
                <div class="kpi-item kpi-watch">
                    <div class="kpi-top"><span>Watchlist</span><span class="kpi-icon" style="background:#f3e5f5;color:#7b1fa2;"><i class="fas fa-star"></i></span></div>
                    <div class="kpi-num">{{ (int)($watchlistTotal ?? 0) }}</div>
                    <div class="kpi-sub"><span class="accent-purple">Saved</span> active auctions</div>
                </div>
            </a>
        </div>
    </div>

    <div class="dash-grid">
        <div class="ux-card">
            <div class="panel-title">
                <h3><i class="fas fa-chart-column"></i> Performance <span class="accent-blue">Overview</span></h3>
                <a class="link-btn" href="{{ route('user.my-bids') }}">View all bids</a>
            </div>
            <div class="panel-body">
                @php
                    $metricA = (int)($stats['total_bids'] ?? 0);
                    $metricB = (int)($stats['active_bidding'] ?? 0);
                    $metricC = (int)($stats['won_auctions'] ?? 0);
                    $maxMetric = max(1, $metricA, $metricB, $metricC);
                @endphp
                <div class="bar-wrap">
                    <div class="bar-col">
                        <div class="bar metric-bar" data-height="{{ max(20, (int)(($metricA / $maxMetric) * 120)) }}" data-label="Total Bids" data-value="{{ $metricA }}"></div>
                        <div class="bar-label">Total Bids</div>
                    </div>
                    <div class="bar-col">
                        <div class="bar metric-bar" data-height="{{ max(20, (int)(($metricB / $maxMetric) * 120)) }}" data-label="Active Bidding" data-value="{{ $metricB }}"></div>
                        <div class="bar-label">Active</div>
                    </div>
                    <div class="bar-col">
                        <div class="bar metric-bar" data-height="{{ max(20, (int)(($metricC / $maxMetric) * 120)) }}" data-label="Won Auctions" data-value="{{ $metricC }}"></div>
                        <div class="bar-label">Won</div>
                    </div>
                </div>
                <div class="quick-grid">
                    <div class="quick-box">
                        <h4><i class="fas fa-bullseye"></i> Currently Winning</h4>
                        @forelse($winningBids->take(3) as $bid)
                            <div class="quick-item"><span>{{ \Illuminate\Support\Str::limit($bid->title, 24) }}</span><strong>₹{{ number_format((float)$bid->my_bid, 0) }}</strong></div>
                        @empty
                            <div class="quick-item"><span>No leading bids yet</span><span>-</span></div>
                        @endforelse
                    </div>
                    <div class="quick-box">
                        <h4><i class="fas fa-user-circle"></i> Profile</h4>
                        <div class="quick-item"><span>Name</span><strong>{{ $profile->name ?? '-' }}</strong></div>
                        <div class="quick-item"><span>Registration</span><strong>{{ $profile->registration_id ?? '-' }}</strong></div>
                        <div style="margin-top:8px;"><a class="link-btn" href="{{ route('user.profile') }}">Open full profile</a></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ux-card">
            <div class="panel-title">
                <h3><i class="fas fa-bell"></i> Recent <span class="accent-purple">Activity</span></h3>
                <a class="btn-soft btn-soft-purple" href="{{ route('user.notifications') }}"><i class="fas fa-bell"></i> Notifications</a>
            </div>
            <div class="panel-body">
                <div class="activity-list">
                    @forelse($recentBids->take(4) as $bid)
                        <div class="activity-item">
                            <div class="activity-left">
                                <div class="activity-icon"><i class="fas fa-gavel"></i></div>
                                <div class="activity-main">
                                    <div class="activity-title">Bid on {{ $bid->title }}</div>
                                    <div class="activity-sub">{{ \Carbon\Carbon::parse($bid->created_at)->format('d M, h:i A') }}</div>
                                </div>
                            </div>
                            <div class="activity-amount">₹{{ number_format((float)$bid->amount, 2) }}</div>
                        </div>
                    @empty
                        <div class="activity-item">
                            <div class="activity-left">
                                <div class="activity-icon"><i class="fas fa-info-circle"></i></div>
                                <div class="activity-main">
                                    <div class="activity-title">No recent bid activity</div>
                                    <div class="activity-sub">Start bidding to see live updates here</div>
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>
                <div style="margin-top:12px; display:flex; justify-content:flex-end;">
                    <a class="btn-soft btn-soft-blue" href="{{ route('user.my-bids') }}"><i class="fas fa-list-check"></i> Open My Bids</a>
                </div>
            </div>
        </div>
    </div>

    <div class="ux-card">
        <div class="panel-title">
                <h3><i class="fas fa-list-check"></i> Quick <span class="accent-green">Access</span></h3>
            <a class="link-btn" href="{{ route('user.auctions.index') }}">Browse auctions</a>
        </div>
        <div class="panel-body">
            <div class="quick-grid">
                <div class="quick-box">
                    <h4><i class="fas fa-star"></i> Watchlist</h4>
                    @forelse($watchlistAuctions->take(4) as $auction)
                        <div class="quick-item"><span>{{ \Illuminate\Support\Str::limit($auction->title, 24) }}</span><a class="link-btn" href="{{ route('user.auctions.show', $auction->id) }}">View</a></div>
                    @empty
                        <div class="quick-item"><span>No watched auctions</span><span>-</span></div>
                    @endforelse
                </div>
                <div class="quick-box">
                    <h4><i class="fas fa-fire"></i> Active Auctions</h4>
                    @forelse($activeAuctions->take(4) as $auction)
                        <div class="active-auction-card">
                            <div class="active-auction-title">{{ \Illuminate\Support\Str::limit($auction->title, 32) }}</div>
                            <div class="active-auction-meta">
                                <span><i class="fas fa-clock"></i> {{ \Carbon\Carbon::parse($auction->end_datetime)->format('d M') }}</span>
                                <span><i class="fas fa-arrow-up"></i> +₹{{ number_format((float)$auction->min_increment, 0) }}</span>
                            </div>
                            <div class="active-auction-price">₹{{ number_format((float)($auction->current_bid ?? $auction->base_price), 2) }}</div>
                            <a class="btn-soft btn-soft-green" href="{{ route('user.auctions.show', $auction->id) }}"><i class="fas fa-hammer"></i> Bid now</a>
                        </div>
                    @empty
                        <div class="quick-item"><span>No active auctions</span><span>-</span></div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    const tip = document.createElement('div');
    tip.className = 'chart-tip';
    document.body.appendChild(tip);

    document.querySelectorAll('.metric-bar').forEach((el) => {
        const h = Number(el.getAttribute('data-height') || 20);
        el.style.height = `${Math.max(20, h)}px`;
        el.style.cursor = 'pointer';
        el.addEventListener('mouseenter', () => {
            tip.textContent = `${el.getAttribute('data-label')}: ${el.getAttribute('data-value')}`;
            tip.classList.add('show');
        });
        el.addEventListener('mousemove', (e) => {
            tip.style.left = `${e.clientX + 12}px`;
            tip.style.top = `${e.clientY - 28}px`;
        });
        el.addEventListener('mouseleave', () => {
            tip.classList.remove('show');
        });
    });
</script>
@endsection
