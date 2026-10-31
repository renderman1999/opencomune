(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const cfg = window.opencomunePublicLogin || {};
        const loginPanel = document.getElementById('login-panel');
        const firstAccessPanel = document.getElementById('first-access-panel');
        const showFirstAccessBtn = document.getElementById('show-first-access-btn');
        const showLoginBtn = document.getElementById('show-login-btn');
        const loginForm = document.getElementById('loginform');
        const firstAccessForm = document.getElementById('first-access-form');
        const verifyError = document.getElementById('verify-error');
        const verifySuccess = document.getElementById('verify-success');
        const turnstileRequired = !!cfg.turnstileRequired;
        let firstAccessTurnstileMounted = false;

        function ensureFirstAccessTurnstile() {
            if (!turnstileRequired || firstAccessTurnstileMounted) {
                return;
            }
            const host = document.getElementById('first-access-turnstile-host');
            if (!host || typeof turnstile === 'undefined' || !cfg.turnstileSiteKey) {
                return;
            }
            turnstile.render(host, { sitekey: cfg.turnstileSiteKey });
            firstAccessTurnstileMounted = true;
        }

        function resetFirstAccessTurnstile() {
            if (!turnstileRequired || typeof turnstile === 'undefined') {
                return;
            }
            const host = document.getElementById('first-access-turnstile-host');
            if (!host) {
                return;
            }
            try {
                turnstile.reset(host);
            } catch (e) {
                host.innerHTML = '';
                firstAccessTurnstileMounted = false;
                ensureFirstAccessTurnstile();
            }
        }

        function getTurnstileToken(scope) {
            const root = scope || document;
            const field = root.querySelector('[name="cf-turnstile-response"]');
            return field && field.value ? field.value.trim() : '';
        }

        function showPanel(panel) {
            if (!loginPanel || !firstAccessPanel) {
                return;
            }
            if (panel === 'first-access') {
                loginPanel.classList.add('is-hidden');
                firstAccessPanel.classList.remove('is-hidden');
                ensureFirstAccessTurnstile();
            } else {
                firstAccessPanel.classList.add('is-hidden');
                loginPanel.classList.remove('is-hidden');
            }
        }

        if (showFirstAccessBtn) {
            showFirstAccessBtn.addEventListener('click', function () {
                showPanel('first-access');
            });
        }

        if (showLoginBtn) {
            showLoginBtn.addEventListener('click', function () {
                showPanel('login');
            });
        }

        if (loginForm) {
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            loginForm.addEventListener('submit', function (e) {
                if (turnstileRequired && !getTurnstileToken(loginForm)) {
                    e.preventDefault();
                    window.alert(cfg.turnstileMessage || 'Completa la verifica anti-spam prima di accedere.');
                    return;
                }
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> ' + (cfg.loggingInLabel || 'Accesso in corso...');
                    submitBtn.disabled = true;
                }
            });
        }

        if (firstAccessForm) {
            firstAccessForm.addEventListener('submit', function (e) {
                e.preventDefault();

                if (turnstileRequired && !getTurnstileToken(firstAccessPanel)) {
                    if (verifyError) {
                        verifyError.textContent = cfg.turnstileMessage || 'Completa la verifica anti-spam prima di continuare.';
                        verifyError.classList.remove('is-hidden');
                    }
                    ensureFirstAccessTurnstile();
                    return;
                }

                const submitBtn = document.getElementById('first-access-submit');
                const originalText = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> ' + (cfg.verifyingLabel || 'Verifica in corso...');
                    submitBtn.disabled = true;
                }
                if (verifyError) {
                    verifyError.classList.add('is-hidden');
                }
                if (verifySuccess) {
                    verifySuccess.classList.add('is-hidden');
                }

                const formData = new FormData();
                formData.append('action', 'opencomune_verify_partner_code');
                formData.append('_wpnonce', cfg.verifyNonce || '');
                formData.append('log', document.getElementById('first_access_login').value);
                formData.append('pwd', document.getElementById('first_access_pass').value);
                formData.append('confirmation_code', document.getElementById('confirmation_code').value);
                const turnstileToken = getTurnstileToken(firstAccessPanel);
                if (turnstileToken) {
                    formData.set('cf-turnstile-response', turnstileToken);
                }

                fetch(cfg.ajaxUrl || '', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.success) {
                            if (verifySuccess) {
                                verifySuccess.textContent = data.data.message || 'Account attivato con successo.';
                                verifySuccess.classList.remove('is-hidden');
                            }
                            window.location.href = data.data.redirect || cfg.dashboardUrl || '/';
                        } else {
                            if (verifyError) {
                                verifyError.textContent = data.data && data.data.message
                                    ? data.data.message
                                    : 'Errore durante la verifica.';
                                verifyError.classList.remove('is-hidden');
                            }
                            resetFirstAccessTurnstile();
                            if (submitBtn) {
                                submitBtn.innerHTML = originalText;
                                submitBtn.disabled = false;
                            }
                        }
                    })
                    .catch(function () {
                        if (verifyError) {
                            verifyError.textContent = cfg.connectionError || 'Errore di connessione. Riprova più tardi.';
                            verifyError.classList.remove('is-hidden');
                        }
                        resetFirstAccessTurnstile();
                        if (submitBtn) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    });
            });
        }

        if (firstAccessPanel && !firstAccessPanel.classList.contains('is-hidden')) {
            ensureFirstAccessTurnstile();
        }
    });
})();
