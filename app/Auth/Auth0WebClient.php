<?php

namespace App\Auth;

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use RuntimeException;

class Auth0WebClient
{
    public function __construct(private LaravelSessionStore $store) {}

    public function make(): Auth0
    {
        $settings = config('services.auth0');

        foreach (['domain', 'client_id', 'client_secret', 'cookie_secret', 'audience'] as $key) {
            if (empty($settings[$key])) {
                throw new RuntimeException("Auth0 setting [$key] is required.");
            }
        }

        return new Auth0(new SdkConfiguration(
            domain: $settings['domain'],
            clientId: $settings['client_id'],
            clientSecret: $settings['client_secret'],
            cookieSecret: $settings['cookie_secret'],
            audience: [$settings['audience']],
            scope: ['openid', 'profile', 'email'],
            usePkce: true,
            sessionStorage: $this->store,
            transientStorage: $this->store,
        ));
    }
}
