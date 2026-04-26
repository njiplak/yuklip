<?php

namespace App\Service\Auth;

use App\Contract\Auth\UserApiAuthContract;
use App\Models\DeviceToken;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

            $fcmToken = $credentials['fcm_token'] ?? null;
            if (is_string($fcmToken) && $fcmToken !== '') {
                $this->upsertDeviceToken(
                    userId: $user->id,
                    fcmToken: $fcmToken,
                    deviceName: $deviceName,
                    platform: $credentials['platform'] ?? null,
                );
            }

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

            $deviceName = $token->name ?? null;
            if ($user && is_string($deviceName) && $deviceName !== '') {
                DeviceToken::where('user_id', $user->id)
                    ->where('device_name', $deviceName)
                    ->delete();
            }

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

    public function registerDevice(array $payload)
    {
        try {
            $user = Auth::guard($this->guard)->user();

            if (!$user) {
                return new Exception('Unauthenticated.');
            }

            $deviceName = $payload['device_name'] ?? 'mobile';

            $this->upsertDeviceToken(
                userId: $user->id,
                fcmToken: $payload['fcm_token'],
                deviceName: $deviceName,
                platform: $payload['platform'] ?? null,
            );

            return ['device_name' => $deviceName];
        } catch (Exception $exception) {
            return $exception;
        }
    }

    private function upsertDeviceToken(int $userId, string $fcmToken, string $deviceName, ?string $platform): void
    {
        DB::transaction(function () use ($userId, $fcmToken, $deviceName, $platform) {
            // Detach this FCM token from any prior owner (token migrating to a new account on the same device).
            DeviceToken::where('token', $fcmToken)->delete();
            // Drop any stale row for this user+device so a fresh registration always replaces it.
            DeviceToken::where('user_id', $userId)
                ->where('device_name', $deviceName)
                ->delete();
            DeviceToken::create([
                'user_id' => $userId,
                'token' => $fcmToken,
                'platform' => $platform,
                'device_name' => $deviceName,
                'last_used_at' => now(),
            ]);
        });
    }
}
