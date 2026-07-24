<?php

namespace App\Sharing;

use App\Models\StoryCluster;
use App\Models\StoryShare;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class StoryShareService
{
    public function createOrReuse(StoryCluster $story, User $user): StoryShare
    {
        return DB::transaction(function () use ($story, $user): StoryShare {
            $lockedStory = StoryCluster::query()
                ->with(['sources', 'media'])
                ->lockForUpdate()
                ->findOrFail($story->getKey());

            $existing = StoryShare::query()
                ->where('story_cluster_id', $lockedStory->getKey())
                ->where('active', true)
                ->latest('created_at')
                ->first();

            if ($existing) {
                return $existing;
            }

            return StoryShare::query()->create([
                'story_cluster_id' => $lockedStory->getKey(),
                'created_by_user_id' => $user->getKey(),
                'snapshot' => $this->snapshot($lockedStory),
            ]);
        });
    }

    public function revoke(StoryCluster $story): bool
    {
        return DB::transaction(function () use ($story): bool {
            StoryCluster::query()->lockForUpdate()->findOrFail($story->getKey());

            return StoryShare::query()
                ->where('story_cluster_id', $story->getKey())
                ->where('active', true)
                ->update(['active' => null, 'revoked_at' => now()]) > 0;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function presentation(StoryShare $share): array
    {
        $snapshot = $share->snapshot;
        $title = trim((string) Arr::get($snapshot, 'title'));
        $takeaway = trim((string) (
            Arr::get($snapshot, 'why_it_matters')
            ?: Arr::get($snapshot, 'summary_points.0')
        ));
        $url = URL::signedRoute('shares.show', ['share' => $share->getKey()]);
        $shortText = Str::limit(
            trim($title.($takeaway !== '' ? ' — '.$takeaway : '')),
            220,
            '…',
        )."\nCurated with Timeline";
        $fullText = implode("\n\n", array_filter([
            $title,
            $takeaway !== '' ? Str::limit($takeaway, 320, '…') : null,
            $url,
            'Curated with Timeline',
        ]));

        return [
            'id' => $share->getKey(),
            'url' => $url,
            'title' => $title,
            'short_text' => $shortText,
            'full_text' => $fullText,
            'platforms' => [
                'whatsapp' => 'https://wa.me/?text='.rawurlencode($fullText),
                'x' => 'https://twitter.com/intent/tweet?text='.rawurlencode($shortText).'&url='.rawurlencode($url),
                'facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url),
                'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($url),
                'telegram' => 'https://t.me/share/url?url='.rawurlencode($url).'&text='.rawurlencode($shortText),
                'reddit' => 'https://www.reddit.com/submit?url='.rawurlencode($url).'&title='.rawurlencode($title),
                'threads' => 'https://www.threads.net/intent/post?text='.rawurlencode($fullText),
                'bluesky' => 'https://bsky.app/intent/compose?text='.rawurlencode($fullText),
                'email' => 'mailto:?subject='.rawurlencode($title).'&body='.rawurlencode($fullText),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(StoryCluster $story): array
    {
        return [
            'title' => $story->title,
            'published_at' => $story->published_at?->toIso8601String(),
            'summary_points' => array_values($story->summary_points ?: $story->technical_bullets ?: []),
            'why_it_matters' => $story->why_it_matters,
            'sources' => $story->sources
                ->sortBy(fn ($source) => $source->role === 'primary' ? 0 : 1)
                ->values()
                ->map(fn ($source) => [
                    'title' => $source->title,
                    'url' => $source->url,
                    'domain' => $source->domain,
                    'role' => $source->role,
                    'published_at' => $source->published_at?->toIso8601String(),
                ])
                ->all(),
            'media' => $story->media
                ->sortBy('position')
                ->values()
                ->map(fn ($media) => [
                    'media_type' => $media->media_type,
                    'url' => $media->url,
                    'provider' => $media->provider,
                    'provider_id' => $media->provider_id,
                    'thumbnail_url' => $media->thumbnail_url,
                    'caption' => $media->caption,
                    'alt_text' => $media->alt_text,
                    'credit' => $media->credit,
                    'source_url' => $media->source_url,
                    'position' => $media->position,
                ])
                ->all(),
        ];
    }
}
