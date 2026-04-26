<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contract\Auth\UserApiAuthContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ApiLoginRequest;
use App\Http\Requests\Api\Auth\RegisterDeviceRequest;
use App\Utils\WebResponse;
use Exception;

class UserApiAuthController extends Controller
{
    protected UserApiAuthContract $service;

    public function __construct(UserApiAuthContract $service)
    {
        $this->service = $service;
    }

    public function login(ApiLoginRequest $request)
    {
        $request->ensureIsNotRateLimited();

        $result = $this->service->login($request->validated());

        if ($result instanceof Exception) {
            $request->hitRateLimiter();
            return WebResponse::json($result);
        }

        $request->clearRateLimiter();
        return WebResponse::json($result, 'Login successful.');
    }

    public function logout()
    {
        $result = $this->service->logout();
        return WebResponse::json($result, 'Logged out.');
    }

    public function me()
    {
        $result = $this->service->me();
        return WebResponse::json($result, 'Authenticated user.');
    }

    public function registerDevice(RegisterDeviceRequest $request)
    {
        $result = $this->service->registerDevice($request->validated());
        return WebResponse::json($result, 'Device registered.');
    }
}
