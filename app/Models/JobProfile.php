<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'enabled', 'search_preferences', 'personal_details', 'career_details',
    'reusable_answers', 'profile_version',
])]
class JobProfile extends Model
{
    use BelongsToTenant, HasUlids;

    protected $attributes = [
        'enabled' => false,
        'profile_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'search_preferences' => 'array',
            'personal_details' => 'encrypted:array',
            'career_details' => 'encrypted:array',
            'reusable_answers' => 'encrypted:array',
        ];
    }

    public function documents()
    {
        return $this->hasMany(JobProfileDocument::class);
    }

    public function searchReady(): bool
    {
        $preferences = $this->search_preferences ?? [];

        return filled($preferences['target_roles'] ?? null)
            && filled($preferences['locations'] ?? null);
    }

    public function applicationReady(): bool
    {
        $personal = $this->personal_details ?? [];
        $career = $this->career_details ?? [];

        return $this->searchReady()
            && filled($personal['full_name'] ?? null)
            && filled($personal['email'] ?? null)
            && filled($personal['phone'] ?? null)
            && filled($career['experience'] ?? null)
            && filled($career['skills'] ?? null)
            && $this->documents()->where('kind', 'resume')->exists();
    }

    public function applicationSnapshot(): array
    {
        return [
            'profile_version' => $this->profile_version,
            'search_preferences' => $this->search_preferences ?? [],
            'personal_details' => $this->personal_details ?? [],
            'career_details' => $this->career_details ?? [],
            'reusable_answers' => $this->reusable_answers ?? [],
            'documents' => $this->documents()->get([
                'id', 'kind', 'label', 'original_name', 'mime_type', 'size_bytes', 'is_default',
            ])->toArray(),
        ];
    }
}
