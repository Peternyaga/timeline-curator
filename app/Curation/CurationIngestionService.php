<?php

namespace App\Curation;

use App\Models\AgentRun;
use App\Models\Directive;
use App\Models\StoryCluster;
use App\Models\StorySource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CurationIngestionService
{
    private const FEEDBACK_SIGNALS = [
        'more_like_this',
        'less_like_this',
        'good_source',
        'bad_source',
        'useful_depth',
        'wrong_depth',
        'timely',
        'stale',
        'novel',
        'already_known',
        'accessible',
        'inaccessible',
    ];

    private const POSITIVE_SIGNALS = [
        'more_like_this',
        'good_source',
        'useful_depth',
        'timely',
        'novel',
        'accessible',
    ];

    private const CORRECTIVE_SIGNALS = [
        'less_like_this',
        'bad_source',
        'wrong_depth',
        'stale',
        'already_known',
        'inaccessible',
    ];

    public function __construct(private CurationPolicyService $policy) {}

    public function begin(string $contextVersion, array $queries, ?string $skillVersion = null): AgentRun
    {
        $this->assertCurrentPolicy($contextVersion);
        if ($queries === [] || count($queries) > 20 || collect($queries)->contains(fn ($query) => ! is_string($query) || trim($query) === '')) {
            throw new CurationException('invalid_query', 'Provide between 1 and 20 non-empty exact queries.');
        }
        if (AgentRun::query()->where('created_at', '>=', now()->startOfDay())->count() >= CurationPolicyService::RUNS_PER_DAY) {
            throw new CurationException('quota_exceeded', 'The daily run quota has been reached.');
        }

        return AgentRun::query()->create([
            'context_version' => $contextVersion,
            'exact_queries' => array_values($queries),
            'skill_version' => $skillVersion,
        ]);
    }

    /** @return array{accepted: list<array<string, string>>, rejected: list<array<string, string>>} */
    public function submit(string $runId, string $contextVersion, array $stories): array
    {
        $this->assertCurrentPolicy($contextVersion);
        $run = AgentRun::query()->findOrFail($runId);
        if ($run->status !== 'running' || $run->context_version !== $contextVersion) {
            throw new CurationException('policy_changed', 'The run is not active for this policy version.');
        }
        if ($stories === [] || count($stories) > 10) {
            throw new CurationException('invalid_batch', 'A batch must contain between 1 and 10 clusters.');
        }

        $accepted = [];
        $rejected = [];
        $newAccepted = 0;
        foreach ($stories as $story) {
            $clientId = is_array($story) ? (string) ($story['client_item_id'] ?? '') : '';
            try {
                $existed = StoryCluster::query()->where('agent_run_id', $run->id)->where('client_item_id', $clientId)->exists();
                $cluster = $this->storeStory($run, $story);
                $accepted[] = ['client_item_id' => $clientId, 'story_id' => $cluster->id, 'idempotent' => $existed];
                $newAccepted += $existed ? 0 : 1;
            } catch (CurationException $e) {
                $rejected[] = ['client_item_id' => $clientId, 'code' => $e->errorCode, 'message' => $e->getMessage()];
            } catch (Throwable) {
                $rejected[] = ['client_item_id' => $clientId, 'code' => 'invalid_story', 'message' => 'The cluster could not be validated.'];
            }
        }

        $run->increment('accepted_count', $newAccepted);
        $run->increment('rejected_count', count($rejected));

        return compact('accepted', 'rejected');
    }

    public function complete(string $runId, string $status = 'completed'): AgentRun
    {
        $run = AgentRun::query()->findOrFail($runId);
        if (! in_array($status, ['completed', 'completed_empty', 'failed'], true)) {
            throw new CurationException('invalid_status', 'Completion status is invalid.');
        }
        $run->update(['status' => $status, 'completed_at' => now()]);

        return $run->fresh();
    }

    private function storeStory(AgentRun $run, mixed $input): StoryCluster
    {
        if (! is_array($input)) {
            throw new CurationException('invalid_story', 'A story cluster must be an object.');
        }
        if ($run->stories()->count() >= 20) {
            throw new CurationException('quota_exceeded', 'The run cluster quota has been reached.');
        }
        if (StoryCluster::query()->count() >= 2000) {
            throw new CurationException('quota_exceeded', 'The tenant storage quota has been reached.');
        }

        $clientId = $this->text($input['client_item_id'] ?? null, 128, 'client_item_id');
        if ($existing = StoryCluster::query()->where('agent_run_id', $run->id)->where('client_item_id', $clientId)->first()) {
            return $existing;
        }

        $title = $this->text($input['title'] ?? null, 255, 'title');
        $summaryPoints = $input['summary_points'] ?? $input['technical_bullets'] ?? null;
        if (! is_array($summaryPoints) || count($summaryPoints) < 1 || count($summaryPoints) > 6) {
            throw new CurationException('invalid_story', 'Provide between one and six summary points.');
        }
        $summaryPoints = array_map(
            fn ($point) => $this->text($point, 600, 'summary_point'),
            array_values($summaryPoints),
        );
        $sources = $input['sources'] ?? null;
        if (! is_array($sources) || $sources === [] || count($sources) > 5) {
            throw new CurationException('invalid_source', 'Provide between one and five sources.');
        }

        $normalizedSources = array_map(fn ($source) => $this->normalizeSource($source), $sources);
        if (collect($normalizedSources)->where('role', 'primary')->count() !== 1) {
            throw new CurationException('invalid_source', 'Exactly one primary source is required.');
        }
        $sourceCount = StorySource::query()->whereHas('storyCluster', fn ($query) => $query->where('agent_run_id', $run->id))->count();
        if ($sourceCount + count($normalizedSources) > 50) {
            throw new CurationException('quota_exceeded', 'The run source quota has been reached.');
        }

        $blockedDomains = Directive::query()->where('enabled', true)->where('strength', 'hard')->get()
            ->flatMap(fn (Directive $directive) => $directive->structured_rules['blocked_domains'] ?? [])->map(fn ($domain) => strtolower($domain))->all();
        foreach ($normalizedSources as $source) {
            if (collect($blockedDomains)->contains(fn ($blocked) => $source['domain'] === $blocked || str_ends_with($source['domain'], '.'.$blocked))) {
                throw new CurationException('rule_violation', "Source domain {$source['domain']} is blocked by a hard directive.");
            }
            if (StorySource::query()->where('url', $source['url'])->exists()) {
                throw new CurationException('duplicate', 'A source URL is already present in this timeline.');
            }
        }

        $fingerprint = hash('sha256', Str::lower(preg_replace('/\s+/', ' ', $title)));
        if (StoryCluster::query()->where('fingerprint', $fingerprint)->exists()) {
            throw new CurationException('duplicate', 'A matching story cluster already exists.');
        }

        $feedbackTags = $this->normalizeFeedbackTags($input['feedback_tags'] ?? null);
        $media = $this->normalizeMedia($input['media'] ?? []);

        return DB::transaction(function () use ($run, $clientId, $title, $summaryPoints, $input, $fingerprint, $normalizedSources, $feedbackTags, $media): StoryCluster {
            $cluster = StoryCluster::query()->create([
                'agent_run_id' => $run->id,
                'client_item_id' => $clientId,
                'title' => $title,
                // Keep the legacy column populated while older plugin builds remain installed.
                'technical_bullets' => $summaryPoints,
                'summary_points' => $summaryPoints,
                'why_it_matters' => isset($input['why_it_matters']) ? $this->text($input['why_it_matters'], 1200, 'why_it_matters') : null,
                'feedback_tags' => $feedbackTags,
                'fingerprint' => $fingerprint,
                'published_at' => now(),
            ]);
            foreach ($normalizedSources as $source) {
                $cluster->sources()->create($source);
            }
            foreach ($media as $item) {
                $cluster->media()->create($item);
            }

            return $cluster;
        });
    }

    /** @return array<string, mixed> */
    private function normalizeSource(mixed $source): array
    {
        if (! is_array($source)) {
            throw new CurationException('invalid_source', 'Each source must be an object.');
        }
        [$url, $domain] = $this->publicHttpsUrl($source['url'] ?? null, 'invalid_source', 'source.url');

        return [
            'title' => $this->text($source['title'] ?? null, 255, 'source.title'),
            'url' => $url,
            'domain' => $domain,
            'role' => ($source['role'] ?? null) === 'primary' ? 'primary' : 'supporting',
            'published_at' => $source['published_at'] ?? null,
            'supports_bullets' => null,
        ];
    }

    /** @return list<array<string, string>>|null */
    private function normalizeFeedbackTags(mixed $tags): ?array
    {
        // Legacy plugin builds did not send story-specific feedback tags.
        if ($tags === null) {
            return null;
        }
        if (! is_array($tags) || count($tags) < 4 || count($tags) > 6) {
            throw new CurationException('invalid_feedback_tag', 'Provide between four and six feedback tags.');
        }

        $normalized = [];
        foreach ($tags as $tag) {
            if (! is_array($tag)) {
                throw new CurationException('invalid_feedback_tag', 'Each feedback tag must be an object.');
            }
            $id = trim((string) ($tag['id'] ?? ''));
            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) || strlen($id) > 64) {
                throw new CurationException('invalid_feedback_tag', 'Feedback tag ids must be unique lower-case slugs of at most 64 characters.');
            }
            $signal = (string) ($tag['signal'] ?? '');
            if (! in_array($signal, self::FEEDBACK_SIGNALS, true)) {
                throw new CurationException('invalid_feedback_tag', 'Feedback tag signal is invalid.');
            }
            $normalized[] = [
                'id' => $id,
                'label' => $this->text($tag['label'] ?? null, 48, 'feedback_tag.label'),
                'signal' => $signal,
            ];
        }

        if (collect($normalized)->pluck('id')->unique()->count() !== count($normalized)) {
            throw new CurationException('invalid_feedback_tag', 'Feedback tag ids must be unique within a story.');
        }
        $signals = collect($normalized)->pluck('signal');
        if (! $signals->contains(fn ($signal) => in_array($signal, self::POSITIVE_SIGNALS, true))
            || ! $signals->contains(fn ($signal) => in_array($signal, self::CORRECTIVE_SIGNALS, true))) {
            throw new CurationException('invalid_feedback_tag', 'Feedback tags must include positive and corrective choices.');
        }

        return $normalized;
    }

    /** @return list<array<string, mixed>> */
    private function normalizeMedia(mixed $media): array
    {
        if (! is_array($media) || count($media) > 3) {
            throw new CurationException('invalid_media', 'Media must be an array containing at most three items.');
        }

        $normalized = [];
        foreach (array_values($media) as $position => $item) {
            if (! is_array($item) || ! in_array($item['type'] ?? null, ['image', 'video'], true)) {
                throw new CurationException('invalid_media', 'Each media item must be an image or video object.');
            }
            [$url] = $this->publicHttpsUrl($item['url'] ?? null, 'invalid_media', 'media.url');
            [$sourceUrl] = $this->publicHttpsUrl($item['source_url'] ?? null, 'invalid_media', 'media.source_url');
            $thumbnailUrl = null;
            if (isset($item['thumbnail_url'])) {
                [$thumbnailUrl] = $this->publicHttpsUrl($item['thumbnail_url'], 'invalid_media', 'media.thumbnail_url');
            }

            $provider = null;
            $providerId = null;
            if ($item['type'] === 'video') {
                [$provider, $providerId] = $this->videoProvider($url);
            }

            $normalized[] = [
                'media_type' => $item['type'],
                'url' => $url,
                'provider' => $provider,
                'provider_id' => $providerId,
                'thumbnail_url' => $thumbnailUrl,
                'caption' => $this->text($item['caption'] ?? null, 500, 'media.caption'),
                'alt_text' => $this->text($item['alt_text'] ?? null, 500, 'media.alt_text'),
                'credit' => $this->text($item['credit'] ?? null, 255, 'media.credit'),
                'source_url' => $sourceUrl,
                'position' => $position,
            ];
        }

        return $normalized;
    }

    /** @return array{0: string, 1: string} */
    private function publicHttpsUrl(mixed $value, string $code, string $field): array
    {
        $url = trim((string) $value);
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new CurationException($code, "$field must be an absolute HTTPS URL.");
        }
        $domain = strtolower(rtrim($parts['host'], '.'));
        if ($domain === 'localhost' || $domain === 'localhost.localdomain' || $this->isPrivateIp($domain)) {
            throw new CurationException($code, "$field cannot use a local or private-network host.");
        }

        return [$url, $domain];
    }

    /** @return array{0: string, 1: string|null} */
    private function videoProvider(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $id = $query['v'] ?? (preg_match('#^(?:shorts|embed)/([^/]+)#', $path, $match) ? $match[1] : null);
            if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,32}$/', $id)) {
                return ['youtube', $id];
            }
        }
        if ($host === 'youtu.be' && preg_match('/^([A-Za-z0-9_-]{6,32})/', $path, $match)) {
            return ['youtube', $match[1]];
        }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true) && preg_match('/^(\d+)/', $path, $match)) {
            return ['vimeo', $match[1]];
        }
        if (preg_match('/\.(?:mp4|webm)$/i', $parts['path'] ?? '')) {
            return ['direct', null];
        }

        throw new CurationException('invalid_media', 'Video URLs must be direct MP4/WebM files or recognized YouTube/Vimeo pages.');
    }

    private function text(mixed $value, int $max, string $field): string
    {
        $value = trim(strip_tags((string) $value));
        if ($value === '' || mb_strlen($value) > $max) {
            throw new CurationException('invalid_story', "$field is required and must be at most $max characters.");
        }

        return $value;
    }

    private function assertCurrentPolicy(string $version): void
    {
        if (! hash_equals($this->policy->context()['context_version'], $version)) {
            throw new CurationException('policy_changed', 'The curation context has changed; retrieve it again before continuing.');
        }
    }

    private function isPrivateIp(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
