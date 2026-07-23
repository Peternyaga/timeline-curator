<?php

namespace Tests\Feature;

use App\Curation\CurationIngestionService;
use App\Curation\CurationPolicyService;
use App\Curation\CurationTools;
use App\Models\AgentRun;
use App\Models\StoryCluster;
use App\Models\Tenant;
use App\Models\Topic;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mcp\Exception\ToolCallException;
use Tests\TestCase;

class CurationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_version_changes_and_valid_cluster_is_published_idempotently(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        Topic::query()->create(['name' => 'Lightweight engineering', 'brief' => 'Production case studies with small stacks']);

        $policy = app(CurationPolicyService::class);
        $version = $policy->context()['context_version'];
        Topic::query()->create(['name' => 'Astronomy', 'brief' => 'Verified primary research']);
        $this->assertNotSame($version, $policy->context()['context_version']);

        $current = $policy->context()['context_version'];
        $ingestion = app(CurationIngestionService::class);
        $run = $ingestion->begin($current, ['site:example.org lightweight stack'], '0.1.0');
        $story = [
            'client_item_id' => 'result-1',
            'title' => 'A small team replaces a heavy frontend stack',
            'technical_bullets' => ['The rendering path moved server-side.', 'The client bundle fell below 20 kB.', 'Operational metrics improved after migration.'],
            'why_it_matters' => 'It provides a concrete lightweight-stack adoption signal.',
            'sources' => [[
                'title' => 'Engineering report',
                'url' => 'https://example.org/engineering/report',
                'role' => 'primary',
                'published_at' => '2026-07-19T10:00:00Z',
                'supports_bullets' => [1, 2, 3],
            ]],
        ];

        $first = $ingestion->submit($run->id, $current, [$story]);
        $second = $ingestion->submit($run->id, $current, [$story]);

        $this->assertCount(1, $first['accepted']);
        $this->assertCount(1, $second['accepted']);
        $this->assertSame($first['accepted'][0]['story_id'], $second['accepted'][0]['story_id']);
        $this->assertSame(1, StoryCluster::query()->count());
        $completed = $ingestion->complete($run->id);
        $this->assertSame(1, $completed->accepted_count);
        $this->assertSame('completed', $completed->status);
    }

    public function test_invalid_source_and_non_three_bullet_cluster_are_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        Topic::query()->create(['name' => 'Physics', 'brief' => 'Primary research']);
        $context = app(CurationPolicyService::class)->context();
        $run = app(CurationIngestionService::class)->begin($context['context_version'], ['physics breakthrough']);

        $result = app(CurationIngestionService::class)->submit($run->id, $context['context_version'], [[
            'client_item_id' => 'bad',
            'title' => 'Bad cluster',
            'technical_bullets' => ['Only one bullet'],
            'sources' => [['title' => 'Local', 'url' => 'http://127.0.0.1/private', 'role' => 'primary', 'supports_bullets' => [1]]],
        ]]);

        $this->assertCount(1, $result['rejected']);
        $this->assertSame('invalid_story', $result['rejected'][0]['code']);
        $this->assertSame(0, StoryCluster::query()->count());
    }

    public function test_context_reports_daily_usage_without_changing_policy_version(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        Topic::query()->create(['name' => 'Physics', 'brief' => 'Primary research']);

        $policy = app(CurationPolicyService::class);
        $before = $policy->context();
        AgentRun::query()->create([
            'context_version' => $before['context_version'],
            'exact_queries' => ['physics breakthrough'],
        ]);
        $after = $policy->context();

        $this->assertSame(0, $before['usage']['runs_used_today']);
        $this->assertSame(3, $before['usage']['runs_remaining_today']);
        $this->assertSame(1, $after['usage']['runs_used_today']);
        $this->assertSame(2, $after['usage']['runs_remaining_today']);
        $this->assertSame($before['context_version'], $after['context_version']);
    }

    public function test_daily_quota_is_returned_as_a_readable_tool_error(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant, ['read:curation-context', 'write:curation-runs']);
        Topic::query()->create(['name' => 'Physics', 'brief' => 'Primary research']);
        $version = app(CurationPolicyService::class)->context()['context_version'];

        foreach (range(1, CurationPolicyService::RUNS_PER_DAY) as $index) {
            AgentRun::query()->create([
                'context_version' => $version,
                'exact_queries' => ["physics breakthrough $index"],
            ]);
        }

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('[quota_exceeded] The daily run quota has been reached.');

        app(CurationTools::class)->beginCurationRun($version, ['one more query'], '0.1.0');
    }
}
