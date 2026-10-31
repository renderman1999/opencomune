(function () {
    'use strict';

    const config = window.opencomuneDashboardSidebarBadges || {};
    const i18n = config.i18n || {};
    const pollMs = Math.max(30000, parseInt(config.pollIntervalMs, 10) || 60000);
    let pollTimer = null;
    let fetchInFlight = false;

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function renderBookingNavBadgesHtml(counts) {
        if (!counts) {
            return '';
        }
        const parts = [];
        const pendingApproval = parseInt(counts.pending_approval, 10) || 0;
        const pendingPayment = parseInt(counts.pending_payment, 10) || 0;
        const confermata = parseInt(counts.confermata, 10) || 0;

        if (pendingApproval > 0) {
            parts.push(
                '<span class="oc-booking-status oc-booking-status--pending-approval oc-dashboard-sidebar__badge" title="' +
                    escapeAttr(i18n.badgePendingApproval || 'In attesa approvazione') +
                    '">' +
                    pendingApproval +
                    '</span>'
            );
        }
        if (pendingPayment > 0) {
            parts.push(
                '<span class="oc-booking-status oc-booking-status--pending oc-dashboard-sidebar__badge" title="' +
                    escapeAttr(i18n.badgePendingPayment || 'In attesa pagamento') +
                    '">' +
                    pendingPayment +
                    '</span>'
            );
        }
        if (confermata > 0) {
            parts.push(
                '<span class="oc-booking-status oc-booking-status--confirmed oc-dashboard-sidebar__badge" title="' +
                    escapeAttr(i18n.badgeNewConfirmed || 'Nuove prenotazioni confermate') +
                    '">' +
                    confermata +
                    '</span>'
            );
        }

        if (!parts.length) {
            return '';
        }

        return '<span class="oc-dashboard-sidebar__badges" data-booking-nav-badges">' + parts.join('') + '</span>';
    }

    function syncBookingNavBadges(counts) {
        const link = document.querySelector('.oc-dashboard-sidebar__link[data-dashboard-nav="prenotazioni"]');
        if (!link) {
            return;
        }
        const existing = link.querySelector('[data-booking-nav-badges]');
        if (existing) {
            existing.remove();
        }
        const html = renderBookingNavBadgesHtml(counts);
        if (html) {
            link.insertAdjacentHTML('beforeend', html);
        }
    }

    function fetchBookingNavBadges() {
        if (fetchInFlight || !config.ajaxUrl || !config.nonce) {
            return;
        }
        if (!document.querySelector('.oc-dashboard-sidebar__link[data-dashboard-nav="prenotazioni"]')) {
            return;
        }

        fetchInFlight = true;
        const body = new FormData();
        body.append('action', 'opencomune_get_booking_sidebar_badge_counts');
        body.append('nonce', config.nonce);

        fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success && data.data && data.data.badges) {
                    syncBookingNavBadges(data.data.badges);
                }
            })
            .catch(function () {
                /* ignore */
            })
            .finally(function () {
                fetchInFlight = false;
            });
    }

    function startPolling() {
        if (pollTimer) {
            return;
        }
        pollTimer = window.setInterval(function () {
            if (document.visibilityState === 'visible') {
                fetchBookingNavBadges();
            }
        }, pollMs);
    }

    function stopPolling() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    window.opencomuneSyncBookingNavBadges = syncBookingNavBadges;
    window.opencomuneFetchBookingNavBadges = fetchBookingNavBadges;

    document.addEventListener('DOMContentLoaded', function () {
        startPolling();
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                fetchBookingNavBadges();
            }
        });
    });

    window.addEventListener('pagehide', stopPolling);
})();
