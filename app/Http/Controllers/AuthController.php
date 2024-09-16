<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Login');
    }

    public function login()
    {
        $url = Socialite::driver('identity')
            ->scopes(['openid', 'profile', 'email', 'groups'])
            ->redirect();

        // Use Interia location instead
        return Inertia::location($url->getTargetUrl());
    }

    public function loginCallback()
    {
        $user = Socialite::driver('identity')->user();
        if (!in_array('54ZYODX15G2K1M76', $user->user['groups'], true)) {
            return redirect()->route('auth.login')->with('error', 'You are not allowed to access this application');
        }
        $user = User::updateOrCreate([
            'remote_id' => $user->getId(),
        ], [
            'remote_id' => $user->getId(),
            'name' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'is_admin' => in_array('6MZYL5XWEXNORPJK', $user->user['groups'], true),
        ]);
        Auth::login($user);
        return redirect()->route('dashboard');
    }

    public function logout()
    {
        return Inertia::location('https://identity.eurofurence.org/oauth2/sessions/logout');
    }


    // Frontchannel Logout
    public function logoutCallback()
    {
        Auth::logout();
        Session::flush();
    }
}
