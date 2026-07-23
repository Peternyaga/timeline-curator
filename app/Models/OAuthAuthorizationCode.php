<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OAuthAuthorizationCode extends Model
{
    use HasUlids;

    protected $table = 'oauth_authorization_codes';

    protected $fillable = [
        'code_hash', 'oauth_client_id', 'user_id', 'redirect_uri', 'scopes',
        'code_challenge', 'expires_at', 'used_at',
    ];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'expires_at' => 'datetime', 'used_at' => 'datetime'];
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
