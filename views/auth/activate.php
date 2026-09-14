<?php
/**
 * Institutional Account Activation / First-Time Setup View
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
        Activate Institutional Account
    </h2>
    <p style="font-size: 0.875rem; color: var(--color-muted-text); margin: 0;">
        First-time setup for administrator-provisioned staff accounts
    </p>
</div>

<form action="<?= $e($appUrl ?? '') ?>/activate" method="POST" data-prevent-duplicate>
    <?= $csrf() ?>

    <div class="form-group" style="margin-bottom: 1.25rem;">
        <label for="username_or_email" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
            Institutional Username or Email <span style="color: var(--color-danger);">*</span>
        </label>
        <div style="position: relative;">
            <span style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--color-muted-text); font-size: 0.875rem;">
                <i class="fa-solid fa-id-badge"></i>
            </span>
            <input 
                type="text" 
                id="username_or_email" 
                name="username_or_email" 
                class="form-control" 
                required 
                autofocus
                placeholder="Staff ID or registered email" 
                style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; outline: none;"
            >
        </div>
    </div>

    <div class="form-group" style="margin-bottom: 1.25rem;">
        <label for="activation_code" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
            Activation Code / Token <span style="color: var(--color-danger);">*</span>
        </label>
        <div style="position: relative;">
            <span style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--color-muted-text); font-size: 0.875rem;">
                <i class="fa-solid fa-key"></i>
            </span>
            <input 
                type="text" 
                id="activation_code" 
                name="activation_code" 
                class="form-control" 
                required
                placeholder="e.g. ACT-2026 or institutional code" 
                style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; outline: none;"
            >
        </div>
    </div>

    <div class="form-group" style="margin-bottom: 1.25rem;">
        <label for="password" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
            Set New Password <span style="color: var(--color-danger);">*</span>
        </label>
        <div style="position: relative;">
            <span style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--color-muted-text); font-size: 0.875rem;">
                <i class="fa-solid fa-lock"></i>
            </span>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="form-control" 
                required 
                minlength="8"
                placeholder="Minimum 8 characters" 
                style="width: 100%; padding: 0.625rem 2.5rem 0.625rem 2.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; outline: none;"
            >
            <button 
                type="button" 
                data-toggle="password" 
                data-target="password" 
                style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--color-muted-text); cursor: pointer; padding: 0.25rem; font-size: 0.875rem;"
                title="Show or hide password"
            >
                <i class="fa-solid fa-eye"></i>
            </button>
        </div>
    </div>

    <div class="form-group" style="margin-bottom: 1.5rem;">
        <label for="password_confirmation" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
            Confirm Password <span style="color: var(--color-danger);">*</span>
        </label>
        <div style="position: relative;">
            <span style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--color-muted-text); font-size: 0.875rem;">
                <i class="fa-solid fa-shield-halved"></i>
            </span>
            <input 
                type="password" 
                id="password_confirmation" 
                name="password_confirmation" 
                class="form-control" 
                required 
                minlength="8"
                placeholder="Re-enter password" 
                style="width: 100%; padding: 0.625rem 2.5rem 0.625rem 2.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; outline: none;"
            >
            <button 
                type="button" 
                data-toggle="password" 
                data-target="password_confirmation" 
                style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--color-muted-text); cursor: pointer; padding: 0.25rem; font-size: 0.875rem;"
                title="Show or hide password"
            >
                <i class="fa-solid fa-eye"></i>
            </button>
        </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: 600; font-size: 0.9375rem; justify-content: center; box-shadow: var(--shadow-sm); margin-bottom: 1.25rem;">
        <i class="fa-solid fa-user-shield" style="margin-right: 0.5rem;"></i> Activate My Account
    </button>
</form>

<div style="text-align: center; border-top: 1px solid var(--color-border); padding-top: 1.25rem;">
    <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin-bottom: 0.5rem;">
        Already activated your account?
    </p>
    <a href="<?= $e($appUrl ?? '') ?>/login" class="btn btn-outline" style="width: 100%; font-size: 0.8125rem; padding: 0.5rem; justify-content: center;">
        <i class="fa-solid fa-arrow-left" style="margin-right: 0.5rem;"></i> Back to Sign In
    </a>
</div>
