<?php

namespace App\Http\Controllers;

use App\Models\OAuthAuthorizationCode;
use App\Models\OAuthClient;
use App\OAuth\TokenFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OAuthAuthorizationController extends Controller
{
    public function show(Request $request): View
    {
        [$client, $parameters] = $this->validatedRequest($request);

        return view('oauth.authorize', compact('client', 'parameters'));
    }

    public function decide(Request $request): RedirectResponse
    {
        [$client, $parameters] = $this->validatedRequest($request);

        if ($request->input('decision') !== 'approve') {
            return $this->redirect($parameters['redirect_uri'], [
                'error' => 'access_denied',
                'state' => $parameters['state'],
            ]);
        }

        $plainCode = TokenFactory::issue('tl_code_');
        OAuthAuthorizationCode::query()->create([
            'code_hash' => TokenFactory::hash($plainCode),
            'oauth_client_id' => $client->id,
            'user_id' => $request->user()->id,
            'redirect_uri' => $parameters['redirect_uri'],
            'scopes' => $parameters['scopes'],
            'code_challenge' => $parameters['code_challenge'],
            'expires_at' => now()->addMinutes((int) config('oauth.authorization_code_ttl_minutes')),
        ]);

        return $this->redirect($parameters['redirect_uri'], [
            'code' => $plainCode,
            'state' => $parameters['state'],
        ]);
    }

    private function validatedRequest(Request $request): array
    {
        $input = $request->validate([
            'response_type' => ['required', 'in:code'],
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
            'state' => ['required', 'string', 'max:2048'],
            'code_challenge' => ['required', 'string', 'min:43', 'max:128'],
            'code_challenge_method' => ['required', 'in:S256'],
            'scope' => ['nullable', 'string', 'max:1000'],
            'resource' => ['nullable', 'string', 'max:2048'],
        ]);

        $client = OAuthClient::query()->where('client_id', $input['client_id'])->first();
        if (! $client || ! $client->acceptsRedirectUri($input['redirect_uri'])) {
            throw ValidationException::withMessages(['client_id' => 'The OAuth client or redirect URL is invalid.']);
        }

        if (isset($input['resource']) && rtrim($input['resource'], '/') !== rtrim(url('/mcp'), '/')) {
            throw ValidationException::withMessages(['resource' => 'This authorization server only issues tokens for the Timeline MCP resource.']);
        }

        $scopes = $this->scopes($input['scope'] ?? '');

        return [$client, [
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => $input['redirect_uri'],
            'state' => $input['state'],
            'code_challenge' => $input['code_challenge'],
            'code_challenge_method' => 'S256',
            'scope' => implode(' ', $scopes),
            'scopes' => $scopes,
            'resource' => $input['resource'] ?? url('/mcp'),
        ]];
    }

    private function scopes(string $requested): array
    {
        $supported = config('oauth.scopes');
        if (trim($requested) === '') {
            return $supported;
        }

        $scopes = array_values(array_unique(preg_split('/\s+/', trim($requested)) ?: []));
        if (array_diff($scopes, $supported)) {
            throw ValidationException::withMessages(['scope' => 'One or more requested scopes are not supported.']);
        }

        return $scopes;
    }

    private function redirect(string $uri, array $query): RedirectResponse
    {
        $separator = str_contains($uri, '?') ? '&' : '?';

        return redirect()->away($uri.$separator.http_build_query(array_filter(
            $query,
            static fn (mixed $value): bool => $value !== null,
        )));
    }
}
