@extends('admin.layout')

@section('title', 'Blacklist Management')

@section('content')
<div class="card">
    <h2>Blacklist Management</h2>
    <p style="color:#6c757d;">Manage blocked identities (PAN, email, mobile, device, IP).</p>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

@if($setupMissing)
    <div class="card"><p style="color:#c62828;">Blacklist table missing. Please run migrations.</p></div>
@else
<div class="card">
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr auto;gap:10px;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Search</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Email, mobile, PAN, user">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Status</label>
            <select name="status">
                <option value="all" {{ $status==='all'?'selected':'' }}>ALL</option>
                <option value="active" {{ $status==='active'?'selected':'' }}>ACTIVE</option>
                <option value="inactive" {{ $status==='inactive'?'selected':'' }}>INACTIVE</option>
            </select>
        </div>
        <button class="btn" type="submit">Apply</button>
    </form>
</div>

<div class="card">
    @if($rows->isEmpty())
        <p style="color:#6c757d;">No blacklist records found.</p>
    @else
        <table>
            <thead><tr><th>ID</th><th>User</th><th>Email</th><th>Mobile</th><th>PAN</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->user_name ?: '-' }}</td>
                    <td>{{ $row->email ?: '-' }}</td>
                    <td>{{ $row->mobile ?: '-' }}</td>
                    <td>{{ $row->pan_card_number ?: '-' }}</td>
                    <td>{{ $row->reason ?: '-' }}</td>
                    <td>{{ (int)$row->is_active === 1 ? 'ACTIVE' : 'INACTIVE' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.blacklist.toggle', $row->id) }}">
                            @csrf
                            <button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" type="submit">
                                {{ (int)$row->is_active === 1 ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endif
@endsection

