@extends('admin.layout')
@section('title','Notification Thread')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0;">Notification Thread</h2>
            <p style="color:#6c757d;margin-top:6px;">Subject: {{ $thread->subject ?: 'No Subject' }}</p>
            <p style="color:#6c757d;margin-top:4px;">Started by: {{ strtoupper((string)($creator->role ?? 'admin')) }}{{ !empty($creator->name) ? ' - '.$creator->name : '' }}</p>
        </div>
        <a href="{{ route('admin.notifications') }}" class="btn btn-secondary">Back to Notifications</a>
    </div>
    @if(session('success'))<div class="alert alert-success" style="margin-top:10px;">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error" style="margin-top:10px;">{{ $errors->first() }}</div>@endif
</div>

<div class="card">
    <h3 style="color:#1a237e;">Recipients</h3>
    <div style="overflow:auto;">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Read</th><th>Last Read</th><th>Profile</th></tr></thead>
            <tbody>
            @foreach($recipients as $r)
                <tr>
                    <td>{{ $r->name }}</td>
                    <td>{{ $r->email }}</td>
                    <td>{{ (int)$r->is_read === 1 ? 'Yes' : 'No' }}</td>
                    <td>{{ $r->last_read_at ? \Carbon\Carbon::parse($r->last_read_at)->format('d-M-Y H:i') : '-' }}</td>
                    <td><a class="btn btn-secondary" style="padding:5px 10px;font-size:12px;" href="{{ route('admin.users.show', $r->id) }}">Open User</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 style="color:#1a237e;">Conversation</h3>
    <div style="border:1px solid #e9ecef;border-radius:10px;padding:12px;margin-bottom:10px;background:#f8fbff;">
        <div style="font-size:13px;color:#1a237e;margin-bottom:6px;"><strong>{{ strtoupper((string)($creator->role ?? 'admin')) }} (Initial Message)</strong> • {{ \Carbon\Carbon::parse($thread->created_at)->format('d-M-Y H:i') }}</div>
        <div>{!! nl2br(e($thread->message)) !!}</div>
        @if($thread->attachment_path)
            <div style="margin-top:8px;"><a href="{{ asset('storage/'.$thread->attachment_path) }}" target="_blank">Attachment</a></div>
        @endif
    </div>

    @foreach($replies as $reply)
        <div style="border:1px solid #e9ecef;border-radius:10px;padding:12px;margin-bottom:10px;{{ $reply->sender_role === 'admin' ? 'background:#f8fbff;' : 'background:#fff9f2;' }}">
            <div style="font-size:13px;color:#1a237e;margin-bottom:6px;">
                <strong>{{ strtoupper($reply->sender_role) }}</strong> • {{ \Carbon\Carbon::parse($reply->created_at)->format('d-M-Y H:i') }}
            </div>
            <div>{!! nl2br(e($reply->message)) !!}</div>
            @if($reply->attachment_path)
                <div style="margin-top:8px;"><a href="{{ asset('storage/'.$reply->attachment_path) }}" target="_blank">Attachment</a></div>
            @endif
        </div>
    @endforeach
</div>

<div class="card">
    <h3 style="color:#1a237e;">Reply to Users</h3>
    <form method="POST" action="{{ route('admin.notifications.reply', $thread->id) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label>Message *</label>
            <textarea name="message" rows="4" required></textarea>
        </div>
        <div class="form-group">
            <label>Attachment (optional)</label>
            <input type="file" name="attachment">
        </div>
        <button class="btn" type="submit">Send Reply</button>
    </form>
</div>
@endsection
