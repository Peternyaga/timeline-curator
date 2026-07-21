<?php

namespace App\Http\Middleware;

use App\Auth\TokenVerifier;
use App\Auth\UserProvisioner;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ResolveBearerTenant
{
    public function __construct(
        private TokenVerifier $verifier,
        private UserProvisioner $provisioner,
        private TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->unauthorized('A bearer token is required.');
        }

        try {
            $claims = $this->verifier->verify($token);
        } catch (Throwable) {
            return $this->unauthorized('The bearer token is invalid.');
        }

        $user = $this->provisioner->fromClaims($claims);

        $permissions = is_array($claims['permissions'] ?? null) ? $claims['permissions'] : [];
        if (is_string($claims['scope'] ?? null)) {
            $permissions = [...$permissions, ...preg_split('/\s+/', trim($claims['scope'])) ?: []];
        }

        $request->setUserResolver(fn () => $user);
        $this->context->set($user->tenant, $permissions);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }

    private function unauthorized(string $message): Response
    {
        return response()->json(['error' => 'unauthorized', 'message' => $message], 401)
            ->header(
                'WWW-Authenticate',
                sprintf('Bearer resource_metadata="%s"', url('/.well-known/oauth-protected-resource/mcp')),
            );
    }
}
