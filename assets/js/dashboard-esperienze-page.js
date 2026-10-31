(function () {
    'use strict';

    const config = window.opencomuneDashboardEsperienze || {};
    const ajaxUrl = config.ajaxUrl || '';

    window.getStatusLabel = function getStatusLabel(status) {
        const labels = {
            publish: 'Pubblicata',
            draft: 'Bozza',
            pending: 'In revisione',
        };
        return labels[status] || status;
    };

    window.toggleEsperienzaStatus = function toggleEsperienzaStatus(id, currentStatus) {
        const newStatus = currentStatus === 'publish' ? 'draft' : 'publish';
        const actionText = newStatus === 'publish' ? 'pubblicare' : 'nascondere';

        if (!window.Swal) {
            return;
        }

        Swal.fire({
            title: 'Conferma Azione',
            text: 'Sei sicuro di voler ' + actionText + ' questa esperienza?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus === 'publish' ? '#10b981' : '#f59e0b',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sì, ' + actionText,
            cancelButtonText: 'Annulla',
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }
            fetch(ajaxUrl + '?action=opencomune_toggle_esperienza_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(newStatus),
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        Swal.fire('Aggiornato!', data.message, 'success');
                        if (typeof window.loadEsperienze === 'function') {
                            window.loadEsperienze();
                        }
                    } else {
                        Swal.fire('Errore!', data.message || "Errore durante l'aggiornamento.", 'error');
                    }
                })
                .catch(function () {
                    Swal.fire('Errore!', 'Errore di connessione.', 'error');
                });
        });
    };

    window.deleteEsperienza = function deleteEsperienza(id) {
        if (!window.Swal) {
            return;
        }

        Swal.fire({
            title: 'Sei sicuro?',
            text: 'Questa azione non può essere annullata!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sì, elimina!',
            cancelButtonText: 'Annulla',
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }
            fetch(ajaxUrl + '?action=opencomune_delete_esperienza', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id),
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        Swal.fire('Eliminata!', "L'esperienza è stata eliminata.", 'success');
                        if (typeof window.loadEsperienze === 'function') {
                            window.loadEsperienze();
                        }
                    } else {
                        Swal.fire('Errore!', data.message || "Errore durante l'eliminazione.", 'error');
                    }
                })
                .catch(function () {
                    Swal.fire('Errore!', 'Errore di connessione.', 'error');
                });
        });
    };

    let currentTourId = null;

    window.openCalendarManager = function openCalendarManager(tourId, tourTitle) {
        currentTourId = tourId;
        const titleEl = document.getElementById('calendarModalTitle');
        const modal = document.getElementById('calendarModal');
        const editLink = document.getElementById('calendarEditLink');
        const base = config.editExperienceBase || '';

        if (titleEl) {
            titleEl.textContent = 'Calendario - ' + tourTitle;
        }
        if (editLink && base) {
            editLink.href = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'id=' + encodeURIComponent(tourId);
        }
        if (modal) {
            modal.style.display = 'block';
        }
        loadCalendarEvents();
    };

    window.closeCalendarModal = function closeCalendarModal() {
        const modal = document.getElementById('calendarModal');
        if (modal) {
            modal.style.display = 'none';
        }
        currentTourId = null;
    };

    function loadCalendarEvents() {
        if (!currentTourId || !ajaxUrl) {
            return;
        }

        const eventsList = document.getElementById('calendarEventsList');
        if (!eventsList) {
            return;
        }

        eventsList.innerHTML =
            '<div class="text-center py-4"><div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div><p class="mt-2 text-gray-500">Caricamento date...</p></div>';

        const from = new Date().toISOString().slice(0, 10);
        const toDate = new Date();
        toDate.setMonth(toDate.getMonth() + 4);
        const to = toDate.toISOString().slice(0, 10);

        fetch(
            ajaxUrl +
                '?action=opencomune_get_calendario&tour_id=' +
                encodeURIComponent(currentTourId) +
                '&from=' +
                from +
                '&to=' +
                to
        )
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.length > 0) {
                    renderCalendarEvents(data);
                } else {
                    eventsList.innerHTML = '<div class="text-center py-8 text-gray-500">Nessuna data configurata</div>';
                }
            })
            .catch(function () {
                eventsList.innerHTML = '<div class="text-center py-8 text-red-500">Errore nel caricamento delle date</div>';
            });
    }

    function renderCalendarEvents(events) {
        const eventsList = document.getElementById('calendarEventsList');
        if (!eventsList) {
            return;
        }

        eventsList.innerHTML = events
            .map(function (event) {
                const eventDate = new Date(event.start);
                const formattedDate = eventDate.toLocaleDateString('it-IT', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                });
                const formattedTime = eventDate.toLocaleTimeString('it-IT', {
                    hour: '2-digit',
                    minute: '2-digit',
                });

                return (
                    '<div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">' +
                    '<div class="flex-1"><div class="flex items-center space-x-3">' +
                    '<div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">' +
                    '<i class="bi bi-calendar-event text-blue-600"></i></div><div>' +
                    '<p class="font-medium text-gray-900">' + formattedDate + '</p>' +
                    '<p class="text-sm text-gray-600">' + formattedTime + ' - ' + event.title + '</p>' +
                    '</div></div></div></div>'
                );
            })
            .join('');
    }
})();
