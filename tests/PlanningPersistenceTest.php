<?php

declare(strict_types=1);

/**
 * PROMIS Phase 1B Procurement Planning Persistence Test Suite
 * Procurement Management Information System
 * University of Science and Technology, Dedicated (USTED)
 *
 * Standalone CLI test runner verifying concrete PDO repository persistence,
 * database constraints, composite foreign keys, backed enums, null handling,
 * and transaction rollback integrity against physical MariaDB/MySQL engine.
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\DatabaseException;
use Promis\Src\Planning\Domain\PlanStatus;
use Promis\Src\Planning\Domain\PlanVersionStatus;
use Promis\Src\Planning\Domain\ReviewCycleStatus;
use Promis\Src\Planning\Domain\ReviewOutcome;
use Promis\Src\Planning\Domain\ReviewQuarter;
use Promis\Src\Planning\Domain\TargetQuarter;
use Promis\Src\Planning\Repository\PlanReviewCycleRepository;
use Promis\Src\Planning\Repository\PlanRevisionRecordRepository;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepository;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepository;

final class PlanningPersistenceTest
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

    // Repositories
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
    private int $fixtureEntityId = 0;
    private int $fixtureEntity2Id = 0;
    private int $fixtureCategoryId = 0;
    private int $fixtureUomId = 0;
    private int $fixtureStandardItemId = 0;

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
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS Phase 1B Procurement Planning Persistence Test Suite\n";
        echo " University of Science and Technology, Dedicated (USTED)\n";
        echo " Relational Engine: MariaDB 10.4+ / MySQL 8.x\n";
        echo " PHP Version: " . PHP_VERSION . "\n";
        echo "===============================================================\n\n";

        try {
            // 0. Environment & Database Check
            $this->testDatabaseConnectivity();

            // 1. Fixture Setup
            $this->setupBaseFixtures();

            // 2. ProcurementPlanRepository Persistence Tests
            $this->testPlanInsertAndFindById();
            $this->testPlanFindByPlanNumber();
            $this->testPlanFindByEntityAndYear();
            $this->testPlanFindByEntity();
            $this->testPlanUpdateStatus();
            $this->testPlanSetCurrentVersion();
            $this->testPlanNullHandling();
            $this->testPlanDuplicatePlanNumberRejection();
            $this->testPlanDuplicateEntityYearRejection();
            $this->testPlanForeignKeyConstraintRejection();

            // 3. ProcurementPlanVersionRepository Persistence Tests
            $this->testVersionInsertAndFindById();
            $this->testVersionFindByPlanId();
            $this->testVersionFindByPlanAndVersion();
            $this->testVersionFindLatestVersion();
            $this->testVersionUpdateStatus();
            $this->testVersionUpdateTotalCost();
            $this->testVersionDuplicateVersionNumberRejection();
            $this->testVersionForeignKeyRejection();
            $this->testVersionCheckConstraintRejection();

            // 4. ProcurementPlanItemRepository Persistence Tests
            $this->testItemInsertAndFindById();
            $this->testItemFindByVersionId();
            $this->testItemCreateBatch();
            $this->testItemDeleteByVersionId();
            $this->testItemCheckConstraintRejection();
            $this->testItemForeignKeyRejection();

            // 5. PlanReviewCycleRepository Persistence Tests
            $this->testReviewCycleInsertAndFindById();
            $this->testReviewCycleFindByPlanQuarter();
            $this->testReviewCycleFindByPlanId();
            $this->testReviewCycleCompleteReview();
            $this->testReviewCycleDuplicateQuarterRejection();
            $this->testReviewCycleCompositeForeignKeyRejection();

            // 6. PlanRevisionRecordRepository Persistence Tests
            $this->testRevisionRecordInsertAndFindById();
            $this->testRevisionRecordFindByPlanId();
            $this->testRevisionRecordFindByNewVersionId();
            $this->testRevisionRecordMarkApproved();
            $this->testRevisionRecordCompositeForeignKeyRejection();

            // 7. Transaction Integrity & Rollback Tests
            $this->testTransactionRollbackOnFailure();
            $this->testTransactionCommitSuccess();

        } finally {
            // 8. Strict Cleanup
            $this->tearDownAllFixtures();
        }

        // Summary report
        echo "\n===============================================================\n";
        echo " PLANNING PERSISTENCE TEST SUMMARY\n";
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
            echo "\nALL PROCUREMENT PLANNING PERSISTENCE TESTS PASSED.\n";
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
    // 0. Database Check & Fixtures
    // =========================================================================

    private function testDatabaseConnectivity(): void
    {
        $ping = Connection::ping();
        $this->assert($ping === true, 'Database ping succeeds and PDO connection is active');

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->assert($driver === 'mysql', "Database driver is PDO MySQL ({$driver})");

        $emulate = $this->db->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $this->assert(empty($emulate) || $emulate === false, 'Native prepared statements are enforced (EMULATE_PREPARES is false)');
    }

    private function setupBaseFixtures(): void
    {
        $time = time();
        $rnd = mt_rand(1000, 9999);

        // 1. Users
        $this->fixtureUserId = $this->insertUser("planner_{$time}_{$rnd}", "planner_{$time}_{$rnd}@usted.edu.gh");
        $this->fixtureApproverId = $this->insertUser("approver_{$time}_{$rnd}", "approver_{$time}_{$rnd}@usted.edu.gh");

        // 2. Campus
        $this->fixtureCampusId = $this->insertCampus("CMP_{$rnd}", "Main Campus {$rnd}");

        // 3. Entity Type
        $this->fixtureEntityTypeId = $this->insertEntityType("DEPT_{$rnd}", "Academic Department {$rnd}");

        // 4. Planning Entities (~62 entities in USTED)
        $this->fixtureEntityId = $this->insertPlanningEntity("ENT_CS_{$rnd}", "Department of Computer Science {$rnd}", $this->fixtureEntityTypeId, $this->fixtureCampusId, $this->fixtureUserId);
        $this->fixtureEntity2Id = $this->insertPlanningEntity("ENT_EE_{$rnd}", "Department of Electrical Engineering {$rnd}", $this->fixtureEntityTypeId, $this->fixtureCampusId, $this->fixtureUserId);

        // 5. Catalogue Master Data
        $this->fixtureCategoryId = $this->insertCategory("CAT_IT_{$rnd}", "Information Technology Equipment {$rnd}");
        $this->fixtureUomId = $this->insertUom("PCS_{$rnd}", "Pieces {$rnd}");
        $this->fixtureStandardItemId = $this->insertStandardItem("ITEM_LAPTOP_{$rnd}", "High-End Workstation Laptop {$rnd}", $this->fixtureCategoryId, $this->fixtureUomId, $this->fixtureUserId);

        $this->assert($this->fixtureEntityId > 0 && $this->fixtureStandardItemId > 0, 'Base prerequisite relational fixtures seeded successfully');
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

    private function insertStandardItem(string $code, string $name, int $catId, int $uomId, int $userId): int
    {
        $sql = "INSERT INTO `standard_items` (`item_code`, `item_name`, `category_id`, `default_uom_id`, `estimated_unit_price`, `created_at`, `created_by`)
                VALUES (:c, :n, :cat, :uom, 4500.00, NOW(), :cb)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'c' => $code,
            'n' => $name,
            'cat' => $catId,
            'uom' => $uomId,
            'cb' => $userId,
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedStandardItems[] = $id;
        return $id;
    }

    // =========================================================================
    // 2. ProcurementPlanRepository Tests
    // =========================================================================

    private function testPlanInsertAndFindById(): void
    {
        $planNumber = 'PLAN-' . uniqid('TEST_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'current_version_id' => null,
            'status' => PlanStatus::DRAFT,
            'created_by' => $this->fixtureUserId,
        ]);

        $this->trackedPlans[] = $planId;
        $this->assert($planId > 0, "ProcurementPlanRepository::create returns positive surrogate ID ({$planId})");

        $dto = $this->planRepo->findById($planId);
        $this->assert($dto !== null, 'ProcurementPlanRepository::findById retrieves created plan record');
        $this->assert($dto->planNumber === $planNumber, "Plan planNumber matches inserted ({$planNumber})");
        $this->assert($dto->planningEntityId === $this->fixtureEntityId, 'Plan planningEntityId matches inserted');
        $this->assert($dto->fiscalYear === 2026, 'Plan fiscalYear is 2026');
        $this->assert($dto->status === PlanStatus::DRAFT, 'Plan status is PlanStatus::DRAFT enum');
        $this->assert($dto->currentVersionId === null, 'Plan currentVersionId is initially null');
    }

    private function testPlanFindByPlanNumber(): void
    {
        $planNumber = 'PLAN-' . uniqid('NUM_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2027,
            'current_version_id' => null,
            'status' => 'DRAFT',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $dto = $this->planRepo->findByPlanNumber($planNumber);
        $this->assert($dto !== null && $dto->id === $planId, 'ProcurementPlanRepository::findByPlanNumber finds plan by natural key');

        $missing = $this->planRepo->findByPlanNumber('NON_EXISTENT_PLAN_NUM');
        $this->assert($missing === null, 'ProcurementPlanRepository::findByPlanNumber returns null for missing plan number');
    }

    private function testPlanFindByEntityAndYear(): void
    {
        $planNumber = 'PLAN-' . uniqid('EY_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2028,
            'status' => 'DRAFT',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $dto = $this->planRepo->findByEntityAndYear($this->fixtureEntityId, 2028);
        $this->assert($dto !== null && $dto->id === $planId, 'ProcurementPlanRepository::findByEntityAndYear finds plan by compound business key');

        $missing = $this->planRepo->findByEntityAndYear($this->fixtureEntityId, 2099);
        $this->assert($missing === null, 'ProcurementPlanRepository::findByEntityAndYear returns null for unallocated fiscal year');
    }

    private function testPlanFindByEntity(): void
    {
        $plans = $this->planRepo->findByEntity($this->fixtureEntityId);
        $this->assert(is_array($plans) && count($plans) >= 3, 'ProcurementPlanRepository::findByEntity returns list of plans for entity');
        $this->assert($plans[0]->fiscalYear >= $plans[1]->fiscalYear, 'findByEntity orders plans by fiscal_year DESC');
    }

    private function testPlanUpdateStatus(): void
    {
        $planNumber = 'PLAN-' . uniqid('STAT_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2029,
            'status' => PlanStatus::DRAFT->value,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $updated = $this->planRepo->updateStatus($planId, PlanStatus::SUBMITTED->value, $this->fixtureUserId);
        $this->assert($updated === true, 'ProcurementPlanRepository::updateStatus returns true on successful update');

        $dto = $this->planRepo->findById($planId);
        $this->assert($dto->status === PlanStatus::SUBMITTED, 'Plan status transitioned to PlanStatus::SUBMITTED');
        $this->assert($dto->updatedBy === $this->fixtureUserId, 'Plan updatedBy stamped with updater ID');
        $this->assert($dto->updatedAt !== null, 'Plan updatedAt timestamp is populated');
    }

    private function testPlanSetCurrentVersion(): void
    {
        $planNumber = 'PLAN-' . uniqid('CV_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2030,
            'status' => 'DRAFT',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        // Insert version
        $versionId = $this->versionRepo->create([
            'procurement_plan_id' => $planId,
            'version_number' => '1.0',
            'status' => PlanVersionStatus::DRAFT->value,
            'total_estimated_cost' => 5000.00,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedVersions[] = $versionId;

        $set = $this->planRepo->setCurrentVersion($planId, $versionId, $this->fixtureUserId);
        $this->assert($set === true, 'ProcurementPlanRepository::setCurrentVersion returns true');

        $dto = $this->planRepo->findById($planId);
        $this->assert($dto->currentVersionId === $versionId, "Plan currentVersionId updated to version ID ({$versionId})");
    }

    private function testPlanNullHandling(): void
    {
        $planNumber = 'PLAN-' . uniqid('NULL_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2031,
            'current_version_id' => null,
            'status' => 'DRAFT',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $dto = $this->planRepo->findById($planId);
        $this->assert($dto->currentVersionId === null, 'Nullable current_version_id correctly stored as null');
        $this->assert($dto->updatedAt === null, 'Nullable updated_at correctly stored as null');
        $this->assert($dto->updatedBy === null, 'Nullable updated_by correctly stored as null');
    }

    private function testPlanDuplicatePlanNumberRejection(): void
    {
        $planNumber = 'PLAN-DUP-' . uniqid();
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2032,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $caught = false;
        try {
            $this->planRepo->create([
                'plan_number' => $planNumber,
                'planning_entity_id' => $this->fixtureEntity2Id,
                'fiscal_year' => 2032,
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $caught = true;
            $this->assert($e->getPrevious() instanceof PDOException, 'Underlying PDOException retained in DatabaseException');
        }
        $this->assert($caught, 'Database engine rejects duplicate plan_number with unique constraint violation');
    }

    private function testPlanDuplicateEntityYearRejection(): void
    {
        $caught = false;
        try {
            $this->planRepo->create([
                'plan_number' => 'PLAN-' . uniqid('DUP_EY_'),
                'planning_entity_id' => $this->fixtureEntityId,
                'fiscal_year' => 2026, // Already created in testPlanInsertAndFindById
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Database engine rejects duplicate (planning_entity_id, fiscal_year) via uq_entity_fiscal_year');
    }

    private function testPlanForeignKeyConstraintRejection(): void
    {
        $caught = false;
        try {
            $this->planRepo->create([
                'plan_number' => 'PLAN-' . uniqid('BAD_FK_'),
                'planning_entity_id' => 999999, // Non-existent entity ID
                'fiscal_year' => 2040,
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Database engine enforces fk_procurement_plans_planning_entity_id and rejects invalid entity');
    }

    // =========================================================================
    // 3. ProcurementPlanVersionRepository Tests
    // =========================================================================

    private function testVersionInsertAndFindById(): void
    {
        $planNumber = 'PLAN-' . uniqid('VER_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2033,
            'status' => 'DRAFT',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $versionId = $this->versionRepo->create([
            'procurement_plan_id' => $planId,
            'version_number' => '1.0',
            'status' => PlanVersionStatus::DRAFT,
            'total_estimated_cost' => 12500.50,
            'revision_reason' => 'Baseline plan',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedVersions[] = $versionId;

        $this->assert($versionId > 0, "ProcurementPlanVersionRepository::create returns version ID ({$versionId})");

        $dto = $this->versionRepo->findById($versionId);
        $this->assert($dto !== null, 'ProcurementPlanVersionRepository::findById retrieves version snapshot');
        $this->assert($dto->procurementPlanId === $planId, 'Version procurementPlanId matches parent plan');
        $this->assert($dto->versionNumber === '1.0', 'Version number is "1.0"');
        $this->assert($dto->status === PlanVersionStatus::DRAFT, 'Version status is PlanVersionStatus::DRAFT');
        $this->assert($dto->totalEstimatedCost === '12500.50', 'Version totalEstimatedCost accurately stored as 12500.50');
        $this->assert($dto->revisionReason === 'Baseline plan', 'Version revisionReason stored accurately');
        $this->assert($dto->approvalDate === null, 'Version approvalDate is initially null');
        $this->assert($dto->approvedByUserId === null, 'Version approvedByUserId is initially null');
    }

    private function testVersionFindByPlanId(): void
    {
        $planNumber = 'PLAN-' . uniqid('VPL_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2034,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create([
            'procurement_plan_id' => $planId,
            'version_number' => '1.0',
            'total_estimated_cost' => 1000.00,
            'created_by' => $this->fixtureUserId,
        ]);
        $v2 = $this->versionRepo->create([
            'procurement_plan_id' => $planId,
            'version_number' => '2.0',
            'total_estimated_cost' => 2000.00,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedVersions[] = $v1;
        $this->trackedVersions[] = $v2;

        $versions = $this->versionRepo->findByPlanId($planId);
        $this->assert(count($versions) === 2, 'ProcurementPlanVersionRepository::findByPlanId returns both versions');
        $this->assert($versions[0]->versionNumber === '1.0', 'First version in list is 1.0');
        $this->assert($versions[1]->versionNumber === '2.0', 'Second version in list is 2.0');
    }

    private function testVersionFindByPlanAndVersion(): void
    {
        $planNumber = 'PLAN-' . uniqid('VPV_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2035,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create([
            'procurement_plan_id' => $planId,
            'version_number' => '1.0',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedVersions[] = $v1;

        $dto = $this->versionRepo->findByPlanAndVersion($planId, '1.0');
        $this->assert($dto !== null && $dto->id === $v1, 'findByPlanAndVersion retrieves exact version snapshot');

        $missing = $this->versionRepo->findByPlanAndVersion($planId, '9.9');
        $this->assert($missing === null, 'findByPlanAndVersion returns null for non-existent version');
    }

    private function testVersionFindLatestVersion(): void
    {
        $planNumber = 'PLAN-' . uniqid('VLAT_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2036,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $v2 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '2.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;
        $this->trackedVersions[] = $v2;

        $latest = $this->versionRepo->findLatestVersion($planId);
        $this->assert($latest !== null && $latest->id === $v2, 'findLatestVersion returns most recent version 2.0');
    }

    private function testVersionUpdateStatus(): void
    {
        $planNumber = 'PLAN-' . uniqid('VSTAT_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2037,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $appDate = date('Y-m-d H:i:s');
        $updated = $this->versionRepo->updateStatus($v1, PlanVersionStatus::APPROVED->value, $this->fixtureApproverId, $appDate);
        $this->assert($updated === true, 'updateStatus returns true');

        $dto = $this->versionRepo->findById($v1);
        $this->assert($dto->status === PlanVersionStatus::APPROVED, 'Version status transitioned to APPROVED');
        $this->assert($dto->approvedByUserId === $this->fixtureApproverId, 'Version approved_by_user_id stamped');
        $this->assert($dto->approvalDate !== null, 'Version approval_date stamped');
    }

    private function testVersionUpdateTotalCost(): void
    {
        $planNumber = 'PLAN-' . uniqid('VTC_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2038,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'total_estimated_cost' => 100.00, 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $res = $this->versionRepo->updateTotalCost($v1, '98765.43');
        $this->assert($res === true, 'updateTotalCost returns true');

        $dto = $this->versionRepo->findById($v1);
        $this->assert($dto->totalEstimatedCost === '98765.43', 'total_estimated_cost successfully updated to 98765.43');
    }

    private function testVersionDuplicateVersionNumberRejection(): void
    {
        $planNumber = 'PLAN-' . uniqid('VDUP_');
        $planId = $this->planRepo->create([
            'plan_number' => $planNumber,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2039,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $caught = false;
        try {
            $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        } catch (DatabaseException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Database engine rejects duplicate version_number on same plan via uq_plan_version');
    }

    private function testVersionForeignKeyRejection(): void
    {
        $caught = false;
        try {
            $this->versionRepo->create(['procurement_plan_id' => 999999, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        } catch (DatabaseException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Database engine rejects version with invalid procurement_plan_id via foreign key');
    }

    private function testVersionCheckConstraintRejection(): void
    {
        $planNumber = 'PLAN-' . uniqid('VCHK_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2041, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $caught = false;
        try {
            $this->versionRepo->create([
                'procurement_plan_id' => $planId,
                'version_number' => '1.0',
                'total_estimated_cost' => -500.00, // Negative cost violates CHECK (total_estimated_cost >= 0.00)
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Database engine rejects negative total_estimated_cost via CHECK constraint');
    }

    // =========================================================================
    // 4. ProcurementPlanItemRepository Tests
    // =========================================================================

    private function testItemInsertAndFindById(): void
    {
        $planNumber = 'PLAN-' . uniqid('ITEM_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2042, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $itemId = $this->itemRepo->create([
            'plan_version_id' => $v1,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Dell Workstations for AI Lab',
            'category_id' => $this->fixtureCategoryId,
            'uom_id' => $this->fixtureUomId,
            'planned_quantity' => 10.00,
            'estimated_unit_cost' => 4500.00,
            'target_quarter' => TargetQuarter::Q1,
            'funding_source' => 'IGF',
            'justification' => 'Mandatory computing equipment upgrade',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedItems[] = $itemId;

        $this->assert($itemId > 0, "ProcurementPlanItemRepository::create returns item ID ({$itemId})");

        $dto = $this->itemRepo->findById($itemId);
        $this->assert($dto !== null, 'ProcurementPlanItemRepository::findById retrieves line item');
        $this->assert($dto->plannedQuantity === '10.00', 'plannedQuantity stored accurately (10.00)');
        $this->assert($dto->estimatedUnitCost === '4500.00', 'estimatedUnitCost stored accurately (4500.00)');
        $this->assert($dto->estimatedTotalCost === '45000.00', 'estimatedTotalCost auto-calculated (10 * 4500 = 45000.00)');
        $this->assert($dto->targetQuarter === TargetQuarter::Q1, 'targetQuarter mapped to TargetQuarter::Q1 enum');
        $this->assert($dto->categoryName !== null, 'categoryName hydrated via catalogue JOIN');
        $this->assert($dto->uomCode !== null, 'uomCode hydrated via catalogue JOIN');
        $this->assert($dto->itemCode !== null, 'itemCode hydrated via catalogue JOIN');
    }

    private function testItemFindByVersionId(): void
    {
        $planNumber = 'PLAN-' . uniqid('ITM_VER_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2043, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $i1 = $this->itemRepo->create([
            'plan_version_id' => $v1,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Line 1',
            'category_id' => $this->fixtureCategoryId,
            'uom_id' => $this->fixtureUomId,
            'planned_quantity' => 2.0,
            'estimated_unit_cost' => 100.0,
            'target_quarter' => 'Q1',
            'funding_source' => 'GOG',
            'created_by' => $this->fixtureUserId,
        ]);
        $i2 = $this->itemRepo->create([
            'plan_version_id' => $v1,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Line 2',
            'category_id' => $this->fixtureCategoryId,
            'uom_id' => $this->fixtureUomId,
            'planned_quantity' => 3.0,
            'estimated_unit_cost' => 200.0,
            'target_quarter' => 'Q2',
            'funding_source' => 'IGF',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedItems[] = $i1;
        $this->trackedItems[] = $i2;

        $items = $this->itemRepo->findByVersionId($v1);
        $this->assert(count($items) === 2, 'findByVersionId returns both items for version');
        $this->assert($items[0]->itemDescription === 'Line 1', 'Line 1 retrieved first');
        $this->assert($items[1]->itemDescription === 'Line 2', 'Line 2 retrieved second');
    }

    private function testItemCreateBatch(): void
    {
        $planNumber = 'PLAN-' . uniqid('BATCH_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2044, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $batch = [
            [
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'Batch Item 1',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 5,
                'estimated_unit_cost' => 10,
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
            ],
            [
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'Batch Item 2',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 15,
                'estimated_unit_cost' => 20,
                'target_quarter' => 'Q2',
                'funding_source' => 'DONOR',
            ],
            [
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'Batch Item 3',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 25,
                'estimated_unit_cost' => 30,
                'target_quarter' => 'Q3',
                'funding_source' => 'GOG',
            ],
        ];

        $insertedCount = $this->itemRepo->createBatch($v1, $batch, $this->fixtureUserId);
        $this->assert($insertedCount === 3, "createBatch returned 3 inserted rows ({$insertedCount})");

        $items = $this->itemRepo->findByVersionId($v1);
        $this->assert(count($items) === 3, 'All 3 batch items retrieved from database');
        foreach ($items as $it) {
            $this->trackedItems[] = $it->id;
        }
    }

    private function testItemDeleteByVersionId(): void
    {
        $planNumber = 'PLAN-' . uniqid('DEL_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2045, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $this->itemRepo->create([
            'plan_version_id' => $v1,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'To be deleted',
            'category_id' => $this->fixtureCategoryId,
            'uom_id' => $this->fixtureUomId,
            'planned_quantity' => 1,
            'estimated_unit_cost' => 10,
            'target_quarter' => 'Q1',
            'funding_source' => 'IGF',
            'created_by' => $this->fixtureUserId,
        ]);

        $deleted = $this->itemRepo->deleteByVersionId($v1);
        $this->assert($deleted === 1, 'deleteByVersionId returns count of deleted rows (1)');

        $itemsAfter = $this->itemRepo->findByVersionId($v1);
        $this->assert(count($itemsAfter) === 0, 'No items remain for version after deleteByVersionId');
    }

    private function testItemCheckConstraintRejection(): void
    {
        $planNumber = 'PLAN-' . uniqid('ICHK_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2046, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $caught = false;
        try {
            $this->itemRepo->create([
                'plan_version_id' => $v1,
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'Negative Quantity',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => -10.0, // Violates CHECK (planned_quantity >= 0.00)
                'estimated_unit_cost' => 10.0,
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Database engine rejects negative planned_quantity via CHECK constraint');
    }

    private function testItemForeignKeyRejection(): void
    {
        $caught = false;
        try {
            $this->itemRepo->create([
                'plan_version_id' => 999999, // Invalid version ID
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'Invalid Version',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 1.0,
                'estimated_unit_cost' => 10.0,
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Database engine rejects item with invalid plan_version_id via foreign key');
    }

    // =========================================================================
    // 5. PlanReviewCycleRepository Tests
    // =========================================================================

    private function testReviewCycleInsertAndFindById(): void
    {
        $planNumber = 'PLAN-' . uniqid('REV_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2047, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $cycleId = $this->cycleRepo->create([
            'procurement_plan_id' => $planId,
            'active_version_id' => $v1,
            'fiscal_year' => 2047,
            'review_quarter' => ReviewQuarter::Q1,
            'review_status' => ReviewCycleStatus::PENDING,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedCycles[] = $cycleId;

        $this->assert($cycleId > 0, "PlanReviewCycleRepository::create returns cycle ID ({$cycleId})");

        $dto = $this->cycleRepo->findById($cycleId);
        $this->assert($dto !== null, 'PlanReviewCycleRepository::findById retrieves review cycle record');
        $this->assert($dto->procurementPlanId === $planId, 'Cycle procurementPlanId matches parent plan');
        $this->assert($dto->activeVersionId === $v1, 'Cycle activeVersionId matches v1');
        $this->assert($dto->reviewQuarter === ReviewQuarter::Q1, 'Cycle reviewQuarter matches ReviewQuarter::Q1 enum');
        $this->assert($dto->reviewStatus === ReviewCycleStatus::PENDING, 'Cycle reviewStatus matches ReviewCycleStatus::PENDING enum');
        $this->assert($dto->reviewOutcome === null, 'Initial reviewOutcome is null');
    }

    private function testReviewCycleFindByPlanQuarter(): void
    {
        $planNumber = 'PLAN-' . uniqid('RC_PQ_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2048, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $cycleId = $this->cycleRepo->create([
            'procurement_plan_id' => $planId,
            'active_version_id' => $v1,
            'fiscal_year' => 2048,
            'review_quarter' => 'Q2',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedCycles[] = $cycleId;

        $dto = $this->cycleRepo->findByPlanQuarter($planId, 2048, 'Q2');
        $this->assert($dto !== null && $dto->id === $cycleId, 'findByPlanQuarter finds cycle by (planId, year, quarter)');

        $missing = $this->cycleRepo->findByPlanQuarter($planId, 2048, 'Q4');
        $this->assert($missing === null, 'findByPlanQuarter returns null for uninitiated quarter');
    }

    private function testReviewCycleFindByPlanId(): void
    {
        $planNumber = 'PLAN-' . uniqid('RC_LST_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2049, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $c1 = $this->cycleRepo->create(['procurement_plan_id' => $planId, 'active_version_id' => $v1, 'fiscal_year' => 2049, 'review_quarter' => 'Q1', 'created_by' => $this->fixtureUserId]);
        $c2 = $this->cycleRepo->create(['procurement_plan_id' => $planId, 'active_version_id' => $v1, 'fiscal_year' => 2049, 'review_quarter' => 'Q2', 'created_by' => $this->fixtureUserId]);
        $this->trackedCycles[] = $c1;
        $this->trackedCycles[] = $c2;

        $cycles = $this->cycleRepo->findByPlanId($planId);
        $this->assert(count($cycles) === 2, 'findByPlanId returns both review cycles for plan');
    }

    private function testReviewCycleCompleteReview(): void
    {
        $planNumber = 'PLAN-' . uniqid('RC_CMP_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2050, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $c1 = $this->cycleRepo->create([
            'procurement_plan_id' => $planId,
            'active_version_id' => $v1,
            'fiscal_year' => 2050,
            'review_quarter' => 'Q1',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedCycles[] = $c1;

        $completed = $this->cycleRepo->completeReview(
            $c1,
            ReviewOutcome::REVISION_REQUIRED->value,
            'Inflation requires 10% upward price adjustment on computing goods',
            $this->fixtureApproverId
        );
        $this->assert($completed === true, 'completeReview returns true on completion');

        $dto = $this->cycleRepo->findById($c1);
        $this->assert($dto->reviewStatus === ReviewCycleStatus::COMPLETED, 'reviewStatus transitioned to COMPLETED');
        $this->assert($dto->reviewOutcome === ReviewOutcome::REVISION_REQUIRED, 'reviewOutcome stamped as REVISION_REQUIRED');
        $this->assert($dto->reviewedByUserId === $this->fixtureApproverId, 'reviewedByUserId stamped');
        $this->assert($dto->completedAt !== null, 'completedAt timestamp recorded');
        $this->assert(str_contains($dto->reviewNotes, 'Inflation'), 'reviewNotes preserved accurately');
    }

    private function testReviewCycleDuplicateQuarterRejection(): void
    {
        $planNumber = 'PLAN-' . uniqid('RC_DUP_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2051, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;

        $c1 = $this->cycleRepo->create(['procurement_plan_id' => $planId, 'active_version_id' => $v1, 'fiscal_year' => 2051, 'review_quarter' => 'Q1', 'created_by' => $this->fixtureUserId]);
        $this->trackedCycles[] = $c1;

        $caught = false;
        try {
            $this->cycleRepo->create(['procurement_plan_id' => $planId, 'active_version_id' => $v1, 'fiscal_year' => 2051, 'review_quarter' => 'Q1', 'created_by' => $this->fixtureUserId]);
        } catch (DatabaseException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Database engine rejects duplicate review cycle for same (plan, year, quarter) via uq_plan_quarter_review');
    }

    private function testReviewCycleCompositeForeignKeyRejection(): void
    {
        // Create Plan A with Version A1
        $planA = $this->planRepo->create(['plan_number' => 'PLAN-A-' . uniqid(), 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2052, 'created_by' => $this->fixtureUserId]);
        $vA1 = $this->versionRepo->create(['procurement_plan_id' => $planA, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planA;
        $this->trackedVersions[] = $vA1;

        // Create Plan B with Version B1
        $planB = $this->planRepo->create(['plan_number' => 'PLAN-B-' . uniqid(), 'planning_entity_id' => $this->fixtureEntity2Id, 'fiscal_year' => 2052, 'created_by' => $this->fixtureUserId]);
        $vB1 = $this->versionRepo->create(['procurement_plan_id' => $planB, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planB;
        $this->trackedVersions[] = $vB1;

        // Attempt illegal state: Create review cycle on Plan A referencing Version B1 (which belongs to Plan B!)
        $caught = false;
        try {
            $this->cycleRepo->create([
                'procurement_plan_id' => $planA,
                'active_version_id' => $vB1, // Belongs to Plan B!
                'fiscal_year' => 2052,
                'review_quarter' => 'Q1',
                'created_by' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Database engine rejects cross-plan version reference via composite FK fk_plan_review_cycles_active_version_plan');
    }

    // =========================================================================
    // 6. PlanRevisionRecordRepository Tests
    // =========================================================================

    private function testRevisionRecordInsertAndFindById(): void
    {
        $planNumber = 'PLAN-' . uniqid('REV_REC_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2053, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $v2 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '2.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;
        $this->trackedVersions[] = $v2;

        $revId = $this->revisionRepo->create([
            'procurement_plan_id' => $planId,
            'review_cycle_id' => null,
            'prior_version_id' => $v1,
            'new_version_id' => $v2,
            'revision_justification' => 'Inter-quarter emergency requisition adjustments approved by Vice-Chancellor',
            'submitted_by_user_id' => $this->fixtureUserId,
        ]);
        $this->trackedRevisions[] = $revId;

        $this->assert($revId > 0, "PlanRevisionRecordRepository::create returns revision ID ({$revId})");

        $dto = $this->revisionRepo->findById($revId);
        $this->assert($dto !== null, 'PlanRevisionRecordRepository::findById retrieves revision record');
        $this->assert($dto->procurementPlanId === $planId, 'Revision procurementPlanId matches plan');
        $this->assert($dto->priorVersionId === $v1, 'Revision priorVersionId is v1');
        $this->assert($dto->newVersionId === $v2, 'Revision newVersionId is v2');
        $this->assert($dto->approvedByUserId === null, 'approvedByUserId is initially null');
    }

    private function testRevisionRecordFindByPlanId(): void
    {
        $planNumber = 'PLAN-' . uniqid('REV_PL_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2054, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $v2 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '2.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;
        $this->trackedVersions[] = $v2;

        $revId = $this->revisionRepo->create([
            'procurement_plan_id' => $planId,
            'prior_version_id' => $v1,
            'new_version_id' => $v2,
            'revision_justification' => 'Q2 Revision',
            'submitted_by_user_id' => $this->fixtureUserId,
        ]);
        $this->trackedRevisions[] = $revId;

        $records = $this->revisionRepo->findByPlanId($planId);
        $this->assert(count($records) === 1, 'findByPlanId returns revision records for plan');
        $this->assert($records[0]->id === $revId, 'Retrieved record matches created revision');
    }

    private function testRevisionRecordFindByNewVersionId(): void
    {
        $planNumber = 'PLAN-' . uniqid('REV_NV_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2055, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $v2 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '2.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;
        $this->trackedVersions[] = $v2;

        $revId = $this->revisionRepo->create([
            'procurement_plan_id' => $planId,
            'prior_version_id' => $v1,
            'new_version_id' => $v2,
            'revision_justification' => 'New Version Provenance Test',
            'submitted_by_user_id' => $this->fixtureUserId,
        ]);
        $this->trackedRevisions[] = $revId;

        $dto = $this->revisionRepo->findByNewVersionId($v2);
        $this->assert($dto !== null && $dto->id === $revId, 'findByNewVersionId traces origin revision record');
    }

    private function testRevisionRecordMarkApproved(): void
    {
        $planNumber = 'PLAN-' . uniqid('REV_APP_');
        $planId = $this->planRepo->create(['plan_number' => $planNumber, 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2056, 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planId;

        $v1 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $v2 = $this->versionRepo->create(['procurement_plan_id' => $planId, 'version_number' => '2.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedVersions[] = $v1;
        $this->trackedVersions[] = $v2;

        $revId = $this->revisionRepo->create([
            'procurement_plan_id' => $planId,
            'prior_version_id' => $v1,
            'new_version_id' => $v2,
            'revision_justification' => 'Approval Workflow Test',
            'submitted_by_user_id' => $this->fixtureUserId,
        ]);
        $this->trackedRevisions[] = $revId;

        $approved = $this->revisionRepo->markApproved($revId, $this->fixtureApproverId);
        $this->assert($approved === true, 'markApproved returns true');

        $dto = $this->revisionRepo->findById($revId);
        $this->assert($dto->approvedByUserId === $this->fixtureApproverId, 'approvedByUserId stamped with approver ID');
        $this->assert($dto->approvedAt !== null, 'approvedAt timestamp stamped');
    }

    private function testRevisionRecordCompositeForeignKeyRejection(): void
    {
        // Create Plan A with Version A1
        $planA = $this->planRepo->create(['plan_number' => 'PLAN-RA-' . uniqid(), 'planning_entity_id' => $this->fixtureEntityId, 'fiscal_year' => 2057, 'created_by' => $this->fixtureUserId]);
        $vA1 = $this->versionRepo->create(['procurement_plan_id' => $planA, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $vA2 = $this->versionRepo->create(['procurement_plan_id' => $planA, 'version_number' => '2.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planA;
        $this->trackedVersions[] = $vA1;
        $this->trackedVersions[] = $vA2;

        // Create Plan B with Version B1
        $planB = $this->planRepo->create(['plan_number' => 'PLAN-RB-' . uniqid(), 'planning_entity_id' => $this->fixtureEntity2Id, 'fiscal_year' => 2057, 'created_by' => $this->fixtureUserId]);
        $vB1 = $this->versionRepo->create(['procurement_plan_id' => $planB, 'version_number' => '1.0', 'created_by' => $this->fixtureUserId]);
        $this->trackedPlans[] = $planB;
        $this->trackedVersions[] = $vB1;

        // Attempt illegal state: Create revision record on Plan A linking prior_version_id = vB1 (belongs to Plan B!)
        $caught = false;
        try {
            $this->revisionRepo->create([
                'procurement_plan_id' => $planA,
                'prior_version_id' => $vB1, // Belongs to Plan B!
                'new_version_id' => $vA2,
                'revision_justification' => 'Cross plan attack attempt',
                'submitted_by_user_id' => $this->fixtureUserId,
            ]);
        } catch (DatabaseException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Database engine rejects cross-plan version reference via composite FK fk_plan_revision_records_prior_version_plan');
    }

    // =========================================================================
    // 7. Transaction Rollback & Commit Integrity Tests
    // =========================================================================

    private function testTransactionRollbackOnFailure(): void
    {
        $planNumber = 'PLAN-ROLLBACK-' . uniqid();
        $rollbackPlanId = 0;
        $rollbackVersionId = 0;

        $this->db->beginTransaction();
        try {
            // Step 1: Create Plan
            $rollbackPlanId = $this->planRepo->create([
                'plan_number' => $planNumber,
                'planning_entity_id' => $this->fixtureEntityId,
                'fiscal_year' => 2058,
                'created_by' => $this->fixtureUserId,
            ]);

            // Step 2: Create Version
            $rollbackVersionId = $this->versionRepo->create([
                'procurement_plan_id' => $rollbackPlanId,
                'version_number' => '1.0',
                'created_by' => $this->fixtureUserId,
            ]);

            // Step 3: Simulate failure during item formulation
            throw new RuntimeException('Simulated unrecoverable failure during plan batch assembly');

        } catch (RuntimeException $e) {
            $this->db->rollBack();
        }

        // Verify that neither plan nor version was committed to the database
        $checkPlan = $this->planRepo->findByPlanNumber($planNumber);
        $this->assert($checkPlan === null, 'Transaction rollback completely discards uncommitted procurement_plan record');

        $checkVersion = $this->versionRepo->findById($rollbackVersionId);
        $this->assert($checkVersion === null, 'Transaction rollback completely discards uncommitted procurement_plan_version record');
    }

    private function testTransactionCommitSuccess(): void
    {
        $planNumber = 'PLAN-COMMIT-' . uniqid();
        $committedPlanId = 0;
        $committedVersionId = 0;
        $committedItemId = 0;

        $this->db->beginTransaction();
        try {
            // Step 1: Create Plan
            $committedPlanId = $this->planRepo->create([
                'plan_number' => $planNumber,
                'planning_entity_id' => $this->fixtureEntityId,
                'fiscal_year' => 2059,
                'created_by' => $this->fixtureUserId,
            ]);

            // Step 2: Create Version 1.0
            $committedVersionId = $this->versionRepo->create([
                'procurement_plan_id' => $committedPlanId,
                'version_number' => '1.0',
                'total_estimated_cost' => 9000.00,
                'created_by' => $this->fixtureUserId,
            ]);

            // Step 3: Create Line Item
            $committedItemId = $this->itemRepo->create([
                'plan_version_id' => $committedVersionId,
                'standard_item_id' => $this->fixtureStandardItemId,
                'item_description' => 'Committed Workstation Item',
                'category_id' => $this->fixtureCategoryId,
                'uom_id' => $this->fixtureUomId,
                'planned_quantity' => 2.0,
                'estimated_unit_cost' => 4500.0,
                'target_quarter' => 'Q1',
                'funding_source' => 'IGF',
                'created_by' => $this->fixtureUserId,
            ]);

            // Step 4: Link active version pointer
            $this->planRepo->setCurrentVersion($committedPlanId, $committedVersionId, $this->fixtureUserId);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->trackedItems[] = $committedItemId;
        $this->trackedVersions[] = $committedVersionId;
        $this->trackedPlans[] = $committedPlanId;

        $planDto = $this->planRepo->findById($committedPlanId);
        $versionDto = $this->versionRepo->findById($committedVersionId);
        $itemDto = $this->itemRepo->findById($committedItemId);

        $this->assert($planDto !== null && $planDto->currentVersionId === $committedVersionId, 'Transaction commit atomically persists plan header and current version pointer');
        $this->assert($versionDto !== null && $versionDto->totalEstimatedCost === '9000.00', 'Transaction commit atomically persists version snapshot');
        $this->assert($itemDto !== null && $itemDto->planVersionId === $committedVersionId, 'Transaction commit atomically persists version line item');
    }

    // =========================================================================
    // 8. Strict Teardown / Zero Orphan Records
    // =========================================================================

    private function tearDownAllFixtures(): void
    {
        // Break circular FKs on procurement_plans first: set current_version_id = NULL
        if (!empty($this->trackedPlans)) {
            $inPlans = implode(',', array_map('intval', $this->trackedPlans));
            $this->db->exec("UPDATE `procurement_plans` SET `current_version_id` = NULL WHERE `id` IN ({$inPlans})");
        }

        // Delete items
        if (!empty($this->trackedItems)) {
            $in = implode(',', array_map('intval', $this->trackedItems));
            $this->db->exec("DELETE FROM `procurement_plan_items` WHERE `id` IN ({$in})");
        }

        // Delete revisions
        if (!empty($this->trackedRevisions)) {
            $in = implode(',', array_map('intval', $this->trackedRevisions));
            $this->db->exec("DELETE FROM `plan_revision_records` WHERE `id` IN ({$in})");
        }

        // Delete review cycles
        if (!empty($this->trackedCycles)) {
            $in = implode(',', array_map('intval', $this->trackedCycles));
            $this->db->exec("DELETE FROM `plan_review_cycles` WHERE `id` IN ({$in})");
        }

        // Delete versions
        if (!empty($this->trackedVersions)) {
            $in = implode(',', array_map('intval', $this->trackedVersions));
            $this->db->exec("DELETE FROM `procurement_plan_versions` WHERE `id` IN ({$in})");
        }

        // Delete plans
        if (!empty($this->trackedPlans)) {
            $in = implode(',', array_map('intval', $this->trackedPlans));
            $this->db->exec("DELETE FROM `procurement_plan_items` WHERE `plan_version_id` IN (SELECT `id` FROM `procurement_plan_versions` WHERE `procurement_plan_id` IN ({$in}))");
            $this->db->exec("DELETE FROM `plan_revision_records` WHERE `procurement_plan_id` IN ({$in})");
            $this->db->exec("DELETE FROM `plan_review_cycles` WHERE `procurement_plan_id` IN ({$in})");
            $this->db->exec("DELETE FROM `procurement_plan_versions` WHERE `procurement_plan_id` IN ({$in})");
            $this->db->exec("DELETE FROM `procurement_plans` WHERE `id` IN ({$in})");
        }

        // Delete standard items
        if (!empty($this->trackedStandardItems)) {
            $in = implode(',', array_map('intval', $this->trackedStandardItems));
            $this->db->exec("DELETE FROM `standard_items` WHERE `id` IN ({$in})");
        }

        // Delete UOMs & Categories
        if (!empty($this->trackedUoms)) {
            $in = implode(',', array_map('intval', $this->trackedUoms));
            $this->db->exec("DELETE FROM `units_of_measure` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedCategories)) {
            $in = implode(',', array_map('intval', $this->trackedCategories));
            $this->db->exec("DELETE FROM `item_categories` WHERE `id` IN ({$in})");
        }

        // Delete planning entities
        if (!empty($this->trackedEntities)) {
            $in = implode(',', array_map('intval', $this->trackedEntities));
            $this->db->exec("DELETE FROM `planning_entities` WHERE `id` IN ({$in})");
        }

        // Delete entity types & campuses
        if (!empty($this->trackedEntityTypes)) {
            $in = implode(',', array_map('intval', $this->trackedEntityTypes));
            $this->db->exec("DELETE FROM `entity_types` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedCampuses)) {
            $in = implode(',', array_map('intval', $this->trackedCampuses));
            $this->db->exec("DELETE FROM `campuses` WHERE `id` IN ({$in})");
        }

        // Delete users
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
}

// Execute tests
PlanningPersistenceTest::main();
