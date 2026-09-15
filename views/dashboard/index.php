<?php
/**
 * Role-Based Operational Workflow Dashboard & Approval Queue View
 * PROMIS - Procurement Management Information System
 *
 * @var array $user
 * @var array $roles
 * @var bool $isRequester
 * @var bool $isHod
 * @var bool $isDean
 * @var bool $isFinance
 * @var bool $isProcurement
 * @var bool $isAdmin
 * @var array $kpis
 * @var array $requesterQueues
 * @var array $hodQueue
 * @var array $deanQueue
 * @var array $financeQueue
 * @var array $procurementQueue
 * @var array $adminStats
 * @var array $recentAuditLogs
 * @var array $budgetSummary
 * @var array $recentRequisitions
 * @var string $activeTab
 * @var string $appUrl
 * @var callable $csrf
 * @var callable $e
 */

use Promis\Src\Execution\Domain\RequisitionStatus;

$userRecord = is_callable($user) ? $user() : ($user ?? []);
$userFullName = $userRecord['name'] ?? $userRecord['full_name'] ?? $userRecord['username'] ?? 'Colleague';

$primaryRole = match(true) {
    $isHod => 'HOD',
    $isDean => 'DEAN',
    $isFinance => 'FINANCE_OFFICER',
    $isProcurement => 'PROCUREMENT_OFFICER',
    $isAdmin => 'ADMIN',
    $isRequester => 'REQUESTER',
    default => !empty($roles) ? $roles[0] : 'STAFF'
};

$pendingWorkload = match($primaryRole) {
    'HOD' => (int)($kpis['hod']['awaiting'] ?? 0),
    'DEAN' => (int)($kpis['dean']['awaiting'] ?? 0),
    'FINANCE_OFFICER' => (int)($kpis['finance']['awaiting'] ?? 0),
    'PROCUREMENT_OFFICER' => (int)($kpis['procurement']['awaiting'] ?? 0),
    'ADMIN' => (int)($kpis['admin']['active_workflows'] ?? 0),
    default => (int)($kpis['requester']['returned'] ?? 0),
};
?>

<!-- 1. Welcome Banner with Operational Focus & Dominant CTA -->
<div class="card" style="margin-bottom: 2rem; background: var(--gradient-brand); color: #ffffff; border: none; border-radius: var(--radius-lg); padding: 2rem 2.25rem; box-shadow: var(--shadow-md);">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
        <div style="max-width: 680px;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <span class="badge" style="background: rgba(255,255,255,0.2); color: #ffffff; border: 1px solid rgba(255,255,255,0.3); font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px;">
                    PROMIS Portal
                </span>
                <span style="font-size: 0.75rem; opacity: 0.85;">•</span>
                <span style="font-size: 0.75rem; opacity: 0.85; font-weight: 500;">USTED Ghana</span>
            </div>
            <h2 style="font-size: 1.625rem; font-weight: 800; color: #ffffff; margin: 0 0 0.5rem 0; letter-spacing: -0.015em; line-height: 1.2;">
                Welcome, <?= $e($userFullName) ?>
            </h2>
            <p style="font-size: 0.9375rem; opacity: 0.94; margin: 0; line-height: 1.6;">
                Review your requests, check pending approvals, and track progress.
            </p>
        </div>

        <!-- Dominant Actions -->
        <div style="display: flex; gap: 0.875rem; flex-wrap: wrap; align-items: center;">
            <a href="<?= $e($appUrl ?? '') ?>/requisitions/create" class="btn" style="background: #22c55e; color: #ffffff; font-weight: 700; font-size: 0.9375rem; min-height: 44px; padding: 0.625rem 1.35rem; border-radius: var(--radius-md); box-shadow: 0 2px 5px rgba(0,0,0,0.15); display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; border: none; transition: transform 0.15s ease;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                <i class="fa-solid fa-plus"></i>
                <span>New Request</span>
            </a>

            <?php if ($isHod || $isDean || $isFinance || $isProcurement || $isAdmin): ?>
                <a href="#approval-queues" class="btn" style="background: #ffffff; color: var(--color-primary); font-weight: 700; font-size: 0.9375rem; min-height: 44px; padding: 0.625rem 1.25rem; border-radius: var(--radius-md); box-shadow: 0 2px 5px rgba(0,0,0,0.15); display: inline-flex; align-items: center; gap: 0.625rem; text-decoration: none; transition: transform 0.15s ease;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                    <i class="fa-solid fa-stamp"></i>
                    <span>Requests Waiting for Me</span>
                    <?php if ($pendingWorkload > 0): ?>
                        <span style="background: var(--color-primary); color: #ffffff; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 800;">
                            <?= $pendingWorkload ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <a href="<?= $e($appUrl ?? '') ?>/procurement-plans" class="btn btn-outline" style="background: rgba(255,255,255,0.18); color: #ffffff; border: 1px solid rgba(255,255,255,0.5); font-size: 0.9375rem; min-height: 44px; padding: 0.625rem 1.125rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-weight: 600;">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Procurement Plans</span>
            </a>
        </div>
    </div>
</div>

<!-- 2. Role-Appropriate KPI Cards -->
<div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
    <?php if ($primaryRole === 'HOD'): ?>
        <!-- HOD KPIs: Waiting for My Review, Recommended, Returned, Rejected -->
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-primary); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Waiting for My Review</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-primary); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['hod']['awaiting'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Department requests to review</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-signature"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Recommended</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-success); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['hod']['endorsed'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Recommended to Faculty</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(0, 105, 56, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-warning); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Returned</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-warning); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['hod']['returned'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Returned for correction</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(221, 153, 51, 0.1); color: var(--color-warning); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-rotate-left"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-danger); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Rejected</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-danger); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['hod']['rejected'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Rejected requests</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(220, 38, 38, 0.1); color: var(--color-danger); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-ban"></i></div>
            </div>
        </a>

    <?php elseif ($primaryRole === 'DEAN'): ?>
        <!-- DEAN KPIs: Waiting for My Approval, Approved, Returned, Rejected -->
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-primary); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Waiting for My Approval</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-primary); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['dean']['awaiting'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Faculty requests to approve</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-stamp"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Approved</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-success); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['dean']['approved'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Approved for Finance</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(0, 105, 56, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-warning); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Returned</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-warning); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['dean']['returned'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Returned to Department</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(221, 153, 51, 0.1); color: var(--color-warning); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-rotate-left"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-danger); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Rejected</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-danger); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['dean']['rejected'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Rejected requests</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(220, 38, 38, 0.1); color: var(--color-danger); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-ban"></i></div>
            </div>
        </a>

    <?php elseif ($primaryRole === 'FINANCE_OFFICER'): ?>
        <!-- FINANCE KPIs: Waiting for Finance Review, Approved for Purchase, Returned, Rejected -->
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-primary); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Waiting for Finance Review</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-primary); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['finance']['awaiting'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Requests waiting for budget approval</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-vault"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Approved for Purchase</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-success); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['finance']['committed'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Funds approved for purchase</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(0, 105, 56, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-lock"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-warning); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Returned</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-warning); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['finance']['returned'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Returned to Department</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(221, 153, 51, 0.1); color: var(--color-warning); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-rotate-left"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-danger); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Rejected</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-danger); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['finance']['rejected'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Rejected requests</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(220, 38, 38, 0.1); color: var(--color-danger); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-ban"></i></div>
            </div>
        </a>

    <?php elseif ($primaryRole === 'PROCUREMENT_OFFICER'): ?>
        <!-- PROCUREMENT KPIs: Waiting to Be Purchased, Being Processed, Items Received, Completed -->
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-primary); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Waiting to Be Purchased</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-primary); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['procurement']['awaiting'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Approved orders waiting for purchase</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-boxes-packing"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-info); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Being Processed</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-info); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['procurement']['processing'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Purchase processing started</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(2, 132, 199, 0.1); color: var(--color-info); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-truck-ramp-box"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Items Received</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-success); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['procurement']['received'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Items delivered and received</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(0, 105, 56, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-box-check"></i></div>
            </div>
        </a>
        <a href="#approval-queues" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid #2563eb; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Completed</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #2563eb; margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['procurement']['received'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Finished requests</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(37, 99, 235, 0.08); color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </a>

    <?php elseif ($primaryRole === 'ADMIN'): ?>
        <!-- ADMIN KPIs: Staff Accounts, Active Accounts, Departments and Units, Active Requests -->
        <a href="<?= $e($appUrl ?? '') ?>/admin/users" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-primary); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Staff Accounts</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-primary); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['admin']['total_users'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Total staff accounts</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-users"></i></div>
            </div>
        </a>
        <a href="<?= $e($appUrl ?? '') ?>/admin/users?status=ACTIVE" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Active Accounts</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-success); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['admin']['active_users'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Staff who can sign in</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(0, 105, 56, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-user-check"></i></div>
            </div>
        </a>
        <a href="<?= $e($appUrl ?? '') ?>/admin/entities" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-info); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Departments and Units</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-info); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['admin']['planning_entities'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Faculties & departments</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(2, 132, 199, 0.1); color: var(--color-info); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-sitemap"></i></div>
            </div>
        </a>
        <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid #8b5cf6; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Active Requests</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #8b5cf6; margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['admin']['active_workflows'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Requests in progress</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(139, 92, 246, 0.1); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-diagram-project"></i></div>
            </div>
        </a>

    <?php else: ?>
        <!-- REQUESTER KPIs: My Drafts, Waiting for Approval, Returned to Me, Completed Requests -->
        <a href="#workbench-drafts" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-warning); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">My Drafts</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-text); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['requester']['drafts'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Saved, not yet sent</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(221, 153, 51, 0.1); color: var(--color-warning); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-file-pen"></i></div>
            </div>
        </a>
        <a href="#workbench-submitted" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-info); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Waiting for Approval</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-text); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['requester']['submitted'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Under review</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(2, 132, 199, 0.1); color: var(--color-info); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-paper-plane"></i></div>
            </div>
        </a>
        <a href="#workbench-returned" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-danger); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Returned to Me</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-danger); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['requester']['returned'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Changes requested</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(220, 38, 38, 0.1); color: var(--color-danger); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-rotate-left"></i></div>
            </div>
        </a>
        <a href="#workbench-completed" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">Completed Requests</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-success); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums;"><?= (int)$kpis['requester']['completed'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text);">Items received</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(0, 105, 56, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.125rem;"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </a>
    <?php endif; ?>
</div>

<!-- 3. Operational Approval Queues & Multi-Role Switching -->
<div id="approval-queues" class="card" style="margin-bottom: 2rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
    
    <!-- Multi-Role Queue Tabs (For users with multiple governance roles) -->
    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--color-border); padding-bottom: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-inbox" style="color: var(--color-primary);"></i>
                Requests Waiting for Me
            </h3>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0.25rem 0 0;">
                Review and take action on requests assigned to your role
            </p>
        </div>

        <!-- Role Queue Selector Tabs -->
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <?php if ($isHod): ?>
                <button type="button" class="btn <?= $activeTab === 'hod' ? 'btn-primary' : 'btn-outline' ?>" onclick="switchRoleQueue('hod')" style="font-size: 0.8125rem; padding: 0.4rem 0.875rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem;">
                    <i class="fa-solid fa-signature"></i>
                    <span>Department Requests</span>
                    <?php if ((int)$kpis['hod']['awaiting'] > 0): ?>
                        <span style="background: rgba(255,255,255,0.3); padding: 0.1rem 0.4rem; border-radius: 9999px; font-weight: 800; font-size: 0.6875rem;"><?= (int)$kpis['hod']['awaiting'] ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>

            <?php if ($isDean): ?>
                <button type="button" class="btn <?= $activeTab === 'dean' ? 'btn-primary' : 'btn-outline' ?>" onclick="switchRoleQueue('dean')" style="font-size: 0.8125rem; padding: 0.4rem 0.875rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem;">
                    <i class="fa-solid fa-stamp"></i>
                    <span>Faculty Requests</span>
                    <?php if ((int)$kpis['dean']['awaiting'] > 0): ?>
                        <span style="background: rgba(255,255,255,0.3); padding: 0.1rem 0.4rem; border-radius: 9999px; font-weight: 800; font-size: 0.6875rem;"><?= (int)$kpis['dean']['awaiting'] ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>

            <?php if ($isFinance): ?>
                <button type="button" class="btn <?= $activeTab === 'finance' ? 'btn-primary' : 'btn-outline' ?>" onclick="switchRoleQueue('finance')" style="font-size: 0.8125rem; padding: 0.4rem 0.875rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem;">
                    <i class="fa-solid fa-vault"></i>
                    <span>Finance Requests</span>
                    <?php if ((int)$kpis['finance']['awaiting'] > 0): ?>
                        <span style="background: rgba(255,255,255,0.3); padding: 0.1rem 0.4rem; border-radius: 9999px; font-weight: 800; font-size: 0.6875rem;"><?= (int)$kpis['finance']['awaiting'] ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>

            <?php if ($isProcurement): ?>
                <button type="button" class="btn <?= $activeTab === 'procurement' ? 'btn-primary' : 'btn-outline' ?>" onclick="switchRoleQueue('procurement')" style="font-size: 0.8125rem; padding: 0.4rem 0.875rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem;">
                    <i class="fa-solid fa-boxes-packing"></i>
                    <span>Purchase Processing</span>
                    <?php if ((int)$kpis['procurement']['awaiting'] > 0): ?>
                        <span style="background: rgba(255,255,255,0.3); padding: 0.1rem 0.4rem; border-radius: 9999px; font-weight: 800; font-size: 0.6875rem;"><?= (int)$kpis['procurement']['awaiting'] ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>

            <?php if ($isRequester || empty($roles)): ?>
                <button type="button" class="btn <?= $activeTab === 'requester' ? 'btn-primary' : 'btn-outline' ?>" onclick="switchRoleQueue('requester')" style="font-size: 0.8125rem; padding: 0.4rem 0.875rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem;">
                    <i class="fa-solid fa-user"></i>
                    <span>My Requests</span>
                </button>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <button type="button" class="btn <?= $activeTab === 'admin' ? 'btn-primary' : 'btn-outline' ?>" onclick="switchRoleQueue('admin')" style="font-size: 0.8125rem; padding: 0.4rem 0.875rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem;">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>System Overview</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 1: HOD DEPARTMENT APPROVAL QUEUE -->
    <?php if ($isHod): ?>
        <div id="queue-panel-hod" class="role-queue-panel" style="display: <?= $activeTab === 'hod' ? 'block' : 'none' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h4 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0;">
                    Department Requests <span style="display:none;">Department Approval Queue</span>
                </h4>
                <span class="badge badge-info" style="font-size: 0.75rem;">Your Department</span>
            </div>

            <?php $hodAwaiting = $hodQueue['awaiting'] ?? []; ?>
            <?php if (empty($hodAwaiting)): ?>
                <div class="empty-state" style="padding: 2.5rem 1rem; text-align: center; background: var(--color-surface-secondary); border-radius: var(--radius-md);">
                    <div style="font-size: 2rem; color: var(--color-success); margin-bottom: 0.5rem;"><i class="fa-solid fa-circle-check"></i></div>
                    <div style="font-weight: 700; color: var(--color-text); margin-bottom: 0.25rem;">No Pending Requests</div>
                    <div style="font-size: 0.8125rem; color: var(--color-muted-text);">No department requests are currently waiting for your review.</div>
                </div>
            <?php else: ?>
                <div class="table-container" style="overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 1.5rem;">
                    <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="background: var(--color-surface-secondary); border-bottom: 2px solid var(--color-border); text-align: left;">
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Request Number</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Requested By</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Department</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Description</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Estimated Cost</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Date Sent</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hodAwaiting as $r): ?>
                                <tr style="border-bottom: 1px solid var(--color-border-subtle);">
                                    <td style="padding: 0.875rem 1rem; font-weight: 700;">
                                        <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" style="color: var(--color-primary); text-decoration: none;">
                                            <?= $e($r['requisition_number']) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; color: var(--color-text); font-weight: 500;"><?= $e($r['requester_name']) ?></td>
                                    <td style="padding: 0.875rem 1rem; color: var(--color-muted-text);"><?= $e($r['department_name']) ?></td>
                                    <td style="padding: 0.875rem 1rem; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= $e($r['justification']) ?>">
                                        <?= $e($r['justification']) ?>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-text);">GHS <?= number_format((float)$r['total_estimated_cost'], 2) ?></td>
                                    <td style="padding: 0.875rem 1rem; color: var(--color-muted-text);"><?= $e(date('M d, Y', strtotime($r['created_at']))) ?></td>
                                    <td style="padding: 0.875rem 1rem;">
                                        <span class="badge badge-info" style="font-size: 0.6875rem; font-weight: 700;">Waiting for My Review</span>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; text-align: right; white-space: nowrap;">
                                        <div style="display: inline-flex; gap: 0.35rem;">
                                            <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" class="btn btn-outline" style="padding: 0.35rem 0.625rem; font-size: 0.75rem;" title="Review Request">
                                                Review Request
                                            </a>
                                            <form method="POST" action="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>/action" style="margin:0; display:inline;">
                                                <?= $csrf() ?>
                                                <input type="hidden" name="action" value="ENDORSE">
                                                <button type="submit" class="btn btn-primary" style="padding: 0.35rem 0.625rem; font-size: 0.75rem;" title="Recommend for Faculty approval">
                                                    Recommend
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-outline" style="padding: 0.35rem 0.5rem; font-size: 0.75rem; color: var(--color-warning); border-color: rgba(221,153,51,0.5);" onclick="openActionModal('RETURN', '<?= (int)$r['id'] ?>', '<?= $e($r['requisition_number']) ?>')">
                                                Send Back
                                            </button>
                                            <button type="button" class="btn btn-outline" style="padding: 0.35rem 0.5rem; font-size: 0.75rem; color: var(--color-danger); border-color: rgba(220,38,38,0.4);" onclick="openActionModal('REJECT', '<?= (int)$r['id'] ?>', '<?= $e($r['requisition_number']) ?>')">
                                                Reject Request
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 2: DEAN FACULTY APPROVAL QUEUE -->
    <?php if ($isDean): ?>
        <div id="queue-panel-dean" class="role-queue-panel" style="display: <?= $activeTab === 'dean' ? 'block' : 'none' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h4 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0;">
                    Faculty Requests <span style="display:none;">Faculty Approval Queue</span>
                </h4>
                <span class="badge badge-info" style="font-size: 0.75rem;">Your Faculty</span>
            </div>

            <?php $deanAwaiting = $deanQueue['awaiting'] ?? []; ?>
            <?php if (empty($deanAwaiting)): ?>
                <div class="empty-state" style="padding: 2.5rem 1rem; text-align: center; background: var(--color-surface-secondary); border-radius: var(--radius-md);">
                    <div style="font-size: 2rem; color: var(--color-success); margin-bottom: 0.5rem;"><i class="fa-solid fa-circle-check"></i></div>
                    <div style="font-weight: 700; color: var(--color-text); margin-bottom: 0.25rem;">No Pending Approvals</div>
                    <div style="font-size: 0.8125rem; color: var(--color-muted-text);">No recommended requests are currently waiting for your approval.</div>
                </div>
            <?php else: ?>
                <div class="table-container" style="overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 1.5rem;">
                    <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="background: var(--color-surface-secondary); border-bottom: 2px solid var(--color-border); text-align: left;">
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Request Number</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Department</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Faculty</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Requested By</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Department Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Estimated Cost</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deanAwaiting as $r): ?>
                                <tr style="border-bottom: 1px solid var(--color-border-subtle);">
                                    <td style="padding: 0.875rem 1rem; font-weight: 700;">
                                        <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" style="color: var(--color-primary); text-decoration: none;">
                                            <?= $e($r['requisition_number']) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; font-weight: 600; color: var(--color-text);"><?= $e($r['department_name']) ?></td>
                                    <td style="padding: 0.875rem 1rem; color: var(--color-muted-text);"><?= $e($r['faculty_name'] ?? 'Faculty Unit') ?></td>
                                    <td style="padding: 0.875rem 1rem; color: var(--color-text);"><?= $e($r['requester_name']) ?></td>
                                    <td style="padding: 0.875rem 1rem;">
                                        <span class="badge badge-success" style="font-size: 0.6875rem; font-weight: 700;">✓ Recommended</span>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-text);">GHS <?= number_format((float)$r['total_estimated_cost'], 2) ?></td>
                                    <td style="padding: 0.875rem 1rem;">
                                        <span class="badge badge-info" style="font-size: 0.6875rem; font-weight: 700;">Waiting for My Approval</span>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; text-align: right; white-space: nowrap;">
                                        <div style="display: inline-flex; gap: 0.35rem;">
                                            <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" class="btn btn-outline" style="padding: 0.35rem 0.625rem; font-size: 0.75rem;" title="Review Request">
                                                Review Request
                                            </a>
                                            <form method="POST" action="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>/action" style="margin:0; display:inline;">
                                                <?= $csrf() ?>
                                                <input type="hidden" name="action" value="APPROVE">
                                                <button type="submit" class="btn btn-primary" style="padding: 0.35rem 0.625rem; font-size: 0.75rem;" title="Approve Request">
                                                    Approve Request
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-outline" style="padding: 0.35rem 0.5rem; font-size: 0.75rem; color: var(--color-warning); border-color: rgba(221,153,51,0.5);" onclick="openActionModal('RETURN', '<?= (int)$r['id'] ?>', '<?= $e($r['requisition_number']) ?>')">
                                                Send Back
                                            </button>
                                            <button type="button" class="btn btn-outline" style="padding: 0.35rem 0.5rem; font-size: 0.75rem; color: var(--color-danger); border-color: rgba(220,38,38,0.4);" onclick="openActionModal('REJECT', '<?= (int)$r['id'] ?>', '<?= $e($r['requisition_number']) ?>')">
                                                Reject Request
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 3: FINANCE COMMITMENT QUEUE -->
    <?php if ($isFinance): ?>
        <div id="queue-panel-finance" class="role-queue-panel" style="display: <?= $activeTab === 'finance' ? 'block' : 'none' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h4 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0;">
                    Finance Requests <span style="display:none;">Finance Commitment Queue</span>
                </h4>
                <span class="badge badge-info" style="font-size: 0.75rem;">Finance Review & Budget Approval</span>
            </div>

            <?php $finAwaiting = $financeQueue['awaiting'] ?? []; ?>
            <?php if (empty($finAwaiting)): ?>
                <div class="empty-state" style="padding: 2.5rem 1rem; text-align: center; background: var(--color-surface-secondary); border-radius: var(--radius-md);">
                    <div style="font-size: 2rem; color: var(--color-success); margin-bottom: 0.5rem;"><i class="fa-solid fa-circle-check"></i></div>
                    <div style="font-weight: 700; color: var(--color-text); margin-bottom: 0.25rem;">No Pending Requests</div>
                    <div style="font-size: 0.8125rem; color: var(--color-muted-text);">No requests are currently waiting for Finance approval.</div>
                </div>
            <?php else: ?>
                <div class="table-container" style="overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 1.5rem;">
                    <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="background: var(--color-surface-secondary); border-bottom: 2px solid var(--color-border); text-align: left;">
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Request Number</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Department</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Faculty</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Requested By</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Approved Cost</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Faculty Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finAwaiting as $r): ?>
                                <tr style="border-bottom: 1px solid var(--color-border-subtle);">
                                    <td style="padding: 0.875rem 1rem; font-weight: 700;">
                                        <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" style="color: var(--color-primary); text-decoration: none;">
                                            <?= $e($r['requisition_number']) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; font-weight: 600; color: var(--color-text);"><?= $e($r['department_name']) ?></td>
                                    <td style="padding: 0.875rem 1rem; color: var(--color-muted-text);"><?= $e($r['faculty_name'] ?? 'Faculty Unit') ?></td>
                                    <td style="padding: 0.875rem 1rem; color: var(--color-text);"><?= $e($r['requester_name']) ?></td>
                                    <td style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-success); font-size: 0.875rem;">GHS <?= number_format((float)$r['total_estimated_cost'], 2) ?></td>
                                    <td style="padding: 0.875rem 1rem;">
                                        <span class="badge badge-success" style="font-size: 0.6875rem; font-weight: 700;">✓ Approved</span>
                                    </td>
                                    <td style="padding: 0.875rem 1rem;">
                                        <span class="badge badge-primary" style="font-size: 0.6875rem; font-weight: 700;">Waiting for Finance Review</span>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; text-align: right; white-space: nowrap;">
                                        <div style="display: inline-flex; gap: 0.35rem;">
                                            <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" class="btn btn-outline" style="padding: 0.35rem 0.625rem; font-size: 0.75rem;" title="Review Request">
                                                Review Request
                                            </a>
                                            <form method="POST" action="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>/action" style="margin:0; display:inline;">
                                                <?= $csrf() ?>
                                                <input type="hidden" name="action" value="APPROVE">
                                                <button type="submit" class="btn btn-primary" style="padding: 0.35rem 0.625rem; font-size: 0.75rem;" title="Approve for Purchase">
                                                    Approve for Purchase
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-outline" style="padding: 0.35rem 0.5rem; font-size: 0.75rem; color: var(--color-warning); border-color: rgba(221,153,51,0.5);" onclick="openActionModal('RETURN', '<?= (int)$r['id'] ?>', '<?= $e($r['requisition_number']) ?>')">
                                                Send Back
                                            </button>
                                            <button type="button" class="btn btn-outline" style="padding: 0.35rem 0.5rem; font-size: 0.75rem; color: var(--color-danger); border-color: rgba(220,38,38,0.4);" onclick="openActionModal('REJECT', '<?= (int)$r['id'] ?>', '<?= $e($r['requisition_number']) ?>')">
                                                Reject Request
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 4: PROCUREMENT QUEUE -->
    <?php if ($isProcurement): ?>
        <div id="queue-panel-procurement" class="role-queue-panel" style="display: <?= $activeTab === 'procurement' ? 'block' : 'none' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h4 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0;">
                    Purchase Processing <span style="display:none;">Procurement Receiving Queue</span>
                </h4>
                <span class="badge badge-info" style="font-size: 0.75rem;">Orders Waiting for Purchase & Delivery</span>
            </div>

            <?php $procAwaiting = $procurementQueue['awaiting'] ?? []; ?>
            <?php if (empty($procAwaiting)): ?>
                <div class="empty-state" style="padding: 2.5rem 1rem; text-align: center; background: var(--color-surface-secondary); border-radius: var(--radius-md);">
                    <div style="font-size: 2rem; color: var(--color-success); margin-bottom: 0.5rem;"><i class="fa-solid fa-circle-check"></i></div>
                    <div style="font-weight: 700; color: var(--color-text); margin-bottom: 0.25rem;">No Orders Waiting</div>
                    <div style="font-size: 0.8125rem; color: var(--color-muted-text);">No approved requests are currently waiting for purchase processing.</div>
                </div>
            <?php else: ?>
                <div class="table-container" style="overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 1.5rem;">
                    <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="background: var(--color-surface-secondary); border-bottom: 2px solid var(--color-border); text-align: left;">
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Request Number</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Department</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Faculty</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Approved Cost</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Finance Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($procAwaiting as $r): ?>
                                <tr style="border-bottom: 1px solid var(--color-border-subtle);">
                                    <td style="padding: 0.875rem 1rem; font-weight: 700;">
                                        <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" style="color: var(--color-primary); text-decoration: none;">
                                            <?= $e($r['requisition_number']) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; font-weight: 600; color: var(--color-text);"><?= $e($r['department_name']) ?></td>
                                    <td style="padding: 0.875rem 1rem; color: var(--color-muted-text);"><?= $e($r['faculty_name'] ?? 'Faculty Unit') ?></td>
                                    <td style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-text);">GHS <?= number_format((float)$r['total_estimated_cost'], 2) ?></td>
                                    <td style="padding: 0.875rem 1rem;">
                                        <span class="badge badge-success" style="font-size: 0.6875rem; font-weight: 700;">✓ Approved for Purchase</span>
                                    </td>
                                    <td style="padding: 0.875rem 1rem;">
                                        <span class="badge badge-primary" style="font-size: 0.6875rem; font-weight: 700;">Waiting to Be Purchased</span>
                                    </td>
                                    <td style="padding: 0.875rem 1rem; text-align: right; white-space: nowrap;">
                                        <div style="display: inline-flex; gap: 0.35rem;">
                                            <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" class="btn btn-outline" style="padding: 0.35rem 0.625rem; font-size: 0.75rem;" title="Review Request">
                                                Review Request
                                            </a>
                                            <form method="POST" action="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>/action" style="margin:0; display:inline;">
                                                <?= $csrf() ?>
                                                <input type="hidden" name="action" value="RECEIVE">
                                                <button type="submit" class="btn btn-primary" style="padding: 0.35rem 0.625rem; font-size: 0.75rem;" title="Record Delivery">
                                                    Record Delivery
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 5: REQUESTER WORKBENCH (Drafts, Submitted, Returned, Rejected, Completed) -->
    <div id="queue-panel-requester" class="role-queue-panel" style="display: <?= $activeTab === 'requester' ? 'block' : 'none' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <h4 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0;">
                My Requests <span style="display:none;">My Requisitions Workbench</span>
            </h4>
            <a href="<?= $e($appUrl ?? '') ?>/requisitions/create" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.4rem 0.75rem;">
                <i class="fa-solid fa-plus" style="margin-right: 0.25rem;"></i> New Request
            </a>
        </div>

        <?php $reqRecent = $requesterQueues['recent'] ?? []; ?>
        <?php if (empty($reqRecent)): ?>
            <div class="empty-state" style="padding: 2.5rem 1rem; text-align: center; background: var(--color-surface-secondary); border-radius: var(--radius-md);">
                <div style="font-size: 2rem; color: var(--color-muted-text); margin-bottom: 0.5rem;"><i class="fa-solid fa-folder-open"></i></div>
                <div style="font-weight: 700; color: var(--color-text); margin-bottom: 0.25rem;">No Requests Created Yet</div>
                <div style="font-size: 0.8125rem; color: var(--color-muted-text); margin-bottom: 1rem;">You have not created any purchase requests yet.</div>
                <a href="<?= $e($appUrl ?? '') ?>/requisitions/create" class="btn btn-primary" style="font-size: 0.8125rem;">
                    Create Your First Request
                </a>
            </div>
        <?php else: ?>
            <div class="table-container" style="overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 1.5rem;">
                <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                    <thead>
                        <tr style="background: var(--color-surface-secondary); border-bottom: 2px solid var(--color-border); text-align: left;">
                            <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Request Number</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Description</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Department</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Estimated Cost</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Status</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Date Sent</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Last Updated</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reqRecent as $r): ?>
                            <?php $rStat = RequisitionStatus::tryFrom((string)$r['status']); ?>
                            <tr style="border-bottom: 1px solid var(--color-border-subtle);">
                                <td style="padding: 0.875rem 1rem; font-weight: 700;">
                                    <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" style="color: var(--color-primary); text-decoration: none;">
                                        <?= $e($r['requisition_number']) ?>
                                    </a>
                                </td>
                                <td style="padding: 0.875rem 1rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= $e($r['justification']) ?>">
                                    <?= $e($r['justification']) ?>
                                </td>
                                <td style="padding: 0.875rem 1rem; color: var(--color-text);"><?= $e($r['department_name']) ?></td>
                                <td style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-text);">GHS <?= number_format((float)$r['total_estimated_cost'], 2) ?></td>
                                <td style="padding: 0.875rem 1rem;">
                                    <span class="badge badge-<?= $rStat ? $rStat->badgeClass() : strtolower((string)$r['status']) ?>" style="font-size: 0.6875rem; font-weight: 700;">
                                        <?= $e($rStat ? $rStat->title() : (string)$r['status']) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.875rem 1rem; color: var(--color-muted-text); font-size: 0.75rem;">
                                    <?= !empty($r['submitted_at']) ? $e(date('M d, Y', strtotime($r['submitted_at']))) : '—' ?>
                                </td>
                                <td style="padding: 0.875rem 1rem; color: var(--color-muted-text); font-size: 0.75rem;">
                                    <?= $e(date('M d, Y', strtotime($r['updated_at'] ?? $r['created_at']))) ?>
                                </td>
                                <td style="padding: 0.875rem 1rem; text-align: right; white-space: nowrap;">
                                    <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>" class="btn btn-outline" style="padding: 0.35rem 0.625rem; font-size: 0.75rem;">
                                        View Request
                                    </a>
                                    <?php if ($r['status'] === 'DRAFT' || $r['status'] === 'RETURNED'): ?>
                                        <form method="POST" action="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$r['id'] ?>/action" style="margin:0; display:inline;">
                                            <?= $csrf() ?>
                                            <input type="hidden" name="action" value="SUBMIT">
                                            <button type="submit" class="btn btn-primary" style="padding: 0.35rem 0.625rem; font-size: 0.75rem;">
                                                Send for Approval
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 6: ADMIN ADMINISTRATIVE OVERSIGHT -->
    <?php if ($isAdmin): ?>
        <div id="queue-panel-admin" class="role-queue-panel" style="display: <?= $activeTab === 'admin' ? 'block' : 'none' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h4 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0;">
                    System Overview
                </h4>
                <span class="badge badge-warning" style="font-size: 0.75rem;">System Administration</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                <div style="background: var(--color-surface-secondary); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    <div style="font-size: 0.875rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.25rem;">
                        <i class="fa-solid fa-users-gear" style="color: var(--color-primary); margin-right: 0.35rem;"></i> Staff Accounts
                    </div>
                    <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0 0 1rem;">Manage university staff accounts, activate logins, and assign departments.</p>
                    <a href="<?= $e($appUrl ?? '') ?>/admin/users" class="btn btn-primary" style="font-size: 0.8125rem; padding: 0.4rem 0.875rem;">
                        Open Staff Accounts
                    </a>
                </div>

                <div style="background: var(--color-surface-secondary); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    <div style="font-size: 0.875rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.25rem;">
                        <i class="fa-solid fa-sitemap" style="color: var(--color-primary); margin-right: 0.35rem;"></i> Departments and Units
                    </div>
                    <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0 0 1rem;">Configure faculties, departments, and administrative units.</p>
                    <a href="<?= $e($appUrl ?? '') ?>/admin/entities" class="btn btn-primary" style="font-size: 0.8125rem; padding: 0.4rem 0.875rem;">
                        Open Departments & Units
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- 4. Institutional Budget Overview Section -->
<div id="budget" class="card" style="margin-bottom: 2rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.625rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(221, 153, 51, 0.12); color: var(--color-accent); font-size: 1rem;">
                    <i class="fa-solid fa-coins"></i>
                </span>
                Department Budgets & Balances
            </h3>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0.375rem 0 0; line-height: 1.4;">
                Fiscal Year <?= $e((string)$budgetSummary['fiscal_year']) ?> budget limits and spending across university departments
            </p>
        </div>
        <div>
            <span class="badge badge-<?= $budgetSummary['has_live_data'] ? 'success' : 'info' ?>" style="font-size: 0.75rem; font-weight: 600; padding: 0.375rem 0.75rem; border-radius: 9999px;">
                <?= $budgetSummary['has_live_data'] ? '● Active budget figures' : '○ Standby State (0.00 GHS)' ?>
            </span>
        </div>
    </div>

    <div class="budget-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
        <div style="background: var(--color-surface-secondary); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); border-left: 4px solid #64748b;">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Total Approved Budget</div>
            <div style="font-size: 1.625rem; font-weight: 800; color: var(--color-text); margin: 0.25rem 0 0.5rem; font-variant-numeric: tabular-nums;">
                GHS <?= number_format((float)$budgetSummary['total_allocated'], 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--color-muted-text);">Approved annual budget limit across all departments</div>
        </div>

        <div style="background: var(--color-surface-secondary); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); border-left: 4px solid var(--color-primary);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-primary); text-transform: uppercase;">Approved for Purchase</div>
            <div style="font-size: 1.625rem; font-weight: 800; color: var(--color-primary); margin: 0.25rem 0 0.5rem; font-variant-numeric: tabular-nums;">
                GHS <?= number_format((float)$budgetSummary['total_committed'], 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--color-muted-text);">Funds approved by Finance for purchases</div>
        </div>

        <div style="background: var(--color-surface-secondary); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-success); text-transform: uppercase;">Remaining Budget</div>
            <div style="font-size: 1.625rem; font-weight: 800; color: var(--color-success); margin: 0.25rem 0 0.5rem; font-variant-numeric: tabular-nums;">
                GHS <?= number_format((float)$budgetSummary['available_balance'], 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--color-muted-text);">Available funds remaining for new requests</div>
        </div>
    </div>
</div>

<!-- 5. Admin Activity History -->
<?php if ($isAdmin && !empty($recentAuditLogs)): ?>
    <div id="audit" class="card" style="margin-bottom: 2rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.625rem;">
                    <i class="fa-solid fa-clipboard-list" style="color: var(--color-primary);"></i>
                    Activity History
                </h3>
                <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0.25rem 0 0;">
                    Recent actions and changes made across the system
                </p>
            </div>
            <span class="badge badge-success" style="font-size: 0.75rem; font-weight: 600;"><i class="fa-solid fa-lock"></i> Permanent History</span>
        </div>

        <div class="table-container" style="overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                <thead>
                    <tr style="background: var(--color-surface-secondary); border-bottom: 2px solid var(--color-border); text-align: left;">
                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Date & Time</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Staff Member</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Action</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">Item</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAuditLogs as $log): ?>
                        <tr style="border-bottom: 1px solid var(--color-border-subtle);">
                            <td style="padding: 0.875rem 1rem; color: var(--color-text); font-weight: 500; white-space: nowrap;">
                                <?= $e(date('M d, Y • h:i A', strtotime($log['event_timestamp']))) ?>
                            </td>
                            <td style="padding: 0.875rem 1rem; font-weight: 600; color: var(--color-text);">
                                <?= $e($log['full_name'] ?? $log['username'] ?? 'System') ?>
                            </td>
                            <td style="padding: 0.875rem 1rem;">
                                <span class="badge badge-info" style="font-size: 0.75rem; font-weight: 600;">
                                    <?= $e($log['action']) ?>
                                </span>
                            </td>
                            <td style="padding: 0.875rem 1rem;">
                                <span style="font-size: 0.75rem; background: var(--color-surface-secondary); border: 1px solid var(--color-border); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm);">
                                    <?= $e($log['record_type']) ?> #<?= (int)$log['record_id'] ?>
                                </span>
                            </td>
                            <td style="padding: 0.875rem 1rem; color: var(--color-muted-text); font-family: monospace; font-size: 0.75rem;">
                                <?= $e($log['ip_address'] === '::1' ? '127.0.0.1 (Localhost)' : $log['ip_address']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Quick Action Modal for Return / Reject Justification -->
<div id="actionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--color-surface); border-radius: var(--radius-lg); padding: 2rem; max-width: 500px; width: 90%; box-shadow: var(--shadow-lg); border: 1px solid var(--color-border);">
        <h3 id="modalTitle" style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.5rem;">
            Reason for this decision
        </h3>
        <p id="modalDesc" style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0 0 1.25rem;">
            Please enter a reason for this decision so the requester can see it.
        </p>
        <form id="modalActionForm" method="POST" action="">
            <?= $csrf() ?>
            <input type="hidden" id="modalActionInput" name="action" value="">
            <div style="margin-bottom: 1.25rem;">
                <label for="modalComments" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                    Reason for this decision: <span style="color: var(--color-danger);">*</span>
                </label>
                <textarea id="modalComments" name="comments" rows="4" required style="width: 100%; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.625rem; font-size: 0.875rem; background: var(--color-surface); color: var(--color-text);" placeholder="Explain what needs to be changed or why this request cannot proceed..."></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline" onclick="closeActionModal()" style="font-size: 0.8125rem; padding: 0.5rem 1rem;">Cancel</button>
                <button type="submit" id="modalSubmitBtn" class="btn btn-primary" style="font-size: 0.8125rem; padding: 0.5rem 1.25rem;">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchRoleQueue(role) {
    document.querySelectorAll('.role-queue-panel').forEach(function(el) {
        el.style.display = 'none';
    });
    var target = document.getElementById('queue-panel-' + role);
    if (target) {
        target.style.display = 'block';
    }
}

function openActionModal(action, reqId, reqNumber) {
    var modal = document.getElementById('actionModal');
    var form = document.getElementById('modalActionForm');
    var actionInput = document.getElementById('modalActionInput');
    var title = document.getElementById('modalTitle');
    var submitBtn = document.getElementById('modalSubmitBtn');
    
    form.action = '<?= $e($appUrl ?? '') ?>/requisitions/' + reqId + '/action';
    actionInput.value = action;
    
    if (action === 'RETURN') {
        title.textContent = 'Send Back Request #' + reqNumber + ' for Changes';
        submitBtn.textContent = 'Send Back';
        submitBtn.className = 'btn btn-warning';
    } else {
        title.textContent = 'Reject Request #' + reqNumber;
        submitBtn.textContent = 'Reject Request';
        submitBtn.className = 'btn btn-danger';
    }
    
    modal.style.display = 'flex';
}

function closeActionModal() {
    document.getElementById('actionModal').style.display = 'none';
}
</script>
