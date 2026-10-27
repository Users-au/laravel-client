<?php

/*
 * (c) Users.au <support@users.au>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Usersau\UsersauLaravelClient;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UsersauLaravelClientServiceProvider extends ServiceProvider
{
    /**
     * Publishes all the config file this package needs to function.
     */
    public function boot()
    {
        $this->app->register(\Usersau\UsersauLaravelClient\Providers\EventServiceProvider::class);
        $this->registerPublishing();
        $this->registerRoutes();
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        if (!config('usersau.routes_enabled', true)) {
            return;
        }

        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config' => config_path(),
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'usersau');
        }
    }

    /**
     * Get the Nova route group configuration array.
     *
     * @return array
     */
    protected function routeConfiguration()
    {
        return [
            'namespace' => 'Usersau\UsersauLaravelClient\Http\Controllers',
            //'prefix' => 'usersau',
            'as' => 'usersau.',
            'middleware' => 'web',
        ];
    }
}
