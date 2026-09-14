<?php
/**
 * Printable Annual Procurement Plan View
 * PROMIS - Procurement Management Information System
 * Layout strictly matches the institutional structure of Resources.pdf
 *
 * @var array $plan
 * @var array $items
 * @var array $groupedItems
 * @var array $categoryTotals
 * @var string $grandTotal
 * @var array|null $approver
 * @var callable $e
 */

$creatorName = trim(($plan['creator_first_name'] ?? '') . ' ' . ($plan['creator_last_name'] ?? '')) ?: 'Planning Officer';
$approverName = $approver ? trim(($approver['first_name'] ?? '') . ' ' . ($approver['last_name'] ?? '')) : 'Authorized Approver';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annual Procurement Plan <?= $e($plan['fiscal_year']) ?> - <?= $e($plan['entity_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Newsreader:ital,wght@0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #111827;
            background: #f3f4f6;
            padding: 20px;
        }

        .no-print-bar {
            max-width: 1050px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: #8c003b;
            color: #ffffff;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .print-document-container {
            max-width: 1050px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        /* Institutional Header */
        .institution-header {
            text-align: center;
            border-bottom: 2px solid #111827;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .institution-name {
            font-size: 15pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #111827;
        }

        .plan-title {
            font-size: 13pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #8c003b;
            margin-top: 6px;
        }

        .entity-banner {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 15px;
            font-size: 10.5pt;
        }

        .entity-title {
            font-weight: 700;
            color: #111827;
        }

        .plan-ref {
            font-weight: 600;
            color: #4b5563;
        }

        /* Official Categorized Table */
        table.procurement-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 30px;
        }

        table.procurement-table th, 
        table.procurement-table td {
            border: 1px solid #374151;
            padding: 6px 8px;
            vertical-align: top;
        }

        table.procurement-table th {
            background-color: #f3f4f6;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9pt;
            text-align: left;
        }

        .category-header-row {
            background-color: #e5e7eb;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 9.5pt;
            color: #111827;
        }

        .category-header-row td {
            padding: 6px 8px;
        }

        .grand-total-row {
            background-color: #f3f4f6;
            font-weight: 800;
            font-size: 10pt;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .tabular-nums { font-variant-numeric: tabular-nums; }

        /* Sign-off Section */
        .signoff-section {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            page-break-inside: avoid;
        }

        .signoff-box {
            border-top: 1px solid #374151;
            padding-top: 8px;
            font-size: 10pt;
        }

        .signoff-role {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9.5pt;
            margin-bottom: 4px;
        }

        .signoff-line {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            font-size: 9.5pt;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
                font-size: 10pt;
            }

            .no-print-bar {
                display: none !important;
            }

            .print-document-container {
                box-shadow: none;
                border: none;
                padding: 0;
                max-width: 100%;
            }

            @page {
                size: A4 portrait;
                margin: 15mm 12mm 15mm 12mm;
            }

            table.procurement-table th {
                background-color: #f3f4f6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .category-header-row {
                background-color: #e5e7eb !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <!-- On-Screen Navigation & Print Action Bar -->
    <div class="no-print-bar">
        <div style="font-size: 13px; font-weight: 600; color: #374151;">
            Formal Institutional Printable View (Mirroring Resources.pdf Layout)
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= $e($appUrl ?? '') ?>/procurement-plans/<?= (int)$plan['id'] ?>" class="btn btn-secondary">
                &larr; Back to Plan
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print();">
                🖨️ Print / Save as PDF
            </button>
        </div>
    </div>

    <!-- Official Printable Document Container -->
    <div class="print-document-container">
        
        <!-- Header -->
        <header class="institution-header">
            <h1 class="institution-name">University of Skills Training and Entrepreneurial Development</h1>
            <div class="plan-title">Annual Procurement Plan for <?= $e($plan['fiscal_year']) ?> Financial Year</div>
        </header>

        <!-- Entity and Plan Reference Strip -->
        <div class="entity-banner">
            <div>
                <strong>INSTITUTIONAL ENTITY:</strong> <?= strtoupper($e($plan['entity_name'])) ?> (<?= $e($plan['entity_code']) ?>)
            </div>
            <div class="plan-ref">
                <strong>PLAN NO:</strong> <?= $e($plan['plan_number']) ?> &nbsp;|&nbsp; <strong>VERSION:</strong> <?= $e($plan['version_number'] ?? '1.0') ?>
            </div>
        </div>

        <!-- Categorized Forecast Table -->
        <table class="procurement-table">
            <thead>
                <tr>
                    <th style="width: 35px;" class="text-center">SN</th>
                    <th style="width: 250px;">Contract Package / Description of Item</th>
                    <th style="width: 220px;">Detailed Specification</th>
                    <th style="width: 60px;" class="text-right">QTY</th>
                    <th style="width: 80px;" class="text-right">Unit Cost (GH¢)</th>
                    <th style="width: 100px;" class="text-right">Total Estimated Cost (GH¢)</th>
                    <th style="width: 65px;" class="text-center">Expected Date</th>
                    <th style="width: 90px;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $snCounter = 1;
                foreach ($groupedItems as $categoryName => $catItems): 
                ?>
                    <!-- Category Header Row -->
                    <tr class="category-header-row">
                        <td colspan="5">
                            <strong><?= strtoupper($e($categoryName)) ?></strong>
                        </td>
                        <td class="text-right tabular-nums">
                            <strong><?= number_format((float)($categoryTotals[$categoryName] ?? 0), 2) ?></strong>
                        </td>
                        <td colspan="2"></td>
                    </tr>

                    <!-- Category Item Lines -->
                    <?php foreach ($catItems as $item): ?>
                        <tr>
                            <td class="text-center"><?= $snCounter++ ?></td>
                            <td>
                                <strong><?= $e($item->itemDescription) ?></strong>
                            </td>
                            <td><?= $e($item->justification ?: 'Standard specifications') ?></td>
                            <td class="text-right tabular-nums">
                                <?= number_format((float)$item->plannedQuantity, 2) ?> <?= $e($item->uomCode ?? '') ?>
                            </td>
                            <td class="text-right tabular-nums">
                                <?= number_format((float)$item->estimatedUnitCost, 2) ?>
                            </td>
                            <td class="text-right tabular-nums" style="font-weight: 600;">
                                <?= number_format((float)$item->estimatedTotalCost, 2) ?>
                            </td>
                            <td class="text-center">
                                <?= $e($item->targetQuarter instanceof \BackedEnum ? $item->targetQuarter->value : (string)$item->targetQuarter) ?>
                            </td>
                            <td>
                                <?= $e($item->fundingSource) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="grand-total-row">
                    <td colspan="5" class="text-right">
                        <strong>GRAND TOTAL ESTIMATED COST:</strong>
                    </td>
                    <td class="text-right tabular-nums" style="font-weight: 800; font-size: 10.5pt;">
                        GH¢ <?= number_format((float)$grandTotal, 2) ?>
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>

        <!-- Sign-Off Endorsement Section (Prepared By & Approved By) -->
        <section class="signoff-section">
            <div class="signoff-box">
                <div class="signoff-role">Prepared By:</div>
                <div style="margin-top: 6px;"><strong>Name:</strong> <?= $e($creatorName) ?></div>
                <div><strong>Designation:</strong> Planning Officer / Head of Department</div>
                <div class="signoff-line">
                    <span><strong>Signature:</strong> _______________________</span>
                    <span><strong>Date:</strong> <?= date('d/m/Y', strtotime($plan['created_at'])) ?></span>
                </div>
            </div>

            <div class="signoff-box">
                <div class="signoff-role">Approved By:</div>
                <div style="margin-top: 6px;"><strong>Name:</strong> <?= $e($approverName) ?></div>
                <div><strong>Designation:</strong> Dean of Faculty / Institutional Approving Officer</div>
                <div class="signoff-line">
                    <span><strong>Signature:</strong> _______________________</span>
                    <span><strong>Date:</strong> <?= !empty($plan['approval_date']) ? date('d/m/Y', strtotime($plan['approval_date'])) : '___/___/20___' ?></span>
                </div>
            </div>
        </section>

    </div>

</body>
</html>
