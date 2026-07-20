<?php

namespace App\Auth;

interface TokenVerifier
{
    /** @return array<string, mixed> */
    public function verify(string $token): array;
}
