<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_shares', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('story_cluster_id');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->boolean('active')->nullable()->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'story_cluster_id'])
                ->references(['tenant_id', 'id'])
                ->on('story_clusters')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'story_cluster_id', 'revoked_at']);
            $table->unique(['tenant_id', 'story_cluster_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_shares');
    }
};
