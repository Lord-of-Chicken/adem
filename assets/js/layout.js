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
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', !isExpanded);
            siteNav.classList.toggle('site-nav--open', !isExpanded);
        });

        siteNav.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', function() {
                menuToggle.setAttribute('aria-expanded', 'false');
                siteNav.classList.remove('site-nav--open');
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!menuToggle.contains(e.target) && !siteNav.contains(e.target)) {
                menuToggle.setAttribute('aria-expanded', 'false');
                siteNav.classList.remove('site-nav--open');
            }
        });

        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                menuToggle.setAttribute('aria-expanded', 'false');
                siteNav.classList.remove('site-nav--open');
            }
        });
    }
});
