<?php
namespace Tests;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Unisharp\JWT\Auth\Guards\JWTAuthGuard;

class JWTTest extends TestCase
{
    public function testFoo()
    {
       // $guard = $this->getAuthGuard();
       $this->assertTrue(true);
    }

    protected function getAuthGuard()
    {
        return new JWTAuthGuard(
            m::mock('Tymon\JWTAuth\JWT'),
            m::mock('Illuminate\Contracts\Auth\UserProvider'),
            \Illuminate\Http\Request::create('/', 'GET')
        );
    }
}
