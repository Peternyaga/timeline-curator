<?php

namespace App\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UserProvisioner
{
    /** @param array<string, mixed> $claims */
    public function fromClaims(array $claims): User
    {
        $subject = trim((string) ($claims['sub'] ?? ''));
        if ($subject === '') {
            throw new InvalidArgumentException('The authenticated identity has no subject claim.');
        }

        if ($user = User::query()->where('auth0_sub', $subject)->first()) {
            return $user;
        }

        return DB::transaction(function () use ($claims, $subject): User {
            if ($existing = User::query()->where('auth0_sub', $subject)->lockForUpdate()->first()) {
                return $existing;
            }

            $name = trim((string) ($claims['name'] ?? $claims['nickname'] ?? 'Timeline user'));
            $tenant = Tenant::query()->create([
                'name' => $name !== '' ? "$name's timeline" : 'Personal timeline',
                'timezone' => 'UTC',
            ]);

            return User::query()->create([
                'tenant_id' => $tenant->id,
                'auth0_sub' => $subject,
                'name' => $name !== '' ? $name : 'Timeline user',
                'email' => isset($claims['email']) ? (string) $claims['email'] : null,
                'email_verified_at' => ($claims['email_verified'] ?? false) ? now() : null,
            ]);
        });
    }
}
