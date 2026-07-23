<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OAuthClient extends Model
{
    use HasUlids;

    protected $table = 'oauth_clients';

    protected $fillable = ['name', 'client_id', 'registration_key', 'redirect_uris'];

    protected function casts(): array
    {
        return ['redirect_uris' => 'array'];
    }

    public function acceptsRedirectUri(string $uri): bool
    {
        return in_array($uri, $this->redirect_uris ?? [], true);
    }
}
