<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\FeedbackEvent;
use App\Models\StoryCluster;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimelineInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_supports_json_and_updates_without_creating_duplicates(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $story = $this->createStory('Feedback target', Carbon::parse('2026-07-23 09:00:00'));

        $this->actingAs($user)->postJson("/stories/{$story->id}/feedback", [
            'relevance_score' => 5,
            'depth_score' => 4,
            'semantic_tags' => ['Great source'],
            'comment' => 'Keep this signal.',
        ])->assertOk()->assertJson([
            'message' => 'Feedback saved.',
            'story_id' => $story->id,
        ]);

        $this->actingAs($user)->postJson("/stories/{$story->id}/feedback", [
            'relevance_score' => 4,
            'depth_score' => 3,
            'semantic_tags' => ['More like this'],
            'comment' => 'Updated.',
        ])->assertOk();

        $this->setTenant($user);
        $this->assertSame(1, FeedbackEvent::query()->count());
        $this->assertDatabaseHas('feedback_events', [
            'story_cluster_id' => $story->id,
            'relevance_score' => 4,
            'depth_score' => 3,
            'comment' => 'Updated.',
        ]);
    }

    public function test_feedback_returns_json_validation_errors_and_anchor_fallback(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $story = $this->createStory('Validation target', Carbon::parse('2026-07-23 09:00:00'));

        $invalid = $this->actingAs($user)->postJson("/stories/{$story->id}/feedback", [
            'relevance_score' => 9,
            'depth_score' => 3,
        ]);
        $this->assertSame(422, $invalid->getStatusCode(), $invalid->getContent());
        $invalid->assertJsonValidationErrors('relevance_score');

        $this->actingAs($user)->post("/stories/{$story->id}/feedback", [
            'relevance_score' => 5,
            'depth_score' => 5,
        ])->assertRedirect(route('timeline').'#story-'.$story->id);
    }

    public function test_blank_feed_cursor_returns_a_newly_published_story(): void
    {
        Carbon::setTestNow('2026-07-23 10:00:00');
        $user = User::factory()->create();
        $this->setTenant($user);

        $response = $this->actingAs($user)->get('/timeline');
        $response->assertOk()->assertSee('data-after-id="00000000000000000000000000"', false);

        $this->setTenant($user);
        $story = $this->createStory('First live story', Carbon::now()->addSecond());

        $this->actingAs($user)->getJson('/timeline/updates?'.http_build_query([
            'after_published_at' => Carbon::now()->toIso8601String(),
            'after_id' => '00000000000000000000000000',
        ]))->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('cursor.id', $story->id)
            ->assertSee('First live story');

        Carbon::setTestNow();
    }

    public function test_updates_are_ordered_newest_first_when_timestamps_match(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $cursor = $this->createStory('Existing', Carbon::parse('2026-07-23 08:00:00'));
        $first = $this->createStory('First new', Carbon::parse('2026-07-23 09:00:00'));
        $second = $this->createStory('Second new', Carbon::parse('2026-07-23 09:00:00'));

        $response = $this->actingAs($user)->getJson($this->updatesUrl($cursor));
        $response->assertOk()->assertJsonPath('count', 2)->assertJsonPath('has_more', false);

        $html = $response->json('html');
        $this->assertLessThan(strpos($html, 'First new'), strpos($html, 'Second new'));
        $this->assertSame($second->id, $response->json('cursor.id'));
        $this->assertNotSame($first->id, $response->json('cursor.id'));
    }

    public function test_update_batches_advance_without_skipping_items(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $cursor = $this->createStory('Existing', Carbon::parse('2026-07-23 08:00:00'));

        foreach (range(1, 21) as $index) {
            $this->createStory("New {$index}", Carbon::parse('2026-07-23 09:00:00')->addSeconds($index));
        }

        $first = $this->actingAs($user)->getJson($this->updatesUrl($cursor));
        $first->assertOk()->assertJsonPath('count', 20)->assertJsonPath('has_more', true);

        $second = $this->actingAs($user)->getJson('/timeline/updates?'.http_build_query([
            'after_published_at' => $first->json('cursor.published_at'),
            'after_id' => $first->json('cursor.id'),
        ]));
        $second->assertOk()->assertJsonPath('count', 1)->assertJsonPath('has_more', false);
        $this->assertStringContainsString('New 21', $second->json('html'));
    }

    public function test_update_cursor_is_tenant_scoped(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->setTenant($second);
        $foreignStory = $this->createStory('Private update', Carbon::parse('2026-07-23 09:00:00'));

        $this->actingAs($first)->getJson($this->updatesUrl($foreignStory))->assertNotFound();
    }

    private function createStory(string $title, Carbon $publishedAt): StoryCluster
    {
        $run = AgentRun::query()->create([
            'context_version' => str_repeat('a', 64),
            'exact_queries' => [$title],
        ]);

        return StoryCluster::query()->create([
            'agent_run_id' => $run->id,
            'client_item_id' => 'item-'.str()->ulid(),
            'title' => $title,
            'technical_bullets' => ['One.', 'Two.', 'Three.'],
            'why_it_matters' => 'It matters.',
            'fingerprint' => hash('sha256', $title.str()->random()),
            'published_at' => $publishedAt,
        ]);
    }

    private function updatesUrl(StoryCluster $story): string
    {
        return '/timeline/updates?'.http_build_query([
            'after_published_at' => $story->published_at->toIso8601String(),
            'after_id' => $story->id,
        ]);
    }

    private function setTenant(User $user): void
    {
        app(TenantContext::class)->set(Tenant::query()->findOrFail($user->tenant_id));
    }
}
