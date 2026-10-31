(function () {
    'use strict';

    const config = window.opencomunePlannerMedia || {};
    const i18n = config.i18n || {};

    function initField(root) {
        if (!root || root.dataset.plannerMediaReady === '1') {
            return;
        }
        if (typeof wp === 'undefined' || !wp.media) {
            return;
        }

        root.dataset.plannerMediaReady = '1';

        const selectBtn = root.querySelector('[data-planner-media-select]');
        const removeBtn = root.querySelector('[data-planner-media-remove]');
        const previewWrap = root.querySelector('[data-planner-media-preview-wrap]');
        const previewImg = root.querySelector('[data-planner-media-preview-img]');
        const urlInput = root.querySelector('[data-planner-media-url]');
        const attachmentInput = root.querySelector('[data-planner-media-attachment-id]');

        let frame = null;

        function setPreview(url, attachmentId) {
            if (urlInput) {
                urlInput.value = url || '';
            }
            if (attachmentInput) {
                attachmentInput.value = attachmentId ? String(attachmentId) : '';
            }
            if (previewImg && url) {
                previewImg.src = url;
            }
            if (previewWrap) {
                previewWrap.classList.toggle('is-hidden', !url);
            }
            if (removeBtn) {
                removeBtn.classList.toggle('is-hidden', !url);
            }
        }

        if (selectBtn) {
            selectBtn.addEventListener('click', function (event) {
                event.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: i18n.title || 'Seleziona immagine',
                    button: {
                        text: i18n.button || 'Usa questa immagine',
                    },
                    library: {
                        type: 'image',
                    },
                    multiple: false,
                });

                frame.on('select', function () {
                    const attachment = frame.state().get('selection').first().toJSON();
                    const previewUrl =
                        attachment.sizes && attachment.sizes.medium
                            ? attachment.sizes.medium.url
                            : attachment.url;
                    setPreview(attachment.url || '', attachment.id || 0);
                    if (previewImg && previewUrl) {
                        previewImg.src = previewUrl;
                    }
                });

                frame.open();
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                setPreview('', 0);
                if (previewImg) {
                    previewImg.removeAttribute('src');
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-planner-media-field]').forEach(initField);
    });
})();
