<?php
/**
 * Procurement Plan Versions & Revisions History View
 * PROMIS - Procurement Management Information System
 *
 * @var array $plan
 * @var array $versions
 * @var array $revisions
 * @var array $cycles
 * @var string $appUrl
 * @var callable $e
 */

$planId = (int)$plan['id'];
?>

<!-- Breadcrumbs -->
<div style="font-size: 0.8125rem; color: var(--color-muted-text); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
    <a href="<?= $e($appUrl ?? '') ?>/procurement-plans" style="color: var(--color-muted-text); text-decoration: none;">Procurement Plans</a>
    <i class="fa-solid fa-chevron-right" style="font-size: 0.6875rem;"></i>
    <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>" style="color: var(--color-muted-text); text-decoration: none;"><?= $e($plan['plan_number']) ?></a>
    <i class="fa-solid fa-chevron-right" style="font-size: 0.6875rem;"></i>
    <span style="color: var(--color-text); font-weight: 600;">Plan History & Versions</span>
</div>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); margin: 0 0 0.375rem; letter-spacing: -0.02em;">
            Plan History: <?= $e($plan['plan_number']) ?>
        </h1>
        <p style="font-size: 0.875rem; color: var(--color-muted-text); margin: 0;">
            History of plan versions, updates, price adjustments, and quarterly reviews.
        </p>
    </div>

    <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>" class="btn btn-secondary" style="font-weight: 600;">
        <i class="fa-solid fa-arrow-left"></i> Back to Plan
    </a>
</div>

<!-- Plan Versions Section -->
<div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.75rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-code-branch" style="color: var(--color-primary);"></i>
            <span>Plan Versions</span>
        </h2>
        <span style="font-size: 0.8125rem; font-weight: 700; color: var(--color-muted-text);">
            <?= count($versions) ?> Total Versions
        </span>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; text-align: left; min-width: 800px;">
            <thead>
                <tr style="background: var(--color-surface-secondary); border-bottom: 1px solid var(--color-border); color: var(--color-muted-text); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                    <th style="padding: 0.75rem 1rem;">Version</th>
                    <th style="padding: 0.75rem 1rem;">Status</th>
                    <th style="padding: 0.75rem 1rem; text-align: right;">Total Estimated Budget</th>
                    <th style="padding: 0.75rem 1rem; text-align: center;">Items</th>
                    <th style="padding: 0.75rem 1rem;">Created By</th>
                    <th style="padding: 0.75rem 1rem;">Approval Info</th>
                    <th style="padding: 0.75rem 1rem;">Reason for Update</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($versions as $v): ?>
                    <?php
                    $vStatus = strtoupper((string)$v['status']);
                    $badgeClass = match($vStatus) {
                        'APPROVED' => 'badge-success',
                        'SUBMITTED' => 'badge-warning',
                        'DRAFT' => 'badge-info',
                        'SUPERSEDED' => 'badge-muted',
                        'REJECTED' => 'badge-danger',
                        default => 'badge-muted',
                    };
                    $isCurrent = (int)($plan['current_version_id'] ?? 0) === (int)$v['id'];
                    ?>
                    <tr style="border-bottom: 1px solid var(--color-border); <?= $isCurrent ? 'background: rgba(140, 0, 59, 0.03);' : '' ?>">
                        <td style="padding: 0.875rem 1rem; font-weight: 700;">
                            <span style="font-size: 0.9375rem; color: var(--color-text);">v<?= $e($v['version_number']) ?></span>
                            <?php if ($isCurrent): ?>
                                <span style="font-size: 0.6875rem; padding: 0.125rem 0.375rem; border-radius: 4px; background: var(--color-primary); color: #ffffff; margin-left: 0.375rem; font-weight: 700;">
                                    ACTIVE
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.875rem 1rem;">
                            <span class="badge <?= $badgeClass ?>" style="font-size: 0.6875rem; font-weight: 700; padding: 0.25rem 0.5rem; text-transform: uppercase;">
                                <?= $e($vStatus) ?>
                            </span>
                        </td>
                        <td style="padding: 0.875rem 1rem; text-align: right; font-weight: 700; color: var(--color-text); font-variant-numeric: tabular-nums;">
                            GHS <?= number_format((float)$v['total_estimated_cost'], 2) ?>
                        </td>
                        <td style="padding: 0.875rem 1rem; text-align: center; color: var(--color-muted-text);">
                            <?= (int)($v['item_count'] ?? 0) ?>
                        </td>
                        <td style="padding: 0.875rem 1rem;">
                            <div><?= $e(trim(($v['creator_first_name'] ?? '') . ' ' . ($v['creator_last_name'] ?? '')) ?: 'Officer') ?></div>
                            <div style="font-size: 0.6875rem; color: var(--color-muted-text);"><?= date('M d, Y', strtotime($v['created_at'])) ?></div>
                        </td>
                        <td style="padding: 0.875rem 1rem;">
                            <?php if (!empty($v['approval_date'])): ?>
                                <div style="font-weight: 600; color: var(--color-success);">Approved</div>
                                <div style="font-size: 0.6875rem; color: var(--color-muted-text);">
                                    by <?= $e(trim(($v['approver_first_name'] ?? '') . ' ' . ($v['approver_last_name'] ?? '')) ?: 'Approver') ?> on <?= date('M d, Y', strtotime($v['approval_date'])) ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--color-muted-text); font-style: italic;">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.875rem 1rem; color: var(--color-muted-text); font-size: 0.75rem;">
                            <?= $e($v['revision_reason'] ?: 'None recorded') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Plan Revision Records Section -->
<div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.75rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-border);">
        <h2 style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-arrows-split-up-and-left" style="color: var(--color-primary);"></i>
            <span>Changes & Price Updates</span>
        </h2>
    </div>

    <?php if (empty($revisions)): ?>
        <div style="padding: 2.5rem; text-align: center; color: var(--color-muted-text); font-size: 0.875rem;">
            No revisions recorded. This plan remains on its original version.
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; text-align: left;">
                <thead>
                    <tr style="background: var(--color-surface-secondary); border-bottom: 1px solid var(--color-border); color: var(--color-muted-text); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 0.75rem 1rem;">Version Change</th>
                        <th style="padding: 0.75rem 1rem;">Type of Change</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">Budget Change (GHS)</th>
                        <th style="padding: 0.75rem 1rem;">Reason for Change</th>
                        <th style="padding: 0.75rem 1rem;">Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($revisions as $r): ?>
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 0.875rem 1rem; font-weight: 700;">
                                v<?= $e($r['prior_version_number'] ?? $r['base_version_number'] ?? '1.0') ?> &rarr; v<?= $e($r['new_version_number'] ?? '2.0') ?>
                            </td>
                            <td style="padding: 0.875rem 1rem; font-weight: 600;">
                                <?= $e(str_replace('_', ' ', $r['revision_type'] ?? 'FORMAL_REVISION')) ?>
                            </td>
                            <td style="padding: 0.875rem 1rem; text-align: right; font-weight: 800; font-variant-numeric: tabular-nums; color: <?= (float)($r['total_cost_delta'] ?? 0) > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>;">
                                <?= (float)($r['total_cost_delta'] ?? 0) >= 0 ? '+' : '' ?>GHS <?= number_format((float)($r['total_cost_delta'] ?? 0), 2) ?>
                            </td>
                            <td style="padding: 0.875rem 1rem; color: var(--color-text);">
                                <?= $e($r['revision_justification'] ?? $r['justification'] ?? 'Revision record') ?>
                            </td>
                            <td style="padding: 0.875rem 1rem;">
                                <div><?= $e(trim(($r['creator_first_name'] ?? '') . ' ' . ($r['creator_last_name'] ?? '')) ?: 'Officer') ?></div>
                                <div style="font-size: 0.6875rem; color: var(--color-muted-text);"><?= !empty($r['submitted_at']) ? date('M d, Y', strtotime($r['submitted_at'])) : date('M d, Y') ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Quarterly Review Cycles Section -->
<div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm);">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-border);">
        <h2 style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-chart-line" style="color: var(--color-primary);"></i>
            <span>Quarterly Reviews (Q1 - Q4)</span>
        </h2>
    </div>

    <?php if (empty($cycles)): ?>
        <div style="padding: 2.5rem; text-align: center; color: var(--color-muted-text); font-size: 0.875rem;">
            No quarterly reviews conducted yet for Year <?= $e($plan['fiscal_year']) ?>.
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; text-align: left;">
                <thead>
                    <tr style="background: var(--color-surface-secondary); border-bottom: 1px solid var(--color-border); color: var(--color-muted-text); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 0.75rem 1rem;">Quarter</th>
                        <th style="padding: 0.75rem 1rem;">Active Version</th>
                        <th style="padding: 0.75rem 1rem;">Review Status</th>
                        <th style="padding: 0.75rem 1rem;">Outcome</th>
                        <th style="padding: 0.75rem 1rem;">Review Notes</th>
                        <th style="padding: 0.75rem 1rem;">Reviewed By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cycles as $c): ?>
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 0.875rem 1rem; font-weight: 800; color: var(--color-primary);">
                                <?= $e($c['review_quarter']) ?> (FY <?= $e($c['fiscal_year']) ?>)
                            </td>
                            <td style="padding: 0.875rem 1rem; font-weight: 600;">
                                v<?= $e($c['active_version_number']) ?>
                            </td>
                            <td style="padding: 0.875rem 1rem;">
                                <span class="badge badge-info" style="font-size: 0.6875rem; font-weight: 700; padding: 0.25rem 0.5rem; text-transform: uppercase;">
                                    <?= $e($c['review_status']) ?>
                                </span>
                            </td>
                            <td style="padding: 0.875rem 1rem; font-weight: 700;">
                                <?= $e(str_replace('_', ' ', $c['outcome'] ?? 'PENDING')) ?>
                            </td>
                            <td style="padding: 0.875rem 1rem; color: var(--color-text);">
                                <?= $e($c['review_notes'] ?: 'No notes recorded') ?>
                            </td>
                            <td style="padding: 0.875rem 1rem;">
                                <div><?= $e(trim(($c['reviewer_first_name'] ?? '') . ' ' . ($c['reviewer_last_name'] ?? '')) ?: 'Reviewer') ?></div>
                                <div style="font-size: 0.6875rem; color: var(--color-muted-text);"><?= !empty($c['review_date']) ? date('M d, Y', strtotime($c['review_date'])) : '-' ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
