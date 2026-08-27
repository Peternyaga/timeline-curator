<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['job_curation_id', 'title', 'url', 'domain', 'role', 'published_at'])]
class JobSource extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function jobCuration()
    {
        return $this->belongsTo(JobCuration::class);
    }
}
