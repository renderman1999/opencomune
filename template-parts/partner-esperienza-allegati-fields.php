<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$existing_allegati = $post_id ? opencomune_get_esperienza_allegati_items($post_id) : [];
$existing_ids = array_map(static function ($item) {
    return (int) $item['id'];
}, $existing_allegati);
?>

<div class="form-section p-6 mt-8">
    <h2 class="section-title">
        <i class="bi bi-paperclip mr-2"></i>
        Allegati
    </h2>

    <p class="text-sm text-gray-500 mb-4">
        Carica documenti o file utili per i visitatori (PDF, immagini, brochure, ecc.).
    </p>

    <button type="button" id="upload-allegati" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        <i class="bi bi-upload mr-1"></i>
        Aggiungi allegati
    </button>

    <div id="allegati-preview" class="mt-4 space-y-2<?php echo empty($existing_allegati) ? ' hidden' : ''; ?>">
        <?php foreach ($existing_allegati as $allegato): ?>
            <div class="allegato-item" data-id="<?php echo esc_attr($allegato['id']); ?>">
                <div class="allegato-item-info">
                    <i class="bi bi-file-earmark-text"></i>
                    <span class="allegato-item-title"><?php echo esc_html($allegato['title']); ?></span>
                </div>
                <button type="button" class="remove-allegato" aria-label="<?php esc_attr_e('Rimuovi allegato', 'opencomune'); ?>">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>

    <input type="hidden" id="allegati-ids" name="allegati_ids" value="<?php echo esc_attr(implode(',', $existing_ids)); ?>">
</div>

<style>
.allegato-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    background: #f9fafb;
}

.allegato-item-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.allegato-item-title {
    font-size: 0.875rem;
    color: #374151;
    word-break: break-word;
}

.remove-allegato {
    border: 0;
    background: transparent;
    color: #dc2626;
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
    padding: 0 0.25rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadBtn = document.getElementById('upload-allegati');
    const preview = document.getElementById('allegati-preview');
    const idsInput = document.getElementById('allegati-ids');
    if (!uploadBtn || !preview || !idsInput || typeof wp === 'undefined' || !wp.media) {
        return;
    }

    let allegatiFrame;

    function getAllegatiIds() {
        return idsInput.value ? idsInput.value.split(',').filter(Boolean) : [];
    }

    function setAllegatiIds(ids) {
        idsInput.value = [...new Set(ids.map(String))].join(',');
        preview.classList.toggle('hidden', ids.length === 0);
    }

    function addAllegatoItem(id, title) {
        const idStr = String(id);
        if (preview.querySelector('.allegato-item[data-id="' + idStr + '"]')) {
            return;
        }

        const item = document.createElement('div');
        item.className = 'allegato-item';
        item.dataset.id = idStr;
        item.innerHTML =
            '<div class="allegato-item-info">' +
                '<i class="bi bi-file-earmark-text"></i>' +
                '<span class="allegato-item-title"></span>' +
            '</div>' +
            '<button type="button" class="remove-allegato" aria-label="Rimuovi allegato">&times;</button>';
        item.querySelector('.allegato-item-title').textContent = title;
        preview.appendChild(item);
    }

    uploadBtn.addEventListener('click', function(e) {
        e.preventDefault();

        if (allegatiFrame) {
            allegatiFrame.open();
            return;
        }

        allegatiFrame = wp.media({
            title: 'Seleziona allegati',
            button: {
                text: 'Aggiungi allegati'
            },
            multiple: true
        });

        allegatiFrame.on('select', function() {
            const attachments = allegatiFrame.state().get('selection').toJSON();
            const currentIds = getAllegatiIds();

            attachments.forEach(function(attachment) {
                const title = attachment.title || attachment.filename || attachment.name || 'Allegato';
                currentIds.push(String(attachment.id));
                addAllegatoItem(attachment.id, title);
            });

            setAllegatiIds(currentIds);
        });

        allegatiFrame.open();
    });

    preview.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.remove-allegato');
        if (!removeBtn) {
            return;
        }

        const item = removeBtn.closest('.allegato-item');
        if (!item) {
            return;
        }

        const itemId = item.dataset.id;
        setAllegatiIds(getAllegatiIds().filter(function(id) {
            return id !== itemId;
        }));
        item.remove();
    });
});
</script>
