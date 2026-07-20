<?php

namespace App\Tenancy;

use App\Models\Tenant;
use LogicException;

class TenantContext
{
    private ?Tenant $tenant = null;

    /** @var list<string> */
    private array $permissions = [];

    public function set(Tenant $tenant, array $permissions = []): void
    {
        $this->tenant = $tenant;
        $this->permissions = array_values(array_unique($permissions));
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->permissions = [];
    }

    public function id(): string
    {
        return $this->tenant?->getKey() ?? throw new LogicException('Tenant context has not been established.');
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new LogicException('Tenant context has not been established.');
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
