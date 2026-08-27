<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'timezone', 'daily_run_limit'])]
class Tenant extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return ['daily_run_limit' => 'integer'];
    }
}
