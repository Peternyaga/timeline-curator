<?php

namespace Tests\Feature;

use App\Models\Directive;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PolicyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_page_is_authenticated_and_separate_from_the_feed(): void
    {
        $this->withoutVite();

        $this->get('/policy')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/policy')
            ->assertOk()
            ->assertSee('Train your task')
            ->assertSee('Add a topic')
            ->assertSee('Agent directives')
            ->assertSee('Browse popular topics')
            ->assertSee('AI &amp; Machine Learning', false)
            ->assertSee('Choose a useful directive')
            ->assertSee('Prefer original sources')
            ->assertSee('data-preset-form="topic"', false)
            ->assertSee('data-preset-form="directive"', false)
            ->assertDontSee('Your private signal');
    }

    public function test_new_user_catalogues_are_open_and_preset_values_use_existing_endpoints(): void
    {
        $user = User::factory()->create();
        $topic = config('policy_catalog.topics.0');
        $directive = config('policy_catalog.directives.0');

        $this->actingAs($user)
            ->get('/policy')
            ->assertOk()
            ->assertSee('data-create-panel="topic"', false)
            ->assertSee('data-create-panel="directive"', false)
            ->assertSee('data-initially-open="true"', false);

        $this->actingAs($user)->post('/topics', [
            'name' => $topic['name'],
            'brief' => $topic['brief'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post('/directives', [
            'body' => $directive['body'],
            'strength' => $directive['strength'],
            'blocked_domains' => '',
            'expires_at' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('topics', [
            'tenant_id' => $user->tenant_id,
            'name' => $topic['name'],
            'brief' => $topic['brief'],
        ]);
        $this->assertDatabaseHas('directives', [
            'tenant_id' => $user->tenant_id,
            'body' => $directive['body'],
            'strength' => $directive['strength'],
        ]);
    }

    public function test_topic_creation_error_reopens_the_form_with_user_input(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        Topic::query()->create(['name' => 'Existing topic', 'brief' => 'Existing coverage']);

        $this->actingAs($user)
            ->from('/policy')
            ->post('/topics', [
                'name' => '',
                'brief' => 'Keep this draft coverage brief.',
            ])
            ->assertRedirect('/policy')
            ->assertSessionHasErrors('name');

        $this->actingAs($user)
            ->get('/policy')
            ->assertOk()
            ->assertSee('data-create-panel="topic"', false)
            ->assertSee('data-initially-open="true"', false)
            ->assertSee('Keep this draft coverage brief.');
    }

    public function test_user_can_edit_archive_and_restore_a_topic(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $topic = Topic::query()->create(['name' => 'Old name', 'brief' => 'Old brief']);

        $this->actingAs($user)->patch("/topics/{$topic->id}", [
            'name' => 'New name',
            'brief' => 'New brief',
        ])->assertRedirect(route('policy').'#topic-'.$topic->id);

        $this->assertDatabaseHas('topics', [
            'id' => $topic->id,
            'tenant_id' => $user->tenant_id,
            'name' => 'New name',
            'brief' => 'New brief',
            'active' => true,
        ]);

        $this->actingAs($user)->patch("/topics/{$topic->id}/archive")
            ->assertRedirect(route('policy').'#topic-'.$topic->id);
        $this->assertFalse($topic->fresh()->active);

        $this->actingAs($user)->patch("/topics/{$topic->id}/restore")
            ->assertRedirect(route('policy').'#topic-'.$topic->id);
        $this->assertTrue($topic->fresh()->active);
    }

    public function test_restoring_a_topic_enforces_the_active_topic_limit(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);

        foreach (range(1, 5) as $index) {
            Topic::query()->create(['name' => "Topic {$index}", 'brief' => "Brief {$index}"]);
        }

        $archived = Topic::query()->create([
            'name' => 'Archived',
            'brief' => 'Waiting for capacity',
            'active' => false,
        ]);

        $this->actingAs($user)
            ->from('/policy')
            ->patch("/topics/{$archived->id}/restore")
            ->assertRedirect('/policy')
            ->assertSessionHasErrors('topic');

        $this->assertFalse($archived->fresh()->active);
    }

    public function test_user_can_edit_archive_and_restore_a_directive(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        $directive = Directive::query()->create([
            'body' => 'Prefer summaries.',
            'strength' => 'soft',
        ]);

        $this->actingAs($user)->patch("/directives/{$directive->id}", [
            'body' => 'Prefer primary sources.',
            'strength' => 'hard',
            'blocked_domains' => 'spam.test, example.com, spam.test',
            'expires_at' => '',
        ])->assertRedirect(route('policy').'#directive-'.$directive->id);

        $directive->refresh();
        $this->assertSame('Prefer primary sources.', $directive->body);
        $this->assertSame('hard', $directive->strength);
        $this->assertSame(['blocked_domains' => ['spam.test', 'example.com']], $directive->structured_rules);

        $this->actingAs($user)->patch("/directives/{$directive->id}/archive");
        $this->assertFalse($directive->fresh()->enabled);

        $this->actingAs($user)->patch("/directives/{$directive->id}/restore");
        $this->assertTrue($directive->fresh()->enabled);
    }

    public function test_expired_directive_must_be_updated_before_it_can_be_restored(): void
    {
        Carbon::setTestNow('2026-07-23 12:00:00');
        $user = User::factory()->create();
        $this->setTenant($user);
        $directive = Directive::query()->create([
            'body' => 'Temporary rule.',
            'strength' => 'hard',
            'enabled' => false,
            'expires_at' => Carbon::yesterday(),
        ]);

        $this->actingAs($user)
            ->from('/policy')
            ->patch("/directives/{$directive->id}/restore")
            ->assertRedirect(route('policy').'#directive-'.$directive->id)
            ->assertSessionHasErrors('directive');
        $this->assertFalse($directive->fresh()->enabled);

        $this->actingAs($user)->patch("/directives/{$directive->id}", [
            'body' => 'Temporary rule.',
            'strength' => 'hard',
            'blocked_domains' => '',
            'expires_at' => '',
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->patch("/directives/{$directive->id}/restore");

        $this->assertTrue($directive->fresh()->enabled);
        Carbon::setTestNow();
    }

    public function test_policy_actions_cannot_target_another_tenant(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->setTenant($second);
        $foreignTopic = Topic::query()->create(['name' => 'Private', 'brief' => 'Second tenant']);
        $foreignDirective = Directive::query()->create(['body' => 'Private rule', 'strength' => 'soft']);

        $this->actingAs($first)->patch("/topics/{$foreignTopic->id}/archive")->assertNotFound();
        $this->actingAs($first)->patch("/directives/{$foreignDirective->id}/archive")->assertNotFound();
    }

    private function setTenant(User $user): void
    {
        app(TenantContext::class)->set(Tenant::query()->findOrFail($user->tenant_id));
    }
}
