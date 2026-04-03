@extends('user.layout')

@section('title', 'Notifications - Auction Portal')

@section('content')
<style>
    .compose-panel { display: none; }
    .compose-panel.open { display: block; }
    .compose-textarea {
        width: 100%;
        min-height: 110px;
        padding: 12px 14px;
        border: 2px solid #d8dfeb;
        border-radius: 10px;
        background: #fff;
        color: #2c3e50;
        resize: vertical;
    }
    .compose-textarea:focus {
        border-color: #1a237e;
        box-shadow: 0 0 0 3px rgba(26,35,126,.12);
        outline: none;
    }
</style>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div>
            <h2>Notifications</h2>
            <p style="color:#6c757d;">Unread: {{ (int) $unreadCount }}</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="btn" id="composeToggleBtn">Compose</button>
            @if($unreadCount > 0)
                <a class="btn btn-secondary" href="{{ route('user.notifications', ['mark_read' => 1]) }}">Mark All as Read</a>
            @endif
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success" style="margin-top:10px;">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error" style="margin-top:10px;">{{ $errors->first() }}</div>@endif
</div>

<div class="card compose-panel" id="composePanel">
    <h3 style="color:#1a237e;">Send Query to Admin</h3>
    <form method="POST" action="{{ route('user.notifications.compose') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label>Subject</label>
            <input type="text" name="subject" placeholder="Subject (optional)">
        </div>
        <div class="form-group">
            <label>Message *</label>
            <textarea class="compose-textarea" name="message" rows="3" required placeholder="Write your query for admin"></textarea>
        </div>
        <div class="form-group">
            <label>Attachment (optional)</label>
            <input type="file" name="attachment">
        </div>
        <button class="btn" type="submit">Send to Admin</button>
    </form>
</div>

<div class="card">
    <h3 style="color:#1a237e;">Message Summary</h3>
    @if(isset($threads) && $threads->isNotEmpty())
        <div style="overflow:auto;">
            <table>
                <thead><tr><th>Subject</th><th>From</th><th>Summary</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                @foreach($threads as $thread)
                    <tr style="{{ (int)$thread->is_read === 0 ? 'background:#eef6ff;' : '' }}">
                        <td>{{ $thread->subject ?: 'No Subject' }}</td>
                        <td>{{ strtoupper((string)($thread->created_by_role ?? 'admin')) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($thread->message, 80) }}</td>
                        <td>{{ \Carbon\Carbon::parse($thread->created_at)->format('d-M-Y H:i') }}</td>
                        <td>{{ (int)$thread->is_read === 0 ? 'Unread' : 'Read' }}</td>
                        <td><a class="btn btn-secondary" style="padding:5px 10px;font-size:12px;" href="{{ route('user.notifications', ['thread' => $thread->id]) }}">Open</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p>No message threads yet.</p>
    @endif
</div>

@if(isset($selectedThread) && $selectedThread)
    <div class="card">
        <h3 style="color:#1a237e;">Message Detail</h3>
        <div style="border:1px solid #e9ecef;border-radius:10px;padding:12px;margin-bottom:10px;background:#f8fbff;">
            <div style="font-size:13px;color:#1a237e;margin-bottom:6px;"><strong>{{ strtoupper((string)($selectedThread->created_by_role ?? 'admin')) }}</strong> • {{ \Carbon\Carbon::parse($selectedThread->created_at)->format('d-M-Y H:i') }}</div>
            <div>{!! nl2br(e($selectedThread->message)) !!}</div>
            @if($selectedThread->attachment_path)
                <div style="margin-top:8px;"><a href="{{ asset('storage/'.$selectedThread->attachment_path) }}" target="_blank">Attachment</a></div>
            @endif
        </div>

        @foreach(($selectedReplies ?? collect()) as $reply)
            <div style="border:1px solid #e9ecef;border-radius:10px;padding:12px;margin-bottom:10px;{{ $reply->sender_role === 'admin' ? 'background:#f8fbff;' : 'background:#fff9f2;' }}">
                <div style="font-size:13px;color:#1a237e;margin-bottom:6px;"><strong>{{ strtoupper($reply->sender_role) }}</strong> • {{ \Carbon\Carbon::parse($reply->created_at)->format('d-M-Y H:i') }}</div>
                <div>{!! nl2br(e($reply->message)) !!}</div>
                @if($reply->attachment_path)
                    <div style="margin-top:8px;"><a href="{{ asset('storage/'.$reply->attachment_path) }}" target="_blank">Attachment</a></div>
                @endif
            </div>
        @endforeach

        <form method="POST" action="{{ route('user.notifications.reply', $selectedThread->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>Reply to Admin</label>
                <textarea class="compose-textarea" name="message" rows="3" required placeholder="Write your reply"></textarea>
            </div>
            <div class="form-group">
                <label>Attachment (optional)</label>
                <input type="file" name="attachment">
            </div>
            <button class="btn" type="submit">Send Reply</button>
        </form>
    </div>
@endif
<script>
    const composeToggleBtn = document.getElementById('composeToggleBtn');
    const composePanel = document.getElementById('composePanel');
    if (composeToggleBtn && composePanel) {
        composeToggleBtn.addEventListener('click', () => {
            composePanel.classList.toggle('open');
            composeToggleBtn.textContent = composePanel.classList.contains('open') ? 'Close Compose' : 'Compose';
        });
    }
</script>
@endsection
