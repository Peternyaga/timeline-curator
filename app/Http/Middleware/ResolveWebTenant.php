<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWebTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->tenant, 403, 'Tenant membership is required.');
        $this->context->set($user->tenant);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
