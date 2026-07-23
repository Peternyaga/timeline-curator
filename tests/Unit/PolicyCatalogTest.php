<?php

namespace Tests\Unit;

use Tests\TestCase;

class PolicyCatalogTest extends TestCase
{
    public function test_catalog_contains_valid_unique_topic_and_directive_presets(): void
    {
        $topics = config('policy_catalog.topics');
        $directives = config('policy_catalog.directives');

        $this->assertCount(36, $topics);
        $this->assertCount(18, $directives);
        $this->assertSame(
            count($topics),
            collect($topics)->pluck('id')->unique()->count(),
            'Topic preset ids must be unique.',
        );
        $this->assertSame(
            count($directives),
            collect($directives)->pluck('id')->unique()->count(),
            'Directive preset ids must be unique.',
        );

        foreach ($topics as $topic) {
            $this->assertNotEmpty($topic['id']);
            $this->assertNotEmpty($topic['category']);
            $this->assertNotEmpty($topic['name']);
            $this->assertNotEmpty($topic['brief']);
            $this->assertLessThanOrEqual(100, mb_strlen($topic['name']));
            $this->assertLessThanOrEqual(3000, mb_strlen($topic['brief']));
            $this->assertNotEmpty($topic['keywords']);
        }

        foreach ($directives as $directive) {
            $this->assertNotEmpty($directive['id']);
            $this->assertNotEmpty($directive['category']);
            $this->assertNotEmpty($directive['label']);
            $this->assertNotEmpty($directive['body']);
            $this->assertContains($directive['strength'], ['soft', 'hard']);
            $this->assertLessThanOrEqual(3000, mb_strlen($directive['body']));
            $this->assertNotEmpty($directive['keywords']);
        }
    }
}
