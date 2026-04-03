@extends('admin.layout')

@section('title', 'Support Tickets')

@section('content')
<div class="card">
    <h2>Support Tickets</h2>
    <p style="color:#6c757d;">Review and resolve user support/helpdesk requests.</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-error">{{ $errors->first() }}</div>
@endif

@if($setupMissing)
    <div class="card"><p style="color:#c62828;">Support tickets table missing. Please run migrations.</p></div>
@else
<div class="card">
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr auto;gap:10px;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Search</label>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Subject, message, user">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Status</label>
            <select name="status">
                @foreach(['all','open','in_progress','resolved','closed'] as $st)
                    <option value="{{ $st }}" {{ ($status ?? 'all') === $st ? 'selected' : '' }}>{{ strtoupper($st) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn">Apply</button>
    </form>
</div>

<div class="card">
    @if($tickets->isEmpty())
        <p style="color:#6c757d;">No tickets found.</p>
    @else
        @foreach($tickets as $ticket)
            <div style="border:1px solid #e9ecef;border-radius:10px;padding:12px;margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <strong>#{{ $ticket->id }} - {{ $ticket->subject }}</strong>
                    <span>{{ strtoupper($ticket->status) }} | {{ strtoupper($ticket->priority) }}</span>
                </div>
                <div style="color:#6c757d;font-size:13px;">{{ $ticket->user_name }} ({{ $ticket->user_email }}) | {{ \Carbon\Carbon::parse($ticket->created_at)->format('d-M-Y H:i') }}</div>
                <p style="margin:10px 0;">{{ $ticket->message }}</p>
                <form method="POST" style="display:grid;grid-template-columns:180px 1fr auto;gap:8px;align-items:end;">
                    @csrf
                    <input type="hidden" name="ticket_id" value="{{ $ticket->id }}">
                    <div class="form-group" style="margin:0;">
                        <label>Status</label>
                        <select name="status">
                            @foreach(['open','in_progress','resolved','closed'] as $st)
                                <option value="{{ $st }}" {{ $ticket->status === $st ? 'selected' : '' }}>{{ strtoupper($st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Admin Reply</label>
                        <input type="text" name="admin_reply" value="{{ $ticket->admin_reply }}" placeholder="Reply to user">
                    </div>
                    <button class="btn" type="submit">Update</button>
                </form>
            </div>
        @endforeach
    @endif
</div>
@endif
@endsection

