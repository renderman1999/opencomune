(function ($) {
    'use strict';

    const config = window.opencomuneEsperienzeCategoriesDt || {};
    const i18n = config.i18n || {};
    const ajaxUrl = config.ajaxUrl || '';

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderEditIcon(url) {
        if (!url) {
            return '';
        }
        const title = escapeHtml(i18n.editTitle || 'Modifica');
        return (
            '<a href="' +
            escapeHtml(url) +
            '" class="oc-dashboard-btn oc-dashboard-btn--edit oc-dashboard-btn--icon" title="' +
            title +
            '" aria-label="' +
            title +
            '"><i class="bi bi-pencil" aria-hidden="true"></i></a>'
        );
    }

    function renderDeleteForm(row) {
        if (!row.delete_action || !row.delete_nonce) {
            return '';
        }

        const template = i18n.deleteConfirm || 'Eliminare la categoria "%s"?';
        const msg = template.replace('%s', row.name || '');
        const deleteTitle = escapeHtml(i18n.deleteTitle || 'Elimina');

        return (
            '<form method="post" class="oc-planner-admin__inline-form" action="' +
            escapeHtml(row.delete_action) +
            '" onsubmit="return confirm(' +
            JSON.stringify(msg) +
            ');">' +
            '<input type="hidden" name="_wpnonce" value="' +
            escapeHtml(row.delete_nonce) +
            '">' +
            '<input type="hidden" name="oc_category_action" value="delete">' +
            '<input type="hidden" name="term_id" value="' +
            escapeHtml(String(row.id)) +
            '">' +
            '<button type="submit" class="oc-dashboard-btn oc-dashboard-btn--delete oc-dashboard-btn--icon" title="' +
            deleteTitle +
            '" aria-label="' +
            deleteTitle +
            '"><i class="bi bi-trash" aria-hidden="true"></i></button></form>'
        );
    }

    function renderActions(row) {
        return (
            '<div class="oc-dashboard-actions">' +
            renderEditIcon(row.edit_url) +
            renderDeleteForm(row) +
            '</div>'
        );
    }

    $(function () {
        const $table = $('#esperienzeCategoriesTable');
        if (!$table.length || typeof $.fn.DataTable === 'undefined') {
            return;
        }

        if ($.fn.DataTable.isDataTable($table)) {
            return;
        }

        $table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: function (d) {
                    d.action = 'opencomune_esperienze_categories_datatables';
                    d.nonce = config.nonce || '';
                },
                dataSrc: function (json) {
                    if (json && json.error && window.console) {
                        console.error(json.error);
                    }
                    return json && Array.isArray(json.data) ? json.data : [];
                },
            },
            pageLength: 10,
            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100],
            ],
            searching: true,
            ordering: true,
            info: true,
            order: [[1, 'asc']],
            columns: [
                {
                    data: 'thumbnail',
                    orderable: false,
                    searchable: false,
                    className: 'oc-dashboard-table__thumb',
                    render: function (data) {
                        if (data) {
                            return (
                                '<img src="' +
                                escapeHtml(data) +
                                '" alt="" class="oc-dashboard-thumb" loading="lazy">'
                            );
                        }
                        return '<span class="text-gray-400 text-sm">—</span>';
                    },
                },
                {
                    data: 'name',
                    render: function (data) {
                        return escapeHtml(data);
                    },
                },
                {
                    data: 'slug',
                    className: 'text-gray-600',
                    render: function (data) {
                        return '<code class="text-sm">' + escapeHtml(data) + '</code>';
                    },
                },
                {
                    data: 'name_en',
                    render: function (data, type, row) {
                        if (data) {
                            return escapeHtml(data);
                        }
                        return '<span class="text-gray-400">—</span>';
                    },
                },
                { data: 'count' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'oc-dashboard-table__actions oc-dashboard-actions-cell',
                    render: function (data, type, row) {
                        return renderActions(row);
                    },
                },
            ],
            columnDefs: [{ orderable: false, targets: [0, 5] }],
            language: {
                emptyTable: 'Nessuna categoria. Usa «Nuova categoria» per crearne una.',
            },
        });
    });
})(jQuery);
