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
        return window.opencomuneBookingCancel || window.opencomuneDashboardBookings || {};
    }

    function buildSummaryHtml(booking, i18n) {
        const rows = [
            [i18n.colBooking || 'Prenotazione', '#' + escapeHtml(booking.id)],
            [i18n.colExperience || 'Esperienza', escapeHtml(booking.esperienza_title || booking.experience_title || '')],
            [i18n.colGuest || 'Cliente', escapeHtml(booking.nome || booking.guest || '')],
            [i18n.colSlot || 'Data/ora', escapeHtml(booking.slot || '')],
            [i18n.colParticipants || 'Partecipanti', escapeHtml(String(booking.participants || ''))],
            [i18n.colStatus || 'Stato attuale', escapeHtml(booking.status_label || '')],
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
            escapeHtml(i18n.cancelStatusHint || 'La prenotazione passerà allo stato: Cancellata') +
            '</p></div>';

        return html;
    }

    function submitCancellation(bookingId, reason, config) {
        const formData = new FormData();
        formData.append('action', 'opencomune_cancel_booking');
        formData.append('nonce', config.nonce);
        formData.append('id', String(bookingId));
        formData.append('cancellation_reason', reason);

        return fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        }).then(function (response) {
            return response.json();
        });
    }

    window.opencomuneOpenBookingCancelModal = function (booking, options) {
        options = options || {};
        const config = getConfig();
        const i18n = config.i18n || {};

        if (!booking || !booking.id || !config.ajaxUrl || !config.nonce) {
            return;
        }

        if (!window.Swal) {
            const reason = window.prompt(i18n.reasonPrompt || 'Motivazione annullamento (obbligatoria):');
            if (!reason || !reason.trim()) {
                return;
            }
            submitCancellation(booking.id, reason.trim(), config).then(function (data) {
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
            title: i18n.cancelTitle || 'Annulla prenotazione',
            html: buildSummaryHtml(booking, i18n),
            input: 'textarea',
            inputLabel: i18n.reasonLabel || 'Motivazione annullamento',
            inputPlaceholder: i18n.reasonPlaceholder || 'Descrivi il motivo (visibile nel registro attività)',
            inputAttributes: {
                maxlength: 2000,
                rows: 4,
                'aria-label': i18n.reasonLabel || 'Motivazione annullamento',
            },
            showCancelButton: true,
            confirmButtonText: i18n.cancelConfirmBtn || 'Conferma annullamento',
            cancelButtonText: i18n.close || 'Chiudi',
            confirmButtonColor: '#dc2626',
            focusConfirm: false,
            customClass: {
                popup: 'oc-booking-cancel-modal',
                input: 'oc-booking-cancel-modal__textarea',
            },
            preConfirm: function (value) {
                const reason = (value || '').trim();
                if (reason.length < 5) {
                    Swal.showValidationMessage(i18n.reasonRequired || 'Inserisci una motivazione di almeno 5 caratteri.');
                    return false;
                }
                return reason;
            },
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) {
                return;
            }

            Swal.fire({
                title: i18n.cancelling || 'Annullamento in corso...',
                allowOutsideClick: false,
                didOpen: function () {
                    Swal.showLoading();
                },
            });

            submitCancellation(booking.id, result.value, config)
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.data && data.data.message ? data.data.message : 'cancel');
                    }
                    return Swal.fire({
                        icon: 'success',
                        title: data.data.message || i18n.cancelled || 'Prenotazione annullata',
                        timer: 2200,
                        showConfirmButton: false,
                    }).then(function () {
                        if (typeof options.onSuccess === 'function') {
                            options.onSuccess(data);
                        }
                    });
                })
                .catch(function (err) {
                    const msg =
                        err && err.message && err.message !== 'cancel'
                            ? err.message
                            : i18n.cancelError || 'Errore durante l\'annullamento';
                    Swal.fire({ icon: 'error', title: msg });
                });
        });
    };
})();
