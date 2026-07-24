<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\FeedbackEvent;
use App\Models\StoryCluster;
use App\Models\StoryMedia;
use App\Models\StoryShare;
use App\Models\StorySource;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StorySharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_is_tenant_scoped_reused_and_contains_only_public_snapshot_data(): void
    {
        $user = User::factory()->create(['name' => 'Private Curator']);
        $this->setTenant($user);
        $story = $this->createStory('A shareable story');
        $this->addEvidence($story);
        FeedbackEvent::query()->create([
            'story_cluster_id' => $story->id,
            'relevance_score' => 5,
            'depth_score' => 4,
            'semantic_tags' => ['more-topic'],
            'comment' => 'Private feedback.',
        ]);

        $created = $this->actingAs($user)->postJson("/stories/{$story->id}/share")
            ->assertCreated()
            ->assertJsonPath('message', 'Public share link created.');
        $shareId = $created->json('share.id');
        $url = $created->json('share.url');

        $reused = $this->actingAs($user)->postJson("/stories/{$story->id}/share")
            ->assertOk()
            ->assertJsonPath('share.id', $shareId)
            ->assertJsonPath('share.url', $url);

        $this->setTenant($user);
        $this->assertSame(1, StoryShare::query()->count());
        $snapshot = StoryShare::query()->findOrFail($shareId)->snapshot;
        $this->assertSame('A shareable story', $snapshot['title']);
        $this->assertSame(['A concise verified point.'], $snapshot['summary_points']);
        $this->assertArrayHasKey('sources', $snapshot);
        $this->assertArrayHasKey('media', $snapshot);
        foreach (['tenant_id', 'created_by_user_id', 'feedback', 'feedback_tags', 'agent_run_id', 'policy'] as $privateKey) {
            $this->assertArrayNotHasKey($privateKey, $snapshot);
        }
        $encoded = json_encode($snapshot);
        $this->assertStringNotContainsString('Private Curator', $encoded);
        $this->assertStringNotContainsString('Private feedback', $encoded);

        $platforms = $created->json('share.platforms');
        $this->assertSame($url, $this->queryValue($platforms['facebook'], 'u'));
        $this->assertSame($url, $this->queryValue($platforms['linkedin'], 'url'));
        $this->assertSame($url, $this->queryValue($platforms['x'], 'url'));
        $this->assertStringContainsString('Curated with Timeline', $created->json('share.full_text'));
        $this->assertStringNotContainsString('Private Curator', $created->json('share.full_text'));

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->postJson("/stories/{$story->id}/share")->assertNotFound();
        $this->actingAs($otherUser)->deleteJson("/stories/{$story->id}/share")->assertNotFound();
    }

    public function test_public_page_requires_an_untampered_signature_and_uses_the_immutable_snapshot(): void
    {
        $user = User::factory()->create(['name' => 'Hidden Account']);
        $this->setTenant($user);
        $story = $this->createStory('Original headline');
        $this->addEvidence($story);

        $created = $this->actingAs($user)->postJson("/stories/{$story->id}/share")->assertCreated();
        $url = $created->json('share.url');
        $shareId = $created->json('share.id');

        $story->update([
            'title' => 'Private edited headline',
            'summary_points' => ['Private edited point.'],
        ]);

        $publicPage = $this->get($url);
        $publicPage
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'no-cache, public')
            ->assertSee('Original headline')
            ->assertSee('A concise verified point.')
            ->assertSee('Primary report')
            ->assertSee('https://images.example.org/story.jpg', false)
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('property="og:image" content="'.asset('images/timeline-share-default.jpg').'"', false)
            ->assertSee('property="og:image:secure_url" content="'.asset('images/timeline-share-default.jpg').'"', false)
            ->assertSee('property="og:image:type" content="image/jpeg"', false)
            ->assertSee('property="og:image:width" content="1200"', false)
            ->assertSee('property="og:image:height" content="630"', false)
            ->assertSee('property="og:image" content="https://images.example.org/story.jpg"', false)
            ->assertSee('property="og:image:alt" content="The verified event"', false)
            ->assertDontSee('Private edited headline')
            ->assertDontSee('Hidden Account');
        $this->assertLessThan(
            strpos($publicPage->getContent(), 'https://images.example.org/story.jpg'),
            strpos($publicPage->getContent(), asset('images/timeline-share-default.jpg')),
        );

        $this->get("/s/{$shareId}")->assertNotFound();
        $this->get($url.'&changed=1')->assertNotFound();
    }

    public function test_revocation_invalidates_the_old_url_and_resharing_creates_a_new_record(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $story = $this->createStory('Revocable story');

        $first = $this->actingAs($user)->postJson("/stories/{$story->id}/share")->assertCreated();
        $firstUrl = $first->json('share.url');
        $firstId = $first->json('share.id');

        $this->actingAs($user)->deleteJson("/stories/{$story->id}/share")
            ->assertOk()
            ->assertJsonPath('revoked', true);
        $this->get($firstUrl)->assertNotFound();

        $second = $this->actingAs($user)->postJson("/stories/{$story->id}/share")->assertCreated();
        $this->assertNotSame($firstId, $second->json('share.id'));
        $this->assertNotSame($firstUrl, $second->json('share.url'));
        $this->setTenant($user);
        $this->assertSame(2, StoryShare::query()->count());
        $this->assertNull(StoryShare::query()->findOrFail($firstId)->active);
        $this->assertNotNull(StoryShare::query()->findOrFail($firstId)->revoked_at);
    }

    public function test_deleting_a_story_cascades_shares_and_a_story_without_media_uses_fallback_artwork(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $story = $this->createStory('Fallback artwork story');

        $created = $this->actingAs($user)->postJson("/stories/{$story->id}/share")->assertCreated();
        $url = $created->json('share.url');
        $shareId = $created->json('share.id');
        $this->get($url)
            ->assertOk()
            ->assertSee(asset('images/timeline-share-default.jpg'), false);

        $story->delete();
        $this->assertNull(StoryShare::withoutGlobalScope('tenant')->find($shareId));
        $this->get($url)->assertNotFound();
    }

    public function test_initial_and_live_story_cards_expose_the_same_share_control(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $existing = $this->createStory('Existing card', Carbon::parse('2026-07-24 09:00:00'));

        $this->actingAs($user)->get('/timeline')
            ->assertOk()
            ->assertSee('data-share-story', false)
            ->assertSee("/stories/{$existing->id}/share", false);

        $this->setTenant($user);
        $new = $this->createStory('Live card', Carbon::parse('2026-07-24 10:00:00'));
        $updates = $this->actingAs($user)->getJson('/timeline/updates?'.http_build_query([
            'after_published_at' => $existing->published_at->toIso8601String(),
            'after_id' => $existing->id,
        ]))->assertOk();
        $this->assertStringContainsString('data-share-story', $updates->json('html'));
        $this->assertStringContainsString("/stories/{$new->id}/share", $updates->json('html'));
    }

    public function test_platform_intents_use_the_prepared_text_and_url_without_an_editable_composer(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $story = $this->createStory('Platform-ready story');

        $response = $this->actingAs($user)->postJson("/stories/{$story->id}/share")->assertCreated();
        $url = $response->json('share.url');
        $title = $response->json('share.title');
        $short = $response->json('share.short_text');
        $full = $response->json('share.full_text');
        $platforms = $response->json('share.platforms');

        $this->assertSame($full, $this->queryValue($platforms['whatsapp'], 'text'));
        $this->assertSame($short, $this->queryValue($platforms['x'], 'text'));
        $this->assertSame($url, $this->queryValue($platforms['x'], 'url'));
        $this->assertSame($url, $this->queryValue($platforms['facebook'], 'u'));
        $this->assertSame($url, $this->queryValue($platforms['linkedin'], 'url'));
        $this->assertSame($url, $this->queryValue($platforms['telegram'], 'url'));
        $this->assertSame($short, $this->queryValue($platforms['telegram'], 'text'));
        $this->assertSame($url, $this->queryValue($platforms['reddit'], 'url'));
        $this->assertSame($title, $this->queryValue($platforms['reddit'], 'title'));
        $this->assertSame($full, $this->queryValue($platforms['threads'], 'text'));
        $this->assertSame($full, $this->queryValue($platforms['bluesky'], 'text'));
        $this->assertStringStartsWith('mailto:?subject='.rawurlencode($title).'&body='.rawurlencode($full), $platforms['email']);

        $this->get($url)
            ->assertOk()
            ->assertSee('data-public-share-native', false)
            ->assertSee('data-public-share-copy', false)
            ->assertDontSee('<textarea', false)
            ->assertDontSee('<input', false);
    }

    public function test_database_rejects_a_second_active_share_and_anonymous_users_cannot_manage_links(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $story = $this->createStory('Single active share');
        $share = $this->actingAs($user)->postJson("/stories/{$story->id}/share")->assertCreated();

        $this->setTenant($user);
        try {
            StoryShare::query()->create([
                'story_cluster_id' => $story->id,
                'created_by_user_id' => $user->id,
                'snapshot' => ['title' => 'Duplicate'],
            ]);
            $this->fail('The database accepted two active shares for one tenant story.');
        } catch (QueryException) {
            $this->assertSame(1, StoryShare::query()->where('active', true)->count());
        }

        auth()->logout();
        $this->postJson("/stories/{$story->id}/share")->assertUnauthorized();
        $this->deleteJson("/stories/{$story->id}/share")->assertUnauthorized();
        $this->get($share->json('share.url'))->assertOk();
    }

    private function createStory(string $title, ?Carbon $publishedAt = null): StoryCluster
    {
        $run = AgentRun::query()->create([
            'context_version' => str_repeat('b', 64),
            'exact_queries' => [$title],
        ]);

        return StoryCluster::query()->create([
            'agent_run_id' => $run->id,
            'client_item_id' => 'share-'.str()->ulid(),
            'title' => $title,
            'technical_bullets' => ['Legacy point.'],
            'summary_points' => ['A concise verified point.'],
            'why_it_matters' => 'This changes what readers should watch next.',
            'feedback_tags' => [
                ['id' => 'more-topic', 'label' => 'More like this', 'signal' => 'more_like_this'],
                ['id' => 'less-topic', 'label' => 'Less like this', 'signal' => 'less_like_this'],
                ['id' => 'good-source', 'label' => 'Strong source', 'signal' => 'good_source'],
                ['id' => 'needs-depth', 'label' => 'Needs depth', 'signal' => 'wrong_depth'],
            ],
            'fingerprint' => hash('sha256', $title.str()->random()),
            'published_at' => $publishedAt ?? Carbon::parse('2026-07-24 09:30:00'),
        ]);
    }

    private function addEvidence(StoryCluster $story): void
    {
        StorySource::query()->create([
            'story_cluster_id' => $story->id,
            'title' => 'Primary report',
            'url' => 'https://example.org/report',
            'domain' => 'example.org',
            'role' => 'primary',
            'published_at' => Carbon::parse('2026-07-24 08:00:00'),
        ]);
        StoryMedia::query()->create([
            'story_cluster_id' => $story->id,
            'media_type' => 'image',
            'url' => 'https://images.example.org/story.jpg',
            'caption' => 'The verified event',
            'alt_text' => 'The verified event',
            'credit' => 'Example photographer',
            'source_url' => 'https://example.org/report',
            'position' => 0,
        ]);
    }

    private function queryValue(string $url, string $key): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query[$key];
    }

    private function setTenant(User $user): void
    {
        app(TenantContext::class)->set(Tenant::query()->findOrFail($user->tenant_id));
    }
}
