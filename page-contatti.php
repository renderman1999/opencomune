<?php
/**
 * Pagina Contatti (slug contatti).
 */

get_header();

$ufficio = function_exists('opencomune_get_ufficio_info') ? opencomune_get_ufficio_info() : [];
$has_info = !empty($ufficio['indirizzo']) || !empty($ufficio['telefono']) || !empty($ufficio['email']) || !empty($ufficio['orari']);
?>

<div class="oc-public-contatti">
    <div class="oc-public-contatti__inner">
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <header class="oc-public-contatti__header">
                    <p class="oc-public-contatti__payoff">
                        <?php esc_html_e('Piattaforma per', 'opencomune'); ?><br>
                        <?php esc_html_e('il Marketing territoriale', 'opencomune'); ?>
                    </p>
                    <h1 class="oc-public-contatti__title"><?php the_title(); ?></h1>
                </header>

                <div class="oc-public-contatti__layout">
                    <?php if ($has_info) : ?>
                        <aside class="oc-public-contatti__info" aria-labelledby="oc-contatti-info-title">
                            <h2 id="oc-contatti-info-title" class="oc-public-contatti__info-title">
                                <?php esc_html_e('Ufficio', 'opencomune'); ?>
                            </h2>
                            <dl class="oc-public-contatti__info-list">
                                <?php if (!empty($ufficio['indirizzo'])) : ?>
                                    <div class="oc-public-contatti__info-item">
                                        <dt><?php esc_html_e('Indirizzo', 'opencomune'); ?></dt>
                                        <dd><?php echo nl2br(esc_html($ufficio['indirizzo'])); ?></dd>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($ufficio['telefono'])) : ?>
                                    <div class="oc-public-contatti__info-item">
                                        <dt><?php esc_html_e('Telefono', 'opencomune'); ?></dt>
                                        <dd>
                                            <a href="<?php echo esc_url('tel:' . preg_replace('/\s+/', '', $ufficio['telefono'])); ?>">
                                                <?php echo esc_html($ufficio['telefono']); ?>
                                            </a>
                                        </dd>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($ufficio['email'])) : ?>
                                    <div class="oc-public-contatti__info-item">
                                        <dt><?php esc_html_e('Email', 'opencomune'); ?></dt>
                                        <dd>
                                            <a href="<?php echo esc_url('mailto:' . $ufficio['email']); ?>">
                                                <?php echo esc_html($ufficio['email']); ?>
                                            </a>
                                        </dd>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($ufficio['orari'])) : ?>
                                    <div class="oc-public-contatti__info-item">
                                        <dt><?php esc_html_e('Orari', 'opencomune'); ?></dt>
                                        <dd><?php echo nl2br(esc_html($ufficio['orari'])); ?></dd>
                                    </div>
                                <?php endif; ?>
                            </dl>
                        </aside>
                    <?php endif; ?>

                    <div class="oc-public-contatti__form-wrap">
                        <div class="oc-public-contatti__prose">
                            <?php the_content(); ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
