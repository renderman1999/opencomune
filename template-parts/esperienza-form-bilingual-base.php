<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$italian_only = !empty($args['italian_only']);
?>
<?php
if ($italian_only) {
    echo '<div class="opencomune-italian-only-fields oc-exp-base-fields__stack">';
} else {
    opencomune_render_esperienza_bilingual_section_start('base', 'Contenuti base');
}

opencomune_render_esperienza_bilingual_field([
    'section' => 'base',
    'italian_only' => $italian_only,
    'en_key' => 'title',
    'it_name' => 'tour_title',
    'it_id' => 'tour_title',
    'it_label' => 'Titolo Esperienza',
    'it_value' => opencomune_get_esperienza_form_it_field_value($post_id, 'tour_title'),
    'en_value' => opencomune_get_esperienza_form_en_field_value($post_id, 'title'),
    'it_required' => true,
    'type' => 'text',
    'class' => 'oc-exp-base-fields__field',
]);

opencomune_render_esperienza_bilingual_field([
    'section' => 'base',
    'italian_only' => $italian_only,
    'en_key' => 'descrizione_breve',
    'it_name' => 'tour_excerpt',
    'it_id' => 'tour_excerpt',
    'it_label' => 'Descrizione Breve',
    'it_value' => opencomune_get_esperienza_form_it_field_value($post_id, 'tour_excerpt'),
    'en_value' => opencomune_get_esperienza_form_en_field_value($post_id, 'descrizione_breve'),
    'rows' => 3,
    'help' => 'Questa descrizione apparira nelle anteprime e nei risultati di ricerca',
    'class' => 'oc-exp-base-fields__field',
]);

opencomune_render_esperienza_bilingual_field([
    'section' => 'base',
    'italian_only' => $italian_only,
    'en_key' => 'content',
    'it_name' => 'tour_description',
    'it_id' => 'tour_description',
    'it_label' => 'Descrizione Completa',
    'it_value' => opencomune_get_esperienza_form_it_field_value($post_id, 'tour_description'),
    'en_value' => opencomune_get_esperienza_form_en_field_value($post_id, 'content'),
    'it_required' => true,
    'type' => 'html',
    'rows' => 20,
    'editor_height' => 480,
    'class' => 'oc-exp-base-fields__field',
]);

if ($italian_only) {
    echo '</div>';
} else {
    opencomune_render_esperienza_bilingual_section_end();
}
