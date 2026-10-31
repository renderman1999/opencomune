(function () {
    'use strict';

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

    function getConfig() {
        return window.opencomuneBookingRefund || {};
    }

    function buildSummaryHtml(booking, order, i18n) {
        const amount = order && order.refund_amount_formatted ? order.refund_amount_formatted : '';
        const rows = [
            [i18n.colBooking || 'Prenotazione', '#' + escapeHtml(booking.id)],
            [i18n.colExperience || 'Esperienza', escapeHtml(booking.esperienza_title || '')],
            [i18n.colGuest || 'Cliente', escapeHtml(booking.nome || '')],
            [i18n.colAmount || 'Importo da rimborsare', escapeHtml(amount)],
        ];

        let html = '<div class="oc-booking-cancel-modal__summary">';
        rows.forEach(function (row) {
            if (!row[1]) {
                return;
            }
            html +=
                '<div class="oc-booking-cancel-modal__row"><span class="oc-booking-cancel-modal__label">' +
                row[0] +
                '</span><span class="oc-booking-cancel-modal__value">' +
                row[1] +
                '</span></div>';
        });
        html +=
            '<p class="oc-booking-cancel-modal__hint">' +
            escapeHtml(i18n.refundHint || 'Il rimborso verrà inviato a SumUp sul metodo di pagamento del cliente.') +
            '</p></div>';

        return html;
    }

    function submitRefund(bookingId, config) {
        const formData = new FormData();
        formData.append('action', 'opencomune_refund_booking_payment');
        formData.append('nonce', config.nonce);
        formData.append('id', String(bookingId));

        return fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        }).then(function (response) {
            return response.json();
        });
    }

    window.opencomuneOpenBookingRefundModal = function (booking, order, options) {
        options = options || {};
        const config = getConfig();
        const i18n = config.i18n || {};

        if (!booking || !booking.id || !config.ajaxUrl || !config.nonce) {
            return;
        }

        if (!window.Swal) {
            if (!window.confirm(i18n.refundConfirmPlain || 'Confermi il rimborso del pagamento?')) {
                return;
            }
            submitRefund(booking.id, config).then(function (data) {
                if (!data.success) {
                    window.alert(data.data && data.data.message ? data.data.message : 'Errore');
                    return;
                }
                if (typeof options.onSuccess === 'function') {
                    options.onSuccess(data);
                }
            });
            return;
        }

        Swal.fire({
            title: i18n.refundTitle || 'Rimborsa pagamento',
            html: buildSummaryHtml(booking, order, i18n),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: i18n.refundConfirmBtn || 'Conferma rimborso',
            cancelButtonText: i18n.close || 'Annulla',
            confirmButtonColor: '#d97706',
            focusCancel: true,
            customClass: {
                popup: 'oc-booking-cancel-modal oc-booking-refund-modal',
            },
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            Swal.fire({
                title: i18n.refunding || 'Rimborso in corso...',
                allowOutsideClick: false,
                didOpen: function () {
                    Swal.showLoading();
                },
            });

            submitRefund(booking.id, config)
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.data && data.data.message ? data.data.message : 'refund');
                    }
                    return Swal.fire({
                        icon: 'success',
                        title: data.data.message || i18n.refunded || 'Rimborso inviato',
                        timer: 2400,
                        showConfirmButton: false,
                    }).then(function () {
                        if (typeof options.onSuccess === 'function') {
                            options.onSuccess(data);
                        }
                    });
                })
                .catch(function (err) {
                    const msg =
                        err && err.message && err.message !== 'refund'
                            ? err.message
                            : i18n.refundError || 'Errore durante il rimborso';
                    Swal.fire({ icon: 'error', title: msg });
                });
        });
    };
})();
