@extends('admin.layout')
@section('title','Manage Users')
@section('content')
<div class="card"><h2>Manage Users</h2><p style="color:#6c757d;">View user activity, registration details, and resend credentials when required.</p></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card">
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <a class="btn {{ ($userFilter ?? 'all') === 'all' ? '' : 'btn-secondary' }}" href="{{ route('admin.manage-users', array_merge(request()->query(), ['user_filter' => 'all', 'page' => 1])) }}">All Users ({{ $allUsersCount ?? 0 }})</a>
        <a class="btn {{ ($userFilter ?? '') === 'blocked' ? 'btn-danger' : 'btn-secondary' }}" href="{{ route('admin.manage-users', array_merge(request()->query(), ['user_filter' => 'blocked', 'page' => 1])) }}">Blocked Users ({{ $blockedUsersCount ?? 0 }})</a>
        <a class="btn {{ ($userFilter ?? '') === 'defaulted' ? 'btn-danger' : 'btn-secondary' }}" href="{{ route('admin.manage-users', array_merge(request()->query(), ['user_filter' => 'defaulted', 'page' => 1])) }}">Defaulted Users ({{ $defaultedUsersCount ?? 0 }})</a>
    </div>
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end;margin-bottom:14px;">
        <div class="form-group" style="margin:0;">
            <label>Search User</label>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Name, email, registration ID">
        </div>
        <div class="form-group" style="margin:0;">
            <label>User Filter</label>
            <select name="user_filter">
                <option value="all" {{ ($userFilter ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                <option value="blocked" {{ ($userFilter ?? '') === 'blocked' ? 'selected' : '' }}>Blocked</option>
                <option value="defaulted" {{ ($userFilter ?? '') === 'defaulted' ? 'selected' : '' }}>Defaulted</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Per Page</label>
            <select name="per_page">
                @foreach(['10','20','50','100','all'] as $size)
                    <option value="{{ $size }}" {{ ($perPage ?? '20') === $size ? 'selected' : '' }}>{{ strtoupper($size) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn" type="submit">Apply</button>
    </form>
    <h3 style="color:#1a237e;">
        {{ ($userFilter ?? 'all') === 'blocked' ? 'Blocked Users' : (($userFilter ?? '') === 'defaulted' ? 'Defaulted Users' : 'All Users') }}
        ({{ method_exists($users, 'total') ? $users->total() : $users->count() }})
    </h3>
    <div style="overflow:auto;">
    <table id="usersTable">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Registration ID</th><th>Payment Status</th><th>Total Bids</th><th>Won Auctions</th><th>Registered</th><th>Actions</th></tr></thead>
        <tbody>
        @foreach($users as $user)
            <tr>
                <td>{{ $user->id }}</td><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->registration_id ?? '-' }}</td>
                <td>{{ $user->payment_status ? ($user->payment_status==='success'?'Paid':ucfirst($user->payment_status)) : '-' }}</td>
                <td>{{ $user->total_bids }}</td><td>{{ $user->won_auctions }}</td><td>{{ \Carbon\Carbon::parse($user->created_at)->format('d-M-Y') }}</td>
                        <td><a class="btn" style="padding:6px 12px;font-size:13px;" href="{{ route('admin.users.show', $user->id) }}">View Details</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @include('partials.grid-pagination', ['rows' => $users])
</div>
@endsection
