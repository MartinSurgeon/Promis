<?php
/**
 * Application Topbar Partial
 * PROMIS - Procurement Management Information System
 *
 * @var array|null $user
 * @var string $appUrl
 * @var string $pageTitle
 * @var array $breadcrumbs
 */

$currentUser = is_callable($user) ? $user() : ($user ?? []);
$roles = $currentUser['roles'] ?? [];
$primaryRole = !empty($roles) ? $roles[0] : 'STAFF';

$firstName = $currentUser['first_name'] ?? '';
$lastName = $currentUser['last_name'] ?? '';
$fullName = trim($firstName . ' ' . $lastName);
if ($fullName === '') {
    $fullName = $currentUser['username'] ?? 'User';
}
$initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
if ($initials === '') {
    $initials = strtoupper(substr($currentUser['username'] ?? 'U', 0, 2));
}
?>

<header class="app-topbar" style="height: 64px; background: var(--color-surface); border-bottom: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; position: sticky; top: 0; z-index: 30;">
    <!-- Left Section: Mobile Toggle & Contextual Navigation -->
    <div style="display: flex; align-items: center; gap: 1rem;">
        <button type="button" id="mobileSidebarToggle" class="desktop-hidden" style="background: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.5rem 0.625rem; color: var(--color-text); cursor: pointer; font-size: 1rem;" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div>
            <nav aria-label="Breadcrumb" style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; color: var(--color-muted-text); margin-bottom: 0.125rem;">
                <a href="<?= $e($appUrl ?? '') ?>/dashboard" style="color: var(--color-muted-text); text-decoration: none; font-weight: 500;">PROMIS</a>
                <?php if (!empty($breadcrumbs)): ?>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <span style="color: var(--color-muted-text); font-size: 0.6875rem; opacity: 0.6;">/</span>
                        <?php if (!empty($crumb['url'])): ?>
                            <a href="<?= $e($crumb['url']) ?>" style="color: var(--color-muted-text); text-decoration: none; font-weight: 500;"><?= $e($crumb['label']) ?></a>
                        <?php else: ?>
                            <span style="color: var(--color-text); font-weight: 600;"><?= $e($crumb['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </nav>
            <div style="font-size: 0.9375rem; font-weight: 700; color: var(--color-text); line-height: 1.2;">
                <?= $e($pageTitle ?? 'Procurement Workbench') ?>
            </div>
        </div>
    </div>

    <!-- Right Section: User Profile Pill & Sign Out Action -->
    <div style="display: flex; align-items: center; gap: 0.875rem;">
        <!-- User Profile Pill -->
        <div class="user-account-badge" style="display: flex; align-items: center; gap: 0.625rem; padding: 0.25rem 0.625rem 0.25rem 0.375rem; border-radius: 9999px; background: var(--color-surface-secondary); border: 1px solid var(--color-border);">
            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--gradient-brand); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.02em; box-shadow: var(--shadow-sm);">
                <?= $e($initials) ?>
            </div>
            <div class="mobile-hidden" style="display: flex; flex-direction: column; line-height: 1.15; padding-right: 0.25rem;">
                <span style="font-size: 0.8125rem; font-weight: 700; color: var(--color-text);">
                    <?= $e($fullName) ?>
                </span>
                <span style="font-size: 0.6875rem; font-weight: 600; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.04em;">
                    <?= $e(str_replace('_', ' ', $primaryRole)) ?>
                </span>
            </div>
        </div>

        <!-- Sign Out Action -->
        <form action="<?= $e($appUrl ?? '') ?>/logout" method="POST" style="margin: 0;">
            <?= $csrf() ?>
            <button type="submit" class="btn btn-outline" 
                    style="height: 36px; padding: 0 0.75rem; font-size: 0.75rem; font-weight: 600; color: var(--color-danger); border-color: rgba(220, 38, 38, 0.25); display: inline-flex; align-items: center; gap: 0.375rem; border-radius: var(--radius-md);" 
                    title="Sign out of PROMIS"
                    data-confirm="Are you sure you want to end your active session and sign out of PROMIS?"
                    data-confirm-title="Sign Out Confirmation"
                    data-confirm-detail="Any unsaved progress in active forms will be discarded."
                    data-confirm-type="danger"
                    data-confirm-btn="Sign Out"
                    data-confirm-icon="fa-arrow-right-from-bracket">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                <span class="mobile-hidden">Sign Out</span>
            </button>
        </form>
    </div>
</header>
