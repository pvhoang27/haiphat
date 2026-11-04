<?php
/** First-party interaction counts and a read-only customer report. */
defined('ABSPATH') || exit;

function hpv_analytics_install() {
    if (get_option('hpv_analytics_schema') === '1') { return; }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = $wpdb->prefix . 'hpv_events';
    dbDelta("CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_key varchar(64) NOT NULL,
        event_type varchar(20) NOT NULL,
        page_path varchar(500) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY event_key (event_key),
        KEY event_date (created_at,event_type)
    ) " . $wpdb->get_charset_collate() . ';');
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) { return; }
    add_role('hpv_report_viewer', 'Xem báo cáo', array('read' => true, 'hpv_view_reports' => true));
    update_option('hpv_analytics_schema', '1', false);
    update_option('hpv_analytics_started', current_time('mysql'), false);
}
add_action('init', 'hpv_analytics_install');

add_action('init', function () {
    if (!wp_next_scheduled('hpv_analytics_cleanup')) { wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'hpv_analytics_cleanup'); }
});
add_action('hpv_analytics_cleanup', function () {
    global $wpdb;
    $wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'hpv_events WHERE created_at < %s', current_datetime()->modify('-180 days')->format('Y-m-d H:i:s')));
});

function hpv_analytics_allowed() {
    return current_user_can('manage_options') || current_user_can('hpv_view_reports');
}

function hpv_analytics_path($url) {
    $path = wp_parse_url($url, PHP_URL_PATH);
    return substr(sanitize_text_field(is_string($path) ? $path : '/'), 0, 500);
}

function hpv_analytics_store($type, $path, $key) {
    global $wpdb;
    return $wpdb->query($wpdb->prepare(
        'INSERT IGNORE INTO ' . $wpdb->prefix . 'hpv_events (event_key,event_type,page_path,created_at) VALUES (%s,%s,%s,%s)',
        $key, $type, hpv_analytics_path($path), current_time('mysql')
    ));
}

add_action('hpv_quote_saved', function ($post_id) {
    $return = isset($_POST['hpv_return']) ? esc_url_raw(wp_unslash($_POST['hpv_return'])) : '/';
    hpv_analytics_store('form', $return, 'form-' . (int) $post_id);
});

function hpv_analytics_collect() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_send_json_error(null, 405); }
    $origin = get_http_origin();
    if (!$origin || strtolower((string) wp_parse_url($origin, PHP_URL_HOST)) !== strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))) {
        wp_send_json_error(null, 403);
    }
    if (is_user_logged_in()) { wp_send_json_success(); }
    foreach (array('event', 'key', 'path') as $field) {
        if (!isset($_POST[$field]) || !is_string($_POST[$field])) { wp_send_json_error(null, 400); }
    }
    $type = sanitize_key(wp_unslash($_POST['event'] ?? ''));
    $key = sanitize_text_field(wp_unslash($_POST['key'] ?? ''));
    $path = wp_unslash($_POST['path'] ?? '/');
    if (!in_array($type, array('zalo', 'phone', 'link'), true) || !preg_match('/^[a-f0-9-]{36}$/i', $key) || !is_string($path)) { wp_send_json_error(null, 400); }
    // A salted, short-lived rate key; raw IP addresses are never stored.
    $rate = 'hpv_ev_' . hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . gmdate('YmdHi'), wp_salt('nonce'));
    $count = (int) get_transient($rate);
    if ($count >= 60) { wp_send_json_error(null, 429); }
    set_transient($rate, $count + 1, 2 * MINUTE_IN_SECONDS);
    if (preg_match('/bot|crawler|spider|headless/i', $_SERVER['HTTP_USER_AGENT'] ?? '')) { wp_send_json_success(); }
    $result = hpv_analytics_store($type, $path, $key);
    if ($result === false) { wp_send_json_error(null, 503); }
    wp_send_json_success();
}
add_action('wp_ajax_nopriv_hpv_track', 'hpv_analytics_collect');
add_action('wp_ajax_hpv_track', 'hpv_analytics_collect');

add_action('wp_enqueue_scripts', function () {
    if (is_user_logged_in()) { return; }
    wp_enqueue_script('hpv-tracking', HPV_CORE_URL . 'assets/tracking.js', array(), '1.0.0', true);
    wp_add_inline_script('hpv-tracking', 'window.hpvTracking=' . wp_json_encode(array('endpoint' => admin_url('admin-ajax.php'))) . ';', 'before');
});

function hpv_report_url() { return home_url('/bao-cao/'); }

// Handle the route directly so installation does not require permalink changes.
add_action('template_redirect', function () {
    if (untrailingslashit(hpv_analytics_path($_SERVER['REQUEST_URI'] ?? '')) !== untrailingslashit(hpv_analytics_path(hpv_report_url()))) { return; }
    if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow', true);
    header('X-Frame-Options: SAMEORIGIN', true);
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (isset($_POST['hpv_report_logout']) && is_string($_POST['hpv_report_logout']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hpv_report_logout'])), 'hpv_report_logout')) {
            wp_logout();
            wp_safe_redirect(hpv_report_url());
            exit;
        }
        if (!isset($_POST['hpv_report_nonce']) || !is_string($_POST['hpv_report_nonce']) || !is_string($_POST['username'] ?? null) || !is_string($_POST['password'] ?? null) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hpv_report_nonce'])), 'hpv_report_login')) {
            $error = 'Phiên đăng nhập hết hạn. Vui lòng tải lại trang.';
        } else {
            $rate = 'hpv_login_' . hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? '', wp_salt('auth'));
            $attempts = (int) get_transient($rate);
            if ($attempts >= 10) {
                $error = 'Bạn đã thử nhiều lần. Vui lòng thử lại sau 15 phút.';
            } else {
                set_transient($rate, $attempts + 1, 15 * MINUTE_IN_SECONDS);
                $user = wp_signon(array('user_login' => sanitize_text_field(wp_unslash($_POST['username'] ?? '')), 'user_password' => (string) wp_unslash($_POST['password'] ?? ''), 'remember' => false), is_ssl());
                if (is_wp_error($user)) { $error = 'Tên đăng nhập hoặc mật khẩu chưa đúng.'; }
                else {
                    delete_transient($rate);
                    $report_query = array();
                    foreach (array('view', 'days', 'demo') as $field) {
                        if (isset($_GET[$field]) && is_string($_GET[$field])) { $report_query[$field] = sanitize_text_field(wp_unslash($_GET[$field])); }
                    }
                    wp_safe_redirect(add_query_arg($report_query, hpv_report_url()));
                    exit;
                }
            }
        }
    }
    status_header(is_user_logged_in() && !hpv_analytics_allowed() ? 403 : 200);
    require __DIR__ . '/report-view.php';
    exit;
}, 0);

// Viewer accounts stay in the customer interface, including after normal login.
add_filter('login_redirect', function ($redirect, $requested, $user) {
    return $user instanceof WP_User && in_array('hpv_report_viewer', $user->roles, true) && !user_can($user, 'manage_options') ? hpv_report_url() : $redirect;
}, 10, 3);
add_action('admin_init', function () {
    if (!wp_doing_ajax() && current_user_can('hpv_view_reports') && !current_user_can('edit_posts') && !current_user_can('manage_options')) { wp_safe_redirect(hpv_report_url()); exit; }
});
