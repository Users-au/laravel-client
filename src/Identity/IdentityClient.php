<?php

namespace Usersau\UsersauLaravelClient\Identity;

use Illuminate\Support\Facades\Http;
use Throwable;

class IdentityClient
{
    public function issuer(): string
    {
        $host = rtrim((string) config('services.usersau.host'), '/');
        $scheme = parse_url($host, PHP_URL_SCHEME);
        if (!filter_var($host, FILTER_VALIDATE_URL) || parse_url($host, PHP_URL_USER) || parse_url($host, PHP_URL_QUERY) || parse_url($host, PHP_URL_FRAGMENT)
            || ($scheme !== 'https' && !($scheme === 'http' && app()->environment('local', 'testing')))) {
            throw new PreferencesException(503);
        }

        return $host;
    }

    public function fetch($user): array
    {
        return $this->request($user, 'get');
    }

    public function update($user, array $changes, int $expectedVersion): array
    {
        return $this->request($user, 'patch', array_merge(array_intersect_key($changes, array_flip(['locale', 'timezone', 'theme'])), ['expected_version' => $expectedVersion]));
    }

    private function request($user, string $method, array $payload = []): array
    {
        $host = $this->issuer();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if (!$user->usersau_access_token || !$user->usersau_id) {
                throw new PreferencesException(401);
            }

            try {
                $response = Http::acceptJson()->withToken($user->usersau_access_token)->timeout(5)->connectTimeout(2)
                    ->withoutRedirecting()->{$method}($host . '/api/interface-preferences', $payload);
            } catch (Throwable $exception) {
                throw new PreferencesException(503);
            }
            if ($response->status() === 401 && $attempt === 0 && $user->usersau_refresh_token) {
                $this->refresh($user);

                continue;
            }
            if ($response->status() === 409) {
                throw new PreferencesException(409, $this->validate($response->json('preferences'), $user));
            }
            if (!$response->successful()) {
                throw new PreferencesException(in_array($response->status(), [401, 403], true) ? $response->status() : 503);
            }

            return $this->validate($response->json(), $user);
        }

        throw new PreferencesException(401);
    }

    private function validate($data, $user): array
    {
        if (!is_array($data) || ($data['sub'] ?? null) !== (string) $user->usersau_id
            || !is_int($data['version'] ?? null) || $data['version'] < 0
            || !in_array($data['theme'] ?? null, ['system', 'light', 'dark'], true)
            || (isset($data['locale']) && (!is_string($data['locale']) || !preg_match('/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/D', $data['locale'])))
            || (isset($data['timezone']) && (!is_string($data['timezone']) || !in_array($data['timezone'], \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)))) {
            throw new PreferencesException(502);
        }

        return array_intersect_key($data, array_flip(['sub', 'locale', 'timezone', 'theme', 'version']));
    }

    private function refresh($user): void
    {
        $original = $user->usersau_access_token;
        $user->getConnection()->transaction(function () use ($user, $original): void {
            $current = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if ($current->usersau_access_token !== $original) {
                $user->setRawAttributes($current->getAttributes(), true);

                return;
            }

            try {
                $response = Http::asForm()->acceptJson()->timeout(5)->connectTimeout(2)->withoutRedirecting()->post($this->issuer() . '/oauth/token', [
                    'grant_type' => 'refresh_token', 'refresh_token' => $current->usersau_refresh_token,
                    'client_id' => config('services.usersau.client_id'), 'client_secret' => config('services.usersau.client_secret'),
                ]);
            } catch (Throwable $exception) {
                throw new PreferencesException(503);
            }
            if (!$response->successful() || !is_string($response->json('access_token')) || $response->json('access_token') === '') {
                throw new PreferencesException(401);
            }
            $current->forceFill([
                'usersau_access_token' => $response->json('access_token'),
                'usersau_refresh_token' => is_string($response->json('refresh_token')) ? $response->json('refresh_token') : $current->usersau_refresh_token,
            ])->save();
            $user->setRawAttributes($current->getAttributes(), true);
        });
    }
}
