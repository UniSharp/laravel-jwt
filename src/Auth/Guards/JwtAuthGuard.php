<?php

namespace Unisharp\JWT\Auth\Guards;

use Tymon\JWTAuth\JWT;
use Tymon\JWTAuth\JWTGuard;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Auth\UserProvider;
use Carbon\Carbon;

class JWTAuthGuard extends JWTGuard
{
    protected $cachedToken;

    /**
     * Create a new authentication guard.
     *
     * @param \Tymon\JWTAuth\JWT                      $jwt
     * @param \Illuminate\Contracts\Auth\UserProvider $provider
     * @param \Illuminate\Http\Request                $request
     */
    public function __construct(JWT $jwt, UserProvider $provider, Request $request)
    {
        parent::__construct($jwt, $provider, $request);
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $token = $this->jwt->setRequest($this->request)->getToken();
        if (!$token) {
            return null;
        }

        // get cached freshed token if exists for concurrency requests
        if ($this->getCachedToken()) {
            $this->jwt = $this->jwt->setToken($this->getCachedToken());
        }

        // token validation
        if ($this->getToken() &&
            ($payload = $this->jwt->check(true)) &&
            $this->validateSubject()
        ) {
            return $this->user = $this->provider->retrieveById($payload['sub']);
        }

        return $this->user;
    }

    /**
     * Logout the user, thus invalidating the token.
     *
     * @param  bool  $forceForever
     *
     * @return void
     */
    public function logout($forceForever = false)
    {
        if ($this->getToken()) {
            $this->requireToken()->invalidate($forceForever);
        }

        $this->user = null;
        $this->jwt->unsetToken();
    }

    /**
     * Return cached token.
     *
     * @return string
     */
    public function getCachedToken()
    {
        if ($this->cachedToken) {
            return $this->cachedToken;
        }

        $key = md5($this->jwt->parser()->parseToken());
        $this->cachedToken = Cache::get($key);

        return $this->cachedToken;
    }

    /**
     * Set cached token.
     *
     * @return this
     */
    public function setCachedToken($key, $refreshToken, $expiresAt = null)
    {
        if (is_null($expiresAt)) {
            $expiresAt = (int) (config('laravel_jwt.cache_ttl') / 60);
        }
        Cache::put($key, $refreshToken, $expiresAt);
        $this->cachedToken = $refreshToken;

        return $this;
    }

    /**
     * Refresh current expired token.
     *
     * @return string
     */
    public function refresh($forceForever = false, $resetClaims = false)
    {
        if ($cache = $this->getCachedToken()) {
            return $cache;
        }

        // refresh token
        $token = $this->jwt->parser()->parseToken();
        $key = md5($token);
        $refreshToken = JWTAuth::refresh($token, $forceForever, $resetClaims);

        // cache newly refreshed token
        $this->setCachedToken($key, $refreshToken);

        return $this->cachedToken;
    }
    
    /**
     * Ensure the JWTSubject matches what is in the token.
     *
     * @return  bool
     */
    protected function validateSubject()
    {
        // If the provider doesn't have the necessary method
        // to get the underlying model name then allow.
        if (! method_exists($this->provider, 'getModel')) {
            return true;
        }
        return $this->jwt->checkSubjectModel($this->provider->getModel());
    }

    /**
     * Get the token.
     *
     * @return false|Token
     */
    public function getToken()
    {
        return $this->jwt->setRequest($this->request)->getToken();
    }
}
