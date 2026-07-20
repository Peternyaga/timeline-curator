<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['story_cluster_id', 'title', 'url', 'domain', 'role', 'published_at', 'supports_bullets'])]
class StorySource extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['supports_bullets' => 'array', 'published_at' => 'datetime'];
    }

    public function storyCluster()
    {
        return $this->belongsTo(StoryCluster::class);
    }
}
