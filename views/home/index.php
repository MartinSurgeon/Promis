<?php
/**
 * Neutral Foundation Home View
 * PROMIS - Procurement Management Information System
 * University of Science and Technology, Dedicated (USTED)
 *
 * @var string $title
 * @var bool $dbConnected
 * @var string $dbEngine
 * @var string $phpVersion
 * @var string $appEnv
 * @var string $appUrl
 */
?>

<div class="card p-6" style="margin-bottom: 2rem; background: linear-gradient(135deg, var(--color-primary-900) 0%, var(--color-primary-800) 100%); color: #ffffff; border: none;">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <span class="badge" style="background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.3); margin-bottom: 0.75rem;">
                Core Architecture Status
            </span>
            <h1 class="text-3xl font-bold" style="color: #ffffff; margin-bottom: 0.5rem;">
                PROMIS Application Foundation
            </h1>
            <p style="color: var(--color-primary-100); max-width: 650px; font-size: 0.95rem; margin-bottom: 1.25rem;">
                Enterprise Procurement Management Information System for the University of Science and Technology, Dedicated (USTED). Layered Core PHP 8.x architecture successfully initialized.
            </p>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <a href="<?= $e(($appUrl ?? '') . '/dashboard') ?>" class="btn" style="background: #ffffff; color: var(--color-primary); font-weight: 700; padding: 0.625rem 1.25rem; text-decoration: none; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Launch Procurement Workbench</span>
                </a>
                <a href="<?= $e(($appUrl ?? '') . '/requisitions?filter=pending') ?>" class="btn" style="background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.3); font-weight: 600; padding: 0.625rem 1.25rem; text-decoration: none; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-stamp"></i>
                    <span>Pending Approval Queues</span>
                </a>
            </div>
        </div>
        <div style="text-align: right;">
            <?php if (!empty($dbConnected)): ?>
                <span class="badge badge-success" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                    ● Database Connected (<?= $e(strtoupper($dbEngine ?? 'MARIADB')) ?>)
                </span>
            <?php else: ?>
                <span class="badge badge-danger" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                    ● Database Disconnected
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-3 gap-6" style="margin-bottom: 2rem;">
    <!-- Architecture Card -->
    <div class="card p-6">
        <div class="flex items-center gap-2" style="margin-bottom: 1rem;">
            <span class="badge badge-info">5-Tier</span>
            <h2 class="text-lg font-bold text-slate-800">Architectural Separation</h2>
        </div>
        <p class="text-slate-600 text-sm" style="margin-bottom: 1rem;">
            Strict boundary enforcement across Presentation, Controller, Service, Repository, and Database layers.
        </p>
        <ul style="font-size: 0.85rem; color: var(--color-slate-600); line-height: 1.8; list-style-type: none; padding-left: 0;">
            <li>✓ <strong>Presentation:</strong> Server-rendered sanitized views</li>
            <li>✓ <strong>Controller:</strong> Request validation, zero SQL</li>
            <li>✓ <strong>Service:</strong> Business logic, transaction orchestration</li>
            <li>✓ <strong>Repository:</strong> Prepared statements only</li>
            <li>✓ <strong>Database:</strong> Strict InnoDB, 122 foreign keys</li>
        </ul>
    </div>

    <!-- Security Card -->
    <div class="card p-6">
        <div class="flex items-center gap-2" style="margin-bottom: 1rem;">
            <span class="badge badge-success">Security</span>
            <h2 class="text-lg font-bold text-slate-800">Defensive Controls</h2>
        </div>
        <p class="text-slate-600 text-sm" style="margin-bottom: 1rem;">
            Built-in security primitives protecting state and communication channels.
        </p>
        <ul style="font-size: 0.85rem; color: var(--color-slate-600); line-height: 1.8; list-style-type: none; padding-left: 0;">
            <li>✓ <strong>Session:</strong> Strict mode, HttpOnly, SameSite=Lax</li>
            <li>✓ <strong>CSRF:</strong> Cryptographic tokens, <code>hash_equals()</code></li>
            <li>✓ <strong>Auth:</strong> Deny-by-default, Argon2id hashing</li>
            <li>✓ <strong>Sanitization:</strong> UTF-8 HTML escaping by default</li>
            <li>✓ <strong>Auditing:</strong> Sensitive-masked diagnostic logging</li>
        </ul>
    </div>

    <!-- Runtime Environment Card -->
    <div class="card p-6">
        <div class="flex items-center gap-2" style="margin-bottom: 1rem;">
            <span class="badge badge-warning">Runtime</span>
            <h2 class="text-lg font-bold text-slate-800">System Environment</h2>
        </div>
        <div style="font-size: 0.85rem; color: var(--color-slate-600); line-height: 2;">
            <div class="flex justify-between border-b" style="border-color: var(--color-slate-100); padding: 0.25rem 0;">
                <span>PHP Version:</span>
                <strong><?= $e($phpVersion ?? PHP_VERSION) ?></strong>
            </div>
            <div class="flex justify-between border-b" style="border-color: var(--color-slate-100); padding: 0.25rem 0;">
                <span>Environment:</span>
                <span class="badge badge-info"><?= $e($appEnv ?? 'local') ?></span>
            </div>
            <div class="flex justify-between border-b" style="border-color: var(--color-slate-100); padding: 0.25rem 0;">
                <span>Database Engine:</span>
                <span><?= $e(strtoupper($dbEngine ?? 'MARIADB')) ?></span>
            </div>
            <div class="flex justify-between" style="padding: 0.25rem 0;">
                <span>Health Endpoint:</span>
                <a href="<?= $e($appUrl ?? '/promis/public') ?>/health" class="text-primary-600" target="_blank">
                    <code>GET /health</code> ↗
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Foundation Verification Card -->
<div class="card p-6">
    <h2 class="text-lg font-bold text-slate-800" style="margin-bottom: 0.75rem;">
        Phase 1 Foundation Verification
    </h2>
    <p class="text-slate-600 text-sm" style="margin-bottom: 1.5rem;">
        The foundation provides zero-framework Core PHP 8.x infrastructure ready for subsequent procurement domain modules.
    </p>

    <div class="grid grid-2 gap-4">
        <div class="p-4 rounded" style="background: var(--color-slate-50); border: 1px solid var(--color-slate-200);">
            <div class="font-bold text-slate-800 text-sm" style="margin-bottom: 0.5rem;">Implemented Foundation Subsystems</div>
            <ul style="font-size: 0.85rem; color: var(--color-slate-600); line-height: 1.8; list-style-type: disc; padding-left: 1.25rem;">
                <li>PSR-4 compliant autoloader without Composer dependencies</li>
                <li>Zero-dependency <code>.env</code> file parser and configuration system</li>
                <li>Strict PDO connection singleton with UTF-8 character encoding</li>
                <li>Parameterized HTTP router with REST verbs and middleware pipeline</li>
                <li>BaseController, BaseService, and BaseRepository abstract classes</li>
                <li>Role-based access control and organizational scope authorization primitives</li>
                <li>Encapsulated view engine with path-traversal protection and layouts</li>
            </ul>
        </div>
        <div class="p-4 rounded" style="background: var(--color-slate-50); border: 1px solid var(--color-slate-200);">
            <div class="font-bold text-slate-800 text-sm" style="margin-bottom: 0.5rem;">Subsequent Procurement Modules (Deferred)</div>
            <ul style="font-size: 0.85rem; color: var(--color-slate-600); line-height: 1.8; list-style-type: disc; padding-left: 1.25rem;">
                <li>Annual Procurement Planning & Activity Line Workflows</li>
                <li>Departmental Requisition Submission, Review & Multi-Tier Approval</li>
                <li>Requisition Consolidation & Tender Package Packaging</li>
                <li>Procurement Method Threshold Rules & Sourcing Management</li>
                <li>Contract Administration, Purchase Orders & Inspection/Receiving</li>
                <li>Finance GL Budgetary Reservation & Commitment Integrations</li>
                <li>External GHANEPS Compliance & Audit Trail Logging</li>
            </ul>
        </div>
    </div>
</div>
