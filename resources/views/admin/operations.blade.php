@extends('admin.layout')

@section('title', 'Admin Operations')

@section('content')
<div class="card">
    <h2>Operations Console</h2>
    <p style="color:#6c757d;">Track operational tasks across winner payments, wallet top-ups, and registrations from one place.</p>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <h3 style="margin:0;color:#1a237e;">Pending Winner Payments</h3>
        <a class="btn btn-secondary" href="{{ route('admin.completed') }}">Open Completed Auctions</a>
    </div>
    @if($pendingWinnerPayments->isEmpty())
        <p style="margin-top:14px;">No pending winner payments right now.</p>
    @else
        <div style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Auction</th>
                        <th>Winner</th>
                        <th>Final Price</th>
                        <th>Payment Window</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pendingWinnerPayments as $auction)
                    <tr>
                        <td>{{ $auction->title }}</td>
                        <td>
                            @if($auction->winner_user_id)
                                <a href="{{ route('admin.users.show', $auction->winner_user_id) }}" style="color:#1a237e;text-decoration:none;">{{ $auction->winner_name ?? 'Unknown User' }}</a>
                                @if(!empty($auction->winner_email))
                                    <br><small>{{ $auction->winner_email }}</small>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $auction->final_price ? '₹'.number_format((float)$auction->final_price,2) : '-' }}</td>
                        <td>{{ $auction->payment_window_expires_at ? \Carbon\Carbon::parse($auction->payment_window_expires_at)->format('d-M-Y h:i A') : '-' }}</td>
                        <td><a class="btn btn-secondary" style="padding:5px 10px;font-size:12px;" href="{{ route('admin.auctions.bids', $auction->id) }}">View Bids</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <h3 style="margin:0;color:#1a237e;">Recent Wallet Top-ups</h3>
    </div>
    @if($recentWalletTopups->isEmpty())
        <p style="margin-top:14px;">No wallet top-up activity found.</p>
    @else
        <div style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Txn ID</th>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recentWalletTopups as $topup)
                    <tr>
                        <td>{{ $topup->transaction_id }}</td>
                        <td><a href="{{ route('admin.users.show', $topup->user_id) }}" style="color:#1a237e;text-decoration:none;">{{ $topup->user_name ?? 'Unknown User' }}</a><br><small>{{ $topup->user_email ?? '-' }}</small></td>
                        <td>₹{{ number_format((float)$topup->amount,2) }}</td>
                        <td>{{ strtoupper((string)$topup->status) }}</td>
                        <td>{{ $topup->created_at ? \Carbon\Carbon::parse($topup->created_at)->format('d-M-Y h:i A') : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <h3 style="margin:0 0 14px;color:#1a237e;">Recent Registrations</h3>
    @if($recentRegistrations->isEmpty())
        <p>No registrations found.</p>
    @else
        <div style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Registration ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Payment</th>
                        <th>Created</th>
                        <th>User Profile</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recentRegistrations as $reg)
                    <tr>
                        <td>{{ $reg->registration_id }}</td>
                        <td>{{ $reg->full_name }}</td>
                        <td>{{ $reg->email }}</td>
                        <td>{{ $reg->mobile }}</td>
                        <td>{{ strtoupper((string)$reg->payment_status) }}</td>
                        <td>{{ \Carbon\Carbon::parse($reg->created_at)->format('d-M-Y') }}</td>
                        <td>
                            @if($reg->user_id)
                                <a class="btn" style="padding:5px 10px;font-size:12px;" href="{{ route('admin.users.show', $reg->user_id) }}">Open User</a>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
