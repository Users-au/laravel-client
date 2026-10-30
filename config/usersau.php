<?php

return [
    'routes_enabled' => true,
    'scopes' => ['openid', 'profile', 'email', 'preferences:read', 'preferences:write'],
    'after_login_url' => '/',
    'after_logout_url' => '/',
    'after_register_url' => '/',
    'user_model' => App\Models\User::class,
    'middleware' => ['web'],
    'profile_photo_column' => null,
];
