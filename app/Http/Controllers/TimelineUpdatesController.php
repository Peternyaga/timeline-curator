<?php

namespace App\Http\Controllers;

use App\Models\StoryCluster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TimelineUpdatesController extends Controller
{
    private const EMPTY_CURSOR_ID = '00000000000000000000000000';

    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate([
            'after_published_at' => ['required', 'date'],
            'after_id' => ['required', 'ulid'],
        ]);

        $after = Carbon::parse($input['after_published_at']);
        $afterId = $input['after_id'];

        if ($afterId !== self::EMPTY_CURSOR_ID) {
            $cursorStory = StoryCluster::query()->findOrFail($afterId);

            if (! $cursorStory->published_at || ! $cursorStory->published_at->equalTo($after)) {
                throw ValidationException::withMessages([
                    'after_published_at' => 'The update cursor does not match the referenced story.',
                ]);
            }
        }

        $candidates = StoryCluster::query()
            ->with(['sources', 'feedback'])
            ->where(function ($query) use ($after, $afterId): void {
                $query->where('published_at', '>', $after)
                    ->orWhere(function ($query) use ($after, $afterId): void {
                        $query->where('published_at', $after)
                            ->where('id', '>', $afterId);
                    });
            })
            ->orderBy('published_at')
            ->orderBy('id')
            ->limit(21)
            ->get();

        $hasMore = $candidates->count() > 20;
        $batch = $candidates->take(20);
        $nextCursor = $batch->last();
        $renderedStories = $batch->sortByDesc(fn (StoryCluster $story) => [
            $story->published_at?->getTimestamp() ?? 0,
            $story->id,
        ]);
        $semanticTags = ['Great source', 'More like this', 'SEO spam', 'Outdated', 'Paywalled'];

        return response()->json([
            'count' => $batch->count(),
            'html' => $renderedStories
                ->map(fn (StoryCluster $story) => view('partials.story-card', [
                    'story' => $story,
                    'semanticTags' => $semanticTags,
                ])->render())
                ->implode(''),
            'cursor' => $nextCursor ? [
                'published_at' => $nextCursor->published_at?->toIso8601String(),
                'id' => $nextCursor->id,
            ] : null,
            'has_more' => $hasMore,
        ])->header('Cache-Control', 'no-store');
    }
}
