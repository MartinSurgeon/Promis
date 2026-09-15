<?php
/**
 * Planning Entity Management View
 * PROMIS - Procurement Management Information System
 *
 * @var array $entities
 * @var int $totalRecords
 * @var int $totalPages
 * @var int $page
 * @var string $search
 * @var int|null $typeFilter
 * @var int|null $campusFilter
 * @var string $statusFilter
 * @var array $metrics
 * @var array $entityTypes
 * @var array $campuses
 * @var array $allEntities
 * @var array $tree
 * @var string $appUrl
 * @var array|null $user
 */

$currentUser = is_callable($user) ? $user() : ($user ?? []);
?>

<!-- KPI Metrics Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- Metric 1: Total Entities -->
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--color-primary);">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(140, 0, 59, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
            <i class="fa-solid fa-building-columns"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.05em;">Total Departments & Units</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); line-height: 1.2;"><?= (int)($metrics['total_entities'] ?? 0) ?></div>
            <div style="font-size: 0.6875rem; color: var(--color-muted-text); margin-top: 0.125rem;">
                All Academic & Admin Units
            </div>
        </div>
    </div>

    <!-- Metric 2: Active Entities -->
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--color-success);">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(22, 163, 74, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.05em;">Active Departments & Units</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); line-height: 1.2;"><?= (int)($metrics['active_entities'] ?? 0) ?></div>
            <div style="font-size: 0.6875rem; color: var(--color-muted-text); margin-top: 0.125rem;">
                <?= (int)($metrics['inactive_entities'] ?? 0) ?> Inactive Units
            </div>
        </div>
    </div>

    <!-- Metric 3: Entity Types -->
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--color-info);">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(2, 132, 199, 0.1); color: var(--color-info); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.05em;">Unit Types</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); line-height: 1.2;"><?= (int)($metrics['entity_types_count'] ?? 0) ?></div>
            <div style="font-size: 0.6875rem; color: var(--color-muted-text); margin-top: 0.125rem;">
                Faculties, Depts & Units
            </div>
        </div>
    </div>

    <!-- Metric 4: Hierarchy Depth -->
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--color-accent);">
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: rgba(217, 119, 6, 0.1); color: var(--color-accent); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
            <i class="fa-solid fa-sitemap"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; letter-spacing: 0.05em;">University Levels</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--color-text); line-height: 1.2;"><?= (int)($metrics['max_hierarchy_depth'] ?? 0) ?></div>
            <div style="font-size: 0.6875rem; color: var(--color-muted-text); margin-top: 0.125rem;">
                Levels in Structure
            </div>
        </div>
    </div>
</div>

<!-- Action Bar & Filter Header -->
<div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem;">
        <div>
            <h2 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.25rem;">
                University Structure
            </h2>
            <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin: 0;">
                Organize and manage university faculties, departments, and administrative units.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="openModal('createEntityModal')" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-plus-circle"></i>
                <span>Add Department or Unit</span>
            </button>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="<?= $e($appUrl ?? '') ?>/admin/entities" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end;">
        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Search</label>
            <input type="text" name="search" value="<?= $e($search ?? '') ?>" placeholder="Search by department code or name..." class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
        </div>
        <div style="min-width: 160px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Unit Type</label>
            <select name="entity_type_id" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                <option value="">All Unit Types</option>
                <?php foreach ($entityTypes as $et): ?>
                    <option value="<?= (int)$et['id'] ?>" <?= $typeFilter === (int)$et['id'] ? 'selected' : '' ?>><?= $e($et['type_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 160px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Campus</label>
            <select name="campus_id" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                <option value="">All Campuses</option>
                <?php foreach ($campuses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $campusFilter === (int)$c['id'] ? 'selected' : '' ?>><?= $e($c['campus_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 130px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Status</label>
            <select name="status" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                <option value="">All</option>
                <option value="ACTIVE" <?= $statusFilter === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                <option value="INACTIVE" <?= $statusFilter === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <a href="<?= $e($appUrl ?? '') ?>/admin/entities" class="btn" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-muted-text); display: inline-flex; align-items: center; gap: 0.375rem;">
                <i class="fa-solid fa-times"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Interactive Tree View -->
<div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-sitemap" style="color: var(--color-primary);"></i>
            University Structure
        </h3>
        <div style="display: flex; gap: 0.5rem;">
            <button type="button" onclick="expandAllNodes()" style="background: none; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.375rem 0.75rem; font-size: 0.75rem; color: var(--color-muted-text); cursor: pointer;">
                <i class="fa-solid fa-expand"></i> Expand All
            </button>
            <button type="button" onclick="collapseAllNodes()" style="background: none; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.375rem 0.75rem; font-size: 0.75rem; color: var(--color-muted-text); cursor: pointer;">
                <i class="fa-solid fa-compress"></i> Collapse All
            </button>
        </div>
    </div>
    <div id="entityTreeContainer" style="font-size: 0.875rem;">
        <?php if (empty($tree)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--color-muted-text);">
                <i class="fa-solid fa-building-circle-xmark" style="font-size: 2rem; margin-bottom: 0.75rem; display: block; opacity: 0.4;"></i>
                <p style="margin: 0;">No departments or units have been created yet. Click "Add Department or Unit" to build your university structure.</p>
            </div>
        <?php else: ?>
            <?php renderTreeNodes($tree, 0, $appUrl ?? ''); ?>
        <?php endif; ?>
    </div>
</div>

<?php
/**
 * Recursively render tree nodes.
 */
function renderTreeNodes(array $nodes, int $depth, string $appUrl): void {
    foreach ($nodes as $node):
        $hasChildren = !empty($node['children_nodes']);
        $isActive = (bool)($node['is_active'] ?? true);
        $typeCode = $node['type_code'] ?? 'DEPT';
        $childCount = (int)($node['child_count'] ?? count($node['children_nodes'] ?? []));

        // Type badge colors
        $typeBadgeColor = match($typeCode) {
            'UNIV' => 'background: rgba(140, 0, 59, 0.12); color: var(--color-primary);',
            'FAC' => 'background: rgba(2, 132, 199, 0.12); color: var(--color-info);',
            'DEPT' => 'background: rgba(22, 163, 74, 0.12); color: var(--color-success);',
            'UNIT' => 'background: rgba(217, 119, 6, 0.12); color: var(--color-accent);',
            default => 'background: rgba(100, 116, 139, 0.12); color: var(--color-muted-text);',
        };

        $statusBadge = $isActive
            ? '<span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--color-success); margin-right: 0.25rem;" title="Active"></span>'
            : '<span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--color-danger); margin-right: 0.25rem;" title="Inactive"></span>';

        $nodeId = 'tree-node-' . (int)$node['id'];
        $paddingLeft = ($depth * 1.5) + 0.5;
?>
        <div class="tree-node" id="<?= $nodeId ?>" style="border-bottom: 1px solid var(--color-border); <?= !$isActive ? 'opacity: 0.6;' : '' ?>">
            <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; padding-left: <?= $paddingLeft ?>rem; cursor: <?= $hasChildren ? 'pointer' : 'default' ?>; transition: background 0.15s ease;" 
                 onmouseover="this.style.background='rgba(140, 0, 59, 0.04)'" 
                 onmouseout="this.style.background='transparent'"
                 <?= $hasChildren ? 'onclick="toggleTreeNode(\'' . $nodeId . '\')"' : '' ?>>
                
                <!-- Expand/Collapse Toggle -->
                <span style="width: 1.25rem; text-align: center; font-size: 0.75rem; color: var(--color-muted-text); flex-shrink: 0;">
                    <?php if ($hasChildren): ?>
                        <i class="fa-solid fa-chevron-down tree-toggle" style="transition: transform 0.2s ease;"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-minus" style="opacity: 0.2;"></i>
                    <?php endif; ?>
                </span>

                <!-- Status Indicator -->
                <?= $statusBadge ?>

                <!-- Entity Code -->
                <span style="font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text); min-width: 80px;"><?= htmlspecialchars($node['entity_code'] ?? '', ENT_QUOTES) ?></span>

                <!-- Entity Name -->
                <span style="font-weight: 600; color: var(--color-text); flex: 1;"><?= htmlspecialchars($node['entity_name'] ?? '', ENT_QUOTES) ?></span>

                <!-- Type Badge -->
                <span style="<?= $typeBadgeColor ?> font-size: 0.6875rem; font-weight: 600; padding: 0.125rem 0.5rem; border-radius: 999px; white-space: nowrap;">
                    <?= htmlspecialchars($node['type_name'] ?? $typeCode, ENT_QUOTES) ?>
                </span>

                <!-- Child Count -->
                <?php if ($childCount > 0): ?>
                    <span style="font-size: 0.6875rem; color: var(--color-muted-text); background: var(--color-surface-secondary); padding: 0.125rem 0.375rem; border-radius: var(--radius-sm);">
                        <?= $childCount ?> child<?= $childCount > 1 ? 'ren' : '' ?>
                    </span>
                <?php endif; ?>

                <!-- Actions -->
                <div style="display: flex; gap: 0.25rem; flex-shrink: 0;" onclick="event.stopPropagation()">
                    <button type="button" onclick="viewEntityDetails(<?= (int)$node['id'] ?>)" title="View Details" aria-label="View Details" style="background: rgba(37, 99, 235, 0.08); border: none; padding: 0.25rem 0.375rem; color: var(--color-info); cursor: pointer; font-size: 0.8125rem; border-radius: var(--radius-sm); transition: all 0.15s ease;">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button type="button" onclick="openEditEntityModal(<?= (int)$node['id'] ?>)" title="Edit" aria-label="Edit" style="background: rgba(124, 58, 237, 0.08); border: none; padding: 0.25rem 0.375rem; color: var(--color-primary); cursor: pointer; font-size: 0.8125rem; border-radius: var(--radius-sm); transition: all 0.15s ease;">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" 
                            data-parent-id="<?= (int)$node['id'] ?>"
                            data-parent-name="<?= htmlspecialchars($node['entity_name'] ?? '', ENT_QUOTES) ?>"
                            onclick="openCreateChildModal(this)" 
                            title="Add Sub-Unit" 
                            aria-label="Add Sub-Unit"
                            style="background: rgba(22, 163, 74, 0.08); border: none; padding: 0.25rem 0.375rem; color: var(--color-success); cursor: pointer; font-size: 0.8125rem; border-radius: var(--radius-sm); transition: all 0.15s ease;">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>

            <!-- Children Container -->
            <?php if ($hasChildren): ?>
                <div class="tree-children" style="display: block;">
                    <?php renderTreeNodes($node['children_nodes'], $depth + 1, $appUrl); ?>
                </div>
            <?php endif; ?>
        </div>
<?php
    endforeach;
}
?>

<!-- Entity Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-border);">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin: 0;">
            Departments & Units Directory
            <span style="font-size: 0.8125rem; font-weight: 400; color: var(--color-muted-text); margin-left: 0.5rem;">(<?= $totalRecords ?> total units)</span>
        </h3>
    </div>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
            <thead>
                <tr style="background: var(--color-surface-secondary);">
                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em; white-space: nowrap;">Code</th>
                    <th style="padding: 0.75rem 0.75rem; text-align: left; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">Department / Unit Name</th>
                    <th style="padding: 0.75rem 0.75rem; text-align: left; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">Type</th>
                    <th style="padding: 0.75rem 0.75rem; text-align: left; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">Campus</th>
                    <th style="padding: 0.75rem 0.75rem; text-align: left; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">Parent Unit</th>
                    <th style="padding: 0.75rem 0.75rem; text-align: left; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">Head of Department / Unit</th>
                    <th style="padding: 0.75rem 0.75rem; text-align: center; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">Staff Members</th>
                    <th style="padding: 0.75rem 0.75rem; text-align: center; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">Status</th>
                    <th style="padding: 0.75rem 1rem; text-align: center; font-weight: 600; color: var(--color-muted-text); text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entities)): ?>
                    <tr>
                        <td colspan="9" style="padding: 2rem; text-align: center; color: var(--color-muted-text);">
                            <i class="fa-solid fa-search" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: block; opacity: 0.3;"></i>
                            No departments or units match your search criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entities as $entity): 
                        $isActive = (bool)($entity['is_active'] ?? true);
                        $typeCode = $entity['type_code'] ?? 'DEPT';
                        $typeBadgeColor = match($typeCode) {
                            'UNIV' => 'background: rgba(140, 0, 59, 0.12); color: var(--color-primary);',
                            'FAC' => 'background: rgba(2, 132, 199, 0.12); color: var(--color-info);',
                            'DEPT' => 'background: rgba(22, 163, 74, 0.12); color: var(--color-success);',
                            'UNIT' => 'background: rgba(217, 119, 6, 0.12); color: var(--color-accent);',
                            default => 'background: rgba(100, 116, 139, 0.12); color: var(--color-muted-text);',
                        };
                    ?>
                        <tr style="border-bottom: 1px solid var(--color-border); <?= !$isActive ? 'opacity: 0.55;' : '' ?>" onmouseover="this.style.background='rgba(140, 0, 59, 0.02)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 0.75rem 1rem; white-space: nowrap;">
                                <span style="font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 0.75rem; font-weight: 600; color: var(--color-primary);"><?= $e($entity['entity_code'] ?? '') ?></span>
                            </td>
                            <td style="padding: 0.75rem; font-weight: 600; color: var(--color-text);">
                                <?= $e($entity['entity_name'] ?? '') ?>
                            </td>
                            <td style="padding: 0.75rem;">
                                <span style="<?= $typeBadgeColor ?> font-size: 0.6875rem; font-weight: 600; padding: 0.125rem 0.5rem; border-radius: 999px; white-space: nowrap;">
                                    <?= $e($entity['type_name'] ?? '') ?>
                                </span>
                            </td>
                            <td style="padding: 0.75rem; color: var(--color-muted-text); font-size: 0.8125rem;">
                                <?= $e($entity['campus_name'] ?? '—') ?>
                            </td>
                            <td style="padding: 0.75rem; color: var(--color-muted-text); font-size: 0.8125rem;">
                                <?= !empty($entity['parent_entity_name']) ? $e($entity['parent_entity_name']) : '<span style="opacity: 0.4;">— Top Level —</span>' ?>
                            </td>
                            <td style="padding: 0.75rem; color: var(--color-muted-text); font-size: 0.8125rem;">
                                <?= !empty($entity['head_user_name']) && trim($entity['head_user_name']) !== '' ? $e($entity['head_user_name']) : '<span style="opacity: 0.4;">—</span>' ?>
                            </td>
                            <td style="padding: 0.75rem; text-align: center;">
                                <span style="font-size: 0.75rem; font-weight: 600; color: var(--color-muted-text);"><?= (int)($entity['assigned_users_count'] ?? 0) ?></span>
                            </td>
                            <td style="padding: 0.75rem; text-align: center;">
                                <?php if ($isActive): ?>
                                    <span style="background: rgba(22, 163, 74, 0.12); color: var(--color-success); font-size: 0.6875rem; font-weight: 600; padding: 0.125rem 0.5rem; border-radius: 999px;">Active</span>
                                <?php else: ?>
                                    <span style="background: rgba(220, 38, 38, 0.12); color: var(--color-danger); font-size: 0.6875rem; font-weight: 600; padding: 0.125rem 0.5rem; border-radius: 999px;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center;">
                                <div style="display: flex; gap: 0.375rem; justify-content: center; align-items: center;">
                                    <button type="button" 
                                            class="entity-action-btn"
                                            onclick="viewEntityDetails(<?= (int)$entity['id'] ?>)" 
                                            title="View Details" 
                                            aria-label="View Details"
                                            style="background: rgba(37, 99, 235, 0.08); border: none; padding: 0.375rem 0.5rem; color: var(--color-info); cursor: pointer; font-size: 0.875rem; border-radius: var(--radius-sm); transition: all 0.15s ease;">
                                        <i class="fa-solid fa-eye"></i>
                                    </                                    <button type="button" 
                                            class="entity-action-btn"
                                            onclick="openEditEntityModal(<?= (int)$entity['id'] ?>)" 
                                            title="Edit Department / Unit" 
                                            aria-label="Edit Department / Unit"
                                            style="background: rgba(124, 58, 237, 0.08); border: none; padding: 0.375rem 0.5rem; color: var(--color-primary); cursor: pointer; font-size: 0.875rem; border-radius: var(--radius-sm); transition: all 0.15s ease;">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <?php if ($isActive): ?>
                                        <button type="button" 
                                                class="entity-action-btn"
                                                data-id="<?= (int)$entity['id'] ?>"
                                                data-active="0"
                                                data-name="<?= $e($entity['entity_name'] ?? '') ?>"
                                                onclick="confirmToggleStatus(this)" 
                                                title="Deactivate Department / Unit" 
                                                aria-label="Deactivate Department / Unit"
                                                style="background: rgba(220, 38, 38, 0.08); border: none; padding: 0.375rem 0.5rem; color: var(--color-danger); cursor: pointer; font-size: 0.875rem; border-radius: var(--radius-sm); transition: all 0.15s ease;">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" 
                                                class="entity-action-btn"
                                                data-id="<?= (int)$entity['id'] ?>"
                                                data-active="1"
                                                data-name="<?= $e($entity['entity_name'] ?? '') ?>"
                                                onclick="confirmToggleStatus(this)" 
                                                title="Activate Department / Unit" 
                                                aria-label="Activate Department / Unit"
                                                style="background: rgba(22, 163, 74, 0.08); border: none; padding: 0.375rem 0.5rem; color: var(--color-success); cursor: pointer; font-size: 0.875rem; border-radius: var(--radius-sm); transition: all 0.15s ease;">
                                            <i class="fa-solid fa-check-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between;">
            <span style="font-size: 0.8125rem; color: var(--color-muted-text);">
                Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRecords ?> units)
            </span>
            <div style="display: flex; gap: 0.375rem;">
                <?php if ($page > 1): ?>
                    <a href="<?= $e($appUrl ?? '') ?>/admin/entities?page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>&entity_type_id=<?= $typeFilter ?? '' ?>&campus_id=<?= $campusFilter ?? '' ?>&status=<?= urlencode($statusFilter ?? '') ?>" style="padding: 0.375rem 0.75rem; font-size: 0.8125rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm); color: var(--color-text); text-decoration: none;">← Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= $e($appUrl ?? '') ?>/admin/entities?page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>&entity_type_id=<?= $typeFilter ?? '' ?>&campus_id=<?= $campusFilter ?? '' ?>&status=<?= urlencode($statusFilter ?? '') ?>" style="padding: 0.375rem 0.75rem; font-size: 0.8125rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm); color: var(--color-text); text-decoration: none;">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- CREATE ENTITY MODAL -->
<!-- ═══════════════════════════════════════════════════════ -->
<div id="createEntityModal" class="modal modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
    <div class="modal-content card" style="width: 100%; max-width: 620px; max-height: 90vh; overflow-y: auto; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); animation: modalSlideIn 0.25s ease-out;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: var(--color-surface); z-index: 1; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 700; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-plus-circle" style="color: var(--color-primary);"></i>
                <span id="createModalTitle">Add Department or Unit</span>
            </h3>
            <button type="button" onclick="closeModal('createEntityModal')" style="background: none; border: none; font-size: 1.25rem; color: var(--color-muted-text); cursor: pointer; padding: 0.25rem;" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="createEntityForm" method="POST" action="<?= $e($appUrl ?? '') ?>/admin/entities">
            <?= $csrf() ?>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div id="createEntityError" style="display: none; padding: 0.75rem 1rem; background: rgba(220, 38, 38, 0.1); border: 1px solid rgba(220, 38, 38, 0.2); border-radius: var(--radius-md); color: var(--color-danger); font-size: 0.8125rem;"></div>

                <!-- Entity Code -->
                <div>
                    <label for="create_entity_code" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                        Department / Unit Code <span style="color: var(--color-danger);">*</span>
                    </label>
                    <input type="text" id="create_entity_code" name="entity_code" required maxlength="50" placeholder="e.g. DEPT-CS" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text); font-family: 'JetBrains Mono', 'Fira Code', monospace; text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()">
                    <p style="margin: 0.25rem 0 0; font-size: 0.6875rem; color: var(--color-muted-text);">Unique university identifier. Read-only after creation.</p>
                </div>

                <!-- Entity Name -->
                <div>
                    <label for="create_entity_name" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                        Department / Unit Name <span style="color: var(--color-danger);">*</span>
                    </label>
                    <input type="text" id="create_entity_name" name="entity_name" required maxlength="150" placeholder="e.g. Department of Computer Science" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <!-- Entity Type -->
                    <div>
                        <label for="create_entity_type_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                            Unit Type <span style="color: var(--color-danger);">*</span>
                        </label>
                        <select id="create_entity_type_id" name="entity_type_id" required style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                            <option value="">Select type...</option>
                            <?php foreach ($entityTypes as $et): ?>
                                <option value="<?= (int)$et['id'] ?>"><?= $e($et['type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campus -->
                    <div>
                        <label for="create_campus_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                            Campus <span style="color: var(--color-danger);">*</span>
                        </label>
                        <select id="create_campus_id" name="campus_id" required style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                            <option value="">Select campus...</option>
                            <?php foreach ($campuses as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= $e($c['campus_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Parent Entity -->
                <div>
                    <label for="create_parent_entity_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                        Parent Unit
                    </label>
                    <select id="create_parent_entity_id" name="parent_entity_id" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                        <option value="">— None (Top Level) —</option>
                        <?php foreach ($allEntities as $pe): ?>
                            <option value="<?= (int)$pe['id'] ?>"><?= $e($pe['entity_code']) ?> — <?= $e($pe['entity_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p style="margin: 0.25rem 0 0; font-size: 0.6875rem; color: var(--color-muted-text);">Leave blank for top-level university units.</p>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <!-- Head of Entity -->
                    <div>
                        <label for="create_head_user_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                            Head of Department / Unit
                        </label>
                        <input type="number" id="create_head_user_id" name="head_user_id" min="1" placeholder="User ID" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                    </div>

                    <!-- Planning Officer -->
                    <div>
                        <label for="create_planning_officer_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                            Procurement / Planning Contact
                        </label>
                        <input type="number" id="create_planning_officer_id" name="planning_officer_id" min="1" placeholder="User ID" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                    </div>
                </div>
            </div>

            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--color-surface-secondary); border-radius: 0 0 var(--radius-lg) var(--radius-lg);">
                <button type="button" onclick="closeModal('createEntityModal')" class="btn" style="padding: 0.5rem 1.25rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-muted-text); background: var(--color-surface); font-size: 0.875rem; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" id="createEntitySubmitBtn" class="btn btn-primary" style="padding: 0.5rem 1.25rem; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-plus-circle"></i>
                    <span>Add Department or Unit</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- EDIT ENTITY MODAL -->
<!-- ═══════════════════════════════════════════════════════ -->
<div id="editEntityModal" class="modal modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
    <div class="modal-content card" style="width: 100%; max-width: 620px; max-height: 90vh; overflow-y: auto; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); animation: modalSlideIn 0.25s ease-out;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: var(--color-surface); z-index: 1; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 700; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-pen-to-square" style="color: var(--color-primary);"></i>
                Edit Department or Unit
            </h3>
            <button type="button" onclick="closeModal('editEntityModal')" style="background: none; border: none; font-size: 1.25rem; color: var(--color-muted-text); cursor: pointer; padding: 0.25rem;" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="editEntityForm" method="POST" action="">
            <?= $csrf() ?>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div id="editEntityError" style="display: none; padding: 0.75rem 1rem; background: rgba(220, 38, 38, 0.1); border: 1px solid rgba(220, 38, 38, 0.2); border-radius: var(--radius-md); color: var(--color-danger); font-size: 0.8125rem;"></div>

                <!-- Entity Code (Read-Only) -->
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                        Department / Unit Code <span style="font-size: 0.6875rem; color: var(--color-muted-text); font-weight: 400;">(read-only)</span>
                    </label>
                    <input type="text" id="edit_entity_code" readonly disabled style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface-secondary); color: var(--color-muted-text); font-family: 'JetBrains Mono', 'Fira Code', monospace; cursor: not-allowed;">
                </div>

                <!-- Entity Name -->
                <div>
                    <label for="edit_entity_name" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                        Department / Unit Name <span style="color: var(--color-danger);">*</span>
                    </label>
                    <input type="text" id="edit_entity_name" name="entity_name" required maxlength="150" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <!-- Entity Type -->
                    <div>
                        <label for="edit_entity_type_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                            Unit Type <span style="color: var(--color-danger);">*</span>
                        </label>
                        <select id="edit_entity_type_id" name="entity_type_id" required style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                            <option value="">Select type...</option>
                            <?php foreach ($entityTypes as $et): ?>
                                <option value="<?= (int)$et['id'] ?>"><?= $e($et['type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campus -->
                    <div>
                        <label for="edit_campus_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                            Campus <span style="color: var(--color-danger);">*</span>
                        </label>
                        <select id="edit_campus_id" name="campus_id" required style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                            <option value="">Select campus...</option>
                            <?php foreach ($campuses as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= $e($c['campus_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Parent Entity -->
                <div>
                    <label for="edit_parent_entity_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                        Parent Unit
                    </label>
                    <select id="edit_parent_entity_id" name="parent_entity_id" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                        <option value="">— None (Top Level) —</option>
                        <?php foreach ($allEntities as $pe): ?>
                            <option value="<?= (int)$pe['id'] ?>"><?= $e($pe['entity_code']) ?> — <?= $e($pe['entity_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="editParentWarning" style="display: none; margin: 0.25rem 0 0; font-size: 0.6875rem; color: var(--color-danger);"></p>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <!-- Head of Entity -->
                    <div>
                        <label for="edit_head_user_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                            Head of Department / Unit
                        </label>
                        <input type="number" id="edit_head_user_id" name="head_user_id" min="1" placeholder="User ID" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                    </div>

                    <!-- Planning Officer -->
                    <div>
                        <label for="edit_planning_officer_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                            Procurement / Planning Contact
                        </label>
                        <input type="number" id="edit_planning_officer_id" name="planning_officer_id" min="1" placeholder="User ID" style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text);">
                    </div>
                </div>
            </div>

            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--color-surface-secondary); border-radius: 0 0 var(--radius-lg) var(--radius-lg);">
                <button type="button" onclick="closeModal('editEntityModal')" class="btn" style="padding: 0.5rem 1.25rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-muted-text); background: var(--color-surface); font-size: 0.875rem; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" id="editEntitySubmitBtn" class="btn btn-primary" style="padding: 0.5rem 1.25rem; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-save"></i>
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- ENTITY DETAILS MODAL -->
<!-- ═══════════════════════════════════════════════════════ -->
<div id="entityDetailsModal" class="modal modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
    <div class="modal-content card" style="width: 100%; max-width: 580px; max-height: 90vh; overflow-y: auto; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); animation: modalSlideIn 0.25s ease-out;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: var(--color-surface); z-index: 1; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 700; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-building" style="color: var(--color-primary);"></i>
                Department / Unit Details
            </h3>
            <button type="button" onclick="closeModal('entityDetailsModal')" style="background: none; border: none; font-size: 1.25rem; color: var(--color-muted-text); cursor: pointer; padding: 0.25rem;" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="entityDetailsContent" style="padding: 1.5rem;">
            <div style="text-align: center; padding: 2rem; color: var(--color-muted-text);">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.5rem;"></i>
                <p style="margin: 0.5rem 0 0;">Loading details...</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- STATUS TOGGLE FORM (hidden) -->
<!-- ═══════════════════════════════════════════════════════ -->
<form id="statusToggleForm" method="POST" action="" style="display: none;">
    <?= $csrf() ?>
    <input type="hidden" id="statusToggleValue" name="is_active" value="">
</form>

<style>
    @keyframes modalSlideIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .modal-overlay, .modal { display: none; }
    .modal-overlay.active, .modal-overlay.modal-open, .modal.active, .modal.modal-open { display: flex !important; }
</style>

<script>
const APP_URL = <?= json_encode($appUrl ?? '') ?>;

// ─── Modal Management ───
window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.setProperty('display', 'flex', 'important');
        modal.classList.add('active');
        modal.classList.add('modal-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        const firstInput = modal.querySelector('input:not([readonly]):not([disabled]), select, button:not(.modal-close)');
        if (firstInput) setTimeout(() => firstInput.focus(), 100);
    }
};

window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.setProperty('display', 'none', 'important');
        modal.classList.remove('active');
        modal.classList.remove('modal-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
};

// Close on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.modal-open, .modal.active, .modal-overlay.active').forEach(m => closeModal(m.id));
    }
});

// Close on overlay click
document.querySelectorAll('.modal-overlay, .modal').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});

// ─── Tree Controls ───
function toggleTreeNode(nodeId) {
    const node = document.getElementById(nodeId);
    if (!node) return;
    const children = node.querySelector('.tree-children');
    const toggle = node.querySelector('.tree-toggle');
    if (children) {
        const isVisible = children.style.display !== 'none';
        children.style.display = isVisible ? 'none' : 'block';
        if (toggle) toggle.style.transform = isVisible ? 'rotate(-90deg)' : 'rotate(0deg)';
    }
}

function expandAllNodes() {
    document.querySelectorAll('.tree-children').forEach(c => c.style.display = 'block');
    document.querySelectorAll('.tree-toggle').forEach(t => t.style.transform = 'rotate(0deg)');
}

function collapseAllNodes() {
    document.querySelectorAll('.tree-children').forEach(c => c.style.display = 'none');
    document.querySelectorAll('.tree-toggle').forEach(t => t.style.transform = 'rotate(-90deg)');
}

// ─── Create Child Shortcut ───
function openCreateChildModal(target, parentName) {
    let parentId = target;
    if (typeof target === 'object' && target !== null && target.dataset) {
        parentId = target.dataset.parentId;
        parentName = target.dataset.parentName;
    }
    document.getElementById('createModalTitle').textContent = parentName ? 'Add Sub-Unit Under ' + parentName : 'Add Sub-Unit';
    const parentSelect = document.getElementById('create_parent_entity_id');
    if (parentSelect && parentId) parentSelect.value = parentId;
    openModal('createEntityModal');
}

// ─── Edit Entity Modal ───
async function openEditEntityModal(entityId) {
    const errBox = document.getElementById('editEntityError');
    errBox.style.display = 'none';

    try {
        const res = await fetch(APP_URL + '/admin/entities/' + entityId + '/json', {
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();

        if (!json.success || !json.data || !json.data.entity) {
            throw new Error(json.message || 'Failed to load entity details.');
        }

        const entity = json.data.entity;
        const form = document.getElementById('editEntityForm');
        form.action = APP_URL + '/admin/entities/' + entityId + '/edit';

        document.getElementById('edit_entity_code').value = entity.entity_code || '';
        document.getElementById('edit_entity_name').value = entity.entity_name || '';
        document.getElementById('edit_entity_type_id').value = entity.entity_type_id || '';
        document.getElementById('edit_campus_id').value = entity.campus_id || '';
        document.getElementById('edit_parent_entity_id').value = entity.parent_entity_id || '';
        document.getElementById('edit_head_user_id').value = entity.head_user_id || '';
        document.getElementById('edit_planning_officer_id').value = entity.planning_officer_id || '';

        // Disable self and descendants in parent dropdown
        const parentSelect = document.getElementById('edit_parent_entity_id');
        const descendantIds = (json.data.children || []).map(c => String(c.id));
        descendantIds.push(String(entityId));

        Array.from(parentSelect.options).forEach(opt => {
            opt.disabled = descendantIds.includes(opt.value);
        });

        openModal('editEntityModal');
    } catch (error) {
        errBox.textContent = error.message;
        errBox.style.display = 'block';
        openModal('editEntityModal');
    }
}

// ─── View Entity Details ───
async function viewEntityDetails(entityId) {
    const container = document.getElementById('entityDetailsContent');
    container.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--color-muted-text);"><i class="fa-solid fa-spinner fa-spin" style="font-size: 1.5rem;"></i><p style="margin: 0.5rem 0 0;">Loading...</p></div>';
    openModal('entityDetailsModal');

    try {
        const res = await fetch(APP_URL + '/admin/entities/' + entityId + '/json', {
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();

        if (!json.success || !json.data) {
            throw new Error(json.message || 'Failed to load entity.');
        }

        const d = json.data;
        const e = d.entity;
        const isActive = Boolean(Number(e.is_active));
        const statusBadge = isActive
            ? '<span style="background:rgba(22,163,74,0.12);color:var(--color-success);font-size:0.75rem;font-weight:600;padding:0.125rem 0.5rem;border-radius:999px;">Active</span>'
            : '<span style="background:rgba(220,38,38,0.12);color:var(--color-danger);font-size:0.75rem;font-weight:600;padding:0.125rem 0.5rem;border-radius:999px;">Inactive</span>';

        let childrenHtml = '';
        if (d.children && d.children.length > 0) {
            childrenHtml = '<div style="margin-top:1rem;"><div style="font-size:0.8125rem;font-weight:600;color:var(--color-text);margin-bottom:0.5rem;">Direct Sub-Units</div>';
            d.children.forEach(c => {
                const cActive = Boolean(Number(c.is_active));
                childrenHtml += '<div style="display:flex;align-items:center;gap:0.5rem;padding:0.375rem 0;border-bottom:1px solid var(--color-border);font-size:0.8125rem;">'
                    + '<span style="width:8px;height:8px;border-radius:50%;background:' + (cActive ? 'var(--color-success)' : 'var(--color-danger)') + ';flex-shrink:0;"></span>'
                    + '<span style="font-family:monospace;font-size:0.75rem;color:var(--color-muted-text);">' + (c.entity_code||'') + '</span>'
                    + '<span style="font-weight:500;">' + (c.entity_name||'') + '</span>'
                    + '<span style="margin-left:auto;font-size:0.6875rem;color:var(--color-muted-text);">' + (c.type_name||'') + '</span>'
                    + '</div>';
            });
            childrenHtml += '</div>';
        }

        container.innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                <div>
                    <div style="font-size:0.6875rem;font-weight:600;color:var(--color-muted-text);text-transform:uppercase;letter-spacing:0.05em;">Code</div>
                    <div style="font-size:0.9375rem;font-weight:700;color:var(--color-primary);font-family:monospace;">${e.entity_code||''}</div>
                </div>
                <div>
                    <div style="font-size:0.6875rem;font-weight:600;color:var(--color-muted-text);text-transform:uppercase;letter-spacing:0.05em;">Status</div>
                    <div style="margin-top:0.125rem;">${statusBadge}</div>
                </div>
                <div style="grid-column:1/-1;">
                    <div style="font-size:0.6875rem;font-weight:600;color:var(--color-muted-text);text-transform:uppercase;letter-spacing:0.05em;">Name</div>
                    <div style="font-size:0.9375rem;font-weight:600;color:var(--color-text);">${e.entity_name||''}</div>
                </div>
                <div>
                    <div style="font-size:0.6875rem;font-weight:600;color:var(--color-muted-text);text-transform:uppercase;letter-spacing:0.05em;">Type</div>
                    <div style="font-size:0.8125rem;color:var(--color-text);">${e.type_name||'—'}</div>
                </div>
                <div>
                    <div style="font-size:0.6875rem;font-weight:600;color:var(--color-muted-text);text-transform:uppercase;letter-spacing:0.05em;">Campus</div>
                    <div style="font-size:0.8125rem;color:var(--color-text);">${e.campus_name||'—'}</div>
                </div>
                <div>
                    <div style="font-size:0.6875rem;font-weight:600;color:var(--color-muted-text);text-transform:uppercase;letter-spacing:0.05em;">Parent Unit</div>
                    <div style="font-size:0.8125rem;color:var(--color-text);">${e.parent_entity_name || '— Top Level —'}</div>
                </div>
                <div>
                    <div style="font-size:0.6875rem;font-weight:600;color:var(--color-muted-text);text-transform:uppercase;letter-spacing:0.05em;">Head of Department / Unit</div>
                    <div style="font-size:0.8125rem;color:var(--color-text);">${e.head_user_name && e.head_user_name.trim() ? e.head_user_name : '—'}</div>
                </div>
                <div>
                    <div style="font-size:0.6875rem;font-weight:600;color:var(--color-muted-text);text-transform:uppercase;letter-spacing:0.05em;">Procurement / Planning Contact</div>
                    <div style="font-size:0.8125rem;color:var(--color-text);">${e.planning_officer_name && e.planning_officer_name.trim() ? e.planning_officer_name : '—'}</div>
                </div>
                <div>
                    <div style="font-size:0.6875rem;font-weight:600;color:var(--color-muted-text);text-transform:uppercase;letter-spacing:0.05em;">Sub-Units</div>
                    <div style="font-size:0.8125rem;color:var(--color-text);">${d.descendant_count||0}</div>
                </div>
                <div>
                    <div style="font-size:0.6875rem;font-weight:600;color:var(--color-muted-text);text-transform:uppercase;letter-spacing:0.05em;">Assigned Staff</div>
                    <div style="font-size:0.8125rem;color:var(--color-text);">${d.assigned_users||0}</div>
                </div>
            </div>
            ${childrenHtml}
        `;
    } catch (error) {
        container.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--color-danger);"><i class="fa-solid fa-exclamation-triangle" style="font-size:1.5rem;margin-bottom:0.5rem;display:block;"></i>' + error.message + '</div>';
    }
}

// ─── Status Toggle with Confirmation ───
function confirmToggleStatus(target, activate, entityName) {
    let entityId = target;
    if (typeof target === 'object' && target !== null && target.dataset) {
        entityId = target.dataset.id;
        activate = target.dataset.active === '1' || target.dataset.active === 'true' || target.dataset.active === true;
        entityName = target.dataset.name || 'this department or unit';
    }

    const action = activate ? 'activate' : 'deactivate';
    let message = `Are you sure you want to ${action} "${entityName}"?`;

    if (!activate) {
        message += '\n\nNote: Sub-units will NOT be automatically deactivated. You can manage them individually.';
    }

    if (confirm(message)) {
        const form = document.getElementById('statusToggleForm');
        form.action = APP_URL + '/admin/entities/' + entityId + '/status';
        document.getElementById('statusToggleValue').value = activate ? '1' : '0';
        form.submit();
    }
}

// ─── Form Submission with Loading State ───
document.getElementById('createEntityForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('createEntitySubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Adding...';
});

document.getElementById('editEntityForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('editEntitySubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
});
</script>
