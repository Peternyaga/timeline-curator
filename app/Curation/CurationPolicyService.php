<?php

namespace App\Curation;

use App\Models\AgentRun;
use App\Models\Directive;
use App\Models\FeedbackEvent;
use App\Models\Topic;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;

class CurationPolicyService
{
    public const RUNS_PER_DAY = 3;

    private const LEGACY_SIGNALS = [
        'Great source' => 'good_source',
        'More like this' => 'more_like_this',
        'SEO spam' => 'bad_source',
        'Outdated' => 'stale',
        'Paywalled' => 'inaccessible',
    ];

    public function __construct(private TenantContext $context) {}

    /** @return array<string, mixed> */
    public function context(): array
    {
        $topics = Topic::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'brief']);
        $directives = Directive::query()
            ->where('enabled', true)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()->get(['id', 'body', 'strength', 'structured_rules', 'expires_at']);
        $feedback = FeedbackEvent::query()
            ->with('storyCluster:id,tenant_id,feedback_tags')
            ->latest()
            ->limit(100)
            ->get();

        $payload = [
            'tenant_id' => $this->context->id(),
            'topics' => $topics->toArray(),
            'directives' => $directives->toArray(),
            'feedback_summary' => $this->summarizeFeedback($feedback),
            'limits' => [
                'runs_per_day' => self::RUNS_PER_DAY,
                'accepted_clusters_per_run' => 20,
                'sources_per_run' => 50,
                'sources_per_cluster' => 5,
                'summary_points_per_cluster' => ['min' => 1, 'max' => 6],
                'media_per_cluster' => 3,
                'feedback_tags_per_cluster' => ['min' => 4, 'max' => 6],
            ],
        ];

        $runsUsedToday = AgentRun::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return [
            ...$payload,
            'usage' => [
                'runs_used_today' => $runsUsedToday,
                'runs_remaining_today' => max(0, self::RUNS_PER_DAY - $runsUsedToday),
                'resets_at' => now()->addDay()->startOfDay()->toIso8601String(),
            ],
            'context_version' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'generated_at' => now()->toIso8601String(),
            'instructions' => [
                'Research with the Codex task web/search/browser capabilities; the Timeline API does not scrape.',
                'Adapt research methods and writing to the user’s topic, whether technical, cultural, local, practical, recreational, or news-driven.',
                'Search from multiple angles, inspect a broad candidate pool, and verify consequential or disputed claims with independent evidence when available.',
                'Prefer topic-appropriate primary and authoritative sources, obey robots, terms, and paywalls, and never bypass access controls.',
                'Return fewer or zero clusters when evidence does not meet the policy.',
                'Include relevant, attributable images or videos when they materially improve a story, and generate balanced story-specific feedback tags.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function summarizeFeedback(Collection $events): array
    {
        if ($events->count() < 3) {
            return ['sample_size' => $events->count(), 'stable_signal' => false];
        }

        $weighted = $events->map(function (FeedbackEvent $event): array {
            $ageDays = max(0, $event->created_at->diffInSeconds(now()) / 86400);

            return ['event' => $event, 'weight' => pow(0.5, $ageDays / 30)];
        });
        $weight = $weighted->sum('weight');

        $tagLabels = [];
        $signals = [];
        foreach ($weighted as $item) {
            $definitions = collect($item['event']->storyCluster?->feedback_tags ?? [])->keyBy('id');
            foreach ($item['event']->semantic_tags ?? [] as $storedTag) {
                $definition = $definitions->get($storedTag);
                $label = is_array($definition) ? ($definition['label'] ?? $storedTag) : $storedTag;
                $signal = is_array($definition)
                    ? ($definition['signal'] ?? null)
                    : (self::LEGACY_SIGNALS[$storedTag] ?? null);
                $tagLabels[$label] = ($tagLabels[$label] ?? 0) + $item['weight'];
                if ($signal) {
                    $signals[$signal] = ($signals[$signal] ?? 0) + $item['weight'];
                }
            }
        }
        arsort($tagLabels);
        arsort($signals);

        return [
            'sample_size' => $events->count(),
            'stable_signal' => true,
            'relevance_mean' => round($weighted->sum(fn ($item) => $item['event']->relevance_score * $item['weight']) / $weight, 2),
            'depth_mean' => round($weighted->sum(fn ($item) => $item['event']->depth_score * $item['weight']) / $weight, 2),
            'top_signals' => array_slice(array_keys($signals), 0, 8),
            'top_tag_labels' => array_slice(array_keys($tagLabels), 0, 8),
            // Compatibility for installed v0.1.x plugin tasks.
            'top_tags' => array_slice(array_keys($tagLabels), 0, 8),
            'recent_comments' => $events->whereNotNull('comment')->take(10)->pluck('comment')->values()->all(),
            'half_life_days' => 30,
        ];
    }
}
