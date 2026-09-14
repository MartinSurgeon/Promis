<?php

declare(strict_types=1);

/**
 * PROMIS Phase 1A Procurement Planning Domain Test Suite
 * Procurement Management Information System
 * University of Skills Training and Entrepreneurial Development (USTED)
 *
 * Standalone CLI test runner verifying the Planning domain contracts, DTOs,
 * validators, variance engines, and authorization integration points.
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Auth\Authorization;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\ValidationException;
use Promis\Src\Planning\Auth\PlanAuthorizationGuard;
use Promis\Src\Planning\Domain\DTO\ItemVarianceDTO;
use Promis\Src\Planning\Domain\DTO\PlanDTO;
use Promis\Src\Planning\Domain\DTO\PlanItemDTO;
use Promis\Src\Planning\Domain\DTO\PlanReviewCycleDTO;
use Promis\Src\Planning\Domain\DTO\PlanRevisionRecordDTO;
use Promis\Src\Planning\Domain\DTO\PlanVersionDTO;
use Promis\Src\Planning\Domain\PlanningPermissions;
use Promis\Src\Planning\Domain\PlanStatus;
use Promis\Src\Planning\Domain\PlanVersionStatus;
use Promis\Src\Planning\Domain\ReviewCycleStatus;
use Promis\Src\Planning\Domain\ReviewOutcome;
use Promis\Src\Planning\Domain\ReviewQuarter;
use Promis\Src\Planning\Domain\TargetQuarter;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepositoryInterface;
use Promis\Src\Planning\Service\PlanVersionService;
use Promis\Src\Planning\Service\ProcurementPlanService;
use Promis\Src\Planning\Validation\PlanItemValidator;
use Promis\Src\Planning\Validation\PlanValidator;
use Promis\Src\Planning\Validation\PlanVersionValidator;
use Promis\Src\Planning\Validation\ReviewCycleValidator;

final class PlanningDomainTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public static function main(): void
    {
        $tester = new self();
        $tester->run();
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS Phase 1A Procurement Planning Domain Test Suite\n";
        echo " University of Skills Training and Entrepreneurial Development (USTED)\n";
        echo " PHP Version: " . PHP_VERSION . "\n";
        echo "===============================================================\n\n";

        App::bootstrap(dirname(__DIR__));

        // 1. Domain Constants & Status Enums
        $this->testPlanStatuses();
        $this->testPlanVersionStatuses();
        $this->testQuarterEnums();
        $this->testReviewEnums();
        $this->testPlanningPermissions();

        // 2. Data Transfer Objects (DTOs)
        $this->testPlanDTOSerialization();
        $this->testPlanVersionDTOSerialization();
        $this->testPlanItemDTOSerialization();
        $this->testReviewCycleDTOSerialization();
        $this->testRevisionRecordDTOSerialization();

        // 3. Validation Logic
        $this->testPlanItemValidationSuccess();
        $this->testPlanItemValidationFailures();
        $this->testPlanValidationSuccess();
        $this->testPlanValidationBudgetCeilingExceeded();
        $this->testPlanValidationInvalidFiscalYear();
        $this->testReviewCycleValidation();
        $this->testPlanVersionValidation();

        // 4. Version Variance Calculation (FR-050)
        $this->testVersionVarianceCalculation();

        // 5. Authorization Guards Integration
        $this->testPlanAuthorizationGuards();

        // 6. Service Integration with Repositories
        $this->testPlanServiceFormulation();

        echo "\n===============================================================\n";
        echo " PLANNING DOMAIN TEST SUMMARY\n";
        echo " Passed: {$this->passed} / " . ($this->passed + $this->failed) . "\n";
        echo " Failed: {$this->failed}\n";
        echo "===============================================================\n";

        if ($this->failed > 0) {
            echo "\nFAILURES:\n";
            foreach ($this->failures as $f) {
                echo " - " . $f . "\n";
            }
            exit(1);
        } else {
            echo "\nALL PLANNING DOMAIN TESTS PASSED SUCCESSFULLY.\n";
            exit(0);
        }
    }

    private function assert(bool $condition, string $testName, string $failureMsg = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $msg = $failureMsg !== '' ? "{$testName} - {$failureMsg}" : $testName;
            $this->failures[] = $msg;
            echo " [FAIL] {$msg}\n";
        }
    }

    private function testPlanStatuses(): void
    {
        $this->assert(PlanStatus::DRAFT->value === 'DRAFT', 'PlanStatus::DRAFT is DRAFT');
        $this->assert(PlanStatus::DRAFT->isEditable() === true, 'PlanStatus::DRAFT is editable');
        $this->assert(PlanStatus::APPROVED->isEditable() === false, 'PlanStatus::APPROVED is not directly editable');
        $this->assert(PlanStatus::APPROVED->isApproved() === true, 'PlanStatus::APPROVED represents active approved state');
        $this->assert(count(PlanStatus::values()) === 7, 'PlanStatus contains exactly 7 lifecycle states');
    }

    private function testPlanVersionStatuses(): void
    {
        $this->assert(PlanVersionStatus::DRAFT->value === 'DRAFT', 'PlanVersionStatus::DRAFT is DRAFT');
        $this->assert(PlanVersionStatus::APPROVED->value === 'APPROVED', 'PlanVersionStatus::APPROVED is APPROVED');
        $this->assert(PlanVersionStatus::SUPERSEDED->value === 'SUPERSEDED', 'PlanVersionStatus::SUPERSEDED is SUPERSEDED');
        $this->assert(PlanVersionStatus::APPROVED->isApproved() === true, 'PlanVersionStatus::APPROVED isApproved() returns true');
    }

    private function testQuarterEnums(): void
    {
        $this->assert(TargetQuarter::Q1->value === 'Q1' && TargetQuarter::Q4->value === 'Q4', 'TargetQuarter enum covers Q1-Q4');
        $this->assert(ReviewQuarter::Q1->value === 'Q1' && ReviewQuarter::Q4->value === 'Q4', 'ReviewQuarter enum covers Q1-Q4');
    }

    private function testReviewEnums(): void
    {
        $this->assert(ReviewCycleStatus::PENDING->value === 'PENDING', 'ReviewCycleStatus contains PENDING');
        $this->assert(ReviewCycleStatus::COMPLETED->isCompleted() === true, 'ReviewCycleStatus::COMPLETED is completed');
        $this->assert(ReviewOutcome::NO_CHANGE->requiresRevision() === false, 'ReviewOutcome::NO_CHANGE does not require revision');
        $this->assert(ReviewOutcome::REVISION_REQUIRED->requiresRevision() === true, 'ReviewOutcome::REVISION_REQUIRED requires revision');
    }

    private function testPlanningPermissions(): void
    {
        $perms = PlanningPermissions::all();
        $this->assert(in_array(PlanningPermissions::CREATE, $perms, true), 'PlanningPermissions includes create');
        $this->assert(in_array(PlanningPermissions::REVIEW, $perms, true), 'PlanningPermissions includes review');
        $this->assert(in_array(PlanningPermissions::REVISE, $perms, true), 'PlanningPermissions includes revise');
        $this->assert(count($perms) === 9, 'PlanningPermissions defines 9 granular actions');
    }

    private function testPlanDTOSerialization(): void
    {
        $dto = new PlanDTO(
            id: 1,
            planNumber: 'APP-2026-ENT005',
            planningEntityId: 5,
            fiscalYear: 2026,
            currentVersionId: 1,
            status: PlanStatus::DRAFT,
            createdAt: '2026-09-12 10:00:00',
            createdBy: 10
        );

        $arr = $dto->toArray();
        $this->assert($arr['plan_number'] === 'APP-2026-ENT005', 'PlanDTO::toArray() exports plan_number correctly');

        $restored = PlanDTO::fromArray($arr);
        $this->assert($restored->id === 1 && $restored->fiscalYear === 2026, 'PlanDTO::fromArray() rehydrates DTO cleanly');
    }

    private function testPlanVersionDTOSerialization(): void
    {
        $dto = new PlanVersionDTO(
            id: 1,
            procurementPlanId: 10,
            versionNumber: '1.0',
            status: PlanVersionStatus::DRAFT,
            totalEstimatedCost: '15400.50',
            createdAt: '2026-09-12 10:00:00',
            createdBy: 10
        );

        $arr = $dto->toArray();
        $this->assert($arr['version_number'] === '1.0' && $arr['total_estimated_cost'] === '15400.50', 'PlanVersionDTO::toArray() exports values');

        $restored = PlanVersionDTO::fromArray($arr);
        $this->assert($restored->versionNumber === '1.0' && $restored->totalEstimatedCost === '15400.50', 'PlanVersionDTO::fromArray() rehydrates correctly');
    }

    private function testPlanItemDTOSerialization(): void
    {
        $dto = new PlanItemDTO(
            id: 100,
            planVersionId: 1,
            standardItemId: 50,
            itemDescription: 'A4 Printing Paper (80gsm)',
            categoryId: 2,
            uomId: 1,
            plannedQuantity: '200.00',
            estimatedUnitCost: '65.00',
            estimatedTotalCost: '13000.00',
            targetQuarter: TargetQuarter::Q1,
            fundingSource: 'GoG Subvention',
            justification: 'Quarterly administrative supply'
        );

        $arr = $dto->toArray();
        $this->assert($arr['planned_quantity'] === '200.00' && $arr['target_quarter'] === 'Q1', 'PlanItemDTO::toArray() exports correctly');

        $restored = PlanItemDTO::fromArray($arr);
        $this->assert($restored->standardItemId === 50 && $restored->estimatedTotalCost === '13000.00', 'PlanItemDTO::fromArray() rehydrates accurately');
    }

    private function testReviewCycleDTOSerialization(): void
    {
        $dto = new PlanReviewCycleDTO(
            id: 1,
            procurementPlanId: 10,
            activeVersionId: 1,
            fiscalYear: 2026,
            reviewQuarter: ReviewQuarter::Q2,
            reviewStatus: ReviewCycleStatus::COMPLETED,
            reviewOutcome: ReviewOutcome::REVISION_REQUIRED,
            reviewedByUserId: 15,
            completedAt: '2026-06-30 14:00:00',
            reviewNotes: 'Increased student enrollment requires additional consumables.'
        );

        $arr = $dto->toArray();
        $this->assert($arr['review_quarter'] === 'Q2' && $arr['review_outcome'] === 'REVISION_REQUIRED', 'PlanReviewCycleDTO exports outcome');

        $restored = PlanReviewCycleDTO::fromArray($arr);
        $this->assert($restored->reviewOutcome === ReviewOutcome::REVISION_REQUIRED, 'PlanReviewCycleDTO restores reviewOutcome enum');
    }

    private function testRevisionRecordDTOSerialization(): void
    {
        $dto = new PlanRevisionRecordDTO(
            id: 1,
            procurementPlanId: 10,
            reviewCycleId: 1,
            priorVersionId: 1,
            newVersionId: 2,
            revisionJustification: 'Scope expansion following Q2 review',
            submittedByUserId: 12,
            submittedAt: '2026-07-01 09:00:00'
        );

        $arr = $dto->toArray();
        $this->assert($arr['prior_version_id'] === 1 && $arr['new_version_id'] === 2, 'PlanRevisionRecordDTO exports version linkages');
    }

    private function testPlanItemValidationSuccess(): void
    {
        $validItem = [
            'standard_item_id' => 1,
            'item_description' => 'Heavy Duty Stapler',
            'category_id' => 1,
            'uom_id' => 1,
            'planned_quantity' => 10,
            'estimated_unit_cost' => 150.00,
            'target_quarter' => 'Q1',
            'funding_source' => 'IGF',
        ];

        $errors = PlanItemValidator::validate($validItem, 1);
        $this->assert(empty($errors), 'PlanItemValidator passes on valid line item');
    }

    private function testPlanItemValidationFailures(): void
    {
        $invalidItem = [
            'standard_item_id' => 0,
            'item_description' => '',
            'category_id' => 0,
            'uom_id' => 0,
            'planned_quantity' => -5,
            'estimated_unit_cost' => -20,
            'target_quarter' => 'INVALID_Q',
            'funding_source' => '',
        ];

        $errors = PlanItemValidator::validate($invalidItem, 1);
        $this->assert(count($errors) >= 6, 'PlanItemValidator catches all invalid field constraints on invalid item');
    }

    private function testPlanValidationSuccess(): void
    {
        $planData = [
            'planning_entity_id' => 5,
            'fiscal_year' => 2026,
        ];
        $items = [
            [
                'standard_item_id' => 1,
                'item_description' => 'A4 Paper',
                'category_id' => 1,
                'uom_id' => 1,
                'planned_quantity' => 100,
                'estimated_unit_cost' => 50.0,
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
            ]
        ];

        $thrown = false;
        try {
            PlanValidator::validate($planData, $items, 10000.00);
        } catch (ValidationException $e) {
            $thrown = true;
        }

        $this->assert(!$thrown, 'PlanValidator successfully validates compliant plan within budget limit');
    }

    private function testPlanValidationBudgetCeilingExceeded(): void
    {
        $planData = [
            'planning_entity_id' => 5,
            'fiscal_year' => 2026,
        ];
        $items = [
            [
                'standard_item_id' => 1,
                'item_description' => 'High End Server',
                'category_id' => 2,
                'uom_id' => 1,
                'planned_quantity' => 5,
                'estimated_unit_cost' => 20000.0, // Total = 100,000
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
            ]
        ];

        $thrown = false;
        try {
            // Budget ceiling is 50,000 (plan total is 100,000)
            PlanValidator::validate($planData, $items, 50000.00);
        } catch (ValidationException $e) {
            $thrown = true;
            $this->assert(isset($e->getErrors()['budget_ceiling']), 'PlanValidator blocks plan that exceeds budget ceiling');
        }

        if (!$thrown) {
            $this->assert(false, 'PlanValidator Budget Ceiling Check', 'Expected budget ceiling validation exception was not thrown');
        }
    }

    private function testPlanValidationInvalidFiscalYear(): void
    {
        $planData = [
            'planning_entity_id' => 5,
            'fiscal_year' => 1990, // Out of allowed bounds (2000-2100)
        ];
        $items = [
            [
                'standard_item_id' => 1,
                'item_description' => 'Test Item',
                'category_id' => 1,
                'uom_id' => 1,
                'planned_quantity' => 1,
                'estimated_unit_cost' => 10.0,
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
            ]
        ];

        $thrown = false;
        try {
            PlanValidator::validate($planData, $items);
        } catch (ValidationException $e) {
            $thrown = true;
            $this->assert(isset($e->getErrors()['fiscal_year']), 'PlanValidator rejects invalid fiscal year outside 2000-2100 bounds');
        }

        if (!$thrown) {
            $this->assert(false, 'PlanValidator Fiscal Year Check', 'Expected invalid fiscal year validation exception was not thrown');
        }
    }

    private function testReviewCycleValidation(): void
    {
        // Valid initiation
        $thrown = false;
        try {
            ReviewCycleValidator::validateInitiation(1, 1, 2026, 'Q2');
        } catch (ValidationException $e) {
            $thrown = true;
        }
        $this->assert(!$thrown, 'ReviewCycleValidator::validateInitiation passes on valid parameters');

        // Invalid outcome
        $thrown = false;
        try {
            ReviewCycleValidator::validateOutcome('INVALID_OUTCOME');
        } catch (ValidationException $e) {
            $thrown = true;
        }
        $this->assert($thrown, 'ReviewCycleValidator::validateOutcome rejects illegal outcome value');

        // REVISION_REQUIRED without mandatory notes
        $thrown = false;
        try {
            ReviewCycleValidator::validateOutcome('REVISION_REQUIRED', '');
        } catch (ValidationException $e) {
            $thrown = true;
            $this->assert(isset($e->getErrors()['review_notes']), 'ReviewCycleValidator enforces mandatory notes when revision is required');
        }
    }

    private function testPlanVersionValidation(): void
    {
        $this->assert(PlanVersionValidator::nextVersionNumber('1.0') === '2.0', 'nextVersionNumber increments 1.0 to 2.0');
        $this->assert(PlanVersionValidator::nextVersionNumber('2.0') === '3.0', 'nextVersionNumber increments 2.0 to 3.0');

        $thrown = false;
        try {
            PlanVersionValidator::validateRevision(1, 1, '', []);
        } catch (ValidationException $e) {
            $thrown = true;
            $this->assert(isset($e->getErrors()['revision_justification']), 'PlanVersionValidator requires non-empty revision justification');
            $this->assert(isset($e->getErrors()['items']), 'PlanVersionValidator requires non-empty revised items');
        }
    }

    private function testVersionVarianceCalculation(): void
    {
        $versionRepoMock = new class implements ProcurementPlanVersionRepositoryInterface {
            public function findById(int $id): ?PlanVersionDTO {
                return new PlanVersionDTO($id, 10, $id === 1 ? '1.0' : '2.0', PlanVersionStatus::APPROVED, '5000.00');
            }
            public function findByPlanId(int $planId): array { return []; }
            public function findByPlanAndVersion(int $planId, string $versionNumber): ?PlanVersionDTO { return null; }
            public function findLatestVersion(int $planId): ?PlanVersionDTO { return null; }
            public function create(array $data): int { return 1; }
            public function updateStatus(int $id, string $status, ?int $approvedByUserId = null, ?string $approvalDate = null): bool { return true; }
            public function updateTotalCost(int $id, string $totalCost): bool { return true; }
        };

        // Mock item repository for variance calculation
        $itemRepoMock = new class implements ProcurementPlanItemRepositoryInterface {
            public function findById(int $id): ?PlanItemDTO { return null; }
            public function findByVersionId(int $versionId): array {
                if ($versionId === 1) {
                    // Version 1.0 baseline items
                    return [
                        new PlanItemDTO(1, 1, 10, 'A4 Paper', 1, 1, '100.00', '50.00', '5000.00', TargetQuarter::Q1, 'IGF'),
                        new PlanItemDTO(2, 1, 20, 'Desk Chairs', 2, 1, '10.00', '300.00', '3000.00', TargetQuarter::Q1, 'IGF'),
                        new PlanItemDTO(3, 1, 30, 'Old Projector', 3, 1, '2.00', '2000.00', '4000.00', TargetQuarter::Q2, 'IGF'),
                    ];
                } else {
                    // Version 2.0 revised items
                    return [
                        new PlanItemDTO(4, 2, 10, 'A4 Paper', 1, 1, '150.00', '50.00', '7500.00', TargetQuarter::Q1, 'IGF'), // MODIFIED (+50 qty, +2500 cost)
                        new PlanItemDTO(5, 2, 20, 'Desk Chairs', 2, 1, '10.00', '300.00', '3000.00', TargetQuarter::Q1, 'IGF'), // UNCHANGED
                        // Item 30 ('Old Projector') is REMOVED
                        new PlanItemDTO(6, 2, 40, 'Digital Smartboard', 3, 1, '1.00', '8000.00', '8000.00', TargetQuarter::Q3, 'IGF'), // ADDED
                    ];
                }
            }
            public function create(array $data): int { return 1; }
            public function createBatch(int $versionId, array $items, int $createdBy): int { return count($items); }
            public function deleteByVersionId(int $versionId): int { return 1; }
        };

        $service = new PlanVersionService(
            db: null,
            planRepo: null,
            versionRepo: $versionRepoMock,
            itemRepo: $itemRepoMock,
            revisionRepo: null
        );

        $variances = $service->calculateVersionVariance(1, 2);

        $this->assert(count($variances) === 4, 'Variance engine produces exactly 4 delta comparisons');

        $byItem = [];
        foreach ($variances as $v) {
            $byItem[$v->standardItemId] = $v;
        }

        $this->assert(isset($byItem[10]) && $byItem[10]->changeType === 'MODIFIED', 'Item #10 (A4 Paper) identified as MODIFIED');
        $this->assert($byItem[10]->quantityDelta === '50.00' && $byItem[10]->costDelta === '2500.00', 'Item #10 delta values calculated accurately (+50 qty, +2500 GHS)');

        $this->assert(isset($byItem[20]) && $byItem[20]->changeType === 'UNCHANGED', 'Item #20 (Desk Chairs) identified as UNCHANGED');

        $this->assert(isset($byItem[30]) && $byItem[30]->changeType === 'REMOVED', 'Item #30 (Old Projector) identified as REMOVED');
        $this->assert($byItem[30]->quantityDelta === '-2.00' && $byItem[30]->costDelta === '-4000.00', 'Item #30 negative delta calculated accurately (-2 qty, -4000 GHS)');

        $this->assert(isset($byItem[40]) && $byItem[40]->changeType === 'ADDED', 'Item #40 (Digital Smartboard) identified as ADDED');
        $this->assert($byItem[40]->quantityDelta === '1.00' && $byItem[40]->costDelta === '8000.00', 'Item #40 positive delta calculated accurately (+1 qty, +8000 GHS)');
    }

    private function testPlanAuthorizationGuards(): void
    {
        // 1. Unauthenticated visitor
        AuthManager::logout();
        $this->assert(PlanAuthorizationGuard::canCreatePlan(10) === false, 'PlanAuthorizationGuard denies unauthenticated guest by default');

        $thrown = false;
        try {
            PlanAuthorizationGuard::requireCanCreatePlan(10);
        } catch (AuthorizationException $e) {
            $thrown = true;
        }
        $this->assert($thrown, 'requireCanCreatePlan throws AuthorizationException for unauthenticated user');

        // 2. User with permission scoped to Entity #10
        $officer = [
            'id' => 50,
            'email' => 'planning_officer@usted.edu.gh',
            'permissions' => [],
            'entity_permissions' => [
                10 => [PlanningPermissions::CREATE, PlanningPermissions::VIEW, PlanningPermissions::REVIEW],
            ]
        ];
        AuthManager::login($officer);

        $this->assert(PlanAuthorizationGuard::canCreatePlan(10) === true, 'PlanAuthorizationGuard allows user with entity-scoped permission for Entity #10');
        $this->assert(PlanAuthorizationGuard::canCreatePlan(20) === false, 'PlanAuthorizationGuard denies user for out-of-scope Entity #20');
        $this->assert(PlanAuthorizationGuard::canApprovePlan(10) === false, 'PlanAuthorizationGuard denies ungranted action (approve) on Entity #10');
    }

    private function testPlanServiceFormulation(): void
    {
        // Mock repositories for service layer execution
        $planRepoMock = new class implements ProcurementPlanRepositoryInterface {
            private array $plans = [];
            public function findById(int $id): ?PlanDTO {
                return $this->plans[$id] ?? null;
            }
            public function findByEntityAndYear(int $planningEntityId, int $fiscalYear): ?PlanDTO {
                foreach ($this->plans as $p) {
                    if ($p->planningEntityId === $planningEntityId && $p->fiscalYear === $fiscalYear) {
                        return $p;
                    }
                }
                return null;
            }
            public function findByPlanNumber(string $planNumber): ?PlanDTO { return null; }
            public function findByEntity(int $planningEntityId): array { return array_values($this->plans); }
            public function create(array $data): int {
                $id = count($this->plans) + 1;
                $data['id'] = $id;
                $this->plans[$id] = PlanDTO::fromArray($data);
                return $id;
            }
            public function updateStatus(int $id, string $status, ?int $updatedBy = null): bool { return true; }
            public function setCurrentVersion(int $id, int $versionId, ?int $updatedBy = null): bool { return true; }
        };

        $versionRepoMock = new class implements ProcurementPlanVersionRepositoryInterface {
            private array $versions = [];
            public function findById(int $id): ?PlanVersionDTO {
                return $this->versions[$id] ?? null;
            }
            public function findByPlanId(int $planId): array { return array_values($this->versions); }
            public function findByPlanAndVersion(int $planId, string $versionNumber): ?PlanVersionDTO { return null; }
            public function findLatestVersion(int $planId): ?PlanVersionDTO { return end($this->versions) ?: null; }
            public function create(array $data): int {
                $id = count($this->versions) + 1;
                $data['id'] = $id;
                $this->versions[$id] = PlanVersionDTO::fromArray($data);
                return $id;
            }
            public function updateStatus(int $id, string $status, ?int $approvedByUserId = null, ?string $approvalDate = null): bool {
                if (isset($this->versions[$id])) {
                    $arr = $this->versions[$id]->toArray();
                    $arr['status'] = $status;
                    $this->versions[$id] = PlanVersionDTO::fromArray($arr);
                }
                return true;
            }
            public function updateTotalCost(int $id, string $totalCost): bool {
                if (isset($this->versions[$id])) {
                    $arr = $this->versions[$id]->toArray();
                    $arr['total_estimated_cost'] = $totalCost;
                    $this->versions[$id] = PlanVersionDTO::fromArray($arr);
                }
                return true;
            }
        };

        $itemRepoMock = new class implements ProcurementPlanItemRepositoryInterface {
            private array $items = [];
            public function findById(int $id): ?PlanItemDTO { return null; }
            public function findByVersionId(int $versionId): array {
                return array_values(array_filter($this->items, fn($i) => $i->planVersionId === $versionId));
            }
            public function create(array $data): int {
                $id = count($this->items) + 1;
                $data['id'] = $id;
                $this->items[$id] = PlanItemDTO::fromArray($data);
                return $id;
            }
            public function createBatch(int $versionId, array $items, int $createdBy): int {
                foreach ($items as $it) {
                    $it['plan_version_id'] = $versionId;
                    $it['created_by'] = $createdBy;
                    $this->create($it);
                }
                return count($items);
            }
            public function deleteByVersionId(int $versionId): int { return 0; }
        };

        $service = new ProcurementPlanService(
            db: null,
            planRepo: $planRepoMock,
            versionRepo: $versionRepoMock,
            itemRepo: $itemRepoMock
        );

        $items = [
            [
                'standard_item_id' => 101,
                'item_description' => 'Stationery Pack',
                'category_id' => 1,
                'uom_id' => 1,
                'planned_quantity' => 50,
                'estimated_unit_cost' => 100.00,
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
            ]
        ];

        // Ensure user is authorized for entity #10
        AuthManager::login([
            'id' => 50,
            'email' => 'officer@usted.edu.gh',
            'permissions' => [PlanningPermissions::CREATE, PlanningPermissions::VIEW],
        ]);

        $createdPlan = $service->createDraftPlan(
            planningEntityId: 10,
            fiscalYear: 2026,
            items: $items,
            userId: 50,
            budgetCeiling: 20000.00
        );

        $this->assert($createdPlan->id === 1, 'ProcurementPlanService creates plan and assigns surrogate ID');
        $this->assert($createdPlan->planNumber === 'APP-2026-ENT010', 'ProcurementPlanService generates standard plan number (APP-2026-ENT010)');
        $this->assert($createdPlan->currentVersion !== null, 'ProcurementPlanService attaches initial Version 1.0 snapshot');
        $this->assert($createdPlan->currentVersion->totalEstimatedCost === '5000.00', 'ProcurementPlanService computes total estimated cost accurately (5000.00 GHS)');
    }
}

// Execute tests
PlanningDomainTest::main();
