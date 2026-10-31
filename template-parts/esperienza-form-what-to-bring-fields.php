<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $what_to_bring = isset($_POST['tour_what_to_bring'])
        ? wp_kses_post(wp_unslash($_POST['tour_what_to_bring']))
        : '';
} else {
    $what_to_bring = $post_id > 0 ? opencomune_get_esperienza_what_to_bring($post_id) : '';
}
?>

<div class="oc-exp-inclusion oc-exp-what-to-bring">
    <h2 class="oc-exp-inclusion__title">
        <?php esc_html_e('Cosa dovrebbero portare i viaggiatori?', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-inclusion__hint oc-exp-what-to-bring__lead">
        <?php esc_html_e('Informa i viaggiatori su ciò che è utile avere con sé: ad esempio abiti caldi, costume da bagno o crema solare.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-inclusion__field">
        <?php
        wp_editor(
            $what_to_bring,
            'tour_what_to_bring',
            [
                'textarea_name' => 'tour_what_to_bring',
                'media_buttons' => true,
                'teeny' => false,
                'textarea_rows' => 10,
                'quicktags' => true,
                'editor_class' => 'oc-exp-inclusion__editor',
            ]
        );
        ?>
    </div>
</div>

<style>
.oc-exp-what-to-bring__lead {
    margin-bottom: 1.25rem;
}
</style>
