<?php
/**
 * Procurement Plan Edit Form View
 * PROMIS - Procurement Management Information System
 * Allows editing draft or returned procurement plans and their items
 *
 * @var array $plan
 * @var array $items
 * @var array $categories
 * @var array $uoms
 * @var array $standardItems
 * @var string $appUrl
 * @var callable $e
 * @var callable $csrf
 */

$planId = (int)$plan['id'];
$status = strtoupper((string)$plan['status']);
?>

<!-- Breadcrumb Navigation -->
<div style="font-size: 0.8125rem; color: var(--color-muted-text); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
    <a href="<?= $e($appUrl ?? '') ?>/procurement-plans" style="color: var(--color-muted-text); text-decoration: none;">Procurement Plans</a>
    <i class="fa-solid fa-chevron-right" style="font-size: 0.6875rem;"></i>
    <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>" style="color: var(--color-muted-text); text-decoration: none;"><?= $e($plan['plan_number']) ?></a>
    <i class="fa-solid fa-chevron-right" style="font-size: 0.6875rem;"></i>
    <span style="color: var(--color-text); font-weight: 600;">Edit Plan</span>
</div>

<div class="page-header" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); margin: 0; letter-spacing: -0.02em;">
            Edit Procurement Plan: <?= $e($plan['plan_number']) ?>
        </h1>
        <span class="badge <?= $status === 'RETURNED' ? 'badge-accent' : 'badge-info' ?>" style="font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.625rem; text-transform: uppercase;">
            <?= $e($status) ?>
        </span>
    </div>
    <p style="font-size: 0.875rem; color: var(--color-muted-text); margin: 0.375rem 0 0;">
        <?= $status === 'RETURNED' ? 'Update your procurement plan according to the reviewer feedback and resubmit for approval.' : 'Modify draft items, quantities, or estimated unit costs before submitting for review.' ?>
    </p>
</div>

<form id="editPlanForm" method="POST" action="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>/edit" style="margin: 0;">
    <?= $csrf() ?>

    <!-- Plan Header Card -->
    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);">
        <h2 style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-building-columns" style="color: var(--color-primary);"></i>
            <span>Plan Header Details</span>
        </h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label for="entity_name_display" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-muted-text); margin-bottom: 0.375rem;">
                    Planning Entity
                </label>
                <input type="text" id="entity_name_display" name="entity_name_display" readonly value="<?= $e($plan['entity_name']) ?> (<?= $e($plan['entity_code']) ?>)"
                       style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-surface-secondary); color: var(--color-text); font-weight: 600;">
            </div>

            <div>
                <label for="fiscal_year_display" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-muted-text); margin-bottom: 0.375rem;">
                    Financial Year
                </label>
                <input type="text" id="fiscal_year_display" name="fiscal_year_display" readonly value="FY <?= $e($plan['fiscal_year']) ?>"
                       style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-surface-secondary); color: var(--color-text); font-weight: 600;">
            </div>

            <div>
                <label for="version_number_display" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-muted-text); margin-bottom: 0.375rem;">
                    Current Active Version
                </label>
                <input type="text" id="version_number_display" name="version_number_display" readonly value="Version <?= $e($plan['version_number'] ?? '1.0') ?>"
                       style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; background: var(--color-surface-secondary); color: var(--color-text); font-weight: 600;">
            </div>
        </div>
    </div>

    <!-- Items Table Section -->
    <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.0625rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-list-check" style="color: var(--color-primary);"></i>
                    <span>Line Items Forecast</span>
                </h2>
                <div style="font-size: 0.8125rem; color: var(--color-muted-text); margin-top: 0.25rem;">
                    Add, remove, or modify planned line items, specifications, and costs.
                </div>
            </div>

            <button type="button" id="addItemBtn" class="btn btn-secondary" style="font-weight: 600; font-size: 0.8125rem; padding: 0.5rem 0.875rem;">
                <i class="fa-solid fa-plus"></i> Add Item Line
            </button>
        </div>

        <div style="overflow-x: auto; margin-bottom: 1.25rem;">
            <table id="itemsTable" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; text-align: left; min-width: 950px;">
                <thead>
                    <tr style="background: var(--color-surface-secondary); border-bottom: 1px solid var(--color-border); color: var(--color-muted-text); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 0.75rem 0.5rem; width: 40px; text-align: center;">#</th>
                        <th style="padding: 0.75rem 0.5rem; width: 170px;">Category / Package</th>
                        <th style="padding: 0.75rem 0.5rem; min-width: 220px;">Item Description & Specifications</th>
                        <th style="padding: 0.75rem 0.5rem; width: 110px;">UOM</th>
                        <th style="padding: 0.75rem 0.5rem; width: 90px; text-align: right;">Qty</th>
                        <th style="padding: 0.75rem 0.5rem; width: 120px; text-align: right;">Est. Unit Cost</th>
                        <th style="padding: 0.75rem 0.5rem; width: 130px; text-align: right;">Total Cost (GHS)</th>
                        <th style="padding: 0.75rem 0.5rem; width: 90px;">Quarter</th>
                        <th style="padding: 0.75rem 0.5rem; width: 130px;">Funding Source</th>
                        <th style="padding: 0.75rem 0.5rem; width: 50px; text-align: center;"></th>
                    </tr>
                </thead>
                <tbody id="itemsTbody">
                    <!-- Populated from existing items -->
                </tbody>
            </table>
        </div>

        <!-- Grand Total Summary Strip -->
        <div style="background: var(--color-surface-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span style="font-size: 0.75rem; color: var(--color-muted-text); text-transform: uppercase; font-weight: 700;">Total Planned Items:</span>
                <span id="totalItemsCount" style="font-size: 1rem; font-weight: 800; color: var(--color-text); margin-left: 0.375rem;">0</span>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 0.875rem; font-weight: 700; color: var(--color-muted-text); text-transform: uppercase;">
                    Total Estimated Plan Cost:
                </span>
                <span id="grandTotalDisplay" style="font-size: 1.375rem; font-weight: 800; color: var(--color-primary); font-variant-numeric: tabular-nums;">
                    GHS 0.00
                </span>
            </div>
        </div>
    </div>

    <!-- Actions Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= $planId ?>" class="btn btn-secondary" style="font-weight: 600;">
            <i class="fa-solid fa-arrow-left"></i> Cancel & Return to Plan
        </a>

        <div style="display: flex; gap: 0.75rem;">
            <button type="submit" name="action" value="save" class="btn btn-secondary" style="font-weight: 700; padding: 0.625rem 1.25rem;">
                <i class="fa-solid fa-floppy-disk"></i> Save Modifications
            </button>

            <button type="submit" name="action" value="submit" class="btn btn-primary" style="font-weight: 700; padding: 0.625rem 1.5rem;"
                    data-confirm="Submit this updated Procurement Plan for institutional approval?"
                    data-confirm-title="Submit Updated Procurement Plan"
                    data-confirm-detail="Editing will be locked during the formal governance review."
                    data-confirm-type="primary"
                    data-confirm-btn="Submit Plan"
                    data-confirm-icon="fa-paper-plane">
                <i class="fa-solid fa-paper-plane"></i> Save & Submit for Approval
            </button>
        </div>
    </div>
</form>

<script>
const CATEGORIES_DATA = <?= json_encode($categories) ?>;
const UOMS_DATA = <?= json_encode($uoms) ?>;

// Existing items serialized
const EXISTING_ITEMS = <?= json_encode(array_map(function($it) {
    return [
        'category_id' => $it->categoryId,
        'item_description' => $it->itemDescription,
        'justification' => $it->justification ?? '',
        'uom_id' => $it->uomId,
        'planned_quantity' => $it->plannedQuantity,
        'estimated_unit_cost' => $it->estimatedUnitCost,
        'target_quarter' => $it->targetQuarter instanceof \BackedEnum ? $it->targetQuarter->value : (string)$it->targetQuarter,
        'funding_source' => $it->fundingSource,
    ];
}, $items)) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('itemsTbody');
    const addItemBtn = document.getElementById('addItemBtn');

    if (EXISTING_ITEMS && EXISTING_ITEMS.length > 0) {
        EXISTING_ITEMS.forEach(it => addItemRow(it));
    } else {
        addItemRow();
    }

    recalculateGrandTotals();

    addItemBtn.addEventListener('click', function() {
        addItemRow();
        recalculateGrandTotals();
    });
});

function addItemRow(data = {}) {
    const tbody = document.getElementById('itemsTbody');
    const rowIdx = tbody.children.length;

    const categoryId = data.category_id || (CATEGORIES_DATA[0] ? CATEGORIES_DATA[0].id : 1);
    const itemDesc = data.item_description || '';
    const justification = data.justification || '';
    const uomId = data.uom_id || (UOMS_DATA[0] ? UOMS_DATA[0].id : 1);
    const qty = data.planned_quantity || '1.00';
    const unitCost = data.estimated_unit_cost || '0.00';
    const quarter = data.target_quarter || 'Q1';
    const funding = data.funding_source || 'GoG Consolidated Fund';

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--color-border)';
    tr.className = 'plan-item-row';

    let catOptions = '';
    CATEGORIES_DATA.forEach(c => {
        catOptions += `<option value="${c.id}" ${c.id == categoryId ? 'selected' : ''}>${escapeHtml(c.category_name)}</option>`;
    });

    let uomOptions = '';
    UOMS_DATA.forEach(u => {
        uomOptions += `<option value="${u.id}" ${u.id == uomId ? 'selected' : ''}>${escapeHtml(u.uom_name || u.uom_code)}</option>`;
    });

    tr.innerHTML = `
        <td style="padding: 0.5rem; text-align: center; color: var(--color-muted-text); font-weight: 700;" class="row-sn">
            ${rowIdx + 1}
        </td>
        <td style="padding: 0.5rem;">
            <select name="items[category_id][]" aria-label="Category or Package" required class="form-control" style="width: 100%; padding: 0.375rem 0.5rem; font-size: 0.8125rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-background); color: var(--color-text);">
                ${catOptions}
            </select>
        </td>
        <td style="padding: 0.5rem;">
            <input type="text" name="items[item_description][]" aria-label="Item Description" value="${escapeHtml(itemDesc)}" required placeholder="Item name / title..." 
                   class="form-control" style="width: 100%; padding: 0.375rem 0.5rem; font-size: 0.8125rem; font-weight: 600; border: 1px solid var(--color-border); border-radius: var(--radius-sm); margin-bottom: 0.25rem; background: var(--color-background); color: var(--color-text);">
            <textarea name="items[justification][]" aria-label="Specifications and Justification" placeholder="Detailed technical specifications / justification..." rows="1"
                      style="width: 100%; padding: 0.25rem 0.5rem; font-size: 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-background); color: var(--color-muted-text); resize: vertical;">${escapeHtml(justification)}</textarea>
        </td>
        <td style="padding: 0.5rem;">
            <select name="items[uom_id][]" aria-label="Unit of Measure" required class="form-control" style="width: 100%; padding: 0.375rem 0.5rem; font-size: 0.8125rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-background); color: var(--color-text);">
                ${uomOptions}
            </select>
        </td>
        <td style="padding: 0.5rem; text-align: right;">
            <input type="number" step="0.01" min="0.01" name="items[planned_quantity][]" aria-label="Planned Quantity" value="${escapeHtml(qty)}" required
                   class="item-qty form-control" style="width: 100%; padding: 0.375rem 0.5rem; font-size: 0.8125rem; text-align: right; font-weight: 600; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-background); color: var(--color-text);">
        </td>
        <td style="padding: 0.5rem; text-align: right;">
            <input type="number" step="0.01" min="0.01" name="items[estimated_unit_cost][]" aria-label="Estimated Unit Cost (GHS)" value="${escapeHtml(unitCost)}" required
                   class="item-cost form-control" style="width: 100%; padding: 0.375rem 0.5rem; font-size: 0.8125rem; text-align: right; font-weight: 600; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-background); color: var(--color-text);">
        </td>
        <td style="padding: 0.5rem; text-align: right;">
            <span class="line-total-display" style="font-weight: 700; color: var(--color-text); font-variant-numeric: tabular-nums;">
                0.00
            </span>
        </td>
        <td style="padding: 0.5rem;">
            <select name="items[target_quarter][]" aria-label="Target Quarter" class="form-control" style="width: 100%; padding: 0.375rem 0.5rem; font-size: 0.8125rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-background); color: var(--color-text);">
                <option value="Q1" ${quarter === 'Q1' ? 'selected' : ''}>Q1</option>
                <option value="Q2" ${quarter === 'Q2' ? 'selected' : ''}>Q2</option>
                <option value="Q3" ${quarter === 'Q3' ? 'selected' : ''}>Q3</option>
                <option value="Q4" ${quarter === 'Q4' ? 'selected' : ''}>Q4</option>
            </select>
        </td>
        <td style="padding: 0.5rem;">
            <select name="items[funding_source][]" aria-label="Funding Source" class="form-control" style="width: 100%; padding: 0.375rem 0.5rem; font-size: 0.8125rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-background); color: var(--color-text);">
                <option value="GoG Consolidated Fund" ${funding === 'GoG Consolidated Fund' ? 'selected' : ''}>GoG Consolidated</option>
                <option value="IGF" ${funding === 'IGF' ? 'selected' : ''}>IGF</option>
                <option value="GETFund" ${funding === 'GETFund' ? 'selected' : ''}>GETFund</option>
                <option value="Donor / Research Grant" ${funding === 'Donor / Research Grant' ? 'selected' : ''}>Donor Grant</option>
            </select>
        </td>
        <td style="padding: 0.5rem; text-align: center;">
            <button type="button" class="remove-row-btn" aria-label="Remove Item Row" style="background: none; border: none; color: var(--color-danger); cursor: pointer; padding: 0.25rem 0.5rem; font-size: 0.9375rem;" title="Remove Line Item">
                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
            </button>
        </td>
    `;

    const qtyInput = tr.querySelector('.item-qty');
    const costInput = tr.querySelector('.item-cost');
    const removeBtn = tr.querySelector('.remove-row-btn');

    qtyInput.addEventListener('input', recalculateGrandTotals);
    costInput.addEventListener('input', recalculateGrandTotals);

    removeBtn.addEventListener('click', function() {
        if (tbody.children.length > 1) {
            tr.remove();
            renumberRows();
            recalculateGrandTotals();
        } else {
            if (typeof window.PromisAlert === 'function') {
                window.PromisAlert({
                    title: 'Minimum Line Items Required',
                    message: 'A procurement plan must contain at least one line item before it can be saved or submitted.',
                    type: 'warning',
                    icon: 'fa-triangle-exclamation'
                });
            } else {
                alert('A procurement plan must have at least one line item.');
            }
        }
    });

    tbody.appendChild(tr);
    recalculateLineTotal(tr);
}

function renumberRows() {
    const rows = document.querySelectorAll('#itemsTbody tr');
    rows.forEach((row, i) => {
        const snCell = row.querySelector('.row-sn');
        if (snCell) {
            snCell.textContent = i + 1;
        }
    });
}

function recalculateLineTotal(row) {
    const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    const cost = parseFloat(row.querySelector('.item-cost').value) || 0;
    const total = qty * cost;
    const display = row.querySelector('.line-total-display');
    if (display) {
        display.textContent = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    return total;
}

function recalculateGrandTotals() {
    const rows = document.querySelectorAll('#itemsTbody tr');
    let grandTotal = 0;
    let validCount = 0;

    rows.forEach(row => {
        grandTotal += recalculateLineTotal(row);
        validCount++;
    });

    document.getElementById('totalItemsCount').textContent = validCount;
    document.getElementById('grandTotalDisplay').textContent = 'GHS ' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
