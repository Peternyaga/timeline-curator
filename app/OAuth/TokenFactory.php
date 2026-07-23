<?php

namespace App\OAuth;

class TokenFactory
{
    public static function issue(string $prefix): string
    {
        return $prefix.rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
