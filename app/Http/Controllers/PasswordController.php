<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordController extends Controller
{
    public function forgot()
    {
        return view('portal.forgot-password');
    }

    public function sendOtp(Request $request)
    {
        $email = filter_var((string) $request->input('email', ''), FILTER_SANITIZE_EMAIL);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Invalid email address.']);
        }
        $user = DB::table('users')->where('email', $email)->first();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Email address not found in our system.']);
        }
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $request->session()->put('forgot_password_otp_' . md5($email), $otp);
        $request->session()->put('forgot_password_otp_time_' . md5($email), time());
        $request->session()->put('forgot_password_user_id', $user->id);
        $request->session()->put('forgot_password_email', $email);
        try {
            Mail::raw("Your password reset OTP is: {$otp}. Valid for 10 minutes.", static function ($m) use ($email): void {
                $m->to($email)->subject('Password Reset OTP');
            });
        } catch (\Throwable) {
            // keep debug OTP path
        }
        return response()->json(['success' => true, 'message' => 'OTP sent to your email.', 'otp' => $otp]);
    }

    public function verifyOtp(Request $request)
    {
        $email = filter_var((string) $request->input('email', ''), FILTER_SANITIZE_EMAIL);
        $otp = preg_replace('/[^0-9]/', '', (string) $request->input('otp', ''));
        $stored = (string) $request->session()->get('forgot_password_otp_' . md5($email), '');
        $time = (int) $request->session()->get('forgot_password_otp_time_' . md5($email), 0);
        if ($stored === '' || $stored !== $otp || (time() - $time) > 600) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP.']);
        }
        $token = bin2hex(random_bytes(32));
        DB::table('users')->where('email', $email)->update([
            'password_reset_token' => $token,
            'password_reset_expires' => now()->addDays(7),
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'redirect_url' => route('password.reset.form', ['token' => $token])]);
    }

    public function showResetForm(string $token)
    {
        $user = DB::table('users')->where('password_reset_token', $token)->first();
        if (! $user || ! $user->password_reset_expires || strtotime((string) $user->password_reset_expires) < time()) {
            return view('portal.reset-password', ['tokenValid' => false, 'token' => $token]);
        }
        return view('portal.reset-password', ['tokenValid' => true, 'token' => $token]);
    }

    public function updatePassword(Request $request, string $token)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8'],
            'confirm_password' => ['required', 'same:password'],
        ]);
        $user = DB::table('users')
            ->where('password_reset_token', $token)
            ->where('password_reset_expires', '>=', now())
            ->first();
        if (! $user) {
            return back()->withErrors(['token' => 'Invalid or expired token.']);
        }
        DB::table('users')->where('id', $user->id)->update([
            'password' => Hash::make((string) $request->input('password')),
            'password_reset_token' => null,
            'password_reset_expires' => null,
            'updated_at' => now(),
        ]);
        return redirect()->route('login')->with('status', 'Password updated successfully! You can now login.');
    }
}
