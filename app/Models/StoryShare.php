<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['story_cluster_id', 'created_by_user_id', 'snapshot', 'active', 'revoked_at'])]
class StoryShare extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function storyCluster()
    {
        return $this->belongsTo(StoryCluster::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
