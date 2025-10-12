<?php
// review-system.php

// Mostra il form recensione solo se l'utente è loggato
function opencomune_review_form($post_id) {
    if (!is_user_logged_in()) {
        echo '<div class="text-center text-gray-500 my-4">Devi <a href="' . esc_url(wp_login_url(get_permalink($post_id))) . '" class="text-blue-600 underline">accedere</a> per lasciare una recensione.</div>';
        return;
    }
    ?>
    <?php if (isset($_GET['review']) && $_GET['review'] === 'ok'): ?>
      <div id="review-success" class="mb-4 p-3 bg-green-100 text-green-800 rounded text-center">
        Grazie per la tua recensione! Sarà visibile dopo l'approvazione.
      </div>
    <?php endif; ?>
    <form id="review-form" class="my-6" method="post">
        <div class="mb-2 font-semibold">Lascia una recensione:</div>
        <div class="flex items-center gap-2 mb-2">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <input type="radio" id="star-<?php echo $i; ?>" name="review_rating" value="<?php echo $i; ?>" required>
                <label for="star-<?php echo $i; ?>" style="color: #fbbf24; font-size: 1.5rem; cursor:pointer;">&#9733;</label>
            <?php endfor; ?>
        </div>
        <textarea name="review_comment" class="w-full border rounded p-2 mb-2" rows="3" placeholder="Scrivi la tua recensione..." required></textarea>
        <input type="hidden" name="review_post_id" value="<?php echo esc_attr($post_id); ?>">
        <?php wp_nonce_field('opencomune_review_nonce', 'opencomune_review_nonce_field'); ?>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded font-semibold">Invia recensione</button>
    </form>
    <script>
    document.getElementById('review-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'opencomune_submit_review');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = data.data.redirect;
            } else {
                alert((data.data && data.data.message) ? data.data.message : 'Errore durante l\'invio della recensione');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Si è verificato un errore durante l\'invio della recensione');
        });
    });
    if (document.getElementById('review-success')) {
      setTimeout(function() {
        if(document.getElementById('review-modal')) document.getElementById('review-modal').classList.add('hidden');
      }, 2000);
    }
    </script>
    <?php
}

// Calcola la media delle recensioni
function opencomune_get_average_rating($post_id) {
    $comments = get_comments(['post_id' => $post_id, 'status' => 'approve']);
    $sum = 0; $count = 0;
    foreach ($comments as $c) {
        $rating = get_comment_meta($c->comment_ID, 'review_rating', true);
        if ($rating) {
            $sum += intval($rating);
            $count++;
        }
    }
    return $count ? round($sum / $count, 1) : 0;
}

// Mostra le recensioni
function opencomune_show_reviews($post_id) {
    $comments = get_comments(['post_id' => $post_id, 'status' => 'approve']);
    $avg = opencomune_get_average_rating($post_id);
    $count = count($comments);
    ?>
    <div id="reviews-list" class="my-8">
        <div class="flex items-center gap-2 mb-4">
            <span class="text-2xl text-yellow-400"><?php for($i=1;$i<=5;$i++) echo $i <= round($avg) ? '★' : '☆'; ?></span>
            <span class="font-semibold"><?php echo $avg; ?>/5</span>
            <span class="text-gray-500 text-sm">(<?php echo $count; ?> recensioni)</span>
        </div>
        <?php if ($count): ?>
            <div class="space-y-4">
                <?php foreach ($comments as $c): 
                    $rating = get_comment_meta($c->comment_ID, 'review_rating', true);
                ?>
                <div class="bg-gray-50 rounded p-3">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-yellow-400"><?php for($i=1;$i<=5;$i++) echo $i <= intval($rating) ? '★' : '☆'; ?></span>
                        <span class="text-xs text-gray-500"><?php echo esc_html($c->comment_author); ?></span>
                        <span class="text-xs text-gray-400"><?php echo get_comment_date('', $c); ?></span>
                    </div>
                    <div class="text-gray-700"><?php echo esc_html($c->comment_content); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            echo '<div class="container mx-auto p-4">
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function opencomune_show_reviews_swiper($post_id) {
    $comments = get_comments(['post_id' => $post_id, 'status' => 'approve']);
    if (!$comments) {
                 echo '<div class="container mx-auto p-4">
</div>';
        return;
    }
    ?>
    <div class="max-w-7xl mx-auto p-4">
        <h2 class="font-bold text-2xl mb-4">Recensioni in evidenza</h2>
        <div class="swiper mySwiperReview">
            <div class="swiper-wrapper">
                <?php foreach ($comments as $c): 
                    $rating = get_comment_meta($c->comment_ID, 'review_rating', true);
                    $author = esc_html($c->comment_author);
                    $date = date_i18n('j F Y', strtotime($c->comment_date));
                    $content = esc_html($c->comment_content);
                    $initial = strtoupper(mb_substr($author, 0, 1));
                    $maxlen = 80;
                    $is_long = mb_strlen($content) > $maxlen;
                    $short_content = $is_long ? mb_substr($content, 0, $maxlen) . '...' : $content;
                ?>
                <div class="swiper-slide">
                    <div class="bg-white rounded-xl shadow p-5 mb-2 flex flex-col gap-2" style="padding:10px;min-width:260px;max-width:350px;">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-yellow-400 text-lg"><?php for($i=1;$i<=5;$i++) echo $i <= intval($rating) ? '★' : '☆'; ?></span>
                        </div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-orange-100 text-orange-700 font-bold text-xl" style="background-color: #fbbf24;color: #000000;"><?php echo $initial; ?></span>
                            <div>
                                <div class="font-bold capitalize"><?php echo $author; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $date; ?></div>
                            </div>
                        </div>
                        <div class="text-gray-700 review-content" style="white-space: pre-line;">
                            <?php if ($is_long): ?>
                                <span class="short-text"><?php echo esc_html($short_content); ?></span>
                                <span class="full-text hidden"><?php echo esc_html($content); ?></span>
                                <a href="#" class="toggle-review text-blue-600 underline text-sm ml-1">Leggi di più</a>
                            <?php else: ?>
                                <?php echo esc_html($content); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function opencomune_show_home_reviews_swiper($limit = 8) {
    $comments = get_comments([
        'number' => $limit,
        'status' => 'approve',
        'post_type' => 'esperienze',
        'orderby' => 'comment_date',
        'order' => 'DESC'
    ]);
    if (!$comments) {
        echo '<div class="container mx-auto p-4">
</div>';
        return;
    }
    ?>
    <div class="container mx-auto px-4 py-8">
    <h2 class="font-bold text-2xl mb-4">Cosa dicono i viaggiatori</h2>
        <div class="swiper homeReviewSwiper">
            <div class="swiper-wrapper">
                <?php foreach ($comments as $c): 
                    $rating = get_comment_meta($c->comment_ID, 'review_rating', true);
                    $author = esc_html($c->comment_author);
                    $date = date_i18n('j F Y', strtotime($c->comment_date));
                    $content = esc_html($c->comment_content);
                    $initial = strtoupper(mb_substr($author, 0, 1));
                    $maxlen = 80;
                    $is_long = mb_strlen($content) > $maxlen;
                    $short_content = $is_long ? mb_substr($content, 0, $maxlen) . '...' : $content;
                    
                    // Ottieni il titolo del tour
                    $tour_title = get_the_title($c->comment_post_ID);
                    $tour_link = get_permalink($c->comment_post_ID);
                ?>
                <div class="swiper-slide">
                    <div class="bg-white rounded-xl shadow p-5 mb-2 flex flex-col gap-2" style="padding:10px;min-width:260px;max-width:350px;">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-yellow-400 text-lg"><?php for($i=1;$i<=5;$i++) echo $i <= intval($rating) ? '★' : '☆'; ?></span>
                        </div>
                        
                        <!-- Titolo del tour -->
                        <div class="mb-2">
                            <a href="<?php echo esc_url($tour_link); ?>" class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                                <?php echo esc_html($tour_title); ?>
                            </a>
                        </div>
                        
                        <div class="flex items-center gap-2 mb-1">
                            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-orange-100 text-orange-700 font-bold text-xl" style="background-color: #fbbf24;color: #000000;">
                                <?php echo $initial; ?>
                            </span>
                            <div>
                                <div class="font-bold capitalize"><?php echo $author; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $date; ?></div>
                            </div>
                        </div>
                        <div class="text-gray-700 review-content" style="white-space: pre-line;">
                            <?php if ($is_long): ?>
                                <span class="short-text"><?php echo esc_html($short_content); ?></span>
                                <span class="full-text hidden"><?php echo esc_html($content); ?></span>
                                <a href="#" class="toggle-review text-blue-600 underline text-sm ml-1">Leggi di più</a>
                            <?php else: ?>
                                <?php echo esc_html($content); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
} 