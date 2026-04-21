<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\LoginRequest;

class ApiLoginRequest extends LoginRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
