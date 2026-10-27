<?php

namespace Usersau\UsersauLaravelClient\Test\Unit;

use Usersau\UsersauLaravelClient\Test\TestCase;

class RoutesDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('usersau.routes_enabled', false);
    }

    public function test_apps_can_use_the_par_service_without_registering_sdk_auth_routes(): void
    {
        $this->assertFalse($this->app['router']->has('usersau.login'));
        $this->assertFalse($this->app['router']->has('usersau.callback'));
    }
}
