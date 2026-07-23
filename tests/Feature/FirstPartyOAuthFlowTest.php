<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstPartyOAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_registration_creates_an_isolated_tenant(): void
    {
        $this->post('/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
            'timezone' => 'Africa/Nairobi',
        ])->assertRedirect('/timeline');

        $user = User::query()->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Africa/Nairobi', $user->tenant->timezone);
        $this->assertNull($user->auth0_sub);
    }

    public function test_codex_can_register_once_and_exchange_a_one_use_pkce_code(): void
    {
        $registration = [
            'client_name' => 'Codex',
            'redirect_uris' => ['http://127.0.0.1:49152/callback'],
            'token_endpoint_auth_method' => 'none',
        ];

        $first = $this->postJson('/oauth/register', $registration)->assertCreated();
        $clientId = $first->json('client_id');
        $this->postJson('/oauth/register', $registration)
            ->assertOk()
            ->assertJsonPath('client_id', $clientId);
        $this->assertDatabaseCount('oauth_clients', 1);

        $user = User::factory()->create();
        $verifier = str_repeat('a', 64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $authorization = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $registration['redirect_uris'][0],
            'state' => 'state-123',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'resource' => url('/mcp'),
        ];

        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertOk()
            ->assertSee('Connect Codex?');

        $redirect = $this->actingAs($user)->post('/oauth/authorize', [
            ...$authorization,
            'decision' => 'approve',
        ])->assertRedirect();
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('state-123', $query['state']);
        $tokenRequest = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $query['code'],
            'redirect_uri' => $registration['redirect_uris'][0],
            'code_verifier' => $verifier,
        ];
        $tokens = $this->post('/oauth/token', $tokenRequest)
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in', 'scope']);

        $this->call('OPTIONS', '/mcp', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokens->json('access_token'),
        ])
            ->assertNoContent();

        $this->post('/oauth/token', $tokenRequest)
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_pkce_mismatch_does_not_consume_the_code(): void
    {
        $client = $this->postJson('/oauth/register', [
            'client_name' => 'Codex',
            'redirect_uris' => ['https://chatgpt.com/connector/oauth/callback'],
        ])->json();
        $user = User::factory()->create();
        $verifier = str_repeat('v', 64);
        $authorization = [
            'response_type' => 'code',
            'client_id' => $client['client_id'],
            'redirect_uri' => $client['redirect_uris'][0],
            'state' => 'safe-state',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
        $redirect = $this->actingAs($user)->post('/oauth/authorize', [
            ...$authorization,
            'decision' => 'approve',
        ]);
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client['client_id'],
            'code' => $query['code'],
            'redirect_uri' => $client['redirect_uris'][0],
            'code_verifier' => str_repeat('x', 64),
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }
}
