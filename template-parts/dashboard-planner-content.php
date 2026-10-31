<?php
/**
 * Contenuto gestione pianificatore (dashboard admin).
 *
 * @var array $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$success_message = isset($args['success_message']) ? (string) $args['success_message'] : '';
$error_message = isset($args['error_message']) ? (string) $args['error_message'] : '';
$data = opencomune_planner_get_admin_view_data();
?>

<div class="mb-8">
    <h1 class="oc-dashboard-section__title text-2xl sm:text-3xl mb-2"><?php esc_html_e('Pianificatore itinerario', 'opencomune'); ?></h1>
    <p class="text-gray-600 mb-0">
        <?php esc_html_e('Genera proposte di giornata usando solo le esperienze pubblicate con coordinate GPS.', 'opencomune'); ?>
    </p>
    <p class="mt-2">
        <a href="<?php echo esc_url(home_url('/dashboard-partner/')); ?>" class="text-blue-700 hover:text-blue-900 font-medium">
            <i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>
            <?php esc_html_e('Torna alla dashboard', 'opencomune'); ?>
        </a>
    </p>
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

<form method="post" class="oc-profilo-partner-form max-w-xl mb-10">
    <?php wp_nonce_field('opencomune_planner_settings'); ?>
    <input type="hidden" name="opencomune_planner_save" value="1">

    <div class="form-section">
        <label class="form-label flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="planner_enabled" value="1" <?php checked($data['enabled']); ?>>
            <?php esc_html_e('Mostra il wizard sul sito pubblico', 'opencomune'); ?>
        </label>
    </div>

    <div class="form-section mt-4">
        <label for="planner_max_days" class="form-label"><?php esc_html_e('Durata massima (giorni)', 'opencomune'); ?></label>
        <input type="number" min="1" max="7" id="planner_max_days" name="planner_max_days" class="form-input max-w-[120px]"
               value="<?php echo esc_attr((string) $data['max_days']); ?>">
    </div>

    <div class="form-section mt-4">
        <label for="planner_notify_email" class="form-label"><?php esc_html_e('Email notifiche (opzionale)', 'opencomune'); ?></label>
        <input type="email" class="form-input" id="planner_notify_email" name="planner_notify_email"
               value="<?php echo esc_attr($data['notify_email']); ?>">
    </div>

    <div class="mt-6">
        <button type="submit" class="btn-primary"><?php esc_html_e('Salva impostazioni', 'opencomune'); ?></button>
    </div>
</form>

<h2 class="text-xl font-bold mb-2"><?php esc_html_e('Esperienze nel catalogo', 'opencomune'); ?></h2>
<p class="text-gray-600">
    <?php
    printf(
        esc_html__('%1$d con coordinate GPS su %2$d pubblicate.', 'opencomune'),
        (int) $data['with_geo'],
        (int) $data['published']
    );
    ?>
</p>
<?php if ($data['with_geo'] < $data['published']) : ?>
    <p class="text-sm text-gray-500"><?php esc_html_e('Le esperienze senza latitudine/longitudine non compaiono nel pianificatore.', 'opencomune'); ?></p>
<?php endif; ?>

<h2 class="text-xl font-bold mt-8 mb-2"><?php esc_html_e('Pagina pubblica', 'opencomune'); ?></h2>
<p class="mb-3">
    <a href="<?php echo esc_url($data['wizard_url']); ?>" class="btn-secondary inline-flex items-center" target="_blank" rel="noopener noreferrer">
        <?php esc_html_e('Apri wizard', 'opencomune'); ?>
    </a>
</p>
<p class="text-sm text-gray-500 max-w-2xl">
    <?php esc_html_e('Crea in WordPress le pagine con template "Pianifica itinerario" (slug consigliato: pianifica-itinerario) e "Itinerario" (slug: itinerario).', 'opencomune'); ?>
</p>

<h2 class="text-xl font-bold mt-8 mb-4"><?php esc_html_e('Ultime richieste', 'opencomune'); ?></h2>
<?php if ($data['requests'] === []) : ?>
    <p class="text-gray-600"><?php esc_html_e('Nessuna richiesta ancora.', 'opencomune'); ?></p>
<?php else : ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-4 py-3 font-semibold"><?php esc_html_e('Data', 'opencomune'); ?></th>
                    <th class="px-4 py-3 font-semibold"><?php esc_html_e('Viaggio', 'opencomune'); ?></th>
                    <th class="px-4 py-3 font-semibold"><?php esc_html_e('Partenza', 'opencomune'); ?></th>
                    <th class="px-4 py-3 font-semibold"><?php esc_html_e('Contatto', 'opencomune'); ?></th>
                    <th class="px-4 py-3 font-semibold"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['requests'] as $row) : ?>
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 whitespace-nowrap"><?php echo esc_html($row['created_at']); ?></td>
                        <td class="px-4 py-3"><?php echo esc_html($row['start_date'] . ' → ' . $row['end_date']); ?></td>
                        <td class="px-4 py-3"><?php echo esc_html($row['start_address']); ?></td>
                        <td class="px-4 py-3">
                            <?php
                            if ($row['contact_email']) {
                                echo esc_html($row['contact_name'] . ' – ' . $row['contact_email']);
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td class="px-4 py-3">
                            <a class="text-blue-700 hover:text-blue-900 font-medium" href="<?php echo esc_url(add_query_arg('token', $row['token'], home_url('/itinerario/'))); ?>" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('Vedi', 'opencomune'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
