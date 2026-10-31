(function () {
    'use strict';

    var dismissLabel = 'Chiudi';
    var hostId = 'oc-planner-toast-host';

    function setDismissLabel(label) {
        if (label && String(label).trim() !== '') {
            dismissLabel = String(label);
        }
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return escapeHtml(str);
    }

    function ensureHost() {
        var host = document.getElementById(hostId);
        if (host) {
            document.body.appendChild(host);
            return host;
        }
        host = document.createElement('div');
        host.id = hostId;
        host.className = 'oc-planner-toast-host';
        host.setAttribute('aria-live', 'polite');
        host.setAttribute('aria-relevant', 'additions');
        document.body.appendChild(host);
        return host;
    }

    function getTextEl(el) {
        if (!el) {
            return null;
        }
        return el.querySelector('.oc-planner-alert__text');
    }

    function dismissAlert(el) {
        if (!el) {
            return;
        }
        el.hidden = true;
        var textEl = getTextEl(el);
        if (textEl) {
            textEl.textContent = '';
        }
    }

    function bindDismiss(el) {
        if (!el || el.dataset.ocPlannerAlertBound === '1') {
            return;
        }
        el.dataset.ocPlannerAlertBound = '1';
        var btn = el.querySelector('.oc-planner-alert__dismiss');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            } else {
                dismissAlert(el);
            }
            var host = document.getElementById(hostId);
            if (host && host.children.length === 0) {
                host.hidden = true;
            }
        });
    }

    function buildToast(message, variant) {
        variant = variant === 'success' ? 'success' : 'error';
        var toast = document.createElement('div');
        toast.className = 'oc-planner-alert oc-planner-alert--toast oc-planner-alert--' + variant;
        toast.setAttribute('role', variant === 'success' ? 'status' : 'alert');
        toast.innerHTML =
            '<span class="oc-planner-alert__text">' +
            escapeHtml(message) +
            '</span>' +
            '<button type="button" class="oc-planner-alert__dismiss" aria-label="' +
            escapeAttr(dismissLabel) +
            '"><span aria-hidden="true">&times;</span></button>';
        bindDismiss(toast);
        return toast;
    }

    function show(message, variant) {
        var msg = message ? String(message).trim() : '';
        if (msg === '') {
            clear();
            return null;
        }
        var host = ensureHost();
        host.hidden = false;
        host.innerHTML = '';
        var toast = buildToast(msg, variant);
        host.appendChild(toast);
        return toast;
    }

    function clear() {
        var host = document.getElementById(hostId);
        if (host) {
            host.innerHTML = '';
            host.hidden = true;
        }
    }

    function setAlert(el, message, variant) {
        if (el) {
            dismissAlert(el);
        }
        show(message, variant);
    }

    function alertMarkup(message, variant, options) {
        options = options || {};
        variant = variant === 'success' ? 'success' : 'error';
        var idAttr = options.id ? ' id="' + escapeAttr(options.id) + '"' : '';
        var extraClass = options.extraClass ? ' ' + escapeHtml(options.extraClass) : ' oc-planner-alert--toast';
        if (extraClass.indexOf('oc-planner-alert--toast') === -1) {
            extraClass += ' oc-planner-alert--toast';
        }
        var hidden = options.hidden || !message ? ' hidden' : '';
        var label = options.dismissLabel || dismissLabel;

        return (
            '<div' +
            idAttr +
            ' class="oc-planner-alert oc-planner-alert--' +
            variant +
            extraClass +
            '" role="' +
            (variant === 'success' ? 'status' : 'alert') +
            '"' +
            hidden +
            '>' +
            '<span class="oc-planner-alert__text">' +
            escapeHtml(message || '') +
            '</span>' +
            '<button type="button" class="oc-planner-alert__dismiss" aria-label="' +
            escapeAttr(label) +
            '"><span aria-hidden="true">&times;</span></button>' +
            '</div>'
        );
    }

    function bindAll(scope) {
        var root = scope && scope.querySelectorAll ? scope : document;
        root.querySelectorAll('.oc-planner-alert').forEach(bindDismiss);
    }

    window.opencomunePlannerAlert = {
        setDismissLabel: setDismissLabel,
        show: show,
        clear: clear,
        set: setAlert,
        dismiss: dismissAlert,
        bind: bindDismiss,
        bindAll: bindAll,
        markup: alertMarkup,
        ensureHost: ensureHost,
    };
})();
