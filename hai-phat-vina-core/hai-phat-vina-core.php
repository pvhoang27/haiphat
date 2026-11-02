<?php
/**
 * Plugin Name: Hải Phát Vina Core
 * Description: Lưu trữ sản phẩm, công trình, thông tin công ty và yêu cầu báo giá độc lập với giao diện. Cung cấp vai trò Quản lý website để bàn giao cho người dùng.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Hải Phát Vina
 * Text Domain: hai-phat-vina-core
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;

// Older themes declare these functions without compatibility guards. Defer
// loading until the matching theme update has been installed during handover.
$hpv_core_theme = wp_get_theme(get_template());
if ($hpv_core_theme->get_template() === 'hai-phat-vina' && version_compare((string) $hpv_core_theme->get('Version'), '2.0.0', '<')) {
    add_action('admin_notices', static function () {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="notice notice-warning"><p><strong>Hải Phát Vina Core đã được cài sẵn.</strong> Cập nhật giao diện Hải Phát Vina lên phiên bản 2.0 trở lên để hoàn tất kích hoạt các chức năng quản lý mới.</p></div>';
    });
    unset($hpv_core_theme);
    return;
}
unset($hpv_core_theme);

define('HPV_CORE_VERSION', '1.0.0');
define('HPV_CORE_URL', plugin_dir_url(__FILE__));
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/site-identity.php';
require_once __DIR__ . '/includes/content.php';
require_once __DIR__ . '/includes/contact.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/blocks.php';

function hpv_core_activate() {
    hpv_setup_business_roles();
    hpv_register_content();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'hpv_core_activate');

function hpv_core_deactivate() {
    // Data, options and role assignments remain available for reactivation.
    // The bundled theme has a compatibility loader and still owns these routes.
    if (get_template() === 'hai-phat-vina') { flush_rewrite_rules(); return; }
    foreach (array('hpv_product', 'hpv_project', 'hpv_request') as $post_type) {
        if (post_type_exists($post_type)) { unregister_post_type($post_type); }
    }
    if (taxonomy_exists('hpv_category')) { unregister_taxonomy('hpv_category'); }
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'hpv_core_deactivate');
