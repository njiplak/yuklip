<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\LoginRequest;

class ApiLoginRequest extends LoginRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'device_name' => ['nullable', 'string', 'max:100'],
            'fcm_token' => ['nullable', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios,web,macos,windows'],
        ]);
    }
}
