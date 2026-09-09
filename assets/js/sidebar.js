/**
 * Sidebar Navigation & Responsive Toggle Handler
 * Procurement Management CMS
 */

document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const mobileBackdrop = document.getElementById('sidebarBackdrop');
    const STORAGE_KEY = 'procure_cms_sidebar_collapsed';

    // Restore desktop collapsed preference from localStorage
    if (window.innerWidth >= 992) {
        const isCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';
        if (isCollapsed) {
            document.body.classList.add('sidebar-collapsed');
        }
    }

    // Toggle sidebar
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            
            if (window.innerWidth < 992) {
                // Mobile behavior: toggle drawer
                document.body.classList.toggle('sidebar-mobile-open');
            } else {
                // Desktop behavior: toggle collapse width
                document.body.classList.toggle('sidebar-collapsed');
                const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem(STORAGE_KEY, isCollapsed ? 'true' : 'false');
            }
        });
    }

    // Close mobile drawer when clicking backdrop
    if (mobileBackdrop) {
        mobileBackdrop.addEventListener('click', function () {
            document.body.classList.remove('sidebar-mobile-open');
        });
    }

    // Handle window resizing
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            document.body.classList.remove('sidebar-mobile-open');
            const isCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';
            if (isCollapsed) {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
        }
    });

    // Initialize Bootstrap tooltips for sidebar if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover'
            });
        });
    }
});
