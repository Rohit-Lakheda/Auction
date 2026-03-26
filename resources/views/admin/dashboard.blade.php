@extends('admin.layout')

@section('title', 'Admin Dashboard')

@section('content')
<style>
    .kpi-link { text-decoration:none; color:inherit; display:block; }
    .kpi-card { text-align:center; padding:22px 18px; transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease; border:1px solid #e9ecef; }
    .kpi-link:hover .kpi-card { transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,.08); }
    .kpi-card.active { border-color:#1a237e; box-shadow:0 0 0 3px rgba(26,35,126,.12); }
    .row-clickable { cursor:pointer; }
    .row-clickable:hover { background:#f6f8ff; }
</style>
<div class="card">
    <h2>Admin Dashboard</h2>
    <p style="color:#6c757d;">Welcome, {{ $adminName }}! Use this control center to manage auctions, users, payments, and operations.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:24px;">
    <a class="kpi-link" href="{{ route('admin.auctions.index', ['status' => 'all']) }}"><div class="card kpi-card"><h3 style="color:#3949ab;font-size:30px;">{{ $totalAuctions }}</h3><p style="margin-top:6px;">Total Auctions</p></div></a>
    <a class="kpi-link" href="{{ route('admin.auctions.index', ['status' => 'active']) }}"><div class="card kpi-card"><h3 style="color:#2e7d32;font-size:30px;">{{ $activeAuctions }}</h3><p style="margin-top:6px;">Live Auctions</p></div></a>
    <a class="kpi-link" href="{{ route('admin.auctions.index', ['status' => 'upcoming']) }}"><div class="card kpi-card"><h3 style="color:#1565c0;font-size:30px;">{{ $upcomingAuctions }}</h3><p style="margin-top:6px;">Upcoming</p></div></a>
    <a class="kpi-link" href="{{ route('admin.auctions.index', ['payment' => 'pending']) }}"><div class="card kpi-card"><h3 style="color:#ef6c00;font-size:30px;">{{ $pendingPayments }}</h3><p style="margin-top:6px;">Pending Winner Payments</p></div></a>
    <a class="kpi-link" href="{{ route('admin.manage-users') }}"><div class="card kpi-card"><h3 style="color:#8e24aa;font-size:30px;">{{ $totalUsers }}</h3><p style="margin-top:6px;">Users</p></div></a>
    <a class="kpi-link" href="{{ route('admin.bids.index') }}"><div class="card kpi-card"><h3 style="color:#546e7a;font-size:30px;">{{ $totalBids }}</h3><p style="margin-top:6px;">Total Bids</p></div></a>
    <a class="kpi-link" href="{{ route('admin.manage-users', ['user_filter' => 'blocked']) }}"><div class="card kpi-card"><h3 style="color:#c62828;font-size:30px;">{{ $blockedUsers }}</h3><p style="margin-top:6px;">Blocked Users</p></div></a>
    <a class="kpi-link" href="{{ route('admin.auctions.index', ['status' => 'failed']) }}"><div class="card kpi-card"><h3 style="color:#6d4c41;font-size:30px;">{{ $failedAuctions }}</h3><p style="margin-top:6px;">Failed Auctions</p></div></a>
</div>

<div class="card">
    <h2>Quick Actions</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
        <a href="{{ route('admin.auctions.add') }}" class="btn">Create Auction</a>
        <a href="{{ route('admin.upload-excel') }}" class="btn btn-success">Bulk Upload</a>
        <a href="{{ route('admin.manage-users') }}" class="btn" style="background:#1565c0;">User Management</a>
        <a href="{{ route('admin.completed') }}" class="btn" style="background:#ef6c00;">Payment Follow-up</a>
        <a href="{{ route('admin.operations') }}" class="btn" style="background:#6d4c41;">Operations Console</a>
        <a href="{{ route('admin.settings') }}" class="btn btn-secondary">System Settings</a>
    </div>
</div>

@endsection
