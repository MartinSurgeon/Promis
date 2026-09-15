<?php
/**
 * Requisition Creation Form View
 * PROMIS - Procurement Management Information System
 * University of Skills Training and Entrepreneurial Development (USTED)
 *
 * @var array $authorizedEntities
 * @var array $selectedEntity
 * @var int $fiscalYear
 * @var array|null $planData
 * @var array $planItems
 * @var array $budgetInfo
 * @var string $appUrl
 * @var callable $csrf
 * @var callable $csrfToken
 * @var callable $e
 * @var callable $old
 * @var array $user
 */
?>

<div class="requisition-create-container" style="max-width: 1100px; margin: 0 auto;">
    <!-- Page Header & Back Navigation -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.875rem;">
            <a href="<?= $e($appUrl) ?>/requisitions" class="btn btn-outline" style="min-height: 40px; padding: 0.45rem 0.875rem; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 0.375rem; border-color: var(--color-border); background: var(--color-surface); text-decoration: none; border-radius: var(--radius-md);">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Requests</span>
            </a>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); margin: 0; letter-spacing: -0.015em; line-height: 1.2;">
                    New Purchase Request
                </h1>
                <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0.2rem 0 0; line-height: 1.4;">
                    Select items from your department's approved annual procurement plan.
                </p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--color-surface); border: 1px solid var(--color-border); padding: 0.4rem 0.875rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text);">
            <i class="fa-regular fa-calendar-check" style="color: var(--color-primary);"></i>
            <span>Budget Year <?= $e((string)$fiscalYear) ?></span>
        </div>
    </div>

    <!-- Entity & Fiscal Year Filter Form (Switches Plan Context) -->
    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem 1.75rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);">
        <form method="GET" action="<?= $e($appUrl) ?>/requisitions/create" id="entityFilterForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; align-items: flex-end;">
            <div>
                <label for="entitySelect" style="display: block; font-size: 0.8125rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.375rem;">
                    <i class="fa-solid fa-building" style="color: var(--color-primary); margin-right: 0.35rem;"></i>
                    Department <span style="color: var(--color-danger);">*</span>
                </label>
                <select name="entity_id" id="entitySelect" class="form-control" onchange="document.getElementById('entityFilterForm').submit();" style="width: 100%; height: 42px; padding: 0 0.875rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); cursor: pointer;">
                    <?php foreach ($authorizedEntities as $ent): ?>
                        <option value="<?= (int)$ent['id'] ?>" <?= (int)$ent['id'] === (int)$selectedEntity['id'] ? 'selected' : '' ?>>
                            <?= $e($ent['entity_name']) ?> (<?= $e($ent['entity_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size: 0.75rem; color: var(--color-muted-text); margin-top: 0.3rem;">
                    You can make purchase requests for this department.
                </div>
            </div>

            <div>
                <label for="yearSelect" style="display: block; font-size: 0.8125rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.375rem;">
                    <i class="fa-solid fa-calendar" style="color: var(--color-primary); margin-right: 0.35rem;"></i>
                    Budget Year
                </label>
                <select name="fiscal_year" id="yearSelect" class="form-control" onchange="document.getElementById('entityFilterForm').submit();" style="width: 100%; height: 42px; padding: 0 0.875rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); cursor: pointer;">
                    <?php 
                    $currY = (int)date('Y');
                    for ($y = $currY + 1; $y >= $currY - 2; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $fiscalYear ? 'selected' : '' ?>>
                            Year <?= $y ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Approved Procurement Plan Assurance Banner -->
    <?php if ($planData): ?>
        <div class="card" style="background: rgba(0, 105, 56, 0.05); border: 1px solid rgba(0, 105, 56, 0.2); border-left: 4px solid var(--color-success); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-success); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.125rem; flex-shrink: 0;">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-success); text-transform: uppercase; letter-spacing: 0.04em;">
                            Approved Procurement Plan
                        </div>
                        <div style="font-size: 1.0625rem; font-weight: 800; color: var(--color-text); margin: 0.1rem 0;">
                            Plan #<?= $e($planData['plan_number']) ?> (Version <?= $e($planData['version_number']) ?>)
                        </div>
                        <div style="font-size: 0.75rem; color: var(--color-muted-text);">
                            Approved Budget Limit: GHS <?= number_format((float)($planData['version_total_cost'] ?? 0), 2) ?>
                            <?php if (!empty($planData['approval_date'])): ?>
                                • Approved on <?= $e(date('M d, Y', strtotime($planData['approval_date']))) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div>
                    <span class="badge badge-success" style="font-size: 0.75rem; font-weight: 700; padding: 0.3rem 0.75rem; border-radius: 9999px;">
                        <i class="fa-solid fa-shield-check" style="margin-right: 0.3rem;"></i> Plan Approved & Active
                    </span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card" style="background: rgba(220, 38, 38, 0.05); border: 1px solid rgba(220, 38, 38, 0.2); border-left: 4px solid var(--color-danger); border-radius: var(--radius-lg); padding: 1.75rem 2rem; margin-bottom: 1.5rem; text-align: center;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: rgba(220, 38, 38, 0.1); color: var(--color-danger); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 0.75rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.35rem;">
                No Approved Procurement Plan for this Department in Year <?= $e((string)$fiscalYear) ?>
            </h3>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); max-width: 600px; margin: 0 auto; line-height: 1.5;">
                Purchase requests in PROMIS must be based on an approved annual procurement plan. Please ensure an annual plan is created and approved, or choose another department.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($planData && !empty($planItems)): ?>
        <!-- Main Creation Form -->
        <form method="POST" action="<?= $e($appUrl) ?>/requisitions" id="createRequisitionForm" data-prevent-duplicate>
            <?= $csrf() ?>
            <input type="hidden" name="planning_entity_id" value="<?= (int)$selectedEntity['id'] ?>">
            <input type="hidden" name="fiscal_year" value="<?= (int)$fiscalYear ?>">

            <!-- Section 1: Requisition Justification -->
            <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.75rem 2rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);">
                <h3 style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.35rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: rgba(140, 0, 59, 0.1); color: var(--color-primary); font-size: 0.8125rem; font-weight: 800;">1</span>
                    Reason for Request <span style="color: var(--color-danger);">*</span>
                </h3>
                <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0 0 1rem; line-height: 1.4;">
                    Explain in simple, clear words why your department needs these items (e.g., "Required for 2nd semester student practicals in the computing laboratory").
                </p>

                <textarea 
                    name="justification" 
                    id="justificationInput" 
                    rows="3" 
                    class="form-control" 
                    required 
                    placeholder="Explain why your department needs these items..." 
                    style="width: 100%; padding: 0.75rem 1rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); line-height: 1.5; resize: vertical;"
                ><?= $e($old('justification', '')) ?></textarea>
            </div>

            <!-- Section 2: Select Items from Approved Plan -->
            <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.75rem 2rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); overflow: hidden;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                    <div>
                        <h3 style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.35rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: rgba(140, 0, 59, 0.1); color: var(--color-primary); font-size: 0.8125rem; font-weight: 800;">2</span>
                            Approved Items You Can Request
                        </h3>
                        <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0;">
                            Enter the quantity you need for each item. You cannot request more than the remaining budget quota.
                        </p>
                    </div>

                    <div style="background: var(--color-surface-secondary); padding: 0.5rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); font-size: 0.8125rem; font-weight: 700; color: var(--color-text); text-align: right;">
                        <span style="color: var(--color-muted-text); font-weight: 500; font-size: 0.75rem; display: block;">Total Estimated Cost</span>
                        <span style="font-size: 1.25rem; font-weight: 800; color: var(--color-primary); font-variant-numeric: tabular-nums;" id="grandTotalDisplay">GHS 0.00</span>
                    </div>
                </div>

                <!-- Responsive Plan Items Table -->
                <div class="table-responsive" style="overflow-x: auto; margin: 0 -2rem -1.75rem; border-top: 1px solid var(--color-border-subtle);">
                    <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.8125rem;">
                        <thead style="background: var(--color-surface-secondary); color: var(--color-text); font-weight: 700;">
                            <tr>
                                <th style="padding: 1rem 1.25rem; width: 45px; text-align: center;">#</th>
                                <th style="padding: 1rem 1.25rem;">Item Description & Details</th>
                                <th style="padding: 1rem 1.25rem; text-align: right;">Unit Price (GHS)</th>
                                <th style="padding: 1rem 1.25rem; text-align: center;">Quota Status</th>
                                <th style="padding: 1rem 1.25rem; width: 150px; text-align: right;">How many do you need?</th>
                                <th style="padding: 1rem 1.25rem; text-align: right;">Item Total (GHS)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($planItems as $idx => $item): 
                                $isAvail = $item['is_available'];
                                $planItemId = (int)$item['plan_item_id'];
                                $unitCost = (float)$item['estimated_unit_cost'];
                                $remQty = (float)$item['remaining_quantity'];
                            ?>
                                <tr style="border-bottom: 1px solid var(--color-border-subtle); background: <?= $isAvail ? 'transparent' : 'rgba(241, 245, 249, 0.6)' ?>;">
                                    <td style="padding: 1rem 1.25rem; text-align: center; color: var(--color-muted-text); font-weight: 600;">
                                        <?= $idx + 1 ?>
                                    </td>
                                    <td style="padding: 1rem 1.25rem;">
                                        <div style="font-weight: 700; color: var(--color-text); font-size: 0.875rem;">
                                            <?= $e($item['item_description']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--color-muted-text); margin-top: 0.2rem;">
                                            <?php if (!empty($item['category_name'])): ?>
                                                Category: <strong style="color: var(--color-text);"><?= $e($item['category_name']) ?></strong> • 
                                            <?php endif; ?>
                                            Unit: <strong><?= $e($item['uom_name'] ?? $item['uom_code'] ?? 'Units') ?></strong> • 
                                            Quarter: <strong><?= $e($item['target_quarter']) ?></strong>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem 1.25rem; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums;">
                                        <?= number_format($unitCost, 2) ?>
                                    </td>
                                    <td style="padding: 1rem 1.25rem; text-align: center;">
                                        <div style="display: inline-flex; flex-direction: column; align-items: center; gap: 0.2rem;">
                                            <span class="badge badge-<?= $isAvail ? 'success' : 'danger' ?>" style="font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                                <?= $isAvail ? number_format($remQty, 2) . ' ' . $e($item['uom_code'] ?? 'Units') . ' Left' : '0.00 Left (Depleted)' ?>
                                            </span>
                                            <span style="font-size: 0.6875rem; color: var(--color-muted-text);">
                                                Planned: <?= number_format((float)$item['planned_quantity'], 2) ?> | Used: <?= number_format((float)$item['used_quantity'], 2) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem 1.25rem; text-align: right;">
                                        <input type="hidden" name="items[<?= $idx ?>][procurement_plan_item_id]" value="<?= $planItemId ?>">
                                        <input 
                                             type="number" 
                                            step="0.01" 
                                            min="0" 
                                            max="<?= $remQty ?>" 
                                            name="items[<?= $idx ?>][requested_quantity]" 
                                            class="form-control item-qty-input" 
                                            data-unit-cost="<?= $unitCost ?>" 
                                            data-max-qty="<?= $remQty ?>"
                                            data-row-idx="<?= $idx ?>"
                                            placeholder="0.00"
                                            <?= !$isAvail ? 'disabled' : '' ?>
                                            style="width: 100%; height: 38px; text-align: right; padding: 0 0.625rem; font-weight: 700; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: <?= $isAvail ? 'var(--color-surface)' : 'rgba(241, 245, 249, 0.8)' ?>;"
                                            oninput="calculateTotals();"
                                        >
                                    </td>
                                    <td style="padding: 1rem 1.25rem; text-align: right; font-weight: 800; color: var(--color-primary); font-variant-numeric: tabular-nums;" id="rowTotal_<?= $idx ?>">
                                        0.00
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 3: Action Submission Buttons -->
            <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem 2rem; margin-bottom: 2rem; box-shadow: var(--shadow-sm);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="font-size: 0.875rem; font-weight: 700; color: var(--color-text);">
                            Ready to send your request?
                        </div>
                        <div style="font-size: 0.75rem; color: var(--color-muted-text); margin-top: 0.15rem;">
                            Sending this request starts the approval process (Department &rarr; Faculty &rarr; Finance &rarr; Purchasing).
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <a href="<?= $e($appUrl) ?>/requisitions" class="btn btn-outline" style="min-height: 44px; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 600; text-decoration: none; border-radius: var(--radius-md); border-color: var(--color-border); background: var(--color-surface);">
                            Cancel
                        </a>

                        <button 
                            type="submit" 
                            name="submit_now" 
                            value="0" 
                            class="btn" 
                            style="min-height: 44px; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 700; border-radius: var(--radius-md); background: var(--color-surface-secondary); color: var(--color-text); border: 1px solid var(--color-border); cursor: pointer; transition: background 0.15s ease;"
                            onmouseover="this.style.background='var(--color-border)'"
                            onmouseout="this.style.background='var(--color-surface-secondary)'"
                        >
                            <i class="fa-regular fa-floppy-disk" style="margin-right: 0.35rem;"></i>
                            Save as Draft
                        </button>

                        <button 
                            type="submit" 
                            name="submit_now" 
                            value="1" 
                            class="btn btn-primary" 
                            id="submitRequisitionBtn"
                            style="min-height: 44px; padding: 0.625rem 1.5rem; font-size: 0.9375rem; font-weight: 700; border-radius: var(--radius-md); background: var(--color-success); color: #ffffff; border: none; box-shadow: 0 2px 4px rgba(0, 105, 56, 0.2); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: transform 0.15s ease, box-shadow 0.15s ease;"
                            onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(0, 105, 56, 0.3)'"
                            onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 4px rgba(0, 105, 56, 0.2)'"
                        >
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Send for Approval</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Dynamic Calculation Script -->
        <script>
            function calculateTotals() {
                var inputs = document.querySelectorAll('.item-qty-input');
                var grandTotal = 0.0;
                var hasSelectedItems = false;

                inputs.forEach(function(input) {
                    var qty = parseFloat(input.value) || 0.0;
                    var maxQty = parseFloat(input.dataset.maxQty) || 0.0;
                    var unitCost = parseFloat(input.dataset.unitCost) || 0.0;
                    var rowIdx = input.dataset.rowIdx;
                    var rowTotalEl = document.getElementById('rowTotal_' + rowIdx);

                    // Visual quota alert
                    if (qty > maxQty) {
                        input.style.borderColor = 'var(--color-danger)';
                        input.style.backgroundColor = 'rgba(220, 38, 38, 0.05)';
                    } else if (qty > 0) {
                        input.style.borderColor = 'var(--color-success)';
                        input.style.backgroundColor = 'rgba(0, 105, 56, 0.05)';
                    } else {
                        input.style.borderColor = 'var(--color-border)';
                        input.style.backgroundColor = 'var(--color-surface)';
                    }

                    var lineTotal = qty * unitCost;
                    if (rowTotalEl) {
                        rowTotalEl.textContent = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }

                    if (qty > 0) {
                        grandTotal += lineTotal;
                        hasSelectedItems = true;
                    }
                });

                var grandTotalEl = document.getElementById('grandTotalDisplay');
                if (grandTotalEl) {
                    grandTotalEl.textContent = 'GHS ' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                calculateTotals();
            });
        </script>
    <?php endif; ?>
</div>
