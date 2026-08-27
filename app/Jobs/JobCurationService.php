<?php

namespace App\Jobs;

use App\Curation\CurationException;
use App\Models\AgentRun;
use App\Models\JobCuration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class JobCurationService
{
    private const SIGNALS = [
        'more_like_this', 'less_like_this', 'accurate_match', 'inaccurate_match',
        'good_source', 'bad_source', 'timely', 'stale', 'eligible', 'ineligible',
        'salary_fit', 'salary_mismatch',
    ];

    private const POSITIVE = ['more_like_this', 'accurate_match', 'good_source', 'timely', 'eligible', 'salary_fit'];

    private const CORRECTIVE = ['less_like_this', 'inaccurate_match', 'bad_source', 'stale', 'ineligible', 'salary_mismatch'];

    public function submit(AgentRun $run, string $contextVersion, array $jobs): array
    {
        $run = $run->fresh();
        if ($run->status !== 'running' || $run->context_version !== $contextVersion) {
            throw new CurationException('policy_changed', 'The run is not active for this policy version.');
        }
        if ($jobs === [] || count($jobs) > 10) {
            throw new CurationException('invalid_batch', 'A job batch must contain between 1 and 10 matches.');
        }

        $accepted = [];
        $rejected = [];
        $newAccepted = 0;
        foreach ($jobs as $job) {
            $clientId = is_array($job) ? (string) ($job['client_item_id'] ?? '') : '';
            try {
                $existing = JobCuration::query()->where('agent_run_id', $run->id)->where('client_item_id', $clientId)->first();
                $curation = $existing ?: $this->store($run, $job);
                $accepted[] = ['client_item_id' => $clientId, 'job_id' => $curation->id, 'idempotent' => (bool) $existing];
                $newAccepted += $existing ? 0 : 1;
            } catch (CurationException $exception) {
                $rejected[] = ['client_item_id' => $clientId, 'code' => $exception->errorCode, 'message' => $exception->getMessage()];
            } catch (Throwable) {
                $rejected[] = ['client_item_id' => $clientId, 'code' => 'invalid_job', 'message' => 'The job match could not be validated.'];
            }
        }

        $run->increment('job_accepted_count', $newAccepted);
        $run->increment('job_rejected_count', count($rejected));

        return compact('accepted', 'rejected');
    }

    private function store(AgentRun $run, mixed $input): JobCuration
    {
        if (! is_array($input) || $run->jobs()->count() >= 20) {
            throw new CurationException('quota_exceeded', 'The run job quota has been reached.');
        }

        $clientId = $this->text($input['client_item_id'] ?? null, 128, 'client_item_id');
        $title = $this->text($input['title'] ?? null, 255, 'title');
        $employer = $this->text($input['employer'] ?? null, 255, 'employer');
        $location = $this->optionalText($input['location'] ?? null, 255);
        $canonicalUrl = $this->httpsUrl($input['canonical_url'] ?? null, 'canonical_url');
        $channel = $input['application_channel'] ?? null;
        if (! in_array($channel, ['web', 'email'], true)) {
            throw new CurationException('invalid_job', 'Application channel must be web or email.');
        }
        $applicationUrl = $channel === 'web' ? $this->httpsUrl($input['application_url'] ?? null, 'application_url') : null;
        $applicationEmail = $channel === 'email' ? filter_var($input['application_email'] ?? null, FILTER_VALIDATE_EMAIL) : null;
        if ($channel === 'email' && ! $applicationEmail) {
            throw new CurationException('invalid_job', 'Email applications require a valid recipient.');
        }

        $postedAt = $this->date($input['posted_at'] ?? null);
        $deadlineAt = $this->date($input['deadline_at'] ?? null);
        if ($deadlineAt?->isPast()) {
            throw new CurationException('expired_job', 'The application deadline has passed.');
        }

        $summary = $this->stringList($input['summary_points'] ?? null, 1, 6, 'summary_points');
        $match = $this->stringList($input['match_points'] ?? null, 1, 6, 'match_points');
        $requirements = $this->stringList($input['requirements'] ?? [], 0, 12, 'requirements');
        $gaps = $this->stringList($input['gaps'] ?? [], 0, 6, 'gaps');
        $tags = $this->feedbackTags($input['feedback_tags'] ?? null);
        $sources = $this->sources($input['sources'] ?? null);
        $fingerprint = hash('sha256', Str::lower(trim($employer).'|'.trim($title).'|'.trim((string) $location).'|'.$canonicalUrl));
        if (JobCuration::query()->where('fingerprint', $fingerprint)->exists()) {
            throw new CurationException('duplicate', 'This job is already in the workspace.');
        }

        return DB::transaction(function () use ($run, $clientId, $title, $employer, $canonicalUrl, $applicationUrl, $channel, $applicationEmail, $location, $input, $summary, $requirements, $match, $gaps, $tags, $fingerprint, $postedAt, $deadlineAt, $sources): JobCuration {
            $job = $run->jobs()->create([
                'client_item_id' => $clientId, 'title' => $title, 'employer' => $employer,
                'canonical_url' => $canonicalUrl, 'application_url' => $applicationUrl,
                'application_channel' => $channel, 'application_email' => $applicationEmail,
                'location' => $location, 'workplace_type' => $this->optionalText($input['workplace_type'] ?? null, 32),
                'employment_type' => $this->optionalText($input['employment_type'] ?? null, 32),
                'salary' => $this->salary($input['salary'] ?? null),
                'summary_points' => $summary, 'requirements' => $requirements, 'match_points' => $match,
                'gaps' => $gaps, 'application_instructions' => $this->optionalText($input['application_instructions'] ?? null, 2000),
                'feedback_tags' => $tags, 'fingerprint' => $fingerprint, 'posted_at' => $postedAt,
                'deadline_at' => $deadlineAt, 'retrieved_at' => now(),
            ]);
            foreach ($sources as $source) {
                $job->sources()->create($source);
            }

            return $job;
        });
    }

    private function sources(mixed $sources): array
    {
        if (! is_array($sources) || count($sources) < 1 || count($sources) > 5) {
            throw new CurationException('invalid_source', 'Provide between one and five inspected sources.');
        }
        $normalized = array_map(function ($source): array {
            if (! is_array($source) || ! in_array($source['role'] ?? null, ['primary', 'supporting'], true)) {
                throw new CurationException('invalid_source', 'Each source needs a valid role.');
            }
            $url = $this->httpsUrl($source['url'] ?? null, 'source.url');

            return [
                'title' => $this->text($source['title'] ?? null, 255, 'source.title'),
                'url' => $url, 'domain' => parse_url($url, PHP_URL_HOST), 'role' => $source['role'],
                'published_at' => $this->date($source['published_at'] ?? null),
            ];
        }, array_values($sources));
        if (count(array_filter($normalized, fn ($source) => $source['role'] === 'primary')) !== 1) {
            throw new CurationException('invalid_source', 'Exactly one source must be primary.');
        }

        return $normalized;
    }

    private function feedbackTags(mixed $tags): array
    {
        if (! is_array($tags) || count($tags) < 4 || count($tags) > 6) {
            throw new CurationException('invalid_feedback_tag', 'Provide between four and six job feedback tags.');
        }
        $normalized = [];
        foreach ($tags as $tag) {
            $id = is_array($tag) ? ($tag['id'] ?? null) : null;
            $signal = is_array($tag) ? ($tag['signal'] ?? null) : null;
            if (! is_string($id) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) || ! in_array($signal, self::SIGNALS, true)) {
                throw new CurationException('invalid_feedback_tag', 'Job feedback tags require unique lower-case slugs and valid signals.');
            }
            $normalized[] = ['id' => $id, 'label' => $this->text($tag['label'] ?? null, 48, 'feedback_tag.label'), 'signal' => $signal];
        }
        if (count(array_unique(array_column($normalized, 'id'))) !== count($normalized)
            || ! array_intersect(array_column($normalized, 'signal'), self::POSITIVE)
            || ! array_intersect(array_column($normalized, 'signal'), self::CORRECTIVE)) {
            throw new CurationException('invalid_feedback_tag', 'Job feedback tags must be unique and include positive and corrective choices.');
        }

        return $normalized;
    }

    private function stringList(mixed $value, int $min, int $max, string $field): array
    {
        if (! is_array($value) || count($value) < $min || count($value) > $max) {
            throw new CurationException('invalid_job', "$field has an invalid number of items.");
        }

        return array_map(fn ($item) => $this->text($item, 600, $field), array_values($value));
    }

    private function salary(mixed $salary): ?array
    {
        if ($salary === null) {
            return null;
        }
        if (! is_array($salary)) {
            throw new CurationException('invalid_job', 'salary must be an object or null.');
        }
        $currency = strtoupper(trim((string) ($salary['currency'] ?? '')));
        $period = $salary['period'] ?? null;
        $minimum = $salary['min'] ?? null;
        $maximum = $salary['max'] ?? null;
        if (($currency !== '' && ! preg_match('/^[A-Z]{3}$/', $currency))
            || ($period !== null && ! in_array($period, ['hour', 'month', 'year'], true))
            || ($minimum !== null && (! is_numeric($minimum) || $minimum < 0))
            || ($maximum !== null && (! is_numeric($maximum) || $maximum < 0))
            || ($minimum !== null && $maximum !== null && $maximum < $minimum)) {
            throw new CurationException('invalid_job', 'salary contains invalid currency, period, or range values.');
        }

        return [
            'currency' => $currency ?: null,
            'min' => $minimum !== null ? (float) $minimum : null,
            'max' => $maximum !== null ? (float) $maximum : null,
            'period' => $period,
            'disclosed' => (bool) ($salary['disclosed'] ?? true),
        ];
    }

    private function text(mixed $value, int $max, string $field): string
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max) {
            throw new CurationException('invalid_job', "$field is invalid.");
        }

        return trim($value);
    }

    private function optionalText(mixed $value, int $max): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
    }

    private function httpsUrl(mixed $value, string $field): string
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL) || parse_url($value, PHP_URL_SCHEME) !== 'https') {
            throw new CurationException('invalid_source', "$field must be a public HTTPS URL.");
        }
        $host = (string) parse_url($value, PHP_URL_HOST);
        if ($host === '' || $host === 'localhost' || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw new CurationException('invalid_source', "$field must not target a private address.");
        }

        return $value;
    }

    private function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new CurationException('invalid_job', 'Job dates must use RFC3339.');
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw new CurationException('invalid_job', 'Job dates must use RFC3339.');
        }
    }
}
