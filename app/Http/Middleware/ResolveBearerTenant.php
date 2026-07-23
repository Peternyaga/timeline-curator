<?php

namespace App\Http\Middleware;

use App\Models\OAuthAccessToken;
use App\OAuth\TokenFactory;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBearerTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->unauthorized('A bearer token is required.');
        }

        $accessToken = OAuthAccessToken::query()
            ->with('user.tenant')
            ->where('token_hash', TokenFactory::hash($token))
            ->first();

        if (! $accessToken || $accessToken->revoked_at || $accessToken->expires_at->isPast()) {
            return $this->unauthorized('The bearer token is invalid.');
        }

        $user = $accessToken->user;
        if (! $user?->tenant) {
            return $this->unauthorized('The bearer token has no Timeline tenant.');
        }

        $request->setUserResolver(fn () => $user);
        $this->context->set($user->tenant, $accessToken->scopes ?? []);

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
