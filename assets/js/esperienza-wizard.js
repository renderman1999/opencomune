(function () {
    const cfg = window.opencomuneEsperienzaWizard || {};
    const flatSteps = cfg.flatSteps || [];
    const requiredStepIds = cfg.requiredStepIds || ['base', 'duration', 'media'];
    const tabRoot = document.getElementById('nuovaEsperienzaTabs');

    if (!tabRoot) {
        return;
    }

    const sidebarSteps = document.querySelectorAll('.oc-exp-wizard__step[data-exp-tab]');
    const tabPanels = tabRoot.querySelectorAll('[data-exp-panel]');
    const searchInput = document.getElementById('ocExpWizardSearch');
    const advancedToggle = document.getElementById('ocExpWizardShowAdvanced');
    const titleLive = document.getElementById('ocExpWizardTitleLive');
    const titleField = document.getElementById('tour_title');
    const prevBtn = document.getElementById('ocExpWizardPrev');
    const nextBtn = document.getElementById('ocExpWizardNext');
    const stepHeading = document.getElementById('ocExpWizardStepHeading');
    const redirectInput = document.getElementById('ocExpWizardRedirectDashboard');

    let currentStepId = tabRoot.querySelector('[data-exp-panel].is-active')?.getAttribute('data-exp-panel') || 'base';

    function stepMeta(stepId) {
        return flatSteps.find(function (s) {
            return s.id === stepId;
        });
    }

    function stepLabel(stepId) {
        const meta = stepMeta(stepId);
        return meta ? meta.label : stepId;
    }

    function isStepRequired(stepId) {
        return requiredStepIds.indexOf(stepId) >= 0;
    }

    function getEditorPlainText(fieldId) {
        const field = document.getElementById(fieldId);
        let textareaValue = field ? String(field.value || '').trim() : '';

        if (typeof window.tinymce !== 'undefined' && typeof window.tinymce.triggerSave === 'function') {
            window.tinymce.triggerSave();
            if (field) {
                textareaValue = String(field.value || '').trim();
            }
        }

        if (typeof window.tinymce !== 'undefined') {
            const editor = window.tinymce.get(fieldId);
            if (editor) {
                const editorText = (editor.getContent({ format: 'text' }) || '').trim();
                if (editorText) {
                    return editorText;
                }
                const html = (editor.getContent() || '').trim();
                if (html && html !== '<p></p>' && html !== '<p><br></p>' && html !== '<p><br data-mce-bogus="1"></p>') {
                    const tmp = document.createElement('div');
                    tmp.innerHTML = html;
                    const stripped = (tmp.textContent || tmp.innerText || '').trim();
                    if (stripped) {
                        return stripped;
                    }
                }
            }
        }

        if (textareaValue) {
            return textareaValue;
        }

        if (field && String(field.value || '').trim() !== '') {
            const tmp = document.createElement('div');
            tmp.innerHTML = field.value;
            return (tmp.textContent || tmp.innerText || '').trim();
        }

        return '';
    }

    function isStepComplete(stepId) {
        if (stepId === 'summary') {
            return true;
        }

        const panel = tabRoot.querySelector('[data-exp-panel="' + stepId + '"]');
        if (!panel) {
            return false;
        }

        if (stepId === 'base') {
            return !!(
                document.getElementById('tour_title')?.value.trim()
                && getEditorPlainText('tour_description')
            );
        }
        if (stepId === 'duration') {
            const d = parseInt(document.getElementById('tour_duration_days')?.value || '0', 10) || 0;
            const h = parseInt(document.getElementById('tour_duration_hours')?.value || '0', 10) || 0;
            const m = parseInt(document.getElementById('tour_duration_minutes')?.value || '0', 10) || 0;
            return (d * 24 * 60 + h * 60 + m) > 0;
        }
        if (stepId === 'media') {
            return !!(document.getElementById('featured-image-id')?.value);
        }
        if (stepId === 'availability') {
            const payload = document.getElementById('tour_availability_rules_payload');
            if (!payload) {
                return false;
            }
            try {
                const rules = JSON.parse(payload.value || '[]');
                return Array.isArray(rules) && rules.length > 0;
            } catch (e) {
                return false;
            }
        }
        if (stepId === 'meeting-points') {
            const payload = document.getElementById('tour_meeting_points_payload');
            if (!payload) {
                return false;
            }
            try {
                const rows = JSON.parse(payload.value || '[]');
                return Array.isArray(rows) && rows.length > 0;
            } catch (e) {
                return false;
            }
        }
        if (stepId === 'itinerary') {
            const payload = document.getElementById('tour_itinerary_steps_payload');
            if (!payload) {
                return false;
            }
            try {
                const rows = JSON.parse(payload.value || '[]');
                return Array.isArray(rows) && rows.length > 0;
            } catch (e) {
                return false;
            }
        }
        if (stepId === 'pickup-arrival') {
            return !!document.querySelector('input[name="tour_pickup_mode"]:checked');
        }
        if (stepId === 'experience-settings') {
            return !!document.querySelector('input[name="tour_experience_type"]:checked');
        }
        if (stepId === 'category-tags') {
            const categoriesEl = document.getElementById('tour_categories');
            const categoryCount = categoriesEl
                ? Array.from(categoriesEl.selectedOptions).filter(function (opt) {
                    return String(opt.value || '').trim() !== '';
                }).length
                : 0;
            return categoryCount > 0;
        }
        if (stepId === 'pricing') {
            const payload = document.getElementById('tour_pricing_categories_payload');
            if (!payload) {
                return false;
            }
            try {
                const rows = JSON.parse(payload.value || '[]');
                if (!Array.isArray(rows) || rows.length === 0) {
                    return false;
                }
                return rows.every(function (row) {
                    if (!row || !row.label) {
                        return false;
                    }
                    const price = parseFloat(String(row.price ?? '').replace(',', '.'));
                    return !Number.isNaN(price) && price >= 0;
                });
            } catch (e) {
                return false;
            }
        }

        return true;
    }

    function markStepCompletion(stepId) {
        const status = document.querySelector('[data-step-status="' + stepId + '"]');
        const stepBtn = document.querySelector('.oc-exp-wizard__step[data-exp-tab="' + stepId + '"]');
        if (!status) {
            return;
        }

        if (stepBtn && stepBtn.disabled) {
            status.classList.remove('is-done', 'is-incomplete');
            if (stepBtn) {
                stepBtn.classList.remove('is-incomplete-required');
            }
            return;
        }

        const required = isStepRequired(stepId);
        const done = isStepComplete(stepId);

        status.classList.remove('is-done', 'is-incomplete');
        if (stepBtn) {
            stepBtn.classList.remove('is-incomplete-required');
        }

        if (required && !done) {
            status.classList.add('is-incomplete');
            if (stepBtn) {
                stepBtn.classList.add('is-incomplete-required');
            }
        } else if (done) {
            status.classList.add('is-done');
        }
    }

    function updateGroupAlerts() {
        document.querySelectorAll('.oc-exp-wizard__group[data-exp-group]').forEach(function (group) {
            const groupId = group.getAttribute('data-exp-group');
            const alert = group.querySelector('[data-group-alert="' + groupId + '"]');
            if (!alert) {
                return;
            }
            let hasIncompleteRequired = false;
            flatSteps.forEach(function (step) {
                if (step.group_id !== groupId) {
                    return;
                }
                if (isStepRequired(step.id) && !isStepComplete(step.id)) {
                    const btn = document.querySelector('.oc-exp-wizard__step[data-exp-tab="' + step.id + '"]');
                    if (btn && !btn.disabled) {
                        hasIncompleteRequired = true;
                    }
                }
            });
            alert.classList.toggle('is-hidden', !hasIncompleteRequired);
        });
    }

    function refreshAllStepStatus() {
        flatSteps.forEach(function (s) {
            markStepCompletion(s.id);
        });
        updateGroupAlerts();
    }

    function activateStep(stepId, options) {
        const opts = options || {};
        if (!tabRoot.querySelector('[data-exp-panel="' + stepId + '"]')) {
            return;
        }

        currentStepId = stepId;

        sidebarSteps.forEach(function (btn) {
            const active = btn.getAttribute('data-exp-tab') === stepId;
            btn.classList.toggle('is-active', active);
        });

        tabPanels.forEach(function (panel) {
            const active = panel.getAttribute('data-exp-panel') === stepId;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });

        if (stepHeading) {
            stepHeading.hidden = stepId === 'summary';
            if (stepId !== 'summary') {
                stepHeading.textContent = stepLabel(stepId);
            }
        }

        const meta = stepMeta(stepId);
        if (meta && meta.group_id) {
            const group = document.querySelector('.oc-exp-wizard__group[data-exp-group="' + meta.group_id + '"]');
            if (group) {
                group.classList.add('is-open');
                const toggle = group.querySelector('.oc-exp-wizard__group-toggle');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                }
            }
        }

        updateNavButtons();

        if (!opts.silent && window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.set('exp_tab', stepId);
            window.history.replaceState({}, '', url.toString());
        }
    }

    window.opencomuneActivateExperienceStep = activateStep;
    window.opencomuneRefreshExperienceWizardStatus = refreshAllStepStatus;

    function stepIndex(stepId) {
        return flatSteps.findIndex(function (s) {
            return s.id === stepId;
        });
    }

    function nextStepId() {
        const idx = stepIndex(currentStepId);
        for (let i = idx + 1; i < flatSteps.length; i++) {
            const btn = document.querySelector('.oc-exp-wizard__step[data-exp-tab="' + flatSteps[i].id + '"]');
            if (btn && !btn.disabled) {
                return flatSteps[i].id;
            }
        }
        return null;
    }

    function prevStepId() {
        const idx = stepIndex(currentStepId);
        for (let i = idx - 1; i >= 0; i--) {
            const btn = document.querySelector('.oc-exp-wizard__step[data-exp-tab="' + flatSteps[i].id + '"]');
            if (btn && !btn.disabled) {
                return flatSteps[i].id;
            }
        }
        return null;
    }

    function updateNavButtons() {
        if (prevBtn) {
            prevBtn.disabled = !prevStepId();
        }
        if (nextBtn) {
            const next = nextStepId();
            nextBtn.disabled = !next;
            const isLast = !next;
            nextBtn.textContent = isLast
                ? (cfg.strings && cfg.strings.finish ? cfg.strings.finish : 'Fine')
                : (cfg.strings && cfg.strings.next ? cfg.strings.next : 'Avanti');
            nextBtn.dataset.isLast = isLast ? '1' : '0';
        }
    }

    sidebarSteps.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) {
                if (typeof Swal !== 'undefined' && btn.getAttribute('data-requires-saved') === '1') {
                    Swal.fire({
                        icon: 'info',
                        title: cfg.strings && cfg.strings.requiresSave ? cfg.strings.requiresSave : 'Salva prima l\'esperienza.',
                        confirmButtonText: 'OK',
                    });
                }
                return;
            }
            activateStep(btn.getAttribute('data-exp-tab'));
        });
    });

    document.querySelectorAll('.oc-exp-wizard__group-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const group = toggle.closest('.oc-exp-wizard__group');
            if (!group) {
                return;
            }
            const open = group.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    if (advancedToggle) {
        advancedToggle.addEventListener('change', function () {
            document.querySelectorAll('.oc-exp-wizard__group.is-optional').forEach(function (group) {
                group.classList.toggle('is-hidden', !advancedToggle.checked);
            });
        });
        document.querySelectorAll('.oc-exp-wizard__group.is-optional').forEach(function (group) {
            group.classList.toggle('is-hidden', !advancedToggle.checked);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = searchInput.value.trim().toLowerCase();
            sidebarSteps.forEach(function (btn) {
                const label = (btn.textContent || '').toLowerCase();
                const match = q === '' || label.indexOf(q) >= 0;
                btn.classList.toggle('is-filter-hidden', !match);
                if (match && q !== '') {
                    const group = btn.closest('.oc-exp-wizard__group');
                    if (group) {
                        group.classList.add('is-open');
                    }
                }
            });
        });
    }

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        }
    });

    if (titleField && titleLive) {
        titleField.addEventListener('input', function () {
            const v = titleField.value.trim();
            titleLive.textContent = v || (cfg.defaultTitle || 'Nuova esperienza');
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            const prev = prevStepId();
            if (prev) {
                activateStep(prev);
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            const next = nextStepId();
            if (next) {
                activateStep(next);
                return;
            }
            if (redirectInput) {
                redirectInput.value = '1';
            }
            const form = document.getElementById('nuova-esperienza-form');
            const saveBtn = form ? form.querySelector('[name="save_tour"]') : null;
            if (saveBtn) {
                saveBtn.click();
            }
        });
    }

    tabRoot.addEventListener('change', refreshAllStepStatus, true);
    tabRoot.addEventListener('input', refreshAllStepStatus, true);

    document.addEventListener('opencomune-availability-rules-changed', refreshAllStepStatus);
    document.addEventListener('opencomune-pricing-categories-changed', refreshAllStepStatus);
    document.addEventListener('opencomune-meeting-points-changed', refreshAllStepStatus);
    document.addEventListener('opencomune-itinerary-steps-changed', refreshAllStepStatus);
    document.addEventListener('opencomune-experience-settings-changed', refreshAllStepStatus);

    if (typeof window.tinymce !== 'undefined') {
        document.addEventListener('tinymce-editor-init', function (event) {
            const editor = event.detail && event.detail.editor;
            if (!editor) {
                return;
            }
            editor.on('change keyup undo redo SetContent', function () {
                refreshAllStepStatus();
            });
            refreshAllStepStatus();
        });

        if (typeof window.tinymce.on === 'function') {
            window.tinymce.on('AddEditor', function (event) {
                const editor = event && event.editor;
                if (!editor || editor.id !== 'tour_description') {
                    return;
                }
                editor.on('change keyup undo redo SetContent init', function () {
                    refreshAllStepStatus();
                });
                refreshAllStepStatus();
            });
        }
    }

    tabRoot.addEventListener('click', function (event) {
        const target = event.target.closest('[data-exp-summary-step]');
        if (!target) {
            return;
        }
        event.preventDefault();
        const stepId = target.getAttribute('data-exp-summary-step');
        if (stepId) {
            activateStep(stepId);
        }
    });

    const urlTab = new URLSearchParams(window.location.search).get('exp_tab');
    const experienceId = parseInt(cfg.experienceId || '0', 10) || 0;
    let initialTab = 'base';
    if (urlTab && tabRoot.querySelector('[data-exp-panel="' + urlTab + '"]')) {
        initialTab = urlTab;
    } else if (experienceId > 0 && tabRoot.querySelector('[data-exp-panel="summary"]')) {
        initialTab = 'summary';
    }
    activateStep(initialTab, { silent: true });
    refreshAllStepStatus();
    window.setTimeout(refreshAllStepStatus, 400);
})();
