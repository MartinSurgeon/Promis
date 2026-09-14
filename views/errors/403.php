<?php
/**
 * 403 Forbidden Error View
 * PROMIS - Procurement Management Information System
 * University of Science and Technology, Dedicated (USTED)
 *
 * @var string $message Optional custom error message
 */
$title = '403 - Forbidden | PROMIS';
?>

<div class="card p-8 text-center" style="max-width: 600px; margin: 4rem auto;">
    <div style="font-size: 3.5rem; font-weight: 800; color: var(--color-danger-600); line-height: 1; margin-bottom: 1rem;">
        403
    </div>
    <h1 class="text-2xl font-bold text-slate-800" style="margin-bottom: 0.75rem;">
        Access Denied
    </h1>
    <p class="text-slate-600" style="margin-bottom: 2rem;">
        <?= htmlspecialchars($message ?? 'You do not have the required role permissions or organizational scope to perform this action.', ENT_QUOTES, 'UTF-8') ?>
    </p>
    <div class="flex gap-4 justify-center">
        <a href="<?= $e(($appUrl ?? '') . '/dashboard') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem;">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Return to Dashboard</span>
        </a>
    </div>
</div>
