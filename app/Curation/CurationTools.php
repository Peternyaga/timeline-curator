<?php

namespace App\Curation;

use App\Jobs\JobApplicationService;
use App\Jobs\JobCurationService;
use App\Jobs\JobProfileDocumentService;
use App\Models\AgentRun;
use App\Models\JobApplication;
use App\Models\JobProfileDocument;
use App\Tenancy\TenantContext;
use Mcp\Exception\ToolCallException;

class CurationTools
{
    public function __construct(
        private TenantContext $context,
        private CurationPolicyService $policy,
        private CurationIngestionService $ingestion,
        private JobCurationService $jobCuration,
        private JobApplicationService $applications,
        private JobProfileDocumentService $documents,
    ) {}

    /** @return array<string, mixed> */
    public function getCurationContext(?string $plugin_version = null): array
    {
        return $this->call(function () use ($plugin_version): array {
            $this->requirePermission('read:curation-context');

            $result = $this->policy->context($plugin_version);
            if (! $this->context->hasPermission('read:job-search-context')) {
                $result['job_search'] = [
                    'authorized' => false,
                    'message' => 'Reconnect Timeline with read:job-search-context to use the job companion.',
                ];
                unset($result['application_queue']);
            } else {
                $result['job_search']['authorized'] = true;
            }

            return $result;
        });
    }

    /** @return array<string, mixed> */
    public function beginCurationRun(string $context_version, array $exact_queries = [], ?string $skill_version = null, array $job_queries = []): array
    {
        return $this->call(function () use ($context_version, $exact_queries, $skill_version, $job_queries): array {
            $this->requirePermission('write:curation-runs');
            $run = $this->ingestion->begin($context_version, $exact_queries, $skill_version, $job_queries);

            return ['run_id' => $run->id, 'status' => $run->status, 'context_version' => $run->context_version];
        });
    }

    public function submitJobBatch(string $run_id, string $context_version, array $jobs): array
    {
        return $this->call(function () use ($run_id, $context_version, $jobs): array {
            $this->requirePermission('write:job-batches');
            $this->policy->assertCurrentContext($context_version);

            return $this->jobCuration->submit(AgentRun::query()->findOrFail($run_id), $context_version, $jobs);
        });
    }

    public function claimNextApplication(string $client_attempt_id): array
    {
        return $this->call(function () use ($client_attempt_id): array {
            $this->requirePermission('read:approved-applications');

            return $this->applications->claim($client_attempt_id) ?? ['status' => 'empty'];
        });
    }

    public function getProfileDocument(string $application_id, string $claim_token, string $document_id): array
    {
        return $this->call(function () use ($application_id, $claim_token, $document_id): array {
            $this->requirePermission('read:approved-applications');
            $application = JobApplication::query()->findOrFail($application_id);
            $this->applications->assertClaim($application, $claim_token);
            $approvedIds = collect($application->profile_snapshot['documents'] ?? [])->pluck('id');
            if (! $approvedIds->contains($document_id)) {
                throw new CurationException('document_not_approved', 'This document was not part of the approved profile snapshot.');
            }
            $document = JobProfileDocument::query()->findOrFail($document_id);

            return [
                'document_id' => $document->id, 'filename' => $document->original_name,
                'mime_type' => $document->mime_type, 'sha256' => $document->sha256,
                'content_base64' => base64_encode($this->documents->contents($document)),
            ];
        });
    }

    public function saveApplicationMaterials(string $application_id, string $claim_token, array $materials): array
    {
        return $this->call(function () use ($application_id, $claim_token, $materials): array {
            $this->requirePermission('write:application-progress');

            return ['saved' => $this->applications->saveMaterials(JobApplication::query()->findOrFail($application_id), $claim_token, $materials)];
        });
    }

    public function requestApplicationInformation(string $application_id, string $claim_token, array $fields, ?string $message = null): array
    {
        return $this->call(function () use ($application_id, $claim_token, $fields, $message): array {
            $this->requirePermission('write:application-progress');
            $questionnaire = $this->applications->requestInformation(JobApplication::query()->findOrFail($application_id), $claim_token, $fields, $message);

            return ['application_id' => $application_id, 'questionnaire_id' => $questionnaire->id, 'status' => 'needs_information'];
        });
    }

    public function recordApplicationOutcome(string $application_id, string $claim_token, string $status, array $evidence = [], ?string $reason = null): array
    {
        return $this->call(function () use ($application_id, $claim_token, $status, $evidence, $reason): array {
            $this->requirePermission('write:application-progress');
            $application = $this->applications->recordOutcome(JobApplication::query()->findOrFail($application_id), $claim_token, $status, $evidence, $reason);

            return ['application_id' => $application->id, 'status' => $application->status, 'completed_at' => $application->completed_at?->toIso8601String()];
        });
    }

    /** @return array<string, mixed> */
    public function submitStoryBatch(string $run_id, string $context_version, array $stories): array
    {
        return $this->call(function () use ($run_id, $context_version, $stories): array {
            $this->requirePermission('write:story-batches');

            return $this->ingestion->submit($run_id, $context_version, $stories);
        });
    }

    /** @return array<string, mixed> */
    public function completeCurationRun(string $run_id, string $status = 'completed'): array
    {
        return $this->call(function () use ($run_id, $status): array {
            $this->requirePermission('write:curation-runs');
            $run = $this->ingestion->complete($run_id, $status);

            return [
                'run_id' => $run->id,
                'status' => $run->status,
                'accepted_count' => $run->accepted_count,
                'rejected_count' => $run->rejected_count,
                'job_accepted_count' => $run->job_accepted_count,
                'job_rejected_count' => $run->job_rejected_count,
            ];
        });
    }

    private function call(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (CurationException $exception) {
            throw new ToolCallException($exception->getMessage(), 0, $exception);
        }
    }

    private function requirePermission(string $permission): void
    {
        if (! $this->context->hasPermission($permission)) {
            throw new CurationException('insufficient_scope', "The OAuth token requires $permission.");
        }
    }
}
