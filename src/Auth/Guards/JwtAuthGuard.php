<?php

namespace Unisharp\JWT\Auth\Guards;

use BadMethodCallException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Tymon\JWTAuth\JWT;

class JWTAuthGuard implements Guard
{
    use GuardHelpers;

    protected $cachedToken;

    /**
     * The JWT instance.
     *
     * @var \Tymon\JWTAuth\JWT
     */
    protected $jwt;

    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Create a new authentication guard.
     *
     * @param \Tymon\JWTAuth\JWT                      $jwt
     * @param \Illuminate\Contracts\Auth\UserProvider $provider
     * @param \Illuminate\Http\Request                $request
     */
    public function __construct(JWT $jwt, UserProvider $provider, Request $request)
    {
        $this->jwt = $jwt;
        $this->provider = $provider;
        $this->request = $request;
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
        if ($this->jwt->setRequest($this->request)->getToken() &&
            ($payload = $this->jwt->check(true)) &&
            $this->validateSubject()
        ) {
            return $this->user = $this->provider->retrieveById($payload['sub']);
        }

        return $this->user;
    }

    /**
     * Log the given user ID into the application without sessions or cookies.
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function onceUsingId($id)
    {
        return true;
    }

    /**
     * Attempt to authenticate the user using the given credentials and return the token.
     *
     * @param  array  $credentials
     * @param  bool  $login
     *
     * @return bool|string
     */
    public function attempt(array $credentials = [], $login = true)
    {
        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        if ($this->hasValidCredentials($user, $credentials)) {
            return $login ? $this->login($user) : true;
        }

        return false;
    }

    /**
     * Validate a user's credentials.
     *
     * @param  array  $credentials
     *
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        return (bool) $this->attempt($credentials, false);
    }

    public function getTokenByClaims(array $claims)
    {
        $factory = new JWTFactory;
        foreach ($claims as $key => $value) {
            $factory = $factory->{$key} = $value;
        }

        return JWTAuth::encode($factory->make());
    }

    /**
     * Create a token for a user.
     *
     * @param  \Tymon\JWTAuth\Contracts\JWTSubject  $user
     *
     * @return string
     */
    public function login(JWTSubject $user)
    {
        $this->setUser($user);

        return $this->jwt->fromUser($user);
    }

    /**
     * Logout the user.
     *
     * @param bool $forceForever
     *
     * @return bool
     */
    public function logout($forceForever = true)
    {
        $this->invalidate($forceForever);
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
        $this->cachedToken = \Cache::get($key);

        return $this->cachedToken;
    }

    /**
     * Refresh current expired token.
     *
     * @return string
     */
    public function refresh()
    {
        $cache = $this->getCachedToken();

        if ($cache) {
            return $cache;
        }

        // refresh token
        $token = $this->jwt->parser()->parseToken();
        $key = md5($token);
        $refresh = \JWTAuth::refresh($token);
        $expiresAt = \Carbon\Carbon::now()
            ->addSeconds(config('laravel_jwt.cache_ttl'));

        // cache newly refreshed token
        \Cache::put($key, $refresh, $expiresAt);
        return $refresh;
    }

    /**
     * Invalidate current token (add it to the blacklist).
     *
     * @param bool $forceForever
     *
     * @return bool
     */
    public function invalidate($forceForever = false)
    {
        return $this->requireToken()->invalidate($forceForever);
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

    /**
     * Set the token.
     *
     * @param Token|string $token
     *
     * @return JwtGuard
     */
    public function setToken($token)
    {
        $this->jwt->setToken($token);

        return $this;
    }

    /**
     * Get the raw Payload instance.
     *
     * @return \Tymon\JWTAuth\Payload
     */
    public function getPayload()
    {
        return $this->jwt->getPayload();
    }

    /**
     * Ensure that a token is available in the request.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     *
     * @return \Tymon\JWTAuth\JWT
     */
    protected function requireToken()
    {
        if (!$this->getToken()) {
            throw new BadRequestHttpException('Token could not be parsed from the request.');
        }

        return $this->jwt;
    }

    /**
     * Return the currently cached user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get the user provider used by the guard.
     *
     * @return \Illuminate\Contracts\Auth\UserProvider
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Set the user provider used by the guard.
     *
     * @param \Illuminate\Contracts\Auth\UserProvider $provider
     *
     * @return $this
     */
    public function setProvider(UserProvider $provider)
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set the current request instance.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Magically call the JWT instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws BadMethodCallException
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->jwt, $method)) {
            return call_user_func_array([$this->jwt, $method], $parameters);
        }

        throw new BadMethodCallException("Method [$method] does not exist.");
    }

    /**
     * Determine if the user matches the credentials.
     *
     * @param  mixed  $user
     * @param  array  $credentials
     * @return bool
     */
    protected function hasValidCredentials($user, $credentials)
    {
        return !is_null($user) && $this->provider->validateCredentials($user, $credentials);
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
        if (!method_exists($this->provider, 'getModel')) {
            return true;
        }

        return $this->jwt->checkProvider($this->provider->getModel());
    }
}
