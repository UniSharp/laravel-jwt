Laravel JWT
==========
![php-badge](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)
[![packagist-badge](https://img.shields.io/packagist/v/unisharp/laravel-jwt.svg)](https://packagist.org/packages/unisharp/laravel-jwt)

## Approach

If you pick `Tymon JWTAuth` as your jwt solution in your project, when you try to refresh your token, the package will blacklist your exchanged token (assume your blacklist feature is enabled). So when your client faces a concurrency use case,  your request might be rejected because that request is sent before your app renews jwt token returned by server. This package caches the refreshed jwt token in a short period to ensure your client side can get correct response even if your request carries an old token in a concurrency case.

## Installation

* Via Composer
```
composer require unisharp/laravel-jwt
```

* Add the Service Provider

```php
Tymon\JWTAuth\Providers\LaravelServiceProvider::class,
Unisharp\JWT\JWTServiceProvider::class,
```

> In Lumen please use `Tymon\JWTAuth\Providers\LumenServiceProvider::class,`

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
$ php artisan jwt:secret
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
> This middleware will automatically refresh jwt token if the existing one has been expired. The new refreshed jwt token will be carried to the response header: `Ahthorization`. The client side needs to replace your expired jwt token with the new one. 

**Note:** The above example assumes you've setup a guard with the name `api` whose driver is `jwt-auth` in your `config/auth.php` file as explained in "Setup Guard Driver" section above.

> The following usage examples assume you've setup your default auth guard to the one which uses the `jwt-auth` driver.
>
> You can also explicitly define the guard before making calls to any of methods by just prefixing it with `Auth::guard('api')`. 
>
> Example: `Auth::guard('api')->user()`

### Attempt To Authenticate And Return Token

``` php
// This will attempt to authenticate the user using the credentials passed and returns a JWT Auth Token for subsequent requests.
$token = Auth::attempt(['email' => 'user@domain.com', 'password' => '123456']);
```

### Authenticate Once By ID

``` php
if(Auth::onceUsingId(1)) {
    // Do something with the authenticated user
}
```

### Authenticate Once By Credentials

``` php
if(Auth::once(['email' => 'user@domain.com', 'password' => '123456'])) {
    // Do something with the authenticated user
}
```

### Validate Credentials

``` php
if(Auth::validate(['email' => 'user@domain.com', 'password' => '123456'])) {
    // Credentials are valid
}
```

### Check User is Authenticated

``` php
if(Auth::check()) {
    // User is authenticated
}
```

### Check User is a Guest

``` php
if(Auth::guest()) {
    // Welcome guests!
}
```

### Logout Authenticated User

``` php
Auth::logout(); // This will invalidate the current token and unset user/token values.
```

### Generate JWT Auth Token By ID
   
``` php
$token = Auth::generateTokenById(1);

echo $token;
```

### Get Authenticated User

Once the user is authenticated via a middleware, You can access its details by doing:

``` php
$user = Auth::user();
```

You can also manually access user info using the token itself:

``` php
$user = Auth::setToken('YourJWTAuthToken')->user();
```

### Get Authenticated User's ID

``` php
$userId = Auth::id();
```

### Refresh Expired Token

Though it's recommended you refresh using the middlewares provided with the package,
but if you'd like, You can also do it manually with this method.

Refresh expired token passed in request:

``` php
$token = Auth::refresh();
```

Refresh passed expired token:

``` php
Auth::setToken('ExpiredToken')->refresh();
```

### Invalidate Token

Invalidate token passed in request:

``` php
$forceForever = false;
Auth::invalidate($forceForever);
```

Invalidate token by setting one manually:

``` php
$forceForever = false;
Auth::setToken('TokenToInvalidate')->invalidate($forceForever);
```

### Get Token

``` php
$token = Auth::getToken(); // Returns current token passed in request.
```

### Get Token Payload

This method will decode the token and return its raw payload.

Get Payload for the token passed in request:

``` php
$payload = Auth::getPayload();
```

Get Payload for the given token manually:

``` php
$payload = Auth::setToken('TokenToGetPayload')->getPayload();
```
