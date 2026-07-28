<?php

namespace Tests\Feature;

use App\Models\OAuthAccessToken;
use App\Models\OAuthClient;
use App\Models\OAuthGrant;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use App\OAuth\TokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_and_revoke_only_their_own_connection(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $client = OAuthClient::query()->create([
            'name' => 'Codex Desktop',
            'client_id' => 'desktop-client',
            'registration_key' => hash('sha256', 'desktop-client'),
            'redirect_uris' => ['http://127.0.0.1/callback'],
        ]);
        $grant = OAuthGrant::query()->create([
            'oauth_client_id' => $client->id,
            'user_id' => $owner->id,
            'scopes' => config('oauth.scopes'),
            'last_refreshed_at' => now(),
        ]);
        OAuthAccessToken::query()->create([
            'token_hash' => TokenFactory::hash('connection-access'),
            'oauth_client_id' => $client->id,
            'oauth_grant_id' => $grant->id,
            'user_id' => $owner->id,
            'scopes' => config('oauth.scopes'),
            'expires_at' => now()->addHour(),
            'last_used_at' => now(),
        ]);
        OAuthRefreshToken::query()->create([
            'token_hash' => TokenFactory::hash('connection-refresh'),
            'oauth_client_id' => $client->id,
            'oauth_grant_id' => $grant->id,
            'user_id' => $owner->id,
            'scopes' => config('oauth.scopes'),
            'expires_at' => null,
        ]);

        $this->actingAs($owner)->get('/connections')
            ->assertOk()
            ->assertSee('Codex Desktop')
            ->assertSee('Active');

        $this->actingAs($other)->delete(route('connections.destroy', $grant))->assertNotFound();
        $this->assertNull($grant->fresh()->revoked_at);

        $this->actingAs($owner)->delete(route('connections.destroy', $grant))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertNotNull(OAuthAccessToken::query()->firstOrFail()->revoked_at);
        $this->assertNotNull(OAuthRefreshToken::query()->firstOrFail()->revoked_at);
    }
}
