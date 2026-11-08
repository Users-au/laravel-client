<?php

return [
    'par_enabled' => true,
    'context_provider' => \Usersau\UsersauLaravelClient\OAuth\ConfiguredAuthContextProvider::class,
    'context' => [
        'context_id' => null,
        'name' => null,
        'logo_url' => null,
        'logo_dark_url' => null,
        'favicon_url' => null,
        'theme_color' => null,
    ],
    'routes_enabled' => true,
    'scopes' => ['openid', 'profile', 'email', 'preferences:read', 'preferences:write'],
    'after_login_url' => '/',
    'after_logout_url' => '/',
    'after_register_url' => '/',
    'user_model' => App\Models\User::class,
    'middleware' => ['web'],
    'profile_photo_column' => null,
];
