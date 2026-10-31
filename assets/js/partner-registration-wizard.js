(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const cfg = window.opencomunePartnerRegistration || {};
        let currentStep = 1;
        const totalSteps = 5;
        const stepTitles = {
            1: 'Dati partner',
            2: 'Localizzazione',
            3: 'Contatti',
            4: 'Accesso',
            5: 'Consensi',
        };

        const nextBtn = document.getElementById('next-btn');
        const prevBtn = document.getElementById('prev-btn');
        const submitBtn = document.getElementById('submit-btn');
        const navContainer = document.getElementById('partner-reg-nav');
        const progressEl = document.getElementById('partner-reg-progress');
        const stepItems = document.querySelectorAll('.oc-partner-reg__step');
        const turnstileRequired = !!cfg.turnstileRequired;
        let partnerTurnstileMounted = false;

        if (!nextBtn || !prevBtn || !submitBtn) {
            return;
        }

        function ensurePartnerRegistrationTurnstile() {
            if (!turnstileRequired || partnerTurnstileMounted) {
                return;
            }
            const host = document.getElementById('partner-registration-turnstile-host');
            if (!host || typeof turnstile === 'undefined' || !cfg.turnstileSiteKey) {
                return;
            }
            turnstile.render(host, {
                sitekey: cfg.turnstileSiteKey,
            });
            partnerTurnstileMounted = true;
        }

        function resetPartnerRegistrationTurnstile() {
            if (!turnstileRequired || typeof turnstile === 'undefined') {
                return;
            }
            const host = document.getElementById('partner-registration-turnstile-host');
            if (!host) {
                return;
            }
            try {
                turnstile.reset(host);
            } catch (e) {
                host.innerHTML = '';
                partnerTurnstileMounted = false;
                ensurePartnerRegistrationTurnstile();
            }
        }

        const descBreve = document.getElementById('descrizione_breve');
        const charCount = document.getElementById('char-count-1');
        if (descBreve && charCount) {
            descBreve.addEventListener('input', function () {
                charCount.textContent = String(this.value.length);
            });
        }

        const emailUfficialeInput = document.getElementById('email_ufficiale');
        const accessEmailPreview = document.getElementById('access-email-preview');

        function refreshAccessEmailPreview() {
            if (accessEmailPreview && emailUfficialeInput) {
                accessEmailPreview.textContent = (emailUfficialeInput.value || '').trim() || '—';
            }
        }

        if (emailUfficialeInput) {
            emailUfficialeInput.addEventListener('input', refreshAccessEmailPreview);
            refreshAccessEmailPreview();
        }

        function partnerRegistrationErrorMessage(data) {
            if (!data || typeof data !== 'object') {
                return 'Errore durante l\'invio della richiesta';
            }
            if (data.data && typeof data.data.message === 'string' && data.data.message !== '') {
                return data.data.message;
            }
            if (typeof data.message === 'string' && data.message !== '') {
                return data.message;
            }
            return 'Errore durante l\'invio della richiesta';
        }

        function showPartnerRegistrationModal(options) {
            const opts = options || {};
            if (typeof Swal !== 'undefined') {
                return Swal.fire({
                    icon: opts.icon || 'error',
                    title: opts.title || 'Attenzione',
                    text: opts.text || '',
                    confirmButtonText: opts.confirmButtonText || 'OK',
                    confirmButtonColor: cfg.confirmColor || '#ffad02',
                });
            }
            alert((opts.title ? opts.title + '\n\n' : '') + (opts.text || ''));
            return Promise.resolve();
        }

        function showPartnerRegistrationError(message) {
            return showPartnerRegistrationModal({
                icon: 'error',
                title: 'Registrazione non completata',
                text: message,
            });
        }

        function updateStepper(step) {
            stepItems.forEach(function (item) {
                const n = parseInt(item.getAttribute('data-step'), 10);
                item.classList.remove('is-active', 'is-done');
                if (n < step) {
                    item.classList.add('is-done');
                } else if (n === step) {
                    item.classList.add('is-active');
                }
            });
            if (progressEl) {
                progressEl.textContent = step + ' / ' + totalSteps + ' — ' + (stepTitles[step] || '');
            }
        }

        function showStep(step) {
            for (let i = 1; i <= totalSteps; i++) {
                const section = document.getElementById('section-' + i);
                if (section) {
                    section.hidden = i !== step;
                }
            }

            updateStepper(step);

            prevBtn.hidden = step <= 1;
            nextBtn.hidden = step >= totalSteps;
            submitBtn.hidden = step !== totalSteps;

            if (navContainer) {
                navContainer.classList.toggle('is-first-step', step === 1);
            }

            if (step === 4) {
                refreshAccessEmailPreview();
            }

            if (step === totalSteps) {
                ensurePartnerRegistrationTurnstile();
            }

            document.dispatchEvent(new CustomEvent('opencomune-partner-registration-step', {
                detail: { step: step },
            }));
        }

        function fieldIsEmpty(field) {
            if (field.type === 'checkbox') {
                return !field.checked;
            }
            return !(field.value || '').trim();
        }

        function validateCurrentStep() {
            const currentSection = document.getElementById('section-' + currentStep);
            if (!currentSection) {
                return false;
            }
            const requiredFields = currentSection.querySelectorAll('[required]');

            for (let i = 0; i < requiredFields.length; i++) {
                const field = requiredFields[i];
                if (fieldIsEmpty(field)) {
                    field.focus();
                    showPartnerRegistrationModal({
                        icon: 'warning',
                        title: 'Campi obbligatori',
                        text: 'Compila tutti i campi obbligatori prima di continuare.',
                    });
                    return false;
                }
            }

            if (currentStep === 2) {
                const cap = (document.getElementById('cap')?.value || '').trim();
                const comune = (document.getElementById('comune')?.value || '').trim();
                const indirizzo = (document.getElementById('indirizzo')?.value || '').trim();
                if (!indirizzo || !cap || !comune) {
                    showPartnerRegistrationModal({
                        icon: 'warning',
                        title: 'Indirizzo incompleto',
                        text: 'Seleziona un indirizzo completo dai suggerimenti (via, CAP e comune).',
                    });
                    return false;
                }
            }

            if (currentStep === 3) {
                const email = (emailUfficialeInput?.value || '').trim();
                if (!email || email.indexOf('@') === -1) {
                    showPartnerRegistrationModal({
                        icon: 'warning',
                        title: 'Email non valida',
                        text: 'Inserisci una email ufficiale valida.',
                    });
                    return false;
                }
            }

            return true;
        }

        nextBtn.addEventListener('click', function () {
            if (validateCurrentStep() && currentStep < totalSteps) {
                currentStep++;
                showStep(currentStep);
            }
        });

        prevBtn.addEventListener('click', function () {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        });

        const form = document.getElementById('partnerRegistrationForm');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (!validateCurrentStep()) {
                return;
            }

            if (turnstileRequired) {
                const turnstileToken = form.querySelector('[name="cf-turnstile-response"]')?.value?.trim();
                if (!turnstileToken) {
                    showPartnerRegistrationModal({
                        icon: 'warning',
                        title: 'Verifica richiesta',
                        text: 'Completa la verifica anti-spam prima di inviare la registrazione.',
                    });
                    ensurePartnerRegistrationTurnstile();
                    return;
                }
            }

            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Invio in corso...';
            submitBtn.disabled = true;

            const formData = new FormData();
            form.querySelectorAll('input, select, textarea').forEach(function (field) {
                if (!field.name) {
                    return;
                }
                if (field.type === 'checkbox') {
                    formData.append(field.name, field.checked ? '1' : '0');
                } else if (field.type === 'file') {
                    if (field.files.length > 0) {
                        formData.append(field.name, field.files[0]);
                    }
                } else {
                    formData.append(field.name, field.value);
                }
            });

            formData.append('action', 'opencomune_submit_partner_registration');
            formData.append('_wpnonce', cfg.nonce || '');
            const turnstileField = form.querySelector('[name="cf-turnstile-response"]');
            if (turnstileField && turnstileField.value) {
                formData.set('cf-turnstile-response', turnstileField.value);
            }

            fetch(cfg.ajaxUrl || '', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.text().then(function (text) {
                        try {
                            return JSON.parse(text);
                        } catch (parseError) {
                            const err = new Error('invalid_json');
                            err.raw = text;
                            throw err;
                        }
                    });
                })
                .then(function (data) {
                    if (data.success) {
                        showPartnerRegistrationModal({
                            icon: 'success',
                            title: 'Registrazione completata',
                            text: 'Controlla la tua email: riceverai la password e il codice per il primo accesso.',
                            confirmButtonText: 'Vai al login',
                        }).then(function () {
                            window.location.href = cfg.loginUrl || '/login/';
                        });
                    } else {
                        showPartnerRegistrationError(partnerRegistrationErrorMessage(data));
                        resetPartnerRegistrationTurnstile();
                    }
                })
                .catch(function (error) {
                    console.error('Errore registrazione partner:', error);
                    showPartnerRegistrationError('Errore di connessione. Riprova più tardi.');
                    resetPartnerRegistrationTurnstile();
                })
                .finally(function () {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        });

        showStep(1);
    });
})();
