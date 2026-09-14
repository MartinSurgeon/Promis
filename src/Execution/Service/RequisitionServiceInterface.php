<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Service;

use Promis\Src\Execution\Domain\DTO\CreateRequisitionRequest;
use Promis\Src\Execution\Domain\DTO\DrawdownResult;
use Promis\Src\Execution\Domain\DTO\RequisitionDTO;
use Promis\Src\Execution\Domain\DTO\RequisitionSubmissionResult;
use Promis\Src\Execution\Domain\DTO\SubmitRequisitionRequest;

/**
 * Contract for Requisition Application Service.
 */
interface RequisitionServiceInterface
{
    /**
     * Create a new draft requisition with items against an approved plan version.
     */
    public function createRequisition(CreateRequisitionRequest $request): RequisitionDTO;

    /**
     * Submit a draft requisition for departmental review and approval,
     * locking plan item rows, validating drawdown quotas, and generating snapshots.
     */
    public function submitRequisition(SubmitRequisitionRequest $request): RequisitionSubmissionResult;

    /**
     * Retrieve a requisition by ID with authorization verification.
     */
    public function getRequisition(int $requisitionId, int $actingUserId): RequisitionDTO;

    /**
     * Compute current drawdown balance for a plan item.
     */
    public function calculateItemDrawdown(int $planItemId, string $requestedQuantity, ?int $excludeRequisitionId = null): DrawdownResult;

    /**
     * Retrieve all requisitions for a planning entity with authorization verification.
     *
     * @return RequisitionDTO[]
     */
    public function getRequisitionsByEntity(int $planningEntityId, int $actingUserId, ?int $fiscalYear = null): array;
}
