<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_grants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('oauth_client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('scopes');
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->foreignUlid('oauth_grant_id')
                ->nullable()
                ->after('oauth_client_id')
                ->constrained('oauth_grants')
                ->cascadeOnDelete();
        });

        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            $table->foreignUlid('oauth_grant_id')
                ->nullable()
                ->after('oauth_client_id')
                ->constrained('oauth_grants')
                ->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable()->change();
        });

        DB::table('oauth_refresh_tokens')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => null]);

        DB::table('oauth_refresh_tokens')
            ->whereNull('revoked_at')
            ->whereNull('oauth_grant_id')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('id')
            ->each(function (object $refresh): void {
                $grantId = (string) Str::ulid();
                $timestamp = now();
                DB::table('oauth_grants')->insert([
                    'id' => $grantId,
                    'oauth_client_id' => $refresh->oauth_client_id,
                    'user_id' => $refresh->user_id,
                    'scopes' => $refresh->scopes,
                    'last_refreshed_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                DB::table('oauth_refresh_tokens')
                    ->where('id', $refresh->id)
                    ->update(['oauth_grant_id' => $grantId]);
                DB::table('oauth_access_tokens')
                    ->where('oauth_client_id', $refresh->oauth_client_id)
                    ->where('user_id', $refresh->user_id)
                    ->whereNull('oauth_grant_id')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', $timestamp)
                    ->update(['oauth_grant_id' => $grantId]);
            });
    }

    public function down(): void
    {
        DB::table('oauth_refresh_tokens')
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addDays(30)]);

        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('oauth_grant_id');
            $table->timestamp('expires_at')->nullable(false)->change();
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('oauth_grant_id');
        });

        Schema::dropIfExists('oauth_grants');
    }
};
