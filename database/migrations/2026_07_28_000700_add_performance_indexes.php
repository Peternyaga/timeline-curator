<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_clusters', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'published_at', 'id'],
                'story_clusters_timeline_cursor',
            );
        });

        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'status', 'completed_at'],
                'agent_runs_success_lookup',
            );
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->index(
                ['oauth_grant_id', 'last_used_at'],
                'oauth_access_tokens_last_used',
            );
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->dropIndex('oauth_access_tokens_last_used');
        });

        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->dropIndex('agent_runs_success_lookup');
        });

        Schema::table('story_clusters', function (Blueprint $table): void {
            $table->dropIndex('story_clusters_timeline_cursor');
        });
    }
};
