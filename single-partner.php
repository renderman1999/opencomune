<?php get_header(); ?>
<main class="container mx-auto px-4 py-8">
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <?php
    $lingue = get_post_meta(get_the_ID(), '_lingue', true);
    $citta = get_post_meta(get_the_ID(), '_citta', true);
    $specializzazioni = get_post_meta(get_the_ID(), '_specializzazioni', true);
    $patentino = get_post_meta(get_the_ID(), '_patentino', true);
    $certificati = get_post_meta(get_the_ID(), '_certificati', true);
    $foto_profilo = get_the_post_thumbnail_url(get_the_ID(), 'full');
    $bio = get_the_content();
    $author_id = get_post_field('post_author', get_the_ID());
    $specializzazioni_terms = get_the_terms(get_the_ID(), 'specializzazioni');
    ?>
    <div class="max-w-3xl mx-auto bg-white rounded-xl shadow p-8 mb-8">
        <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
            <?php if ($foto_profilo): ?>
                <img src="<?php echo esc_url($foto_profilo); ?>" alt="Foto profilo" class="w-32 h-32 rounded-full object-cover border" />
            <?php else: ?>
                <div class="w-32 h-32 rounded-full bg-gray-200 flex items-center justify-center text-4xl text-gray-400">?</div>
            <?php endif; ?>
            <div class="flex-1">
                <h1 class="text-3xl font-bold mb-2 capitalize"><?php the_title(); ?></h1>
                <div class="mb-2 text-gray-600"><?php echo esc_html($bio); ?></div>
                <div class="mb-1"><span class="font-semibold">Lingue:</span> 
                    <?php
                    if (is_array($lingue)) {
                        foreach ($lingue as $l) {
                            echo '<span class="inline-block bg-green-100 text-green-800 rounded px-2 py-1 text-xs mr-1">' . esc_html($l) . '</span>';
                        }
                    } else {
                        echo '<span class="inline-block bg-green-100 text-green-800 rounded px-2 py-1 text-xs mr-1">' . esc_html($lingue) . '</span>';
                    }
                    ?>
                </div>
                <div class="mb-1"><span class="font-semibold">Città operative:</span> 
                    <?php
                    if (is_array($citta)) {
                        foreach ($citta as $c) {
                            echo '<span class="inline-block bg-gray-100 text-gray-700 rounded px-2 py-1 text-xs mr-1 capitalize">' . esc_html(ucwords(strtolower($c))) . '</span>';
                        }
                    } else {
                        echo '<span class="inline-block bg-gray-100 text-gray-700 rounded px-2 py-1 text-xs mr-1 capitalize">' . esc_html(ucwords(strtolower($citta))) . '</span>';
                    }
                    ?>
                </div>
                <div class="mb-1"><span class="font-semibold">Tipologia:</span>
                    <?php
                    if (is_array($specializzazioni)) {
                        foreach ($specializzazioni as $spec) {
                            echo '<span class="inline-block bg-blue-100 text-blue-800 rounded px-2 py-1 text-xs mr-1">' . esc_html($spec) . '</span>';
                        }
                    } else {
                        echo esc_html($specializzazioni);
                    }
                    ?>
                </div>
                <?php if (!empty($patentino)): ?>
                    <div class="inline-flex items-center gap-2 mt-2">
                        <span class="inline-flex items-center bg-yellow-100 text-yellow-800 rounded px-3 py-1 text-xs font-semibold border border-yellow-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2l4 -4m5 2a9 9 0 11-18 0a9 9 0 0118 0z" /></svg>
                            Guida certificata
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Esperienze/Tour della guida -->
    <div class="max-w-3xl mx-auto mb-8">
        <h2 class="text-2xl font-bold mb-4">Esperienze e Tour di questa guida</h2>
        <?php
        $tour_query = new WP_Query([
            'post_type' => 'tour',
            'author' => $author_id,
            'post_status' => 'publish',
            'posts_per_page' => 6,
        ]);
        if ($tour_query->have_posts()): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php while ($tour_query->have_posts()): $tour_query->the_post(); ?>
                    <div class="bg-white rounded shadow p-4 flex flex-col">
                        <?php if (has_post_thumbnail()): ?>
                            <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('medium', ['class' => 'rounded mb-2 w-full h-40 object-cover']); ?></a>
                        <?php endif; ?>
                        <a href="<?php the_permalink(); ?>" class="font-semibold text-lg text-blue-700 hover:underline"><?php the_title(); ?></a>
                        <div class="text-gray-500 text-sm mt-1"><?php echo get_the_excerpt(); ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php wp_reset_postdata(); ?>
        <?php else: ?>
            <div class="text-gray-500">Nessun tour pubblicato da questa guida.</div>
        <?php endif; ?>
    </div>
<?php endwhile; endif; ?>
</main>
<?php get_footer(); ?>
