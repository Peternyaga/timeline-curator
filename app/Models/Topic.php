<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'brief', 'active'])]
class Topic extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
