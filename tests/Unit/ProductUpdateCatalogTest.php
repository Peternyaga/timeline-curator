<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProductUpdateCatalogTest extends TestCase
{
    public function test_product_update_catalog_has_unique_complete_entries(): void
    {
        $updates = config('product_updates.items');

        $this->assertNotEmpty($updates);
        $this->assertSame(
            count($updates),
            count(array_unique(array_column($updates, 'id'))),
        );

        foreach ($updates as $update) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $update['id']);
            $this->assertNotEmpty($update['version']);
            $this->assertNotEmpty($update['published_at']);
            $this->assertNotEmpty($update['title']);
            $this->assertNotEmpty($update['summary']);
            $this->assertNotEmpty($update['action_label']);
            $this->assertNotEmpty($update['action_route']);
        }
    }
}
