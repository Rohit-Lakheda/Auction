@extends('admin.layout')
@section('title','Admin Notifications')
@section('content')
<div class="card">
    <h2>Admin Notifications</h2>
    <p style="color:#6c757d;">Send message + email to selected users with attachment, and manage reply threads.</p>
    @if(session('success'))<div class="alert alert-success" style="margin-top:10px;">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error" style="margin-top:10px;">{{ $errors->first() }}</div>@endif
    @if(!empty($setupMissing))
        <div class="alert alert-error" style="margin-top:10px;">Messaging tables are missing. Please run migrations.</div>
    @endif
</div>

@if(empty($setupMissing))
<div class="card">
    <h3 style="margin-bottom:10px;color:#1a237e;">Compose Message</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:12px;">
        <div class="card" style="padding:12px;margin:0;"><strong>Total Users:</strong> {{ $stats['total_users'] }}</div>
        <div class="card" style="padding:12px;margin:0;"><strong>Blocked:</strong> {{ $stats['blocked_users'] }}</div>
        <div class="card" style="padding:12px;margin:0;"><strong>Defaulted:</strong> {{ $stats['defaulted_users'] }}</div>
    </div>
    <form method="POST" action="{{ route('admin.notifications.send') }}" enctype="multipart/form-data" id="composeForm">
        @csrf
        <div class="form-group">
            <label>Subject (optional)</label>
            <input type="text" name="subject" maxlength="255" placeholder="Subject">
        </div>
        <div class="form-group">
            <label>Message *</label>
            <textarea name="message" rows="5" required placeholder="Write your message for selected users"></textarea>
        </div>
        <div class="form-group">
            <label>Attachment (optional)</label>
            <input type="file" name="attachment">
        </div>

        <div class="form-group">
            <label>Search Users (no page refresh)</label>
            <input type="text" id="userSearch" placeholder="Search by name/email">
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <button type="button" class="btn btn-secondary" id="selectAllFiltered">Select Filtered</button>
            <button type="button" class="btn btn-secondary" id="clearAllSelected">Clear Selected</button>
            <span style="color:#6c757d;align-self:center;">Selected: <strong id="selectedCount">0</strong></span>
        </div>

        <div style="max-height:360px;overflow:auto;border:1px solid #e3e8ef;border-radius:10px;">
            <table style="margin-top:0;">
                <thead><tr><th style="width:60px;">Select</th><th>Name</th><th>Email</th><th>Blocked</th><th>Default Count</th></tr></thead>
                <tbody id="userRows">
                @foreach($users as $u)
                    <tr data-name="{{ strtolower($u->name) }}" data-email="{{ strtolower($u->email) }}">
                        <td><input type="checkbox" name="selected_users[]" value="{{ $u->id }}" class="user-check"></td>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ (int)$u->is_blocked === 1 ? 'Yes' : 'No' }}</td>
                        <td>{{ (int)$u->default_count }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:14px;">
            <button type="submit" class="btn">Send to Selected Users</button>
        </div>
    </form>
</div>

<div class="card">
    <h3 style="color:#1a237e;">Message Threads</h3>
    @if($threads->isEmpty())
        <p>No messages sent yet.</p>
    @else
        <div style="overflow:auto;">
            <table>
                <thead><tr><th>Subject</th><th>Message Preview</th><th>Started By</th><th>Recipients</th><th>Replies</th><th>Sent At</th><th>Action</th></tr></thead>
                <tbody>
                @foreach($threads as $t)
                    <tr>
                        <td>{{ $t->subject ?: 'No Subject' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($t->message, 90) }}</td>
                        <td>{{ strtoupper((string)($t->created_by_role ?? 'admin')) }}{{ !empty($t->created_by_name) ? ' - '.$t->created_by_name : '' }}</td>
                        <td>{{ (int)$t->recipient_count }}</td>
                        <td>{{ (int)$t->reply_count }}</td>
                        <td>{{ \Carbon\Carbon::parse($t->created_at)->format('d-M-Y H:i') }}</td>
                        <td><a href="{{ route('admin.notifications.thread', $t->id) }}" class="btn btn-secondary" style="padding:5px 10px;font-size:12px;">Open Thread</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
    const searchInput = document.getElementById('userSearch');
    const rows = Array.from(document.querySelectorAll('#userRows tr'));
    const checks = Array.from(document.querySelectorAll('.user-check'));
    const selectedCount = document.getElementById('selectedCount');
    const selectFilteredBtn = document.getElementById('selectAllFiltered');
    const clearBtn = document.getElementById('clearAllSelected');

    function updateSelectedCount() {
        selectedCount.textContent = checks.filter(c => c.checked).length.toString();
    }

    function applyFilter() {
        const q = (searchInput.value || '').toLowerCase().trim();
        rows.forEach((row) => {
            const name = row.getAttribute('data-name') || '';
            const email = row.getAttribute('data-email') || '';
            const show = q === '' || name.includes(q) || email.includes(q);
            row.style.display = show ? '' : 'none';
        });
    }

    checks.forEach(c => c.addEventListener('change', updateSelectedCount));
    searchInput.addEventListener('input', applyFilter);
    selectFilteredBtn.addEventListener('click', () => {
        rows.forEach((row) => {
            if (row.style.display !== 'none') {
                const chk = row.querySelector('.user-check');
                if (chk) chk.checked = true;
            }
        });
        updateSelectedCount();
    });
    clearBtn.addEventListener('click', () => {
        checks.forEach(c => c.checked = false);
        updateSelectedCount();
    });

    updateSelectedCount();
    applyFilter();
</script>
@endif
@endsection
