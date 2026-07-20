<?php

namespace App\Auth;

use Auth0\SDK\Contract\StoreInterface;
use Illuminate\Contracts\Session\Session;

class LaravelSessionStore implements StoreInterface
{
    private const BAG = 'auth0_sdk';

    public function __construct(private Session $session) {}

    public function defer(bool $deferring): void {}

    public function delete(string $key): void
    {
        $values = $this->session->get(self::BAG, []);
        unset($values[$key]);
        $this->session->put(self::BAG, $values);
    }

    public function get(string $key, $default = null)
    {
        return $this->session->get(self::BAG, [])[$key] ?? $default;
    }

    public function purge(): void
    {
        $this->session->forget(self::BAG);
    }

    public function set(string $key, $value): void
    {
        $values = $this->session->get(self::BAG, []);
        $values[$key] = $value;
        $this->session->put(self::BAG, $values);
    }
}
