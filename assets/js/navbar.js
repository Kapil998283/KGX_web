document.addEventListener('DOMContentLoaded', function() {
    // Navbar elements
    const navbar = document.querySelector('[data-nav]');
    const navOpenBtn = document.querySelector('[data-nav-open-btn]');
    const navCloseBtn = document.querySelector('[data-nav-close-btn]');
    const overlay = document.querySelector('[data-overlay]');

    // Function to open navbar
    const openNavbar = function() {
        navbar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    // Function to close navbar
    const closeNavbar = function() {
        navbar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    };

    // Event listeners
    if (navOpenBtn) {
        navOpenBtn.addEventListener('click', openNavbar);
    }

    if (navCloseBtn) {
        navCloseBtn.addEventListener('click', closeNavbar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeNavbar);
    }

    // Close navbar when clicking on a navbar link
    const navbarLinks = document.querySelectorAll('.navbar-link');
    navbarLinks.forEach(link => {
        link.addEventListener('click', closeNavbar);
    });

    // Handle dropdown menus
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.nextElementSibling;
            dropdown.classList.toggle('active');
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            const dropdowns = document.querySelectorAll('.dropdown-menu');
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
}); 