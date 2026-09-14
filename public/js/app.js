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

    // 4. Modal Dialog Controller & Universal Confirmation System
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            const firstInput = modal.querySelector('button.btn-primary, button.btn-danger, button.btn-success, input:not([type="hidden"]), textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 50);
            }
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
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

    // Global Modal Container Builder
    function ensureGlobalConfirmModal() {
        let modal = document.getElementById('promisGlobalConfirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'promisGlobalConfirmModal';
            modal.className = 'modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-hidden', 'true');
            modal.setAttribute('aria-labelledby', 'promisConfirmModalTitle');

            modal.innerHTML = `
                <div class="modal-backdrop" data-modal-close></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <div style="display: flex; align-items: center; gap: 0.875rem;">
                            <div id="promisConfirmIconBadge" class="confirm-icon-badge confirm-icon-primary">
                                <i id="promisConfirmIcon" class="fa-solid fa-circle-question"></i>
                            </div>
                            <div>
                                <h3 id="promisConfirmModalTitle" class="modal-title" style="font-size: 1.0625rem; font-weight: 800;">Confirmation</h3>
                                <div id="promisConfirmSubtitle" style="font-size: 0.75rem; color: var(--color-muted-text); margin-top: 0.125rem;">University Procurement Governance</div>
                            </div>
                        </div>
                        <button type="button" class="modal-close" data-modal-close aria-label="Close dialog">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p id="promisConfirmMessage" style="font-size: 0.9375rem; color: var(--color-text); margin: 0 0 0.75rem 0; line-height: 1.55; font-weight: 500;"></p>
                        <div id="promisConfirmDetail" style="display: none; padding: 0.625rem 0.875rem; border-radius: var(--radius-md); background: var(--color-surface-secondary); border-left: 3px solid var(--color-primary); font-size: 0.8125rem; color: var(--color-muted-text); line-height: 1.5;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="promisConfirmCancelBtn" class="btn btn-outline" style="font-weight: 600; min-height: 38px; padding: 0.5rem 1rem;">
                            Cancel
                        </button>
                        <button type="button" id="promisConfirmActionBtn" class="btn btn-primary" style="font-weight: 700; min-height: 38px; padding: 0.5rem 1.25rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                            <i id="promisConfirmBtnIcon" class="fa-solid fa-check"></i>
                            <span id="promisConfirmBtnText">Confirm</span>
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Bind backdrop & close handlers
            modal.querySelectorAll('[data-modal-close]').forEach(btn => {
                btn.addEventListener('click', () => window.closeModal('promisGlobalConfirmModal'));
            });
        }
        return modal;
    }

    /**
     * Universal HCI Confirmation Dialog
     * @param {Object} options
     * @returns {Promise<boolean>}
     */
    window.PromisConfirm = function(options = {}) {
        return new Promise((resolve) => {
            const modal = ensureGlobalConfirmModal();
            const titleEl = document.getElementById('promisConfirmModalTitle');
            const messageEl = document.getElementById('promisConfirmMessage');
            const detailEl = document.getElementById('promisConfirmDetail');
            const iconBadge = document.getElementById('promisConfirmIconBadge');
            const iconEl = document.getElementById('promisConfirmIcon');
            const cancelBtn = document.getElementById('promisConfirmCancelBtn');
            const actionBtn = document.getElementById('promisConfirmActionBtn');
            const btnIcon = document.getElementById('promisConfirmBtnIcon');
            const btnText = document.getElementById('promisConfirmBtnText');

            const type = options.type || 'primary';
            const title = options.title || 'Please Confirm';
            const message = options.message || 'Are you sure you want to proceed with this action?';
            const detail = options.detail || '';
            const confirmText = options.confirmText || 'Confirm';
            const cancelText = options.cancelText || 'Cancel';
            const customIcon = options.icon || null;

            titleEl.textContent = title;
            messageEl.textContent = message;

            if (detail) {
                detailEl.textContent = detail;
                detailEl.style.display = 'block';
            } else {
                detailEl.style.display = 'none';
            }

            // Configure type-specific styles and icons
            iconBadge.className = 'confirm-icon-badge';
            if (type === 'danger') {
                iconBadge.classList.add('confirm-icon-danger');
                iconEl.className = customIcon ? `fa-solid ${customIcon}` : 'fa-solid fa-triangle-exclamation';
                actionBtn.className = 'btn btn-danger';
                detailEl.style.borderLeftColor = 'var(--color-danger)';
            } else if (type === 'warning') {
                iconBadge.classList.add('confirm-icon-warning');
                iconEl.className = customIcon ? `fa-solid ${customIcon}` : 'fa-solid fa-triangle-exclamation';
                actionBtn.className = 'btn btn-accent';
                detailEl.style.borderLeftColor = 'var(--color-warning)';
            } else if (type === 'success') {
                iconBadge.classList.add('confirm-icon-success');
                iconEl.className = customIcon ? `fa-solid ${customIcon}` : 'fa-solid fa-circle-check';
                actionBtn.className = 'btn btn-success';
                detailEl.style.borderLeftColor = 'var(--color-success)';
            } else {
                iconBadge.classList.add('confirm-icon-primary');
                iconEl.className = customIcon ? `fa-solid ${customIcon}` : 'fa-solid fa-circle-question';
                actionBtn.className = 'btn btn-primary';
                detailEl.style.borderLeftColor = 'var(--color-primary)';
            }

            btnText.textContent = confirmText;
            if (customIcon) {
                btnIcon.className = `fa-solid ${customIcon}`;
                btnIcon.style.display = 'inline-block';
            } else {
                btnIcon.className = 'fa-solid fa-check';
                btnIcon.style.display = 'inline-block';
            }

            cancelBtn.textContent = cancelText;
            cancelBtn.style.display = 'inline-block';

            // Clean event handlers
            const newActionBtn = actionBtn.cloneNode(true);
            actionBtn.parentNode.replaceChild(newActionBtn, actionBtn);

            const newCancelBtn = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

            newActionBtn.addEventListener('click', () => {
                window.closeModal('promisGlobalConfirmModal');
                if (typeof options.onConfirm === 'function') options.onConfirm();
                resolve(true);
            });

            newCancelBtn.addEventListener('click', () => {
                window.closeModal('promisGlobalConfirmModal');
                if (typeof options.onCancel === 'function') options.onCancel();
                resolve(false);
            });

            window.openModal('promisGlobalConfirmModal');
        });
    };

    /**
     * Universal HCI Alert Dialog (Replaces native alert)
     * @param {Object} options
     * @returns {Promise<void>}
     */
    window.PromisAlert = function(options = {}) {
        return new Promise((resolve) => {
            const modal = ensureGlobalConfirmModal();
            const titleEl = document.getElementById('promisConfirmModalTitle');
            const messageEl = document.getElementById('promisConfirmMessage');
            const detailEl = document.getElementById('promisConfirmDetail');
            const iconBadge = document.getElementById('promisConfirmIconBadge');
            const iconEl = document.getElementById('promisConfirmIcon');
            const cancelBtn = document.getElementById('promisConfirmCancelBtn');
            const actionBtn = document.getElementById('promisConfirmActionBtn');
            const btnIcon = document.getElementById('promisConfirmBtnIcon');
            const btnText = document.getElementById('promisConfirmBtnText');

            const type = options.type || 'info';
            const title = options.title || 'Notice';
            const message = options.message || '';
            const detail = options.detail || '';
            const btnLabel = options.btnText || 'Understood';
            const customIcon = options.icon || null;

            titleEl.textContent = title;
            messageEl.textContent = message;

            if (detail) {
                detailEl.textContent = detail;
                detailEl.style.display = 'block';
            } else {
                detailEl.style.display = 'none';
            }

            iconBadge.className = 'confirm-icon-badge';
            if (type === 'danger') {
                iconBadge.classList.add('confirm-icon-danger');
                iconEl.className = customIcon ? `fa-solid ${customIcon}` : 'fa-solid fa-circle-xmark';
                actionBtn.className = 'btn btn-danger';
            } else if (type === 'warning') {
                iconBadge.classList.add('confirm-icon-warning');
                iconEl.className = customIcon ? `fa-solid ${customIcon}` : 'fa-solid fa-triangle-exclamation';
                actionBtn.className = 'btn btn-accent';
            } else if (type === 'success') {
                iconBadge.classList.add('confirm-icon-success');
                iconEl.className = customIcon ? `fa-solid ${customIcon}` : 'fa-solid fa-circle-check';
                actionBtn.className = 'btn btn-success';
            } else {
                iconBadge.classList.add('confirm-icon-info');
                iconEl.className = customIcon ? `fa-solid ${customIcon}` : 'fa-solid fa-circle-info';
                actionBtn.className = 'btn btn-primary';
            }

            btnText.textContent = btnLabel;
            btnIcon.className = 'fa-solid fa-check';
            cancelBtn.style.display = 'none'; // Hide cancel on pure alert

            const newActionBtn = actionBtn.cloneNode(true);
            actionBtn.parentNode.replaceChild(newActionBtn, actionBtn);

            newActionBtn.addEventListener('click', () => {
                window.closeModal('promisGlobalConfirmModal');
                resolve();
            });

            window.openModal('promisGlobalConfirmModal');
        });
    };

    // Declarative Confirmation Listener for Elements with [data-confirm]
    document.addEventListener('click', async (e) => {
        const target = e.target.closest('[data-confirm]');
        if (!target) return;

        const confirmMessage = target.getAttribute('data-confirm');
        if (!confirmMessage) return;

        e.preventDefault();
        e.stopPropagation();

        const title = target.getAttribute('data-confirm-title') || 'Confirmation Required';
        const detail = target.getAttribute('data-confirm-detail') || '';
        const type = target.getAttribute('data-confirm-type') || 'primary';
        const confirmText = target.getAttribute('data-confirm-btn') || 'Yes, Proceed';
        const cancelText = target.getAttribute('data-confirm-cancel') || 'Cancel';
        const icon = target.getAttribute('data-confirm-icon') || null;

        const confirmed = await window.PromisConfirm({
            title,
            message: confirmMessage,
            detail,
            type,
            confirmText,
            cancelText,
            icon
        });

        if (confirmed) {
            // If the element is a button inside a form, or is a submit button, submit its form
            if (target.tagName === 'BUTTON' || target.tagName === 'INPUT') {
                const form = target.closest('form');
                if (form) {
                    // Append submit button value if present
                    if (target.name && target.value) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = target.name;
                        hidden.value = target.value;
                        form.appendChild(hidden);
                    }
                    HTMLFormElement.prototype.submit.call(form);
                    return;
                }
            } else if (target.tagName === 'A' && target.href) {
                window.location.href = target.href;
            }
        }
    }, true);

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
