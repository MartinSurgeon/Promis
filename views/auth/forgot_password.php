<?php
/**
 * Password Recovery Request View
 * PROMIS - Procurement Management Information System
 *
 * @var string $title
 * @var string $appUrl
 * @var callable $csrf
 * @var callable $e
 */
?>

<div style="margin-bottom: 1.5rem; text-align: center;">
    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.25rem;">
        Password Recovery
    </h2>
    <p style="font-size: 0.875rem; color: var(--color-muted-text); margin: 0;">
        Enter your institutional username or email address to request a secure password reset link
    </p>
</div>

<form action="<?= $e($appUrl ?? '') ?>/forgot-password" method="POST" data-prevent-duplicate>
    <?= $csrf() ?>

    <div class="form-group" style="margin-bottom: 1.5rem;">
        <label for="username_or_email" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
            Institutional Username or Email <span style="color: var(--color-danger);">*</span>
        </label>
        <div style="position: relative;">
            <span style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--color-muted-text); font-size: 0.875rem;">
                <i class="fa-solid fa-envelope"></i>
            </span>
            <input 
                type="text" 
                id="username_or_email" 
                name="username_or_email" 
                class="form-control" 
                required 
                autofocus
                placeholder="Staff ID or institutional email" 
                style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; outline: none;"
            >
        </div>
        <p style="font-size: 0.75rem; color: var(--color-muted-text); margin-top: 0.375rem;">
            If the account exists, recovery instructions will be dispatched to your registered institutional address.
        </p>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: 600; font-size: 0.9375rem; justify-content: center; box-shadow: var(--shadow-sm); margin-bottom: 1.25rem;">
        <i class="fa-solid fa-paper-plane" style="margin-right: 0.5rem;"></i> Send Recovery Instructions
    </button>
</form>

<div style="text-align: center; border-top: 1px solid var(--color-border); padding-top: 1.25rem;">
    <a href="<?= $e($appUrl ?? '') ?>/login" class="btn btn-outline" style="width: 100%; font-size: 0.8125rem; padding: 0.5rem; justify-content: center;">
        <i class="fa-solid fa-arrow-left" style="margin-right: 0.5rem;"></i> Back to Sign In
    </a>
</div>
