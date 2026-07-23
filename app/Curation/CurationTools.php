<?php

namespace App\Curation;

use App\Tenancy\TenantContext;
use Mcp\Exception\ToolCallException;

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
        return $this->call(function (): array {
            $this->requirePermission('read:curation-context');

            return $this->policy->context();
        });
    }

    /** @return array<string, mixed> */
    public function beginCurationRun(string $context_version, array $exact_queries, ?string $skill_version = null): array
    {
        return $this->call(function () use ($context_version, $exact_queries, $skill_version): array {
            $this->requirePermission('write:curation-runs');
            $run = $this->ingestion->begin($context_version, $exact_queries, $skill_version);

            return ['run_id' => $run->id, 'status' => $run->status, 'context_version' => $run->context_version];
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
