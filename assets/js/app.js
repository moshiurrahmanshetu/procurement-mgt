/**
 * General Application UI Helpers
 * Procurement Management CMS
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Password Visibility Toggle
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const targetInputId = this.getAttribute('data-target');
            const targetInput = targetInputId ? document.getElementById(targetInputId) : this.closest('.auth-input-group').querySelector('input');
            const icon = this.querySelector('i');

            if (targetInput) {
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    if (icon) {
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                } else {
                    targetInput.type = 'password';
                    if (icon) {
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                }
            }
        });
    });

    // 2. Auto-dismiss alerts after 6 seconds
    const autoDismissAlerts = document.querySelectorAll('.alert-dismissible');
    if (autoDismissAlerts.length > 0) {
        setTimeout(function () {
            autoDismissAlerts.forEach(function (alert) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }
            });
        }, 6000);
    }

    // 3. Avatar Upload Live Preview
    const avatarFileInput = document.getElementById('avatarFileInput');
    const avatarPreviewImg = document.getElementById('avatarPreview');

    if (avatarFileInput && avatarPreviewImg) {
        avatarFileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Selected image exceeds 2MB limit. Please choose a smaller image.');
                    avatarFileInput.value = '';
                    return;
                }

                // Check file type
                const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Invalid file format. Please upload JPG, PNG, or WEBP.');
                    avatarFileInput.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    avatarPreviewImg.src = event.target.result;
                    avatarPreviewImg.classList.remove('d-none');
                    
                    const avatarFallbackEl = document.getElementById('avatarFallback');
                    if (avatarFallbackEl) {
                        avatarFallbackEl.classList.add('d-none');
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
