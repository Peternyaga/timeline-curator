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
            ->assertDontSee('Your private signal');
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
