<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\ProductUpdateRead;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_see_and_can_dismiss_release_updates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/timeline')
            ->assertOk()
            ->assertSee('More reliable scheduled curation')
            ->assertSee('Updates');

        $this->actingAs($user)->post(route('updates.read', '2026-07-durable-connections'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('product_update_reads', [
            'user_id' => $user->id,
            'update_id' => '2026-07-durable-connections',
        ]);

        $this->actingAs($user)->get('/timeline')
            ->assertOk()
            ->assertDontSee('More reliable scheduled curation');
    }

    public function test_update_reads_are_user_scoped_and_all_updates_can_be_marked_read(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->post(route('updates.read-all'))->assertRedirect();
        $this->assertSame(count(config('product_updates.items')), ProductUpdateRead::query()
            ->where('user_id', $first->id)->count());

        $this->actingAs($second)->get('/updates')
            ->assertOk()
            ->assertSee('More reliable scheduled curation')
            ->assertSee('New');

        $this->actingAs($second)->post(route('updates.read', 'unknown-update'))->assertNotFound();
    }

    public function test_timeline_warns_when_scheduled_curation_is_stale(): void
    {
        $user = User::factory()->create();
        app(TenantContext::class)->set($user->tenant);
        AgentRun::query()->create([
            'status' => 'completed',
            'context_version' => hash('sha256', 'stale'),
            'exact_queries' => ['stale run'],
            'completed_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->get('/timeline')
            ->assertOk()
            ->assertSee('Your Timeline has not refreshed recently.')
            ->assertSee('Check connection');
    }
}
