@extends('user.layout')

@section('title', 'My Bids - Auction Portal')

@section('content')
<style>
    .visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    .page-my-bids { max-width: 1200px; margin: 0 auto; }
    .my-bids-hero {
        position: relative;
        background: linear-gradient(125deg, #eef2ff 0%, #fff8f0 38%, #e8f5f4 100%);
        border: 1px solid #d8dff5;
        border-radius: 14px;
        padding: 22px 24px 22px 28px;
        margin-bottom: 16px;
        box-shadow: 0 4px 18px rgba(63, 81, 181, 0.1);
        overflow: hidden;
    }
    .my-bids-hero::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 5px;
        background: linear-gradient(180deg, #5c6bc0 0%, #7e57c2 45%, #26a69a 100%);
        border-radius: 14px 0 0 14px;
    }
    .my-bids-hero h1 {
        color: #111;
        font-size: 26px;
        font-weight: 600;
        margin: 0 0 8px;
        letter-spacing: -0.02em;
    }
    .my-bids-hero p {
        color: #4a5568;
        font-size: 14px;
        margin: 0;
        line-height: 1.55;
    }
    .my-bids-filter {
        background: linear-gradient(180deg, #ffffff 0%, #faf8ff 100%);
        border: 1px solid #e1e6f4;
        border-radius: 12px;
        padding: 18px 20px;
        margin-bottom: 18px;
        box-shadow: 0 3px 12px rgba(94, 53, 177, 0.06);
    }
    .my-bids-filter-grid {
        display: grid;
        grid-template-columns: minmax(200px, 1.4fr) minmax(140px, 0.8fr) auto;
        gap: 14px;
        align-items: end;
    }
    @media (max-width: 768px) {
        .my-bids-filter-grid { grid-template-columns: 1fr; }
    }
    .my-bids-filter-adv {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid #eef1f6;
    }
    .my-bids-filter label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #333;
        margin-bottom: 6px;
    }
    .my-bids-search-wrap {
        position: relative;
    }
    .my-bids-search-wrap .search-ico {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #7e57c2;
        font-size: 14px;
        pointer-events: none;
    }
    .my-bids-search-wrap input {
        width: 100%;
        padding: 11px 12px 11px 36px;
        border: 1px solid #d0d9f0;
        border-radius: 10px;
        font-size: 14px;
        color: #111;
        background: linear-gradient(180deg, #fff 0%, #f8f9ff 100%);
    }
    .my-bids-search-wrap input:focus {
        outline: none;
        border-color: #90a4d4;
        box-shadow: 0 0 0 3px rgba(63, 81, 181, 0.12);
    }
    .my-bids-filter select {
        width: 100%;
        padding: 11px 32px 11px 12px;
        border: 1px solid #d8e3ef;
        border-radius: 10px;
        font-size: 14px;
        color: #111;
        background: #fcfdff;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
    }
    .my-bids-filter select:focus {
        outline: none;
        border-color: #90a4d4;
        box-shadow: 0 0 0 3px rgba(63, 81, 181, 0.12);
    }
    .my-bids-filter .adv-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d0d9f0;
        border-radius: 10px;
        font-size: 13px;
        color: #111;
        background: #fffefb;
    }
    .btn-filter-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 11px 22px;
        background: linear-gradient(135deg, #5c6bc0 0%, #3949ab 50%, #00897b 100%);
        color: #fff !important;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 3px 10px rgba(57, 73, 171, 0.35);
    }
    .btn-filter-primary:hover { filter: brightness(1.06); }
    .my-bids-table-card {
        background: #fff;
        border: 1px solid #dde3f0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(63, 81, 181, 0.08);
    }
    .my-bids-table-scroll { overflow-x: auto; }
    .my-bids-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .my-bids-table thead th {
        text-align: left;
        padding: 14px 16px;
        background: linear-gradient(90deg, #e8eaf6 0%, #f3e5f5 40%, #e0f2f1 100%);
        color: #37474f;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 2px solid #c5cae9;
    }
    .my-bids-table thead th:nth-child(2) { color: #3949ab; }
    .my-bids-table thead th:nth-child(4) { color: #00695c; }
    .my-bids-table thead th:nth-child(5) { color: #00838f; }
    .my-bids-table tbody td {
        padding: 16px;
        border-bottom: 1px solid #eceff4;
        color: #111;
        vertical-align: middle;
    }
    .my-bids-table tbody tr:nth-child(even) { background: #f8f9ff; }
    .my-bids-table tbody tr:last-child td { border-bottom: none; }
    .my-bids-table tbody tr:hover { background: #f0f4ff !important; }
    .col-domain { font-weight: 600; color: #1a237e; max-width: 280px; }
    .col-your-bid {
        font-weight: 700;
        color: #5c6bc0;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .col-highest {
        font-weight: 600;
        color: #00695c;
        font-variant-numeric: tabular-nums;
    }
    .col-time { color: #006978; font-size: 13px; white-space: nowrap; font-weight: 500; }
    .col-time.muted { color: #90a4ae; font-weight: 400; }
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
    }
    .status-pill.winning {
        background: linear-gradient(180deg, #e8f5e9 0%, #c8e6c9 100%);
        color: #1b5e20;
        border: 1px solid #81c784;
    }
    .status-pill.winning i { font-size: 14px; color: #2e7d32; }
    .status-pill.outbid {
        background: linear-gradient(180deg, #ffebee 0%, #ffcdd2 100%);
        color: #b71c1c;
        border: 1px solid #ef9a9a;
    }
    .status-pill.outbid i { font-size: 14px; color: #c62828; }
    .status-pill.status-closed {
        background: #e3f2fd;
        color: #0d47a1;
        border: 1px solid #90caf9;
    }
    .status-pill.status-completed {
        background: #e0f7fa;
        color: #006064;
        border: 1px solid #4dd0e1;
    }
    .status-pill.status-failed {
        background: #fff3e0;
        color: #e65100;
        border: 1px solid #ffcc80;
    }
    .status-pill.status-cancelled {
        background: #fce4ec;
        color: #ad1457;
        border: 1px solid #f48fb1;
    }
    .status-pill.status-neutral {
        background: #eceff1;
        color: #455a64;
        border: 1px solid #cfd8dc;
        font-weight: 500;
    }
    .btn-view-auction {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: linear-gradient(180deg, #e8eaf6 0%, #c5cae9 100%);
        color: #1a237e !important;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid #9fa8da;
    }
    .btn-view-auction:hover { background: linear-gradient(180deg, #c5cae9 0%, #9fa8da 100%); }
    .my-bids-empty {
        text-align: center;
        padding: 48px 24px;
        background: linear-gradient(145deg, #fff8f0 0%, #e8eaf6 50%, #e0f7fa 100%);
        border: 1px solid #d1c4e9;
        border-radius: 12px;
        color: #5c6370;
    }
    .my-bids-empty i {
        font-size: 42px;
        color: #5c6bc0;
        margin-bottom: 12px;
        filter: drop-shadow(2px 3px 0 rgba(38, 166, 154, 0.35));
    }
    .my-bids-empty h3 { color: #111; font-size: 18px; margin-bottom: 8px; font-weight: 600; }
    .my-bids-pagination-wrap {
        padding: 12px 16px;
        background: linear-gradient(180deg, #f5f7ff 0%, #fafbff 100%);
        border-top: 1px solid #d8dff5;
    }
    .my-bids-pagination-wrap > div { margin-top: 0 !important; align-items: center; }
    .my-bids-pagination-wrap .pagination {
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        justify-content: flex-end;
        list-style: none;
        padding: 0;
    }
    .my-bids-pagination-wrap .pagination li span,
    .my-bids-pagination-wrap .pagination li a {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid #e0e6f0;
        color: #3949ab;
        font-size: 13px;
        text-decoration: none;
    }
    .my-bids-pagination-wrap .pagination li.active span {
        background: linear-gradient(180deg, #c5cae9 0%, #9fa8da 100%);
        border-color: #7986cb;
        color: #1a237e;
        font-weight: 600;
    }
    .my-bids-pagination-wrap .pagination li.disabled span {
        color: #adb5bd;
        border-color: #e9ecef;
        background: #fff;
    }
</style>

<div class="page-my-bids">
    <div class="my-bids-hero">
        <h1><i class="fas fa-gavel" style="color:#5c6bc0;margin-right:10px;"></i>My Bids</h1>
        <p>Here are your placed bids in domain auctions. Your bid amounts are highlighted; status shows whether you are leading or have been outbid.</p>
    </div>

    <form method="GET" class="my-bids-filter">
        <div class="my-bids-filter-grid">
            <div>
                <label for="mb_search">Search</label>
                <div class="my-bids-search-wrap">
                    <span class="search-ico"><i class="fas fa-search"></i></span>
                    <input id="mb_search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Auction title…" autocomplete="off">
                </div>
            </div>
            <div>
                <label for="mb_status">Status</label>
                <select id="mb_status" name="status">
                    <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All statuses</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="closed" {{ ($filters['status'] ?? '') === 'closed' ? 'selected' : '' }}>Closed</option>
                    <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div>
                <label class="visually-hidden" for="mb_submit">Filter</label>
                <button id="mb_submit" class="btn-filter-primary" type="submit">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </div>
        <div class="my-bids-filter-adv">
            <div>
                <label for="mb_from">From date</label>
                <input id="mb_from" class="adv-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="mb_to">To date</label>
                <input id="mb_to" class="adv-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div>
                <label for="mb_pp">Per page</label>
                <select id="mb_pp" class="adv-input" name="per_page">
                    @foreach(['10','20','50','100','all'] as $size)
                        <option value="{{ $size }}" {{ ($filters['per_page'] ?? '20') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    @if ($myBids->isEmpty())
        <div class="my-bids-empty">
            <div><i class="fas fa-inbox"></i></div>
            <h3>No bids yet</h3>
            <p>You have not placed any bids matching these filters. Try browsing <a href="{{ route('user.auctions.index') }}" style="color:#3949ab;font-weight:600;">live auctions</a>.</p>
        </div>
    @else
        <div class="my-bids-table-card">
            <div class="my-bids-table-scroll">
                <table class="my-bids-table">
                    <thead>
                        <tr>
                            <th>Domain / auction</th>
                            <th>Your bid</th>
                            <th>Bid status</th>
                            <th>Highest bid</th>
                            <th>Time left</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($myBids as $bid)
                        @php
                            $winning = ((float) $bid->my_highest_bid === (float) $bid->highest_bid);
                            $isActive = $bid->status === 'active';
                            $end = \Carbon\Carbon::parse($bid->end_datetime);
                        @endphp
                        <tr>
                            <td class="col-domain">{{ $bid->title }}</td>
                            <td class="col-your-bid">₹{{ number_format((float) $bid->my_highest_bid, 2) }}</td>
                            <td>
                                @if ($isActive)
                                    @if ($winning)
                                        <span class="status-pill winning"><i class="fas fa-check-circle"></i> Highest bid</span>
                                    @else
                                        <span class="status-pill outbid"><i class="fas fa-exclamation-circle"></i> Outbid</span>
                                    @endif
                                @else
                                    @php
                                        $auctionSt = strtolower((string) $bid->status);
                                        $stMap = ['closed' => 'closed', 'completed' => 'completed', 'failed' => 'failed', 'cancelled' => 'cancelled'];
                                        $stClass = $stMap[$auctionSt] ?? 'neutral';
                                    @endphp
                                    <span class="status-pill status-{{ $stClass }}">{{ strtoupper($bid->status) }}</span>
                                @endif
                            </td>
                            <td class="col-highest">₹{{ number_format((float) $bid->highest_bid, 2) }}</td>
                            <td class="col-time {{ $isActive && $end->isFuture() ? '' : 'muted' }}">
                                @if ($isActive && $end->isFuture())
                                    <i class="far fa-clock" style="margin-right:4px;opacity:.75;"></i>{{ $end->diffForHumans() }}
                                @elseif ($isActive)
                                    <i class="fas fa-hourglass-end" style="margin-right:4px;opacity:.75;"></i>Ended
                                @else
                                    —
                                @endif
                            </td>
                            <td style="text-align:right;">
                                @if ($isActive)
                                    <a class="btn-view-auction" href="{{ route('user.auctions.show', $bid->id) }}">
                                        View <i class="fas fa-arrow-right" style="font-size:11px;"></i>
                                    </a>
                                @else
                                    <span style="color:#adb5bd;font-size:13px;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="my-bids-pagination-wrap">
                @include('partials.grid-pagination', ['rows' => $myBids])
            </div>
        </div>
    @endif

</div>
@endsection
