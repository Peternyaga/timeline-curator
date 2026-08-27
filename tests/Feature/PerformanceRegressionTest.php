<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\OAuthAccessToken;
use App\Models\OAuthClient;
use App\Models\OAuthGrant;
use App\Models\StoryCluster;
use App\Models\StorySource;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_query_count_does_not_grow_with_story_count(): void
    {
        $user = User::factory()->create();
        app(TenantContext::class)->set($user->tenant);
        $run = AgentRun::query()->create([
            'context_version' => hash('sha256', 'performance'),
            'exact_queries' => ['performance'],
        ]);

        foreach (range(1, 12) as $index) {
            $story = StoryCluster::query()->create([
                'agent_run_id' => $run->id,
                'client_item_id' => 'performance-'.$index,
                'title' => 'Performance story '.$index,
                'technical_bullets' => ['Point'],
                'summary_points' => ['Point'],
                'feedback_tags' => [],
                'fingerprint' => hash('sha256', 'performance-'.$index),
                'published_at' => now()->subMinutes($index),
            ]);
            StorySource::query()->create([
                'story_cluster_id' => $story->id,
                'title' => 'Source',
                'url' => 'https://example.com/'.$index,
                'domain' => 'example.com',
                'role' => 'primary',
                'supports_bullets' => null,
            ]);
        }
        app(TenantContext::class)->clear();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get('/timeline')->assertOk();

        $this->assertLessThanOrEqual(10, count(DB::getQueryLog()));
    }

    public function test_connections_page_does_not_hydrate_token_history(): void
    {
        $user = User::factory()->create();
        $client = OAuthClient::query()->create([
            'name' => 'Performance client',
            'client_id' => 'performance-client',
            'registration_key' => hash('sha256', 'performance-client'),
            'redirect_uris' => ['http://127.0.0.1/callback'],
        ]);
        $grant = OAuthGrant::query()->create([
            'oauth_client_id' => $client->id,
            'user_id' => $user->id,
            'scopes' => config('oauth.scopes'),
        ]);

        foreach (range(1, 100) as $index) {
            OAuthAccessToken::query()->create([
                'token_hash' => hash('sha256', 'performance-token-'.$index),
                'oauth_client_id' => $client->id,
                'oauth_grant_id' => $grant->id,
                'user_id' => $user->id,
                'scopes' => config('oauth.scopes'),
                'expires_at' => now()->addHour(),
                'last_used_at' => now()->subMinutes(101 - $index),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get('/connections')->assertOk();

        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
    }
}
