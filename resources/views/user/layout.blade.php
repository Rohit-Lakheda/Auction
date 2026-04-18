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
        body { font-family: 'Varela Round', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; color: #111; line-height: 1.6; }
        input, select, textarea, button { font-family: 'Varela Round', sans-serif; }
        input::placeholder, textarea::placeholder { font-family: 'Varela Round', sans-serif; }
        .app-shell {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-height: 100dvh;
        }
        @media (min-width: 992px) {
            .app-shell { flex-direction: row; }
        }
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 20px 14px;
            position: sticky;
            top: 0;
            height: 100vh;
            height: 100dvh;
            overflow-y: auto;
            flex-shrink: 0;
            -webkit-overflow-scrolling: touch;
        }
        .sidebar-top-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .sidebar-top-row .sidebar-logo { border: none; margin: 0; padding: 8px 0 0 10px; flex: 1; min-width: 0; }
        .sidebar-close-btn {
            display: none;
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 10px;
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-size: 20px;
            cursor: pointer;
        }
        .sidebar-close-btn:hover { background: rgba(255,255,255,0.2); }
        .sidebar-logo { display: flex; align-items: center; gap: 10px; }
        @media (min-width: 992px) {
            .sidebar-close-btn { display: none !important; }
            .sidebar-top-row { padding-bottom: 18px; margin-bottom: 14px; }
        }
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
        .menu-link i.fa-fw { width: 1.25em; text-align: center; }
        .menu-badge {
            margin-left: auto;
            background: #ef5350;
            color: #fff;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 600;
        }
        .logout-btn { background: #c62828; margin-top: 12px; }
        .logout-btn:hover { background: #b71c1c; }
        .content-area { flex: 1; min-width: 0; width: 100%; }
        .topbar-mobile {
            display: none;
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: #fff;
            padding: 12px 14px;
            padding-top: max(12px, env(safe-area-inset-top));
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1001;
            flex: 0 0 auto;
            width: 100%;
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
        }
        .topbar-mobile .topbar-title { font-weight: 600; font-size: 16px; letter-spacing: 0.02em; }
        .hamburger-btn {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 22px;
            cursor: pointer;
            padding: 8px;
            border-radius: 10px;
            line-height: 1;
        }
        .hamburger-btn:hover { background: rgba(255,255,255,0.12); }
        .hamburger-btn:focus-visible { outline: 2px solid rgba(255,255,255,0.6); outline-offset: 2px; }
        body.drawer-open { overflow: hidden; touch-action: none; }
        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
        .card { background: white; padding: 35px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 25px; border: 1px solid #e9ecef; }
        .card h2 { font-size: 28px; color: #111; margin-bottom: 20px; font-weight: 600; }
        .btn { padding: 12px 24px; background: #1a237e; color: white; text-decoration: none; border: none; border-radius: 8px; cursor: pointer; display: inline-block; }
        .btn:hover { background: #283593; }
        .btn-success { background: #2e7d32; }
        .btn-success:hover { background: #1b5e20; }
        .btn-secondary { background: #546e7a; }
        .btn-secondary:hover { background: #455a64; }
        /* Black body text; one colored keyword (use .alert-keyword + .alert-body) */
        .alert-show {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #e8ecf2;
            border-left: 4px solid;
            color: #111;
            font-size: 14px;
            line-height: 1.55;
        }
        .alert-show .alert-keyword { font-weight: 600; margin-right: 6px; }
        .alert-show .alert-body { color: #111; }
        .alert-show-success { background: #f4fbf6; border-left-color: #2e7d32; }
        .alert-show-success .alert-keyword { color: #2e7d32; }
        .alert-show-error { background: #fff8f8; border-left-color: #c62828; }
        .alert-show-error .alert-keyword { color: #c62828; }
        .alert-show-info { background: #f5f9fc; border-left-color: #1565c0; }
        .alert-show-info .alert-keyword { color: #1565c0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); gap: 25px; }
        @media (max-width: 575px) {
            .card { padding: 22px 18px; }
            .card h2 { font-size: 22px; }
        }
        .auction-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #e9ecef; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 15px; text-align: left; border-bottom: 1px solid #e9ecef; }
        table th { background: #f8f9fa; color: #111; font-weight: 600; }
        .form-group { margin-bottom: 25px; }
        .form-group input { width: 100%; padding: 12px 16px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; font-family: inherit; }
        .theme-label { display:block; margin-bottom:6px; color:#111; font-size:14px; font-weight:500; }
        .theme-control {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #d8dfeb;
            border-radius: 10px;
            background: #fff;
            color: #111;
            transition: border-color .2s, box-shadow .2s;
        }
        .theme-control:focus {
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26,35,126,.12);
            outline: none;
        }
        .sidebar-backdrop { display: none; }
        @media (max-width: 991px) {
            .topbar-mobile { display: flex; order: 1; }
            .content-area { order: 2; }
            .sidebar-close-btn { display: flex; }
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                z-index: 1003;
                width: min(300px, 88vw);
                max-width: 100%;
                transform: translateX(-105%);
                transition: transform 0.28s ease;
                box-shadow: none;
                padding-top: max(20px, env(safe-area-inset-top));
                padding-bottom: max(20px, env(safe-area-inset-bottom));
            }
            .sidebar.open {
                transform: translateX(0);
                box-shadow: 8px 0 32px rgba(0,0,0,0.25);
            }
            .sidebar-backdrop.open {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.45);
                z-index: 1002;
                -webkit-tap-highlight-color: transparent;
            }
            .container { padding: 16px; padding-left: max(16px, env(safe-area-inset-left)); padding-right: max(16px, env(safe-area-inset-right)); }
        }
    </style>
</head>
<body>
@php
    // [EMD/WALLET DISABLED] Wallet balance query removed
    // $walletBalance = \Illuminate\Support\Facades\DB::table('users')
    //     ->where('id', (int) session('user_id'))
    //     ->value('wallet_balance') ?? 0;
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
    $notificationsNavActive = in_array($currentRoute, ['user.notifications', 'user.notifications.show'], true);
@endphp
<div class="app-shell">
    <div class="topbar-mobile">
        <button id="hamburgerOpen" class="hamburger-btn" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Auction Portal</div>
        <div style="width:40px;flex-shrink:0;" aria-hidden="true"></div>
    </div>
    <aside id="sidebar" class="sidebar" aria-label="Main navigation">
        <div class="sidebar-top-row">
            <div class="sidebar-logo">
                <img src="{{ asset('images/nixi_logo1.jpg') }}" alt="NIXI Logo">
                <strong>Auction Portal</strong>
            </div>
            <button type="button" class="sidebar-close-btn" id="sidebarClose" aria-label="Close menu"><i class="fas fa-times"></i></button>
        </div>
        {{-- [EMD/WALLET DISABLED] Wallet balance card removed from sidebar --}}
        {{-- <div class="sidebar-wallet">
            <div class="sidebar-wallet-label">Wallet Balance</div>
            <div class="sidebar-wallet-amount">₹{{ number_format((float) $walletBalance, 2) }}</div>
        </div> --}}
        <a class="menu-link {{ $currentRoute === 'user.dashboard' ? 'active' : '' }}" href="{{ route('user.dashboard') }}"><i class="fas fa-fw fa-gauge-high"></i><span>Dashboard</span></a>
        <a class="menu-link {{ str_starts_with((string) $currentRoute, 'user.auctions') ? 'active' : '' }}" href="{{ route('user.auctions.index') }}"><i class="fas fa-fw fa-hammer"></i><span>Browse auctions</span></a>
        <a class="menu-link {{ $currentRoute === 'user.my-bids' ? 'active' : '' }}" href="{{ route('user.my-bids') }}"><i class="fas fa-fw fa-list-check"></i><span>My bids</span></a>
        <a class="menu-link {{ $notificationsNavActive ? 'active' : '' }}" href="{{ route('user.notifications') }}"><i class="fas fa-fw fa-bell"></i><span>Notifications</span>@if($unreadNotificationCount > 0)<span class="menu-badge">{{ $unreadNotificationCount }}</span>@endif</a>
        <a class="menu-link {{ $currentRoute === 'user.profile' ? 'active' : '' }}" href="{{ route('user.profile') }}"><i class="fas fa-fw fa-user"></i><span>Profile</span></a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="logout-btn" type="submit"><i class="fas fa-fw fa-right-from-bracket"></i><span>Logout</span></button>
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
(function () {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const openBtn = document.getElementById('hamburgerOpen');
    const closeBtn = document.getElementById('sidebarClose');
    if (!sidebar || !backdrop) return;

    function openDrawer() {
        sidebar.classList.add('open');
        backdrop.classList.add('open');
        document.body.classList.add('drawer-open');
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', 'true');
        }
    }
    function closeDrawer() {
        sidebar.classList.remove('open');
        backdrop.classList.remove('open');
        document.body.classList.remove('drawer-open');
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', 'false');
        }
    }
    if (openBtn) {
        openBtn.addEventListener('click', function () {
            if (sidebar.classList.contains('open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closeDrawer);
    }
    backdrop.addEventListener('click', closeDrawer);
    sidebar.querySelectorAll('a.menu-link').forEach(function (a) {
        a.addEventListener('click', closeDrawer);
    });
    const logoutForm = sidebar.querySelector('form[action*="logout"]');
    if (logoutForm) {
        logoutForm.addEventListener('submit', function () {
            closeDrawer();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeDrawer();
        }
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            closeDrawer();
        }
    });
})();
</script>
</body>
</html>
