<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => config('usersau.middleware', ['web'])
],
    function () {
        Route::get('/auth/usersau/redirect', [
            \Usersau\UsersauLaravelClient\Http\Controllers\AuthController::class,
            'redirect'
        ])->name('login');

        Route::get('/auth/usersau/register', [
            \Usersau\UsersauLaravelClient\Http\Controllers\AuthController::class,
            'register'
        ])->name('register');

        Route::get('/auth/usersau/callback', [
            \Usersau\UsersauLaravelClient\Http\Controllers\AuthController::class,
            'callback'
        ]);

        Route::get('/auth/usersau/logout', [
            \Usersau\UsersauLaravelClient\Http\Controllers\AuthController::class,
            'logout'
        ])->name('logout');

        Route::get('/auth/usersau/account', [
            \Usersau\UsersauLaravelClient\Http\Controllers\AuthController::class,
            'account'
        ])->name('account');
    }
);