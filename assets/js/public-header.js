(function () {
    'use strict';

    function initPublicHeader() {
        const header = document.getElementById('site-header');
        if (!header || !header.classList.contains('site-header--public')) {
            return;
        }

        const scrollThreshold = 12;
        let headerHeight = 0;

        function measureHeader() {
            headerHeight = header.offsetHeight;
            document.documentElement.style.setProperty('--site-header-height', headerHeight + 'px');
        }

        function updateScrollState() {
            const shouldFix = window.scrollY > scrollThreshold;
            header.classList.toggle('site-header--fixed', shouldFix);
            document.body.classList.toggle('site-header-is-fixed', shouldFix);
        }

        measureHeader();
        updateScrollState();

        window.addEventListener('scroll', updateScrollState, { passive: true });
        window.addEventListener('resize', function () {
            const wasFixed = header.classList.contains('site-header--fixed');
            measureHeader();
            if (wasFixed) {
                updateScrollState();
            }
        });

        const toggle = document.getElementById('site-nav-toggle');
        const overlay = document.getElementById('site-nav-overlay');
        const closeBtn = document.getElementById('site-nav-close');

        function closeOverlay() {
            if (!overlay || !toggle) {
                return;
            }
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            toggle.classList.remove('is-active');
            toggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('site-nav-open');
        }

        function openOverlay() {
            if (!overlay || !toggle) {
                return;
            }
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            toggle.classList.add('is-active');
            toggle.setAttribute('aria-expanded', 'true');
            document.body.classList.add('site-nav-open');
        }

        if (toggle && overlay) {
            toggle.addEventListener('click', function () {
                if (overlay.classList.contains('is-open')) {
                    closeOverlay();
                } else {
                    openOverlay();
                }
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeOverlay);
        }

        if (overlay) {
            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    closeOverlay();
                }
            });
            overlay.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', closeOverlay);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeOverlay();
            }
        });

        const userBtn = document.getElementById('user-menu-btn');
        const userMenu = document.getElementById('user-menu-dropdown');
        if (userBtn && userMenu) {
            userBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                userMenu.classList.toggle('is-hidden');
                userBtn.setAttribute(
                    'aria-expanded',
                    userMenu.classList.contains('is-hidden') ? 'false' : 'true'
                );
            });
            userMenu.addEventListener('click', function (event) {
                event.stopPropagation();
            });
            document.addEventListener('click', function () {
                userMenu.classList.add('is-hidden');
                userBtn.setAttribute('aria-expanded', 'false');
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPublicHeader);
    } else {
        initPublicHeader();
    }
})();
