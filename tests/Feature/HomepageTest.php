<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomepageTest extends TestCase
{
    public function test_homepage_renders_the_editorial_hero_artwork(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('images/timeline-home-hero-720.jpg')
            ->assertSee('images/timeline-home-hero-1400.jpg')
            ->assertSee('An editorial collage representing stories gathered into a connected Timeline.')
            ->assertSee(route('guide'))
            ->assertDontSee('<script type="module"', false);
    }
}
