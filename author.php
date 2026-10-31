<?php
get_header();

$author = get_queried_object();
$author_id = ($author instanceof WP_User) ? (int) $author->ID : 0;

if ($author_id <= 0) {
    echo '<main class="max-w-7xl mx-auto p-4"><p class="text-gray-600">' . esc_html__('Autore non trovato.', 'opencomune') . '</p></main>';
    get_footer();
    return;
}

$author_user = get_userdata($author_id);
$is_partner = $author_user && in_array('partner', (array) $author_user->roles, true);
$is_ente_author = $author_user && opencomune_user_is_ente_author($author_id);
$partner = $is_partner ? opencomune_get_partner_by_user_id($author_id) : null;

$display_name = $partner && !empty($partner->nome_attivita)
    ? $partner->nome_attivita
    : ($author_user ? $author_user->display_name : '');
$avatar_url = opencomune_get_partner_avatar_url($partner, $author_id);
$descrizione_breve = $partner && !empty($partner->descrizione_breve)
    ? opencomune_get_partner_public_description($partner->descrizione_breve)
    : opencomune_get_partner_public_description(get_user_meta($author_id, 'user_bio', true));
$descrizione_estesa = $partner && !empty($partner->descrizione_estesa) ? $partner->descrizione_estesa : '';
$tipologia = $partner && !empty($partner->tipologia_attivita) ? $partner->tipologia_attivita : '';
$comune = $partner && !empty($partner->comune) ? $partner->comune : '';
$sito_web = $partner && !empty($partner->sito_web) ? $partner->sito_web : ($author_user ? $author_user->user_url : '');
$telefono = $partner && !empty($partner->telefono_attivita) ? $partner->telefono_attivita : get_user_meta($author_id, 'user_phone', true);
$email = $partner && !empty($partner->email_ufficiale) ? $partner->email_ufficiale : ($author_user ? $author_user->user_email : '');
$indirizzo_parts = [];
if ($partner && !empty($partner->indirizzo)) {
    $indirizzo_parts[] = $partner->indirizzo;
}
if ($partner && !empty($partner->cap)) {
    $indirizzo_parts[] = $partner->cap;
}
if ($comune) {
    $indirizzo_parts[] = $comune;
}
$indirizzo = implode(', ', $indirizzo_parts);
$esperienze_count = (int) count_user_posts($author_id, 'esperienze', true);
$profile_label = $is_partner
    ? __('Partner certificato', 'opencomune')
    : ($is_ente_author ? __('Ufficio turistico', 'opencomune') : __('Organizzatore', 'opencomune'));
$partner_share_urls = opencomune_get_partner_share_urls($author_id, $display_name);
$esperienze_title = $is_partner
    ? __('Esperienze del partner', 'opencomune')
    : __('Esperienze pubblicate', 'opencomune');
?>

<style>
.author-page-layout {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}
@media (min-width: 768px) {
    .author-page-layout {
        flex-direction: row;
        align-items: flex-start;
    }
}
.author-page-main {
    flex: 1;
    min-width: 0;
}
.author-page-sidebar {
    width: 100%;
    flex-shrink: 0;
}
@media (min-width: 768px) {
    .author-page-sidebar {
        width: 24rem;
        position: sticky;
        top: 2rem;
    }
}
.author-esperienze-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    align-items: stretch;
}
.author-esperienze-grid .oc-home-tour-card {
    height: 100%;
}
@media (min-width: 640px) {
    .author-esperienze-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 1024px) {
    .author-esperienze-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 1280px) {
    .author-esperienze-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
.author-partner-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #166534;
}
.author-meta-badge {
    display: inline-block;
    background: #dbeafe;
    color: #1e40af;
    border-radius: 9999px;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.author-empty-notice {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 0.5rem;
    border: 1px solid #bfdbfe;
    background-color: #eff6ff;
    color: #1e40af;
    font-size: 0.9375rem;
    line-height: 1.5;
}
.author-sidebar-logo {
    width: 5rem;
    height: 5rem;
    margin: 0 auto 1rem;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.author-sidebar-logo img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    padding: 0.375rem;
}
</style>

<main class="max-w-7xl mx-auto p-4">
    <div class="author-page-layout">
        <div class="author-page-main">
            <h1 class="text-2xl md:text-3xl font-bold mb-4 text-gray-900"><?php echo esc_html($esperienze_title); ?></h1>

            <?php if (have_posts()) : ?>
                <div class="author-esperienze-grid">
                    <?php
                    while (have_posts()) :
                        the_post();
                        get_template_part('template-parts/home', 'esperienza-card', ['post_id' => get_the_ID()]);
                    endwhile;
                    ?>
                </div>

                <div class="mt-10">
                    <?php opencomune_render_pagination(); ?>
                </div>
            <?php else : ?>
                <div class="author-empty-notice" role="alert">
                    <i class="bi bi-info-circle"></i>
                    <span><?php esc_html_e('Nessuna esperienza pubblicata al momento.', 'opencomune'); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <aside class="author-page-sidebar bg-white rounded-xl shadow p-6 h-fit">
            <div class="author-sidebar-logo">
                <?php if ($avatar_url) : ?>
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($display_name); ?>">
                <?php else : ?>
                    <span class="text-3xl text-gray-400"><i class="bi bi-building"></i></span>
                <?php endif; ?>
            </div>

            <div class="text-center mb-4">
                <span class="author-partner-badge mb-2">
                    <i class="bi bi-patch-check"></i>
                    <?php echo esc_html($profile_label); ?>
                </span>
                <h2 class="text-xl font-bold text-gray-900 mt-2"><?php echo esc_html($display_name); ?></h2>
            </div>

            <?php if ($descrizione_breve) : ?>
                <p class="text-sm text-gray-700 mb-4 text-center"><?php echo esc_html($descrizione_breve); ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap items-center justify-center gap-2 mb-4">
                <?php if ($tipologia) : ?>
                    <span class="author-meta-badge"><?php echo esc_html($tipologia); ?></span>
                <?php endif; ?>
                <?php if ($comune) : ?>
                    <span class="author-meta-badge"><i class="bi bi-geo-alt-fill mr-1"></i><?php echo esc_html($comune); ?></span>
                <?php endif; ?>
                <?php if ($esperienze_count > 0) : ?>
                    <span class="author-meta-badge"><i class="bi bi-compass mr-1"></i><?php echo esc_html($esperienze_count); ?> <?php echo $esperienze_count === 1 ? esc_html__('esperienza', 'opencomune') : esc_html__('esperienze', 'opencomune'); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($descrizione_estesa) : ?>
                <div class="prose max-w-none text-gray-700 text-sm mb-5"><?php echo wpautop(esc_html($descrizione_estesa)); ?></div>
            <?php endif; ?>

            <div class="border-t border-gray-200 pt-4 mb-5">
                <h3 class="font-bold text-lg mb-3"><?php esc_html_e('Informazioni', 'opencomune'); ?></h3>
                <div class="space-y-3 text-sm text-gray-700">
                    <?php if ($indirizzo) : ?>
                        <div><strong><?php esc_html_e('Indirizzo', 'opencomune'); ?>:</strong><br><?php echo esc_html($indirizzo); ?></div>
                    <?php endif; ?>
                    <?php if ($telefono) : ?>
                        <div><strong><?php esc_html_e('Telefono', 'opencomune'); ?>:</strong><br><a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $telefono)); ?>" class="text-blue-700 hover:text-blue-900"><?php echo esc_html($telefono); ?></a></div>
                    <?php endif; ?>
                    <?php if ($email) : ?>
                        <div><strong><?php esc_html_e('Email', 'opencomune'); ?>:</strong><br><a href="mailto:<?php echo esc_attr($email); ?>" class="text-blue-700 hover:text-blue-900 break-all"><?php echo esc_html($email); ?></a></div>
                    <?php endif; ?>
                    <?php if ($sito_web) : ?>
                        <div><strong><?php esc_html_e('Sito web', 'opencomune'); ?>:</strong><br><a href="<?php echo esc_url($sito_web); ?>" target="_blank" rel="noopener noreferrer" class="text-blue-700 hover:text-blue-900 break-all"><?php echo esc_html($sito_web); ?></a></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200">
                <h3 class="font-bold text-lg mb-3"><?php esc_html_e('Condividi', 'opencomune'); ?></h3>
                <?php
                get_template_part('template-parts/share', 'buttons', [
                    'urls' => $partner_share_urls,
                    'group_label' => __('Condividi questo partner', 'opencomune'),
                ]);
                ?>
            </div>
        </aside>
    </div>
</main>

<?php get_footer(); ?>
