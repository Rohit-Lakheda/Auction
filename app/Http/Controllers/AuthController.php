<?php

namespace App\Http\Controllers;

use App\Services\BlacklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private readonly BlacklistService $blacklistService)
    {
    }

    public function showLogin(Request $request)
    {
        if ($request->session()->has('user_id')) {
            return $this->redirectByRole((string) $request->session()->get('role', 'user'));
        }

        return view('portal.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $email = strtolower(trim((string) $validated['email']));
        $fingerprint = $this->blacklistService->getFingerprint($request);

        if ($this->blacklistService->isIdentityBlocked([
            'email' => $email,
            'ip_address' => $request->ip(),
            'device_fingerprint' => $fingerprint,
        ])) {
            $this->logLoginAttempt($email, false, $request, 'blacklisted_identity');
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Your account access is blocked. Contact support.',
            ]);
        }

        $user = DB::table('users')
            ->select(['id', 'name', 'email', 'password', 'role'])
            ->where('email', $email)
            ->first();

        if (! $user) {
            $this->logLoginAttempt($email, false, $request, 'user_not_found');
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Invalid email or password',
            ]);
        }

        $storedPassword = (string) $user->password;
        $isHashed = preg_match('/^\$2[ayb]\$.{56}$/', $storedPassword) === 1;
        $passwordValid = false;
        $needsHashing = false;

        if ($isHashed) {
            $passwordValid = Hash::check($validated['password'], $storedPassword);
        } else {
            $passwordValid = hash_equals($storedPassword, $validated['password']);
            $needsHashing = $passwordValid;
        }

        if (! $passwordValid) {
            $this->logLoginAttempt($email, false, $request, 'bad_password');
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Invalid email or password',
            ]);
        }

        if ($needsHashing) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['password' => Hash::make($validated['password'])]);
        }

        if (Schema::hasColumn('users', 'must_reset_password') && (int) (DB::table('users')->where('id', $user->id)->value('must_reset_password') ?? 0) === 1) {
            $token = (string) (DB::table('users')->where('id', $user->id)->value('password_reset_token') ?? '');
            if ($token === '') {
                $token = Str::random(64);
                DB::table('users')->where('id', $user->id)->update([
                    'password_reset_token' => $token,
                    'password_reset_expires' => now()->addDays(7),
                    'updated_at' => now(),
                ]);
            }
            $this->logLoginAttempt($email, true, $request, 'forced_password_reset');
            return redirect()->route('password.reset.form', ['token' => $token])
                ->with('status', 'Please reset your password before continuing.');
        }

        $request->session()->put([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        $request->session()->regenerate();
        $loginUpdate = ['updated_at' => now()];
        if (Schema::hasColumn('users', 'last_login_at')) {
            $loginUpdate['last_login_at'] = now();
        }
        if (Schema::hasColumn('users', 'last_login_ip')) {
            $loginUpdate['last_login_ip'] = (string) $request->ip();
        }
        DB::table('users')->where('id', $user->id)->update($loginUpdate);
        $this->logLoginAttempt($email, true, $request, 'login_success');

        return $this->redirectByRole((string) $user->role);
    }

    public function showRegister()
    {
        return view('portal.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request->session()->put([
            'user_id' => $userId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => 'user',
        ]);
        $request->session()->regenerate();

        return redirect()->route('user.auctions.index');
    }

    public function logout(Request $request)
    {
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function redirectByRole(string $role)
    {
        if ($role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('user.dashboard');
    }

    private function logLoginAttempt(string $email, bool $success, Request $request, string $reason): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'actor_user_id' => null,
            'actor_role' => 'guest',
            'action' => $success ? 'login_success' : 'login_failed',
            'entity_type' => 'user',
            'entity_id' => $email,
            'ip_address' => (string) $request->ip(),
            'device_fingerprint' => $this->blacklistService->getFingerprint($request),
            'meta' => json_encode(['reason' => $reason], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
