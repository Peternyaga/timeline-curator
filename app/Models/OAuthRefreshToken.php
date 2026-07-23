<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OAuthRefreshToken extends Model
{
    use HasUlids;

    protected $table = 'oauth_refresh_tokens';

    protected $fillable = [
        'token_hash', 'oauth_client_id', 'user_id', 'scopes', 'expires_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(OAuthClient::class, 'oauth_client_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
