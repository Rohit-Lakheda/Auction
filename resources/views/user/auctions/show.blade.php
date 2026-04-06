@extends('user.layout')

@section('title', $auction->title . ' - Auction Portal')

@section('content')
@php
    $viewerUserId = $viewerUserId ?? (int) session('user_id');
    $isLeading = $myHighestBid > 0 && abs((float) $myHighestBid - (float) $currentBid) < 0.000001;
    $isOutbidState = $userHasBid && ! $isLeading && $myHighestBid > 0;
    $myBidHistory = $myBidHistory ?? collect();
@endphp
<style>
    .auction-show-page { max-width: 1280px; margin: 0 auto; }
    .auction-show-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(300px, 380px);
        gap: 24px;
        align-items: start;
    }
    @media (max-width: 991px) {
        .auction-show-grid { grid-template-columns: 1fr; }
    }
    .as-back { margin-bottom: 12px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .as-back a.btn { text-decoration: none; }
    .as-breadcrumb { font-size: 14px; color: #6b7280; margin-bottom: 20px; }
    .as-breadcrumb a { color: #2563eb; text-decoration: none; font-weight: 500; }
    .as-breadcrumb a:hover { text-decoration: underline; color: #111; }
    .as-breadcrumb strong { color: #111; font-weight: 600; }
    /* Title row: name + LIVE + compact timer */
    .as-hero-line {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px 14px;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid #eef1f6;
    }
    .as-title {
        color: #111;
        font-size: clamp(17px, 2.1vw, 22px);
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.02em;
        line-height: 1.3;
        flex: 1 1 220px;
        min-width: 0;
    }
    .as-hero-chips {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }
    .as-badge-live {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.07em;
        background: linear-gradient(180deg, #dcedc8 0%, #c5e1a5 100%);
        color: #33691e;
        border: 1px solid #9ccc65;
        box-shadow: 0 1px 3px rgba(51, 105, 30, 0.12);
    }
    .as-timer-inline {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 4px 11px 4px 9px;
        border-radius: 999px;
        background: linear-gradient(180deg, #f0f7ff 0%, #e3f0ff 100%);
        border: 1px solid #b3d9ff;
        box-shadow: 0 1px 3px rgba(25, 118, 210, 0.08);
    }
    .as-timer-inline .as-timer-ico {
        font-size: 12px;
        color: #1976d2;
        opacity: 0.9;
    }
    .as-timer-stack {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0;
        line-height: 1.15;
    }
    .as-timer-micro {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #1976d2;
    }
    .as-timer-digits {
        font-size: 13px;
        font-weight: 700;
        color: #0d47a1;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.02em;
    }
    .as-outbid-alert {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px 18px;
        border-radius: 12px;
        border: 1px solid #ffcdd2;
        background: linear-gradient(180deg, #ffebee 0%, #fce4ec 100%);
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(198, 40, 40, 0.08);
    }
    .as-outbid-alert > i { color: #c62828; font-size: 22px; margin-top: 2px; }
    .as-outbid-alert .t { font-weight: 700; color: #111; font-size: 18px; margin-bottom: 4px; }
    .as-outbid-alert .t .st { color: #c62828; font-weight: 800; }
    .as-outbid-alert .d { color: #444; font-size: 14px; line-height: 1.5; }
    .as-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 22px;
    }
    @media (max-width: 900px) { .as-stat-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .as-stat-grid { grid-template-columns: 1fr; } }
    .as-stat {
        border-radius: 12px;
        padding: 14px 16px;
        border: 1px solid rgba(0,0,0,.06);
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    }
    .as-stat .ic { font-size: 14px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; font-weight: 600; color: #37474f; }
    .as-stat .ic i { opacity: 0.85; }
    .as-stat .val { font-size: 22px; font-weight: 800; color: #111; font-variant-numeric: tabular-nums; }
    .as-stat .sub { font-size: 11px; color: #546e7a; margin-top: 4px; }
    .as-stat-green { background: linear-gradient(180deg, #e8f5e9 0%, #f1f8f4 100%); border-color: #c8e6c9; }
    .as-stat-green .ic { color: #2e7d32; }
    .as-stat-blue { background: linear-gradient(180deg, #e3f2fd 0%, #f3f8ff 100%); border-color: #c5cae9; }
    .as-stat-blue .ic { color: #1565c0; }
    .as-stat-orange { background: linear-gradient(180deg, #fff3e0 0%, #fffaf5 100%); border-color: #ffcc80; }
    .as-stat-orange .ic { color: #ef6c00; }
    .as-stat-red { background: linear-gradient(180deg, #ffebee 0%, #fff8f8 100%); border-color: #ffcdd2; }
    .as-stat-red .ic { color: #c62828; }
    .as-desc {
        background: #fff;
        border: 1px solid #e8ecf2;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 22px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    }
    .as-desc h3 { color: #111; font-size: 17px; font-weight: 700; margin: 0 0 12px; }
    .as-desc .body { color: #333; line-height: 1.65; font-size: 14px; }
    .as-tables-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-top: 8px;
    }
    @media (max-width: 768px) { .as-tables-grid { grid-template-columns: 1fr; } }
    .as-table-card {
        background: #fff;
        border: 1px solid #e8ecf2;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
    }
    .as-table-card h4 {
        margin: 0;
        padding: 14px 16px;
        font-size: 15px;
        font-weight: 700;
        color: #111;
        background: #f4f6fa;
        border-bottom: 1px solid #e8ecf2;
    }
    .as-table-card table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .as-table-card th { text-align: left; padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; background: #fafbfc; border-bottom: 1px solid #eef1f6; }
    .as-table-card td { padding: 12px; border-bottom: 1px solid #f0f2f6; color: #111; vertical-align: middle; }
    .as-table-card tr:last-child td { border-bottom: none; }
    .as-bidder { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .as-bidder { font-weight: 600; }
    .as-you-badge {
        font-size: 10px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 10px;
        background: #ffebee;
        color: #c62828;
        border: 1px solid #ffcdd2;
    }
    .as-pill { 
        display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700;
    }
    .as-pill-lead { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
    .as-pill-out { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
    .as-pill-prev { background: #eceff1; color: #546e7a; border: 1px solid #cfd8dc; }
    /* Sidebar */
    .as-sidebar { position: sticky; top: 20px; display: flex; flex-direction: column; gap: 16px; }
    .as-side-card {
        background: #fff;
        border: 1px solid #e8ecf2;
        border-radius: 12px;
        padding: 18px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.07);
    }
    .as-side-card h3 { margin: 0 0 14px; font-size: 16px; font-weight: 700; color: #111; }
    .as-status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 14px;
    }
    .as-status-badge.lead { background: #e8f5e9; color: #1b5e20; border: 1px solid #81c784; }
    .as-status-badge.out { background: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; }
    .as-status-badge.neutral { background: #f4f6fa; color: #546e7a; border: 1px solid #cfd8dc; }
    .as-kv { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f2f6; font-size: 14px; }
    .as-kv:last-child { border-bottom: none; }
    .as-kv span:first-child { color: #64748b; }
    .as-kv strong { color: #111; font-weight: 700; }
    .as-status-live { font-size: 12px; color: #455a64; line-height: 1.45; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e0e6ed; }
    .as-status-live strong { color: #1565c0; }
    .as-btn-primary {
        display: flex; width: 100%; justify-content: center; align-items: center; gap: 8px;
        padding: 14px 18px; border: none; border-radius: 10px; cursor: pointer;
        font-size: 15px; font-weight: 700; color: #fff !important;
        background: linear-gradient(180deg, #1976d2 0%, #1565c0 100%);
        box-shadow: 0 3px 10px rgba(21, 101, 192, 0.35);
        text-decoration: none;
    }
    .as-btn-primary:hover { filter: brightness(1.05); }
    .as-btn-primary:disabled { opacity: 0.65; cursor: not-allowed; }
    .as-btn-watch {
        display: flex; width: 100%; justify-content: center; align-items: center; gap: 8px;
        margin-top: 10px;
        padding: 12px 16px; border-radius: 10px; border: 1px solid #c5cae9;
        background: #fafbff; color: #3949ab !important; font-weight: 600; font-size: 14px; cursor: pointer;
    }
    .as-btn-watch:hover { background: #eef1fb; }
    .as-btn-orange {
        display: flex; width: 100%; justify-content: center; align-items: center; gap: 8px;
        padding: 14px 18px; border: none; border-radius: 10px; cursor: pointer;
        font-size: 15px; font-weight: 700; color: #fff !important;
        background: linear-gradient(180deg, #ff9800 0%, #f57c00 100%);
        box-shadow: 0 3px 10px rgba(245, 124, 0, 0.35);
    }
    .as-form-group { margin-bottom: 14px; }
    .as-form-group label { display: block; font-weight: 600; color: #111; margin-bottom: 8px; font-size: 14px; }
    .as-form-group input {
        width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 18px; font-weight: 700; color: #111;
    }
    .as-form-group input:focus { outline: none; border-color: #90caf9; box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.15); }
    .as-form-group small { display: block; margin-top: 6px; color: #64748b; font-size: 12px; }
    .as-participation-note {
        font-size: 13px; color: #5d4037; line-height: 1.5; margin-bottom: 12px;
        padding: 12px; background: #fff8f0; border-radius: 10px; border: 1px solid #ffe0b2;
    }
</style>

<div class="auction-show-page">
    @if (session('bid_success'))
        <div class="alert-show alert-show-success" style="margin-bottom:16px;">
            <span class="alert-keyword">Success.</span><span class="alert-body">{{ session('bid_success') }}</span>
        </div>
    @endif
    @if (session('bid_error'))
        <div class="alert-show alert-show-error" style="margin-bottom:16px;">
            <span class="alert-keyword">Error.</span><span class="alert-body">{{ session('bid_error') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert-show alert-show-error" style="margin-bottom:16px;">
            <span class="alert-keyword">Error.</span><span class="alert-body">{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="auction-show-grid">
        <div class="auction-main">
            <div class="as-back">
                <a href="{{ route('user.auctions.index') }}" class="btn btn-secondary">← Back to Auctions</a>
            </div>
            <div class="as-breadcrumb">
                <a href="{{ route('user.auctions.index') }}">Auctions</a> / <strong>{{ $auction->title }}</strong>
            </div>

            <div class="as-hero-line">
                <h1 class="as-title">{{ $auction->title }}</h1>
                <div class="as-hero-chips">
                    <span class="as-badge-live"><i class="fas fa-circle" style="font-size:7px;"></i> LIVE</span>
                    <div class="as-timer-inline" title="Time remaining until auction ends">
                        <i class="far fa-clock as-timer-ico"></i>
                        <div class="as-timer-stack">
                            <span class="as-timer-micro">Time left</span>
                            <span id="detailCountdown" class="as-timer-digits">—</span>
                        </div>
                    </div>
                </div>
            </div>

            @if($isOutbidState && $isParticipant)
                <div class="as-outbid-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <div class="t">You've been <span class="st">outbid</span></div>
                        <div class="d">Another bidder is ahead. Place at least <strong>₹{{ number_format($minNextBid, 2) }}</strong> to compete.</div>
                    </div>
                </div>
            @endif

            <div class="as-stat-grid">
                <div class="as-stat as-stat-green">
                    <div class="ic"><i class="fas fa-tag"></i> Base price</div>
                    <div class="val">₹{{ number_format((float) $auction->base_price, 2) }}</div>
                </div>
                <div class="as-stat as-stat-blue">
                    <div class="ic"><i class="fas fa-arrow-up"></i> Highest bid</div>
                    <div class="val">₹{{ number_format($currentBid, 2) }}</div>
                </div>
                <div class="as-stat as-stat-orange">
                    <div class="ic"><i class="fas fa-bolt"></i> Minimum next bid</div>
                    <div class="val">₹{{ number_format($minNextBid, 2) }}</div>
                </div>
                <div class="as-stat as-stat-red">
                    <div class="ic"><i class="fas fa-user"></i> Your bid</div>
                    <div class="val">{{ $myHighestBid > 0 ? '₹'.number_format($myHighestBid, 2) : '—' }}</div>
                    <div class="sub">{{ $userHasBid ? ($isLeading ? 'You are leading' : 'Not the highest') : 'No bid yet' }}</div>
                </div>
            </div>

            <div class="as-desc">
                <h3><i class="fas fa-align-left" style="color:#3949ab;margin-right:8px;"></i>Description</h3>
                <div class="body">{!! nl2br(e($auction->description)) !!}</div>
            </div>

            <div class="as-tables-grid">
                <div class="as-table-card">
                    <h4><i class="fas fa-list-ul" style="margin-right:8px;color:#1565c0;"></i>My bid summary</h4>
                    @if($myBidHistory->isEmpty())
                        <table><tbody><tr><td style="padding:20px;color:#64748b;">You have not placed a bid on this auction yet.</td></tr></tbody></table>
                    @else
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($myBidHistory as $idx => $mb)
                                @php
                                    $isFirst = $idx === 0;
                                    if ($isFirst) {
                                        $rowStatus = $isLeading ? 'lead' : 'out';
                                    } else {
                                        $rowStatus = 'prev';
                                    }
                                @endphp
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($mb->created_at)->format('d M Y, H:i') }}</td>
                                    <td><strong>₹{{ number_format((float) $mb->amount, 2) }}</strong></td>
                                    <td>
                                        @if($rowStatus === 'lead')
                                            <span class="as-pill as-pill-lead">Leading</span>
                                        @elseif($rowStatus === 'out')
                                            <span class="as-pill as-pill-out">Outbid</span>
                                        @else
                                            <span class="as-pill as-pill-prev">Previous</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                <div class="as-table-card">
                    <h4><i class="fas fa-users" style="margin-right:8px;color:#7b1fa2;"></i>Recent bids</h4>
                    @if ($recentBids->isEmpty())
                        <table><tbody><tr><td style="padding:20px;color:#64748b;">No bids yet — be the first.</td></tr></tbody></table>
                    @else
                        <table>
                            <thead>
                                <tr>
                                    <th>Bidder</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($recentBids as $bid)
                                @php
                                    $isYou = (int) $bid->user_id === (int) $viewerUserId;
                                    $amt = (float) $bid->amount;
                                    $isLeadingRow = abs($amt - (float) $currentBid) < 0.000001;
                                    if ($isLeadingRow) {
                                        $rs = 'lead';
                                    } elseif ($isYou && ! $isLeadingRow) {
                                        $rs = 'out';
                                    } else {
                                        $rs = 'prev';
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <div class="as-bidder">
                                            <i class="fas fa-user" style="color:#90a4ae;font-size:12px;"></i>
                                            <span>{{ $bid->name }}</span>
                                            @if($isYou)<span class="as-you-badge">You</span>@endif
                                        </div>
                                    </td>
                                    <td><strong>₹{{ number_format($amt, 2) }}</strong></td>
                                    <td>
                                        @if($rs === 'lead')
                                            <span class="as-pill as-pill-lead">Leading</span>
                                        @elseif($rs === 'out')
                                            <span class="as-pill as-pill-out">Outbid</span>
                                        @else
                                            <span class="as-pill as-pill-prev">Previous</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <aside class="as-sidebar">
            <div class="as-side-card">
                <h3><i class="fas fa-chart-pie" style="margin-right:8px;color:#1565c0;"></i>Your auction status</h3>
                @if(! $userHasBid)
                    <span class="as-status-badge neutral">No bids yet</span>
                @elseif($isLeading)
                    <span class="as-status-badge lead">Leading</span>
                @else
                    <span class="as-status-badge out">Outbid</span>
                @endif
                <div class="as-kv">
                    <span>Your last bid</span>
                    <strong>{{ $myHighestBid > 0 ? '₹'.number_format($myHighestBid, 2) : '—' }}</strong>
                </div>
                <div class="as-kv">
                    <span>Minimum next bid</span>
                    <strong>₹{{ number_format($minNextBid, 2) }}</strong>
                </div>
                <div class="as-kv">
                    <span>Current high</span>
                    <strong>₹{{ number_format($currentBid, 2) }}</strong>
                </div>
                <div id="auctionStatusBox" class="as-status-live">Loading rank &amp; top bidders…</div>
            </div>

            @if($isParticipant)
                <div class="as-side-card">
                    <h3><i class="fas fa-gavel" style="margin-right:8px;color:#2e7d32;"></i>Place bid</h3>
                    @if ($userHasBid && ! $isLeading)
                        <div class="alert-show alert-show-info" style="margin-bottom: 20px;">
                            <span class="alert-keyword">Tip.</span><span class="alert-body">You can raise your bid to stay in the lead.</span>
                        </div>
                    @elseif(!$userHasBid)
                        <div class="alert-show alert-show-info" style="margin-bottom: 20px;">
                            <span class="alert-keyword">New?</span><span class="alert-body">Enter at least the minimum next bid.</span>
                        </div>
                    @endif
                    <form id="bidForm" action="{{ route('user.auctions.bid', $auction->id) }}" method="POST">
                        @csrf
                        <div class="as-form-group">
                            <label for="bidAmountInput">Bid amount (₹)</label>
                            <input id="bidAmountInput" type="number" name="bid_amount" step="0.01" min="{{ $minNextBid }}" value="{{ $minNextBid }}" required>
                            <small>Valid from <strong>₹{{ number_format($minNextBid, 2) }}</strong> upward</small>
                        </div>
                        <button id="bidSubmitBtn" type="submit" class="as-btn-primary"><i class="fas fa-hammer"></i> Place bid</button>
                    </form>
                    <form method="POST" action="{{ route('user.auctions.watch', $auction->id) }}">
                        @csrf
                        <button type="submit" class="as-btn-watch">
                            <i class="fas fa-heart"></i> {{ $isWatchlisted ? 'Remove from watchlist' : 'Add to watchlist' }}
                        </button>
                    </form>
                </div>
            @else
                <div class="as-side-card">
                    <h3><i class="fas fa-lock-open" style="margin-right:8px;color:#ef6c00;"></i>Participation</h3>
                    <div class="as-participation-note">
                        <strong>Participation fee:</strong> ₹{{ number_format($requiredEmd, 2) }}. Pay to unlock bidding on this auction.
                    </div>
                    <form method="POST" action="{{ route('user.auctions.join', $auction->id) }}">
                        @csrf
                        <button type="submit" class="as-btn-orange"><i class="fas fa-wallet"></i> Complete participation fee &amp; bid</button>
                    </form>
                    <form method="POST" action="{{ route('user.auctions.watch', $auction->id) }}" style="margin-top:10px;">
                        @csrf
                        <button type="submit" class="as-btn-watch">
                            <i class="fas fa-heart"></i> {{ $isWatchlisted ? 'Remove from watchlist' : 'Add to watchlist' }}
                        </button>
                    </form>
                </div>
            @endif
        </aside>
    </div>
</div>

<script>
    const statusBox = document.getElementById('auctionStatusBox');
    const statusUrl = @json(route('user.auctions.status', $auction->id));
    const detailCountdown = document.getElementById('detailCountdown');
    const endTs = @json(\Carbon\Carbon::parse($auction->end_datetime)->timestamp);
    const bidForm = document.getElementById('bidForm');
    const bidSubmitBtn = document.getElementById('bidSubmitBtn');
    const bidAmountInput = document.getElementById('bidAmountInput');
    const minNextBid = @json((float) $minNextBid);
    const updateAuctionStatus = async () => {
        try {
            const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.success) {
                statusBox.textContent = data.message || 'Unable to load status';
                return;
            }
            const msg = data.data.message || 'Status available';
            const top = (data.data.top_bidders || []).map((b, i) => `H${i + 1}: ${b.bidder_name || ('User #' + b.user_id)} (₹${Number(b.amount).toFixed(2)})`).join(' · ');
            statusBox.innerHTML = '<strong>' + msg + '</strong><br>' + (top || 'No top bidders yet');
        } catch (e) {
            statusBox.textContent = 'Unable to load status right now.';
        }
    };
    const tickCountdown = () => {
        const now = Math.floor(Date.now() / 1000);
        const diff = endTs - now;
        if (diff <= 0) {
            detailCountdown.textContent = 'Ending soon';
            return;
        }
        const h = Math.floor(diff / 3600);
        const m = Math.floor((diff % 3600) / 60);
        const s = diff % 60;
        detailCountdown.textContent = h + 'h ' + m + 'm ' + s + 's';
    };
    if (bidForm && bidSubmitBtn && bidAmountInput) {
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
            bidSubmitBtn.textContent = 'Placing bid…';
        });
    }
    tickCountdown();
    setInterval(tickCountdown, 1000);
    updateAuctionStatus();
    setInterval(updateAuctionStatus, 15000);
</script>
@endsection
