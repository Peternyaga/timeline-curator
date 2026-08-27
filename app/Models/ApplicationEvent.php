<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['job_application_id', 'event', 'metadata'])]
class ApplicationEvent extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['metadata' => 'encrypted:array'];
    }

    public function application()
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }
}
