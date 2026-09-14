<?php
/**
 * Requisitions List & Approval Queue View
 * PROMIS - Procurement Management Information System
 *
 * @var array $requisitions
 * @var int $totalRecords
 * @var float|null $totalQueueCost
 * @var int|null $pendingCount
 * @var int $page
 * @var int $totalPages
 * @var string $search
 * @var string $status
 * @var string $filter
 * @var string $appUrl
 * @var callable $e
 * @var array|null $user
 */

use Promis\Src\Execution\Domain\RequisitionStatus;

$statuses = RequisitionStatus::filterList();
$currentUser = is_callable($user ?? null) ? $user() : ($user ?? []);
$roles = $currentUser['roles'] ?? [];

$isHod = in_array('HOD', $roles, true);
$isDean = in_array('DEAN', $roles, true);
$isFinance = in_array('FINANCE_OFFICER', $roles, true);
$isProcurement = in_array('PROCUREMENT_OFFICER', $roles, true);
$isAdmin = in_array('ADMIN', $roles, true) || in_array('SUPER_ADMIN', $roles, true);
$isRequester = in_array('REQUESTER', $roles, true);

$primaryRole = match(true) {
    $isHod => 'HOD',
    $isDean => 'DEAN',
    $isFinance => 'FINANCE_OFFICER',
    $isProcurement => 'PROCUREMENT_OFFICER',
    $isAdmin => 'ADMIN',
    $isRequester => 'REQUESTER',
    default => !empty($roles) ? $roles[0] : 'STAFF'
};

$stageName = match($primaryRole) {
    'HOD' => 'Departmental Endorsement',
    'DEAN' => 'Deanship Approval',
    'FINANCE_OFFICER' => 'Budget Commitment',
    'PROCUREMENT_OFFICER' => 'Procurement Receipt',
    'ADMIN' => 'Governance Review',
    'REQUESTER' => 'Authoring Draft',
    default => 'Governance Review',
};
?>

<!-- Queue KPI Summary Metrics Strip -->
<div class="queue-kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
    <!-- KPI 1: Queue Workload -->
    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; display: flex; align-items: center; gap: 1.125rem; box-shadow: var(--shadow-sm); margin: 0;">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.08); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
            <i class="fa-solid fa-inbox"></i>
        </div>
        <div style="min-width: 0;">
            <div style="font-size: 1.375rem; font-weight: 800; color: var(--color-text); line-height: 1.2; font-variant-numeric: tabular-nums;">
                <?= (int)$totalRecords ?> <span style="font-size: 0.8125rem; font-weight: 500; color: var(--color-muted-text);"><?= (int)$totalRecords === 1 ? 'Request' : 'Requests' ?></span>
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-top: 0.25rem; line-height: 1.3;">
                <?= $filter === 'pending' ? 'Requests Waiting for You' : 'Total Requisitions' ?>
            </div>
        </div>
    </div>

    <!-- KPI 2: Total Estimated Commitment -->
    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; display: flex; align-items: center; gap: 1.125rem; box-shadow: var(--shadow-sm); margin: 0;">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(0, 105, 56, 0.08); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div style="min-width: 0;">
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--color-text); line-height: 1.2; font-variant-numeric: tabular-nums;">
                GHS <?= number_format((float)($totalQueueCost ?? 0), 2) ?>
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-top: 0.25rem; line-height: 1.3;">
                Total Cost of Requests
            </div>
        </div>
    </div>

    <!-- KPI 3: Governance Stage -->
    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; display: flex; align-items: center; gap: 1.125rem; box-shadow: var(--shadow-sm); margin: 0;">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(221, 153, 51, 0.12); color: var(--color-accent); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
            <i class="fa-solid fa-stamp"></i>
        </div>
        <div style="min-width: 0;">
            <div style="font-size: 1rem; font-weight: 700; color: var(--color-text); line-height: 1.2; word-break: break-word;">
                <?= $e($stageName) ?>
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-top: 0.25rem; line-height: 1.3;">
                <?= $e(str_replace('_', ' ', $primaryRole)) ?> Stage
            </div>
        </div>
    </div>

    <!-- KPI 4: Compliance Standard -->
    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; display: flex; align-items: center; gap: 1.125rem; box-shadow: var(--shadow-sm); margin: 0;">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(37, 99, 235, 0.08); color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div style="min-width: 0;">
            <div style="font-size: 1rem; font-weight: 700; color: var(--color-text); line-height: 1.2;">
                Ghana PPA Rules
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-top: 0.25rem;">
                Official Procurement Standards
            </div>
        </div>
    </div>
</div>

<!-- Main Operations Cohesive Card Container (Header, Filters, Data Table & Pagination) -->
<div class="card" style="margin-bottom: 2rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden;">
    <!-- Page Header & Segmented View Control -->
    <div style="padding: 1.75rem 2rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--color-border-subtle);">
        <div>
            <h1 style="font-size: 1.375rem; font-weight: 800; color: var(--color-text); margin: 0 0 0.35rem 0; letter-spacing: -0.01em;">
                <?= $filter === 'pending' ? 'Pending Approval Queues' : 'Departmental Requisitions' ?>
            </h1>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0; line-height: 1.4;">
                <?= $filter === 'pending' 
                    ? 'Review, verify, and approve departmental requests waiting for your sign-off.'
                    : 'See all item requests from departments and check their progress step-by-step.' ?>
            </p>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <!-- Primary Make a Requisition Action Button -->
            <a href="<?= $e($appUrl ?? '') ?>/requisitions/create" class="btn btn-primary" style="background: var(--color-success); color: #ffffff; font-weight: 700; font-size: 0.875rem; min-height: 40px; padding: 0.5rem 1.125rem; border-radius: var(--radius-md); box-shadow: 0 2px 4px rgba(0,105,56,0.15); display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; border: none; transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(0,105,56,0.25)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 4px rgba(0,105,56,0.15)';">
                <i class="fa-solid fa-plus"></i>
                <span>Make a Requisition</span>
            </a>

            <!-- Segmented Mode Control (Familiar Tab Switcher) -->
            <div class="view-mode-toggle" style="display: inline-flex; background: var(--color-surface-secondary); padding: 0.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); gap: 0.25rem;">
                <a href="<?= $e($appUrl ?? '') ?>/requisitions" 
                   style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.45rem 1rem; font-size: 0.8125rem; font-weight: 600; text-decoration: none; border-radius: calc(var(--radius-md) - 2px); transition: all 0.15s ease; color: <?= $filter !== 'pending' ? 'var(--color-primary)' : 'var(--color-muted-text)' ?>; background: <?= $filter !== 'pending' ? 'var(--color-surface)' : 'transparent' ?>; box-shadow: <?= $filter !== 'pending' ? '0 1px 3px rgba(0,0,0,0.08)' : 'none' ?>;">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>All Requisitions</span>
                </a>
                <a href="<?= $e($appUrl ?? '') ?>/requisitions?filter=pending" 
                   style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.45rem 1rem; font-size: 0.8125rem; font-weight: 600; text-decoration: none; border-radius: calc(var(--radius-md) - 2px); transition: all 0.15s ease; color: <?= $filter === 'pending' ? 'var(--color-primary)' : 'var(--color-muted-text)' ?>; background: <?= $filter === 'pending' ? 'var(--color-surface)' : 'transparent' ?>; box-shadow: <?= $filter === 'pending' ? '0 1px 3px rgba(0,0,0,0.08)' : 'none' ?>;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Pending Queue</span>
                    <?php if (!empty($pendingCount) && $pendingCount > 0): ?>
                        <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 0.45rem; font-size: 0.6875rem; font-weight: 800; border-radius: 9999px; background: var(--color-primary); color: #ffffff;">
                            <?= (int)$pendingCount ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Unified Search & Filter Toolbar -->
    <div style="padding: 1.25rem 2rem; background: var(--color-surface-secondary); border-bottom: 1px solid var(--color-border-subtle);">
        <form method="GET" action="<?= $e($appUrl ?? '') ?>/requisitions" class="search-filter-bar" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <?php if ($filter !== ''): ?>
                <input type="hidden" name="filter" value="<?= $e($filter) ?>">
            <?php endif; ?>

            <!-- Search Input -->
            <div style="flex: 1; min-width: 260px; position: relative;">
                <span style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--color-muted-text); font-size: 0.875rem; pointer-events: none;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input 
                    type="text" 
                    name="search" 
                    value="<?= $e($search) ?>" 
                    placeholder="Search by requisition # or justification..." 
                    class="form-control"
                    style="width: 100%; height: 42px; padding: 0 0.875rem 0 2.5rem; font-size: 0.8125rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); outline: none; background: var(--color-surface);"
                >
            </div>

            <!-- Status Select -->
            <div style="min-width: 220px;">
                <select name="status" class="form-control" style="width: 100%; height: 42px; padding: 0 0.875rem; font-size: 0.8125rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); outline: none; background: var(--color-surface); cursor: pointer;">
                    <?php foreach ($statuses as $val => $lbl): ?>
                        <option value="<?= $e($val) ?>" <?= $status === $val ? 'selected' : '' ?>>
                            <?= $e($lbl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Actions Group (Hick's Law: 1 Primary Search CTA + Clear) -->
            <div class="filter-btn-group" style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 1.25rem; font-size: 0.8125rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; white-space: nowrap; border-radius: var(--radius-md); box-shadow: var(--shadow-xs);">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Search</span>
                </button>

                <a href="<?= $e($appUrl ?? '') ?>/requisitions<?= $filter === 'pending' ? '?filter=pending' : '' ?>" class="btn btn-outline" style="height: 42px; padding: 0 1rem; font-size: 0.8125rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.375rem; color: var(--color-muted-text); border: 1px solid var(--color-border); background: var(--color-surface); border-radius: var(--radius-md); white-space: nowrap;" title="Clear search and filters">
                    <i class="fa-solid fa-xmark"></i>
                    <span>Clear</span>
                </a>
            </div>
        </form>
    </div>

    <!-- Data Table / Empty State Container -->
    <?php if (empty($requisitions)): ?>
        <div class="empty-state" style="padding: 4rem 2rem; text-align: center;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--color-surface-secondary); border: 1px solid var(--color-border); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: var(--color-muted-text); font-size: 1.75rem;">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <h4 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.5rem 0;">
                No Requisitions Matching Criteria
            </h4>
            <p style="font-size: 0.875rem; color: var(--color-muted-text); max-width: 480px; margin: 0 auto 1.75rem; line-height: 1.5;">
                <?= $filter === 'pending' 
                    ? 'There are currently no procurement requisitions pending approval for your active governance role.' 
                    : 'No procurement records matched your active query and filter criteria. Try adjusting your search query or reset the filters.' ?>
            </p>
            <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="btn btn-outline" style="font-size: 0.8125rem; font-weight: 600; padding: 0.5rem 1.25rem; border-radius: var(--radius-md);">
                Reset All Filters
            </a>
        </div>
    <?php else: ?>
        <div class="table-container table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem; min-width: 760px;">
                <thead>
                    <tr style="background: var(--color-surface-secondary); border-bottom: 1px solid var(--color-border); text-align: left;">
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; width: 14%;">Requisition #</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; width: 22%;">Department / Entity</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; width: 16%;">Requester</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; width: 16%;">Total Estimated Cost</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; width: 18%;">Governance Status</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; width: 12%;">Submitted</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: right; width: 10%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requisitions as $r): ?>
                        <tr style="border-bottom: 1px solid var(--color-border); transition: background-color 0.15s ease;">
                            <!-- Requisition Number -->
                            <td style="padding: 1rem 1.25rem; vertical-align: middle;">
                                <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" style="font-weight: 700; color: var(--color-primary); text-decoration: none; font-family: monospace; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 0.375rem;" title="View requisition details">
                                    <span><?= $e($r['requisition_number']) ?></span>
                                </a>
                            </td>

                            <!-- Entity / Department -->
                            <td style="padding: 1rem 1.25rem; vertical-align: middle; color: var(--color-text); line-height: 1.35;">
                                <div style="font-weight: 600; color: var(--color-text);">
                                    <?= $e($r['entity_name']) ?>
                                </div>
                            </td>

                            <!-- Requester -->
                            <td style="padding: 1rem 1.25rem; vertical-align: middle; color: var(--color-muted-text);">
                                <div style="font-weight: 500; color: var(--color-text);">
                                    <?= $e($r['requester_name']) ?>
                                </div>
                            </td>

                            <!-- Cost -->
                            <td style="padding: 1rem 1.25rem; vertical-align: middle; font-weight: 700; color: var(--color-text); font-variant-numeric: tabular-nums;">
                                GHS <?= number_format((float)$r['total_estimated_cost'], 2) ?>
                            </td>

                            <!-- Status Badge -->
                            <td style="padding: 1rem 1.25rem; vertical-align: middle;">
                                <?php $rStatus = RequisitionStatus::tryFrom((string)$r['status']); ?>
                                <div style="display: inline-flex; flex-direction: column; gap: 0.2rem;">
                                    <span class="badge badge-<?= $rStatus ? $rStatus->badgeClass() : strtolower((string)$r['status']) ?>" style="font-size: 0.75rem; font-weight: 700; width: fit-content; padding: 0.25rem 0.625rem; border-radius: 9999px;">
                                        <?= $e($rStatus ? $rStatus->title() : (string)$r['status']) ?>
                                    </span>
                                    <?php if ($rStatus): ?>
                                        <span style="font-size: 0.6875rem; color: var(--color-muted-text); font-weight: 500; margin-left: 0.125rem;">
                                            <?= $e($rStatus->sublabel()) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Submitted Date -->
                            <td style="padding: 1rem 1.25rem; vertical-align: middle; color: var(--color-muted-text); font-size: 0.8125rem; white-space: nowrap;">
                                <?= $e($r['submitted_at'] ? date('M d, Y', strtotime($r['submitted_at'])) : 'Draft') ?>
                            </td>

                            <!-- Action (Fitts's Law: Tactile Button with Clear Tap Target) -->
                            <td style="padding: 1rem 1.25rem; vertical-align: middle; text-align: right; white-space: nowrap;">
                                <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" class="btn btn-outline" style="min-height: 36px; padding: 0.4rem 0.875rem; font-size: 0.8125rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.375rem; border: 1.5px solid var(--color-primary); color: var(--color-primary); background: transparent; border-radius: var(--radius-md); transition: all 0.15s ease;" onmouseover="this.style.background='rgba(140, 0, 59, 0.08)'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='transparent'; this.style.transform='none';" title="Review requisition details">
                                    <span>Review</span>
                                    <i class="fa-solid fa-chevron-right" style="font-size: 0.6875rem;"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar inside Card Footer -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: var(--color-surface-secondary); border-top: 1px solid var(--color-border); flex-wrap: wrap; gap: 0.5rem;">
                <div style="font-size: 0.8125rem; color: var(--color-muted-text);">
                    Page <?= (int)$page ?> of <?= (int)$totalPages ?> (<?= (int)$totalRecords ?> Total)
                </div>
                <div style="display: flex; gap: 0.375rem;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&filter=<?= urlencode($filter) ?>" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: var(--radius-md);">
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&filter=<?= urlencode($filter) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: var(--radius-md);">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&filter=<?= urlencode($filter) ?>" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: var(--radius-md);">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Peak-End Rule: Institutional Governance & Helpdesk Assurance Strip -->
<div class="card" style="background: linear-gradient(135deg, #ffffff 0%, var(--color-surface-secondary) 100%); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.75rem 2rem; box-shadow: var(--shadow-sm); margin-top: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div style="max-width: 720px;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span class="badge badge-success" style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 9999px; padding: 0.2rem 0.625rem;">
                    <i class="fa-solid fa-shield-check" style="margin-right: 0.25rem;"></i> Statutory Assurance
                </span>
                <span style="font-size: 0.75rem; color: var(--color-muted-text);">•</span>
                <span style="font-size: 0.75rem; color: var(--color-muted-text); font-weight: 600;">Republic of Ghana Public Procurement Authority</span>
            </div>
            <h4 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.375rem 0;">
                University of Skills Training and Entrepreneurial Development (USTED)
            </h4>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0; line-height: 1.5;">
                All departmental procurement requests are tracked under the Public Procurement Act, 2003 (Act 663) as amended by Act 914. Contact the Directorate of Procurement for procurement thresholds or packaging assistance.
            </p>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="mailto:procurement@usted.edu.gh" class="btn btn-outline" style="font-size: 0.8125rem; padding: 0.5rem 0.875rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem;" title="Contact Procurement Directorate Helpdesk">
                <i class="fa-solid fa-headset" style="color: var(--color-primary);"></i>
                <span>Procurement Helpdesk</span>
            </a>
            <a href="#mainContent" class="btn btn-primary" style="font-size: 0.8125rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem; box-shadow: var(--shadow-xs);">
                <i class="fa-solid fa-arrow-up"></i>
                <span>Back to Top</span>
            </a>
        </div>
    </div>
</div>
