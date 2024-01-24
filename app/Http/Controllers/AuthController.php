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
        $user = User::updateOrCreate([
            'remote_id' => $user->getId(),
        ], [
            'remote_id' => $user->getId(),
            'name' => $user->getName(),
            'avatar' => $user->getAvatar(),
        ]);
        Auth::login($user);
        return redirect()->route('dashboard');
    }

    public function logout()
    {
        return Socialite::driver('identity');
    }


    // Frontchannel Logout
    public function logoutCallback()
    {
        Auth::logout();
        Session::flush();
    }
}
