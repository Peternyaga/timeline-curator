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
        $bullets = $input['technical_bullets'] ?? null;
        if (! is_array($bullets) || count($bullets) !== 3) {
            throw new CurationException('invalid_story', 'Exactly three technical bullets are required.');
        }
        $bullets = array_map(fn ($bullet) => $this->text($bullet, 600, 'technical_bullet'), array_values($bullets));
        $sources = $input['sources'] ?? null;
        if (! is_array($sources) || $sources === [] || count($sources) > 5) {
            throw new CurationException('invalid_source', 'Provide between one and five sources.');
        }

        $normalizedSources = array_map(fn ($source) => $this->normalizeSource($source), $sources);
        if (collect($normalizedSources)->where('role', 'primary')->count() !== 1) {
            throw new CurationException('invalid_source', 'Exactly one primary source is required.');
        }
        $coveredBullets = collect($normalizedSources)->flatMap(fn ($source) => $source['supports_bullets'])->unique()->sort()->values()->all();
        if ($coveredBullets !== [1, 2, 3]) {
            throw new CurationException('invalid_source', 'The source evidence must collectively cover all three bullets.');
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

        return DB::transaction(function () use ($run, $clientId, $title, $bullets, $input, $fingerprint, $normalizedSources): StoryCluster {
            $cluster = StoryCluster::query()->create([
                'agent_run_id' => $run->id,
                'client_item_id' => $clientId,
                'title' => $title,
                'technical_bullets' => $bullets,
                'why_it_matters' => isset($input['why_it_matters']) ? $this->text($input['why_it_matters'], 1200, 'why_it_matters') : null,
                'fingerprint' => $fingerprint,
                'published_at' => now(),
            ]);
            foreach ($normalizedSources as $source) {
                $cluster->sources()->create($source);
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
        $url = trim((string) ($source['url'] ?? ''));
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new CurationException('invalid_source', 'Sources must use absolute HTTPS URLs.');
        }
        $domain = strtolower(rtrim($parts['host'], '.'));
        if ($domain === 'localhost' || $domain === 'localhost.localdomain' || $this->isPrivateIp($domain)) {
            throw new CurationException('invalid_source', 'Local and private-network source URLs are not allowed.');
        }
        $supports = $source['supports_bullets'] ?? [];
        if (! is_array($supports) || $supports === [] || collect($supports)->contains(fn ($index) => ! in_array($index, [1, 2, 3], true))) {
            throw new CurationException('invalid_source', 'Each source must cite one or more bullet numbers from 1 to 3.');
        }

        return [
            'title' => $this->text($source['title'] ?? null, 255, 'source.title'),
            'url' => $url,
            'domain' => $domain,
            'role' => ($source['role'] ?? null) === 'primary' ? 'primary' : 'supporting',
            'published_at' => $source['published_at'] ?? null,
            'supports_bullets' => array_values(array_unique($supports)),
        ];
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
