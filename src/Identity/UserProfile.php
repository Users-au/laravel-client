<?php

namespace Usersau\UsersauLaravelClient\Identity;

use InvalidArgumentException;

class UserProfile
{
    public const PREFERENCES_CLAIM = 'urn:usersau:preferences';
    private array $claims;

    public function __construct(array $claims)
    {
        if (!isset($claims['sub']) || !is_string($claims['sub']) || $claims['sub'] === '') {
            throw new InvalidArgumentException('A profile subject is required.');
        }
        $this->claims = ['sub' => $claims['sub']];
        foreach (['name', 'given_name', 'family_name', 'middle_name', 'nickname', 'preferred_username', 'profile', 'picture', 'website', 'gender', 'birthdate', 'zoneinfo', 'locale', 'email', 'phone_number'] as $key) {
            if (isset($claims[$key]) && is_string($claims[$key]) && $claims[$key] !== '') {
                $this->claims[$key] = $claims[$key];
            }
        }
        foreach (['profile', 'picture', 'website'] as $key) {
            if (isset($this->claims[$key]) && (!filter_var($this->claims[$key], FILTER_VALIDATE_URL)
                || !in_array(parse_url($this->claims[$key], PHP_URL_SCHEME), ['https', 'http'], true))) {
                unset($this->claims[$key]);
            }
        }
        foreach (['email_verified', 'phone_number_verified'] as $key) {
            if (isset($claims[$key]) && is_bool($claims[$key])) {
                $this->claims[$key] = $claims[$key];
            }
        }
        if (isset($claims['updated_at']) && is_int($claims['updated_at'])) {
            $this->claims['updated_at'] = $claims['updated_at'];
        }
        if (isset($claims['address']) && is_array($claims['address'])) {
            $this->claims['address'] = array_filter(array_intersect_key($claims['address'], array_flip(['formatted', 'street_address', 'locality', 'region', 'postal_code', 'country'])), 'is_string');
        }
        if (isset($claims[self::PREFERENCES_CLAIM]) && is_array($claims[self::PREFERENCES_CLAIM])) {
            $preferences = $claims[self::PREFERENCES_CLAIM];
            if (($preferences['sub'] ?? null) === $claims['sub'] && is_int($preferences['version'] ?? null) && $preferences['version'] >= 0
                && in_array($preferences['theme'] ?? null, ['light', 'dark', 'system'], true)
                && (!isset($preferences['locale']) || (is_string($preferences['locale']) && preg_match('/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/D', $preferences['locale'])))
                && (!isset($preferences['timezone']) || (is_string($preferences['timezone']) && in_array($preferences['timezone'], \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)))) {
                $this->claims[self::PREFERENCES_CLAIM] = array_intersect_key($preferences, array_flip(['sub', 'locale', 'timezone', 'theme', 'version']));
            }
        }
    }

    public function subject(): string
    {
        return $this->claims['sub'];
    }
    public function toArray(): array
    {
        return $this->claims;
    }
    public function preferences(): ?array
    {
        return $this->claims[self::PREFERENCES_CLAIM] ?? null;
    }
}
