@extends('user.layout')

@section('title', 'Auctions - Auction Portal')

@section('content')
@if(session('payment') === 'success')
    <div class="alert-show alert-show-success" style="margin-bottom:14px;">
        <span class="alert-keyword">Success.</span><span class="alert-body">Payment completed successfully.</span>
    </div>
@endif
@if(session('error'))
    <div class="alert-show alert-show-error" style="margin-bottom:14px;">
        <span class="alert-keyword">Notice.</span><span class="alert-body">{{ session('error') }}</span>
    </div>
@endif
@php
    $view = $view ?? 'all';
    $tabCounts = $tabCounts ?? ['all' => 0, 'live' => 0, 'bidding' => 0, 'watchlist' => 0, 'won' => 0];
    $qBase = array_filter([
        'sort' => $sort,
        'search' => $search,
        'per_page' => $perPage ?? '12',
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<style>
    .auction-page-shell { display:grid; gap:14px; }
    .auction-hero {
        background:linear-gradient(135deg,#fafbff 0%,#f5f7fc 100%);
        border:1px solid #e7ecf5;
        border-radius:14px;
        padding:18px 20px;
        box-shadow:0 2px 8px rgba(15,23,42,.05);
    }
    .auction-hero-top { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
    .auction-title { color:#111; margin:0; font-size:26px; font-weight:600; letter-spacing:-0.02em; }
    .auction-sub { color:#444; margin-top:4px; font-size:14px; }
    .count-pill { background:#f0f4f8; color:#111; border-radius:999px; padding:6px 12px; font-size:13px; font-weight:600; border:1px solid #dde4ee; }
    .auction-tabs { display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
    .tab-pill {
        display:inline-flex; align-items:center; gap:8px;
        padding:9px 14px; border-radius:999px; border:1px solid #e2e8f0;
        text-decoration:none; font-size:13px; color:#111;
        transition:transform .15s ease, box-shadow .15s ease;
    }
    .tab-pill i { color: inherit; opacity: 0.92; }
    .tab-pill:hover { transform:translateY(-1px); box-shadow:0 4px 10px rgba(15,23,42,.08); }
    .tab-pill .tab-n { font-size:11px; padding:2px 7px; border-radius:999px; border:1px solid #e8ecf2; background:#fafafa; color:#111; font-weight:600; }
    .tab-all { color:#111; background:#fff; }
    .tab-all.active { background:#eef6ff; border-color:#b8d4f5; color:#111; }
    .tab-live { color:#111; background:#fff; }
    .tab-live.active { background:#fff7ea; border-color:#f6ddbc; color:#111; }
    .tab-bidding { color:#111; background:#fff; }
    .tab-bidding.active { background:#fff4e6; border-color:#ffcc80; color:#111; }
    .tab-watch { color:#111; background:#fff; }
    .tab-watch.active { background:#f8f0ff; border-color:#e1d2fb; color:#111; }
    .tab-won { color:#111; background:#fff; }
    .tab-won.active { background:#eaf7ef; border-color:#c8e8d4; color:#111; }
    .filter-card {
        background:#fff;
        border:1px solid #e7ecf5;
        border-radius:14px;
        padding:16px 18px;
        box-shadow:0 2px 8px rgba(15,23,42,.05);
    }
    .filter-card h3 { margin:0 0 12px; font-size:15px; color:#111; display:flex; align-items:center; gap:8px; font-weight:600; }
    .filter-card h3 i { color:#7b1fa2; }
    .filter-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; align-items:end; }
    .filter-group label { display:block; font-size:12px; color:#333; font-weight:500; margin-bottom:6px; }
    .filter-row input, .filter-row select {
        width:100%; padding:11px 12px; border:1px solid #d8e3ef; border-radius:10px; background:#fff; font-size:14px; color:#111;
    }
    .filter-row input:focus, .filter-row select:focus { outline:none; border-color:#90b4d8; box-shadow:0 0 0 3px rgba(100,150,200,.1); }
    .btn-apply { background:linear-gradient(180deg,#4ecb89,#3bb377); color:#fff; border:none; border-radius:10px; padding:11px 18px; cursor:pointer; font-size:14px; }
    .btn-apply:hover { filter:brightness(1.03); }
    .btn-reset { background:#f0f4f8; color:#111; border:1px solid #d8e3ef; border-radius:10px; padding:11px 16px; text-decoration:none; font-size:14px; display:inline-flex; align-items:center; justify-content:center; gap:6px; }
    .btn-reset:hover { background:#e8eef5; }
    .auction-grid-modern {
        display:grid;
        grid-template-columns:repeat(3,1fr);
        gap:14px;
    }
    @media (max-width:1100px) { .auction-grid-modern { grid-template-columns:repeat(2,1fr); } }
    @media (max-width:640px) { .auction-grid-modern { grid-template-columns:1fr; } }
    .auction-tile {
        background:#fff; border:1px solid #e7ecf5; border-radius:14px; padding:16px; box-shadow:0 2px 8px rgba(15,23,42,.05);
        transition:transform .18s ease, box-shadow .18s ease;
    }
    .auction-tile:nth-child(3n+1) { background:linear-gradient(180deg,#ffffff 0%,#f9fbff 100%); }
    .auction-tile:nth-child(3n+2) { background:linear-gradient(180deg,#ffffff 0%,#fffaf5 100%); }
    .auction-tile:nth-child(3n+3) { background:linear-gradient(180deg,#ffffff 0%,#f5fcf8 100%); }
    .auction-tile:hover { transform:translateY(-2px); box-shadow:0 8px 18px rgba(15,23,42,.09); }
    .auction-top-row { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:8px; }
    .auction-card-title { color:#111; font-size:clamp(18px,2vw,24px); line-height:1.2; margin:0; font-weight:600; }
    .watch-btn { border:none; background:#f6f8fc; color:#9aa8bf; border-radius:50%; width:34px; height:34px; cursor:pointer; }
    .watch-btn.active { color:#ec5a9a; background:#fff1f7; }
    .auction-price { font-size:clamp(26px,3vw,36px); color:#111; font-weight:700; margin:8px 0 10px; }
    .auction-meta-row { display:flex; gap:16px; color:#333; font-size:13px; flex-wrap:wrap; margin-bottom:14px; }
    .auction-meta-row span { display:inline-flex; align-items:center; gap:6px; }
    .auction-meta-row i { color:#1565c0; }
    .auction-meta-row span:nth-child(2) i { color:#2e7d32; }
    .auction-meta-row span:nth-child(3) i { color:#e65100; }
    .btn-bid { background:linear-gradient(180deg,#4fa6ff,#3d8ae8); color:#fff; border:none; border-radius:10px; padding:10px 18px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:14px; }
    .btn-bid:hover { filter:brightness(1.05); }
    .btn-won { background:linear-gradient(180deg,#4ecb89,#35a86a); color:#fff; border-radius:10px; padding:10px 18px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:14px; }
    .empty-state { text-align:center; padding:32px; color:#333; }
</style>

<div class="auction-page-shell">
    <div class="auction-hero">
        <div class="auction-hero-top">
            <div>
                <h2 class="auction-title">Domain Auctions</h2>
                <div class="auction-sub">Browse, filter, and bid — tabs update the list below.</div>
            </div>
            <span class="count-pill">
                @if($view === 'all') All {{ $tabCounts['all'] ?? 0 }}
                @elseif($view === 'live') Live (7d) {{ $tabCounts['live'] ?? 0 }}
                @elseif($view === 'bidding') My active bids {{ $tabCounts['bidding'] ?? 0 }}
                @elseif($view === 'watchlist') Watchlist {{ $tabCounts['watchlist'] ?? 0 }}
                @else Won {{ $tabCounts['won'] ?? 0 }}
                @endif
            </span>
        </div>
        <div class="auction-tabs">
            <a class="tab-pill tab-all {{ $view === 'all' ? 'active' : '' }}" href="{{ route('user.auctions.index', array_merge($qBase, ['view' => 'all'])) }}">
                <i class="fas fa-gavel"></i> All Auctions <span class="tab-n">{{ $tabCounts['all'] ?? 0 }}</span>
            </a>
            <a class="tab-pill tab-live {{ $view === 'live' ? 'active' : '' }}" href="{{ route('user.auctions.index', array_merge($qBase, ['view' => 'live'])) }}">
                <i class="fas fa-bolt"></i> Live (7d) <span class="tab-n">{{ $tabCounts['live'] ?? 0 }}</span>
            </a>
            <a class="tab-pill tab-bidding {{ $view === 'bidding' ? 'active' : '' }}" href="{{ route('user.auctions.index', array_merge($qBase, ['view' => 'bidding'])) }}">
                <i class="fas fa-hand-holding-dollar"></i> My active bids <span class="tab-n">{{ $tabCounts['bidding'] ?? 0 }}</span>
            </a>
            <a class="tab-pill tab-watch {{ $view === 'watchlist' ? 'active' : '' }}" href="{{ route('user.auctions.index', array_merge($qBase, ['view' => 'watchlist'])) }}">
                <i class="fas fa-star"></i> Watchlist <span class="tab-n">{{ $tabCounts['watchlist'] ?? 0 }}</span>
            </a>
            <a class="tab-pill tab-won {{ $view === 'won' ? 'active' : '' }}" href="{{ route('user.auctions.index', array_merge($qBase, ['view' => 'won'])) }}">
                <i class="fas fa-trophy"></i> Won Auctions <span class="tab-n">{{ $tabCounts['won'] ?? 0 }}</span>
            </a>
        </div>
    </div>

    <div class="filter-card">
        <h3><i class="fas fa-sliders" style="color:#7b1fa2;"></i> Filters</h3>
        <form method="GET" class="filter-row">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by title">
            </div>
            <div class="filter-group">
                <label>Sort</label>
                <select name="sort">
                    <option value="ending_soon" {{ $sort==='ending_soon' ? 'selected' : '' }}>Ending Soon</option>
                    <option value="highest_bid" {{ $sort==='highest_bid' ? 'selected' : '' }}>Highest Bid</option>
                    <option value="newest" {{ $sort==='newest' ? 'selected' : '' }}>Newest</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Per page</label>
                <select name="per_page">
                    @foreach(['10','20','50','100','all'] as $size)
                        <option value="{{ $size }}" {{ ($perPage ?? '12') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group" style="display:flex; gap:8px; flex-wrap:wrap;">
                <button class="btn-apply" type="submit"><i class="fas fa-check"></i> Apply</button>
                <a class="btn-reset" href="{{ route('user.auctions.index', ['view' => $view]) }}"><i class="fas fa-rotate-right"></i> Reset</a>
            </div>
        </form>
    </div>

    @if ($auctions->isEmpty())
        <div class="card">
            <p class="empty-state">No auctions match this tab and filters. Try another tab or clear filters.</p>
        </div>
    @else
        <div class="auction-grid-modern">
            @foreach ($auctions as $auction)
                @php
                    $isWonView = ($view === 'won');
                    $displayAmount = $isWonView
                        ? (float) ($auction->final_price ?? $auction->current_bid ?? $auction->base_price ?? 0)
                        : (float) ($auction->current_bid ?? $auction->base_price);
                    $watchlisted = (int)($auction->watchlisted_count ?? 0) > 0;
                    $secondsLeft = $isWonView ? 0 : max(0, strtotime((string)$auction->end_datetime) - time());
                    $h = floor($secondsLeft / 3600);
                    $m = floor(($secondsLeft % 3600) / 60);
                @endphp
                <div class="auction-tile">
                    <div class="auction-top-row">
                        <h3 class="auction-card-title">{{ $auction->title }}</h3>
                        @if(!$isWonView)
                            <form method="POST" action="{{ route('user.auctions.watch', $auction->id) }}">
                                @csrf
                                <button class="watch-btn {{ $watchlisted ? 'active' : '' }}" type="submit" title="{{ $watchlisted ? 'Remove from watchlist' : 'Save to watchlist' }}">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </form>
                        @else
                            <span title="Won" style="color:#2e7d32;"><i class="fas fa-trophy"></i></span>
                        @endif
                    </div>
                    <div class="auction-price">₹{{ number_format($displayAmount, 2) }}</div>
                    <div class="auction-meta-row">
                        @if($isWonView)
                            <span><i class="fas fa-clock"></i> Ended {{ \Carbon\Carbon::parse($auction->end_datetime)->format('d M Y') }}</span>
                            <span><i class="fas fa-user"></i> {{ $auction->bid_count }} Bids</span>
                            <span><i class="fas fa-circle-check"></i> {{ strtoupper((string)($auction->payment_status ?? '-')) }}</span>
                        @else
                            <span><i class="fas fa-clock"></i> {{ $h }}h {{ $m }}m</span>
                            <span><i class="fas fa-user"></i> {{ $auction->bid_count }} Bids</span>
                            <span><i class="fas fa-arrow-up"></i> +₹{{ number_format((float)$auction->min_increment, 0) }}</span>
                        @endif
                    </div>
                    @if($isWonView)
                        <a class="btn-won" href="{{ route('user.auctions.index', ['view' => 'won']) }}"><i class="fas fa-trophy"></i> All won auctions</a>
                    @else
                        <a class="btn-bid" href="{{ route('user.auctions.show', $auction->id) }}"><i class="fas fa-hammer"></i> Place Bid</a>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="card">
            @include('partials.grid-pagination', ['rows' => $auctions])
        </div>
    @endif
</div>
@endsection
