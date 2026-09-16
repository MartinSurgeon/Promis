<?php
/**
 * Institutional User Login View
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
        Sign In
    </h2>
    <p style="font-size: 0.875rem; color: var(--color-muted-text); margin: 0;">
        Sign in to access your purchase requests and approvals
    </p>
</div>

<form action="<?= $e($appUrl ?? '') ?>/login" method="POST" data-prevent-duplicate>
    <?= $csrf() ?>

    <div class="form-group" style="margin-bottom: 1.25rem;">
        <label for="username_or_email" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
            Staff Username or Email <span style="color: var(--color-danger);">*</span>
        </label>
        <div style="position: relative;">
            <span style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--color-muted-text); font-size: 0.875rem;">
                <i class="fa-solid fa-user"></i>
            </span>
            <input 
                type="text" 
                id="username_or_email" 
                name="username_or_email" 
                class="form-control" 
                required 
                autofocus
                placeholder="e.g. staff.id or name@usted.edu.gh" 
                style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; outline: none;"
            >
        </div>
    </div>

    <div class="form-group" style="margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.375rem;">
            <label for="password" class="form-label" style="font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin: 0;">
                Password <span style="color: var(--color-danger);">*</span>
            </label>
            <a href="<?= $e($appUrl ?? '') ?>/forgot-password" style="font-size: 0.75rem; color: var(--color-primary); font-weight: 600; text-decoration: none;">
                Forgot password?
            </a>
        </div>
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
                placeholder="Enter your password" 
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

    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: 600; font-size: 0.9375rem; justify-content: center; box-shadow: var(--shadow-sm); margin-bottom: 1.25rem;">
        <i class="fa-solid fa-right-to-bracket" style="margin-right: 0.5rem;"></i> Sign In
    </button>
</form>

<div style="text-align: center; border-top: 1px solid var(--color-border); padding-top: 1.25rem; margin-bottom: 1.25rem;">
    <p style="font-size: 0.8125rem; color: var(--color-muted-text); margin-bottom: 0.5rem;">
        New staff or first-time account setup?
    </p>
    <a href="<?= $e($appUrl ?? '') ?>/activate" class="btn btn-outline" style="width: 100%; font-size: 0.8125rem; padding: 0.5rem; justify-content: center;">
        <i class="fa-solid fa-user-check" style="margin-right: 0.5rem;"></i> Activate Account
    </a>
</div>

<!-- Sample Staff Accounts for Testing Panel -->
<div class="card" style="background: var(--color-surface-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.875rem; text-align: left;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
        <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-primary); display: flex; align-items: center; gap: 0.375rem;">
            <i class="fa-solid fa-user-group"></i> Sample Staff Accounts for Testing
        </span>
        <span style="font-size: 0.6875rem; color: var(--color-muted-text); font-weight: 600;">
            Password: <code>Password123!</code>
        </span>
    </div>
    <p style="font-size: 0.75rem; color: var(--color-muted-text); margin: 0 0 0.5rem 0;">
        Select a university role to test the system:
    </p>
    <div style="display: flex; flex-wrap: wrap; gap: 0.375rem;">
        <button type="button" class="btn btn-outline" onclick="fillCredentials('admin.user', 'Password123!')" style="font-size: 0.6875rem; padding: 0.25rem 0.5rem; background: var(--color-surface); border-radius: var(--radius-sm);" title="Role: System Administrator">
            <i class="fa-solid fa-shield-halved" style="color: var(--color-primary);"></i> Admin
        </button>
        <button type="button" class="btn btn-outline" onclick="fillCredentials('kwame.mensah', 'Password123!')" style="font-size: 0.6875rem; padding: 0.25rem 0.5rem; background: var(--color-surface); border-radius: var(--radius-sm);" title="Role: Head of Department (HOD)">
            <i class="fa-solid fa-user-tie" style="color: #0284c7;"></i> Head of Dept
        </button>
        <button type="button" class="btn btn-outline" onclick="fillCredentials('dean.user', 'Password123!')" style="font-size: 0.6875rem; padding: 0.25rem 0.5rem; background: var(--color-surface); border-radius: var(--radius-sm);" title="Role: Faculty Dean">
            <i class="fa-solid fa-graduation-cap" style="color: #7c3aed;"></i> Dean
        </button>
        <button type="button" class="btn btn-outline" onclick="fillCredentials('finance.user', 'Password123!')" style="font-size: 0.6875rem; padding: 0.25rem 0.5rem; background: var(--color-surface); border-radius: var(--radius-sm);" title="Role: Finance Officer">
            <i class="fa-solid fa-vault" style="color: #16a34a;"></i> Finance
        </button>
        <button type="button" class="btn btn-outline" onclick="fillCredentials('procurement.user', 'Password123!')" style="font-size: 0.6875rem; padding: 0.25rem 0.5rem; background: var(--color-surface); border-radius: var(--radius-sm);" title="Role: Procurement Officer">
            <i class="fa-solid fa-boxes-packing" style="color: #ea580c;"></i> Procurement
        </button>
    </div>
</div>

<script>
function fillCredentials(username, password) {
    var u = document.getElementById('username_or_email');
    var p = document.getElementById('password');
    if (u) { u.value = username; u.focus(); }
    if (p) { p.value = password; }
}
</script>
