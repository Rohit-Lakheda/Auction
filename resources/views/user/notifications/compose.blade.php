@extends('user.layout')

@section('title', 'New message - Notifications')

@section('content')
<style>
    .compose-page {
        max-width: 720px;
        margin: 0 auto;
        padding-bottom: 32px;
    }
    .compose-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 18px;
        padding: 8px 14px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b !important;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.15s, border-color 0.15s;
    }
    .compose-back:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #334155 !important;
    }
    .compose-hero {
        display: flex;
        align-items: center;
        gap: 14px;
        border-radius: 12px;
        padding: 18px 20px;
        margin-bottom: 20px;
        background: #f8fafc;
        border: 1px solid #e8ecf0;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .compose-hero-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: #eef2f6;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .compose-hero h1 {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: #1e293b;
    }
    .compose-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e8ecf2;
        padding: 22px 20px 24px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }
    .compose-card .compose-sub {
        margin: 0 0 20px;
        font-size: 14px;
        color: #64748b;
        line-height: 1.45;
    }
    .compose-textarea {
        width: 100%;
        min-height: 140px;
        padding: 12px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        color: #1e293b;
        resize: vertical;
        font-size: 15px;
        line-height: 1.5;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .compose-textarea:focus {
        border-color: #94a3b8;
        box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.25);
        outline: none;
    }
    .compose-input-wrap .theme-control {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .compose-input-wrap .theme-control:focus {
        border-color: #94a3b8;
        box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2);
    }
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
    .compose-actions {
        margin-top: 4px;
        padding-top: 18px;
        border-top: 1px solid #f1f5f9;
    }
    .compose-submit {
        padding: 10px 22px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background: #f1f5f9;
        color: #334155 !important;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.15s, border-color 0.15s;
    }
    .compose-submit:hover {
        background: #e2e8f0;
        border-color: #94a3b8;
        color: #0f172a !important;
    }
</style>

<div class="compose-page">
    <a class="compose-back" href="{{ route('user.notifications') }}"><i class="fas fa-arrow-left"></i> Back to notifications</a>

    <div class="compose-hero">
        <div class="compose-hero-icon" aria-hidden="true"><i class="fas fa-pen"></i></div>
        <h1>New message</h1>
    </div>

    <div class="compose-card">
        <p class="compose-sub">Send a message to the admin team. Subject and attachment are optional.</p>

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

        <form method="POST" action="{{ route('user.notifications.compose') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group compose-input-wrap">
                <label class="theme-label">Subject</label>
                <input class="theme-control" type="text" name="subject" value="{{ old('subject') }}" placeholder="Optional">
            </div>
            <div class="form-group">
                <label class="theme-label">Message *</label>
                <textarea class="compose-textarea" name="message" rows="6" required placeholder="Your message…">{{ old('message') }}</textarea>
            </div>
            <div class="form-group compose-file-field">
                <label class="theme-label" for="compose-attachment">Attachment (optional)</label>
                <input class="compose-file-native" type="file" name="attachment" id="compose-attachment" accept="*/*">
                <div class="compose-file-row">
                    <label class="compose-file-btn" for="compose-attachment"><i class="fas fa-paperclip"></i> Choose file</label>
                    <span class="compose-file-name is-empty" id="compose-file-name" data-empty="No file selected">No file selected</span>
                </div>
            </div>
            <div class="compose-actions">
                <button class="compose-submit" type="submit"><i class="fas fa-paper-plane"></i> Send</button>
            </div>
        </form>
    </div>
</div>
<script>
    (function () {
        var input = document.getElementById('compose-attachment');
        var label = document.getElementById('compose-file-name');
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
