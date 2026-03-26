@extends('admin.layout')
@section('title','Add Auction')
@section('content')
<div class="card">
    <h2>Add New Auction</h2>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
    <form method="POST">@csrf
        <div class="form-group"><label>Auction Title *</label><input type="text" name="title" required value="{{ old('title') }}"></div>
        <div class="form-group"><label>Description</label><textarea name="description" style="width:100%;padding:12px 16px;border:2px solid #e9ecef;border-radius:8px;">{{ old('description') }}</textarea></div>
        <div class="form-group"><label>Base Price (₹) *</label><input type="number" step="0.01" min="0" name="base_price" required value="{{ old('base_price') }}"></div>
        <div class="form-group"><label>Minimum Bid Increment (₹) *</label><input type="number" step="0.01" min="0" name="min_increment" required value="{{ old('min_increment') }}"></div>
        <div class="form-group"><label>EMD Amount (₹) *</label><input type="number" step="0.01" min="0" name="emd_amount" required value="{{ old('emd_amount', app(\App\Services\AppSettingsService::class)->getFloat('emd_default_amount', (float) config('emd.default_emd_amount', 10000))) }}"></div>
        <div class="form-group"><label>Auction Start Date *</label><input type="date" name="start_datetime" required value="{{ old('start_datetime') }}"></div>
        <div class="form-group"><label>Auction End Date *</label><input type="date" name="end_datetime" required value="{{ old('end_datetime') }}"></div>
        <button class="btn" type="submit">Create Auction</button>
        <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">Cancel</a>
    </form>
</div>
@endsection
