<?php
/**
 * Procurement Plan Details View
 * PROMIS - Procurement Management Information System
 *
 * @var array $plan
 * @var array $items
 * @var array $groupedItems
 * @var array $categoryTotals
 * @var string $grandTotal
 * @var array $actionLogs
 * @var bool $canEdit
 * @var bool $canSubmit
 * @var bool $canApprove
 * @var bool $canReturn
 * @var bool $canReject
 * @var string $appUrl
 * @var callable $e
 * @var callable $csrf
 */

$planId = (int)$plan['id'];
$status = strtoupper((string)$plan['status']);
$statusBadgeClass = match($status) {
    'APPROVED' => 'badge-success',
    'SUBMITTED', 'UNDER_REVIEW' => 'badge-warning',
    'DRAFT' => 'badge-info',
    'RETURNED' => 'badge-accent',
    'REJECTED' => 'badge-danger',
    default => 'badge-muted',
};
?>

<!-- Breadcrumbs -->
<div style="font-size: 0.8125rem; color: var(--color-muted-text); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
    <a href="<?= $e($appUrl ?? '') ?>/procurement-plans" style="color: var(--color-muted-text); text-decoration: none;">Procurement Plans</a>
    <i class="fa-solid fa-chevron-right" style="font-size: 0.6875rem;"></i>
    <span style="color: var(--color-text); font-weight: 600;"><?= $e($plan['plan_number']) ?></span>
</div>

<!-- Header Card -->
<div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.375rem;">
                <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); margin: 0; letter-spacing: -0.02em;">
                    <?= $e($plan['plan_number']) ?>
                </h1>
                <span class="badge <?= $statusBadgeClass ?>" style="font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.625rem; text-transform: uppercase;">
                    <?= $e(str_replace('_', ' ', $status)) ?>
                </span>
                <span style="display: inline-block; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); background: var(--color-surface-secondary); border: 1px solid var(--color-border); font-size: 0.75rem; font-weight: 700;">
                    v<?= $e($plan['version_number'] ?? '1.0') ?> Baseline
                </span>
            </div>
            <div style="font-size: 0.9375rem; font-weight: 600; color: var(--color-primary);">
                <?= $e($plan['entity_name']) ?> (<?= $e($plan['entity_code']) ?>)
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <?php if ($status === 'APPROVED'): ?>
                <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>/print" target="_blank" class="btn btn-outline" style="font-weight: 600; font-size: 0.8125rem;">
                    <i class="fa-solid fa-print"></i> Print Official Plan
                </a>
                <a href="<?= $e($appUrl ?? '') ?>/requisitions/create?plan_id=<?= $planId ?>" class="btn btn-primary" style="font-weight: 600; font-size: 0.8125rem;">
                    <i class="fa-solid fa-cart-plus"></i> Make Requisition
                </a>
            <?php endif; ?>

            <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>/versions" class="btn btn-secondary" style="font-weight: 600; font-size: 0.8125rem;">
                <i class="fa-solid fa-code-branch"></i> Version History
            </a>
        </div>
    </div>

    <!-- Metadata Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; border-top: 1px solid var(--color-border); padding-top: 1.25rem;">
        <div>
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted-text);">Financial Year</div>
            <div style="font-size: 0.9375rem; font-weight: 700; color: var(--color-text); margin-top: 0.25rem;">
                FY <?= $e($plan['fiscal_year']) ?>
            </div>
        </div>

        <div>
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted-text);">Prepared By</div>
            <div style="font-size: 0.875rem; font-weight: 600; color: var(--color-text); margin-top: 0.25rem;">
                <?= $e(trim(($plan['creator_first_name'] ?? '') . ' ' . ($plan['creator_last_name'] ?? '')) ?: 'Institutional User') ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--color-muted-text);"><?= date('M d, Y H:i', strtotime($plan['created_at'])) ?></div>
        </div>

        <div>
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted-text);">Approval Status</div>
            <div style="font-size: 0.875rem; font-weight: 600; color: var(--color-text); margin-top: 0.25rem;">
                <?php if (!empty($plan['approval_date'])): ?>
                    Approved on <?= date('M d, Y', strtotime($plan['approval_date'])) ?>
                <?php else: ?>
                    <?= $e(str_replace('_', ' ', $status)) ?>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: var(--color-muted-text);">Total Estimated Budget</div>
            <div style="font-size: 1.125rem; font-weight: 800; color: var(--color-primary); margin-top: 0.25rem; font-variant-numeric: tabular-nums;">
                GHS <?= number_format((float)$grandTotal, 2) ?>
            </div>
        </div>
    </div>
</div>

<!-- Workflow Action Decision Panel (Contextual) -->
<?php if ($canEdit || $canSubmit || $canApprove || $canReturn || $canReject): ?>
    <div class="card" style="background: var(--color-surface); border: 2px solid var(--color-primary); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="font-size: 1rem; font-weight: 800; color: var(--color-primary); margin: 0 0 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-bolt"></i>
                    <span>Available Governance Actions</span>
                </h3>
                <div style="font-size: 0.8125rem; color: var(--color-muted-text);">
                    You have authorized role permissions to act on this procurement plan stage.
                </div>
            </div>

            <div style="display: flex; gap: 0.625rem; flex-wrap: wrap;">
                <?php if ($canEdit): ?>
                    <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>/edit" class="btn btn-secondary" style="font-weight: 700; font-size: 0.875rem;">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Plan Items
                    </a>
                <?php endif; ?>

                <?php if ($canSubmit): ?>
                    <form method="POST" action="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>/action" style="margin: 0;">
                        <?= $csrf() ?>
                        <input type="hidden" name="action" value="SUBMIT">
                        <button type="submit" class="btn btn-primary" style="font-weight: 700; font-size: 0.875rem;"
                                data-confirm="Submit this Annual Procurement Plan for formal institutional review and approval?"
                                data-confirm-title="Submit Procurement Plan"
                                data-confirm-detail="Plan items will be locked for review by institutional approvers."
                                data-confirm-type="primary"
                                data-confirm-btn="Submit for Approval"
                                data-confirm-icon="fa-paper-plane">
                            <i class="fa-solid fa-paper-plane"></i> Submit for Approval
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($canReturn): ?>
                    <button type="button" class="btn btn-secondary" style="font-weight: 700; font-size: 0.875rem; color: var(--color-accent);" onclick="document.getElementById('returnModal').style.display='flex'">
                        <i class="fa-solid fa-rotate-left"></i> Return for Correction
                    </button>
                <?php endif; ?>

                <?php if ($canReject): ?>
                    <button type="button" class="btn btn-danger" style="font-weight: 700; font-size: 0.875rem;" onclick="document.getElementById('rejectModal').style.display='flex'">
                        <i class="fa-solid fa-ban"></i> Reject Plan
                    </button>
                <?php endif; ?>

                <?php if ($canApprove): ?>
                    <form method="POST" action="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>/action" style="margin: 0;">
                        <?= $csrf() ?>
                        <input type="hidden" name="action" value="APPROVE">
                        <button type="submit" class="btn btn-success" style="font-weight: 700; font-size: 0.875rem; background: var(--color-success); border-color: var(--color-success); color: #ffffff;"
                                data-confirm="Formally approve this Annual Procurement Plan?"
                                data-confirm-title="Approve Procurement Plan"
                                data-confirm-detail="Approved items will become immediately active for departmental requisition drawdown."
                                data-confirm-type="success"
                                data-confirm-btn="Approve Plan"
                                data-confirm-icon="fa-circle-check">
                            <i class="fa-solid fa-circle-check"></i> Approve Procurement Plan
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Return Modal Dialog -->
<div id="returnModal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="returnModalTitle">
    <div class="modal-backdrop" onclick="closeModal('returnModal')"></div>
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <h3 id="returnModalTitle" class="modal-title" style="color: var(--color-warning);">
                <i class="fa-solid fa-rotate-left"></i> Return Plan for Correction
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('returnModal')" aria-label="Close dialog">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>/action" style="margin: 0;">
            <?= $csrf() ?>
            <input type="hidden" name="action" value="RETURN">
            <div class="modal-body">
                <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0 0 1rem;">
                    Provide clear feedback to the department explaining what adjustments or clarifications are required before approval.
                </p>
                <div style="margin-bottom: 0.5rem;">
                    <label for="return_comments" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem;">Feedback & Queries <span style="color: var(--color-danger);">*</span></label>
                    <textarea id="return_comments" name="comments" required rows="4" placeholder="Specify items needing revision, cost adjustments, or justification..."
                              style="width: 100%; padding: 0.625rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text); resize: vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('returnModal')">Cancel</button>
                <button type="submit" class="btn btn-accent">Submit Return Decision</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal Dialog -->
<div id="rejectModal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="rejectModalTitle">
    <div class="modal-backdrop" onclick="closeModal('rejectModal')"></div>
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <h3 id="rejectModalTitle" class="modal-title" style="color: var(--color-danger);">
                <i class="fa-solid fa-ban"></i> Reject Procurement Plan
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('rejectModal')" aria-label="Close dialog">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>/action" style="margin: 0;">
            <?= $csrf() ?>
            <input type="hidden" name="action" value="REJECT">
            <div class="modal-body">
                <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0 0 1rem;">
                    Are you sure you want to reject this procurement plan? Please provide the institutional justification.
                </p>
                <div style="margin-bottom: 0.5rem;">
                    <label for="reject_comments" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem;">Rejection Justification <span style="color: var(--color-danger);">*</span></label>
                    <textarea id="reject_comments" name="comments" required rows="4" placeholder="Reason for rejection..."
                              style="width: 100%; padding: 0.625rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-background); color: var(--color-text); resize: vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<!-- Itemized Forecast Grouped by Category (Mirroring Resources.pdf) -->
<div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <h2 style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-table-list" style="color: var(--color-primary);"></i>
            <span>Itemized Procurement Breakdown by Contract Package</span>
        </h2>
        <span style="font-size: 0.8125rem; font-weight: 700; color: var(--color-muted-text);">
            <?= count($items) ?> Total Line Items
        </span>
    </div>

    <?php if (empty($groupedItems)): ?>
        <div style="padding: 3rem; text-align: center; color: var(--color-muted-text);">
            No items have been registered under this plan version.
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; text-align: left; min-width: 900px;">
                <thead>
                    <tr style="background: var(--color-surface-secondary); border-bottom: 1px solid var(--color-border); color: var(--color-muted-text); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 0.75rem 1rem; width: 50px; text-align: center;">SN</th>
                        <th style="padding: 0.75rem 1rem; min-width: 250px;">Contract Package / Description of Item</th>
                        <th style="padding: 0.75rem 1rem; min-width: 200px;">Detailed Specification / Remarks</th>
                        <th style="padding: 0.75rem 1rem; width: 100px; text-align: right;">QTY</th>
                        <th style="padding: 0.75rem 1rem; width: 130px; text-align: right;">Unit Cost (GHS)</th>
                        <th style="padding: 0.75rem 1rem; width: 140px; text-align: right;">Total Estimated Cost (GHS)</th>
                        <th style="padding: 0.75rem 1rem; width: 90px; text-align: center;">Target Quarter</th>
                        <th style="padding: 0.75rem 1rem; width: 140px;">Funding Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $globalSn = 1;
                    foreach ($groupedItems as $categoryName => $catItems): 
                    ?>
                        <!-- Category Group Header Row -->
                        <tr style="background: rgba(140, 0, 59, 0.04); border-top: 2px solid var(--color-border); border-bottom: 1px solid var(--color-border);">
                            <td colspan="5" style="padding: 0.625rem 1rem; font-weight: 800; font-size: 0.875rem; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.03em;">
                                <i class="fa-solid fa-folder-open" style="margin-right: 0.375rem;"></i>
                                <?= $e($categoryName) ?>
                            </td>
                            <td style="padding: 0.625rem 1rem; text-align: right; font-weight: 800; color: var(--color-primary); font-variant-numeric: tabular-nums;">
                                GHS <?= number_format((float)($categoryTotals[$categoryName] ?? 0), 2) ?>
                            </td>
                            <td colspan="2" style="padding: 0.625rem 1rem; font-size: 0.75rem; color: var(--color-muted-text);">
                                (<?= count($catItems) ?> items)
                            </td>
                        </tr>

                        <!-- Category Items -->
                        <?php foreach ($catItems as $item): ?>
                            <tr style="border-bottom: 1px solid var(--color-border); transition: background 0.1s ease;" onmouseover="this.style.background='var(--color-surface-secondary)'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 0.75rem 1rem; text-align: center; color: var(--color-muted-text); font-weight: 700;">
                                    <?= $globalSn++ ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; font-weight: 600; color: var(--color-text);">
                                    <?= $e($item->itemDescription) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: var(--color-muted-text); font-size: 0.75rem;">
                                    <?= $e($item->justification ?: 'Standard specifications') ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 600;">
                                    <?= number_format((float)$item->plannedQuantity, 2) ?> <span style="font-size: 0.6875rem; color: var(--color-muted-text);"><?= $e($item->uomCode ?? '') ?></span>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums;">
                                    <?= number_format((float)$item->estimatedUnitCost, 2) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: var(--color-text); font-variant-numeric: tabular-nums;">
                                    <?= number_format((float)$item->estimatedTotalCost, 2) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                    <span style="display: inline-block; padding: 0.125rem 0.5rem; border-radius: var(--radius-sm); background: var(--color-surface-secondary); font-size: 0.6875rem; font-weight: 700;">
                                        <?= $e($item->targetQuarter instanceof \BackedEnum ? $item->targetQuarter->value : (string)$item->targetQuarter) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem; font-size: 0.75rem; color: var(--color-muted-text);">
                                    <?= $e($item->fundingSource) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: var(--color-surface-secondary); border-top: 2px solid var(--color-border); font-size: 0.9375rem;">
                        <th colspan="5" style="padding: 1rem; text-align: right; font-weight: 800; text-transform: uppercase; color: var(--color-text);">
                            Grand Total Estimated Cost:
                        </th>
                        <th style="padding: 1rem; text-align: right; font-weight: 800; color: var(--color-primary); font-size: 1.0625rem; font-variant-numeric: tabular-nums;">
                            GHS <?= number_format((float)$grandTotal, 2) ?>
                        </th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Workflow Action History Timeline -->
<div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: var(--shadow-sm);">
    <h2 style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fa-solid fa-clock-rotate-left" style="color: var(--color-primary);"></i>
        <span>Workflow & Governance Audit Trail</span>
    </h2>

    <?php if (empty($actionLogs)): ?>
        <div style="font-size: 0.8125rem; color: var(--color-muted-text); padding: 0.5rem 0;">
            No lifecycle workflow actions recorded yet.
        </div>
    <?php else: ?>
        <div style="position: relative; padding-left: 1.5rem; border-left: 2px solid var(--color-border); margin-left: 0.5rem;">
            <?php foreach ($actionLogs as $log): ?>
                <div style="margin-bottom: 1.25rem; position: relative;">
                    <div style="position: absolute; left: -1.95rem; top: 0.125rem; width: 14px; height: 14px; border-radius: 50%; background: var(--color-primary); border: 2px solid var(--color-surface);"></div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <span style="font-size: 0.8125rem; font-weight: 700; color: var(--color-text); text-transform: uppercase;">
                                <?= $e(str_replace('_', ' ', $log['action'])) ?>
                            </span>
                            <span style="font-size: 0.75rem; color: var(--color-muted-text); margin-left: 0.375rem;">
                                by <?= $e(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: ($log['username'] ?? 'User')) ?>
                            </span>
                        </div>
                        <div style="font-size: 0.6875rem; color: var(--color-muted-text);">
                            <?= date('M d, Y H:i:s', strtotime($log['action_timestamp'])) ?>
                        </div>
                    </div>
                    <?php if (!empty($log['comments'])): ?>
                        <div style="margin-top: 0.375rem; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); background: var(--color-surface-secondary); font-size: 0.75rem; color: var(--color-text); border-left: 3px solid var(--color-primary);">
                            <?= $e($log['comments']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
