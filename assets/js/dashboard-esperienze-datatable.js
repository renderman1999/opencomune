(function ($) {
    'use strict';

    const tableConfig = window.opencomuneDashboardTable || {};
    const pageConfig = window.opencomuneDashboardEsperienze || {};
    const i18n = tableConfig.i18n || {};
    const ajaxUrl = pageConfig.ajaxUrl || tableConfig.ajaxUrl || '';
    const showAuthor = !!tableConfig.showAuthor;

    let esperienzeTable = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getStatusLabel(status) {
        if (typeof window.getStatusLabel === 'function') {
            return window.getStatusLabel(status);
        }
        const labels = {
            publish: 'Pubblicata',
            draft: 'Bozza',
            pending: 'In revisione',
        };
        return labels[status] || status;
    }

    function translationLangPresent(esperienza, lang) {
        const translations = Array.isArray(esperienza.translations) ? esperienza.translations : [];
        return translations.some(function (translation) {
            return (translation.lang || '').toLowerCase() === lang;
        });
    }

    function renderTranslationBadges(esperienza) {
        const hasIt = translationLangPresent(esperienza, 'it') || !!esperienza.id;
        const hasEn = translationLangPresent(esperienza, 'en');
        let badges = '<div class="oc-dashboard-lang">';
        if (hasIt) {
            badges += '<span class="oc-dashboard-lang-badge oc-dashboard-lang-badge--it">' + escapeHtml(i18n.itBadge || 'IT') + '</span>';
        }
        if (hasEn) {
            badges += '<span class="oc-dashboard-lang-badge oc-dashboard-lang-badge--en">' + escapeHtml(i18n.enBadge || 'EN') + '</span>';
        } else {
            badges += '<span class="oc-dashboard-lang-badge oc-dashboard-lang-badge--pending">' + escapeHtml(i18n.enBadge || 'EN') + '</span>';
        }
        badges += '</div>';
        return badges;
    }

    function renderTranslationCell(esperienza) {
        const transTitle = escapeHtml(i18n.translations || 'Traduzioni');
        return (
            '<div class="oc-dashboard-trans-cell">' +
            renderTranslationBadges(esperienza) +
            '<button type="button" class="oc-dashboard-btn oc-dashboard-btn--translations oc-dashboard-btn--icon js-dashboard-translations" ' +
            'data-trans-id="' + escapeHtml(esperienza.id) + '" ' +
            'data-trans-title="' + escapeHtml(esperienza.title || '') + '" ' +
            'title="' + transTitle + '" aria-label="' + transTitle + '">' +
            '<i class="bi bi-pencil-square" aria-hidden="true"></i></button></div>'
        );
    }

    function renderThumb(esperienza) {
        if (esperienza.thumbnail) {
            return '<img src="' + escapeHtml(esperienza.thumbnail) + '" alt="" class="oc-dashboard-thumb" loading="lazy">';
        }
        return '<span class="oc-dashboard-thumb oc-dashboard-thumb--empty"><i class="bi bi-image" aria-hidden="true"></i></span>';
    }

    function renderStatusToggleButton(esperienza) {
        const status = esperienza.status || 'draft';
        const id = parseInt(esperienza.id, 10);
        if (!id || typeof window.toggleEsperienzaStatus !== 'function') {
            return '';
        }
        if (status === 'publish') {
            return (
                '<button type="button" class="oc-dashboard-btn oc-dashboard-btn--icon oc-dashboard-btn--status oc-dashboard-btn--to-draft" ' +
                'title="' + escapeHtml(i18n.setDraftTitle || 'Metti in bozza') + '" aria-label="' + escapeHtml(i18n.setDraftTitle || 'Metti in bozza') + '" onclick="toggleEsperienzaStatus(' + id + ', \'publish\')">' +
                '<i class="bi bi-eye-slash" aria-hidden="true"></i></button>'
            );
        }
        return (
            '<button type="button" class="oc-dashboard-btn oc-dashboard-btn--icon oc-dashboard-btn--status oc-dashboard-btn--to-publish" ' +
            'title="' + escapeHtml(i18n.setPublishTitle || 'Pubblica') + '" aria-label="' + escapeHtml(i18n.setPublishTitle || 'Pubblica') + '" onclick="toggleEsperienzaStatus(' + id + ', \'' + escapeHtml(status) + '\')">' +
            '<i class="bi bi-check-circle" aria-hidden="true"></i></button>'
        );
    }

    function renderPublicViewLink(esperienza) {
        if (esperienza.status !== 'publish' || !esperienza.view_url) {
            return '';
        }
        return (
            '<a href="' + escapeHtml(esperienza.view_url) + '" class="oc-dashboard-btn oc-dashboard-btn--view oc-dashboard-btn--icon" ' +
            'title="' + escapeHtml(i18n.viewPublicTitle || 'Apri pagina pubblica') + '" target="_blank" rel="noopener noreferrer">' +
            '<i class="bi bi-eye" aria-hidden="true"></i></a>'
        );
    }

    function renderActions(esperienza) {
        const title = escapeHtml(esperienza.title);
        const editUrl = escapeHtml(esperienza.edit_url || '#');
        const viewUrl = escapeHtml(esperienza.view_url || '');
        return (
            '<div class="oc-dashboard-actions">' +
            renderStatusToggleButton(esperienza) +
            renderPublicViewLink(esperienza) +
            '<a href="' + editUrl + '" class="oc-dashboard-btn oc-dashboard-btn--edit oc-dashboard-btn--icon" title="Modifica" aria-label="Modifica"><i class="bi bi-pencil" aria-hidden="true"></i></a>' +
            '<button type="button" class="oc-dashboard-btn oc-dashboard-btn--icon oc-dashboard-btn--delete" title="Elimina" aria-label="Elimina" onclick="deleteEsperienza(' + parseInt(esperienza.id, 10) + ')"><i class="bi bi-trash" aria-hidden="true"></i></button>' +
            '<button type="button" class="oc-dashboard-btn oc-dashboard-btn--icon oc-dashboard-btn--qr js-dashboard-qr" title="QR" aria-label="QR" data-qr-url="' + viewUrl + '" data-qr-title="' + title + '"><i class="bi bi-qr-code" aria-hidden="true"></i></button>' +
            '</div>'
        );
    }

    function buildColumns() {
        const cols = [
            {
                data: 'id',
                render: function (data) {
                    return '<code>#' + escapeHtml(data) + '</code>';
                },
            },
            {
                data: 'thumbnail',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    return renderThumb(row);
                },
            },
            {
                data: 'title',
                render: function (data, type, row) {
                    const statusLabel = escapeHtml(getStatusLabel(row.status));
                    return (
                        '<div class="oc-dashboard-title-cell"><strong>' + escapeHtml(data) + '</strong>' +
                        '<small><span class="status-badge status-' + escapeHtml(row.status) + '">' + statusLabel + '</span></small></div>'
                    );
                },
            },
        ];

        cols.push({
            data: 'categories_label',
            orderable: false,
            render: function (data) {
                const label = String(data || '').trim();
                if (label === '') {
                    return '<span class="text-gray-400">—</span>';
                }
                return '<span class="text-sm text-gray-700">' + escapeHtml(label) + '</span>';
            },
        });

        if (showAuthor) {
            cols.push({
                data: 'author_name',
                orderable: false,
                render: function (data) {
                    return escapeHtml(data || '—');
                },
            });
        }

        cols.push({
            data: null,
            orderable: false,
            searchable: false,
            render: function (data, type, row) {
                return renderTranslationCell(row);
            },
        });

        cols.push({
            data: null,
            orderable: false,
            searchable: false,
            className: 'oc-dashboard-actions-cell',
            render: function (data, type, row) {
                return renderActions(row);
            },
        });

        return cols;
    }

    function loadCategorie() {
        const select = document.getElementById('filterCategoria');
        if (!select || !ajaxUrl) {
            return;
        }
        fetch(ajaxUrl + '?action=opencomune_get_categorie_esperienze')
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    return;
                }
                select.innerHTML = '<option value="">Tutte le categorie</option>';
                data.data.forEach(function (categoria) {
                    const opt = document.createElement('option');
                    opt.value = categoria.slug;
                    opt.textContent = categoria.name;
                    select.appendChild(opt);
                });
            });
    }

    function initDataTable() {
        if (!$('#esperienzeTable').length || typeof $.fn.DataTable === 'undefined') {
            return;
        }

        const nonOrderable = showAuthor ? [1, 3, 4, 5, 6] : [1, 3, 4, 5];

        esperienzeTable = $('#esperienzeTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: ajaxUrl,
                type: 'POST',
                data: function (d) {
                    d.action = 'opencomune_get_esperienze_dashboard';
                    d.filter_status = $('#filterStatus').val() || '';
                    d.filter_categoria = $('#filterCategoria').val() || '';
                },
                dataSrc: 'data',
            },
            paging: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            searching: true,
            ordering: true,
            info: true,
            order: [[0, 'desc']],
            columns: buildColumns(),
            columnDefs: [
                { orderable: false, targets: nonOrderable },
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/it-IT.json',
            },
        });

        window.esperienzeDataTable = esperienzeTable;
    }

    window.loadEsperienze = function loadEsperienze() {
        if (esperienzeTable) {
            esperienzeTable.ajax.reload(null, false);
        }
    };

    $(document).ready(function () {
        loadCategorie();

        if (typeof $.fn.DataTable !== 'undefined') {
            initDataTable();
        } else {
            window.setTimeout(initDataTable, 300);
        }

        $('#filterStatus, #filterCategoria').on('change', function () {
            window.loadEsperienze();
        });

        $('#refreshData').on('click', function () {
            window.loadEsperienze();
        });
    });
})(jQuery);
