<?php
/**
 * Riepilogo esperienza (wizard edit).
 *
 * @var array $args
 */

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
if ($post_id <= 0 || !function_exists('opencomune_get_esperienza_form_summary_data')) {
    return;
}

$summary = opencomune_get_esperienza_form_summary_data($post_id);
if ($summary === []) {
    return;
}
?>

<div class="oc-exp-summary">
    <header class="oc-exp-summary__hero">
        <h2 class="oc-exp-summary__title"><?php echo esc_html($summary['title']); ?></h2>
        <ul class="oc-exp-summary__badges" role="list">
            <li>
                <span class="oc-exp-summary__badge oc-exp-summary__badge--status oc-exp-summary__badge--<?php echo esc_attr($summary['status']); ?>">
                    <?php echo esc_html($summary['status_label']); ?>
                </span>
            </li>
            <li>
                <span class="oc-exp-summary__badge oc-exp-summary__badge--muted">
                    <?php echo esc_html($summary['experience_type_label']); ?>
                </span>
            </li>
            <li>
                <span class="oc-exp-summary__badge oc-exp-summary__badge--outline">
                    <?php echo esc_html($summary['booking_schedule_label']); ?>
                </span>
            </li>
        </ul>
    </header>

    <?php if (!empty($summary['recommendations'])) : ?>
        <details class="oc-exp-summary__rec" open>
            <summary class="oc-exp-summary__rec-toggle">
                <span><?php esc_html_e('Suggerimenti', 'opencomune'); ?></span>
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </summary>
            <p class="oc-exp-summary__rec-lead">
                <?php esc_html_e('Sono opzionali ma possono migliorare la qualità dell\'esperienza.', 'opencomune'); ?>
            </p>
            <ul class="oc-exp-summary__rec-list" role="list">
                <?php foreach ($summary['recommendations'] as $rec) : ?>
                    <li>
                        <button type="button" class="oc-exp-summary__rec-link" data-exp-summary-step="<?php echo esc_attr($rec['step']); ?>">
                            <span class="oc-exp-summary__rec-dot" aria-hidden="true"></span>
                            <span><?php echo esc_html($rec['label']); ?></span>
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>

    <div class="oc-exp-summary__grid">
        <section class="oc-exp-summary-card">
            <header class="oc-exp-summary-card__head">
                <h3 class="oc-exp-summary-card__title"><?php esc_html_e('Disponibilità', 'opencomune'); ?></h3>
                <button type="button" class="oc-exp-summary__open-step" data-exp-summary-step="booking-schedule">
                    <?php esc_html_e('Apri step', 'opencomune'); ?>
                </button>
            </header>
            <dl class="oc-exp-summary-kv">
                <?php foreach ($summary['availability_rows'] as $row) : ?>
                    <div class="oc-exp-summary-kv__row">
                        <dt><?php echo esc_html($row['label']); ?></dt>
                        <dd><?php echo esc_html($row['value']); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>

        <section class="oc-exp-summary-card">
            <header class="oc-exp-summary-card__head">
                <h3 class="oc-exp-summary-card__title"><?php esc_html_e('Categorie prezzo', 'opencomune'); ?></h3>
                <button type="button" class="oc-exp-summary__open-step" data-exp-summary-step="pricing">
                    <?php esc_html_e('Apri step', 'opencomune'); ?>
                </button>
            </header>
            <p class="oc-exp-summary-card__lead">
                <?php esc_html_e('Le categorie che definiscono i prezzi di questa esperienza.', 'opencomune'); ?>
            </p>
            <?php if (empty($summary['pricing_categories'])) : ?>
                <p class="oc-exp-summary-empty"><?php esc_html_e('Nessuna categoria configurata.', 'opencomune'); ?></p>
            <?php else : ?>
                <table class="oc-exp-summary-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col"><?php esc_html_e('Titolo', 'opencomune'); ?></th>
                            <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Predefinita', 'opencomune'); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['pricing_categories'] as $cat) : ?>
                            <tr>
                                <td><?php echo (int) $cat['public_id']; ?></td>
                                <td><?php echo esc_html($cat['label']); ?></td>
                                <td class="oc-exp-summary-table__tag">
                                    <?php if (!empty($cat['is_default'])) : ?>
                                        <span class="oc-exp-summary__badge oc-exp-summary__badge--default"><?php esc_html_e('Predefinita', 'opencomune'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="oc-exp-summary-card">
            <header class="oc-exp-summary-card__head">
                <h3 class="oc-exp-summary-card__title"><?php esc_html_e('Orari di inizio', 'opencomune'); ?></h3>
                <button type="button" class="oc-exp-summary__open-step" data-exp-summary-step="start-times">
                    <?php esc_html_e('Apri step', 'opencomune'); ?>
                </button>
            </header>
            <p class="oc-exp-summary-card__lead">
                <?php esc_html_e('Orari fissi di partenza: ogni slot ha disponibilità gestita separatamente.', 'opencomune'); ?>
            </p>
            <?php if (empty($summary['start_times'])) : ?>
                <p class="oc-exp-summary-empty"><?php esc_html_e('Nessun orario configurato.', 'opencomune'); ?></p>
            <?php else : ?>
                <table class="oc-exp-summary-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col"><?php esc_html_e('Orario di partenza', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Etichetta esterna', 'opencomune'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['start_times'] as $st) : ?>
                            <tr>
                                <td><?php echo (int) $st['display_id']; ?></td>
                                <td><?php echo esc_html($st['time']); ?></td>
                                <td><?php echo esc_html($st['external_label']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="oc-exp-summary-card">
            <header class="oc-exp-summary-card__head">
                <h3 class="oc-exp-summary-card__title"><?php esc_html_e('Media', 'opencomune'); ?></h3>
                <button type="button" class="oc-exp-summary__open-step" data-exp-summary-step="media">
                    <?php esc_html_e('Modifica foto', 'opencomune'); ?>
                </button>
            </header>
            <?php if (empty($summary['media'])) : ?>
                <p class="oc-exp-summary-empty"><?php esc_html_e('Nessuna immagine caricata.', 'opencomune'); ?></p>
            <?php else : ?>
                <p class="oc-exp-summary-card__sub"><?php esc_html_e('Foto', 'opencomune'); ?></p>
                <ul class="oc-exp-summary-media" role="list">
                    <?php foreach ($summary['media'] as $item) : ?>
                        <li class="oc-exp-summary-media__item">
                            <img src="<?php echo esc_url($item['url']); ?>" alt="" loading="lazy" width="120" height="90">
                            <?php if (!empty($item['is_cover'])) : ?>
                                <span class="oc-exp-summary-media__cover"><?php esc_html_e('COPERTINA', 'opencomune'); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="oc-exp-summary-card">
            <header class="oc-exp-summary-card__head">
                <h3 class="oc-exp-summary-card__title"><?php esc_html_e('Come arrivare?', 'opencomune'); ?></h3>
                <button type="button" class="oc-exp-summary__open-step" data-exp-summary-step="pickup-arrival">
                    <?php esc_html_e('Apri step', 'opencomune'); ?>
                </button>
            </header>
            <p class="oc-exp-summary-card__lead">
                <?php esc_html_e('Servizio selezionato:', 'opencomune'); ?>
                <span class="oc-exp-summary__pill"><?php echo esc_html($summary['pickup_mode_label']); ?></span>
            </p>
            <?php if (empty($summary['meeting_points'])) : ?>
                <p class="oc-exp-summary-empty"><?php esc_html_e('Nessun punto di incontro.', 'opencomune'); ?></p>
            <?php else : ?>
                <table class="oc-exp-summary-table oc-exp-summary-table--stack">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Titolo', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Indirizzo', 'opencomune'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['meeting_points'] as $point) : ?>
                            <tr>
                                <td><?php echo esc_html($point['title']); ?></td>
                                <td><?php echo esc_html($point['address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="oc-exp-summary-card">
            <header class="oc-exp-summary-card__head">
                <h3 class="oc-exp-summary-card__title"><?php esc_html_e('Itinerario', 'opencomune'); ?></h3>
                <button type="button" class="oc-exp-summary__open-step" data-exp-summary-step="itinerary">
                    <?php esc_html_e('Apri step', 'opencomune'); ?>
                </button>
            </header>
            <p class="oc-exp-summary-card__lead">
                <?php esc_html_e('Tappe che descrivono il programma.', 'opencomune'); ?>
            </p>
            <?php if (empty($summary['itinerary_steps'])) : ?>
                <p class="oc-exp-summary-empty"><?php esc_html_e('Nessuna tappa aggiunta.', 'opencomune'); ?></p>
            <?php else : ?>
                <table class="oc-exp-summary-table oc-exp-summary-table--stack">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Titolo', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Indirizzo', 'opencomune'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['itinerary_steps'] as $step) : ?>
                            <tr>
                                <td><?php echo esc_html($step['title']); ?></td>
                                <td><?php echo esc_html($step['address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</div>
