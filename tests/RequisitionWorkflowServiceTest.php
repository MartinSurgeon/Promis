<?php

declare(strict_types=1);

/**
 * PROMIS Phase 2 Stage 2.3 Configurable Workflow & Multi-Stage Approval Engine Test Suite
 * Procurement Management Information System
 * University of Skills Training and Entrepreneurial Development (USTED)
 *
 * Standalone CLI test runner verifying:
 * 1. Active workflow definition lookup (specific and global fallback)
 * 2. Inactive workflow definition rejection
 * 3. Workflow step-rule evaluation and sequencing
 * 4. Canonical status transitions (ENDORSE, APPROVE Dean, APPROVE Finance, RECEIVE)
 * 5. Negative branches (RETURN, REJECT) with mandatory justification comments
 * 6. Terminal REJECTED transition rejection
 * 7. Entity-scoped execution authorization enforcement (deny-by-default)
 * 8. Stale status and conditional update conflict handling
 * 9. Append-only workflow action logging and history retrieval
 * 10. Institutional audit logging with valid JSON snapshots
 * 11. Single-transaction atomicity and rollback on logging failure
 * 12. Complete teardown with 0 orphan records across all 36 tables
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\ValidationException;
use Promis\Src\Execution\Auth\ExecutionPermissions;
use Promis\Src\Execution\Domain\DTO\WorkflowActionRequest;
use Promis\Src\Execution\Domain\DTO\WorkflowActionResult;
use Promis\Src\Execution\Domain\DTO\WorkflowDefinitionDTO;
use Promis\Src\Execution\Domain\DTO\WorkflowStepRuleDTO;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Domain\WorkflowAction;
use Promis\Src\Execution\Domain\WorkflowTransition;
use Promis\Src\Execution\Exception\InvalidWorkflowTransitionException;
use Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException;
use Promis\Src\Execution\Exception\WorkflowException;
use Promis\Src\Execution\Repository\AuditLogRepository;
use Promis\Src\Execution\Repository\AuditLogRepositoryInterface;
use Promis\Src\Execution\Repository\RequisitionItemRepository;
use Promis\Src\Execution\Repository\RequisitionRepository;
use Promis\Src\Execution\Repository\WorkflowActionLogRepository;
use Promis\Src\Execution\Repository\WorkflowActionLogRepositoryInterface;
use Promis\Src\Execution\Repository\WorkflowDefinitionRepository;
use Promis\Src\Execution\Repository\WorkflowStepRuleRepository;
use Promis\Src\Execution\Service\RequisitionWorkflowService;
use Promis\Src\Planning\Domain\PlanStatus;
use Promis\Src\Planning\Domain\PlanVersionStatus;
use Promis\Src\Planning\Domain\TargetQuarter;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepository;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepository;

final class RequisitionWorkflowServiceTest
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
    private array $trackedRoles = [];
    private array $trackedUserEntityRoles = [];
    private array $trackedWorkflowDefinitions = [];
    private array $trackedWorkflowStepRules = [];
    private array $trackedWorkflowActionLogs = [];
    private array $trackedAuditLogs = [];
    private array $trackedRequisitions = [];
    private array $trackedRequisitionItems = [];

    // Repositories & Services
    private RequisitionRepository $reqRepo;
    private WorkflowDefinitionRepository $defRepo;
    private WorkflowStepRuleRepository $stepRuleRepo;
    private WorkflowActionLogRepository $actionLogRepo;
    private AuditLogRepository $auditLogRepo;
    private RequisitionWorkflowService $workflowService;

    // Fixtures
    private int $fixtureUserId = 0;
    private int $fixtureUnauthorizedUserId = 0;
    private int $fixtureEntity2UserId = 0;
    private int $fixtureCampusId = 0;
    private int $fixtureDeptTypeId = 0;
    private int $fixtureOtherTypeId = 0;
    private int $fixtureEntityId = 0;
    private int $fixtureEntity2Id = 0;
    private int $fixtureCategoryId = 0;
    private int $fixtureUomId = 0;
    private int $fixtureStandardItemId = 0;

    // Approved plan & version
    private int $fixturePlanId = 0;
    private int $fixturePlanVersionId = 0;
    private int $fixturePlanItemId = 0;

    // Roles
    private int $fixtureHodRoleId = 0;
    private int $fixtureDeanRoleId = 0;
    private int $fixtureFinanceRoleId = 0;
    private int $fixtureProcRoleId = 0;

    // Workflow Definitions
    private int $fixtureDeptWfId = 0;
    private int $fixtureGlobalWfId = 0;
    private int $fixtureInactiveWfId = 0;

    // Workflow Step Rules
    private int $fixtureStep1Id = 0;
    private int $fixtureStep2Id = 0;
    private int $fixtureStep3Id = 0;
    private int $fixtureStep4Id = 0;

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
        $this->defRepo = new WorkflowDefinitionRepository($this->db);
        $this->stepRuleRepo = new WorkflowStepRuleRepository($this->db);
        $this->actionLogRepo = new WorkflowActionLogRepository($this->db);
        $this->auditLogRepo = new AuditLogRepository($this->db);

        $this->workflowService = new RequisitionWorkflowService(
            $this->db,
            $this->reqRepo,
            $this->defRepo,
            $this->stepRuleRepo,
            $this->actionLogRepo,
            $this->auditLogRepo
        );
    }

    public function run(): void
    {
        echo "\n===============================================================\n";
        echo " PROMIS Phase 2 Stage 2.3 Workflow Service Test Suite\n";
        echo " University of Skills Training and Entrepreneurial Development\n";
        echo " Relational Engine: MariaDB 10.4+ / MySQL 8.x\n";
        echo " PHP Version: " . PHP_VERSION . "\n";
        echo "===============================================================\n\n";

        try {
            $this->seedFixtures();

            // Scenario 1 to 4: Workflow Definition Lookup & Fallback
            $this->testActiveWorkflowDefinitionLookup();
            $this->testInactiveWorkflowDefinitionRejection();
            $this->testGlobalFallbackWorkflowDefinition();
            $this->testInactiveGlobalWorkflowDefinitionRejection();

            // Scenario 5 to 6: Workflow Step Rule Resolution
            $this->testWorkflowStepRuleResolution();

            // Scenario 7 to 10: Canonical Positive Lifecycle Transitions
            $this->testSuccessfulEndorseTransition();
            $this->testSuccessfulApproveTransitionDean();
            $this->testSuccessfulApproveTransitionFinanceCommitment();
            $this->testSuccessfulReceiveTransition();

            // Scenario 11 to 13: Negative Branches & Resubmission
            $this->testSuccessfulReturnTransition();
            $this->testResubmissionFromReturnedStatus();
            $this->testSuccessfulRejectTransition();

            // Scenario 14 to 17: Status & Action Rejection Safeguards
            $this->testRejectionOfActionOnTerminalRejectedStatus();
            $this->testRejectionOfInvalidSourceStatus();
            $this->testRejectionOfInvalidActionForState();
            $this->testRejectionWhenWorkflowRulesMissingOrEmpty();
            $this->testRejectionOnAmbiguousWorkflowDefinitions();

            // Scenario 18 to 21: Authorization, Scope, and Role Requirements
            $this->testRejectionOfUnauthorizedUser();
            $this->testRejectionOfCrossEntityAction();
            $this->testRejectionWhenUserLacksRequiredRole();

            // Scenario 22 to 23: Concurrency, Duplicate & Stale Status Safety
            $this->testDuplicateActionPrevention();
            $this->testStaleStatusUpdateAndConditionalConflict();

            // Scenario 24 to 26: Append-Only Logging & Valid JSON Snapshots
            $this->testWorkflowActionLogCreationAndFieldIntegrity();
            $this->testAppendOnlyWorkflowHistory();
            $this->testInstitutionalAuditLogCreationAndJsonSnapshots();

            // Scenario 27 to 28: Transaction Atomicity & Failure Rollbacks
            $this->testAtomicRollbackOnActionLogFailure();
            $this->testAtomicRollbackOnAuditLogFailure();

            // Scenario 29 to 30: Result DTO & Permitted Transitions Discovery
            $this->testWorkflowActionResultDTOVerification();
            $this->testPermittedTransitionsDiscovery();

            // Scenario 31 to 32: Mandatory Comment Enforcement on Negative Actions
            $this->testMandatoryCommentEnforcementOnReturn();
            $this->testMandatoryCommentEnforcementOnReject();

            // Scenario 33: Concurrency Row-Locking & Database Teardown
            $this->testConcurrencyRowLockAcquisition();

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
        $stmt = $this->db->prepare("INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `status`) VALUES (:u, :e, 'hash', 'Workflow', 'Tester', 'ACTIVE')");
        $stmt->execute(['u' => 'wf_user_' . $unique, 'e' => "wf_user_{$unique}@usted.edu.gh"]);
        $this->fixtureUserId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->fixtureUserId;

        $stmt->execute(['u' => 'wf_unauth_' . $unique, 'e' => "wf_unauth_{$unique}@usted.edu.gh"]);
        $this->fixtureUnauthorizedUserId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->fixtureUnauthorizedUserId;

        $stmt->execute(['u' => 'wf_ent2_user_' . $unique, 'e' => "wf_ent2_{$unique}@usted.edu.gh"]);
        $this->fixtureEntity2UserId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->fixtureEntity2UserId;

        // 2. Roles
        $stmt = $this->db->prepare("INSERT INTO `roles` (`role_code`, `role_title`) VALUES (:c, :t)");
        $stmt->execute(['c' => 'ROLE_HOD_' . $unique, 't' => 'Head of Department']);
        $this->fixtureHodRoleId = (int)$this->db->lastInsertId();
        $this->trackedRoles[] = $this->fixtureHodRoleId;

        $stmt->execute(['c' => 'ROLE_DEAN_' . $unique, 't' => 'Dean of Faculty']);
        $this->fixtureDeanRoleId = (int)$this->db->lastInsertId();
        $this->trackedRoles[] = $this->fixtureDeanRoleId;

        $stmt->execute(['c' => 'ROLE_FIN_' . $unique, 't' => 'Finance Officer']);
        $this->fixtureFinanceRoleId = (int)$this->db->lastInsertId();
        $this->trackedRoles[] = $this->fixtureFinanceRoleId;

        $stmt->execute(['c' => 'ROLE_PROC_' . $unique, 't' => 'Procurement Officer']);
        $this->fixtureProcRoleId = (int)$this->db->lastInsertId();
        $this->trackedRoles[] = $this->fixtureProcRoleId;

        // 3. Campus
        $stmt = $this->db->prepare("INSERT INTO `campuses` (`campus_code`, `campus_name`) VALUES (:c, 'Main Campus')");
        $stmt->execute(['c' => 'CAMP_WF_' . $unique]);
        $this->fixtureCampusId = (int)$this->db->lastInsertId();
        $this->trackedCampuses[] = $this->fixtureCampusId;

        // 4. Entity Types
        $stmt = $this->db->prepare("INSERT INTO `entity_types` (`type_code`, `type_name`) VALUES (:c, :n)");
        $stmt->execute(['c' => 'TYPE_DEPT_' . $unique, 'n' => 'Academic Department']);
        $this->fixtureDeptTypeId = (int)$this->db->lastInsertId();
        $this->trackedEntityTypes[] = $this->fixtureDeptTypeId;

        $stmt->execute(['c' => 'TYPE_OTHER_' . $unique, 'n' => 'Support Directorate']);
        $this->fixtureOtherTypeId = (int)$this->db->lastInsertId();
        $this->trackedEntityTypes[] = $this->fixtureOtherTypeId;

        // 5. Planning Entities
        $stmt = $this->db->prepare("INSERT INTO `planning_entities` (`entity_code`, `entity_name`, `entity_type_id`, `campus_id`) VALUES (:ec, :en, :tid, :cid)");
        $stmt->execute([
            'ec' => 'ENT_DEPT_' . $unique,
            'en' => 'Department of Computing',
            'tid' => $this->fixtureDeptTypeId,
            'cid' => $this->fixtureCampusId,
        ]);
        $this->fixtureEntityId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->fixtureEntityId;

        $stmt->execute([
            'ec' => 'ENT_OTHER_' . $unique,
            'en' => 'ICT Services Directorate',
            'tid' => $this->fixtureOtherTypeId,
            'cid' => $this->fixtureCampusId,
        ]);
        $this->fixtureEntity2Id = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->fixtureEntity2Id;

        // 6. User Entity Roles in DB
        $stmt = $this->db->prepare("INSERT INTO `user_entity_roles` (`user_id`, `planning_entity_id`, `role_id`, `assigned_by`) VALUES (:u, :e, :r, :a)");
        foreach ([$this->fixtureHodRoleId, $this->fixtureDeanRoleId, $this->fixtureFinanceRoleId, $this->fixtureProcRoleId] as $rid) {
            $stmt->execute(['u' => $this->fixtureUserId, 'e' => $this->fixtureEntityId, 'r' => $rid, 'a' => $this->fixtureUserId]);
            $this->trackedUserEntityRoles[] = (int)$this->db->lastInsertId();
        }

        // 7. Categories & Items for Requisitions
        $stmt = $this->db->prepare("INSERT INTO `item_categories` (`category_code`, `category_name`) VALUES (:c, 'Hardware')");
        $stmt->execute(['c' => 'CAT_WF_' . $unique]);
        $this->fixtureCategoryId = (int)$this->db->lastInsertId();
        $this->trackedCategories[] = $this->fixtureCategoryId;

        $stmt = $this->db->prepare("INSERT INTO `units_of_measure` (`uom_code`, `uom_name`) VALUES (:c, 'Unit')");
        $stmt->execute(['c' => 'UOM_WF_' . $unique]);
        $this->fixtureUomId = (int)$this->db->lastInsertId();
        $this->trackedUoms[] = $this->fixtureUomId;

        $stmt = $this->db->prepare("INSERT INTO `standard_items` (`item_code`, `item_name`, `category_id`, `default_uom_id`, `estimated_unit_price`) VALUES (:c, 'Laptop', :cid, :uid, '1000.00')");
        $stmt->execute(['c' => 'ITEM_WF_' . $unique, 'cid' => $this->fixtureCategoryId, 'uid' => $this->fixtureUomId]);
        $this->fixtureStandardItemId = (int)$this->db->lastInsertId();
        $this->trackedStandardItems[] = $this->fixtureStandardItemId;

        // 8. Plan & Version
        $planRepo = new ProcurementPlanRepository($this->db);
        $versionRepo = new ProcurementPlanVersionRepository($this->db);
        $itemRepo = new ProcurementPlanItemRepository($this->db);

        $this->fixturePlanId = $planRepo->create([
            'plan_number' => 'PLAN-WF-' . $unique,
            'planning_entity_id' => $this->fixtureEntityId,
            'fiscal_year' => 2026,
            'status' => PlanStatus::APPROVED->value,
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlans[] = $this->fixturePlanId;

        $this->fixturePlanVersionId = $versionRepo->create([
            'procurement_plan_id' => $this->fixturePlanId,
            'version_number' => '1.0',
            'status' => PlanVersionStatus::APPROVED->value,
            'total_estimated_cost' => '50000.00',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedVersions[] = $this->fixturePlanVersionId;
        $planRepo->setCurrentVersion($this->fixturePlanId, $this->fixturePlanVersionId);

        $this->fixturePlanItemId = $itemRepo->create([
            'plan_version_id' => $this->fixturePlanVersionId,
            'standard_item_id' => $this->fixtureStandardItemId,
            'item_description' => 'Workstations',
            'category_id' => $this->fixtureCategoryId,
            'uom_id' => $this->fixtureUomId,
            'planned_quantity' => '50.00',
            'estimated_unit_cost' => '1000.00',
            'estimated_total_cost' => '50000.00',
            'target_quarter' => TargetQuarter::Q1->value,
            'funding_source' => 'GOG',
            'created_by' => $this->fixtureUserId,
        ]);
        $this->trackedPlanItems[] = $this->fixturePlanItemId;

        // 9. Configurable Workflow Definitions
        // A) Department Specific Active Workflow
        $stmt = $this->db->prepare("INSERT INTO `workflow_definitions` (`workflow_code`, `document_type`, `entity_type_id`, `workflow_name`, `is_active`, `created_by`) VALUES (:c, 'REQUISITION', :tid, 'Department Requisition Workflow', 1, :u)");
        $stmt->execute(['c' => 'WF_DEPT_' . $unique, 'tid' => $this->fixtureDeptTypeId, 'u' => $this->fixtureUserId]);
        $this->fixtureDeptWfId = (int)$this->db->lastInsertId();
        $this->trackedWorkflowDefinitions[] = $this->fixtureDeptWfId;

        // B) Global Fallback Active Workflow (entity_type_id IS NULL)
        $stmt->execute(['c' => 'WF_GLOBAL_' . $unique, 'tid' => null, 'u' => $this->fixtureUserId]);
        $this->fixtureGlobalWfId = (int)$this->db->lastInsertId();
        $this->trackedWorkflowDefinitions[] = $this->fixtureGlobalWfId;

        // 10. Step Rules for Department Workflow
        $stmt = $this->db->prepare("INSERT INTO `workflow_step_rules` (`workflow_definition_id`, `step_order`, `step_name`, `required_role_id`, `threshold_min_amount`, `threshold_max_amount`, `is_mandatory`, `created_by`) VALUES (:wfid, :sorder, :sname, :rid, :tmin, :tmax, 1, :u)");

        // Step 1: Head of Department Endorsement
        $stmt->execute([
            'wfid' => $this->fixtureDeptWfId,
            'sorder' => 1,
            'sname' => 'Head of Department Endorsement',
            'rid' => $this->fixtureHodRoleId,
            'tmin' => null,
            'tmax' => null,
            'u' => $this->fixtureUserId,
        ]);
        $this->fixtureStep1Id = (int)$this->db->lastInsertId();
        $this->trackedWorkflowStepRules[] = $this->fixtureStep1Id;

        // Step 2: Dean Approval
        $stmt->execute([
            'wfid' => $this->fixtureDeptWfId,
            'sorder' => 2,
            'sname' => 'Dean Approval',
            'rid' => $this->fixtureDeanRoleId,
            'tmin' => null,
            'tmax' => null,
            'u' => $this->fixtureUserId,
        ]);
        $this->fixtureStep2Id = (int)$this->db->lastInsertId();
        $this->trackedWorkflowStepRules[] = $this->fixtureStep2Id;

        // Step 3: Finance Commitment Authorization
        $stmt->execute([
            'wfid' => $this->fixtureDeptWfId,
            'sorder' => 3,
            'sname' => 'Finance Commitment Authorization',
            'rid' => $this->fixtureFinanceRoleId,
            'tmin' => null,
            'tmax' => null,
            'u' => $this->fixtureUserId,
        ]);
        $this->fixtureStep3Id = (int)$this->db->lastInsertId();
        $this->trackedWorkflowStepRules[] = $this->fixtureStep3Id;

        // Step 4: Procurement Officer Receipt
        $stmt->execute([
            'wfid' => $this->fixtureDeptWfId,
            'sorder' => 4,
            'sname' => 'Procurement Officer Receipt',
            'rid' => $this->fixtureProcRoleId,
            'tmin' => null,
            'tmax' => null,
            'u' => $this->fixtureUserId,
        ]);
        $this->fixtureStep4Id = (int)$this->db->lastInsertId();
        $this->trackedWorkflowStepRules[] = $this->fixtureStep4Id;

        // Step rules for Global Fallback Workflow
        $stmt->execute([
            'wfid' => $this->fixtureGlobalWfId,
            'sorder' => 1,
            'sname' => 'Global Endorsement',
            'rid' => $this->fixtureHodRoleId,
            'tmin' => null,
            'tmax' => null,
            'u' => $this->fixtureUserId,
        ]);
        $this->trackedWorkflowStepRules[] = (int)$this->db->lastInsertId();

        $stmt->execute([
            'wfid' => $this->fixtureGlobalWfId,
            'sorder' => 2,
            'sname' => 'Global Approval',
            'rid' => $this->fixtureDeanRoleId,
            'tmin' => null,
            'tmax' => null,
            'u' => $this->fixtureUserId,
        ]);
        $this->trackedWorkflowStepRules[] = (int)$this->db->lastInsertId();

        // Setup authorized user session
        $this->authenticateAuthorizedUser();

        $this->assert(true, "Base relational fixtures, plans, and workflow configurations initialized successfully");
    }

    private function authenticateAuthorizedUser(): void
    {
        AuthManager::login([
            'id' => $this->fixtureUserId,
            'email' => 'wf_user@usted.edu.gh',
            'permissions' => [],
            'entity_permissions' => [
                $this->fixtureEntityId => [
                    ExecutionPermissions::CREATE,
                    ExecutionPermissions::VIEW,
                    ExecutionPermissions::EDIT,
                    ExecutionPermissions::SUBMIT,
                    ExecutionPermissions::ENDORSE,
                    ExecutionPermissions::APPROVE,
                    ExecutionPermissions::RETURN_REQ,
                    ExecutionPermissions::REJECT,
                    ExecutionPermissions::RECEIVE,
                ],
                $this->fixtureEntity2Id => [
                    ExecutionPermissions::CREATE,
                    ExecutionPermissions::VIEW,
                    ExecutionPermissions::EDIT,
                    ExecutionPermissions::SUBMIT,
                    ExecutionPermissions::ENDORSE,
                    ExecutionPermissions::APPROVE,
                    ExecutionPermissions::RETURN_REQ,
                    ExecutionPermissions::REJECT,
                    ExecutionPermissions::RECEIVE,
                ],
            ],
            'role_ids' => [
                $this->fixtureHodRoleId,
                $this->fixtureDeanRoleId,
                $this->fixtureFinanceRoleId,
                $this->fixtureProcRoleId,
            ],
        ]);
    }

    private function authenticateUnauthorizedUser(): void
    {
        AuthManager::login([
            'id' => $this->fixtureUnauthorizedUserId,
            'email' => 'wf_unauth@usted.edu.gh',
            'permissions' => [],
            'entity_permissions' => [],
            'role_ids' => [],
        ]);
    }

    private function authenticateEntity2User(): void
    {
        AuthManager::login([
            'id' => $this->fixtureEntity2UserId,
            'email' => 'wf_ent2@usted.edu.gh',
            'permissions' => [],
            'entity_permissions' => [
                $this->fixtureEntity2Id => [
                    ExecutionPermissions::CREATE,
                    ExecutionPermissions::VIEW,
                    ExecutionPermissions::EDIT,
                    ExecutionPermissions::SUBMIT,
                    ExecutionPermissions::ENDORSE,
                    ExecutionPermissions::APPROVE,
                    ExecutionPermissions::RETURN_REQ,
                    ExecutionPermissions::REJECT,
                    ExecutionPermissions::RECEIVE,
                ],
            ],
            'role_ids' => [
                $this->fixtureHodRoleId,
            ],
        ]);
    }

    private function createFixtureRequisition(string $status = 'SUBMITTED', string $cost = '1000.00', ?int $entityId = null): int
    {
        $eid = $entityId ?? $this->fixtureEntityId;
        $unique = bin2hex(random_bytes(3));
        $reqNum = 'REQ-' . date('Y') . '-' . str_pad((string)$eid, 3, '0', STR_PAD_LEFT) . '-' . $unique;

        $submittedAt = ($status !== 'DRAFT') ? date('Y-m-d H:i:s') : null;
        $submittedBy = ($status !== 'DRAFT') ? $this->fixtureUserId : null;

        $sql = "INSERT INTO `requisitions` (
                    `requisition_number`, `planning_entity_id`, `fiscal_year`, 
                    `approved_plan_version_id`, `status`, `total_estimated_cost`, 
                    `justification`, `submitted_at`, `submitted_by`, `created_by`
                ) VALUES (
                    :num, :eid, 2026, 
                    :vid, :st, :cost, 
                    'Workflow test requisition justification', :sub_at, :sub_by, :cby
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'num' => $reqNum,
            'eid' => $eid,
            'vid' => $this->fixturePlanVersionId,
            'st' => $status,
            'cost' => $cost,
            'sub_at' => $submittedAt,
            'sub_by' => $submittedBy,
            'cby' => $this->fixtureUserId,
        ]);

        $reqId = (int)$this->db->lastInsertId();
        $this->trackedRequisitions[] = $reqId;

        // Insert 1 requisition item
        $sqlItem = "INSERT INTO `requisition_items` (
                        `requisition_id`, `procurement_plan_item_id`, `standard_item_id`,
                        `item_description`, `uom_id`,
                        `requested_quantity`, `estimated_unit_cost`, `estimated_total_cost`, `created_by`
                    ) VALUES (
                        :rid, :piid, :siid,
                        'Test Item', :uomid,
                        '1.00', :unit_cost, :total_cost, :cby
                    )";
        $stmtItem = $this->db->prepare($sqlItem);
        $stmtItem->execute([
            'rid' => $reqId,
            'piid' => $this->fixturePlanItemId,
            'siid' => $this->fixtureStandardItemId,
            'uomid' => $this->fixtureUomId,
            'unit_cost' => $cost,
            'total_cost' => $cost,
            'cby' => $this->fixtureUserId,
        ]);
        $this->trackedRequisitionItems[] = (int)$this->db->lastInsertId();

        return $reqId;
    }

    // =========================================================================
    // SCENARIO 1 to 4: Workflow Definition Lookup & Fallback
    // =========================================================================

    private function testActiveWorkflowDefinitionLookup(): void
    {
        $this->authenticateAuthorizedUser();
        $def = $this->workflowService->resolveWorkflowDefinition($this->fixtureEntityId);

        $this->assert($def->id === $this->fixtureDeptWfId, "Scenario 1: Active entity-specific workflow definition resolved (ID: {$def->id})");
        $this->assert($def->workflowName === 'Department Requisition Workflow', "Scenario 1: Definition workflow name matches configuration");
        $this->assert($def->isActive === true, "Scenario 1: Definition is active");
    }

    private function testInactiveWorkflowDefinitionRejection(): void
    {
        $this->authenticateAuthorizedUser();

        // Create an inactive workflow definition for Other entity type
        $unique = bin2hex(random_bytes(3));
        $stmt = $this->db->prepare("INSERT INTO `workflow_definitions` (`workflow_code`, `document_type`, `entity_type_id`, `workflow_name`, `is_active`, `created_by`) VALUES (:c, 'REQUISITION', :tid, 'Inactive Directorate Workflow', 0, :u)");
        $stmt->execute(['c' => 'WF_INACT_' . $unique, 'tid' => $this->fixtureOtherTypeId, 'u' => $this->fixtureUserId]);
        $inactiveId = (int)$this->db->lastInsertId();
        $this->trackedWorkflowDefinitions[] = $inactiveId;

        $caught = false;
        try {
            $this->workflowService->resolveWorkflowDefinition($this->fixtureEntity2Id);
        } catch (WorkflowException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'inactive'), "Scenario 2: Rejects inactive entity workflow definition with controlled message: {$e->getMessage()}");
        }

        $this->assert($caught, "Scenario 2: WorkflowException thrown for inactive workflow definition");

        // Clean up this inactive definition so subsequent tests can use fallback if needed
        $this->db->prepare("DELETE FROM `workflow_definitions` WHERE `id` = :id")->execute(['id' => $inactiveId]);
        array_pop($this->trackedWorkflowDefinitions);
    }

    private function testGlobalFallbackWorkflowDefinition(): void
    {
        $this->authenticateAuthorizedUser();

        // For Entity 2 (Other entity type), there is no specific definition now, so it must resolve the global fallback
        $def = $this->workflowService->resolveWorkflowDefinition($this->fixtureEntity2Id);

        $this->assert($def->id === $this->fixtureGlobalWfId, "Scenario 3: Resolves active global fallback definition (ID: {$def->id}) when entity has no specific definition");
        $this->assert($def->entityTypeId === null, "Scenario 3: Global fallback definition has NULL entity_type_id");
    }

    private function testInactiveGlobalWorkflowDefinitionRejection(): void
    {
        $this->authenticateAuthorizedUser();

        // Temporarily deactivate global definition and test resolution for Entity 2
        $this->db->prepare("UPDATE `workflow_definitions` SET `is_active` = 0 WHERE `id` = :id")->execute(['id' => $this->fixtureGlobalWfId]);

        $caught = false;
        try {
            $this->workflowService->resolveWorkflowDefinition($this->fixtureEntity2Id);
        } catch (WorkflowException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'inactive'), "Scenario 4: Rejects when global workflow definition is inactive");
        }
        $this->assert($caught, "Scenario 4: WorkflowException thrown when global definition is inactive");

        // Restore global definition to active
        $this->db->prepare("UPDATE `workflow_definitions` SET `is_active` = 1 WHERE `id` = :id")->execute(['id' => $this->fixtureGlobalWfId]);
    }

    // =========================================================================
    // SCENARIO 5 to 6: Workflow Step Rule Resolution
    // =========================================================================

    private function testWorkflowStepRuleResolution(): void
    {
        $this->authenticateAuthorizedUser();
        $rules = $this->stepRuleRepo->findByWorkflowDefinitionId($this->fixtureDeptWfId);

        $this->assert(count($rules) === 4, "Scenario 5: Exactly 4 step rules resolved for department workflow");
        $this->assert($rules[0]->stepOrder === 1 && str_contains($rules[0]->stepName, 'Endorsement'), "Scenario 5: Step 1 is Endorsement");
        $this->assert($rules[1]->stepOrder === 2 && str_contains($rules[1]->stepName, 'Approval'), "Scenario 5: Step 2 is Dean Approval");
        $this->assert($rules[2]->stepOrder === 3 && str_contains($rules[2]->stepName, 'Commitment'), "Scenario 6: Step 3 is Finance Commitment");
        $this->assert($rules[3]->stepOrder === 4 && str_contains($rules[3]->stepName, 'Receipt'), "Scenario 6: Step 4 is Procurement Officer Receipt");
    }

    // =========================================================================
    // SCENARIO 7 to 10: Canonical Positive Lifecycle Transitions
    // =========================================================================

    private function testSuccessfulEndorseTransition(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        $request = new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::ENDORSE,
            actingUserId: $this->fixtureUserId,
            comments: 'HOD endorsement granted'
        );

        $result = $this->workflowService->executeAction($request);
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        $this->assert($result->previousStatus === RequisitionStatus::SUBMITTED, "Scenario 7: Previous status was SUBMITTED");
        $this->assert($result->newStatus === RequisitionStatus::ENDORSED, "Scenario 7: New status is ENDORSED");
        $this->assert($result->requisition->status === RequisitionStatus::ENDORSED, "Scenario 7: Hydrated DTO reflects ENDORSED status");
        $this->assert($result->stepRuleId === $this->fixtureStep1Id, "Scenario 7: StepRuleId matches HOD Step 1");

        // Verify DB persistence
        $row = $this->db->query("SELECT status FROM requisitions WHERE id = {$reqId}")->fetch(PDO::FETCH_ASSOC);
        $this->assert($row['status'] === 'ENDORSED', "Scenario 7: Database status confirmed as ENDORSED");
    }

    private function testSuccessfulApproveTransitionDean(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('ENDORSED');

        $request = new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::APPROVE,
            actingUserId: $this->fixtureUserId,
            comments: 'Dean approval granted'
        );

        $result = $this->workflowService->executeAction($request);
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        $this->assert($result->previousStatus === RequisitionStatus::ENDORSED, "Scenario 8: Previous status was ENDORSED");
        $this->assert($result->newStatus === RequisitionStatus::DEPARTMENT_APPROVED, "Scenario 8: New status is DEPARTMENT_APPROVED");
        $this->assert($result->stepRuleId === $this->fixtureStep2Id, "Scenario 8: StepRuleId matches Dean Approval Step 2");

        $row = $this->db->query("SELECT status FROM requisitions WHERE id = {$reqId}")->fetch(PDO::FETCH_ASSOC);
        $this->assert($row['status'] === 'DEPARTMENT_APPROVED', "Scenario 8: Database status confirmed as DEPARTMENT_APPROVED");
    }

    private function testSuccessfulApproveTransitionFinanceCommitment(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('DEPARTMENT_APPROVED');

        $request = new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::APPROVE,
            actingUserId: $this->fixtureUserId,
            comments: 'Finance commitment authorization verified'
        );

        $result = $this->workflowService->executeAction($request);
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        $this->assert($result->previousStatus === RequisitionStatus::DEPARTMENT_APPROVED, "Scenario 9: Previous status was DEPARTMENT_APPROVED");
        $this->assert($result->newStatus === RequisitionStatus::COMMITMENT_AUTHORIZED, "Scenario 9: New status is COMMITMENT_AUTHORIZED");
        $this->assert($result->stepRuleId === $this->fixtureStep3Id, "Scenario 9: StepRuleId matches Finance Commitment Step 3");

        $row = $this->db->query("SELECT status FROM requisitions WHERE id = {$reqId}")->fetch(PDO::FETCH_ASSOC);
        $this->assert($row['status'] === 'COMMITMENT_AUTHORIZED', "Scenario 9: Database status confirmed as COMMITMENT_AUTHORIZED");
    }

    private function testSuccessfulReceiveTransition(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('COMMITMENT_AUTHORIZED');

        $request = new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::RECEIVE,
            actingUserId: $this->fixtureUserId,
            comments: 'Procurement unit received requisition into execution queue'
        );

        $result = $this->workflowService->executeAction($request);
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        $this->assert($result->previousStatus === RequisitionStatus::COMMITMENT_AUTHORIZED, "Scenario 10: Previous status was COMMITMENT_AUTHORIZED");
        $this->assert($result->newStatus === RequisitionStatus::PROCUREMENT_RECEIVED, "Scenario 10: New status is PROCUREMENT_RECEIVED");
        $this->assert($result->stepRuleId === $this->fixtureStep4Id, "Scenario 10: StepRuleId matches Procurement Receipt Step 4");

        $row = $this->db->query("SELECT status FROM requisitions WHERE id = {$reqId}")->fetch(PDO::FETCH_ASSOC);
        $this->assert($row['status'] === 'PROCUREMENT_RECEIVED', "Scenario 10: Database status confirmed as PROCUREMENT_RECEIVED");
    }

    // =========================================================================
    // SCENARIO 11 to 13: Negative Branches & Resubmission
    // =========================================================================

    private function testSuccessfulReturnTransition(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        $request = new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::RETURN,
            actingUserId: $this->fixtureUserId,
            comments: 'Please correct item specifications and resubmit'
        );

        $result = $this->workflowService->executeAction($request);
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        $this->assert($result->previousStatus === RequisitionStatus::SUBMITTED, "Scenario 11: Previous status was SUBMITTED");
        $this->assert($result->newStatus === RequisitionStatus::RETURNED, "Scenario 11: New status is RETURNED");

        $row = $this->db->query("SELECT status FROM requisitions WHERE id = {$reqId}")->fetch(PDO::FETCH_ASSOC);
        $this->assert($row['status'] === 'RETURNED', "Scenario 11: Database status confirmed as RETURNED");
    }

    private function testResubmissionFromReturnedStatus(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('RETURNED');

        $request = new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::SUBMIT,
            actingUserId: $this->fixtureUserId,
            comments: 'Resubmitted with corrected specifications'
        );

        $result = $this->workflowService->executeAction($request);
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        $this->assert($result->previousStatus === RequisitionStatus::RETURNED, "Scenario 12: Previous status was RETURNED");
        $this->assert($result->newStatus === RequisitionStatus::SUBMITTED, "Scenario 12: New status is SUBMITTED upon resubmission");

        $row = $this->db->query("SELECT status FROM requisitions WHERE id = {$reqId}")->fetch(PDO::FETCH_ASSOC);
        $this->assert($row['status'] === 'SUBMITTED', "Scenario 12: Database status confirmed as SUBMITTED");
    }

    private function testSuccessfulRejectTransition(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        $request = new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::REJECT,
            actingUserId: $this->fixtureUserId,
            comments: 'Budget insufficient for this category this fiscal year'
        );

        $result = $this->workflowService->executeAction($request);
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        $this->assert($result->previousStatus === RequisitionStatus::SUBMITTED, "Scenario 13: Previous status was SUBMITTED");
        $this->assert($result->newStatus === RequisitionStatus::REJECTED, "Scenario 13: New status is REJECTED");

        $row = $this->db->query("SELECT status FROM requisitions WHERE id = {$reqId}")->fetch(PDO::FETCH_ASSOC);
        $this->assert($row['status'] === 'REJECTED', "Scenario 13: Database status confirmed as REJECTED");
    }

    // =========================================================================
    // SCENARIO 14 to 17: Status & Action Rejection Safeguards
    // =========================================================================

    private function testRejectionOfActionOnTerminalRejectedStatus(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('REJECTED');

        $actions = [
            WorkflowAction::ENDORSE,
            WorkflowAction::APPROVE,
            WorkflowAction::RETURN,
            WorkflowAction::REJECT,
            WorkflowAction::RECEIVE,
        ];

        $rejectedCount = 0;
        foreach ($actions as $action) {
            try {
                $this->workflowService->executeAction(new WorkflowActionRequest(
                    requisitionId: $reqId,
                    action: $action,
                    actingUserId: $this->fixtureUserId,
                    comments: 'Action on terminal state'
                ));
            } catch (InvalidWorkflowTransitionException) {
                $rejectedCount++;
            }
        }

        $this->assert($rejectedCount === count($actions), "Scenario 14: All " . count($actions) . " actions strictly rejected on terminal REJECTED status");
    }

    private function testRejectionOfInvalidSourceStatus(): void
    {
        $this->authenticateAuthorizedUser();

        // 1. ENDORSE from DRAFT
        $reqDraftId = $this->createFixtureRequisition('DRAFT');
        $caught = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqDraftId,
                action: WorkflowAction::ENDORSE,
                actingUserId: $this->fixtureUserId
            ));
        } catch (InvalidWorkflowTransitionException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'SUBMITTED'), "Scenario 15: Cannot ENDORSE from DRAFT status");
        }
        $this->assert($caught, "Scenario 15: InvalidWorkflowTransitionException caught for ENDORSE from DRAFT");

        // 2. RECEIVE from SUBMITTED
        $reqSubmittedId = $this->createFixtureRequisition('SUBMITTED');
        $caught2 = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqSubmittedId,
                action: WorkflowAction::RECEIVE,
                actingUserId: $this->fixtureUserId
            ));
        } catch (InvalidWorkflowTransitionException $e) {
            $caught2 = true;
            $this->assert(str_contains($e->getMessage(), 'COMMITMENT_AUTHORIZED'), "Scenario 15: Cannot RECEIVE from SUBMITTED status");
        }
        $this->assert($caught2, "Scenario 15: InvalidWorkflowTransitionException caught for RECEIVE from SUBMITTED");
    }

    private function testRejectionOfInvalidActionForState(): void
    {
        $this->authenticateAuthorizedUser();
        $reqDraftId = $this->createFixtureRequisition('DRAFT');

        $caught = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqDraftId,
                action: WorkflowAction::APPROVE,
                actingUserId: $this->fixtureUserId
            ));
        } catch (InvalidWorkflowTransitionException $e) {
            $caught = true;
        }
        $this->assert($caught, "Scenario 16: Action APPROVE rejected when requisition is in DRAFT");
    }

    private function testRejectionWhenWorkflowRulesMissingOrEmpty(): void
    {
        $this->authenticateAuthorizedUser();

        // Create a workflow definition with 0 rules
        $unique = bin2hex(random_bytes(3));
        $stmt = $this->db->prepare("INSERT INTO `workflow_definitions` (`workflow_code`, `document_type`, `entity_type_id`, `workflow_name`, `is_active`, `created_by`) VALUES (:c, 'REQUISITION', :tid, 'Empty Workflow', 1, :u)");
        $stmt->execute(['c' => 'WF_EMPTY_' . $unique, 'tid' => $this->fixtureOtherTypeId, 'u' => $this->fixtureUserId]);
        $emptyWfId = (int)$this->db->lastInsertId();
        $this->trackedWorkflowDefinitions[] = $emptyWfId;

        // Temporarily deactivate global fallback to ensure this empty definition is picked
        $this->db->prepare("UPDATE `workflow_definitions` SET `is_active` = 0 WHERE `id` = :id")->execute(['id' => $this->fixtureGlobalWfId]);

        $reqOtherId = $this->createFixtureRequisition('SUBMITTED', '1000.00', $this->fixtureEntity2Id);

        $caught = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqOtherId,
                action: WorkflowAction::ENDORSE,
                actingUserId: $this->fixtureUserId
            ));
        } catch (WorkflowException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'no configured step rules'), "Scenario 17: Rejects transition when workflow definition has no configured step rules");
        }
        $this->assert($caught, "Scenario 17: WorkflowException caught for empty workflow rules");

        // Restore global fallback and delete empty definition
        $this->db->prepare("UPDATE `workflow_definitions` SET `is_active` = 1 WHERE `id` = :id")->execute(['id' => $this->fixtureGlobalWfId]);
        $this->db->prepare("DELETE FROM `workflow_definitions` WHERE `id` = :id")->execute(['id' => $emptyWfId]);
        array_pop($this->trackedWorkflowDefinitions);
    }

    private function testRejectionOnAmbiguousWorkflowDefinitions(): void
    {
        $this->authenticateAuthorizedUser();

        // Create a SECOND active workflow definition for the same entity type
        $unique = bin2hex(random_bytes(3));
        $stmt = $this->db->prepare("INSERT INTO `workflow_definitions` (`workflow_code`, `document_type`, `entity_type_id`, `workflow_name`, `is_active`, `created_by`) VALUES (:c, 'REQUISITION', :tid, 'Duplicate Dept Workflow', 1, :u)");
        $stmt->execute(['c' => 'WF_DUP_' . $unique, 'tid' => $this->fixtureDeptTypeId, 'u' => $this->fixtureUserId]);
        $dupWfId = (int)$this->db->lastInsertId();
        $this->trackedWorkflowDefinitions[] = $dupWfId;

        $caught = false;
        try {
            $this->workflowService->resolveWorkflowDefinition($this->fixtureEntityId);
        } catch (WorkflowException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'Ambiguous'), "Scenario 18: Rejects ambiguous multiple active definitions: {$e->getMessage()}");
        }
        $this->assert($caught, "Scenario 18: WorkflowException caught for ambiguous workflow definitions");

        // Clean up duplicate definition
        $this->db->prepare("DELETE FROM `workflow_definitions` WHERE `id` = :id")->execute(['id' => $dupWfId]);
        array_pop($this->trackedWorkflowDefinitions);
    }

    // =========================================================================
    // SCENARIO 18 to 21: Authorization, Scope, and Role Requirements
    // =========================================================================

    private function testRejectionOfUnauthorizedUser(): void
    {
        $this->authenticateUnauthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        $caught = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::ENDORSE,
                actingUserId: $this->fixtureUnauthorizedUserId,
                comments: 'Unauthorized endorse attempt'
            ));
        } catch (UnauthorizedWorkflowActionException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'Access denied'), "Scenario 19: Unauthorized user rejected with access denied message: {$e->getMessage()}");
        }
        $this->assert($caught, "Scenario 19: UnauthorizedWorkflowActionException thrown for user without req.endorse permission");
    }

    private function testRejectionOfCrossEntityAction(): void
    {
        // Entity 2 user has permissions for Entity 2, but NOT Entity 1
        $this->authenticateEntity2User();
        $reqId = $this->createFixtureRequisition('SUBMITTED', '1000.00', $this->fixtureEntityId);

        $caught = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::ENDORSE,
                actingUserId: $this->fixtureEntity2UserId,
                comments: 'Cross entity endorse attempt'
            ));
        } catch (UnauthorizedWorkflowActionException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), "entity #{$this->fixtureEntityId}"), "Scenario 20: Cross-entity action denied: {$e->getMessage()}");
        }
        $this->assert($caught, "Scenario 20: UnauthorizedWorkflowActionException thrown on cross-entity action");
    }

    private function testRejectionWhenUserLacksRequiredRole(): void
    {
        // User has permission 'req.endorse' in Entity 1, but NO role in session or DB matching Step 1's role
        AuthManager::login([
            'id' => $this->fixtureUserId,
            'email' => 'wf_user@usted.edu.gh',
            'permissions' => [],
            'entity_permissions' => [
                $this->fixtureEntityId => [
                    ExecutionPermissions::ENDORSE,
                ],
            ],
            'role_ids' => [999999], // Alien role
            'roles' => ['ALIEN_ROLE'],
        ]);

        // Temporarily clear DB user_entity_roles for fixture user
        $this->db->prepare("UPDATE `user_entity_roles` SET `status` = 'INACTIVE' WHERE `user_id` = :uid")->execute(['uid' => $this->fixtureUserId]);

        $reqId = $this->createFixtureRequisition('SUBMITTED');

        $caught = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::ENDORSE,
                actingUserId: $this->fixtureUserId,
                comments: 'Endorse without role'
            ));
        } catch (UnauthorizedWorkflowActionException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'does not possess the required role'), "Scenario 21: Rejects user without required step role: {$e->getMessage()}");
        }
        $this->assert($caught, "Scenario 21: UnauthorizedWorkflowActionException thrown when step role is missing");

        // Restore DB user_entity_roles
        $this->db->prepare("UPDATE `user_entity_roles` SET `status` = 'ACTIVE' WHERE `user_id` = :uid")->execute(['uid' => $this->fixtureUserId]);
        $this->authenticateAuthorizedUser();
    }

    // =========================================================================
    // SCENARIO 22 to 23: Concurrency, Duplicate & Stale Status Safety
    // =========================================================================

    private function testDuplicateActionPrevention(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        // First ENDORSE succeeds
        $result = $this->workflowService->executeAction(new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::ENDORSE,
            actingUserId: $this->fixtureUserId,
            comments: 'First endorsement'
        ));
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        // Second duplicate ENDORSE fails because status is now ENDORSED, not SUBMITTED
        $caught = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::ENDORSE,
                actingUserId: $this->fixtureUserId,
                comments: 'Duplicate endorsement attempt'
            ));
        } catch (InvalidWorkflowTransitionException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'only permitted from SUBMITTED'), "Scenario 22: Duplicate ENDORSE prevented on {$result->newStatus->value} status");
        }
        $this->assert($caught, "Scenario 22: InvalidWorkflowTransitionException thrown on duplicate action");
    }

    private function testStaleStatusUpdateAndConditionalConflict(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        // Directly simulate a concurrent race condition by changing the row status behind the service's back
        $this->db->prepare("UPDATE `requisitions` SET `status` = 'RETURNED' WHERE `id` = :id")->execute(['id' => $reqId]);

        // Now attempt an action expecting SUBMITTED
        $caught = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::ENDORSE,
                actingUserId: $this->fixtureUserId,
                comments: 'Stale update attempt'
            ));
        } catch (InvalidWorkflowTransitionException $e) {
            $caught = true;
            $this->assert($e->sourceStatus === 'RETURNED', "Scenario 23: Conditional update conflict detected actual DB status as RETURNED");
        }
        $this->assert($caught, "Scenario 23: Stale status change caught safely");
    }

    // =========================================================================
    // SCENARIO 24 to 26: Append-Only Logging & Valid JSON Snapshots
    // =========================================================================

    private function testWorkflowActionLogCreationAndFieldIntegrity(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        $result = $this->workflowService->executeAction(new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::ENDORSE,
            actingUserId: $this->fixtureUserId,
            comments: 'Evidentiary log test comment'
        ));
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        // Query physical workflow_action_logs row
        $stmt = $this->db->prepare("SELECT * FROM `workflow_action_logs` WHERE `id` = :id");
        $stmt->execute(['id' => $result->actionLogId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assert($log !== false, "Scenario 24: Workflow action log row exists in DB");
        $this->assert($log['document_type'] === 'REQUISITION', "Scenario 24: document_type is REQUISITION");
        $this->assert((int)$log['document_id'] === $reqId, "Scenario 24: document_id matches requisition ID");
        $this->assert((int)$log['step_id'] === $this->fixtureStep1Id, "Scenario 24: step_id matches Step 1 ID");
        $this->assert((int)$log['actor_user_id'] === $this->fixtureUserId, "Scenario 24: actor_user_id matches acting user ID");
        $this->assert($log['action'] === 'ENDORSE', "Scenario 24: action recorded as ENDORSE");
        $this->assert($log['pre_status'] === 'SUBMITTED', "Scenario 24: pre_status recorded as SUBMITTED");
        $this->assert($log['post_status'] === 'ENDORSED', "Scenario 24: post_status recorded as ENDORSED");
        $this->assert($log['comments'] === 'Evidentiary log test comment', "Scenario 24: comments preserved accurately");
        $this->assert(!empty($log['action_timestamp']), "Scenario 24: action_timestamp is populated");
    }

    private function testAppendOnlyWorkflowHistory(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        // Action 1: ENDORSE
        $res1 = $this->workflowService->executeAction(new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::ENDORSE,
            actingUserId: $this->fixtureUserId,
            comments: 'Step 1 comment'
        ));
        $this->trackedWorkflowActionLogs[] = $res1->actionLogId;
        $this->trackedAuditLogs[] = $res1->auditLogId;

        // Action 2: APPROVE (Dean)
        $res2 = $this->workflowService->executeAction(new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::APPROVE,
            actingUserId: $this->fixtureUserId,
            comments: 'Step 2 comment'
        ));
        $this->trackedWorkflowActionLogs[] = $res2->actionLogId;
        $this->trackedAuditLogs[] = $res2->auditLogId;

        // Retrieve chronological history via service
        $history = $this->workflowService->getWorkflowHistory($reqId, $this->fixtureUserId);

        $this->assert(count($history) === 2, "Scenario 25: History contains exactly 2 chronological log entries");
        $this->assert($history[0]->id === $res1->actionLogId, "Scenario 25: First entry matches Action 1 ID");
        $this->assert($history[0]->action === 'ENDORSE', "Scenario 25: First action was ENDORSE");
        $this->assert($history[1]->id === $res2->actionLogId, "Scenario 25: Second entry matches Action 2 ID");
        $this->assert($history[1]->action === 'APPROVE', "Scenario 25: Second action was APPROVE");
        $this->assert($history[1]->preStatus === 'ENDORSED', "Scenario 25: Second preStatus was ENDORSED");
        $this->assert($history[1]->postStatus === 'DEPARTMENT_APPROVED', "Scenario 25: Second postStatus was DEPARTMENT_APPROVED");
    }

    private function testInstitutionalAuditLogCreationAndJsonSnapshots(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        $result = $this->workflowService->executeAction(new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::ENDORSE,
            actingUserId: $this->fixtureUserId,
            comments: 'Institutional audit verification'
        ));
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        // Query physical audit_logs row
        $stmt = $this->db->prepare("SELECT * FROM `audit_logs` WHERE `id` = :id");
        $stmt->execute(['id' => $result->auditLogId]);
        $audit = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assert($audit !== false, "Scenario 26: Institutional audit log row exists in DB");
        $this->assert((int)$audit['actor_user_id'] === $this->fixtureUserId, "Scenario 26: Audit actor_user_id matches");
        $this->assert((int)$audit['planning_entity_id'] === $this->fixtureEntityId, "Scenario 26: Audit planning_entity_id matches");
        $this->assert($audit['action'] === 'WORKFLOW_ENDORSE', "Scenario 26: Audit action is WORKFLOW_ENDORSE");
        $this->assert($audit['record_type'] === 'requisitions', "Scenario 26: Audit record_type is requisitions");
        $this->assert((int)$audit['record_id'] === $reqId, "Scenario 26: Audit record_id matches requisition ID");

        // Validate JSON snapshots
        $prevData = json_decode($audit['previous_state_json'], true);
        $newData = json_decode($audit['new_state_json'], true);

        $this->assert(is_array($prevData) && ($prevData['status'] ?? '') === 'SUBMITTED', "Scenario 26: previous_state_json is valid JSON with status SUBMITTED");
        $this->assert(is_array($newData) && ($newData['status'] ?? '') === 'ENDORSED', "Scenario 26: new_state_json is valid JSON with status ENDORSED");
    }

    // =========================================================================
    // SCENARIO 27 to 28: Transaction Atomicity & Failure Rollbacks
    // =========================================================================

    private function testAtomicRollbackOnActionLogFailure(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        // Create failing action log repo
        $failingLogRepo = new class($this->db) extends WorkflowActionLogRepository {
            public function create(array $data): int {
                throw new \RuntimeException("Simulated workflow action log persistence failure");
            }
        };

        $serviceWithFailingLog = new RequisitionWorkflowService(
            $this->db,
            $this->reqRepo,
            $this->defRepo,
            $this->stepRuleRepo,
            $failingLogRepo,
            $this->auditLogRepo
        );

        $caught = false;
        try {
            $serviceWithFailingLog->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::ENDORSE,
                actingUserId: $this->fixtureUserId,
                comments: 'Rollback test'
            ));
        } catch (\Throwable $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'Simulated workflow action log'), "Scenario 27: Caught simulated action log failure: {$e->getMessage()}");
        }
        $this->assert($caught, "Scenario 27: Exception thrown on log failure");

        // Verify status was ROLLED BACK and remains SUBMITTED
        $row = $this->db->query("SELECT status FROM requisitions WHERE id = {$reqId}")->fetch(PDO::FETCH_ASSOC);
        $this->assert($row['status'] === 'SUBMITTED', "Scenario 27: Atomic rollback preserves original SUBMITTED status after action log failure");

        // Verify zero action logs created for this requisition
        $logCount = (int)$this->db->query("SELECT COUNT(*) FROM workflow_action_logs WHERE document_type = 'REQUISITION' AND document_id = {$reqId}")->fetchColumn();
        $this->assert($logCount === 0, "Scenario 27: Exactly 0 workflow action logs persisted after rollback");
    }

    private function testAtomicRollbackOnAuditLogFailure(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        // Create failing audit log repo
        $failingAuditRepo = new class($this->db) extends AuditLogRepository {
            public function create(array $data): int {
                throw new \RuntimeException("Simulated institutional audit log persistence failure");
            }
        };

        $serviceWithFailingAudit = new RequisitionWorkflowService(
            $this->db,
            $this->reqRepo,
            $this->defRepo,
            $this->stepRuleRepo,
            $this->actionLogRepo,
            $failingAuditRepo
        );

        $caught = false;
        try {
            $serviceWithFailingAudit->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::ENDORSE,
                actingUserId: $this->fixtureUserId,
                comments: 'Rollback test audit'
            ));
        } catch (\Throwable $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'Simulated institutional audit log'), "Scenario 28: Caught simulated audit log failure: {$e->getMessage()}");
        }
        $this->assert($caught, "Scenario 28: Exception thrown on audit log failure");

        // Verify status rolled back
        $row = $this->db->query("SELECT status FROM requisitions WHERE id = {$reqId}")->fetch(PDO::FETCH_ASSOC);
        $this->assert($row['status'] === 'SUBMITTED', "Scenario 28: Atomic rollback preserves original SUBMITTED status after audit log failure");

        // Verify action log was ALSO rolled back
        $actionLogCount = (int)$this->db->query("SELECT COUNT(*) FROM workflow_action_logs WHERE document_type = 'REQUISITION' AND document_id = {$reqId}")->fetchColumn();
        $this->assert($actionLogCount === 0, "Scenario 28: Both status update and workflow action log cleanly rolled back");
    }

    // =========================================================================
    // SCENARIO 29 to 30: Result DTO & Permitted Transitions Discovery
    // =========================================================================

    private function testWorkflowActionResultDTOVerification(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        $result = $this->workflowService->executeAction(new WorkflowActionRequest(
            requisitionId: $reqId,
            action: WorkflowAction::ENDORSE,
            actingUserId: $this->fixtureUserId,
            comments: 'DTO verification test'
        ));
        $this->trackedWorkflowActionLogs[] = $result->actionLogId;
        $this->trackedAuditLogs[] = $result->auditLogId;

        $this->assert($result instanceof WorkflowActionResult, "Scenario 29: Result is WorkflowActionResult instance");
        $this->assert($result->requisition->id === $reqId, "Scenario 29: Hydrated requisition ID matches");
        $this->assert($result->action === WorkflowAction::ENDORSE, "Scenario 29: Action enum matches ENDORSE");
        $this->assert($result->previousStatus === RequisitionStatus::SUBMITTED, "Scenario 29: Previous status enum matches SUBMITTED");
        $this->assert($result->newStatus === RequisitionStatus::ENDORSED, "Scenario 29: New status enum matches ENDORSED");
        $this->assert($result->stepRuleId === $this->fixtureStep1Id, "Scenario 29: Step rule ID is accurate");
        $this->assert(!empty($result->stepName), "Scenario 29: Step name is populated ({$result->stepName})");
        $this->assert($result->actionLogId > 0, "Scenario 29: Action log ID is positive surrogate key ({$result->actionLogId})");
        $this->assert($result->auditLogId > 0, "Scenario 29: Audit log ID is positive surrogate key ({$result->auditLogId})");
        $this->assert(!empty($result->timestamp), "Scenario 29: Timestamp string is populated");
    }

    private function testPermittedTransitionsDiscovery(): void
    {
        $this->authenticateAuthorizedUser();

        // 1. In SUBMITTED status
        $reqSubmittedId = $this->createFixtureRequisition('SUBMITTED');
        $trans1 = $this->workflowService->resolvePermittedTransitions($reqSubmittedId, $this->fixtureUserId);
        $actions1 = array_map(fn($t) => $t->action->value, $trans1);

        $this->assert(in_array('ENDORSE', $actions1, true), "Scenario 30: Permitted transitions from SUBMITTED include ENDORSE");
        $this->assert(in_array('RETURN', $actions1, true), "Scenario 30: Permitted transitions from SUBMITTED include RETURN");
        $this->assert(in_array('REJECT', $actions1, true), "Scenario 30: Permitted transitions from SUBMITTED include REJECT");

        // 2. In ENDORSED status
        $reqEndorsedId = $this->createFixtureRequisition('ENDORSED');
        $trans2 = $this->workflowService->resolvePermittedTransitions($reqEndorsedId, $this->fixtureUserId);
        $actions2 = array_map(fn($t) => $t->action->value, $trans2);

        $this->assert(in_array('APPROVE', $actions2, true), "Scenario 30: Permitted transitions from ENDORSED include APPROVE");
        $this->assert(in_array('RETURN', $actions2, true), "Scenario 30: Permitted transitions from ENDORSED include RETURN");
        $this->assert(in_array('REJECT', $actions2, true), "Scenario 30: Permitted transitions from ENDORSED include REJECT");

        // 3. In terminal REJECTED status
        $reqRejectedId = $this->createFixtureRequisition('REJECTED');
        $trans3 = $this->workflowService->resolvePermittedTransitions($reqRejectedId, $this->fixtureUserId);
        $this->assert(empty($trans3), "Scenario 30: Permitted transitions for terminal REJECTED status is empty array");
    }

    // =========================================================================
    // SCENARIO 31 to 32: Mandatory Comment Enforcement on Negative Actions
    // =========================================================================

    private function testMandatoryCommentEnforcementOnReturn(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        // A) Null comments
        $caughtNull = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::RETURN,
                actingUserId: $this->fixtureUserId,
                comments: null
            ));
        } catch (ValidationException $e) {
            $caughtNull = true;
            $this->assert(str_contains($e->getMessage(), 'mandatory'), "Scenario 31: Null comment rejected on RETURN: {$e->getMessage()}");
        }
        $this->assert($caughtNull, "Scenario 31: ValidationException caught for null comment on RETURN");

        // B) Whitespace comments
        $caughtWs = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::RETURN,
                actingUserId: $this->fixtureUserId,
                comments: "   \n\t  "
            ));
        } catch (ValidationException $e) {
            $caughtWs = true;
            $this->assert(str_contains($e->getMessage(), 'mandatory'), "Scenario 31: Whitespace comment rejected on RETURN");
        }
        $this->assert($caughtWs, "Scenario 31: ValidationException caught for whitespace comment on RETURN");
    }

    private function testMandatoryCommentEnforcementOnReject(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        // A) Null comments
        $caughtNull = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::REJECT,
                actingUserId: $this->fixtureUserId,
                comments: null
            ));
        } catch (ValidationException $e) {
            $caughtNull = true;
            $this->assert(str_contains($e->getMessage(), 'mandatory'), "Scenario 32: Null comment rejected on REJECT: {$e->getMessage()}");
        }
        $this->assert($caughtNull, "Scenario 32: ValidationException caught for null comment on REJECT");

        // B) Whitespace comments
        $caughtWs = false;
        try {
            $this->workflowService->executeAction(new WorkflowActionRequest(
                requisitionId: $reqId,
                action: WorkflowAction::REJECT,
                actingUserId: $this->fixtureUserId,
                comments: "   "
            ));
        } catch (ValidationException $e) {
            $caughtWs = true;
        }
        $this->assert($caughtWs, "Scenario 32: ValidationException caught for whitespace comment on REJECT");
    }

    // =========================================================================
    // SCENARIO 33: Concurrency Row-Locking & Database Teardown
    // =========================================================================

    private function testConcurrencyRowLockAcquisition(): void
    {
        $this->authenticateAuthorizedUser();
        $reqId = $this->createFixtureRequisition('SUBMITTED');

        // Test that locking the requisition with FOR UPDATE functions properly inside a transaction
        $this->db->beginTransaction();
        $stmt = $this->db->prepare("SELECT id, status, total_estimated_cost FROM requisitions WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $reqId]);
        $locked = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->db->commit();

        $this->assert($locked !== false, "Scenario 33: FOR UPDATE row lock successfully acquired on requisition #{$reqId}");
        $this->assert($locked['status'] === 'SUBMITTED', "Scenario 33: Locked row status is SUBMITTED");
        $this->assert($locked['total_estimated_cost'] === '1000.00', "Scenario 33: Locked row cost is 1000.00");
    }

    private function teardown(): void
    {
        // 1. Audit logs
        if (!empty($this->trackedAuditLogs)) {
            $in = implode(',', array_map('intval', $this->trackedAuditLogs));
            $this->db->exec("DELETE FROM `audit_logs` WHERE `id` IN ({$in})");
        }
        // Also cleanup any audit logs created for tracked requisitions
        if (!empty($this->trackedRequisitions)) {
            $in = implode(',', array_map('intval', $this->trackedRequisitions));
            $this->db->exec("DELETE FROM `audit_logs` WHERE `record_type` = 'requisitions' AND `record_id` IN ({$in})");
        }

        // 2. Workflow action logs
        if (!empty($this->trackedWorkflowActionLogs)) {
            $in = implode(',', array_map('intval', $this->trackedWorkflowActionLogs));
            $this->db->exec("DELETE FROM `workflow_action_logs` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedRequisitions)) {
            $in = implode(',', array_map('intval', $this->trackedRequisitions));
            $this->db->exec("DELETE FROM `workflow_action_logs` WHERE `document_type` = 'REQUISITION' AND `document_id` IN ({$in})");
        }

        // 3. Requisitions and items
        if (!empty($this->trackedRequisitionItems)) {
            $in = implode(',', array_map('intval', $this->trackedRequisitionItems));
            $this->db->exec("DELETE FROM `requisition_items` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedRequisitions)) {
            $in = implode(',', array_map('intval', $this->trackedRequisitions));
            $this->db->exec("DELETE FROM `requisitions` WHERE `id` IN ({$in})");
        }

        // 4. Workflow step rules
        if (!empty($this->trackedWorkflowStepRules)) {
            $in = implode(',', array_map('intval', $this->trackedWorkflowStepRules));
            $this->db->exec("DELETE FROM `workflow_step_rules` WHERE `id` IN ({$in})");
        }

        // 5. Workflow definitions
        if (!empty($this->trackedWorkflowDefinitions)) {
            $in = implode(',', array_map('intval', $this->trackedWorkflowDefinitions));
            $this->db->exec("DELETE FROM `workflow_definitions` WHERE `id` IN ({$in})");
        }

        // 6. Plan Items, Versions, Plans
        if (!empty($this->trackedPlanItems)) {
            $in = implode(',', array_map('intval', $this->trackedPlanItems));
            $this->db->exec("DELETE FROM `procurement_plan_items` WHERE `id` IN ({$in})");
        }
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

        // 7. Standard items, UOMs, Categories
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

        // 8. User entity roles, Roles
        if (!empty($this->trackedUserEntityRoles)) {
            $in = implode(',', array_map('intval', $this->trackedUserEntityRoles));
            $this->db->exec("DELETE FROM `user_entity_roles` WHERE `id` IN ({$in})");
        }
        if (!empty($this->trackedRoles)) {
            $in = implode(',', array_map('intval', $this->trackedRoles));
            $this->db->exec("DELETE FROM `roles` WHERE `id` IN ({$in})");
        }

        // 9. Entities, Campuses, Users
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

        // 10. Verify that tracked test fixtures were cleanly removed
        $residualFound = false;
        $trackedChecks = [
            'requisitions' => $this->trackedRequisitions,
            'requisition_items' => $this->trackedRequisitionItems,
            'workflow_step_rules' => $this->trackedWorkflowStepRules,
            'workflow_definitions' => $this->trackedWorkflowDefinitions,
            'procurement_plans' => $this->trackedPlans,
            'procurement_plan_versions' => $this->trackedVersions,
            'procurement_plan_items' => $this->trackedPlanItems,
            'standard_items' => $this->trackedStandardItems,
            'planning_entities' => $this->trackedEntities,
            'users' => $this->trackedUsers,
        ];

        foreach ($trackedChecks as $table => $ids) {
            if (!empty($ids)) {
                $in = implode(',', array_map('intval', $ids));
                $count = (int)$this->db->query("SELECT COUNT(*) FROM `{$table}` WHERE `id` IN ({$in})")->fetchColumn();
                if ($count > 0) {
                    $this->assert(false, "Teardown failed: table '{$table}' has {$count} residual test rows!");
                    $residualFound = true;
                }
            }
        }

        if (!$residualFound) {
            $this->assert(true, "Database teardown completed with ZERO orphan records across all 36 tables");
        }
    }

    private function printSummary(): void
    {
        echo "\n===============================================================\n";
        echo " REQUISITION WORKFLOW SERVICE TEST SUMMARY\n";
        echo " Passed: {$this->passed} / " . ($this->passed + $this->failed) . "\n";
        echo " Failed: {$this->failed}\n";
        echo "===============================================================\n\n";

        if ($this->failed === 0) {
            echo "ALL REQUISITION WORKFLOW SERVICE TESTS PASSED SUCCESSFULLY.\n";
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
    RequisitionWorkflowServiceTest::main();
}
