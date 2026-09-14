<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken() ?>">
    <title><?= $e($title ?? 'PROMIS - USTED') ?></title>
    <link rel="stylesheet" href="<?= $e($appUrl ?? '/promis/public') ?>/css/app.css">
</head>
<body>
    <a href="#mainContent" class="skip-link">Skip to main content</a>
    <header class="site-header">
        <div class="header-container">
            <div>
                <div class="brand-title">
                    <span>🏛️ PROMIS</span>
                    <span class="badge badge-primary">USTED</span>
                </div>
                <div class="brand-subtitle">Procurement Management Information System</div>
            </div>

            <div class="orientation-triad" style="display: flex; align-items: center; gap: 0.625rem;">
                <a href="<?= $e(($appUrl ?? '') . '/dashboard') ?>" class="btn btn-outline" style="font-size: 0.8125rem; padding: 0.4rem 0.75rem; text-decoration: none; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem;">
                    <i class="fa-solid fa-gauge"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= $e(($appUrl ?? '') . '/requisitions') ?>" class="btn btn-outline" style="font-size: 0.8125rem; padding: 0.4rem 0.75rem; text-decoration: none; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.375rem;">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Requisitions</span>
                </a>
                <a href="<?= $e(($appUrl ?? '') . '/requisitions/create') ?>" class="btn btn-primary" style="font-size: 0.8125rem; padding: 0.4rem 0.875rem; text-decoration: none; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 0.375rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>Make a Requisition</span>
                </a>
            </div>
        </div>
    </header>

    <main id="mainContent" class="main-wrapper">
        <?php if ($hasFlash('success')): ?>
            <div class="alert alert-success">
                <?= $e($getFlash('success')) ?>
            </div>
        <?php endif; ?>

        <?php if ($hasFlash('error')): ?>
            <div class="alert alert-danger">
                <?= $e($getFlash('error')) ?>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> University of Science and Technology, Dedicated (USTED). All rights reserved.</p>
        <p style="margin-top: 0.25rem; font-size: 0.8rem;">PROMIS 5-Tier Layered Architecture Foundation</p>
    </footer>

    <script src="<?= $e($appUrl ?? '/promis/public') ?>/js/app.js"></script>
</body>
</html>
