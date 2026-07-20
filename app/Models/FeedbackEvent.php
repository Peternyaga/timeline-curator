<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['story_cluster_id', 'relevance_score', 'depth_score', 'semantic_tags', 'comment'])]
class FeedbackEvent extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['semantic_tags' => 'array'];
    }
}
