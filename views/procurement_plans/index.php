<?php
/**
 * Procurement Plans List View
 * PROMIS - Procurement Management Information System
 *
 * @var array $plans
 * @var int $totalRows
 * @var int $page
 * @var int $totalPages
 * @var string $search
 * @var string $status
 * @var string $fiscalYear
 * @var int $entityFilter
 * @var array $statusCounts
 * @var array $availableEntities
 * @var bool $canCreate
 * @var string $appUrl
 * @var callable $e
 * @var array|null $user
 */

$currentUser = is_callable($user ?? null) ? $user() : ($user ?? []);
$roles = $currentUser['roles'] ?? [];
$isHod = in_array('HOD', $roles, true);
$isDean = in_array('DEAN', $roles, true);
$isFinance = in_array('FINANCE_OFFICER', $roles, true);
$isProcurement = in_array('PROCUREMENT_OFFICER', $roles, true);
$isAdmin = in_array('ADMIN', $roles, true);

// Calculate total value of displayed plans
$displayedTotal = '0.00';
foreach ($plans as $p) {
    if (!empty($p['total_estimated_cost'])) {
        $displayedTotal = \Promis\Src\Planning\Domain\Decimal::add($displayedTotal, (string)$p['total_estimated_cost'], 2);
    }
}
?>

<!-- Header & Primary Action CTA -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); margin: 0 0 0.375rem; letter-spacing: -0.02em;">
            Annual Procurement Plans
        </h1>
        <p style="font-size: 0.875rem; color: var(--color-muted-text); margin: 0; max-width: 650px;">
            Plan and manage annual department procurement requests for goods, works, and services.
        </p>
    </div>

    <?php if ($canCreate): ?>
        <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/create" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 700; box-shadow: var(--shadow-sm); padding: 0.625rem 1.25rem;">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span>New Procurement Plan</span>
        </a>
    <?php endif; ?>
</div>

<!-- KPI Summary Metrics Strip -->
<div class="queue-kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.125rem 1.25rem; display: flex; align-items: center; gap: 1rem; margin: 0; box-shadow: var(--shadow-sm);">
        <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.08); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div style="min-width: 0;">
            <div style="font-size: 1.375rem; font-weight: 800; color: var(--color-text); line-height: 1.2;">
                <?= (int)($statusCounts['ALL'] ?? 0) ?>
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-top: 0.25rem;">
                Total Plans
            </div>
        </div>
    </div>

    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.125rem 1.25rem; display: flex; align-items: center; gap: 1rem; margin: 0; box-shadow: var(--shadow-sm);">
        <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: rgba(0, 105, 56, 0.08); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div style="min-width: 0;">
            <div style="font-size: 1.375rem; font-weight: 800; color: var(--color-success); line-height: 1.2;">
                <?= (int)($statusCounts['APPROVED'] ?? 0) ?>
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-top: 0.25rem;">
                Approved Plans
            </div>
        </div>
    </div>

    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.125rem 1.25rem; display: flex; align-items: center; gap: 1rem; margin: 0; box-shadow: var(--shadow-sm);">
        <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: rgba(221, 153, 51, 0.12); color: var(--color-accent); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <div style="min-width: 0;">
            <div style="font-size: 1.375rem; font-weight: 800; color: var(--color-text); line-height: 1.2;">
                <?= (int)($statusCounts['SUBMITTED'] ?? 0) + (int)($statusCounts['UNDER_REVIEW'] ?? 0) ?>
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-top: 0.25rem;">
                Waiting for Approval
            </div>
        </div>
    </div>

    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.125rem 1.25rem; display: flex; align-items: center; gap: 1rem; margin: 0; box-shadow: var(--shadow-sm);">
        <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: rgba(59, 130, 246, 0.08); color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
            <i class="fa-solid fa-file-pen"></i>
        </div>
        <div style="min-width: 0;">
            <div style="font-size: 1.375rem; font-weight: 800; color: var(--color-text); line-height: 1.2;">
                <?= (int)($statusCounts['DRAFT'] ?? 0) + (int)($statusCounts['RETURNED'] ?? 0) ?>
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-top: 0.25rem;">
                Drafts & Returned Plans
            </div>
        </div>
    </div>
</div>

<!-- Status Filter Tabs -->
<div style="display: flex; gap: 0.375rem; overflow-x: auto; padding-bottom: 0.75rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--color-border);">
    <?php
    $tabs = [
        '' => ['label' => 'All Plans', 'count' => $statusCounts['ALL'] ?? 0],
        'DRAFT' => ['label' => 'Draft', 'count' => $statusCounts['DRAFT'] ?? 0],
        'SUBMITTED' => ['label' => 'Submitted', 'count' => $statusCounts['SUBMITTED'] ?? 0],
        'UNDER_REVIEW' => ['label' => 'Under Review', 'count' => $statusCounts['UNDER_REVIEW'] ?? 0],
        'APPROVED' => ['label' => 'Approved', 'count' => $statusCounts['APPROVED'] ?? 0],
        'RETURNED' => ['label' => 'Returned', 'count' => $statusCounts['RETURNED'] ?? 0],
        'REJECTED' => ['label' => 'Rejected', 'count' => $statusCounts['REJECTED'] ?? 0],
    ];
    ?>
    <?php foreach ($tabs as $tabKey => $tabInfo): ?>
        <?php $isActive = ($status === $tabKey) || ($status === '' && $tabKey === ''); ?>
        <a href="<?= $e($appUrl ?? '') ?>/procurement-plans?status=<?= urlencode($tabKey) ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $entityFilter ? '&entity_id=' . (int)$entityFilter : '' ?>"
           style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.875rem; border-radius: var(--radius-md); font-size: 0.8125rem; font-weight: <?= $isActive ? '700' : '500' ?>; text-decoration: none; white-space: nowrap; color: <?= $isActive ? 'var(--color-primary)' : 'var(--color-muted-text)' ?>; background: <?= $isActive ? 'rgba(140, 0, 59, 0.08)' : 'transparent' ?>; border: 1px solid <?= $isActive ? 'rgba(140, 0, 59, 0.2)' : 'transparent' ?>;">
            <span><?= $e($tabInfo['label']) ?></span>
            <span style="font-size: 0.6875rem; padding: 0.125rem 0.375rem; border-radius: 10px; background: <?= $isActive ? 'var(--color-primary)' : 'var(--color-border)' ?>; color: <?= $isActive ? '#ffffff' : 'var(--color-text)' ?>; font-weight: 700;">
                <?= (int)$tabInfo['count'] ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Search & Filter Controls -->
<div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1rem 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);">
    <form method="GET" action="<?= $e($appUrl ?? '') ?>/procurement-plans" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between; margin: 0;">
        <input type="hidden" name="status" value="<?= $e($status) ?>">

        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; flex: 1; min-width: 280px;">
            <div style="position: relative; flex: 1; min-width: 220px;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--color-muted-text); font-size: 0.875rem;"></i>
                <input type="text" name="search" value="<?= $e($search) ?>" placeholder="Search by plan number or department..." 
                       style="width: 100%; padding: 0.5rem 0.875rem 0.5rem 2.25rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text);">
            </div>

            <?php if (count($availableEntities) > 1): ?>
                <select name="entity_id" style="padding: 0.5rem 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text); min-width: 180px;">
                    <option value="">All Departments</option>
                    <?php foreach ($availableEntities as $ent): ?>
                        <option value="<?= (int)$ent['id'] ?>" <?= $entityFilter === (int)$ent['id'] ? 'selected' : '' ?>>
                            <?= $e($ent['entity_name']) ?> (<?= $e($ent['entity_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <select name="fiscal_year" style="padding: 0.5rem 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text); min-width: 130px;">
                <option value="">All Budget Years</option>
                <?php 
                $curYear = (int)date('Y');
                for ($y = $curYear + 1; $y >= $curYear - 2; $y--): ?>
                    <option value="<?= $y ?>" <?= $fiscalYear === (string)$y ? 'selected' : '' ?>>Year <?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if ($search || $status || $entityFilter || $fiscalYear): ?>
                <a href="<?= $e($appUrl ?? '') ?>/procurement-plans" class="btn btn-secondary" style="padding: 0.5rem 0.875rem; font-size: 0.875rem;" title="Clear filters">
                    <i class="fa-solid fa-xmark"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Plans Table Card -->
<div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;">
    <?php if (empty($plans)): ?>
        <div style="padding: 4rem 2rem; text-align: center;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(140, 0, 59, 0.06); color: var(--color-primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1rem;">
                <i class="fa-solid fa-calendar-xmark"></i>
            </div>
            <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.5rem;">No Procurement Plans Found</h3>
            <p style="font-size: 0.875rem; color: var(--color-muted-text); max-width: 450px; margin: 0 auto 1.5rem;">
                <?= $search || $status || $entityFilter ? 'No procurement plans match your filter criteria. Try adjusting your filters or search terms.' : 'No procurement plans have been created yet for your department or entity.' ?>
            </p>
            <?php if ($canCreate): ?>
                <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/create" class="btn btn-primary" style="font-weight: 600;">
                    <i class="fa-solid fa-plus"></i> Create New Plan
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left;">
                <thead>
                    <tr style="background: var(--color-surface-secondary); border-bottom: 1px solid var(--color-border); color: var(--color-muted-text); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 0.875rem 1rem;">Plan Number</th>
                        <th style="padding: 0.875rem 1rem;">Department</th>
                        <th style="padding: 0.875rem 1rem; text-align: center;">Budget Year</th>
                        <th style="padding: 0.875rem 1rem; text-align: center;">Version</th>
                        <th style="padding: 0.875rem 1rem; text-align: right;">Total Estimated Budget</th>
                        <th style="padding: 0.875rem 1rem; text-align: center;">Items</th>
                        <th style="padding: 0.875rem 1rem;">Status</th>
                        <th style="padding: 0.875rem 1rem; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $p): ?>
                        <?php
                        $st = strtoupper((string)$p['status']);
                        $statusBadgeClass = match($st) {
                            'APPROVED' => 'badge-success',
                            'SUBMITTED', 'UNDER_REVIEW' => 'badge-warning',
                            'DRAFT' => 'badge-info',
                            'RETURNED' => 'badge-accent',
                            'REJECTED' => 'badge-danger',
                            default => 'badge-muted',
                        };
                        ?>
                        <tr style="border-bottom: 1px solid var(--color-border); transition: background 0.1s ease;" onmouseover="this.style.background='var(--color-surface-secondary)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 0.875rem 1rem; font-weight: 700;">
                                <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= (int)$p['id'] ?>" style="color: var(--color-primary); text-decoration: none;">
                                    <?= $e($p['plan_number']) ?>
                                </a>
                            </td>
                            <td style="padding: 0.875rem 1rem;">
                                <div style="font-weight: 600; color: var(--color-text);"><?= $e($p['entity_name']) ?></div>
                                <div style="font-size: 0.6875rem; color: var(--color-muted-text);"><?= $e($p['entity_code']) ?></div>
                            </td>
                            <td style="padding: 0.875rem 1rem; text-align: center; font-weight: 600;">
                                <?= $e($p['fiscal_year']) ?>
                            </td>
                            <td style="padding: 0.875rem 1rem; text-align: center;">
                                <span style="display: inline-block; padding: 0.125rem 0.5rem; border-radius: var(--radius-sm); background: var(--color-surface-secondary); border: 1px solid var(--color-border); font-size: 0.75rem; font-weight: 700;">
                                    v<?= $e($p['version_number'] ?? '1.0') ?>
                                </span>
                            </td>
                            <td style="padding: 0.875rem 1rem; text-align: right; font-weight: 700; color: var(--color-text); font-variant-numeric: tabular-nums;">
                                GHS <?= number_format((float)($p['total_estimated_cost'] ?? 0), 2) ?>
                            </td>
                            <td style="padding: 0.875rem 1rem; text-align: center; color: var(--color-muted-text);">
                                <?= (int)($p['item_count'] ?? 0) ?>
                            </td>
                            <td style="padding: 0.875rem 1rem;">
                                <span class="badge <?= $statusBadgeClass ?>" style="font-size: 0.6875rem; font-weight: 700; padding: 0.25rem 0.5rem; border-radius: 4px; text-transform: uppercase;">
                                    <?= $e(str_replace('_', ' ', $st)) ?>
                                </span>
                            </td>
                            <td style="padding: 0.875rem 1rem; text-align: right; white-space: nowrap;">
                                <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= (int)$p['id'] ?>" class="btn btn-secondary" style="padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600;" title="View Plan Details">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>

                                <?php if (in_array($st, ['DRAFT', 'RETURNED'], true)): ?>
                                    <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= (int)$p['id'] ?>/edit" class="btn btn-outline" style="padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; margin-left: 0.25rem;" title="Edit Plan Items">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </a>
                                <?php endif; ?>

                                <?php if ($st === 'APPROVED'): ?>
                                    <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= (int)$p['id'] ?>/print" target="_blank" class="btn btn-outline" style="padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; margin-left: 0.25rem;" title="Print Plan (Matches Reference PDF)">
                                        <i class="fa-solid fa-print"></i> Print
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <?php if ($totalPages > 1): ?>
            <div style="padding: 0.875rem 1.25rem; border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <div style="font-size: 0.8125rem; color: var(--color-muted-text);">
                    Showing page <?= (int)$page ?> of <?= (int)$totalPages ?> (<?= (int)$totalRows ?> total plans)
                </div>
                <div style="display: flex; gap: 0.375rem;">
                    <?php if ($page > 1): ?>
                        <a href="<?= $e($appUrl ?? '') ?>/procurement-plans?page=<?= $page - 1 ?><?= $status ? '&status=' . urlencode($status) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $entityFilter ? '&entity_id=' . (int)$entityFilter : '' ?>" class="btn btn-secondary" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                            &laquo; Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($pNum = 1; $pNum <= $totalPages; $pNum++): ?>
                        <a href="<?= $e($appUrl ?? '') ?>/procurement-plans?page=<?= $pNum ?><?= $status ? '&status=' . urlencode($status) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $entityFilter ? '&entity_id=' . (int)$entityFilter : '' ?>" class="btn <?= $pNum === $page ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                            <?= $pNum ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= $e($appUrl ?? '') ?>/procurement-plans?page=<?= $page + 1 ?><?= $status ? '&status=' . urlencode($status) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $entityFilter ? '&entity_id=' . (int)$entityFilter : '' ?>" class="btn btn-secondary" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                            Next &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
