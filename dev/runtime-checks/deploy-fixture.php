<?php
$root = '/var/www/html/hpv-deploy-qa';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$hpv_fixture_action = isset($argv[1]) ? $argv[1] : '';
if ($hpv_fixture_action === 'install') { define('WP_INSTALLING', true); }
require $root . '/wp-load.php';
if ($wpdb->prefix !== 'hpv_deploy_qa_') { throw new RuntimeException('Isolated deployment database required.'); }
function hpv_deploy_assert($value, $message) {
    if (!$value) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
if ($hpv_fixture_action === 'install') {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    if (is_blog_installed()) { throw new RuntimeException('Use a fresh disposable fixture.'); }
    wp_install('Deploy QA', 'deploy_qa', 'deploy-qa@example.test', false, '', wp_generate_password(30));
    update_option('home', 'http://127.0.0.1/hpv-deploy-qa');
    update_option('siteurl', 'http://127.0.0.1/hpv-deploy-qa');
    update_option('permalink_structure', '/%postname%/');
    switch_theme('hai-phat-vina');
    $home = wp_insert_post(array('post_type' => 'page', 'post_title' => 'Trang chủ thật', 'post_status' => 'publish', 'post_content' => '<p>Nội dung khách đã nhập; dấu nháy \' và C:\\bạt phải giữ.</p>'));
    update_option('show_on_front', 'page'); update_option('page_on_front', $home);
    update_option('hpv_settings', array('phone' => '0912.345.678', 'recipient' => 'customer@example.test'));
    register_post_type('hpv_product', array('public' => true));
    register_post_type('hpv_request', array('public' => false));
    $product = wp_insert_post(array('post_type' => 'hpv_product', 'post_title' => 'Sản phẩm thật', 'post_status' => 'publish', 'post_content' => 'Nội dung sản phẩm thật'));
    update_option('hpv_deploy_original_product', $product);
    wp_insert_post(array('post_type' => 'hpv_request', 'post_title' => 'Yêu cầu khách thật', 'post_status' => 'private'));
    echo "Isolated legacy site ready.\n";
} elseif ($hpv_fixture_action === 'check') {
    hpv_deploy_assert(wp_is_block_theme() && defined('HPV_CORE_VERSION'), 'Release activates native block theme and business plugin');
    $content = get_post_field('post_content', get_option('page_on_front'));
    hpv_deploy_assert(has_block('core/cover', $content) && strpos($content, 'Nội dung khách đã nhập;') !== false, 'Release migrates homepage while retaining original customer copy');
    $structure = get_option('hpv_customer_structure_v1');
    $product_ids = array_map('intval', array_values(array_slice($structure['pages'], 0, 7, true)));
    $original_id = (int) get_option('hpv_deploy_original_product');
    $published_ids = get_posts(array('post_type' => 'hpv_product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids'));
    $expected_ids = array_merge(array($original_id), $product_ids);
    sort($published_ids); sort($expected_ids);
    hpv_deploy_assert($published_ids === $expected_ids && get_post_field('post_content', $original_id) === 'Nội dung sản phẩm thật' && (int) wp_count_posts('hpv_request')->private === 1, 'Release preserves original product content and private requests, adding only the seven requested products');
    foreach ($product_ids as $id) {
        hpv_deploy_assert(get_post_type($id) === 'hpv_product' && strpos(get_permalink($id), '/san-pham/') !== false, 'Requested product belongs to Products with a product URL');
    }
    hpv_deploy_assert(get_post_field('post_name', $structure['pages']['chinh-sach-bao-mat']) === 'chinh-sach-bao-mat', 'Privacy page uses the canonical slug');
    $navigation = get_post_field('post_content', $structure['navigation']);
    $footer = get_block_template(get_stylesheet() . '//footer', 'wp_template_part');
    foreach ($product_ids as $id) {
        hpv_deploy_assert(strpos($navigation, get_permalink($id)) !== false && strpos($footer->content, get_permalink($id)) !== false, 'Menu and footer point to the migrated product');
    }
    hpv_deploy_assert(hpv_get('phone') === '0912.345.678', 'Release preserves company settings');
    hpv_deploy_assert(!is_file(ABSPATH . '.maintenance'), 'Release removes its maintenance file');
    if (get_option('hpv_deploy_customer_edit')) {
        hpv_deploy_assert(strpos($content, 'Khách sửa sau bàn giao') !== false, 'Repeated deployment or rollback preserves post-handover edits');
    }
} elseif ($hpv_fixture_action === 'edit') {
    $id = get_option('page_on_front');
    wp_update_post(wp_slash(array('ID' => $id, 'post_content' => get_post_field('post_content', $id) . '<!-- wp:paragraph --><p>Khách sửa sau bàn giao; \' chữ và \\ đường dẫn.</p><!-- /wp:paragraph -->')));
    update_option('hpv_deploy_customer_edit', true);
    echo "Simulated customer edit saved.\n";
}
