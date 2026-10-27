<?php

namespace Usersau\UsersauLaravelClient\Test\Unit;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Usersau\UsersauLaravelClient\Contracts\AuthContextProvider;
use Usersau\UsersauLaravelClient\OAuth\PushedAuthorizationClient;
use Usersau\UsersauLaravelClient\Test\TestCase;

class PushedAuthorizationClientTest extends TestCase
{
    public function test_context_and_credentials_travel_only_over_the_server_request(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://shopper.users.au/oauth/par' => Http::response([
            'request_uri' => 'urn:ietf:params:oauth:request_uri:' . str_repeat('x', 64), 'expires_in' => 300,
        ], 201)]);
        $url = $this->client()->authorizationUrl($this->parameters(), $this->provider());
        $this->assertStringStartsWith('https://shopper.users.au/oauth/authorize?', $url);
        $this->assertStringNotContainsString('secret', $url);
        $this->assertStringNotContainsString('Shop A', $url);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic ' . base64_encode('client:secret'))
            && json_decode($request['context'], true)['branding']['name'] === 'Shop A'
            && $request['redirect_uri'] === 'https://shop.example/callback');
    }

    public function test_provider_failures_do_not_include_sensitive_response_bodies(): void
    {
        Http::fake(['*' => Http::response(['error' => 'private-secret-response'], 400)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Users.au rejected the pushed authorization request.');
        $this->client()->authorizationUrl($this->parameters(), $this->provider());
    }

    public function test_invalid_provider_origin_is_rejected_before_sending_credentials(): void
    {
        Http::preventStrayRequests();
        $this->expectException(RuntimeException::class);
        (new PushedAuthorizationClient(['host' => 'http://attacker.test', 'client_id' => 'client', 'client_secret' => 'secret']))
            ->authorizationUrl($this->parameters(), $this->provider());
    }

    private function client(): PushedAuthorizationClient
    {
        return new PushedAuthorizationClient(['host' => 'https://shopper.users.au', 'client_id' => 'client', 'client_secret' => 'secret']);
    }

    private function parameters(): array
    {
        return ['redirect_uri' => 'https://shop.example/callback', 'state' => 'state', 'code_challenge' => 'challenge'];
    }

    private function provider(): AuthContextProvider
    {
        return new class implements AuthContextProvider {
            public function context(): array
            {
                return ['store_id' => 'a', 'branding' => ['name' => 'Shop A'], 'links' => ['home_url' => 'https://shop.example']];
            }
        };
    }
}
