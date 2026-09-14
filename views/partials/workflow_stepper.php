<?php
/**
 * Requisition Workflow Progress Stepper Partial
 * PROMIS - Procurement Management Information System
 *
 * @var string $currentStatus Requisition status (e.g., DRAFT, SUBMITTED, ENDORSED, APPROVED, COMMITTED, RECEIVED, RETURNED, REJECTED)
 */

use Promis\Src\Execution\Domain\RequisitionStatus;

$statusStr = strtoupper($currentStatus ?? 'DRAFT');
$statusEnum = RequisitionStatus::tryFrom($statusStr);

$canonicalSteps = [
    'DRAFT' => ['label' => 'Draft', 'icon' => 'fa-file-lines'],
    'SUBMITTED' => ['label' => 'Submitted', 'icon' => 'fa-paper-plane'],
    'ENDORSED' => ['label' => 'Endorsed (HOD)', 'icon' => 'fa-signature'],
    'DEPARTMENT_APPROVED' => ['label' => 'Approved (Dean)', 'icon' => 'fa-circle-check'],
    'COMMITMENT_AUTHORIZED' => ['label' => 'Committed (Finance)', 'icon' => 'fa-vault'],
    'PROCUREMENT_RECEIVED' => ['label' => 'Received (Procurement)', 'icon' => 'fa-box-check'],
];

$order = ['DRAFT', 'SUBMITTED', 'ENDORSED', 'DEPARTMENT_APPROVED', 'COMMITMENT_AUTHORIZED', 'PROCUREMENT_RECEIVED'];
$currentIndex = array_search($statusStr, $order, true);
$isNegative = in_array($statusStr, ['RETURNED', 'REJECTED'], true);
$status = $statusStr;
?>

<div class="workflow-stepper-card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
        <h3 style="font-size: 0.9375rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-route" style="color: var(--color-primary);"></i>
            Approval Lifecycle & Governance Pipeline
        </h3>
        <div>
            <span class="badge badge-<?= strtolower($status) ?>" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                Status: <?= $e($status) ?>
            </span>
        </div>
    </div>

    <?php if ($status === 'RETURNED'): ?>
        <div class="alert alert-warning" style="margin-bottom: 1rem;">
            <i class="fa-solid fa-rotate-left alert-icon"></i>
            <div>
                <strong>Requisition Returned for Modification.</strong>
                Review the feedback in the timeline below and make necessary updates before resubmitting.
            </div>
        </div>
    <?php elseif ($status === 'REJECTED'): ?>
        <div class="alert alert-danger" style="margin-bottom: 1rem;">
            <i class="fa-solid fa-ban alert-icon"></i>
            <div>
                <strong>Requisition Terminated / Rejected.</strong>
                This requisition has been formally rejected and cannot proceed further.
            </div>
        </div>
    <?php endif; ?>

    <!-- Step Pipeline with Track Line -->
    <?php 
    $pct = ($currentIndex !== false && !$isNegative) ? min(100, round(($currentIndex / (count($order) - 1)) * 100)) : 0;
    ?>
    <div class="stepper-container" style="position: relative; overflow-x: auto; padding: 0.75rem 0.25rem 0.25rem;">
        <!-- Background Track Line -->
        <div style="position: absolute; top: 26px; left: 45px; right: 45px; height: 3px; background: var(--color-border); z-index: 1;"></div>
        <?php if ($pct > 0): ?>
            <!-- Active Fill Progress Line -->
            <div style="position: absolute; top: 26px; left: 45px; width: calc((100% - 90px) * <?= $pct ?> / 100); height: 3px; background: var(--color-primary); z-index: 1; transition: width 0.3s ease;"></div>
        <?php endif; ?>

        <div class="stepper" style="display: flex; align-items: flex-start; justify-content: space-between; position: relative; z-index: 2; min-width: 560px;">
            <?php foreach ($order as $idx => $stepKey): 
                $step = $canonicalSteps[$stepKey];
                $isComplete = ($currentIndex !== false && $idx <= $currentIndex && !$isNegative);
                $isCurrent = ($currentIndex !== false && $idx === $currentIndex);
            ?>
                <div class="step-item" style="display: flex; flex-direction: column; align-items: center; text-align: center; flex: 1; min-width: 90px; position: relative;">
                    <!-- Step Circle -->
                    <div style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 700; z-index: 2; transition: all var(--transition-fast);
                        <?php if ($isCurrent): ?>
                            background: var(--color-primary); color: #ffffff; box-shadow: 0 0 0 4px rgba(140, 0, 59, 0.2);
                        <?php elseif ($isComplete): ?>
                            background: var(--color-success); color: #ffffff;
                        <?php else: ?>
                            background: var(--color-surface); color: var(--color-muted-text); border: 2px solid var(--color-border);
                        <?php endif; ?>">
                        <?php if ($isComplete && !$isCurrent): ?>
                            <i class="fa-solid fa-check"></i>
                        <?php else: ?>
                            <span><?= $idx + 1 ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Step Label -->
                    <div style="font-size: 0.75rem; font-weight: <?= $isCurrent ? '700' : '600' ?>; color: <?= $isCurrent ? 'var(--color-primary)' : ($isComplete ? 'var(--color-text)' : 'var(--color-muted-text)') ?>; margin-top: 0.5rem; line-height: 1.25;">
                        <?= $e($step['label']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
