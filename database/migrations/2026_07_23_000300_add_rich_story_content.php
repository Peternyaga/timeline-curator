<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_clusters', function (Blueprint $table) {
            $table->json('summary_points')->nullable()->after('technical_bullets');
            $table->json('feedback_tags')->nullable()->after('why_it_matters');
        });

        DB::table('story_clusters')
            ->whereNull('summary_points')
            ->orderBy('id')
            ->chunkById(100, function ($stories): void {
                foreach ($stories as $story) {
                    DB::table('story_clusters')
                        ->where('id', $story->id)
                        ->update(['summary_points' => $story->technical_bullets]);
                }
            }, 'id');

        Schema::table('story_sources', function (Blueprint $table) {
            $table->json('supports_bullets')->nullable()->change();
        });

        Schema::create('story_media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('story_cluster_id');
            $table->enum('media_type', ['image', 'video']);
            $table->text('url');
            $table->string('provider', 32)->nullable();
            $table->string('provider_id', 128)->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->string('caption', 500);
            $table->string('alt_text', 500);
            $table->string('credit', 255);
            $table->text('source_url');
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'story_cluster_id'])
                ->references(['tenant_id', 'id'])
                ->on('story_clusters')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'story_cluster_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_media');

        DB::table('story_sources')
            ->whereNull('supports_bullets')
            ->update(['supports_bullets' => json_encode([])]);

        Schema::table('story_sources', function (Blueprint $table) {
            $table->json('supports_bullets')->nullable(false)->change();
        });

        Schema::table('story_clusters', function (Blueprint $table) {
            $table->dropColumn(['summary_points', 'feedback_tags']);
        });
    }
};
