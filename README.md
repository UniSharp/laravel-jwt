Laravel JWT
==========
![php-badge](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)
[![packagist-badge](https://img.shields.io/packagist/v/unisharp/laravel-jwt.svg)](https://packagist.org/packages/unisharp/laravel-jwt)

## Installation

* Via Composer
```
composer require unisharp/laravel-jwt
```

* Add the Service Provider

```php
Tymon\JWTAuth\Providers\JWTAuthServiceProvider::class,
Unisharp\JWT\JWTServiceProvider::class,
```

Next, also in the app.php config file, under the aliases array, you may want to add the JWTAuth facade.

```
'JWTAuth' => 'Tymon\JWTAuth\Facades\JWTAuth',
'JWTFactory' => 'Tymon\JWTAuth\Facades\JWTFactory'
```

Finally, you will want to publish the config using the following command:

```
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\JWTAuthServiceProvider"
php artisan vendor:publish --provider="Unisharp\JWT\JWTServiceProvider"
```

Don't forget to set a secret key in the config file!

```
$ php artisan jwt:generate
```

this will generate a new random key, which will be used to sign your tokens.

And you're done!

## Usage

Open your `config/auth.php` config file and in place of driver under any of your guards, just add the `jwt-auth` as your driver and you're all set.
Make sure you also set `provider` for the guard to communicate with your database.

### Setup Guard Driver

``` php
// config/auth.php
'guards' => [
    'api' => [
        'driver' => 'jwt-auth',
        'provider' => 'users'
    ],
    
    // ...
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model'  => App\User::class,
    ],
],
```

### Middleware Usage

Middleware protecting the route:

``` php
Route::get('api/content', ['middleware' => 'laravel.jwt', 'uses' => 'ContentController@content']);
```

Middleware protecting the controller:

``` php
<?php

namespace App\Http\Controllers;

class ContentController extends Controller
{
    public function __construct() 
    {
        $this->middleware('laravel.jwt');
    }
}
```
