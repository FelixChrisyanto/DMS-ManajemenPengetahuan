document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.querySelector('.wrapper');
    const sidebarToggle = document.querySelector('#sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const content = document.querySelector('.content-wrapper');

    // Sidebar Toggle Logic
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            wrapper.classList.toggle('sidebar-collapsed');
            
            // Save state to localStorage
            const isCollapsed = wrapper.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
    }

    // Restore Sidebar State
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        wrapper.classList.add('sidebar-collapsed');
    }

    // Active Menu Highlighting
    const currentPath = window.location.pathname.split('/').pop() || 'index.php';
    const menuLinks = document.querySelectorAll('.sidebar-link');
    
    menuLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPath) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // Tooltip / Dropdown helpers (Optional placeholder for UI)
    console.log('PT Lintas Nusantara Ekspedisi - DMS UI Loaded');
});

// Toast Notification Helper
function showToast(message, type = 'info') {
    // Basic toast implementation if needed
    console.log(`[${type.toUpperCase()}] ${message}`);
}
