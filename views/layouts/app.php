<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken() ?>">
    <title><?= $e($title ?? 'PROMIS - USTED Procurement Management') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $e($appUrl ?? '/promis/public') ?>/css/app.css">
</head>
<body style="background-color: var(--color-background); color: var(--color-text); font-family: 'Inter', sans-serif;">
    <a href="#mainContent" class="skip-link">Skip to main content</a>
    <div class="app-layout" style="display: flex; min-height: 100vh;">
        <!-- Backdrop for mobile drawer -->
        <div id="sidebarBackdrop" class="sidebar-backdrop"></div>

        <!-- Role-Aware Sidebar Navigation -->
        <?php 
        $userObj = $user();
        require dirname(__DIR__) . '/partials/sidebar.php'; 
        ?>

        <!-- Main Content Area -->
        <div class="app-main" style="flex: 1; display: flex; flex-direction: column; min-width: 0;">
            <!-- Topbar -->
            <?php require dirname(__DIR__) . '/partials/topbar.php'; ?>

            <!-- Page Body -->
            <main id="mainContent" class="app-content" style="flex: 1; padding: 1.5rem; max-width: 1400px; width: 100%; margin: 0 auto;">
                <!-- Flash Alerts -->
                <?php if ($hasFlash('success')): ?>
                    <div class="alert alert-success" style="margin-bottom: 1.25rem;">
                        <i class="fa-solid fa-circle-check alert-icon"></i>
                        <div><?= $e($getFlash('success')) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($hasFlash('error')): ?>
                    <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
                        <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
                        <div><?= $e($getFlash('error')) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($hasFlash('info')): ?>
                    <div class="alert alert-info" style="margin-bottom: 1.25rem;">
                        <i class="fa-solid fa-circle-info alert-icon"></i>
                        <div><?= $e($getFlash('info')) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($hasFlash('warning')): ?>
                    <div class="alert alert-warning" style="margin-bottom: 1.25rem;">
                        <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
                        <div><?= $e($getFlash('warning')) ?></div>
                    </div>
                <?php endif; ?>

                <?= $content ?>
            </main>

            <!-- Institutional Footer -->
            <footer style="padding: 1rem 1.5rem; border-top: 1px solid var(--color-border); font-size: 0.75rem; color: var(--color-muted-text); display: flex; justify-content: space-between; align-items: center; flex-wrap: gap: 0.5rem;">
                <div>
                    &copy; <?= date('Y') ?> University of Skills Training and Entrepreneurial Development (USTED). All rights reserved.
                </div>
                <div style="display: flex; gap: 1rem;">
                    <span>PROMIS Core v2.4</span>
                    <span>•</span>
                    <span>Security & Audit Active</span>
                </div>
            </footer>
        </div>
    </div>

    <!-- Application JavaScript Client -->
    <script src="<?= $e($appUrl ?? '/promis/public') ?>/js/app.js"></script>
</body>
</html>
