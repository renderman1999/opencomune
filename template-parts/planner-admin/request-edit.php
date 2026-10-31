<?php
if (!defined('ABSPATH')) {
    exit;
}

$id = opencomune_planner_admin_get_record_id();
$trip = $id > 0 ? opencomune_planner_get_trip_by_id($id) : null;

if ($trip === null) {
    echo '<p class="text-gray-600">' . esc_html__('Richiesta non trovata.', 'opencomune') . '</p>';
    echo '<p><a class="text-blue-700 hover:text-blue-900 font-medium" href="' . esc_url(opencomune_planner_admin_base_url('requests')) . '">';
    esc_html_e('Torna alle richieste', 'opencomune');
    echo '</a></p>';
    return;
}

$itinerary_url = add_query_arg('token', $trip['token'], home_url('/itinerario/'));
$contact = opencomune_planner_contact_from_trip_payload($trip);
?>

<p class="mb-4">
    <a href="<?php echo esc_url(opencomune_planner_admin_base_url('requests')); ?>" class="text-blue-700 hover:text-blue-900 font-medium">
        <i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>
        <?php esc_html_e('Torna alle richieste', 'opencomune'); ?>
    </a>
</p>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
    <form method="post" class="oc-profilo-partner-form" action="<?php echo esc_url(opencomune_planner_admin_form_action('request-edit', ['id' => $id])); ?>">
        <?php wp_nonce_field('opencomune_planner_request'); ?>
        <input type="hidden" name="oc_planner_action" value="save_request">
        <input type="hidden" name="request_id" value="<?php echo esc_attr((string) $id); ?>">

        <div class="form-section">
            <h2 class="section-title"><?php esc_html_e('Contatto', 'opencomune'); ?></h2>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="form-label" for="planner_request_name"><?php esc_html_e('Nome', 'opencomune'); ?></label>
                    <input class="form-input" type="text" id="planner_request_name" name="contact_name" required minlength="3"
                           value="<?php echo esc_attr($contact['name']); ?>">
                </div>
                <div>
                    <label class="form-label" for="planner_request_email"><?php esc_html_e('Email', 'opencomune'); ?></label>
                    <input class="form-input" type="email" id="planner_request_email" name="contact_email" required
                           value="<?php echo esc_attr($contact['email']); ?>">
                </div>
                <div>
                    <label class="form-label" for="planner_request_phone"><?php esc_html_e('Telefono', 'opencomune'); ?></label>
                    <input class="form-input" type="text" id="planner_request_phone" name="contact_phone"
                           value="<?php echo esc_attr($contact['phone']); ?>">
                </div>
                <div>
                    <label class="form-label" for="planner_request_notes"><?php esc_html_e('Note del visitatore', 'opencomune'); ?></label>
                    <textarea class="form-input" id="planner_request_notes" name="contact_notes" rows="4"><?php echo esc_textarea($contact['notes']); ?></textarea>
                </div>
                <div>
                    <label class="form-label" for="planner_request_client_message"><?php esc_html_e('Messaggio personalizzato (email al cliente)', 'opencomune'); ?></label>
                    <textarea class="form-input" id="planner_request_client_message" name="client_email_message" rows="4"><?php echo esc_textarea((string) ($trip['clientEmailMessage'] ?? '')); ?></textarea>
                    <p class="text-sm text-gray-500 mt-1"><?php esc_html_e('Testo aggiuntivo incluso nell\'email con link all\'itinerario.', 'opencomune'); ?></p>
                </div>
            </div>
        </div>

        <div class="oc-profilo-partner-actions flex flex-wrap gap-3">
            <button type="submit" class="btn-primary"><?php esc_html_e('Salva modifiche', 'opencomune'); ?></button>
        </div>
    </form>

    <div class="oc-planner-request-meta">
        <div class="form-section">
            <h2 class="section-title"><?php esc_html_e('Viaggio', 'opencomune'); ?></h2>
            <dl class="oc-planner-request-dl">
                <dt><?php esc_html_e('Date', 'opencomune'); ?></dt>
                <dd><?php echo esc_html($trip['startDate'] . ' → ' . $trip['endDate']); ?></dd>
                <dt><?php esc_html_e('Partenza', 'opencomune'); ?></dt>
                <dd><?php echo esc_html($trip['startTime'] . ' – ' . $trip['startAddress']); ?></dd>
                <dt><?php esc_html_e('Richiesta inviata', 'opencomune'); ?></dt>
                <dd><?php echo $trip['emailSentAt'] ? esc_html((string) $trip['emailSentAt']) : '—'; ?></dd>
                <dt><?php esc_html_e('Ultima email al cliente', 'opencomune'); ?></dt>
                <dd><?php echo !empty($trip['clientEmailSentAt']) ? esc_html((string) $trip['clientEmailSentAt']) : '—'; ?></dd>
                <dt><?php esc_html_e('Link pubblico', 'opencomune'); ?></dt>
                <dd>
                    <a href="<?php echo esc_url($itinerary_url); ?>" class="text-blue-700 hover:text-blue-900 break-all" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html($itinerary_url); ?>
                    </a>
                </dd>
            </dl>
        </div>

        <form method="post" class="mt-4" action="<?php echo esc_url(opencomune_planner_admin_form_action('request-edit', ['id' => $id])); ?>">
            <?php wp_nonce_field('opencomune_planner_request'); ?>
            <input type="hidden" name="oc_planner_action" value="resend_request">
            <input type="hidden" name="request_id" value="<?php echo esc_attr((string) $id); ?>">
            <button type="submit" class="btn-secondary"><?php esc_html_e('Reinvia email al cliente', 'opencomune'); ?></button>
        </form>

        <form method="post" class="mt-4" action="<?php echo esc_url(opencomune_planner_admin_form_action('requests')); ?>" onsubmit="return confirm('<?php echo esc_js(sprintf(__('Eliminare la richiesta di "%s"?', 'opencomune'), $contact['name'] !== '' ? $contact['name'] : $contact['email'])); ?>');">
            <?php wp_nonce_field('opencomune_planner_request'); ?>
            <input type="hidden" name="oc_planner_action" value="delete_request">
            <input type="hidden" name="request_id" value="<?php echo esc_attr((string) $id); ?>">
            <button type="submit" class="btn-secondary text-red-700 border-red-200 hover:bg-red-50"><?php esc_html_e('Elimina richiesta', 'opencomune'); ?></button>
        </form>
    </div>
</div>

<div class="form-section">
    <h2 class="section-title"><?php esc_html_e('Itinerario proposto', 'opencomune'); ?></h2>
    <?php if (empty($trip['days'])) : ?>
        <p class="text-gray-600"><?php esc_html_e('Nessuna tappa.', 'opencomune'); ?></p>
    <?php else : ?>
        <?php foreach ($trip['days'] as $day) : ?>
            <div class="oc-planner-request-day mb-6">
                <h3 class="font-bold text-lg mb-2"><?php echo esc_html((string) ($day['date'] ?? '')); ?></h3>
                <?php if (empty($day['stops']) || !is_array($day['stops'])) : ?>
                    <p class="text-gray-500 text-sm"><?php esc_html_e('Nessuna tappa.', 'opencomune'); ?></p>
                <?php else : ?>
                    <ol class="list-decimal list-inside space-y-1 text-sm">
                        <?php foreach ($day['stops'] as $stop) : ?>
                            <li>
                                <?php
                                echo esc_html(
                                    sprintf(
                                        '%s–%s · %s (%d min)',
                                        (string) ($stop['arrive'] ?? ''),
                                        (string) ($stop['leave'] ?? ''),
                                        (string) ($stop['name'] ?? ''),
                                        (int) ($stop['durationMin'] ?? 0)
                                    )
                                );
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
