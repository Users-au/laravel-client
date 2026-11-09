<?php

namespace Usersau\UsersauLaravelClient\OAuth;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Usersau\UsersauLaravelClient\Contracts\AuthContextProvider;

class PushedAuthorizationClient
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function authorizationUrl(array $parameters, AuthContextProvider $provider): string
    {
        return $this->push('/oauth/par', '/oauth/authorize', [
            'response_type' => 'code',
            'scope' => $parameters['scope'] ?? 'openid profile email',
            'redirect_uri' => $parameters['redirect_uri'],
            'state' => $parameters['state'],
            'code_challenge' => $parameters['code_challenge'],
            'code_challenge_method' => 'S256',
        ], $provider);
    }

    public function accountUrl(AuthContextProvider $provider): string
    {
        return $this->push('/oauth/context', '/account', ['purpose' => 'account'], $provider);
    }

    public function logoutUrl(AuthContextProvider $provider): string
    {
        return $this->push('/oauth/context', '/logout', ['purpose' => 'logout'], $provider);
    }

    private function push(string $endpoint, string $entry, array $parameters, AuthContextProvider $provider): string
    {
        $host = rtrim($this->config['host'] ?? '', '/');
        if (parse_url($host, PHP_URL_SCHEME) !== 'https' || !filter_var($host, FILTER_VALIDATE_URL)
            || parse_url($host, PHP_URL_USER) || parse_url($host, PHP_URL_QUERY)
            || parse_url($host, PHP_URL_FRAGMENT) || parse_url($host, PHP_URL_PATH)
            || empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            throw new RuntimeException('A valid HTTPS Users.au origin and confidential client credentials are required.');
        }

        try {
            $response = Http::asForm()->acceptJson()->withBasicAuth($this->config['client_id'], $this->config['client_secret'])
                ->timeout(15)->withoutRedirecting()->post($host . $endpoint, $parameters + [
                    'client_id' => $this->config['client_id'],
                    'context' => json_encode($provider->context(), JSON_THROW_ON_ERROR),
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            throw new RuntimeException('Users.au is temporarily unavailable.');
        }
        // Do not attach response bodies or credentials to errors sent to the application.
        $uri = $response->json('request_uri');
        if ($response->status() !== 201 || !is_string($uri)
            || !preg_match('/^urn:ietf:params:oauth:request_uri:[A-Za-z0-9_-]{43,128}$/D', $uri)) {
            throw new RuntimeException('Users.au rejected the pushed authorization request.');
        }

        return $host . $entry . '?' . http_build_query([
            'client_id' => $this->config['client_id'], 'request_uri' => $uri,
        ]);
    }
}
