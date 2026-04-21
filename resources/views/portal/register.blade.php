<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box} body{font-family:'Varela Round',sans-serif;background-color:#f8f9fa;color:#2c3e50}
        input,select,textarea,button{font-family:'Varela Round',sans-serif !important}
        input::placeholder,textarea::placeholder{font-family:'Varela Round',sans-serif}
        .top-header{background:linear-gradient(135deg,#1a237e 0%,#283593 100%);color:#fff;padding:18px 0;box-shadow:0 4px 6px rgba(0,0,0,.1);margin-bottom:40px}
        .top-header-content{max-width:1400px;margin:0 auto;display:flex;align-items:center;justify-content:center;gap:18px;padding:0 30px}
        .top-header-logo{height:50px;background:#fff;padding:5px;border-radius:12px}.top-header-title{font-size:24px;font-weight:400}
        .card{border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);border:1px solid #e9ecef}.card-header{background:#1a237e;color:#fff}
        .btn-primary{background:#1a237e;border-color:#1a237e}.btn-outline-primary{color:#1a237e;border-color:#1a237e}.form-control:focus{border-color:#1a237e;box-shadow:0 0 0 .2rem rgba(26,35,126,.15)}
    </style>
</head>
<body>
<div class="top-header"><div class="top-header-content"><img src="{{ asset('images/nixi_logo1.jpg') }}" alt="NIXI Logo" class="top-header-logo"><span class="top-header-title">Auction Portal</span></div></div>

<!-- Age Restriction Modal -->
<div class="modal fade" id="ageRestrictionModal" tabindex="-1" aria-labelledby="ageRestrictionModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title" id="ageRestrictionModalLabel">
                    <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>Age Restriction
                </h5>
            </div>
            <div class="modal-body text-center py-5">
                <div style="font-size: 48px; margin-bottom: 20px; color: #dc3545;">
                    <i class="fas fa-ban"></i>
                </div>
                <h4 style="margin-bottom: 15px; color: #2c3e50;">Registration Not Allowed</h4>
                <p style="font-size: 16px; color: #555; margin-bottom: 25px;">
                    You must be at least <strong>18 years old</strong> to register on this platform. Your current age is <strong><span id="ageDisplayValue">--</span> years</strong>.
                </p>
                <p style="font-size: 14px; color: #888; margin-bottom: 0;">
                    For more information, please contact our support team.
                </p>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-center pb-4">
                <button type="button" class="btn btn-danger px-5" onclick="redirectToHome()" style="font-weight: 500;">
                    Return to Home
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container"><div class="row justify-content-center"><div class="col-lg-10 col-xl-9"><div class="card shadow">
<div class="card-header"><h4 class="mb-0">Registration</h4></div>
<div class="card-body">
    @if(session('payment_retry_available'))
        <div class="alert alert-warning">
            Registration payment is pending/failed.
            <a href="{{ route('payments.registration.retry') }}" class="btn btn-sm btn-primary" style="margin-left:10px;">Retry Payment</a>
        </div>
    @endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0"><li>{{ $errors->first() }}</li></ul></div>@endif
    <p>Please fill out the form below to register for an account.</p>
    <form method="POST" id="registrationForm" action="{{ route('register.submit') }}">@csrf
        <input type="hidden" name="device_fingerprint" id="device_fingerprint">
        <div class="mb-4">
            <label class="form-label fw-bold">Registration Type <span style="color:#4169E1">*</span></label>
            <div class="d-flex gap-4">
                <div class="form-check" hidden><input class="form-check-input" type="radio" name="registration_type" id="registration_type_entity" value="entity" {{ (old('registration_type','individual')==='entity')?'checked':'' }} required><label class="form-check-label" for="registration_type_entity">Entity</label></div>
                <div class="form-check"><input class="form-check-input" type="radio" name="registration_type" id="registration_type_individual" value="individual" {{ (old('registration_type','individual')==='individual')?'checked':'' }} required><label class="form-check-label" for="registration_type_individual">Individual</label></div>
            </div>
            <small class="form-text text-muted" hidden>Select whether you are registering as an individual or an entity</small>
        </div>
        <div class="mb-4" style="border-top:2px solid #e9ecef;padding-top:25px;">
            <div class="row mb-3">
                <div class="col-md-6 mb-3 mb-md-0"><label for="fullname" class="form-label" id="fullnameLabel">Full Name <span style="color:#4169E1">*</span></label><input type="text" class="form-control" id="fullname" name="fullname" value="{{ old('fullname') }}" placeholder="Enter full name or entity name" required><small class="form-text text-muted" id="fullnameHelp">Only letters, spaces, apostrophes, and hyphens are allowed</small></div>
                <div class="col-md-6"><label for="dateofbirth" class="form-label" id="dateofbirthLabel">Date of Birth <span style="color:#4169E1">*</span></label><input type="date" class="form-control" id="dateofbirth" name="dateofbirth" value="{{ old('dateofbirth') }}" required><small class="form-text text-muted" id="dateofbirthHelp"></small></div>
            </div>
            <div class="mb-3"><label for="pancardno" class="form-label">PAN Number <span style="color:#4169E1">*</span></label><div class="input-group"><input type="text" class="form-control" id="pancardno" name="pancardno" value="{{ old('pancardno') }}" placeholder="ABCDE1234F" maxlength="10" required><button type="button" class="btn btn-outline-primary" id="verifyPanBtn" onclick="verifyPan()">Verify PAN</button></div><small class="form-text text-muted">Format: ABCDE1234F (5 letters, 4 digits, 1 letter)</small><div id="panVerificationStatus" class="mt-2" style="display:none;"></div></div>
        </div>
        <div class="mb-4" style="border-top:2px solid #e9ecef;padding-top:25px;">
            <div class="row mb-3">
                <div class="col-md-6 mb-3 mb-md-0"><label for="email" class="form-label">Email Address <span style="color:#4169E1">*</span></label><div class="input-group"><input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required><button type="button" class="btn btn-outline-primary" id="getEmailOtpBtn" onclick="getEmailOtp()">Get OTP</button></div><div id="emailOtpSection" style="display:none;" class="mt-2"><div class="input-group"><input type="text" class="form-control" id="email_otp" placeholder="Enter 6-digit OTP" maxlength="6"><button type="button" class="btn btn-success" onclick="verifyEmailOtp()">Verify</button></div><small class="form-text text-muted" id="emailOtpStatus"></small></div><div id="emailVerificationStatus" class="mt-2" style="display:none;"></div></div>
                <div class="col-md-6"><label for="mobile" class="form-label">Mobile Number <span style="color:#4169E1">*</span></label><div class="input-group"><input type="tel" class="form-control" id="mobile" name="mobile" value="{{ old('mobile') }}" placeholder="10-digit mobile number" maxlength="10" required><button type="button" class="btn btn-outline-primary" id="getMobileOtpBtn" onclick="getMobileOtp()">Get OTP</button></div><div id="mobileOtpSection" style="display:none;" class="mt-2"><div class="input-group"><input type="text" class="form-control" id="mobile_otp" placeholder="Enter 6-digit OTP" maxlength="6"><button type="button" class="btn btn-success" onclick="verifyMobileOtp()">Verify</button></div><small class="form-text text-muted" id="mobileOtpStatus"></small></div><div id="mobileVerificationStatus" class="mt-2" style="display:none;"></div></div>
            </div>
        </div>
        <div class="mb-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="declaration" name="declaration" value="1" required><label class="form-check-label" for="declaration"><strong>I hereby declare and authorize</strong> to collect, process, store, and use the information provided in this registration form for verification and service delivery. <span style="color:#4169E1">*</span></label></div></div>
        <div class="mb-3"><button type="submit" class="btn btn-primary" id="submitBtn" disabled>Register</button></div>
        <div id="verificationWarning" class="alert alert-warning" style="display:none;">Please complete all required fields and verifications before submitting the form.</div>
    </form>
    <br><div class="links">Already have an account? <a href="{{ route('login') }}">Login here</a><br></div>
</div></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const csrf='{{ csrf_token() }}'; let emailVerified=false,mobileVerified=false,panVerified=false,registrationType='entity';
document.getElementById('device_fingerprint').value = btoa([
    navigator.userAgent || '',
    navigator.language || '',
    Intl.DateTimeFormat().resolvedOptions().timeZone || '',
    navigator.platform || '',
].join('|')).slice(0, 120);
const postJson=async(url,payload)=>{const fd=new FormData();Object.entries(payload).forEach(([k,v])=>fd.append(k,v));const r=await fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf},body:fd});return r.json();};
document.querySelectorAll('input[name="registration_type"]').forEach(radio=>{radio.addEventListener('change',function(){registrationType=this.value;updateLabelsBasedOnRegistrationType();});});
function updateLabelsBasedOnRegistrationType(){const fl=document.getElementById('fullnameLabel');const fi=document.getElementById('fullname');const fh=document.getElementById('fullnameHelp');const dl=document.getElementById('dateofbirthLabel');const dh=document.getElementById('dateofbirthHelp');if(registrationType==='individual'){fl.innerHTML='Full Name <span style="color: #4169E1;">*</span>';fi.placeholder='Enter your full name';fh.textContent='Only letters, spaces, apostrophes (\'), and hyphens (-) are allowed';dl.innerHTML='Date of Birth <span style="color: #4169E1;">*</span>';dh.textContent='Enter your date of birth';}else{fl.innerHTML='Entity Name <span style="color: #4169E1;">*</span>';fi.placeholder='Enter entity/company name';fh.textContent='Only letters, spaces, apostrophes (\'), and hyphens (-) are allowed';dl.innerHTML='Date of Incorporation <span style="color: #4169E1;">*</span>';dh.textContent='Enter the date of incorporation';}}
updateLabelsBasedOnRegistrationType();
document.getElementById('pancardno').addEventListener('input',e=>e.target.value=e.target.value.toUpperCase().replace(/[^A-Z0-9]/g,''));
document.getElementById('fullname').addEventListener('input',e=>{e.target.value=e.target.value.replace(/[^a-zA-Z\s'-]/g,'');checkAllValidations();});
document.getElementById('mobile').addEventListener('input',e=>e.target.value=e.target.value.replace(/\D/g,''));
document.getElementById('email_otp').addEventListener('input',e=>e.target.value=e.target.value.replace(/\D/g,''));
document.getElementById('mobile_otp').addEventListener('input',e=>e.target.value=e.target.value.replace(/\D/g,''));
function verifyPan(){const panNo=document.getElementById('pancardno').value;const fullName=document.getElementById('fullname').value;const dob=document.getElementById('dateofbirth').value;if(!fullName||!dob||!panNo){alert('Please fill name, date and PAN first.');return;}const btn=document.getElementById('verifyPanBtn');const statusDiv=document.getElementById('panVerificationStatus');btn.disabled=true;btn.textContent='Verifying...';statusDiv.style.display='block';statusDiv.innerHTML='<small class="text-info">Verifying PAN... Please wait...</small>';postJson('{{ route('register.pan.verify') }}',{pancardno:panNo,fullname:fullName,dateofbirth:dob}).then(data=>{if(data.success&&data.request_id){statusDiv.innerHTML='<small class="text-info">Verification initiated. Checking status...</small>';checkPanStatus(data.request_id,panNo,btn,statusDiv);}else{statusDiv.innerHTML='<small style="color:#dc3545;"><strong>✗ '+(data.message||'PAN verification failed')+'</strong></small>';btn.disabled=false;btn.textContent='Verify PAN';}});}
function checkPanStatus(requestId,panNo,btn,statusDiv){let pollCount=0;const poll=()=>{pollCount++;if(pollCount>30){statusDiv.innerHTML='<small style="color:#dc3545;"><strong>✗ Verification timeout. Please try again.</strong></small>';btn.disabled=false;btn.textContent='Verify PAN';return;}postJson('{{ route('register.pan.status') }}',{request_id:requestId,pancardno:panNo}).then(data=>{if(data.status==='completed'){if(data.verified===true){if(data.age_restricted===true){document.getElementById('ageDisplayValue').textContent=data.age;const ageModal=new bootstrap.Modal(document.getElementById('ageRestrictionModal'));ageModal.show();statusDiv.innerHTML='<small style="color:#dc3545;"><strong>✗ Age restriction: Must be 18 years or older</strong></small>';btn.disabled=false;btn.textContent='Verify PAN';return;}panVerified=true;['pancardno','fullname','dateofbirth'].forEach(id=>{const el=document.getElementById(id);el.readOnly=true;el.style.backgroundColor='#d4edda';el.style.borderColor='#28a745';});btn.disabled=true;btn.textContent='Verified';btn.classList.remove('btn-outline-primary');btn.classList.add('btn-success');statusDiv.innerHTML='<small class="text-success"><strong>✓ PAN verified successfully!</strong></small>';checkAllValidations();}else{statusDiv.innerHTML='<small style="color:#dc3545;"><strong>✗ '+(data.message||'PAN verification failed')+'</strong></small>';btn.disabled=false;btn.textContent='Verify PAN';}}else if(data.status==='failed'){statusDiv.innerHTML='<small style="color:#dc3545;"><strong>✗ '+(data.message||'PAN verification failed')+'</strong></small>';btn.disabled=false;btn.textContent='Verify PAN';}else{statusDiv.innerHTML='<small class="text-info">'+(data.message||'Verification in progress...')+'</small>';setTimeout(poll,2000);}}).catch(()=>setTimeout(poll,2000));};setTimeout(poll,2000);}
function getEmailOtp(){const email=document.getElementById('email').value;if(!email){alert('Please enter your email address first.');return;}const btn=document.getElementById('getEmailOtpBtn');btn.disabled=true;btn.textContent='Sending...';postJson('{{ route('register.otp.email.send') }}',{email}).then(data=>{if(data.success){document.getElementById('emailOtpSection').style.display='block';document.getElementById('emailOtpStatus').textContent='OTP sent to your email. Please check and enter it.'+(data.otp?' (Dev Mode OTP: '+data.otp+')':'');btn.disabled=false;btn.textContent='Resend OTP';}else{alert(data.message||'Failed to send OTP.');btn.disabled=false;btn.textContent='Get OTP';}});}
function verifyEmailOtp(){const email=document.getElementById('email').value;const otp=document.getElementById('email_otp').value;postJson('{{ route('register.otp.email.verify') }}',{email,otp}).then(data=>{if(data.success){emailVerified=true;const emailInput=document.getElementById('email');emailInput.readOnly=true;emailInput.style.backgroundColor='#d4edda';emailInput.style.borderColor='#28a745';document.getElementById('getEmailOtpBtn').disabled=true;document.getElementById('getEmailOtpBtn').textContent='Verified';document.getElementById('getEmailOtpBtn').classList.remove('btn-outline-primary');document.getElementById('getEmailOtpBtn').classList.add('btn-success');document.getElementById('emailOtpSection').style.display='none';document.getElementById('emailVerificationStatus').style.display='block';document.getElementById('emailVerificationStatus').innerHTML='<small class="text-success"><strong>✓ Email verified successfully!</strong></small>';checkAllValidations();}else{document.getElementById('emailOtpStatus').textContent=data.message||'Invalid OTP.';}});}
function getMobileOtp(){const mobile=document.getElementById('mobile').value;if(!mobile||mobile.length!==10){alert('Please enter a valid 10-digit mobile number.');return;}const btn=document.getElementById('getMobileOtpBtn');btn.disabled=true;btn.textContent='Sending...';postJson('{{ route('register.otp.mobile.send') }}',{mobile}).then(data=>{if(data.success){document.getElementById('mobileOtpSection').style.display='block';document.getElementById('mobileOtpStatus').textContent='OTP sent to your mobile. Please check and enter it.'+(data.otp?' (Dev: '+data.otp+')':'');document.getElementById('mobileOtpStatus').className='form-text text-success';btn.disabled=false;btn.textContent='Resend OTP';}else{alert(data.message||'Failed to send OTP.');btn.disabled=false;btn.textContent='Get OTP';}});}
function verifyMobileOtp(){const mobile=document.getElementById('mobile').value;const otp=document.getElementById('mobile_otp').value;postJson('{{ route('register.otp.mobile.verify') }}',{mobile,otp}).then(data=>{if(data.success){mobileVerified=true;const mobileInput=document.getElementById('mobile');mobileInput.readOnly=true;mobileInput.style.backgroundColor='#d4edda';mobileInput.style.borderColor='#28a745';document.getElementById('getMobileOtpBtn').disabled=true;document.getElementById('getMobileOtpBtn').textContent='Verified';document.getElementById('getMobileOtpBtn').classList.remove('btn-outline-primary');document.getElementById('getMobileOtpBtn').classList.add('btn-success');document.getElementById('mobileOtpSection').style.display='none';document.getElementById('mobileVerificationStatus').style.display='block';document.getElementById('mobileVerificationStatus').innerHTML='<small class="text-success"><strong>✓ Mobile verified successfully!</strong></small>';checkAllValidations();}else{document.getElementById('mobileOtpStatus').textContent=data.message||'Invalid OTP.';}});}
function checkAllValidations(){const panValid=panVerified;const nameValid=document.getElementById('fullname').value.trim().length>1;const emailValid=emailVerified;const mobileValid=mobileVerified;const dobValid=document.getElementById('dateofbirth').value;const declarationValid=document.getElementById('declaration').checked;const allValid=panValid&&nameValid&&emailValid&&mobileValid&&dobValid&&declarationValid;document.getElementById('submitBtn').disabled=!allValid;document.getElementById('verificationWarning').style.display=allValid?'none':'block';}
document.getElementById('dateofbirth').addEventListener('change',checkAllValidations);document.getElementById('declaration').addEventListener('change',checkAllValidations);
document.getElementById('registrationForm').addEventListener('submit',function(e){if(!panVerified||!emailVerified||!mobileVerified||!document.getElementById('declaration').checked){e.preventDefault();alert('Please complete all required fields, verifications, and accept the declaration before submitting.');checkAllValidations();}});
checkAllValidations();
function redirectToHome(){window.location.href='{{ route('home') }}';}
</script>
</body>
</html>
