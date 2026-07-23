<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
}
