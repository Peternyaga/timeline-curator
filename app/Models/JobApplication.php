<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'job_curation_id', 'status', 'channel', 'listing_snapshot', 'profile_snapshot',
    'profile_version', 'client_attempt_id', 'claim_token_hash', 'approved_at',
    'leased_at', 'lease_expires_at', 'completed_at', 'manual_reason',
])]
class JobApplication extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'listing_snapshot' => 'encrypted:array', 'profile_snapshot' => 'encrypted:array',
            'approved_at' => 'datetime', 'leased_at' => 'datetime',
            'lease_expires_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function jobCuration()
    {
        return $this->belongsTo(JobCuration::class);
    }

    public function materials()
    {
        return $this->hasMany(ApplicationMaterial::class);
    }

    public function questionnaires()
    {
        return $this->hasMany(ApplicationQuestionnaire::class);
    }

    public function events()
    {
        return $this->hasMany(ApplicationEvent::class);
    }
}
