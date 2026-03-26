@extends('portal.layout')

@section('title', 'Auction Portal - Home')

@section('content')
    <div class="logo-container">
        <img src="{{ asset('images/nixi_logo1.jpg') }}" alt="NIXI Logo">
        <h1>🎯 Auction Portal</h1>
    </div>
    <p>Welcome to the online auction platform. Place bids and win amazing items!</p>
    <div class="buttons">
        <a href="{{ route('login') }}" class="btn">Login</a>
        <a href="{{ route('register') }}" class="btn btn-secondary">Register</a>
    </div>
@endsection
