<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('brief');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'active']);
        });

        Schema::create('directives', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->enum('strength', ['hard', 'soft'])->default('soft');
            $table->json('structured_rules')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'enabled']);
        });

        Schema::create('agent_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('running');
            $table->string('context_version', 64);
            $table->json('exact_queries');
            $table->string('skill_version', 64)->nullable();
            $table->unsignedSmallInteger('accepted_count')->default(0);
            $table->unsignedSmallInteger('rejected_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('story_clusters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('agent_run_id');
            $table->string('client_item_id', 128);
            $table->string('title');
            $table->json('technical_bullets');
            $table->text('why_it_matters')->nullable();
            $table->string('fingerprint', 64);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'agent_run_id', 'client_item_id'], 'story_idempotency');
            $table->unique(['tenant_id', 'fingerprint']);
            $table->foreign(['tenant_id', 'agent_run_id'])->references(['tenant_id', 'id'])->on('agent_runs')->cascadeOnDelete();
            $table->index(['tenant_id', 'published_at']);
        });

        Schema::create('story_sources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('story_cluster_id');
            $table->string('title');
            $table->text('url');
            $table->string('domain');
            $table->enum('role', ['primary', 'supporting'])->default('supporting');
            $table->timestamp('published_at')->nullable();
            $table->json('supports_bullets');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'story_cluster_id'])->references(['tenant_id', 'id'])->on('story_clusters')->cascadeOnDelete();
            $table->index(['tenant_id', 'domain']);
        });

        Schema::create('feedback_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('story_cluster_id');
            $table->unsignedTinyInteger('relevance_score');
            $table->unsignedTinyInteger('depth_score');
            $table->json('semantic_tags')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'story_cluster_id']);
            $table->foreign(['tenant_id', 'story_cluster_id'])->references(['tenant_id', 'id'])->on('story_clusters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_events');
        Schema::dropIfExists('story_sources');
        Schema::dropIfExists('story_clusters');
        Schema::dropIfExists('agent_runs');
        Schema::dropIfExists('directives');
        Schema::dropIfExists('topics');
    }
};
