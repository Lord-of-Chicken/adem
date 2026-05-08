function initFlashes() {
    document.querySelectorAll('.flash:not([data-flash-init])').forEach(flash => {
        flash.setAttribute('data-flash-init', '1');

        const btn = document.createElement('button');
        btn.className = 'flash__close';
        btn.innerHTML = '&times;';
        btn.setAttribute('aria-label', 'Fermer');
        flash.appendChild(btn);

        function dismiss() {
            flash.classList.add('flash--dismissed');
            flash.addEventListener('animationend', () => flash.remove(), { once: true });
        }

        btn.addEventListener('click', dismiss);
        setTimeout(dismiss, 5000);
    });
}

function initLayout() {
    // Mobile menu toggle
    const menuToggle = document.getElementById('menuToggle');
    const siteNav = document.querySelector('.site-nav');

    if (menuToggle && siteNav) {
        // Remove existing backdrop to avoid duplicates on Turbo navigation
        document.querySelectorAll('.site-nav-backdrop').forEach(el => el.remove());

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

    // Language dropdowns
    document.querySelectorAll('.lang-dropdown__toggle').forEach(toggle => {
        // Clone to remove any stale listeners from previous Turbo visit
        const fresh = toggle.cloneNode(true);
        toggle.parentNode.replaceChild(fresh, toggle);

        fresh.addEventListener('click', function(e) {
            e.stopPropagation();
            const isExpanded = fresh.getAttribute('aria-expanded') === 'true';
            // Close all dropdowns
            document.querySelectorAll('.lang-dropdown__toggle').forEach(t => {
                t.setAttribute('aria-expanded', 'false');
                t.closest('.lang-dropdown').querySelector('.lang-dropdown__menu').hidden = true;
            });
            if (!isExpanded) {
                fresh.setAttribute('aria-expanded', 'true');
                fresh.closest('.lang-dropdown').querySelector('.lang-dropdown__menu').hidden = false;
            }
        });
    });
}

function closeLangDropdowns() {
    document.querySelectorAll('.lang-dropdown__toggle').forEach(t => {
        t.setAttribute('aria-expanded', 'false');
        const menu = t.closest('.lang-dropdown')?.querySelector('.lang-dropdown__menu');
        if (menu) menu.hidden = true;
    });
}

document.addEventListener('click', closeLangDropdowns);
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLangDropdowns();
});

// turbo:load fires on both initial load and Turbo navigations
document.addEventListener('turbo:load', initLayout);
document.addEventListener('turbo:load', initFlashes);

// Fallback if Turbo is absent
if (typeof Turbo === 'undefined') {
    document.addEventListener('DOMContentLoaded', initLayout);
    document.addEventListener('DOMContentLoaded', initFlashes);
}
