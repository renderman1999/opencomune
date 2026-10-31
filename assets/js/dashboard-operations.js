(function () {
    'use strict';

    const config = window.opencomuneOperationsDashboard || {};
    const i18n = config.i18n || {};

    function escapeHtml(str) {
        if (str == null) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function populatePartnerFilter() {
        const select = document.getElementById('filterOperationsPartner');
        if (!select || !config.partners) {
            return;
        }
        config.partners.forEach(function (partner) {
            const opt = document.createElement('option');
            opt.value = String(partner.id);
            opt.textContent = partner.label;
            select.appendChild(opt);
        });
    }

    function setLoading(loading) {
        const loadingEl = document.getElementById('operationsLoading');
        const contentEl = document.getElementById('operationsContent');
        if (loadingEl) {
            loadingEl.style.display = loading ? 'block' : 'none';
        }
        if (contentEl) {
            contentEl.style.display = loading ? 'none' : 'block';
        }
    }

    function actionBadgeClass(action) {
        if (action === 'payment_refund') {
            return 'oc-operations-badge--refund';
        }
        if (action === 'cancelled') {
            return 'oc-operations-badge--cancel';
        }
        if (action === 'payment_received') {
            return 'oc-operations-badge--payment';
        }
        if (action === 'partner_login' || action === 'admin_login') {
            return 'oc-operations-badge--login';
        }
        return '';
    }

    function renderTable(items) {
        const container = document.getElementById('operationsList');
        const countEl = document.getElementById('operationsCountLabel');
        if (!container) {
            return;
        }

        const count = items ? items.length : 0;
        if (countEl) {
            countEl.textContent = count + ' ' + (count === 1 ? 'operazione' : 'operazioni');
        }

        if (!items || !items.length) {
            container.innerHTML =
                '<p class="oc-dashboard-empty">' + escapeHtml(i18n.noItems || 'Nessuna operazione') + '</p>';
            return;
        }

        const rows = items
            .map(function (item) {
                let bookingCell = escapeHtml(i18n.noBooking || '—');
                if (item.booking_id > 0) {
                    bookingCell = item.booking_detail_url
                        ? '<a href="' +
                          escapeHtml(item.booking_detail_url) +
                          '" class="text-blue-700 hover:text-blue-900">#' +
                          escapeHtml(String(item.booking_id)) +
                          '</a>'
                        : '#' + escapeHtml(String(item.booking_id));
                    if (item.guest_name) {
                        bookingCell +=
                            '<div class="text-sm text-gray-600">' + escapeHtml(item.guest_name) + '</div>';
                    }
                }

                let detailCell = escapeHtml(item.message || item.reason || '—');
                if (item.action === 'cancelled' && item.status_from_label && item.status_to_label) {
                    detailCell =
                        escapeHtml(item.status_from_label + ' → ' + item.status_to_label) +
                        (item.message || item.reason
                            ? '<br><span class="text-gray-600">' + escapeHtml(item.message || item.reason) + '</span>'
                            : '');
                } else if (item.amount_formatted && item.action === 'payment_received') {
                    detailCell =
                        escapeHtml(item.message || item.reason || '—') +
                        '<br><strong>' +
                        escapeHtml(item.amount_formatted) +
                        '</strong>';
                }

                return (
                    '<tr>' +
                    '<td class="oc-booking-meta">' +
                    escapeHtml(item.created_at || '—') +
                    '</td>' +
                    '<td><span class="oc-operations-badge ' +
                    actionBadgeClass(item.action) +
                    '">' +
                    escapeHtml(item.action_label || item.action) +
                    '</span></td>' +
                    '<td>' +
                    bookingCell +
                    '</td>' +
                    '<td class="oc-dashboard-title-cell">' +
                    escapeHtml(item.esperienza_title || '—') +
                    '</td>' +
                    '<td>' +
                    escapeHtml(item.partner_name || '—') +
                    '</td>' +
                    '<td>' +
                    escapeHtml(item.user || '—') +
                    '</td>' +
                    '<td class="oc-operations-detail">' +
                    detailCell +
                    '</td>' +
                    '</tr>'
                );
            })
            .join('');

        const header =
            '<thead><tr>' +
            '<th>' +
            escapeHtml(i18n.colDate || 'Data') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colAction || 'Operazione') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colBooking || 'Prenotazione') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colExperience || 'Esperienza') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colPartner || 'Partner') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colUser || 'Utente') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colDetail || 'Dettaglio') +
            '</th>' +
            '</tr></thead>';

        container.innerHTML =
            '<div class="oc-dashboard-table-wrap"><table class="oc-dashboard-table oc-dashboard-table--operations">' +
            header +
            '<tbody>' +
            rows +
            '</tbody></table></div>';
    }

    function loadOperations() {
        if (!config.ajaxUrl || !config.nonce) {
            return;
        }

        setLoading(true);

        const daysEl = document.getElementById('filterOperationsDays');
        const actionEl = document.getElementById('filterOperationsAction');
        const partnerEl = document.getElementById('filterOperationsPartner');

        const formData = new FormData();
        formData.append('action', 'opencomune_get_operations_log');
        formData.append('nonce', config.nonce);
        formData.append('days', daysEl ? daysEl.value : '90');
        formData.append('action_filter', actionEl ? actionEl.value : '');
        formData.append('partner_id', partnerEl ? partnerEl.value : '');

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                setLoading(false);
                if (!data.success) {
                    throw new Error(data.data && data.data.message ? data.data.message : 'load');
                }
                renderTable(data.data.items || []);
            })
            .catch(function () {
                setLoading(false);
                const container = document.getElementById('operationsList');
                if (container) {
                    container.innerHTML =
                        '<p class="oc-dashboard-empty oc-dashboard-empty--error">' +
                        escapeHtml(i18n.loadError || 'Errore caricamento') +
                        '</p>';
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        populatePartnerFilter();

        ['filterOperationsDays', 'filterOperationsAction', 'filterOperationsPartner'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', loadOperations);
            }
        });

        const refreshBtn = document.getElementById('operationsRefreshBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', loadOperations);
        }

        loadOperations();
    });
})();
