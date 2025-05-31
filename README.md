# Users.au OAuth Laravel Client

[![Latest Stable Version](https://poser.pugx.org/users-au/laravel-client/v/stable.svg)](https://packagist.org/packages/users-au/laravel-client)
[![License](https://poser.pugx.org/users-au/laravel-client/license.svg)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/users-au/laravel-client.svg?style=flat-square)](https://packagist.org/packages/users-au/laravel-client)
[![Tests](https://github.com/users-au/laravel-client/workflows/Tests/badge.svg)](https://github.com/users-au/laravel-client/actions)

## Table of Contents

- [Introduction](#introduction)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Reference](#api-reference)
- [Migration Guide](#migration-guide)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [Changelog](#changelog)
- [License](#license)

## Introduction

This package provides a seamless Laravel integration for Users.au OAuth authentication. It simplifies the process of implementing Users.au single sign-on (SSO) in your Laravel applications, handling authentication, user management, and session control.

## Features

- 🔐 **OAuth 2.0 Authentication** - Secure authentication via Users.au
- 👤 **Automatic User Management** - Creates and updates user records automatically
- 🔄 **Token Management** - Handles access and refresh tokens
- 🎨 **Profile Photo Support** - Optional profile photo synchronization
- 🛡️ **Middleware Protection** - Configurable middleware for route protection
- 📱 **Account Management** - Direct integration with Users.au account pages
- 🚪 **Single Sign-Out** - Coordinated logout across systems

## Requirements

- [PHP](https://php.net) >= 8.0
- [Composer](https://getcomposer.org) >= 2.0
- Laravel 5.0+ (supports versions 5.x through 10.x)
- Users.au OAuth application credentials

## Installation

Install the package via Composer:

```bash
composer require users-au/laravel-client
```

Publish the package assets:

```bash
php artisan vendor:publish --provider="Usersau\UsersauLaravelClient\UsersauLaravelClientServiceProvider"
```

Run the migrations to add required columns to your users table:

```bash
php artisan migrate
```

## Configuration

### 1. Update Your User Model

Add the following fields to your User model's `fillable` and `hidden` arrays:

```php
// app/Models/User.php

protected $fillable = [
    // ... existing fields
    'usersau_id',
    'usersau_access_token',
    'usersau_refresh_token',
];

protected $hidden = [
    // ... existing fields
    'usersau_id',
    'usersau_access_token',
    'usersau_refresh_token',
];
```

### 2. Service Configuration

Add Users.au configuration to your `config/services.php`:

```php
'usersau' => [    
    'client_id' => env('USERSAU_CLIENT_ID'),  
    'client_secret' => env('USERSAU_CLIENT_SECRET'),  
    'redirect' => env('USERSAU_REDIRECT_URI'),
    'host' => env('USERSAU_HOST'),
],
```

### 3. Environment Variables

Set the following environment variables in your `.env` file:

```env
USERSAU_CLIENT_ID="your_client_id"
USERSAU_CLIENT_SECRET="your_client_secret"
USERSAU_REDIRECT_URI="https://yourdomain.com/auth/usersau/callback"
USERSAU_HOST="https://auth.yourdomain.com"
```

### 4. Package Configuration

The package publishes a configuration file at `config/usersau.php`. You can customize:

```php
return [
    'after_login_url' => '/',              // Redirect after successful login
    'after_logout_url' => '/',             // Redirect after logout
    'after_register_url' => '/',           // Redirect after registration
    'user_model' => App\Models\User::class, // Your user model
    'middleware' => ['web'],               // Middleware for auth routes
    'profile_photo_column' => null,        // Column name for profile photos (optional)
];
```

### 5. Manual Service Provider Registration (Optional)

If auto-discovery is disabled, manually register the service provider in `config/app.php`:

```php
'providers' => [
    // ... other providers
    Usersau\UsersauLaravelClient\UsersauLaravelClientServiceProvider::class,
],
```

## Usage

### Authentication Routes

The package automatically registers the following routes:

| Route | Name | Description |
|-------|------|-------------|
| `GET /auth/usersau/redirect` | `usersau.login` | Initiates OAuth flow |
| `GET /auth/usersau/callback` | - | OAuth callback handler |
| `GET /auth/usersau/logout` | `usersau.logout` | Logout and redirect to Users.au |
| `GET /auth/usersau/register` | `usersau.register` | Redirect to Users.au registration |
| `GET /auth/usersau/account` | `usersau.account` | Redirect to Users.au account page |

### Basic Usage Examples

#### Login Link

```php
// In your Blade template
<a href="{{ route('usersau.login') }}" class="btn btn-primary">
    Login with Users.au
</a>
```

#### Logout Link

```php
<a href="{{ route('usersau.logout') }}" class="btn btn-secondary">
    Logout
</a>
```

#### Registration Link

```php
<a href="{{ route('usersau.register') }}" class="btn btn-success">
    Register with Users.au
</a>
```

#### Account Management Link

```php
@auth
<a href="{{ route('usersau.account') }}" class="btn btn-info">
    Manage Account
</a>
@endauth
```

### Middleware Protection

Protect your routes using Laravel's built-in auth middleware:

```php
// In your routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
});
```

### Custom User Handling

You can listen for authentication events to perform custom actions:

```php
// In a service provider
Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
    // Custom logic after user login
    $user = $event->user;
    
    // Log login activity
    activity()
        ->performedOn($user)
        ->log('User logged in via Users.au');
});
```

## API Reference

### AuthController Methods

The `AuthController` provides the following public methods:

#### `redirect()`
Initiates the OAuth flow by redirecting to Users.au.

#### `callback()`
Handles the OAuth callback, creates/updates user records, and logs in the user.

**Process:**
1. Retrieves user data from Users.au
2. Creates or updates local user record
3. Syncs profile photo (if configured)
4. Logs in the user
5. Redirects to configured URL

#### `logout()`
Logs out the user locally and redirects to Users.au logout.

#### `account()`
Redirects authenticated users to their Users.au account page.

#### `register()`
Redirects to Users.au registration page.

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `after_login_url` | string | `'/'` | URL to redirect after login |
| `after_logout_url` | string | `'/'` | URL to redirect after logout |
| `after_register_url` | string | `'/'` | URL to redirect after registration |
| `user_model` | string | `App\Models\User::class` | User model class |
| `middleware` | array | `['web']` | Middleware for auth routes |
| `profile_photo_column` | string|null | `null` | Database column for profile photos |

## Migration Guide

### From Version 1.x to 2.x

If you're upgrading from an older version:

1. Update your composer requirements
2. Run `php artisan vendor:publish --provider="Usersau\UsersauLaravelClient\UsersauLaravelClientServiceProvider" --force`
3. Run `php artisan migrate`
4. Update your environment variables if needed

## Troubleshooting

### Common Issues

#### "Invalid State Exception"
This usually occurs when the OAuth state parameter doesn't match. Common causes:
- Session configuration issues
- Multiple redirect attempts
- Browser security settings

**Solution:** Ensure your session driver is properly configured and cookies are enabled.

#### "Client Exception during OAuth"
This indicates communication issues with Users.au servers.

**Solutions:**
- Verify your `USERSAU_CLIENT_ID` and `USERSAU_CLIENT_SECRET`
- Check your `USERSAU_REDIRECT_URI` matches exactly what's configured in Users.au
- Ensure `USERSAU_HOST` is correct

#### "User Model Not Found"
The configured user model doesn't exist.

**Solution:** Verify the `user_model` in `config/usersau.php` points to your correct User model.

#### Migration Errors
Issues running the package migrations.

**Solutions:**
- Ensure your users table exists before running migrations
- Check for conflicting column names
- Verify database connection

### Debug Mode

Enable debug mode in your `.env` for detailed error messages:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Support

For additional support:
- Check the [GitHub Issues](https://github.com/users-au/laravel-client/issues)
- Review the [Users.au Documentation](https://www.users.au/docs)
- Contact support at [support@users.au](mailto:support@users.au)

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `composer test`
4. Follow PSR-12 coding standards

### Testing

Run the test suite:

```bash
composer test
```

For coverage reports:

```bash
vendor/bin/phpunit --coverage-html coverage
```

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.

