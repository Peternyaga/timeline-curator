<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'status', 'context_version', 'exact_queries', 'job_queries', 'skill_version',
    'accepted_count', 'rejected_count', 'job_accepted_count', 'job_rejected_count', 'completed_at',
])]
class AgentRun extends Model
{
    use BelongsToTenant, HasUlids;

    protected $attributes = [
        'status' => 'running',
        'accepted_count' => 0,
        'rejected_count' => 0,
        'job_accepted_count' => 0,
        'job_rejected_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'exact_queries' => 'array',
            'job_queries' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function stories()
    {
        return $this->hasMany(StoryCluster::class);
    }

    public function jobs()
    {
        return $this->hasMany(JobCuration::class);
    }
}
