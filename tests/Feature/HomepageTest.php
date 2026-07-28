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
            ->assertSee('Timeline Curator presented as an editorial collage')
            ->assertDontSee('<script type="module"', false);
    }
}
