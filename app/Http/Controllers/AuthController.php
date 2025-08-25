<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class AuthController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Login');
    }

    public function login()
    {
        try {
            // Check if identity configuration is set
            if (empty(config('services.identity.openid_configuration'))) {
                return back()->with('error', 'OAuth identity service is not configured. Please check your environment variables.');
            }

            $url = Socialite::driver('identity')
                ->scopes(['openid', 'profile', 'email', 'groups'])
                ->redirect();

            // Use Interia location instead
            return Inertia::location($url->getTargetUrl());
        } catch (\Exception $e) {
            // Log the error and redirect back with a helpful message
            \Log::error('OAuth login error: ' . $e->getMessage());
            return back()->with('error', 'Unable to connect to authentication service: ' . $e->getMessage());
        }
    }

    public function loginCallback()
    {
        try {
            $user = Socialite::driver('identity')->user();
        } catch (Exception $e) {
            return redirect()->route('auth.login');
        }
        if (!in_array('54ZYODX15G2K1M76', $user->user['groups'], true)) {
            return redirect()->route('auth.login')->with('error', 'You are not allowed to access this application');
        }

        $user = User::updateOrCreate([
            'remote_id' => $user->getId(),
        ], [
            'remote_id' => $user->getId(),
            'name' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'is_admin' => in_array('6MZYL5XWEXNORPJK', $user->user['groups'], true) || in_array('N9OY0K8OJVXR1P7L', $user->user['groups'], true),
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
