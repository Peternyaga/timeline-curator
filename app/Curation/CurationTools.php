<?php

namespace App\Curation;

use App\Tenancy\TenantContext;

class CurationTools
{
    public function __construct(
        private TenantContext $context,
        private CurationPolicyService $policy,
        private CurationIngestionService $ingestion,
    ) {}

    /** @return array<string, mixed> */
    public function getCurationContext(): array
    {
        $this->requirePermission('read:curation-context');

        return $this->policy->context();
    }

    /** @return array<string, mixed> */
    public function beginCurationRun(string $context_version, array $exact_queries, ?string $skill_version = null): array
    {
        $this->requirePermission('write:curation-runs');
        $run = $this->ingestion->begin($context_version, $exact_queries, $skill_version);

        return ['run_id' => $run->id, 'status' => $run->status, 'context_version' => $run->context_version];
    }

    /** @return array<string, mixed> */
    public function submitStoryBatch(string $run_id, string $context_version, array $stories): array
    {
        $this->requirePermission('write:story-batches');

        return $this->ingestion->submit($run_id, $context_version, $stories);
    }

    /** @return array<string, mixed> */
    public function completeCurationRun(string $run_id, string $status = 'completed'): array
    {
        $this->requirePermission('write:curation-runs');
        $run = $this->ingestion->complete($run_id, $status);

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'accepted_count' => $run->accepted_count,
            'rejected_count' => $run->rejected_count,
        ];
    }

    private function requirePermission(string $permission): void
    {
        if (! $this->context->hasPermission($permission)) {
            throw new CurationException('insufficient_scope', "The OAuth token requires $permission.");
        }
    }
}
