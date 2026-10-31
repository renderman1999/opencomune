<?php
/**
 * Link esterno per prenotazione o informazioni (Punto di Incontro).
 *
 * @var array $args
 */

$tour_destination = $args['tour_destination'] ?? '';
?>

<div class="mt-6">
    <label for="tour_destination" class="form-label">
        Link esterno
    </label>
    <input type="url"
           id="tour_destination"
           name="tour_destination"
           class="form-input"
           placeholder="https://esempio.com/prenota"
           value="<?php echo esc_attr($tour_destination); ?>">
    <p class="text-sm text-gray-500 mt-1">
        Utile se il form di prenotazione sulla piattaforma è disattivato: indica il link per prenotare o maggiori informazioni (sito partner, pagina evento, mappa, ecc.).
    </p>
</div>
