<?php
/**
 * Role-Aware Sidebar Navigation Partial
 * PROMIS - Procurement Management Information System
 *
 * @var array|null $user
 * @var string $appUrl
 * @var string $activeNav
 */

$currentUser = is_callable($user) ? $user() : ($user ?? []);
$roles = $currentUser['roles'] ?? [];
$isRequester = in_array('REQUESTER', $roles, true);
$isHod = in_array('HOD', $roles, true);
$isDean = in_array('DEAN', $roles, true);
$isFinance = in_array('FINANCE_OFFICER', $roles, true);
$isProcurement = in_array('PROCUREMENT_OFFICER', $roles, true);
$isAdmin = in_array('ADMIN', $roles, true) || in_array('SUPER_ADMIN', $roles, true);

// Fallback if no specific role is assigned
if (empty($roles)) {
    $isRequester = true;
}

$active = $activeNav ?? 'dashboard';

$pendingBadge = $pendingCount ?? null;
if ($pendingBadge === null && !empty($roles)) {
    try {
        $db = \Promis\Core\Database\Connection::get();
        $pWhere = "1=1";
        if ($isHod) {
            $pWhere = "status = 'SUBMITTED'";
        } elseif ($isDean) {
            $pWhere = "status = 'ENDORSED'";
        } elseif ($isFinance) {
            $pWhere = "status = 'DEPARTMENT_APPROVED'";
        } elseif ($isProcurement) {
            $pWhere = "status = 'COMMITMENT_AUTHORIZED'";
        } else {
            $pWhere = "status IN ('SUBMITTED', 'ENDORSED', 'DEPARTMENT_APPROVED', 'COMMITMENT_AUTHORIZED')";
        }
        $pStmt = $db->query("SELECT COUNT(*) FROM requisitions WHERE {$pWhere}");
        $pendingBadge = (int)$pStmt->fetchColumn();
    } catch (\Throwable) {
        $pendingBadge = 0;
    }
}
?>

<aside class="app-sidebar" id="appSidebar">
    <!-- Brand / Logo Area -->
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between;">
        <a href="<?= $e($appUrl ?? '') ?>/dashboard" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: var(--color-text);">
            <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: var(--gradient-brand); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #ffffff; box-shadow: var(--shadow-sm);">
                🏛️
            </div>
            <div>
                <div style="font-size: 1.15rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; color: var(--color-primary);">PROMIS</div>
                <div style="font-size: 0.6875rem; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.05em;">USTED Ghana</div>
            </div>
        </a>
        <button type="button" id="sidebarCloseBtn" class="desktop-hidden" style="background: none; border: none; font-size: 1.125rem; color: var(--color-muted-text); cursor: pointer; padding: 0.25rem;" aria-label="Close sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav style="padding: 1rem 0.75rem; flex: 1; overflow-y: auto;">
        <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-muted-text); padding: 0.5rem 0.75rem 0.25rem;">
            Main Navigation
        </div>

        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="margin-bottom: 0.25rem;">
                <a href="<?= $e($appUrl ?? '') ?>/dashboard" class="nav-item <?= $active === 'dashboard' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.875rem; font-weight: <?= $active === 'dashboard' ? '600' : '500' ?>; color: <?= $active === 'dashboard' ? 'var(--color-primary)' : 'var(--color-text)' ?>; background: <?= $active === 'dashboard' ? 'rgba(140, 0, 59, 0.08)' : 'transparent' ?>; border-left: <?= $active === 'dashboard' ? '3px solid var(--color-primary)' : '3px solid transparent' ?>; transition: all 0.15s ease;">
                    <i class="fa-solid fa-chart-pie" style="width: 1.25rem; text-align: center; color: <?= $active === 'dashboard' ? 'var(--color-primary)' : 'var(--color-muted-text)' ?>;"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li style="margin-bottom: 0.25rem;">
                <a href="<?= $e($appUrl ?? '') ?>/procurement-plans" class="nav-item <?= $active === 'procurement_plans' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.875rem; font-weight: <?= $active === 'procurement_plans' ? '600' : '500' ?>; color: <?= $active === 'procurement_plans' ? 'var(--color-primary)' : 'var(--color-text)' ?>; background: <?= $active === 'procurement_plans' ? 'rgba(140, 0, 59, 0.08)' : 'transparent' ?>; border-left: <?= $active === 'procurement_plans' ? '3px solid var(--color-primary)' : '3px solid transparent' ?>; transition: all 0.15s ease;">
                    <i class="fa-solid fa-calendar-check" style="width: 1.25rem; text-align: center; color: <?= $active === 'procurement_plans' ? 'var(--color-primary)' : 'var(--color-muted-text)' ?>;"></i>
                    <span>Procurement Plans</span>
                </a>
            </li>

            <li style="margin-bottom: 0.25rem;">
                <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="nav-item <?= $active === 'requisitions' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.875rem; font-weight: <?= $active === 'requisitions' ? '600' : '500' ?>; color: <?= $active === 'requisitions' ? 'var(--color-primary)' : 'var(--color-text)' ?>; background: <?= $active === 'requisitions' ? 'rgba(140, 0, 59, 0.08)' : 'transparent' ?>; border-left: <?= $active === 'requisitions' ? '3px solid var(--color-primary)' : '3px solid transparent' ?>; transition: all 0.15s ease;">
                    <i class="fa-solid fa-file-invoice-dollar" style="width: 1.25rem; text-align: center; color: <?= $active === 'requisitions' ? 'var(--color-primary)' : 'var(--color-muted-text)' ?>;"></i>
                    <span>Purchase Requests</span>
                </a>
            </li>

            <?php if ($isHod || $isDean || $isFinance || $isProcurement || $isAdmin): ?>
                <li style="margin-bottom: 0.25rem;">
                    <a href="<?= $e($appUrl ?? '') ?>/requisitions?filter=pending" class="nav-item <?= $active === 'approvals' ? 'active' : '' ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 0.625rem 0.875rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.875rem; font-weight: <?= $active === 'approvals' ? '600' : '500' ?>; color: <?= $active === 'approvals' ? 'var(--color-primary)' : 'var(--color-text)' ?>; background: <?= $active === 'approvals' ? 'rgba(140, 0, 59, 0.08)' : 'transparent' ?>; border-left: <?= $active === 'approvals' ? '3px solid var(--color-primary)' : '3px solid transparent' ?>; transition: all 0.15s ease;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fa-solid fa-stamp" style="width: 1.25rem; text-align: center; color: <?= $active === 'approvals' ? 'var(--color-primary)' : 'var(--color-muted-text)' ?>;"></i>
                            <span>Requests Waiting for Me</span>
                        </div>
                        <?php if (!empty($pendingBadge) && $pendingBadge > 0): ?>
                            <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 0.375rem; font-size: 0.6875rem; font-weight: 700; border-radius: 10px; background: var(--color-primary); color: #ffffff; box-shadow: var(--shadow-sm);">
                                <?= (int)$pendingBadge ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <?php if ($isFinance || $isProcurement || $isAdmin): ?>
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-muted-text); padding: 1.25rem 0.75rem 0.25rem;">
                Finance & Purchases
            </div>

            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php if ($isFinance || $isAdmin): ?>
                    <li style="margin-bottom: 0.25rem;">
                        <a href="<?= $e($appUrl ?? '') ?>/dashboard#budget" class="nav-item <?= $active === 'budget' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.875rem; font-weight: 500; color: var(--color-text);">
                            <i class="fa-solid fa-vault" style="width: 1.25rem; text-align: center; color: var(--color-muted-text);"></i>
                            <span>Department Budgets</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($isProcurement || $isAdmin): ?>
                    <li style="margin-bottom: 0.25rem;">
                        <a href="<?= $e($appUrl ?? '') ?>/requisitions?stage=PROCUREMENT_RECEIPT" class="nav-item <?= $active === 'procurement' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.875rem; font-weight: 500; color: var(--color-text);">
                            <i class="fa-solid fa-boxes-packing" style="width: 1.25rem; text-align: center; color: var(--color-muted-text);"></i>
                            <span>Purchases & Deliveries</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-muted-text); padding: 1.25rem 0.75rem 0.25rem;">
                Administration
            </div>

            <ul style="list-style: none; padding: 0; margin: 0;">
                <li style="margin-bottom: 0.25rem;">
                    <a href="<?= $e($appUrl ?? '') ?>/admin/users" class="nav-item <?= $active === 'admin_users' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.875rem; font-weight: <?= $active === 'admin_users' ? '600' : '500' ?>; color: <?= $active === 'admin_users' ? 'var(--color-primary)' : 'var(--color-text)' ?>; background: <?= $active === 'admin_users' ? 'rgba(140, 0, 59, 0.08)' : 'transparent' ?>; border-left: <?= $active === 'admin_users' ? '3px solid var(--color-primary)' : '3px solid transparent' ?>; transition: all 0.15s ease;">
                        <i class="fa-solid fa-users-gear" style="width: 1.25rem; text-align: center; color: <?= $active === 'admin_users' ? 'var(--color-primary)' : 'var(--color-muted-text)' ?>;"></i>
                        <span>Staff Accounts</span>
                    </a>
                </li>
                <li style="margin-bottom: 0.25rem;">
                    <a href="<?= $e($appUrl ?? '') ?>/admin/entities" class="nav-item <?= $active === 'admin_entities' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.875rem; font-weight: <?= $active === 'admin_entities' ? '600' : '500' ?>; color: <?= $active === 'admin_entities' ? 'var(--color-primary)' : 'var(--color-text)' ?>; background: <?= $active === 'admin_entities' ? 'rgba(140, 0, 59, 0.08)' : 'transparent' ?>; border-left: <?= $active === 'admin_entities' ? '3px solid var(--color-primary)' : '3px solid transparent' ?>; transition: all 0.15s ease;">
                        <i class="fa-solid fa-sitemap" style="width: 1.25rem; text-align: center; color: <?= $active === 'admin_entities' ? 'var(--color-primary)' : 'var(--color-muted-text)' ?>;"></i>
                        <span>Departments & Units</span>
                    </a>
                </li>
                <li style="margin-bottom: 0.25rem;">
                    <a href="<?= $e($appUrl ?? '') ?>/dashboard#audit" class="nav-item" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.875rem; font-weight: 500; color: var(--color-text);">
                        <i class="fa-solid fa-clipboard-list" style="width: 1.25rem; text-align: center; color: var(--color-muted-text);"></i>
                        <span>Activity History</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>

    <!-- User Profile Footer Card -->
    <div style="padding: 1rem; border-top: 1px solid var(--color-border); background: var(--color-surface-secondary);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--color-primary); color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; flex-shrink: 0;">
                <?= $e(strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'] ?? 'U', 0, 1))) ?>
            </div>
            <div style="min-width: 0; flex: 1;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--color-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?= $e($currentUser['full_name'] ?? $currentUser['username'] ?? 'User') ?>
                </div>
                <div style="font-size: 0.6875rem; color: var(--color-muted-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?= $e(!empty($roles) ? implode(', ', $roles) : 'Staff Member') ?>
                </div>
            </div>
            <form action="<?= $e($appUrl ?? '') ?>/logout" method="POST" style="margin: 0;">
                <?= $csrf() ?>
                <button type="submit" 
                        style="background: none; border: none; color: var(--color-danger); cursor: pointer; padding: 0.375rem; font-size: 0.9375rem; border-radius: var(--radius-sm);" 
                        title="Sign Out"
                        data-confirm="Are you sure you want to end your active session and sign out of PROMIS?"
                        data-confirm-title="Sign Out Confirmation"
                        data-confirm-detail="Any unsaved progress in active forms will be discarded."
                        data-confirm-type="danger"
                        data-confirm-btn="Sign Out"
                        data-confirm-icon="fa-arrow-right-from-bracket">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </div>
</aside>
