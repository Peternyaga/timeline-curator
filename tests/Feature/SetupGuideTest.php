<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_guide_is_public_and_contains_the_complete_plugin_journey(): void
    {
        $this->withoutVite();

        $this->get(route('guide'))
            ->assertOk()
            ->assertSee('Turn your interests into a living Timeline.')
            ->assertSee('Peternyaga/timeline-curator')
            ->assertSee('codex plugin add timeline-curator@vumbua-labs')
            ->assertSee('codex mcp login timeline')
            ->assertSee('@Timeline Curator Run my Timeline curation cycle now.')
            ->assertSee('Schedule the same instruction')
            ->assertSee(route('register'));
    }

    public function test_authenticated_guide_uses_the_application_navigation(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('guide'))
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSee('Start with your interests')
            ->assertSee(route('policy'))
            ->assertSee(route('timeline'));
    }
}
