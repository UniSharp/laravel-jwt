<?php

namespace Unisharp\JWT;

use Illuminate\Support\ServiceProvider;
use Unisharp\JWT\Auth\Guards\JWTAuthGuard;
use Unisharp\JWT\Http\Middleware\JWTRefresh;

class JWTServiceProvider extends ServiceProvider
{
    protected $configs;

    /**
     * The middleware aliases.
     *
     * @var array
     */
    protected $middlewareAliases = [
        'laravel.jwt' => JWTRefresh::class,
    ];

    /**
     * Boot the services for the application.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootConfig();
        $this->loadConfig();
        $this->extendAuthGuard();
        $this->registerMiddleware();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot configure.
     *
     * @return void
     */
    protected function bootConfig()
    {
        $path = __DIR__ . '/config/laravel_jwt.php';
        $this->mergeConfigFrom($path, 'laravel_jwt');
        if (function_exists('config_path')) {
            $this->publishes([$path => config_path('laravel_jwt.php')]);
        }
    }

    /**
     * Register middleware.
     *
     * @return void
     */
    protected function registerMiddleware()
    {
        if ($this->isLumen()) {
            $this->app->routeMiddleware($this->middlewareAliases);
        } else {
            $this->aliasMiddleware();
        }
    }

    /**
     * Load configure.
     *
     * @return void
     */
    protected function loadConfig($configs = [])
    {
        $this->configs = $configs ?: config('laravel_jwt');
    }

    /**
     * Extend auth guard.
     *
     * @return void
     */
    protected function extendAuthGuard()
    {
        $this->app['auth']->extend('jwt-auth', function ($app, $name, array $config) {
            $guard = new JWTAuthGuard(
                $app['tymon.jwt'],
                $app['auth']->createUserProvider($config['provider']),
                $app['request']
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }

    /**
     * Return isLumen.
     *
     * @return boolean
     */
    protected function isLumen()
    {
        return str_contains($this->app->version(), 'Lumen');
    }

    /**
     * Alias the middleware.
     *
     * @return void
     */
    protected function aliasMiddleware()
    {
        $router = $this->app['router'];
        $method = method_exists($router, 'aliasMiddleware') ? 'aliasMiddleware' : 'middleware';
        foreach ($this->middlewareAliases as $alias => $middleware) {
            $router->$method($alias, $middleware);
        }
    }
}
