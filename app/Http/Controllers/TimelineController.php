<?php

namespace App\Http\Controllers;

use App\Models\AgentRun;
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
        $latestRun = AgentRun::query()->latest()->first();
        $latestSuccessfulRun = AgentRun::query()
            ->whereIn('status', ['completed', 'completed_empty'])
            ->latest('completed_at')
            ->first();
        $curatorHealth = match (true) {
            $latestRun?->status === 'failed' => [
                'title' => 'The latest curation run failed.',
                'message' => 'Open Scheduled to inspect the run. If it reports authentication, reconnect Timeline from Connections.',
            ],
            $latestRun?->status === 'running' && $latestRun->created_at->lt(now()->subHours(4)) => [
                'title' => 'A curation run appears to be stuck.',
                'message' => 'The run has remained open for more than four hours. Inspect the scheduled task before the next cycle.',
            ],
            $latestSuccessfulRun?->completed_at?->lt(now()->subHours(36)) => [
                'title' => 'Your Timeline has not refreshed recently.',
                'message' => 'No successful curation has completed in the last 36 hours. Check Scheduled and your Timeline connection.',
            ],
            default => null,
        };

        return view('timeline', [
            'stories' => $stories,
            'liveCursor' => $liveCursor,
            'curatorHealth' => $curatorHealth,
        ]);
    }
}
