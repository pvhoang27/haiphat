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
    switch_theme('hai-phat-vina');
    $home = wp_insert_post(array('post_type' => 'page', 'post_title' => 'Trang chủ thật', 'post_status' => 'publish', 'post_content' => '<p>Nội dung khách đã nhập; dấu nháy \' và C:\\bạt phải giữ.</p>'));
    update_option('show_on_front', 'page'); update_option('page_on_front', $home);
    update_option('hpv_settings', array('phone' => '0912.345.678', 'recipient' => 'customer@example.test'));
    register_post_type('hpv_product', array('public' => true));
    register_post_type('hpv_request', array('public' => false));
    wp_insert_post(array('post_type' => 'hpv_product', 'post_title' => 'Sản phẩm thật', 'post_status' => 'publish'));
    wp_insert_post(array('post_type' => 'hpv_request', 'post_title' => 'Yêu cầu khách thật', 'post_status' => 'private'));
    echo "Isolated legacy site ready.\n";
} elseif ($hpv_fixture_action === 'check') {
    hpv_deploy_assert(wp_is_block_theme() && defined('HPV_CORE_VERSION'), 'Release activates native block theme and business plugin');
    $content = get_post_field('post_content', get_option('page_on_front'));
    hpv_deploy_assert(has_block('core/cover', $content) && strpos($content, 'Nội dung khách đã nhập;') !== false, 'Release migrates homepage while retaining original customer copy');
    hpv_deploy_assert((int) wp_count_posts('hpv_product')->publish === 1 && (int) wp_count_posts('hpv_request')->private === 1, 'Release preserves products and private requests without importing demo');
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
