<?php

namespace App\Http\Controllers;

use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthorizationCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\OAuth\TokenFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            return $this->tokens($client, $code->user_id, $code->scopes);
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
                ->where('token_hash', TokenFactory::hash($input['refresh_token']))
                ->lockForUpdate()
                ->first();
            $client = OAuthClient::query()->where('client_id', $input['client_id'])->first();

            if (! $refresh || ! $client || $refresh->oauth_client_id !== $client->id
                || $refresh->revoked_at || $refresh->expires_at->isPast()) {
                return $this->error('invalid_grant', 'The refresh token is invalid, expired, or revoked.');
            }

            $refresh->update(['revoked_at' => now()]);

            return $this->tokens($client, $refresh->user_id, $refresh->scopes);
        });
    }

    private function tokens(OAuthClient $client, int $userId, array $scopes): JsonResponse
    {
        $access = TokenFactory::issue('tl_at_');
        $refresh = TokenFactory::issue('tl_rt_');
        $accessTtl = (int) config('oauth.access_token_ttl_minutes');

        OAuthAccessToken::query()->create([
            'token_hash' => TokenFactory::hash($access),
            'oauth_client_id' => $client->id,
            'user_id' => $userId,
            'scopes' => $scopes,
            'expires_at' => now()->addMinutes($accessTtl),
        ]);
        OAuthRefreshToken::query()->create([
            'token_hash' => TokenFactory::hash($refresh),
            'oauth_client_id' => $client->id,
            'user_id' => $userId,
            'scopes' => $scopes,
            'expires_at' => now()->addDays((int) config('oauth.refresh_token_ttl_days')),
        ]);

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
}
