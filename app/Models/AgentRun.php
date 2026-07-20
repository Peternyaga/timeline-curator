<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['status', 'context_version', 'exact_queries', 'skill_version', 'accepted_count', 'rejected_count', 'completed_at'])]
class AgentRun extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['exact_queries' => 'array', 'completed_at' => 'datetime'];
    }

    public function stories()
    {
        return $this->hasMany(StoryCluster::class);
    }
}
