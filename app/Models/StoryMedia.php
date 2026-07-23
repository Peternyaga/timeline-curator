<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'story_cluster_id',
    'media_type',
    'url',
    'provider',
    'provider_id',
    'thumbnail_url',
    'caption',
    'alt_text',
    'credit',
    'source_url',
    'position',
])]
class StoryMedia extends Model
{
    use BelongsToTenant, HasUlids;

    protected $table = 'story_media';

    public function storyCluster()
    {
        return $this->belongsTo(StoryCluster::class);
    }
}
