<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['job_curation_id', 'interest_score', 'match_score', 'semantic_tags', 'comment'])]
class JobFeedbackEvent extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['semantic_tags' => 'array'];
    }

    public function jobCuration()
    {
        return $this->belongsTo(JobCuration::class);
    }
}
