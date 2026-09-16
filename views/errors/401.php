<?php
/**
 * 401 Unauthorized Error View
 * PROMIS - Procurement Management Information System
 * University of Science and Technology, Dedicated (USTED)
 *
 * @var string $message Optional custom error message
 */
$title = 'Please Sign In | PROMIS';
?>

<div class="card p-8 text-center" style="max-width: 600px; margin: 4rem auto;">
    <div style="font-size: 3.5rem; font-weight: 800; color: var(--color-primary-600); line-height: 1; margin-bottom: 1rem;">
        401
    </div>
    <h1 class="text-2xl font-bold text-slate-800" style="margin-bottom: 0.75rem;">
        Please Sign In
    </h1>
    <p class="text-slate-600" style="margin-bottom: 2rem;">
        <?= htmlspecialchars($message ?? 'You must sign in with your staff account to access this page.', ENT_QUOTES, 'UTF-8') ?>
    </p>
    <div class="flex gap-4 justify-center">
        <a href="<?= $e(($appUrl ?? '') . '/login') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem;">
            <i class="fa-solid fa-right-to-bracket"></i>
            <span>Go to Sign In</span>
        </a>
    </div>
</div>
