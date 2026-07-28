<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OAuthGrant extends Model
{
    use HasUlids;

    protected $table = 'oauth_grants';

    protected $fillable = [
        'oauth_client_id', 'user_id', 'scopes', 'last_refreshed_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_refreshed_at' => 'datetime',
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

    public function accessTokens()
    {
        return $this->hasMany(OAuthAccessToken::class, 'oauth_grant_id');
    }

    public function lastUsedAccessToken(): HasOne
    {
        return $this->hasOne(OAuthAccessToken::class, 'oauth_grant_id')
            ->ofMany('last_used_at', 'max');
    }

    public function refreshTokens()
    {
        return $this->hasMany(OAuthRefreshToken::class, 'oauth_grant_id');
    }
}
