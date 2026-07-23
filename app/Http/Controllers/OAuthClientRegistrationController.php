<?php

namespace App\Http\Controllers;

use App\Models\OAuthClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OAuthClientRegistrationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate([
            'client_name' => ['nullable', 'string', 'max:120'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
            'redirect_uris.*' => ['required', 'string', 'max:2048'],
            'token_endpoint_auth_method' => ['nullable', 'in:none'],
            'grant_types' => ['nullable', 'array'],
            'response_types' => ['nullable', 'array'],
        ]);

        $redirectUris = array_values(array_unique($input['redirect_uris']));
        foreach ($redirectUris as $uri) {
            if (! $this->validRedirectUri($uri)) {
                throw ValidationException::withMessages([
                    'redirect_uris' => 'Redirect URLs must use HTTPS, except for HTTP loopback callbacks.',
                ]);
            }
        }

        $registrationKey = hash('sha256', json_encode([
            'name' => $input['client_name'] ?? 'Codex',
            'redirect_uris' => $redirectUris,
        ], JSON_THROW_ON_ERROR));

        $client = OAuthClient::query()->firstOrCreate(
            ['registration_key' => $registrationKey],
            [
                'name' => $input['client_name'] ?? 'Codex',
                'client_id' => 'tlc_'.substr($registrationKey, 0, 48),
                'redirect_uris' => $redirectUris,
            ],
        );

        return response()->json([
            'client_id' => $client->client_id,
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
        ], $client->wasRecentlyCreated ? 201 : 200)->header('Cache-Control', 'no-store');
    }

    private function validRedirectUri(string $uri): bool
    {
        $parts = parse_url($uri);
        if (! is_array($parts) || isset($parts['fragment']) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (strtolower($parts['scheme']) === 'https') {
            return true;
        }

        return strtolower($parts['scheme']) === 'http'
            && in_array(Str::lower($parts['host']), ['localhost', '127.0.0.1', '::1'], true);
    }
}
