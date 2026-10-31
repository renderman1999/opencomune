<?php
/**
 * Paginazione pubblica stile OpenComune.
 *
 * @var array $args Argomenti passati da opencomune_render_pagination().
 */

if (!defined('ABSPATH')) {
    exit;
}

$total = max(0, (int) ($args['total'] ?? 0));
$current = max(1, (int) ($args['current'] ?? 1));
$mid_size = max(0, (int) ($args['mid_size'] ?? 2));
$prev_text = (string) ($args['prev_text'] ?? __('Precedente', 'opencomune'));
$next_text = (string) ($args['next_text'] ?? __('Successiva', 'opencomune'));
$add_args = is_array($args['add_args'] ?? null) ? $args['add_args'] : [];

if ($total <= 1) {
    return;
}

static $styles_printed = false;
if (!$styles_printed) {
    $styles_printed = true;
    ?>
    <style>
    .opencomune-pagination {
        margin-top: 2.5rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
    }
    .opencomune-pagination__summary {
        font-size: 0.875rem;
        color: #6b7280;
    }
    .opencomune-pagination__nav {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .opencomune-pagination__item {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-width: 2.5rem;
        padding: 0.5rem 0.85rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid #d1d5db;
        color: #374151;
        background: #fff;
        transition: all 0.2s ease;
        line-height: 1.25;
    }
    .opencomune-pagination__item:hover {
        border-color: #2563eb;
        color: #2563eb;
        background: #eff6ff;
    }
    .opencomune-pagination__item.is-active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
        cursor: default;
        pointer-events: none;
    }
    .opencomune-pagination__item.is-prev,
    .opencomune-pagination__item.is-next {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    .opencomune-pagination__item.is-disabled {
        opacity: 0.45;
        pointer-events: none;
        cursor: not-allowed;
    }
    .opencomune-pagination__dots {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2rem;
        padding: 0.5rem 0.25rem;
        color: #6b7280;
        font-weight: 600;
    }
    </style>
    <?php
}

$links = paginate_links([
    'total' => $total,
    'current' => $current,
    'mid_size' => $mid_size,
    'prev_next' => true,
    'prev_text' => '<i class="bi bi-chevron-left" aria-hidden="true"></i><span>' . esc_html($prev_text) . '</span>',
    'next_text' => '<span>' . esc_html($next_text) . '</span><i class="bi bi-chevron-right" aria-hidden="true"></i>',
    'type' => 'array',
    'add_args' => $add_args,
]);

if (!is_array($links) || $links === []) {
    return;
}
?>

<div class="opencomune-pagination">
    <p class="opencomune-pagination__summary">
        <?php
        printf(
            esc_html__('Pagina %1$s di %2$s', 'opencomune'),
            number_format_i18n($current),
            number_format_i18n($total)
        );
        ?>
    </p>
    <nav class="opencomune-pagination__nav" aria-label="<?php esc_attr_e('Paginazione', 'opencomune'); ?>">
        <?php foreach ($links as $link) : ?>
            <?php if (strpos($link, 'dots') !== false) : ?>
                <span class="opencomune-pagination__dots" aria-hidden="true">&hellip;</span>
                <?php continue; ?>
            <?php endif; ?>

            <?php
            $is_current = strpos($link, 'current') !== false;
            $is_prev = strpos($link, 'prev ') !== false;
            $is_next = strpos($link, 'next ') !== false;
            $classes = 'opencomune-pagination__item';

            if ($is_current) {
                $classes .= ' is-active';
            }
            if ($is_prev) {
                $classes .= ' is-prev';
            }
            if ($is_next) {
                $classes .= ' is-next';
            }

            if (preg_match('/<span[^>]*class="[^"]*page-numbers[^"]*"/', $link)) {
                $link = preg_replace(
                    '/<span([^>]*)class="([^"]*)"/',
                    '<span$1class="$2 ' . esc_attr($classes) . '"',
                    $link,
                    1
                );
            } elseif (preg_match('/<a[^>]*class="[^"]*page-numbers[^"]*"/', $link)) {
                $link = preg_replace(
                    '/<a([^>]*)class="([^"]*)"/',
                    '<a$1class="$2 ' . esc_attr($classes) . '"',
                    $link,
                    1
                );
            } elseif (preg_match('/<(a|span)/', $link)) {
                $link = preg_replace('/<(a|span)/', '<$1 class="' . esc_attr($classes) . '"', $link, 1);
            }

            echo wp_kses_post($link);
            ?>
        <?php endforeach; ?>
    </nav>
</div>
