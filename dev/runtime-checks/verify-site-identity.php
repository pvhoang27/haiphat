<?php
require '/var/www/html/wp-load.php';
if (get_option('home') !== 'http://127.0.0.1:8787') { throw new RuntimeException('Run only on the local QA site.'); }
function hpv_identity_assert($value, $message) {
    if (!$value) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
function hpv_identity_request($method, $body = null) {
    $request = new WP_REST_Request($method, '/wp/v2/settings');
    if ($body !== null) { $request->set_header('Content-Type', 'application/json'); $request->set_body(is_string($body) ? $body : wp_json_encode($body)); }
    return rest_do_request($request);
}
$manager = get_user_by('login', 'hpv_manager_check');
$saved = array();
foreach (array('blogname', 'blogdescription', 'site_logo', 'site_icon', 'admin_email', 'users_can_register') as $option) { $saved[$option] = get_option($option); }
$saved_logo = get_theme_mod('custom_logo');
wp_set_current_user($manager->ID);
try {
    $options = hpv_identity_request('OPTIONS');
    hpv_identity_assert($options->get_status() === 200 && strpos($options->get_headers()['Allow'], 'POST') !== false, 'Gutenberg detects manager identity editing permission via OPTIONS');
    $response = hpv_identity_request('GET');
    $data = $response->get_data();
    hpv_identity_assert($response->get_status() === 200 && array_keys($data) === array('title', 'description', 'site_logo', 'site_icon'), 'Manager reads only allowlisted site identity fields');
    hpv_identity_assert(!isset($data['email']) && !isset($data['url']) && !isset($data['users_can_register']), 'Manager identity response does not expose unrelated settings');
    $response = hpv_identity_request('POST', array('title' => 'QA Hải Phát', 'description' => 'QA mô tả ngắn'));
    hpv_identity_assert($response->get_status() === 200 && get_option('blogname') === 'QA Hải Phát' && get_option('blogdescription') === 'QA mô tả ngắn', 'Manager saves native Site Title and Tagline');
    $logo = absint(get_option('hpv_brand_logo_id'));
    $response = hpv_identity_request('POST', array('site_logo' => $logo));
    hpv_identity_assert($response->get_status() === 200 && (int) get_option('site_logo') === $logo, 'Manager saves an existing Media Library image as site logo');
    $response = hpv_identity_request('POST', array('title' => 'Must not persist', 'email' => 'qa-forbidden@example.test'));
    hpv_identity_assert($response->get_status() === 403 && get_option('blogname') === 'QA Hải Phát' && get_option('admin_email') === $saved['admin_email'], 'Mixed allowed and forbidden settings rejected without partial write');
    $response = hpv_identity_request('POST', array('users_can_register' => true));
    hpv_identity_assert($response->get_status() === 403 && get_option('users_can_register') === $saved['users_can_register'], 'Manager cannot enable registration through identity endpoint');
    $response = hpv_identity_request('POST', array('site_logo' => -1));
    hpv_identity_assert($response->get_status() === 400, 'Negative logo IDs rejected');
    $response = hpv_identity_request('POST', array('site_logo' => $manager->ID));
    hpv_identity_assert($response->get_status() === 400, 'Non-image IDs rejected');
    $response = hpv_identity_request('POST', '{"title":');
    hpv_identity_assert($response->get_status() === 400, 'Malformed JSON rejected');
    hpv_identity_assert(!current_user_can('manage_options'), 'Identity editing grants no general settings capability');
    wp_set_current_user(username_exists('hpv_editor_check'));
    hpv_identity_assert(hpv_identity_request('GET')->get_status() === 403, 'Ordinary Editor cannot use manager identity access');
    wp_set_current_user(0);
    hpv_identity_assert(hpv_identity_request('GET')->get_status() === 401, 'Guest cannot read manager identity endpoint');
    wp_set_current_user(username_exists('hpv_check'));
    $data = hpv_identity_request('GET')->get_data();
    hpv_identity_assert(isset($data['email']) && isset($data['timezone']), 'Administrator retains native full settings endpoint');
} finally {
    foreach ($saved as $option => $value) { update_option($option, $value); }
    set_theme_mod('custom_logo', $saved_logo);
}
