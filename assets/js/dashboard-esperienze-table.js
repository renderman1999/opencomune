(function () {
    'use strict';

    const config = window.opencomuneDashboardTable || {};
    const i18n = config.i18n || {};

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
            badges += '<span class="oc-dashboard-lang-badge oc-dashboard-lang-badge--it" title="' + escapeHtml(i18n.itBadge || 'IT') + '">' +
                escapeHtml(i18n.itBadge || 'IT') + '</span>';
        }

        if (hasEn) {
            badges += '<span class="oc-dashboard-lang-badge oc-dashboard-lang-badge--en" title="' + escapeHtml(i18n.enBadge || 'EN') + '">' +
                escapeHtml(i18n.enBadge || 'EN') + '</span>';
        } else {
            badges += '<span class="oc-dashboard-lang-badge oc-dashboard-lang-badge--pending" title="' + escapeHtml(i18n.enBadge || 'EN') + '">' +
                escapeHtml(i18n.enBadge || 'EN') + '</span>';
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
            '<i class="bi bi-pencil-square" aria-hidden="true"></i>' +
            '</button>' +
            '</div>'
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
            const label = escapeHtml(i18n.setDraft || 'Bozza');
            const title = escapeHtml(i18n.setDraftTitle || 'Metti in bozza');
            return (
                '<button type="button" class="oc-dashboard-btn oc-dashboard-btn--status oc-dashboard-btn--to-draft" ' +
                'title="' + title + '" onclick="toggleEsperienzaStatus(' + id + ', \'publish\')">' +
                '<i class="bi bi-eye-slash" aria-hidden="true"></i> ' + label +
                '</button>'
            );
        }

        const label = escapeHtml(i18n.setPublish || 'Pubblica');
        const title = escapeHtml(i18n.setPublishTitle || 'Pubblica esperienza');
        const current = escapeHtml(status);
        return (
            '<button type="button" class="oc-dashboard-btn oc-dashboard-btn--status oc-dashboard-btn--to-publish" ' +
            'title="' + title + '" onclick="toggleEsperienzaStatus(' + id + ', \'' + current + '\')">' +
            '<i class="bi bi-check-circle" aria-hidden="true"></i> ' + label +
            '</button>'
        );
    }

    function renderPublicViewLink(esperienza) {
        if (esperienza.status !== 'publish' || !esperienza.view_url) {
            return '';
        }
        const viewUrl = escapeHtml(esperienza.view_url);
        const title = escapeHtml(i18n.viewPublicTitle || 'Apri pagina pubblica');
        return (
            '<a href="' + viewUrl + '" class="oc-dashboard-btn oc-dashboard-btn--view oc-dashboard-btn--icon" ' +
            'title="' + title + '" target="_blank" rel="noopener noreferrer">' +
            '<i class="bi bi-eye" aria-hidden="true"></i>' +
            '</a>'
        );
    }

    window.renderEsperienze = function renderEsperienze() {
        if (typeof window.loadEsperienze === 'function') {
            window.loadEsperienze();
        }
    };

    let currentQrUrl = '';

    window.openEsperienzaQrModal = function openEsperienzaQrModal(url, title) {
        const modal = document.getElementById('ocDashboardQrModal');
        const wrap = document.getElementById('ocDashboardQrCanvasWrap');
        const urlEl = document.getElementById('ocDashboardQrUrl');
        const titleEl = document.getElementById('ocDashboardQrTitle');

        if (!modal || !wrap || !url) {
            return;
        }

        currentQrUrl = url;
        wrap.innerHTML = '';
        if (titleEl) {
            titleEl.textContent = title ? String(title) : (i18n.qrTitle || 'QR Code esperienza');
        }
        if (urlEl) {
            urlEl.textContent = url;
        }

        if (typeof QRCode !== 'undefined') {
            const canvas = document.createElement('canvas');
            wrap.appendChild(canvas);
            QRCode.toCanvas(canvas, url, { width: 220, margin: 2 }, function () {});
        } else {
            wrap.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&amp;data=' + encodeURIComponent(url) + '" alt="QR" width="220" height="220">';
        }

        modal.hidden = false;
        document.body.classList.add('oc-dashboard-qr-open');
    };

    window.closeEsperienzaQrModal = function closeEsperienzaQrModal() {
        const modal = document.getElementById('ocDashboardQrModal');
        if (modal) {
            modal.hidden = true;
        }
        document.body.classList.remove('oc-dashboard-qr-open');
        currentQrUrl = '';
    };

    let currentTranslationsPostId = 0;

    function syncTranslationEditorsToTextareas(root) {
        if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
            window.tinymce.triggerSave();
        }
        if (!root) {
            return;
        }
        root.querySelectorAll('.oc-trans-html-editor-wrap textarea[id]').forEach(function (textarea) {
            if (window.tinymce) {
                const editor = window.tinymce.get(textarea.id);
                if (editor && !editor.isHidden()) {
                    textarea.value = editor.getContent();
                }
            }
        });
    }

    function destroyTranslationHtmlEditors(root) {
        if (!root || !window.wp || !window.wp.editor || typeof window.wp.editor.remove !== 'function') {
            return;
        }
        root.querySelectorAll('.oc-trans-html-editor-wrap textarea[id]').forEach(function (textarea) {
            if (textarea.id) {
                window.wp.editor.remove(textarea.id);
            }
            delete textarea.dataset.editorInit;
        });
    }

    function initTranslationHtmlEditors(root) {
        if (!root || !window.wp || !window.wp.editor || typeof window.wp.editor.initialize !== 'function') {
            return;
        }
        const settings = typeof window.wp.editor.getDefaultSettings === 'function'
            ? window.wp.editor.getDefaultSettings()
            : null;
        if (!settings) {
            return;
        }

        root.querySelectorAll('.oc-trans-html-editor-wrap textarea[id]').forEach(function (textarea) {
            if (!textarea.id || textarea.dataset.editorInit === '1') {
                return;
            }
            if (window.tinymce && window.tinymce.get(textarea.id)) {
                textarea.dataset.editorInit = '1';
                return;
            }
            textarea.dataset.editorInit = '1';
            window.wp.editor.initialize(textarea.id, {
                tinymce: settings.tinymce,
                quicktags: settings.quicktags,
                mediaButtons: true,
            });
        });
    }

    function setTranslationFieldValue(fieldsRoot, inputName, value) {
        const input = fieldsRoot.querySelector('[name="' + inputName + '"]');
        if (!input) {
            return;
        }
        if (window.tinymce && input.id) {
            const editor = window.tinymce.get(input.id);
            if (editor && !editor.isHidden()) {
                editor.setContent(value || '');
                input.value = value || '';
                return;
            }
        }
        input.value = value || '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function setTranslationsLoading(isLoading) {
        const loading = document.getElementById('ocDashboardTranslationsLoading');
        if (loading) {
            loading.hidden = !isLoading;
        }
    }

    function setTranslationsAiMode(enabled) {
        const panel = document.querySelector('#ocDashboardTranslationsModal .oc-dashboard-trans-modal__panel');
        if (panel) {
            panel.classList.toggle('oc-dashboard-trans-modal--ai-on', !!enabled);
        }
    }

    function setupTranslationsAiUi(aiConfigured) {
        const aiBar = document.getElementById('ocDashboardTranslationsAiBar');
        const aiToggle = document.getElementById('ocDashboardTranslationsAiToggle');
        if (!aiBar || !aiToggle) {
            return;
        }

        if (aiConfigured) {
            aiBar.hidden = false;
        } else {
            aiBar.hidden = true;
            aiToggle.checked = false;
        }
        setTranslationsAiMode(aiToggle.checked);

        if (!aiToggle.dataset.bound) {
            aiToggle.dataset.bound = '1';
            aiToggle.addEventListener('change', function () {
                setTranslationsAiMode(aiToggle.checked);
            });
        }
    }

    function bindAutoTranslateButtons() {
        const fieldsRoot = document.getElementById('ocDashboardTranslationsFields');
        if (!fieldsRoot) {
            return;
        }
        fieldsRoot.querySelectorAll('.oc-ai-auto-translate-btn').forEach(function (btn) {
            if (btn.dataset.bound === '1') {
                return;
            }
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                if (!currentTranslationsPostId || !config.ajaxUrl || !config.translationsNonce) {
                    return;
                }
                const fieldKey = btn.getAttribute('data-field-key') || '';
                if (!fieldKey) {
                    return;
                }

                const originalLabel = btn.textContent;
                btn.disabled = true;
                btn.textContent = i18n.aiTranslating || 'Traduzione in corso...';

                const formData = new FormData();
                formData.append('action', 'opencomune_ai_translate_esperienza_field');
                formData.append('nonce', config.translationsNonce);
                formData.append('post_id', String(currentTranslationsPostId));
                formData.append('field_key', fieldKey);

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (!data.success || !data.data) {
                            throw new Error(data.data && data.data.message ? data.data.message : 'error');
                        }
                        setTranslationFieldValue(fieldsRoot, data.data.input_name, data.data.value || '');
                    })
                    .catch(function (err) {
                        const msg = err && err.message ? err.message : (i18n.aiTranslateError || 'Impossibile tradurre questo campo.');
                        if (window.Swal) {
                            Swal.fire({ icon: 'error', title: msg });
                        }
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.textContent = originalLabel || (i18n.aiAutoTranslate || 'Traduci automaticamente');
                    });
            });
        });
    }

    window.closeEsperienzaTranslationsModal = function closeEsperienzaTranslationsModal() {
        const modal = document.getElementById('ocDashboardTranslationsModal');
        if (modal) {
            modal.hidden = true;
        }
        document.body.classList.remove('oc-dashboard-trans-open');
        currentTranslationsPostId = 0;
        const fields = document.getElementById('ocDashboardTranslationsFields');
        if (fields) {
            destroyTranslationHtmlEditors(fields);
            fields.innerHTML = '';
        }
        const aiToggle = document.getElementById('ocDashboardTranslationsAiToggle');
        if (aiToggle) {
            aiToggle.checked = false;
        }
        setTranslationsAiMode(false);
        const aiBar = document.getElementById('ocDashboardTranslationsAiBar');
        if (aiBar) {
            aiBar.hidden = true;
        }
    };

    window.openEsperienzaTranslationsModal = function openEsperienzaTranslationsModal(postId, title) {
        const modal = document.getElementById('ocDashboardTranslationsModal');
        const fields = document.getElementById('ocDashboardTranslationsFields');
        const postInput = document.getElementById('ocDashboardTranslationsPostId');
        const subtitle = document.getElementById('ocDashboardTranslationsSubtitle');
        const titleEl = document.getElementById('ocDashboardTranslationsTitle');

        if (!modal || !fields || !config.ajaxUrl || !config.translationsNonce) {
            return;
        }

        currentTranslationsPostId = parseInt(postId, 10) || 0;
        if (!currentTranslationsPostId) {
            return;
        }

        if (postInput) {
            postInput.value = String(currentTranslationsPostId);
        }
        if (subtitle) {
            subtitle.textContent = title ? String(title) : '';
        }
        if (titleEl) {
            titleEl.textContent = i18n.translationsTitle || 'Traduzioni inglese';
        }

        fields.innerHTML = '';
        modal.hidden = false;
        document.body.classList.add('oc-dashboard-trans-open');
        setTranslationsLoading(true);

        const url = config.ajaxUrl +
            '?action=opencomune_get_esperienza_translations_form' +
            '&post_id=' + encodeURIComponent(currentTranslationsPostId) +
            '&nonce=' + encodeURIComponent(config.translationsNonce);

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success || !data.data || !data.data.html) {
                    throw new Error(data.data && data.data.message ? data.data.message : 'load');
                }
                fields.innerHTML = data.data.html;
                window.setTimeout(function () {
                    initTranslationHtmlEditors(fields);
                }, 0);
                setupTranslationsAiUi(!!data.data.ai_configured);
                bindAutoTranslateButtons();
            })
            .catch(function () {
                fields.innerHTML = '<p class="text-red-600">' + escapeHtml(i18n.translationsLoadError || 'Impossibile caricare le traduzioni') + '</p>';
            })
            .finally(function () {
                setTranslationsLoading(false);
            });
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-close-qr-modal]').forEach(function (el) {
            el.addEventListener('click', closeEsperienzaQrModal);
        });

        const copyBtn = document.getElementById('ocDashboardQrCopyBtn');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                if (!currentQrUrl) {
                    return;
                }
                navigator.clipboard.writeText(currentQrUrl).then(function () {
                    if (window.Swal) {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: i18n.copied || 'Link copiato', showConfirmButton: false, timer: 1800 });
                    }
                });
            });
        }

        const navLinks = document.querySelectorAll('[data-dashboard-nav]');
        navLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                navLinks.forEach(function (item) {
                    item.classList.remove('is-active');
                });
                link.classList.add('is-active');
            });
        });
        if (navLinks.length && !document.querySelector('[data-dashboard-nav].is-active')) {
            navLinks[0].classList.add('is-active');
        }

        document.querySelectorAll('[data-close-trans-modal]').forEach(function (el) {
            el.addEventListener('click', closeEsperienzaTranslationsModal);
        });

        const transForm = document.getElementById('ocDashboardTranslationsForm');
        if (transForm) {
            transForm.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!currentTranslationsPostId || !config.ajaxUrl || !config.translationsNonce) {
                    return;
                }

                const saveBtn = document.getElementById('ocDashboardTranslationsSaveBtn');
                const fieldsRoot = document.getElementById('ocDashboardTranslationsFields');
                syncTranslationEditorsToTextareas(fieldsRoot);
                const formData = new FormData(transForm);
                formData.append('action', 'opencomune_save_esperienza_translations_dashboard');
                formData.append('nonce', config.translationsNonce);
                formData.set('post_id', String(currentTranslationsPostId));

                if (saveBtn) {
                    saveBtn.disabled = true;
                }
                setTranslationsLoading(true);

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (!data.success) {
                            throw new Error(data.data && data.data.message ? data.data.message : 'save');
                        }
                        if (window.Swal) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: i18n.translationsSaved || 'Traduzioni salvate',
                                showConfirmButton: false,
                                timer: 2000,
                            });
                        }
                        if (typeof window.loadEsperienze === 'function') {
                            window.loadEsperienze();
                        }
                    })
                    .catch(function () {
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'error',
                                title: i18n.translationsSaveError || 'Errore durante il salvataggio',
                            });
                        }
                    })
                    .finally(function () {
                        setTranslationsLoading(false);
                        if (saveBtn) {
                            saveBtn.disabled = false;
                        }
                    });
            });
        }

        document.addEventListener('click', function (event) {
            const qrBtn = event.target.closest('.js-dashboard-qr');
            if (qrBtn) {
                openEsperienzaQrModal(qrBtn.getAttribute('data-qr-url') || '', qrBtn.getAttribute('data-qr-title') || '');
                return;
            }

            const transBtn = event.target.closest('.js-dashboard-translations');
            if (transBtn) {
                openEsperienzaTranslationsModal(
                    transBtn.getAttribute('data-trans-id') || '',
                    transBtn.getAttribute('data-trans-title') || ''
                );
            }
        });
    });
})();
