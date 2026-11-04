<?php
require '/var/www/html/wp-load.php';
function hpv_report_assert($condition, $message) {
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL); exit(1); }
    echo 'PASS: ' . $message . PHP_EOL;
}
global $wpdb;
hpv_report_assert(get_option('hpv_analytics_schema') === '1', 'Analytics table installed');
hpv_report_assert(wp_next_scheduled('hpv_analytics_cleanup'), 'Retention scheduled');
wp_set_current_user(0);
hpv_report_assert(!hpv_analytics_allowed(), 'Guests cannot read report data');
$viewer = new WP_User(0);
$viewer->caps = array('hpv_report_viewer' => true);
$viewer->get_role_caps();
hpv_report_assert($viewer->has_cap('hpv_view_reports') && !$viewer->has_cap('edit_posts') && !$viewer->has_cap('manage_options'), 'Viewer role only grants read-only reporting');
$key = 'qa-' . wp_generate_uuid4();
$form_key = 'form-' . random_int(1000000000, 2000000000);
try {
    hpv_report_assert(hpv_analytics_store('zalo', '/san-pham/?phone=private#fragment', $key) === 1, 'Click saved');
    hpv_report_assert(hpv_analytics_store('zalo', '/san-pham/', $key) === 0, 'Repeated event identifier is not counted twice');
    $path = $wpdb->get_var($wpdb->prepare('SELECT page_path FROM ' . $wpdb->prefix . 'hpv_events WHERE event_key=%s', $key));
    hpv_report_assert($path === '/san-pham/', 'Query strings and fragments are excluded');
    $_POST['hpv_return'] = home_url('/lien-he/?email=private');
    do_action('hpv_quote_saved', (int) substr($form_key, 5));
    do_action('hpv_quote_saved', (int) substr($form_key, 5));
    $count = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $wpdb->prefix . 'hpv_events WHERE event_key=%s AND event_type=%s', $form_key, 'form'));
    hpv_report_assert((int) $count === 1, 'Saved form hook counts one successful request');
} finally {
    $wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'hpv_events WHERE event_key IN (%s,%s)', $key, $form_key));
}
