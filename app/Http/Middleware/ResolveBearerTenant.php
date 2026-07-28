<?php

namespace App\Http\Middleware;

use App\Models\OAuthAccessToken;
use App\OAuth\TokenFactory;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveBearerTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->unauthorized('A bearer token is required.', 'invalid_request');
        }

        $accessToken = OAuthAccessToken::query()
            ->with(['user.tenant', 'grant'])
            ->where('token_hash', TokenFactory::hash($token))
            ->first();

        if (! $accessToken || $accessToken->revoked_at || $accessToken->expires_at->isPast()
            || $accessToken->grant?->revoked_at) {
            Log::warning('Timeline bearer token rejected.', [
                'reason' => ! $accessToken
                    ? 'unknown_token'
                    : ($accessToken->expires_at->isPast() ? 'expired_token' : 'revoked_token'),
                'oauth_grant_id' => $accessToken?->oauth_grant_id,
                'user_id' => $accessToken?->user_id,
            ]);

            return $this->unauthorized('The bearer token is invalid.');
        }

        $user = $accessToken->user;
        if (! $user?->tenant) {
            return $this->unauthorized('The bearer token has no Timeline tenant.');
        }

        $request->setUserResolver(fn () => $user);
        $this->context->set($user->tenant, $accessToken->scopes ?? []);
        if (! $accessToken->last_used_at || $accessToken->last_used_at->lt(now()->subMinutes(5))) {
            $accessToken->update(['last_used_at' => now()]);
        }

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }

    private function unauthorized(string $message, string $error = 'invalid_token'): Response
    {
        return response()->json(['error' => 'unauthorized', 'message' => $message], 401)
            ->header(
                'WWW-Authenticate',
                sprintf(
                    'Bearer resource_metadata="%s", error="%s", error_description="%s"',
                    url('/.well-known/oauth-protected-resource/mcp'),
                    $error,
                    $message,
                ),
            );
    }
}
