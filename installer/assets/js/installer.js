/**
 * Procurement Management CMS — Installer Script
 * Clean Vanilla JavaScript for interactive installation wizard
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize Bootstrap tooltips if available
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Refresh requirements button handler
    const btnRecheck = document.getElementById('btn-recheck-requirements');
    if (btnRecheck) {
        btnRecheck.addEventListener('click', function () {
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Checking...';
            this.disabled = true;
            window.location.reload();
        });
    }
});
