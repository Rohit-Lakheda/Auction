@extends('user.layout')

@section('title', ($selectedThread->subject ?: 'Message') . ' - Notifications')

@section('content')
<style>
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
    .reply-bubble { border:1px solid #e9ecef; border-radius:10px; padding:14px; margin-bottom:14px; }
    .reply-bubble.admin { background:#f8fbff; }
    .reply-bubble.user { background:#fff9f2; }
    .compose-file-field { margin-top: 4px; }
    .compose-file-native {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    .compose-file-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
    }
    .compose-file-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border: 2px solid #d8dfeb;
        border-radius: 10px;
        background: #fff;
        color: #1a237e;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: border-color 0.2s, box-shadow 0.2s, background 0.15s;
    }
    .compose-file-btn i { opacity: 0.85; font-size: 13px; }
    .compose-file-field:focus-within .compose-file-btn {
        border-color: #1a237e;
        box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.12);
        outline: none;
    }
    .compose-file-btn:hover {
        background: #f8f9fc;
        border-color: #c5cce8;
    }
    .compose-file-name {
        font-size: 14px;
        color: #64748b;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .compose-file-name.is-empty { font-style: italic; color: #94a3b8; }
</style>

<div class="card">
    <div style="margin-bottom:16px;">
        <a href="{{ route('user.notifications') }}" class="btn btn-secondary">← Back to notifications</a>
    </div>
    <h2 style="color:#111;margin-bottom:8px;">{{ $selectedThread->subject ?: 'No subject' }}</h2>
    <p style="color:#6c757d;font-size:14px;margin-bottom:20px;">
        {{ \Carbon\Carbon::parse($selectedThread->created_at)->format('d-M-Y H:i') }}
        @if(!empty($selectedThread->created_by_name))
            · {{ $selectedThread->created_by_name }}
        @endif
    </p>

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

    <div style="border:1px solid #e9ecef;border-radius:10px;padding:14px;margin-bottom:14px;background:#f8fbff;">
        <div style="font-size:13px;color:#1a237e;margin-bottom:8px;">
            <strong>{{ strtoupper((string)($selectedThread->created_by_role ?? 'user')) }}</strong>
        </div>
        <div style="color:#111;line-height:1.6;">{!! nl2br(e($selectedThread->message)) !!}</div>
        @if($selectedThread->attachment_path)
            <div style="margin-top:10px;"><a href="{{ asset('storage/'.$selectedThread->attachment_path) }}" target="_blank" rel="noopener">Download attachment</a></div>
        @endif
    </div>

    @foreach(($selectedReplies ?? collect()) as $reply)
        <div class="reply-bubble {{ ($reply->sender_role ?? '') === 'admin' ? 'admin' : 'user' }}">
            <div style="font-size:13px;color:#1a237e;margin-bottom:8px;">
                <strong>{{ strtoupper((string)($reply->sender_role ?? '')) }}</strong>
                · {{ \Carbon\Carbon::parse($reply->created_at)->format('d-M-Y H:i') }}
            </div>
            <div style="color:#111;line-height:1.6;">{!! nl2br(e($reply->message)) !!}</div>
            @if(!empty($reply->attachment_path))
                <div style="margin-top:10px;"><a href="{{ asset('storage/'.$reply->attachment_path) }}" target="_blank" rel="noopener">Download attachment</a></div>
            @endif
        </div>
    @endforeach

    <h3 style="color:#1a237e;margin:24px 0 12px;font-size:17px;">Reply</h3>
    <form method="POST" action="{{ route('user.notifications.reply', $selectedThread->id) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label class="theme-label">Message</label>
            <textarea class="compose-textarea" name="message" rows="4" required placeholder="Write your reply to admin"></textarea>
        </div>
        <div class="form-group compose-file-field">
            <label class="theme-label" for="thread-reply-attachment">Attachment (optional)</label>
            <input class="compose-file-native" type="file" name="attachment" id="thread-reply-attachment" accept="*/*">
            <div class="compose-file-row">
                <label class="compose-file-btn" for="thread-reply-attachment"><i class="fas fa-paperclip"></i> Choose file</label>
                <span class="compose-file-name is-empty" id="thread-reply-file-name" data-empty="No file selected">No file selected</span>
            </div>
        </div>
        <button class="btn" type="submit"><i class="fas fa-paper-plane"></i> Send reply</button>
    </form>
</div>
<script>
    (function () {
        var input = document.getElementById('thread-reply-attachment');
        var label = document.getElementById('thread-reply-file-name');
        if (!input || !label) return;
        var emptyText = label.getAttribute('data-empty') || 'No file selected';
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (f) {
                label.textContent = f.name;
                label.classList.remove('is-empty');
            } else {
                label.textContent = emptyText;
                label.classList.add('is-empty');
            }
        });
    })();
</script>
@endsection
