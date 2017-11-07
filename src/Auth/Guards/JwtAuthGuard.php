<?php

namespace App\Auth\Guards;

use BadMethodCallException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Tymon\JWTAuth\JWT;
use App\User;
use App\Token;
use App\Events\TokenRefreshed;

class JwtAuthGuard implements Guard
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

        // get token
        $token = $this->jwt->setRequest($this->request)->getToken();
        if (!$token) {
            return null;
        }

        // set cached freshed token
        if ($this->getCachedToken()) {
            $this->jwt = $this->jwt->setToken($this->getCachedToken());
        }

        // check token
        if ($this->jwt->check()) {
            return $this->user = $this->generateUserByClaims($this->getClaims());
        }

        return $this->user;
    }

    /**
     * Validate a user's credentials.
     *
     * @param array $credentials
     *
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        throw new BadMethodCallException("Method validate is not implemented.");
    }

    /**
     * Create a token for a user.
     *
     * @return string
     */
    public function login(array $claims)
    {
        $user = $this->generateUserByClaims($claims);
        $this->setUser($user);

        return $user;
    }

    public function generateUserByClaims(array $claims)
    {
        if (!array_key_exists('sub', $claims)) {
            throw new Exception('sub key is required');
        }
        $sub = $claims['sub'];
        unset($claims['sub']);
        $user = new User;
        $user->uid = $sub;
        foreach ($claims as $key => $value) {
            $user->{$key} = $value;
        }

        return $user;
    }

    public function generateTokenByClaims(array $claims)
    {
        $factory = new JWTFactory;
        foreach ($claims as $key => $value) {
            $factory = $factory->{$key} = $value;
        }

        return JWTAuth::encode($factory->make());
    }

    public function getClaims()
    {
        return [
            'sub' => $this->jwt->payload()->get('sub'),
            'name' => $this->jwt->payload()->get('name'),
            'is_admin' => $this->jwt->payload()->get('is_admin')
        ];
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
     * Logout the user.
     *
     * @param bool $forceForever
     *
     * @return bool
     */
    public function logout($forceForever = true)
    {
        // for leishan SSO only
        Token::where('jwt_hash', md5($this->getToken()))->delete();

        $this->invalidate($forceForever);
        $this->user = null;
        $this->jwt->unsetToken();
    }

    public function getCachedToken()
    {
        // retrun cached token
        if ($this->cachedToken) {
            return $this->cachedToken;
        }

        // generate token key
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
        // get cached token
        $cache = $this->getCachedToken();

        // return cached token
        if ($cache) {
            return $cache;
        }

        // refresh token
        $token = $this->jwt->parser()->parseToken();
        $key = md5($token);

        $refresh = \JWTAuth::refresh($token);
        $expiresAt = \Carbon\Carbon::now()
            ->addSeconds(config('jwt.cache_ttl'));

        // for leishan SSO only
        $this->updateLeishanToken($key, $refresh);

        // cache token key
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

    // for leishan SSO only
    protected function updateLeishanToken($hash, $new)
    {
        $token = Token::where('jwt_hash', $hash)->first();
        if (!$token) {
            return false;
        }
        $token->jwt_hash = md5($new);
        $token->jwt_token = $new;
        $token->save();

        event(new TokenRefreshed($token));
    }
}
