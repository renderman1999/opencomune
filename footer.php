    <?php
    $is_partner_dashboard = opencomune_is_partner_dashboard_page();
    $hide_site_footer = function_exists('opencomune_planner_use_flow_chrome') && opencomune_planner_use_flow_chrome();
    $footer_class = $is_partner_dashboard
        ? 'oc-partner-footer mt-auto w-full text-white'
        : 'oc-public-footer site-footer mt-auto w-full';
    ?>
    <?php if (!$hide_site_footer) : ?>
    <footer class="<?php echo esc_attr($footer_class); ?>"<?php echo $is_partner_dashboard ? ' style="background-color: #004d99;"' : ''; ?>>
        <?php if (!$is_partner_dashboard) : ?>
            <?php
            $indirizzo = trim((string) get_option('opencomune_indirizzo_ufficio', ''));
            $telefono = trim((string) get_option('opencomune_telefono_ufficio', ''));
            $email = trim((string) get_option('opencomune_email_ufficio', ''));
            $orari = trim((string) get_option('opencomune_orari_apertura', ''));
            $has_contact = $indirizzo !== '' || $telefono !== '' || $email !== '' || $orari !== '';
            ?>
            <div class="oc-public-footer__main">
                <div class="oc-public-footer__inner">
                    <div class="oc-public-footer__intro">
                        <div class="oc-public-footer__brand">
                            <a href="<?php echo esc_url(home_url('/')); ?>" class="oc-public-footer__logo" rel="home">
                                <?php
                                $logo_id = (int) get_theme_mod('custom_logo');
                                if ($logo_id > 0) {
                                    echo wp_get_attachment_image(
                                        $logo_id,
                                        'medium',
                                        false,
                                        [
                                            'class' => 'oc-public-footer__logo-image',
                                            'alt' => get_bloginfo('name'),
                                        ]
                                    );
                                } else {
                                    echo '<span class="oc-public-footer__logo-text">' . esc_html(get_bloginfo('name')) . '</span>';
                                }
                                ?>
                            </a>
                            <p class="oc-public-footer__payoff">
                                <?php esc_html_e('Piattaforma per', 'opencomune'); ?><br>
                                <?php esc_html_e('il Marketing territoriale', 'opencomune'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="oc-public-footer__grid">
                        <section class="oc-public-footer__col" aria-labelledby="oc-footer-contacts-heading">
                            <h2 id="oc-footer-contacts-heading" class="oc-public-footer__heading">
                                <?php esc_html_e('Contatti', 'opencomune'); ?>
                            </h2>
                            <?php if ($has_contact) : ?>
                                <dl class="oc-public-footer__contact-list">
                                    <?php if ($indirizzo !== '') : ?>
                                        <div class="oc-public-footer__contact-item">
                                            <dt><?php esc_html_e('Indirizzo', 'opencomune'); ?></dt>
                                            <dd><?php echo nl2br(esc_html($indirizzo)); ?></dd>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($telefono !== '') : ?>
                                        <div class="oc-public-footer__contact-item">
                                            <dt><?php esc_html_e('Telefono', 'opencomune'); ?></dt>
                                            <dd>
                                                <a href="<?php echo esc_url('tel:' . preg_replace('/\s+/', '', $telefono)); ?>">
                                                    <?php echo esc_html($telefono); ?>
                                                </a>
                                            </dd>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($email !== '') : ?>
                                        <div class="oc-public-footer__contact-item">
                                            <dt><?php esc_html_e('Email', 'opencomune'); ?></dt>
                                            <dd>
                                                <a href="<?php echo esc_url('mailto:' . $email); ?>">
                                                    <?php echo esc_html($email); ?>
                                                </a>
                                            </dd>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($orari !== '') : ?>
                                        <div class="oc-public-footer__contact-item">
                                            <dt><?php esc_html_e('Orari', 'opencomune'); ?></dt>
                                            <dd><?php echo nl2br(esc_html($orari)); ?></dd>
                                        </div>
                                    <?php endif; ?>
                                </dl>
                            <?php else : ?>
                                <p class="oc-public-footer__empty">
                                    <?php esc_html_e('Per informazioni sull’ufficio e sul territorio, visita la pagina contatti.', 'opencomune'); ?>
                                </p>
                                <a class="oc-public-footer__empty-link" href="<?php echo esc_url(home_url('/contatti/')); ?>">
                                    <?php esc_html_e('Vai ai contatti', 'opencomune'); ?>
                                </a>
                            <?php endif; ?>
                        </section>

                        <section class="oc-public-footer__col" aria-labelledby="oc-footer-links-heading">
                            <h2 id="oc-footer-links-heading" class="oc-public-footer__heading">
                                <?php esc_html_e('Link utili', 'opencomune'); ?>
                            </h2>
                            <?php opencomune_render_footer_nav_menu(); ?>
                        </section>

                        <section class="oc-public-footer__col" aria-labelledby="oc-footer-servizi-heading">
                            <h2 id="oc-footer-servizi-heading" class="oc-public-footer__heading">
                                <?php esc_html_e('Servizi', 'opencomune'); ?>
                            </h2>
                            <?php opencomune_render_footer_servizi_nav_menu(); ?>
                        </section>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="<?php echo $is_partner_dashboard ? 'oc-partner-footer__bar' : 'oc-public-footer__bar'; ?>"<?php echo $is_partner_dashboard ? ' style="background-color: #003366;"' : ''; ?>>
            <div class="oc-public-footer__bar-inner">
                <p class="oc-public-footer__copyright">
                    &copy; <?php echo esc_html((string) date('Y')); ?>
                    <?php echo esc_html(get_bloginfo('name')); ?>.
                    <?php esc_html_e('Tutti i diritti riservati.', 'opencomune'); ?>
                </p>
                <p class="oc-public-footer__credit">
                    <?php esc_html_e('Powered by', 'opencomune'); ?>
                    <a href="https://wewelcome.it/" target="_blank" rel="noopener noreferrer">Wewelcome.it</a>
                </p>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    <?php wp_footer(); ?>
</body>
</html>
