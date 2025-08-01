<?php

namespace Usersau\UsersauLaravelClient\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirect()
    {
        // Store in session that this is a login flow
        session(['usersau_flow' => 'login']);
        
        return Socialite::driver('usersau')->redirect();
    }

    public function callback()
    {
        try {
            /** @var \SocialiteProviders\Manager\OAuth2\User $user */
            $authUser = Socialite::driver('usersau')->user();
            // Let's create a new entry in our users table (or update if it already exists) with some information from the user
            $userModel = app(config('usersau.user_model'));

            $updateArray = [
                'name' => $authUser->getName(),
                'email' => $authUser->getEmail(),
                'usersau_access_token' => $authUser->token,
                'usersau_refresh_token' => $authUser->refreshToken,
            ];
            if (config('usersau.profile_photo_column') ?? false) {
                $updateArray[config('usersau.profile_photo_column')] = $authUser->getAvatar();
            }
            
            $user = $userModel->updateOrCreate([
                'usersau_id' => $authUser->getId(),
            ], $updateArray);
            
            // Logging the user in
            app(\Illuminate\Support\Facades\Auth::class)::login($user);
            
            // Redirect based on the flow that initiated the OAuth process
            $flow = session('usersau_flow', 'login'); // Default to login for backward compatibility
            session()->forget('usersau_flow'); // Clean up the session
            
            if ($flow === 'register') {
                $url = config('usersau.after_register_url');
            } else {
                $url = config('usersau.after_login_url');
            }

            return redirect()->to($url);
        } catch (\Laravel\Socialite\Two\InvalidStateException|\GuzzleHttp\Exception\ClientException $e) {
            return redirect()->route('usersau.login')->with('status', 'Unable to login at this time. Please try again.');
        }
    }

    public function logout()
    {
        /** @var Authenticatable $user */
        app(\Illuminate\Support\Facades\Auth::class)::logout();
        // Build the logout URL using the host from the config file
        $url = config('services.usersau.host') . '/logout?' . http_build_query([
            'continue' => url(config('usersau.after_logout_url')),
        ]);

        return redirect()->away($url);
    }

    public function account()
    {
        /** @var Authenticatable $user */
        $user = app(\Illuminate\Support\Facades\Auth::class)::user();
        if (! $user) {
            return redirect()->route('usersau.login');
        }

        return redirect()->away(config('services.usersau.host') . '/account');
    }

    public function register()
    {
        // Store in session that this is a registration flow
        session(['usersau_flow' => 'register']);
        
        // Build the registration URL with redirect_uri parameter, similar to how Socialite handles login
        $callbackUrl = url('/auth/usersau/callback');
        $registerUrl = config('services.usersau.host') . '/register?' . http_build_query([
            'redirect_uri' => $callbackUrl,
        ]);
        
        return redirect()->away($registerUrl);
    }
}
