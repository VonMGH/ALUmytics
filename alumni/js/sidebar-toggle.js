/**
 * Sidebar Toggle Functionality for Mobile Devices
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
        // Prevent body scroll when sidebar is open (mobile)
        if (sidebar.classList.contains('show')) {
            body.classList.add('sidebar-open');
        } else {
            body.classList.remove('sidebar-open');
        }
    } else {
        // Desktop: collapse/expand by toggling class on the sidebar element
        sidebar.classList.toggle('collapsed');
        // Clear mobile-only classes on desktop
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('active');
        body.classList.remove('sidebar-open');
    }
}

// Close sidebar when clicking on a link (for better UX on mobile)
document.addEventListener('DOMContentLoaded', function() {
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;
    
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Only close on mobile screens
            if (window.innerWidth <= 1024) {
                if (sidebar) sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('active');
                body.classList.remove('sidebar-open');
            }
        });
    });
    
    // Handle responsive transitions
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            if (sidebar) sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('active');
            body.classList.remove('sidebar-open');
        } else {
            // When switching to mobile, clear desktop collapsed state on sidebar
            if (sidebar) sidebar.classList.remove('collapsed');
        }
    });
});
