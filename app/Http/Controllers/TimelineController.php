<?php

namespace App\Http\Controllers;

use App\Models\StoryCluster;
use Illuminate\Support\Carbon;

class TimelineController extends Controller
{
    public function __invoke()
    {
        $stories = StoryCluster::query()
            ->with(['sources', 'media', 'feedback'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(20);
        $latest = $stories->currentPage() === 1 ? $stories->getCollection()->first() : null;
        $liveCursor = $stories->currentPage() === 1 ? [
            'published_at' => ($latest?->published_at ?? Carbon::createFromTimestampUTC(0))->toIso8601String(),
            'id' => $latest?->id ?? '00000000000000000000000000',
        ] : null;

        return view('timeline', [
            'stories' => $stories,
            'liveCursor' => $liveCursor,
        ]);
    }
}
