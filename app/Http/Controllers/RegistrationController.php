<?php

namespace App\Http\Controllers;

use App\Services\BlacklistService;
use App\Services\BulkSmsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly BlacklistService $blacklistService,
        private readonly BulkSmsService $bulkSmsService,
    ) {
    }

    public function show()
    {
        return view('portal.register');
    }

    public function sendEmailOtp(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Invalid email address.']);
        }

        if ($this->blacklistService->isIdentityBlocked([
            'email' => $email,
            'ip_address' => $request->ip(),
            'device_fingerprint' => $this->blacklistService->getFingerprint($request),
        ])) {
            return response()->json(['success' => false, 'message' => 'Registration is blocked for this identity.']);
        }

        if ($this->existsInUsersOrRegistration('email', $email)) {
            return response()->json(['success' => false, 'message' => 'Email address already exists.']);
        }

        $otp = (string) random_int(100000, 999999);
        $request->session()->put('email_otp_' . md5($email), $otp);
        $request->session()->put('email_otp_time_' . md5($email), time());

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email. Please check and enter it.',
            'otp' => $otp,
            'email_sent' => $this->sendOtpEmail($email, $otp),
        ]);
    }

    public function verifyEmailOtp(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $otp = preg_replace('/[^0-9]/', '', (string) $request->input('otp', ''));
        $storedOtp = (string) $request->session()->get('email_otp_' . md5($email), '');
        $otpTime = (int) $request->session()->get('email_otp_time_' . md5($email), 0);

        if (strlen($otp) !== 6) {
            return response()->json(['success' => false, 'message' => 'Invalid OTP format.']);
        }

        if (! $storedOtp || $storedOtp !== $otp || (time() - $otpTime) > 600) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP.']);
        }

        $request->session()->put('email_verified_' . md5($email), true);
        return response()->json(['success' => true, 'message' => 'Email verified successfully.']);
    }

    public function sendMobileOtp(Request $request)
    {
        $mobile = $this->normalizeMobile((string) $request->input('mobile', ''));
        if (strlen($mobile) !== 10) {
            return response()->json(['success' => false, 'message' => 'Mobile number must be 10 digits.']);
        }

        if ($this->existsInUsersOrRegistration('mobile', $mobile)) {
            return response()->json(['success' => false, 'message' => 'Mobile number already exists.']);
        }

        $otp = (string) random_int(100000, 999999);
        $request->session()->put('mobile_otp_' . md5($mobile), $otp);
        $request->session()->put('mobile_otp_time_' . md5($mobile), time());

        if (config('sms.enabled')) {
            if (! $this->bulkSmsService->sendOtp($mobile, $otp)) {
                Log::warning('Registration mobile OTP: SMS send failed', [
                    'mobile_last4' => substr($mobile, -4),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Could not send SMS. Please try again later or contact support.',
                ]);
            }

            return response()->json(['success' => true, 'message' => 'OTP sent to your mobile.']);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP generated (SMS gateway disabled — use OTP below in local dev).',
            'otp' => $otp,
        ]);
    }

    public function verifyMobileOtp(Request $request)
    {
        $mobile = $this->normalizeMobile((string) $request->input('mobile', ''));
        $otp = preg_replace('/[^0-9]/', '', (string) $request->input('otp', ''));
        $storedOtp = (string) $request->session()->get('mobile_otp_' . md5($mobile), '');
        $otpTime = (int) $request->session()->get('mobile_otp_time_' . md5($mobile), 0);

        if (strlen($otp) !== 6) {
            return response()->json(['success' => false, 'message' => 'Invalid OTP format.']);
        }

        if (! $storedOtp || $storedOtp !== $otp || (time() - $otpTime) > 600) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP.']);
        }

        $request->session()->put('mobile_verified_' . md5($mobile), true);
        return response()->json(['success' => true, 'message' => 'Mobile verified successfully.']);
    }

    public function verifyPan(Request $request)
    {
        $pan = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $request->input('pancardno', '')));
        $fullName = trim((string) $request->input('fullname', ''));
        $dob = trim((string) $request->input('dateofbirth', ''));
        if (! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan)) {
            return response()->json(['success' => false, 'message' => 'Invalid PAN format.']);
        }
        if ($fullName === '' || $dob === '') {
            return response()->json(['success' => false, 'message' => 'Name and date are required before PAN verification.']);
        }

        if ($this->existsInUsersOrRegistration('pan_card_number', $pan)) {
            return response()->json(['success' => false, 'message' => 'PAN already exists.']);
        }

        if ($this->blacklistService->isIdentityBlocked([
            'pan_card_number' => $pan,
            'ip_address' => $request->ip(),
            'device_fingerprint' => $this->blacklistService->getFingerprint($request),
        ])) {
            return response()->json(['success' => false, 'message' => 'PAN is blocked from registration.']);
        }

        $dobNormalized = (string) \Carbon\Carbon::parse($dob)->format('Y-m-d');
        $fullNameNormalized = strtoupper($fullName);

        $response = Http::withHeaders([
            'account-id' => (string) env('IDFY_ACCOUNT_ID', ''),
            'api-key' => (string) env('IDFY_API_KEY', ''),
            'Content-Type' => 'application/json',
        ])->post(rtrim((string) env('IDFY_BASE_URL', 'https://eve.idfy.com'), '/') . '/v3/tasks/async/verify_with_source/ind_pan', [
            'task_id' => (string) \Illuminate\Support\Str::uuid(),
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'data' => [
                'id_number' => $pan,
                // IdFy expects `full_name` in this flow (matches the working service you showed).
                'full_name' => $fullNameNormalized,
                'dob' => $dobNormalized,
            ],
        ]);

        if (! $response->successful()) {
            return response()->json(['success' => false, 'message' => 'PAN verification API failed.']);
        }
        $json = $response->json();
        $requestId = (string) ($json['request_id'] ?? '');
        if ($requestId === '') {
            return response()->json(['success' => false, 'message' => 'Failed to initiate PAN verification.']);
        }
        $request->session()->put('pan_verification_request_id_' . md5($pan), $requestId);
        $request->session()->put('pan_verification_dob_' . md5($pan), $dobNormalized);

        return response()->json(['success' => true, 'request_id' => $requestId, 'message' => 'Verification initiated']);
    }

    public function checkPanStatus(Request $request)
    {
        $requestId = trim((string) $request->input('request_id', ''));
        $pan = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $request->input('pancardno', '')));
        if ($requestId === '' || $pan === '') {
            return response()->json(['status' => 'failed', 'message' => 'Invalid verification request.']);
        }

        $response = Http::withHeaders([
            'account-id' => (string) env('IDFY_ACCOUNT_ID', ''),
            'api-key' => (string) env('IDFY_API_KEY', ''),
        ])->get(rtrim((string) env('IDFY_BASE_URL', 'https://eve.idfy.com'), '/') . '/v3/tasks', [
            'request_id' => $requestId,
        ]);

        if (! $response->successful()) {
            return response()->json(['status' => 'processing', 'message' => 'Verification in progress...']);
        }
        $payload = $response->json();
        $status = strtolower((string) ($payload[0]['status'] ?? $payload['status'] ?? 'in_progress'));
        if (! in_array($status, ['completed', 'failed'], true)) {
            return response()->json(['status' => 'processing', 'message' => 'Verification in progress...']);
        }
        if ($status === 'failed') {
            Log::error('IdFy PAN verification failed', [
                'request_id' => $requestId,
                'pan' => substr($pan, 0, 4) . '****',
                'payload' => $payload,
            ]);
            return response()->json(['status' => 'failed', 'message' => 'PAN verification failed.']);
        }

        $sourceOutput = $payload[0]['result']['source_output'] ?? ($payload['result']['source_output'] ?? []);
        $panStatus = (string) ($sourceOutput['pan_status'] ?? '');
        $nameMatch = (bool) ($sourceOutput['name_match'] ?? false);
        $dobMatch = (bool) ($sourceOutput['dob_match'] ?? false);
        $verified = (stripos($panStatus, 'valid') !== false) && $nameMatch && $dobMatch;

        if (! $verified) {
            return response()->json(['status' => 'completed', 'verified' => false, 'message' => 'PAN is invalid or details do not match.']);
        }

        // Age Restriction Check
        $dob = $request->session()->get('pan_verification_dob_' . md5($pan));
        if ($dob) {
            $age = $this->calculateAge($dob);
            if ($age < 18) {
                return response()->json([
                    'status' => 'completed',
                    'verified' => false,
                    'message' => 'You must be at least 18 years old to register.',
                    'age_restricted' => true,
                    'age' => $age,
                ]);
            }
        }

        $request->session()->put('pan_verified_' . md5($pan), true);
        $request->session()->put('pan_verification_data_' . md5($pan), [
            'pan_number' => $pan,
            'pan_status' => $panStatus,
            'name_match' => $nameMatch,
            'dob_match' => $dobMatch,
            'verified_at' => now()->toDateTimeString(),
        ]);
        return response()->json(['status' => 'completed', 'verified' => true, 'message' => 'PAN verified successfully.']);
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'registration_type' => ['required', 'in:individual,entity'],
            'fullname' => ['required', 'string', 'min:2', 'max:255'],
            'pancardno' => ['required', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}$/'],
            'email' => ['required', 'email'],
            'mobile' => ['required', 'digits:10'],
            'dateofbirth' => ['required', 'date', 'before:today'],
            'declaration' => ['accepted'],
        ]);

        $email = strtolower($validated['email']);
        $mobileRaw = (string) $validated['mobile'];
        $mobile = $this->normalizeMobile($mobileRaw);
        $pan = strtoupper($validated['pancardno']);
        $deviceFingerprint = $this->blacklistService->getFingerprint($request);

        if ($this->blacklistService->isIdentityBlocked([
            'email' => $email,
            'mobile' => $mobile,
            'pan_card_number' => $pan,
            'ip_address' => $request->ip(),
            'device_fingerprint' => $deviceFingerprint,
        ])) {
            return back()->withErrors(['email' => 'Registration blocked for this identity.'])->withInput();
        }

        if (! $request->session()->get('email_verified_' . md5($email), false)) {
            return back()->withErrors(['email' => 'Please verify your email address.'])->withInput();
        }
        $isMobileVerified = (bool) $request->session()->get('mobile_verified_' . md5($mobile), false);
        if (! $isMobileVerified && $mobileRaw !== $mobile) {
            // Backward-compatible fallback if an older session stored verification on raw input.
            $isMobileVerified = (bool) $request->session()->get('mobile_verified_' . md5($mobileRaw), false);
        }
        if (! $isMobileVerified) {
            return back()->withErrors(['mobile' => 'Please verify your mobile number.'])->withInput();
        }
        if (! $request->session()->get('pan_verified_' . md5($pan), false)) {
            return back()->withErrors(['pancardno' => 'Please verify your PAN Card.'])->withInput();
        }
        if ($this->existsInUsersOrRegistration('email', $email)) {
            return back()->withErrors(['email' => 'Email address is already registered.'])->withInput();
        }
        if ($this->existsInUsersOrRegistration('mobile', $mobile)) {
            return back()->withErrors(['mobile' => 'Mobile number is already registered.'])->withInput();
        }
        if ($this->existsInUsersOrRegistration('pan_card_number', $pan)) {
            return back()->withErrors(['pancardno' => 'PAN is already registered.'])->withInput();
        }

        $request->session()->put('pending_registration', [
            'registration_type' => $validated['registration_type'],
            'full_name' => $validated['fullname'],
            'date_of_birth' => $validated['dateofbirth'],
            'pan_card_number' => $pan,
            'email' => $email,
            'mobile' => $mobile,
            'device_fingerprint' => $deviceFingerprint,
            'ip_address' => (string) $request->ip(),
            'expires_at' => now()->addMinutes((int) config('auction.registration_expiry_minutes', 30))->toDateTimeString(),
        ]);

        return redirect()->route('payments.registration.initiate');
    }

    private function existsInUsersOrRegistration(string $column, string $value): bool
    {
        $inUsers = Schema::hasTable('users') && Schema::hasColumn('users', $column)
            ? DB::table('users')->where($column, $value)->exists()
            : false;
        $inRegistration = Schema::hasTable('registration') && Schema::hasColumn('registration', $column)
            ? DB::table('registration')->where($column, $value)->exists()
            : false;

        return $inUsers || $inRegistration;
    }

    private function existsInRegistration(string $column, string $value): bool
    {
        if (! Schema::hasTable('registration') || ! Schema::hasColumn('registration', $column)) {
            return false;
        }
        return DB::table('registration')->where($column, $value)->exists();
    }

    private function normalizeMobile(string $mobile): string
    {
        return preg_replace('/[^0-9]/', '', trim($mobile));
    }

    private function sendOtpEmail(string $email, string $otp): bool
    {
        try {
            $this->applyActiveAdminEmailSettingsForOtp();
            Mail::mailer('smtp')->raw("Your OTP for email verification is: {$otp}", static function ($message) use ($email): void {
                $message->to($email)->subject('Email Verification OTP');
            });
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function applyActiveAdminEmailSettingsForOtp(): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('email_settings')) {
                return;
            }

            $settings = DB::table('email_settings')
                ->where('is_active', 1)
                ->latest('updated_at')
                ->first();

            if (! $settings) {
                return;
            }

            $encryption = strtolower(trim((string) ($settings->encryption ?? '')));
            $smtpScheme = match ($encryption) {
                'ssl' => 'smtps',
                'tls', '' => 'smtp',
                default => 'smtp',
            };

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => (string) ($settings->smtp_host ?? config('mail.mailers.smtp.host')),
                'mail.mailers.smtp.port' => (int) ($settings->smtp_port ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.username' => (string) ($settings->smtp_username ?? config('mail.mailers.smtp.username')),
                'mail.mailers.smtp.password' => (string) ($settings->smtp_password ?? config('mail.mailers.smtp.password')),
                'mail.mailers.smtp.scheme' => $smtpScheme,
                'mail.mailers.smtp.timeout' => 10,
                'mail.from.address' => (string) ($settings->from_email ?? config('mail.from.address')),
                'mail.from.name' => (string) ($settings->from_name ?? config('mail.from.name')),
            ]);

            Mail::purge('smtp');
        } catch (\Throwable) {
            // Keep default mail config if dynamic DB settings cannot be loaded.
        }
    }

    private function calculateAge(string $dob): int
    {
        try {
            $birthDate = \Carbon\Carbon::parse($dob);
            $today = \Carbon\Carbon::now();
            return $today->diffInYears($birthDate);
        } catch (\Throwable) {
            return 0;
        }
    }
}
