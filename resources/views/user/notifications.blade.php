@extends('user.layout')

@section('title', 'Notifications - Auction Portal')

@section('content')
@php
    $filter = $filter ?? 'all';
    $searchQ = $searchQ ?? '';
    $notificationGroups = $notificationGroups ?? [];
    $unreadCount = (int) ($unreadCount ?? 0);
    $totalMessages = (int) ($totalMessages ?? 0);
    $tabQuery = fn (string $f) => array_filter(['filter' => $f, 'q' => $searchQ ?: null]);
@endphp
<style>
    .notif-page { max-width: 960px; margin: 0 auto; }
    .notif-hero {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 20px;
    }
    .notif-hero h1 {
        color: #111;
        font-size: 26px;
        font-weight: 700;
        margin: 0 0 6px;
        letter-spacing: -0.02em;
    }
    .notif-hero .sub {
        color: #64748b;
        font-size: 14px;
        margin: 0;
    }
    .notif-hero-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .notif-btn-ghost {
        padding: 10px 16px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #334155;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .notif-btn-ghost:hover { background: #f8fafc; border-color: #cbd5e1; color: #111; }
    .notif-btn-primary {
        padding: 10px 16px;
        border-radius: 10px;
        border: none;
        background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff !important;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    a.notif-btn-primary:hover { filter: brightness(1.05); color: #fff !important; }
    .notif-tabs-wrap {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 18px;
        padding-bottom: 4px;
        border-bottom: 1px solid #e8ecf2;
    }
    .notif-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 8px;
    }
    .notif-tab {
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
        text-decoration: none;
        border-radius: 8px 8px 0 0;
        border-bottom: 3px solid transparent;
        margin-bottom: -1px;
    }
    .notif-tab:hover { color: #111; background: #f8fafc; }
    .notif-tab.active {
        color: #1d4ed8;
        border-bottom-color: #2563eb;
        background: transparent;
    }
    .notif-tab .badge {
        display: inline-block;
        min-width: 20px;
        padding: 2px 7px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: #fee2e2;
        color: #b91c1c;
        margin-left: 4px;
    }
    .notif-search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    .notif-search-form select {
        padding: 9px 12px;
        border-radius: 10px;
        border: 1px solid #d8e3ef;
        font-size: 13px;
        color: #111;
        background: #fff;
    }
    .notif-search-form input[type="text"] {
        padding: 9px 12px;
        border-radius: 10px;
        border: 1px solid #d8e3ef;
        font-size: 13px;
        min-width: 160px;
        color: #111;
    }
    .notif-search-form button[type="submit"] {
        padding: 9px 16px;
        border-radius: 10px;
        border: none;
        background: #2563eb;
        color: #fff;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
    }
    .notif-sec { margin-bottom: 28px; }
    .notif-sec h2 {
        font-size: 13px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin: 0 0 12px;
    }
    .notif-card {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        align-items: flex-start;
        background: #fff;
        border: 1px solid #e8ecf2;
        border-radius: 12px;
        padding: 16px 18px;
        margin-bottom: 12px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        transition: box-shadow 0.15s ease;
    }
    .notif-card:hover { box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08); }
    .notif-card.unread { border-left: 4px solid #2563eb; padding-left: 14px; }
    .notif-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .notif-icon.is-outbid { background: #ffebee; color: #c62828; }
    .notif-icon.is-winning { background: #e8f5e9; color: #2e7d32; }
    .notif-icon.is-payment { background: #fff3e0; color: #ef6c00; }
    .notif-icon.is-auction { background: #e3f2fd; color: #1565c0; }
    .notif-icon.is-system { background: #e8eaf6; color: #3949ab; }
    .notif-body { flex: 1 1 200px; min-width: 0; }
    .notif-head {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 8px;
        margin-bottom: 6px;
    }
    .notif-ttl { font-size: 16px; font-weight: 700; color: #111; margin: 0; }
    .notif-time { font-size: 13px; color: #94a3b8; white-space: nowrap; }
    .notif-desc { font-size: 14px; color: #475569; line-height: 1.5; margin: 0 0 8px; }
    .notif-meta { font-size: 12px; color: #64748b; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
    .notif-meta i { margin-right: 4px; color: #94a3b8; }
    .notif-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        flex-shrink: 0;
    }
    .notif-actions .btn-solid {
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .notif-actions .btn-solid.blue { background: #2563eb; color: #fff !important; }
    .notif-actions .btn-solid.blue:hover { filter: brightness(1.05); }
    .notif-actions .btn-outline {
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155 !important;
    }
    .notif-actions .btn-outline:hover { background: #f8fafc; border-color: #94a3b8; color: #111 !important; }
    .notif-empty {
        text-align: center;
        padding: 48px 24px;
        color: #64748b;
        background: #fff;
        border: 1px dashed #d8e3ef;
        border-radius: 12px;
    }
    .notif-empty i { font-size: 40px; color: #cbd5e1; margin-bottom: 12px; }
</style>

<div class="notif-page">
    <div class="notif-hero">
        <div>
            <h1>Notifications</h1>
            <p class="sub">Stay updated with your auction activity and messages.</p>
        </div>
        <div class="notif-hero-actions">
            <a class="notif-btn-primary" href="{{ route('user.notifications.new') }}"><i class="fas fa-pen"></i> New message</a>
            @if($unreadCount > 0)
                <a class="notif-btn-ghost" href="{{ route('user.notifications', array_filter(['filter' => $filter, 'q' => $searchQ ?: null, 'mark_read' => 1])) }}">Mark all as read</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert-show alert-show-success" style="margin-bottom:16px;">
            <span class="alert-keyword">Success.</span><span class="alert-body">{{ session('success') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="alert-show alert-show-error" style="margin-bottom:16px;">
            <span class="alert-keyword">Error.</span><span class="alert-body">{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="notif-tabs-wrap">
        <div class="notif-tabs">
            <a class="notif-tab {{ $filter === 'all' ? 'active' : '' }}" href="{{ route('user.notifications', $tabQuery('all')) }}">All</a>
            <a class="notif-tab {{ $filter === 'unread' ? 'active' : '' }}" href="{{ route('user.notifications', $tabQuery('unread')) }}">
                Unread @if($unreadCount > 0)<span class="badge">{{ $unreadCount }}</span>@endif
            </a>
            <a class="notif-tab {{ $filter === 'auctions' ? 'active' : '' }}" href="{{ route('user.notifications', $tabQuery('auctions')) }}">Auctions</a>
            <a class="notif-tab {{ $filter === 'payments' ? 'active' : '' }}" href="{{ route('user.notifications', $tabQuery('payments')) }}">Payments</a>
            <a class="notif-tab {{ $filter === 'system' ? 'active' : '' }}" href="{{ route('user.notifications', $tabQuery('system')) }}">System</a>
        </div>
        <form method="GET" action="{{ route('user.notifications') }}" class="notif-search-form">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="text" name="q" value="{{ $searchQ }}" placeholder="Search" autocomplete="off">
            <button type="submit">Search</button>
        </form>
    </div>

    @if(empty($notificationGroups))
        <div class="notif-empty">
            <div><i class="fas fa-bell-slash"></i></div>
            @if($totalMessages === 0 && $filter === 'all' && $searchQ === '')
                <p style="margin:0;font-size:15px;color:#64748b;">No messages yet. Use <strong>New message</strong> to contact admin.</p>
            @else
                <p style="margin:0;font-size:15px;color:#64748b;">No notifications match your filters. Try another tab or clear search.</p>
            @endif
        </div>
    @else
        @foreach($notificationGroups as $group)
            <section class="notif-sec">
                <h2>{{ $group['label'] }}</h2>
                @foreach($group['items'] as $thread)
                    @php
                        $kind = $thread->notif_kind ?? 'system';
                        $iconClass = match ($kind) {
                            'outbid' => 'is-outbid',
                            'winning' => 'is-winning',
                            'payment' => 'is-payment',
                            'auction' => 'is-auction',
                            default => 'is-system',
                        };
                        $icon = match ($kind) {
                            'outbid' => 'fa-bullhorn',
                            'winning' => 'fa-trophy',
                            'payment' => 'fa-wallet',
                            'auction' => 'fa-gavel',
                            default => 'fa-gear',
                        };
                        $title = trim((string) ($thread->subject ?? ''));
                        if ($title === '') {
                            $title = match ($kind) {
                                'outbid' => 'Outbid alert',
                                'winning' => 'Auction result',
                                'payment' => 'Payment reminder',
                                'auction' => 'Auction update',
                                default => 'System message',
                            };
                        }
                        $preview = \Illuminate\Support\Str::limit(strip_tags((string) $thread->message), 140);
                        $when = \Carbon\Carbon::parse($thread->created_at);
                        $isUnread = (int) $thread->is_read === 0;
                    @endphp
                    <article class="notif-card {{ $isUnread ? 'unread' : '' }}">
                        <div class="notif-icon {{ $iconClass }}">
                            <i class="fas {{ $icon }}"></i>
                        </div>
                        <div class="notif-body">
                            <div class="notif-head">
                                <h3 class="notif-ttl">{{ $title }}</h3>
                                <span class="notif-time">{{ $when->diffForHumans() }}</span>
                            </div>
                            <p class="notif-desc">{{ $preview }}</p>
                            <div class="notif-meta">
                                <span><i class="fas fa-user"></i> {{ $thread->created_by_name ?? 'Admin' }}</span>
                                @if($kind !== 'system')
                                    <span><i class="fas fa-tag"></i> {{ ucfirst($kind) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="notif-actions">
                            @if(in_array($kind, ['auction', 'outbid', 'winning'], true))
                                <a class="btn-solid blue" href="{{ route('user.auctions.index') }}"><i class="fas fa-hammer"></i> Browse auctions</a>
                            @endif
                            @if($kind === 'payment')
                                <a class="btn-solid blue" href="{{ route('user.auctions.index', ['view' => 'won']) }}"><i class="fas fa-receipt"></i> Payments</a>
                            @endif
                            <a class="btn-outline" href="{{ route('user.notifications.show', $thread->id) }}">View</a>
                        </div>
                    </article>
                @endforeach
            </section>
        @endforeach
    @endif
</div>
@endsection
