---
description: Semantic HTML5, Tailwind CSS, Vanilla JS ES6+, Fetch API, mobile-first responsive design, and UI states for PROMIS.
---

# PROMIS Frontend Engineering Workflow

## Purpose

This workflow defines the frontend engineering standards for PROMIS. PROMIS uses modern **Semantic HTML5**, **Tailwind CSS**, **Vanilla JavaScript ES6+**, **Fetch API**, and **Font Awesome**. It is engineered from the ground up to be mobile-first, highly accessible, and fast.

---

## 1. Approved Frontend Tech Stack

- **Markup**: Semantic HTML5 (`<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<form>`, `<table>`, `<footer>`).
- **Styling**: Tailwind CSS utility classes.
  - *No inline style attributes* (e.g. `style="..."` is prohibited except for dynamic numeric percentages in progress bars).
  - Use consistent Tailwind palette classes already present in PROMIS.
- **Scripting**: Vanilla JavaScript ES6+ (modules, async/await, arrow functions, DOM query methods).
  - *Do NOT introduce jQuery, React, Vue, or heavy UI frameworks.*
- **Network Calls**: Native Fetch API with JSON error handling and CSRF header propagation.
- **Icons**: Font Awesome classes (e.g. `<i class="fa-solid fa-check"></i>`).

---

## 2. Mobile-First Responsive Rule

Always design and style starting from the smallest viewport:

```text
Mobile (default) → Tablet (md:) → Desktop (lg:) → Large Desktop (xl:)
```

- **Never simply shrink a desktop UI**: Reorganize layouts using Tailwind responsive grid and flexbox utilities (e.g. `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3`).
- **Touch Targets (Fitts's Law)**: Buttons, inputs, and interactive badges must be easy to tap on touchscreen devices (minimum recommended touch target area: `py-2.5 px-4` or equivalent).
- **Responsive Navigation**: Implement clean, collapsible mobile navigation drawers or bottom bars that expand into top/sidebar navigation on desktop viewports.

---

## 3. Responsive Table Strategies

Procurement data often contains wide tabular rows (item descriptions, quantities, unit prices, totals, planning entities). Handle wide tables gracefully using one or more of these strategies:

1. **Priority Column Visibility**:
   - Show essential columns on mobile (e.g., Reference, Status, Total Amount); hide secondary columns until tablet/desktop (`hidden md:table-cell`).
2. **Horizontal Scrolling Wrapper**:
   - Wrap `<table>` in an overflow container:
     ```html
     <div class="w-full overflow-x-auto rounded-lg border border-slate-200">
         <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
             ...
         </table>
     </div>
     ```
3. **Card/Detail Transformation**:
   - On small screens, transform table rows into stacked card summaries; expand full columns on larger screens (`block md:table-row`).
4. **Collapsible Secondary Information**:
   - Use expandable detail rows (`<details>` or toggled row) for multi-line specifications or approval history.

---

## 4. Required UI States

Every data-driven or interactive component must provide explicit UI feedback for five essential states:

1. **Loading State**:
   - Disable trigger buttons and display a spinner or skeleton screen (e.g. Font Awesome `<i class="fa-solid fa-circle-notch fa-spin"></i>`).
2. **Empty State**:
   - Clear icon, friendly explanation, and an immediate primary CTA when no records exist (e.g., *"No item requests found. Click 'Create Request' to start."*).
3. **Success State**:
   - Unambiguous visual confirmation stating:
     - **What happened** (*"Request submitted successfully."*)
     - **Reference Number** (*"Reference: PR-2026-00125"*)
     - **Current Status** (*"Status: Pending Director Approval"*)
     - **Next Step** (*"Next: Forwarded to Director for review"*)
4. **Error State**:
   - Clear, safe banner explaining what went wrong and how the user can retry or correct it.
5. **Validation State**:
   - Highlight invalid inputs with red borders and provide immediate inline text messages underneath the corresponding fields.

---

## 5. Fetch API Best Practices

When interacting with backend endpoints from Vanilla JS:

```javascript
async function submitRequisition(payload) {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Submitting...';

    try {
        const response = await fetch('/api/requisitions/submit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Submission failed.');
        }

        showSuccessNotification(data.message, data.data.requisition_number);
    } catch (err) {
        showErrorBanner(err.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Submit Request';
    }
}
```

---

## 6. Related Workflows
- Refer to [/promis-ui-ux](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-ui-ux.md) for HCI laws, cognitive load management, and page patterns.
- Refer to [/promis-backend](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-backend.md) for API contract specifications.
