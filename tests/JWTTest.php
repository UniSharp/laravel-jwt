<?php
namespace Tests;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Unisharp\JWT\Auth\Guards\JWTAuthGuard;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class JWTTest extends TestCase
{
    public function testRequireToken()
    {
        $jwt = m::mock('Tymon\JWTAuth\JWT');
        $jwt->shouldReceive('setRequest')
            ->once()
            ->andReturn($jwt);
        $jwt->shouldReceive('getToken')
            ->once()
            ->andReturn(false);

        $guard = $this->getAuthGuard($jwt);
        $this->expectException(JWTException::class);
        $guard->requireToken();
    }

    public function testGetToken()
    {
        $jwt = m::mock('Tymon\JWTAuth\JWT');
        $jwt->shouldReceive('setRequest')
            ->once()
            ->andReturn($jwt);
        $jwt->shouldReceive('getToken')
            ->once()
            ->andReturn('token');

        $guard = $this->getAuthGuard($jwt);
        $this->assertEquals($guard->getToken(), 'token');
    }

    protected function getAuthGuard($jwt = null, $provider = null)
    {
        return new JWTAuthGuard(
            $jwt ?: m::mock('Tymon\JWTAuth\JWT'),
            $provider ?: m::mock('Illuminate\Contracts\Auth\UserProvider'),
            \Illuminate\Http\Request::create('/', 'GET')
        );
    }
}
