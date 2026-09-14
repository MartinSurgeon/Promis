/**
 * PROMIS - Procurement Management Information System
 * University of Skills Training and Entrepreneurial Development (USTED)
 * Client-Side Interactive Components & Utilities (Vanilla ES6+)
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Automatic CSRF Token Injection for all Fetch requests
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;

    if (csrfToken && window.fetch) {
        const originalFetch = window.fetch;
        window.fetch = function (resource, options = {}) {
            options = options || {};
            options.headers = options.headers || {};

            const method = (options.method || 'GET').toUpperCase();
            if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
                if (options.headers instanceof Headers) {
                    if (!options.headers.has('X-CSRF-TOKEN')) {
                        options.headers.append('X-CSRF-TOKEN', csrfToken);
                    }
                } else if (Array.isArray(options.headers)) {
                    options.headers.push(['X-CSRF-TOKEN', csrfToken]);
                } else {
                    options.headers['X-CSRF-TOKEN'] = csrfToken;
                }
            }

            return originalFetch(resource, options);
        };
    }

    // 2. Password Visibility Toggle
    const passwordToggles = document.querySelectorAll('[data-toggle="password"]');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = toggle.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            if (!targetInput) return;

            const isPassword = targetInput.type === 'password';
            targetInput.type = isPassword ? 'text' : 'password';
            toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            } else {
                toggle.textContent = isPassword ? 'Hide' : 'Show';
            }
        });
    });

    // 3. Form Submit Loading States & Duplicate Submission Defense
    const forms = document.querySelectorAll('form[data-prevent-duplicate]');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn || submitBtn.disabled) return;

            // Simple HTML5 validation check
            if (!form.checkValidity()) {
                return;
            }

            submitBtn.disabled = true;
            submitBtn.classList.add('btn-loading');
            
            const originalHtml = submitBtn.innerHTML;
            submitBtn.setAttribute('data-original-html', originalHtml);
            submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
        });
    });

    // 4. Modal Dialog Controller
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            const firstInput = modal.querySelector('input:not([type="hidden"]), textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 50);
            }
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    window.PromisModal = {
        open: function(modalId) {
            window.openModal(modalId);
        },
        close: function(modalId) {
            window.closeModal(modalId);
        }
    };

    document.querySelectorAll('[data-modal-close]').forEach(closer => {
        closer.addEventListener('click', (e) => {
            e.preventDefault();
            const modal = closer.closest('.modal');
            if (modal) {
                window.closeModal(modal.id);
            }
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            const modal = backdrop.closest('.modal');
            if (modal) {
                window.closeModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal.active');
            if (activeModal) {
                window.closeModal(activeModal.id);
            }
        }
    });

    // 5. Mobile Sidebar Drawer Toggle
    const sidebar = document.querySelector('.app-sidebar');
    const sidebarToggles = document.querySelectorAll('.sidebar-toggle-btn, #mobileSidebarToggle');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    let overlay = document.querySelector('.sidebar-overlay') || document.getElementById('sidebarBackdrop');

    if (!overlay && sidebar) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    const toggleSidebar = () => {
        if (!sidebar) return;
        sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('active');
    };

    const closeSidebar = () => {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
    };

    sidebarToggles.forEach(btn => btn.addEventListener('click', toggleSidebar));
    if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // 6. Tab Navigation Switching
    const tabButtons = document.querySelectorAll('[data-tab]');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-tab');
            const group = btn.closest('.tabs-container') || document;

            group.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
            group.querySelectorAll('[data-tab-content]').forEach(c => c.style.display = 'none');

            btn.classList.add('active');
            const activeContent = group.querySelector(`[data-tab-content="${target}"]`);
            if (activeContent) {
                activeContent.style.display = 'block';
            }
        });
    });
});
