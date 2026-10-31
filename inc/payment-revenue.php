<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_incassi_dashboard_url() {
    return home_url('/incassi/');
}

function opencomune_ensure_incassi_dashboard_page() {
    $slug = 'incassi';
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post) {
        return;
    }

    $page_id = wp_insert_post([
        'post_title' => __('Incassi', 'opencomune'),
        'post_name' => $slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '',
    ], true);

    if (!is_wp_error($page_id) && $page_id > 0) {
        update_post_meta($page_id, '_wp_page_template', 'page-incassi.php');
    }
}

add_action('after_setup_theme', 'opencomune_ensure_incassi_dashboard_page', 21);

/**
 * Partner con almeno una esperienza (per filtro).
 *
 * @return array<int, array{id:int,label:string}>
 */
function opencomune_get_incassi_partner_filter_options() {
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT DISTINCT u.ID AS id, u.display_name AS name, u.user_email AS email
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->posts} p ON p.post_author = u.ID AND p.post_type = 'esperienze' AND p.post_status != 'trash'
         ORDER BY u.display_name ASC"
    );

    $options = [];
    foreach ($rows ?: [] as $row) {
        $label = trim((string) $row->name);
        if ($label === '') {
            $label = (string) $row->email;
        }
        $options[] = [
            'id' => (int) $row->id,
            'label' => $label,
        ];
    }

    return $options;
}

/**
 * Esperienze del partner (filtro incassi).
 *
 * @return array<int, array{id:int,label:string}>
 */
function opencomune_get_incassi_experience_filter_options($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $posts = get_posts([
        'post_type' => 'esperienze',
        'author' => $user_id,
        'post_status' => ['publish', 'pending', 'draft', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => false,
    ]);

    $options = [];
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $options[] = [
            'id' => (int) $post->ID,
            'label' => get_the_title($post) ?: ('#' . $post->ID),
        ];
    }

    return $options;
}

/**
 * @return array{where:string,params:array,status:string}
 */
function opencomune_build_incassi_orders_query_parts(array $args) {
    $days = max(0, (int) ($args['days'] ?? 90));
    $partner_id = (int) ($args['partner_id'] ?? 0);
    $tour_id = (int) ($args['tour_id'] ?? 0);
    $status = sanitize_key((string) ($args['status'] ?? 'settled'));

    $allowed = ['settled', 'completed', 'refunded', 'pending', 'cancelled', 'all'];
    if (!in_array($status, $allowed, true)) {
        $status = 'settled';
    }

    $where = ['1=1'];
    $params = [];

    if ($status === 'settled') {
        $where[] = "o.status IN ('completed', 'refunded')";
    } elseif ($status === 'all') {
        // nessun filtro stato
    } else {
        $where[] = 'o.status = %s';
        $params[] = $status;
    }

    if ($days > 0) {
        if ($status === 'settled') {
            $where[] = "(
                (o.status = 'completed' AND COALESCE(o.data_pagamento, o.data_creazione) >= DATE_SUB(NOW(), INTERVAL %d DAY))
                OR (o.status = 'refunded' AND COALESCE(o.refund_at, o.data_pagamento, o.data_creazione) >= DATE_SUB(NOW(), INTERVAL %d DAY))
            )";
            $params[] = $days;
            $params[] = $days;
        } elseif ($status === 'refunded') {
            $where[] = 'COALESCE(o.refund_at, o.data_pagamento, o.data_creazione) >= DATE_SUB(NOW(), INTERVAL %d DAY)';
            $params[] = $days;
        } else {
            $where[] = 'COALESCE(o.data_pagamento, o.data_creazione) >= DATE_SUB(NOW(), INTERVAL %d DAY)';
            $params[] = $days;
        }
    }

    if ($partner_id > 0) {
        $where[] = 'p.post_author = %d';
        $params[] = $partner_id;
    }

    if ($tour_id > 0) {
        $where[] = 'o.tour_id = %d';
        $params[] = $tour_id;
    }

    return [
        'where' => implode(' AND ', $where),
        'params' => $params,
        'status' => $status,
    ];
}

/**
 * @param array<int, object> $completed_rows
 * @param array<int, object> $refund_rows
 * @return array<int, array<string, mixed>>
 */
function opencomune_merge_incassi_monthly_chart_rows(array $completed_rows, array $refund_rows) {
    $periods = [];

    foreach ($completed_rows as $row) {
        $key = (string) $row->period;
        if ($key === '') {
            continue;
        }
        if (!isset($periods[$key])) {
            $periods[$key] = [
                'gross' => 0.0,
                'commission' => 0.0,
                'net' => 0.0,
                'refund' => 0.0,
                'count' => 0,
            ];
        }
        $periods[$key]['gross'] += (float) $row->gross;
        $periods[$key]['commission'] += (float) $row->commission;
        $periods[$key]['net'] += (float) $row->net;
        $periods[$key]['count'] += (int) $row->cnt;
    }

    foreach ($refund_rows as $row) {
        $key = (string) $row->period;
        if ($key === '') {
            continue;
        }
        if (!isset($periods[$key])) {
            $periods[$key] = [
                'gross' => 0.0,
                'commission' => 0.0,
                'net' => 0.0,
                'refund' => 0.0,
                'count' => 0,
            ];
        }
        $periods[$key]['refund'] += (float) $row->refund;
    }

    ksort($periods);
    $out = [];
    foreach ($periods as $label => $data) {
        $net_after = max(0, round($data['gross'] - $data['refund'], 2));
        $out[] = [
            'label' => $label,
            'gross' => round($data['gross'], 2),
            'commission' => round($data['commission'], 2),
            'net' => round($data['net'], 2),
            'refund' => round($data['refund'], 2),
            'net_after_refund' => $net_after,
            'count' => (int) $data['count'],
        ];
    }

    return $out;
}

/**
 * @return array<string, mixed>
 */
function opencomune_query_incassi_dashboard_data(array $args = []) {
    global $wpdb;

    opencomune_ensure_tour_ordini_table();
    if (function_exists('opencomune_ensure_tour_ordini_commission_columns')) {
        opencomune_ensure_tour_ordini_commission_columns();
    }
    if (function_exists('opencomune_ensure_tour_ordini_refund_columns')) {
        opencomune_ensure_tour_ordini_refund_columns();
    }
    if (function_exists('opencomune_sync_booking_commissions_from_orders')) {
        opencomune_sync_booking_commissions_from_orders(50);
    }

    $table = opencomune_tour_ordini_table_name();
    $parts = opencomune_build_incassi_orders_query_parts($args);
    $filter_status = $parts['status'];
    $join = "LEFT JOIN {$wpdb->posts} p ON p.ID = o.tour_id AND p.post_type = 'esperienze'";

    $sql_base = "FROM {$table} o {$join} WHERE {$parts['where']}";

    $summary_sql = "SELECT
            SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS payment_count,
            SUM(CASE WHEN o.status = 'refunded' THEN 1 ELSE 0 END) AS refund_count,
            COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.totale ELSE 0 END), 0) AS gross_total,
            COALESCE(SUM(CASE WHEN o.status = 'refunded' THEN COALESCE(o.refund_amount, o.totale) ELSE 0 END), 0) AS refund_total,
            COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.commission_amount ELSE 0 END), 0) AS commission_total,
            COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.net_amount ELSE 0 END), 0) AS net_total
        {$sql_base}";

    $summary_row = $parts['params']
        ? $wpdb->get_row($wpdb->prepare($summary_sql, $parts['params']))
        : $wpdb->get_row($summary_sql);

    $monthly_completed_sql = "SELECT DATE_FORMAT(o.data_pagamento, '%Y-%m') AS period,
            COALESCE(SUM(o.totale), 0) AS gross,
            COALESCE(SUM(o.commission_amount), 0) AS commission,
            COALESCE(SUM(o.net_amount), 0) AS net,
            COUNT(*) AS cnt
        {$sql_base}
        AND o.status = 'completed'
        AND o.data_pagamento IS NOT NULL
        GROUP BY period
        ORDER BY period ASC
        LIMIT 24";

    $monthly_refund_sql = "SELECT DATE_FORMAT(COALESCE(o.refund_at, o.data_pagamento), '%Y-%m') AS period,
            COALESCE(SUM(COALESCE(o.refund_amount, o.totale)), 0) AS refund
        {$sql_base}
        AND o.status = 'refunded'
        AND COALESCE(o.refund_at, o.data_pagamento) IS NOT NULL
        GROUP BY period
        ORDER BY period ASC
        LIMIT 24";

    $monthly_completed_rows = $parts['params']
        ? $wpdb->get_results($wpdb->prepare($monthly_completed_sql, $parts['params']))
        : $wpdb->get_results($monthly_completed_sql);

    $monthly_refund_rows = $parts['params']
        ? $wpdb->get_results($wpdb->prepare($monthly_refund_sql, $parts['params']))
        : $wpdb->get_results($monthly_refund_sql);

    $partner_sql = "SELECT p.post_author AS partner_id,
            COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.totale ELSE 0 END), 0) AS gross,
            COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.commission_amount ELSE 0 END), 0) AS commission,
            COALESCE(SUM(CASE WHEN o.status = 'refunded' THEN COALESCE(o.refund_amount, o.totale) ELSE 0 END), 0) AS refund
        {$sql_base}
        AND p.post_author IS NOT NULL AND p.post_author > 0
        GROUP BY p.post_author
        ORDER BY commission DESC
        LIMIT 12";

    $partner_rows = $parts['params']
        ? $wpdb->get_results($wpdb->prepare($partner_sql, $parts['params']))
        : $wpdb->get_results($partner_sql);

    $chart_by_experience = [];
    $scoped_partner = (int) ($args['partner_id'] ?? 0);
    if ($scoped_partner > 0) {
        $experience_sql = "SELECT o.tour_id AS tour_id,
                COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.totale ELSE 0 END), 0) AS gross,
                COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.commission_amount ELSE 0 END), 0) AS commission,
                COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.net_amount ELSE 0 END), 0) AS net,
                COALESCE(SUM(CASE WHEN o.status = 'refunded' THEN COALESCE(o.refund_amount, o.totale) ELSE 0 END), 0) AS refund
            {$sql_base}
            AND o.tour_id IS NOT NULL AND o.tour_id > 0
            GROUP BY o.tour_id
            ORDER BY net DESC
            LIMIT 12";

        $experience_rows = $parts['params']
            ? $wpdb->get_results($wpdb->prepare($experience_sql, $parts['params']))
            : $wpdb->get_results($experience_sql);

        foreach ($experience_rows ?: [] as $row) {
            $tid = (int) $row->tour_id;
            $title = $tid > 0 ? get_the_title($tid) : '';
            if ($title === '') {
                $title = '#' . $tid;
            }
            $net_after = max(0, (float) $row->net - (float) $row->refund);
            $chart_by_experience[] = [
                'label' => $title,
                'gross' => round((float) $row->gross, 2),
                'commission' => round((float) $row->commission, 2),
                'net' => round($net_after, 2),
                'refund' => round((float) $row->refund, 2),
            ];
        }
    }

    $limit = min(300, max(1, (int) ($args['limit'] ?? 100)));
    $list_sql = "SELECT o.*, p.post_title AS esperienza_title, p.post_author AS partner_id
        {$sql_base}
        ORDER BY
            CASE WHEN o.status = 'refunded' THEN COALESCE(o.refund_at, o.data_pagamento, o.data_creazione)
                 ELSE COALESCE(o.data_pagamento, o.data_creazione) END DESC
        LIMIT %d";

    $list_params = array_merge($parts['params'], [$limit]);
    $list_rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_params));

    $chart_monthly = opencomune_merge_incassi_monthly_chart_rows(
        $monthly_completed_rows ?: [],
        $monthly_refund_rows ?: []
    );

    $chart_partners = [];
    foreach ($partner_rows ?: [] as $row) {
        $pid = (int) $row->partner_id;
        $user = get_userdata($pid);
        $name = $user && $user->exists() ? $user->display_name : ('#' . $pid);
        $chart_partners[] = [
            'label' => $name,
            'gross' => round((float) $row->gross, 2),
            'commission' => round((float) $row->commission, 2),
            'refund' => round((float) $row->refund, 2),
        ];
    }

    $payments = [];
    foreach ($list_rows ?: [] as $row) {
        $partner_id = (int) ($row->partner_id ?? 0);
        $partner_label = '';
        if ($partner_id > 0) {
            $user = get_userdata($partner_id);
            $partner_label = $user && $user->exists() ? $user->display_name : ('#' . $partner_id);
        }

        $order_status = sanitize_key((string) ($row->status ?? ''));
        $is_refunded = $order_status === 'refunded';
        $refund_amount = isset($row->refund_amount) ? (float) $row->refund_amount : (float) $row->totale;
        $gross = (float) $row->totale;
        $breakdown = function_exists('opencomune_get_order_commission_breakdown')
            ? opencomune_get_order_commission_breakdown($row)
            : [
                'gross' => $gross,
                'percent' => null,
                'commission' => isset($row->commission_amount) ? (float) $row->commission_amount : 0.0,
                'net' => isset($row->net_amount) ? (float) $row->net_amount : max(0, $gross),
            ];
        $commission = (float) $breakdown['commission'];
        $net = (float) $breakdown['net'];

        if ($is_refunded) {
            $paid_at = !empty($row->refund_at)
                ? date_i18n('d/m/Y H:i', strtotime($row->refund_at))
                : (!empty($row->data_pagamento) ? date_i18n('d/m/Y H:i', strtotime($row->data_pagamento)) : '');
        } else {
            $paid_at = !empty($row->data_pagamento)
                ? date_i18n('d/m/Y H:i', strtotime($row->data_pagamento))
                : '';
        }

        $booking_id = (int) ($row->booking_id ?? 0);
        $detail_url = $booking_id > 0 && function_exists('opencomune_get_partner_booking_detail_url')
            ? opencomune_get_partner_booking_detail_url($booking_id)
            : '';

        $refund_ratio = $gross > 0 ? min(1.0, $refund_amount / $gross) : 1.0;
        $display_commission = $is_refunded ? round($commission * $refund_ratio, 2) : $commission;
        $display_net = $is_refunded ? round($net * $refund_ratio, 2) : $net;

        $display_gross = $is_refunded ? $refund_amount : $gross;
        $gross_formatted = $is_refunded
            ? ('−' . number_format_i18n($refund_amount, 2) . ' EUR')
            : (number_format_i18n($gross, 2) . ' EUR');
        if (!$breakdown['has_stored']) {
            $commission_formatted = '—';
            $net_formatted = '—';
        } elseif ($is_refunded) {
            $commission_formatted = '−' . number_format_i18n($display_commission, 2) . ' EUR';
            $net_formatted = '−' . number_format_i18n($display_net, 2) . ' EUR';
        } else {
            $commission_formatted = number_format_i18n($display_commission, 2) . ' EUR';
            $net_formatted = number_format_i18n($display_net, 2) . ' EUR';
        }

        $payments[] = [
            'id' => (int) $row->id,
            'order_id' => (string) $row->order_id,
            'paid_at' => $paid_at,
            'partner_id' => $partner_id,
            'partner_name' => $partner_label,
            'esperienza_title' => (string) ($row->esperienza_title ?? ''),
            'gross' => $display_gross,
            'gross_formatted' => $gross_formatted,
            'commission' => $is_refunded ? -$display_commission : $display_commission,
            'commission_formatted' => $commission_formatted,
            'net' => $is_refunded ? -$display_net : $display_net,
            'net_formatted' => $net_formatted,
            'refund_amount' => $is_refunded ? $refund_amount : 0.0,
            'refund_amount_formatted' => $is_refunded ? number_format_i18n($refund_amount, 2) . ' EUR' : '',
            'is_refund' => $is_refunded,
            'commission_percent' => isset($breakdown['percent']) ? (float) $breakdown['percent'] : null,
            'status' => (string) $row->status,
            'status_label' => function_exists('opencomune_tour_order_status_label')
                ? opencomune_tour_order_status_label($row->status)
                : (string) $row->status,
            'status_badge_class' => function_exists('opencomune_tour_order_status_badge_class')
                ? opencomune_tour_order_status_badge_class($row->status)
                : 'oc-booking-status--pending',
            'gateway' => (string) ($row->gateway ?? ''),
            'booking_id' => $booking_id,
            'detail_url' => $detail_url,
        ];
    }

    $gross_total = $summary_row ? (float) $summary_row->gross_total : 0.0;
    $refund_total = $summary_row ? (float) $summary_row->refund_total : 0.0;
    $commission_total = $summary_row ? (float) $summary_row->commission_total : 0.0;
    $net_total = $summary_row ? (float) $summary_row->net_total : 0.0;
    $net_after_refund = max(0, $gross_total - $refund_total);
    $partner_net_after_refund = max(0, $net_total - $refund_total);

    return [
        'summary' => [
            'payment_count' => $summary_row ? (int) $summary_row->payment_count : 0,
            'refund_count' => $summary_row ? (int) $summary_row->refund_count : 0,
            'gross_total' => round($gross_total, 2),
            'refund_total' => round($refund_total, 2),
            'commission_total' => round($commission_total, 2),
            'net_total' => round($net_total, 2),
            'net_after_refund' => round($net_after_refund, 2),
            'partner_net_after_refund' => round($partner_net_after_refund, 2),
            'gross_formatted' => number_format_i18n($gross_total, 2) . ' EUR',
            'refund_total_formatted' => number_format_i18n($refund_total, 2) . ' EUR',
            'commission_formatted' => number_format_i18n($commission_total, 2) . ' EUR',
            'net_formatted' => number_format_i18n($net_total, 2) . ' EUR',
            'net_after_refund_formatted' => number_format_i18n($net_after_refund, 2) . ' EUR',
            'partner_net_after_refund_formatted' => number_format_i18n($partner_net_after_refund, 2) . ' EUR',
            'filter_status' => $filter_status,
        ],
        'chart_monthly' => $chart_monthly,
        'chart_partners' => $chart_partners,
        'chart_by_experience' => $chart_by_experience,
        'payments' => $payments,
    ];
}

/**
 * @return array{days:int,partner_id:int,tour_id:int,status:string,limit:int}
 */
function opencomune_parse_incassi_dashboard_request_args() {
    $is_admin = opencomune_user_can_manage_all_esperienze();
    $partner_id = 0;
    $tour_id = isset($_POST['tour_id']) ? (int) $_POST['tour_id'] : 0;

    if ($is_admin) {
        $partner_id = isset($_POST['partner_id']) ? (int) $_POST['partner_id'] : 0;
    } else {
        $partner_id = get_current_user_id();
        if ($tour_id > 0) {
            $post = get_post($tour_id);
            if (
                !$post instanceof WP_Post
                || $post->post_type !== 'esperienze'
                || (int) $post->post_author !== $partner_id
            ) {
                $tour_id = 0;
            }
        }
    }

    return [
        'days' => isset($_POST['days']) ? (int) $_POST['days'] : 90,
        'partner_id' => $partner_id,
        'tour_id' => $tour_id,
        'status' => isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'settled',
        'limit' => isset($_POST['limit']) ? (int) $_POST['limit'] : 100,
    ];
}

add_action('wp_ajax_opencomune_get_incassi_dashboard', 'opencomune_get_incassi_dashboard_callback');
function opencomune_get_incassi_dashboard_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_incassi_dashboard', 'nonce');

    $data = opencomune_query_incassi_dashboard_data(opencomune_parse_incassi_dashboard_request_args());

    wp_send_json_success($data);
}

function opencomune_enqueue_dashboard_incassi_assets() {
    $is_admin = opencomune_user_can_manage_all_esperienze();
    $user_id = get_current_user_id();
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    opencomune_enqueue_dashboard_layout_assets();

    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
        [],
        '4.4.1',
        true
    );

    wp_enqueue_script(
        'opencomune-dashboard-incassi',
        $theme_uri . '/assets/js/dashboard-incassi.js',
        ['chartjs'],
        file_exists($theme_dir . '/assets/js/dashboard-incassi.js') ? (string) filemtime($theme_dir . '/assets/js/dashboard-incassi.js') : '1.0',
        true
    );

    wp_localize_script('opencomune-dashboard-incassi', 'opencomuneIncassiDashboard', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('opencomune_incassi_dashboard'),
        'isAdmin' => $is_admin,
        'partners' => $is_admin ? opencomune_get_incassi_partner_filter_options() : [],
        'experiences' => $is_admin ? [] : opencomune_get_incassi_experience_filter_options($user_id),
        'i18n' => [
            'loadError' => __('Impossibile caricare gli incassi', 'opencomune'),
            'noPayments' => __('Nessun pagamento nel periodo selezionato', 'opencomune'),
            'colDate' => __('Data pagamento', 'opencomune'),
            'colPartner' => __('Partner', 'opencomune'),
            'colExperience' => __('Esperienza', 'opencomune'),
            'colGross' => __('Incassato', 'opencomune'),
            'colCommission' => __('Commissione', 'opencomune'),
            'colNet' => __('Netto partner', 'opencomune'),
            'colStatus' => __('Stato', 'opencomune'),
            'colOrder' => __('Ordine', 'opencomune'),
            'chartMonthlyTitle' => __('Incassi per mese', 'opencomune'),
            'chartPartnersTitle' => __('Commissioni per partner', 'opencomune'),
            'chartExperiencesTitle' => __('Netto per esperienza', 'opencomune'),
            'allExperiences' => __('Tutte le esperienze', 'opencomune'),
            'statPlatformCommission' => __('Guadagno commissioni', 'opencomune'),
            'statYourNet' => __('Il tuo netto', 'opencomune'),
            'statCommissionDeducted' => __('Commissioni piattaforma', 'opencomune'),
            'chartGross' => __('Incassato', 'opencomune'),
            'chartCommission' => __('Commissioni', 'opencomune'),
            'chartNet' => __('Netto partner', 'opencomune'),
            'chartRefund' => __('Rimborsi', 'opencomune'),
            'chartNetAfterRefund' => __('Netto dopo rimborsi', 'opencomune'),
            'colRefund' => __('Rimborso', 'opencomune'),
            'openBooking' => __('Prenotazione', 'opencomune'),
        ],
    ]);
}
