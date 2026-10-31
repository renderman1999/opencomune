(function () {
    'use strict';

    const config = window.opencomuneDashboardBookings || {};

    let guestFilterDebounceTimer = null;

    function isBookingsHash() {
        return window.location.hash === '#section-prenotazioni';
    }

    function syncDashboardPartnerView() {
        const app = document.querySelector('.oc-dashboard-app');
        if (!app || !document.getElementById('section-overview')) {
            return false;
        }
        const isBookings = isBookingsHash();
        document.documentElement.classList.toggle('oc-dashboard-view-prenotazioni', isBookings);
        app.classList.toggle('is-view-prenotazioni', isBookings);
        return isBookings;
    }

    function reloadBookingsTable(resetPaging) {
        if (typeof window.opencomuneReloadBookingsTable === 'function') {
            window.opencomuneReloadBookingsTable(resetPaging);
        }
    }

    function resetBookingsPageAndLoad() {
        reloadBookingsTable(true);
    }

    function bookingFromCancelButton(btn) {
        return {
            id: parseInt(btn.getAttribute('data-booking-id'), 10),
            esperienza_title: btn.getAttribute('data-booking-title') || '',
            nome: btn.getAttribute('data-booking-guest') || '',
            slot: btn.getAttribute('data-booking-slot') || '',
            status: btn.getAttribute('data-booking-status') || '',
            status_label: btn.getAttribute('data-booking-status-label') || '',
            participants: btn.getAttribute('data-booking-participants') || '',
        };
    }

    function cancelBooking(btn) {
        if (!window.opencomuneOpenBookingCancelModal) {
            return;
        }
        const booking = bookingFromCancelButton(btn);
        if (!booking.id) {
            return;
        }
        window.opencomuneOpenBookingCancelModal(booking, {
            onSuccess: function () {
                reloadBookingsTable(false);
            },
        });
    }

    function bookingFromRefundButton(btn) {
        return {
            id: parseInt(btn.getAttribute('data-booking-id'), 10),
            esperienza_title: btn.getAttribute('data-booking-title') || '',
            nome: btn.getAttribute('data-booking-guest') || '',
        };
    }

    function refundBooking(btn) {
        if (!window.opencomuneOpenBookingRefundModal) {
            return;
        }
        const booking = bookingFromRefundButton(btn);
        if (!booking.id) {
            return;
        }
        const order = {
            refund_amount_formatted: btn.getAttribute('data-refund-amount') || '',
        };
        window.opencomuneOpenBookingRefundModal(booking, order, {
            onSuccess: function () {
                reloadBookingsTable(false);
            },
        });
    }

    function applyBookingsViewSideEffects() {
        reloadBookingsTable(false);
        markPartnerBookingsSeen();
        const section = document.getElementById('section-prenotazioni');
        if (section) {
            section.scrollIntoView({ behavior: 'auto', block: 'start' });
        }
    }

    function renderBookingNavBadgesHtml(counts) {
        if (!counts) {
            return '';
        }
        const i18n = config.i18n || {};
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

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
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

    function markPartnerBookingsSeen() {
        if (!config.ajaxUrl || !config.nonce) {
            return;
        }
        const body = new FormData();
        body.append('action', 'opencomune_mark_partner_bookings_seen');
        body.append('nonce', config.nonce);
        fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success && data.data && data.data.badges) {
                    if (typeof window.opencomuneSyncBookingNavBadges === 'function') {
                        window.opencomuneSyncBookingNavBadges(data.data.badges);
                    }
                }
            })
            .catch(function () {
                /* ignore */
            });
    }

    function setBookingsViewMode() {
        if (config.esperienzeUrl && window.location.hash === '#section-esperienze') {
            window.location.replace(config.esperienzeUrl);
            return;
        }

        if (syncDashboardPartnerView()) {
            applyBookingsViewSideEffects();
        }
    }

    function activateNavForHash() {
        const hash = window.location.hash;
        const navHash = hash || '#section-overview';
        const link = document.querySelector('[data-dashboard-nav][href*="' + navHash + '"]');
        if (link) {
            document.querySelectorAll('[data-dashboard-nav]').forEach(function (item) {
                item.classList.remove('is-active');
            });
            link.classList.add('is-active');
        }

        if (syncDashboardPartnerView()) {
            applyBookingsViewSideEffects();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const refreshBtn = document.getElementById('refreshBookings');
        const daysEl = document.getElementById('filterBookingsDays');
        const statusEl = document.getElementById('filterBookingsStatus');
        const guestEl = document.getElementById('filterBookingsGuest');

        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                resetBookingsPageAndLoad();
            });
        }
        if (daysEl) {
            daysEl.addEventListener('change', resetBookingsPageAndLoad);
        }
        if (statusEl) {
            statusEl.addEventListener('change', resetBookingsPageAndLoad);
        }
        if (guestEl) {
            guestEl.addEventListener('input', function () {
                if (guestFilterDebounceTimer) {
                    clearTimeout(guestFilterDebounceTimer);
                }
                guestFilterDebounceTimer = setTimeout(resetBookingsPageAndLoad, 400);
            });
            guestEl.addEventListener('search', resetBookingsPageAndLoad);
        }

        document.addEventListener('click', function (event) {
            const cancelBtn = event.target.closest('.js-booking-cancel');
            if (cancelBtn) {
                event.preventDefault();
                cancelBooking(cancelBtn);
                return;
            }
            const refundBtn = event.target.closest('.js-booking-refund');
            if (refundBtn) {
                event.preventDefault();
                refundBooking(refundBtn);
            }
        });

        document.querySelectorAll('[data-dashboard-nav]').forEach(function (link) {
            link.addEventListener('click', function () {
                const href = link.getAttribute('href') || '';
                if (href.indexOf('#section-prenotazioni') !== -1) {
                    window.setTimeout(setBookingsViewMode, 50);
                } else if (href.indexOf('#section-overview') !== -1) {
                    window.setTimeout(function () {
                        syncDashboardPartnerView();
                    }, 50);
                }
            });
        });

        activateNavForHash();
        window.addEventListener('hashchange', activateNavForHash);

        window.opencomuneLoadDashboardBookings = function () {
            reloadBookingsTable(false);
        };
    });
})();
