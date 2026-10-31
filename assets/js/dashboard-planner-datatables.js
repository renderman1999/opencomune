(function ($) {
    'use strict';

    const config = window.opencomunePlannerAdminDt || {};
    const i18n = config.i18n || {};
    const ajaxUrl = config.ajaxUrl || '';

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function wrapActions(inner) {
        return '<div class="oc-dashboard-actions">' + inner + '</div>';
    }

    function renderEditIcon(url, label) {
        if (!url) {
            return '';
        }
        const title = escapeHtml(label || i18n.editTitle || 'Modifica');
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

    function renderViewIcon(url, label) {
        if (!url) {
            return '';
        }
        const title = escapeHtml(label || i18n.viewTitle || 'Apri');
        return (
            '<a href="' +
            escapeHtml(url) +
            '" class="oc-dashboard-btn oc-dashboard-btn--view oc-dashboard-btn--icon" target="_blank" rel="noopener noreferrer" title="' +
            title +
            '" aria-label="' +
            title +
            '"><i class="bi bi-eye" aria-hidden="true"></i></a>'
        );
    }

    function renderDeleteIconForm(row, deleteConfig) {
        if (!row.delete_action || !deleteConfig) {
            return '';
        }

        const template = i18n[deleteConfig.confirmKey] || 'Eliminare "%s"?';
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
            '<input type="hidden" name="oc_planner_action" value="' +
            escapeHtml(deleteConfig.ocAction) +
            '">' +
            '<input type="hidden" name="' +
            escapeHtml(deleteConfig.idField) +
            '" value="' +
            escapeHtml(String(row.id)) +
            '">' +
            '<button type="submit" class="oc-dashboard-btn oc-dashboard-btn--delete oc-dashboard-btn--icon" title="' +
            deleteTitle +
            '" aria-label="' +
            deleteTitle +
            '"><i class="bi bi-trash" aria-hidden="true"></i></button></form>'
        );
    }

    function renderRowActions(row, options) {
        options = options || {};
        let html = renderEditIcon(row.edit_url, i18n.editTitle);
        if (options.viewUrl) {
            html += renderViewIcon(options.viewUrl, i18n.viewTitle);
        }
        if (options.delete) {
            html += renderDeleteIconForm(row, options.delete);
        }
        return wrapActions(html);
    }

    const entityConfigs = {
        cities: {
            order: [[0, 'asc']],
            nonOrderable: [5],
            columns: [
                {
                    data: 'name',
                    render: function (data) {
                        return escapeHtml(data);
                    },
                },
                { data: 'slug' },
                { data: 'destinations_count' },
                { data: 'sort_order' },
                { data: 'status_label' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'oc-dashboard-table__actions oc-dashboard-actions-cell',
                    render: function (data, type, row) {
                        return renderRowActions(row, {
                            delete: {
                                confirmKey: 'deleteCityConfirm',
                                ocAction: 'delete_city',
                                idField: 'city_id',
                            },
                        });
                    },
                },
            ],
        },
        interests: {
            order: [[0, 'asc']],
            nonOrderable: [4],
            columns: [
                {
                    data: 'name',
                    render: function (data, type, row) {
                        let html = escapeHtml(data);
                        if (row.is_food) {
                            html += '<span class="text-xs text-gray-500 ml-1">' + escapeHtml(i18n.food || 'food') + '</span>';
                        }
                        return html;
                    },
                },
                {
                    data: 'slug',
                    className: 'text-gray-600',
                    render: function (data) {
                        return escapeHtml(data);
                    },
                },
                { data: 'sort_order' },
                { data: 'status_label' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'oc-dashboard-table__actions oc-dashboard-actions-cell',
                    render: function (data, type, row) {
                        return renderRowActions(row, {
                            delete: {
                                confirmKey: 'deleteInterestConfirm',
                                ocAction: 'delete_interest',
                                idField: 'interest_id',
                            },
                        });
                    },
                },
            ],
        },
        focuses: {
            order: [[0, 'asc']],
            nonOrderable: [4],
            columns: [
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
                        return escapeHtml(data);
                    },
                },
                { data: 'sort_order' },
                { data: 'status_label' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'oc-dashboard-table__actions oc-dashboard-actions-cell',
                    render: function (data, type, row) {
                        return renderRowActions(row, {
                            delete: {
                                confirmKey: 'deleteFocusConfirm',
                                ocAction: 'delete_focus',
                                idField: 'focus_id',
                            },
                        });
                    },
                },
            ],
        },
        pois: {
            order: [[0, 'asc']],
            nonOrderable: [5],
            columns: [
                {
                    data: 'name',
                    render: function (data, type, row) {
                        let html = escapeHtml(data);
                        if (row.address) {
                            html +=
                                '<span class="block text-xs text-gray-500 mt-0.5">' + escapeHtml(row.address) + '</span>';
                        }
                        return html;
                    },
                },
                { data: 'city_label' },
                { data: 'interests_label' },
                {
                    data: 'duration_min',
                    render: function (data) {
                        return escapeHtml(String(data)) + ' min';
                    },
                },
                { data: 'status_label' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'oc-dashboard-table__actions oc-dashboard-actions-cell',
                    render: function (data, type, row) {
                        return renderRowActions(row, {
                            delete: {
                                confirmKey: 'deletePoiConfirm',
                                ocAction: 'delete_poi',
                                idField: 'poi_id',
                            },
                        });
                    },
                },
            ],
        },
        requests: {
            order: [[0, 'desc']],
            nonOrderable: [4],
            columns: [
                {
                    data: 'created_at',
                    className: 'whitespace-nowrap',
                    render: function (data) {
                        return escapeHtml(data);
                    },
                },
                {
                    data: 'trip_label',
                    render: function (data) {
                        return escapeHtml(data);
                    },
                },
                {
                    data: 'start_address',
                    render: function (data) {
                        return escapeHtml(data);
                    },
                },
                {
                    data: 'contact_label',
                    render: function (data) {
                        return escapeHtml(data);
                    },
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'oc-dashboard-table__actions oc-dashboard-actions-cell',
                    render: function (data, type, row) {
                        return renderRowActions(row, {
                            viewUrl: row.view_url,
                            delete: {
                                confirmKey: 'deleteRequestConfirm',
                                ocAction: 'delete_request',
                                idField: 'request_id',
                            },
                        });
                    },
                },
            ],
        },
    };

    function initPlannerTable($table) {
        const entity = $table.data('planner-entity');
        const entityConfig = entityConfigs[entity];
        if (!entityConfig || typeof $.fn.DataTable === 'undefined') {
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
                    d.action = 'opencomune_planner_admin_datatables';
                    d.nonce = config.nonce || '';
                    d.entity = entity;
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
            order: entityConfig.order,
            columns: entityConfig.columns,
            columnDefs: entityConfig.nonOrderable.length
                ? [{ orderable: false, targets: entityConfig.nonOrderable }]
                : [],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/it-IT.json',
            },
        });
    }

    $(document).ready(function () {
        $('table[data-planner-entity]').each(function () {
            initPlannerTable($(this));
        });
    });
})(jQuery);
