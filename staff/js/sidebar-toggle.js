/**
 * Sidebar toggle — matches alumni/js/sidebar-toggle.js
 */

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;
    const isMobile = window.innerWidth <= 1024;

    if (!sidebar) return;

    if (isMobile) {
        if (overlay) {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('active');
        } else {
            sidebar.classList.toggle('show');
        }
        if (sidebar.classList.contains('show')) {
            body.classList.add('sidebar-open');
        } else {
            body.classList.remove('sidebar-open');
        }
    } else {
        sidebar.classList.toggle('collapsed');
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('active');
        body.classList.remove('sidebar-open');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;

    sidebarLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 1024) {
                if (link.classList.contains('submenu-toggle')) {
                    return;
                }
                if (sidebar) sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('active');
                body.classList.remove('sidebar-open');
            }
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) {
            if (sidebar) sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('active');
            body.classList.remove('sidebar-open');
        } else if (sidebar) {
            sidebar.classList.remove('collapsed');
        }
    });
});
