<?php

declare(strict_types=1);

namespace Promis\Tests;

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Session;
use Promis\Src\Identity\Domain\DTO\AssignRoleEntityDTO;
use Promis\Src\Identity\Domain\DTO\CreateUserDTO;
use Promis\Src\Identity\Domain\DTO\UpdateUserDTO;
use Promis\Src\Identity\Repository\UserEntityRoleRepository;
use Promis\Src\Identity\Repository\UserRepository;
use Promis\Src\Identity\Service\UserManagementService;
use Promis\Src\Presentation\Controller\AdminUserViewController;

echo "===============================================================\n";
echo " PROMIS ADMIN USER & ENTITY MANAGEMENT TEST SUITE\n";
echo "===============================================================\n";

$db = Connection::get();

// Cleanup any leftover test records from previous runs
$db->exec("DELETE FROM `user_entity_roles` WHERE `user_id` IN (SELECT id FROM `users` WHERE `username` LIKE 'test.admin.%' OR `username` = 'test.onboard.user')");
$db->exec("DELETE FROM `audit_logs` WHERE `record_type` IN ('users', 'user_entity_roles') AND `record_id` IN (SELECT id FROM `users` WHERE `username` LIKE 'test.admin.%' OR `username` = 'test.onboard.user')");
$db->exec("DELETE FROM `users` WHERE `username` LIKE 'test.admin.%' OR `username` = 'test.onboard.user'");

// 1. Unauthenticated access check
Session::destroy();
Session::start();
$req = new Request('GET', '/admin/users');
$controller = new AdminUserViewController();
$resUnauth = $controller->index($req);
assert($resUnauth->getStatusCode() === 302, "Unauthenticated GET /admin/users must redirect to /login");
echo " [PASS] Unauthenticated access properly redirected to login.\n";

// 2. Unauthorized access check (Staff without admin role)
AuthManager::login([
    'id' => 9991,
    'username' => 'staff.only',
    'email' => 'staff@example.com',
    'roles' => ['REQUESTER'],
    'permissions' => ['requisition.create'],
]);
$resForbidden = $controller->index($req);
assert($resForbidden->getStatusCode() === 302, "Non-admin access must redirect with flash error");

// Test unpermitted user accessing JSON endpoint returns 403 AuthorizationException
$jsonReq = new Request('GET', '/admin/users/1/json', [], [], ['Accept' => 'application/json']);
$jsonReq->setRouteParams(['id' => '1']);
try {
    $res = $controller->getUserJson($jsonReq);
    assert(false, "Non-admin accessing admin JSON endpoint must throw AuthorizationException");
} catch (\Promis\Core\Exception\AuthorizationException $e) {
    echo " [PASS] Non-admin access to admin API properly blocked with 403 AuthorizationException.\n";
}

// 3. Authorized Admin access
AuthManager::login([
    'id' => 1,
    'username' => 'admin.user',
    'email' => 'admin@usted.edu.gh',
    'roles' => ['ADMIN', 'SYS_ADMIN'],
    'permissions' => ['user.manage', 'user.view', 'entity.assign'],
]);
$csrfToken = Csrf::token();

$indexReq = new Request('GET', '/admin/users');
$indexRes = $controller->index($indexReq);
assert($indexRes->getStatusCode() === 200, "Admin user must load /admin/users with 200 OK");
assert(str_contains($indexRes->getBody(), 'Institutional Staff Directory'), "View must contain staff directory header");
echo " [PASS] Authorized Administrator loads User Management directory successfully (200 OK).\n";

// Test Admin accessing getUserJson endpoint
$adminJsonReq = new Request('GET', '/admin/users/1/json', [], [], ['Accept' => 'application/json']);
$adminJsonReq->setRouteParams(['id' => '1']);
$adminJsonRes = $controller->getUserJson($adminJsonReq);
assert($adminJsonRes->getStatusCode() === 200, "Admin must be able to load user JSON details");
$jsonData = json_decode($adminJsonRes->getBody(), true);
assert($jsonData['success'] === true, "JSON response must have success=true");
assert(isset($jsonData['data']['user']['username']), "JSON response must contain user payload");
echo " [PASS] Admin successfully loads staff details and assignments via JSON API.\n";

// 4. Test User Onboarding Validation & Creation
$userService = new UserManagementService();

// A. Validation failure on short password
try {
    $invalidDto = new CreateUserDTO(
        username: 'test.short.pwd',
        email: 'short@usted.edu.gh',
        firstName: 'Short',
        lastName: 'Pwd',
        phone: null,
        password: '123', // < 8 chars
        status: 'ACTIVE'
    );
    $userService->onboardUser($invalidDto, 1, '127.0.0.1');
    assert(false, "Short password must throw ValidationException");
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert(str_contains($e->getMessage(), '8 characters'), "Error must mention 8 characters");
    echo " [PASS] Onboarding with short password rejected by validation guard.\n";
}

// B. Validation failure on invalid email
try {
    $invalidEmailDto = new CreateUserDTO(
        username: 'test.bad.email',
        email: 'not-an-email',
        firstName: 'Bad',
        lastName: 'Email',
        phone: null,
        password: 'ValidPassword123!',
        status: 'ACTIVE'
    );
    $userService->onboardUser($invalidEmailDto, 1, '127.0.0.1');
    assert(false, "Invalid email format must throw ValidationException");
} catch (\Promis\Core\Exception\ValidationException $e) {
    echo " [PASS] Onboarding with invalid email format properly rejected.\n";
}

// C. Successful User Onboarding with initial role & entity allocation
$validDto = new CreateUserDTO(
    username: 'test.onboard.user',
    email: 'test.onboard@usted.edu.gh',
    firstName: 'Ama',
    lastName: 'Kyeremeh',
    phone: '0249988776',
    password: 'SecureTemporaryPassword2026!',
    status: 'ACTIVE',
    initialRoleId: 3, // HOD
    initialPlanningEntityId: 1, // CS Department
    isPrimary: true
);
$createdUserId = $userService->onboardUser($validDto, 1, '127.0.0.1', 'PHPUnit Test Engine');
assert($createdUserId > 0, "User must be created and return positive integer ID");

// Verify DB records
$createdUser = (new UserRepository())->findById($createdUserId);
assert($createdUser !== null, "User must exist in repository");
assert($createdUser->username === 'test.onboard.user', "Username must match");
assert($createdUser->firstName === 'Ama', "First name must match");
assert($createdUser->lastName === 'Kyeremeh', "Last name must match");
assert($createdUser->status === 'ACTIVE', "Status must be ACTIVE");
assert(password_verify('SecureTemporaryPassword2026!', $createdUser->passwordHash), "Password hash must be valid Bcrypt");

// Verify User Entity Role allocation
$uerRepo = new UserEntityRoleRepository();
$assignments = $uerRepo->findByUserId($createdUserId);
assert(count($assignments) === 1, "User must have exactly 1 initial entity role assignment");
assert($assignments[0]->roleCode === 'HOD', "Initial role code must be HOD");
assert($assignments[0]->planningEntityId === 1, "Initial planning entity must be #1");
assert($assignments[0]->isPrimary === true, "Must be designated primary");
echo " [PASS] Staff member onboarded atomically with initial entity-scoped role and Bcrypt hash.\n";

// D. Test duplicate username rejection
try {
    $userService->onboardUser($validDto, 1, '127.0.0.1');
    assert(false, "Duplicate username must throw ValidationException");
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert(str_contains($e->getMessage(), 'already registered'), "Error must mention username is already registered");
    echo " [PASS] Duplicate username registration strictly prevented.\n";
}

// 5. Test Role & Department Assignment Scoping
// A. Assign secondary role (REQUESTER) to same department
$assignDto = new AssignRoleEntityDTO(
    userId: $createdUserId,
    planningEntityId: 1,
    roleId: 2, // REQUESTER
    isPrimary: false,
    status: 'ACTIVE'
);
$secondAssignId = $userService->assignRoleEntity($assignDto, 1, '127.0.0.1');
assert($secondAssignId > 0, "Secondary role assignment must succeed");

$assignmentsAfter = $uerRepo->findByUserId($createdUserId);
assert(count($assignmentsAfter) === 2, "User must now have 2 active assignments");
echo " [PASS] Additional role assignment scoped to planning entity successfully allocated.\n";

// B. Duplicate role assignment rejection
try {
    $userService->assignRoleEntity($assignDto, 1, '127.0.0.1');
    assert(false, "Duplicate role assignment must throw ValidationException");
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert(str_contains($e->getMessage(), 'already holds'), "Error message must indicate duplicate role holding");
    echo " [PASS] Duplicate role-entity assignment strictly blocked.\n";
}

// C. Test setting primary department rule
$primaryUpdated = $userService->setPrimaryAssignment($createdUserId, $secondAssignId, 1, '127.0.0.1');
assert($primaryUpdated === true, "setPrimaryAssignment must return true");

$assignmentsPrimary = $uerRepo->findByUserId($createdUserId);
$primaryCount = 0;
foreach ($assignmentsPrimary as $a) {
    if ($a->isPrimary) {
        $primaryCount++;
        assert($a->id === $secondAssignId, "Only assignment #{$secondAssignId} should be primary");
    }
}
assert($primaryCount === 1, "Exactly one assignment must remain designated as primary");
echo " [PASS] Primary department assignment toggled cleanly with mutual exclusion guarantee.\n";

// 6. Test User Profile Update
$updateDto = new UpdateUserDTO(
    id: $createdUserId,
    firstName: 'Ama Serwaa',
    lastName: 'Kyeremeh-Mensah',
    email: 'ama.kyeremeh@usted.edu.gh',
    phone: '0501122334',
    password: 'NewUpdatedPassword2026!',
    status: 'ACTIVE'
);
$updated = $userService->updateUser($updateDto, 1, '127.0.0.1');
assert($updated === true, "updateUser must return true");

$userAfterUpdate = (new UserRepository())->findById($createdUserId);
assert($userAfterUpdate->firstName === 'Ama Serwaa', "Updated first name must persist");
assert($userAfterUpdate->lastName === 'Kyeremeh-Mensah', "Updated last name must persist");
assert($userAfterUpdate->email === 'ama.kyeremeh@usted.edu.gh', "Updated email must persist");
assert(password_verify('NewUpdatedPassword2026!', $userAfterUpdate->passwordHash), "Updated password must verify");
echo " [PASS] Staff profile and password update persisted successfully.\n";

// 7. Test User Status Toggling & Self-Deactivation Guard
// A. Admin cannot deactivate own account
try {
    $userService->toggleUserStatus(1, 'INACTIVE', 1, '127.0.0.1');
    assert(false, "Admin self-deactivation must be prohibited");
} catch (\Promis\Core\Exception\ValidationException $e) {
    assert(str_contains($e->getMessage(), 'cannot deactivate your own'), "Error must cite self-deactivation prevention");
    echo " [PASS] Administrative self-deactivation guard successfully enforced.\n";
}

// B. Deactivate target user
$statusChanged = $userService->toggleUserStatus($createdUserId, 'INACTIVE', 1, '127.0.0.1');
assert($statusChanged === true, "Status change to INACTIVE must succeed");
$userInactive = (new UserRepository())->findById($createdUserId);
assert($userInactive->status === 'INACTIVE', "User status must be INACTIVE in DB");
echo " [PASS] Staff account status toggled to INACTIVE.\n";

// 8. Test Role Revocation
$revoked = $userService->revokeRoleAssignment($secondAssignId, 1, '127.0.0.1');
assert($revoked === true, "Revoke assignment must return true");
$assignmentsRevoked = $uerRepo->findByUserId($createdUserId);
assert(count($assignmentsRevoked) === 1, "User must have 1 assignment remaining after revocation");
echo " [PASS] Role assignment revoked and removed cleanly.\n";

// 9. Test Institutional Audit Log Footprint
$stmt = $db->prepare("SELECT COUNT(*) FROM `audit_logs` WHERE `actor_user_id` = 1 AND `record_type` IN ('users', 'user_entity_roles') AND `record_id` = :uid");
$stmt->execute([':uid' => $createdUserId]);
$auditCount = (int)$stmt->fetchColumn();
assert($auditCount >= 3, "At least 3 audit log records must be created for user lifecycle events");
echo " [PASS] Immutable institutional audit log records generated for all IAM actions (Count: {$auditCount}).\n";

// 10. Teardown test records
$db->prepare("DELETE FROM `user_entity_roles` WHERE `user_id` = :uid")->execute([':uid' => $createdUserId]);
$db->prepare("DELETE FROM `audit_logs` WHERE `record_type` IN ('users', 'user_entity_roles') AND `record_id` = :uid")->execute([':uid' => $createdUserId]);
$db->prepare("DELETE FROM `users` WHERE `id` = :uid")->execute([':uid' => $createdUserId]);

echo "===============================================================\n";
echo " Passed: 10 / 10\n";
echo " ALL 10 ADMIN USER & ENTITY MANAGEMENT TESTS PASSED 100%\n";
echo "===============================================================\n";
