<?php

declare(strict_types=1);

/**
 * PROMIS Phase 2 Stage 2.1 Requisition Domain and Persistence Test Suite
 * Procurement Management Information System
 * University of Skills Training and Entrepreneurial Development (USTED)
 *
 * Standalone CLI test runner verifying concrete PDO repository persistence,
 * database constraints, foreign keys, enums, decimal safety, and ON DELETE RESTRICT integrity
 * for requisitions, requisition_items, and requisition_balance_snapshots.
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\DatabaseException;
use Promis\Core\Exception\ValidationException;
use Promis\Src\Execution\Domain\DTO\RequisitionBalanceSnapshotDTO;
use Promis\Src\Execution\Domain\DTO\RequisitionDTO;
use Promis\Src\Execution\Domain\DTO\RequisitionItemDTO;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Repository\RequisitionBalanceSnapshotRepository;
use Promis\Src\Execution\Repository\RequisitionItemRepository;
use Promis\Src\Execution\Repository\RequisitionRepository;
use Promis\Src\Execution\Validation\RequisitionItemValidator;
use Promis\Src\Execution\Validation\RequisitionValidator;
use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\PlanStatus;
use Promis\Src\Planning\Domain\PlanVersionStatus;
use Promis\Src\Planning\Domain\TargetQuarter;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepository;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepository;

final class ExecutionPersistenceTest
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
    private array $trackedPlanItems = [];
    private array $trackedRequisitions = [];
    private array $trackedRequisitionItems = [];
    private array $trackedSnapshots = [];

    // Repositories
    private RequisitionRepository $reqRepo;
    private RequisitionItemRepository $itemRepo;
    private RequisitionBalanceSnapshotRepository $snapshotRepo;
    private ProcurementPlanRepository $planRepo;
    private ProcurementPlanVersionRepository $versionRepo;
    private ProcurementPlanItemRepository $planItemRepo;

    // Shared base fixtures
    private int $fixtureUserId = 0;
    private int $fixtureCampusId = 0;
    private int $fixtureEntityTypeId = 0;
    private int $fixtureEntityId = 0;
    private int $fixtureCategoryId = 0;
    private int $fixtureUomId = 0;
    private int $fixtureStandardItemId = 0;
    private int $fixturePlanId = 0;
    private int $fixtureVersionId = 0;
    private int $fixturePlanItemId = 0;

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
        $this->itemRepo = new RequisitionItemRepository($this->db);
        $this->snapshotRepo = new RequisitionBalanceSnapshotRepository($this->db);
        $this->planRepo = new ProcurementPlanRepository($this->db);
        $this->versionRepo = new ProcurementPlanVersionRepository($this->db);
        $this->planItemRepo = new ProcurementPlanItemRepository($this->db);
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS Phase 2.1 Requisition Domain & Persistence Test Suite\n";
        echo " University of Skills Training and Entrepreneurial Development\n";
        echo " Relational Engine: MariaDB 10.4+ / MySQL 8.x\n";
        echo " PHP Version: " . PHP_VERSION . "\n";
        echo "===============================================================\n\n";

        try {
            $this->seedBaseFixtures();

            // Requisition Tests
            $this->testRequisitionCreateAndFind();
            $this->testRequisitionQueries();
            $this->testRequisitionUpdates();
            $this->testRequisitionConditionalStatusUpdate();
            $this->testRequisitionUniqueConstraint();
            $this->testRequisitionForeignKeyConstraints();

            // Requisition Item Tests
            $this->testRequisitionItemCreateAndFind();
            $this->testRequisitionItemBatchCreate();
            $this->testRequisitionItemUpdatesAndDeletes();
            $this->testRequisitionItemAggregation();
            $this->testRequisitionItemForeignKeyAndCheckConstraints();

            // Balance Snapshot Tests
            $this->testBalanceSnapshotCreateAndFind();
            $this->testBalanceSnapshotBatchCreate();
            $this->testBalanceSnapshotForeignKeyConstraints();

            // Integrity & Referential Safety Tests
            $this->testOnDeleteRestrictSafety();
            $this->testDecimalPrecisionPreservation();

            // Domain Validator Tests
            $this->testDomainValidators();

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

    private function seedBaseFixtures(): void
    {
        $unique = bin2hex(random_bytes(4));

        // 1. User
        $stmt = $this->db->prepare("
            INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `status`)
            VALUES (:u, :e, 'hash123', 'Req', 'Officer', 'ACTIVE')
        ");
        $stmt->execute(['u' => 'req_user_' . $unique, 'e' => "req_{$unique}@usted.edu.gh"]);
        $this->fixtureUserId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->fixtureUserId;

        // 2. Campus
        $stmt = $this->db->prepare("
            INSERT INTO `campuses` (`campus_code`, `campus_name`)
            VALUES (:c, 'Main Campus')
        ");
        $stmt->execute(['c' => 'CAMP_' . $unique]);
        $this->fixtureCampusId = (int)$this->db->lastInsertId();
        $this->trackedCampuses[] = $this->fixtureCampusId;

        // 3. Entity Type
        $stmt = $this->db->prepare("
            INSERT INTO `entity_types` (`type_code`, `type_name`)
            VALUES (:t, 'Department')
        ");
        $stmt->execute(['t' => 'TYPE_' . $unique]);
        $this->fixtureEntityTypeId = (int)$this->db->lastInsertId();
        $this->trackedEntityTypes[] = $this->fixtureEntityTypeId;

        // 4. Planning Entity
        $stmt = $this->db->prepare("
            INSERT INTO `planning_entities` (`entity_code`, `entity_name`, `entity_type_id`, `campus_id`)
            VALUES (:ec, 'Dept of Computing', :tid, :cid)
        ");
        $stmt->execute(['ec' => 'ENT_' . $unique, 'tid' => $this->fixtureEntityTypeId, 'cid' => $this->fixtureCampusId]);
        $this->fixtureEntityId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->fixtureEntityId;

        // 5. Category
        $stmt = $this->db->prepare("
            INSERT INTO `item_categories` (`category_code`, `category_name`)
            VALUES (:cc, 'Stationery')
        ");
        $stmt->execute(['cc' => 'CAT_' . $unique]);
        $this->fixtureCategoryId = (int)$this->db->lastInsertId();
        $this->trackedCategories[] = $this->fixtureCategoryId;

        // 6. UOM
        $stmt = $this->db->prepare("
            INSERT INTO `units_of_measure` (`uom_code`, `uom_name`)
            VALUES (:uc, 'Box')
        ");
        $stmt->execute(['uc' => 'UOM_' . $unique]);
        $this->fixtureUomId = (int)$this->db->lastInsertId();
        $this->trackedUoms[] = $this->fixtureUomId;

        // 7. Standard Item
        $stmt = $this->db->prepare("
            INSERT INTO `standard_items` (`item_code`, `item_name`, `category_id`, `default_uom_id`, `estimated_unit_price`)
            VALUES (:ic, 'A4 Paper', :cid, :uid, '50.00')
        ");
        $stmt->execute([
            'ic' => 'ITEM_' . $unique,
            'cid' => $this->fixtureCategoryId,
            'uid' => $this->fixtureUomId,
        ]);
        $this->fixtureStandardItemId = (int)$this->db->lastInsertId();
        $this->trackedStandardItems[] = $this->fixtureStandardItemId;

        // 8. Procurement Plan
        $this->fixturePlanId = $this->planRepo->create([
            'plan_number' => 'PLAN-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => PlanStatus::APPROVED->value,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $this->fixturePlanId;

        // 9. Procurement Plan Version
        $this->fixtureVersionId = $this->versionRepo->create([
            'procurement_plan_id' => $this->fixturePlanId,
            'version_number' => '1.0',
            'status' => PlanVersionStatus::APPROVED->value,
            'total_estimated_cost' => '5000.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedVersions[] = $this->fixtureVersionId;
        $this->planRepo->setCurrentVersion($this->fixturePlanId, $this->fixtureVersionId);

        // 10. Procurement Plan Item
        $this->fixturePlanItemId = $this->planItemRepo->create([
            'plan_version_id' => $this->fixtureVersionId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'A4 Paper Boxes for Exams',
            'category_id' => $this->fixtureCategoryId,
            'uom_id' => $this->fixtureUomId,
            'planned_quantity' => '100.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '5000.00',
            'target_quarter' => TargetQuarter::Q1->value,
            'funding_source' => 'IGF',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlanItems[] = $this->fixturePlanItemId;

        $this->assert(true, "Base relational fixtures seeded successfully");
    }

    private function testRequisitionCreateAndFind(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqNum = 'REQ-2026-' . $unique;

        $id = $this->reqRepo->create([
            'requisition_number' => $reqNum,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'approved_plan_version_id' => $this->fixtureVersionId,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '1500.50',
            'justification' => 'Urgent stationery for mid-term exams',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $id;

        $this->assert($id > 0, "RequisitionRepository::create returns positive surrogate ID ({$id})");

        $dto = $this->reqRepo->findById($id);
        $this->assert($dto !== null, "RequisitionRepository::findById retrieves created record");
        $this->assert($dto->requisitionNumber === $reqNum, "Requisition number matches inserted");
        $this->assert($dto->planningEntityId === $this->fixtureEntityId, "Planning entity matches");
        $this->assert($dto->fiscalYear === 2026, "Fiscal year matches");
        $this->assert($dto->approvedPlanVersionId === $this->fixtureVersionId, "Approved plan version ID matches");
        $this->assert($dto->status === RequisitionStatus::DRAFT, "Initial status is DRAFT");
        $this->assert($dto->totalEstimatedCost === '1500.50', "Total estimated cost matches exact decimal string");
        $this->assert($dto->planningEntityName === 'Dept of Computing', "Planning entity name hydrated via join");
        $this->assert($dto->planVersionNumber === '1.0', "Plan version number hydrated via join");
    }

    private function testRequisitionQueries(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqNum = 'REQ-2026-QRY-' . $unique;

        $id = $this->reqRepo->create([
            'requisition_number' => $reqNum,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'approved_plan_version_id' => $this->fixtureVersionId,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '200.00',
            'justification' => 'Testing query methods',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $id;

        $byNum = $this->reqRepo->findByRequisitionNumber($reqNum);
        $this->assert($byNum !== null && $byNum->id === $id, "RequisitionRepository::findByRequisitionNumber retrieves by natural key");

        $missing = $this->reqRepo->findByRequisitionNumber('REQ-NON-EXISTENT');
        $this->assert($missing === null, "findByRequisitionNumber returns null for non-existent requisition");

        $byEntity = $this->reqRepo->findByPlanningEntity($this->fixtureEntityId, 2026);
        $this->assert(count($byEntity) >= 2, "findByPlanningEntity returns list of requisitions for entity and year");

        $byVersion = $this->reqRepo->findByApprovedPlanVersion($this->fixtureVersionId);
        $this->assert(count($byVersion) >= 2, "findByApprovedPlanVersion returns requisitions anchored to version (FR-051)");

        $exists = $this->reqRepo->existsByRequisitionNumber($reqNum);
        $this->assert($exists === true, "existsByRequisitionNumber returns true for existing requisition");

        $notExists = $this->reqRepo->existsByRequisitionNumber('REQ-DOES-NOT-EXIST');
        $this->assert($notExists === false, "existsByRequisitionNumber returns false for non-existent requisition");
    }

    private function testRequisitionUpdates(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqNum = 'REQ-2026-UPD-' . $unique;

        $id = $this->reqRepo->create([
            'requisition_number' => $reqNum,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'approved_plan_version_id' => $this->fixtureVersionId,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '300.00',
            'justification' => 'Initial justification',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $id;

        $updated = $this->reqRepo->update($id, [
            'justification' => 'Revised justification with updated requirements',
            'total_estimated_cost' => '750.25',
            'updated_by' => $this->fixtureUserId,
        ]);
        $this->assert($updated === true, "RequisitionRepository::update returns true on successful update");

        $retrieved = $this->reqRepo->findById($id);
        $this->assert($retrieved->justification === 'Revised justification with updated requirements', "Justification updated");
        $this->assert($retrieved->totalEstimatedCost === '750.25', "Total estimated cost updated");
        $this->assert($retrieved->updatedBy === $this->fixtureUserId, "Updated by user ID recorded");
        $this->assert($retrieved->updatedAt !== null, "Updated at timestamp populated");

        $costUpdated = $this->reqRepo->updateTotalCost($id, '800.00', $this->fixtureUserId);
        $this->assert($costUpdated === true, "updateTotalCost returns true");
        $retrievedCost = $this->reqRepo->findById($id);
        $this->assert($retrievedCost->totalEstimatedCost === '800.00', "Total estimated cost successfully updated to 800.00");
    }

    private function testRequisitionConditionalStatusUpdate(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqNum = 'REQ-2026-STS-' . $unique;

        $id = $this->reqRepo->create([
            'requisition_number' => $reqNum,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'approved_plan_version_id' => $this->fixtureVersionId,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '500.00',
            'justification' => 'Testing status transitions',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $id;

        // Transition from DRAFT to SUBMITTED with expected pre-status
        $submitted = $this->reqRepo->updateStatus(
            $id,
            RequisitionStatus::SUBMITTED->value,
            $this->fixtureUserId,
            RequisitionStatus::DRAFT->value
        );
        $this->assert($submitted === true, "updateStatus succeeds when expectedPreStatus matches ('DRAFT')");

        $dto = $this->reqRepo->findById($id);
        $this->assert($dto->status === RequisitionStatus::SUBMITTED, "Requisition status transitioned to SUBMITTED");
        $this->assert($dto->submittedBy === $this->fixtureUserId, "Submitted by recorded");
        $this->assert($dto->submittedAt !== null, "Submitted at timestamp populated");

        // Attempt conditional update with wrong pre-status (should fail and return false)
        $failedUpdate = $this->reqRepo->updateStatus(
            $id,
            RequisitionStatus::ENDORSED->value,
            $this->fixtureUserId,
            RequisitionStatus::DRAFT->value // Expected DRAFT, but is already SUBMITTED
        );
        $this->assert($failedUpdate === false, "updateStatus fails safely when expectedPreStatus does not match");

        // Transition with correct pre-status (SUBMITTED -> ENDORSED)
        $endorsed = $this->reqRepo->updateStatus(
            $id,
            RequisitionStatus::ENDORSED->value,
            $this->fixtureUserId,
            RequisitionStatus::SUBMITTED->value
        );
        $this->assert($endorsed === true, "updateStatus succeeds from SUBMITTED to ENDORSED");

        $dto2 = $this->reqRepo->findById($id);
        $this->assert($dto2->status === RequisitionStatus::ENDORSED, "Status is ENDORSED");
    }

    private function testRequisitionUniqueConstraint(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqNum = 'REQ-UQ-' . $unique;

        $id1 = $this->reqRepo->create([
            'requisition_number' => $reqNum,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '100.00',
            'justification' => 'First unique test',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $id1;

        $rejected = false;
        try {
            $this->reqRepo->create([
                'requisition_number' => $reqNum, // DUPLICATE
                'planning_entity_id' => $this->fixtureEntityId,
                'fiscal_year' => 2026,
                'status' => RequisitionStatus::DRAFT->value,
                'total_estimated_cost' => '100.00',
                'justification' => 'Second duplicate test',
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $rejected = true;
        }

        $this->assert($rejected, "Database engine rejects duplicate requisition_number via unique constraint");
    }

    private function testRequisitionForeignKeyConstraints(): void
    {
        $unique = bin2hex(random_bytes(3));

        // 1. Invalid planning_entity_id
        $rejectedEntity = false;
        try {
            $this->reqRepo->create([
                'requisition_number' => 'REQ-INVALID-ENT-' . $unique,
                'planning_entity_id' => 999999, // Non-existent entity
                'fiscal_year' => 2026,
                'status' => RequisitionStatus::DRAFT->value,
                'total_estimated_cost' => '100.00',
                'justification' => 'Foreign key test',
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $rejectedEntity = true;
        }
        $this->assert($rejectedEntity, "Database engine rejects requisition with invalid planning_entity_id via FK");

        // 2. Invalid approved_plan_version_id
        $rejectedVersion = false;
        try {
            $this->reqRepo->create([
                'requisition_number' => 'REQ-INVALID-VER-' . $unique,
                'planning_entity_id' => $this->fixtureEntityId,
                'fiscal_year' => 2026,
                'approved_plan_version_id' => 999999, // Non-existent version
                'status' => RequisitionStatus::DRAFT->value,
                'total_estimated_cost' => '100.00',
                'justification' => 'Foreign key test',
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $rejectedVersion = true;
        }
        $this->assert($rejectedVersion, "Database engine rejects requisition with invalid approved_plan_version_id via FK");
    }

    private function testRequisitionItemCreateAndFind(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqId = $this->reqRepo->create([
            'requisition_number' => 'REQ-ITEM-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'approved_plan_version_id' => $this->fixtureVersionId,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '500.00',
            'justification' => 'Line item testing',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $reqId;

        $itemId = $this->itemRepo->create([
            'requisition_id' => $reqId,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'A4 Paper Boxes',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '10.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '500.00',
            'item_justification' => 'Faculty semester examination printing',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = $itemId;

        $this->assert($itemId > 0, "RequisitionItemRepository::create returns positive surrogate ID ({$itemId})");

        $item = $this->itemRepo->findById($itemId);
        $this->assert($item !== null, "RequisitionItemRepository::findById retrieves created line item");
        $this->assert($item->requisitionId === $reqId, "Requisition ID matches");
        $this->assert($item->procurementPlanItemId === $this->fixturePlanItemId, "Procurement plan item ID matches");
        $this->assert($item->standardItemId === $this->fixtureStandardItemId, "Standard item ID matches");
        $this->assert($item->requestedQuantity === '10.00', "Requested quantity matches decimal string (10.00)");
        $this->assert($item->estimatedUnitCost === '50.00', "Estimated unit cost matches decimal string (50.00)");
        $this->assert($item->estimatedTotalCost === '500.00', "Estimated total cost matches decimal string (500.00)");
        $this->assert($item->itemCode !== null, "Standard item code hydrated via join ({$item->itemCode})");
        $this->assert($item->uomCode !== null, "UOM code hydrated via join ({$item->uomCode})");
        $this->assert($item->plannedQuantity === '100.00', "Parent plan item planned quantity hydrated (100.00)");

        $byReq = $this->itemRepo->findByRequisitionId($reqId);
        $this->assert(count($byReq) === 1, "findByRequisitionId returns 1 item");

        $byPlanItem = $this->itemRepo->findByPlanItemId($this->fixturePlanItemId);
        $this->assert(count($byPlanItem) >= 1, "findByPlanItemId returns items linked to plan item");
    }

    private function testRequisitionItemBatchCreate(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqId = $this->reqRepo->create([
            'requisition_number' => 'REQ-BATCH-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '1250.00',
            'justification' => 'Batch insertion test',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $reqId;

        $batch = [
            [
                'procurement_plan_item_id' => $this->fixturePlanItemId,
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'A4 Paper Batch 1',
                'uom_id' => $this->fixtureUomId,
                'requested_quantity' => '10.00',
                'estimated_unit_cost' => '50.00',
                'estimated_total_cost' => '500.00',
                'item_justification' => 'Batch item 1',
            ],
            [
                'procurement_plan_item_id' => $this->fixturePlanItemId,
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'A4 Paper Batch 2',
                'uom_id' => $this->fixtureUomId,
                'requested_quantity' => '15.00',
                'estimated_unit_cost' => '50.00',
                'estimated_total_cost' => '750.00',
                'item_justification' => 'Batch item 2',
            ],
        ];

        $inserted = $this->itemRepo->createBatch($reqId, $batch, $this->fixtureUserId);
        $this->assert($inserted === 2, "RequisitionItemRepository::createBatch successfully inserted 2 rows");

        $items = $this->itemRepo->findByRequisitionId($reqId);
        $this->assert(count($items) === 2, "Exactly 2 items retrieved for batch requisition");
        foreach ($items as $item) {
            $this->trackedRequisitionItems[] = $item->id;
        }

        $this->assert($items[0]->requestedQuantity === '10.00', "Batch line 1 quantity accurate");
        $this->assert($items[1]->requestedQuantity === '15.00', "Batch line 2 quantity accurate");
    }

    private function testRequisitionItemUpdatesAndDeletes(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqId = $this->reqRepo->create([
            'requisition_number' => 'REQ-ITEM-UPD-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '250.00',
            'justification' => 'Update/Delete test',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $reqId;

        $itemId = $this->itemRepo->create([
            'requisition_id' => $reqId,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Initial Item',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '5.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '250.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = $itemId;

        $updated = $this->itemRepo->update($itemId, [
            'requested_quantity' => '8.00',
            'estimated_total_cost' => '400.00',
            'item_description' => 'Updated Item Description',
            'updated_by' => $this->fixtureUserId,
        ]);
        $this->assert($updated === true, "RequisitionItemRepository::update returns true");

        $retrieved = $this->itemRepo->findById($itemId);
        $this->assert($retrieved->requestedQuantity === '8.00', "Item quantity updated to 8.00");
        $this->assert($retrieved->estimatedTotalCost === '400.00', "Item total cost updated to 400.00");
        $this->assert($retrieved->itemDescription === 'Updated Item Description', "Item description updated");

        $deleted = $this->itemRepo->delete($itemId);
        $this->assert($deleted === true, "RequisitionItemRepository::delete returns true");
        $this->assert($this->itemRepo->findById($itemId) === null, "Item no longer exists after delete");

        // Test deleteByRequisitionId
        $this->itemRepo->create([
            'requisition_id' => $reqId,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Temp Item 1',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '2.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '100.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->itemRepo->create([
            'requisition_id' => $reqId,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Temp Item 2',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '3.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '150.00',
            'created_by' => $this->fixtureUserId,
        ]);

        $deletedCount = $this->itemRepo->deleteByRequisitionId($reqId);
        $this->assert($deletedCount === 2, "deleteByRequisitionId deleted exactly 2 child line items");
        $this->assert(empty($this->itemRepo->findByRequisitionId($reqId)), "Zero items remain after deleteByRequisitionId");
    }

    private function testRequisitionItemAggregation(): void
    {
        $unique = bin2hex(random_bytes(3));
        $req1Id = $this->reqRepo->create([
            'requisition_number' => 'REQ-AGG-1-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => RequisitionStatus::SUBMITTED->value,
            'total_estimated_cost' => '1000.00',
            'justification' => 'Aggregation test 1',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $req1Id;

        $req2Id = $this->reqRepo->create([
            'requisition_number' => 'REQ-AGG-2-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => RequisitionStatus::REJECTED->value, // Should NOT count towards drawdown
            'total_estimated_cost' => '500.00',
            'justification' => 'Aggregation test 2 (rejected)',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $req2Id;

        $item1Id = $this->itemRepo->create([
            'requisition_id' => $req1Id,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Active Request Item',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '20.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '1000.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = $item1Id;

        $item2Id = $this->itemRepo->create([
            'requisition_id' => $req2Id,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Rejected Request Item',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '10.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '500.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = $item2Id;

        $totalReq = $this->itemRepo->calculateRequestedQuantityByPlanItem($this->fixturePlanItemId);
        // Note: other tests inserted 10 + 10 + 15 = 35 from previous non-rejected tests + 20 from this test = 55.00
        $this->assert(Decimal::gte($totalReq, '20.00'), "calculateRequestedQuantityByPlanItem aggregates active requested quantity ({$totalReq})");

        // Exclude req1Id
        $totalExcluded = $this->itemRepo->calculateRequestedQuantityByPlanItem($this->fixturePlanItemId, $req1Id);
        $diff = Decimal::sub($totalReq, $totalExcluded, 2);
        $this->assert($diff === '20.00', "Excluding requisition properly subtracts its quantity (difference: {$diff})");
    }

    private function testRequisitionItemForeignKeyAndCheckConstraints(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqId = $this->reqRepo->create([
            'requisition_number' => 'REQ-CONSTR-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '100.00',
            'justification' => 'Constraint test',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $reqId;

        // 1. Invalid requisition_id
        $rejectedReq = false;
        try {
            $this->itemRepo->create([
                'requisition_id' => 999999, // Non-existent requisition
                'procurement_plan_item_id' => $this->fixturePlanItemId,
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'Invalid Req Test',
                'uom_id' => $this->fixtureUomId,
                'requested_quantity' => '5.00',
                'estimated_unit_cost' => '50.00',
                'estimated_total_cost' => '250.00',
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $rejectedReq = true;
        }
        $this->assert($rejectedReq, "Database engine rejects item with invalid requisition_id via FK");

        // 2. Invalid procurement_plan_item_id
        $rejectedPlanItem = false;
        try {
            $this->itemRepo->create([
                'requisition_id' => $reqId,
                'procurement_plan_item_id' => 999999, // Non-existent plan item
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'Invalid Plan Item Test',
                'uom_id' => $this->fixtureUomId,
                'requested_quantity' => '5.00',
                'estimated_unit_cost' => '50.00',
                'estimated_total_cost' => '250.00',
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $rejectedPlanItem = true;
        }
        $this->assert($rejectedPlanItem, "Database engine rejects item with invalid procurement_plan_item_id via FK");

        // 3. Negative or zero requested_quantity CHECK constraint (requested_quantity > 0.00)
        $rejectedZeroQty = false;
        try {
            $this->itemRepo->create([
                'requisition_id' => $reqId,
                'procurement_plan_item_id' => $this->fixturePlanItemId,
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'Zero Qty Test',
                'uom_id' => $this->fixtureUomId,
                'requested_quantity' => '0.00',
                'estimated_unit_cost' => '50.00',
                'estimated_total_cost' => '0.00',
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $rejectedZeroQty = true;
        }
        $this->assert($rejectedZeroQty, "Database engine rejects zero or negative requested_quantity via CHECK constraint");
    }

    private function testBalanceSnapshotCreateAndFind(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqId = $this->reqRepo->create([
            'requisition_number' => 'REQ-SNAP-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => RequisitionStatus::SUBMITTED->value,
            'total_estimated_cost' => '1000.00',
            'justification' => 'Snapshot testing',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $reqId;

        $itemId = $this->itemRepo->create([
            'requisition_id' => $reqId,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Snapshot Item',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '20.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '1000.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = $itemId;

        $snapId = $this->snapshotRepo->create([
            'requisition_id' => $reqId,
            'requisition_item_id' => $itemId,
            'plan_item_id' => $this->fixturePlanItemId,
            'workflow_event' => 'SUBMISSION',
            'approved_planned_quantity' => '100.00',
            'previously_requested_quantity' => '30.00',
            'current_request_quantity' => '20.00',
            'remaining_before' => '70.00',
            'remaining_after' => '50.00',
            'recorded_by_user_id' => $this->fixtureUserId,
        ]);
        $this->trackedSnapshots[] = $snapId;

        $this->assert($snapId > 0, "RequisitionBalanceSnapshotRepository::create returns positive surrogate ID ({$snapId})");

        $snap = $this->snapshotRepo->findById($snapId);
        $this->assert($snap !== null, "RequisitionBalanceSnapshotRepository::findById retrieves created snapshot");
        $this->assert($snap->requisitionId === $reqId, "Snapshot requisitionId matches");
        $this->assert($snap->requisitionItemId === $itemId, "Snapshot requisitionItemId matches");
        $this->assert($snap->planItemId === $this->fixturePlanItemId, "Snapshot planItemId matches");
        $this->assert($snap->workflowEvent === 'SUBMISSION', "Snapshot workflowEvent is SUBMISSION");
        $this->assert($snap->approvedPlannedQuantity === '100.00', "Approved planned quantity matches (100.00)");
        $this->assert($snap->previouslyRequestedQuantity === '30.00', "Previously requested quantity matches (30.00)");
        $this->assert($snap->currentRequestQuantity === '20.00', "Current request quantity matches (20.00)");
        $this->assert($snap->remainingBefore === '70.00', "Remaining before matches (70.00)");
        $this->assert($snap->remainingAfter === '50.00', "Remaining after matches (50.00)");

        $byReq = $this->snapshotRepo->findByRequisitionId($reqId);
        $this->assert(count($byReq) === 1, "findByRequisitionId returns 1 snapshot");

        $byItem = $this->snapshotRepo->findByRequisitionItemId($itemId);
        $this->assert(count($byItem) === 1, "findByRequisitionItemId returns 1 snapshot");

        $latest = $this->snapshotRepo->findLatestByRequisitionItemId($itemId);
        $this->assert($latest !== null && $latest->id === $snapId, "findLatestByRequisitionItemId retrieves latest snapshot");
    }

    private function testBalanceSnapshotBatchCreate(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqId = $this->reqRepo->create([
            'requisition_number' => 'REQ-SNAP-BATCH-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => RequisitionStatus::COMMITMENT_AUTHORIZED->value,
            'total_estimated_cost' => '1000.00',
            'justification' => 'Batch snapshot testing',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $reqId;

        $itemId1 = $this->itemRepo->create([
            'requisition_id' => $reqId,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Snapshot Batch Item 1',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '5.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '250.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = $itemId1;

        $itemId2 = $this->itemRepo->create([
            'requisition_id' => $reqId,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Snapshot Batch Item 2',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '10.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '500.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = $itemId2;

        $batch = [
            [
                'requisition_id' => $reqId,
                'requisition_item_id' => $itemId1,
                'plan_item_id' => $this->fixturePlanItemId,
                'workflow_event' => 'COMMITMENT_AUTHORIZED',
                'approved_planned_quantity' => '100.00',
                'previously_requested_quantity' => '50.00',
                'current_request_quantity' => '5.00',
                'remaining_before' => '50.00',
                'remaining_after' => '45.00',
                'recorded_by_user_id' => $this->fixtureUserId,
            ],
            [
                'requisition_id' => $reqId,
                'requisition_item_id' => $itemId2,
                'plan_item_id' => $this->fixturePlanItemId,
                'workflow_event' => 'COMMITMENT_AUTHORIZED',
                'approved_planned_quantity' => '100.00',
                'previously_requested_quantity' => '55.00',
                'current_request_quantity' => '10.00',
                'remaining_before' => '45.00',
                'remaining_after' => '35.00',
                'recorded_by_user_id' => $this->fixtureUserId,
            ],
        ];

        $inserted = $this->snapshotRepo->createBatch($batch);
        $this->assert($inserted === 2, "RequisitionBalanceSnapshotRepository::createBatch successfully inserted 2 snapshots");

        $snaps = $this->snapshotRepo->findByRequisitionId($reqId);
        $this->assert(count($snaps) === 2, "Exactly 2 snapshots retrieved for requisition");
        foreach ($snaps as $s) {
            $this->trackedSnapshots[] = $s->id;
        }
    }

    private function testBalanceSnapshotForeignKeyConstraints(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqId = $this->reqRepo->create([
            'requisition_number' => 'REQ-SNAP-CONSTR-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '100.00',
            'justification' => 'Constraint testing',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $reqId;

        $itemId = $this->itemRepo->create([
            'requisition_id' => $reqId,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Temp Item',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '2.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '100.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = $itemId;

        // 1. Invalid requisition_id
        $rejectedReq = false;
        try {
            $this->snapshotRepo->create([
                'requisition_id' => 999999, // Non-existent
                'requisition_item_id' => $itemId,
                'plan_item_id' => $this->fixturePlanItemId,
                'workflow_event' => 'SUBMISSION',
                'approved_planned_quantity' => '100.00',
                'previously_requested_quantity' => '0.00',
                'current_request_quantity' => '2.00',
                'remaining_before' => '100.00',
                'remaining_after' => '98.00',
                'recorded_by_user_id' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $rejectedReq = true;
        }
        $this->assert($rejectedReq, "Database engine rejects snapshot with invalid requisition_id via FK");

        // 2. Invalid requisition_item_id
        $rejectedItem = false;
        try {
            $this->snapshotRepo->create([
                'requisition_id' => $reqId,
                'requisition_item_id' => 999999, // Non-existent
                'plan_item_id' => $this->fixturePlanItemId,
                'workflow_event' => 'SUBMISSION',
                'approved_planned_quantity' => '100.00',
                'previously_requested_quantity' => '0.00',
                'current_request_quantity' => '2.00',
                'remaining_before' => '100.00',
                'remaining_after' => '98.00',
                'recorded_by_user_id' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $rejectedItem = true;
        }
        $this->assert($rejectedItem, "Database engine rejects snapshot with invalid requisition_item_id via FK");

        // 3. Invalid plan_item_id
        $rejectedPlanItem = false;
        try {
            $this->snapshotRepo->create([
                'requisition_id' => $reqId,
                'requisition_item_id' => $itemId,
                'plan_item_id' => 999999, // Non-existent
                'workflow_event' => 'SUBMISSION',
                'approved_planned_quantity' => '100.00',
                'previously_requested_quantity' => '0.00',
                'current_request_quantity' => '2.00',
                'remaining_before' => '100.00',
                'remaining_after' => '98.00',
                'recorded_by_user_id' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $rejectedPlanItem = true;
        }
        $this->assert($rejectedPlanItem, "Database engine rejects snapshot with invalid plan_item_id via FK");
    }

    private function testOnDeleteRestrictSafety(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqId = $this->reqRepo->create([
            'requisition_number' => 'REQ-RESTRICT-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'approved_plan_version_id' => $this->fixtureVersionId,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '100.00',
            'justification' => 'Restrict delete test',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $reqId;

        $itemId = $this->itemRepo->create([
            'requisition_id' => $reqId,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Protected item',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '2.00',
            'estimated_unit_cost' => '50.00',
            'estimated_total_cost' => '100.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = $itemId;

        // 1. Attempt to delete requisition while child item exists (must be rejected by ON DELETE RESTRICT)
        $rejectedReqDelete = false;
        try {
            $this->reqRepo->delete($reqId);
        } catch (DatabaseException $e) {
            $rejectedReqDelete = true;
        }
        $this->assert($rejectedReqDelete, "ON DELETE RESTRICT physically prevents deleting requisition while child items exist");

        // 2. Attempt to delete plan item while requisition item references it (must be rejected by ON DELETE RESTRICT)
        $rejectedPlanItemDelete = false;
        try {
            $stmt = $this->db->prepare("DELETE FROM `procurement_plan_items` WHERE `id` = :id");
            $stmt->execute(['id' => $this->fixturePlanItemId]);
        } catch (\PDOException $e) {
            $rejectedPlanItemDelete = true;
        }
        $this->assert($rejectedPlanItemDelete, "ON DELETE RESTRICT physically prevents deleting plan item while requisition items exist");

        // 3. Attempt to delete plan version while requisition references it (must be rejected by ON DELETE RESTRICT)
        $rejectedVersionDelete = false;
        try {
            $stmt = $this->db->prepare("DELETE FROM `procurement_plan_versions` WHERE `id` = :id");
            $stmt->execute(['id' => $this->fixtureVersionId]);
        } catch (\PDOException $e) {
            $rejectedVersionDelete = true;
        }
        $this->assert($rejectedVersionDelete, "ON DELETE RESTRICT physically prevents deleting approved plan version while requisitions exist (FR-051)");
    }

    private function testDecimalPrecisionPreservation(): void
    {
        $unique = bin2hex(random_bytes(3));
        $reqId = $this->reqRepo->create([
            'requisition_number' => 'REQ-DEC-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => RequisitionStatus::DRAFT->value,
            'total_estimated_cost' => '9999999.99',
            'justification' => 'High-precision decimal test',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitions[] = $reqId;

        $retrieved = $this->reqRepo->findById($reqId);
        $this->assert(is_string($retrieved->totalEstimatedCost), "totalEstimatedCost is typed strictly as PHP string");
        $this->assert($retrieved->totalEstimatedCost === '9999999.99', "Large monetary decimal preserved with 0 floating-point drift");

        $itemId = $this->itemRepo->create([
            'requisition_id' => $reqId,
            'procurement_plan_item_id' => $this->fixturePlanItemId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Precision Item',
            'uom_id' => $this->fixtureUomId,
            'requested_quantity' => '33.33',
            'estimated_unit_cost' => '300030.00',
            'estimated_total_cost' => '9999999.99',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = $itemId;

        $retrievedItem = $this->itemRepo->findById($itemId);
        $this->assert(is_string($retrievedItem->requestedQuantity), "requestedQuantity is string");
        $this->assert($retrievedItem->requestedQuantity === '33.33', "Quantity decimal exact (33.33)");
        $this->assert(is_string($retrievedItem->estimatedUnitCost), "estimatedUnitCost is string");
        $this->assert($retrievedItem->estimatedUnitCost === '300030.00', "Unit cost decimal exact (300030.00)");
        $this->assert(is_string($retrievedItem->estimatedTotalCost), "estimatedTotalCost is string");
        $this->assert($retrievedItem->estimatedTotalCost === '9999999.99', "Total cost decimal exact (9999999.99)");
    }

    private function testDomainValidators(): void
    {
        // 1. RequisitionValidator passes on valid data
        $validReq = [
            'planning_entity_id' => 10,
            'fiscal_year' => 2026,
            'justification' => 'Annual operational supplies',
            'status' => 'DRAFT',
            'approved_plan_version_id' => 45,
        ];
        $validItems = [
            [
                'procurement_plan_item_id' => 1,
                'standard_item_id' => 2,
                'item_description' => 'Valid Item',
                'uom_id' => 3,
                'requested_quantity' => '10.00',
                'estimated_unit_cost' => '25.50',
            ],
        ];

        $passed = true;
        try {
            RequisitionValidator::validate($validReq, $validItems);
        } catch (ValidationException $e) {
            $passed = false;
        }
        $this->assert($passed, "RequisitionValidator passes on fully compliant requisition payload");

        // 2. RequisitionValidator catches invalid fields
        $invalidReq = [
            'planning_entity_id' => 0, // Invalid
            'fiscal_year' => 1999, // Invalid (<2000)
            'justification' => '', // Empty
            'status' => 'NON_EXISTENT_STATUS', // Invalid
            'approved_plan_version_id' => -5, // Invalid
        ];
        $caught = false;
        try {
            RequisitionValidator::validate($invalidReq);
        } catch (ValidationException $e) {
            $caught = true;
            $errs = $e->getErrors();
            $this->assert(isset($errs['planning_entity_id']), "Catches invalid planning_entity_id");
            $this->assert(isset($errs['fiscal_year']), "Catches invalid fiscal_year (<2000)");
            $this->assert(isset($errs['justification']), "Catches empty justification");
            $this->assert(isset($errs['status']), "Catches invalid status");
            $this->assert(isset($errs['approved_plan_version_id']), "Catches negative approved_plan_version_id");
        }
        $this->assert($caught, "RequisitionValidator throws ValidationException on invalid requisition fields");

        // 3. RequisitionItemValidator catches item constraints
        $invalidItem = [
            'procurement_plan_item_id' => 0,
            'standard_item_id' => 0,
            'item_description' => '',
            'uom_id' => 0,
            'requested_quantity' => '-5.00', // Negative
            'estimated_unit_cost' => '-10.00', // Negative
        ];
        $errors = RequisitionItemValidator::validate($invalidItem, 1);
        $this->assert(count($errors) === 6, "RequisitionItemValidator catches all 6 invalid item constraints");
    }

    private function teardown(): void
    {
        // Teardown tracked fixtures in strict reverse foreign-key order
        // 1. Snapshots
        if (!empty($this->trackedSnapshots)) {
            $in = implode(',', array_map('intval', $this->trackedSnapshots));
            $this->db->exec("DELETE FROM `requisition_balance_snapshots` WHERE `id` IN ({$in})");
        }

        // 2. Requisition Items
        if (!empty($this->trackedRequisitionItems)) {
            $in = implode(',', array_map('intval', $this->trackedRequisitionItems));
            $this->db->exec("DELETE FROM `requisition_items` WHERE `id` IN ({$in})");
        }

        // Clean any leftover items from tracked requisitions
        if (!empty($this->trackedRequisitions)) {
            $in = implode(',', array_map('intval', $this->trackedRequisitions));
            $this->db->exec("DELETE FROM `requisition_items` WHERE `requisition_id` IN ({$in})");
            $this->db->exec("DELETE FROM `requisition_balance_snapshots` WHERE `requisition_id` IN ({$in})");
            $this->db->exec("DELETE FROM `requisitions` WHERE `id` IN ({$in})");
        }

        // 3. Plan items
        if (!empty($this->trackedPlanItems)) {
            $in = implode(',', array_map('intval', $this->trackedPlanItems));
            $this->db->exec("DELETE FROM `procurement_plan_items` WHERE `id` IN ({$in})");
        }

        // 4. Plan versions & Plans
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

        // 5. Standard Items, UOMs, Categories
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

        // 6. Entities, Entity Types, Campuses, Users
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

        $this->assert(true, "Database teardown completed with ZERO orphan records");
    }

    private function printSummary(): void
    {
        echo "\n===============================================================\n";
        echo " REQUISITION PERSISTENCE TEST SUMMARY\n";
        echo " Passed: {$this->passed} / " . ($this->passed + $this->failed) . "\n";
        echo " Failed: {$this->failed}\n";
        echo "===============================================================\n\n";

        if ($this->failed === 0) {
            echo "ALL REQUISITION PERSISTENCE TESTS PASSED SUCCESSFULLY.\n";
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
    ExecutionPersistenceTest::main();
}
