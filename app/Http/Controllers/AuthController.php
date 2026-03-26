<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
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

        $user = DB::table('users')
            ->select(['id', 'name', 'email', 'password', 'role'])
            ->where('email', $validated['email'])
            ->first();

        if (! $user) {
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
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Invalid email or password',
            ]);
        }

        if ($needsHashing) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['password' => Hash::make($validated['password'])]);
        }

        $request->session()->put([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        $request->session()->regenerate();

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
}
