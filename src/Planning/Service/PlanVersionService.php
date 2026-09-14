<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Service;

use PDO;
use Promis\Core\Exception\AppException;
use Promis\Core\Exception\DatabaseException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Service\BaseService;
use Promis\Src\Planning\Auth\PlanAuthorizationGuard;
use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\DTO\ItemVarianceDTO;
use Promis\Src\Planning\Domain\DTO\PlanRevisionRecordDTO;
use Promis\Src\Planning\Domain\DTO\PlanVersionDTO;
use Promis\Src\Planning\Domain\PlanStatus;
use Promis\Src\Planning\Domain\PlanVersionStatus;
use Promis\Src\Planning\Repository\PlanReviewCycleRepository;
use Promis\Src\Planning\Repository\PlanReviewCycleRepositoryInterface;
use Promis\Src\Planning\Repository\PlanRevisionRecordRepository;
use Promis\Src\Planning\Repository\PlanRevisionRecordRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepository;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepository;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepositoryInterface;
use Promis\Src\Planning\Validation\PlanItemValidator;
use Promis\Src\Planning\Validation\PlanVersionValidator;

/**
 * Application service implementing Plan Version Preservation and Delta Tracking (FR-050).
 * Enforces arbitrary-precision decimal strings, transaction ownership, authorization, and cross-plan integrity.
 */
final class PlanVersionService extends BaseService implements PlanVersionServiceInterface
{
    private ProcurementPlanRepositoryInterface $planRepo;
    private ProcurementPlanVersionRepositoryInterface $versionRepo;
    private ProcurementPlanItemRepositoryInterface $itemRepo;
    private PlanRevisionRecordRepositoryInterface $revisionRepo;
    private PlanReviewCycleRepositoryInterface $reviewRepo;

    public function __construct(
        ?PDO $db = null,
        ?ProcurementPlanRepositoryInterface $planRepo = null,
        ?ProcurementPlanVersionRepositoryInterface $versionRepo = null,
        ?ProcurementPlanItemRepositoryInterface $itemRepo = null,
        ?PlanRevisionRecordRepositoryInterface $revisionRepo = null,
        ?PlanReviewCycleRepositoryInterface $reviewRepo = null
    ) {
        parent::__construct($db);
        $this->planRepo = $planRepo ?? new ProcurementPlanRepository($this->db);
        $this->versionRepo = $versionRepo ?? new ProcurementPlanVersionRepository($this->db);
        $this->itemRepo = $itemRepo ?? new ProcurementPlanItemRepository($this->db);
        $this->revisionRepo = $revisionRepo ?? new PlanRevisionRecordRepository($this->db);
        $this->reviewRepo = $reviewRepo ?? new PlanReviewCycleRepository($this->db);
    }

    public function createPlanRevision(
        int $planId,
        ?int $reviewCycleId,
        string $justification,
        array $revisedItems,
        int $userId
    ): PlanVersionDTO {
        if ($userId <= 0) {
            throw new ValidationException('A valid acting user ID is required.', [
                'user_id' => ['User ID must be a positive integer.']
            ]);
        }

        $plan = $this->planRepo->findById($planId);
        if ($plan === null) {
            throw new ValidationException("Procurement plan #{$planId} not found.", [
                'procurement_plan_id' => ['Plan not found.']
            ]);
        }

        // 1. Authorization check
        PlanAuthorizationGuard::requireCanRevisePlan($plan->planningEntityId);

        // 2. Resolve and validate current version
        if ($plan->currentVersionId === null) {
            throw new ValidationException('Cannot create a revision on a plan without an active version.', [
                'prior_version_id' => ['Plan has no active version attached.']
            ]);
        }

        $priorVersion = $this->versionRepo->findById($plan->currentVersionId);
        if ($priorVersion === null) {
            throw new ValidationException('Cannot create a revision without an existing approved baseline version.', [
                'prior_version_id' => ['Baseline version not found.']
            ]);
        }

        // Cross-plan check for prior version
        if ($priorVersion->procurementPlanId !== $planId) {
            throw new ValidationException("Prior version #{$priorVersion->id} does not belong to procurement plan #{$planId}.", [
                'prior_version_id' => ['Cross-plan prior version reference is not permitted.']
            ]);
        }

        // 3. Review cycle checks (if supplied)
        if ($reviewCycleId !== null) {
            if ($reviewCycleId <= 0) {
                throw new ValidationException('Review cycle ID must be a positive integer.', [
                    'review_cycle_id' => ['Invalid review cycle ID.']
                ]);
            }
            $cycle = $this->reviewRepo->findById($reviewCycleId);
            if ($cycle === null) {
                throw new ValidationException("Review cycle #{$reviewCycleId} not found.", [
                    'review_cycle_id' => ['Review cycle not found.']
                ]);
            }
            if ($cycle->procurementPlanId !== $planId) {
                throw new ValidationException("Review cycle #{$reviewCycleId} does not belong to procurement plan #{$planId}.", [
                    'review_cycle_id' => ['Cross-plan review cycle reference is not permitted.']
                ]);
            }
            if (!$cycle->reviewStatus->isCompleted()) {
                throw new ValidationException("Review cycle #{$reviewCycleId} is not completed.", [
                    'review_cycle_id' => ['Review cycle must be completed before staging a revision.']
                ]);
            }
            if ($cycle->reviewOutcome === null || !$cycle->reviewOutcome->requiresRevision()) {
                throw new ValidationException("Review cycle #{$reviewCycleId} does not authorize revision (outcome must be REVISION_REQUIRED).", [
                    'review_cycle_id' => ['Review outcome does not require revision.']
                ]);
            }
            if ($cycle->activeVersionId !== $priorVersion->id) {
                throw new ValidationException("Review cycle was conducted on version #{$cycle->activeVersionId}, not current version #{$priorVersion->id}.", [
                    'review_cycle_id' => ['Review cycle version mismatch with current baseline.']
                ]);
            }
        }

        // 4. Validate revision inputs
        PlanVersionValidator::validateRevision($planId, $priorVersion->id, $justification, $revisedItems);

        // 5. Compute next version number numerically (e.g. 1.0 -> 2.0)
        $nextVersionNumber = PlanVersionValidator::nextVersionNumber($priorVersion->versionNumber);

        // 6. Calculate total cost for new revision using decimal-safe arithmetic
        $totalStr = '0.00';
        $preparedItems = [];
        foreach ($revisedItems as $item) {
            $qtyStr = Decimal::normalize($item['planned_quantity'] ?? '0.00', 2);
            $costStr = Decimal::normalize($item['estimated_unit_cost'] ?? '0.00', 2);
            $lineTotal = Decimal::mul($qtyStr, $costStr, 2);
            $totalStr = Decimal::add($totalStr, $lineTotal, 2);
            $item['planned_quantity'] = $qtyStr;
            $item['estimated_unit_cost'] = $costStr;
            $item['estimated_total_cost'] = $lineTotal;
            $preparedItems[] = $item;
        }

        // 7. Execute transactional revision creation (without updating plan current_version_id)
        return $this->transaction(function () use (
            $planId,
            $reviewCycleId,
            $priorVersion,
            $nextVersionNumber,
            $justification,
            $preparedItems,
            $userId,
            $totalStr
        ): PlanVersionDTO {
            // Step A: Create new revision version record (status: DRAFT)
            $newVersionId = $this->versionRepo->create([
                'procurement_plan_id' => $planId,
                'version_number' => $nextVersionNumber,
                'status' => PlanVersionStatus::DRAFT->value,
                'total_estimated_cost' => $totalStr,
                'approval_date' => null,
                'approved_by_user_id' => null,
                'revision_reason' => $justification,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
            ]);

            if ($newVersionId <= 0) {
                throw new DatabaseException("Failed to persist revision plan version record.");
            }

            // Step B: Create provenance record in plan_revision_records
            $revisionRecordId = $this->revisionRepo->create([
                'procurement_plan_id' => $planId,
                'review_cycle_id' => $reviewCycleId,
                'prior_version_id' => $priorVersion->id,
                'new_version_id' => $newVersionId,
                'revision_justification' => $justification,
                'submitted_by_user_id' => $userId,
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);

            if ($revisionRecordId <= 0) {
                throw new DatabaseException("Failed to persist plan revision record provenance.");
            }

            // Step C: Insert revised line items under new version
            $inserted = $this->itemRepo->createBatch($newVersionId, $preparedItems, $userId);
            if ($inserted !== count($preparedItems)) {
                throw new DatabaseException("Batch item insert mismatch ({$inserted} of " . count($preparedItems) . " inserted).");
            }

            // Step D: Retrieve and return fully hydrated new version DTO
            $versionDto = $this->versionRepo->findById($newVersionId);
            if ($versionDto === null) {
                throw new DatabaseException("Failed to retrieve staged version #{$newVersionId}.");
            }
            $items = $this->itemRepo->findByVersionId($newVersionId);

            return PlanVersionDTO::fromArray($versionDto->toArray(), $items);
        });
    }

    public function approvePlanRevision(int $revisionRecordId, int $approverUserId): PlanVersionDTO
    {
        if ($approverUserId <= 0) {
            throw new ValidationException('A valid acting approver user ID is required.', [
                'approver_user_id' => ['User ID must be a positive integer.']
            ]);
        }

        $revision = $this->revisionRepo->findById($revisionRecordId);
        if ($revision === null) {
            throw new ValidationException("Plan revision record #{$revisionRecordId} not found.", [
                'revision_record_id' => ['Revision record not found.']
            ]);
        }

        // 3. Reject already approved revisions
        if ($revision->approvedByUserId !== null) {
            throw new ValidationException("Plan revision record #{$revisionRecordId} has already been approved.", [
                'revision_record_id' => ['Revision record already approved.']
            ]);
        }

        // 4. Retrieve plan
        $plan = $this->planRepo->findById($revision->procurementPlanId);
        if ($plan === null) {
            throw new ValidationException("Procurement plan #{$revision->procurementPlanId} not found.", [
                'procurement_plan_id' => ['Plan not found.']
            ]);
        }

        // 1. Confirm approver authorization
        PlanAuthorizationGuard::requireCanApprovePlan($plan->planningEntityId);

        // 4. Retrieve prior version & new version
        $priorVersion = $this->versionRepo->findById($revision->priorVersionId);
        $newVersion = $this->versionRepo->findById($revision->newVersionId);

        if ($priorVersion === null || $newVersion === null) {
            throw new ValidationException('Prior or new version not found.', [
                'version_id' => ['Version record not found.']
            ]);
        }

        // 5. Confirm all records belong to the same plan
        if ($priorVersion->procurementPlanId !== $revision->procurementPlanId) {
            throw new ValidationException("Prior version #{$priorVersion->id} does not belong to procurement plan #{$revision->procurementPlanId}.", [
                'prior_version_id' => ['Cross-plan revision prior version reference detected.']
            ]);
        }

        if ($newVersion->procurementPlanId !== $revision->procurementPlanId) {
            throw new ValidationException("New version #{$newVersion->id} does not belong to procurement plan #{$revision->procurementPlanId}.", [
                'new_version_id' => ['Cross-plan revision new version reference detected.']
            ]);
        }

        // 6. Confirm the new version is DRAFT
        if ($newVersion->status !== PlanVersionStatus::DRAFT) {
            throw new ValidationException("Version #{$newVersion->id} is in status '{$newVersion->status->value}' and cannot be approved. New version must be DRAFT.", [
                'status' => ['Version is no longer in DRAFT status.']
            ]);
        }

        // 7. Confirm the prior version is the expected current or active version
        if ($plan->currentVersionId !== $priorVersion->id) {
            throw new ValidationException("Prior version #{$priorVersion->id} is not the current active version of plan #{$plan->id}.", [
                'prior_version_id' => ['Prior version does not match current active version of the plan.']
            ]);
        }

        if (!$priorVersion->status->isApproved()) {
            throw new ValidationException("Prior version #{$priorVersion->id} is in status '{$priorVersion->status->value}' and is not in an approved baseline state. Prior version must be in APPROVED status.", [
                'prior_version_id' => ['Prior version must be in APPROVED status.']
            ]);
        }

        // 8-12. Conditional atomic updates inside transaction
        return $this->transaction(function () use (
            $revisionRecordId,
            $revision,
            $priorVersion,
            $newVersion,
            $plan,
            $approverUserId
        ): PlanVersionDTO {
            $now = date('Y-m-d H:i:s');

            // 1. Conditionally mark revision record approved (WHERE id = :id AND approved_by_user_id IS NULL)
            $success = $this->revisionRepo->markApproved($revisionRecordId, $approverUserId, $now);
            if (!$success) {
                throw new DatabaseException("Failed to mark revision record #{$revisionRecordId} approved or concurrent approval detected.");
            }

            // 2. Transition new version to APPROVED (WHERE id = :id AND status = 'DRAFT')
            $success = $this->versionRepo->updateStatus(
                $newVersion->id,
                PlanVersionStatus::APPROVED->value,
                $approverUserId,
                $now,
                PlanVersionStatus::DRAFT->value
            );
            if (!$success) {
                throw new DatabaseException("Failed to transition new version #{$newVersion->id} to APPROVED or concurrent modification detected.");
            }

            // 3. Transition prior version to SUPERSEDED (WHERE id = :id AND status = 'APPROVED')
            $success = $this->versionRepo->updateStatus(
                $priorVersion->id,
                PlanVersionStatus::SUPERSEDED->value,
                null,
                null,
                PlanVersionStatus::APPROVED->value
            );
            if (!$success) {
                throw new DatabaseException("Failed to transition prior version #{$priorVersion->id} to SUPERSEDED or concurrent modification detected.");
            }

            // 4. Update plan's current_version_id pointer to new version (WHERE id = :id AND current_version_id = :prior_version_id)
            $success = $this->planRepo->setCurrentVersion(
                $plan->id,
                $newVersion->id,
                $approverUserId,
                $priorVersion->id
            );
            if (!$success) {
                throw new DatabaseException("Failed to update plan #{$plan->id} current version pointer or concurrent modification detected.");
            }

            // 5. Ensure plan status is set to APPROVED
            $success = $this->planRepo->updateStatus($plan->id, PlanStatus::APPROVED->value, $approverUserId);
            if (!$success) {
                throw new DatabaseException("Failed to transition plan #{$plan->id} status to APPROVED.");
            }

            // 6. Return fully hydrated approved version DTO
            $updatedVersion = $this->versionRepo->findById($newVersion->id);
            if ($updatedVersion === null) {
                throw new DatabaseException("Failed to retrieve approved version #{$newVersion->id}.");
            }
            $items = $this->itemRepo->findByVersionId($newVersion->id);

            return PlanVersionDTO::fromArray($updatedVersion->toArray(), $items);
        });
    }

    public function calculateVersionVariance(int $priorVersionId, int $newVersionId): array
    {
        $priorVersion = $this->versionRepo->findById($priorVersionId);
        $newVersion = $this->versionRepo->findById($newVersionId);

        if ($priorVersion === null || $newVersion === null) {
            throw new ValidationException('One or both versions not found for variance calculation.', [
                'version_id' => ['Version not found.']
            ]);
        }

        // Cross-plan protection: Versions must belong to the same plan
        if ($priorVersion->procurementPlanId !== $newVersion->procurementPlanId) {
            throw new ValidationException('Cannot calculate variance between versions belonging to different procurement plans.', [
                'procurement_plan_id' => ['Cross-plan variance comparison is not permitted.']
            ]);
        }

        $plan = $this->planRepo->findById($priorVersion->procurementPlanId);
        if ($plan !== null) {
            PlanAuthorizationGuard::requireCanViewPlan($plan->planningEntityId);
        }

        $priorItems = $this->itemRepo->findByVersionId($priorVersionId);
        $newItems = $this->itemRepo->findByVersionId($newVersionId);

        $priorMap = [];
        foreach ($priorItems as $item) {
            $priorMap[$item->standardItemId] = $item;
        }

        $newMap = [];
        foreach ($newItems as $item) {
            $newMap[$item->standardItemId] = $item;
        }

        $variances = [];

        // Check modified, unchanged, or removed items
        foreach ($priorMap as $itemId => $prior) {
            if (isset($newMap[$itemId])) {
                $new = $newMap[$itemId];

                $qtyDeltaStr = Decimal::sub($new->plannedQuantity, $prior->plannedQuantity, 2);
                $costDeltaStr = Decimal::sub($new->estimatedTotalCost, $prior->estimatedTotalCost, 2);
                $quarterChanged = $new->targetQuarter !== $prior->targetQuarter;

                $isZeroQty = Decimal::eq($qtyDeltaStr, '0.00', 2);
                $isZeroCost = Decimal::eq($costDeltaStr, '0.00', 2);

                $changeType = ($isZeroQty && $isZeroCost && !$quarterChanged)
                    ? 'UNCHANGED'
                    : 'MODIFIED';

                $variances[] = new ItemVarianceDTO(
                    standardItemId: $itemId,
                    itemDescription: $new->itemDescription,
                    changeType: $changeType,
                    previousQuantity: $prior->plannedQuantity,
                    revisedQuantity: $new->plannedQuantity,
                    quantityDelta: $qtyDeltaStr,
                    previousTotalCost: $prior->estimatedTotalCost,
                    revisedTotalCost: $new->estimatedTotalCost,
                    costDelta: $costDeltaStr,
                    previousQuarter: $prior->targetQuarter->value,
                    revisedQuarter: $new->targetQuarter->value
                );
            } else {
                // Item was removed in new version
                $qtyDeltaStr = Decimal::sub('0.00', $prior->plannedQuantity, 2);
                $costDeltaStr = Decimal::sub('0.00', $prior->estimatedTotalCost, 2);

                $variances[] = new ItemVarianceDTO(
                    standardItemId: $itemId,
                    itemDescription: $prior->itemDescription,
                    changeType: 'REMOVED',
                    previousQuantity: $prior->plannedQuantity,
                    revisedQuantity: '0.00',
                    quantityDelta: $qtyDeltaStr,
                    previousTotalCost: $prior->estimatedTotalCost,
                    revisedTotalCost: '0.00',
                    costDelta: $costDeltaStr,
                    previousQuarter: $prior->targetQuarter->value,
                    revisedQuarter: null
                );
            }
        }

        // Check newly added items
        foreach ($newMap as $itemId => $new) {
            if (!isset($priorMap[$itemId])) {
                $qtyDeltaStr = Decimal::sub($new->plannedQuantity, '0.00', 2);
                $costDeltaStr = Decimal::sub($new->estimatedTotalCost, '0.00', 2);

                $variances[] = new ItemVarianceDTO(
                    standardItemId: $itemId,
                    itemDescription: $new->itemDescription,
                    changeType: 'ADDED',
                    previousQuantity: '0.00',
                    revisedQuantity: $new->plannedQuantity,
                    quantityDelta: $qtyDeltaStr,
                    previousTotalCost: '0.00',
                    revisedTotalCost: $new->estimatedTotalCost,
                    costDelta: $costDeltaStr,
                    previousQuarter: null,
                    revisedQuarter: $new->targetQuarter->value
                );
            }
        }

        return $variances;
    }

    public function getRevisionHistory(int $planId): array
    {
        $plan = $this->planRepo->findById($planId);
        if ($plan === null) {
            return [];
        }

        PlanAuthorizationGuard::requireCanViewPlan($plan->planningEntityId);

        return $this->revisionRepo->findByPlanId($planId);
    }
}
