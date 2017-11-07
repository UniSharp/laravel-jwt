<?php

namespace Unisharp\JWT;

use Illuminate\Support\ServiceProvider;

class JWTServiceProvider extends ServiceProvider
{
    protected $configs;

    /**
     * Boot the services for the application.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootConfig();
        $this->loadConfig();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('uni.jwt', function ($app) {
            $configs = $this->configs;

            return;
        });
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
     * Load configure.
     *
     * @return void
     */
    protected function loadConfig($configs = [])
    {
        $this->configs = $configs ?: config('laravel_jwt');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['uni.jwt'];
    }
}
