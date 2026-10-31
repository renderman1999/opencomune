<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$italian_only = !empty($args['italian_only']);

if ($italian_only) {
    echo '<div class="opencomune-italian-only-fields">';
} else {
    opencomune_render_esperienza_bilingual_section_start('cancellation', 'Politica di cancellazione');
}

opencomune_render_esperienza_bilingual_field([
    'section' => 'cancellation',
    'italian_only' => $italian_only,
    'en_key' => 'cancellation_policy',
    'it_name' => 'tour_cancellation_policy',
    'it_id' => 'tour_cancellation_policy',
    'it_label' => 'Politica di Cancellazione',
    'it_value' => opencomune_get_esperienza_form_it_field_value($post_id, 'tour_cancellation_policy'),
    'en_value' => opencomune_get_esperienza_form_en_field_value($post_id, 'cancellation_policy'),
    'placeholder_it' => 'Descrivi la politica di cancellazione...',
]);

if ($italian_only) {
    echo '</div>';
} else {
    opencomune_render_esperienza_bilingual_section_end();
}
