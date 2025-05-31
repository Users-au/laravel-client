<?php

namespace Usersau\UsersauLaravelClient\Test\Feature;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Usersau\UsersauLaravelClient\Test\TestCase;
use Usersau\UsersauLaravelClient\Test\TestUser;

class AuthControllerTest extends TestCase
{
    public function test_redirect_initiates_oauth_flow()
    {
        $socialiteDriverMock = Mockery::mock();
        $socialiteDriverMock->shouldReceive('redirect')
            ->once()
            ->andReturn(new RedirectResponse('http://localhost/oauth/authorize'));

        Socialite::shouldReceive('driver')
            ->with('usersau')
            ->once()
            ->andReturn($socialiteDriverMock);

        $response = $this->get('/auth/usersau/redirect');

        $response->assertRedirect();
        $this->assertEquals('http://localhost/oauth/authorize', $response->getTargetUrl());
    }

    public function test_callback_creates_new_user_and_logs_in()
    {
        $socialiteUserMock = Mockery::mock();
        $socialiteUserMock->shouldReceive('getId')->andReturn('12345');
        $socialiteUserMock->shouldReceive('getName')->andReturn('John Doe');
        $socialiteUserMock->shouldReceive('getEmail')->andReturn('john@example.com');
        $socialiteUserMock->shouldReceive('getAvatar')->andReturn('http://localhost/avatar.jpg');
        $socialiteUserMock->token = 'access_token_123';
        $socialiteUserMock->refreshToken = 'refresh_token_123';

        $socialiteDriverMock = Mockery::mock();
        $socialiteDriverMock->shouldReceive('user')
            ->once()
            ->andReturn($socialiteUserMock);

        Socialite::shouldReceive('driver')
            ->with('usersau')
            ->once()
            ->andReturn($socialiteDriverMock);

        Auth::shouldReceive('login')->once();

        $response = $this->get('/auth/usersau/callback');

        $response->assertRedirect('/dashboard');
        
        $this->assertDatabaseHas('users', [
            'usersau_id' => '12345',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_callback_updates_existing_user()
    {
        // Create existing user
        $user = TestUser::create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => 'dummy_password',
            'usersau_id' => '12345',
            'usersau_access_token' => 'old_token',
            'usersau_refresh_token' => 'old_refresh_token',
        ]);

        $socialiteUserMock = Mockery::mock();
        $socialiteUserMock->shouldReceive('getId')->andReturn('12345');
        $socialiteUserMock->shouldReceive('getName')->andReturn('Updated Name');
        $socialiteUserMock->shouldReceive('getEmail')->andReturn('updated@example.com');
        $socialiteUserMock->shouldReceive('getAvatar')->andReturn('http://localhost/avatar.jpg');
        $socialiteUserMock->token = 'new_access_token';
        $socialiteUserMock->refreshToken = 'new_refresh_token';

        $socialiteDriverMock = Mockery::mock();
        $socialiteDriverMock->shouldReceive('user')
            ->once()
            ->andReturn($socialiteUserMock);

        Socialite::shouldReceive('driver')
            ->with('usersau')
            ->once()
            ->andReturn($socialiteDriverMock);

        Auth::shouldReceive('login')->once();

        $response = $this->get('/auth/usersau/callback');

        $response->assertRedirect('/dashboard');
        
        $this->assertDatabaseHas('users', [
            'usersau_id' => '12345',
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'usersau_access_token' => 'new_access_token',
            'usersau_refresh_token' => 'new_refresh_token',
        ]);
    }

    public function test_callback_handles_invalid_state_exception()
    {
        $socialiteDriverMock = Mockery::mock();
        $socialiteDriverMock->shouldReceive('user')
            ->once()
            ->andThrow(new \Laravel\Socialite\Two\InvalidStateException());

        Socialite::shouldReceive('driver')
            ->with('usersau')
            ->once()
            ->andReturn($socialiteDriverMock);

        $response = $this->get('/auth/usersau/callback');

        $response->assertRedirect(route('usersau.login'));
        $response->assertSessionHas('status', 'Unable to login at this time. Please try again.');
    }

    public function test_callback_handles_client_exception()
    {
        $socialiteDriverMock = Mockery::mock();
        $request = new \GuzzleHttp\Psr7\Request('GET', 'test');
        $response = new \GuzzleHttp\Psr7\Response(400, [], 'Bad Request');
        $socialiteDriverMock->shouldReceive('user')
            ->once()
            ->andThrow(new \GuzzleHttp\Exception\ClientException('Client error', $request, $response));

        Socialite::shouldReceive('driver')
            ->with('usersau')
            ->once()
            ->andReturn($socialiteDriverMock);

        $response = $this->get('/auth/usersau/callback');

        $response->assertRedirect(route('usersau.login'));
        $response->assertSessionHas('status', 'Unable to login at this time. Please try again.');
    }

    public function test_logout_logs_out_user_and_redirects_to_usersau()
    {
        Auth::shouldReceive('logout')->once();

        $response = $this->get('/auth/usersau/logout');

        $response->assertRedirect();
        $this->assertStringContainsString('http://localhost/logout', $response->getTargetUrl());
        $this->assertStringContainsString('continue=' . urlencode(url('/')), $response->getTargetUrl());
    }

    public function test_account_redirects_authenticated_user_to_usersau_account()
    {
        $user = new TestUser();
        $user->id = 1;
        
        Auth::shouldReceive('user')->andReturn($user);

        $response = $this->get('/auth/usersau/account');

        $response->assertRedirect('http://localhost/account');
    }

    public function test_account_redirects_unauthenticated_user_to_login()
    {
        Auth::shouldReceive('user')->andReturn(null);

        $response = $this->get('/auth/usersau/account');

        $response->assertRedirect(route('usersau.login'));
    }

    public function test_register_redirects_to_usersau_registration()
    {
        $response = $this->get('/auth/usersau/register');

        $response->assertRedirect('http://localhost/register');
    }

    public function test_routes_are_registered_with_correct_names()
    {
        $this->assertNotNull(route('usersau.login'));
        $this->assertNotNull(route('usersau.logout'));
        $this->assertNotNull(route('usersau.register'));
        $this->assertNotNull(route('usersau.account'));
    }

    public function test_middleware_is_applied_to_routes()
    {
        $router = app('router');
        $routes = $router->getRoutes();
        
        foreach ($routes as $route) {
            if (str_contains($route->uri, 'auth/usersau')) {
                $this->assertContains('web', $route->middleware());
            }
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
} 