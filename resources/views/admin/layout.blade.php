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
        .menu-link, .logout-btn {
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
        }
        .menu-link:hover, .menu-link.active, .logout-btn:hover { background:rgba(255,255,255,.15); }
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
        <div style="padding:8px 12px 4px;font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.75;">Overview</div>
        <a class="menu-link {{ $currentRoute === 'admin.dashboard' ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="fas fa-gauge-high"></i> Dashboard</a>
        <a class="menu-link {{ $currentRoute === 'admin.operations' ? 'active' : '' }}" href="{{ route('admin.operations') }}"><i class="fas fa-screwdriver-wrench"></i> Operations</a>
        <a class="menu-link {{ $currentRoute === 'admin.bids.index' ? 'active' : '' }}" href="{{ route('admin.bids.index') }}"><i class="fas fa-hand-holding-dollar"></i> All Bids</a>
        <div style="padding:12px 12px 4px;font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.75;">Auction Control</div>
        <a class="menu-link {{ $currentRoute === 'admin.auctions.index' ? 'active' : '' }}" href="{{ route('admin.auctions.index') }}"><i class="fas fa-table-list"></i> Auction Details</a>
        <a class="menu-link {{ $currentRoute === 'admin.auctions.add' ? 'active' : '' }}" href="{{ route('admin.auctions.add') }}"><i class="fas fa-plus-circle"></i> Add Auction</a>
        <a class="menu-link {{ $currentRoute === 'admin.upload-excel' ? 'active' : '' }}" href="{{ route('admin.upload-excel') }}"><i class="fas fa-file-arrow-up"></i> Upload Excel</a>
        <a class="menu-link {{ $currentRoute === 'admin.completed' ? 'active' : '' }}" href="{{ route('admin.completed') }}"><i class="fas fa-check-double"></i> Completed Auctions</a>
        <div style="padding:12px 12px 4px;font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.75;">Users & Configuration</div>
        <a class="menu-link {{ $currentRoute === 'admin.manage-users' ? 'active' : '' }}" href="{{ route('admin.manage-users') }}"><i class="fas fa-users"></i> Manage Users</a>
        <a class="menu-link {{ str_starts_with((string)$currentRoute, 'admin.notifications') ? 'active' : '' }}" href="{{ route('admin.notifications') }}"><i class="fas fa-bell"></i> Notifications</a>
        <a class="menu-link {{ $currentRoute === 'admin.support.tickets' ? 'active' : '' }}" href="{{ route('admin.support.tickets') }}"><i class="fas fa-headset"></i> Support Tickets</a>
        <a class="menu-link {{ $currentRoute === 'admin.blacklist' ? 'active' : '' }}" href="{{ route('admin.blacklist') }}"><i class="fas fa-user-slash"></i> Blacklist</a>
        <a class="menu-link {{ $currentRoute === 'admin.audit.logs' ? 'active' : '' }}" href="{{ route('admin.audit.logs') }}"><i class="fas fa-clipboard-list"></i> Audit Logs</a>
        <a class="menu-link {{ $currentRoute === 'admin.settings' ? 'active' : '' }}" href="{{ route('admin.settings') }}"><i class="fas fa-gear"></i> Settings</a>
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
