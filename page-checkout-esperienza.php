<?php
/**
 * Template Name: Checkout Esperienza
 *
 * Wizard checkout (contatto → viaggiatori → riepilogo / pagamento).
 */

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'opencomune-checkout-esperienza',
        get_template_directory_uri() . '/assets/css/checkout-esperienza.css',
        [],
        '1.0.12'
    );

    $payment_result_css = get_template_directory() . '/assets/css/payment-result.css';
    wp_enqueue_style(
        'opencomune-payment-result',
        get_template_directory_uri() . '/assets/css/payment-result.css',
        [],
        is_readable($payment_result_css) ? (string) filemtime($payment_result_css) : '1.0.0'
    );
}, 100);

get_header();

$token = isset($_GET['session']) ? sanitize_text_field(wp_unslash($_GET['session'])) : '';
$session = $token !== '' ? opencomune_get_checkout_session($token) : null;

if (!$session) {
    echo '<main class="max-w-3xl mx-auto p-8 text-center"><p class="text-red-600 font-semibold">';
    esc_html_e('Sessione checkout scaduta o non valida.', 'opencomune');
    echo '</p></main>';
    get_footer();
    exit;
}

$payload = $session['payload'];
$experience_id = (int) $session['experience_id'];
$session_status = (string) ($session['status'] ?? 'open');
$requires_approval = opencomune_experience_requires_booking_approval($experience_id);
$checkout_mode = 'wizard';
if ($session_status === 'pending_approval') {
    $checkout_mode = 'request_sent';
} elseif ($session_status === 'awaiting_payment') {
    $checkout_mode = 'payment_only';
}
$post = get_post($experience_id);
$title = $post ? $post->post_title : '';
$experience_url = $post ? get_permalink($post) : home_url('/');
$experience_image_url = opencomune_get_esperienza_thumbnail_url($experience_id, 'medium_large');
$rest_base = esc_url(rest_url('opencomune/v1/checkout/sessions/' . rawurlencode($token)));
$confirm_url = esc_url(rest_url('opencomune/v1/checkout/sessions/' . rawurlencode($token) . '/confirm'));
$payment_nonce = wp_create_nonce('processa_pagamento_nonce');
$ajax_url = esc_url(admin_url('admin-ajax.php'));
$requires_online = opencomune_experience_requires_online_payment($experience_id);
$allows_online = opencomune_experience_allows_online_payment($experience_id);
$gateway = opencomune_resolve_payment_gateway_config_for_experience($experience_id);
$total = (float) ($payload['totals']['total'] ?? 0);
$pricing_categories = opencomune_format_pricing_categories_for_api($experience_id);

$request_sent_slot_label = '';
if (!empty($payload['date'])) {
    $slot_ts = strtotime((string) $payload['date']);
    $request_sent_slot_label = $slot_ts ? date_i18n('d/m/Y', $slot_ts) : (string) $payload['date'];
    if (!empty($payload['time'])) {
        $request_sent_slot_label .= ' · ' . substr(sanitize_text_field((string) $payload['time']), 0, 5);
    }
}
$request_sent_participants = (int) ($payload['totals']['participants'] ?? 0);
$request_sent_contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];
$request_sent_email = sanitize_email($request_sent_contact['email'] ?? '');
$request_sent_booking_id = (int) ($session['booking_id'] ?? 0);
?>

<?php if ($checkout_mode === 'request_sent') : ?>
<main class="oc-payment-result">
    <div class="oc-payment-result__card">
        <div class="oc-payment-result__icon oc-payment-result__icon--pending" aria-hidden="true">
            <i class="bi bi-hourglass-split"></i>
        </div>
        <h1 class="oc-payment-result__title"><?php esc_html_e('Richiesta inviata', 'opencomune'); ?></h1>
        <p class="oc-payment-result__message">
            <?php esc_html_e('Il partner ha ricevuto la tua richiesta. Se verrà approvata, riceverai il link per completare il pagamento.', 'opencomune'); ?>
        </p>

        <div class="oc-payment-result__details">
            <?php if ($title !== '') : ?>
                <p><strong><?php esc_html_e('Esperienza', 'opencomune'); ?>:</strong> <?php echo esc_html($title); ?></p>
            <?php endif; ?>
            <?php if ($request_sent_slot_label !== '') : ?>
                <p><strong><?php esc_html_e('Data e ora', 'opencomune'); ?>:</strong> <?php echo esc_html($request_sent_slot_label); ?></p>
            <?php endif; ?>
            <?php if ($request_sent_participants > 0) : ?>
                <p><strong><?php esc_html_e('Partecipanti', 'opencomune'); ?>:</strong> <?php echo (int) $request_sent_participants; ?></p>
            <?php endif; ?>
            <?php if ($request_sent_booking_id > 0) : ?>
                <p><strong><?php esc_html_e('Prenotazione', 'opencomune'); ?>:</strong> #<?php echo (int) $request_sent_booking_id; ?></p>
            <?php endif; ?>
            <?php if ($request_sent_email !== '') : ?>
                <p><strong><?php esc_html_e('Email', 'opencomune'); ?>:</strong> <?php echo esc_html($request_sent_email); ?></p>
            <?php endif; ?>
        </div>

        <div class="oc-payment-result__actions">
            <a href="<?php echo esc_url($experience_url); ?>" class="oc-payment-result__btn oc-payment-result__btn--primary">
                <?php esc_html_e('Torna all\'esperienza', 'opencomune'); ?>
            </a>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="oc-payment-result__btn oc-payment-result__btn--secondary">
                <?php esc_html_e('Home', 'opencomune'); ?>
            </a>
        </div>
    </div>
</main>
<?php else : ?>

<main class="oc-checkout max-w-6xl mx-auto p-4 md:p-8" id="oc-checkout-app"
      data-rest="<?php echo esc_attr($rest_base); ?>"
      data-confirm="<?php echo esc_attr($confirm_url); ?>"
      data-token="<?php echo esc_attr($token); ?>"
      data-experience-id="<?php echo (int) $experience_id; ?>"
      data-requires-online="<?php echo $requires_online ? '1' : '0'; ?>"
      data-allows-online="<?php echo $allows_online ? '1' : '0'; ?>"
      data-gateway="<?php echo esc_attr($gateway['gateway']); ?>"
      data-total="<?php echo esc_attr(number_format($total, 2, '.', '')); ?>"
      data-ajax="<?php echo esc_attr($ajax_url); ?>"
      data-payment-nonce="<?php echo esc_attr($payment_nonce); ?>"
      data-experience-url="<?php echo esc_url($experience_url); ?>"
      data-checkout-mode="<?php echo esc_attr($checkout_mode); ?>"
      data-requires-approval="<?php echo $requires_approval ? '1' : '0'; ?>"
      data-session-status="<?php echo esc_attr($session_status); ?>">

    <div class="oc-checkout__progress">
        <span class="is-active" data-step-label="contact"><?php esc_html_e('Contatto', 'opencomune'); ?></span>
        <span data-step-label="travelers"><?php esc_html_e('Partecipanti', 'opencomune'); ?></span>
        <span data-step-label="review"><?php esc_html_e('Riepilogo Ordine', 'opencomune'); ?></span>
    </div>

    <div class="oc-checkout__grid">
        <div class="oc-checkout__main">
            <section class="oc-checkout__panel is-active" data-panel="contact">
                <h1 class="text-2xl font-bold mb-4"><?php esc_html_e('Contatto principale', 'opencomune'); ?></h1>
                <form id="oc-checkout-contact" class="space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" for="oc-first-name"><?php esc_html_e('Nome', 'opencomune'); ?> *</label>
                            <input class="oc-field-input" id="oc-first-name" name="first_name" required autocomplete="given-name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" for="oc-last-name"><?php esc_html_e('Cognome', 'opencomune'); ?> *</label>
                            <input class="oc-field-input" id="oc-last-name" name="last_name" required autocomplete="family-name">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" for="oc-email"><?php esc_html_e('E-mail', 'opencomune'); ?> *</label>
                        <input type="email" class="oc-field-input" id="oc-email" name="email" required autocomplete="email">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" for="oc-phone"><?php esc_html_e('Telefono', 'opencomune'); ?> *</label>
                        <div class="oc-phone-input-row">
                            <?php
                            opencomune_render_phone_prefix_select([
                                'id' => 'oc-phone-prefix',
                                'name' => 'phone_prefix',
                                'class' => 'oc-field-input oc-phone-prefix-select',
                                'selected' => opencomune_get_default_phone_prefix(),
                            ]);
                            ?>
                            <input type="tel" class="oc-field-input oc-phone-number" id="oc-phone" name="phone" autocomplete="tel-national" inputmode="tel" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" for="oc-notes"><?php esc_html_e('Note', 'opencomune'); ?></label>
                        <textarea class="oc-field-input" id="oc-notes" name="notes" rows="3"></textarea>
                    </div>
                    <div class="oc-checkout__actions">
                        <button type="button" class="oc-checkout__btn oc-checkout__btn--cancel" data-oc-cancel-checkout>
                            <?php esc_html_e('Annulla', 'opencomune'); ?>
                        </button>
                        <button type="button" class="oc-checkout__btn oc-checkout__btn--primary" id="oc-contact-continue">
                            <?php esc_html_e('Continua', 'opencomune'); ?>
                        </button>
                    </div>
                </form>
            </section>

            <section class="oc-checkout__panel" data-panel="travelers" hidden>
                <h2 class="text-2xl font-bold mb-4"><?php esc_html_e('Partecipanti all\'esperienza', 'opencomune'); ?></h2>
                <form id="oc-checkout-travelers" class="space-y-4">
                    <p class="text-sm text-gray-600" id="oc-travelers-lead"></p>
                    <div id="oc-travelers-fields" class="space-y-6"></div>
                    <div id="oc-travelers-minors" class="oc-travelers-minors" hidden></div>
                    <div class="oc-checkout__actions">
                        <button type="button" class="oc-checkout__btn oc-checkout__btn--cancel" data-oc-cancel-checkout>
                            <?php esc_html_e('Annulla', 'opencomune'); ?>
                        </button>
                        <button type="button" class="oc-checkout__btn oc-checkout__btn--secondary" data-oc-goto="contact">
                            <?php esc_html_e('Indietro', 'opencomune'); ?>
                        </button>
                        <button type="button" class="oc-checkout__btn oc-checkout__btn--primary" id="oc-travelers-continue">
                            <?php esc_html_e('Continua', 'opencomune'); ?>
                        </button>
                    </div>
                </form>
            </section>

            <section class="oc-checkout__panel" data-panel="review" hidden>
                <h2 class="text-2xl font-bold mb-4"><?php esc_html_e('Ci siamo quasi', 'opencomune'); ?></h2>
                <div class="oc-review-card border rounded-lg p-4 mb-4 bg-white" id="oc-review-card"></div>
                <div class="oc-checkout-consents">
                    <label class="oc-checkout-switch" for="oc-consent-cancel">
                        <input type="checkbox" id="oc-consent-cancel" class="oc-checkout-switch__input" required>
                        <span class="oc-checkout-switch__track" aria-hidden="true"><span class="oc-checkout-switch__thumb"></span></span>
                        <span class="oc-checkout-switch__label"><?php esc_html_e('Accetto la politica sulle disdette', 'opencomune'); ?></span>
                    </label>
                    <label class="oc-checkout-switch" for="oc-consent-privacy">
                        <input type="checkbox" id="oc-consent-privacy" class="oc-checkout-switch__input" required>
                        <span class="oc-checkout-switch__track" aria-hidden="true"><span class="oc-checkout-switch__thumb"></span></span>
                        <span class="oc-checkout-switch__label"><?php esc_html_e('Ho letto l\'informativa sulla privacy', 'opencomune'); ?></span>
                    </label>
                </div>
                <?php if ($allows_online && $requires_online && (!$requires_approval || $checkout_mode === 'payment_only')) : ?>
                <div class="oc-checkout-payment-inline" id="oc-review-payment">
                    <h3 class="oc-checkout-payment-inline__title"><?php esc_html_e('Pagamento', 'opencomune'); ?></h3>
                    <div class="oc-checkout-payment-inline__total">
                        <span><?php esc_html_e('Totale da pagare', 'opencomune'); ?></span>
                        <strong id="oc-review-payment-total"><?php echo esc_html(number_format($total, 2, ',', '.')); ?> &euro;</strong>
                    </div>
                    <div class="oc-checkout-payment-inline__gateway">
                        <img
                            class="oc-checkout-payment-inline__gateway-logo"
                            src="<?php echo esc_url(get_template_directory_uri() . '/assets/payment_logo/sumuplogo.svg'); ?>"
                            alt="<?php echo esc_attr(opencomune_get_payment_gateway_labels()['sumup'] ?? 'SumUp'); ?>"
                            width="1188"
                            height="500"
                            decoding="async"
                        >
                    </div>
                </div>
                <?php endif; ?>
                <div class="oc-checkout__actions">
                    <button type="button" class="oc-checkout__btn oc-checkout__btn--cancel" data-oc-cancel-checkout>
                        <?php esc_html_e('Annulla', 'opencomune'); ?>
                    </button>
                    <button type="button" class="oc-checkout__btn oc-checkout__btn--secondary" data-oc-goto="travelers">
                        <?php esc_html_e('Indietro', 'opencomune'); ?>
                    </button>
                    <button type="button" id="oc-confirm-booking" class="oc-checkout__btn oc-checkout__btn--primary">
                        <?php
                        if ($checkout_mode === 'payment_only' && $requires_online && $allows_online) {
                            esc_html_e('Procedi al pagamento', 'opencomune');
                        } elseif ($requires_approval) {
                            esc_html_e('Invia richiesta', 'opencomune');
                        } elseif ($requires_online && $allows_online) {
                            esc_html_e('Procedi al pagamento', 'opencomune');
                        } else {
                            esc_html_e('Conferma prenotazione', 'opencomune');
                        }
                        ?>
                    </button>
                </div>
            </section>
        </div>

        <aside class="oc-checkout__summary">
            <?php if ($experience_image_url) : ?>
                <div class="oc-checkout__summary-media">
                    <img src="<?php echo esc_url($experience_image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" width="320" height="180">
                </div>
            <?php endif; ?>
            <div class="oc-checkout__summary-body">
            <h3 class="font-bold text-lg mb-2"><?php echo esc_html($title); ?></h3>
            <p class="text-sm text-gray-600 mb-4" id="oc-summary-meta"></p>
            <div class="flex justify-between font-semibold text-lg border-t pt-3">
                <span><?php esc_html_e('Totale (EUR)', 'opencomune'); ?></span>
                <span id="oc-summary-total"><?php echo esc_html(number_format($total, 2, ',', '.')); ?> €</span>
            </div>
            </div>
        </aside>
    </div>
</main>

<style>
.oc-checkout__progress { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:2rem; font-size:0.875rem; }
.oc-checkout__progress span { padding-bottom:0.25rem; border-bottom:3px solid #e5e7eb; color:#6b7280; }
.oc-checkout__progress span.is-active { border-color:#1d4ed8; color:#1e3a8a; font-weight:600; }
.oc-checkout__grid { display:grid; gap:2rem; }
@media (min-width: 900px) { .oc-checkout__grid { grid-template-columns: 1fr 320px; } }
.oc-checkout__summary { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:0; height:fit-content; overflow:hidden; }
.oc-checkout__summary-body { padding:1.25rem; }
.oc-checkout__panel[hidden] { display:none; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('oc-checkout-app');
    if (!root) return;

    const restUrl = root.dataset.rest;
    const confirmUrl = root.dataset.confirm;
    const checkoutToken = root.dataset.token || '';
    const experienceId = root.dataset.experienceId;
    const allowsOnline = root.dataset.allowsOnline === '1';
    const gateway = root.dataset.gateway;
    const ajaxUrl = root.dataset.ajax;
    const paymentNonce = root.dataset.paymentNonce;
    const experienceUrl = root.dataset.experienceUrl || '/';
    const checkoutMode = root.dataset.checkoutMode || 'wizard';
    const requiresApproval = root.dataset.requiresApproval === '1';

    let sessionPayload = <?php echo wp_json_encode($payload); ?>;
    if (<?php echo (int) ($session['booking_id'] ?? 0); ?> > 0 && !sessionPayload.booking_id) {
        sessionPayload.booking_id = <?php echo (int) ($session['booking_id'] ?? 0); ?>;
    }
    let cancelInProgress = false;
    const phonePrefixes = <?php echo wp_json_encode(array_keys(opencomune_get_phone_prefix_options())); ?>;
    const defaultPhonePrefix = <?php echo wp_json_encode(opencomune_get_default_phone_prefix()); ?>;

    const contactForm = document.getElementById('oc-checkout-contact');
    const travelersForm = document.getElementById('oc-checkout-travelers');

    const pricingCategories = <?php echo wp_json_encode($pricing_categories); ?>;
    const pricingCategoryById = {};
    pricingCategories.forEach(function (cat) {
        if (cat && cat.id) {
            pricingCategoryById[cat.id] = cat;
        }
    });

    const checkoutLabels = <?php echo wp_json_encode([
        'travelerTitle' => __('Partecipante %1$d di %2$d', 'opencomune'),
        'firstName' => __('Nome', 'opencomune'),
        'lastName' => __('Cognome', 'opencomune'),
        'leadSingle' => __('Inserisci nome e cognome dell\'adulto partecipante.', 'opencomune'),
        'leadMultiple' => __('Inserisci nome e cognome per tutti i %d adulti.', 'opencomune'),
        'leadAdultOnly' => __('Inserisci i dati degli adulti. I minori sono indicati sotto.', 'opencomune'),
        'participantFallback' => __('Partecipante', 'opencomune'),
        'minorLine' => __('+ %d %s', 'opencomune'),
    ]); ?>;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function categoryCollectsTravelerName(categoryId, categoryLabel) {
        const cat = pricingCategoryById[categoryId];
        if (cat && typeof cat.collects_traveler_name === 'boolean') {
            return cat.collects_traveler_name;
        }
        if (cat) {
            if (cat.max_age !== null && cat.max_age !== undefined && parseInt(cat.max_age, 10) < 18) {
                return false;
            }
            if (cat.min_age !== null && cat.min_age !== undefined && parseInt(cat.min_age, 10) >= 18) {
                return true;
            }
        }
        const lower = String(categoryLabel || '').toLowerCase();
        if (/bambin|child|infant|ragazz|minor/.test(lower)) {
            return false;
        }
        return true;
    }

    function getTravelerParticipantBreakdown() {
        const lines = (sessionPayload.totals && sessionPayload.totals.lines) || [];
        const adultSlots = [];
        const minorLines = [];

        lines.forEach(function (line) {
            const qty = parseInt(line.quantity, 10) || 0;
            if (qty <= 0) {
                return;
            }
            const label = line.label || checkoutLabels.participantFallback;
            const categoryId = line.category_id || '';
            if (categoryCollectsTravelerName(categoryId, label)) {
                for (let i = 0; i < qty; i++) {
                    adultSlots.push({ category_id: categoryId, category_label: label });
                }
            } else {
                minorLines.push({ category_id: categoryId, category_label: label, quantity: qty });
            }
        });

        if (!adultSlots.length && !minorLines.length) {
            const totalPax = parseInt((sessionPayload.totals && sessionPayload.totals.participants) || '1', 10) || 1;
            for (let i = 0; i < totalPax; i++) {
                adultSlots.push({ category_id: '', category_label: checkoutLabels.participantFallback });
            }
        }

        return { adultSlots, minorLines };
    }

    function formatMinorSummaryLine(quantity, label) {
        return checkoutLabels.minorLine
            .replace('%d', String(quantity))
            .replace('%s', label);
    }

    function buildMinorGroupTravelers(minorLines) {
        return minorLines.map(function (line) {
            return {
                first_name: '',
                last_name: '',
                category_id: line.category_id,
                category_label: line.category_label,
                minor_group: true,
                quantity: line.quantity,
            };
        });
    }

    function collectAdultTravelersFromForm() {
        const container = document.getElementById('oc-travelers-fields');
        if (!container) {
            return [];
        }
        const list = [];
        container.querySelectorAll('.oc-traveler-block').forEach(function (block) {
            list.push({
                first_name: (block.querySelector('.oc-traveler-first')?.value || '').trim(),
                last_name: (block.querySelector('.oc-traveler-last')?.value || '').trim(),
                category_id: block.getAttribute('data-category-id') || '',
                category_label: block.getAttribute('data-category-label') || '',
            });
        });
        return list;
    }

    function collectTravelersFromForm() {
        const breakdown = getTravelerParticipantBreakdown();
        return collectAdultTravelersFromForm().concat(buildMinorGroupTravelers(breakdown.minorLines));
    }

    function renderTravelerFields() {
        const container = document.getElementById('oc-travelers-fields');
        if (!container) {
            return;
        }
        const breakdown = getTravelerParticipantBreakdown();
        const slots = breakdown.adultSlots;
        const total = slots.length;
        const saved = collectAdultTravelersFromForm();
        const fromSession = Array.isArray(sessionPayload.travelers)
            ? sessionPayload.travelers.filter(function (t) { return !t.minor_group; })
            : [];
        const contact = sessionPayload.contact || {};
        let html = '';

        slots.forEach(function (slot, index) {
            let firstName = '';
            let lastName = '';
            if (saved[index] && (saved[index].first_name || saved[index].last_name)) {
                firstName = saved[index].first_name;
                lastName = saved[index].last_name;
            } else if (fromSession[index]) {
                firstName = fromSession[index].first_name || '';
                lastName = fromSession[index].last_name || '';
            } else if (index === 0 && contact.first_name) {
                firstName = contact.first_name;
                lastName = contact.last_name || '';
            }

            const title = checkoutLabels.travelerTitle
                .replace('%1$d', String(index + 1))
                .replace('%2$d', String(total));
            const subtitle = slot.category_label
                ? '<span class="oc-traveler-block__category">' + escapeHtml(slot.category_label) + '</span>'
                : '';

            html += '<div class="oc-traveler-block" data-traveler-index="' + index + '" data-category-id="' + escapeHtml(slot.category_id) + '" data-category-label="' + escapeHtml(slot.category_label) + '">';
            html += '<h3 class="oc-traveler-block__title">' + escapeHtml(title) + subtitle + '</h3>';
            html += '<div class="grid md:grid-cols-2 gap-4">';
            html += '<div><label class="block text-sm font-medium mb-1">' + escapeHtml(checkoutLabels.firstName) + ' *</label>';
            html += '<input type="text" class="oc-field-input oc-traveler-first" required autocomplete="given-name" value="' + escapeHtml(firstName) + '"></div>';
            html += '<div><label class="block text-sm font-medium mb-1">' + escapeHtml(checkoutLabels.lastName) + ' *</label>';
            html += '<input type="text" class="oc-field-input oc-traveler-last" required autocomplete="family-name" value="' + escapeHtml(lastName) + '"></div>';
            html += '</div></div>';
        });

        container.innerHTML = html;

        const minorsEl = document.getElementById('oc-travelers-minors');
        if (minorsEl) {
            if (breakdown.minorLines.length) {
                const lines = breakdown.minorLines.map(function (line) {
                    return formatMinorSummaryLine(line.quantity, line.category_label);
                });
                minorsEl.innerHTML = lines.map(function (text) {
                    return '<p class="oc-travelers-minors__line">' + escapeHtml(text) + '</p>';
                }).join('');
                minorsEl.hidden = false;
            } else {
                minorsEl.innerHTML = '';
                minorsEl.hidden = true;
            }
        }

        const lead = document.getElementById('oc-travelers-lead');
        if (lead) {
            if (breakdown.minorLines.length) {
                lead.textContent = total <= 1
                    ? checkoutLabels.leadAdultOnly
                    : checkoutLabels.leadMultiple.replace('%d', String(total));
            } else if (total <= 1) {
                lead.textContent = checkoutLabels.leadSingle;
            } else {
                lead.textContent = checkoutLabels.leadMultiple.replace('%d', String(total));
            }
        }
    }

    function setStep(step) {
        root.querySelectorAll('[data-panel]').forEach(function (el) {
            el.hidden = el.getAttribute('data-panel') !== step;
        });
        root.querySelectorAll('[data-step-label]').forEach(function (el) {
            el.classList.toggle('is-active', el.getAttribute('data-step-label') === step);
        });
        if (step === 'travelers') {
            renderTravelerFields();
        }
        window.scrollTo({ top: root.offsetTop - 16, behavior: 'smooth' });
    }

    function patchSession(body) {
        return fetch(restUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok || (data && data.code)) {
                    const msg = (data && data.message) ? data.message : 'Impossibile salvare i dati. Riprova.';
                    throw new Error(msg);
                }
                if (data && data.payload) {
                    sessionPayload = data.payload;
                }
                return data;
            });
        });
    }

    function setButtonLoading(btn, loading, loadingText) {
        if (!btn) return;
        if (loading) {
            btn.disabled = true;
            btn.dataset.ocDefaultLabel = btn.textContent;
            btn.textContent = loadingText || 'Attendere…';
        } else {
            btn.disabled = false;
            if (btn.dataset.ocDefaultLabel) {
                btn.textContent = btn.dataset.ocDefaultLabel;
            }
        }
    }

    function refreshSummary() {
        const totals = sessionPayload.totals || {};
        const meta = document.getElementById('oc-summary-meta');
        if (meta && sessionPayload.date && sessionPayload.time) {
            meta.textContent = sessionPayload.date + ' - ' + sessionPayload.time + ' · ' + (totals.participants || 1) + ' partecipanti';
        }
        const card = document.getElementById('oc-review-card');
        if (card) {
            const experienceTitle = <?php echo wp_json_encode($title); ?>;
            const experienceImageUrl = <?php echo wp_json_encode($experience_image_url); ?>;
            let travelersHtml = '';
            const travelers = Array.isArray(sessionPayload.travelers) ? sessionPayload.travelers : [];
            if (travelers.length) {
                travelersHtml = '<ul class="oc-review-card__travelers">';
                travelers.forEach(function (t, i) {
                    if (t.minor_group && t.quantity) {
                        travelersHtml += '<li class="oc-review-card__travelers-minor">' +
                            escapeHtml(formatMinorSummaryLine(parseInt(t.quantity, 10) || 0, t.category_label || '')) +
                            '</li>';
                        return;
                    }
                    const name = ((t.first_name || '') + ' ' + (t.last_name || '')).trim();
                    const cat = t.category_label ? ' <span class="text-gray-500">(' + escapeHtml(t.category_label) + ')</span>' : '';
                    travelersHtml += '<li>' + escapeHtml(name || ('Partecipante ' + (i + 1))) + cat + '</li>';
                });
                travelersHtml += '</ul>';
            }
            const mediaHtml = experienceImageUrl
                ? '<div class="oc-review-card__media"><img src="' + escapeHtml(experienceImageUrl) + '" alt="' + escapeHtml(experienceTitle) + '" loading="lazy" width="320" height="200"></div>'
                : '';
            card.innerHTML =
                '<div class="oc-review-card__layout">' +
                mediaHtml +
                '<div class="oc-review-card__body">' +
                '<p class="oc-review-card__title">' + escapeHtml(experienceTitle) + '</p>' +
                '<p class="oc-review-card__meta">' + escapeHtml(sessionPayload.date + ' · ' + sessionPayload.time) + '</p>' +
                travelersHtml +
                '</div></div>';
        }
    }

    function splitContactPhone(contact) {
        const result = { prefix: defaultPhonePrefix, number: '' };
        if (!contact) {
            return result;
        }
        if (contact.phone_prefix) {
            result.prefix = contact.phone_prefix;
        }
        if (contact.phone_local) {
            result.number = String(contact.phone_local).trim();
            return result;
        }
        const full = String(contact.phone || '').trim();
        if (!full) {
            return result;
        }
        for (let i = 0; i < phonePrefixes.length; i++) {
            const p = phonePrefixes[i];
            if (full.indexOf(p) === 0) {
                result.prefix = p;
                result.number = full.slice(p.length).trim();
                return result;
            }
        }
        result.number = full;
        return result;
    }

    function prefillContactFromSession() {
        const c = sessionPayload.contact;
        if (!c || !contactForm) return;
        const map = {
            first_name: 'oc-first-name',
            last_name: 'oc-last-name',
            email: 'oc-email',
            notes: 'oc-notes',
        };
        Object.keys(map).forEach(function (key) {
            const el = document.getElementById(map[key]);
            if (el && c[key]) {
                el.value = c[key];
            }
        });
        const phoneParts = splitContactPhone(c);
        const prefixEl = document.getElementById('oc-phone-prefix');
        const phoneEl = document.getElementById('oc-phone');
        if (prefixEl) {
            prefixEl.value = phoneParts.prefix;
        }
        if (phoneEl) {
            phoneEl.value = phoneParts.number;
        }
    }

    prefillContactFromSession();
    refreshSummary();

    let initialStep = sessionPayload.step && sessionPayload.step !== 'contact' ? sessionPayload.step : 'contact';
    if (initialStep === 'payment' || initialStep === 'request_sent') {
        initialStep = 'review';
    }
    if (checkoutMode === 'payment_only') {
        initialStep = 'review';
    }
    if (checkoutMode !== 'request_sent' && initialStep !== 'contact') {
        setStep(initialStep);
    }
    if (checkoutMode === 'payment_only') {
        refreshSummary();
    }

    root.querySelectorAll('[data-oc-goto]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setStep(btn.getAttribute('data-oc-goto'));
        });
    });

    function cancelCheckout() {
        if (cancelInProgress) {
            return;
        }
        const confirmMessage = <?php echo wp_json_encode(__('Annullare la prenotazione e tornare all\'esperienza?', 'opencomune')); ?>;
        if (!window.confirm(confirmMessage)) {
            return;
        }
        cancelInProgress = true;
        root.querySelectorAll('[data-oc-cancel-checkout]').forEach(function (btn) {
            btn.disabled = true;
        });
        fetch(restUrl, { method: 'DELETE' })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { data: data };
                });
            })
            .then(function (res) {
                const target = (res.data && res.data.redirect_url) ? res.data.redirect_url : experienceUrl;
                window.location.href = target;
            })
            .catch(function () {
                window.location.href = experienceUrl;
            });
    }

    root.querySelectorAll('[data-oc-cancel-checkout]').forEach(function (btn) {
        btn.addEventListener('click', cancelCheckout);
    });

    function readContactFromForm() {
        const fd = new FormData(contactForm);
        const phonePrefix = String(fd.get('phone_prefix') || defaultPhonePrefix).trim();
        const phoneLocal = String(fd.get('phone') || '').trim();
        return {
            first_name: String(fd.get('first_name') || '').trim(),
            last_name: String(fd.get('last_name') || '').trim(),
            email: String(fd.get('email') || '').trim(),
            phone_prefix: phonePrefix,
            phone_local: phoneLocal,
            phone: phoneLocal ? (phonePrefix + ' ' + phoneLocal) : '',
            notes: String(fd.get('notes') || '').trim(),
        };
    }

    function goContactToTravelers() {
        if (!contactForm.reportValidity()) {
            return;
        }
        const contact = readContactFromForm();
        sessionPayload.contact = contact;
        const btn = document.getElementById('oc-contact-continue');
        setButtonLoading(btn, true);
        patchSession({ step: 'travelers', contact: contact })
            .then(function () {
                setStep('travelers');
            })
            .catch(function (err) {
                alert(err.message || 'Errore di salvataggio.');
            })
            .finally(function () {
                setButtonLoading(btn, false);
            });
    }

    const contactContinue = document.getElementById('oc-contact-continue');
    if (contactContinue) {
        contactContinue.addEventListener('click', goContactToTravelers);
    }
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();
            goContactToTravelers();
        });
    }

    function goTravelersToReview() {
        if (!travelersForm.reportValidity()) {
            return;
        }
        const breakdown = getTravelerParticipantBreakdown();
        const adults = collectTravelersFromForm().filter(function (t) { return !t.minor_group; });
        if (!adults.length && breakdown.adultSlots.length) {
            alert('Inserisci i dati di almeno un adulto.');
            return;
        }
        const invalidAdult = adults.find(function (t) {
            return !(t.first_name && t.last_name);
        });
        if (invalidAdult) {
            alert('Inserisci nome e cognome per ogni adulto.');
            return;
        }
        sessionPayload.travelers = adults.concat(buildMinorGroupTravelers(breakdown.minorLines));
        if (!sessionPayload.travelers.length) {
            alert('Inserisci i dati di almeno un partecipante.');
            return;
        }
        const btn = document.getElementById('oc-travelers-continue');
        setButtonLoading(btn, true);
        patchSession({ step: 'review', travelers: sessionPayload.travelers })
            .then(function () {
                refreshSummary();
                setStep('review');
            })
            .catch(function (err) {
                alert(err.message || 'Errore di salvataggio.');
            })
            .finally(function () {
                setButtonLoading(btn, false);
            });
    }

    const travelersContinue = document.getElementById('oc-travelers-continue');
    if (travelersContinue) {
        travelersContinue.addEventListener('click', goTravelersToReview);
    }
    if (travelersForm) {
        travelersForm.addEventListener('submit', function (e) {
            e.preventDefault();
            goTravelersToReview();
        });
    }

    function buildCheckoutParticipantRows() {
        const lines = (sessionPayload.totals && sessionPayload.totals.lines) || [];
        if (!lines.length) {
            return [];
        }
        return lines.map(function (line) {
            return {
                category_id: line.category_id || '',
                quantity: parseInt(line.quantity, 10) || 0,
            };
        }).filter(function (row) { return row.quantity > 0; });
    }

    function startCheckoutPayment(triggerBtn) {
        const c = sessionPayload.contact || {};
        const phoneParts = splitContactPhone(c);
        const participantRows = buildCheckoutParticipantRows();
        const body = new URLSearchParams({
            action: 'opencomune_processa_pagamento',
            nonce: paymentNonce,
            tour_id: experienceId,
            data: sessionPayload.date || '',
            orario: sessionPayload.time || '',
            event_id: sessionPayload.event_id || '',
            nome: c.first_name || '',
            cognome: c.last_name || '',
            email: c.email || '',
            telefono: c.phone_local || phoneParts.number,
            prefisso: c.phone_prefix || phoneParts.prefix,
            note: c.notes || '',
            gateway: gateway,
            checkout_token: checkoutToken,
            booking_id: sessionPayload.booking_id || '',
            checkout_total: (sessionPayload.totals && sessionPayload.totals.total != null)
                ? String(sessionPayload.totals.total)
                : '',
            participants_json: participantRows.length ? JSON.stringify(participantRows) : '',
        });
        if (triggerBtn) {
            triggerBtn.disabled = true;
        }
        return fetch(ajaxUrl, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success && res.data && res.data.redirect_url) {
                    window.location.href = res.data.redirect_url;
                    return;
                }
                const errMsg = (res.data && res.data.message) ? res.data.message : (res.data || 'Pagamento non disponibile');
                alert(errMsg);
                if (triggerBtn) {
                    triggerBtn.disabled = false;
                }
            })
            .catch(function () {
                alert('Errore di rete');
                if (triggerBtn) {
                    triggerBtn.disabled = false;
                }
            });
    }

    const confirmBookingBtn = document.getElementById('oc-confirm-booking');
    if (confirmBookingBtn) {
        confirmBookingBtn.addEventListener('click', function () {
            if (checkoutMode === 'payment_only') {
                if (!document.getElementById('oc-consent-cancel').checked || !document.getElementById('oc-consent-privacy').checked) {
                    alert('Accetta le condizioni per continuare.');
                    return;
                }
                startCheckoutPayment(confirmBookingBtn);
                return;
            }
            if (!document.getElementById('oc-consent-cancel').checked || !document.getElementById('oc-consent-privacy').checked) {
                alert('Accetta le condizioni per continuare.');
                return;
            }
            const btn = this;
            btn.disabled = true;
            fetch(confirmUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.code) {
                        alert(data.message || 'Errore conferma');
                        btn.disabled = false;
                        return;
                    }
                    if (data.booking_id) {
                        sessionPayload.booking_id = data.booking_id;
                    }
                    if (data.requires_approval || data.status === 'pending_approval') {
                        window.location.reload();
                        return;
                    }
                    if (data.requires_payment && allowsOnline) {
                        return startCheckoutPayment(btn);
                    }
                    alert('Prenotazione confermata! ID #' + data.booking_id);
                    window.location.href = <?php echo wp_json_encode($post ? get_permalink($post) : home_url('/')); ?>;
                })
                .catch(function () {
                    alert('Errore di rete');
                    btn.disabled = false;
                });
        });
    }
});
</script>

<?php endif; ?>

<?php
get_footer();
