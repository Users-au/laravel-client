<?php

namespace Usersau\UsersauLaravelClient\Test\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Usersau\UsersauLaravelClient\Test\TestCase;

class PushedEntriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['usersau.par_enabled' => true, 'app.url' => 'https://app.example', 'app.name' => 'Application A',
            'usersau.context.logo_url' => 'https://app.example/logo.png',
            'services.usersau.host' => 'https://identity.example', 'services.usersau.redirect' => 'https://app.example/auth/usersau/callback']);
        Http::fake(['*' => Http::response(['request_uri' => 'urn:ietf:params:oauth:request_uri:' . str_repeat('x', 64), 'expires_in' => 300], 201)]);
        Socialite::extend('usersau', function ($app) {
            $provider = new \SocialiteProviders\Usersau\Provider($app['request'], config('services.usersau.client_id'), config('services.usersau.client_secret'), config('services.usersau.redirect'));

            return $provider->setConfig(new \SocialiteProviders\Manager\Config(config('services.usersau.client_id'), config('services.usersau.client_secret'), config('services.usersau.redirect'), ['host' => config('services.usersau.host')]));
        });
    }

    public function test_login_pushes_socialite_state_and_s256_while_browser_only_receives_a_handle(): void
    {
        $response = $this->get('/auth/usersau/redirect?home_url=https://attacker.example/');
        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://identity.example/oauth/authorize?', $url);
        $this->assertStringNotContainsString('Application A', $url);
        $state = session('state');
        $verifier = session('code_verifier');
        $this->assertNotEmpty($state);
        $this->assertNotEmpty($verifier);
        Http::assertSent(function ($request) use ($state, $verifier) {
            $context = json_decode($request['context'], true);

            return $request->url() === 'https://identity.example/oauth/par'
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode('test_client_id:test_client_secret'))
                && $request['state'] === $state && $request['code_challenge_method'] === 'S256'
                && $request['code_challenge'] === rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')
                && $context['branding']['name'] === 'Application A' && $context['links']['home_url'] === 'https://app.example/';
        });
    }

    public function test_callback_recreates_provider_and_sends_original_s256_verifier(): void
    {
        $this->get('/auth/usersau/redirect')->assertRedirect();
        $state = session('state');
        $verifier = session('code_verifier');
        Socialite::getFacadeRoot()->forgetDrivers();
        $history = [];
        $handler = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'fixture-access', 'refresh_token' => 'fixture-refresh', 'token_type' => 'Bearer'])),
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 'subject-42', 'name' => 'Example User', 'email' => 'user@example.test'])),
        ]));
        $handler->push(\GuzzleHttp\Middleware::history($history));
        $client = new \GuzzleHttp\Client(['handler' => $handler]);
        $this->app['events']->listen(\Illuminate\Routing\Events\RouteMatched::class, function () use ($client): void {
            /** @var \SocialiteProviders\Usersau\Provider $provider */
            $provider = Socialite::driver('usersau');
            $provider->setHttpClient($client);
        });
        Auth::shouldReceive('login')->once();
        $this->get('/auth/usersau/callback?' . http_build_query(['state' => $state, 'code' => 'fixture-code']))->assertRedirect('/dashboard');
        parse_str((string) $history[0]['request']->getBody(), $fields);
        $this->assertSame($verifier, $fields['code_verifier']);
        $this->assertSame('https://app.example/auth/usersau/callback', $fields['redirect_uri']);
    }

    public function test_account_pushes_fresh_context_and_guest_never_gets_an_account_handle(): void
    {
        Auth::shouldReceive('user')->once()->andReturn(null);
        $this->get('/auth/usersau/account')->assertRedirect(route('usersau.login'));
        Http::assertNothingSent();
        Auth::shouldReceive('user')->once()->andReturn((object) ['id' => 1]);
        $this->get('/auth/usersau/account')->assertRedirectContains('https://identity.example/account?');
        Http::assertSent(fn ($request) => $request->url() === 'https://identity.example/oauth/context'
            && $request['purpose'] === 'account' && !isset($request['redirect_uri'])
            && json_decode($request['context'], true)['branding']['logo_url'] === 'https://app.example/logo.png');
    }

    public function test_registration_and_logout_keep_their_intent_with_pushed_requests(): void
    {
        $this->get('/auth/usersau/register')->assertRedirectContains('/oauth/authorize?');
        $this->assertSame('register', session('usersau_flow'));
        Auth::shouldReceive('logout')->once();
        $this->get('/auth/usersau/logout')->assertRedirectContains('https://identity.example/logout?');
        Http::assertSent(fn ($request) => ($request['purpose'] ?? null) === 'logout'
            && json_decode($request['context'], true)['links']['logout_url'] === 'https://app.example/');
    }

    public function test_provider_rejection_never_falls_back_to_unbranded_login(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['error' => 'secret-response'], 401)]);
        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Users.au rejected the pushed authorization request.');
        $this->get('/auth/usersau/redirect');
    }
}
