<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'agent_run_id', 'client_item_id', 'title', 'employer', 'canonical_url',
    'application_url', 'application_channel', 'application_email', 'location',
    'workplace_type', 'employment_type', 'salary', 'summary_points', 'requirements',
    'match_points', 'gaps', 'application_instructions', 'feedback_tags', 'fingerprint',
    'posted_at', 'deadline_at', 'retrieved_at',
])]
class JobCuration extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'salary' => 'array', 'summary_points' => 'array', 'requirements' => 'array',
            'match_points' => 'array', 'gaps' => 'array', 'feedback_tags' => 'array',
            'posted_at' => 'datetime', 'deadline_at' => 'datetime', 'retrieved_at' => 'datetime',
        ];
    }

    public function scopeForJobsWorkspace(Builder $query): Builder
    {
        return $query->with([
            'sources:id,tenant_id,job_curation_id,title,url,domain,role,published_at',
            'feedback:id,tenant_id,job_curation_id,interest_score,match_score,semantic_tags,comment',
            'latestApplication' => fn ($query) => $query->select([
                'job_applications.id', 'job_applications.tenant_id',
                'job_applications.job_curation_id', 'job_applications.status',
                'job_applications.approved_at', 'job_applications.lease_expires_at',
            ]),
        ]);
    }

    public function sources()
    {
        return $this->hasMany(JobSource::class);
    }

    public function feedback()
    {
        return $this->hasOne(JobFeedbackEvent::class);
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function latestApplication()
    {
        return $this->hasOne(JobApplication::class)->latestOfMany();
    }
}
