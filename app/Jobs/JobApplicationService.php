<?php

namespace App\Jobs;

use App\Curation\CurationException;
use App\Models\ApplicationEvent;
use App\Models\ApplicationQuestionnaire;
use App\Models\JobApplication;
use App\Models\JobCuration;
use App\Models\JobProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class JobApplicationService
{
    private const FIELD_TYPES = [
        'short_text', 'long_text', 'email', 'phone', 'number', 'date',
        'single_select', 'multi_select', 'boolean', 'file',
    ];

    private const CLASSIFICATIONS = ['normal', 'sensitive', 'legal'];

    private const MATERIAL_MIME_TYPES = [
        'text/plain', 'text/markdown', 'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function approve(JobCuration $job, JobProfile $profile): JobApplication
    {
        if (! $profile->searchReady()) {
            throw new CurationException('profile_incomplete', 'Complete the role and location sections before approving an application.');
        }
        if ($job->deadline_at?->isPast()) {
            throw new CurationException('expired_job', 'This job application deadline has passed.');
        }
        if ($job->applications()->whereIn('status', ['queued', 'leased', 'needs_information', 'ready_to_resume', 'needs_manual_action', 'submitted', 'attempted_unconfirmed'])->exists()) {
            throw new CurationException('already_approved', 'This job already has an active or completed application.');
        }

        $application = $job->applications()->create([
            'status' => 'queued',
            'channel' => $job->application_channel,
            'listing_snapshot' => $this->listingSnapshot($job),
            'profile_snapshot' => $profile->applicationSnapshot(),
            'profile_version' => $profile->profile_version,
            'approved_at' => now(),
        ]);
        $this->event($application, 'approved', ['channel' => $application->channel]);

        return $application;
    }

    public function claim(string $clientAttemptId): ?array
    {
        return DB::transaction(function () use ($clientAttemptId): ?array {
            JobApplication::query()
                ->where('status', 'leased')
                ->where('lease_expires_at', '<=', now())
                ->update([
                    'status' => 'queued', 'client_attempt_id' => null, 'claim_token_hash' => null,
                    'leased_at' => null, 'lease_expires_at' => null,
                ]);

            if ($existing = JobApplication::query()->where('client_attempt_id', $clientAttemptId)->first()) {
                if ($existing->status !== 'leased' || ! $existing->lease_expires_at?->isFuture()) {
                    return ['application_id' => $existing->id, 'status' => $existing->status, 'idempotent' => true];
                }

                return $this->claimPacket($existing, $this->claimToken($existing, $clientAttemptId), true);
            }

            $application = JobApplication::query()
                ->whereIn('status', ['queued', 'ready_to_resume'])
                ->oldest('approved_at')
                ->lockForUpdate()
                ->first();
            if (! $application) {
                return null;
            }

            $token = $this->claimToken($application, $clientAttemptId);
            $application->update([
                'status' => 'leased', 'client_attempt_id' => $clientAttemptId,
                'claim_token_hash' => hash('sha256', $token), 'leased_at' => now(),
                'lease_expires_at' => now()->addMinutes(30),
            ]);
            $this->event($application, 'claimed', ['client_attempt_id' => $clientAttemptId]);

            return $this->claimPacket($application, $token);
        });
    }

    public function assertClaim(JobApplication $application, string $claimToken): void
    {
        if ($application->status !== 'leased'
            || ! $application->lease_expires_at?->isFuture()
            || ! hash_equals((string) $application->claim_token_hash, hash('sha256', $claimToken))) {
            throw new CurationException('invalid_claim', 'The application claim is invalid or expired.');
        }
    }

    public function saveMaterials(JobApplication $application, string $claimToken, array $materials): array
    {
        $this->assertClaim($application, $claimToken);
        if ($materials === [] || count($materials) > 5) {
            throw new CurationException('invalid_material', 'Provide between one and five application materials.');
        }

        $stored = [];
        foreach ($materials as $material) {
            if (! is_array($material) || ! in_array($material['kind'] ?? null, ['resume', 'cover_letter', 'email_body', 'supporting', 'answer_attachment'], true)) {
                throw new CurationException('invalid_material', 'Application material kind is invalid.');
            }
            $mimeType = (string) ($material['mime_type'] ?? 'text/plain');
            if (! in_array($mimeType, self::MATERIAL_MIME_TYPES, true)) {
                throw new CurationException('invalid_material', 'Application material type is not supported.');
            }
            $content = (string) ($material['content'] ?? '');
            $encoding = $material['encoding'] ?? 'utf8';
            if ($encoding === 'base64') {
                $decoded = base64_decode($content, true);
                if ($decoded === false || strlen($decoded) > 5 * 1024 * 1024) {
                    throw new CurationException('invalid_material', 'Encoded material is invalid or exceeds 5 MB.');
                }
            } elseif ($content === '' || strlen($content) > 250000) {
                throw new CurationException('invalid_material', 'Text material is empty or too large.');
            }

            $paths = $material['fact_paths'] ?? [];
            if (! is_array($paths) || $paths === []) {
                throw new CurationException('invalid_material', 'Generated materials require profile fact paths.');
            }
            foreach ($paths as $path) {
                if (! is_string($path)
                    || ! preg_match('/^(search_preferences|personal_details|career_details|reusable_answers)\./', $path)
                    || data_get($application->profile_snapshot, $path) === null) {
                    throw new CurationException('invalid_material', 'A material references a profile fact that was not approved.');
                }
            }

            $record = $application->materials()->create([
                'kind' => $material['kind'],
                'filename' => isset($material['filename']) ? Str::limit((string) $material['filename'], 255, '') : null,
                'mime_type' => $mimeType,
                'content' => $encoding === 'base64' ? $content : base64_encode($content),
                'fact_paths' => array_values(array_unique($paths)),
                'profile_version' => $application->profile_version,
            ]);
            $stored[] = ['material_id' => $record->id, 'kind' => $record->kind];
        }

        $this->event($application, 'materials_saved', ['count' => count($stored)]);

        return $stored;
    }

    public function requestInformation(JobApplication $application, string $claimToken, array $fields, ?string $message = null): ApplicationQuestionnaire
    {
        $this->assertClaim($application, $claimToken);
        if ($fields === [] || count($fields) > 20) {
            throw new CurationException('invalid_questionnaire', 'Provide between one and twenty fields.');
        }

        $ids = [];
        $normalized = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                throw new CurationException('invalid_questionnaire', 'Each generated field must be an object.');
            }
            $id = (string) ($field['id'] ?? '');
            $type = $field['type'] ?? null;
            $classification = $field['classification'] ?? 'normal';
            $label = trim((string) ($field['label'] ?? ''));
            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id)
                || in_array($id, $ids, true)
                || ! in_array($type, self::FIELD_TYPES, true)
                || ! in_array($classification, self::CLASSIFICATIONS, true)
                || $label === '' || mb_strlen($label) > 240) {
                throw new CurationException('invalid_questionnaire', 'A generated field is invalid.');
            }
            if (preg_match('/password|passcode|one.?time|otp|captcha|bank|card|payment|social security|national id|passport number/i', $label)) {
                throw new CurationException('unsupported_secret', 'Timeline cannot collect credentials, CAPTCHA, payment, or full identity numbers.');
            }
            $choices = $field['choices'] ?? [];
            if (in_array($type, ['single_select', 'multi_select'], true)
                && (! is_array($choices) || count($choices) < 2 || count($choices) > 30)) {
                throw new CurationException('invalid_questionnaire', 'Select fields require between two and thirty choices.');
            }
            if (collect($choices)->contains(fn ($choice) => ! is_string($choice) || trim($choice) === '' || mb_strlen($choice) > 120)) {
                throw new CurationException('invalid_questionnaire', 'Questionnaire choices must be short text values.');
            }

            $ids[] = $id;
            $normalized[] = [
                'id' => $id, 'type' => $type, 'classification' => $classification,
                'label' => $label, 'help_text' => Str::limit((string) ($field['help_text'] ?? ''), 500, ''),
                'required' => (bool) ($field['required'] ?? false),
                'choices' => array_values(array_map(fn ($choice) => Str::limit((string) $choice, 120, ''), $choices)),
            ];
        }

        $questionnaire = $application->questionnaires()->create([
            'schema_payload' => ['message' => Str::limit((string) $message, 1000, ''), 'fields' => $normalized],
            'status' => 'pending', 'requested_at' => now(),
        ]);
        $application->update([
            'status' => 'needs_information', 'claim_token_hash' => null,
            'leased_at' => null, 'lease_expires_at' => null,
        ]);
        $this->event($application, 'information_requested', ['questionnaire_id' => $questionnaire->id, 'field_count' => count($normalized)]);
        $this->sendActionRequiredEmail($application);

        return $questionnaire;
    }

    public function recordOutcome(JobApplication $application, string $claimToken, string $status, array $evidence = [], ?string $reason = null): JobApplication
    {
        $this->assertClaim($application, $claimToken);
        if (! in_array($status, ['submitted', 'attempted_unconfirmed', 'needs_manual_action', 'failed'], true)) {
            throw new CurationException('invalid_outcome', 'Application outcome is invalid.');
        }
        if ($status === 'submitted') {
            $confirmationUrl = $evidence['confirmation_url'] ?? null;
            $recipient = $evidence['recipient'] ?? null;
            $valid = $application->channel === 'web'
                ? filter_var($confirmationUrl, FILTER_VALIDATE_URL) && parse_url($confirmationUrl, PHP_URL_SCHEME) === 'https' && filled($evidence['confirmed_at'] ?? null)
                : filter_var($recipient, FILTER_VALIDATE_EMAIL)
                    && strcasecmp((string) $recipient, (string) ($application->listing_snapshot['application_email'] ?? '')) === 0
                    && filled($evidence['provider_message_id'] ?? null) && filled($evidence['sent_at'] ?? null);
            if (! $valid) {
                throw new CurationException('missing_confirmation', 'Submitted applications require channel-specific confirmation evidence.');
            }
        }
        if ($status === 'needs_manual_action' && blank($reason)) {
            throw new CurationException('invalid_outcome', 'Manual action requires a clear reason.');
        }

        $application->update([
            'status' => $status, 'manual_reason' => $reason,
            'claim_token_hash' => null, 'leased_at' => null, 'lease_expires_at' => null,
            'completed_at' => in_array($status, ['submitted', 'attempted_unconfirmed', 'failed'], true) ? now() : null,
        ]);
        $this->event($application, 'outcome_recorded', ['status' => $status, 'evidence' => $evidence, 'reason' => $reason]);
        if ($status === 'needs_manual_action') {
            $this->sendActionRequiredEmail($application);
        }

        return $application->fresh();
    }

    private function event(JobApplication $application, string $event, array $metadata = []): ApplicationEvent
    {
        return $application->events()->create(['event' => $event, 'metadata' => $metadata ?: null]);
    }

    private function listingSnapshot(JobCuration $job): array
    {
        $job->loadMissing('sources');

        return [
            'job_id' => $job->id,
            'title' => $job->title,
            'employer' => $job->employer,
            'canonical_url' => $job->canonical_url,
            'application_url' => $job->application_url,
            'application_email' => $job->application_email,
            'application_channel' => $job->application_channel,
            'location' => $job->location,
            'workplace_type' => $job->workplace_type,
            'employment_type' => $job->employment_type,
            'salary' => $job->salary,
            'summary_points' => $job->summary_points,
            'requirements' => $job->requirements,
            'match_points' => $job->match_points,
            'gaps' => $job->gaps,
            'application_instructions' => $job->application_instructions,
            'posted_at' => $job->posted_at?->toIso8601String(),
            'deadline_at' => $job->deadline_at?->toIso8601String(),
            'sources' => $job->sources->map(fn ($source) => [
                'title' => $source->title,
                'url' => $source->url,
                'role' => $source->role,
                'published_at' => $source->published_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function claimToken(JobApplication $application, string $clientAttemptId): string
    {
        return hash_hmac('sha256', $application->tenant_id.'|'.$application->id.'|'.$clientAttemptId, (string) config('app.key'));
    }

    private function claimPacket(JobApplication $application, string $token, bool $idempotent = false): array
    {
        return [
            'application_id' => $application->id,
            'claim_token' => $token,
            'lease_expires_at' => $application->lease_expires_at->toIso8601String(),
            'channel' => $application->channel,
            'listing' => $application->listing_snapshot,
            'profile' => $application->profile_snapshot,
            'questionnaire_answers' => $application->questionnaires()
                ->where('status', 'answered')->latest()->get(['id', 'answer_payload'])->toArray(),
            'idempotent' => $idempotent,
        ];
    }

    private function sendActionRequiredEmail(JobApplication $application): void
    {
        $user = User::query()->where('tenant_id', $application->tenant_id)->first();
        if (! $user) {
            return;
        }
        rescue(fn () => Mail::raw(
            'An approved job application needs your attention. Sign in to Timeline and open Jobs > Applications. No application answers are included in this email.',
            fn ($mail) => $mail->to($user->email)->subject('Action required for a job application'),
        ), report: false);
    }
}
