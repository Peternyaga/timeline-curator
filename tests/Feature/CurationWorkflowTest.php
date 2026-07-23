<?php

namespace Tests\Feature;

use App\Curation\CurationIngestionService;
use App\Curation\CurationPolicyService;
use App\Curation\CurationTools;
use App\Models\AgentRun;
use App\Models\StoryCluster;
use App\Models\StoryMedia;
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
        $run = $ingestion->begin($current, ['site:example.org lightweight stack'], '0.3.0');
        $story = [
            'client_item_id' => 'result-1',
            'title' => 'A small team replaces a heavy frontend stack',
            'summary_points' => [
                'The rendering path moved server-side.',
                'The client bundle fell below 20 kB.',
                'Operational metrics improved after migration.',
                'The team published migration notes.',
            ],
            'why_it_matters' => 'It provides a concrete lightweight-stack adoption signal.',
            'sources' => [[
                'title' => 'Engineering report',
                'url' => 'https://example.org/engineering/report',
                'role' => 'primary',
                'published_at' => '2026-07-19T10:00:00Z',
            ]],
            'media' => [[
                'type' => 'image',
                'url' => 'https://cdn.example.org/migration.jpg',
                'caption' => 'The new server-rendered application.',
                'alt_text' => 'Application dashboard after the migration',
                'credit' => 'Example Engineering',
                'source_url' => 'https://example.org/engineering/report',
            ]],
            'feedback_tags' => $this->feedbackTags(),
        ];

        $first = $ingestion->submit($run->id, $current, [$story]);
        $second = $ingestion->submit($run->id, $current, [$story]);

        $this->assertCount(1, $first['accepted']);
        $this->assertCount(1, $second['accepted']);
        $this->assertSame($first['accepted'][0]['story_id'], $second['accepted'][0]['story_id']);
        $this->assertSame(1, StoryCluster::query()->count());
        $this->assertSame($story['summary_points'], StoryCluster::query()->firstOrFail()->summary_points);
        $this->assertSame(1, StoryMedia::query()->count());
        $completed = $ingestion->complete($run->id);
        $this->assertSame(1, $completed->accepted_count);
        $this->assertSame('completed', $completed->status);
    }

    public function test_summary_points_are_flexible_and_legacy_bullets_remain_supported(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        Topic::query()->create(['name' => 'Physics', 'brief' => 'Primary research']);
        $context = app(CurationPolicyService::class)->context();
        $run = app(CurationIngestionService::class)->begin($context['context_version'], ['physics breakthrough']);

        $result = app(CurationIngestionService::class)->submit($run->id, $context['context_version'], [
            [
                'client_item_id' => 'one-point',
                'title' => 'A concise result',
                'summary_points' => ['One verified point is sufficient.'],
                'sources' => [['title' => 'Paper', 'url' => 'https://example.org/paper', 'role' => 'primary']],
                'feedback_tags' => $this->feedbackTags(),
            ],
            [
                'client_item_id' => 'legacy',
                'title' => 'Legacy payload',
                'technical_bullets' => ['Old clients can still publish.'],
                'sources' => [[
                    'title' => 'Legacy source',
                    'url' => 'https://example.net/legacy',
                    'role' => 'primary',
                    'supports_bullets' => [1],
                ]],
            ],
            [
                'client_item_id' => 'too-many',
                'title' => 'Too many points',
                'summary_points' => array_fill(0, 7, 'Point'),
                'sources' => [['title' => 'Source', 'url' => 'https://example.com/too-many', 'role' => 'primary']],
                'feedback_tags' => $this->feedbackTags(),
            ],
        ]);

        $this->assertCount(2, $result['accepted']);
        $this->assertCount(1, $result['rejected']);
        $this->assertSame('invalid_story', $result['rejected'][0]['code']);
        $this->assertSame(2, StoryCluster::query()->count());
    }

    public function test_unsafe_media_and_unbalanced_feedback_tags_are_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        Topic::query()->create(['name' => 'Culture', 'brief' => 'New exhibitions']);
        $context = app(CurationPolicyService::class)->context();
        $run = app(CurationIngestionService::class)->begin($context['context_version'], ['new exhibitions']);
        $base = [
            'summary_points' => ['A new exhibition opened.'],
            'sources' => [['title' => 'Museum', 'url' => 'https://museum.example.org/news', 'role' => 'primary']],
        ];

        $result = app(CurationIngestionService::class)->submit($run->id, $context['context_version'], [
            [
                ...$base,
                'client_item_id' => 'unsafe-media',
                'title' => 'Unsafe media',
                'media' => [[
                    'type' => 'image',
                    'url' => 'https://127.0.0.1/private.jpg',
                    'caption' => 'Private image',
                    'alt_text' => 'Private image',
                    'credit' => 'Unknown',
                    'source_url' => 'https://museum.example.org/news',
                ]],
                'feedback_tags' => $this->feedbackTags(),
            ],
            [
                ...$base,
                'client_item_id' => 'unbalanced-tags',
                'title' => 'Unbalanced tags',
                'feedback_tags' => [
                    ['id' => 'more-art', 'label' => 'More art like this', 'signal' => 'more_like_this'],
                    ['id' => 'strong-source', 'label' => 'Strong museum source', 'signal' => 'good_source'],
                    ['id' => 'good-depth', 'label' => 'Useful exhibition detail', 'signal' => 'useful_depth'],
                    ['id' => 'fresh-find', 'label' => 'A fresh discovery', 'signal' => 'novel'],
                ],
            ],
        ]);

        $this->assertSame(['invalid_media', 'invalid_feedback_tag'], array_column($result['rejected'], 'code'));
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
        $instructions = implode(' ', $before['instructions']);
        $this->assertStringContainsString('dedicated media search for every candidate', $instructions);
        $this->assertStringContainsString('no suitable visual survives verification', $instructions);
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

        app(CurationTools::class)->beginCurationRun($version, ['one more query'], '0.3.0');
    }

    /** @return list<array{id: string, label: string, signal: string}> */
    private function feedbackTags(): array
    {
        return [
            ['id' => 'more-like-this', 'label' => 'More lightweight case studies', 'signal' => 'more_like_this'],
            ['id' => 'strong-source', 'label' => 'Strong original source', 'signal' => 'good_source'],
            ['id' => 'less-like-this', 'label' => 'Less frontend migration news', 'signal' => 'less_like_this'],
            ['id' => 'needs-depth', 'label' => 'Needs more implementation detail', 'signal' => 'wrong_depth'],
        ];
    }
}
