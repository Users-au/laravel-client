<?php

namespace Usersau\UsersauLaravelClient\OAuth;

use Usersau\UsersauLaravelClient\Contracts\AuthContextProvider;

class ConfiguredAuthContextProvider implements AuthContextProvider
{
    public function context(): array
    {
        // Derive these from trusted application configuration, never browser input or Referer.
        $home = rtrim((string) config('app.url'), '/') . '/';

        return [
            'context_id' => config('usersau.context.context_id') ?: $home,
            'branding' => array_filter([
                'name' => config('usersau.context.name') ?: config('app.name'),
                'logo_url' => config('usersau.context.logo_url'),
                'logo_dark_url' => config('usersau.context.logo_dark_url'),
                'favicon_url' => config('usersau.context.favicon_url'),
                'theme_color' => config('usersau.context.theme_color'),
            ], fn ($value) => $value !== null && $value !== ''),
            'links' => [
                'home_url' => $home,
                'logout_url' => filter_var(config('usersau.after_logout_url'), FILTER_VALIDATE_URL)
                    ? config('usersau.after_logout_url')
                    : $home . ltrim(config('usersau.after_logout_url', '/'), '/'),
            ],
        ];
    }
}
