<?php

namespace Usersau\UsersauLaravelClient\Test\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Usersau\UsersauLaravelClient\Events\UserProfileSynchronized;
use Usersau\UsersauLaravelClient\Test\TestCase;

class IdentityProfileCallbackTest extends TestCase
{
    public function test_callback_emits_full_claims_with_a_standard_sub_without_legacy_id(): void
    {
        Event::fake([UserProfileSynchronized::class]);
        $profile = Mockery::mock();
        $profile->shouldReceive('getId')->andReturn(null);
        $profile->shouldReceive('getName')->andReturn('Jane');
        $profile->shouldReceive('getEmail')->andReturn('jane@example.test');
        $profile->token = 'access';
        $profile->refreshToken = 'refresh';
        $profile->user = ['sub' => 'standard-sub', 'locale' => 'zh-CN', 'zoneinfo' => 'Australia/Sydney', 'given_name' => 'Jane'];
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($profile);
        Socialite::shouldReceive('driver')->with('usersau')->andReturn($driver);
        Auth::shouldReceive('login')->once();
        $this->get('/auth/usersau/callback')->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['usersau_id' => 'standard-sub']);
        Event::assertDispatched(UserProfileSynchronized::class, fn ($event) => $event->profile->toArray()['locale'] === 'zh-CN' && $event->profile->subject() === 'standard-sub');
    }

    public function test_subject_mismatch_is_rejected_before_the_local_user_is_written(): void
    {
        $profile = Mockery::mock();
        $profile->shouldReceive('getId')->andReturn('legacy-id');
        $profile->user = ['sub' => 'different-sub'];
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($profile);
        Socialite::shouldReceive('driver')->with('usersau')->andReturn($driver);
        Auth::shouldReceive('login')->never();
        $this->get('/auth/usersau/callback')->assertStatus(502);
        $this->assertDatabaseCount('users', 0);
    }
    public function test_malformed_provider_profiles_are_rejected_before_any_local_account_write(): void
    {
        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->once()->andThrow(new \UnexpectedValueException('upstream-private-detail'));
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('usersau')->once()->andReturn($provider);
        $this->get('/auth/usersau/callback')->assertStatus(502)->assertDontSee('upstream-private-detail');
        $this->assertDatabaseCount('users', 0);
    }
}
