<?php

namespace Usersau\UsersauLaravelClient\Test;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Usersau\UsersauLaravelClient\UsersauLaravelClientServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            SocialiteServiceProvider::class,
            UsersauLaravelClientServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));

        $app['config']->set('services.usersau', [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'redirect' => 'http://localhost/auth/usersau/callback',
            'host' => 'http://localhost',
        ]);

        $app['config']->set('usersau', [
            'after_login_url' => '/dashboard',
            'after_logout_url' => '/',
            'after_register_url' => '/welcome',
            'user_model' => TestUser::class,
            'middleware' => ['web'],
            'profile_photo_column' => null,
        ]);
    }

    protected function setUpDatabase()
    {
        // Create a custom users table for testing
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('usersau_id', 36)->unique()->nullable();
            $table->text('usersau_access_token')->nullable();
            $table->text('usersau_refresh_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}

class TestUser extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'usersau_id',
        'usersau_access_token',
        'usersau_refresh_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'usersau_access_token',
        'usersau_refresh_token',
    ];
}
