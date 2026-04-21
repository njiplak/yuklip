<?php

namespace App\Contract\Auth;

interface UserApiAuthContract extends UserAuthContract
{
    public function me();
}
