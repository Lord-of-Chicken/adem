// Profile dropdown toggle and mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileToggle && profileDropdown) {
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isExpanded = profileToggle.getAttribute('aria-expanded') === 'true';
            profileToggle.setAttribute('aria-expanded', !isExpanded);
            profileDropdown.hidden = isExpanded;
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileToggle.setAttribute('aria-expanded', 'false');
                profileDropdown.hidden = true;
            }
        });

        // Close dropdown on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                profileToggle.setAttribute('aria-expanded', 'false');
                profileDropdown.hidden = true;
            }
        });
    }

    // Mobile menu toggle
    const menuToggle = document.getElementById('menuToggle');
    const siteNav = document.querySelector('.site-nav');

    if (menuToggle && siteNav) {
        const backdrop = document.createElement('div');
        backdrop.className = 'site-nav-backdrop';
        document.body.appendChild(backdrop);

        function openMenu() {
            menuToggle.setAttribute('aria-expanded', 'true');
            siteNav.classList.add('site-nav--open');
            backdrop.classList.add('site-nav-backdrop--visible');
            document.body.classList.add('mobile-menu-open');
        }

        function closeMenu() {
            menuToggle.setAttribute('aria-expanded', 'false');
            siteNav.classList.remove('site-nav--open');
            backdrop.classList.remove('site-nav-backdrop--visible');
            document.body.classList.remove('mobile-menu-open');
        }

        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            menuToggle.getAttribute('aria-expanded') === 'true' ? closeMenu() : openMenu();
        });

        backdrop.addEventListener('click', closeMenu);

        siteNav.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', closeMenu);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeMenu();
        });
    }
});
