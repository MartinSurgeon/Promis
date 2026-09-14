<?php
/**
 * Role-Aware Institutional Dashboard View
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
 * @var array $metrics
 * @var array $recentRequisitions
 * @var array $budgetSummary
 * @var array $recentAuditLogs
 * @var string $appUrl
 * @var callable $e
 */

use Promis\Src\Execution\Domain\RequisitionStatus;

$userRecord = is_callable($user) ? $user() : ($user ?? []);
$userFullName = $userRecord['name'] ?? $userRecord['full_name'] ?? $userRecord['username'] ?? 'Colleague';

$pendingWorkload = match(true) {
    $isHod => (int)($metrics['pending_endorsement'] ?? 0),
    $isDean => (int)($metrics['pending_approval'] ?? 0),
    $isFinance => (int)($metrics['pending_commitment'] ?? 0),
    $isProcurement => (int)($metrics['awaiting_receipt'] ?? 0),
    $isAdmin => (int)($metrics['submitted'] ?? 0),
    default => (int)($metrics['my_returned'] ?? 0),
};
?>

<!-- Welcome Banner with Core Orientation Triad & Dominant CTA -->
<div class="card" style="margin-bottom: 2rem; background: var(--gradient-brand); color: #ffffff; border: none; border-radius: var(--radius-lg); padding: 2rem 2.25rem; box-shadow: var(--shadow-md);">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
        <div style="max-width: 680px;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <span class="badge" style="background: rgba(255,255,255,0.2); color: #ffffff; border: 1px solid rgba(255,255,255,0.3); font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px;">
                    Institutional Procurement Portal
                </span>
                <span style="font-size: 0.75rem; opacity: 0.85;">•</span>
                <span style="font-size: 0.75rem; opacity: 0.85; font-weight: 500;">USTED Ghana</span>
            </div>
            <h2 style="font-size: 1.625rem; font-weight: 800; color: #ffffff; margin: 0 0 0.5rem 0; letter-spacing: -0.015em; line-height: 1.2;">
                Welcome, <?= $e($userFullName) ?>
            </h2>
            <p style="font-size: 0.9375rem; opacity: 0.94; margin: 0; line-height: 1.6;">
                You are logged in as <strong style="text-decoration: underline; text-underline-offset: 3px;"><?= $e(implode(', ', $roles)) ?></strong>. Track your item requests, approve pending requests, and monitor department spending.
            </p>
        </div>

        <!-- Dominant Primary CTA & Secondary Action (Hick's Law & Fitts's Law) -->
        <div style="display: flex; gap: 0.875rem; flex-wrap: wrap; align-items: center;">
            <a href="<?= $e($appUrl ?? '') ?>/requisitions/create" class="btn" style="background: #22c55e; color: #ffffff; font-weight: 700; font-size: 0.9375rem; min-height: 44px; padding: 0.625rem 1.35rem; border-radius: var(--radius-md); box-shadow: 0 2px 5px rgba(0,0,0,0.15); display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; border: none; transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.15)';">
                <i class="fa-solid fa-plus"></i>
                <span>Make a Requisition</span>
            </a>

            <?php if ($isHod || $isDean || $isFinance || $isProcurement || $isAdmin): ?>
                <a href="<?= $e($appUrl ?? '') ?>/requisitions?filter=pending" class="btn" style="background: #ffffff; color: var(--color-primary); font-weight: 700; font-size: 0.9375rem; min-height: 44px; padding: 0.625rem 1.25rem; border-radius: var(--radius-md); box-shadow: 0 2px 5px rgba(0,0,0,0.15); display: inline-flex; align-items: center; gap: 0.625rem; text-decoration: none; transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.15)';">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Review Pending Queue</span>
                    <?php if ($pendingWorkload > 0): ?>
                        <span style="background: var(--color-primary); color: #ffffff; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 800;">
                            <?= $pendingWorkload ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="<?= $e($appUrl ?? '') ?>/procurement-plans" class="btn btn-outline" style="background: rgba(255,255,255,0.18); color: #ffffff; border: 1px solid rgba(255,255,255,0.5); font-size: 0.9375rem; min-height: 44px; padding: 0.625rem 1.125rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-weight: 600; transition: background 0.15s ease;" onmouseover="this.style.background='rgba(255,255,255,0.28)'" onmouseout="this.style.background='rgba(255,255,255,0.18)'">
                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                <span>Annual Plans</span>
            </a>
            <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="btn btn-outline" style="background: rgba(255,255,255,0.12); color: #ffffff; border: 1px solid rgba(255,255,255,0.4); font-size: 0.9375rem; min-height: 44px; padding: 0.625rem 1.125rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-weight: 600; transition: background 0.15s ease;" onmouseover="this.style.background='rgba(255,255,255,0.22)'" onmouseout="this.style.background='rgba(255,255,255,0.12)'">
                <i class="fa-solid fa-list" aria-hidden="true"></i>
                <span>All Requisitions</span>
            </a>
        </div>
    </div>
</div>

<!-- Role-Specific Actionable Metrics Cards (Interactive & Tactile) -->
<div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
    <?php 
    $isGovernance = $isHod || $isDean || $isFinance || $isProcurement || $isAdmin;
    if ($isGovernance): 
    ?>
        <!-- Governance & Finance Roles 4-Card Balanced Suite -->
        <!-- Card 1: Stage Pending Queue Workload -->
        <a href="<?= $e($appUrl ?? '') ?>/requisitions?filter=pending" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-primary); box-shadow: var(--shadow-sm); transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
                        <?php if ($isHod): ?>Pending Endorsements
                        <?php elseif ($isDean): ?>Pending Dean Approvals
                        <?php elseif ($isFinance): ?>Pending Finance Approvals
                        <?php elseif ($isProcurement): ?>Ready for Goods Delivery
                        <?php else: ?>Requests Waiting for Review
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-primary); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums; line-height: 1.2;">
                        <?= (int)$pendingWorkload ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text); font-weight: 500;">
                        Waiting for your action
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.125rem; flex-shrink: 0;">
                    <i class="fa-solid <?php if ($isHod): ?>fa-signature<?php elseif ($isDean): ?>fa-stamp<?php elseif ($isFinance): ?>fa-vault<?php elseif ($isProcurement): ?>fa-boxes-packing<?php else: ?>fa-inbox<?php endif; ?>"></i>
                </div>
            </div>
        </a>

        <!-- Card 2: Total Active Requisitions -->
        <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-info); box-shadow: var(--shadow-sm); transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
                        Total Requisitions
                    </div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-text); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums; line-height: 1.2;">
                        <?= (int)($metrics['total_requisitions'] ?? count($recentRequisitions)) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text); font-weight: 500;">
                        All department requests
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(2, 132, 199, 0.1); color: var(--color-info); display: flex; align-items: center; justify-content: center; font-size: 1.125rem; flex-shrink: 0;">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>
        </a>

        <!-- Card 3: Total Committed Expenditures -->
        <a href="#budget" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success); box-shadow: var(--shadow-sm); transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
                        Money Spent or Reserved
                    </div>
                    <div style="font-size: 1.375rem; font-weight: 800; color: var(--color-success); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums; line-height: 1.2;">
                        GHS <?= number_format((float)($budgetSummary['total_committed'] ?? 0), 2) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text); font-weight: 500;">
                        Approved for university purchases
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(5, 131, 23, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.125rem; flex-shrink: 0;">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
        </a>

        <!-- Card 4: Governance Compliance Standard -->
        <div class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid #2563eb; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
                        Procurement Rules
                    </div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #2563eb; margin: 0.35rem 0 0.25rem; line-height: 1.2;">
                        Ghana PPA
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text); font-weight: 500;">
                        Public Procurement Authority standard
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(37, 99, 235, 0.08); color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.125rem; flex-shrink: 0;">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Requester Metrics (Generously Padded with Zero Border Collision) -->
        <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-warning); box-shadow: var(--shadow-sm); transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
                        My Drafts
                    </div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-text); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums; line-height: 1.2;">
                        <?= (int)($metrics['my_drafts'] ?? $metrics['drafts'] ?? 0) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text); font-weight: 500;">
                        Saved, not yet submitted
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(221, 153, 51, 0.1); color: var(--color-warning); display: flex; align-items: center; justify-content: center; font-size: 1.125rem; flex-shrink: 0;">
                    <i class="fa-solid fa-file-pen"></i>
                </div>
            </div>
        </a>

        <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-info); box-shadow: var(--shadow-sm); transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
                        In Approval Pipeline
                    </div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-text); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums; line-height: 1.2;">
                        <?= (int)($metrics['my_submitted'] ?? $metrics['submitted'] ?? 0) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text); font-weight: 500;">
                        Under departmental review
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(2, 132, 199, 0.1); color: var(--color-info); display: flex; align-items: center; justify-content: center; font-size: 1.125rem; flex-shrink: 0;">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
            </div>
        </a>

        <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-danger); box-shadow: var(--shadow-sm); transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
                        Returned for Revision
                    </div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-danger); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums; line-height: 1.2;">
                        <?= (int)($metrics['my_returned'] ?? $metrics['returned'] ?? 0) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text); font-weight: 500;">
                        Action required by you
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(220, 38, 38, 0.1); color: var(--color-danger); display: flex; align-items: center; justify-content: center; font-size: 1.125rem; flex-shrink: 0;">
                    <i class="fa-solid fa-rotate-left"></i>
                </div>
            </div>
        </a>

        <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="card" style="display: block; background: var(--color-surface); padding: 1.25rem 1.5rem; text-decoration: none; border-radius: var(--radius-lg); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success); box-shadow: var(--shadow-sm); transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
                        Approved & Completed
                    </div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-success); margin: 0.35rem 0 0.25rem; font-variant-numeric: tabular-nums; line-height: 1.2;">
                        <?= (int)($metrics['completed'] ?? 0) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text); font-weight: 500;">
                        Received by Procurement
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(5, 131, 23, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.125rem; flex-shrink: 0;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
        </a>
    <?php endif; ?>
</div>

<!-- Institutional Budget Overview Section (Finance / Admin / General Awareness) -->
<div id="budget" class="card" style="margin-bottom: 2rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.625rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(221, 153, 51, 0.12); color: var(--color-accent); font-size: 1rem;">
                    <i class="fa-solid fa-coins"></i>
                </span>
                Institutional Budget Availability & Commitment Status
            </h3>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0.375rem 0 0; line-height: 1.4;">
                Fiscal Year <?= $e((string)$budgetSummary['fiscal_year']) ?> Expenditure & Commitment Ceiling across authorized academic and administrative entities
            </p>
        </div>
        <div>
            <span class="badge badge-<?= $budgetSummary['has_live_data'] ? 'success' : 'info' ?>" style="font-size: 0.75rem; font-weight: 600; padding: 0.375rem 0.75rem; border-radius: 9999px;">
                <?= $budgetSummary['has_live_data'] ? '● Live database-backed metrics refreshed on page load' : '○ Standby State (0.00 GHS)' ?>
            </span>
        </div>
    </div>

    <!-- Gestalt Similarity: Consistent, Structured Metric Cards -->
    <div class="budget-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
        <!-- Total Budget Card -->
        <div style="background: var(--color-surface-secondary); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); border-left: 4px solid #64748b; box-shadow: var(--shadow-xs);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.05em;">
                    Total Allocated Budget
                </div>
                <span style="color: #64748b; font-size: 1rem;">
                    <i class="fa-solid fa-landmark"></i>
                </span>
            </div>
            <div style="font-size: 1.625rem; font-weight: 800; color: var(--color-text); margin: 0.25rem 0 0.5rem; font-variant-numeric: tabular-nums; letter-spacing: -0.01em;">
                GHS <?= number_format((float)$budgetSummary['total_allocated'], 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--color-muted-text); line-height: 1.4;">
                Approved institutional ceiling across active planning entities
            </div>
        </div>

        <!-- Committed Expenditures Card -->
        <div style="background: var(--color-surface-secondary); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); border-left: 4px solid var(--color-primary); box-shadow: var(--shadow-xs);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.05em;">
                    Committed Expenditures
                </div>
                <span style="color: var(--color-primary); font-size: 1rem;">
                    <i class="fa-solid fa-lock"></i>
                </span>
            </div>
            <div style="font-size: 1.625rem; font-weight: 800; color: var(--color-primary); margin: 0.25rem 0 0.5rem; font-variant-numeric: tabular-nums; letter-spacing: -0.01em;">
                GHS <?= number_format((float)$budgetSummary['total_committed'], 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--color-muted-text); line-height: 1.4;">
                Formally locked via Finance commitment authorizations
            </div>
        </div>

        <!-- Remaining Available Balance Card -->
        <div style="background: var(--color-surface-secondary); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); border-left: 4px solid var(--color-success); box-shadow: var(--shadow-xs);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-success); text-transform: uppercase; letter-spacing: 0.05em;">
                    Remaining Available Balance
                </div>
                <span style="color: var(--color-success); font-size: 1rem;">
                    <i class="fa-solid fa-wallet"></i>
                </span>
            </div>
            <div style="font-size: 1.625rem; font-weight: 800; color: var(--color-success); margin: 0.25rem 0 0.5rem; font-variant-numeric: tabular-nums; letter-spacing: -0.01em;">
                GHS <?= number_format((float)$budgetSummary['available_balance'], 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--color-muted-text); line-height: 1.4;">
                Unencumbered funds available for new requisitions
            </div>
        </div>
    </div>

    <!-- Budget Utilization Progress Bar with Generous Breathing Room -->
    <?php 
    $totalAlloc = (float)($budgetSummary['total_allocated'] ?? 0);
    $totalComm = (float)($budgetSummary['total_committed'] ?? 0);
    $utilPct = $totalAlloc > 0 ? min(100, round(($totalComm / $totalAlloc) * 100, 1)) : 0;
    ?>
    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border-subtle); padding-bottom: 0.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.5rem;">
            <span style="display: flex; align-items: center; gap: 0.375rem;">
                <i class="fa-solid fa-chart-line" style="color: var(--color-muted-text);"></i>
                Institutional Budget Utilization Rate
            </span>
            <span style="font-weight: 700; color: var(--color-primary); font-variant-numeric: tabular-nums; background: rgba(140, 0, 59, 0.08); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm);">
                <?= $utilPct ?>% Committed
            </span>
        </div>
        <div style="height: 12px; background: #e2e8f0; border-radius: 9999px; overflow: hidden; padding: 2px;">
            <div style="height: 100%; width: <?= $utilPct ?>%; background: <?= $utilPct > 90 ? 'var(--color-danger)' : ($utilPct > 70 ? 'var(--color-warning)' : 'var(--color-primary)') ?>; border-radius: 9999px; transition: width 0.3s ease;"></div>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted-text); margin-top: 0.625rem;">
            Budget allocations are formally appropriated under the Public Financial Management Act (PFMA Act 921) and statutory warrants.
        </div>
    </div>
</div>

<!-- Recent Requisitions Table / Workbench Activity -->
<div class="card" style="margin-bottom: 2rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-clock-rotate-left" style="color: var(--color-primary);"></i>
                Recent Requisition Activity
            </h3>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0.25rem 0 0;">
                Real-time tracking of procurement requests across university departments
            </p>
        </div>
        <div>
            <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="btn btn-outline" style="font-size: 0.8125rem; padding: 0.45rem 0.875rem; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600; border-radius: var(--radius-md);">
                <span>View All Requisitions</span>
                <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
            </a>
        </div>
    </div>

    <?php if (empty($recentRequisitions)): ?>
        <div class="empty-state" style="padding: 3rem 1rem; text-align: center;">
            <div style="width: 54px; height: 54px; border-radius: 50%; background: var(--color-surface-secondary); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--color-muted-text); font-size: 1.5rem;">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <h4 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.375rem;">
                No Requisitions Recorded Yet
            </h4>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); max-width: 450px; margin: 0 auto 1.25rem;">
                Your departmental requisition workbench is ready. When requisitions are created against approved procurement plans, they will appear here.
            </p>
            <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="btn btn-primary" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                <i class="fa-solid fa-list" style="margin-right: 0.375rem;"></i> Requisition Explorer
            </a>
        </div>
    <?php else: ?>
        <div class="table-container" style="overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: var(--color-surface-secondary); border-bottom: 2px solid var(--color-border); text-align: left;">
                        <th style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Requisition #</th>
                        <th style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Planning Entity</th>
                        <th style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Requester</th>
                        <th style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Estimated Cost</th>
                        <th style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
                        <th style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Created Date</th>
                        <th style="padding: 0.875rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRequisitions as $req): ?>
                        <tr style="border-bottom: 1px solid var(--color-border-subtle); transition: background-color 0.15s ease;">
                            <!-- Linkified Requisition Number -->
                            <td style="padding: 1rem; vertical-align: middle;">
                                <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$req['id'] ?>" style="font-weight: 700; color: var(--color-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.375rem;" title="View requisition details">
                                    <span><?= $e($req['requisition_number']) ?></span>
                                </a>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle; color: var(--color-text); font-weight: 600;">
                                <?= $e($req['entity_name']) ?>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle; color: var(--color-muted-text);">
                                <?= $e($req['requester_name']) ?>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle; font-weight: 700; color: var(--color-text); font-variant-numeric: tabular-nums;">
                                GHS <?= number_format((float)$req['total_estimated_cost'], 2) ?>
                            </td>
                            <!-- Two-Line Status Badge -->
                            <td style="padding: 1rem; vertical-align: middle;">
                                <?php $rStatus = RequisitionStatus::tryFrom((string)$req['status']); ?>
                                <div style="display: inline-flex; flex-direction: column; gap: 0.2rem;">
                                    <span class="badge badge-<?= $rStatus ? $rStatus->badgeClass() : strtolower((string)$req['status']) ?>" style="font-size: 0.75rem; font-weight: 700; width: fit-content; padding: 0.25rem 0.625rem; border-radius: 9999px;">
                                        <?= $e($rStatus ? $rStatus->title() : (string)$req['status']) ?>
                                    </span>
                                    <?php if ($rStatus): ?>
                                        <span style="font-size: 0.6875rem; color: var(--color-muted-text); font-weight: 500; margin-left: 0.125rem;">
                                            <?= $e($rStatus->sublabel()) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle; color: var(--color-muted-text); font-size: 0.8125rem; white-space: nowrap;">
                                <?= $e(date('M d, Y', strtotime($req['created_at']))) ?>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle; text-align: right; white-space: nowrap;">
                                <a href="<?= $e($appUrl ?? '') ?>/requisitions/<?= (int)$req['id'] ?>" class="btn btn-outline" style="padding: 0.45rem 0.875rem; font-size: 0.8125rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.375rem; border-color: var(--color-border); color: var(--color-primary); border-radius: var(--radius-md); transition: all 0.15s ease;" title="Review requisition">
                                    <span>Review</span>
                                    <i class="fa-solid fa-chevron-right" style="font-size: 0.6875rem;"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin && !empty($recentAuditLogs)): ?>
    <?php
    $formatAuditAction = function(string $action): array {
        return match($action) {
            'AUTH_LOGIN_SUCCESS' => ['label' => 'Sign In Success', 'badge' => 'badge-success', 'icon' => 'fa-right-to-bracket'],
            'AUTH_LOGIN_FAILED' => ['label' => 'Sign In Failed', 'badge' => 'badge-danger', 'icon' => 'fa-triangle-exclamation'],
            'AUTH_LOGOUT' => ['label' => 'Signed Out', 'badge' => 'badge-info', 'icon' => 'fa-arrow-right-from-bracket'],
            'AUTH_ACTIVATE' => ['label' => 'Account Activated', 'badge' => 'badge-success', 'icon' => 'fa-user-check'],
            'WORKFLOW_SUBMIT' => ['label' => 'Submitted', 'badge' => 'badge-info', 'icon' => 'fa-paper-plane'],
            'WORKFLOW_ENDORSE' => ['label' => 'HOD Endorsement', 'badge' => 'badge-info', 'icon' => 'fa-signature'],
            'WORKFLOW_APPROVE' => ['label' => 'Dean Approval', 'badge' => 'badge-success', 'icon' => 'fa-circle-check'],
            'WORKFLOW_COMMIT' => ['label' => 'Finance Commitment', 'badge' => 'badge-success', 'icon' => 'fa-vault'],
            'WORKFLOW_RECEIVE' => ['label' => 'Procurement Receipt', 'badge' => 'badge-primary', 'icon' => 'fa-boxes-packing'],
            'WORKFLOW_RETURN' => ['label' => 'Returned for Revision', 'badge' => 'badge-warning', 'icon' => 'fa-rotate-left'],
            'WORKFLOW_REJECT' => ['label' => 'Requisition Terminated', 'badge' => 'badge-danger', 'icon' => 'fa-ban'],
            default => ['label' => ucwords(strtolower(str_replace('_', ' ', $action))), 'badge' => 'badge-info', 'icon' => 'fa-clipboard-check'],
        };
    };
    ?>
    <!-- Admin Read-Only Institutional Audit History -->
    <div id="audit" class="card" style="margin-bottom: 2rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.625rem;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.1); color: var(--color-primary); font-size: 1rem;">
                        <i class="fa-solid fa-shield-halved"></i>
                    </span>
                    Read-Only Institutional Audit Log
                </h3>
                <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0.25rem 0 0;">
                    Immutable system action history (Read-only security compliance under statutory standards)
                </p>
            </div>
            <div>
                <span class="badge badge-success" style="font-size: 0.75rem; font-weight: 600; padding: 0.375rem 0.75rem; border-radius: 9999px;">
                    <i class="fa-solid fa-lock" style="margin-right: 0.25rem;"></i> Immutable Trail
                </span>
            </div>
        </div>

        <div class="table-container" style="overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                <thead>
                    <tr style="background: var(--color-surface-secondary); border-bottom: 2px solid var(--color-border); text-align: left;">
                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Timestamp</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Actor</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Action Recorded</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Record Target</th>
                        <th style="padding: 0.75rem 1rem; font-weight: 700; color: var(--color-muted-text); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Origin IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAuditLogs as $log): ?>
                        <?php $actMeta = $formatAuditAction($log['action']); ?>
                        <tr style="border-bottom: 1px solid var(--color-border-subtle); transition: background-color 0.15s ease;">
                            <td style="padding: 0.875rem 1rem; color: var(--color-text); font-weight: 500; white-space: nowrap;">
                                <?= $e(date('M d, Y • h:i A', strtotime($log['event_timestamp']))) ?>
                            </td>
                            <td style="padding: 0.875rem 1rem; font-weight: 600; color: var(--color-text);">
                                <?= $e($log['full_name'] ?? $log['username'] ?? 'System') ?>
                            </td>
                            <td style="padding: 0.875rem 1rem;">
                                <span class="badge <?= $actMeta['badge'] ?>" style="font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.625rem; border-radius: 9999px;">
                                    <i class="fa-solid <?= $actMeta['icon'] ?>"></i>
                                    <span><?= $e($actMeta['label']) ?></span>
                                </span>
                            </td>
                            <td style="padding: 0.875rem 1rem;">
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; background: var(--color-surface-secondary); border: 1px solid var(--color-border); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); font-weight: 500;">
                                    <span style="color: var(--color-muted-text); text-transform: capitalize;"><?= $e($log['record_type']) ?></span>
                                    <span style="font-weight: 700; color: var(--color-primary);">#<?= (int)$log['record_id'] ?></span>
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

<!-- Peak-End Rule: Institutional Governance, Compliance & Helpdesk Assurance Strip -->
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
                All requisition actions, budgetary commitments, and deanship endorsements are executed in strict accordance with the Public Procurement Act, 2003 (Act 663) as amended by Act 914, and the Public Financial Management Act, 2016 (Act 921).
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
