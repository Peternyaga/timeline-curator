<?php

namespace App\Http\Controllers;

use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthorizationCode;
use App\Models\OAuthClient;
use App\Models\OAuthGrant;
use App\Models\OAuthRefreshToken;
use App\OAuth\TokenFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OAuthTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return match ($request->input('grant_type')) {
            'authorization_code' => $this->exchangeCode($request),
            'refresh_token' => $this->refresh($request),
            default => $this->error('unsupported_grant_type', 'Only authorization_code and refresh_token are supported.'),
        };
    }

    private function exchangeCode(Request $request): JsonResponse
    {
        $input = $request->validate([
            'grant_type' => ['required', 'in:authorization_code'],
            'client_id' => ['required', 'string'],
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
            'code_verifier' => ['required', 'string', 'min:43', 'max:128'],
            'resource' => ['nullable', 'string'],
        ]);

        if (! preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $input['code_verifier'])) {
            return $this->error('invalid_grant', 'The PKCE verifier is invalid.');
        }

        return DB::transaction(function () use ($input): JsonResponse {
            $code = OAuthAuthorizationCode::query()
                ->where('code_hash', TokenFactory::hash($input['code']))
                ->lockForUpdate()
                ->first();
            $client = OAuthClient::query()->where('client_id', $input['client_id'])->first();

            $challenge = rtrim(strtr(base64_encode(hash('sha256', $input['code_verifier'], true)), '+/', '-_'), '=');
            if (! $code || ! $client || $code->oauth_client_id !== $client->id
                || $code->used_at || $code->expires_at->isPast()
                || ! hash_equals($code->redirect_uri, $input['redirect_uri'])
                || ! hash_equals($code->code_challenge, $challenge)) {
                return $this->error('invalid_grant', 'The authorization code is invalid, expired, used, or failed PKCE validation.');
            }

            $code->update(['used_at' => now()]);

            $grant = OAuthGrant::query()->create([
                'oauth_client_id' => $client->id,
                'user_id' => $code->user_id,
                'scopes' => $code->scopes,
                'last_refreshed_at' => now(),
            ]);

            Log::info('Timeline OAuth grant authorized.', $this->logContext($grant));

            return $this->tokens($client, $code->user_id, $code->scopes, $grant);
        });
    }

    private function refresh(Request $request): JsonResponse
    {
        $input = $request->validate([
            'grant_type' => ['required', 'in:refresh_token'],
            'client_id' => ['required', 'string'],
            'refresh_token' => ['required', 'string'],
            'scope' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($input): JsonResponse {
            $refresh = OAuthRefreshToken::query()
                ->with('grant')
                ->where('token_hash', TokenFactory::hash($input['refresh_token']))
                ->lockForUpdate()
                ->first();
            $client = OAuthClient::query()->where('client_id', $input['client_id'])->first();

            if (! $refresh || ! $client || $refresh->oauth_client_id !== $client->id
                || ($refresh->expires_at && $refresh->expires_at->isPast())) {
                Log::warning('Timeline OAuth refresh rejected.', [
                    'reason' => ! $refresh ? 'unknown_token' : 'invalid_client_or_expired',
                    'client_id' => $client?->id,
                ]);

                return $this->error('invalid_grant', 'The refresh token is invalid, expired, or revoked.');
            }

            if ($refresh->revoked_at) {
                return $this->error('invalid_grant', 'The refresh token is invalid, expired, or revoked.');
            }

            $grant = $refresh->grant;
            if ($grant?->revoked_at) {
                Log::warning('Timeline OAuth refresh rejected for a revoked grant.', $this->logContext($grant));

                return $this->error('invalid_grant', 'The authorization has been revoked.');
            }

            if (! $grant) {
                $grant = OAuthGrant::query()->create([
                    'oauth_client_id' => $client->id,
                    'user_id' => $refresh->user_id,
                    'scopes' => $refresh->scopes,
                    'last_refreshed_at' => now(),
                ]);
                $refresh->update(['oauth_grant_id' => $grant->id]);
            }

            $grant->update(['last_refreshed_at' => now()]);
            Log::info('Timeline OAuth token refreshed.', $this->logContext($grant));

            return $this->tokens(
                $client,
                $refresh->user_id,
                $refresh->scopes,
                $grant,
                $input['refresh_token'],
            );
        });
    }

    private function tokens(
        OAuthClient $client,
        int $userId,
        array $scopes,
        OAuthGrant $grant,
        ?string $existingRefreshToken = null,
    ): JsonResponse {
        $access = TokenFactory::issue('tl_at_');
        $refresh = $existingRefreshToken ?? TokenFactory::issue('tl_rt_');
        $accessTtl = (int) config('oauth.access_token_ttl_minutes');
        $refreshTtl = (int) config('oauth.refresh_token_ttl_days');
        $refreshUntilRevoked = (bool) config('oauth.refresh_token_until_revoked');

        OAuthAccessToken::query()->create([
            'token_hash' => TokenFactory::hash($access),
            'oauth_client_id' => $client->id,
            'oauth_grant_id' => $grant->id,
            'user_id' => $userId,
            'scopes' => $scopes,
            'expires_at' => now()->addMinutes($accessTtl),
        ]);
        if ($existingRefreshToken === null) {
            OAuthRefreshToken::query()->create([
                'token_hash' => TokenFactory::hash($refresh),
                'oauth_client_id' => $client->id,
                'oauth_grant_id' => $grant->id,
                'user_id' => $userId,
                'scopes' => $scopes,
                'expires_at' => ! $refreshUntilRevoked && $refreshTtl > 0
                    ? now()->addDays($refreshTtl)
                    : null,
            ]);
        }

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $access,
            'expires_in' => $accessTtl * 60,
            'refresh_token' => $refresh,
            'scope' => implode(' ', $scopes),
        ])->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }

    private function error(string $error, string $description): JsonResponse
    {
        return response()->json(['error' => $error, 'error_description' => $description], 400)
            ->header('Cache-Control', 'no-store');
    }

    /** @return array<string, int|string|null> */
    private function logContext(OAuthGrant $grant): array
    {
        return [
            'oauth_grant_id' => $grant->id,
            'oauth_client_id' => $grant->oauth_client_id,
            'user_id' => $grant->user_id,
        ];
    }
}
