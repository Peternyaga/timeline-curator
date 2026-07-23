<?php

namespace Tests\Feature;

use App\Models\OAuthAccessToken;
use App\Models\OAuthClient;
use App\Models\User;
use App\OAuth\TokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class McpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private string $mcpSessionPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mcpSessionPath = storage_path('framework/testing/mcp-sessions');
        File::deleteDirectory($this->mcpSessionPath);
        config()->set('mcp.session_path', $this->mcpSessionPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mcpSessionPath);
        parent::tearDown();
    }

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

    public function test_valid_token_resolves_its_users_tenant(): void
    {
        $user = $this->createValidAccessToken('valid-token');
        $this->call('OPTIONS', '/mcp', server: ['HTTP_AUTHORIZATION' => 'Bearer valid-token'])->assertNoContent();
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

    public function test_mcp_session_survives_the_initialized_follow_up_request(): void
    {
        config()->set('mcp.allowed_hosts', ['curator.vumbualabs.com']);
        $this->bindValidTokenVerifier();

        $initialize = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
        ])->postJson('http://curator.vumbualabs.com/mcp', $this->initializePayload())
            ->assertOk();

        $sessionId = $initialize->headers->get('Mcp-Session-Id');
        $this->assertNotEmpty($sessionId);

        $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Mcp-Session-Id' => $sessionId,
        ])->postJson('http://curator.vumbualabs.com/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ])->assertAccepted();

        $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Mcp-Session-Id' => $sessionId,
        ])->postJson('http://curator.vumbualabs.com/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_curation_context',
                'arguments' => new \stdClass,
            ],
        ])->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.feedback_summary.sample_size', 0);
    }

    private function bindValidTokenVerifier(): void
    {
        $this->createValidAccessToken('valid-token');
    }

    private function createValidAccessToken(string $plainToken): User
    {
        $user = User::factory()->create();
        $client = OAuthClient::query()->create([
            'name' => 'Codex test',
            'client_id' => 'test-client',
            'registration_key' => hash('sha256', 'test-client'),
            'redirect_uris' => ['http://127.0.0.1/callback'],
        ]);
        OAuthAccessToken::query()->create([
            'token_hash' => TokenFactory::hash($plainToken),
            'oauth_client_id' => $client->id,
            'user_id' => $user->id,
            'scopes' => config('oauth.scopes'),
            'expires_at' => now()->addHour(),
        ]);

        return $user;
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
