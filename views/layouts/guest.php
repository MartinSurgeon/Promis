<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken() ?>">
    <title><?= $e($title ?? 'PROMIS - USTED') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $e($appUrl ?? '/promis/public') ?>/css/app.css">
</head>
<body>
    <div class="guest-wrapper">
        <div class="guest-card">
            <div class="guest-header">
                <div style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                    <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        🏛️
                    </div>
                    <div style="text-align: left;">
                        <div style="font-size: 1.35rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1;">PROMIS</div>
                        <div style="font-size: 0.75rem; font-weight: 600; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.05em;">USTED</div>
                    </div>
                </div>
                <div style="font-size: 0.8125rem; opacity: 0.85; margin-top: 0.25rem;">
                    Procurement & Institutional Management System
                </div>
            </div>

            <div class="guest-body">
                <?php if ($hasFlash('success')): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check alert-icon"></i>
                        <div><?= $e($getFlash('success')) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($hasFlash('error')): ?>
                    <div class="alert alert-danger">
                        <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
                        <div><?= $e($getFlash('error')) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($hasFlash('info')): ?>
                    <div class="alert alert-info">
                        <i class="fa-solid fa-circle-info alert-icon"></i>
                        <div><?= $e($getFlash('info')) ?></div>
                    </div>
                <?php endif; ?>

                <?= $content ?>
            </div>

            <div class="guest-footer">
                &copy; <?= date('Y') ?> University of Skills Training and Entrepreneurial Development (USTED).<br>
                Official University Procurement Information Workbench.
            </div>
        </div>
    </div>
    <script src="<?= $e($appUrl ?? '/promis/public') ?>/js/app.js"></script>
</body>
</html>
