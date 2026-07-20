<?php

namespace App\Curation;

use App\Models\Directive;
use App\Models\FeedbackEvent;
use App\Models\Topic;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;

class CurationPolicyService
{
    public function __construct(private TenantContext $context) {}

    /** @return array<string, mixed> */
    public function context(): array
    {
        $topics = Topic::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'brief']);
        $directives = Directive::query()
            ->where('enabled', true)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()->get(['id', 'body', 'strength', 'structured_rules', 'expires_at']);
        $feedback = FeedbackEvent::query()->latest()->limit(100)->get();

        $payload = [
            'tenant_id' => $this->context->id(),
            'topics' => $topics->toArray(),
            'directives' => $directives->toArray(),
            'feedback_summary' => $this->summarizeFeedback($feedback),
            'limits' => [
                'runs_per_day' => 3,
                'accepted_clusters_per_run' => 20,
                'sources_per_run' => 50,
                'sources_per_cluster' => 5,
            ],
        ];

        return [
            ...$payload,
            'context_version' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'generated_at' => now()->toIso8601String(),
            'instructions' => [
                'Research with the Codex task web/search/browser capabilities; the Timeline API does not scrape.',
                'Prefer primary and authoritative sources, obey robots, terms, and paywalls, and never bypass access controls.',
                'Return fewer or zero clusters when evidence does not meet the policy.',
                'Every cluster must contain exactly three technical bullets with source-to-bullet evidence mapping.',
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

        $tags = [];
        foreach ($weighted as $item) {
            foreach ($item['event']->semantic_tags ?? [] as $tag) {
                $tags[$tag] = ($tags[$tag] ?? 0) + $item['weight'];
            }
        }
        arsort($tags);

        return [
            'sample_size' => $events->count(),
            'stable_signal' => true,
            'relevance_mean' => round($weighted->sum(fn ($item) => $item['event']->relevance_score * $item['weight']) / $weight, 2),
            'depth_mean' => round($weighted->sum(fn ($item) => $item['event']->depth_score * $item['weight']) / $weight, 2),
            'top_tags' => array_slice(array_keys($tags), 0, 8),
            'recent_comments' => $events->whereNotNull('comment')->take(10)->pluck('comment')->values()->all(),
            'half_life_days' => 30,
        ];
    }
}
