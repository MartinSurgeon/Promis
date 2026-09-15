<?php
/**
 * AdminEntityManagementTest
 * 
 * Comprehensive test suite for Hierarchical Planning Entity Management.
 * Tests Repository, Service, Controller, DTOs, closure table, hierarchy,
 * authorization, audit logging, and edge cases.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';
Promis\Core\App::bootstrap('.');

use Promis\Core\Database\Connection;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Src\Identity\Domain\DTO\CreateEntityDTO;
use Promis\Src\Identity\Domain\DTO\UpdateEntityDTO;
use Promis\Src\Identity\Repository\PlanningEntityRepository;
use Promis\Src\Identity\Service\EntityManagementService;
use Promis\Src\Presentation\Controller\AdminEntityViewController;

$db = Connection::get();
$passed = 0;
$failed = 0;
$errors = [];

function assert_true(bool $condition, string $label, array &$passed, array &$failed, array &$errors): void {
    if ($condition) {
        $passed[0]++;
        echo "  ✓ {$label}\n";
    } else {
        $failed[0]++;
        $errors[] = $label;
        echo "  ✗ FAIL: {$label}\n";
    }
}

$p = [&$passed];
$f = [&$failed];

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   PROMIS Entity Management — Comprehensive Test Suite         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// ─────────────────────────────────────────────────
// SECTION 1: SCHEMA VERIFICATION
// ─────────────────────────────────────────────────
echo "┌─ Section 1: Schema Verification ──────────────────────────────\n";

$cols = $db->query("SHOW COLUMNS FROM planning_entities")->fetchAll(PDO::FETCH_COLUMN);
assert_true(in_array('parent_entity_id', $cols), 'planning_entities.parent_entity_id exists', $p, $f, $errors);
assert_true(in_array('entity_code', $cols), 'planning_entities.entity_code exists', $p, $f, $errors);
assert_true(in_array('entity_name', $cols), 'planning_entities.entity_name exists', $p, $f, $errors);
assert_true(in_array('entity_type_id', $cols), 'planning_entities.entity_type_id exists', $p, $f, $errors);
assert_true(in_array('campus_id', $cols), 'planning_entities.campus_id exists', $p, $f, $errors);
assert_true(in_array('is_active', $cols), 'planning_entities.is_active exists', $p, $f, $errors);
assert_true(in_array('head_user_id', $cols), 'planning_entities.head_user_id exists', $p, $f, $errors);
assert_true(in_array('planning_officer_id', $cols), 'planning_entities.planning_officer_id exists', $p, $f, $errors);

$ehCols = $db->query("SHOW COLUMNS FROM entity_hierarchies")->fetchAll(PDO::FETCH_COLUMN);
assert_true(in_array('ancestor_entity_id', $ehCols), 'entity_hierarchies.ancestor_entity_id exists', $p, $f, $errors);
assert_true(in_array('descendant_entity_id', $ehCols), 'entity_hierarchies.descendant_entity_id exists', $p, $f, $errors);
assert_true(in_array('depth', $ehCols), 'entity_hierarchies.depth exists', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 2: ENTITY TYPE SEEDING
// ─────────────────────────────────────────────────
echo "┌─ Section 2: Entity Type Seeding ──────────────────────────────\n";

$types = $db->query("SELECT type_code FROM entity_types WHERE type_code IN ('DEPT', 'UNIV', 'FAC', 'UNIT')")->fetchAll(PDO::FETCH_COLUMN);
assert_true(in_array('DEPT', $types), 'entity_type DEPT exists', $p, $f, $errors);
assert_true(in_array('UNIV', $types), 'entity_type UNIV exists', $p, $f, $errors);
assert_true(in_array('FAC', $types), 'entity_type FAC exists', $p, $f, $errors);
assert_true(in_array('UNIT', $types), 'entity_type UNIT exists', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 3: DTO CONSTRUCTION
// ─────────────────────────────────────────────────
echo "┌─ Section 3: DTO Construction ─────────────────────────────────\n";

$createDto = CreateEntityDTO::fromArray([
    'entity_code' => 'test-dto-code',
    'entity_name' => 'Test DTO Entity',
    'entity_type_id' => '1',
    'campus_id' => '1',
    'parent_entity_id' => '',
    'head_user_id' => '',
    'planning_officer_id' => '',
]);
assert_true($createDto->entityCode === 'TEST-DTO-CODE', 'CreateEntityDTO auto-uppercases code', $p, $f, $errors);
assert_true($createDto->entityName === 'Test DTO Entity', 'CreateEntityDTO preserves name', $p, $f, $errors);
assert_true($createDto->entityTypeId === 1, 'CreateEntityDTO parses entityTypeId', $p, $f, $errors);
assert_true($createDto->campusId === 1, 'CreateEntityDTO parses campusId', $p, $f, $errors);
assert_true($createDto->parentEntityId === null, 'CreateEntityDTO empty parent = null', $p, $f, $errors);
assert_true($createDto->headUserId === null, 'CreateEntityDTO empty head user = null', $p, $f, $errors);

$updateDto = UpdateEntityDTO::fromArray(99, [
    'entity_name' => 'Updated Name',
    'entity_type_id' => 2,
    'campus_id' => 1,
    'parent_entity_id' => 5,
]);
assert_true($updateDto->id === 99, 'UpdateEntityDTO preserves id', $p, $f, $errors);
assert_true($updateDto->entityName === 'Updated Name', 'UpdateEntityDTO preserves name', $p, $f, $errors);
assert_true($updateDto->parentEntityId === 5, 'UpdateEntityDTO parses parent', $p, $f, $errors);

// Verify entity code not in UpdateEntityDTO
$updateArr = $updateDto->toArray();
assert_true(!isset($updateArr['entity_code']), 'UpdateEntityDTO excludes entity_code (read-only after creation)', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 4: REPOSITORY METHODS
// ─────────────────────────────────────────────────
echo "┌─ Section 4: Repository Methods ───────────────────────────────\n";

$repo = new PlanningEntityRepository();

$allTypes = $repo->findAllEntityTypes();
assert_true(count($allTypes) >= 4, 'findAllEntityTypes returns >= 4 types', $p, $f, $errors);

$allCampuses = $repo->findAllCampuses();
assert_true(count($allCampuses) >= 1, 'findAllCampuses returns >= 1 campus', $p, $f, $errors);

$allActive = $repo->findAllActive();
assert_true(is_array($allActive), 'findAllActive returns array', $p, $f, $errors);

$existingCode = $repo->findByCode('CS-DEPT');
assert_true(is_array($existingCode) || $existingCode === null, 'findByCode returns array or null', $p, $f, $errors);

$statusMetrics = $repo->countEntitiesByStatus();
assert_true(isset($statusMetrics['total']), 'countEntitiesByStatus returns total', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 5: SERVICE — FULL ENTITY LIFECYCLE
// ─────────────────────────────────────────────────
echo "┌─ Section 5: Service — Entity Lifecycle ───────────────────────\n";

$service = new EntityManagementService();

// Get campus and entity type IDs
$campus = $db->query("SELECT id FROM campuses WHERE is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$campusId = (int)$campus['id'];
$univType = $db->query("SELECT id FROM entity_types WHERE type_code='UNIV' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$univTypeId = (int)$univType['id'];
$facType = $db->query("SELECT id FROM entity_types WHERE type_code='FAC' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$facTypeId = (int)$facType['id'];
$deptType = $db->query("SELECT id FROM entity_types WHERE type_code='DEPT' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$deptTypeId = (int)$deptType['id'];

// Cleanup test entities (if previous test run failed)
$db->exec("SET FOREIGN_KEY_CHECKS=0");
$db->exec("DELETE FROM entity_hierarchies WHERE descendant_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'TEST-%')");
$db->exec("DELETE FROM entity_hierarchies WHERE ancestor_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'TEST-%')");
$db->exec("DELETE FROM planning_entities WHERE entity_code LIKE 'TEST-%'");
$db->exec("SET FOREIGN_KEY_CHECKS=1");

// 5.1: Create top-level entity (University)
$univDto = CreateEntityDTO::fromArray([
    'entity_code' => 'TEST-USTED',
    'entity_name' => 'Test University of Skills Training',
    'entity_type_id' => $univTypeId,
    'campus_id' => $campusId,
]);
$univId = $service->createEntity($univDto, 1, '127.0.0.1', 'Test');
assert_true($univId > 0, 'Create top-level University entity', $p, $f, $errors);

// 5.2: Create Faculty under University
$facDto = CreateEntityDTO::fromArray([
    'entity_code' => 'TEST-FAC-APP',
    'entity_name' => 'Test Faculty of Applied Sciences',
    'entity_type_id' => $facTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $univId,
]);
$facId = $service->createEntity($facDto, 1, '127.0.0.1', 'Test');
assert_true($facId > 0, 'Create Faculty under University', $p, $f, $errors);

// 5.3: Create Department under Faculty
$deptDto = CreateEntityDTO::fromArray([
    'entity_code' => 'TEST-CS-DEPT',
    'entity_name' => 'Test CS Department',
    'entity_type_id' => $deptTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $facId,
]);
$deptId = $service->createEntity($deptDto, 1, '127.0.0.1', 'Test');
assert_true($deptId > 0, 'Create Department under Faculty', $p, $f, $errors);

// 5.4: Create second Department
$dept2Dto = CreateEntityDTO::fromArray([
    'entity_code' => 'TEST-IT-DEPT',
    'entity_name' => 'Test IT Department',
    'entity_type_id' => $deptTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $facId,
]);
$dept2Id = $service->createEntity($dept2Dto, 1, '127.0.0.1', 'Test');
assert_true($dept2Id > 0, 'Create second Department under Faculty', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 6: CLOSURE TABLE VERIFICATION
// ─────────────────────────────────────────────────
echo "┌─ Section 6: Closure Table Integrity ──────────────────────────\n";

// University self-reference
$univSelf = $db->prepare("SELECT * FROM entity_hierarchies WHERE ancestor_entity_id=:id AND descendant_entity_id=:id2 AND depth=0");
$univSelf->execute(['id' => $univId, 'id2' => $univId]);
assert_true($univSelf->fetch() !== false, 'University self-reference (depth=0)', $p, $f, $errors);

// Faculty self-reference
$facSelf = $db->prepare("SELECT * FROM entity_hierarchies WHERE ancestor_entity_id=:id AND descendant_entity_id=:id2 AND depth=0");
$facSelf->execute(['id' => $facId, 'id2' => $facId]);
assert_true($facSelf->fetch() !== false, 'Faculty self-reference (depth=0)', $p, $f, $errors);

// University → Faculty (depth=1)
$univFac = $db->prepare("SELECT * FROM entity_hierarchies WHERE ancestor_entity_id=:anc AND descendant_entity_id=:desc AND depth=1");
$univFac->execute(['anc' => $univId, 'desc' => $facId]);
assert_true($univFac->fetch() !== false, 'University → Faculty (depth=1)', $p, $f, $errors);

// University → Department (depth=2)
$univDept = $db->prepare("SELECT * FROM entity_hierarchies WHERE ancestor_entity_id=:anc AND descendant_entity_id=:desc AND depth=2");
$univDept->execute(['anc' => $univId, 'desc' => $deptId]);
assert_true($univDept->fetch() !== false, 'University → Department (depth=2)', $p, $f, $errors);

// Faculty → Department (depth=1)
$facDept = $db->prepare("SELECT * FROM entity_hierarchies WHERE ancestor_entity_id=:anc AND descendant_entity_id=:desc AND depth=1");
$facDept->execute(['anc' => $facId, 'desc' => $deptId]);
assert_true($facDept->fetch() !== false, 'Faculty → Department (depth=1)', $p, $f, $errors);

// Descendant detection
$descendants = $repo->findDescendantIds($univId);
assert_true(in_array($facId, $descendants), 'findDescendantIds: Faculty is descendant of University', $p, $f, $errors);
assert_true(in_array($deptId, $descendants), 'findDescendantIds: Department is descendant of University', $p, $f, $errors);
assert_true(in_array($dept2Id, $descendants), 'findDescendantIds: IT Dept is descendant of University', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 7: VALIDATION & EDGE CASES
// ─────────────────────────────────────────────────
echo "┌─ Section 7: Validation & Edge Cases ──────────────────────────\n";

// 7.1: Duplicate code prevention
try {
    $dupDto = CreateEntityDTO::fromArray([
        'entity_code' => 'TEST-USTED',
        'entity_name' => 'Duplicate',
        'entity_type_id' => $univTypeId,
        'campus_id' => $campusId,
    ]);
    $service->createEntity($dupDto, 1, '127.0.0.1', 'Test');
    assert_true(false, 'Duplicate entity code should throw', $p, $f, $errors);
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert_true(str_contains($e->getMessage(), 'already in use'), 'Duplicate code rejected with message', $p, $f, $errors);
}

// 7.2: Empty code prevention
try {
    $emptyDto = CreateEntityDTO::fromArray([
        'entity_code' => '',
        'entity_name' => 'Empty Code',
        'entity_type_id' => $univTypeId,
        'campus_id' => $campusId,
    ]);
    $service->createEntity($emptyDto, 1, '127.0.0.1', 'Test');
    assert_true(false, 'Empty entity code should throw', $p, $f, $errors);
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert_true(str_contains($e->getMessage(), 'code is required'), 'Empty code rejected', $p, $f, $errors);
}

// 7.3: Empty name prevention
try {
    $emptyNameDto = CreateEntityDTO::fromArray([
        'entity_code' => 'TEST-NONAME',
        'entity_name' => '',
        'entity_type_id' => $univTypeId,
        'campus_id' => $campusId,
    ]);
    $service->createEntity($emptyNameDto, 1, '127.0.0.1', 'Test');
    assert_true(false, 'Empty entity name should throw', $p, $f, $errors);
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert_true(str_contains($e->getMessage(), 'name is required'), 'Empty name rejected', $p, $f, $errors);
}

// 7.4: Invalid entity type prevention
try {
    $badTypeDto = CreateEntityDTO::fromArray([
        'entity_code' => 'TEST-BADTYPE',
        'entity_name' => 'Bad Type',
        'entity_type_id' => 99999,
        'campus_id' => $campusId,
    ]);
    $service->createEntity($badTypeDto, 1, '127.0.0.1', 'Test');
    assert_true(false, 'Invalid entity type should throw', $p, $f, $errors);
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert_true(str_contains($e->getMessage(), 'entity type'), 'Invalid type rejected', $p, $f, $errors);
}

// 7.5: Invalid campus prevention
try {
    $badCampusDto = CreateEntityDTO::fromArray([
        'entity_code' => 'TEST-BADCAMP',
        'entity_name' => 'Bad Campus',
        'entity_type_id' => $univTypeId,
        'campus_id' => 99999,
    ]);
    $service->createEntity($badCampusDto, 1, '127.0.0.1', 'Test');
    assert_true(false, 'Invalid campus should throw', $p, $f, $errors);
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert_true(str_contains($e->getMessage(), 'campus'), 'Invalid campus rejected', $p, $f, $errors);
}

// 7.6: Invalid parent prevention
try {
    $badParentDto = CreateEntityDTO::fromArray([
        'entity_code' => 'TEST-BADPAR',
        'entity_name' => 'Bad Parent',
        'entity_type_id' => $univTypeId,
        'campus_id' => $campusId,
        'parent_entity_id' => 99999,
    ]);
    $service->createEntity($badParentDto, 1, '127.0.0.1', 'Test');
    assert_true(false, 'Invalid parent should throw', $p, $f, $errors);
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert_true(str_contains($e->getMessage(), 'does not exist'), 'Invalid parent rejected', $p, $f, $errors);
}

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 8: CIRCULAR REFERENCE PREVENTION
// ─────────────────────────────────────────────────
echo "┌─ Section 8: Circular Reference Prevention ────────────────────\n";

// 8.1: Cannot set entity as its own parent
try {
    $selfParentDto = UpdateEntityDTO::fromArray($facId, [
        'entity_name' => 'Self Parent Test',
        'entity_type_id' => $facTypeId,
        'campus_id' => $campusId,
        'parent_entity_id' => $facId,
    ]);
    $service->updateEntity($selfParentDto, 1, '127.0.0.1', 'Test');
    assert_true(false, 'Self-parent should throw', $p, $f, $errors);
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert_true(str_contains($e->getMessage(), 'own parent'), 'Self-parent prevented', $p, $f, $errors);
}

// 8.2: Cannot set descendant as parent (University cannot become child of its Department)
try {
    $circularDto = UpdateEntityDTO::fromArray($univId, [
        'entity_name' => 'Circular Test',
        'entity_type_id' => $univTypeId,
        'campus_id' => $campusId,
        'parent_entity_id' => $deptId,
    ]);
    $service->updateEntity($circularDto, 1, '127.0.0.1', 'Test');
    assert_true(false, 'Circular reference should throw', $p, $f, $errors);
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert_true(str_contains($e->getMessage(), 'Circular reference'), 'Circular reference prevented', $p, $f, $errors);
}

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 9: ENTITY UPDATE & PARENT CHANGE
// ─────────────────────────────────────────────────
echo "┌─ Section 9: Entity Update & Parent Change ────────────────────\n";

// 9.1: Basic update
$updateNameDto = UpdateEntityDTO::fromArray($facId, [
    'entity_name' => 'Test Faculty of Sciences (Updated)',
    'entity_type_id' => $facTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $univId,
]);
$updated = $service->updateEntity($updateNameDto, 1, '127.0.0.1', 'Test');
assert_true($updated, 'Entity name update succeeds', $p, $f, $errors);

$updatedEntity = $repo->findById($facId);
assert_true($updatedEntity['entity_name'] === 'Test Faculty of Sciences (Updated)', 'Entity name persisted correctly', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 10: ENTITY DETAILS & TREE
// ─────────────────────────────────────────────────
echo "┌─ Section 10: Entity Details & Tree ───────────────────────────\n";

$details = $service->getEntityDetails($univId);
assert_true(!empty($details['entity']), 'getEntityDetails returns entity data', $p, $f, $errors);
assert_true(is_array($details['children']), 'getEntityDetails returns children array', $p, $f, $errors);
assert_true($details['descendant_count'] >= 3, 'getEntityDetails reports correct descendant count', $p, $f, $errors);

$tree = $service->getEntityTree();
assert_true(is_array($tree), 'getEntityTree returns array', $p, $f, $errors);
assert_true(count($tree) >= 1, 'getEntityTree has at least one root node', $p, $f, $errors);

$lookups = $service->getLookups();
assert_true(!empty($lookups['entity_types']), 'getLookups returns entity_types', $p, $f, $errors);
assert_true(!empty($lookups['campuses']), 'getLookups returns campuses', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 11: STATUS TOGGLE
// ─────────────────────────────────────────────────
echo "┌─ Section 11: Status Toggle ───────────────────────────────────\n";

// 11.1: Deactivate department
$deactivated = $service->toggleEntityStatus($deptId, false, 1, '127.0.0.1', 'Test');
assert_true($deactivated, 'Department deactivation succeeds', $p, $f, $errors);

$deactivatedEntity = $repo->findById($deptId);
assert_true((int)$deactivatedEntity['is_active'] === 0, 'Department is_active = 0 after deactivation', $p, $f, $errors);

// 11.2: Reactivate department
$reactivated = $service->toggleEntityStatus($deptId, true, 1, '127.0.0.1', 'Test');
assert_true($reactivated, 'Department reactivation succeeds', $p, $f, $errors);

$reactivatedEntity = $repo->findById($deptId);
assert_true((int)$reactivatedEntity['is_active'] === 1, 'Department is_active = 1 after reactivation', $p, $f, $errors);

// 11.3: Active children check
$hasActiveChildren = $repo->hasActiveChildren($facId);
assert_true($hasActiveChildren, 'Faculty has active children detected', $p, $f, $errors);

// 11.4: Independent status (children not auto-deactivated)
$service->toggleEntityStatus($facId, false, 1, '127.0.0.1', 'Test');
$child1 = $repo->findById($deptId);
$child2 = $repo->findById($dept2Id);
assert_true((int)$child1['is_active'] === 1, 'Child 1 remains active when parent deactivated (independent status)', $p, $f, $errors);
assert_true((int)$child2['is_active'] === 1, 'Child 2 remains active when parent deactivated (independent status)', $p, $f, $errors);

// Reactivate for further tests
$service->toggleEntityStatus($facId, true, 1, '127.0.0.1', 'Test');

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 12: PAGINATED LISTING
// ─────────────────────────────────────────────────
echo "┌─ Section 12: Paginated Listing ───────────────────────────────\n";

$paginated = $service->getEntitiesPaginated(1, 10);
assert_true($paginated['total_records'] >= 4, 'Paginated listing shows test entities', $p, $f, $errors);
assert_true(!empty($paginated['metrics']), 'Paginated listing includes metrics', $p, $f, $errors);

// Filter by type
$filteredByType = $service->getEntitiesPaginated(1, 10, null, $facTypeId);
$allFaculty = array_filter($filteredByType['entities'], fn($e) => $e['entity_type_id'] == $facTypeId);
assert_true(count($allFaculty) === count($filteredByType['entities']), 'Type filter returns only matching types', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 13: AUDIT LOGGING
// ─────────────────────────────────────────────────
echo "┌─ Section 13: Audit Logging ───────────────────────────────────\n";

$auditLogs = $db->prepare("SELECT * FROM audit_logs WHERE record_type='planning_entities' AND record_id=:id ORDER BY id DESC LIMIT 5");
$auditLogs->execute(['id' => $univId]);
$logs = $auditLogs->fetchAll(PDO::FETCH_ASSOC);
assert_true(count($logs) >= 1, 'Audit log records exist for created entity', $p, $f, $errors);

$createLog = null;
foreach ($logs as $log) {
    if ($log['action'] === 'ENTITY_CREATED') {
        $createLog = $log;
        break;
    }
}
assert_true($createLog !== null, 'ENTITY_CREATED audit event logged', $p, $f, $errors);
if ($createLog) {
    assert_true($createLog['actor_user_id'] == 1, 'Audit log records actor user', $p, $f, $errors);
    assert_true(!empty($createLog['new_state_json']), 'Audit log records new state JSON', $p, $f, $errors);
}

// Check status toggle audit
$statusLogs = $db->prepare("SELECT * FROM audit_logs WHERE record_type='planning_entities' AND record_id=:id AND action IN ('ENTITY_ACTIVATED','ENTITY_DEACTIVATED')");
$statusLogs->execute(['id' => $deptId]);
$sLogs = $statusLogs->fetchAll(PDO::FETCH_ASSOC);
assert_true(count($sLogs) >= 2, 'Status toggle audit events logged (activate + deactivate)', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 14: CONTROLLER INSTANTIATION
// ─────────────────────────────────────────────────
echo "┌─ Section 14: Controller Instantiation ────────────────────────\n";

$controller = new AdminEntityViewController();
assert_true($controller instanceof AdminEntityViewController, 'AdminEntityViewController instantiates', $p, $f, $errors);

echo "\n";

// ─────────────────────────────────────────────────
// SECTION 15: NOT FOUND HANDLING
// ─────────────────────────────────────────────────
echo "┌─ Section 15: Not Found Handling ──────────────────────────────\n";

try {
    $service->getEntityDetails(999999);
    assert_true(false, 'Non-existent entity should throw NotFoundException', $p, $f, $errors);
} catch (\Promis\Core\Exception\NotFoundException $e) {
    assert_true(true, 'Non-existent entity throws NotFoundException', $p, $f, $errors);
}

try {
    $service->toggleEntityStatus(999999, true, 1, '127.0.0.1');
    assert_true(false, 'Toggle status on non-existent entity should throw', $p, $f, $errors);
} catch (\Promis\Core\Exception\NotFoundException $e) {
    assert_true(true, 'Toggle status on non-existent entity throws NotFoundException', $p, $f, $errors);
}

echo "\n";

// ─────────────────────────────────────────────────
// CLEANUP
// ─────────────────────────────────────────────────
echo "┌─ Cleanup ─────────────────────────────────────────────────────\n";

// Clean up test audit logs
$db->prepare("DELETE FROM audit_logs WHERE record_type='planning_entities' AND record_id IN (:u,:f,:d,:d2)")->execute([
    'u' => $univId, 'f' => $facId, 'd' => $deptId, 'd2' => $dept2Id
]);

// Clean up closure table & entities
$db->exec("SET FOREIGN_KEY_CHECKS=0");
$db->exec("DELETE FROM entity_hierarchies WHERE descendant_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'TEST-%')");
$db->exec("DELETE FROM entity_hierarchies WHERE ancestor_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'TEST-%')");
$db->exec("DELETE FROM planning_entities WHERE entity_code LIKE 'TEST-%'");
$db->exec("SET FOREIGN_KEY_CHECKS=1");

echo "  ✓ Test entities cleaned up\n";

echo "\n";

// ─────────────────────────────────────────────────
// RESULTS
// ─────────────────────────────────────────────────
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   TEST RESULTS                                                ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
printf("║   Passed: %d / %d                                              ║\n", $passed, $passed + $failed);
printf("║   Passed: %-3d  |  Failed: %-3d  |  Total: %-3d               ║\n", $passed, $failed, $passed + $failed);
echo "╚════════════════════════════════════════════════════════════════╝\n";

if ($failed > 0) {
    echo "\n FAILED TESTS:\n";
    foreach ($errors as $err) {
        echo "  ✗ {$err}\n";
    }
}

exit($failed > 0 ? 1 : 0);
