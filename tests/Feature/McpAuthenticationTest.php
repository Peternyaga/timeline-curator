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

    public function test_mcp_accepts_the_configured_application_host(): void
    {
        config()->set('mcp.allowed_hosts', ['curator.vumbualabs.com']);
        $this->bindValidTokenVerifier();

        $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
        ])->postJson('http://curator.vumbualabs.com/mcp', $this->initializePayload())
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Timeline Curator');
    }

    public function test_mcp_rejects_an_unconfigured_host(): void
    {
        config()->set('mcp.allowed_hosts', ['curator.vumbualabs.com']);
        $this->bindValidTokenVerifier();

        $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
        ])->postJson('http://attacker.example/mcp', $this->initializePayload())
            ->assertForbidden()
            ->assertSeeText('Invalid Host header');
    }

    private function bindValidTokenVerifier(): void
    {
        $this->app->bind(TokenVerifier::class, fn () => new class implements TokenVerifier
        {
            public function verify(string $token): array
            {
                return ['sub' => 'auth0|host-test', 'scope' => 'read:curation-context'];
            }
        });
    }

    private function initializePayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
            ],
        ];
    }
}
