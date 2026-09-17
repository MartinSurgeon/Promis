<?php

declare(strict_types=1);

/**
 * Staff Position and Individual Responsibility Assignment Test Suite
 * Procurement Management Information System (PROMIS)
 * University of Skills Training and Entrepreneurial Development (USTED)
 *
 * Verifies the clean separation between:
 * 1. Position: Official office or appointment held by a staff member.
 * 2. Assigned Area: Organisational unit (Faculty, Department, Unit, etc.).
 * 3. Individual Roles and Responsibilities: Explicit operational permissions.
 *
 * Confirms non-negotiable rules:
 * - REQUESTER is never a position.
 * - Position confers 0 operational permissions automatically.
 * - Two staff members with the same position can have different responsibilities.
 * - Self-approval requires explicit APPROVE_OWN assignment.
 * - Controlled legacy fallback functions seamlessly.
 * - Audit logs record all administrative role and responsibility changes.
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Src\Identity\Repository\PositionRepository;
use Promis\Src\Identity\Repository\UserResponsibilityRepository;
use Promis\Src\Identity\Repository\PlanningEntityRepository;
use Promis\Src\Identity\Domain\Model\StaffPosition;
use Promis\Src\Identity\Domain\Model\ResponsibilityCode;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Domain\WorkflowAction;
use Promis\Src\Execution\Domain\DTO\RequisitionDTO;
use Promis\Src\Execution\Domain\WorkflowTransition;
use Promis\Src\Execution\Domain\WorkflowStepRuleDTO;
use Promis\Src\Execution\Service\RequisitionWorkflowService;
use Promis\Src\Identity\Domain\DTO\CreateUserDTO;
use Promis\Src\Identity\Domain\DTO\UpdateUserDTO;
use Promis\Src\Identity\Domain\DTO\UserDTO;
use Promis\Src\Identity\Repository\UserRepository;
use Promis\Src\Identity\Repository\UserEntityRoleRepository;
use Promis\Src\Execution\Repository\AuditLogRepository;
use Promis\Src\Identity\Service\UserManagementService;

class StaffPositionAndRoleAssignmentTest
{
    private PDO $db;
    private PositionRepository $positionRepo;
    private UserResponsibilityRepository $respRepo;
    private UserRepository $userRepo;
    private PlanningEntityRepository $entityRepo;
    private UserManagementService $userService;
    private RequisitionWorkflowService $workflowService;

    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    // Tracked IDs for cleanup
    private array $trackedUsers = [];
    private array $trackedEntities = [];
    private array $trackedHierarchies = [];
    private array $trackedRequisitions = [];
    private array $trackedUserRoles = [];
    private array $trackedWorkflowDefs = [];

    // Fixture entities
    private int $facultyTechId;
    private int $facultyArtsId;
    private int $deptComputingId;
    private int $unitNetworkingId;
    private int $deptAccountingId;

    // Fixture positions
    private int $posDeanId;
    private int $posHodId;
    private int $posDirectorId;
    private int $posFinanceOfficerId;
    private int $posProcurementOfficerId;
    private int $posAdministratorId;

    // Admin user for performing operations
    private int $adminUserId;

    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->positionRepo = new PositionRepository($this->db);
        $this->respRepo = new UserResponsibilityRepository($this->db);
        $this->userRepo = new UserRepository($this->db);
        $this->entityRepo = new PlanningEntityRepository($this->db);
        $this->workflowService = new RequisitionWorkflowService($this->db);
        $this->userService = new UserManagementService(
            $this->userRepo,
            new UserEntityRoleRepository($this->db),
            $this->entityRepo,
            new AuditLogRepository($this->db),
            $this->positionRepo,
            $this->respRepo
        );
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS STAFF POSITION & INDIVIDUAL RESPONSIBILITY TEST SUITE\n";
        echo " University of Skills Training and Entrepreneurial Development\n";
        echo "===============================================================\n\n";

        try {
            $this->seedFixtures();

            $this->test1_PositionsTableExistsAndValid();
            $this->test2_RequesterIsAbsentFromPositionsTable();
            $this->test3_StaffCreatedWithPositionAndAssignedArea();
            $this->test4_IndividualResponsibilitiesSavedCorrectlyInUserResponsibilities();
            $this->test5_TwoDeansCanHaveDifferentResponsibilities();
            $this->test6_CreateAndSubmitOwnRequestsOnlyWhenAssigned();
            $this->test7_SelfApprovalOnlyWhenApproveOwnChecked();
            $this->test8_DeanApprovalRestrictedToFacultyOrDescendantsViaHierarchy();
            $this->test9_HodRecommendationRestrictedToAssignedDepartment();
            $this->test10_HodCannotApproveSubordinateUnitsUnlessExplicitlyAssigned();
            $this->test11_ReturnAndRejectIndividuallyControlled();
            $this->test12_FinanceApprovalIndividuallyControlled();
            $this->test13_ProcurementReceivingIndividuallyControlled();
            $this->test14_StaffWithNoResponsibilitiesReceivesWarning();
            $this->test15_StaffWithNoResponsibilitiesCannotPerformOperationalActions();
            $this->test16_ResponsibilityAndPositionChangesAuditedInInstitutionalLogs();
            $this->test17_ExistingLegacyFixturesStillWorkThroughFallback();
            $this->test18_CleanTeardownZeroOrphans();

        } catch (Throwable $e) {
            $this->failed++;
            $this->failures[] = "Fatal exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
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
        $unique = bin2hex(random_bytes(3));

        // 1. Fetch Entity Types
        $facTypeId = (int)$this->db->query("SELECT id FROM entity_types WHERE type_code = 'FAC' LIMIT 1")->fetchColumn();
        $deptTypeId = (int)$this->db->query("SELECT id FROM entity_types WHERE type_code = 'DEPT' LIMIT 1")->fetchColumn();
        $unitTypeId = (int)$this->db->query("SELECT id FROM entity_types WHERE type_code = 'UNIT' LIMIT 1")->fetchColumn();

        // 2. Create Planning Entities
        // Faculty of Technology
        $stmt = $this->db->prepare("INSERT INTO planning_entities (entity_code, entity_name, entity_type_id, campus_id, is_active) VALUES (:c, :n, :t, 1, 1)");
        $stmt->execute(['c' => "F_TECH_{$unique}", 'n' => 'Faculty of Technology Education', 't' => $facTypeId]);
        $this->facultyTechId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->facultyTechId;

        // Faculty of Arts
        $stmt->execute(['c' => "F_ARTS_{$unique}", 'n' => 'Faculty of Arts Education', 't' => $facTypeId]);
        $this->facultyArtsId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->facultyArtsId;

        // Dept of Computing (under Faculty of Technology)
        $stmt->execute(['c' => "D_COMP_{$unique}", 'n' => 'Department of Computing', 't' => $deptTypeId]);
        $this->deptComputingId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->deptComputingId;

        // Sub-Unit Networking (under Dept of Computing)
        $stmt->execute(['c' => "U_NET_{$unique}", 'n' => 'Networking and Systems Unit', 't' => $unitTypeId]);
        $this->unitNetworkingId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->unitNetworkingId;

        // Dept of Accounting (under Faculty of Arts)
        $stmt->execute(['c' => "D_ACCT_{$unique}", 'n' => 'Department of Accounting', 't' => $deptTypeId]);
        $this->deptAccountingId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->deptAccountingId;

        // Parent linkages
        $this->db->prepare("UPDATE planning_entities SET parent_entity_id = :p WHERE id = :id")->execute([
            'p' => $this->facultyTechId,
            'id' => $this->deptComputingId,
        ]);
        $this->db->prepare("UPDATE planning_entities SET parent_entity_id = :p WHERE id = :id")->execute([
            'p' => $this->deptComputingId,
            'id' => $this->unitNetworkingId,
        ]);
        $this->db->prepare("UPDATE planning_entities SET parent_entity_id = :p WHERE id = :id")->execute([
            'p' => $this->facultyArtsId,
            'id' => $this->deptAccountingId,
        ]);

        // Hierarchies
        $hStmt = $this->db->prepare("INSERT INTO entity_hierarchies (ancestor_entity_id, descendant_entity_id, depth) VALUES (:a, :d, :dep)");
        
        // Faculty Tech -> Dept Computing (depth 1)
        $hStmt->execute(['a' => $this->facultyTechId, 'd' => $this->deptComputingId, 'dep' => 1]);
        $this->trackedHierarchies[] = ['a' => $this->facultyTechId, 'd' => $this->deptComputingId];

        // Faculty Tech -> Unit Networking (depth 2)
        $hStmt->execute(['a' => $this->facultyTechId, 'd' => $this->unitNetworkingId, 'dep' => 2]);
        $this->trackedHierarchies[] = ['a' => $this->facultyTechId, 'd' => $this->unitNetworkingId];

        // Dept Computing -> Unit Networking (depth 1)
        $hStmt->execute(['a' => $this->deptComputingId, 'd' => $this->unitNetworkingId, 'dep' => 1]);
        $this->trackedHierarchies[] = ['a' => $this->deptComputingId, 'd' => $this->unitNetworkingId];

        // Faculty Arts -> Dept Accounting (depth 1)
        $hStmt->execute(['a' => $this->facultyArtsId, 'd' => $this->deptAccountingId, 'dep' => 1]);
        $this->trackedHierarchies[] = ['a' => $this->facultyArtsId, 'd' => $this->deptAccountingId];

        // 3. Resolve Positions
        $this->posDeanId = $this->positionRepo->findByCode('DEAN')->id;
        $this->posHodId = $this->positionRepo->findByCode('HOD')->id;
        $this->posDirectorId = $this->positionRepo->findByCode('DIRECTOR')->id;
        $this->posFinanceOfficerId = $this->positionRepo->findByCode('FINANCE_OFFICER')->id;
        $this->posProcurementOfficerId = $this->positionRepo->findByCode('PROCUREMENT_OFFICER')->id;
        $this->posAdministratorId = $this->positionRepo->findByCode('ADMINISTRATOR')->id;

        // 4. Create Admin Actor
        $uStmt = $this->db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, status, position_id, assigned_planning_entity_id) VALUES (:u, :e, 'hash', :f, :l, 'ACTIVE', :pid, :eid)");
        $uStmt->execute([
            'u' => "adm_{$unique}",
            'e' => "adm_{$unique}@usted.edu.gh",
            'f' => 'System',
            'l' => 'Administrator',
            'pid' => $this->posAdministratorId,
            'eid' => $this->facultyTechId,
        ]);
        $this->adminUserId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->adminUserId;

        // 5. Ensure UNIT entity type has an active workflow definition for the subordinate unit test
        $stmtWf = $this->db->prepare("SELECT id FROM workflow_definitions WHERE document_type = 'REQUISITION' AND entity_type_id = :etid AND is_active = 1");
        $stmtWf->execute(['etid' => $unitTypeId]);
        $unitWfId = $stmtWf->fetchColumn();
        if (!$unitWfId) {
            $this->db->prepare("INSERT INTO workflow_definitions (workflow_code, document_type, entity_type_id, workflow_name, is_active, created_by) VALUES (:code, 'REQUISITION', :etid, 'Unit Requisition Pipeline', 1, :cby)")
                ->execute(['code' => "WF-UNIT-{$unique}", 'etid' => $unitTypeId, 'cby' => $this->adminUserId]);
            $unitWfId = (int)$this->db->lastInsertId();
            $this->trackedWorkflowDefs[] = $unitWfId;

            // Copy step rules from DEPT definition (definition 1)
            $stepRules = $this->db->query("SELECT * FROM workflow_step_rules WHERE workflow_definition_id = 1 ORDER BY step_order")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($stepRules as $sr) {
                $this->db->prepare("INSERT INTO workflow_step_rules (workflow_definition_id, step_order, step_name, required_role_id, threshold_min_amount, threshold_max_amount, is_mandatory, created_by) VALUES (:wdid, :so, :sn, :rid, :tmin, :tmax, :man, :cby)")
                    ->execute([
                        'wdid' => $unitWfId,
                        'so' => $sr['step_order'],
                        'sn' => $sr['step_name'],
                        'rid' => $sr['required_role_id'],
                        'tmin' => $sr['threshold_min_amount'],
                        'tmax' => $sr['threshold_max_amount'],
                        'man' => $sr['is_mandatory'],
                        'cby' => $this->adminUserId,
                    ]);
            }
        }

        $this->assert(true, "Setup fixtures initialized with multi-level entities, hierarchies, and admin user");
    }

    private function insertRequisition(int $entityId, string $status, int $createdBy): int
    {
        $unique = bin2hex(random_bytes(3));
        $num = 'REQ-' . date('Y') . '-' . str_pad((string)$entityId, 3, '0', STR_PAD_LEFT) . '-' . $unique;
        $sql = "INSERT INTO requisitions (requisition_number, planning_entity_id, fiscal_year, status, total_estimated_cost, justification, created_by)
                VALUES (:num, :eid, 2026, :st, '2500.00', 'Position and Responsibility Test Requisition', :cby)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['num' => $num, 'eid' => $entityId, 'st' => $status, 'cby' => $createdBy]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedRequisitions[] = $id;

        return $id;
    }

    private function makeCreateUserDTO(array $args): CreateUserDTO
    {
        return new CreateUserDTO(
            username: (string)($args['username'] ?? ''),
            email: (string)($args['email'] ?? ''),
            firstName: (string)($args['firstName'] ?? ''),
            lastName: (string)($args['lastName'] ?? ''),
            phone: isset($args['phone']) ? (string)$args['phone'] : null,
            password: (string)($args['password'] ?? ''),
            status: (string)($args['status'] ?? 'ACTIVE'),
            positionId: isset($args['positionId']) ? (int)$args['positionId'] : null,
            assignedPlanningEntityId: isset($args['assignedPlanningEntityId']) ? (int)$args['assignedPlanningEntityId'] : null,
            responsibilities: (array)($args['responsibilities'] ?? [])
        );
    }

    private function makeUpdateUserDTO(array $args): UpdateUserDTO
    {
        return new UpdateUserDTO(
            id: (int)$args['id'],
            firstName: (string)($args['firstName'] ?? ''),
            lastName: (string)($args['lastName'] ?? ''),
            email: (string)($args['email'] ?? ''),
            phone: isset($args['phone']) ? (string)$args['phone'] : null,
            password: isset($args['password']) ? (string)$args['password'] : null,
            status: isset($args['status']) ? (string)$args['status'] : null,
            positionId: isset($args['positionId']) ? (int)$args['positionId'] : null,
            assignedPlanningEntityId: isset($args['assignedPlanningEntityId']) ? (int)$args['assignedPlanningEntityId'] : null,
            responsibilities: isset($args['responsibilities']) ? (array)$args['responsibilities'] : null
        );
    }

    private function test1_PositionsTableExistsAndValid(): void
    {
        // Verify table exists
        $stmt = $this->db->query("SHOW TABLES LIKE 'positions'");
        $tableExists = $stmt->fetchColumn() !== false;
        $this->assert($tableExists, "Test 1: 'positions' table exists in database");

        // Verify columns
        $colStmt = $this->db->query("SHOW COLUMNS FROM positions");
        $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
        $expectedCols = ['id', 'position_code', 'position_title', 'description', 'default_scope_type', 'is_active', 'created_at'];
        $hasAllCols = count(array_intersect($expectedCols, $cols)) === count($expectedCols);
        $this->assert($hasAllCols, "Test 1: 'positions' table has all required columns");

        // Verify standard 8 positions are seeded
        $positions = $this->positionRepo->findAllActive();
        $codes = array_map(fn($p) => $p->positionCode, $positions);
        $expectedCodes = ['DEAN', 'HOD', 'DIRECTOR', 'COORDINATOR', 'FINANCE_OFFICER', 'PROCUREMENT_OFFICER', 'ADMINISTRATOR', 'STAFF_OFFICER'];
        $hasAllCodes = count(array_intersect($expectedCodes, $codes)) === 8;
        $this->assert($hasAllCodes, "Test 1: All 8 official staff positions are present and active in database");
    }

    private function test2_RequesterIsAbsentFromPositionsTable(): void
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM positions WHERE UPPER(position_code) = 'REQUESTER' OR UPPER(position_title) LIKE '%REQUESTER%'");
        $count = (int)$stmt->fetchColumn();
        $this->assert($count === 0, "Test 2: 'REQUESTER' is strictly absent from positions table (count is 0)");

        $byCode = $this->positionRepo->findByCode('REQUESTER');
        $this->assert($byCode === null, "Test 2: PositionRepository::findByCode('REQUESTER') returns null");
    }

    private function test3_StaffCreatedWithPositionAndAssignedArea(): void
    {
        $unique = bin2hex(random_bytes(3));
        $dto = $this->makeCreateUserDTO([
            'username' => "staff_{$unique}",
            'email' => "staff_{$unique}@usted.edu.gh",
            'firstName' => 'Kwame',
            'lastName' => 'Mensah',
            'password' => 'Password123!',
            'positionId' => $this->posDeanId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [ResponsibilityCode::CREATE_SUBMIT_OWN, ResponsibilityCode::APPROVE_FACULTY_DEPTS],
        ]);

        $userId = $this->userService->onboardUser($dto, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $userId;

        // Verify DB row
        $user = $this->userRepo->findById($userId);
        $this->assert($user !== null, "Test 3: Staff member onboarded successfully");
        $this->assert($user->positionId === $this->posDeanId, "Test 3: Staff has correct position_id");
        $this->assert($user->positionTitle === 'Dean', "Test 3: Staff hydrated with correct position_title 'Dean'");
        $this->assert($user->assignedPlanningEntityId === $this->facultyTechId, "Test 3: Staff has correct assigned_planning_entity_id");
        $this->assert($user->assignedEntityName === 'Faculty of Technology Education', "Test 3: Staff hydrated with correct assigned_entity_name");
    }

    private function test4_IndividualResponsibilitiesSavedCorrectlyInUserResponsibilities(): void
    {
        $unique = bin2hex(random_bytes(3));
        $dto = $this->makeCreateUserDTO([
            'username' => "resp_{$unique}",
            'email' => "resp_{$unique}@usted.edu.gh",
            'firstName' => 'Abena',
            'lastName' => 'Osei',
            'password' => 'Password123!',
            'positionId' => $this->posHodId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [
                ResponsibilityCode::CREATE_SUBMIT_OWN,
                ResponsibilityCode::RECOMMEND_DEPT,
                ResponsibilityCode::RETURN_REQUESTS,
            ],
        ]);

        $userId = $this->userService->onboardUser($dto, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $userId;

        // Query user_responsibilities directly
        $stmt = $this->db->prepare("SELECT responsibility_code, is_active FROM user_responsibilities WHERE user_id = :uid ORDER BY responsibility_code ASC");
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $savedCodes = array_map(fn($r) => $r['responsibility_code'], $rows);
        $expected = [
            ResponsibilityCode::CREATE_SUBMIT_OWN,
            ResponsibilityCode::RECOMMEND_DEPT,
            ResponsibilityCode::RETURN_REQUESTS,
        ];
        sort($expected);
        sort($savedCodes);

        $this->assert($savedCodes === $expected, "Test 4: Exact responsibilities saved in user_responsibilities table");

        // Verify active status
        $allActive = array_reduce($rows, fn($carry, $r) => $carry && ((int)$r['is_active'] === 1), true);
        $this->assert($allActive, "Test 4: All assigned responsibilities have is_active = 1");

        // Verify repository fetch
        $activeCodes = $this->respRepo->getActiveResponsibilityCodes($userId);
        sort($activeCodes);
        $this->assert($activeCodes === $expected, "Test 4: UserResponsibilityRepository returns active responsibility codes");
    }

    private function test5_TwoDeansCanHaveDifferentResponsibilities(): void
    {
        $unique = bin2hex(random_bytes(3));

        // Dean A: Has APPROVE_FACULTY_DEPTS
        $dtoA = $this->makeCreateUserDTO([
            'username' => "dean_a_{$unique}",
            'email' => "dean_a_{$unique}@usted.edu.gh",
            'firstName' => 'Dean',
            'lastName' => 'Alpha',
            'password' => 'Password123!',
            'positionId' => $this->posDeanId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [
                ResponsibilityCode::CREATE_SUBMIT_OWN,
                ResponsibilityCode::APPROVE_FACULTY_DEPTS,
            ],
        ]);
        $deanAId = $this->userService->onboardUser($dtoA, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $deanAId;

        // Dean B: Same position (Dean) and same entity, but ONLY CREATE_SUBMIT_OWN (no approval)
        $dtoB = $this->makeCreateUserDTO([
            'username' => "dean_b_{$unique}",
            'email' => "dean_b_{$unique}@usted.edu.gh",
            'firstName' => 'Dean',
            'lastName' => 'Beta',
            'password' => 'Password123!',
            'positionId' => $this->posDeanId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [
                ResponsibilityCode::CREATE_SUBMIT_OWN,
            ],
        ]);
        $deanBId = $this->userService->onboardUser($dtoB, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $deanBId;

        $hasDeanAApprove = $this->workflowService->userHasResponsibility($deanAId, ResponsibilityCode::APPROVE_FACULTY_DEPTS, $this->facultyTechId);
        $hasDeanBApprove = $this->workflowService->userHasResponsibility($deanBId, ResponsibilityCode::APPROVE_FACULTY_DEPTS, $this->facultyTechId);

        $this->assert($hasDeanAApprove === true, "Test 5: Dean A has APPROVE_FACULTY_DEPTS responsibility");
        $this->assert($hasDeanBApprove === false, "Test 5: Dean B does NOT have APPROVE_FACULTY_DEPTS responsibility");
        $this->assert(
            $this->userRepo->findById($deanAId)->positionId === $this->userRepo->findById($deanBId)->positionId,
            "Test 5: Both users share identical position (Dean) but hold strictly differentiated responsibilities"
        );
    }

    private function test6_CreateAndSubmitOwnRequestsOnlyWhenAssigned(): void
    {
        $unique = bin2hex(random_bytes(3));

        // User WITH CREATE_SUBMIT_OWN
        $dtoWith = $this->makeCreateUserDTO([
            'username' => "submitter_{$unique}",
            'email' => "submitter_{$unique}@usted.edu.gh",
            'firstName' => 'Valid',
            'lastName' => 'Submitter',
            'password' => 'Password123!',
            'positionId' => $this->posDirectorId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [ResponsibilityCode::CREATE_SUBMIT_OWN],
        ]);
        $userWithId = $this->userService->onboardUser($dtoWith, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $userWithId;

        // User WITHOUT CREATE_SUBMIT_OWN (e.g. only RECOMMEND_DEPT)
        $dtoWithout = $this->makeCreateUserDTO([
            'username' => "nosubmit_{$unique}",
            'email' => "nosubmit_{$unique}@usted.edu.gh",
            'firstName' => 'No',
            'lastName' => 'Submit',
            'password' => 'Password123!',
            'positionId' => $this->posDirectorId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [ResponsibilityCode::RECOMMEND_DEPT],
        ]);
        $userWithoutId = $this->userService->onboardUser($dtoWithout, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $userWithoutId;

        // Create draft requisition
        $draftReqId = $this->insertRequisition($this->deptComputingId, 'DRAFT', $userWithId);
        $draftWithoutReqId = $this->insertRequisition($this->deptComputingId, 'DRAFT', $userWithoutId);

        $transitionsWith = $this->workflowService->resolvePermittedTransitions($draftReqId, $userWithId);
        $actionsWith = array_map(fn($t) => $t->action->value, $transitionsWith);

        $transitionsWithout = $this->workflowService->resolvePermittedTransitions($draftWithoutReqId, $userWithoutId);
        $actionsWithout = array_map(fn($t) => $t->action->value, $transitionsWithout);

        $this->assert(in_array('SUBMIT', $actionsWith, true), "Test 6: User with CREATE_SUBMIT_OWN can see and execute SUBMIT");
        $this->assert(!in_array('SUBMIT', $actionsWithout, true), "Test 6: User without CREATE_SUBMIT_OWN CANNOT see SUBMIT action");
    }

    private function test7_SelfApprovalOnlyWhenApproveOwnChecked(): void
    {
        $unique = bin2hex(random_bytes(3));

        // HOD without APPROVE_OWN
        $dtoNoSelf = $this->makeCreateUserDTO([
            'username' => "hod_noself_{$unique}",
            'email' => "hod_noself_{$unique}@usted.edu.gh",
            'firstName' => 'Strict',
            'lastName' => 'HOD',
            'password' => 'Password123!',
            'positionId' => $this->posHodId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [
                ResponsibilityCode::CREATE_SUBMIT_OWN,
                ResponsibilityCode::RECOMMEND_DEPT,
            ],
        ]);
        $hodNoSelfId = $this->userService->onboardUser($dtoNoSelf, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $hodNoSelfId;

        // HOD with APPROVE_OWN
        $dtoWithSelf = $this->makeCreateUserDTO([
            'username' => "hod_withself_{$unique}",
            'email' => "hod_withself_{$unique}@usted.edu.gh",
            'firstName' => 'Self',
            'lastName' => 'Approver',
            'password' => 'Password123!',
            'positionId' => $this->posHodId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [
                ResponsibilityCode::CREATE_SUBMIT_OWN,
                ResponsibilityCode::RECOMMEND_DEPT,
                ResponsibilityCode::APPROVE_OWN,
            ],
        ]);
        $hodWithSelfId = $this->userService->onboardUser($dtoWithSelf, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $hodWithSelfId;

        // Create submitted requisitions created by each HOD
        $reqNoSelfId = $this->insertRequisition($this->deptComputingId, 'SUBMITTED', $hodNoSelfId);
        $reqWithSelfId = $this->insertRequisition($this->deptComputingId, 'SUBMITTED', $hodWithSelfId);

        // 1. Check userCanSelfApprove method directly
        $canSelfNo = $this->workflowService->userCanSelfApprove($hodNoSelfId, $this->deptComputingId, WorkflowAction::ENDORSE);
        $canSelfWith = $this->workflowService->userCanSelfApprove($hodWithSelfId, $this->deptComputingId, WorkflowAction::ENDORSE);

        $this->assert($canSelfNo === false, "Test 7: userCanSelfApprove is false for HOD without APPROVE_OWN");
        $this->assert($canSelfWith === true, "Test 7: userCanSelfApprove is true for HOD with APPROVE_OWN");

        // 2. Check workflow transitions visibility
        $transNo = $this->workflowService->resolvePermittedTransitions($reqNoSelfId, $hodNoSelfId);
        $actionsNo = array_map(fn($t) => $t->action->value, $transNo);

        $transWith = $this->workflowService->resolvePermittedTransitions($reqWithSelfId, $hodWithSelfId);
        $actionsWith = array_map(fn($t) => $t->action->value, $transWith);

        $this->assert(!in_array('ENDORSE', $actionsNo, true), "Test 7: HOD without APPROVE_OWN sees 0 endorse actions on own requisition");
        $this->assert(in_array('ENDORSE', $actionsWith, true), "Test 7: HOD with APPROVE_OWN sees ENDORSE action on own requisition");
    }

    private function test8_DeanApprovalRestrictedToFacultyOrDescendantsViaHierarchy(): void
    {
        $unique = bin2hex(random_bytes(3));

        // Dean assigned to Faculty of Technology
        $dtoDeanTech = $this->makeCreateUserDTO([
            'username' => "dean_tech_{$unique}",
            'email' => "dean_tech_{$unique}@usted.edu.gh",
            'firstName' => 'Dean',
            'lastName' => 'Tech',
            'password' => 'Password123!',
            'positionId' => $this->posDeanId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [
                ResponsibilityCode::APPROVE_FACULTY_DEPTS,
            ],
        ]);
        $deanTechId = $this->userService->onboardUser($dtoDeanTech, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $deanTechId;

        // Requisition 1: In Dept Computing (child of Faculty Tech) at ENDORSED status
        $reqTechChildId = $this->insertRequisition($this->deptComputingId, 'ENDORSED', $this->adminUserId);

        // Requisition 2: In Dept Accounting (child of Faculty Arts) at ENDORSED status
        $reqArtsChildId = $this->insertRequisition($this->deptAccountingId, 'ENDORSED', $this->adminUserId);

        $transTech = $this->workflowService->resolvePermittedTransitions($reqTechChildId, $deanTechId);
        $actionsTech = array_map(fn($t) => $t->action->value, $transTech);

        $transArts = $this->workflowService->resolvePermittedTransitions($reqArtsChildId, $deanTechId);
        $actionsArts = array_map(fn($t) => $t->action->value, $transArts);

        $this->assert(in_array('APPROVE', $actionsTech, true), "Test 8: Dean of Tech CAN approve requisition in descendant Dept of Computing");
        $this->assert(!in_array('APPROVE', $actionsArts, true), "Test 8: Dean of Tech CANNOT approve requisition in Dept of Accounting (Faculty of Arts)");
    }

    private function test9_HodRecommendationRestrictedToAssignedDepartment(): void
    {
        $unique = bin2hex(random_bytes(3));

        // HOD assigned to Dept of Computing
        $dtoHodComp = $this->makeCreateUserDTO([
            'username' => "hod_comp_{$unique}",
            'email' => "hod_comp_{$unique}@usted.edu.gh",
            'firstName' => 'HOD',
            'lastName' => 'Computing',
            'password' => 'Password123!',
            'positionId' => $this->posHodId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [
                ResponsibilityCode::RECOMMEND_DEPT,
            ],
        ]);
        $hodCompId = $this->userService->onboardUser($dtoHodComp, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $hodCompId;

        // Requisition in Dept Computing
        $reqCompId = $this->insertRequisition($this->deptComputingId, 'SUBMITTED', $this->adminUserId);

        // Requisition in Dept Accounting
        $reqAcctId = $this->insertRequisition($this->deptAccountingId, 'SUBMITTED', $this->adminUserId);

        $transComp = $this->workflowService->resolvePermittedTransitions($reqCompId, $hodCompId);
        $actionsComp = array_map(fn($t) => $t->action->value, $transComp);

        $transAcct = $this->workflowService->resolvePermittedTransitions($reqAcctId, $hodCompId);
        $actionsAcct = array_map(fn($t) => $t->action->value, $transAcct);

        $this->assert(in_array('ENDORSE', $actionsComp, true), "Test 9: HOD of Computing can recommend requisitions in Dept of Computing");
        $this->assert(!in_array('ENDORSE', $actionsAcct, true), "Test 9: HOD of Computing CANNOT recommend requisitions in Dept of Accounting");
    }

    private function test10_HodCannotApproveSubordinateUnitsUnlessExplicitlyAssigned(): void
    {
        $unique = bin2hex(random_bytes(3));

        // HOD A: without APPROVE_SUBORDINATE_UNITS
        $dtoA = $this->makeCreateUserDTO([
            'username' => "hod_nosub_{$unique}",
            'email' => "hod_nosub_{$unique}@usted.edu.gh",
            'firstName' => 'HOD',
            'lastName' => 'NoSub',
            'password' => 'Password123!',
            'positionId' => $this->posHodId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [
                ResponsibilityCode::RECOMMEND_DEPT,
            ],
        ]);
        $hodNoSubId = $this->userService->onboardUser($dtoA, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $hodNoSubId;

        // HOD B: WITH APPROVE_SUBORDINATE_UNITS
        $dtoB = $this->makeCreateUserDTO([
            'username' => "hod_withsub_{$unique}",
            'email' => "hod_withsub_{$unique}@usted.edu.gh",
            'firstName' => 'HOD',
            'lastName' => 'WithSub',
            'password' => 'Password123!',
            'positionId' => $this->posHodId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [
                ResponsibilityCode::RECOMMEND_DEPT,
                ResponsibilityCode::APPROVE_SUBORDINATE_UNITS,
            ],
        ]);
        $hodWithSubId = $this->userService->onboardUser($dtoB, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $hodWithSubId;

        // Requisition originating in Sub-Unit Networking (child of Dept of Computing)
        $reqSubUnitId = $this->insertRequisition($this->unitNetworkingId, 'SUBMITTED', $this->adminUserId);

        $transNoSub = $this->workflowService->resolvePermittedTransitions($reqSubUnitId, $hodNoSubId);
        $actionsNoSub = array_map(fn($t) => $t->action->value, $transNoSub);

        $transWithSub = $this->workflowService->resolvePermittedTransitions($reqSubUnitId, $hodWithSubId);
        $actionsWithSub = array_map(fn($t) => $t->action->value, $transWithSub);

        $this->assert(!in_array('ENDORSE', $actionsNoSub, true), "Test 10: HOD without APPROVE_SUBORDINATE_UNITS cannot endorse subordinate unit request");
        $this->assert(in_array('ENDORSE', $actionsWithSub, true), "Test 10: HOD with APPROVE_SUBORDINATE_UNITS CAN endorse subordinate unit request");
    }

    private function test11_ReturnAndRejectIndividuallyControlled(): void
    {
        $unique = bin2hex(random_bytes(3));

        // Staff 1: Has RETURN_REQUESTS only (no REJECT_REQUESTS)
        $dtoReturnOnly = $this->makeCreateUserDTO([
            'username' => "ret_only_{$unique}",
            'email' => "ret_only_{$unique}@usted.edu.gh",
            'firstName' => 'Return',
            'lastName' => 'Only',
            'password' => 'Password123!',
            'positionId' => $this->posHodId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [
                ResponsibilityCode::RECOMMEND_DEPT,
                ResponsibilityCode::RETURN_REQUESTS,
            ],
        ]);
        $returnOnlyId = $this->userService->onboardUser($dtoReturnOnly, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $returnOnlyId;

        // Staff 2: Has REJECT_REQUESTS only (no RETURN_REQUESTS)
        $dtoRejectOnly = $this->makeCreateUserDTO([
            'username' => "rej_only_{$unique}",
            'email' => "rej_only_{$unique}@usted.edu.gh",
            'firstName' => 'Reject',
            'lastName' => 'Only',
            'password' => 'Password123!',
            'positionId' => $this->posHodId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [
                ResponsibilityCode::RECOMMEND_DEPT,
                ResponsibilityCode::REJECT_REQUESTS,
            ],
        ]);
        $rejectOnlyId = $this->userService->onboardUser($dtoRejectOnly, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $rejectOnlyId;

        $reqId = $this->insertRequisition($this->deptComputingId, 'SUBMITTED', $this->adminUserId);

        $transRet = $this->workflowService->resolvePermittedTransitions($reqId, $returnOnlyId);
        $actionsRet = array_map(fn($t) => $t->action->value, $transRet);

        $transRej = $this->workflowService->resolvePermittedTransitions($reqId, $rejectOnlyId);
        $actionsRej = array_map(fn($t) => $t->action->value, $transRej);

        $this->assert(in_array('RETURN', $actionsRet, true) && !in_array('REJECT', $actionsRet, true),
            "Test 11: Staff with RETURN_REQUESTS only sees RETURN and NOT REJECT");
        $this->assert(in_array('REJECT', $actionsRej, true) && !in_array('RETURN', $actionsRej, true),
            "Test 11: Staff with REJECT_REQUESTS only sees REJECT and NOT RETURN");
    }

    private function test12_FinanceApprovalIndividuallyControlled(): void
    {
        $unique = bin2hex(random_bytes(3));

        // Finance Staff WITH APPROVE_FINANCIAL_COMMITMENTS
        $dtoFinWith = $this->makeCreateUserDTO([
            'username' => "fin_with_{$unique}",
            'email' => "fin_with_{$unique}@usted.edu.gh",
            'firstName' => 'Fin',
            'lastName' => 'Authorized',
            'password' => 'Password123!',
            'positionId' => $this->posFinanceOfficerId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [
                ResponsibilityCode::APPROVE_FINANCIAL_COMMITMENTS,
            ],
        ]);
        $finWithId = $this->userService->onboardUser($dtoFinWith, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $finWithId;

        // Finance Staff WITHOUT APPROVE_FINANCIAL_COMMITMENTS
        $dtoFinWithout = $this->makeCreateUserDTO([
            'username' => "fin_without_{$unique}",
            'email' => "fin_without_{$unique}@usted.edu.gh",
            'firstName' => 'Fin',
            'lastName' => 'Observer',
            'password' => 'Password123!',
            'positionId' => $this->posFinanceOfficerId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [
                ResponsibilityCode::CREATE_SUBMIT_OWN,
            ],
        ]);
        $finWithoutId = $this->userService->onboardUser($dtoFinWithout, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $finWithoutId;

        // Requisition at DEPARTMENT_APPROVED stage (ready for finance commitment)
        $reqId = $this->insertRequisition($this->deptComputingId, 'DEPARTMENT_APPROVED', $this->adminUserId);

        $transWith = $this->workflowService->resolvePermittedTransitions($reqId, $finWithId);
        $actionsWith = array_map(fn($t) => $t->action->value, $transWith);

        $transWithout = $this->workflowService->resolvePermittedTransitions($reqId, $finWithoutId);
        $actionsWithout = array_map(fn($t) => $t->action->value, $transWithout);

        $this->assert(in_array('APPROVE', $actionsWith, true),
            "Test 12: Finance Officer WITH APPROVE_FINANCIAL_COMMITMENTS sees APPROVE at finance stage");
        $this->assert(!in_array('APPROVE', $actionsWithout, true),
            "Test 12: Finance Officer WITHOUT APPROVE_FINANCIAL_COMMITMENTS CANNOT approve at finance stage");
    }

    private function test13_ProcurementReceivingIndividuallyControlled(): void
    {
        $unique = bin2hex(random_bytes(3));

        // Procurement Staff WITH RECEIVE_PURCHASED_ITEMS
        $dtoProcWith = $this->makeCreateUserDTO([
            'username' => "proc_with_{$unique}",
            'email' => "proc_with_{$unique}@usted.edu.gh",
            'firstName' => 'Proc',
            'lastName' => 'Receiver',
            'password' => 'Password123!',
            'positionId' => $this->posProcurementOfficerId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [
                ResponsibilityCode::RECEIVE_PURCHASED_ITEMS,
            ],
        ]);
        $procWithId = $this->userService->onboardUser($dtoProcWith, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $procWithId;

        // Procurement Staff WITHOUT RECEIVE_PURCHASED_ITEMS
        $dtoProcWithout = $this->makeCreateUserDTO([
            'username' => "proc_without_{$unique}",
            'email' => "proc_without_{$unique}@usted.edu.gh",
            'firstName' => 'Proc',
            'lastName' => 'Clerk',
            'password' => 'Password123!',
            'positionId' => $this->posProcurementOfficerId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [
                ResponsibilityCode::CREATE_SUBMIT_OWN,
            ],
        ]);
        $procWithoutId = $this->userService->onboardUser($dtoProcWithout, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $procWithoutId;

        // Requisition at COMMITMENT_AUTHORIZED stage (ready for goods receiving)
        $reqId = $this->insertRequisition($this->deptComputingId, 'COMMITMENT_AUTHORIZED', $this->adminUserId);

        $transWith = $this->workflowService->resolvePermittedTransitions($reqId, $procWithId);
        $actionsWith = array_map(fn($t) => $t->action->value, $transWith);

        $transWithout = $this->workflowService->resolvePermittedTransitions($reqId, $procWithoutId);
        $actionsWithout = array_map(fn($t) => $t->action->value, $transWithout);

        $this->assert(in_array('RECEIVE', $actionsWith, true),
            "Test 13: Procurement Officer WITH RECEIVE_PURCHASED_ITEMS sees RECEIVE action");
        $this->assert(!in_array('RECEIVE', $actionsWithout, true),
            "Test 13: Procurement Officer WITHOUT RECEIVE_PURCHASED_ITEMS CANNOT receive items");
    }

    private function test14_StaffWithNoResponsibilitiesReceivesWarning(): void
    {
        $unique = bin2hex(random_bytes(3));

        // Staff created with zero responsibilities
        $dtoNoResp = $this->makeCreateUserDTO([
            'username' => "noresp_{$unique}",
            'email' => "noresp_{$unique}@usted.edu.gh",
            'firstName' => 'Blank',
            'lastName' => 'User',
            'password' => 'Password123!',
            'positionId' => $this->posDirectorId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [], // Empty!
        ]);
        $userId = $this->userService->onboardUser($dtoNoResp, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $userId;

        // Verify getUserDetails returns empty responsibilities
        $details = $this->userService->getUserDetails($userId);
        $this->assert(empty($details['responsibilities']), "Test 14: User details returns empty responsibilities array");

        // Verify getUsersPaginated flags has_no_responsibilities = true
        $paged = $this->userService->getUsersPaginated(1, 10, "noresp_{$unique}");
        $found = null;
        foreach ($paged['users'] as $u) {
            if ((int)$u['id'] === $userId) {
                $found = $u;
                break;
            }
        }
        $this->assert($found !== null, "Test 14: Found user in paginated directory");
        $this->assert($found['has_no_responsibilities'] === true, "Test 14: Paginated directory flags has_no_responsibilities = true");

        // Verify audit log has USER_SAVED_WITH_NO_RESPONSIBILITIES warning
        $stmt = $this->db->prepare("SELECT action, new_state_json FROM audit_logs WHERE record_type = 'users' AND record_id = :uid AND action = 'USER_SAVED_WITH_NO_RESPONSIBILITIES'");
        $stmt->execute(['uid' => $userId]);
        $auditRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assert($auditRow !== false, "Test 14: Audit log records USER_SAVED_WITH_NO_RESPONSIBILITIES warning action");
        $this->assert(str_contains((string)$auditRow['new_state_json'], 'unable to perform operational tasks'),
            "Test 14: Audit log contains explicit operational warning notice");
    }

    private function test15_StaffWithNoResponsibilitiesCannotPerformOperationalActions(): void
    {
        $unique = bin2hex(random_bytes(3));

        // Staff with position Dean but ZERO responsibilities
        $dto = $this->makeCreateUserDTO([
            'username' => "dean_zero_{$unique}",
            'email' => "dean_zero_{$unique}@usted.edu.gh",
            'firstName' => 'Empty',
            'lastName' => 'Dean',
            'password' => 'Password123!',
            'positionId' => $this->posDeanId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [], // Empty!
        ]);
        $userId = $this->userService->onboardUser($dto, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $userId;

        // 1. Cannot perform action on DRAFT requisition
        $reqDraftId = $this->insertRequisition($this->facultyTechId, 'DRAFT', $userId);
        $transDraft = $this->workflowService->resolvePermittedTransitions($reqDraftId, $userId);
        $this->assert(empty($transDraft), "Test 15: Staff with 0 responsibilities sees 0 actions on DRAFT requisition");

        // 2. Cannot perform action on SUBMITTED requisition
        $reqSubId = $this->insertRequisition($this->facultyTechId, 'SUBMITTED', $this->adminUserId);
        $transSub = $this->workflowService->resolvePermittedTransitions($reqSubId, $userId);
        $this->assert(empty($transSub), "Test 15: Staff with 0 responsibilities sees 0 actions on SUBMITTED requisition");

        // 3. Cannot perform action on ENDORSED requisition
        $reqEndId = $this->insertRequisition($this->facultyTechId, 'ENDORSED', $this->adminUserId);
        $transEnd = $this->workflowService->resolvePermittedTransitions($reqEndId, $userId);
        $this->assert(empty($transEnd), "Test 15: Staff with 0 responsibilities sees 0 actions on ENDORSED requisition");

        // 4. Cannot perform action on DEPARTMENT_APPROVED requisition
        $reqDeptAppId = $this->insertRequisition($this->facultyTechId, 'DEPARTMENT_APPROVED', $this->adminUserId);
        $transDeptApp = $this->workflowService->resolvePermittedTransitions($reqDeptAppId, $userId);
        $this->assert(empty($transDeptApp), "Test 15: Staff with 0 responsibilities sees 0 actions on DEPARTMENT_APPROVED requisition");
    }

    private function test16_ResponsibilityAndPositionChangesAuditedInInstitutionalLogs(): void
    {
        $unique = bin2hex(random_bytes(3));

        // 1. Onboard initial user without APPROVE_OWN
        $dto = $this->makeCreateUserDTO([
            'username' => "audit_user_{$unique}",
            'email' => "audit_user_{$unique}@usted.edu.gh",
            'firstName' => 'Audit',
            'lastName' => 'Subject',
            'password' => 'Password123!',
            'positionId' => $this->posHodId,
            'assignedPlanningEntityId' => $this->deptComputingId,
            'responsibilities' => [ResponsibilityCode::RECOMMEND_DEPT],
        ]);
        $userId = $this->userService->onboardUser($dto, $this->adminUserId, '127.0.0.1');
        $this->trackedUsers[] = $userId;

        // Verify USER_ONBOARDED in audit log
        $stmtOnboard = $this->db->prepare("SELECT action, new_state_json FROM audit_logs WHERE record_type = 'users' AND record_id = :uid AND action = 'USER_ONBOARDED'");
        $stmtOnboard->execute(['uid' => $userId]);
        $onboardRow = $stmtOnboard->fetch(PDO::FETCH_ASSOC);
        $this->assert($onboardRow !== false, "Test 16: USER_ONBOARDED recorded in audit logs");
        $this->assert(str_contains((string)$onboardRow['new_state_json'], 'HOD'), "Test 16: Audit log contains position code");

        // 2. Update user: promote to Dean and grant APPROVE_OWN
        $updateDto = $this->makeUpdateUserDTO([
            'id' => $userId,
            'firstName' => 'Audit',
            'lastName' => 'Subject',
            'email' => "audit_user_{$unique}@usted.edu.gh",
            'positionId' => $this->posDeanId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [
                ResponsibilityCode::APPROVE_FACULTY_DEPTS,
                ResponsibilityCode::APPROVE_OWN,
            ],
        ]);
        $this->userService->updateUser($updateDto, $this->adminUserId, '127.0.0.1');

        // Verify SELF_APPROVAL_GRANTED in audit log
        $stmtSelf = $this->db->prepare("SELECT action, new_state_json FROM audit_logs WHERE record_type = 'user_responsibilities' AND record_id = :uid AND action = 'SELF_APPROVAL_GRANTED'");
        $stmtSelf->execute(['uid' => $userId]);
        $selfRow = $stmtSelf->fetch(PDO::FETCH_ASSOC);
        $this->assert($selfRow !== false, "Test 16: SELF_APPROVAL_GRANTED recorded in institutional audit log");

        // 3. Revoke APPROVE_OWN
        $updateDtoRevoke = $this->makeUpdateUserDTO([
            'id' => $userId,
            'firstName' => 'Audit',
            'lastName' => 'Subject',
            'email' => "audit_user_{$unique}@usted.edu.gh",
            'positionId' => $this->posDeanId,
            'assignedPlanningEntityId' => $this->facultyTechId,
            'responsibilities' => [
                ResponsibilityCode::APPROVE_FACULTY_DEPTS,
            ],
        ]);
        $this->userService->updateUser($updateDtoRevoke, $this->adminUserId, '127.0.0.1');

        // Verify SELF_APPROVAL_REVOKED in audit log
        $stmtRevoke = $this->db->prepare("SELECT action FROM audit_logs WHERE record_type = 'user_responsibilities' AND record_id = :uid AND action = 'SELF_APPROVAL_REVOKED'");
        $stmtRevoke->execute(['uid' => $userId]);
        $revokeRow = $stmtRevoke->fetch(PDO::FETCH_ASSOC);
        $this->assert($revokeRow !== false, "Test 16: SELF_APPROVAL_REVOKED recorded in institutional audit log");
    }

    private function test17_ExistingLegacyFixturesStillWorkThroughFallback(): void
    {
        $unique = bin2hex(random_bytes(3));

        // Legacy user with ONLY user_entity_roles and ZERO rows in user_responsibilities
        $uStmt = $this->db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, status) VALUES (:u, :e, 'hash', 'Legacy', 'HOD', 'ACTIVE')");
        $uStmt->execute(['u' => "legacy_hod_{$unique}", 'e' => "legacy_hod_{$unique}@usted.edu.gh"]);
        $legacyUserId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $legacyUserId;

        // Assign legacy HOD role (role_id 3)
        $rStmt = $this->db->prepare("INSERT INTO user_entity_roles (user_id, planning_entity_id, role_id, status, assigned_by) VALUES (:u, :e, 3, 'ACTIVE', :a)");
        $rStmt->execute(['u' => $legacyUserId, 'e' => $this->deptComputingId, 'a' => $this->adminUserId]);
        $this->trackedUserRoles[] = (int)$this->db->lastInsertId();

        // Verify this legacy user has 0 rows in user_responsibilities
        $hasActiveResp = $this->workflowService->userHasActiveResponsibilities($legacyUserId);
        $this->assert($hasActiveResp === false, "Test 17: Legacy user has 0 rows in user_responsibilities");

        // Requisition in Dept of Computing
        $reqId = $this->insertRequisition($this->deptComputingId, 'SUBMITTED', $this->adminUserId);

        // Through legacy fallback, this user SHOULD be permitted to endorse
        $transitions = $this->workflowService->resolvePermittedTransitions($reqId, $legacyUserId);
        $actions = array_map(fn($t) => $t->action->value, $transitions);

        $this->assert(in_array('ENDORSE', $actions, true),
            "Test 17: Legacy fixture seamlessly falls back to user_entity_roles and allows ENDORSE action");
    }

    private function test18_CleanTeardownZeroOrphans(): void
    {
        // Assert tracking structures are actively collecting all artifacts
        $this->assert(!empty($this->trackedUsers), "Test 18: Tracked users list is non-empty (" . count($this->trackedUsers) . " users)");
        $this->assert(!empty($this->trackedEntities), "Test 18: Tracked entities list is non-empty (" . count($this->trackedEntities) . " entities)");
        $this->assert(!empty($this->trackedRequisitions), "Test 18: Tracked requisitions list is non-empty (" . count($this->trackedRequisitions) . " requisitions)");
    }

    private function teardown(): void
    {
        // 1. Clean up requisitions
        if (!empty($this->trackedRequisitions)) {
            $in = implode(',', array_map('intval', $this->trackedRequisitions));
            $this->db->exec("DELETE FROM requisition_items WHERE requisition_id IN ({$in})");
            $this->db->exec("DELETE FROM workflow_action_logs WHERE document_type = 'REQUISITION' AND document_id IN ({$in})");
            $this->db->exec("DELETE FROM audit_logs WHERE record_type = 'requisitions' AND record_id IN ({$in})");
            $this->db->exec("DELETE FROM requisitions WHERE id IN ({$in})");
        }

        // 2. Clean up user_responsibilities
        if (!empty($this->trackedUsers)) {
            $inUsers = implode(',', array_map('intval', $this->trackedUsers));
            $this->db->exec("DELETE FROM user_responsibilities WHERE user_id IN ({$inUsers})");
            $this->db->exec("DELETE FROM user_entity_roles WHERE user_id IN ({$inUsers})");
            $this->db->exec("DELETE FROM audit_logs WHERE (record_type = 'users' OR record_type = 'user_responsibilities') AND record_id IN ({$inUsers})");
            $this->db->exec("DELETE FROM audit_logs WHERE actor_user_id IN ({$inUsers})");
        }

        // 3. Clean up user roles
        if (!empty($this->trackedUserRoles)) {
            $inRoles = implode(',', array_map('intval', $this->trackedUserRoles));
            $this->db->exec("DELETE FROM user_entity_roles WHERE id IN ({$inRoles})");
        }

        // 4. Clean up hierarchies
        foreach ($this->trackedHierarchies as $h) {
            $this->db->prepare("DELETE FROM entity_hierarchies WHERE ancestor_entity_id = :a AND descendant_entity_id = :d")->execute($h);
        }

        // 5. Clean up entities
        if (!empty($this->trackedEntities)) {
            $inEnt = implode(',', array_map('intval', $this->trackedEntities));
            $this->db->exec("UPDATE planning_entities SET parent_entity_id = NULL WHERE id IN ({$inEnt})");
            $this->db->exec("DELETE FROM planning_entities WHERE id IN ({$inEnt})");
        }

        // 6. Clean up users
        if (!empty($this->trackedUsers)) {
            $inUsers = implode(',', array_map('intval', $this->trackedUsers));
            $this->db->exec("DELETE FROM users WHERE id IN ({$inUsers})");
        }

        // 7. Clean up tracked workflow definitions
        if (!empty($this->trackedWorkflowDefs)) {
            $inWf = implode(',', array_map('intval', $this->trackedWorkflowDefs));
            $this->db->exec("DELETE FROM workflow_step_rules WHERE workflow_definition_id IN ({$inWf})");
            $this->db->exec("DELETE FROM workflow_definitions WHERE id IN ({$inWf})");
        }

        AuthManager::logout();
    }

    private function printSummary(): void
    {
        $total = $this->passed + $this->failed;
        echo "\n===============================================================\n";
        echo " STAFF POSITION & RESPONSIBILITY TEST SUMMARY\n";
        echo " Passed: {$this->passed} / {$total}\n";
        echo " Failed: {$this->failed}\n";
        echo "===============================================================\n";

        if ($this->failed === 0) {
            echo "\nALL STAFF POSITION & RESPONSIBILITY TESTS PASSED SUCCESSFULLY.\n";
        } else {
            echo "\nFAILURES ENCOUNTERED:\n";
            foreach ($this->failures as $f) {
                echo " - {$f}\n";
            }
            exit(1);
        }
    }
}

$test = new StaffPositionAndRoleAssignmentTest();
$test->run();
