(function () {
    'use strict';

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getAjaxUrl() {
        if (typeof ajax_object !== 'undefined' && ajax_object.ajax_url) {
            return ajax_object.ajax_url;
        }
        if (typeof window.opencomuneDashboardBookings !== 'undefined' && window.opencomuneDashboardBookings.ajaxUrl) {
            return window.opencomuneDashboardBookings.ajaxUrl;
        }
        return '/wp-admin/admin-ajax.php';
    }

    function renderTopExperiences(list) {
        const container = document.getElementById('perfTopExperiences');
        if (!container) {
            return;
        }

        if (!list || !list.length) {
            container.innerHTML =
                '<p class="oc-dashboard-metric__empty">' +
                escapeHtml('Nessuna vendita in questo periodo.') +
                '</p>';
            return;
        }

        const items = list
            .map(function (item) {
                return (
                    '<div class="oc-dashboard-metric__top-row">' +
                    '<span class="oc-dashboard-metric__top-name">' +
                    escapeHtml(item.title) +
                    '</span>' +
                    '<span class="oc-dashboard-metric__top-count">' +
                    escapeHtml(String(item.count)) +
                    '</span>' +
                    '</div>'
                );
            })
            .join('');

        container.innerHTML = items;
    }

    function loadDashboardHomePerformance() {
        const select = document.getElementById('dashboardPerformanceDays');
        const days = select ? select.value : '14';
        const bookingsEl = document.getElementById('perfBookings');
        const passengersEl = document.getElementById('perfPassengers');
        const grossEl = document.getElementById('perfGross');

        if (bookingsEl) {
            bookingsEl.textContent = '…';
        }
        if (passengersEl) {
            passengersEl.textContent = '…';
        }
        if (grossEl) {
            grossEl.textContent = '…';
        }

        fetch(getAjaxUrl() + '?action=opencomune_get_dashboard_home_performance&days=' + encodeURIComponent(days), {
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success || !data.data) {
                    throw new Error('performance');
                }
                const payload = data.data;
                if (bookingsEl) {
                    bookingsEl.textContent = String(payload.bookings ?? 0);
                }
                if (passengersEl) {
                    passengersEl.textContent = String(payload.passengers ?? 0);
                }
                if (grossEl) {
                    grossEl.textContent = payload.gross_formatted || '0,00 €';
                }
                renderTopExperiences(payload.top_experiences || []);
            })
            .catch(function () {
                if (bookingsEl) {
                    bookingsEl.textContent = '0';
                }
                if (passengersEl) {
                    passengersEl.textContent = '0';
                }
                if (grossEl) {
                    grossEl.textContent = '0,00 €';
                }
                renderTopExperiences([]);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById('dashboardPerformanceDays');
        if (!select) {
            return;
        }
        loadDashboardHomePerformance();
        select.addEventListener('change', loadDashboardHomePerformance);
    });

    window.opencomuneLoadDashboardHomePerformance = loadDashboardHomePerformance;
})();
