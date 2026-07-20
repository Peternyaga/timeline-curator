<?php

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);
            $builder->where($builder->qualifyColumn('tenant_id'), $context->id());
        });

        static::creating(function ($model): void {
            $model->tenant_id = app(TenantContext::class)->id();
        });
    }
}
