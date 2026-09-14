<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Service;

use PDO;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Service\BaseService;
use Promis\Src\Execution\Auth\ExecutionAuthorizationGuard;
use Promis\Src\Execution\Domain\DTO\CreateRequisitionRequest;
use Promis\Src\Execution\Domain\DTO\DrawdownResult;
use Promis\Src\Execution\Domain\DTO\RequisitionDTO;
use Promis\Src\Execution\Domain\DTO\RequisitionItemDTO;
use Promis\Src\Execution\Domain\DTO\RequisitionSubmissionResult;
use Promis\Src\Execution\Domain\DTO\SubmitRequisitionRequest;
use Promis\Src\Execution\Domain\DrawdownCalculator;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Exception\DrawdownExceededException;
use Promis\Src\Execution\Exception\RequisitionException;
use Promis\Src\Execution\Repository\RequisitionBalanceSnapshotRepository;
use Promis\Src\Execution\Repository\RequisitionBalanceSnapshotRepositoryInterface;
use Promis\Src\Execution\Repository\RequisitionItemRepository;
use Promis\Src\Execution\Repository\RequisitionItemRepositoryInterface;
use Promis\Src\Execution\Repository\RequisitionRepository;
use Promis\Src\Execution\Repository\RequisitionRepositoryInterface;
use Promis\Src\Execution\Validation\RequisitionItemValidator;
use Promis\Src\Execution\Validation\RequisitionValidator;
use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\PlanStatus;
use Promis\Src\Planning\Domain\PlanVersionStatus;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepository;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepository;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepositoryInterface;

/**
 * Application service implementing Departmental Requisition Creation, Drawdown Quota Protection,
 * and Atomic Multi-Item Submission against Approved Procurement Plans.
 */
final class RequisitionService extends BaseService implements RequisitionServiceInterface
{
    private RequisitionRepositoryInterface $reqRepo;
    private RequisitionItemRepositoryInterface $itemRepo;
    private RequisitionBalanceSnapshotRepositoryInterface $snapshotRepo;
    private ProcurementPlanRepositoryInterface $planRepo;
    private ProcurementPlanVersionRepositoryInterface $versionRepo;
    private ProcurementPlanItemRepositoryInterface $planItemRepo;

    public function __construct(
        ?PDO $db = null,
        ?RequisitionRepositoryInterface $reqRepo = null,
        ?RequisitionItemRepositoryInterface $itemRepo = null,
        ?RequisitionBalanceSnapshotRepositoryInterface $snapshotRepo = null,
        ?ProcurementPlanRepositoryInterface $planRepo = null,
        ?ProcurementPlanVersionRepositoryInterface $versionRepo = null,
        ?ProcurementPlanItemRepositoryInterface $planItemRepo = null
    ) {
        parent::__construct($db);
        $this->reqRepo = $reqRepo ?? new RequisitionRepository($this->db);
        $this->itemRepo = $itemRepo ?? new RequisitionItemRepository($this->db);
        $this->snapshotRepo = $snapshotRepo ?? new RequisitionBalanceSnapshotRepository($this->db);
        $this->planRepo = $planRepo ?? new ProcurementPlanRepository($this->db);
        $this->versionRepo = $versionRepo ?? new ProcurementPlanVersionRepository($this->db);
        $this->planItemRepo = $planItemRepo ?? new ProcurementPlanItemRepository($this->db);
    }

    public function createRequisition(CreateRequisitionRequest $request): RequisitionDTO
    {
        if ($request->actingUserId <= 0) {
            throw new ValidationException('A valid acting user ID is required.', [
                'acting_user_id' => ['User ID must be a positive integer.'],
            ]);
        }

        // 1. Enforce server-side authorization check (Entity Scoped RBAC)
        ExecutionAuthorizationGuard::requireCanCreateRequisition($request->planningEntityId);

        // 2. Validate input parameters and bounds
        if ($request->planningEntityId <= 0) {
            throw new ValidationException('A valid planning entity is required.', [
                'planning_entity_id' => ['Planning entity ID must be a positive integer.'],
            ]);
        }

        if ($request->fiscalYear < 2000 || $request->fiscalYear > 2100) {
            throw new ValidationException('Fiscal year must be a valid 4-digit year between 2000 and 2100.', [
                'fiscal_year' => ['Fiscal year must be between 2000 and 2100.'],
            ]);
        }

        $justification = trim($request->justification);
        if ($justification === '') {
            throw new ValidationException('Requisition justification is required.', [
                'justification' => ['Justification cannot be empty.'],
            ]);
        }

        if (empty($request->items)) {
            throw new ValidationException('A requisition must contain at least one line item.', [
                'items' => ['At least one item is required.'],
            ]);
        }

        // 3. Confirm planning entity exists and is active
        if (!$this->confirmPlanningEntityExists($request->planningEntityId)) {
            throw new ValidationException("Planning entity #{$request->planningEntityId} does not exist or is inactive.", [
                'planning_entity_id' => ["Planning entity #{$request->planningEntityId} does not exist or is inactive."],
            ]);
        }

        // 4. Validate Approved Plan Version linkage and eligibility (FR-051)
        $version = $this->versionRepo->findById($request->approvedPlanVersionId);
        if ($version === null) {
            throw new ValidationException("Approved procurement plan version #{$request->approvedPlanVersionId} does not exist.", [
                'approved_plan_version_id' => ["Plan version #{$request->approvedPlanVersionId} does not exist."],
            ]);
        }

        if ($version->status !== PlanVersionStatus::APPROVED) {
            throw new ValidationException(
                "Plan version #{$version->versionNumber} is in '{$version->status->value}' status. Only APPROVED plan versions can be executed.",
                ['approved_plan_version_id' => ["Plan version #{$version->versionNumber} is not approved."]]
            );
        }

        // 5. Validate Parent Procurement Plan ownership, status, and active version pointer
        $plan = $this->planRepo->findById($version->procurementPlanId);
        if ($plan === null) {
            throw new ValidationException("Parent procurement plan for version #{$version->versionNumber} does not exist.", [
                'procurement_plan_id' => ['Parent procurement plan does not exist.'],
            ]);
        }

        if ($plan->status !== PlanStatus::APPROVED) {
            throw new ValidationException(
                "Procurement plan #{$plan->planNumber} is in '{$plan->status->value}' status. Requisitions can only be raised against APPROVED procurement plans.",
                ['procurement_plan_id' => ["Procurement plan #{$plan->planNumber} is not approved."]]
            );
        }

        if ($plan->currentVersionId !== $version->id) {
            throw new ValidationException(
                "Plan version #{$version->versionNumber} is not the current active approved version for Plan #{$plan->planNumber}.",
                ['approved_plan_version_id' => ["Plan version #{$version->versionNumber} is superseded or not active."]]
            );
        }

        if ($plan->planningEntityId !== $request->planningEntityId) {
            throw new ValidationException(
                "Plan version #{$version->versionNumber} belongs to entity #{$plan->planningEntityId}, which does not match requisition entity #{$request->planningEntityId}.",
                ['planning_entity_id' => ['Entity mismatch: plan belongs to a different entity.']]
            );
        }

        if ($plan->fiscalYear !== $request->fiscalYear) {
            throw new ValidationException(
                "Plan version #{$version->versionNumber} is for fiscal year {$plan->fiscalYear}, which does not match requisition fiscal year {$request->fiscalYear}.",
                ['fiscal_year' => ['Fiscal year mismatch: plan belongs to a different year.']]
            );
        }

        // 6. Validate Plan Items & Cross-Plan Integrity
        $preparedItems = [];
        $totalCostAccumulator = '0.00';

        foreach ($request->items as $index => $itemInput) {
            $lineErrors = RequisitionItemValidator::validate($itemInput, $index + 1);
            if (!empty($lineErrors)) {
                throw new ValidationException('Invalid requisition line item data.', ['items' => $lineErrors]);
            }

            $planItemId = (int)$itemInput['procurement_plan_item_id'];
            $planItem = $this->planItemRepo->findById($planItemId);

            if ($planItem === null) {
                throw new ValidationException("Procurement plan item #{$planItemId} does not exist.", [
                    'procurement_plan_item_id' => ["Plan item #{$planItemId} does not exist."],
                ]);
            }

            // Cross-plan protection: item must belong directly to the approved version
            if ($planItem->planVersionId !== $version->id) {
                throw new ValidationException(
                    "Plan item #{$planItemId} belongs to version #{$planItem->planVersionId}, not to the approved version #{$version->id}.",
                    ['procurement_plan_item_id' => ["Plan item #{$planItemId} does not belong to version #{$version->id}."]]
                );
            }

            $qty = Decimal::normalize($itemInput['requested_quantity'], 2);
            $unitCost = isset($itemInput['estimated_unit_cost'])
                ? Decimal::normalize($itemInput['estimated_unit_cost'], 2)
                : $planItem->estimatedUnitCost;

            $lineTotal = Decimal::mul($qty, $unitCost, 2);
            $totalCostAccumulator = Decimal::add($totalCostAccumulator, $lineTotal, 2);

            $preparedItems[] = [
                'procurement_plan_item_id' => $planItemId,
                'standard_item_id' => (int)($itemInput['standard_item_id'] ?? $planItem->standardItemId),
                'item_description' => (string)($itemInput['item_description'] ?? $planItem->itemDescription),
                'uom_id' => (int)($itemInput['uom_id'] ?? $planItem->uomId),
                'requested_quantity' => $qty,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => $lineTotal,
                'item_justification' => isset($itemInput['item_justification']) ? (string)$itemInput['item_justification'] : null,
            ];
        }

        // 7. Resolve Requisition Number
        $reqNumber = $request->requisitionNumber !== null ? trim($request->requisitionNumber) : '';
        if ($reqNumber !== '') {
            if ($this->reqRepo->existsByRequisitionNumber($reqNumber)) {
                throw new ValidationException("Requisition number '{$reqNumber}' already exists.", [
                    'requisition_number' => ["Requisition number '{$reqNumber}' already exists."],
                ]);
            }
        } else {
            $reqNumber = $this->generateRequisitionNumber($request->fiscalYear, $request->planningEntityId);
        }

        // 8. Execute Requisition Header and Items insertion inside a single managed transaction
        return $this->transaction(function () use ($request, $reqNumber, $totalCostAccumulator, $preparedItems) {
            $requisitionId = $this->reqRepo->create([
                'requisition_number' => $reqNumber,
                'planning_entity_id' => $request->planningEntityId,
                'fiscal_year' => $request->fiscalYear,
                'approved_plan_version_id' => $request->approvedPlanVersionId,
                'status' => RequisitionStatus::DRAFT->value,
                'total_estimated_cost' => $totalCostAccumulator,
                'justification' => $request->justification,
                'created_by' => $request->actingUserId,
            ]);

            $this->itemRepo->createBatch($requisitionId, $preparedItems, $request->actingUserId);

            $dto = $this->reqRepo->findById($requisitionId);
            if ($dto === null) {
                throw new RequisitionException("Failed to retrieve created requisition #{$requisitionId}.");
            }

            $items = $this->itemRepo->findByRequisitionId($requisitionId);

            return new RequisitionDTO(
                id: $dto->id,
                requisitionNumber: $dto->requisitionNumber,
                planningEntityId: $dto->planningEntityId,
                fiscalYear: $dto->fiscalYear,
                approvedPlanVersionId: $dto->approvedPlanVersionId,
                status: $dto->status,
                totalEstimatedCost: $dto->totalEstimatedCost,
                justification: $dto->justification,
                submittedAt: $dto->submittedAt,
                submittedBy: $dto->submittedBy,
                createdAt: $dto->createdAt,
                createdBy: $dto->createdBy,
                updatedAt: $dto->updatedAt,
                updatedBy: $dto->updatedBy,
                items: $items,
                planningEntityName: $dto->planningEntityName,
                planningEntityCode: $dto->planningEntityCode,
                planVersionNumber: $dto->planVersionNumber
            );
        });
    }

    public function submitRequisition(SubmitRequisitionRequest $request): RequisitionSubmissionResult
    {
        if ($request->actingUserId <= 0) {
            throw new ValidationException('A valid acting user ID is required.', [
                'acting_user_id' => ['User ID must be a positive integer.'],
            ]);
        }

        $requisition = $this->reqRepo->findById($request->requisitionId);
        if ($requisition === null) {
            throw new ValidationException("Requisition #{$request->requisitionId} does not exist.", [
                'requisition_id' => ["Requisition #{$request->requisitionId} does not exist."],
            ]);
        }

        // 1. Authorization verification
        ExecutionAuthorizationGuard::requireCanSubmitRequisition($requisition->planningEntityId);

        // 2. Pre-status validation: Only DRAFT or RETURNED requisitions can be submitted
        if (!$requisition->status->isEditable()) {
            throw new ValidationException(
                "Cannot submit requisition #{$requisition->requisitionNumber}: current status is '{$requisition->status->value}'. Only DRAFT or RETURNED requisitions can be submitted.",
                ['status' => ["Requisition is not in an editable submission status."]]
            );
        }

        // 3. Confirm items exist
        $items = $this->itemRepo->findByRequisitionId($requisition->id);
        if (empty($items)) {
            throw new ValidationException("Cannot submit an empty requisition with zero line items.", [
                'items' => ['Requisition must contain at least one line item before submission.'],
            ]);
        }

        // 4. Atomic Lock, Drawdown Revalidation, Snapshot Creation, and Status Transition
        return $this->transaction(function () use ($requisition, $items, $request) {
            $planItemIds = array_map(fn(RequisitionItemDTO $item) => $item->procurementPlanItemId, $items);
            $planItemIds = array_values(array_unique($planItemIds));

            // Concurrency Control: Lock the referenced procurement plan items using FOR UPDATE
            $lockedPlanItems = $this->lockPlanItems($planItemIds);

            $snapshotsToCreate = [];
            $drawdownResults = [];

            foreach ($items as $item) {
                $planItemId = $item->procurementPlanItemId;
                if (!isset($lockedPlanItems[$planItemId])) {
                    throw new ValidationException("Plan item #{$planItemId} not found under lock.");
                }

                $planItem = $lockedPlanItems[$planItemId];

                // Calculate cumulative previously requested quantity (excluding current requisition)
                $previouslyRequested = $this->itemRepo->calculateRequestedQuantityByPlanItem(
                    $planItemId,
                    $requisition->id
                );

                // Compute real-time exact drawdown
                $drawdown = DrawdownCalculator::calculate(
                    $planItem['planned_quantity'],
                    $previouslyRequested,
                    $item->requestedQuantity,
                    $planItemId
                );

                if ($drawdown->isExceeded) {
                    throw new DrawdownExceededException(
                        "Requested quantity of {$item->requestedQuantity} for item '{$item->itemDescription}' exceeds remaining balance of {$drawdown->remainingBefore} (Approved: {$drawdown->approvedPlannedQuantity}, Previously Requested: {$drawdown->previouslyRequestedQuantity}).",
                        planItemId: $planItemId,
                        requestedQuantity: $item->requestedQuantity,
                        remainingBalance: $drawdown->remainingBefore
                    );
                }

                $drawdownResults[] = $drawdown;

                $snapshotsToCreate[] = [
                    'requisition_id' => $requisition->id,
                    'requisition_item_id' => $item->id,
                    'plan_item_id' => $planItemId,
                    'workflow_event' => 'SUBMISSION',
                    'approved_planned_quantity' => $drawdown->approvedPlannedQuantity,
                    'previously_requested_quantity' => $drawdown->previouslyRequestedQuantity,
                    'current_request_quantity' => $drawdown->currentRequestQuantity,
                    'remaining_before' => $drawdown->remainingBefore,
                    'remaining_after' => $drawdown->remainingAfter,
                    'recorded_by_user_id' => $request->actingUserId,
                ];
            }

            // Create evidentiary balance snapshots inside the same transaction
            $this->snapshotRepo->createBatch($snapshotsToCreate);

            // Conditional status update ensuring concurrency safety
            $updated = $this->reqRepo->updateStatus(
                $requisition->id,
                RequisitionStatus::SUBMITTED->value,
                $request->actingUserId,
                $requisition->status->value
            );

            if (!$updated) {
                throw new RequisitionException(
                    "Concurrent modification detected: Requisition #{$requisition->requisitionNumber} is no longer in '{$requisition->status->value}' status."
                );
            }

            $submittedReq = $this->reqRepo->findById($requisition->id);
            if ($submittedReq === null) {
                throw new RequisitionException("Failed to retrieve submitted requisition.");
            }

            $savedSnapshots = $this->snapshotRepo->findByRequisitionId($requisition->id);

            return new RequisitionSubmissionResult(
                requisition: $submittedReq,
                snapshots: $savedSnapshots,
                drawdownResults: $drawdownResults
            );
        });
    }

    public function getRequisition(int $requisitionId, int $actingUserId): RequisitionDTO
    {
        $dto = $this->reqRepo->findById($requisitionId);
        if ($dto === null) {
            throw new ValidationException("Requisition #{$requisitionId} does not exist.");
        }

        ExecutionAuthorizationGuard::requireCanViewRequisition($dto->planningEntityId);

        $items = $this->itemRepo->findByRequisitionId($requisitionId);

        return new RequisitionDTO(
            id: $dto->id,
            requisitionNumber: $dto->requisitionNumber,
            planningEntityId: $dto->planningEntityId,
            fiscalYear: $dto->fiscalYear,
            approvedPlanVersionId: $dto->approvedPlanVersionId,
            status: $dto->status,
            totalEstimatedCost: $dto->totalEstimatedCost,
            justification: $dto->justification,
            submittedAt: $dto->submittedAt,
            submittedBy: $dto->submittedBy,
            createdAt: $dto->createdAt,
            createdBy: $dto->createdBy,
            updatedAt: $dto->updatedAt,
            updatedBy: $dto->updatedBy,
            items: $items,
            planningEntityName: $dto->planningEntityName,
            planningEntityCode: $dto->planningEntityCode,
            planVersionNumber: $dto->planVersionNumber
        );
    }

    public function calculateItemDrawdown(int $planItemId, string $requestedQuantity, ?int $excludeRequisitionId = null): DrawdownResult
    {
        $planItem = $this->planItemRepo->findById($planItemId);
        if ($planItem === null) {
            throw new ValidationException("Procurement plan item #{$planItemId} does not exist.");
        }

        $previouslyRequested = $this->itemRepo->calculateRequestedQuantityByPlanItem($planItemId, $excludeRequisitionId);

        return DrawdownCalculator::calculate(
            $planItem->plannedQuantity,
            $previouslyRequested,
            $requestedQuantity,
            $planItemId
        );
    }

    /**
     * @return RequisitionDTO[]
     */
    public function getRequisitionsByEntity(int $planningEntityId, int $actingUserId, ?int $fiscalYear = null): array
    {
        ExecutionAuthorizationGuard::requireCanViewRequisition($planningEntityId);

        return $this->reqRepo->findByPlanningEntity($planningEntityId, $fiscalYear);
    }

    /**
     * Pessimistic row lock for procurement plan items inside transaction.
     *
     * @param int[] $planItemIds
     * @return array<int, array> Keyed by plan_item_id
     */
    private function lockPlanItems(array $planItemIds): array
    {
        if (empty($planItemIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($planItemIds), '?'));
        $sql = "SELECT `id`, `plan_version_id`, `planned_quantity`, `estimated_unit_cost` 
                FROM `procurement_plan_items` 
                WHERE `id` IN ({$placeholders}) 
                FOR UPDATE";

        $stmt = $this->db->prepare($sql);
        foreach ($planItemIds as $index => $id) {
            $stmt->bindValue($index + 1, (int)$id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['id']] = $row;
        }

        return $result;
    }

    private function generateRequisitionNumber(int $fiscalYear, int $planningEntityId): string
    {
        $paddedEntity = str_pad((string)$planningEntityId, 3, '0', STR_PAD_LEFT);
        $prefix = "REQ-{$fiscalYear}-ENT{$paddedEntity}";

        for ($i = 0; $i < 100; $i++) {
            $seq = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = "{$prefix}-{$seq}";
            if (!$this->reqRepo->existsByRequisitionNumber($candidate)) {
                return $candidate;
            }
        }

        return "{$prefix}-" . bin2hex(random_bytes(3));
    }

    private function confirmPlanningEntityExists(int $planningEntityId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM `planning_entities` WHERE `id` = :id AND `is_active` = 1 LIMIT 1");
        $stmt->bindValue(':id', $planningEntityId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }
}
