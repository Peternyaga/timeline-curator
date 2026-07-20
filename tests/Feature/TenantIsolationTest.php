<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_models_cannot_read_or_spoof_another_tenant(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        $context = app(TenantContext::class);

        $context->set($first);
        $owned = Topic::query()->create(['name' => 'Owned', 'brief' => 'First tenant topic']);

        $context->set($second);
        $foreign = Topic::query()->create(['tenant_id' => $first->id, 'name' => 'Foreign', 'brief' => 'Second tenant topic']);
        $this->assertSame($second->id, $foreign->tenant_id);

        $context->set($first);
        $this->assertSame([$owned->id], Topic::query()->pluck('id')->all());
        $this->assertNull(Topic::query()->find($foreign->id));
    }

    public function test_web_tenant_context_is_taken_from_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/timeline')->assertOk();
    }
}
