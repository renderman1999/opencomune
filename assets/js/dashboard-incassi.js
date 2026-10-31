(function () {
    'use strict';

    const config = window.opencomuneIncassiDashboard || {};
    const i18n = config.i18n || {};
    const isAdmin = !!config.isAdmin;

    let chartMonthly = null;
    let chartPartners = null;

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
        const select = document.getElementById('filterIncassiPartner');
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

    function populateExperienceFilter() {
        const select = document.getElementById('filterIncassiExperience');
        if (!select || !config.experiences) {
            return;
        }
        config.experiences.forEach(function (exp) {
            const opt = document.createElement('option');
            opt.value = String(exp.id);
            opt.textContent = exp.label;
            select.appendChild(opt);
        });
    }

    function setLoading(loading) {
        const loadingEl = document.getElementById('incassiLoading');
        const contentEl = document.getElementById('incassiContent');
        if (loadingEl) {
            loadingEl.style.display = loading ? 'block' : 'none';
        }
        if (contentEl) {
            contentEl.style.display = loading ? 'none' : 'block';
        }
    }

    function updateSummary(summary) {
        const s = summary || {};
        const countEl = document.getElementById('incassiStatCount');
        const refundCountEl = document.getElementById('incassiStatRefundCount');
        const refundTotalEl = document.getElementById('incassiStatRefundTotal');
        const grossEl = document.getElementById('incassiStatGross');
        const netAfterEl = document.getElementById('incassiStatNetAfter');
        const commissionEl = document.getElementById('incassiStatCommission');
        const netEl = document.getElementById('incassiStatNet');
        if (countEl) {
            countEl.textContent = String(s.payment_count || 0);
        }
        if (refundCountEl) {
            refundCountEl.textContent = String(s.refund_count || 0);
        }
        if (refundTotalEl) {
            refundTotalEl.textContent = s.refund_total_formatted
                ? '−' + s.refund_total_formatted
                : '0,00 EUR';
        }
        if (grossEl) {
            grossEl.textContent = s.gross_formatted || '0,00 EUR';
        }
        if (netAfterEl) {
            const netAfter = isAdmin
                ? s.net_after_refund_formatted
                : s.partner_net_after_refund_formatted || s.net_after_refund_formatted;
            netAfterEl.textContent = netAfter || '0,00 EUR';
        }
        if (commissionEl) {
            commissionEl.textContent = s.commission_formatted || '0,00 EUR';
        }
        if (netEl) {
            netEl.textContent = s.net_formatted || '0,00 EUR';
        }
    }

    function useExperienceChart() {
        if (!isAdmin) {
            return true;
        }
        const partnerEl = document.getElementById('filterIncassiPartner');
        return partnerEl && partnerEl.value !== '';
    }

    function updateSecondaryChartTitle() {
        const titleEl = document.getElementById('incassiChartSecondaryTitle');
        if (!titleEl) {
            return;
        }
        if (useExperienceChart()) {
            titleEl.textContent = i18n.chartExperiencesTitle || 'Netto per esperienza';
        } else {
            titleEl.textContent = i18n.chartPartnersTitle || 'Commissioni per partner';
        }
    }

    function renderCharts(data) {
        if (!window.Chart) {
            return;
        }

        const monthly = data.chart_monthly || [];
        const byExperience = data.chart_by_experience || [];
        const partners = data.chart_partners || [];
        const showExperience = useExperienceChart();
        const secondary = showExperience ? byExperience : partners;

        updateSecondaryChartTitle();

        const monthlyCtx = document.getElementById('incassiChartMonthly');
        if (monthlyCtx) {
            if (chartMonthly) {
                chartMonthly.destroy();
            }
            const monthlyDatasets = [
                {
                    label: i18n.chartGross || 'Incassato',
                    data: monthly.map(function (r) {
                        return r.gross;
                    }),
                    backgroundColor: 'rgba(59, 130, 246, 0.65)',
                },
                {
                    label: i18n.chartRefund || 'Rimborsi',
                    data: monthly.map(function (r) {
                        return r.refund || 0;
                    }),
                    backgroundColor: 'rgba(245, 158, 11, 0.8)',
                },
                {
                    label: i18n.chartNetAfterRefund || 'Netto dopo rimborsi',
                    data: monthly.map(function (r) {
                        return r.net_after_refund != null ? r.net_after_refund : Math.max(0, (r.gross || 0) - (r.refund || 0));
                    }),
                    backgroundColor: 'rgba(16, 185, 129, 0.75)',
                },
            ];
            if (isAdmin) {
                monthlyDatasets.push({
                    label: i18n.chartCommission || 'Commissioni',
                    data: monthly.map(function (r) {
                        return r.commission;
                    }),
                    backgroundColor: 'rgba(99, 102, 241, 0.65)',
                });
            }

            chartMonthly = new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: monthly.map(function (r) {
                        return r.label;
                    }),
                    datasets: monthlyDatasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true },
                    },
                },
            });
        }

        const partnersCtx = document.getElementById('incassiChartPartners');
        if (partnersCtx) {
            if (chartPartners) {
                chartPartners.destroy();
            }

            let secondaryLabel = i18n.chartCommission || 'Commissioni';
            let secondaryField = 'commission';
            if (showExperience) {
                secondaryLabel = i18n.chartNet || 'Netto';
                secondaryField = 'net';
            }

            chartPartners = new Chart(partnersCtx, {
                type: 'bar',
                data: {
                    labels: secondary.map(function (r) {
                        return r.label;
                    }),
                    datasets: [
                        {
                            label: secondaryLabel,
                            data: secondary.map(function (r) {
                                return r[secondaryField];
                            }),
                            backgroundColor: showExperience
                                ? 'rgba(59, 130, 246, 0.65)'
                                : 'rgba(5, 150, 105, 0.75)',
                        },
                    ],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { beginAtZero: true },
                    },
                },
            });
        }
    }

    function renderPaymentsTable(payments) {
        const container = document.getElementById('incassiPaymentsList');
        if (!container) {
            return;
        }

        if (!payments || !payments.length) {
            container.innerHTML =
                '<p class="oc-dashboard-empty">' + escapeHtml(i18n.noPayments || 'Nessun pagamento') + '</p>';
            return;
        }

        const rows = payments
            .map(function (p) {
                const bookingLink = p.detail_url
                    ? '<a href="' +
                      escapeHtml(p.detail_url) +
                      '" class="text-blue-700 hover:text-blue-900 text-sm">' +
                      escapeHtml(i18n.openBooking || 'Prenotazione') +
                      '</a>'
                    : '—';

                const rowClass = p.is_refund ? ' class="oc-incassi-row--refund"' : '';

                let row =
                    '<tr' +
                    rowClass +
                    '>' +
                    '<td class="oc-booking-meta">' +
                    escapeHtml(p.paid_at || '—') +
                    '</td>';

                if (isAdmin) {
                    row +=
                        '<td>' +
                        escapeHtml(p.partner_name || '—') +
                        '</td>';
                }

                row +=
                    '<td class="oc-dashboard-title-cell">' +
                    escapeHtml(p.esperienza_title) +
                    '</td>' +
                    '<td>' +
                    escapeHtml(p.gross_formatted) +
                    '</td>' +
                    '<td>' +
                    escapeHtml(p.commission_formatted) +
                    '</td>' +
                    '<td>' +
                    escapeHtml(p.net_formatted) +
                    '</td>' +
                    '<td>' +
                    '<span class="oc-booking-status ' +
                    escapeHtml(p.status_badge_class || 'oc-booking-status--pending') +
                    '">' +
                    escapeHtml(p.status_label) +
                    '</span>' +
                    '</td>' +
                    '<td><code class="oc-booking-detail__code">' +
                    escapeHtml(p.order_id) +
                    '</code></td>' +
                    '<td>' +
                    bookingLink +
                    '</td>' +
                    '</tr>';

                return row;
            })
            .join('');

        let header =
            '<thead><tr>' +
            '<th>' +
            escapeHtml(i18n.colDate || 'Data') +
            '</th>';

        if (isAdmin) {
            header +=
                '<th>' +
                escapeHtml(i18n.colPartner || 'Partner') +
                '</th>';
        }

        header +=
            '<th>' +
            escapeHtml(i18n.colExperience || 'Esperienza') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colGross || 'Incassato') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colCommission || 'Commissione') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colNet || 'Netto') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colStatus || 'Stato') +
            '</th>' +
            '<th>' +
            escapeHtml(i18n.colOrder || 'Ordine') +
            '</th>' +
            '<th></th>' +
            '</tr></thead>';

        container.innerHTML =
            '<div class="oc-dashboard-table-wrap"><table class="oc-dashboard-table oc-dashboard-table--incassi">' +
            header +
            '<tbody>' +
            rows +
            '</tbody></table></div>';
    }

    function loadIncassi() {
        if (!config.ajaxUrl || !config.nonce) {
            return;
        }

        setLoading(true);

        const daysEl = document.getElementById('filterIncassiDays');
        const partnerEl = document.getElementById('filterIncassiPartner');
        const experienceEl = document.getElementById('filterIncassiExperience');
        const statusEl = document.getElementById('filterIncassiStatus');

        const formData = new FormData();
        formData.append('action', 'opencomune_get_incassi_dashboard');
        formData.append('nonce', config.nonce);
        formData.append('days', daysEl ? daysEl.value : '90');
        if (partnerEl) {
            formData.append('partner_id', partnerEl.value);
        }
        if (experienceEl) {
            formData.append('tour_id', experienceEl.value);
        }
        formData.append('status', statusEl ? statusEl.value : 'settled');

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
                updateSummary(data.data.summary);
                renderCharts(data.data);
                renderPaymentsTable(data.data.payments);
            })
            .catch(function () {
                setLoading(false);
                const container = document.getElementById('incassiPaymentsList');
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
        populateExperienceFilter();

        ['filterIncassiDays', 'filterIncassiPartner', 'filterIncassiExperience', 'filterIncassiStatus'].forEach(
            function (id) {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', loadIncassi);
                }
            }
        );

        const refreshBtn = document.getElementById('incassiRefreshBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', loadIncassi);
        }

        loadIncassi();
    });
})();
