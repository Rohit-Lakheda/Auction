<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin - Auction Portal')</title>
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family:'Varela Round',sans-serif; background:#f8f9fa; color:#2c3e50; }
        input, select, textarea, button { font-family:'Varela Round',sans-serif; font-size:15px; }
        input::placeholder, textarea::placeholder { font-family:'Varela Round',sans-serif; }
        .app-shell { display:flex; min-height:100vh; }
        .sidebar {
            width:280px;
            background:linear-gradient(180deg,#1a237e 0%,#283593 100%);
            color:#fff;
            padding:20px 14px;
            position:sticky;
            top:0;
            height:100vh;
            overflow-y:auto;
        }
        .sidebar-logo {
            display:flex;
            align-items:center;
            gap:10px;
            padding:8px 10px 18px;
            border-bottom:1px solid rgba(255,255,255,0.2);
            margin-bottom:14px;
        }
        .sidebar-logo img { height:44px; background:#fff; padding:5px; border-radius:10px; }
        .menu-link, .logout-btn, .menu-group-summary {
            width: calc(100% - 16px);
            margin:4px 8px;
            display:flex;
            align-items:center;
            gap:10px;
            color:#fff;
            text-decoration:none;
            padding:11px 12px;
            border-radius:10px;
            border:none;
            background:transparent;
            cursor:pointer;
            text-align:left;
            font-size:14px;
        }
        .menu-link:hover, .menu-link.active, .logout-btn:hover, .menu-group-summary:hover { background:rgba(255,255,255,.15); }
        .menu-group { margin-top:4px; }
        .menu-group summary { list-style:none; }
        .menu-group summary::-webkit-details-marker { display:none; }
        .menu-group[open] .menu-group-summary { background:rgba(255,255,255,.12); }
        .menu-sub-links { margin:4px 0 8px 0; }
        .menu-sub-links .menu-link { padding-left:34px; font-size:13px; opacity:.95; }
        .menu-group-caret { margin-left:auto; font-size:12px; opacity:.8; }
        .logout-btn { background:#c62828; margin-top:12px; }
        .logout-btn:hover { background:#b71c1c; }
        .content-area { flex:1; min-width:0; }
        .topbar-mobile {
            display:none;
            background:linear-gradient(135deg,#1a237e 0%,#283593 100%);
            color:#fff;
            padding:12px 14px;
            align-items:center;
            justify-content:space-between;
            position:sticky;
            top:0;
            z-index:1001;
        }
        .hamburger-btn { background:transparent; border:none; color:#fff; font-size:22px; cursor:pointer; }
        .sidebar-backdrop { display:none; }
        .container { max-width:1400px; margin:0 auto; padding:24px; }
        .card { background:#fff; padding:35px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.08); margin-bottom:25px; border:1px solid #e9ecef; }
        .btn { padding:10px 14px; border-radius:8px; color:#fff; text-decoration:none; display:inline-block; background:#1a237e; }
        .btn-danger { background:#c62828; }
        .btn-success { background:#2e7d32; }
        .btn-secondary { background:#546e7a; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; margin-bottom:6px; color:#1a237e; font-size:16px; }
        .form-group input, .form-group select, .form-group textarea {
            width:100%;
            padding:12px 16px;
            border:2px solid #d7dee8;
            border-radius:8px;
            background:#fff;
            color:#2c3e50;
            outline:none;
            transition:border-color .2s ease, box-shadow .2s ease;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color:#1a237e;
            box-shadow:0 0 0 3px rgba(26,35,126,0.12);
        }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th, td { padding:12px; border-bottom:1px solid #e9ecef; text-align:left; }
        th { background:#f8f9fa; color:#1a237e; }
        @media (max-width: 991px) {
            .topbar-mobile { display:flex; }
            .sidebar { position:fixed; left:-300px; top:0; z-index:1002; transition:left .25s ease; }
            .sidebar.open { left:0; }
            .sidebar-backdrop.open { display:block; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1001; }
            .container { padding:16px; }
        }
    </style>
</head>
<body>
@php
    $currentRoute = request()->route()?->getName();
@endphp
<div class="app-shell">
    <div class="topbar-mobile">
        <button id="hamburgerOpen" class="hamburger-btn" type="button"><i class="fas fa-bars"></i></button>
        <div>Admin Panel</div>
        <div style="width:22px;"></div>
    </div>
    <aside id="sidebar" class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/nixi_logo1.jpg') }}" alt="NIXI Logo">
            <strong>Admin Panel</strong>
        </div>
        <a class="menu-link {{ $currentRoute === 'admin.dashboard' ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="fas fa-gauge-high"></i> Dashboard</a>
        <a class="menu-link {{ $currentRoute === 'admin.operations' ? 'active' : '' }}" href="{{ route('admin.operations') }}"><i class="fas fa-screwdriver-wrench"></i> Operations Center</a>

        <details class="menu-group" {{ str_starts_with((string) $currentRoute, 'admin.auctions') || $currentRoute === 'admin.upload-excel' || $currentRoute === 'admin.completed' || $currentRoute === 'admin.bids.index' ? 'open' : '' }}>
            <summary class="menu-group-summary"><i class="fas fa-gavel"></i> Auctions <i class="fas fa-chevron-down menu-group-caret"></i></summary>
            <div class="menu-sub-links">
                <a class="menu-link {{ $currentRoute === 'admin.auctions.index' ? 'active' : '' }}" href="{{ route('admin.auctions.index') }}">All Auctions</a>
                <a class="menu-link {{ $currentRoute === 'admin.auctions.add' ? 'active' : '' }}" href="{{ route('admin.auctions.add') }}">Add Auction</a>
                <a class="menu-link {{ $currentRoute === 'admin.upload-excel' ? 'active' : '' }}" href="{{ route('admin.upload-excel') }}">Import Auctions</a>
                <a class="menu-link {{ $currentRoute === 'admin.completed' ? 'active' : '' }}" href="{{ route('admin.completed') }}">Completed Auctions</a>
                <a class="menu-link {{ $currentRoute === 'admin.bids.index' ? 'active' : '' }}" href="{{ route('admin.bids.index') }}">Bid Monitoring</a>
            </div>
        </details>

        <details class="menu-group" {{ $currentRoute === 'admin.manage-users' || $currentRoute === 'admin.blacklist' || $currentRoute === 'admin.support.tickets' || str_starts_with((string)$currentRoute, 'admin.notifications') ? 'open' : '' }}>
            <summary class="menu-group-summary"><i class="fas fa-users"></i> Users & Support <i class="fas fa-chevron-down menu-group-caret"></i></summary>
            <div class="menu-sub-links">
                <a class="menu-link {{ $currentRoute === 'admin.manage-users' ? 'active' : '' }}" href="{{ route('admin.manage-users') }}">Manage Users</a>
                <a class="menu-link {{ str_starts_with((string)$currentRoute, 'admin.notifications') ? 'active' : '' }}" href="{{ route('admin.notifications') }}">Notifications</a>
                <a class="menu-link {{ $currentRoute === 'admin.support.tickets' ? 'active' : '' }}" href="{{ route('admin.support.tickets') }}">Support Tickets</a>
                <a class="menu-link {{ $currentRoute === 'admin.blacklist' ? 'active' : '' }}" href="{{ route('admin.blacklist') }}">Blocked Identities</a>
            </div>
        </details>

        <details class="menu-group" {{ $currentRoute === 'admin.audit.logs' || $currentRoute === 'admin.settings' ? 'open' : '' }}>
            <summary class="menu-group-summary"><i class="fas fa-sliders"></i> Configuration <i class="fas fa-chevron-down menu-group-caret"></i></summary>
            <div class="menu-sub-links">
                <a class="menu-link {{ $currentRoute === 'admin.settings' ? 'active' : '' }}" href="{{ route('admin.settings') }}">Settings</a>
                <a class="menu-link {{ $currentRoute === 'admin.audit.logs' ? 'active' : '' }}" href="{{ route('admin.audit.logs') }}">Audit Logs</a>
            </div>
        </details>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="logout-btn" type="submit"><i class="fas fa-right-from-bracket"></i> Logout</button>
        </form>
    </aside>
    <div id="sidebarBackdrop" class="sidebar-backdrop"></div>
    <main class="content-area">
        <div class="container">@yield('content')</div>
    </main>
</div>
<script>
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const openBtn = document.getElementById('hamburgerOpen');
    if (openBtn) {
        openBtn.addEventListener('click', () => {
            sidebar.classList.add('open');
            backdrop.classList.add('open');
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('open');
            backdrop.classList.remove('open');
        });
    }
</script>
</body>
</html>
