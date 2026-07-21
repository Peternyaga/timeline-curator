<?php

namespace Tests\Feature;

use App\Auth\TokenVerifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_requires_bearer_authentication(): void
    {
        $this->postJson('/mcp', [])->assertUnauthorized()->assertHeader(
            'WWW-Authenticate',
            'Bearer resource_metadata="http://localhost/.well-known/oauth-protected-resource/mcp"',
        );
    }

    public function test_mcp_exposes_path_aware_protected_resource_metadata(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', 'http://localhost/mcp')
            ->assertJsonStructure(['authorization_servers', 'bearer_methods_supported', 'scopes_supported']);
    }

    public function test_valid_subject_is_provisioned_into_its_own_tenant(): void
    {
        $this->app->bind(TokenVerifier::class, fn () => new class implements TokenVerifier
        {
            public function verify(string $token): array
            {
                return ['sub' => 'auth0|alice', 'name' => 'Alice', 'scope' => 'read:curation-context write:curation-runs write:story-batches'];
            }
        });

        $this->call('OPTIONS', '/mcp', server: ['HTTP_AUTHORIZATION' => 'Bearer valid-token'])->assertNoContent();
        $user = User::query()->where('auth0_sub', 'auth0|alice')->firstOrFail();
        $this->assertSame($user->tenant_id, $user->tenant->id);
        $this->assertDatabaseCount('tenants', 1);
    }
}
