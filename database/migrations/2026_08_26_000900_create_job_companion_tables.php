<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->json('job_queries')->nullable()->after('exact_queries');
            $table->unsignedSmallInteger('job_accepted_count')->default(0)->after('rejected_count');
            $table->unsignedSmallInteger('job_rejected_count')->default(0)->after('job_accepted_count');
        });

        Schema::create('job_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->json('search_preferences')->nullable();
            $table->longText('personal_details')->nullable();
            $table->longText('career_details')->nullable();
            $table->longText('reusable_answers')->nullable();
            $table->unsignedInteger('profile_version')->default(1);
            $table->timestamps();
            $table->unique('tenant_id');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'enabled']);
        });

        Schema::create('job_profile_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('job_profile_id');
            $table->string('kind', 32)->default('supporting');
            $table->string('label');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path');
            $table->string('sha256', 64);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'job_profile_id'])->references(['tenant_id', 'id'])->on('job_profiles')->cascadeOnDelete();
            $table->index(['tenant_id', 'job_profile_id', 'kind']);
        });

        Schema::create('job_curations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('agent_run_id');
            $table->string('client_item_id', 128);
            $table->string('title');
            $table->string('employer');
            $table->text('canonical_url');
            $table->text('application_url')->nullable();
            $table->string('application_channel', 16);
            $table->string('application_email')->nullable();
            $table->string('location')->nullable();
            $table->string('workplace_type', 32)->nullable();
            $table->string('employment_type', 32)->nullable();
            $table->json('salary')->nullable();
            $table->json('summary_points');
            $table->json('requirements')->nullable();
            $table->json('match_points');
            $table->json('gaps')->nullable();
            $table->text('application_instructions')->nullable();
            $table->json('feedback_tags');
            $table->string('fingerprint', 64);
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('retrieved_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'agent_run_id', 'client_item_id'], 'job_curation_idempotency');
            $table->unique(['tenant_id', 'fingerprint']);
            $table->foreign(['tenant_id', 'agent_run_id'])->references(['tenant_id', 'id'])->on('agent_runs')->cascadeOnDelete();
            $table->index(['tenant_id', 'retrieved_at']);
            $table->index(['tenant_id', 'deadline_at']);
        });

        Schema::create('job_sources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('job_curation_id');
            $table->string('title');
            $table->text('url');
            $table->string('domain');
            $table->string('role', 16)->default('supporting');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'job_curation_id'])->references(['tenant_id', 'id'])->on('job_curations')->cascadeOnDelete();
            $table->index(['tenant_id', 'job_curation_id']);
        });

        Schema::create('job_feedback_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('job_curation_id');
            $table->unsignedTinyInteger('interest_score');
            $table->unsignedTinyInteger('match_score');
            $table->json('semantic_tags')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'job_curation_id']);
            $table->foreign(['tenant_id', 'job_curation_id'])->references(['tenant_id', 'id'])->on('job_curations')->cascadeOnDelete();
        });

        Schema::create('job_applications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('job_curation_id');
            $table->string('status', 32)->default('queued');
            $table->string('channel', 16);
            $table->longText('listing_snapshot');
            $table->longText('profile_snapshot');
            $table->unsignedInteger('profile_version');
            $table->string('client_attempt_id', 128)->nullable();
            $table->string('claim_token_hash', 64)->nullable();
            $table->timestamp('approved_at');
            $table->timestamp('leased_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('manual_reason')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'client_attempt_id']);
            $table->foreign(['tenant_id', 'job_curation_id'])->references(['tenant_id', 'id'])->on('job_curations')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'approved_at']);
            $table->index(['tenant_id', 'lease_expires_at']);
        });

        Schema::create('application_materials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('job_application_id');
            $table->string('kind', 32);
            $table->string('filename')->nullable();
            $table->string('mime_type', 100);
            $table->longText('content');
            $table->json('fact_paths');
            $table->unsignedInteger('profile_version');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'job_application_id'])->references(['tenant_id', 'id'])->on('job_applications')->cascadeOnDelete();
            $table->index(['tenant_id', 'job_application_id']);
        });

        Schema::create('application_questionnaires', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('job_application_id');
            $table->longText('schema_payload');
            $table->longText('answer_payload')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'job_application_id'])->references(['tenant_id', 'id'])->on('job_applications')->cascadeOnDelete();
            $table->index(['tenant_id', 'job_application_id', 'status']);
        });

        Schema::create('application_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('job_application_id');
            $table->string('event', 64);
            $table->longText('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'job_application_id'])->references(['tenant_id', 'id'])->on('job_applications')->cascadeOnDelete();
            $table->index(['tenant_id', 'job_application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
        Schema::dropIfExists('application_questionnaires');
        Schema::dropIfExists('application_materials');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_feedback_events');
        Schema::dropIfExists('job_sources');
        Schema::dropIfExists('job_curations');
        Schema::dropIfExists('job_profile_documents');
        Schema::dropIfExists('job_profiles');

        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->dropColumn(['job_queries', 'job_accepted_count', 'job_rejected_count']);
        });
    }
};
