<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['body', 'strength', 'structured_rules', 'enabled', 'expires_at'])]
class Directive extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['structured_rules' => 'array', 'enabled' => 'boolean', 'expires_at' => 'datetime'];
    }
}
