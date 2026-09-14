<?php
/**
 * Requisition Detail, Budget Verification, and Workflow Actions View
 * PROMIS - Procurement Management Information System
 *
 * @var array $requisition
 * @var array $items
 * @var array $permittedTransitions
 * @var array $workflowHistory
 * @var array $budgetInfo
 * @var string $appUrl
 * @var callable $csrf
 * @var callable $e
 */
use Promis\Src\Execution\Domain\RequisitionStatus;

$status = (string)$requisition['status'];
$rStatusEnum = RequisitionStatus::tryFrom($status);
$badgeCls = $rStatusEnum ? $rStatusEnum->badgeClass() : strtolower($status);
$statusLabel = $rStatusEnum ? $rStatusEnum->label() : $status;
$currentStatus = $status;
$requisitionId = (int)$requisition['id'];
$actionUrl = ($appUrl ?? '') . "/requisitions/{$requisitionId}/action";
?>

<!-- Header / Context Navigation -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        <a href="<?= $e($appUrl ?? '') ?>/requisitions" class="btn btn-outline" style="padding: 0.375rem 0.75rem; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 0.375rem; border-color: var(--color-border);">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Requisitions</span>
        </a>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <h1 style="font-size: 1.25rem; font-weight: 800; color: var(--color-text); margin: 0; letter-spacing: -0.01em;">
                Requisition <?= $e($requisition['requisition_number']) ?>
            </h1>
            <?php if ($rStatusEnum): ?>
                <span class="badge badge-<?= $badgeCls ?>" style="font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.625rem; border-radius: 9999px;">
                    <?= $e($rStatusEnum->title()) ?>
                </span>
            <?php else: ?>
                <span class="badge badge-<?= $badgeCls ?>" style="font-size: 0.75rem; font-weight: 700;">
                    <?= $e($statusLabel) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: var(--color-muted-text);">
        <i class="fa-regular fa-calendar"></i>
        <span>Created: <?= $e(date('M d, Y H:i', strtotime($requisition['created_at']))) ?></span>
    </div>
</div>

<!-- Workflow Stepper Timeline Component -->
<?php require dirname(__DIR__) . '/partials/workflow_stepper.php'; ?>

<!-- Action Panel (Hick's Law: 1 Dominant Primary CTA) -->
<div class="card p-6" style="margin-bottom: 1.5rem; border-radius: var(--radius-lg); padding: 1.75rem 2rem; border: 1px solid var(--color-border); border-top: 4px solid var(--color-primary); box-shadow: var(--shadow-sm); background: var(--color-surface);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-circle-check" style="color: var(--color-primary);"></i>
                Actions You Can Take
            </h3>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0;">
                Actions you can take on this request right now based on your role
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <?php if (empty($permittedTransitions)): ?>
                <div style="font-size: 0.8125rem; color: var(--color-muted-text); background: var(--color-surface-secondary); padding: 0.5rem 0.875rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-lock" style="color: var(--color-muted-text);"></i>
                    <span>Viewing only: No action needed from you at this step.</span>
                </div>
            <?php else: ?>
                <?php foreach ($permittedTransitions as $transition): 
                    $act = $transition->action->value;
                    $isNeg = $transition->action->isNegative();
                ?>
                    <?php if ($isNeg): ?>
                        <!-- Secondary Negative Action (Return / Reject) -->
                        <button 
                            type="button" 
                            class="btn btn-outline" 
                            style="font-size: 0.8125rem; font-weight: 600; padding: 0.55rem 1rem; border-radius: var(--radius-md); color: <?= $act === 'RETURN' ? 'var(--color-warning)' : 'var(--color-danger)' ?>; border-color: <?= $act === 'RETURN' ? 'rgba(221, 153, 51, 0.5)' : 'rgba(220, 38, 38, 0.4)' ?>; background: var(--color-surface); transition: all 0.15s ease;"
                            onclick="openWorkflowDecisionModal('<?= $e($act) ?>', '<?= $e($actionUrl) ?>')"
                        >
                            <i class="fa-solid <?= $act === 'RETURN' ? 'fa-rotate-left' : 'fa-ban' ?>" style="margin-right: 0.375rem;"></i>
                            <?= $act === 'RETURN' ? 'Send Back for Changes' : 'Decline Request' ?>
                        </button>
                    <?php else: ?>
                        <!-- Dominant Primary Action (Submit, Endorse, Approve, Receive) -->
                        <form method="POST" action="<?= $e($actionUrl) ?>" style="margin: 0;" data-prevent-duplicate>
                            <?= $csrf() ?>
                            <input type="hidden" name="action" value="<?= $e($act) ?>">
                            <button 
                                type="submit" 
                                class="btn btn-primary" 
                                style="font-size: 0.875rem; font-weight: 700; min-height: 40px; padding: 0.55rem 1.25rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 0.5rem; transition: transform 0.15s ease, box-shadow 0.15s ease;"
                                data-confirm="Do you want to continue with <?= match($act) {
                                    'SUBMIT' => 'submitting this requisition for departmental endorsement',
                                    'ENDORSE' => 'signing and recommending this requisition for Dean approval',
                                    'APPROVE' => ($requisition['status'] === 'ENDORSED' ? 'approving this requisition for budget commitment' : 'authorizing budget commitment and approving this purchase'),
                                    'RECEIVE' => 'confirming official physical receipt and delivery of requested items',
                                    default => $act,
                                } ?>?"
                                data-confirm-title="Confirm <?= match($act) {
                                    'SUBMIT' => 'Requisition Submission',
                                    'ENDORSE' => 'Departmental Endorsement',
                                    'APPROVE' => ($requisition['status'] === 'ENDORSED' ? 'Deanship Approval' : 'Budget Commitment Authorization'),
                                    'RECEIVE' => 'Procurement Delivery Receipt',
                                    default => 'Governance Decision',
                                } ?>"
                                data-confirm-detail="This action will be permanently recorded in the official university audit trail."
                                data-confirm-type="<?= match($act) {
                                    'APPROVE', 'RECEIVE' => 'success',
                                    default => 'primary',
                                } ?>"
                                data-confirm-btn="<?= match($act) {
                                    'SUBMIT' => 'Submit Requisition',
                                    'ENDORSE' => 'Sign & Endorse',
                                    'APPROVE' => 'Authorize & Approve',
                                    'RECEIVE' => 'Confirm Delivery',
                                    default => 'Confirm',
                                } ?>"
                                data-confirm-icon="<?= match($act) {
                                    'SUBMIT' => 'fa-paper-plane',
                                    'ENDORSE' => 'fa-signature',
                                    'APPROVE' => 'fa-stamp',
                                    'RECEIVE' => 'fa-box-check',
                                    default => 'fa-circle-check',
                                } ?>"
                            >
                                <i class="fa-solid <?= match($act) {
                                    'SUBMIT' => 'fa-paper-plane',
                                    'ENDORSE' => 'fa-signature',
                                    'APPROVE' => 'fa-stamp',
                                    'RECEIVE' => 'fa-box-check',
                                    default => 'fa-circle-check',
                                } ?>"></i>
                                <span><?= match($act) {
                                    'SUBMIT' => 'Send for Review',
                                    'ENDORSE' => 'Sign & Recommend (HOD)',
                                    'APPROVE' => ($requisition['status'] === 'ENDORSED' ? 'Approve Request (Dean)' : 'Confirm Budget & Approve (Finance)'),
                                    'RECEIVE' => 'Confirm Items Received (Procurement)',
                                    default => $act,
                                } ?></span>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Key Highlights 4-Card Summary Strip (Miller's Law & Gestalt Proximity) -->
<div class="highlights-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
    <div class="card" style="background: var(--color-surface); padding: 1.25rem 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin: 0;">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
            Requester
        </div>
        <div style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0.35rem 0 0.15rem;">
            <?= $e($requisition['requester_name']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            <?= $e($requisition['requester_email']) ?>
        </div>
    </div>

    <div class="card" style="background: var(--color-surface); padding: 1.25rem 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin: 0;">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
            Department / Unit & Year
        </div>
        <div style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0.35rem 0 0.15rem;">
            <?= $e($requisition['entity_name']) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted-text);">
            Code: <?= $e($requisition['entity_code']) ?> • Year <?= $e((string)$requisition['fiscal_year']) ?>
        </div>
    </div>

    <div class="card" style="background: var(--color-surface); padding: 1.25rem 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin: 0;">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
            Total Estimated Cost
        </div>
        <div style="font-size: 1.25rem; font-weight: 800; color: var(--color-primary); margin: 0.35rem 0 0.15rem; font-variant-numeric: tabular-nums;">
            GHS <?= number_format((float)$requisition['total_estimated_cost'], 2) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted-text);">
            <?= count($items) ?> item line(s)
        </div>
    </div>

    <div class="card" style="background: var(--color-surface); padding: 1.25rem 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin: 0;">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.04em;">
            Budget Status
        </div>
        <div style="margin: 0.35rem 0 0.15rem;">
            <span class="badge badge-<?= $budgetInfo['is_available'] ? 'success' : 'danger' ?>" style="font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.625rem; border-radius: 9999px;">
                <?= $budgetInfo['is_available'] ? '● Money Available' : '⚠ Budget Low' ?>
            </span>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-muted-text); font-variant-numeric: tabular-nums;">
            Available: GHS <?= number_format((float)$budgetInfo['available_balance'], 2) ?>
        </div>
    </div>
</div>

<!-- Requisition Summary & Institutional Budget Verification -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Requisition Overview Card -->
    <div class="card p-6" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.75rem 2rem; box-shadow: var(--shadow-sm); margin: 0;">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0 0 1rem 0; border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem;">
            Request Overview
        </h3>
        <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.75rem; font-size: 0.875rem;">
            <div style="color: var(--color-muted-text); font-weight: 500;">Request Number:</div>
            <div style="font-weight: 700; color: var(--color-primary);"><?= $e($requisition['requisition_number']) ?></div>

            <div style="color: var(--color-muted-text); font-weight: 500;">Department/Unit:</div>
            <div style="font-weight: 600; color: var(--color-text);"><?= $e($requisition['entity_name']) ?> (<?= $e($requisition['entity_code']) ?>)</div>

            <div style="color: var(--color-muted-text); font-weight: 500;">Year:</div>
            <div><?= $e((string)$requisition['fiscal_year']) ?></div>

            <div style="color: var(--color-muted-text); font-weight: 500;">Requester:</div>
            <div><?= $e($requisition['requester_name']) ?> (<?= $e($requisition['requester_email']) ?>)</div>

            <div style="color: var(--color-muted-text); font-weight: 500;">Estimated Total:</div>
            <div style="font-weight: 800; color: var(--color-text); font-size: 1.125rem;">
                GHS <?= number_format((float)$requisition['total_estimated_cost'], 2) ?>
            </div>

            <div style="color: var(--color-muted-text); font-weight: 500;">Reason / Purpose:</div>
            <div style="color: var(--color-text); line-height: 1.4; background: var(--color-surface-secondary); padding: 0.5rem; border-radius: var(--radius-sm);">
                <?= nl2br($e($requisition['justification'])) ?>
            </div>
        </div>
    </div>

    <!-- Institutional Budget Card -->
    <div class="card p-6" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.75rem 2rem; box-shadow: var(--shadow-sm); margin: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem; margin-bottom: 1rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-vault" style="color: var(--color-forest-green);"></i>
                Department Budget Check
            </h3>
            <span class="badge badge-<?= $budgetInfo['is_available'] ? 'success' : 'danger' ?>" style="font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.625rem; border-radius: 9999px;">
                <?= $budgetInfo['is_available'] ? '● Money Available' : '⚠ Budget Low' ?>
            </span>
        </div>

        <div style="display: grid; grid-template-columns: 150px 1fr; gap: 0.625rem; font-size: 0.8125rem;">
            <div style="color: var(--color-muted-text);">Annual Budget:</div>
            <div style="font-weight: 600; color: var(--color-text);">
                GHS <?= number_format((float)$budgetInfo['allocated_amount'], 2) ?>
            </div>

            <div style="color: var(--color-muted-text);">Money Already Spent/Reserved:</div>
            <div style="font-weight: 600; color: var(--color-primary);">
                GHS <?= number_format((float)$budgetInfo['committed_amount'], 2) ?>
            </div>

            <div style="color: var(--color-muted-text);">Remaining Balance:</div>
            <div style="font-weight: 700; color: <?= $budgetInfo['is_available'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                GHS <?= number_format((float)$budgetInfo['available_balance'], 2) ?>
            </div>

            <div style="color: var(--color-muted-text);">This Request Amount:</div>
            <div style="font-weight: 700; color: var(--color-text);">
                GHS <?= number_format((float)$budgetInfo['requisition_amount'], 2) ?>
            </div>

            <div style="color: var(--color-muted-text);">Funding Source:</div>
            <div><?= $e($budgetInfo['funding_source']) ?></div>
        </div>

        <div style="margin-top: 1rem; padding: 0.625rem; background: var(--color-surface-secondary); border-radius: var(--radius-sm); font-size: 0.75rem; color: var(--color-muted-text); line-height: 1.35;">
            <strong>Helpful Note:</strong> We check your department budget so requests are kept within university funding limits.
        </div>
    </div>
</div>

<!-- Itemized Line Items Table -->
<div class="card p-6" style="margin-bottom: 1.5rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.75rem 2rem; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0;">
            Requested Items (<?= count($items) ?>)
        </h3>
        <div style="font-weight: 700; font-size: 0.9375rem; color: var(--color-primary);">
            Grand Total: GHS <?= number_format((float)$requisition['total_estimated_cost'], 2) ?>
        </div>
    </div>

    <div class="table-container" style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
            <thead>
                <tr style="border-bottom: 2px solid var(--color-border); text-align: left;">
                    <th style="padding: 0.625rem; color: var(--color-muted-text);">#</th>
                    <th style="padding: 0.625rem; color: var(--color-muted-text);">Item Code</th>
                    <th style="padding: 0.625rem; color: var(--color-muted-text);">Item Description & Specifications</th>
                    <th style="padding: 0.625rem; color: var(--color-muted-text);">Unit</th>
                    <th style="padding: 0.625rem; color: var(--color-muted-text); text-align: right;">Quantity</th>
                    <th style="padding: 0.625rem; color: var(--color-muted-text); text-align: right;">Estimated Price (GHS)</th>
                    <th style="padding: 0.625rem; color: var(--color-muted-text); text-align: right;">Total (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item): ?>
                    <tr style="border-bottom: 1px solid var(--color-border);">
                        <td style="padding: 0.625rem; color: var(--color-muted-text);"><?= $idx + 1 ?></td>
                        <td style="padding: 0.625rem; font-weight: 600; color: var(--color-primary);">
                            <?= $e($item['item_code']) ?>
                        </td>
                        <td style="padding: 0.625rem;">
                            <div style="font-weight: 600; color: var(--color-text);"><?= $e($item['item_description']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--color-muted-text);"><?= $e($item['standard_name']) ?></div>
                            <?php if (!empty($item['item_justification'])): ?>
                                <div style="font-size: 0.75rem; color: var(--color-muted-text); font-style: italic; margin-top: 0.25rem;">
                                    "<?= $e($item['item_justification']) ?>"
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.625rem; color: var(--color-text);"><?= $e($item['uom_name']) ?></td>
                        <td style="padding: 0.625rem; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums;"><?= number_format((float)$item['requested_quantity'], 2) ?></td>
                        <td style="padding: 0.625rem; text-align: right; font-variant-numeric: tabular-nums;"><?= number_format((float)$item['estimated_unit_cost'], 2) ?></td>
                        <td style="padding: 0.625rem; text-align: right; font-weight: 700; color: var(--color-text); font-variant-numeric: tabular-nums;">
                            <?= number_format((float)$item['estimated_total_cost'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Read-Only Chronological Workflow Action History -->
<div class="card p-6" style="margin-bottom: 1.5rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.75rem 2rem; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-timeline" style="color: var(--color-primary);"></i>
            Approval History & Comments
        </h3>
        <span class="badge badge-info" style="font-size: 0.6875rem;">
            Permanent Audit Record
        </span>
    </div>

    <?php if (empty($workflowHistory)): ?>
        <div style="padding: 1.5rem; text-align: center; color: var(--color-muted-text); font-size: 0.8125rem;">
            No actions have been recorded for this request yet.
        </div>
    <?php else: ?>
        <div class="table-container" style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--color-border); text-align: left;">
                        <th style="padding: 0.625rem; color: var(--color-muted-text);">Date & Time</th>
                        <th style="padding: 0.625rem; color: var(--color-muted-text);">Action</th>
                        <th style="padding: 0.625rem; color: var(--color-muted-text);">Status Change</th>
                        <th style="padding: 0.625rem; color: var(--color-muted-text);">Officer</th>
                        <th style="padding: 0.625rem; color: var(--color-muted-text);">Notes / Comments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workflowHistory as $log): ?>
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 0.625rem; font-family: monospace; color: var(--color-muted-text);">
                                <?= $e($log->actionTimestamp) ?>
                            </td>
                            <td style="padding: 0.625rem; font-weight: 600;">
                                <span class="badge badge-<?= in_array($log->action, ['RETURN', 'REJECT']) ? 'danger' : 'success' ?>" style="font-size: 0.6875rem;">
                                    <?= $e($log->action) ?>
                                </span>
                            </td>
                            <td style="padding: 0.625rem; color: var(--color-text);">
                                <span style="color: var(--color-muted-text);"><?= $e($log->preStatus) ?></span>
                                <i class="fa-solid fa-arrow-right" style="font-size: 0.6875rem; margin: 0 0.25rem; color: var(--color-muted-text);"></i>
                                <span style="font-weight: 600;"><?= $e($log->postStatus) ?></span>
                            </td>
                            <td style="padding: 0.625rem; font-weight: 500;">
                                <?= $e($log->actorName ?? "User #{$log->actorUserId}") ?>
                            </td>
                            <td style="padding: 0.625rem; color: var(--color-text);">
                                <?= !empty($log->comments) ? nl2br($e($log->comments)) : '<span style="color: var(--color-muted-text); font-style: italic;">No notes recorded</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Institutional Governance & Helpdesk Assurance Strip -->
<div class="card" style="background: linear-gradient(135deg, #ffffff 0%, var(--color-surface-secondary) 100%); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.75rem 2rem; box-shadow: var(--shadow-sm); margin-top: 2rem; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div style="max-width: 720px;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span class="badge badge-success" style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 9999px; padding: 0.2rem 0.625rem;">
                    <i class="fa-solid fa-shield-check" style="margin-right: 0.25rem;"></i> Official Record
                </span>
                <span style="font-size: 0.75rem; color: var(--color-muted-text);">•</span>
                <span style="font-size: 0.75rem; color: var(--color-muted-text); font-weight: 600;">University Procurement Process</span>
            </div>
            <h4 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.375rem 0;">
                Official Decision Record: <?= $e($requisition['requisition_number']) ?>
            </h4>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0; line-height: 1.5;">
                Every signature, budget approval, and procurement step for this request is saved permanently with official timestamps.
            </p>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="mailto:procurement@usted.edu.gh" class="btn btn-outline" style="font-size: 0.8125rem; padding: 0.5rem 0.875rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem;" title="Contact Procurement Helpdesk">
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

<!-- Reusable Return / Reject Action Modal -->
<?php require dirname(__DIR__) . '/partials/return_reject_modal.php'; ?>
