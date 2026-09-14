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
use Promis\Src\Planning\Domain\DTO\PlanDTO;
use Promis\Src\Planning\Domain\DTO\PlanVersionDTO;
use Promis\Src\Planning\Domain\PlanStatus;
use Promis\Src\Planning\Domain\PlanVersionStatus;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepository;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepository;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepositoryInterface;
use Promis\Src\Planning\Validation\PlanItemValidator;
use Promis\Src\Planning\Validation\PlanValidator;

/**
 * Application service implementing Annual Procurement Plan formulation and management.
 * Enforces arbitrary-precision decimal strings, transaction ownership, authorization, and cross-plan integrity.
 */
final class ProcurementPlanService extends BaseService implements ProcurementPlanServiceInterface
{
    private ProcurementPlanRepositoryInterface $planRepo;
    private ProcurementPlanVersionRepositoryInterface $versionRepo;
    private ProcurementPlanItemRepositoryInterface $itemRepo;

    public function __construct(
        ?PDO $db = null,
        ?ProcurementPlanRepositoryInterface $planRepo = null,
        ?ProcurementPlanVersionRepositoryInterface $versionRepo = null,
        ?ProcurementPlanItemRepositoryInterface $itemRepo = null
    ) {
        parent::__construct($db);
        $this->planRepo = $planRepo ?? new ProcurementPlanRepository($this->db);
        $this->versionRepo = $versionRepo ?? new ProcurementPlanVersionRepository($this->db);
        $this->itemRepo = $itemRepo ?? new ProcurementPlanItemRepository($this->db);
    }

    public function createPlan(int $planningEntityId, int $fiscalYear, int $userId): PlanDTO
    {
        if ($userId <= 0) {
            throw new ValidationException('A valid acting user ID is required.', [
                'user_id' => ['User ID must be a positive integer.']
            ]);
        }

        // 1. Enforce server-side authorization check
        PlanAuthorizationGuard::requireCanCreatePlan($planningEntityId);

        // 2. Validate bounds
        if ($planningEntityId <= 0) {
            throw new ValidationException('A valid planning entity is required.', [
                'planning_entity_id' => ['Planning entity ID must be a positive integer.']
            ]);
        }

        if ($fiscalYear < 2000 || $fiscalYear > 2100) {
            throw new ValidationException('Fiscal year must be a valid 4-digit year between 2000 and 2100.', [
                'fiscal_year' => ['Fiscal year must be between 2000 and 2100.']
            ]);
        }

        // 3. Confirm planning entity exists
        if (!$this->confirmPlanningEntityExists($planningEntityId)) {
            throw new ValidationException("Planning entity #{$planningEntityId} does not exist.", [
                'planning_entity_id' => ["Planning entity #{$planningEntityId} does not exist."]
            ]);
        }

        // 4. Ensure uniqueness: Only one plan per planning entity per fiscal year
        $existing = $this->planRepo->findByEntityAndYear($planningEntityId, $fiscalYear);
        if ($existing !== null) {
            throw new ValidationException("A procurement plan for entity #{$planningEntityId} in fiscal year {$fiscalYear} already exists.", [
                'fiscal_year' => ["A plan for entity #{$planningEntityId} in year {$fiscalYear} already exists (Plan #{$existing->planNumber})."]
            ]);
        }

        return $this->createPlanInternal($planningEntityId, $fiscalYear, $userId);
    }

    public function createInitialVersion(
        int $planId,
        int $userId,
        string $versionNumber = '1.0',
        ?string $totalEstimatedCost = null
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

        if (!PlanAuthorizationGuard::canCreatePlan($plan->planningEntityId) && !PlanAuthorizationGuard::canEditPlan($plan->planningEntityId)) {
            PlanAuthorizationGuard::requireCanEditPlan($plan->planningEntityId);
        }

        if ($plan->status !== PlanStatus::DRAFT) {
            throw new ValidationException("Cannot create a version for a plan in status '{$plan->status->value}'. Plan must be in DRAFT status.", [
                'status' => ['Plan must be in DRAFT status.']
            ]);
        }

        if (trim($versionNumber) === '' || !preg_match('/^\d+(\.\d+)*$/', $versionNumber)) {
            throw new ValidationException("Version number '{$versionNumber}' is invalid.", [
                'version_number' => ['Version number must follow numeric dot notation, e.g. 1.0.']
            ]);
        }

        $existing = $this->versionRepo->findByPlanAndVersion($planId, $versionNumber);
        if ($existing !== null) {
            throw new ValidationException("Version {$versionNumber} already exists for plan #{$planId}.", [
                'version_number' => ['Version already exists.']
            ]);
        }

        $normalizedCost = $totalEstimatedCost !== null 
            ? Decimal::normalize($totalEstimatedCost, 2) 
            : '0.00';

        return $this->createInitialVersionInternal($planId, $userId, $versionNumber, $normalizedCost);
    }

    public function addPlanItems(int $planId, int $versionId, array $items, int $userId): int
    {
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

        if (!PlanAuthorizationGuard::canCreatePlan($plan->planningEntityId) && !PlanAuthorizationGuard::canEditPlan($plan->planningEntityId)) {
            PlanAuthorizationGuard::requireCanEditPlan($plan->planningEntityId);
        }

        $version = $this->versionRepo->findById($versionId);
        if ($version === null) {
            throw new ValidationException("Plan version #{$versionId} not found.", [
                'plan_version_id' => ['Version not found.']
            ]);
        }

        // Cross-plan check: Version must belong to plan
        if ($version->procurementPlanId !== $planId) {
            throw new ValidationException("Plan version #{$versionId} does not belong to procurement plan #{$planId}.", [
                'plan_version_id' => ['Cross-plan version item addition is not permitted.']
            ]);
        }

        if ($version->status !== PlanVersionStatus::DRAFT) {
            throw new ValidationException("Cannot add items to version in '{$version->status->value}' status.", [
                'status' => ['Version must be in DRAFT status.']
            ]);
        }

        if (empty($items)) {
            throw new ValidationException('Plan must contain at least one line item.', [
                'items' => ['At least one item is required.']
            ]);
        }

        $itemErrors = [];
        $preparedItems = [];
        foreach ($items as $index => $item) {
            $lineErrors = PlanItemValidator::validate($item, $index + 1);
            if (!empty($lineErrors)) {
                $itemErrors = array_merge($itemErrors, $lineErrors);
            }

            $rawQty = $item['planned_quantity'] ?? null;
            $rawCost = $item['estimated_unit_cost'] ?? null;

            if (Decimal::isValid($rawQty) && Decimal::isValid($rawCost)) {
                $qtyStr = Decimal::normalize($rawQty, 2);
                $costStr = Decimal::normalize($rawCost, 2);
                $lineTotal = Decimal::mul($qtyStr, $costStr, 2);
                $item['planned_quantity'] = $qtyStr;
                $item['estimated_unit_cost'] = $costStr;
                $item['estimated_total_cost'] = $lineTotal;
                $preparedItems[] = $item;
            }
        }

        if (!empty($itemErrors)) {
            throw new ValidationException('Plan items validation failed.', ['items' => $itemErrors]);
        }

        return $this->transaction(function () use ($versionId, $preparedItems, $userId): int {
            $count = $this->addPlanItemsInternal($versionId, $preparedItems, $userId);
            $this->calculateAndPersistVersionTotalInternal($versionId);
            return $count;
        });
    }

    public function calculateAndPersistVersionTotal(int $versionId): string
    {
        $version = $this->versionRepo->findById($versionId);
        if ($version === null) {
            throw new ValidationException("Plan version #{$versionId} not found.", [
                'plan_version_id' => ['Version not found.']
            ]);
        }

        $plan = $this->planRepo->findById($version->procurementPlanId);
        if ($plan !== null) {
            if (!PlanAuthorizationGuard::canCreatePlan($plan->planningEntityId) && !PlanAuthorizationGuard::canEditPlan($plan->planningEntityId)) {
                PlanAuthorizationGuard::requireCanEditPlan($plan->planningEntityId);
            }
        }

        return $this->calculateAndPersistVersionTotalInternal($versionId);
    }

    public function setCurrentVersion(int $planId, int $versionId, int $userId): bool
    {
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

        if (!PlanAuthorizationGuard::canCreatePlan($plan->planningEntityId) && !PlanAuthorizationGuard::canEditPlan($plan->planningEntityId)) {
            PlanAuthorizationGuard::requireCanEditPlan($plan->planningEntityId);
        }

        $version = $this->versionRepo->findById($versionId);
        if ($version === null) {
            throw new ValidationException("Plan version #{$versionId} not found.", [
                'plan_version_id' => ['Version not found.']
            ]);
        }

        // Cross-plan check: Version must belong to plan
        if ($version->procurementPlanId !== $planId) {
            throw new ValidationException("Plan version #{$versionId} does not belong to procurement plan #{$planId}.", [
                'current_version_id' => ['Cross-plan version assignment is not permitted.']
            ]);
        }

        // Eligibility check: Cannot assign SUPERSEDED or REJECTED version as current
        if ($version->status === PlanVersionStatus::SUPERSEDED || $version->status === PlanVersionStatus::REJECTED) {
            throw new ValidationException("Cannot assign a {$version->status->value} version as current version.", [
                'current_version_id' => ["{$version->status->value} versions are ineligible to become current."]
            ]);
        }

        return $this->setCurrentVersionInternal($planId, $versionId, $userId);
    }

    public function createDraftPlan(
        int $planningEntityId,
        int $fiscalYear,
        array $items,
        int $userId,
        string|float|int|null $budgetCeiling = null
    ): PlanDTO {
        if ($userId <= 0) {
            throw new ValidationException('A valid acting user ID is required.', [
                'user_id' => ['User ID must be a positive integer.']
            ]);
        }

        // 1. Enforce server-side authorization check
        PlanAuthorizationGuard::requireCanCreatePlan($planningEntityId);

        // 2. Validate input domain rules including ceiling
        PlanValidator::validate([
            'planning_entity_id' => $planningEntityId,
            'fiscal_year' => $fiscalYear,
        ], $items, $budgetCeiling);

        // 3. Confirm planning entity exists
        if (!$this->confirmPlanningEntityExists($planningEntityId)) {
            throw new ValidationException("Planning entity #{$planningEntityId} does not exist.", [
                'planning_entity_id' => ["Planning entity #{$planningEntityId} does not exist."]
            ]);
        }

        // 4. Ensure uniqueness: Only one plan per planning entity per fiscal year
        $existing = $this->planRepo->findByEntityAndYear($planningEntityId, $fiscalYear);
        if ($existing !== null) {
            throw new ValidationException("A procurement plan for entity #{$planningEntityId} in fiscal year {$fiscalYear} already exists.", [
                'fiscal_year' => ["A plan for entity #{$planningEntityId} in year {$fiscalYear} already exists (Plan #{$existing->planNumber})."]
            ]);
        }

        // Prepare line items
        $preparedItems = [];
        foreach ($items as $item) {
            $qtyStr = Decimal::normalize($item['planned_quantity'] ?? '0.00', 2);
            $costStr = Decimal::normalize($item['estimated_unit_cost'] ?? '0.00', 2);
            $lineTotal = Decimal::mul($qtyStr, $costStr, 2);
            $item['planned_quantity'] = $qtyStr;
            $item['estimated_unit_cost'] = $costStr;
            $item['estimated_total_cost'] = $lineTotal;
            $preparedItems[] = $item;
        }

        // 5. Execute transactional composite creation
        return $this->transaction(function () use ($planningEntityId, $fiscalYear, $preparedItems, $userId): PlanDTO {
            // Step 1: Create plan container
            $planDto = $this->createPlanInternal($planningEntityId, $fiscalYear, $userId);

            // Step 2: Create initial version 1.0 container
            $versionDto = $this->createInitialVersionInternal($planDto->id, $userId, '1.0', '0.00');

            // Step 3: Insert line items
            $inserted = $this->addPlanItemsInternal($versionDto->id, $preparedItems, $userId);
            if ($inserted !== count($preparedItems)) {
                throw new DatabaseException("Failed to insert all planned line items ({$inserted} of " . count($preparedItems) . " inserted).");
            }

            // Step 4: Calculate and persist version total
            $this->calculateAndPersistVersionTotalInternal($versionDto->id);

            // Step 5: Set current version pointer
            $this->setCurrentVersionInternal($planDto->id, $versionDto->id, $userId);

            // Step 6: Retrieve and return fully hydrated plan DTO
            $hydrated = $this->getPlanDetails($planDto->id, $versionDto->id);
            if ($hydrated === null) {
                throw new DatabaseException("Failed to retrieve formulated plan #{$planDto->id} details.");
            }

            return $hydrated;
        });
    }

    public function submitPlanForReview(int $planId, int $userId): PlanDTO
    {
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

        PlanAuthorizationGuard::requireCanSubmitPlan($plan->planningEntityId);

        $errors = $this->validatePlanForSubmission($planId);
        if (!empty($errors)) {
            throw new ValidationException('Procurement plan cannot be submitted for review.', [
                'submission' => $errors
            ]);
        }

        return $this->transaction(function () use ($planId, $plan, $userId): PlanDTO {
            if ($plan->currentVersionId !== null) {
                $this->calculateAndPersistVersionTotalInternal($plan->currentVersionId);

                $updatedVersion = $this->versionRepo->updateStatus($plan->currentVersionId, PlanVersionStatus::SUBMITTED->value);
                if (!$updatedVersion) {
                    throw new DatabaseException("Failed to update version #{$plan->currentVersionId} status to SUBMITTED.");
                }
            }

            $updatedPlan = $this->planRepo->updateStatus($planId, PlanStatus::SUBMITTED->value, $userId);
            if (!$updatedPlan) {
                throw new DatabaseException("Failed to update plan #{$planId} status to SUBMITTED.");
            }

            $hydrated = $this->getPlanDetails($planId);
            if ($hydrated === null) {
                throw new DatabaseException("Failed to retrieve submitted plan #{$planId}.");
            }

            return $hydrated;
        });
    }

    public function getPlanDetails(int $planId, ?int $versionId = null): ?PlanDTO
    {
        $plan = $this->planRepo->findById($planId);
        if ($plan === null) {
            return null;
        }

        PlanAuthorizationGuard::requireCanViewPlan($plan->planningEntityId);

        $targetVersionId = $versionId ?? $plan->currentVersionId;
        $versionDto = null;

        if ($targetVersionId !== null) {
            $versionDto = $this->getPlanVersion($targetVersionId);
        }

        return new PlanDTO(
            id: $plan->id,
            planNumber: $plan->planNumber,
            planningEntityId: $plan->planningEntityId,
            fiscalYear: $plan->fiscalYear,
            currentVersionId: $plan->currentVersionId,
            status: $plan->status,
            createdAt: $plan->createdAt,
            createdBy: $plan->createdBy,
            updatedAt: $plan->updatedAt,
            updatedBy: $plan->updatedBy,
            currentVersion: $versionDto
        );
    }

    public function getPlanVersion(int $versionId): ?PlanVersionDTO
    {
        $version = $this->versionRepo->findById($versionId);
        if ($version === null) {
            return null;
        }

        $plan = $this->planRepo->findById($version->procurementPlanId);
        if ($plan !== null) {
            PlanAuthorizationGuard::requireCanViewPlan($plan->planningEntityId);
        }

        $items = $this->itemRepo->findByVersionId($versionId);

        return PlanVersionDTO::fromArray($version->toArray(), $items);
    }

    public function validatePlanForSubmission(int $planId): array
    {
        $plan = $this->planRepo->findById($planId);
        if ($plan === null) {
            return ['Plan does not exist.'];
        }

        if (!$plan->status->isEditable()) {
            return ["Plan is in status '{$plan->status->value}' and cannot be submitted."];
        }

        if ($plan->currentVersionId === null) {
            return ['Plan has no active version snapshot attached.'];
        }

        $version = $this->versionRepo->findById($plan->currentVersionId);
        if ($version === null || $version->procurementPlanId !== $planId) {
            return ['Plan active version is invalid or does not belong to this plan.'];
        }

        $items = $this->itemRepo->findByVersionId($plan->currentVersionId);
        if (empty($items)) {
            return ['Plan must contain at least one line item prior to submission.'];
        }

        $errors = [];
        foreach ($items as $index => $item) {
            $lineErrors = PlanItemValidator::validate($item->toArray(), $index + 1);
            if (!empty($lineErrors)) {
                $errors = array_merge($errors, $lineErrors);
            }
        }

        return $errors;
    }

    // =========================================================================
    // Private Internal Transaction-Aware Helpers
    // =========================================================================

    private function createPlanInternal(int $planningEntityId, int $fiscalYear, int $userId): PlanDTO
    {
        $planNumber = sprintf('APP-%d-ENT%03d', $fiscalYear, $planningEntityId);

        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $planningEntityId,
            'fiscal_year' => $fiscalYear,
            'current_version_id' => null,
            'status' => PlanStatus::DRAFT->value,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
        ]);

        if ($planId <= 0) {
            throw new DatabaseException("Failed to persist procurement plan record.");
        }

        $dto = $this->planRepo->findById($planId);
        if ($dto === null) {
            throw new DatabaseException("Failed to retrieve created procurement plan #{$planId}.");
        }

        return $dto;
    }

    private function createInitialVersionInternal(
        int $planId,
        int $userId,
        string $versionNumber,
        string $totalEstimatedCost
    ): PlanVersionDTO {
        $versionId = $this->versionRepo->create([
            'procurement_plan_id' => $planId,
            'version_number' => $versionNumber,
            'status' => PlanVersionStatus::DRAFT->value,
            'total_estimated_cost' => $totalEstimatedCost,
            'approval_date' => null,
            'approved_by_user_id' => null,
            'revision_reason' => 'Initial baseline plan formulation',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
        ]);

        if ($versionId <= 0) {
            throw new DatabaseException("Failed to persist procurement plan version record.");
        }

        $dto = $this->versionRepo->findById($versionId);
        if ($dto === null) {
            throw new DatabaseException("Failed to retrieve created version #{$versionId}.");
        }

        return $dto;
    }

    private function addPlanItemsInternal(int $versionId, array $items, int $userId): int
    {
        $count = $this->itemRepo->createBatch($versionId, $items, $userId);
        if ($count !== count($items)) {
            throw new DatabaseException("Batch item insert mismatch ({$count} of " . count($items) . " inserted).");
        }

        return $count;
    }

    private function calculateAndPersistVersionTotalInternal(int $versionId): string
    {
        $items = $this->itemRepo->findByVersionId($versionId);
        $totalStr = '0.00';
        foreach ($items as $item) {
            $lineTotal = Decimal::mul($item->plannedQuantity, $item->estimatedUnitCost, 2);
            $totalStr = Decimal::add($totalStr, $lineTotal, 2);
        }

        $success = $this->versionRepo->updateTotalCost($versionId, $totalStr);
        if (!$success) {
            throw new DatabaseException("Failed to update version #{$versionId} total cost to GHS {$totalStr}.");
        }

        return $totalStr;
    }

    private function setCurrentVersionInternal(int $planId, int $versionId, int $userId): bool
    {
        $success = $this->planRepo->setCurrentVersion($planId, $versionId, $userId);
        if (!$success) {
            throw new DatabaseException("Failed to set current version pointer to version #{$versionId} on plan #{$planId}.");
        }

        return true;
    }

    private function confirmPlanningEntityExists(int $planningEntityId): bool
    {
        if ($this->planRepo instanceof ProcurementPlanRepository) {
            try {
                $stmt = $this->db->prepare("SELECT 1 FROM `planning_entities` WHERE `id` = :id LIMIT 1");
                $stmt->execute(['id' => $planningEntityId]);
                return $stmt->fetch() !== false;
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }
}
