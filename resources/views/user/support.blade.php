@extends('user.layout')

@section('title', 'Support - Auction Portal')

@section('content')
<div class="card">
    <h2>Support / Helpdesk</h2>
    <p style="color:#6c757d;">Raise a ticket and track admin responses.</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-error">{{ $errors->first() }}</div>
@endif

@if($setupMissing)
    <div class="card"><p style="color:#c62828;">Support module is not ready. Please run migrations.</p></div>
@else
<div class="grid" style="grid-template-columns:1fr 1.4fr;">
    <div class="card">
        <h3>Create Ticket</h3>
        <form method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="theme-label">Subject</label>
                <input class="theme-control" type="text" name="subject" required>
            </div>
            <div class="form-group">
                <label class="theme-label">Category</label>
                <select class="theme-control" name="category">
                    <option value="">General</option>
                    <option value="payment">Payment</option>
                    <option value="auction">Auction</option>
                    <option value="account">Account</option>
                </select>
            </div>
            <div class="form-group">
                <label class="theme-label">Priority</label>
                <select class="theme-control" name="priority">
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <div class="form-group">
                <label class="theme-label">Message</label>
                <textarea class="theme-control" name="message" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label class="theme-label">Attachment (optional)</label>
                <input class="theme-control" type="file" name="attachment">
            </div>
            <button type="submit" class="btn">Submit Ticket</button>
        </form>
    </div>

    <div class="card">
        <h3>My Tickets</h3>
        @if($tickets->isEmpty())
            <p style="color:#6c757d;">No tickets submitted yet.</p>
        @else
            <table>
                <thead><tr><th>ID</th><th>Subject</th><th>Status</th><th>Priority</th><th>Admin Reply</th></tr></thead>
                <tbody>
                @foreach($tickets as $ticket)
                    <tr>
                        <td>#{{ $ticket->id }}</td>
                        <td>{{ $ticket->subject }}</td>
                        <td>{{ strtoupper($ticket->status) }}</td>
                        <td>{{ strtoupper($ticket->priority) }}</td>
                        <td>{{ $ticket->admin_reply ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endif
@endsection

