@extends('admin.layout')

@section('title', 'Audit Logs')

@section('content')
<div class="card">
    <h2>Audit Logs</h2>
    <p style="color:#6c757d;">Track critical admin/user system actions.</p>
</div>

@if($setupMissing)
    <div class="card"><p style="color:#c62828;">Audit logs table missing. Please run migrations.</p></div>
@else
<div class="card">
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr auto;gap:10px;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Search</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Action, entity, IP">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Action</label>
            <select name="action">
                <option value="all">ALL</option>
                @foreach($actions as $act)
                    <option value="{{ $act }}" {{ $action===$act?'selected':'' }}>{{ strtoupper($act) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn" type="submit">Apply</button>
    </form>
</div>

<div class="card">
    @if($logs->isEmpty())
        <p style="color:#6c757d;">No logs found.</p>
    @else
        <table>
            <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>IP</th><th>Meta</th></tr></thead>
            <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d-M-Y H:i:s') }}</td>
                    <td>{{ ($log->actor_role ?: '-') . ' #' . ($log->actor_user_id ?: '-') }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ ($log->entity_type ?: '-') . ' / ' . ($log->entity_id ?: '-') }}</td>
                    <td>{{ $log->ip_address ?: '-' }}</td>
                    <td style="max-width:280px;white-space:normal;word-break:break-word;">{{ $log->meta ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endif
@endsection

