<?php
/* Template Name: Dettaglio Prenotazione Partner */

if (!opencomune_user_can_access_dashboard()) {
    wp_safe_redirect(home_url());
    exit;
}

$booking_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($booking_id <= 0) {
    wp_safe_redirect(opencomune_get_dashboard_bookings_list_url());
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_booking_detail'])) {
    if (
        !isset($_POST['opencomune_booking_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['opencomune_booking_nonce'])), 'opencomune_save_booking_' . $booking_id)
    ) {
        $error_message = __('Sessione scaduta. Riprova.', 'opencomune');
    } else {
        $fields = [
            'nome' => isset($_POST['nome']) ? wp_unslash($_POST['nome']) : '',
            'email' => isset($_POST['email']) ? wp_unslash($_POST['email']) : '',
            'telefono' => isset($_POST['telefono']) ? wp_unslash($_POST['telefono']) : '',
            'note' => isset($_POST['note']) ? wp_unslash($_POST['note']) : '',
            'participants' => isset($_POST['participants']) ? (int) $_POST['participants'] : 1,
        ];
        if (opencomune_user_can_manage_all_esperienze() && isset($_POST['status'])) {
            $fields['status'] = wp_unslash($_POST['status']);
        }

        $save_result = opencomune_save_booking_details($booking_id, $fields);
        if (is_wp_error($save_result)) {
            $error_message = $save_result->get_error_message();
        } else {
            $success_message = __('Prenotazione aggiornata.', 'opencomune');
        }
    }
}

$detail = opencomune_get_booking_detail_payload($booking_id);
if (!$detail) {
    wp_safe_redirect(opencomune_get_dashboard_bookings_list_url());
    exit;
}

opencomune_mark_partner_bookings_seen();

$is_admin = opencomune_user_can_manage_all_esperienze();
$read_only = empty($detail['can_edit']);

opencomune_enqueue_dashboard_assets();
opencomune_render_dashboard_shell_open();

wp_localize_script('opencomune-booking-cancel-modal', 'opencomuneBookingCancelDetail', [
    'booking' => [
        'id' => (int) $detail['id'],
        'esperienza_title' => $detail['esperienza_title'],
        'nome' => $detail['nome'],
        'slot' => $detail['slot'],
        'status' => $detail['status'],
        'status_label' => $detail['status_label'],
        'participants' => (int) $detail['participants'],
    ],
    'order' => !empty($detail['order']) ? [
        'refund_amount_formatted' => $detail['order']['refund_amount_formatted'] ?? $detail['order']['amount_formatted'] ?? '',
    ] : null,
]);
?>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main">
        <section class="oc-dashboard-section oc-dashboard-panel oc-booking-detail p-6">
            <div class="mb-6">
                <p class="mb-2">
                    <a href="<?php echo esc_url($detail['list_url']); ?>" class="oc-booking-detail__back">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        <?php esc_html_e('Torna alle prenotazioni', 'opencomune'); ?>
                    </a>
                </p>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="oc-dashboard-section__title text-2xl mb-1">
                            <?php echo esc_html(sprintf(__('Prenotazione #%d', 'opencomune'), (int) $detail['id'])); ?>
                        </h1>
                        <p class="text-gray-600 mb-0">
                            <?php echo esc_html($detail['esperienza_title']); ?>
                            <?php if (!empty($detail['esperienza_url'])) : ?>
                                <a href="<?php echo esc_url($detail['esperienza_url']); ?>" class="text-blue-700 hover:text-blue-900 ml-2" target="_blank" rel="noopener noreferrer">
                                    <?php esc_html_e('Pagina pubblica', 'opencomune'); ?>
                                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php
                    $status_badge_class = 'oc-booking-status--cancelled';
                    if ($detail['status'] === 'confermata') {
                        $status_badge_class = 'oc-booking-status--confirmed';
                    } elseif ($detail['status'] === 'pending_payment') {
                        $status_badge_class = 'oc-booking-status--pending';
                    } elseif ($detail['status'] === 'pending_approval') {
                        $status_badge_class = 'oc-booking-status--pending-approval';
                    }
                    ?>
                    <span class="oc-booking-detail__header-status oc-booking-status oc-booking-status--header <?php echo esc_attr($status_badge_class); ?>">
                        <?php echo esc_html($detail['status_label']); ?>
                    </span>
                </div>
            </div>

            <?php if ($error_message !== '') : ?>
                <div class="error-message mb-4">
                    <i class="bi bi-exclamation-triangle mr-2" aria-hidden="true"></i>
                    <?php echo esc_html($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message !== '') : ?>
                <div class="success-message mb-4">
                    <i class="bi bi-check-circle mr-2" aria-hidden="true"></i>
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>

            <div class="oc-booking-detail__grid">
                <div class="oc-booking-detail__card">
                    <h2 class="oc-booking-detail__card-title"><?php esc_html_e('Slot e esperienza', 'opencomune'); ?></h2>
                    <dl class="oc-booking-detail__meta">
                        <dt><?php esc_html_e('Data e ora', 'opencomune'); ?></dt>
                        <dd><?php echo esc_html($detail['slot']); ?></dd>
                        <dt><?php esc_html_e('Prenotato il', 'opencomune'); ?></dt>
                        <dd><?php echo esc_html($detail['data_prenotazione']); ?></dd>
                        <dt><?php esc_html_e('Metodo pagamento', 'opencomune'); ?></dt>
                        <dd><?php echo esc_html($detail['metodo_pagamento'] !== '' ? $detail['metodo_pagamento'] : '—'); ?></dd>
                        <?php if (!empty($detail['edit_esperienza_url'])) : ?>
                            <dt><?php esc_html_e('Esperienza', 'opencomune'); ?></dt>
                            <dd>
                                <a href="<?php echo esc_url($detail['edit_esperienza_url']); ?>" class="text-blue-700 hover:text-blue-900">
                                    <?php esc_html_e('Modifica esperienza', 'opencomune'); ?>
                                </a>
                            </dd>
                        <?php endif; ?>
                    </dl>
                </div>

                <?php if (!empty($detail['order'])) : ?>
                    <div class="oc-booking-detail__card">
                        <h2 class="oc-booking-detail__card-title"><?php esc_html_e('Ordine di pagamento', 'opencomune'); ?></h2>
                        <dl class="oc-booking-detail__meta">
                            <dt><?php esc_html_e('Riferimento ordine', 'opencomune'); ?></dt>
                            <dd><?php echo esc_html($detail['order']['order_id'] !== '' ? $detail['order']['order_id'] : ('#' . (int) $detail['order']['id'])); ?></dd>
                            <dt><?php esc_html_e('Stato ordine', 'opencomune'); ?></dt>
                            <dd>
                                <span class="oc-booking-status <?php echo esc_attr($detail['order']['status_badge_class'] ?? 'oc-booking-status--pending'); ?>">
                                    <?php echo esc_html($detail['order']['status_label'] ?? $detail['order']['status']); ?>
                                </span>
                            </dd>
                            <dt><?php esc_html_e('Importo', 'opencomune'); ?></dt>
                            <dd><?php echo esc_html($detail['order']['amount_formatted'] ?? ''); ?></dd>
                            <dt><?php esc_html_e('Gateway', 'opencomune'); ?></dt>
                            <dd><?php echo esc_html($detail['order']['gateway_label'] ?? $detail['order']['gateway']); ?></dd>
                            <?php if (!empty($detail['order']['created_at'])) : ?>
                                <dt><?php esc_html_e('Ordine creato', 'opencomune'); ?></dt>
                                <dd><?php echo esc_html($detail['order']['created_at']); ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($detail['order']['paid_at'])) : ?>
                                <dt><?php esc_html_e('Pagamento registrato', 'opencomune'); ?></dt>
                                <dd><?php echo esc_html($detail['order']['paid_at']); ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($detail['order']['refund_status']) && $detail['order']['refund_status'] === 'refunded') : ?>
                                <dt><?php esc_html_e('Rimborso', 'opencomune'); ?></dt>
                                <dd>
                                    <?php
                                    echo esc_html($detail['order']['refund_status_label'] ?? __('Rimborsato', 'opencomune'));
                                    if (!empty($detail['order']['refunded_amount_formatted'])) {
                                        echo ' — ' . esc_html($detail['order']['refunded_amount_formatted']);
                                    }
                                    if (!empty($detail['order']['refunded_at'])) {
                                        echo ' (' . esc_html($detail['order']['refunded_at']) . ')';
                                    }
                                    ?>
                                </dd>
                            <?php endif; ?>
                            <?php if ($is_admin && !empty($detail['order']['commission_snapshot_stored'])) : ?>
                                <dt><?php esc_html_e('Commissione piattaforma', 'opencomune'); ?></dt>
                                <dd>
                                    <?php
                                    echo esc_html(
                                        number_format_i18n((float) $detail['order']['commission_percent'], 2) . '%'
                                        . ' (' . ($detail['order']['commission_amount_formatted'] ?? number_format_i18n((float) $detail['order']['commission_amount'], 2) . ' EUR') . ')'
                                    );
                                    ?>
                                </dd>
                                <?php if (!empty($detail['order']['net_amount_formatted']) || isset($detail['order']['net_amount'])) : ?>
                                    <dt><?php esc_html_e('Netto dopo commissione', 'opencomune'); ?></dt>
                                    <dd><?php echo esc_html($detail['order']['net_amount_formatted'] ?? number_format_i18n((float) $detail['order']['net_amount'], 2) . ' EUR'); ?></dd>
                                <?php endif; ?>
                            <?php endif; ?>
                        </dl>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($detail['order']) && (!empty($detail['order']['sumup']) || !empty($detail['order']['sumup_error']))) : ?>
                <details class="oc-booking-detail__card oc-booking-detail__card--sumup oc-booking-accordion mt-4">
                    <summary class="oc-booking-accordion__summary">
                        <span class="oc-booking-accordion__summary-text">
                            <span class="oc-booking-detail__card-title oc-booking-accordion__title"><?php esc_html_e('Transazione SumUp', 'opencomune'); ?></span>
                            <?php if (!empty($detail['order']['sumup']['transaction_code'])) : ?>
                                <span class="oc-booking-accordion__hint"><?php echo esc_html($detail['order']['sumup']['transaction_code']); ?></span>
                            <?php elseif (!empty($detail['order']['sumup']['amount_formatted'])) : ?>
                                <span class="oc-booking-accordion__hint"><?php echo esc_html($detail['order']['sumup']['amount_formatted']); ?></span>
                            <?php endif; ?>
                        </span>
                        <i class="bi bi-chevron-down oc-booking-accordion__chevron" aria-hidden="true"></i>
                    </summary>
                    <div class="oc-booking-accordion__panel">
                    <?php if (!empty($detail['order']['sumup_error'])) : ?>
                        <p class="text-sm text-gray-600 mb-0"><?php echo esc_html($detail['order']['sumup_error']); ?></p>
                    <?php else :
                        $sumup = $detail['order']['sumup']; ?>
                        <dl class="oc-booking-detail__meta">
                            <dt><?php esc_html_e('Stato checkout', 'opencomune'); ?></dt>
                            <dd><?php echo esc_html($sumup['checkout_status'] !== '' ? $sumup['checkout_status'] : '—'); ?></dd>
                            <dt><?php esc_html_e('Importo checkout', 'opencomune'); ?></dt>
                            <dd><?php echo esc_html($sumup['amount_formatted']); ?></dd>
                            <?php if (!empty($sumup['checkout_id'])) : ?>
                                <dt><?php esc_html_e('ID checkout SumUp', 'opencomune'); ?></dt>
                                <dd><code class="oc-booking-detail__code"><?php echo esc_html($sumup['checkout_id']); ?></code></dd>
                            <?php endif; ?>
                            <?php if (!empty($sumup['checkout_reference'])) : ?>
                                <dt><?php esc_html_e('Riferimento checkout', 'opencomune'); ?></dt>
                                <dd><code class="oc-booking-detail__code"><?php echo esc_html($sumup['checkout_reference']); ?></code></dd>
                            <?php endif; ?>
                            <?php if (!empty($sumup['merchant_code'])) : ?>
                                <dt><?php esc_html_e('Merchant', 'opencomune'); ?></dt>
                                <dd><?php echo esc_html($sumup['merchant_code']); ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($sumup['transaction_code'])) : ?>
                                <dt><?php esc_html_e('Codice transazione', 'opencomune'); ?></dt>
                                <dd><code class="oc-booking-detail__code"><?php echo esc_html($sumup['transaction_code']); ?></code></dd>
                            <?php endif; ?>
                            <?php if (!empty($sumup['transaction_id'])) : ?>
                                <dt><?php esc_html_e('ID transazione', 'opencomune'); ?></dt>
                                <dd><code class="oc-booking-detail__code"><?php echo esc_html($sumup['transaction_id']); ?></code></dd>
                            <?php endif; ?>
                            <?php if (!empty($sumup['transaction_status'])) : ?>
                                <dt><?php esc_html_e('Stato transazione', 'opencomune'); ?></dt>
                                <dd><?php echo esc_html($sumup['transaction_status']); ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($sumup['transaction_amount_formatted'])) : ?>
                                <dt><?php esc_html_e('Importo transazione', 'opencomune'); ?></dt>
                                <dd><?php echo esc_html($sumup['transaction_amount_formatted']); ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($sumup['payment_type'])) : ?>
                                <dt><?php esc_html_e('Tipo pagamento', 'opencomune'); ?></dt>
                                <dd><?php echo esc_html($sumup['payment_type']); ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($sumup['paid_at'])) : ?>
                                <dt><?php esc_html_e('Data/ora transazione', 'opencomune'); ?></dt>
                                <dd><?php echo esc_html($sumup['paid_at']); ?></dd>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>

            <form method="post" class="oc-booking-detail__form mt-6" id="ocBookingDetailForm">
                <?php wp_nonce_field('opencomune_save_booking_' . $booking_id, 'opencomune_booking_nonce'); ?>
                <input type="hidden" name="save_booking_detail" value="1">

                <h2 class="oc-booking-detail__card-title"><?php esc_html_e('Dati cliente e prenotazione', 'opencomune'); ?></h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="booking_nome" class="form-label"><?php esc_html_e('Nome completo', 'opencomune'); ?> <span class="form-required">*</span></label>
                        <input type="text" id="booking_nome" name="nome" class="form-input" required
                               value="<?php echo esc_attr($detail['nome']); ?>" <?php disabled($read_only); ?>>
                    </div>
                    <div>
                        <label for="booking_email" class="form-label"><?php esc_html_e('Email', 'opencomune'); ?> <span class="form-required">*</span></label>
                        <input type="email" id="booking_email" name="email" class="form-input" required
                               value="<?php echo esc_attr($detail['email']); ?>" <?php disabled($read_only); ?>>
                    </div>
                    <div>
                        <label for="booking_telefono" class="form-label"><?php esc_html_e('Telefono', 'opencomune'); ?></label>
                        <input type="text" id="booking_telefono" name="telefono" class="form-input"
                               value="<?php echo esc_attr($detail['telefono']); ?>" <?php disabled($read_only); ?>>
                    </div>
                    <div>
                        <label for="booking_participants" class="form-label"><?php esc_html_e('Partecipanti', 'opencomune'); ?></label>
                        <input type="number" id="booking_participants" name="participants" class="form-input" min="1" step="1"
                               value="<?php echo (int) $detail['participants']; ?>" <?php disabled($read_only); ?>>
                    </div>
                    <?php if ($is_admin) : ?>
                        <div>
                            <label for="booking_status" class="form-label"><?php esc_html_e('Stato prenotazione', 'opencomune'); ?></label>
                            <?php if (!empty($detail['can_change_status'])) : ?>
                                <select id="booking_status" name="status" class="form-input">
                                    <option value="confermata" <?php selected($detail['status'], 'confermata'); ?>><?php esc_html_e('Confermata', 'opencomune'); ?></option>
                                    <option value="pending_payment" <?php selected($detail['status'], 'pending_payment'); ?>><?php esc_html_e('In attesa pagamento', 'opencomune'); ?></option>
                                    <option value="pending_approval" <?php selected($detail['status'], 'pending_approval'); ?>><?php esc_html_e('In attesa approvazione', 'opencomune'); ?></option>
                                    <option value="cancellata" <?php selected($detail['status'], 'cancellata'); ?>><?php esc_html_e('Cancellata', 'opencomune'); ?></option>
                                </select>
                            <?php else : ?>
                                <p class="mb-1">
                                    <span class="oc-booking-status <?php echo esc_attr('oc-booking-status--' . ($detail['status'] === 'confermata' ? 'confirmed' : ($detail['status'] === 'pending_payment' ? 'pending' : 'cancelled'))); ?>">
                                        <?php echo esc_html($detail['status_label']); ?>
                                    </span>
                                </p>
                                <?php if (!empty($detail['status_change_locked_message'])) : ?>
                                    <p class="text-sm text-gray-500 mb-0"><?php echo esc_html($detail['status_change_locked_message']); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-4">
                    <label for="booking_note" class="form-label"><?php esc_html_e('Note', 'opencomune'); ?></label>
                    <textarea id="booking_note" name="note" class="form-input" rows="4" <?php disabled($read_only); ?>><?php echo esc_textarea($detail['note']); ?></textarea>
                </div>

                <?php if (!$read_only) : ?>
                    <div class="flex flex-wrap gap-3 mt-6">
                        <?php if (!empty($detail['can_approve_request'])) : ?>
                            <button type="button" id="js-booking-approve-request" class="btn-primary">
                                <?php esc_html_e('Approva richiesta', 'opencomune'); ?>
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($detail['can_reject_request'])) : ?>
                            <button type="button" id="js-booking-reject-request" class="oc-dashboard-btn oc-dashboard-btn--delete">
                                <?php esc_html_e('Rifiuta richiesta', 'opencomune'); ?>
                            </button>
                        <?php endif; ?>
                        <button type="submit" name="booking_action" value="save" class="btn-primary">
                            <?php esc_html_e('Salva modifiche', 'opencomune'); ?>
                        </button>
                        <?php if (!empty($detail['can_cancel'])) : ?>
                            <button type="button" id="js-booking-cancel-detail" class="oc-dashboard-btn oc-dashboard-btn--delete">
                                <?php esc_html_e('Annulla prenotazione', 'opencomune'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php elseif ($detail['status'] === 'cancellata') : ?>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <p class="text-sm text-gray-500 mb-0"><?php esc_html_e('Questa prenotazione è annullata.', 'opencomune'); ?></p>
                        <?php if (!empty($detail['can_refund'])) : ?>
                            <button type="button" id="js-booking-refund-detail" class="oc-dashboard-btn oc-dashboard-btn--refund">
                                <?php esc_html_e('Rimborsa pagamento', 'opencomune'); ?>
                            </button>
                        <?php elseif (!empty($detail['order']['refund_status']) && $detail['order']['refund_status'] === 'refunded') : ?>
                            <span class="text-sm text-emerald-700 font-medium"><?php esc_html_e('Pagamento rimborsato', 'opencomune'); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>

            <?php if (!empty($detail['activity_log'])) : ?>
                <div class="oc-booking-detail__card oc-booking-detail__card--log mt-6">
                    <h2 class="oc-booking-detail__card-title"><?php esc_html_e('Registro attività', 'opencomune'); ?></h2>
                    <ul class="oc-booking-activity-log">
                        <?php foreach ($detail['activity_log'] as $entry) : ?>
                            <li class="oc-booking-activity-log__item">
                                <div class="oc-booking-activity-log__meta">
                                    <strong><?php echo esc_html($entry['created_at']); ?></strong>
                                    <?php if ($entry['user'] !== '') : ?>
                                        <span><?php echo esc_html($entry['user']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (($entry['action'] ?? '') === 'payment_refund') : ?>
                                    <p class="oc-booking-activity-log__transition">
                                        <strong><?php echo esc_html($entry['action_label'] ?? __('Rimborso pagamento', 'opencomune')); ?></strong>
                                    </p>
                                <?php elseif (in_array($entry['action'] ?? '', ['request_submitted', 'request_approved', 'request_rejected'], true)) : ?>
                                    <p class="oc-booking-activity-log__transition">
                                        <strong><?php echo esc_html($entry['action_label'] ?? $entry['action']); ?></strong>
                                        <?php if (!empty($entry['status_to_label'])) : ?>
                                            <?php echo ' — ' . esc_html($entry['status_to_label']); ?>
                                        <?php endif; ?>
                                    </p>
                                <?php else : ?>
                                    <p class="oc-booking-activity-log__transition">
                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                /* translators: 1: old status, 2: new status */
                                                __('Stato: %1$s → %2$s', 'opencomune'),
                                                $entry['status_from_label'],
                                                $entry['status_to_label']
                                            )
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>
                                <p class="oc-booking-activity-log__reason"><?php echo esc_html($entry['reason']); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var payload = window.opencomuneBookingCancelDetail || {};

    var cancelBtn = document.getElementById('js-booking-cancel-detail');
    if (cancelBtn && typeof window.opencomuneOpenBookingCancelModal === 'function') {
        cancelBtn.addEventListener('click', function () {
            if (!payload.booking) {
                return;
            }
            window.opencomuneOpenBookingCancelModal(payload.booking, {
                onSuccess: function () {
                    window.location.reload();
                },
            });
        });
    }

    var refundBtn = document.getElementById('js-booking-refund-detail');
    if (refundBtn && typeof window.opencomuneOpenBookingRefundModal === 'function') {
        refundBtn.addEventListener('click', function () {
            if (!payload.booking) {
                return;
            }
            window.opencomuneOpenBookingRefundModal(payload.booking, payload.order || {}, {
                onSuccess: function () {
                    window.location.reload();
                },
            });
        });
    }

    var bookingsConfig = window.opencomuneDashboardBookings || {};
    var bookingId = payload.booking && payload.booking.id ? payload.booking.id : 0;

    function submitBookingRequest(decision, reason) {
        if (!bookingsConfig.ajaxUrl || !bookingsConfig.nonce || !bookingId) {
            return Promise.reject(new Error('Config mancante'));
        }
        var body = new FormData();
        body.append('action', 'opencomune_respond_booking_request');
        body.append('nonce', bookingsConfig.nonce);
        body.append('id', String(bookingId));
        body.append('decision', decision);
        if (reason) {
            body.append('reason', reason);
        }
        return fetch(bookingsConfig.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); });
    }

    var approveBtn = document.getElementById('js-booking-approve-request');
    if (approveBtn) {
        approveBtn.addEventListener('click', function () {
            var msg = <?php echo wp_json_encode(__('Approvi questa richiesta di prenotazione?', 'opencomune')); ?>;
            if (!window.confirm(msg)) {
                return;
            }
            approveBtn.disabled = true;
            submitBookingRequest('approve', '')
                .then(function (data) {
                    if (!data.success) {
                        alert((data.data && data.data.message) ? data.data.message : 'Errore');
                        approveBtn.disabled = false;
                        return;
                    }
                    window.location.reload();
                })
                .catch(function () {
                    alert('Errore di rete');
                    approveBtn.disabled = false;
                });
        });
    }

    var rejectBtn = document.getElementById('js-booking-reject-request');
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function () {
            var promptMsg = <?php echo wp_json_encode(__('Motivo del rifiuto (obbligatorio):', 'opencomune')); ?>;
            var reason = window.prompt(promptMsg);
            if (!reason || !reason.trim()) {
                return;
            }
            rejectBtn.disabled = true;
            submitBookingRequest('reject', reason.trim())
                .then(function (data) {
                    if (!data.success) {
                        alert((data.data && data.data.message) ? data.data.message : 'Errore');
                        rejectBtn.disabled = false;
                        return;
                    }
                    window.location.reload();
                })
                .catch(function () {
                    alert('Errore di rete');
                    rejectBtn.disabled = false;
                });
        });
    }
});
</script>

<?php opencomune_render_dashboard_shell_close(); ?>
