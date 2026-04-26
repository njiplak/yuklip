<?php

namespace App\Contract\Auth;

interface UserApiAuthContract extends UserAuthContract
{
    public function me();

    public function registerDevice(array $payload);
}
