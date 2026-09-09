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

    // Refresh requirements button handler (Step 1)
    const btnRecheck = document.getElementById('btn-recheck-requirements');
    if (btnRecheck) {
        btnRecheck.addEventListener('click', function () {
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Checking...';
            this.disabled = true;
            window.location.reload();
        });
    }

    // Live AJAX Database Connection Tester (Step 2)
    const btnTestDb = document.getElementById('btn-test-db-connection');
    const alertBox = document.getElementById('connection-status-alert');
    const alertIcon = document.getElementById('connection-status-icon');
    const alertText = document.getElementById('connection-status-text');

    if (btnTestDb && alertBox) {
        btnTestDb.addEventListener('click', function () {
            const host = document.getElementById('db_host').value.trim();
            const port = document.getElementById('db_port').value.trim();
            const name = document.getElementById('db_name').value.trim();
            const user = document.getElementById('db_user').value.trim();
            const pass = document.getElementById('db_pass').value;
            const csrfInput = document.querySelector('input[name="installer_csrf"]');
            const csrfToken = csrfInput ? csrfInput.value : '';

            if (!host || !name || !user) {
                alertBox.className = 'alert alert-warning mb-4';
                alertIcon.className = 'bi bi-exclamation-circle-fill text-warning';
                alertText.textContent = 'Please provide Database Host, Database Name, and Username before testing.';
                alertBox.classList.remove('d-none');
                return;
            }

            const originalHtml = btnTestDb.innerHTML;
            btnTestDb.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Connecting...';
            btnTestDb.disabled = true;

            const formData = new FormData();
            formData.append('ajax_action', 'test_connection');
            formData.append('installer_csrf', csrfToken);
            formData.append('db_host', host);
            formData.append('db_port', port);
            formData.append('db_name', name);
            formData.append('db_user', user);
            formData.append('db_pass', pass);

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                btnTestDb.innerHTML = originalHtml;
                btnTestDb.disabled = false;

                if (data.success) {
                    alertBox.className = 'alert alert-success mb-4';
                    alertIcon.className = 'bi bi-check-circle-fill text-success';
                    alertText.textContent = data.message || 'Connection successful! Database is online and accessible.';
                } else {
                    alertBox.className = 'alert alert-danger mb-4';
                    alertIcon.className = 'bi bi-x-circle-fill text-danger';
                    alertText.textContent = data.message || 'Database connection failed.';
                }
                alertBox.classList.remove('d-none');
            })
            .catch(function (error) {
                btnTestDb.innerHTML = originalHtml;
                btnTestDb.disabled = false;

                alertBox.className = 'alert alert-danger mb-4';
                alertIcon.className = 'bi bi-exclamation-triangle-fill text-danger';
                alertText.textContent = 'Network or server error while testing database connection.';
                alertBox.classList.remove('d-none');
            });
        });
    }

    // Step 3: SQL Source Toggle
    const radioBundled = document.getElementById('source_bundled');
    const radioUpload = document.getElementById('source_upload');
    const uploadContainer = document.getElementById('upload-container');

    if (radioBundled && radioUpload && uploadContainer) {
        radioBundled.addEventListener('change', function () {
            if (this.checked) {
                uploadContainer.classList.add('d-none');
            }
        });

        radioUpload.addEventListener('change', function () {
            if (this.checked) {
                uploadContainer.classList.remove('d-none');
            }
        });
    }

    // Form Submit Loading Spinners
    const forms = ['database-form', 'import-form', 'admin-form'];
    forms.forEach(function (formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function () {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
                }
            });
        }
    });
});
