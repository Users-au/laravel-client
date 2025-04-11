# Users.au Oauth Laravel Client

[![Latest Stable Version](https://poser.pugx.org/users-au/users-au-laravel-client/v/stable.svg)](https://packagist.org/packages/users-au/users-au-laravel-client)
[![License](https://poser.pugx.org/users-au/users-au-laravel-client/license.svg)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/users-au/users-au-laravel-client.svg?style=flat-square)](https://packagist.org/packages/users-au/users-au-laravel-client)

## Introduction

This package is a Laravel client for Users.au oauth.
It provides a simple way to integrate your Laravel application to log in with Users.au.

## Installation

Requirements:
- [PHP](https://php.net) >= 8
- [Composer](https://getcomposer.org) >= 2

To get the latest version, simply run:

```
composer require users-au/laravel-client
```

Then do vendor publish:

```
php artisan vendor:publish --provider="Usersau\UsersauLaravelClient\UsersauLaravelClientServiceProvider"
```

Modify user model:

```php
$this->fillable = [
    ...
    'usersau_id',
    'usersau_access_token',
    'usersau_refresh_token',
];
$this->hidden = [
    ...
    'usersau_id',
    'usersau_access_token',
    'usersau_refresh_token',
];
```

Add configuration to `config/services.php`

```php
'usersau' => [    
  'client_id' => env('USERSAU_CLIENT_ID'),  
  'client_secret' => env('USERSAU_CLIENT_SECRET'),  
  'redirect' => env('USERSAU_REDIRECT_URI'),
  'host' => env('USERSAU_HOST'),
],
```

If you need to register the service provider manually. Open `config/app.php` and add the following to the `providers` array:

```php
Usersau\UsersauLaravelClient\UsersauLaravelClientServiceProvider::class,
```

Run the migrations:

```php
php artisan migrate
```

## Configuration

### Users.au Client ID and Secret

You will need to register your application with Users.au to receive a Client ID and Client Secret.
You can do this at [https://www.users.au](https://www.users.au).

### Environment variables

You can set the following environment variables in your `.env` file:

```
USERSAU_CLIENT_ID="your_client_id"
USERSAU_CLIENT_SECRET="your_client_secret"
USERSAU_REDIRECT_URI="https://www.yourdomain.com/auth/usersau/callback"
USERSAU_HOST="https://auth.youdomain.com"
```

## TODO

- [x] Add tests

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please feel free to fork this package and contribute by submitting a pull request to enhance the functionalities.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
