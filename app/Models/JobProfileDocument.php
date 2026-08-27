<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'job_profile_id', 'kind', 'label', 'original_name', 'mime_type',
    'size_bytes', 'storage_path', 'sha256', 'is_default',
])]
class JobProfileDocument extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function profile()
    {
        return $this->belongsTo(JobProfile::class, 'job_profile_id');
    }
}
