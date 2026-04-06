@extends('admin.layout')
@section('title','Admin Settings')
@section('content')
<style>.tabs{display:flex;gap:10px;margin-bottom:30px;border-bottom:2px solid #e9ecef}.tab{padding:12px 24px;background:transparent;border:none;border-bottom:3px solid transparent;cursor:pointer;color:#6c757d}.tab.active{color:#1a237e;border-bottom-color:#1a237e;font-weight:500}.tab-content{display:none}.tab-content.active{display:block}</style>
<div class="card">
    <h2>Admin Settings</h2>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
    <div class="tabs">
        <button type="button" class="tab {{ $activeTab==='registration'?'active':'' }}" onclick="switchTab('registration',this)">Registration Settings</button>
        {{-- [EMD DISABLED] EMD Settings tab removed --}}
        <button type="button" class="tab {{ $activeTab==='email'?'active':'' }}" onclick="switchTab('email',this)">Email Settings</button>
    </div>
    <div id="tab-registration" class="tab-content {{ $activeTab==='registration'?'active':'' }}">
        <form method="POST">@csrf<input type="hidden" name="form_type" value="registration_amount">
            <div class="form-group"><label>Registration Amount (₹) *</label><input type="number" name="registration_amount" step="0.01" min="0" required value="{{ number_format($registrationAmount,2,'.','') }}"></div>
            <button class="btn" type="submit">Update Registration Amount</button>
        </form>
        <form method="POST" style="margin-top:16px;">@csrf<input type="hidden" name="form_type" value="participation_fee">
            <div class="form-group"><label>Default Bid Participation Fee (₹)</label><input type="number" name="bid_participation_fee" step="0.01" min="0" required value="{{ number_format($defaultParticipationFee,2,'.','') }}"></div>
            <small style="display:block;margin-bottom:10px;color:#6c757d;">Used when auction-specific participation fee is not set.</small>
            <button class="btn" type="submit">Update Default Participation Fee</button>
        </form>
    </div>
    {{-- [EMD DISABLED] EMD Settings tab content removed
    <div id="tab-emd" class="tab-content {{ $activeTab==='emd'?'active':'' }}">...(removed)...</div>
    --}}
    <div id="tab-email" class="tab-content {{ $activeTab==='email'?'active':'' }}">
        <form method="POST">@csrf<input type="hidden" name="form_type" value="email_settings">
            <div class="form-group"><label>SMTP Host *</label><input type="text" name="smtp_host" required value="{{ $emailSettings['smtp_host'] }}"></div>
            <div class="form-group"><label>SMTP Port *</label><input type="number" name="smtp_port" required value="{{ $emailSettings['smtp_port'] }}" min="1" max="65535"></div>
            <div class="form-group"><label>SMTP Username *</label><input type="text" name="smtp_username" required value="{{ $emailSettings['smtp_username'] }}"></div>
            <div class="form-group"><label>SMTP Password {{ empty($emailSettings['smtp_password']) ? '*' : '' }}</label><input type="password" name="smtp_password" {{ empty($emailSettings['smtp_password']) ? 'required' : '' }} placeholder="{{ empty($emailSettings['smtp_password']) ? 'Enter SMTP password' : 'Leave blank to keep current password' }}"></div>
            <div class="form-group"><label>From Email Address *</label><input type="email" name="from_email" required value="{{ $emailSettings['from_email'] }}"></div>
            <div class="form-group"><label>From Name</label><input type="text" name="from_name" value="{{ $emailSettings['from_name'] }}"></div>
            <div class="form-group"><label>Encryption Type *</label><select name="encryption"><option value="tls" {{ $emailSettings['encryption']==='tls'?'selected':'' }}>TLS</option><option value="ssl" {{ $emailSettings['encryption']==='ssl'?'selected':'' }}>SSL</option><option value="none" {{ $emailSettings['encryption']==='none'?'selected':'' }}>None</option></select></div>
            <div class="form-group"><label><input type="checkbox" name="is_active" value="1" {{ $emailSettings['is_active'] ? 'checked' : '' }}> Active</label></div>
            <button class="btn" type="submit">Update Email Settings</button>
        </form>
        <form method="POST" style="margin-top:16px;">@csrf<input type="hidden" name="form_type" value="test_email">
            <div class="form-group"><label>Send Test Email To</label><input type="email" name="test_email_to" placeholder="you@example.com" required></div>
            <button class="btn btn-secondary" type="submit">Test Email Configuration</button>
        </form>
    </div>
    <div style="margin-top:30px;"><a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Back to Dashboard</a></div>
</div>
<script>function switchTab(tab,el){document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));document.getElementById('tab-'+tab).classList.add('active');el.classList.add('active');const u=new URL(window.location);u.searchParams.set('tab',tab);window.history.pushState({},'',u);}</script>
@endsection
