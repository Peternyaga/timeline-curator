<?php

namespace App\Auth;

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use Auth0\SDK\Token;
use RuntimeException;

class Auth0TokenVerifier implements TokenVerifier
{
    public function verify(string $token): array
    {
        $domain = (string) config('services.auth0.domain');
        $audience = (string) config('services.auth0.audience');

        if ($domain === '' || $audience === '') {
            throw new RuntimeException('Auth0 API configuration is incomplete.');
        }

        $auth0 = new Auth0(new SdkConfiguration(
            strategy: SdkConfiguration::STRATEGY_API,
            domain: $domain,
            audience: [$audience],
        ));

        return $auth0->decode(
            token: $token,
            tokenAudience: [$audience],
            tokenType: Token::TYPE_ACCESS_TOKEN,
        )->toArray();
    }
}
