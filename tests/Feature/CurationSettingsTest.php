<?php

namespace Tests\Feature;

use App\Curation\CurationPolicyService;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_set_their_tenant_daily_run_limit(): void
    {
        $user = User::factory()->create();
        $otherTenant = Tenant::factory()->create(['daily_run_limit' => 10]);

        $this->actingAs($user)
            ->patch(route('policy.run-limit'), ['daily_run_limit' => 6])
            ->assertRedirect(route('policy'))
            ->assertSessionHas('status', 'Daily curation run limit updated.');

        $this->assertSame(6, $user->tenant->fresh()->daily_run_limit);
        $this->assertSame(10, $otherTenant->fresh()->daily_run_limit);
    }

    public function test_daily_run_limit_is_validated_and_returned_in_curation_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('policy'))
            ->patch(route('policy.run-limit'), ['daily_run_limit' => 11])
            ->assertRedirect(route('policy'))
            ->assertSessionHasErrors('daily_run_limit');

        app(TenantContext::class)->set($user->tenant->fresh());
        $context = app(CurationPolicyService::class)->context();

        $this->assertSame(10, $context['limits']['runs_per_day']);
        $this->assertSame(10, $context['usage']['runs_remaining_today']);
    }
}
