@extends('user.layout')

@section('title', 'Wallet - Auction Portal')

@section('content')
    @if (session('success'))
        <div class="alert-show alert-show-success">
            <span class="alert-keyword">Success.</span><span class="alert-body">{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="alert-show alert-show-error">
            <span class="alert-keyword">Error.</span><span class="alert-body">{{ session('error') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert-show alert-show-error">
            <span class="alert-keyword">Error.</span><span class="alert-body">{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="card">
        <h2>My Wallet</h2>
        <p style="color:#6c757d;">Use wallet to add money and view credit/debit history for all auction operations.</p>
        <div style="margin-top:16px; display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
            <div style="background:#e8f5e9; border:1px solid #c8e6c9; border-radius:10px; padding:16px;">
                <div style="font-size:14px; color:#2e7d32;">Available Balance</div>
                <div style="font-size:30px; font-weight:700; color:#1b5e20;">₹{{ number_format((float) $walletBalance, 2) }}</div>
            </div>
            <div style="background:#fff3e0; border:1px solid #ffcc80; border-radius:10px; padding:16px;">
                <div style="font-size:14px; color:#ef6c00;">Locked in EMD</div>
                <div style="font-size:30px; font-weight:700; color:#e65100;">₹{{ number_format((float) $lockedBalance, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>Add Money to Wallet</h3>
        <form method="POST" action="{{ route('wallet.add-money') }}" style="max-width:420px;">
            @csrf
            <div class="form-group">
                <label>Amount (₹) *</label>
                <input type="number" name="amount" min="1" step="0.01" required placeholder="Enter amount">
            </div>
            <button class="btn btn-success" type="submit">Proceed to Payment Gateway</button>
        </form>
    </div>

    <div class="card">
        <h3>Transaction History</h3>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                @php
                    $filters = [
                        'all' => 'All',
                        'credit' => 'Credit',
                        'debit' => 'Debit',
                    ];
                @endphp
                @foreach($filters as $filterKey => $filterLabel)
                    <a href="{{ route('wallet.index', ['filter' => $filterKey]) }}"
                       class="btn {{ $activeFilter === $filterKey ? 'btn-success' : 'btn-secondary' }}"
                       style="padding:8px 12px;font-size:13px;">
                        {{ $filterLabel }}
                    </a>
                @endforeach
            </div>
            <a href="{{ route('wallet.export', ['filter' => $activeFilter]) }}" class="btn">Export CSV</a>
        </div>
        @if($transactions->isEmpty())
            <p style="color:#6c757d;">No wallet transactions yet.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Direction</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th>Remarks</th>
                </tr>
                </thead>
                <tbody>
                @foreach($transactions as $tx)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($tx->created_at)->format('d-M-Y H:i') }}</td>
                        <td>{{ $tx->type }}</td>
                        <td>{{ $tx->direction }}</td>
                        <td style="color:{{ $tx->amount_color }};font-weight:700;">{{ $tx->amount_sign }}₹{{ number_format((float) $tx->amount, 2) }}</td>
                        <td>{{ strtoupper($tx->status) }}</td>
                        <td>{{ $tx->reference_display }}</td>
                        <td>{{ $tx->remarks ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection

