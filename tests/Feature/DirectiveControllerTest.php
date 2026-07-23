<?php

namespace Tests\Feature;

use App\Models\Directive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DirectiveControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_a_directive_without_an_expiry_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/directives', [
            'body' => 'Prefer primary sources.',
            'strength' => 'soft',
            'blocked_domains' => '',
            'expires_at' => '',
        ])->assertRedirect()->assertSessionHas('status', 'Directive added.');

        $this->assertDatabaseHas('directives', [
            'tenant_id' => $user->tenant_id,
            'body' => 'Prefer primary sources.',
            'strength' => 'soft',
        ]);
    }

    public function test_today_is_a_valid_inclusive_expiry_date(): void
    {
        Carbon::setTestNow('2026-07-21 14:00:00');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/directives', [
            'body' => 'Exclude low-quality aggregators.',
            'strength' => 'hard',
            'blocked_domains' => 'example.com, spam.test',
            'expires_at' => '2026-07-21',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $directive = Directive::query()->withoutGlobalScopes()->sole();

        $this->assertSame('2026-07-21 23:59:59', $directive->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(
            ['blocked_domains' => ['example.com', 'spam.test']],
            $directive->structured_rules,
        );

        Carbon::setTestNow();
    }
}
