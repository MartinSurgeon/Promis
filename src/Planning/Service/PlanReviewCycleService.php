<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Service;

use PDO;
use Promis\Core\Exception\AppException;
use Promis\Core\Exception\DatabaseException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Service\BaseService;
use Promis\Src\Planning\Auth\PlanAuthorizationGuard;
use Promis\Src\Planning\Domain\DTO\PlanReviewCycleDTO;
use Promis\Src\Planning\Domain\ReviewCycleStatus;
use Promis\Src\Planning\Domain\ReviewOutcome;
use Promis\Src\Planning\Domain\ReviewQuarter;
use Promis\Src\Planning\Repository\PlanReviewCycleRepository;
use Promis\Src\Planning\Repository\PlanReviewCycleRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepository;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepositoryInterface;
use Promis\Src\Planning\Validation\ReviewCycleValidator;

/**
 * Application service implementing Quarterly Plan Review Cycles (FR-049).
 */
final class PlanReviewCycleService extends BaseService implements PlanReviewCycleServiceInterface
{
    private PlanReviewCycleRepositoryInterface $reviewRepo;
    private ProcurementPlanRepositoryInterface $planRepo;
    private ProcurementPlanVersionRepositoryInterface $versionRepo;

    public function __construct(
        ?PDO $db = null,
        ?PlanReviewCycleRepositoryInterface $reviewRepo = null,
        ?ProcurementPlanRepositoryInterface $planRepo = null,
        ?ProcurementPlanVersionRepositoryInterface $versionRepo = null
    ) {
        parent::__construct($db);
        $this->reviewRepo = $reviewRepo ?? new PlanReviewCycleRepository($this->db);
        $this->planRepo = $planRepo ?? new ProcurementPlanRepository($this->db);
        $this->versionRepo = $versionRepo ?? new ProcurementPlanVersionRepository($this->db);
    }

    public function initiateReviewCycle(
        int $planId,
        int $fiscalYear,
        string $quarter,
        int $userId,
        ?int $activeVersionId = null
    ): PlanReviewCycleDTO {
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
        PlanAuthorizationGuard::requireCanReviewPlan($plan->planningEntityId);

        // 2. Plan status check: Must be an active approved plan
        if (!$plan->status->isApproved()) {
            throw new ValidationException("Quarterly review cycles can only be initiated on approved procurement plans. Current status is '{$plan->status->value}'.", [
                'status' => ['Plan must be in an approved status.']
            ]);
        }

        // 3. Resolve target active version
        if ($activeVersionId === null) {
            if ($plan->currentVersionId === null) {
                throw new ValidationException('Cannot initiate a quarterly review on a plan without an active version.', [
                    'active_version_id' => ['Plan has no active version attached.']
                ]);
            }
            $targetVersionId = $plan->currentVersionId;
            $version = $this->versionRepo->findById($targetVersionId);
            if ($version === null) {
                throw new ValidationException("Active version #{$targetVersionId} not found.", [
                    'active_version_id' => ['Version not found.']
                ]);
            }
            if (!$version->status->isApproved()) {
                throw new ValidationException("Active version #{$targetVersionId} is in status '{$version->status->value}' and is not eligible for review. Version must be APPROVED.", [
                    'active_version_id' => ['Active version must be in APPROVED status.']
                ]);
            }
        } else {
            $targetVersionId = $activeVersionId;
            if ($targetVersionId <= 0) {
                throw new ValidationException('A valid active version ID must be specified.', [
                    'active_version_id' => ['Version ID must be a positive integer.']
                ]);
            }
            $version = $this->versionRepo->findById($targetVersionId);
            if ($version === null) {
                throw new ValidationException("Version #{$targetVersionId} not found.", [
                    'active_version_id' => ['Version not found.']
                ]);
            }
            // Cross-plan check: Version must belong to plan
            if ($version->procurementPlanId !== $planId) {
                throw new ValidationException("Version #{$targetVersionId} does not belong to procurement plan #{$planId}.", [
                    'active_version_id' => ['Cross-plan review-cycle active version assignment is not permitted.']
                ]);
            }
            // Eligibility check: Version must be APPROVED
            if (!$version->status->isApproved()) {
                throw new ValidationException("Version #{$targetVersionId} is in status '{$version->status->value}' and is not eligible for review. Version must be APPROVED.", [
                    'active_version_id' => ['Version must be in APPROVED status.']
                ]);
            }
        }

        // 4. Validate fiscal year
        if ($fiscalYear < 2000 || $fiscalYear > 2100) {
            throw new ValidationException('Fiscal year must be a valid 4-digit year between 2000 and 2100.', [
                'fiscal_year' => ['Fiscal year must be between 2000 and 2100.']
            ]);
        }

        // 5. Validate quarter
        if (!in_array($quarter, ReviewQuarter::values(), true)) {
            throw new ValidationException("Review quarter '{$quarter}' is invalid.", [
                'review_quarter' => ['Review quarter must be one of: ' . implode(', ', ReviewQuarter::values()) . '.']
            ]);
        }

        // 6. Validator rules check
        ReviewCycleValidator::validateInitiation($planId, $targetVersionId, $fiscalYear, $quarter);

        // 7. Ensure uniqueness: Only 1 review cycle per plan per fiscal year and quarter
        $existing = $this->reviewRepo->findByPlanQuarter($planId, $fiscalYear, $quarter);
        if ($existing !== null) {
            throw new ValidationException("Quarterly review for {$quarter} in fiscal year {$fiscalYear} already exists.", [
                'review_quarter' => ["A review session for {$quarter} ({$fiscalYear}) has already been initiated."]
            ]);
        }

        // 8. Create the review cycle in IN_PROGRESS status with correct plan and version references
        return $this->transaction(function () use ($planId, $targetVersionId, $fiscalYear, $quarter, $userId): PlanReviewCycleDTO {
            $cycleId = $this->reviewRepo->create([
                'procurement_plan_id' => $planId,
                'active_version_id' => $targetVersionId,
                'fiscal_year' => $fiscalYear,
                'review_quarter' => $quarter,
                'review_status' => ReviewCycleStatus::IN_PROGRESS->value,
                'review_outcome' => null,
                'reviewed_by_user_id' => $userId,
                'completed_at' => null,
                'review_notes' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
            ]);

            if ($cycleId <= 0) {
                throw new DatabaseException("Failed to persist plan review cycle record.");
            }

            $dto = $this->reviewRepo->findById($cycleId);
            if ($dto === null) {
                throw new DatabaseException("Failed to retrieve created review cycle #{$cycleId}.");
            }

            return $dto;
        });
    }

    public function recordReviewOutcome(
        int $cycleId,
        string $outcome,
        ?string $notes,
        int $userId
    ): PlanReviewCycleDTO {
        if ($userId <= 0) {
            throw new ValidationException('A valid acting user ID is required.', [
                'user_id' => ['User ID must be a positive integer.']
            ]);
        }

        $cycle = $this->reviewRepo->findById($cycleId);
        if ($cycle === null) {
            throw new ValidationException("Review cycle #{$cycleId} not found.", [
                'cycle_id' => ['Review cycle not found.']
            ]);
        }

        // 1. Reject already completed cycles
        if ($cycle->reviewStatus->isCompleted() || $cycle->reviewStatus === ReviewCycleStatus::COMPLETED) {
            throw new ValidationException("Review cycle #{$cycleId} is already completed.", [
                'review_status' => ['Review cycle is already closed and cannot be modified.']
            ]);
        }

        $plan = $this->planRepo->findById($cycle->procurementPlanId);
        if ($plan === null) {
            throw new ValidationException("Associated procurement plan not found.", [
                'procurement_plan_id' => ['Plan not found.']
            ]);
        }

        // 2. Authorization check
        PlanAuthorizationGuard::requireCanReviewPlan($plan->planningEntityId);

        // 3. Reject invalid or alien version references
        $version = $this->versionRepo->findById($cycle->activeVersionId);
        if ($version === null) {
            throw new ValidationException("Active version #{$cycle->activeVersionId} not found.", [
                'active_version_id' => ['Version not found.']
            ]);
        }
        if ($version->procurementPlanId !== $cycle->procurementPlanId) {
            throw new ValidationException("Review cycle references version #{$cycle->activeVersionId} which belongs to a different plan.", [
                'active_version_id' => ['Cross-plan version reference detected.']
            ]);
        }

        // 4. Define and enforce plan and version status eligibility
        if (!$plan->status->isApproved()) {
            throw new ValidationException("Cannot complete review on plan in '{$plan->status->value}' status. Plan must be in an approved state.", [
                'status' => ['Plan must be in an approved state.']
            ]);
        }
        if (!$version->status->isApproved()) {
            throw new ValidationException("Cannot complete review on version in '{$version->status->value}' status. Version must be in an approved state.", [
                'status' => ['Version must be in an approved state.']
            ]);
        }

        // 5. Validate outcome & require notes for REVISION_REQUIRED
        ReviewCycleValidator::validateOutcome($outcome, $notes);

        // 6. Execute the operation atomically
        return $this->transaction(function () use ($cycleId, $outcome, $notes, $userId): PlanReviewCycleDTO {
            $completedAt = date('Y-m-d H:i:s');
            $success = $this->reviewRepo->completeReview(
                $cycleId,
                $outcome,
                $notes,
                $userId,
                $completedAt
            );

            if (!$success) {
                throw new DatabaseException("Failed to update review cycle #{$cycleId} outcome.");
            }

            $dto = $this->reviewRepo->findById($cycleId);
            if ($dto === null) {
                throw new DatabaseException("Failed to retrieve completed review cycle #{$cycleId}.");
            }

            return $dto;
        });
    }

    public function getReviewCyclesForPlan(int $planId): array
    {
        $plan = $this->planRepo->findById($planId);
        if ($plan === null) {
            return [];
        }

        PlanAuthorizationGuard::requireCanViewPlan($plan->planningEntityId);

        return $this->reviewRepo->findByPlanId($planId);
    }
}
