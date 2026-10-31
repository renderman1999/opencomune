<?php
/**
 * Selezione categorie esperienza con possibilità di crearne di nuove.
 *
 * @var array $args
 */

$selected_slugs = isset($args['selected_slugs']) ? (array) $args['selected_slugs'] : [];
$new_categories = isset($args['new_categories']) ? (array) $args['new_categories'] : [];
$categorie = isset($args['categorie']) ? $args['categorie'] : get_terms([
    'taxonomy' => 'categorie_esperienze',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC',
]);

if (is_wp_error($categorie)) {
    $categorie = [];
}
?>

<label class="form-label">
    Categorie <span class="text-gray-500 font-normal">(facoltativo)</span>
</label>

<?php if (!empty($categorie)) : ?>
<div class="grid grid-cols-2 gap-3 mt-2">
    <?php foreach ($categorie as $categoria) : ?>
        <label class="flex items-center space-x-2 cursor-pointer">
            <input type="checkbox"
                   name="tour_categories[]"
                   value="<?php echo esc_attr($categoria->slug); ?>"
                   class="form-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                   <?php checked(in_array($categoria->slug, $selected_slugs, true)); ?>>
            <span class="text-sm text-gray-700"><?php echo esc_html($categoria->name); ?></span>
        </label>
    <?php endforeach; ?>
</div>
<?php else : ?>
<p class="text-sm text-gray-500 mt-2">Nessuna categoria esistente. Puoi crearne una qui sotto.</p>
<?php endif; ?>

<div class="mt-4">
    <label for="new-category-input" class="form-label">Crea nuova categoria</label>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <input type="text" id="new-category-input" class="form-input" style="flex: 1; min-width: 200px;"
               placeholder="Es. Enogastronomia, Trekking...">
        <button type="button" id="add-new-category-btn" class="btn-secondary" style="white-space: nowrap;">
            Aggiungi
        </button>
    </div>
    <div id="new-categories-tags" style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem;">
        <?php foreach ($new_categories as $new_category) :
            $new_category = sanitize_text_field($new_category);
            if ($new_category === '') {
                continue;
            }
        ?>
            <span class="new-category-tag" style="display: inline-flex; align-items: center; gap: 0.35rem; background: #eff6ff; color: #1d4ed8; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.875rem;">
                <?php echo esc_html($new_category); ?>
                <button type="button" class="remove-new-category" style="border: none; background: transparent; color: #1d4ed8; cursor: pointer; font-weight: bold;" aria-label="Rimuovi">&times;</button>
                <input type="hidden" name="tour_new_categories[]" value="<?php echo esc_attr($new_category); ?>">
            </span>
        <?php endforeach; ?>
    </div>
    <p class="text-sm text-gray-500 mt-2">Se non trovi la categoria giusta, aggiungine una nuova: verrà creata al salvataggio.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('new-category-input');
    const addBtn = document.getElementById('add-new-category-btn');
    const tagsContainer = document.getElementById('new-categories-tags');

    if (!input || !addBtn || !tagsContainer) {
        return;
    }

    function getExistingNames() {
        const names = [];
        tagsContainer.querySelectorAll('input[name="tour_new_categories[]"]').forEach(function(hidden) {
            names.push(hidden.value.trim().toLowerCase());
        });
        return names;
    }

    function addCategoryTag(name) {
        const trimmed = name.trim();
        if (!trimmed) {
            return;
        }

        const lower = trimmed.toLowerCase();
        const existingNames = getExistingNames();
        if (existingNames.includes(lower)) {
            return;
        }

        const tag = document.createElement('span');
        tag.className = 'new-category-tag';
        tag.style.cssText = 'display:inline-flex;align-items:center;gap:0.35rem;background:#eff6ff;color:#1d4ed8;padding:0.25rem 0.6rem;border-radius:9999px;font-size:0.875rem;';
        tag.innerHTML = '<span class="new-category-label"></span>' +
            '<button type="button" class="remove-new-category" style="border:none;background:transparent;color:#1d4ed8;cursor:pointer;font-weight:bold;" aria-label="Rimuovi">&times;</button>';

        tag.querySelector('.new-category-label').textContent = trimmed;

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'tour_new_categories[]';
        hidden.value = trimmed;
        tag.appendChild(hidden);

        tag.querySelector('.remove-new-category').addEventListener('click', function() {
            tag.remove();
        });

        tagsContainer.appendChild(tag);
    }

    addBtn.addEventListener('click', function() {
        addCategoryTag(input.value);
        input.value = '';
        input.focus();
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addCategoryTag(input.value);
            input.value = '';
        }
    });

    tagsContainer.querySelectorAll('.remove-new-category').forEach(function(btn) {
        btn.addEventListener('click', function() {
            btn.closest('.new-category-tag').remove();
        });
    });
});
</script>
