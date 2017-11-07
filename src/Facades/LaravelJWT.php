<?php

namespace Unisharp\JWT\Facades;

use Illuminate\Support\Facades\Facade;

class LaravelJWT extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'uni.jwt';
    }
}