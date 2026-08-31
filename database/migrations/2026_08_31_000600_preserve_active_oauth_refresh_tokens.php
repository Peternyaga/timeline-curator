<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('oauth_refresh_tokens')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->update(['expires_at' => null]);
    }

    public function down(): void
    {
        // Durable grants cannot be assigned a safe synthetic expiry on rollback.
    }
};
