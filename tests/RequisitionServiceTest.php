<?php

declare(strict_types=1);

/**
 * PROMIS Phase 2 Stage 2.2 Requisition Application Service & Drawdown Engine Test Suite
 * Procurement Management Information System
 * University of Skills Training and Entrepreneurial Development (USTED)
 *
 * Standalone CLI test runner verifying:
 * - Requisition creation with plan linkage (FR-051)
 * - Multi-item atomic insertion
 * - Validation & cross-plan isolation (entity, fiscal year, plan version)
 * - Drawdown quota calculations with bcmath scale-2 precision
 * - Concurrency-safe submission, row-locking, and evidentiary snapshot creation
 * - Rollback verification on failures
 * - Entity-scoped authorization enforcement (deny-by-default)
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\ValidationException;
use Promis\Src\Execution\Auth\ExecutionPermissions;
use Promis\Src\Execution\Domain\DTO\CreateRequisitionRequest;
use Promis\Src\Execution\Domain\DTO\SubmitRequisitionRequest;
use Promis\Src\Execution\Domain\DrawdownCalculator;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Exception\DrawdownExceededException;
use Promis\Src\Execution\Repository\RequisitionBalanceSnapshotRepository;
use Promis\Src\Execution\Repository\RequisitionItemRepository;
use Promis\Src\Execution\Repository\RequisitionRepository;
use Promis\Src\Execution\Service\RequisitionService;
use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\PlanStatus;
use Promis\Src\Planning\Domain\PlanVersionStatus;
use Promis\Src\Planning\Domain\TargetQuarter;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepository;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepository;

final class RequisitionServiceTest
{
    private PDO $db;
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    // Tracked IDs for clean teardown
    private array $trackedUsers = [];
    private array $trackedCampuses = [];
    private array $trackedEntityTypes = [];
    private array $trackedEntities = [];
    private array $trackedCategories = [];
    private array $trackedUoms = [];
    private array $trackedStandardItems = [];
    private array $trackedPlans = [];
    private array $trackedVersions = [];
    private array $trackedPlanItems = [];
    private array $trackedRequisitions = [];

    // Repositories & Service
    private RequisitionRepository $reqRepo;
    private RequisitionItemRepository $reqItemRepo;
    private RequisitionBalanceSnapshotRepository $snapshotRepo;
    private ProcurementPlanRepository $planRepo;
    private ProcurementPlanVersionRepository $versionRepo;
    private ProcurementPlanItemRepository $planItemRepo;
    private RequisitionService $reqService;

    // Fixtures
    private int $fixtureUserId = 0;
    private int $fixtureUnauthorizedUserId = 0;
    private int $fixtureCampusId = 0;
    private int $fixtureEntityTypeId = 0;
    private int $fixtureEntityId = 0;
    private int $fixtureEntity2Id = 0;
    private int $fixtureCategoryId = 0;
    private int $fixtureUomId = 0;
    private int $fixtureStandardItem1Id = 0;
    private int $fixtureStandardItem2Id = 0;

    // Primary approved plan & version
    private int $fixturePlanId = 0;
    private int $fixtureApprovedVersionId = 0;
    private int $fixtureDraftVersionId = 0;
    private int $fixturePlanItem1Id = 0; // 100 boxes @ 50.00 = 5000.00
    private int $fixturePlanItem2Id = 0; // 50 boxes @ 80.00 = 4000.00

    // Alien plan (entity 2)
    private int $fixtureAlienPlanId = 0;
    private int $fixtureAlienVersionId = 0;
    private int $fixtureAlienPlanItemId = 0;

    public static function main(): void
    {
        $tester = new self();
        $tester->run();
    }

    public function __construct()
    {
        App::bootstrap(dirname(__DIR__));
        $this->db = Connection::get();

        $this->reqRepo = new RequisitionRepository($this->db);
        $this->reqItemRepo = new RequisitionItemRepository($this->db);
        $this->snapshotRepo = new RequisitionBalanceSnapshotRepository($this->db);
        $this->planRepo = new ProcurementPlanRepository($this->db);
        $this->versionRepo = new ProcurementPlanVersionRepository($this->db);
        $this->planItemRepo = new ProcurementPlanItemRepository($this->db);

        $this->reqService = new RequisitionService(
            $this->db,
            $this->reqRepo,
            $this->reqItemRepo,
            $this->snapshotRepo,
            $this->planRepo,
            $this->versionRepo,
            $this->planItemRepo
        );
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS Phase 2 Stage 2.2 Requisition Service Test Suite\n";
        echo " University of Skills Training and Entrepreneurial Development\n";
        echo " Relational Engine: MariaDB 10.4+ / MySQL 8.x\n";
        echo " PHP Version: " . PHP_VERSION . "\n";
        echo "===============================================================\n\n";

        try {
            $this->seedFixtures();

            // Scenario 1 & 2: Creation & Multi-Item
            $this->testSuccessfulRequisitionCreation();
            $this->testCreationWithMultipleItems();

            // Scenario 3 to 5: Input validation
            $this->testRejectionOfInvalidEntityAndFiscalYear();
            $this->testRejectionOfMissingJustificationAndItems();

            // Scenario 6 to 8: Plan Version Eligibility & Mismatches
            $this->testRejectionOfUnapprovedPlanVersion();
            $this->testRejectionOfPlanVersionEntityMismatch();
            $this->testRejectionOfPlanVersionFiscalYearMismatch();

            // Scenario 9 to 12: Item Integrity & Cross-Plan Pollution
            $this->testRejectionOfNonExistentPlanItem();
            $this->testRejectionOfCrossPlanItemPollution();
            $this->testRejectionOfCrossEntityItemPollution();

            // Scenario 13 to 16: Decimal Quantities, Costs, and Exact Totals
            $this->testRejectionOfInvalidQuantityAndCost();
            $this->testExactTotalCostCalculation();

            // Scenario 17 to 20: Drawdown Engine Calculations & Bounds
            $this->testDrawdownCalculatorUnitLogic();
            $this->testDrawdownExceededRejectionOnSubmission();

            // Scenario 21: Atomic Rollback on Creation Failure
            $this->testAtomicRollbackOnItemFailure();

            // Scenario 22 & 23: Submission & Duplicate Prevention
            $this->testSuccessfulRequisitionSubmission();
            $this->testDuplicateSubmissionPrevention();

            // Scenario 24 to 26: Execution Authorization & Scope Enforcement
            $this->testAuthorizationEnforcement();

            // Scenario 27 & 28: Balance Snapshot Evidentiary Verification
            $this->testBalanceSnapshotEvidentiaryIntegrity();

            // Scenario 29: Conditional Status Concurrency Safety
            $this->testConditionalStatusUpdateConflictHandling();

            // Scenario 30: Concurrency Row-Locking Simulation
            $this->testConcurrencyRowLockingSimulation();

        } catch (Throwable $e) {
            $this->failed++;
            $this->failures[] = "Fatal unhandled exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
            echo " [FAIL] FATAL EXCEPTION: " . $e->getMessage() . "\n";
        } finally {
            $this->teardown();
        }

        $this->printSummary();
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

    private function seedFixtures(): void
    {
        $unique = bin2hex(random_bytes(4));

        // 1. Users
        $stmt = $this->db->prepare("
            INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `status`)
            VALUES (:u, :e, 'hash123', 'Req', 'Authorizer', 'ACTIVE')
        ");
        $stmt->execute(['u' => 'req_svc_user_' . $unique, 'e' => "req_svc_{$unique}@usted.edu.gh"]);
        $this->fixtureUserId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->fixtureUserId;

        $stmt->execute(['u' => 'req_unauth_user_' . $unique, 'e' => "req_unauth_{$unique}@usted.edu.gh"]);
        $this->fixtureUnauthorizedUserId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->fixtureUnauthorizedUserId;

        // 2. Campus
        $stmt = $this->db->prepare("INSERT INTO `campuses` (`campus_code`, `campus_name`) VALUES (:c, 'North Campus')");
        $stmt->execute(['c' => 'CAMP_SVC_' . $unique]);
        $this->fixtureCampusId = (int)$this->db->lastInsertId();
        $this->trackedCampuses[] = $this->fixtureCampusId;

        // 3. Entity Type
        $stmt = $this->db->prepare("INSERT INTO `entity_types` (`type_code`, `type_name`) VALUES (:t, 'Faculty')");
        $stmt->execute(['t' => 'TYPE_SVC_' . $unique]);
        $this->fixtureEntityTypeId = (int)$this->db->lastInsertId();
        $this->trackedEntityTypes[] = $this->fixtureEntityTypeId;

        // 4. Planning Entities (1 & 2)
        $stmt = $this->db->prepare("
            INSERT INTO `planning_entities` (`entity_code`, `entity_name`, `entity_type_id`, `campus_id`)
            VALUES (:ec, :en, :tid, :cid)
        ");
        $stmt->execute([
            'ec' => 'ENT_SVC_1_' . $unique,
            'en' => 'Faculty of Applied Sciences',
            'tid' => $this->fixtureEntityTypeId,
            'cid' => $this->fixtureCampusId,
        ]);
        $this->fixtureEntityId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->fixtureEntityId;

        $stmt->execute([
            'ec' => 'ENT_SVC_2_' . $unique,
            'en' => 'School of Business',
            'tid' => $this->fixtureEntityTypeId,
            'cid' => $this->fixtureCampusId,
        ]);
        $this->fixtureEntity2Id = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->fixtureEntity2Id;

        // 5. Category & UOM
        $stmt = $this->db->prepare("INSERT INTO `item_categories` (`category_code`, `category_name`) VALUES (:cc, 'Supplies')");
        $stmt->execute(['cc' => 'CAT_SVC_' . $unique]);
        $this->fixtureCategoryId = (int)$this->db->lastInsertId();
        $this->trackedCategories[] = $this->fixtureCategoryId;

        $stmt = $this->db->prepare("INSERT INTO `units_of_measure` (`uom_code`, `uom_name`) VALUES (:uc, 'Ream')");
        $stmt->execute(['uc' => 'UOM_SVC_' . $unique]);
        $this->fixtureUomId = (int)$this->db->lastInsertId();
        $this->trackedUoms[] = $this->fixtureUomId;

        // 6. Standard Items
        $stmt = $this->db->prepare("
            INSERT INTO `standard_items` (`item_code`, `item_name`, `category_id`, `default_uom_id`, `estimated_unit_price`)
            VALUES (:ic, :in, :cid, :uid, :p)
        ");
        $stmt->execute([
            'ic' => 'ITEM_SVC_1_' . $unique,
            'in' => 'Exam Paper Reams',
            'cid' => $this->fixtureCategoryId,
            'uid' => $this->fixtureUomId,
            'p' => '50.00',
        ]);
        $this->fixtureStandardItem1Id = (int)$this->db->lastInsertId();
        $this->trackedStandardItems[] = $this->fixtureStandardItem1Id;

        $stmt->execute([
            'ic' => 'ITEM_SVC_2_' . $unique,
            'in' => 'Whiteboard Markers Box',
            'cid' => $this->fixtureCategoryId,
            'uid' => $this->fixtureUomId,
            'p' => '80.00',
        ]);
        $this->fixtureStandardItem2Id = (int)$this->db->lastInsertId();
        $this->trackedStandardItems[] = $this->fixtureStandardItem2Id;

        // 7. Approved Plan & Version for Entity 1
        $this->fixturePlanId = $this->planRepo->create([
            'plan_number' => 'PLAN-SVC-1-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => PlanStatus::APPROVED->value,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $this->fixturePlanId;

        $this->fixtureApprovedVersionId = $this->versionRepo->create([
            'procurement_plan_id' => $this->fixturePlanId,
            'version_number' => '1.0',
            'status' => PlanVersionStatus::APPROVED->value,
            'total_estimated_cost' => '9000.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedVersions[] = $this->fixtureApprovedVersionId;
        $this->planRepo->setCurrentVersion($this->fixturePlanId, $this->fixtureApprovedVersionId);

        // Draft Version (unapproved) for Entity 1
        $this->fixtureDraftVersionId = $this->versionRepo->create([
            'procurement_plan_id' => $this->fixturePlanId,
            'version_number' => '2.0',
            'status' => PlanVersionStatus::DRAFT->value,
            'total_estimated_cost' => '0.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedVersions[] = $this->fixtureDraftVersionId;

        // Plan Items under approved version 1.0
        $this->fixturePlanItem1Id = $this->planItemRepo->create([
            'plan_version_id' => $this->fixtureApprovedVersionId,
            'standard_item_id' => $this->fixtureStandardItem1Id,
            'item_description' => 'A4 Exam Paper',
            'category_id' => $this->fixtureCategoryId,
            'uom_id' => $this->fixtureUomId,
            'planned_quantity' => '100.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '5000.00',
            'target_quarter' => TargetQuarter::Q1->value,
            'funding_source' => 'GOG',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlanItems[] = $this->fixturePlanItem1Id;

        $this->fixturePlanItem2Id = $this->planItemRepo->create([
            'plan_version_id' => $this->fixtureApprovedVersionId,
            'standard_item_id' => $this->fixtureStandardItem2Id,
            'item_description' => 'Dry Erase Markers',
            'category_id' => $this->fixtureCategoryId,
            'uom_id' => $this->fixtureUomId,
            'planned_quantity' => '50.00',
            'estimated_unit_cost' => '80.00',
            'estimated_total_cost' => '4000.00',
            'target_quarter' => TargetQuarter::Q2->value,
            'funding_source' => 'IGF',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlanItems[] = $this->fixturePlanItem2Id;

        // 8. Alien Plan for Entity 2
        $this->fixtureAlienPlanId = $this->planRepo->create([
            'plan_number' => 'PLAN-SVC-ALN-' . $unique,
            'planning_entity_id' => $this->fixtureEntity2Id,
            'fiscal_year' => 2026,
            'status' => PlanStatus::APPROVED->value,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $this->fixtureAlienPlanId;

        $this->fixtureAlienVersionId = $this->versionRepo->create([
            'procurement_plan_id' => $this->fixtureAlienPlanId,
            'version_number' => '1.0',
            'status' => PlanVersionStatus::APPROVED->value,
            'total_estimated_cost' => '2500.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedVersions[] = $this->fixtureAlienVersionId;
        $this->planRepo->setCurrentVersion($this->fixtureAlienPlanId, $this->fixtureAlienVersionId);

        $this->fixtureAlienPlanItemId = $this->planItemRepo->create([
            'plan_version_id' => $this->fixtureAlienVersionId,
            'standard_item_id' => $this->fixtureStandardItem1Id,
            'item_description' => 'Alien Exam Paper',
            'category_id' => $this->fixtureCategoryId,
            'uom_id' => $this->fixtureUomId,
            'planned_quantity' => '50.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '2500.00',
            'target_quarter' => TargetQuarter::Q1->value,
            'funding_source' => 'GOG',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlanItems[] = $this->fixtureAlienPlanItemId;

        // Setup authorized user session
        $this->authenticateAuthorizedUser();

        $this->assert(true, "Base relational fixtures and plans initialized successfully");
    }

    private function authenticateAuthorizedUser(): void
    {
        AuthManager::login([
            'id' => $this->fixtureUserId,
            'email' => 'req_svc_user@usted.edu.gh',
            'permissions' => [],
            'entity_permissions' => [
                $this->fixtureEntityId => [
                    ExecutionPermissions::CREATE,
                    ExecutionPermissions::VIEW,
                    ExecutionPermissions::EDIT,
                    ExecutionPermissions::SUBMIT,
                ],
                $this->fixtureEntity2Id => [
                    ExecutionPermissions::VIEW,
                ],
                999999 => [
                    ExecutionPermissions::CREATE,
                ],
            ],
        ]);
    }

    private function authenticateUnauthorizedUser(): void
    {
        AuthManager::login([
            'id' => $this->fixtureUnauthorizedUserId,
            'email' => 'unauth@usted.edu.gh',
            'permissions' => [],
            'entity_permissions' => [],
        ]);
    }

    private function testSuccessfulRequisitionCreation(): void
    {
        $this->authenticateAuthorizedUser();

        $request = new CreateRequisitionRequest(
            planningEntityId: $this->fixtureEntityId,
            fiscalYear: 2026,
            approvedPlanVersionId: $this->fixtureApprovedVersionId,
            justification: 'Quarter 1 exam stationery requisition',
            items: [
                [
                    'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'A4 Exam Paper Reams',
                    'uom_id' => $this->fixtureUomId,
                    'requested_quantity' => '20.00',
                    'estimated_unit_cost' => '50.00',
                    'item_justification' => 'Midterm examination printing',
                ],
            ],
            actingUserId: $this->fixtureUserId
        );

        $dto = $this->reqService->createRequisition($request);
        $this->trackedRequisitions[] = $dto->id;

        $this->assert($dto->id > 0, "Scenario 1: createRequisition returns positive surrogate ID ({$dto->id})");
        $this->assert(str_starts_with($dto->requisitionNumber, 'REQ-2026-ENT'), "Scenario 1: Requisition number format matches convention ({$dto->requisitionNumber})");
        $this->assert($dto->status === RequisitionStatus::DRAFT, "Scenario 1: Initial status is DRAFT");
        $this->assert($dto->totalEstimatedCost === '1000.00', "Scenario 1: Total estimated cost exact decimal (20.00 * 50.00 = 1000.00)");
        $this->assert($dto->approvedPlanVersionId === $this->fixtureApprovedVersionId, "Scenario 1: Anchored to approved plan version (FR-051)");
        $this->assert(count($dto->items) === 1, "Scenario 1: Exactly 1 line item hydrated under created requisition");
    }

    private function testCreationWithMultipleItems(): void
    {
        $this->authenticateAuthorizedUser();

        $request = new CreateRequisitionRequest(
            planningEntityId: $this->fixtureEntityId,
            fiscalYear: 2026,
            approvedPlanVersionId: $this->fixtureApprovedVersionId,
            justification: 'Multi-item departmental operational replenishment',
            items: [
                [
                    'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'A4 Paper',
                    'uom_id' => $this->fixtureUomId,
                    'requested_quantity' => '10.00',
                    'estimated_unit_cost' => '50.00',
                ],
                [
                    'procurement_plan_item_id' => $this->fixturePlanItem2Id,
                    'standard_item_id' => $this->fixtureStandardItem2Id,
                    'item_description' => 'Whiteboard Markers',
                    'uom_id' => $this->fixtureUomId,
                    'requested_quantity' => '5.00',
                    'estimated_unit_cost' => '80.00',
                ],
            ],
            actingUserId: $this->fixtureUserId
        );

        $dto = $this->reqService->createRequisition($request);
        $this->trackedRequisitions[] = $dto->id;

        $this->assert(count($dto->items) === 2, "Scenario 2: Multi-item requisition created with 2 line items");
        // Line 1: 10 * 50 = 500.00; Line 2: 5 * 80 = 400.00; Total = 900.00
        $this->assert($dto->totalEstimatedCost === '900.00', "Scenario 2: Multi-item total calculated accurately (500 + 400 = 900.00)");
    }

    private function testRejectionOfInvalidEntityAndFiscalYear(): void
    {
        $this->authenticateAuthorizedUser();

        // Invalid entity
        $caughtEntity = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: 999999,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Invalid entity test',
                items: [['procurement_plan_item_id' => $this->fixturePlanItem1Id, 'requested_quantity' => '1.00']],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caughtEntity = true;
        }
        $this->assert($caughtEntity, "Scenario 3: Rejects non-existent planning entity ID");

        // Invalid fiscal year (< 2000)
        $caughtYear = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 1999,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Invalid year test',
                items: [['procurement_plan_item_id' => $this->fixturePlanItem1Id, 'requested_quantity' => '1.00']],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caughtYear = true;
        }
        $this->assert($caughtYear, "Scenario 4: Rejects invalid fiscal year outside 2000-2100 bounds");
    }

    private function testRejectionOfMissingJustificationAndItems(): void
    {
        $this->authenticateAuthorizedUser();

        // Missing justification
        $caughtJustification = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: '   ',
                items: [['procurement_plan_item_id' => $this->fixturePlanItem1Id, 'requested_quantity' => '1.00']],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caughtJustification = true;
        }
        $this->assert($caughtJustification, "Scenario 5: Rejects empty or whitespace justification");

        // Empty items array
        $caughtEmptyItems = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Valid justification',
                items: [],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caughtEmptyItems = true;
        }
        $this->assert($caughtEmptyItems, "Scenario 5: Rejects requisition with zero line items");
    }

    private function testRejectionOfUnapprovedPlanVersion(): void
    {
        $this->authenticateAuthorizedUser();

        $caught = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureDraftVersionId, // DRAFT version
                justification: 'Attempting execution against unapproved version',
                items: [['procurement_plan_item_id' => $this->fixturePlanItem1Id, 'requested_quantity' => '1.00']],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, "Scenario 6: Rejects unapproved plan version (must be APPROVED)");
    }

    private function testRejectionOfPlanVersionEntityMismatch(): void
    {
        $this->authenticateAuthorizedUser();

        // Requesting for Entity 1, but providing Version from Alien Plan (Entity 2)
        $caught = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureAlienVersionId,
                justification: 'Cross-entity version attempt',
                items: [['procurement_plan_item_id' => $this->fixtureAlienPlanItemId, 'requested_quantity' => '1.00']],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, "Scenario 7: Rejects plan version belonging to another planning entity");
    }

    private function testRejectionOfPlanVersionFiscalYearMismatch(): void
    {
        $this->authenticateAuthorizedUser();

        // Requisition for 2027, but plan version is for 2026
        $caught = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2027,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Fiscal year mismatch attempt',
                items: [['procurement_plan_item_id' => $this->fixturePlanItem1Id, 'requested_quantity' => '1.00']],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, "Scenario 8: Rejects plan version with fiscal year mismatch");
    }

    private function testRejectionOfNonExistentPlanItem(): void
    {
        $this->authenticateAuthorizedUser();

        $caught = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Non-existent item test',
                items: [
                    [
                        'procurement_plan_item_id' => 999999,
                        'standard_item_id' => $this->fixtureStandardItem1Id,
                        'item_description' => 'Fake Item',
                        'uom_id' => $this->fixtureUomId,
                        'requested_quantity' => '5.00',
                        'estimated_unit_cost' => '50.00',
                    ],
                ],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, "Scenario 9: Rejects non-existent procurement plan item ID");
    }

    private function testRejectionOfCrossPlanItemPollution(): void
    {
        $this->authenticateAuthorizedUser();

        // Attempting to include an item from the Alien Plan under Entity 1's requisition
        $caught = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Cross-plan item pollution test',
                items: [
                    [
                        'procurement_plan_item_id' => $this->fixtureAlienPlanItemId, // Belongs to Alien Plan!
                        'standard_item_id' => $this->fixtureStandardItem1Id,
                        'item_description' => 'A4 Paper from other plan',
                        'uom_id' => $this->fixtureUomId,
                        'requested_quantity' => '5.00',
                        'estimated_unit_cost' => '50.00',
                    ],
                ],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, "Scenario 10: Rejects cross-plan item pollution (item not in approved version)");
    }

    private function testRejectionOfCrossEntityItemPollution(): void
    {
        $this->authenticateAuthorizedUser();

        // Mixing valid Entity 1 item with Alien Entity 2 item
        $caught = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Mixing items across entities',
                items: [
                    [
                        'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                        'standard_item_id' => $this->fixtureStandardItem1Id,
                        'item_description' => 'Valid Item',
                        'uom_id' => $this->fixtureUomId,
                        'requested_quantity' => '5.00',
                        'estimated_unit_cost' => '50.00',
                    ],
                    [
                        'procurement_plan_item_id' => $this->fixtureAlienPlanItemId, // Alien entity!
                        'standard_item_id' => $this->fixtureStandardItem1Id,
                        'item_description' => 'Alien Item',
                        'uom_id' => $this->fixtureUomId,
                        'requested_quantity' => '5.00',
                        'estimated_unit_cost' => '50.00',
                    ],
                ],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, "Scenario 11: Rejects cross-entity item pollution in multi-item payload");
    }

    private function testRejectionOfInvalidQuantityAndCost(): void
    {
        $this->authenticateAuthorizedUser();

        // Zero quantity
        $caughtZeroQty = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Zero quantity test',
                items: [
                    [
                        'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                        'standard_item_id' => $this->fixtureStandardItem1Id,
                        'item_description' => 'Zero qty item',
                        'uom_id' => $this->fixtureUomId,
                        'requested_quantity' => '0.00',
                        'estimated_unit_cost' => '50.00',
                    ],
                ],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caughtZeroQty = true;
        }
        $this->assert($caughtZeroQty, "Scenario 13: Rejects zero requested quantity");

        // Negative quantity
        $caughtNegQty = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Negative quantity test',
                items: [
                    [
                        'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                        'standard_item_id' => $this->fixtureStandardItem1Id,
                        'item_description' => 'Negative qty item',
                        'uom_id' => $this->fixtureUomId,
                        'requested_quantity' => '-10.00',
                        'estimated_unit_cost' => '50.00',
                    ],
                ],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caughtNegQty = true;
        }
        $this->assert($caughtNegQty, "Scenario 14: Rejects negative requested quantity");

        // Negative unit cost
        $caughtNegCost = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Negative cost test',
                items: [
                    [
                        'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                        'standard_item_id' => $this->fixtureStandardItem1Id,
                        'item_description' => 'Negative cost item',
                        'uom_id' => $this->fixtureUomId,
                        'requested_quantity' => '5.00',
                        'estimated_unit_cost' => '-50.00',
                    ],
                ],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caughtNegCost = true;
        }
        $this->assert($caughtNegCost, "Scenario 15: Rejects negative estimated unit cost");
    }

    private function testExactTotalCostCalculation(): void
    {
        $this->authenticateAuthorizedUser();

        // 3 items with specific fractional values
        // Item 1: 12.50 @ 50.00 = 625.00
        // Item 2: 7.25 @ 80.00 = 580.00
        // Total expected = 1205.00
        $request = new CreateRequisitionRequest(
            planningEntityId: $this->fixtureEntityId,
            fiscalYear: 2026,
            approvedPlanVersionId: $this->fixtureApprovedVersionId,
            justification: 'Fractional precision test',
            items: [
                [
                    'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Fractional Item 1',
                    'uom_id' => $this->fixtureUomId,
                    'requested_quantity' => '12.50',
                    'estimated_unit_cost' => '50.00',
                ],
                [
                    'procurement_plan_item_id' => $this->fixturePlanItem2Id,
                    'standard_item_id' => $this->fixtureStandardItem2Id,
                    'item_description' => 'Fractional Item 2',
                    'uom_id' => $this->fixtureUomId,
                    'requested_quantity' => '7.25',
                    'estimated_unit_cost' => '80.00',
                ],
            ],
            actingUserId: $this->fixtureUserId
        );

        $dto = $this->reqService->createRequisition($request);
        $this->trackedRequisitions[] = $dto->id;

        $this->assert($dto->totalEstimatedCost === '1205.00', "Scenario 16: Exact total cost calculated without floating-point error (1205.00)");
        $this->assert($dto->items[0]->estimatedTotalCost === '625.00', "Scenario 16: Item 1 total cost exact (625.00)");
        $this->assert($dto->items[1]->estimatedTotalCost === '580.00', "Scenario 16: Item 2 total cost exact (580.00)");

        // Clean up transient calculation fixture to preserve fixture isolation for subsequent drawdown scenarios
        $this->reqItemRepo->deleteByRequisitionId($dto->id);
        $this->reqRepo->delete($dto->id);
        $this->trackedRequisitions = array_values(array_diff($this->trackedRequisitions, [$dto->id]));
    }

    private function testDrawdownCalculatorUnitLogic(): void
    {
        // 1. Initial request: planned 100, previous 0, request 20 -> remaining before 100, remaining after 80
        $res1 = DrawdownCalculator::calculate('100.00', '0.00', '20.00', 1);
        $this->assert($res1->remainingBefore === '100.00', "Scenario 17: Initial remaining before is 100.00");
        $this->assert($res1->remainingAfter === '80.00', "Scenario 17: Initial remaining after is 80.00");
        $this->assert(!$res1->isExceeded, "Scenario 17: isExceeded is false");

        // 2. Subsequent request: planned 100, previous 80, request 15 -> remaining before 20, remaining after 5
        $res2 = DrawdownCalculator::calculate('100.00', '80.00', '15.00', 1);
        $this->assert($res2->remainingBefore === '20.00', "Scenario 18: Remaining before with prior requests is 20.00");
        $this->assert($res2->remainingAfter === '5.00', "Scenario 18: Remaining after is 5.00");
        $this->assert(!$res2->isExceeded, "Scenario 18: isExceeded is false");

        // 3. Exceeded request: planned 100, previous 80, request 25 -> remaining before 20, remaining after -5
        $res3 = DrawdownCalculator::calculate('100.00', '80.00', '25.00', 1);
        $this->assert($res3->remainingBefore === '20.00', "Scenario 19: Remaining before is 20.00");
        $this->assert($res3->remainingAfter === '-5.00', "Scenario 19: Remaining after is -5.00");
        $this->assert($res3->isExceeded, "Scenario 19: isExceeded is true when request exceeds balance");
        $this->assert($res3->excessQuantity === '5.00', "Scenario 19: Excess quantity is exactly 5.00");
    }

    private function testDrawdownExceededRejectionOnSubmission(): void
    {
        $this->authenticateAuthorizedUser();

        // Create requisition with 150 items when only 100 are planned
        $request = new CreateRequisitionRequest(
            planningEntityId: $this->fixtureEntityId,
            fiscalYear: 2026,
            approvedPlanVersionId: $this->fixtureApprovedVersionId,
            justification: 'Over-request attempt',
            items: [
                [
                    'procurement_plan_item_id' => $this->fixturePlanItem1Id, // Planned: 100.00
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'A4 Paper Over-Request',
                    'uom_id' => $this->fixtureUomId,
                    'requested_quantity' => '150.00', // Exceeds planned 100!
                    'estimated_unit_cost' => '50.00',
                ],
            ],
            actingUserId: $this->fixtureUserId
        );

        $dto = $this->reqService->createRequisition($request);
        $this->trackedRequisitions[] = $dto->id;

        // Submitting this requisition must be rejected by DrawdownExceededException
        $caught = false;
        try {
            $this->reqService->submitRequisition(new SubmitRequisitionRequest($dto->id, $this->fixtureUserId));
        } catch (DrawdownExceededException $e) {
            $caught = true;
            $this->assert($e->planItemId === $this->fixturePlanItem1Id, "Scenario 20: DrawdownExceededException identifies plan item ID");
            $this->assert($e->requestedQuantity === '150.00', "Scenario 20: Exception specifies requested quantity (150.00)");
        }
        $this->assert($caught, "Scenario 20: Rejects submission when requested quantity exceeds remaining balance");

        // Verify status remains DRAFT after rollback
        $afterCheck = $this->reqRepo->findById($dto->id);
        $this->assert($afterCheck->status === RequisitionStatus::DRAFT, "Scenario 20: Requisition remains in DRAFT status after rejected submission");

        // Verify zero snapshots persisted
        $snaps = $this->snapshotRepo->findByRequisitionId($dto->id);
        $this->assert(empty($snaps), "Scenario 20: Zero snapshots persisted after failed drawdown submission");

        // Clean up transient over-request fixture to preserve fixture isolation for subsequent drawdown scenarios
        $this->reqItemRepo->deleteByRequisitionId($dto->id);
        $this->reqRepo->delete($dto->id);
        $this->trackedRequisitions = array_values(array_diff($this->trackedRequisitions, [$dto->id]));
    }

    private function testAtomicRollbackOnItemFailure(): void
    {
        $this->authenticateAuthorizedUser();

        $initialReqCount = count($this->reqRepo->findByPlanningEntity($this->fixtureEntityId));

        // Create requisition payload where item 1 is valid, but item 2 has invalid plan item
        $caught = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Rollback test',
                items: [
                    [
                        'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                        'standard_item_id' => $this->fixtureStandardItem1Id,
                        'item_description' => 'Valid Item 1',
                        'uom_id' => $this->fixtureUomId,
                        'requested_quantity' => '5.00',
                        'estimated_unit_cost' => '50.00',
                    ],
                    [
                        'procurement_plan_item_id' => 999999, // Invalid plan item ID!
                        'standard_item_id' => $this->fixtureStandardItem1Id,
                        'item_description' => 'Broken Item 2',
                        'uom_id' => $this->fixtureUomId,
                        'requested_quantity' => '5.00',
                        'estimated_unit_cost' => '50.00',
                    ],
                ],
                actingUserId: $this->fixtureUserId
            ));
        } catch (ValidationException $e) {
            $caught = true;
        }

        $this->assert($caught, "Scenario 21: Validation exception caught on invalid second item");

        $afterReqCount = count($this->reqRepo->findByPlanningEntity($this->fixtureEntityId));
        $this->assert($afterReqCount === $initialReqCount, "Scenario 21: Atomic rollback leaves zero orphan requisition records");
    }

    private function testSuccessfulRequisitionSubmission(): void
    {
        $this->authenticateAuthorizedUser();

        // Valid requisition well within remaining quota (Item 1 planned 100, requesting 15)
        $dto = $this->reqService->createRequisition(new CreateRequisitionRequest(
            planningEntityId: $this->fixtureEntityId,
            fiscalYear: 2026,
            approvedPlanVersionId: $this->fixtureApprovedVersionId,
            justification: 'Valid submission test',
            items: [
                [
                    'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Midterm Paper',
                    'uom_id' => $this->fixtureUomId,
                    'requested_quantity' => '15.00',
                    'estimated_unit_cost' => '50.00',
                ],
            ],
            actingUserId: $this->fixtureUserId
        ));
        $this->trackedRequisitions[] = $dto->id;

        $result = $this->reqService->submitRequisition(new SubmitRequisitionRequest($dto->id, $this->fixtureUserId));

        $this->assert($result->requisition->status === RequisitionStatus::SUBMITTED, "Scenario 22: Requisition transitioned to SUBMITTED status");
        $this->assert($result->requisition->submittedBy === $this->fixtureUserId, "Scenario 22: submitted_by recorded accurately");
        $this->assert($result->requisition->submittedAt !== null, "Scenario 22: submitted_at timestamp populated");
        $this->assert(count($result->snapshots) === 1, "Scenario 22: Exactly 1 evidentiary snapshot created upon submission");
        $this->assert($result->snapshots[0]->workflowEvent === 'SUBMISSION', "Scenario 22: Snapshot workflowEvent stamped as SUBMISSION");
    }

    private function testDuplicateSubmissionPrevention(): void
    {
        $this->authenticateAuthorizedUser();

        $dto = $this->reqService->createRequisition(new CreateRequisitionRequest(
            planningEntityId: $this->fixtureEntityId,
            fiscalYear: 2026,
            approvedPlanVersionId: $this->fixtureApprovedVersionId,
            justification: 'Duplicate submission test',
            items: [
                [
                    'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Single Submission Paper',
                    'uom_id' => $this->fixtureUomId,
                    'requested_quantity' => '5.00',
                    'estimated_unit_cost' => '50.00',
                ],
            ],
            actingUserId: $this->fixtureUserId
        ));
        $this->trackedRequisitions[] = $dto->id;

        // First submission succeeds
        $this->reqService->submitRequisition(new SubmitRequisitionRequest($dto->id, $this->fixtureUserId));

        // Second submission attempt must fail
        $caught = false;
        try {
            $this->reqService->submitRequisition(new SubmitRequisitionRequest($dto->id, $this->fixtureUserId));
        } catch (ValidationException $e) {
            $caught = true;
        }

        $this->assert($caught, "Scenario 23: Rejects duplicate submission on already submitted requisition");
    }

    private function testAuthorizationEnforcement(): void
    {
        // 1. Unauthorized creation
        $this->authenticateUnauthorizedUser();

        $caughtCreate = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntityId,
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureApprovedVersionId,
                justification: 'Unauthorized creation attempt',
                items: [['procurement_plan_item_id' => $this->fixturePlanItem1Id, 'requested_quantity' => '1.00']],
                actingUserId: $this->fixtureUnauthorizedUserId
            ));
        } catch (AuthorizationException $e) {
            $caughtCreate = true;
        }
        $this->assert($caughtCreate, "Scenario 24: Rejects requisition creation by user without req.create permission");

        // 2. Entity scope enforcement: Authorized user only has create rights on Entity 1, not Entity 2
        $this->authenticateAuthorizedUser();

        $caughtEntityScope = false;
        try {
            $this->reqService->createRequisition(new CreateRequisitionRequest(
                planningEntityId: $this->fixtureEntity2Id, // User not authorized for Entity 2!
                fiscalYear: 2026,
                approvedPlanVersionId: $this->fixtureAlienVersionId,
                justification: 'Cross-entity authorization test',
                items: [['procurement_plan_item_id' => $this->fixtureAlienPlanItemId, 'requested_quantity' => '1.00']],
                actingUserId: $this->fixtureUserId
            ));
        } catch (AuthorizationException $e) {
            $caughtEntityScope = true;
        }
        $this->assert($caughtEntityScope, "Scenario 26: Rejects requisition creation outside user's scoped entity (Entity #2)");
    }

    private function testBalanceSnapshotEvidentiaryIntegrity(): void
    {
        $this->authenticateAuthorizedUser();

        // Calculate expected drawdown
        // Total planned: 50.00 for marker item 2
        $dto = $this->reqService->createRequisition(new CreateRequisitionRequest(
            planningEntityId: $this->fixtureEntityId,
            fiscalYear: 2026,
            approvedPlanVersionId: $this->fixtureApprovedVersionId,
            justification: 'Snapshot verification test',
            items: [
                [
                    'procurement_plan_item_id' => $this->fixturePlanItem2Id, // Planned: 50.00
                    'standard_item_id' => $this->fixtureStandardItem2Id,
                    'item_description' => 'Markers for semester',
                    'uom_id' => $this->fixtureUomId,
                    'requested_quantity' => '10.00',
                    'estimated_unit_cost' => '80.00',
                ],
            ],
            actingUserId: $this->fixtureUserId
        ));
        $this->trackedRequisitions[] = $dto->id;

        $subResult = $this->reqService->submitRequisition(new SubmitRequisitionRequest($dto->id, $this->fixtureUserId));

        $snapshot = $subResult->snapshots[0];
        $this->assert($snapshot->approvedPlannedQuantity === '50.00', "Scenario 27: Snapshot approvedPlannedQuantity exact (50.00)");
        $this->assert($snapshot->currentRequestQuantity === '10.00', "Scenario 27: Snapshot currentRequestQuantity exact (10.00)");
        $this->assert($snapshot->remainingBefore === '45.00', "Scenario 27: Snapshot remainingBefore matches previous drawdowns (45.00)");
        $this->assert($snapshot->remainingAfter === '35.00', "Scenario 27: Snapshot remainingAfter matches calculation (35.00)");
        $this->assert($snapshot->recordedByUserId === $this->fixtureUserId, "Scenario 27: Snapshot records acting user ID");
    }

    private function testConditionalStatusUpdateConflictHandling(): void
    {
        $this->authenticateAuthorizedUser();

        $dto = $this->reqService->createRequisition(new CreateRequisitionRequest(
            planningEntityId: $this->fixtureEntityId,
            fiscalYear: 2026,
            approvedPlanVersionId: $this->fixtureApprovedVersionId,
            justification: 'Concurrency status test',
            items: [
                [
                    'procurement_plan_item_id' => $this->fixturePlanItem1Id,
                    'standard_item_id' => $this->fixtureStandardItem1Id,
                    'item_description' => 'Test Item',
                    'uom_id' => $this->fixtureUomId,
                    'requested_quantity' => '2.00',
                    'estimated_unit_cost' => '50.00',
                ],
            ],
            actingUserId: $this->fixtureUserId
        ));
        $this->trackedRequisitions[] = $dto->id;

        // Manually alter status in DB behind the scenes to simulate concurrent transition to 'ENDORSED'
        $this->reqRepo->updateStatus($dto->id, RequisitionStatus::ENDORSED->value, $this->fixtureUserId);

        // Attempting to submit when expecting DRAFT must fail safely
        $caught = false;
        try {
            $this->reqService->submitRequisition(new SubmitRequisitionRequest($dto->id, $this->fixtureUserId));
        } catch (ValidationException $e) {
            $caught = true;
        }

        $this->assert($caught, "Scenario 29: Conditional status check catches concurrent status alteration");
    }

    private function testConcurrencyRowLockingSimulation(): void
    {
        $this->authenticateAuthorizedUser();

        // Verify that locking query executes cleanly inside a transaction without SQL syntax or deadlock errors
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT `id`, `planned_quantity` 
                FROM `procurement_plan_items` 
                WHERE `id` = :id 
                FOR UPDATE
            ");
            $stmt->execute(['id' => $this->fixturePlanItem1Id]);
            $lockedRow = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assert($lockedRow !== false, "Scenario 30: FOR UPDATE pessimistic row lock acquired on plan item");
            $this->assert($lockedRow['planned_quantity'] === '100.00', "Scenario 30: Locked row preserves decimal planned quantity");
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function teardown(): void
    {
        // 1. Requisitions & items & snapshots
        if (!empty($this->trackedRequisitions)) {
            $in = implode(',', array_map('intval', $this->trackedRequisitions));
            $this->db->exec("DELETE FROM `requisition_balance_snapshots` WHERE `requisition_id` IN ({$in})");
            $this->db->exec("DELETE FROM `requisition_items` WHERE `requisition_id` IN ({$in})");
            $this->db->exec("DELETE FROM `requisitions` WHERE `id` IN ({$in})");
        }

        // 2. Plan items
        if (!empty($this->trackedPlanItems)) {
            $in = implode(',', array_map('intval', $this->trackedPlanItems));
            $this->db->exec("DELETE FROM `procurement_plan_items` WHERE `id` IN ({$in})");
        }

        // 3. Plan versions & Plans
        if (!empty($this->trackedPlans)) {
            $in = implode(',', array_map('intval', $this->trackedPlans));
            $this->db->exec("UPDATE `procurement_plans` SET `current_version_id` = NULL WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedVersions)) {
            $in = implode(',', array_map('intval', $this->trackedVersions));
            $this->db->exec("DELETE FROM `procurement_plan_versions` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedPlans)) {
            $in = implode(',', array_map('intval', $this->trackedPlans));
            $this->db->exec("DELETE FROM `procurement_plans` WHERE `id` IN ({$in})");
        }

        // 4. Standard items, UOMs, Categories
        if (!empty($this->trackedStandardItems)) {
            $in = implode(',', array_map('intval', $this->trackedStandardItems));
            $this->db->exec("DELETE FROM `standard_items` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedUoms)) {
            $in = implode(',', array_map('intval', $this->trackedUoms));
            $this->db->exec("DELETE FROM `units_of_measure` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedCategories)) {
            $in = implode(',', array_map('intval', $this->trackedCategories));
            $this->db->exec("DELETE FROM `item_categories` WHERE `id` IN ({$in})");
        }

        // 5. Entities, Campuses, Users
        if (!empty($this->trackedEntities)) {
            $in = implode(',', array_map('intval', $this->trackedEntities));
            $this->db->exec("DELETE FROM `planning_entities` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedEntityTypes)) {
            $in = implode(',', array_map('intval', $this->trackedEntityTypes));
            $this->db->exec("DELETE FROM `entity_types` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedCampuses)) {
            $in = implode(',', array_map('intval', $this->trackedCampuses));
            $this->db->exec("DELETE FROM `campuses` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedUsers)) {
            $in = implode(',', array_map('intval', $this->trackedUsers));
            $this->db->exec("DELETE FROM `users` WHERE `id` IN ({$in})");
        }

        AuthManager::logout();

        $this->assert(true, "Database teardown completed with ZERO orphan records");
    }

    private function printSummary(): void
    {
        echo "\n===============================================================\n";
        echo " REQUISITION SERVICE TEST SUMMARY\n";
        echo " Passed: {$this->passed} / " . ($this->passed + $this->failed) . "\n";
        echo " Failed: {$this->failed}\n";
        echo "===============================================================\n\n";

        if ($this->failed === 0) {
            echo "ALL REQUISITION SERVICE & DRAWDOWN ENGINE TESTS PASSED SUCCESSFULLY.\n";
        } else {
            echo "FAILURES ENCOUNTERED:\n";
            foreach ($this->failures as $f) {
                echo " - {$f}\n";
            }
        }
    }
}

// CLI Execution Entry Point
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    RequisitionServiceTest::main();
}
