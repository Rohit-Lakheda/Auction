<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Auction Portal')</title>
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Varela Round', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; color: #2c3e50; line-height: 1.6; }
        input, select, textarea, button { font-family: 'Varela Round', sans-serif; }
        input::placeholder, textarea::placeholder { font-family: 'Varela Round', sans-serif; }
        .app-shell { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #1a237e 0%, #283593 100%); color: white; padding: 20px 14px; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .sidebar-logo { display: flex; align-items: center; gap: 10px; padding: 8px 10px 18px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 14px; }
        .sidebar-logo img { height: 44px; background: #fff; padding: 5px; border-radius: 10px; }
        .sidebar-wallet { background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; padding: 12px; margin: 10px 8px 14px; }
        .sidebar-wallet-label { font-size: 12px; opacity: 0.9; }
        .sidebar-wallet-amount { font-size: 22px; font-weight: 700; margin-top: 4px; }
        .menu-link, .logout-btn {
            width: calc(100% - 16px);
            margin: 4px 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            text-decoration: none;
            padding: 11px 12px;
            border-radius: 10px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 15px;
            text-align: left;
        }
        .menu-link:hover, .logout-btn:hover, .menu-link.active { background: rgba(255,255,255,0.15); }
        .logout-btn { background: #c62828; margin-top: 12px; }
        .logout-btn:hover { background: #b71c1c; }
        .content-area { flex: 1; min-width: 0; }
        .topbar-mobile {
            display: none;
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: #fff;
            padding: 12px 14px;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1001;
        }
        .hamburger-btn { background: transparent; border: none; color: #fff; font-size: 22px; cursor: pointer; }
        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
        .card { background: white; padding: 35px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 25px; border: 1px solid #e9ecef; }
        .card h2 { font-size: 28px; color: #1a237e; margin-bottom: 20px; font-weight: 400; }
        .btn { padding: 12px 24px; background: #1a237e; color: white; text-decoration: none; border: none; border-radius: 8px; cursor: pointer; display: inline-block; }
        .btn:hover { background: #283593; }
        .btn-success { background: #2e7d32; }
        .btn-success:hover { background: #1b5e20; }
        .btn-secondary { background: #546e7a; }
        .btn-secondary:hover { background: #455a64; }
        .alert { padding: 16px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid; }
        .alert-error { background: #ffebee; color: #c62828; border-color: #c62828; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-color: #2e7d32; }
        .alert-info { background: #e3f2fd; color: #1565c0; border-color: #1565c0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
        .auction-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #e9ecef; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 15px; text-align: left; border-bottom: 1px solid #e9ecef; }
        table th { background: #f8f9fa; color: #1a237e; font-weight: 400; }
        .form-group { margin-bottom: 25px; }
        .form-group input { width: 100%; padding: 12px 16px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; font-family: inherit; }
        .theme-label { display:block; margin-bottom:6px; color:#1a237e; font-size:14px; }
        .theme-control {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #d8dfeb;
            border-radius: 10px;
            background: #fff;
            color: #2c3e50;
            transition: border-color .2s, box-shadow .2s;
        }
        .theme-control:focus {
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26,35,126,.12);
            outline: none;
        }
        .sidebar-backdrop { display: none; }
        @media (max-width: 991px) {
            .topbar-mobile { display: flex; }
            .sidebar { position: fixed; left: -300px; top: 0; z-index: 1002; transition: left .25s ease; }
            .sidebar.open { left: 0; }
            .sidebar-backdrop.open { display: block; position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 1001; }
            .container { padding: 16px; }
        }
    </style>
</head>
<body>
@php
    $walletBalance = \Illuminate\Support\Facades\DB::table('users')
        ->where('id', (int) session('user_id'))
        ->value('wallet_balance') ?? 0;
    $userId = (int) session('user_id');
    $unreadNotificationCount = 0;
    if ($userId > 0 && \Illuminate\Support\Facades\Schema::hasTable('admin_message_recipients')) {
        $unreadNotificationCount = (int) \Illuminate\Support\Facades\DB::table('admin_message_recipients')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->count();
    } elseif ($userId > 0 && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
        $unreadNotificationCount = (int) \Illuminate\Support\Facades\DB::table('notifications')
            ->where('user_id', $userId)
            ->where('read_status', 0)
            ->count();
    }
    $currentRoute = request()->route()?->getName();
@endphp
<div class="app-shell">
    <div class="topbar-mobile">
        <button id="hamburgerOpen" class="hamburger-btn" type="button"><i class="fas fa-bars"></i></button>
        <div>Auction Portal</div>
        <a href="{{ route('wallet.index') }}" style="color:#fff;text-decoration:none;"><i class="fas fa-wallet"></i></a>
    </div>
    <aside id="sidebar" class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/nixi_logo1.jpg') }}" alt="NIXI Logo">
            <strong>Auction Portal</strong>
        </div>
        <div class="sidebar-wallet">
            <div class="sidebar-wallet-label">Wallet Balance</div>
            <div class="sidebar-wallet-amount">₹{{ number_format((float) $walletBalance, 2) }}</div>
        </div>
        <a class="menu-link {{ $currentRoute === 'user.dashboard' ? 'active' : '' }}" href="{{ route('user.dashboard') }}"><i class="fas fa-gauge-high"></i> Dashboard</a>
        <a class="menu-link {{ str_starts_with((string)$currentRoute, 'user.auctions') ? 'active' : '' }}" href="{{ route('user.auctions.index') }}"><i class="fas fa-gavel"></i> Auctions</a>
        <a class="menu-link {{ $currentRoute === 'user.my-bids' ? 'active' : '' }}" href="{{ route('user.my-bids') }}"><i class="fas fa-list-check"></i> My Bids</a>
        <a class="menu-link {{ $currentRoute === 'user.won-auctions' ? 'active' : '' }}" href="{{ route('user.won-auctions') }}"><i class="fas fa-trophy"></i> Won Auctions</a>
        <a class="menu-link {{ $currentRoute === 'user.notifications' ? 'active' : '' }}" href="{{ route('user.notifications') }}"><i class="fas fa-bell"></i> Notifications @if($unreadNotificationCount > 0)<span style="margin-left:auto;background:#ef5350;color:#fff;border-radius:20px;padding:2px 8px;font-size:12px;">{{ $unreadNotificationCount }}</span>@endif</a>
        <a class="menu-link {{ str_starts_with((string)$currentRoute, 'wallet.') ? 'active' : '' }}" href="{{ route('wallet.index') }}"><i class="fas fa-wallet"></i> Wallet</a>
        <a class="menu-link {{ $currentRoute === 'user.profile' ? 'active' : '' }}" href="{{ route('user.profile') }}"><i class="fas fa-user"></i> Profile</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="logout-btn" type="submit"><i class="fas fa-right-from-bracket"></i> Logout</button>
        </form>
    </aside>
    <div id="sidebarBackdrop" class="sidebar-backdrop"></div>
    <main class="content-area">
        <div class="container">
            @yield('content')
        </div>
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
