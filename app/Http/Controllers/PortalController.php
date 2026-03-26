<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function home(Request $request)
    {
        if ($request->session()->has('user_id')) {
            $role = (string) $request->session()->get('role', 'user');
            if ($role !== 'admin') {
                return redirect()->route('user.auctions.index');
            }
        }

        return view('portal.home');
    }

    public function register()
    {
        return view('portal.register');
    }
}
