@extends('admin.layout')
@section('title','Notification Thread')
@section('content')
@php $recipientCount = $recipients->count(); $firstRecipient = $recipients->first(); @endphp
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0;">Notification Thread</h2>
            <p style="color:#6c757d;margin-top:6px;">Subject: {{ $thread->subject ?: 'No Subject' }}</p>
            <p style="color:#6c757d;margin-top:4px;">Started by: {{ strtoupper((string)($creator->role ?? 'admin')) }}{{ !empty($creator->name) ? ' - '.$creator->name : '' }}</p>
            @if($recipientCount === 1 && $firstRecipient)
                <p style="color:#1a237e;margin-top:8px;font-weight:600;">Private conversation with {{ $firstRecipient->name }} <span style="font-weight:400;color:#6c757d;">({{ $firstRecipient->email }})</span></p>
            @elseif($recipientCount > 1)
                <p style="color:#856404;margin-top:8px;font-size:14px;">Legacy thread: {{ $recipientCount }} users on one message — replies are visible to all listed recipients. New bulk sends create one thread per user.</p>
            @endif
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

<style>
    .adm-file-field { margin-top: 4px; }
    .adm-file-native { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
    .adm-file-row { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
    .adm-file-btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px;
        border: 2px solid #d8dfeb; border-radius: 10px; background: #fff; color: #1a237e;
        font-size: 14px; font-weight: 600; cursor: pointer;
    }
    .adm-file-field:focus-within .adm-file-btn { border-color: #1a237e; box-shadow: 0 0 0 3px rgba(26,35,126,.12); }
    .adm-file-name { font-size: 14px; color: #64748b; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .adm-file-name.is-empty { font-style: italic; color: #94a3b8; }
</style>
<div class="card">
    <h3 style="color:#1a237e;">Reply @if($recipientCount === 1 && $firstRecipient) to {{ $firstRecipient->name }} @else to recipients @endif</h3>
    <form method="POST" action="{{ route('admin.notifications.reply', $thread->id) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label>Message *</label>
            <textarea name="message" rows="4" required></textarea>
        </div>
        <div class="form-group adm-file-field">
            <label for="admin-reply-attachment">Attachment (optional)</label>
            <input class="adm-file-native" type="file" name="attachment" id="admin-reply-attachment" accept="*/*">
            <div class="adm-file-row">
                <label class="adm-file-btn" for="admin-reply-attachment"><i class="fas fa-paperclip"></i> Choose file</label>
                <span class="adm-file-name is-empty" id="admin-reply-file-name" data-empty="No file selected">No file selected</span>
            </div>
        </div>
        <button class="btn" type="submit">Send Reply</button>
    </form>
</div>
<script>
(function () {
    var input = document.getElementById('admin-reply-attachment');
    var label = document.getElementById('admin-reply-file-name');
    if (!input || !label) return;
    var emptyText = label.getAttribute('data-empty') || 'No file selected';
    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        if (f) { label.textContent = f.name; label.classList.remove('is-empty'); }
        else { label.textContent = emptyText; label.classList.add('is-empty'); }
    });
})();
</script>
@endsection
