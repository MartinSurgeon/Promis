<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Repository;

use Promis\Src\Planning\Domain\DTO\PlanRevisionRecordDTO;

/**
 * Contract for Plan Revision Provenance persistence operations (plan_revision_records).
 * Supports FR-050.
 */
interface PlanRevisionRecordRepositoryInterface
{
    public function findById(int $id): ?PlanRevisionRecordDTO;

    /**
     * @return PlanRevisionRecordDTO[]
     */
    public function findByPlanId(int $planId): array;

    public function findByNewVersionId(int $newVersionId): ?PlanRevisionRecordDTO;

    public function create(array $data): int;

    public function markApproved(int $id, int $approvedByUserId, ?string $approvedAt = null): bool;
}
