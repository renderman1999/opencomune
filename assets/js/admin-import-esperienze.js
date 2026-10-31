(function ($) {
    'use strict';

    const config = window.opencomuneImportCsv || {};
    let previewToken = '';
    let previewItems = [];

    function setLoading(isLoading, message) {
        const $loader = $('#opencomune-import-loader');
        const loaderMessage = message || 'Elaborazione in corso...';

        $loader.find('.opencomune-import-loader__message').text(loaderMessage);
        $loader.toggleClass('is-visible', isLoading).attr('aria-hidden', isLoading ? 'false' : 'true');
        $('body').toggleClass('opencomune-import-loading', isLoading);

        $('#opencomune-import-preview-btn').prop('disabled', isLoading);
        $('#opencomune-import-confirm-btn').prop('disabled', isLoading);
        $('[data-opencomune-import-close]').prop('disabled', isLoading);
    }

    function showNotice(type, message) {
        const $notice = $('<div>', {
            class: 'notice notice-' + type + ' is-dismissible',
            html: $('<p>').text(message),
        });
        $('.opencomune-import-notices').empty().append($notice);
    }

    function partnerActionLabel(action) {
        const labels = {
            existing: 'Partner esistente',
            will_create: 'Nuovo partner',
            created: 'Partner creato',
            none: 'Nessun partner',
        };
        return labels[action] || action;
    }

    function experienceActionLabel(action) {
        const labels = {
            create: 'Da creare',
            update: 'Da sovrascrivere',
            skip: 'Duplicato (salta)',
            error: 'Errore',
        };
        return labels[action] || action;
    }

    function renderPreviewSummary(summary) {
        if (!summary) {
            return '';
        }

        return [
            'Righe valide: ' + summary.valid,
            'Errori: ' + summary.errors,
            'Partner esistenti: ' + summary.partners_existing,
            'Partner da creare: ' + summary.partners_to_create,
            'Esperienze da creare: ' + summary.experiences_create,
            'Esperienze da aggiornare: ' + summary.experiences_update,
            'Duplicati: ' + summary.experiences_skip,
            'Copertine Google Places: ' + (summary.covers_to_fetch || 0),
        ].join(' | ');
    }

    function renderPreviewTable(items) {
        const $tbody = $('#opencomune-import-preview-table tbody');
        $tbody.empty();

        items.forEach(function (item) {
            const $row = $('<tr>');
            if (item.experience_action === 'error') {
                $row.addClass('opencomune-import-row-error');
            }

            $row.append($('<td>').text(item.line));
            $row.append($('<td>').text(item.title));
            $row.append($('<td>').text(item.organization || '-'));
            $row.append(
                $('<td>').append(
                    $('<span>', {
                        class: 'opencomune-import-badge opencomune-import-badge--' + item.partner_action,
                        text: partnerActionLabel(item.partner_action),
                    })
                )
            );
            $row.append($('<td>').text(item.date_label || '-'));
            $row.append($('<td>').text(item.city || '-'));
            $row.append($('<td>').text(item.tipologia || '-'));
            $row.append(
                $('<td>').append(
                    $('<span>', {
                        class: 'opencomune-import-badge opencomune-import-badge--' + item.experience_action,
                        text: experienceActionLabel(item.experience_action),
                    })
                )
            );
            $row.append($('<td>').text(item.message || ''));

            $tbody.append($row);
        });
    }

    function openPreviewModal(data) {
        previewToken = data.token;
        previewItems = data.items || [];

        $('#opencomune-import-preview-summary').text(renderPreviewSummary(data.summary));
        renderPreviewTable(previewItems);
        $('#opencomune-import-preview-modal').addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('opencomune-import-modal-open');
    }

    function closePreviewModal() {
        $('#opencomune-import-preview-modal').removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('opencomune-import-modal-open');
    }

    function getFormOptions() {
        return {
            import_publish_after_import: $('#import_publish_after_import').is(':checked') ? '1' : '0',
            import_update_existing: $('#import_update_existing').is(':checked') ? '1' : '0',
            import_google_places_cover: $('#import_google_places_cover').is(':checked') ? '1' : '0',
        };
    }

    function requestPreview() {
        const fileInput = document.getElementById('import_csv_file');
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            showNotice('error', 'Seleziona un file CSV da importare.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'opencomune_import_csv_preview');
        formData.append('nonce', config.nonce);
        formData.append('import_csv_file', fileInput.files[0]);
        formData.append('import_publish_after_import', getFormOptions().import_publish_after_import);
        formData.append('import_update_existing', getFormOptions().import_update_existing);
        formData.append('import_google_places_cover', getFormOptions().import_google_places_cover);

        setLoading(true, 'Generazione anteprima in corso...');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        })
            .done(function (response) {
                if (!response || !response.success) {
                    showNotice('error', (response && response.data && response.data.message) || 'Anteprima non disponibile.');
                    return;
                }
                openPreviewModal(response.data);
            })
            .fail(function () {
                showNotice('error', 'Errore di comunicazione durante l\'anteprima.');
            })
            .always(function () {
                setLoading(false, '');
            });
    }

    function confirmImport() {
        if (!previewToken) {
            showNotice('error', 'Anteprima scaduta. Ricarica il file CSV.');
            return;
        }

        setLoading(true, 'Importazione in corso... Attendi il completamento. L\'operazione puo richiedere piu tempo se attivi Google Places.');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'opencomune_import_csv_confirm',
                nonce: config.nonce,
                token: previewToken,
            },
        })
            .done(function (response) {
                closePreviewModal();

                if (!response || !response.success) {
                    showNotice('error', (response && response.data && response.data.message) || 'Importazione fallita.');
                    return;
                }

                previewToken = '';
                showNotice('success', response.data.message);
                renderResultsTable(response.data.results);

                const $results = $('#opencomune-import-results-wrap');
                if ($results.length) {
                    $('html, body').animate({ scrollTop: $results.offset().top - 40 }, 300);
                }
            })
            .fail(function () {
                showNotice('error', 'Errore di comunicazione durante l\'importazione.');
            })
            .always(function () {
                setLoading(false);
            });
    }

    function renderResultsTable(results) {
        if (!results || !results.items || !results.items.length) {
            $('#opencomune-import-results-wrap').hide();
            return;
        }

        const $tbody = $('#opencomune-import-results-table tbody');
        $tbody.empty();

        results.items.forEach(function (item) {
            const $row = $('<tr>');
            $row.append($('<td>').text(item.line));
            $row.append($('<td>').text(item.title));
            $row.append($('<td>').text(item.organization || '-'));
            $row.append($('<td>').text(item.status));
            $row.append($('<td>').text(item.message || ''));

            const $idCell = $('<td>');
            if (item.post_id) {
                $idCell.append(
                    $('<a>', {
                        href: item.edit_url,
                        text: String(item.post_id),
                        target: '_blank',
                        rel: 'noopener noreferrer',
                    })
                );
            } else {
                $idCell.text('-');
            }
            $row.append($idCell);

            $tbody.append($row);
        });

        $('#opencomune-import-results-wrap').show();
    }

    $(function () {
        $('#opencomune-import-preview-btn').on('click', function (event) {
            event.preventDefault();
            requestPreview();
        });

        $('#opencomune-import-confirm-btn').on('click', function (event) {
            event.preventDefault();
            confirmImport();
        });

        $(document).on('click', '[data-opencomune-import-close]', function (event) {
            event.preventDefault();
            closePreviewModal();
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                closePreviewModal();
            }
        });
    });
})(jQuery);
