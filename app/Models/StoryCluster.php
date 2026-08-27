<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'agent_run_id',
    'client_item_id',
    'title',
    'technical_bullets',
    'summary_points',
    'why_it_matters',
    'feedback_tags',
    'fingerprint',
    'published_at',
])]
class StoryCluster extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'technical_bullets' => 'array',
            'summary_points' => 'array',
            'feedback_tags' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function scopeForTimeline(Builder $query): Builder
    {
        return $query
            ->select([
                'id',
                'tenant_id',
                'title',
                'technical_bullets',
                'summary_points',
                'why_it_matters',
                'feedback_tags',
                'published_at',
            ])
            ->with([
                'sources:id,tenant_id,story_cluster_id,url,domain,role',
                'media:id,tenant_id,story_cluster_id,media_type,url,provider,provider_id,thumbnail_url,caption,alt_text,credit,source_url,position',
                'feedback:id,tenant_id,story_cluster_id,relevance_score,depth_score,semantic_tags,comment',
            ]);
    }

    public function sources()
    {
        return $this->hasMany(StorySource::class);
    }

    public function feedback()
    {
        return $this->hasOne(FeedbackEvent::class);
    }

    public function media()
    {
        return $this->hasMany(StoryMedia::class)->orderBy('position');
    }

    public function shares()
    {
        return $this->hasMany(StoryShare::class);
    }
}
