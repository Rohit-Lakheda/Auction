@extends('admin.layout')
@section('title','Upload Excel')
@section('content')
<div class="card">
    <h2>Upload Auctions (Excel/CSV)</h2>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
    <div class="alert alert-info">
        <strong>CSV Format Required:</strong><br>
        Columns: item_title, item_description, base_price, min_increment, start_date, end_date, participation_fee(optional)<br>
        <a href="{{ asset('sample_auction_template.csv') }}" download style="font-weight:bold;">Download Sample Template</a>
    </div>
    @if(empty($preview))
        <form method="POST" enctype="multipart/form-data">@csrf
            <div class="form-group"><label>Select Excel/CSV File *</label><input type="file" name="excel_file" accept=".csv,.xlsx" required></div>
            <button class="btn" type="submit">Upload & Preview</button>
            <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">Cancel</a>
        </form>
    @else
        <h3>Preview Data ({{ count($preview) }} rows)</h3>
        <div style="max-height:400px;overflow-y:auto;margin:20px 0;">
            <table>
                <thead><tr><th>Title</th><th>Description</th><th>Base Price</th><th>Min Increment</th><th>Participation Fee</th><th>Start Date</th><th>End Date</th></tr></thead>
                <tbody>
                @foreach($preview as $row)
                    <tr><td>{{ $row['title'] }}</td><td>{{ \Illuminate\Support\Str::limit($row['description'], 50) }}</td><td>₹{{ number_format((float)$row['base_price'],2) }}</td><td>₹{{ number_format((float)$row['min_increment'],2) }}</td><td>₹{{ number_format((float)($row['emd_amount'] ?? 0),2) }}</td><td>{{ \Carbon\Carbon::parse($row['start_datetime'])->format('d-M-Y') }}</td><td>{{ \Carbon\Carbon::parse($row['end_datetime'])->format('d-M-Y') }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST">@csrf<input type="hidden" name="confirm_import" value="1"><button type="submit" class="btn btn-success">✓ Confirm Import</button><a class="btn btn-secondary" href="{{ route('admin.upload-excel') }}">Cancel</a></form>
    @endif
</div>
@endsection
