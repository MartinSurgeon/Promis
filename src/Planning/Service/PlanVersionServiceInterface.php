<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Service;

use Promis\Src\Planning\Domain\DTO\ItemVarianceDTO;
use Promis\Src\Planning\Domain\DTO\PlanRevisionRecordDTO;
use Promis\Src\Planning\Domain\DTO\PlanVersionDTO;

/**
 * Service contract for Plan Version Preservation, Revision Staging, and Delta Tracking (FR-050).
 */
interface PlanVersionServiceInterface
{
    /**
     * Stage an authorized plan revision creating a new incremented version (e.g. Version 2.0).
     *
     * @param int $planId
     * @param int|null $reviewCycleId
     * @param string $justification
     * @param array[] $revisedItems
     * @param int $userId
     * @return PlanVersionDTO Newly staged revision version DTO
     */
    public function createPlanRevision(
        int $planId,
        ?int $reviewCycleId,
        string $justification,
        array $revisedItems,
        int $userId
    ): PlanVersionDTO;

    /**
     * Compute itemized quantity and cost variances between two versions (FR-050).
     *
     * @param int $priorVersionId
     * @param int $newVersionId
     * @return ItemVarianceDTO[]
     */
    public function calculateVersionVariance(int $priorVersionId, int $newVersionId): array;

    /**
     * Approve a staged plan revision, activating the new version, superseding the prior version,
     * and updating the plan current version pointer.
     *
     * @param int $revisionRecordId
     * @param int $approverUserId
     * @return PlanVersionDTO Approved version DTO
     */
    public function approvePlanRevision(int $revisionRecordId, int $approverUserId): PlanVersionDTO;

    /**
     * Retrieve all historical revision provenance records for a plan.
     *
     * @return PlanRevisionRecordDTO[]
     */
    public function getRevisionHistory(int $planId): array;
}

