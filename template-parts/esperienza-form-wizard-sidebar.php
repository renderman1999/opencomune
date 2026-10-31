<?php
/**
 * Sidebar step wizard (riferimento layout tipo Bókun).
 *
 * @var array $args
 */

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$title = isset($args['title']) ? (string) $args['title'] : '';
$preview_url = isset($args['preview_url']) ? (string) $args['preview_url'] : '';
$experiences_list_url = function_exists('opencomune_get_esperienze_dashboard_url')
    ? opencomune_get_esperienze_dashboard_url()
    : home_url('/esperienze-partner/');
$groups = opencomune_get_esperienza_form_step_groups();
$has_saved = $post_id > 0;
?>

<aside class="oc-exp-wizard__sidebar" id="ocExpWizardSidebar" aria-label="<?php esc_attr_e('Passaggi esperienza', 'opencomune'); ?>">
    <div class="oc-exp-wizard__sidebar-top">
        <a href="<?php echo esc_url($experiences_list_url); ?>" class="oc-exp-wizard__back btn-primary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <?php esc_html_e('Torna alle esperienze', 'opencomune'); ?>
        </a>

        <?php if ($has_saved) : ?>
            <p class="oc-exp-wizard__meta-id">
                <?php
                printf(
                    esc_html__('ID esperienza: %d', 'opencomune'),
                    $post_id
                );
                ?>
            </p>
        <?php else : ?>
            <p class="oc-exp-wizard__meta-id"><?php esc_html_e('Nuova bozza', 'opencomune'); ?></p>
        <?php endif; ?>

        <h1 class="oc-exp-wizard__title" id="ocExpWizardTitleLive">
            <?php echo esc_html($title !== '' ? $title : __('Nuova esperienza', 'opencomune')); ?>
        </h1>

        <?php if ($preview_url !== '') : ?>
            <a href="<?php echo esc_url($preview_url); ?>" class="oc-exp-wizard__preview" target="_blank" rel="noopener noreferrer">
                <i class="bi bi-eye" aria-hidden="true"></i>
                <?php esc_html_e('Anteprima', 'opencomune'); ?>
            </a>
        <?php else : ?>
            <button type="button" class="oc-exp-wizard__preview is-disabled" disabled title="<?php esc_attr_e('Disponibile dopo il primo salvataggio', 'opencomune'); ?>">
                <i class="bi bi-eye" aria-hidden="true"></i>
                <?php esc_html_e('Anteprima', 'opencomune'); ?>
            </button>
        <?php endif; ?>

        <label class="oc-exp-wizard__search-wrap">
            <span class="screen-reader-text"><?php esc_html_e('Cerca step', 'opencomune'); ?></span>
            <input type="search" id="ocExpWizardSearch" class="oc-exp-wizard__search" placeholder="<?php esc_attr_e('Cerca step…', 'opencomune'); ?>" autocomplete="off">
            <kbd class="oc-exp-wizard__search-kbd" aria-hidden="true">Ctrl K</kbd>
        </label>

        <label class="oc-exp-wizard__toggle-opt">
            <input type="checkbox" id="ocExpWizardShowAdvanced" checked>
            <span><?php esc_html_e('Mostra opzioni aggiuntive', 'opencomune'); ?></span>
        </label>
    </div>

    <nav class="oc-exp-wizard__nav" id="ocExpWizardNav">
        <?php foreach ($groups as $group) : ?>
            <?php
            if (!empty($group['requires_saved']) && !$has_saved) {
                continue;
            }
            $group_optional = !empty($group['optional_group']);
            $group_open = !empty($group['default_open']);
            ?>
            <div class="oc-exp-wizard__group<?php echo $group_optional ? ' is-optional' : ''; ?><?php echo $group_open ? ' is-open' : ''; ?>"
                 data-exp-group="<?php echo esc_attr($group['id']); ?>">
                <button type="button" class="oc-exp-wizard__group-toggle" aria-expanded="<?php echo $group_open ? 'true' : 'false'; ?>">
                    <span class="oc-exp-wizard__group-label"><?php echo esc_html($group['label']); ?></span>
                    <span class="oc-exp-wizard__group-alert is-hidden" data-group-alert="<?php echo esc_attr($group['id']); ?>" title="<?php esc_attr_e('Step incompleti', 'opencomune'); ?>">
                        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    </span>
                    <i class="bi bi-chevron-down oc-exp-wizard__group-chevron" aria-hidden="true"></i>
                </button>
                <ul class="oc-exp-wizard__steps">
                    <?php foreach ($group['steps'] as $step) : ?>
                        <?php
                        $step_id = $step['id'];
                        $requires_saved = !empty($step['requires_saved']);
                        $step_disabled = $requires_saved && !$has_saved;
                        ?>
                        <li>
                            <button type="button"
                                    class="oc-exp-wizard__step"
                                    data-exp-tab="<?php echo esc_attr($step_id); ?>"
                                    data-requires-saved="<?php echo $requires_saved ? '1' : '0'; ?>"
                                    <?php echo $step_disabled ? ' disabled' : ''; ?>>
                                <span class="oc-exp-wizard__step-status" data-step-status="<?php echo esc_attr($step_id); ?>" aria-hidden="true"></span>
                                <span class="oc-exp-wizard__step-label"><?php echo esc_html($step['label']); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>
</aside>
