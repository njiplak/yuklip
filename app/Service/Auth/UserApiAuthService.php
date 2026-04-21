<?php

namespace App\Service\Auth;

use App\Contract\Auth\UserApiAuthContract;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserApiAuthService extends UserAuthService implements UserApiAuthContract
{
    protected string|null $guard = 'sanctum';

    public function login(array $credentials)
    {
        try {
            $user = $this->model::query()
                ->where($this->username, $credentials[$this->username])
                ->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return new Exception('Invalid credentials.');
            }

            $deviceName = $credentials['device_name'] ?? 'mobile';

            $user->tokens()->where('name', $deviceName)->delete();

            $token = $user->createToken($deviceName)->plainTextToken;

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ];
        } catch (Exception $exception) {
            return $exception;
        }
    }

    public function logout(): Exception|bool
    {
        try {
            $user = Auth::guard($this->guard)->user();
            $token = $user?->currentAccessToken();

            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }

            return true;
        } catch (Exception $exception) {
            return $exception;
        }
    }

    public function me()
    {
        try {
            $user = Auth::guard($this->guard)->user();

            if (!$user) {
                return new Exception('Unauthenticated.');
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        } catch (Exception $exception) {
            return $exception;
        }
    }
}
