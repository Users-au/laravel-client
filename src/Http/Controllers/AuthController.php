<?php

namespace Usersau\UsersauLaravelClient\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Usersau\UsersauLaravelClient\Contracts\AuthContextProvider;
use Usersau\UsersauLaravelClient\OAuth\PushedAuthorizationClient;

class AuthController extends Controller
{
    public function redirect()
    {
        // Store in session that this is a login flow
        session(['usersau_flow' => 'login']);

        /** @var \SocialiteProviders\Usersau\Provider $provider */
        $provider = Socialite::driver('usersau');
        $provider->scopes(config('usersau.scopes', ['openid', 'profile', 'email', 'preferences:read', 'preferences:write']));
        if (!config('usersau.par_enabled', true)) {
            return $provider->redirect();
        }
        // Socialite owns state, the S256 verifier and callback verification for both paths.
        $authorization = $provider->enablePKCE()->redirect();
        parse_str(parse_url($authorization->getTargetUrl(), PHP_URL_QUERY) ?? '', $parameters);

        return redirect()->away($this->pushedClient()->authorizationUrl($parameters, app(AuthContextProvider::class)));

    }

    public function callback()
    {
        try {
            /** @var \SocialiteProviders\Usersau\Provider $provider */
            $provider = Socialite::driver('usersau');
            if (config('usersau.par_enabled', true)) {
                $provider->enablePKCE();
            }
            $authUser = $provider->user();
            // Let's create a new entry in our users table (or update if it already exists) with some information from the user
            $raw = get_object_vars($authUser)['user'] ?? null;
            $claims = is_array($raw) ? $raw : [];
            $claims['sub'] = $claims['sub'] ?? (string) $authUser->getId();
            if ((string) $authUser->getId() !== '' && $claims['sub'] !== (string) $authUser->getId()) {
                abort(502, 'Identity subject mismatch.');
            }
            $normalizedProfile = new \Usersau\UsersauLaravelClient\Identity\UserProfile($claims);
            $userModel = app(config('usersau.user_model'));

            $updateArray = [
                'name' => $authUser->getName(),
                'email' => $authUser->getEmail(),
                'usersau_access_token' => $authUser->token,
                'usersau_refresh_token' => $authUser->refreshToken,
            ];
            if (config('usersau.profile_photo_column') ?? false) {
                $picture = $normalizedProfile->toArray()['picture'] ?? $authUser->getAvatar();
                if (is_string($picture) && filter_var($picture, FILTER_VALIDATE_URL)
                    && in_array(parse_url($picture, PHP_URL_SCHEME), ['http', 'https'], true)) {
                    $updateArray[config('usersau.profile_photo_column')] = $picture;
                }
            }

            $user = $userModel->updateOrCreate([
                'usersau_id' => $claims['sub'],
            ], $updateArray);

            event(new \Usersau\UsersauLaravelClient\Events\UserProfileSynchronized(
                $user,
                $normalizedProfile,
                rtrim((string) config('services.usersau.host'), '/'),
            ));

            // Logging the user in
            Auth::login($user);
            request()->session()->regenerate();

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
        } catch (\UnexpectedValueException|\InvalidArgumentException $exception) {
            abort(502, 'Invalid identity profile.');
        }
    }

    public function logout()
    {
        /** @var Authenticatable $user */
        $pushedUrl = config('usersau.par_enabled', true)
            ? $this->pushedClient()->logoutUrl(app(AuthContextProvider::class)) : null;
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        if ($pushedUrl) {
            return redirect()->away($pushedUrl);
        }
        // Build the logout URL using the host from the config file
        $url = config('services.usersau.host') . '/logout?' . http_build_query([
            'continue' => url(config('usersau.after_logout_url')),
        ]);

        return redirect()->away($url);
    }

    public function account()
    {
        /** @var Authenticatable $user */
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('usersau.login');
        }

        return redirect()->away(config('usersau.par_enabled', true)
            ? $this->pushedClient()->accountUrl(app(AuthContextProvider::class))
            : config('services.usersau.host') . '/account');
    }

    public function register()
    {
        if (config('usersau.par_enabled', true)) {
            $response = $this->redirect();
            session(['usersau_flow' => 'register']);

            return $response;
        }
        // Store in session that this is a registration flow
        session(['usersau_flow' => 'register']);

        // Build the registration URL with redirect_uri parameter, similar to how Socialite handles login
        $callbackUrl = url('/auth/usersau/callback');
        $registerUrl = config('services.usersau.host') . '/register?' . http_build_query([
            'redirect_uri' => $callbackUrl,
        ]);

        return redirect()->away($registerUrl);
    }
    private function pushedClient(): PushedAuthorizationClient
    {
        return new PushedAuthorizationClient(config('services.usersau'));
    }
}
