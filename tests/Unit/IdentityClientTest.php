<?php

namespace Usersau\UsersauLaravelClient\Test\Unit;

use Illuminate\Support\Facades\Http;
use Usersau\UsersauLaravelClient\Identity\IdentityClient;
use Usersau\UsersauLaravelClient\Identity\PreferencesException;
use Usersau\UsersauLaravelClient\Identity\UserProfile;
use Usersau\UsersauLaravelClient\Test\TestCase;
use Usersau\UsersauLaravelClient\Test\TestUser;

class IdentityClientTest extends TestCase
{
    private function user(): TestUser
    {
        return TestUser::create(['name' => 'Jane', 'email' => 'jane@example.test', 'usersau_id' => 'subject-1', 'usersau_access_token' => 'access', 'usersau_refresh_token' => 'refresh']);
    }

    private function preferences(int $version = 1): array
    {
        return ['sub' => 'subject-1', 'locale' => 'zh-CN', 'timezone' => 'Australia/Sydney', 'theme' => 'dark', 'version' => $version];
    }

    public function test_standard_profile_is_optional_typed_and_does_not_expose_arbitrary_claims(): void
    {
        $profile = new UserProfile(['sub' => 'subject-1', 'given_name' => 'Jane', 'email_verified' => false, 'updated_at' => 123,
            'password' => 'secret', 'address' => ['locality' => 'Sydney', 'secret' => 'hidden'], UserProfile::PREFERENCES_CLAIM => $this->preferences()]);
        $this->assertSame('Jane', $profile->toArray()['given_name']);
        $this->assertFalse($profile->toArray()['email_verified']);
        $this->assertArrayNotHasKey('password', $profile->toArray());
        $this->assertSame(['locality' => 'Sydney'], $profile->toArray()['address']);
        $this->assertSame(1, $profile->preferences()['version']);
        $this->assertNull((new UserProfile(['sub' => 'different', UserProfile::PREFERENCES_CLAIM => $this->preferences()]))->preferences());
    }

    public function test_malformed_preferences_and_unsafe_profile_urls_are_omitted(): void
    {
        $profile = new UserProfile(['sub' => 'subject-1', 'picture' => 'javascript:alert(1)',
            UserProfile::PREFERENCES_CLAIM => array_merge($this->preferences(), ['locale' => ['invalid']])]);
        $this->assertNull($profile->preferences());
        $this->assertArrayNotHasKey('picture', $profile->toArray());
    }

    public function test_fetch_and_patch_use_the_authenticated_subject_and_whitelisted_fields(): void
    {
        Http::fake(['localhost/api/interface-preferences' => Http::response($this->preferences())]);
        $user = $this->user();
        $client = new IdentityClient();
        $this->assertSame('dark', $client->fetch($user)['theme']);
        $client->update($user, ['theme' => 'dark', 'user_id' => 999, 'email_verified' => true], 0);
        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH' && $request->hasHeader('Authorization', 'Bearer access')
                && $request->data() === ['theme' => 'dark', 'expected_version' => 0];
        });
    }

    public function test_conflict_preserves_current_server_values(): void
    {
        Http::fake(['*' => Http::response(['error' => 'preferences_conflict', 'preferences' => $this->preferences(2)], 409)]);

        try {
            (new IdentityClient())->update($this->user(), ['theme' => 'light'], 0);
            $this->fail('Expected a conflict.');
        } catch (PreferencesException $exception) {
            $this->assertSame(409, $exception->status);
            $this->assertSame(2, $exception->preferences['version']);
        }
    }

    public function test_a_response_for_another_subject_is_rejected(): void
    {
        Http::fake(['*' => Http::response(array_merge($this->preferences(), ['sub' => 'other']))]);
        $this->expectException(PreferencesException::class);
        (new IdentityClient())->fetch($this->user());
    }

    public function test_expired_tokens_refresh_once_without_expanding_scopes(): void
    {
        Http::fake([
            'localhost/api/interface-preferences' => Http::sequence()->push([], 401)->push($this->preferences()),
            'localhost/oauth/token' => Http::response(['access_token' => 'new-access', 'refresh_token' => 'new-refresh']),
        ]);
        $user = $this->user();
        $this->assertSame('dark', (new IdentityClient())->fetch($user)['theme']);
        $this->assertSame('new-access', $user->fresh()->usersau_access_token);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/oauth/token') && !isset($request['scope']));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer new-access'));
    }

    public function test_missing_scope_requires_reauthorization_without_refreshing_or_leaking_response_bodies(): void
    {
        Http::fake(['*' => Http::response(['debug' => 'secret-server-body'], 403)]);

        try {
            (new IdentityClient())->fetch($this->user());
            $this->fail('Expected an authorization failure.');
        } catch (PreferencesException $exception) {
            $this->assertSame(403, $exception->status);
            $this->assertSame('preferences_reauthorization_required', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }
}
