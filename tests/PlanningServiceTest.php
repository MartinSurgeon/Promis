<?php

declare(strict_types=1);

/**
 * PROMIS Phase 1C Procurement Planning Application Service Layer Test Suite
 * Procurement Management Information System
 * University of Science and Technology, Dedicated (USTED)
 *
 * Standalone CLI test runner verifying the application service layer workflows,
 * business invariants, cross-plan checks, decimal-safe monetary calculations,
 * and multi-repository transaction rollback integrity against physical MariaDB/MySQL.
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\AppException;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\DatabaseException;
use Promis\Core\Exception\ValidationException;
use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\DTO\PlanDTO;
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
use Promis\Src\Planning\Repository\PlanReviewCycleRepository;
use Promis\Src\Planning\Repository\PlanRevisionRecordRepository;
use Promis\Src\Planning\Repository\PlanRevisionRecordRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepository;
use Promis\Src\Planning\Service\PlanReviewCycleService;
use Promis\Src\Planning\Service\PlanVersionService;
use Promis\Src\Planning\Service\ProcurementPlanService;


final class PlanningServiceTest
{
    private PDO $db;
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    // Tracked IDs for clean fixture teardown
    private array $trackedUsers = [];
    private array $trackedCampuses = [];
    private array $trackedEntityTypes = [];
    private array $trackedEntities = [];
    private array $trackedCategories = [];
    private array $trackedUoms = [];
    private array $trackedStandardItems = [];
    private array $trackedPlans = [];
    private array $trackedVersions = [];
    private array $trackedItems = [];
    private array $trackedCycles = [];
    private array $trackedRevisions = [];

    // Services under test
    private ProcurementPlanService $planService;
    private PlanReviewCycleService $cycleService;
    private PlanVersionService $versionService;

    // Repositories for state verification
    private ProcurementPlanRepository $planRepo;
    private ProcurementPlanVersionRepository $versionRepo;
    private ProcurementPlanItemRepository $itemRepo;
    private PlanReviewCycleRepository $cycleRepo;
    private PlanRevisionRecordRepository $revisionRepo;

    // Shared base fixtures
    private int $fixtureUserId = 0;
    private int $fixtureApproverId = 0;
    private int $fixtureCampusId = 0;
    private int $fixtureEntityTypeId = 0;
    private int $fixtureEntity1Id = 0;
    private int $fixtureEntity2Id = 0;
    private int $fixtureCategoryId = 0;
    private int $fixtureUomId = 0;
    private int $fixtureStandardItem1Id = 0;
    private int $fixtureStandardItem2Id = 0;
    private int $fixtureStandardItem3Id = 0;

    // Workflow state carried between successive stages
    private ?int $workflowPlanId = null;
    private ?int $workflowV1Id = null;
    private ?int $workflowV2Id = null;
    private ?int $workflowCycle1Id = null;
    private ?int $workflowCycle2Id = null;
    private ?int $workflowRevisionId = null;

    public static function main(): void
    {
        $tester = new self();
        $tester->run();
    }

    public function __construct()
    {
        App::bootstrap(dirname(__DIR__));
        $this->db = Connection::get();

        $this->planRepo = new ProcurementPlanRepository($this->db);
        $this->versionRepo = new ProcurementPlanVersionRepository($this->db);
        $this->itemRepo = new ProcurementPlanItemRepository($this->db);
        $this->cycleRepo = new PlanReviewCycleRepository($this->db);
        $this->revisionRepo = new PlanRevisionRecordRepository($this->db);

        $this->planService = new ProcurementPlanService($this->db, $this->planRepo, $this->versionRepo, $this->itemRepo);
        $this->cycleService = new PlanReviewCycleService($this->db, $this->cycleRepo, $this->planRepo, $this->versionRepo);
        $this->versionService = new PlanVersionService($this->db, $this->planRepo, $this->versionRepo, $this->itemRepo, $this->revisionRepo, $this->cycleRepo);
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS Phase 1C Procurement Planning Service Test Suite\n";
        echo " University of Science and Technology, Dedicated (USTED)\n";
        echo " Relational Engine: MariaDB 10.4+ / MySQL 8.x\n";
        echo " PHP Version: " . PHP_VERSION . "\n";
        echo "===============================================================\n\n";

        try {
            // Setup base fixtures and user authentication context
            $this->setupBaseFixtures();

            // Scenario 1: Create plan workflow
            $this->testScenario1_CreatePlanWorkflow();

            // Scenario 2: Create initial version
            $this->testScenario2_CreateInitialVersion();

            // Scenario 3: Add items and calculate totals
            $this->testScenario3_AddItemsAndCalculateTotals();

            // Scenario 4: Set current version
            $this->testScenario4_SetCurrentVersion();

            // Scenario 5: Submit plan for review
            $this->testScenario5_SubmitPlanForReview();

            // Scenario 6: Initiate review cycle
            $this->testScenario6_InitiateReviewCycle();

            // Scenario 7: Record NO_CHANGE outcome
            $this->testScenario7_RecordNoChangeOutcome();

            // Scenario 8: Record REVISION_REQUIRED outcome
            $this->testScenario8_RecordRevisionRequiredOutcome();

            // Scenario 9: Create plan revision
            $this->testScenario9_CreatePlanRevision();

            // Scenario 10: Approve plan revision
            $this->testScenario10_ApprovePlanRevision();

            // Scenario 11: Calculate version variance
            $this->testScenario11_CalculateVersionVariance();

            // Scenario 12: Reject duplicate plan
            $this->testScenario12_RejectDuplicatePlan();

            // Scenario 13: Reject invalid quarter
            $this->testScenario13_RejectInvalidQuarter();

            // Scenario 14: Reject negative quantity and cost
            $this->testScenario14_RejectNegativeQuantityAndCost();

            // Scenario 15: Reject cross-plan current-version assignment
            $this->testScenario15_RejectCrossPlanCurrentVersionAssignment();

            // Scenario 16: Reject cross-plan review-cycle version assignment
            $this->testScenario16_RejectCrossPlanReviewCycleVersionAssignment();

            // Scenario 17: Reject cross-plan revision references
            $this->testScenario17_RejectCrossPlanRevisionReferences();

            // Scenario 18: Verify rollback after batch-item failure
            $this->testScenario18_VerifyRollbackAfterBatchItemFailure();

            // Scenario 19: Verify rollback after approval failure
            $this->testScenario19_VerifyRollbackAfterApprovalFailure();

        } finally {
            $this->tearDownAllFixtures();
        }

        // Summary report
        echo "\n===============================================================\n";
        echo " PLANNING SERVICE TEST SUMMARY\n";
        echo " {$this->passed} / " . ($this->passed + $this->failed) . " Passed\n";
        echo " {$this->failed} Failed\n";
        echo "===============================================================\n";

        if ($this->failed > 0) {
            echo "\nFAILURES:\n";
            foreach ($this->failures as $f) {
                echo " - " . $f . "\n";
            }
            exit(1);
        } else {
            echo "\nALL 19 PROCUREMENT PLANNING SERVICE SCENARIOS PASSED.\n";
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$message}\n";
        } else {
            $this->failed++;
            $this->failures[] = $message;
            echo " [FAIL] {$message}\n";
        }
    }

    // =========================================================================
    // Fixtures Setup & Teardown
    // =========================================================================

    private function setupBaseFixtures(): void
    {
        $time = time();
        $rnd = mt_rand(1000, 9999);

        // 1. Users
        $this->fixtureUserId = $this->insertUser("planner_svc_{$time}_{$rnd}", "planner_svc_{$time}_{$rnd}@usted.edu.gh");
        $this->fixtureApproverId = $this->insertUser("approver_svc_{$time}_{$rnd}", "approver_svc_{$time}_{$rnd}@usted.edu.gh");

        // 2. Campus & Entity Type
        $this->fixtureCampusId = $this->insertCampus("CMP_SVC_{$rnd}", "Main Campus {$rnd}");
        $this->fixtureEntityTypeId = $this->insertEntityType("DEPT_SVC_{$rnd}", "Academic Department {$rnd}");

        // 3. Planning Entities (Entity 1 and Entity 2 for cross-plan tests)
        $this->fixtureEntity1Id = $this->insertPlanningEntity("ENT_CS_{$rnd}", "Computer Science {$rnd}", $this->fixtureEntityTypeId, $this->fixtureCampusId, $this->fixtureUserId);
        $this->fixtureEntity2Id = $this->insertPlanningEntity("ENT_EE_{$rnd}", "Electrical Engineering {$rnd}", $this->fixtureEntityTypeId, $this->fixtureCampusId, $this->fixtureUserId);

        // 4. Catalogue Data
        $this->fixtureCategoryId = $this->insertCategory("CAT_SVC_{$rnd}", "ICT Equipment {$rnd}");
        $this->fixtureUomId = $this->insertUom("PCS_SVC_{$rnd}", "Pieces {$rnd}");
        $this->fixtureStandardItem1Id = $this->insertStandardItem("ITEM_LAPTOP_{$rnd}", "Core i7 Laptop {$rnd}", $this->fixtureCategoryId, $this->fixtureUomId, $this->fixtureUserId, 4500.00);
        $this->fixtureStandardItem2Id = $this->insertStandardItem("ITEM_MONITOR_{$rnd}", "27-inch IPS Monitor {$rnd}", $this->fixtureCategoryId, $this->fixtureUomId, $this->fixtureUserId, 1200.00);
        $this->fixtureStandardItem3Id = $this->insertStandardItem("ITEM_PRINTER_{$rnd}", "Laser Multifunction Printer {$rnd}", $this->fixtureCategoryId, $this->fixtureUomId, $this->fixtureUserId, 3500.00);

        // Authenticate test user with full planning permissions
        AuthManager::login([
            'id' => $this->fixtureUserId,
            'username' => "planner_svc_{$time}_{$rnd}",
            'email' => "planner_svc_{$time}_{$rnd}@usted.edu.gh",
            'permissions' => PlanningPermissions::all(),
        ]);

        $this->assert($this->fixtureEntity1Id > 0 && $this->fixtureStandardItem1Id > 0, 'Base prerequisite relational fixtures seeded successfully');
    }

    private function insertUser(string $username, string $email): int
    {
        $sql = "INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `status`, `created_at`)
                VALUES (:u, :e, :p, 'Test', 'User', 'ACTIVE', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'u' => $username,
            'e' => $email,
            'p' => password_hash('SecretPassword123!', PASSWORD_BCRYPT),
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $id;
        return $id;
    }

    private function insertCampus(string $code, string $name): int
    {
        $sql = "INSERT INTO `campuses` (`campus_code`, `campus_name`, `created_at`) VALUES (:c, :n, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['c' => $code, 'n' => $name]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedCampuses[] = $id;
        return $id;
    }

    private function insertEntityType(string $code, string $name): int
    {
        $sql = "INSERT INTO `entity_types` (`type_code`, `type_name`, `created_at`) VALUES (:c, :n, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['c' => $code, 'n' => $name]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedEntityTypes[] = $id;
        return $id;
    }

    private function insertPlanningEntity(string $code, string $name, int $typeId, int $campusId, int $userId): int
    {
        $sql = "INSERT INTO `planning_entities` (`entity_code`, `entity_name`, `entity_type_id`, `campus_id`, `head_user_id`, `planning_officer_id`, `created_at`, `created_by`)
                VALUES (:c, :n, :t, :cmp, :h, :po, NOW(), :cb)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'c' => $code,
            'n' => $name,
            't' => $typeId,
            'cmp' => $campusId,
            'h' => $userId,
            'po' => $userId,
            'cb' => $userId,
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $id;
        return $id;
    }

    private function insertCategory(string $code, string $name): int
    {
        $sql = "INSERT INTO `item_categories` (`category_code`, `category_name`, `created_at`) VALUES (:c, :n, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['c' => $code, 'n' => $name]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedCategories[] = $id;
        return $id;
    }

    private function insertUom(string $code, string $name): int
    {
        $sql = "INSERT INTO `units_of_measure` (`uom_code`, `uom_name`, `created_at`) VALUES (:c, :n, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['c' => $code, 'n' => $name]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedUoms[] = $id;
        return $id;
    }

    private function insertStandardItem(string $code, string $name, int $catId, int $uomId, int $userId, float $price): int
    {
        $sql = "INSERT INTO `standard_items` (`item_code`, `item_name`, `category_id`, `default_uom_id`, `estimated_unit_price`, `created_at`, `created_by`)
                VALUES (:c, :n, :cat, :uom, :pr, NOW(), :cb)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'c' => $code,
            'n' => $name,
            'cat' => $catId,
            'uom' => $uomId,
            'pr' => $price,
            'cb' => $userId,
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedStandardItems[] = $id;
        return $id;
    }

    private function tearDownAllFixtures(): void
    {
        // Break foreign key cycle before deletion
        $this->db->exec("UPDATE `procurement_plans` SET `current_version_id` = NULL");

        // 1. Delete all plan line items (children of versions)
        if (!empty($this->trackedVersions)) {
            $in = implode(',', array_map('intval', $this->trackedVersions));
            $this->db->exec("DELETE FROM `procurement_plan_items` WHERE `plan_version_id` IN ({$in})");
        }
        if (!empty($this->trackedPlans)) {
            $in = implode(',', array_map('intval', $this->trackedPlans));
            $this->db->exec("DELETE FROM `procurement_plan_items` WHERE `plan_version_id` IN (SELECT `id` FROM `procurement_plan_versions` WHERE `procurement_plan_id` IN ({$in}))");
        }

        // 2. Delete revisions
        if (!empty($this->trackedRevisions)) {
            $in = implode(',', array_map('intval', $this->trackedRevisions));
            $this->db->exec("DELETE FROM `plan_revision_records` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedPlans)) {
            $in = implode(',', array_map('intval', $this->trackedPlans));
            $this->db->exec("DELETE FROM `plan_revision_records` WHERE `procurement_plan_id` IN ({$in})");
        }

        // 3. Delete review cycles
        if (!empty($this->trackedCycles)) {
            $in = implode(',', array_map('intval', $this->trackedCycles));
            $this->db->exec("DELETE FROM `plan_review_cycles` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedPlans)) {
            $in = implode(',', array_map('intval', $this->trackedPlans));
            $this->db->exec("DELETE FROM `plan_review_cycles` WHERE `procurement_plan_id` IN ({$in})");
        }

        // 4. Delete versions
        if (!empty($this->trackedVersions)) {
            $in = implode(',', array_map('intval', $this->trackedVersions));
            $this->db->exec("DELETE FROM `procurement_plan_versions` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedPlans)) {
            $in = implode(',', array_map('intval', $this->trackedPlans));
            $this->db->exec("DELETE FROM `procurement_plan_versions` WHERE `procurement_plan_id` IN ({$in})");
        }

        // 5. Delete plans
        if (!empty($this->trackedPlans)) {
            $in = implode(',', array_map('intval', $this->trackedPlans));
            $this->db->exec("DELETE FROM `procurement_plans` WHERE `id` IN ({$in})");
        }

        // 6. Delete standard items
        if (!empty($this->trackedStandardItems)) {
            $in = implode(',', array_map('intval', $this->trackedStandardItems));
            $this->db->exec("DELETE FROM `standard_items` WHERE `id` IN ({$in})");
        }

        // 7. Delete UOMs & Categories
        if (!empty($this->trackedUoms)) {
            $in = implode(',', array_map('intval', $this->trackedUoms));
            $this->db->exec("DELETE FROM `units_of_measure` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedCategories)) {
            $in = implode(',', array_map('intval', $this->trackedCategories));
            $this->db->exec("DELETE FROM `item_categories` WHERE `id` IN ({$in})");
        }

        // 8. Delete planning entities
        if (!empty($this->trackedEntities)) {
            $in = implode(',', array_map('intval', $this->trackedEntities));
            $this->db->exec("DELETE FROM `planning_entities` WHERE `id` IN ({$in})");
        }

        // 9. Delete entity types & campuses
        if (!empty($this->trackedEntityTypes)) {
            $in = implode(',', array_map('intval', $this->trackedEntityTypes));
            $this->db->exec("DELETE FROM `entity_types` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedCampuses)) {
            $in = implode(',', array_map('intval', $this->trackedCampuses));
            $this->db->exec("DELETE FROM `campuses` WHERE `id` IN ({$in})");
        }

        // 10. Delete users
        if (!empty($this->trackedUsers)) {
            $in = implode(',', array_map('intval', $this->trackedUsers));
            $this->db->exec("DELETE FROM `users` WHERE `id` IN ({$in})");
        }

        // Verify clean state of tracked plans
        if (!empty($this->trackedPlans)) {
            $in = implode(',', array_map('intval', $this->trackedPlans));
            $remainingPlans = (int)$this->db->query("SELECT COUNT(*) FROM `procurement_plans` WHERE `id` IN ({$in})")->fetchColumn();
            $this->assert($remainingPlans === 0, 'Database teardown completed with ZERO orphan procurement_plans');
        } else {
            $this->assert(true, 'Database teardown completed with ZERO orphan procurement_plans');
        }
    }


    // =========================================================================
    // 19 Required Scenarios
    // =========================================================================

    /**
     * Scenario 1: Create plan workflow
     */
    private function testScenario1_CreatePlanWorkflow(): void
    {
        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2026, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;
        $this->workflowPlanId = $plan->id;

        $this->assert($plan->id > 0, "Scenario 1: ProcurementPlanService::createPlan returns positive ID ({$plan->id})");
        $this->assert($plan->planningEntityId === $this->fixtureEntity1Id, 'Scenario 1: Plan planning_entity_id matches input');
        $this->assert($plan->fiscalYear === 2026, 'Scenario 1: Plan fiscal_year matches input');
        $this->assert($plan->status === PlanStatus::DRAFT, 'Scenario 1: Initial plan status is DRAFT');
        $this->assert($plan->currentVersionId === null, 'Scenario 1: Initial current_version_id is null');
        $this->assert(str_starts_with($plan->planNumber, 'APP-2026-ENT'), 'Scenario 1: Plan number correctly generated');
    }

    /**
     * Scenario 2: Create initial version
     */
    private function testScenario2_CreateInitialVersion(): void
    {
        $version = $this->planService->createInitialVersion($this->workflowPlanId, $this->fixtureUserId, '1.0');
        $this->trackedVersions[] = $version->id;
        $this->workflowV1Id = $version->id;

        $this->assert($version->id > 0, "Scenario 2: ProcurementPlanService::createInitialVersion returns version ID ({$version->id})");
        $this->assert($version->versionNumber === '1.0', 'Scenario 2: Version number is 1.0');
        $this->assert($version->status === PlanVersionStatus::DRAFT, 'Scenario 2: Version status is DRAFT');
        $this->assert($version->totalEstimatedCost === '0.00', 'Scenario 2: Initial version total cost is 0.00');
        $this->assert($version->procurementPlanId === $this->workflowPlanId, 'Scenario 2: Version linked to correct plan');
    }

    /**
     * Scenario 3: Add items and calculate totals
     */
    private function testScenario3_AddItemsAndCalculateTotals(): void
    {
        $items = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Workstation Laptop for Lab',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 10,
                'estimated_unit_cost' => 150.25,
                'target_quarter' => 'Q1',
                'funding_source' => 'GOG',
            ],
            [
                'standard_item_id' => $this->fixtureStandardItem2Id,
                'item_description' => '27-inch IPS Monitor',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 5,
                'estimated_unit_cost' => 200.50,
                'target_quarter' => 'Q2',
                'funding_source' => 'IGF',
            ],
        ];

        // 10 * 150.25 = 1502.50
        // 5 * 200.50 = 1002.50
        // Total = 2505.00
        $insertedCount = $this->planService->addPlanItems($this->workflowPlanId, $this->workflowV1Id, $items, $this->fixtureUserId);
        $this->assert($insertedCount === 2, 'Scenario 3: addPlanItems successfully inserted 2 line items');

        $version = $this->planService->getPlanVersion($this->workflowV1Id);
        $this->assert($version !== null, 'Scenario 3: Plan version retrieved with line items');
        $this->assert(count($version->items) === 2, 'Scenario 3: Exactly 2 items hydrated under version');
        $this->assert($version->totalEstimatedCost === '2505.00', 'Scenario 3: Version total estimated cost accurately calculated (2505.00)');

        // Check line items decimal accuracy
        $line1 = $version->items[0];
        $this->assert($line1->estimatedTotalCost === '1502.50', 'Scenario 3: Line item 1 decimal cost exact (1502.50)');
        $line2 = $version->items[1];
        $this->assert($line2->estimatedTotalCost === '1002.50', 'Scenario 3: Line item 2 decimal cost exact (1002.50)');
    }

    /**
     * Scenario 4: Set current version
     */
    private function testScenario4_SetCurrentVersion(): void
    {
        $success = $this->planService->setCurrentVersion($this->workflowPlanId, $this->workflowV1Id, $this->fixtureUserId);
        $this->assert($success === true, 'Scenario 4: setCurrentVersion returns true');

        $plan = $this->planService->getPlanDetails($this->workflowPlanId);
        $this->assert($plan->currentVersionId === $this->workflowV1Id, 'Scenario 4: Plan current_version_id matches version 1.0');
        $this->assert($plan->currentVersion !== null, 'Scenario 4: Plan currentVersion DTO is hydrated');
        $this->assert($plan->currentVersion->totalEstimatedCost === '2505.00', 'Scenario 4: Hydrated current version retains total cost');
    }

    /**
     * Scenario 5: Submit plan for review
     */
    private function testScenario5_SubmitPlanForReview(): void
    {
        $submittedPlan = $this->planService->submitPlanForReview($this->workflowPlanId, $this->fixtureUserId);

        $this->assert($submittedPlan->status === PlanStatus::SUBMITTED, 'Scenario 5: Plan transitioned to SUBMITTED status');

        $version = $this->versionRepo->findById($this->workflowV1Id);
        $this->assert($version->status === PlanVersionStatus::SUBMITTED, 'Scenario 5: Version snapshot transitioned to SUBMITTED status');
    }

    /**
     * Scenario 6: Initiate review cycle
     */
    private function testScenario6_InitiateReviewCycle(): void
    {
        // Promote plan and version to APPROVED baseline to simulate active plan review
        $this->planRepo->updateStatus($this->workflowPlanId, PlanStatus::APPROVED->value, $this->fixtureApproverId);
        $this->versionRepo->updateStatus($this->workflowV1Id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));

        $cycle = $this->cycleService->initiateReviewCycle($this->workflowPlanId, 2026, 'Q1', $this->fixtureUserId);
        $this->trackedCycles[] = $cycle->id;
        $this->workflowCycle1Id = $cycle->id;

        $this->assert($cycle->id > 0, "Scenario 6: Review cycle initiated with ID ({$cycle->id})");
        $this->assert($cycle->procurementPlanId === $this->workflowPlanId, 'Scenario 6: Cycle points to plan');
        $this->assert($cycle->activeVersionId === $this->workflowV1Id, 'Scenario 6: Cycle points to active version 1.0');
        $this->assert($cycle->reviewQuarter === ReviewQuarter::Q1, 'Scenario 6: Quarter is Q1');
        $this->assert($cycle->reviewStatus === ReviewCycleStatus::IN_PROGRESS, 'Scenario 6: Status is IN_PROGRESS');
        $this->assert($cycle->reviewOutcome === null, 'Scenario 6: Initial outcome is null');
    }

    /**
     * Scenario 7: Record NO_CHANGE outcome
     */
    private function testScenario7_RecordNoChangeOutcome(): void
    {
        $completedCycle = $this->cycleService->recordReviewOutcome(
            $this->workflowCycle1Id,
            ReviewOutcome::NO_CHANGE->value,
            'Q1 procurement on track. No scope or quantity changes required.',
            $this->fixtureUserId
        );

        $this->assert($completedCycle->reviewStatus === ReviewCycleStatus::COMPLETED, 'Scenario 7: Cycle status transitioned to COMPLETED');
        $this->assert($completedCycle->reviewOutcome === ReviewOutcome::NO_CHANGE, 'Scenario 7: Cycle outcome stamped as NO_CHANGE');
        $this->assert($completedCycle->completedAt !== null, 'Scenario 7: completed_at timestamp populated');
    }

    /**
     * Scenario 8: Record REVISION_REQUIRED outcome
     */
    private function testScenario8_RecordRevisionRequiredOutcome(): void
    {
        // Initiate Q2 review cycle
        $cycle2 = $this->cycleService->initiateReviewCycle($this->workflowPlanId, 2026, 'Q2', $this->fixtureUserId);
        $this->trackedCycles[] = $cycle2->id;
        $this->workflowCycle2Id = $cycle2->id;

        $completedCycle2 = $this->cycleService->recordReviewOutcome(
            $cycle2->id,
            ReviewOutcome::REVISION_REQUIRED->value,
            'Additional hardware required due to expansion of computer lab.',
            $this->fixtureUserId
        );

        $this->assert($completedCycle2->reviewStatus === ReviewCycleStatus::COMPLETED, 'Scenario 8: Q2 Cycle status is COMPLETED');
        $this->assert($completedCycle2->reviewOutcome === ReviewOutcome::REVISION_REQUIRED, 'Scenario 8: Q2 outcome stamped as REVISION_REQUIRED');
        $this->assert($completedCycle2->reviewNotes !== null, 'Scenario 8: Mandatory review notes preserved');
    }

    /**
     * Scenario 9: Create plan revision
     */
    private function testScenario9_CreatePlanRevision(): void
    {
        // Revised items:
        // Item 1: quantity increased from 10 to 15 (15 * 150.25 = 2253.75)
        // Item 2: quantity unchanged (5 * 200.50 = 1002.50)
        // Item 3: newly added item (2 * 500.00 = 1000.00)
        // Total revised: 2253.75 + 1002.50 + 1000.00 = 4256.25
        $revisedItems = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Workstation Laptop for Lab (Expanded)',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 15,
                'estimated_unit_cost' => 150.25,
                'target_quarter' => 'Q1',
                'funding_source' => 'GOG',
            ],
            [
                'standard_item_id' => $this->fixtureStandardItem2Id,
                'item_description' => '27-inch IPS Monitor',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 5,
                'estimated_unit_cost' => 200.50,
                'target_quarter' => 'Q2',
                'funding_source' => 'IGF',
            ],
            [
                'standard_item_id' => $this->fixtureStandardItem3Id,
                'item_description' => 'Laser Multifunction Printer',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 2,
                'estimated_unit_cost' => 500.00,
                'target_quarter' => 'Q3',
                'funding_source' => 'IGF',
            ],
        ];

        $v2 = $this->versionService->createPlanRevision(
            $this->workflowPlanId,
            $this->workflowCycle2Id,
            'Scope expansion for laboratory computing equipment following Q2 review',
            $revisedItems,
            $this->fixtureUserId
        );
        $this->trackedVersions[] = $v2->id;
        $this->workflowV2Id = $v2->id;

        $this->assert($v2->id > 0, "Scenario 9: Staged revision created version ID ({$v2->id})");
        $this->assert($v2->versionNumber === '2.0', 'Scenario 9: New version number is 2.0');
        $this->assert($v2->status === PlanVersionStatus::DRAFT, 'Scenario 9: New version status is DRAFT');
        $this->assert($v2->totalEstimatedCost === '4256.25', 'Scenario 9: Revision total cost calculated accurately (4256.25)');

        // Verify revision provenance record in repository
        $history = $this->versionService->getRevisionHistory($this->workflowPlanId);
        $this->assert(count($history) === 1, 'Scenario 9: Exactly 1 revision provenance record created');
        $revRecord = $history[0];
        $this->workflowRevisionId = $revRecord->id;
        $this->trackedRevisions[] = $revRecord->id;

        $this->assert($revRecord->priorVersionId === $this->workflowV1Id, 'Scenario 9: Provenance links prior version 1.0');
        $this->assert($revRecord->newVersionId === $this->workflowV2Id, 'Scenario 9: Provenance links new version 2.0');
        $this->assert($revRecord->reviewCycleId === $this->workflowCycle2Id, 'Scenario 9: Provenance links review cycle');
        $this->assert($revRecord->approvedByUserId === null, 'Scenario 9: Revision initially unapproved');
    }

    /**
     * Scenario 10: Approve plan revision
     */
    private function testScenario10_ApprovePlanRevision(): void
    {
        $approvedV2 = $this->versionService->approvePlanRevision($this->workflowRevisionId, $this->fixtureApproverId);

        $this->assert($approvedV2->status === PlanVersionStatus::APPROVED, 'Scenario 10: Version 2.0 status transitioned to APPROVED');
        $this->assert($approvedV2->approvedByUserId === $this->fixtureApproverId, 'Scenario 10: Version 2.0 stamped with approver user ID');
        $this->assert($approvedV2->approvalDate !== null, 'Scenario 10: Version 2.0 stamped with approval date');

        // Verify prior version superseded
        $priorV1 = $this->versionRepo->findById($this->workflowV1Id);
        $this->assert($priorV1->status === PlanVersionStatus::SUPERSEDED, 'Scenario 10: Prior version 1.0 transitioned to SUPERSEDED');

        // Verify plan pointer updated
        $plan = $this->planRepo->findById($this->workflowPlanId);
        $this->assert($plan->currentVersionId === $this->workflowV2Id, 'Scenario 10: Plan current_version_id pointer updated to version 2.0');
        $this->assert($plan->status === PlanStatus::APPROVED, 'Scenario 10: Plan status is APPROVED');

        // Verify revision record approved
        $revRecord = $this->revisionRepo->findById($this->workflowRevisionId);
        $this->assert($revRecord->approvedByUserId === $this->fixtureApproverId, 'Scenario 10: Revision record stamped with approver');

        // Concurrency guard: Reject duplicate approval of already approved revision
        $rejectedDuplicate = false;
        try {
            $this->versionService->approvePlanRevision($this->workflowRevisionId, $this->fixtureApproverId);
        } catch (ValidationException $e) {
            $rejectedDuplicate = true;
        }
        $this->assert($rejectedDuplicate === true, 'Scenario 10: Rejects approval of an already approved revision');
    }

    /**
     * Scenario 11: Calculate version variance
     */
    private function testScenario11_CalculateVersionVariance(): void
    {
        $variances = $this->versionService->calculateVersionVariance($this->workflowV1Id, $this->workflowV2Id);

        $this->assert(count($variances) === 3, 'Scenario 11: Exactly 3 items analyzed in delta variance engine');

        $map = [];
        foreach ($variances as $v) {
            $map[$v->standardItemId] = $v;
        }

        // Item 1: MODIFIED (+5 quantity, +751.25 GHS)
        $this->assert(isset($map[$this->fixtureStandardItem1Id]), 'Scenario 11: Item 1 present in variance');
        $v1Item = $map[$this->fixtureStandardItem1Id];
        $this->assert($v1Item->changeType === 'MODIFIED', 'Scenario 11: Item 1 classified as MODIFIED');
        $this->assert($v1Item->quantityDelta === '5.00', 'Scenario 11: Item 1 quantity delta is +5.0');
        $this->assert($v1Item->costDelta === '751.25', 'Scenario 11: Item 1 cost delta is +751.25');

        // Item 2: UNCHANGED (0 quantity delta, 0 cost delta)
        $this->assert(isset($map[$this->fixtureStandardItem2Id]), 'Scenario 11: Item 2 present in variance');
        $v2Item = $map[$this->fixtureStandardItem2Id];
        $this->assert($v2Item->changeType === 'UNCHANGED', 'Scenario 11: Item 2 classified as UNCHANGED');
        $this->assert($v2Item->quantityDelta === '0.00', 'Scenario 11: Item 2 quantity delta is 0.0');
        $this->assert($v2Item->costDelta === '0.00', 'Scenario 11: Item 2 cost delta is 0.0');

        // Item 3: ADDED (+2 quantity, +1000.00 GHS)
        $this->assert(isset($map[$this->fixtureStandardItem3Id]), 'Scenario 11: Item 3 present in variance');
        $v3Item = $map[$this->fixtureStandardItem3Id];
        $this->assert($v3Item->changeType === 'ADDED', 'Scenario 11: Item 3 classified as ADDED');
        $this->assert($v3Item->quantityDelta === '2.00', 'Scenario 11: Item 3 quantity delta is +2.0');
        $this->assert($v3Item->costDelta === '1000.00', 'Scenario 11: Item 3 cost delta is +1000.00');
    }

    /**
     * Scenario 12: Reject duplicate plan
     */
    private function testScenario12_RejectDuplicatePlan(): void
    {
        $caught = false;
        try {
            // Plan for entity1 and year 2026 already exists from Scenario 1
            $this->planService->createPlan($this->fixtureEntity1Id, 2026, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'already exists'), 'Scenario 12: ValidationException indicates duplicate plan exists');
        }

        $this->assert($caught === true, 'Scenario 12: Rejects duplicate plan for same entity and fiscal year');
    }

    /**
     * Scenario 13: Reject invalid quarter
     */
    private function testScenario13_RejectInvalidQuarter(): void
    {
        $caught = false;
        try {
            $this->cycleService->initiateReviewCycle($this->workflowPlanId, 2026, 'Q5', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caught = true;
        }

        $this->assert($caught === true, 'Scenario 13: Rejects invalid review quarter (Q5)');
    }

    /**
     * Scenario 14: Reject negative quantity and cost
     */
    private function testScenario14_RejectNegativeQuantityAndCost(): void
    {
        // Create auxiliary plan & version
        $plan = $this->planService->createPlan($this->fixtureEntity2Id, 2026, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;
        $ver = $this->planService->createInitialVersion($plan->id, $this->fixtureUserId);
        $this->trackedVersions[] = $ver->id;

        // Negative quantity test
        $caughtQty = false;
        try {
            $this->planService->addPlanItems($plan->id, $ver->id, [
                [
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Negative quantity item',
                    'category_id' => $this->fixtureCategoryId,
                    'uom_id' => $this->fixtureUomId,
                    'planned_quantity' => -5,
                    'estimated_unit_cost' => 100.00,
                    'target_quarter' => 'Q1',
                    'funding_source' => 'GOG',
                ]
            ], $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtQty = true;
        }
        $this->assert($caughtQty === true, 'Scenario 14: Rejects negative planned quantity');

        // Negative unit cost test
        $caughtCost = false;
        try {
            $this->planService->addPlanItems($plan->id, $ver->id, [
                [
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Negative unit cost item',
                    'category_id' => $this->fixtureCategoryId,
                    'uom_id' => $this->fixtureUomId,
                    'planned_quantity' => 5,
                    'estimated_unit_cost' => -20.00,
                    'target_quarter' => 'Q1',
                    'funding_source' => 'GOG',
                ]
            ], $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtCost = true;
        }
        $this->assert($caughtCost === true, 'Scenario 14: Rejects negative estimated unit cost');
    }

    /**
     * Scenario 15: Reject cross-plan current-version assignment
     */
    private function testScenario15_RejectCrossPlanCurrentVersionAssignment(): void
    {
        // Plan A is $this->workflowPlanId (Entity 1)
        // Plan B is created for Entity 2
        $planB = $this->planService->createPlan($this->fixtureEntity2Id, 2027, $this->fixtureUserId);
        $this->trackedPlans[] = $planB->id;
        $verB = $this->planService->createInitialVersion($planB->id, $this->fixtureUserId);
        $this->trackedVersions[] = $verB->id;

        $caught = false;
        try {
            // Attempt to assign Version B (belongs to Plan B) as current version for Plan A
            $this->planService->setCurrentVersion($this->workflowPlanId, $verB->id, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'does not belong to'), 'Scenario 15: Error explains version mismatch');
        }

        $this->assert($caught === true, 'Scenario 15: Rejects cross-plan current-version assignment');
    }

    /**
     * Scenario 16: Reject cross-plan review-cycle version assignment
     */
    private function testScenario16_RejectCrossPlanReviewCycleVersionAssignment(): void
    {
        // Create plan B for Entity 2 in 2028
        $planB = $this->planService->createPlan($this->fixtureEntity2Id, 2028, $this->fixtureUserId);
        $this->trackedPlans[] = $planB->id;
        $verB = $this->planService->createInitialVersion($planB->id, $this->fixtureUserId);
        $this->trackedVersions[] = $verB->id;

        $caught = false;
        try {
            // Attempt to initiate review cycle on Plan A with activeVersionId = $verB->id (Plan B's version)
            $this->cycleService->initiateReviewCycle(
                $this->workflowPlanId,
                2026,
                'Q3',
                $this->fixtureUserId,
                $verB->id
            );
        } catch (ValidationException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'does not belong to'), 'Scenario 16: Error explains version mismatch');
        }

        $this->assert($caught === true, 'Scenario 16: Rejects cross-plan review-cycle active version assignment');
    }

    /**
     * Scenario 17: Reject cross-plan revision references
     */
    private function testScenario17_RejectCrossPlanRevisionReferences(): void
    {
        // Plan B for Entity 2 in 2029
        $planB = $this->planService->createPlan($this->fixtureEntity2Id, 2029, $this->fixtureUserId);
        $this->trackedPlans[] = $planB->id;
        $verB = $this->planService->createInitialVersion($planB->id, $this->fixtureUserId);
        $this->trackedVersions[] = $verB->id;
        $this->planRepo->setCurrentVersion($planB->id, $verB->id);

        $items = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Test Item',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 1,
                'estimated_unit_cost' => 100.00,
                'target_quarter' => 'Q1',
                'funding_source' => 'GOG',
            ]
        ];

        // Attempt 1: Review cycle from Plan A referenced in Plan B revision
        $caughtCycle = false;
        try {
            $this->versionService->createPlanRevision(
                $planB->id,
                $this->workflowCycle1Id, // Belongs to Plan A!
                'Justification test referencing alien cycle',
                $items,
                $this->fixtureUserId
            );
        } catch (ValidationException $e) {
            $caughtCycle = true;
            $this->assert(str_contains($e->getMessage(), 'does not belong to'), 'Scenario 17: Rejects alien review cycle reference');
        }
        $this->assert($caughtCycle === true, 'Scenario 17: Rejects cross-plan review cycle reference in plan revision');

        // Attempt 2: Reject approving an alien version revision
        // Use a mock revision repository to provide a revision record linking Plan B to Plan A's version
        $alienRevisionDto = new PlanRevisionRecordDTO(
            id: 99999,
            procurementPlanId: $planB->id,
            reviewCycleId: null,
            priorVersionId: $this->workflowV1Id, // Belongs to Plan A!
            newVersionId: $verB->id,
            revisionJustification: 'Alien prior version test',
            submittedByUserId: $this->fixtureUserId,
            submittedAt: date('Y-m-d H:i:s'),
            approvedByUserId: null,
            approvedAt: null
        );

        $mockRevisionRepo = new class($this->revisionRepo, $alienRevisionDto) implements PlanRevisionRecordRepositoryInterface {
            private PlanRevisionRecordRepositoryInterface $inner;
            private PlanRevisionRecordDTO $mockDto;
            public function __construct(PlanRevisionRecordRepositoryInterface $inner, PlanRevisionRecordDTO $dto) {
                $this->inner = $inner;
                $this->mockDto = $dto;
            }
            public function findById(int $id): ?PlanRevisionRecordDTO {
                if ($id === 99999) {
                    return $this->mockDto;
                }
                return $this->inner->findById($id);
            }
            public function findByPlanId(int $planId): array { return $this->inner->findByPlanId($planId); }
            public function findByNewVersionId(int $newVersionId): ?PlanRevisionRecordDTO { return $this->inner->findByNewVersionId($newVersionId); }
            public function create(array $data): int { return $this->inner->create($data); }

            public function markApproved(int $id, int $approvedByUserId, ?string $approvedAt = null): bool { return $this->inner->markApproved($id, $approvedByUserId, $approvedAt); }
        };




        $alienVersionService = new PlanVersionService(
            $this->db,
            $this->planRepo,
            $this->versionRepo,
            $this->itemRepo,
            $mockRevisionRepo,
            $this->cycleRepo
        );

        $caughtApproval = false;
        try {
            $alienVersionService->approvePlanRevision(99999, $this->fixtureApproverId);
        } catch (ValidationException $e) {
            $caughtApproval = true;
            $this->assert(str_contains($e->getMessage(), 'Cross-plan') || str_contains($e->getMessage(), 'does not belong to'), 'Scenario 17: Rejects approval of revision with cross-plan version');
        }
        $this->assert($caughtApproval === true, 'Scenario 17: Rejects approval of cross-plan revision record');
    }


    /**
     * Scenario 18: Verify rollback after batch-item failure
     */
    private function testScenario18_VerifyRollbackAfterBatchItemFailure(): void
    {
        // Create dedicated plan & version for rollback testing
        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2030, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;
        $ver = $this->planService->createInitialVersion($plan->id, $this->fixtureUserId);
        $this->trackedVersions[] = $ver->id;

        // Batch of 2 items: 1st is valid, 2nd has negative quantity
        $batch = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Valid line item prior to failure',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 10,
                'estimated_unit_cost' => 100.00,
                'target_quarter' => 'Q1',
                'funding_source' => 'GOG',
            ],
            [
                'standard_item_id' => $this->fixtureStandardItem2Id,
                'item_description' => 'Invalid line item triggering failure',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => -99, // INVALID!
                'estimated_unit_cost' => 50.00,
                'target_quarter' => 'Q1',
                'funding_source' => 'GOG',
            ]
        ];

        $caught = false;
        try {
            $this->planService->addPlanItems($plan->id, $ver->id, $batch, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caught = true;
        }

        $this->assert($caught === true, 'Scenario 18: ValidationException caught on invalid batch item');

        // Verify that item 1 was NOT persisted due to rollback
        $persistedItems = $this->itemRepo->findByVersionId($ver->id);
        $this->assert(count($persistedItems) === 0, 'Scenario 18: Zero items persisted in database after rollback');

        $verDto = $this->versionRepo->findById($ver->id);
        $this->assert($verDto->totalEstimatedCost === '0.00', 'Scenario 18: Version total estimated cost remains 0.00');
    }

    /**
     * Scenario 19: Verify rollback after approval failure
     */
    private function testScenario19_VerifyRollbackAfterApprovalFailure(): void
    {
        // Create plan, initial approved version v1, staged revision v2
        $plan = $this->planService->createPlan($this->fixtureEntity2Id, 2031, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;
        $v1 = $this->planService->createInitialVersion($plan->id, $this->fixtureUserId, '1.0', '1000.00');
        $this->trackedVersions[] = $v1->id;
        $this->planRepo->updateStatus($plan->id, PlanStatus::APPROVED->value, $this->fixtureApproverId);
        $this->planRepo->setCurrentVersion($plan->id, $v1->id);
        $this->versionRepo->updateStatus($v1->id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));

        // Stage revision v2
        $v2 = $this->versionService->createPlanRevision(
            $plan->id,
            null,
            'Staged revision for approval rollback test',
            [
                [
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Revised item',
                    'category_id' => $this->fixtureCategoryId,
                    'uom_id' => $this->fixtureUomId,
                    'planned_quantity' => 20,
                    'estimated_unit_cost' => 100.00,
                    'target_quarter' => 'Q1',
                    'funding_source' => 'GOG',
                ]
            ],
            $this->fixtureUserId
        );
        $this->trackedVersions[] = $v2->id;

        $history = $this->versionService->getRevisionHistory($plan->id);
        $revRecord = $history[0];
        $this->trackedRevisions[] = $revRecord->id;

        // Decorate ProcurementPlanRepository to simulate an intentional failure during the transaction
        $failingPlanRepo = new class($this->planRepo) implements ProcurementPlanRepositoryInterface {
            private ProcurementPlanRepositoryInterface $inner;
            public function __construct(ProcurementPlanRepositoryInterface $inner) {
                $this->inner = $inner;
            }
            public function findById(int $id): ?PlanDTO { return $this->inner->findById($id); }
            public function findByPlanNumber(string $planNumber): ?PlanDTO { return $this->inner->findByPlanNumber($planNumber); }
            public function findByEntityAndYear(int $planningEntityId, int $fiscalYear): ?PlanDTO { return $this->inner->findByEntityAndYear($planningEntityId, $fiscalYear); }
            public function findByEntity(int $planningEntityId): array { return $this->inner->findByEntity($planningEntityId); }
            public function create(array $data): int { return $this->inner->create($data); }
            public function updateStatus(int $id, string $status, ?int $updatedBy = null): bool { return $this->inner->updateStatus($id, $status, $updatedBy); }
            public function setCurrentVersion(int $id, int $versionId, ?int $updatedBy = null): bool {
                throw new \RuntimeException('Simulated database deadlock/crash during plan pointer update');
            }
        };


        $faultyVersionService = new PlanVersionService(
            $this->db,
            $failingPlanRepo,
            $this->versionRepo,
            $this->itemRepo,
            $this->revisionRepo,
            $this->cycleRepo
        );

        $caught = false;
        try {
            $faultyVersionService->approvePlanRevision($revRecord->id, $this->fixtureApproverId);
        } catch (\RuntimeException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'Simulated database deadlock'), 'Scenario 19: Simulated failure caught');
        }

        $this->assert($caught === true, 'Scenario 19: Approval encountered mid-transaction failure');

        // Verify complete rollback:
        // 1. Revision record must still be unapproved
        $restoredRev = $this->revisionRepo->findById($revRecord->id);
        $this->assert($restoredRev->approvedByUserId === null, 'Scenario 19: Revision record remains unapproved after rollback');

        // 2. Version 2.0 must still be DRAFT
        $restoredV2 = $this->versionRepo->findById($v2->id);
        $this->assert($restoredV2->status === PlanVersionStatus::DRAFT, 'Scenario 19: Version 2.0 remains in DRAFT status');

        // 3. Prior version 1.0 must still be APPROVED (NOT superseded)
        $restoredV1 = $this->versionRepo->findById($v1->id);
        $this->assert($restoredV1->status === PlanVersionStatus::APPROVED, 'Scenario 19: Prior version 1.0 remains APPROVED');

        // 4. Plan pointer must still point to version 1.0
        $restoredPlan = $this->planRepo->findById($plan->id);
        $this->assert($restoredPlan->currentVersionId === $v1->id, 'Scenario 19: Plan pointer remains linked to Version 1.0');
    }

    /**
     * Scenario 20: Atomicity rollback in createDraftPlan
     */
    private function testScenario20_CreateDraftPlanAtomicityRollback(): void
    {
        $year = 2045;
        $items = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Workstation Laptop',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 2,
                'estimated_unit_cost' => 4500.00,
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
            ],
        ];

        // Wrap plan repository to simulate failure at setCurrentVersion
        $failingPlanRepo = new class($this->planRepo) implements ProcurementPlanRepositoryInterface {
            public function __construct(private ProcurementPlanRepositoryInterface $inner) {}
            public function findById(int $id): ?PlanDTO { return $this->inner->findById($id); }
            public function findByPlanNumber(string $planNumber): ?PlanDTO { return $this->inner->findByPlanNumber($planNumber); }
            public function findByEntityAndYear(int $entityId, int $fiscalYear): ?PlanDTO { return $this->inner->findByEntityAndYear($entityId, $fiscalYear); }
            public function findByEntity(int $entityId): array { return $this->inner->findByEntity($entityId); }
            public function create(array $data): int { return $this->inner->create($data); }
            public function updateStatus(int $id, string $status, ?int $updatedBy = null): bool { return $this->inner->updateStatus($id, $status, $updatedBy); }
            public function setCurrentVersion(int $id, int $versionId, ?int $updatedBy = null): bool {
                throw new \RuntimeException('Simulated failure during setCurrentVersion step in createDraftPlan');
            }
        };

        $faultyPlanService = new ProcurementPlanService($this->db, $failingPlanRepo, $this->versionRepo, $this->itemRepo);

        $caught = false;
        try {
            $faultyPlanService->createDraftPlan($this->fixtureEntity1Id, $year, $items, $this->fixtureUserId);
        } catch (\RuntimeException $e) {
            $caught = true;
        }

        $this->assert($caught === true, 'Scenario 20: Simulated error caught during createDraftPlan');

        // Confirm zero orphan plan, zero orphan version, zero orphan item
        $plan = $this->planRepo->findByEntityAndYear($this->fixtureEntity1Id, $year);
        $this->assert($plan === null, 'Scenario 20: No partial plan persists after rollback');

        $orphanPlanNumber = sprintf('APP-%d-ENT%03d', $year, $this->fixtureEntity1Id);
        $byNumber = $this->planRepo->findByPlanNumber($orphanPlanNumber);
        $this->assert($byNumber === null, 'Scenario 20: No orphan plan by number persists');
    }

    /**
     * Scenario 21: Validation gates in submitPlanForReview
     */
    private function testScenario21_SubmitPlanForReviewValidationGates(): void
    {
        // 1. Non-existent plan
        $caught = false;
        try {
            $this->planService->submitPlanForReview(999999, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught === true, 'Scenario 21: Rejects submission of non-existent plan');

        // 2. Plan with no current version
        $planNoVer = $this->planService->createPlan($this->fixtureEntity1Id, 2046, $this->fixtureUserId);
        $this->trackedPlans[] = $planNoVer->id;

        $caughtNoVer = false;
        try {
            $this->planService->submitPlanForReview($planNoVer->id, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtNoVer = true;
        }
        $this->assert($caughtNoVer === true, 'Scenario 21: Rejects submission of plan without current version');

        // 3. Plan with empty line items in current version
        $verEmpty = $this->planService->createInitialVersion($planNoVer->id, $this->fixtureUserId);
        $this->trackedVersions[] = $verEmpty->id;
        $this->planService->setCurrentVersion($planNoVer->id, $verEmpty->id, $this->fixtureUserId);

        $caughtEmpty = false;
        try {
            $this->planService->submitPlanForReview($planNoVer->id, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtEmpty = true;
        }
        $this->assert($caughtEmpty === true, 'Scenario 21: Rejects submission of plan with 0 items');

        // 4. Successful submission when items exist
        $items = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Workstation Laptop',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 1,
                'estimated_unit_cost' => 4500.00,
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
            ],
        ];
        $this->planService->addPlanItems($planNoVer->id, $verEmpty->id, $items, $this->fixtureUserId);

        $submitted = $this->planService->submitPlanForReview($planNoVer->id, $this->fixtureUserId);
        $this->assert($submitted->status === PlanStatus::SUBMITTED, 'Scenario 21: Plan transitioned to SUBMITTED');
        $this->assert($submitted->currentVersion->status === PlanVersionStatus::SUBMITTED, 'Scenario 21: Version transitioned to SUBMITTED');

        // 5. Ineligible when already SUBMITTED
        $caughtAlready = false;
        try {
            $this->planService->submitPlanForReview($planNoVer->id, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtAlready = true;
        }
        $this->assert($caughtAlready === true, 'Scenario 21: Rejects submission of already submitted plan');
    }

    /**
     * Scenario 22: Version number format and eligibility in setCurrentVersion
     */
    private function testScenario22_VersionEligibilityAndFormat(): void
    {
        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2047, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;

        // 1. Rejects invalid version format
        $caughtFmt = false;
        try {
            $this->planService->createInitialVersion($plan->id, $this->fixtureUserId, 'invalid-version');
        } catch (ValidationException $e) {
            $caughtFmt = true;
        }
        $this->assert($caughtFmt === true, 'Scenario 22: Rejects invalid version format (non-numeric)');

        // 2. Accepts valid format
        $v1 = $this->planService->createInitialVersion($plan->id, $this->fixtureUserId, '1.0');
        $this->trackedVersions[] = $v1->id;
        $this->assert($v1->versionNumber === '1.0', 'Scenario 22: Valid version format accepted');

        // 3. Rejects assigning SUPERSEDED version as current
        $this->versionRepo->updateStatus($v1->id, PlanVersionStatus::SUPERSEDED->value);
        $caughtSup = false;
        try {
            $this->planService->setCurrentVersion($plan->id, $v1->id, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtSup = true;
        }
        $this->assert($caughtSup === true, 'Scenario 22: Rejects assigning SUPERSEDED version as current');

        // 4. Rejects assigning REJECTED version as current
        $this->versionRepo->updateStatus($v1->id, PlanVersionStatus::REJECTED->value);
        $caughtRej = false;
        try {
            $this->planService->setCurrentVersion($plan->id, $v1->id, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtRej = true;
        }
        $this->assert($caughtRej === true, 'Scenario 22: Rejects assigning REJECTED version as current');
    }

    /**
     * Scenario 23: Bcmath precision in calculateAndPersistVersionTotal
     */
    private function testScenario23_CalculateAndPersistVersionTotalBcmath(): void
    {
        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2048, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;

        $ver = $this->planService->createInitialVersion($plan->id, $this->fixtureUserId);
        $this->trackedVersions[] = $ver->id;

        // Line 1: 3.33 * 10.15 = 33.7995 -> 33.79 (scale 2)
        // Line 2: 7.50 * 123.45 = 925.875 -> 925.87 (scale 2)
        // Total = 33.79 + 925.87 = 959.66
        $items = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Precision Item 1',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => '3.33',
                'estimated_unit_cost' => '10.15',
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
            ],
            [
                'standard_item_id' => $this->fixtureStandardItem2Id,
                'item_description' => 'Precision Item 2',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => '7.50',
                'estimated_unit_cost' => '123.45',
                'target_quarter' => 'Q2',
                'funding_source' => 'GOG',
            ],
        ];

        $this->planService->addPlanItems($plan->id, $ver->id, $items, $this->fixtureUserId);

        // Call calculateAndPersistVersionTotal directly
        $total = $this->planService->calculateAndPersistVersionTotal($ver->id);

        $this->assert(is_string($total), 'Scenario 23: calculateAndPersistVersionTotal returns native string');
        $this->assert($total === '959.66', 'Scenario 23: Exact bcmath precision computed (959.66 GHS)');

        // Verify database persistence
        $persistedVer = $this->versionRepo->findById($ver->id);
        $this->assert($persistedVer->totalEstimatedCost === '959.66', 'Scenario 23: Total persisted as exact decimal string in database');
    }

    /**
     * Scenario 24: Add plan items input validation rules
     */
    private function testScenario24_AddPlanItemsValidationRules(): void
    {
        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2049, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;

        $ver = $this->planService->createInitialVersion($plan->id, $this->fixtureUserId);
        $this->trackedVersions[] = $ver->id;

        // 1. Rejects empty items array
        $caughtEmpty = false;
        try {
            $this->planService->addPlanItems($plan->id, $ver->id, [], $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtEmpty = true;
        }
        $this->assert($caughtEmpty === true, 'Scenario 24: Rejects empty items array');

        // 2. Rejects zero quantity
        $caughtZeroQty = false;
        try {
            $this->planService->addPlanItems($plan->id, $ver->id, [
                [
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Item',
                    'category_id' => $this->fixtureCategoryId,
                    'uom_id' => $this->fixtureUomId,
                    'planned_quantity' => 0,
                    'estimated_unit_cost' => 100.00,
                    'target_quarter' => 'Q1',
                    'funding_source' => 'IGF',
                ]
            ], $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtZeroQty = true;
        }
        $this->assert($caughtZeroQty === true, 'Scenario 24: Rejects zero planned quantity');

        // 3. Rejects negative unit cost
        $caughtNegCost = false;
        try {
            $this->planService->addPlanItems($plan->id, $ver->id, [
                [
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Item',
                    'category_id' => $this->fixtureCategoryId,
                    'uom_id' => $this->fixtureUomId,
                    'planned_quantity' => 5,
                    'estimated_unit_cost' => -50.00,
                    'target_quarter' => 'Q1',
                    'funding_source' => 'IGF',
                ]
            ], $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtNegCost = true;
        }
        $this->assert($caughtNegCost === true, 'Scenario 24: Rejects negative unit cost');

        // 4. Rejects invalid target quarter
        $caughtBadQtr = false;
        try {
            $this->planService->addPlanItems($plan->id, $ver->id, [
                [
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Item',
                    'category_id' => $this->fixtureCategoryId,
                    'uom_id' => $this->fixtureUomId,
                    'planned_quantity' => 5,
                    'estimated_unit_cost' => 50.00,
                    'target_quarter' => 'Q9',
                    'funding_source' => 'IGF',
                ]
            ], $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtBadQtr = true;
        }
        $this->assert($caughtBadQtr === true, 'Scenario 24: Rejects invalid target quarter');

        // 5. Rejects missing description and missing funding source
        $caughtDesc = false;
        try {
            $this->planService->addPlanItems($plan->id, $ver->id, [
                [
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => '',
                    'category_id' => $this->fixtureCategoryId,
                    'uom_id' => $this->fixtureUomId,
                    'planned_quantity' => 5,
                    'estimated_unit_cost' => 50.00,
                    'target_quarter' => 'Q1',
                    'funding_source' => '',
                ]
            ], $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtDesc = true;
        }
        $this->assert($caughtDesc === true, 'Scenario 24: Rejects missing item description and funding source');

        // 6. Rejects adding items to non-DRAFT version
        $this->versionRepo->updateStatus($ver->id, PlanVersionStatus::SUBMITTED->value);
        $caughtNonDraft = false;
        try {
            $this->planService->addPlanItems($plan->id, $ver->id, [
                [
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Valid Item',
                    'category_id' => $this->fixtureCategoryId,
                    'uom_id' => $this->fixtureUomId,
                    'planned_quantity' => 5,
                    'estimated_unit_cost' => 50.00,
                    'target_quarter' => 'Q1',
                    'funding_source' => 'IGF',
                ]
            ], $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtNonDraft = true;
        }
        $this->assert($caughtNonDraft === true, 'Scenario 24: Rejects adding items to non-DRAFT version');
    }

    /**
     * Scenario 25: Create plan validation and number formatting
     */
    private function testScenario25_CreatePlanValidationAndNumberFormatting(): void
    {
        // 1. Rejects invalid user ID <= 0
        $caughtUser = false;
        try {
            $this->planService->createPlan($this->fixtureEntity1Id, 2050, 0);
        } catch (ValidationException $e) {
            $caughtUser = true;
        }
        $this->assert($caughtUser === true, 'Scenario 25: Rejects user ID <= 0');

        // 2. Rejects entity ID <= 0
        $caughtEnt = false;
        try {
            $this->planService->createPlan(0, 2050, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtEnt = true;
        }
        $this->assert($caughtEnt === true, 'Scenario 25: Rejects entity ID <= 0');

        // 3. Rejects non-existent planning entity ID
        $caughtNonExistent = false;
        try {
            $this->planService->createPlan(999999, 2050, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtNonExistent = true;
        }
        $this->assert($caughtNonExistent === true, 'Scenario 25: Rejects non-existent planning entity ID');

        // 4. Rejects fiscal year out of bounds (< 2000 or > 2100)
        $caughtYearLow = false;
        try {
            $this->planService->createPlan($this->fixtureEntity1Id, 1999, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtYearLow = true;
        }
        $this->assert($caughtYearLow === true, 'Scenario 25: Rejects fiscal year < 2000');

        $caughtYearHigh = false;
        try {
            $this->planService->createPlan($this->fixtureEntity1Id, 2101, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtYearHigh = true;
        }
        $this->assert($caughtYearHigh === true, 'Scenario 25: Rejects fiscal year > 2100');

        // 5. Verifies zero-padded format: APP-{fiscalYear}-ENT{zero-padded-entity-id}
        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2050, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;

        $expectedNumber = sprintf('APP-2050-ENT%03d', $this->fixtureEntity1Id);
        $this->assert($plan->planNumber === $expectedNumber, "Scenario 25: Generated standard plan number matches format ({$expectedNumber})");
        $this->assert($plan->status === PlanStatus::DRAFT, 'Scenario 25: Created plan has DRAFT status');
    }

    /**
     * Scenario 26: Authorization enforcement
     */
    private function testScenario26_AuthorizationEnforcement(): void
    {
        // 1. Logout / Guest access check
        AuthManager::logout();

        $caughtGuest = false;
        try {
            $this->planService->createPlan($this->fixtureEntity1Id, 2051, $this->fixtureUserId);
        } catch (AuthorizationException $e) {
            $caughtGuest = true;
        }
        $this->assert($caughtGuest === true, 'Scenario 26: Denies unauthenticated guest on createPlan');

        // 2. Login user without SUBMIT permission
        AuthManager::login([
            'id' => $this->fixtureUserId,
            'username' => 'limited_user',
            'permissions' => [PlanningPermissions::VIEW, PlanningPermissions::CREATE],
        ]);

        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2051, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;

        $caughtSubmit = false;
        try {
            $this->planService->submitPlanForReview($plan->id, $this->fixtureUserId);
        } catch (AuthorizationException $e) {
            $caughtSubmit = true;
        }
        $this->assert($caughtSubmit === true, 'Scenario 26: Denies user lacking SUBMIT permission on submitPlanForReview');

        // Restore full permissions for fixtures teardown
        AuthManager::login([
            'id' => $this->fixtureUserId,
            'username' => 'full_admin',
            'permissions' => PlanningPermissions::all(),
        ]);
    }

    /**
     * Scenario 27: PlanReviewCycleService initiateReviewCycle edge cases
     */
    private function testScenario27_InitiateReviewCycleEdgeCases(): void
    {
        // 1. Setup dedicated plan & version
        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2060, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;
        $ver = $this->planService->createInitialVersion($plan->id, $this->fixtureUserId);
        $this->trackedVersions[] = $ver->id;
        $this->planService->addPlanItems($plan->id, $ver->id, [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Dedicated item for cycle test',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 1,
                'estimated_unit_cost' => 100.00,
                'target_quarter' => 'Q1',
                'funding_source' => 'GOG',
            ]
        ], $this->fixtureUserId);
        $this->planService->setCurrentVersion($plan->id, $ver->id, $this->fixtureUserId);

        // 2. Reject unapproved plan (status is DRAFT)
        $caughtUnapprovedPlan = false;
        try {
            $this->cycleService->initiateReviewCycle($plan->id, 2060, 'Q1', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtUnapprovedPlan = true;
            $this->assert(str_contains($e->getMessage(), 'approved procurement plans'), 'Scenario 27: Error indicates plan must be approved');
        }
        $this->assert($caughtUnapprovedPlan === true, 'Scenario 27: Rejects review initiation on unapproved (DRAFT) plan');

        // Promote plan to APPROVED, but keep version in DRAFT
        $this->planRepo->updateStatus($plan->id, PlanStatus::APPROVED->value, $this->fixtureApproverId);

        // 3. Reject unapproved version (version status is DRAFT) with null version ID
        $caughtUnapprovedVerNull = false;
        try {
            $this->cycleService->initiateReviewCycle($plan->id, 2060, 'Q1', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtUnapprovedVerNull = true;
            $this->assert(str_contains($e->getMessage(), 'must be APPROVED'), 'Scenario 27: Error indicates active version must be APPROVED');
        }
        $this->assert($caughtUnapprovedVerNull === true, 'Scenario 27: Rejects review initiation when active version is DRAFT (null version ID)');

        // 4. Reject unapproved version with explicit version ID
        $caughtUnapprovedVerExplicit = false;
        try {
            $this->cycleService->initiateReviewCycle($plan->id, 2060, 'Q1', $this->fixtureUserId, $ver->id);
        } catch (ValidationException $e) {
            $caughtUnapprovedVerExplicit = true;
            $this->assert(str_contains($e->getMessage(), 'must be APPROVED'), 'Scenario 27: Error indicates supplied version must be APPROVED');
        }
        $this->assert($caughtUnapprovedVerExplicit === true, 'Scenario 27: Rejects review initiation when supplied version is DRAFT');

        // Promote version to APPROVED
        $this->versionRepo->updateStatus($ver->id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));

        // 5. Success with explicit version ID
        $cycle1 = $this->cycleService->initiateReviewCycle($plan->id, 2060, 'Q1', $this->fixtureUserId, $ver->id);
        $this->trackedCycles[] = $cycle1->id;
        $this->assert($cycle1->id > 0, 'Scenario 27: Successfully initiated review cycle with explicit version ID');
        $this->assert($cycle1->procurementPlanId === $plan->id, 'Scenario 27: Cycle links to correct plan');
        $this->assert($cycle1->activeVersionId === $ver->id, 'Scenario 27: Cycle links to supplied version');
        $this->assert($cycle1->reviewQuarter === ReviewQuarter::Q1, 'Scenario 27: Cycle review quarter is Q1');
        $this->assert($cycle1->fiscalYear === 2060, 'Scenario 27: Cycle fiscal year is 2060');
        $this->assert($cycle1->reviewStatus === ReviewCycleStatus::IN_PROGRESS, 'Scenario 27: Cycle initial status is IN_PROGRESS');
        $this->assert($cycle1->reviewOutcome === null, 'Scenario 27: Cycle initial outcome is null');

        // 6. Duplicate review cycle rejection for same plan, fiscal year, quarter
        $caughtDup = false;
        try {
            $this->cycleService->initiateReviewCycle($plan->id, 2060, 'Q1', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtDup = true;
            $this->assert(str_contains($e->getMessage(), 'already exists'), 'Scenario 27: Error explains duplicate review session');
        }
        $this->assert($caughtDup === true, 'Scenario 27: Rejects duplicate review cycle for same plan, fiscal year, and quarter');

        // 7. Invalid quarter rejection
        $caughtBadQtr = false;
        try {
            $this->cycleService->initiateReviewCycle($plan->id, 2060, 'Q5', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtBadQtr = true;
        }
        $this->assert($caughtBadQtr === true, 'Scenario 27: Rejects invalid review quarter (Q5)');

        $caughtEmptyQtr = false;
        try {
            $this->cycleService->initiateReviewCycle($plan->id, 2060, '', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtEmptyQtr = true;
        }
        $this->assert($caughtEmptyQtr === true, 'Scenario 27: Rejects empty review quarter');

        // 8. Fiscal year out of bounds (< 2000 or > 2100)
        $caughtYearLow = false;
        try {
            $this->cycleService->initiateReviewCycle($plan->id, 1999, 'Q2', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtYearLow = true;
        }
        $this->assert($caughtYearLow === true, 'Scenario 27: Rejects fiscal year < 2000');

        $caughtYearHigh = false;
        try {
            $this->cycleService->initiateReviewCycle($plan->id, 2101, 'Q2', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtYearHigh = true;
        }
        $this->assert($caughtYearHigh === true, 'Scenario 27: Rejects fiscal year > 2100');

        // 9. Non-existent plan ID
        $caughtBadPlan = false;
        try {
            $this->cycleService->initiateReviewCycle(999999, 2060, 'Q2', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtBadPlan = true;
        }
        $this->assert($caughtBadPlan === true, 'Scenario 27: Rejects non-existent procurement plan ID');

        // 10. Acting user ID <= 0
        $caughtBadUser = false;
        try {
            $this->cycleService->initiateReviewCycle($plan->id, 2060, 'Q2', 0);
        } catch (ValidationException $e) {
            $caughtBadUser = true;
        }
        $this->assert($caughtBadUser === true, 'Scenario 27: Rejects acting user ID <= 0');

        // 11. Cross-plan active version rejection
        $planB = $this->planService->createPlan($this->fixtureEntity2Id, 2060, $this->fixtureUserId);
        $this->trackedPlans[] = $planB->id;
        $verB = $this->planService->createInitialVersion($planB->id, $this->fixtureUserId);
        $this->trackedVersions[] = $verB->id;
        $this->versionRepo->updateStatus($verB->id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));

        $caughtCross = false;
        try {
            $this->cycleService->initiateReviewCycle($plan->id, 2060, 'Q2', $this->fixtureUserId, $verB->id);
        } catch (ValidationException $e) {
            $caughtCross = true;
            $this->assert(str_contains($e->getMessage(), 'does not belong to'), 'Scenario 27: Error indicates version mismatch');
        }
        $this->assert($caughtCross === true, 'Scenario 27: Rejects cross-plan version reference in initiateReviewCycle');

        // 12. Success with null version ID (resolves currentVersionId)
        $cycle2 = $this->cycleService->initiateReviewCycle($plan->id, 2060, 'Q2', $this->fixtureUserId);
        $this->trackedCycles[] = $cycle2->id;
        $this->assert($cycle2->id > 0, 'Scenario 27: Successfully initiated review cycle with resolved active version');
        $this->assert($cycle2->activeVersionId === $ver->id, 'Scenario 27: Auto-resolved activeVersionId matches plan currentVersionId');
        $this->assert($cycle2->reviewQuarter === ReviewQuarter::Q2, 'Scenario 27: Auto-resolved cycle review quarter is Q2');
    }

    /**
     * Scenario 28: PlanReviewCycleService recordReviewOutcome edge cases
     */
    private function testScenario28_RecordReviewOutcomeEdgeCases(): void
    {
        // Setup dedicated plan, version, and review cycles
        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2061, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;
        $ver = $this->planService->createInitialVersion($plan->id, $this->fixtureUserId);
        $this->trackedVersions[] = $ver->id;
        $this->planService->addPlanItems($plan->id, $ver->id, [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Item for outcome tests',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 2,
                'estimated_unit_cost' => 50.00,
                'target_quarter' => 'Q1',
                'funding_source' => 'GOG',
            ]
        ], $this->fixtureUserId);
        $this->planService->setCurrentVersion($plan->id, $ver->id, $this->fixtureUserId);

        // Promote plan and version to APPROVED
        $this->planRepo->updateStatus($plan->id, PlanStatus::APPROVED->value, $this->fixtureApproverId);
        $this->versionRepo->updateStatus($ver->id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));

        // Initiate 2 review cycles: Q1 and Q2
        $cycleQ1 = $this->cycleService->initiateReviewCycle($plan->id, 2061, 'Q1', $this->fixtureUserId);
        $this->trackedCycles[] = $cycleQ1->id;
        $cycleQ2 = $this->cycleService->initiateReviewCycle($plan->id, 2061, 'Q2', $this->fixtureUserId);
        $this->trackedCycles[] = $cycleQ2->id;

        // 1. Acting user ID <= 0 rejection
        $caughtUser = false;
        try {
            $this->cycleService->recordReviewOutcome($cycleQ1->id, ReviewOutcome::NO_CHANGE->value, null, 0);
        } catch (ValidationException $e) {
            $caughtUser = true;
        }
        $this->assert($caughtUser === true, 'Scenario 28: Rejects acting user ID <= 0 in recordReviewOutcome');

        // 2. Non-existent cycle ID rejection
        $caughtBadCycle = false;
        try {
            $this->cycleService->recordReviewOutcome(999999, ReviewOutcome::NO_CHANGE->value, null, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtBadCycle = true;
        }
        $this->assert($caughtBadCycle === true, 'Scenario 28: Rejects non-existent review cycle ID');

        // 3. Invalid outcome string rejection
        $caughtBadOutcome = false;
        try {
            $this->cycleService->recordReviewOutcome($cycleQ1->id, 'NOT_A_REAL_OUTCOME', null, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtBadOutcome = true;
            $this->assert(str_contains($e->getMessage(), 'Review cycle completion validation failed'), 'Scenario 28: Error identifies completion validation failure');
        }
        $this->assert($caughtBadOutcome === true, 'Scenario 28: Rejects invalid review outcome string');

        // 4. Missing notes when outcome is REVISION_REQUIRED
        $caughtNullNotes = false;
        try {
            $this->cycleService->recordReviewOutcome($cycleQ1->id, ReviewOutcome::REVISION_REQUIRED->value, null, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtNullNotes = true;
            $this->assert(isset($e->getErrors()['review_notes']), 'Scenario 28: Error requires review notes');
        }
        $this->assert($caughtNullNotes === true, 'Scenario 28: Rejects REVISION_REQUIRED with null notes');

        $caughtEmptyNotes = false;
        try {
            $this->cycleService->recordReviewOutcome($cycleQ1->id, ReviewOutcome::REVISION_REQUIRED->value, '', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtEmptyNotes = true;
        }
        $this->assert($caughtEmptyNotes === true, 'Scenario 28: Rejects REVISION_REQUIRED with empty string notes');

        $caughtSpacesNotes = false;
        try {
            $this->cycleService->recordReviewOutcome($cycleQ1->id, ReviewOutcome::REVISION_REQUIRED->value, '     ', $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtSpacesNotes = true;
        }
        $this->assert($caughtSpacesNotes === true, 'Scenario 28: Rejects REVISION_REQUIRED with whitespace-only notes');

        // 5. Success with NO_CHANGE (notes null/optional)
        $completedQ1 = $this->cycleService->recordReviewOutcome(
            $cycleQ1->id,
            ReviewOutcome::NO_CHANGE->value,
            null,
            $this->fixtureUserId
        );
        $this->assert($completedQ1->reviewStatus === ReviewCycleStatus::COMPLETED, 'Scenario 28: Cycle Q1 status transitioned to COMPLETED');
        $this->assert($completedQ1->reviewOutcome === ReviewOutcome::NO_CHANGE, 'Scenario 28: Cycle Q1 outcome stamped as NO_CHANGE');
        $this->assert($completedQ1->completedAt !== null, 'Scenario 28: Cycle Q1 completed_at timestamp recorded');
        $this->assert($completedQ1->reviewedByUserId === $this->fixtureUserId, 'Scenario 28: Cycle Q1 reviewer recorded');

        // Enforce: Plan and Version statuses remain APPROVED after NO_CHANGE
        $planCheck = $this->planRepo->findById($plan->id);
        $verCheck = $this->versionRepo->findById($ver->id);
        $this->assert($planCheck->status->isApproved(), 'Scenario 28: Plan remains in approved state after NO_CHANGE outcome');
        $this->assert($verCheck->status->isApproved(), 'Scenario 28: Version remains in approved state after NO_CHANGE outcome');

        // 6. Reject already completed cycle
        $caughtAlreadyCompleted = false;
        try {
            $this->cycleService->recordReviewOutcome(
                $cycleQ1->id,
                ReviewOutcome::NO_CHANGE->value,
                'Attempting to edit closed cycle',
                $this->fixtureUserId
            );
        } catch (ValidationException $e) {
            $caughtAlreadyCompleted = true;
            $this->assert(str_contains($e->getMessage(), 'already completed'), 'Scenario 28: Error specifies cycle is already completed');
        }
        $this->assert($caughtAlreadyCompleted === true, 'Scenario 28: Rejects recording outcome on already completed cycle');

        // 7. Success with REVISION_REQUIRED (with valid notes)
        $notes = 'Increased student enrollment requires 15 additional workstations in Q3.';
        $completedQ2 = $this->cycleService->recordReviewOutcome(
            $cycleQ2->id,
            ReviewOutcome::REVISION_REQUIRED->value,
            $notes,
            $this->fixtureUserId
        );
        $this->assert($completedQ2->reviewStatus === ReviewCycleStatus::COMPLETED, 'Scenario 28: Cycle Q2 status transitioned to COMPLETED');
        $this->assert($completedQ2->reviewOutcome === ReviewOutcome::REVISION_REQUIRED, 'Scenario 28: Cycle Q2 outcome stamped as REVISION_REQUIRED');
        $this->assert($completedQ2->reviewNotes === $notes, 'Scenario 28: Cycle Q2 review notes correctly persisted');
        $this->assert($completedQ2->completedAt !== null, 'Scenario 28: Cycle Q2 completed_at timestamp recorded');
        $this->assert($completedQ2->reviewedByUserId === $this->fixtureUserId, 'Scenario 28: Cycle Q2 reviewer recorded');

        // Enforce: Active baseline remains unchanged until revision workflow is executed (FR-049/FR-050)
        $planCheck2 = $this->planRepo->findById($plan->id);
        $verCheck2 = $this->versionRepo->findById($ver->id);
        $this->assert($planCheck2->status->isApproved(), 'Scenario 28: Plan remains in approved state ready for revision');
        $this->assert($verCheck2->status->isApproved(), 'Scenario 28: Baseline version remains active until revision approval');

        // 8. Reject alien/cross-plan version reference in recordReviewOutcome
        // Create Plan B with Version B
        $planB = $this->planService->createPlan($this->fixtureEntity2Id, 2061, $this->fixtureUserId);
        $this->trackedPlans[] = $planB->id;
        $verB = $this->planService->createInitialVersion($planB->id, $this->fixtureUserId);
        $this->trackedVersions[] = $verB->id;
        $this->planRepo->updateStatus($planB->id, PlanStatus::APPROVED->value, $this->fixtureApproverId);
        $this->versionRepo->updateStatus($verB->id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));

        // Insert cycle referencing Plan A but Version B (from Plan B) under disabled FK
        $this->db->exec("SET FOREIGN_KEY_CHECKS=0");
        $stmt = $this->db->prepare("INSERT INTO `plan_review_cycles` (
            `procurement_plan_id`, `active_version_id`, `fiscal_year`, `review_quarter`, `review_status`, `created_at`, `created_by`
        ) VALUES (:p, :v, 2061, 'Q3', 'IN_PROGRESS', NOW(), :u)");
        $stmt->execute(['p' => $plan->id, 'v' => $verB->id, 'u' => $this->fixtureUserId]);
        $alienCycleId = (int)$this->db->lastInsertId();
        $this->db->exec("SET FOREIGN_KEY_CHECKS=1");
        $this->trackedCycles[] = $alienCycleId;

        $caughtAlien = false;
        try {
            $this->cycleService->recordReviewOutcome($alienCycleId, ReviewOutcome::NO_CHANGE->value, null, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtAlien = true;
            $this->assert(str_contains($e->getMessage(), 'different plan') || isset($e->getErrors()['active_version_id']), 'Scenario 28: Error detects cross-plan version in review cycle');
        }
        $this->assert($caughtAlien === true, 'Scenario 28: Rejects recording outcome when cycle references alien version');

        // 9. Reject outcome completion if plan or version is not approved
        $planUnapproved = $this->planService->createPlan($this->fixtureEntity1Id, 2062, $this->fixtureUserId);
        $this->trackedPlans[] = $planUnapproved->id;
        $verUnapproved = $this->planService->createInitialVersion($planUnapproved->id, $this->fixtureUserId);
        $this->trackedVersions[] = $verUnapproved->id;
        $this->planService->setCurrentVersion($planUnapproved->id, $verUnapproved->id, $this->fixtureUserId);

        // Insert cycle on unapproved plan
        $this->db->exec("SET FOREIGN_KEY_CHECKS=0");
        $stmt = $this->db->prepare("INSERT INTO `plan_review_cycles` (
            `procurement_plan_id`, `active_version_id`, `fiscal_year`, `review_quarter`, `review_status`, `created_at`, `created_by`
        ) VALUES (:p, :v, 2062, 'Q1', 'IN_PROGRESS', NOW(), :u)");
        $stmt->execute(['p' => $planUnapproved->id, 'v' => $verUnapproved->id, 'u' => $this->fixtureUserId]);
        $unapprovedCycleId = (int)$this->db->lastInsertId();
        $this->db->exec("SET FOREIGN_KEY_CHECKS=1");
        $this->trackedCycles[] = $unapprovedCycleId;

        $caughtUnapprovedPlanOutcome = false;
        try {
            $this->cycleService->recordReviewOutcome($unapprovedCycleId, ReviewOutcome::NO_CHANGE->value, null, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtUnapprovedPlanOutcome = true;
            $this->assert(str_contains($e->getMessage(), 'Plan must be in an approved state'), 'Scenario 28: Error enforces plan must be approved');
        }
        $this->assert($caughtUnapprovedPlanOutcome === true, 'Scenario 28: Rejects recording review outcome on unapproved plan');
    }

    /**
     * Scenario 29: PlanVersionService createPlanRevision edge cases
     */
    private function testScenario29_CreatePlanRevisionEdgeCases(): void
    {
        // 1. Setup baseline plan
        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2063, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;

        $items = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Baseline Laptop',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 2,
                'estimated_unit_cost' => 1000.00,
                'target_quarter' => 'Q1',
                'funding_source' => 'GOG',
            ]
        ];

        // A. Reject user ID <= 0
        $caughtUser = false;
        try {
            $this->versionService->createPlanRevision($plan->id, null, 'Valid justification text here', $items, 0);
        } catch (ValidationException $e) {
            $caughtUser = true;
            $this->assert(str_contains($e->getMessage(), 'user ID is required'), 'Scenario 29: Error requires acting user ID');
        }
        $this->assert($caughtUser === true, 'Scenario 29: Rejects acting user ID <= 0 in createPlanRevision');

        // B. Reject non-existent plan
        $caughtPlan = false;
        try {
            $this->versionService->createPlanRevision(999999, null, 'Valid justification text here', $items, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtPlan = true;
            $this->assert(str_contains($e->getMessage(), 'not found'), 'Scenario 29: Error indicates plan not found');
        }
        $this->assert($caughtPlan === true, 'Scenario 29: Rejects non-existent procurement plan in createPlanRevision');

        // C. Reject plan without current version
        $caughtNoVer = false;
        try {
            $this->versionService->createPlanRevision($plan->id, null, 'Valid justification text here', $items, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtNoVer = true;
            $this->assert(str_contains($e->getMessage(), 'without an active version'), 'Scenario 29: Error indicates missing active version');
        }
        $this->assert($caughtNoVer === true, 'Scenario 29: Rejects plan without active version attached');

        // Create version v1 and attach to plan
        $v1 = $this->planService->createInitialVersion($plan->id, $this->fixtureUserId, '1.0');
        $this->trackedVersions[] = $v1->id;
        $this->planService->addPlanItems($plan->id, $v1->id, $items, $this->fixtureUserId);
        $this->planService->setCurrentVersion($plan->id, $v1->id, $this->fixtureUserId);
        $this->planRepo->updateStatus($plan->id, PlanStatus::APPROVED->value, $this->fixtureApproverId);
        $this->versionRepo->updateStatus($v1->id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));

        // D. Cross-plan prior version check
        // Create plan B and version B
        $planB = $this->planService->createPlan($this->fixtureEntity2Id, 2063, $this->fixtureUserId);
        $this->trackedPlans[] = $planB->id;
        $verB = $this->planService->createInitialVersion($planB->id, $this->fixtureUserId, '1.0');
        $this->trackedVersions[] = $verB->id;

        // Force planB current_version_id to v1 (from plan A)
        $this->db->exec("SET FOREIGN_KEY_CHECKS=0");
        $this->db->exec("UPDATE `procurement_plans` SET `current_version_id` = {$v1->id} WHERE `id` = {$planB->id}");
        $this->db->exec("SET FOREIGN_KEY_CHECKS=1");

        $caughtCrossPrior = false;
        try {
            $this->versionService->createPlanRevision($planB->id, null, 'Cross plan justification', $items, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtCrossPrior = true;
            $this->assert(str_contains($e->getMessage(), 'does not belong to'), 'Scenario 29: Error detects cross-plan prior version');
        }
        $this->assert($caughtCrossPrior === true, 'Scenario 29: Rejects cross-plan prior version reference');

        // Reset planB pointer
        $this->db->exec("UPDATE `procurement_plans` SET `current_version_id` = {$verB->id} WHERE `id` = {$planB->id}");

        // E. Review cycle validations (when supplied)
        // E1. reviewCycleId <= 0
        $caughtCycleId = false;
        try {
            $this->versionService->createPlanRevision($plan->id, -1, 'Valid justification text here', $items, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtCycleId = true;
            $this->assert(str_contains($e->getMessage(), 'positive integer'), 'Scenario 29: Error rejects negative review cycle ID');
        }
        $this->assert($caughtCycleId === true, 'Scenario 29: Rejects negative review cycle ID');

        // E2. Non-existent review cycle ID
        $caughtMissingCycle = false;
        try {
            $this->versionService->createPlanRevision($plan->id, 999999, 'Valid justification text here', $items, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtMissingCycle = true;
            $this->assert(str_contains($e->getMessage(), 'not found'), 'Scenario 29: Error indicates cycle not found');
        }
        $this->assert($caughtMissingCycle === true, 'Scenario 29: Rejects non-existent review cycle ID');

        // E3. Cycle belonging to another plan
        $this->planRepo->updateStatus($planB->id, PlanStatus::APPROVED->value, $this->fixtureApproverId);
        $this->versionRepo->updateStatus($verB->id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));
        $cycleOther = $this->cycleService->initiateReviewCycle($planB->id, 2063, 'Q1', $this->fixtureUserId, $verB->id);
        $this->trackedCycles[] = $cycleOther->id;

        $caughtOtherCycle = false;
        try {
            $this->versionService->createPlanRevision($plan->id, $cycleOther->id, 'Valid justification text here', $items, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtOtherCycle = true;
            $this->assert(str_contains($e->getMessage(), 'does not belong to'), 'Scenario 29: Error detects cycle belonging to another plan');
        }
        $this->assert($caughtOtherCycle === true, 'Scenario 29: Rejects review cycle belonging to another plan');

        // E4. Cycle is not completed (IN_PROGRESS)
        $cyclePlan = $this->cycleService->initiateReviewCycle($plan->id, 2063, 'Q1', $this->fixtureUserId, $v1->id);
        $this->trackedCycles[] = $cyclePlan->id;

        $caughtIncompleteCycle = false;
        try {
            $this->versionService->createPlanRevision($plan->id, $cyclePlan->id, 'Valid justification text here', $items, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtIncompleteCycle = true;
            $this->assert(str_contains($e->getMessage(), 'not completed'), 'Scenario 29: Error indicates review cycle is not completed');
        }
        $this->assert($caughtIncompleteCycle === true, 'Scenario 29: Rejects uncompleted review cycle');

        // E5. Cycle completed with outcome NO_CHANGE
        $this->cycleService->recordReviewOutcome($cyclePlan->id, ReviewOutcome::NO_CHANGE->value, null, $this->fixtureUserId);

        $caughtNoChange = false;
        try {
            $this->versionService->createPlanRevision($plan->id, $cyclePlan->id, 'Valid justification text here', $items, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtNoChange = true;
            $this->assert(str_contains($e->getMessage(), 'does not authorize revision'), 'Scenario 29: Error indicates NO_CHANGE does not authorize revision');
        }
        $this->assert($caughtNoChange === true, 'Scenario 29: Rejects review cycle with NO_CHANGE outcome');

        // F. Justification validation
        // F1. Empty justification
        $caughtEmptyJust = false;
        try {
            $this->versionService->createPlanRevision($plan->id, null, '', $items, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtEmptyJust = true;
        }
        $this->assert($caughtEmptyJust === true, 'Scenario 29: Rejects empty revision justification');

        // F2. Justification < 10 characters
        $caughtShortJust = false;
        try {
            $this->versionService->createPlanRevision($plan->id, null, 'Too short', $items, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtShortJust = true;
            $this->assert(str_contains($e->getMessage(), 'at least 10 characters') || isset($e->getErrors()['revision_justification']), 'Scenario 29: Error enforces 10 char minimum justification');
        }
        $this->assert($caughtShortJust === true, 'Scenario 29: Rejects justification shorter than 10 characters');

        // G. Revised items validation
        // G1. Empty items array
        $caughtEmptyItems = false;
        try {
            $this->versionService->createPlanRevision($plan->id, null, 'Sufficient justification length here', [], $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtEmptyItems = true;
        }
        $this->assert($caughtEmptyItems === true, 'Scenario 29: Rejects empty revised items array');

        // G2. Invalid line item (quantity <= 0)
        $badItems = $items;
        $badItems[0]['planned_quantity'] = '0.00';
        $caughtBadQty = false;
        try {
            $this->versionService->createPlanRevision($plan->id, null, 'Sufficient justification length here', $badItems, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtBadQty = true;
        }
        $this->assert($caughtBadQty === true, 'Scenario 29: Rejects item with planned quantity <= 0');

        // G3. Invalid line item (negative cost)
        $badItemsCost = $items;
        $badItemsCost[0]['estimated_unit_cost'] = '-50.00';
        $caughtBadCost = false;
        try {
            $this->versionService->createPlanRevision($plan->id, null, 'Sufficient justification length here', $badItemsCost, $this->fixtureUserId);
        } catch (ValidationException $e) {
            $caughtBadCost = true;
        }
        $this->assert($caughtBadCost === true, 'Scenario 29: Rejects item with negative estimated unit cost');

        // H. Successful creation without review cycle (e.g. ad-hoc revision)
        // 2 items:
        // Item 1: 5 * 100.25 = 501.25
        // Item 2: 2 * 250.50 = 501.00
        // Total = 1002.25
        $validItems = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Revised Laptop Batch',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => '5.00',
                'estimated_unit_cost' => '100.25',
                'target_quarter' => 'Q2',
                'funding_source' => 'GOG',
            ],
            [
                'standard_item_id' => $this->fixtureStandardItem2Id,
                'item_description' => 'Revised Monitor Batch',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => '2.00',
                'estimated_unit_cost' => '250.50',
                'target_quarter' => 'Q3',
                'funding_source' => 'IGF',
            ],
        ];

        $v2 = $this->versionService->createPlanRevision(
            $plan->id,
            null,
            'Ad-hoc mid-year budget reallocation justification',
            $validItems,
            $this->fixtureUserId
        );
        $this->trackedVersions[] = $v2->id;

        $this->assert($v2->id > 0, 'Scenario 29: Revision version successfully staged');
        $this->assert($v2->versionNumber === '2.0', 'Scenario 29: Next version number calculated numerically as 2.0');
        $this->assert($v2->status === PlanVersionStatus::DRAFT, 'Scenario 29: Staged revision version status is DRAFT');
        $this->assert($v2->totalEstimatedCost === '1002.25', 'Scenario 29: New version total calculated and persisted using bcmath (1002.25)');
        $this->assert(count($v2->items) === 2, 'Scenario 29: Both revised line items successfully inserted and hydrated');

        // Verify plan current_version_id was NOT updated
        $planCheck = $this->planRepo->findById($plan->id);
        $this->assert($planCheck->currentVersionId === $v1->id, 'Scenario 29: Plan current_version_id was NOT updated prior to approval');

        // Verify provenance record
        $revRecords = $this->revisionRepo->findByPlanId($plan->id);
        $this->assert(count($revRecords) === 1, 'Scenario 29: Exactly one revision record created in plan_revision_records');
        $rec = $revRecords[0];
        $this->trackedRevisions[] = $rec->id;
        $this->assert($rec->priorVersionId === $v1->id, 'Scenario 29: Revision provenance links prior version');
        $this->assert($rec->newVersionId === $v2->id, 'Scenario 29: Revision provenance links new version');
        $this->assert($rec->reviewCycleId === null, 'Scenario 29: Revision provenance has null review_cycle_id when not supplied');
        $this->assert($rec->submittedByUserId === $this->fixtureUserId, 'Scenario 29: Revision provenance stamps submitted_by_user_id');
        $this->assert($rec->approvedByUserId === null, 'Scenario 29: Revision provenance initially has null approved_by_user_id');

        // I. Successful creation WITH valid completed REVISION_REQUIRED cycle
        $planWithCycle = $this->planService->createPlan($this->fixtureEntity1Id, 2065, $this->fixtureUserId);
        $this->trackedPlans[] = $planWithCycle->id;
        $v1_cycle = $this->planService->createInitialVersion($planWithCycle->id, $this->fixtureUserId, '1.0');
        $this->trackedVersions[] = $v1_cycle->id;
        $this->planService->addPlanItems($planWithCycle->id, $v1_cycle->id, $items, $this->fixtureUserId);
        $this->planService->setCurrentVersion($planWithCycle->id, $v1_cycle->id, $this->fixtureUserId);
        $this->planRepo->updateStatus($planWithCycle->id, PlanStatus::APPROVED->value, $this->fixtureApproverId);
        $this->versionRepo->updateStatus($v1_cycle->id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));

        $cycleQ2 = $this->cycleService->initiateReviewCycle($planWithCycle->id, 2065, 'Q2', $this->fixtureUserId, $v1_cycle->id);
        $this->trackedCycles[] = $cycleQ2->id;
        $this->cycleService->recordReviewOutcome(
            $cycleQ2->id,
            ReviewOutcome::REVISION_REQUIRED->value,
            'Mandated revision for additional equipment in second half.',
            $this->fixtureUserId
        );

        $v2_cycle = $this->versionService->createPlanRevision(
            $planWithCycle->id,
            $cycleQ2->id,
            'Revision responding to Q2 review recommendations and notes',
            $validItems,
            $this->fixtureUserId
        );
        $this->trackedVersions[] = $v2_cycle->id;

        $recWithCycle = $this->revisionRepo->findByNewVersionId($v2_cycle->id);
        $this->assert($recWithCycle !== null, 'Scenario 29: Revision record retrieved by new version ID');
        $this->trackedRevisions[] = $recWithCycle->id;
        $this->assert($recWithCycle->reviewCycleId === $cycleQ2->id, 'Scenario 29: Provenance correctly links completed review cycle ID');

        // J. Next numerical version from 2.0 -> 3.0
        // Update $v2 status to APPROVED and update plan current_version_id to $v2->id
        $this->versionRepo->updateStatus($v2->id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));
        $this->db->exec("UPDATE `procurement_plans` SET `current_version_id` = {$v2->id} WHERE `id` = {$plan->id}");

        $v3 = $this->versionService->createPlanRevision(
            $plan->id,
            null,
            'Staging third revision to test 2.0 -> 3.0 incrementation',
            $validItems,
            $this->fixtureUserId
        );
        $this->trackedVersions[] = $v3->id;
        $this->assert($v3->versionNumber === '3.0', 'Scenario 29: Version number numerically incremented from 2.0 to 3.0');
        $rec3 = $this->revisionRepo->findByNewVersionId($v3->id);
        if ($rec3) {
            $this->trackedRevisions[] = $rec3->id;
        }

        // Restore pointer
        $this->db->exec("UPDATE `procurement_plans` SET `current_version_id` = {$v1->id} WHERE `id` = {$plan->id}");
    }

    /**
     * Scenario 30: PlanVersionService approvePlanRevision edge cases
     */
    private function testScenario30_ApprovePlanRevisionEdgeCases(): void
    {
        // Setup dedicated plan for 2064 with approved baseline v1
        $plan = $this->planService->createPlan($this->fixtureEntity1Id, 2064, $this->fixtureUserId);
        $this->trackedPlans[] = $plan->id;

        $v1 = $this->planService->createInitialVersion($plan->id, $this->fixtureUserId, '1.0');
        $this->trackedVersions[] = $v1->id;

        $items = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Baseline Laptop',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 1,
                'estimated_unit_cost' => 2000.00,
                'target_quarter' => 'Q1',
                'funding_source' => 'GOG',
            ]
        ];
        $this->planService->addPlanItems($plan->id, $v1->id, $items, $this->fixtureUserId);
        $this->planService->setCurrentVersion($plan->id, $v1->id, $this->fixtureUserId);
        $this->planRepo->updateStatus($plan->id, PlanStatus::APPROVED->value, $this->fixtureApproverId);
        $this->versionRepo->updateStatus($v1->id, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, date('Y-m-d H:i:s'));

        // Stage revision v2
        $revisedItems = [
            [
                'standard_item_id' => $this->fixtureStandardItem1Id,
                'item_description' => 'Upgraded Laptop',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 2,
                'estimated_unit_cost' => 2500.00,
                'target_quarter' => 'Q2',
                'funding_source' => 'GOG',
            ]
        ];
        $v2 = $this->versionService->createPlanRevision(
            $plan->id,
            null,
            'Comprehensive upgrade justification exceeding ten characters',
            $revisedItems,
            $this->fixtureUserId
        );
        $this->trackedVersions[] = $v2->id;

        $revRecord = $this->revisionRepo->findByNewVersionId($v2->id);
        $this->assert($revRecord !== null, 'Scenario 30: Staged revision record found');
        $this->trackedRevisions[] = $revRecord->id;

        // 1. Reject approver user ID <= 0
        $caughtUser = false;
        try {
            $this->versionService->approvePlanRevision($revRecord->id, 0);
        } catch (ValidationException $e) {
            $caughtUser = true;
            $this->assert(str_contains($e->getMessage(), 'approver user ID is required'), 'Scenario 30: Error indicates valid approver ID required');
        }
        $this->assert($caughtUser === true, 'Scenario 30: Rejects approver user ID <= 0');

        // 2. Reject non-existent revision record ID
        $caughtMissingRec = false;
        try {
            $this->versionService->approvePlanRevision(999999, $this->fixtureApproverId);
        } catch (ValidationException $e) {
            $caughtMissingRec = true;
            $this->assert(str_contains($e->getMessage(), 'not found'), 'Scenario 30: Error indicates revision record not found');
        }
        $this->assert($caughtMissingRec === true, 'Scenario 30: Rejects non-existent revision record ID');

        // 3. Reject approval when new version is not DRAFT
        $this->versionRepo->updateStatus($v2->id, PlanVersionStatus::REJECTED->value);
        $caughtNotDraft = false;
        try {
            $this->versionService->approvePlanRevision($revRecord->id, $this->fixtureApproverId);
        } catch (ValidationException $e) {
            $caughtNotDraft = true;
            $this->assert(str_contains($e->getMessage(), 'must be DRAFT'), 'Scenario 30: Error requires new version to be DRAFT');
        }
        $this->assert($caughtNotDraft === true, 'Scenario 30: Rejects approval when new version is not DRAFT');
        // Restore to DRAFT
        $this->versionRepo->updateStatus($v2->id, PlanVersionStatus::DRAFT->value);

        // 4. Reject approval when prior version is not current active version of plan
        $this->db->exec("UPDATE `procurement_plans` SET `current_version_id` = NULL WHERE `id` = {$plan->id}");
        $caughtPriorMismatch = false;
        try {
            $this->versionService->approvePlanRevision($revRecord->id, $this->fixtureApproverId);
        } catch (ValidationException $e) {
            $caughtPriorMismatch = true;
            $this->assert(str_contains($e->getMessage(), 'not the current active version'), 'Scenario 30: Error requires prior version to be current active version');
        }
        $this->assert($caughtPriorMismatch === true, 'Scenario 30: Rejects approval when prior version does not match plan current_version_id');
        // Restore plan current_version_id
        $this->db->exec("UPDATE `procurement_plans` SET `current_version_id` = {$v1->id} WHERE `id` = {$plan->id}");

        // 5. Reject approval when prior version is not in APPROVED status
        $this->versionRepo->updateStatus($v1->id, PlanVersionStatus::DRAFT->value);
        $caughtPriorNotApproved = false;
        try {
            $this->versionService->approvePlanRevision($revRecord->id, $this->fixtureApproverId);
        } catch (ValidationException $e) {
            $caughtPriorNotApproved = true;
            $this->assert(str_contains($e->getMessage(), 'must be in APPROVED status'), 'Scenario 30: Error requires prior version to be in APPROVED status');
        }
        $this->assert($caughtPriorNotApproved === true, 'Scenario 30: Rejects approval when prior version is not APPROVED');
        // Restore prior version to APPROVED
        $this->versionRepo->updateStatus($v1->id, PlanVersionStatus::APPROVED->value);

        // 6. Successful atomic approval
        $approvedV2 = $this->versionService->approvePlanRevision($revRecord->id, $this->fixtureApproverId);

        $this->assert($approvedV2->status === PlanVersionStatus::APPROVED, 'Scenario 30: New version transitioned to APPROVED');
        $this->assert($approvedV2->approvedByUserId === $this->fixtureApproverId, 'Scenario 30: New version stamped with approver ID');
        $this->assert($approvedV2->approvalDate !== null, 'Scenario 30: New version stamped with approval date');

        // Prior version superseded
        $priorCheck = $this->versionRepo->findById($v1->id);
        $this->assert($priorCheck->status === PlanVersionStatus::SUPERSEDED, 'Scenario 30: Prior version transitioned to SUPERSEDED');

        // Plan updated
        $planCheck = $this->planRepo->findById($plan->id);
        $this->assert($planCheck->currentVersionId === $v2->id, 'Scenario 30: Plan current_version_id updated to new version');
        $this->assert($planCheck->status === PlanStatus::APPROVED, 'Scenario 30: Plan status is APPROVED');

        // Revision record approved
        $revCheck = $this->revisionRepo->findById($revRecord->id);
        $this->assert($revCheck->approvedByUserId === $this->fixtureApproverId, 'Scenario 30: Revision record stamped with approver ID');
        $this->assert($revCheck->approvedAt !== null, 'Scenario 30: Revision record stamped with approval timestamp');

        // 7. Reject duplicate approval of already approved revision
        $caughtAlreadyApproved = false;
        try {
            $this->versionService->approvePlanRevision($revRecord->id, $this->fixtureApproverId);
        } catch (ValidationException $e) {
            $caughtAlreadyApproved = true;
            $this->assert(str_contains($e->getMessage(), 'already been approved'), 'Scenario 30: Error indicates revision already approved');
        }
        $this->assert($caughtAlreadyApproved === true, 'Scenario 30: Rejects duplicate approval of approved revision');

        // 8. Conditional update failure / concurrency guard simulation
        $v3 = $this->versionService->createPlanRevision(
            $plan->id,
            null,
            'Another valid justification for concurrency testing',
            $revisedItems,
            $this->fixtureUserId
        );
        $this->trackedVersions[] = $v3->id;
        $rev3 = $this->revisionRepo->findByNewVersionId($v3->id);
        $this->trackedRevisions[] = $rev3->id;

        // Mock revision repo where markApproved returns false (simulating concurrent worker)
        $mockFailingRevisionRepo = new class($this->revisionRepo) implements PlanRevisionRecordRepositoryInterface {
            private PlanRevisionRecordRepositoryInterface $inner;
            public function __construct(PlanRevisionRecordRepositoryInterface $inner) { $this->inner = $inner; }
            public function findById(int $id): ?PlanRevisionRecordDTO { return $this->inner->findById($id); }
            public function findByPlanId(int $planId): array { return $this->inner->findByPlanId($planId); }
            public function findByNewVersionId(int $newVersionId): ?PlanRevisionRecordDTO { return $this->inner->findByNewVersionId($newVersionId); }
            public function create(array $data): int { return $this->inner->create($data); }
            public function markApproved(int $id, int $approvedByUserId, ?string $approvedAt = null): bool {
                return false;
            }
        };

        $concurrencyService = new PlanVersionService(
            $this->db,
            $this->planRepo,
            $this->versionRepo,
            $this->itemRepo,
            $mockFailingRevisionRepo,
            $this->cycleRepo
        );

        $caughtConcurrency = false;
        try {
            $concurrencyService->approvePlanRevision($rev3->id, $this->fixtureApproverId);
        } catch (DatabaseException $e) {
            $caughtConcurrency = true;
            $this->assert(str_contains($e->getMessage(), 'concurrent approval detected') || str_contains($e->getMessage(), 'Failed to mark'), 'Scenario 30: DatabaseException thrown on conditional update failure');
        }
        $this->assert($caughtConcurrency === true, 'Scenario 30: Throws DatabaseException when conditional row count is 0');

        // Verify state remains rolled back: v3 remains DRAFT, plan pointer remains v2
        $v3Check = $this->versionRepo->findById($v3->id);
        $this->assert($v3Check->status === PlanVersionStatus::DRAFT, 'Scenario 30: Version 3.0 remains DRAFT after failed conditional update');
        $planCheckAfterRollback = $this->planRepo->findById($plan->id);
        $this->assert($planCheckAfterRollback->currentVersionId === $v2->id, 'Scenario 30: Plan current_version_id remains pointing to version 2.0');
    }
}

// Execute tests
PlanningServiceTest::main();
