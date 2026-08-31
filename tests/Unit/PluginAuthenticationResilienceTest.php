<?php

namespace Tests\Unit;

use Tests\TestCase;

class PluginAuthenticationResilienceTest extends TestCase
{
    public function test_timeline_authentication_cannot_block_unrelated_codex_tasks(): void
    {
        $configuration = json_decode(
            file_get_contents(base_path('plugins/timeline-curator/.mcp.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $timeline = $configuration['mcpServers']['timeline'];

        $this->assertSame('oauth', $timeline['auth']);
        $this->assertFalse($timeline['required']);
        $this->assertContains('read:curation-context', $timeline['scopes']);
        $this->assertContains('read:job-search-context', $timeline['scopes']);
        $this->assertContains('read:approved-applications', $timeline['scopes']);
    }

    public function test_plugin_and_server_catalog_report_the_same_release(): void
    {
        $manifest = json_decode(
            file_get_contents(base_path('plugins/timeline-curator/.codex-plugin/plugin.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertStringStartsWith(config('product_updates.current_plugin_version').'+codex.', $manifest['version']);
    }
}
