(function () {
    'use strict';

    function init() {
        const config = window.opencomuneSocialProof;
        const root = document.getElementById('oc-social-proof');
        if (!config || !root || !Array.isArray(config.items) || config.items.length === 0) {
            return;
        }

        if (window.sessionStorage && sessionStorage.getItem('ocSocialProofDismissed') === '1') {
            return;
        }

        const link = document.getElementById('oc-social-proof-link');
        const img = document.getElementById('oc-social-proof-img');
        const fallback = document.getElementById('oc-social-proof-thumb-fallback');
        const labelEl = document.getElementById('oc-social-proof-label');
        const titleEl = document.getElementById('oc-social-proof-title');
        const closeBtn = document.getElementById('oc-social-proof-close');

        if (!link || !img || !fallback || !labelEl || !titleEl || !closeBtn) {
            return;
        }

        const items = config.items.slice();
        let index = 0;
        let hideTimer = null;
        let cycleTimer = null;
        let isShowing = false;

        function randomViewers() {
            return Math.floor(Math.random() * 23) + 8;
        }

        function pickNextIndex() {
            if (items.length === 1) {
                return 0;
            }
            let next = Math.floor(Math.random() * items.length);
            if (next === index) {
                next = (next + 1) % items.length;
            }
            return next;
        }

        function formatLabel(count) {
            const template = config.labelTemplate || '%d persone stanno osservando:';
            return template.replace('%d', String(count));
        }

        function hideBadge() {
            root.classList.remove('is-visible');
            isShowing = false;
            window.setTimeout(function () {
                if (!isShowing) {
                    root.hidden = true;
                }
            }, 1000);
        }

        function showBadge() {
            index = pickNextIndex();
            const item = items[index];
            if (!item) {
                return;
            }

            const viewers = randomViewers();
            labelEl.innerHTML =
                '<i class="bi bi-eye" aria-hidden="true"></i> ' +
                formatLabel(viewers);
            titleEl.textContent = item.title || '';
            link.href = item.url || '#';

            if (item.image) {
                img.src = item.image;
                img.alt = item.title || '';
                img.hidden = false;
                fallback.hidden = true;
            } else {
                img.hidden = true;
                img.removeAttribute('src');
                fallback.hidden = false;
                fallback.textContent = (item.title || config.placeholderLetter || 'E').trim().charAt(0).toUpperCase();
            }

            root.hidden = false;
            window.requestAnimationFrame(function () {
                root.classList.add('is-visible');
            });
            isShowing = true;

            window.clearTimeout(hideTimer);
            hideTimer = window.setTimeout(hideBadge, config.visibleMs || 6500);
        }

        function startCycle() {
            showBadge();
            cycleTimer = window.setInterval(showBadge, config.intervalMs || 10000);
        }

        closeBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            window.clearInterval(cycleTimer);
            window.clearTimeout(hideTimer);
            hideBadge();
            if (window.sessionStorage) {
                sessionStorage.setItem('ocSocialProofDismissed', '1');
            }
        });

        link.addEventListener('click', function () {
            window.clearInterval(cycleTimer);
            window.clearTimeout(hideTimer);
        });

        window.setTimeout(startCycle, config.initialDelayMs || 4000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
