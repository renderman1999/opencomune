<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$italian_only = !empty($args['italian_only']);
$skip_included = !empty($args['skip_included']);
$skip_not_included = !empty($args['skip_not_included']);
$skip_requirements = !empty($args['skip_requirements']);

if ($italian_only) {
    echo '<div class="opencomune-italian-only-fields">';
} else {
    opencomune_render_esperienza_bilingual_section_start('details', 'Dettagli esperienza');
}

opencomune_render_esperienza_bilingual_field([
    'section' => 'details',
    'italian_only' => $italian_only,
    'en_key' => 'highlights',
    'it_name' => 'tour_highlights',
    'it_id' => 'tour_highlights',
    'it_label' => 'Punti Salienti',
    'it_value' => opencomune_get_esperienza_form_it_field_value($post_id, 'tour_highlights'),
    'en_value' => opencomune_get_esperienza_form_en_field_value($post_id, 'highlights'),
    'placeholder_it' => 'Elenca i punti salienti dell\'esperienza...',
]);

opencomune_render_esperienza_bilingual_field([
    'section' => 'details',
    'italian_only' => $italian_only,
    'en_key' => 'itinerary',
    'it_name' => 'tour_itinerary',
    'it_id' => 'tour_itinerary',
    'it_label' => 'Itinerario',
    'it_value' => opencomune_get_esperienza_form_it_field_value($post_id, 'tour_itinerary'),
    'en_value' => opencomune_get_esperienza_form_en_field_value($post_id, 'itinerary'),
    'placeholder_it' => 'Descrivi l\'itinerario dettagliato...',
]);

if (!$skip_included) {
    opencomune_render_esperienza_bilingual_field([
        'section' => 'details',
        'italian_only' => $italian_only,
        'en_key' => 'included',
        'it_name' => 'tour_whats_included',
        'it_id' => 'tour_whats_included',
        'it_label' => 'Cosa Include',
        'it_value' => opencomune_get_esperienza_form_it_field_value($post_id, 'tour_whats_included'),
        'en_value' => opencomune_get_esperienza_form_en_field_value($post_id, 'included'),
        'placeholder_it' => 'Cosa e incluso nel prezzo...',
    ]);
}

if (!$skip_not_included) {
    opencomune_render_esperienza_bilingual_field([
        'section' => 'details',
        'italian_only' => $italian_only,
        'en_key' => 'not_included',
        'it_name' => 'tour_whats_not_included',
        'it_id' => 'tour_whats_not_included',
        'it_label' => 'Cosa Non Include',
        'it_value' => opencomune_get_esperienza_form_it_field_value($post_id, 'tour_whats_not_included'),
        'en_value' => opencomune_get_esperienza_form_en_field_value($post_id, 'not_included'),
        'placeholder_it' => 'Cosa non e incluso...',
    ]);
}

if (!$skip_requirements) {
    opencomune_render_esperienza_bilingual_field([
        'section' => 'details',
        'italian_only' => $italian_only,
        'en_key' => 'requirements',
        'it_name' => 'tour_requirements',
        'it_id' => 'tour_requirements',
        'it_label' => 'Requisiti',
        'it_value' => opencomune_get_esperienza_form_it_field_value($post_id, 'tour_requirements'),
        'en_value' => opencomune_get_esperienza_form_en_field_value($post_id, 'requirements'),
        'placeholder_it' => 'Requisiti per partecipare...',
    ]);
}

if ($italian_only) {
    echo '</div>';
} else {
    opencomune_render_esperienza_bilingual_section_end();
}
