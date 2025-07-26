<?php

namespace Usersau\UsersauLaravelClient\Test\Unit;

use Usersau\UsersauLaravelClient\Test\TestCase;
use Usersau\UsersauLaravelClient\UsersauLaravelClientServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_is_registered()
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(UsersauLaravelClientServiceProvider::class, $providers);
    }

    public function test_routes_are_loaded()
    {
        $router = $this->app['router'];
        $routes = $router->getRoutes();

        $expectedRoutes = [
            'auth/usersau/redirect',
            'auth/usersau/callback',
            'auth/usersau/logout',
            'auth/usersau/register',
            'auth/usersau/account',
        ];

        foreach ($expectedRoutes as $expectedRoute) {
            $found = false;
            foreach ($routes as $route) {
                if ($route->uri() === $expectedRoute) {
                    $found = true;

                    break;
                }
            }
            $this->assertTrue($found, "Route {$expectedRoute} was not found");
        }
    }

    public function test_configuration_is_set_correctly()
    {
        $this->assertEquals('test_client_id', config('services.usersau.client_id'));
        $this->assertEquals('test_client_secret', config('services.usersau.client_secret'));
        $this->assertEquals('http://localhost/auth/usersau/callback', config('services.usersau.redirect'));
        $this->assertEquals('http://localhost', config('services.usersau.host'));

        $this->assertEquals('/dashboard', config('usersau.after_login_url'));
        $this->assertEquals('/', config('usersau.after_logout_url'));
        $this->assertEquals('/welcome', config('usersau.after_register_url'));
        $this->assertEquals(['web'], config('usersau.middleware'));
    }

    public function test_event_service_provider_is_registered()
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(
            \Usersau\UsersauLaravelClient\Providers\EventServiceProvider::class,
            $providers
        );
    }
}
