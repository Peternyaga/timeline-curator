<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductUpdateRead extends Model
{
    protected $fillable = ['user_id', 'update_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
