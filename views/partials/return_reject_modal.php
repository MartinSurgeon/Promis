<?php
/**
 * Return / Reject Action Modal Dialog Partial
 * PROMIS - Procurement Management Information System
 *
 * Enforces mandatory non-empty explanation for negative workflow decisions.
 *
 * @var int $requisitionId
 * @var string $appUrl
 * @var callable $csrf
 * @var callable $e
 */
?>

<div class="modal" id="workflowDecisionModal" role="dialog" aria-hidden="true" aria-labelledby="modalDecisionTitle">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-content" style="max-width: 520px;">
        <form id="workflowDecisionForm" method="POST" action="" data-prevent-duplicate>
            <?= $csrf() ?>
            <input type="hidden" name="action" id="modalDecisionAction" value="">

            <div class="modal-header">
                <h3 class="modal-title" id="modalDecisionTitle" style="display: flex; align-items: center; gap: 0.5rem;">
                    <i id="modalDecisionIcon" class="fa-solid fa-triangle-exclamation" style="color: var(--color-warning);"></i>
                    <span id="modalDecisionHeading">Send Back or Reject Request</span>
                </h3>
                <button type="button" class="modal-close" data-modal-close aria-label="Close dialog">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body">
                <div id="modalDecisionNotice" class="alert alert-warning" style="margin-bottom: 1rem; font-size: 0.8125rem;">
                    Please provide a reason for this decision.
                </div>

                <div class="form-group">
                    <label for="modalDecisionComment" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.375rem;">
                        Reason for this decision <span style="color: var(--color-danger);">*</span>
                    </label>
                    <textarea 
                        name="comments" 
                        id="modalDecisionComment" 
                        rows="4" 
                        class="form-control" 
                        required 
                        minlength="5"
                        placeholder="Explain what needs to be changed or why this request cannot proceed..." 
                        style="width: 100%; padding: 0.625rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; resize: vertical;"
                    ></textarea>
                    <div style="font-size: 0.75rem; color: var(--color-muted-text); margin-top: 0.25rem;">
                        This note will be saved in the request history so the requester can see it.
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline" data-modal-close>
                    Cancel
                </button>
                <button type="submit" id="modalDecisionSubmitBtn" class="btn btn-danger">
                    <span id="modalDecisionSubmitText">Confirm</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openWorkflowDecisionModal(action, actionUrl) {
    const form = document.getElementById('workflowDecisionForm');
    const actionInput = document.getElementById('modalDecisionAction');
    const title = document.getElementById('modalDecisionHeading');
    const icon = document.getElementById('modalDecisionIcon');
    const submitBtn = document.getElementById('modalDecisionSubmitBtn');
    const submitText = document.getElementById('modalDecisionSubmitText');
    const notice = document.getElementById('modalDecisionNotice');

    form.action = actionUrl;
    actionInput.value = action;

    if (action === 'RETURN') {
        title.textContent = 'Send Back Request for Changes';
        icon.className = 'fa-solid fa-rotate-left';
        icon.style.color = 'var(--color-warning)';
        submitBtn.className = 'btn btn-accent';
        submitText.textContent = 'Send Back to Requester';
        notice.innerHTML = '<strong>Sending Back Request:</strong> The requester will be able to update their request and send it back for review.';
    } else {
        title.textContent = 'Reject Request';
        icon.className = 'fa-solid fa-ban';
        icon.style.color = 'var(--color-danger)';
        submitBtn.className = 'btn btn-danger';
        submitText.textContent = 'Confirm Rejection';
        notice.innerHTML = '<strong>Rejecting Request:</strong> This request will be closed and cannot be approved.';
    }

    if (typeof window.openModal === 'function') {
        window.openModal('workflowDecisionModal');
    } else if (window.PromisModal && typeof window.PromisModal.open === 'function') {
        window.PromisModal.open('workflowDecisionModal');
    } else {
        const m = document.getElementById('workflowDecisionModal');
        if (m) {
            m.classList.add('active');
            m.style.display = 'flex';
        }
    }
}
</script>
