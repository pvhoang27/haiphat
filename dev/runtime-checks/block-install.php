<?php
require '/var/www/html/wp-load.php';
if (get_option('home') !== 'http://127.0.0.1:8787') { throw new RuntimeException('Run only on the local QA site.'); }
$admin = get_user_by('login', 'hpv_check');
wp_set_current_user($admin->ID);
$pages = hpv_create_demo_content();
$result = hpv_prepare_block_site($pages);
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
echo 'Block pages prepared: ' . wp_json_encode($result) . PHP_EOL;
if (in_array('--refresh-patterns', $argv, true)) {
    // Explicit QA-only refresh; production migration never replaces edited blocks.
    $patterns = hpv_site_patterns();
    wp_update_post(wp_slash(array('ID' => $pages['home'], 'post_content' => $patterns['home'])));
    echo "Refreshed local homepage fixture from current patterns.\n";
}
$manager = get_user_by('login', 'hpv_manager_check');
if (!$manager) {
    $id = wp_insert_user(array('user_login' => 'hpv_manager_check', 'user_pass' => 'Hpv-local-manager-2026!', 'role' => 'hpv_site_manager', 'user_email' => 'hpv-manager@example.test'));
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
}
