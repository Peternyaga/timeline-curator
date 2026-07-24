<?php

namespace App\Http\Controllers;

use App\Models\StoryShare;
use App\Sharing\StoryShareService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicStoryShareController extends Controller
{
    public function __invoke(Request $request, string $share, StoryShareService $shares): Response
    {
        abort_unless($request->hasValidSignature(), 404);

        $record = StoryShare::withoutGlobalScope('tenant')
            ->whereKey($share)
            ->where('active', true)
            ->whereNull('revoked_at')
            ->firstOrFail();
        $snapshot = $record->snapshot;
        $presentation = $shares->presentation($record);
        $description = Str::limit(trim((string) (
            Arr::get($snapshot, 'why_it_matters')
            ?: Arr::get($snapshot, 'summary_points.0')
        )), 200, '…');
        $media = collect(Arr::get($snapshot, 'media', []));
        $socialMedia = $media->firstWhere('media_type', 'image')
            ?? $media->first(fn (array $item) => filled($item['thumbnail_url'] ?? null));
        $socialImage = $socialMedia
            ? ($socialMedia['media_type'] === 'image'
                ? $socialMedia['url']
                : ($socialMedia['thumbnail_url'] ?? null))
            : null;

        return response()
            ->view('shares.show', [
                'snapshot' => $snapshot,
                'presentation' => $presentation,
                'description' => $description,
                'socialImage' => $socialImage ?: asset('images/timeline-share-default.png'),
                'socialImageAlt' => $socialMedia['alt_text'] ?? 'Timeline Curator editorial research preview',
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
