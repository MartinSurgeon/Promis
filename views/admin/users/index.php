<?php
/**
 * User & Entity Management View
 * PROMIS - Procurement Management Information System
 *
 * @var array $users
 * @var int $totalRecords
 * @var int $totalPages
 * @var int $page
 * @var string $search
 * @var string $roleFilter
 * @var int|null $entityFilter
 * @var string $statusFilter
 * @var array $metrics
 * @var array $entities
 * @var array $roles
 * @var string $appUrl
 * @var array|null $user
 */

$currentUser = is_callable($user) ? $user() : ($user ?? []);
?>

<!-- KPI Metrics Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- Metric 1: Total Staff -->
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--color-primary);">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.05em;">Total Staff Accounts</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); line-height: 1.2;"><?= (int)($metrics['total_users'] ?? 0) ?></div>
            <div style="font-size: 0.6875rem; color: var(--color-muted-text); margin-top: 0.125rem;">
                <span style="color: var(--color-success); font-weight: 600;"><?= (int)($metrics['active_users'] ?? 0) ?> Active</span> &bull; 
                <span style="color: var(--color-warning); font-weight: 600;"><?= (int)($metrics['pending_users'] ?? 0) ?> Pending</span>
            </div>
        </div>
    </div>

    <!-- Metric 2: Active Accounts -->
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--color-success);">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(22, 163, 74, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.05em;">Active Operational</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); line-height: 1.2;"><?= (int)($metrics['active_users'] ?? 0) ?></div>
            <div style="font-size: 0.6875rem; color: var(--color-muted-text); margin-top: 0.125rem;">
                <?= (int)($metrics['inactive_users'] ?? 0) ?> Inactive / Suspended
            </div>
        </div>
    </div>

    <!-- Metric 3: Planning Entities Covered -->
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--color-info);">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(2, 132, 199, 0.1); color: var(--color-info); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
            <i class="fa-solid fa-building-columns"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.05em;">Departments Covered</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); line-height: 1.2;"><?= (int)($metrics['assigned_entities'] ?? 0) ?></div>
            <div style="font-size: 0.6875rem; color: var(--color-muted-text); margin-top: 0.125rem;">
                Across USTED Academic Units
            </div>
        </div>
    </div>

    <!-- Metric 4: Role-Entity Allocations -->
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--color-accent);">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(217, 119, 6, 0.1); color: var(--color-accent); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.05em;">Role Scopes Granted</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); line-height: 1.2;"><?= (int)($metrics['total_assignments'] ?? 0) ?></div>
            <div style="font-size: 0.6875rem; color: var(--color-muted-text); margin-top: 0.125rem;">
                Active Entity Scopes
            </div>
        </div>
    </div>
</div>

<!-- Action Bar & Filter Header -->
<div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem;">
        <div>
            <h2 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.25rem;">
                Institutional Staff Directory
            </h2>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0;">
                Manage staff access, assign operational roles, and scope permissions to academic departments and faculties.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="openModal('onboardModal')" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-user-plus"></i>
                <span>Onboard Staff Member</span>
            </button>
        </div>
    </div>

    <!-- Search and Filter Form -->
    <form method="GET" action="<?= $e($appUrl ?? '') ?>/admin/users" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin: 0;">
        <!-- Search Input -->
        <div style="flex: 1; min-width: 220px; position: relative;">
            <input type="text" name="search" value="<?= $e($search ?? '') ?>" placeholder="Search by name, email, or username..."
                   style="width: 100%; padding: 0.5rem 0.75rem 0.5rem 2.25rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--color-muted-text); font-size: 0.875rem;"></i>
        </div>

        <!-- Role Filter -->
        <div style="min-width: 160px;">
            <select name="role" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                <option value="">All System Roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $e($r['role_code']) ?>" <?= ($roleFilter ?? '') === $r['role_code'] ? 'selected' : '' ?>>
                        <?= $e($r['role_title']) ?> (<?= $e($r['role_code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Planning Entity Filter -->
        <div style="min-width: 180px;">
            <select name="entity_id" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                <option value="">All Departments / Units</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int)$ent['id'] ?>" <?= ($entityFilter ?? 0) === (int)$ent['id'] ? 'selected' : '' ?>>
                        <?= $e($ent['entity_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Status Filter -->
        <div style="min-width: 130px;">
            <select name="status" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                <option value="">All Statuses</option>
                <option value="ACTIVE" <?= ($statusFilter ?? '') === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                <option value="PENDING" <?= ($statusFilter ?? '') === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                <option value="INACTIVE" <?= ($statusFilter ?? '') === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <!-- Submit & Reset Buttons -->
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if (!empty($search) || !empty($roleFilter) || !empty($entityFilter) || !empty($statusFilter)): ?>
                <a href="<?= $e($appUrl ?? '') ?>/admin/users" class="btn btn-outline" style="padding: 0.5rem 0.75rem;" title="Clear Filters">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Staff Directory Table -->
<div class="card" style="padding: 0; overflow: hidden; margin-bottom: 1.5rem;">
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="background: var(--color-surface-secondary); border-bottom: 1px solid var(--color-border);">
                    <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted-text);">Staff Member</th>
                    <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted-text);">Status</th>
                    <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted-text);">Assigned Roles</th>
                    <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted-text);">Scoped Departments</th>
                    <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted-text);">Last Login</th>
                    <th style="padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted-text); text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" style="padding: 3rem 1rem; text-align: center; color: var(--color-muted-text);">
                            <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">👥</div>
                            <div style="font-size: 1rem; font-weight: 600; color: var(--color-text);">No Staff Members Found</div>
                            <div style="font-size: 0.8125rem; margin-top: 0.25rem;">Try adjusting your search criteria or onboard a new staff member.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $uid = (int)$u['id'];
                        $fullName = trim($u['first_name'] . ' ' . $u['last_name']);
                        $statusClass = match($u['status']) {
                            'ACTIVE' => 'badge-success',
                            'PENDING' => 'badge-warning',
                            default => 'badge-secondary',
                        };
                        ?>
                        <tr style="border-bottom: 1px solid var(--color-border); transition: background 0.15s ease;">
                            <!-- Staff Member Info -->
                            <td style="padding: 0.875rem 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--gradient-brand); color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9375rem; flex-shrink: 0; box-shadow: var(--shadow-sm);">
                                        <?= $e(strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1))) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.875rem; color: var(--color-text);">
                                            <?= $e($fullName) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--color-muted-text); display: flex; gap: 0.5rem; align-items: center;">
                                            <span><i class="fa-solid fa-at" style="font-size: 0.6875rem;"></i> <?= $e($u['username']) ?></span>
                                            <span>&bull;</span>
                                            <span><?= $e($u['email']) ?></span>
                                            <?php if (!empty($u['phone'])): ?>
                                                <span>&bull;</span>
                                                <span><i class="fa-solid fa-phone" style="font-size: 0.6875rem;"></i> <?= $e($u['phone']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Status Badge -->
                            <td style="padding: 0.875rem 1rem;">
                                <span class="badge <?= $statusClass ?>" style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">
                                    <?= $e($u['status']) ?>
                                </span>
                            </td>

                            <!-- Assigned Roles -->
                            <td style="padding: 0.875rem 1rem;">
                                <?php if (empty($u['roles'])): ?>
                                    <span style="font-size: 0.75rem; color: var(--color-muted-text); font-style: italic;">No roles assigned</span>
                                <?php else: ?>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                        <?php foreach ($u['roles'] as $rc): ?>
                                            <?php
                                            $chipColor = match($rc) {
                                                'ADMIN', 'SYS_ADMIN' => 'background: rgba(140, 0, 59, 0.1); color: var(--color-primary); border: 1px solid rgba(140, 0, 59, 0.2);',
                                                'HOD' => 'background: rgba(2, 132, 199, 0.1); color: var(--color-info); border: 1px solid rgba(2, 132, 199, 0.2);',
                                                'DEAN' => 'background: rgba(124, 58, 237, 0.1); color: #7c3aed; border: 1px solid rgba(124, 58, 237, 0.2);',
                                                'FINANCE_OFFICER' => 'background: rgba(22, 163, 74, 0.1); color: var(--color-success); border: 1px solid rgba(22, 163, 74, 0.2);',
                                                'PROCUREMENT_OFFICER' => 'background: rgba(217, 119, 6, 0.1); color: var(--color-accent); border: 1px solid rgba(217, 119, 6, 0.2);',
                                                default => 'background: var(--color-surface-secondary); color: var(--color-text); border: 1px solid var(--color-border);',
                                            };
                                            ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); <?= $chipColor ?>">
                                                <i class="fa-solid fa-user-shield" style="font-size: 0.625rem;"></i> <?= $e($rc) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Scoped Departments -->
                            <td style="padding: 0.875rem 1rem;">
                                <?php if (empty($u['entities'])): ?>
                                    <span style="font-size: 0.75rem; color: var(--color-muted-text); font-style: italic;">Unassigned</span>
                                <?php else: ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                        <?php foreach ($u['assignments'] as $asgn): ?>
                                            <div style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.75rem;">
                                                <i class="fa-solid fa-building" style="font-size: 0.6875rem; color: var(--color-muted-text);"></i>
                                                <span style="color: var(--color-text); font-weight: 500;"><?= $e($asgn['entity_name']) ?></span>
                                                <?php if ($asgn['is_primary']): ?>
                                                    <span style="font-size: 0.625rem; background: rgba(22, 163, 74, 0.12); color: var(--color-success); font-weight: 700; padding: 0.1rem 0.375rem; border-radius: var(--radius-sm);" title="Primary Affiliation">
                                                        PRIMARY
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Last Login -->
                            <td style="padding: 0.875rem 1rem; font-size: 0.75rem; color: var(--color-muted-text);">
                                <?php if (!empty($u['last_login_at'])): ?>
                                    <i class="fa-regular fa-clock" style="margin-right: 0.25rem;"></i> <?= $e(date('M j, Y H:i', strtotime($u['last_login_at']))) ?>
                                <?php else: ?>
                                    <span style="font-style: italic;">Never logged in</span>
                                <?php endif; ?>
                            </td>

                            <!-- Action Buttons -->
                            <td style="padding: 0.875rem 1rem; text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; align-items: center; gap: 0.375rem;">
                                    <!-- Assign Roles & Entities -->
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openAssignModal(<?= $uid ?>)" title="Manage Roles & Entity Scopes">
                                        <i class="fa-solid fa-shield-halved"></i> Roles
                                    </button>

                                    <!-- Edit Profile -->
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openEditModal(<?= $uid ?>, '<?= $e(addslashes($u['first_name'])) ?>', '<?= $e(addslashes($u['last_name'])) ?>', '<?= $e(addslashes($u['email'])) ?>', '<?= $e(addslashes($u['phone'] ?? '')) ?>', '<?= $e($u['status']) ?>')" title="Edit Profile">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Status Toggle Button -->
                                    <?php if ($uid !== (int)($currentUser['id'] ?? 0)): ?>
                                        <?php if ($u['status'] === 'ACTIVE'): ?>
                                            <form method="POST" action="<?= $e($appUrl ?? '') ?>/admin/users/<?= $uid ?>/status" style="margin: 0; display: inline;">
                                                <?= $csrf() ?>
                                                <input type="hidden" name="status" value="INACTIVE">
                                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-danger); border-color: rgba(220, 38, 38, 0.3);" 
                                                        title="Deactivate Account"
                                                        data-confirm="Are you sure you want to deactivate staff member <?= $e($fullName) ?>?"
                                                        data-confirm-title="Deactivate Staff Account"
                                                        data-confirm-detail="The user will be immediately prevented from logging into PROMIS and executing approvals."
                                                        data-confirm-type="danger"
                                                        data-confirm-btn="Deactivate Staff">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= $e($appUrl ?? '') ?>/admin/users/<?= $uid ?>/status" style="margin: 0; display: inline;">
                                                <?= $csrf() ?>
                                                <input type="hidden" name="status" value="ACTIVE">
                                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-success); border-color: rgba(22, 163, 74, 0.3);" 
                                                        title="Activate Account"
                                                        data-confirm="Activate account for <?= $e($fullName) ?>?"
                                                        data-confirm-title="Activate Staff Account"
                                                        data-confirm-detail="The user will regain system access based on their assigned entity roles."
                                                        data-confirm-type="success"
                                                        data-confirm-btn="Activate Staff">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div style="padding: 1rem; border-top: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; background: var(--color-surface-secondary);">
            <div style="font-size: 0.8125rem; color: var(--color-muted-text);">
                Showing Page <span style="font-weight: 700; color: var(--color-text);"><?= $page ?></span> of <span style="font-weight: 700; color: var(--color-text);"><?= $totalPages ?></span> (<?= $totalRecords ?> total staff)
            </div>
            <div style="display: flex; gap: 0.375rem;">
                <?php if ($page > 1): ?>
                    <a href="<?= $e($appUrl ?? '') ?>/admin/users?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&entity_id=<?= urlencode((string)$entityFilter) ?>&status=<?= urlencode($statusFilter) ?>" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= $e($appUrl ?? '') ?>/admin/users?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&entity_id=<?= urlencode((string)$entityFilter) ?>&status=<?= urlencode($statusFilter) ?>" class="btn btn-outline btn-sm">
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: ONBOARD NEW STAFF MEMBER -->
<!-- ========================================================================= -->
<div id="onboardModal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="onboardModalTitle">
    <div class="modal-backdrop" onclick="closeModal('onboardModal')"></div>
    <div class="modal-content" style="max-width: 640px;">
        <div class="modal-header">
            <h3 id="onboardModalTitle" class="modal-title" style="color: var(--color-primary);">
                <i class="fa-solid fa-user-plus"></i> Onboard New Staff Member
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('onboardModal')" aria-label="Close dialog">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="<?= $e($appUrl ?? '') ?>/admin/users" onsubmit="return validateOnboardForm(this)" style="margin: 0;">
            <?= $csrf() ?>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0 0 1.25rem;">
                    Create a new staff identity and provision their initial institutional role and academic department binding.
                </p>

                <!-- Section: Personal Information -->
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-primary); margin-bottom: 0.75rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.25rem;">
                    1. Identity & Credentials
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <label for="ob_first_name" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">First Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" id="ob_first_name" name="first_name" required placeholder="e.g. Kwame"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                    </div>
                    <div>
                        <label for="ob_last_name" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Last Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" id="ob_last_name" name="last_name" required placeholder="e.g. Mensah"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <label for="ob_username" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Username / Staff ID <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" id="ob_username" name="username" required placeholder="e.g. kwame.mensah"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                    </div>
                    <div>
                        <label for="ob_email" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Institutional Email <span style="color: var(--color-danger);">*</span></label>
                        <input type="email" id="ob_email" name="email" required placeholder="kmensah@usted.edu.gh"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div>
                        <label for="ob_phone" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Phone Number</label>
                        <input type="text" id="ob_phone" name="phone" placeholder="+233 24 123 4567"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                    </div>
                    <div>
                        <label for="ob_password" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Temporary Password <span style="color: var(--color-danger);">*</span></label>
                        <input type="password" id="ob_password" name="password" required minlength="8" placeholder="Minimum 8 characters"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                    </div>
                </div>

                <!-- Section: Initial Role & Entity Allocation -->
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-primary); margin-bottom: 0.75rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.25rem;">
                    2. Role & Department Allocation (Optional)
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <label for="ob_role_id" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Initial Role</label>
                        <select id="ob_role_id" name="initial_role_id" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                            <option value="">-- Assign Later --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= $e($r['role_title']) ?> (<?= $e($r['role_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="ob_entity_id" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Academic Department / Unit</label>
                        <select id="ob_entity_id" name="initial_planning_entity_id" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                            <option value="">-- Assign Later --</option>
                            <?php foreach ($entities as $ent): ?>
                                <option value="<?= (int)$ent['id'] ?>"><?= $e($ent['entity_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <input type="checkbox" id="ob_is_primary" name="is_primary" value="1" checked style="width: 1rem; height: 1rem; accent-color: var(--color-primary);">
                    <label for="ob_is_primary" style="font-size: 0.8125rem; color: var(--color-text); cursor: pointer;">
                        Designate as officer's Primary Home Department
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('onboardModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Complete Onboarding</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: EDIT STAFF PROFILE -->
<!-- ========================================================================= -->
<div id="editUserModal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="editUserModalTitle">
    <div class="modal-backdrop" onclick="closeModal('editUserModal')"></div>
    <div class="modal-content" style="max-width: 540px;">
        <div class="modal-header">
            <h3 id="editUserModalTitle" class="modal-title" style="color: var(--color-primary);">
                <i class="fa-solid fa-user-pen"></i> Edit Staff Profile
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('editUserModal')" aria-label="Close dialog">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="editUserForm" method="POST" action="" style="margin: 0;">
            <?= $csrf() ?>
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <label for="eu_first_name" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">First Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" id="eu_first_name" name="first_name" required
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                    </div>
                    <div>
                        <label for="eu_last_name" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Last Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" id="eu_last_name" name="last_name" required
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <label for="eu_email" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Institutional Email <span style="color: var(--color-danger);">*</span></label>
                        <input type="email" id="eu_email" name="email" required
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                    </div>
                    <div>
                        <label for="eu_phone" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Phone Number</label>
                        <input type="text" id="eu_phone" name="phone"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                    </div>
                </div>

                <div style="margin-bottom: 0.75rem;">
                    <label for="eu_status" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Account Status</label>
                    <select id="eu_status" name="status" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                        <option value="ACTIVE">ACTIVE</option>
                        <option value="PENDING">PENDING</option>
                        <option value="INACTIVE">INACTIVE</option>
                    </select>
                </div>

                <div style="margin-bottom: 0.5rem;">
                    <label for="eu_password" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Reset Password <span style="font-weight: normal; color: var(--color-muted-text);">(Leave blank to keep current)</span></label>
                    <input type="password" id="eu_password" name="password" minlength="8" placeholder="Enter new password to reset..."
                           style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Profile Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: MANAGE ROLES & ENTITY SCOPING -->
<!-- ========================================================================= -->
<div id="assignRoleModal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="assignRoleModalTitle">
    <div class="modal-backdrop" onclick="closeModal('assignRoleModal')"></div>
    <div class="modal-content" style="max-width: 680px;">
        <div class="modal-header">
            <h3 id="assignRoleModalTitle" class="modal-title" style="color: var(--color-primary);">
                <i class="fa-solid fa-shield-halved"></i> Manage Roles & Department Scopes
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('assignRoleModal')" aria-label="Close dialog">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
            <!-- Staff Header Banner -->
            <div id="assignModalStaffBanner" style="padding: 0.875rem 1rem; border-radius: var(--radius-md); background: var(--color-surface-secondary); margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div id="assignStaffName" style="font-weight: 700; font-size: 0.9375rem; color: var(--color-text);">Loading...</div>
                    <div id="assignStaffMeta" style="font-size: 0.75rem; color: var(--color-muted-text);">Loading identity...</div>
                </div>
                <div id="assignStaffStatus"></div>
            </div>

            <!-- Existing Active Assignments -->
            <div style="margin-bottom: 1.5rem;">
                <div style="font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-muted-text); margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>Active Departmental Roles</span>
                    <span id="assignCountBadge" class="badge badge-secondary" style="font-size: 0.6875rem;">0 Scopes</span>
                </div>
                <div id="existingAssignmentsContainer" style="border: 1px solid var(--color-border); border-radius: var(--radius-md); overflow: hidden;">
                    <div style="padding: 1.5rem; text-align: center; color: var(--color-muted-text); font-size: 0.8125rem;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Loading role assignments...
                    </div>
                </div>
            </div>

            <!-- Add New Assignment Section -->
            <div style="border-top: 1px solid var(--color-border); padding-top: 1.25rem;">
                <div style="font-size: 0.875rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-plus-circle" style="color: var(--color-primary);"></i> Grant New Role & Department Scope
                </div>
                <form id="newAssignmentForm" method="POST" action="" style="margin: 0;">
                    <?= $csrf() ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div>
                            <label for="asgn_role_id" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">System Role <span style="color: var(--color-danger);">*</span></label>
                            <select id="asgn_role_id" name="role_id" required style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>"><?= $e($r['role_title']) ?> (<?= $e($r['role_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="asgn_entity_id" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">Planning Entity / Unit <span style="color: var(--color-danger);">*</span></label>
                            <select id="asgn_entity_id" name="planning_entity_id" required style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($entities as $ent): ?>
                                    <option value="<?= (int)$ent['id'] ?>"><?= $e($ent['entity_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="asgn_is_primary" name="is_primary" value="1" style="width: 1rem; height: 1rem; accent-color: var(--color-primary);">
                            <label for="asgn_is_primary" style="font-size: 0.8125rem; color: var(--color-text); cursor: pointer;">
                                Set as Primary Home Department
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-plus"></i> Grant Role Scope
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('assignRoleModal')">Close</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- CLIENT-SIDE SCRIPT FOR MODALS & LIVE VALIDATION -->
<!-- ========================================================================= -->
<script>
const APP_URL = '<?= $e($appUrl ?? '') ?>';
const CSRF_TOKEN = '<?= \Promis\Core\Security\Csrf::token() ?>';

function openEditModal(userId, firstName, lastName, email, phone, status) {
    document.getElementById('editUserForm').action = APP_URL + '/admin/users/' + userId + '/edit';
    document.getElementById('eu_first_name').value = firstName;
    document.getElementById('eu_last_name').value = lastName;
    document.getElementById('eu_email').value = email;
    document.getElementById('eu_phone').value = phone;
    document.getElementById('eu_status').value = status;
    document.getElementById('eu_password').value = '';
    openModal('editUserModal');
}

async function openAssignModal(userId) {
    openModal('assignRoleModal');
    
    document.getElementById('assignStaffName').innerText = 'Loading...';
    document.getElementById('assignStaffMeta').innerText = 'Fetching assignments...';
    document.getElementById('assignStaffStatus').innerHTML = '';
    document.getElementById('newAssignmentForm').action = APP_URL + '/admin/users/' + userId + '/roles';
    
    const container = document.getElementById('existingAssignmentsContainer');
    container.innerHTML = '<div style="padding: 1.5rem; text-align: center; color: var(--color-muted-text); font-size: 0.8125rem;"><i class="fa-solid fa-spinner fa-spin"></i> Loading role assignments...</div>';

    try {
        const response = await fetch(APP_URL + '/admin/users/' + userId + '/json', {
            headers: { 'Accept': 'application/json' }
        });
        const json = await response.json();
        
        if (!json.success || !json.data) {
            container.innerHTML = '<div style="padding: 1rem; color: var(--color-danger); font-size: 0.8125rem;">Failed to load staff details.</div>';
            return;
        }

        const user = json.data.user;
        const assignments = json.data.assignments || [];

        document.getElementById('assignStaffName').innerText = user.first_name + ' ' + user.last_name;
        document.getElementById('assignStaffMeta').innerText = '@' + user.username + ' • ' + user.email;
        document.getElementById('assignStaffStatus').innerHTML = '<span class="badge ' + (user.status === 'ACTIVE' ? 'badge-success' : 'badge-secondary') + '" style="font-size: 0.6875rem;">' + user.status + '</span>';
        document.getElementById('assignCountBadge').innerText = assignments.length + ' Scopes';

        if (assignments.length === 0) {
            container.innerHTML = '<div style="padding: 1.25rem; text-align: center; color: var(--color-muted-text); font-size: 0.8125rem; font-style: italic;">No active role or departmental scopes assigned to this user yet.</div>';
            return;
        }

        let html = '<table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">';
        html += '<thead><tr style="background: var(--color-surface-secondary); border-bottom: 1px solid var(--color-border);">';
        html += '<th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.6875rem; text-transform: uppercase; color: var(--color-muted-text);">Role</th>';
        html += '<th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.6875rem; text-transform: uppercase; color: var(--color-muted-text);">Department / Unit</th>';
        html += '<th style="padding: 0.5rem 0.75rem; text-align: center; font-size: 0.6875rem; text-transform: uppercase; color: var(--color-muted-text);">Primary</th>';
        html += '<th style="padding: 0.5rem 0.75rem; text-align: right; font-size: 0.6875rem; text-transform: uppercase; color: var(--color-muted-text);">Actions</th>';
        html += '</tr></thead><tbody>';

        assignments.forEach(a => {
            html += '<tr style="border-bottom: 1px solid var(--color-border);">';
            html += '<td style="padding: 0.5rem 0.75rem; font-weight: 600; color: var(--color-primary);">' + escapeHtml(a.role_title) + ' <span style="font-size: 0.6875rem; font-weight: normal; color: var(--color-muted-text);">(' + escapeHtml(a.role_code) + ')</span></td>';
            html += '<td style="padding: 0.5rem 0.75rem; color: var(--color-text);">' + escapeHtml(a.entity_name) + '</td>';
            html += '<td style="padding: 0.5rem 0.75rem; text-align: center;">';
            if (a.is_primary) {
                html += '<span style="font-size: 0.625rem; background: rgba(22, 163, 74, 0.15); color: var(--color-success); font-weight: 700; padding: 0.15rem 0.4rem; border-radius: var(--radius-sm);">PRIMARY</span>';
            } else {
                html += '<form method="POST" action="' + APP_URL + '/admin/users/' + userId + '/roles/' + a.id + '/primary" style="display:inline; margin:0;">';
                html += '<input type="hidden" name="_csrf_token" value="' + CSRF_TOKEN + '">';
                html += '<button type="submit" class="btn btn-outline btn-sm" style="font-size: 0.625rem; padding: 0.15rem 0.35rem;" title="Make Primary Department">Set Primary</button>';
                html += '</form>';
            }
            html += '</td>';
            html += '<td style="padding: 0.5rem 0.75rem; text-align: right;">';
            html += '<form method="POST" action="' + APP_URL + '/admin/users/' + userId + '/roles/' + a.id + '/delete" style="display:inline; margin:0;">';
            html += '<input type="hidden" name="_csrf_token" value="' + CSRF_TOKEN + '">';
            html += '<button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-danger); border-color: rgba(220, 38, 38, 0.3); font-size: 0.6875rem; padding: 0.2rem 0.4rem;" ';
            html += 'data-confirm="Revoke role ' + escapeHtml(a.role_title) + ' for ' + escapeHtml(a.entity_name) + '?" ';
            html += 'data-confirm-title="Revoke Role Assignment" ';
            html += 'data-confirm-detail="The user will no longer be permitted to perform approvals or workflow actions for this department." ';
            html += 'data-confirm-type="danger" ';
            html += 'data-confirm-btn="Revoke Role">';
            html += '<i class="fa-solid fa-trash"></i> Revoke</button>';
            html += '</form>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        container.innerHTML = html;
        
        // Re-attach confirm handler if needed
        if (window.initPromisConfirmDelegation) {
            window.initPromisConfirmDelegation();
        }

    } catch (e) {
        container.innerHTML = '<div style="padding: 1rem; color: var(--color-danger); font-size: 0.8125rem;">Network error fetching assignments.</div>';
    }
}

function validateOnboardForm(form) {
    const password = form.password.value;
    if (password.length < 8) {
        if (window.PromisAlert) {
            window.PromisAlert({
                title: 'Password Too Short',
                message: 'Temporary password must be at least 8 characters in length for institutional security compliance.',
                type: 'warning'
            });
        } else {
            alert('Temporary password must be at least 8 characters long.');
        }
        form.password.focus();
        return false;
    }
    return true;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
