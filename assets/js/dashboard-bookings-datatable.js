(function ($) {
    'use strict';

    const config = window.opencomuneDashboardBookings || {};
    const i18n = config.i18n || {};
    const showPartner = !!config.showPartner;
    const ajaxUrl =
        config.ajaxUrl ||
        (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '') ||
        '/wp-admin/admin-ajax.php';

    let bookingsTable = null;

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

    function statusClass(status) {
        if (status === 'confermata') {
            return 'oc-booking-status--confirmed';
        }
        if (status === 'pending_payment') {
            return 'oc-booking-status--pending';
        }
        if (status === 'pending_approval') {
            return 'oc-booking-status--pending-approval';
        }
        if (status === 'cancellata') {
            return 'oc-booking-status--cancelled';
        }
        return '';
    }

    function renderIdCell(b) {
        const detailUrl = b.detail_url || '#';
        return (
            '<a href="' +
            escapeHtml(detailUrl) +
            '" class="oc-booking-id-link font-medium text-blue-700 hover:text-blue-900">#' +
            escapeHtml(String(b.id)) +
            '</a>'
        );
    }

    function renderGuestCell(b) {
        let html = escapeHtml(b.nome);
        html +=
            '<div class="oc-booking-contact">' +
            '<a href="mailto:' +
            escapeHtml(b.email) +
            '">' +
            escapeHtml(b.email) +
            '</a>';
        if (b.telefono) {
            html += '<span>' + escapeHtml(b.telefono) + '</span>';
        }
        html += '</div>';
        return html;
    }

    function renderStatusCell(b) {
        let html =
            '<span class="oc-booking-status ' +
            statusClass(b.status) +
            '">' +
            escapeHtml(b.status_label) +
            '</span>';
        if (b.metodo_pagamento) {
            html += '<span class="oc-booking-payment">' + escapeHtml(b.metodo_pagamento) + '</span>';
        }
        return html;
    }

    function renderActions(b) {
        const detailUrl = b.detail_url || '#';
        const editBtn =
            '<a href="' +
            escapeHtml(detailUrl) +
            '" class="oc-dashboard-btn oc-dashboard-btn--edit oc-dashboard-btn--icon" title="' +
            escapeHtml(i18n.openDetail || 'Dettaglio') +
            '"><i class="bi bi-eye" aria-hidden="true"></i></a>';

        const cancelBtn = b.can_cancel
            ? '<button type="button" class="oc-dashboard-btn oc-dashboard-btn--delete js-booking-cancel" data-booking-id="' +
              b.id +
              '" data-booking-title="' +
              escapeHtml(b.esperienza_title) +
              '" data-booking-guest="' +
              escapeHtml(b.nome) +
              '" data-booking-slot="' +
              escapeHtml(b.slot) +
              '" data-booking-status="' +
              escapeHtml(b.status) +
              '" data-booking-status-label="' +
              escapeHtml(b.status_label) +
              '" data-booking-participants="' +
              escapeHtml(String(b.participants)) +
              '" title="' +
              escapeHtml(i18n.cancelTitle || 'Annulla prenotazione') +
              '"><i class="bi bi-x-circle" aria-hidden="true"></i></button>'
            : '';

        let refundBtn = '';
        if (b.can_refund) {
            refundBtn =
                '<button type="button" class="oc-dashboard-btn oc-dashboard-btn--icon oc-dashboard-btn--refund js-booking-refund" data-booking-id="' +
                b.id +
                '" data-booking-title="' +
                escapeHtml(b.esperienza_title) +
                '" data-booking-guest="' +
                escapeHtml(b.nome) +
                '" data-refund-amount="' +
                escapeHtml(b.refund_amount_formatted || '') +
                '" title="' +
                escapeHtml(i18n.refundTitle || 'Rimborsa pagamento') +
                '"><i class="bi bi-cash-coin" aria-hidden="true"></i></button>';
        } else if (b.is_refunded) {
            refundBtn =
                '<span class="oc-dashboard-btn oc-dashboard-btn--refund-done" title="' +
                escapeHtml(i18n.refundedLabel || 'Pagamento rimborsato') +
                '"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>';
        }

        return '<div class="oc-dashboard-actions">' + editBtn + refundBtn + cancelBtn + '</div>';
    }

    function buildColumns() {
        const cols = [
            {
                data: 'id',
                className: 'oc-booking-meta oc-booking-id-cell',
                render: function (data, type, row) {
                    return renderIdCell(row);
                },
            },
            {
                data: 'esperienza_title',
                className: 'oc-dashboard-title-cell',
                render: function (data) {
                    return escapeHtml(data);
                },
            },
        ];

        if (showPartner) {
            cols.push({
                data: 'partner_name',
                render: function (data) {
                    return escapeHtml(data || '—');
                },
            });
        }

        cols.push(
            {
                data: 'slot',
                render: function (data) {
                    return escapeHtml(data);
                },
            },
            {
                data: 'nome',
                render: function (data, type, row) {
                    return renderGuestCell(row);
                },
            },
            {
                data: 'participants',
                className: 'oc-booking-participants',
                render: function (data) {
                    return escapeHtml(String(data));
                },
            },
            {
                data: 'status',
                render: function (data, type, row) {
                    return renderStatusCell(row);
                },
            },
            {
                data: 'data_prenotazione',
                className: 'oc-booking-meta',
                render: function (data) {
                    return escapeHtml(data);
                },
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'oc-dashboard-actions-cell',
                render: function (data, type, row) {
                    return renderActions(row);
                },
            }
        );

        return cols;
    }

    function filterValues() {
        const daysEl = document.getElementById('filterBookingsDays');
        const statusEl = document.getElementById('filterBookingsStatus');
        const guestEl = document.getElementById('filterBookingsGuest');
        return {
            days: daysEl ? daysEl.value : '90',
            status: statusEl ? statusEl.value : '',
            guest_name: guestEl && guestEl.value.trim() !== '' ? guestEl.value.trim() : '',
        };
    }

    function initDataTable() {
        if (!$('#bookingsTable').length || typeof $.fn.DataTable === 'undefined') {
            return;
        }

        const actionsIndex = showPartner ? 8 : 7;

        bookingsTable = $('#bookingsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: function (d) {
                    d.action = 'opencomune_get_prenotazioni';
                    d.nonce = config.nonce || '';
                    const filters = filterValues();
                    d.days = filters.days;
                    d.status = filters.status;
                    d.guest_name = filters.guest_name;
                },
                dataSrc: function (json) {
                    if (json && json.error) {
                        window.console.error(json.error);
                    }
                    return json && Array.isArray(json.data) ? json.data : [];
                },
            },
            paging: true,
            pageLength: 15,
            lengthMenu: [
                [15, 25, 50, 100],
                [15, 25, 50, 100],
            ],
            searching: true,
            ordering: true,
            info: true,
            order: [[0, 'desc']],
            columns: buildColumns(),
            columnDefs: [{ orderable: false, targets: [actionsIndex] }],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/it-IT.json',
            },
        });

        window.opencomuneBookingsDataTable = bookingsTable;
    }

    window.opencomuneReloadBookingsTable = function opencomuneReloadBookingsTable(resetPaging) {
        if (bookingsTable) {
            bookingsTable.ajax.reload(null, resetPaging !== false);
        }
    };

    $(document).ready(function () {
        if (typeof $.fn.DataTable !== 'undefined') {
            initDataTable();
        } else {
            window.setTimeout(initDataTable, 300);
        }
    });
})(jQuery);
