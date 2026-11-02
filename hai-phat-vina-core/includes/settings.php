<?php
/**
 * Business settings and permissions shared with the compatibility theme.
 * The option and function names deliberately match the original installation.
 */
defined('ABSPATH') || exit;
if (!function_exists('hpv_defaults')) {

function hpv_defaults() {
    return array(
        'brand' => 'Hải Phát Vina',
        'legal_name' => 'Công ty TNHH Thương Mại Dịch Vụ Hải Phát Vina',
        'tagline' => 'Mái che & bạt công trình',
        'tax_id' => '0601326528',
        'phone' => '0868.201.868',
        'zalo' => '0868201868',
        'email' => 'congtyhaiphatvina@gmail.com',
        'recipient' => 'congtyhaiphatvina@gmail.com',
        'address_1' => 'Nguyễn Trực, Phú Lãm, Hà Đông',
        'address_2' => 'Nguyễn Ảnh Thủ, quận 12, Hồ Chí Minh',
        'address_3' => 'Thuận Giao, Thành Phố Thuận An, Bình Dương',
        'address_4' => 'Lộc Hoà, Thành phố Nam Định',
        'map_embed_1' => '', 'map_embed_2' => '', 'map_embed_3' => '', 'map_embed_4' => '',
        'bank_account' => '9868201868',
        'bank_name' => 'Ngân hàng Quân đội - chi nhánh Tân Mai',
        'hero_title' => "Che nắng mưa.\nMở rộng không gian.",
        'hero_text' => 'Mái xếp, mái hiên và bạt công trình cho nhà ở, không gian kinh doanh và sản xuất. Cùng Hải Phát Vina lựa chọn giải pháp phù hợp với nhu cầu của bạn.',
        'description' => 'Hải Phát Vina — thông tin sản phẩm mái xếp, mái hiên, bạt nhựa, bạt HDPE và dù che ngoài trời. Liên hệ 0868.201.868 để trao đổi quy cách và nhận báo giá.',
        'hero_image' => 0,
        'about_image' => 0,
    );
}


function hpv_get($key) {
    $saved = get_option('hpv_settings', array());
    $saved = is_array($saved) ? $saved : array();
    $defaults = hpv_defaults();
    return isset($saved[$key]) && is_scalar($saved[$key]) ? $saved[$key] : (isset($defaults[$key]) ? $defaults[$key] : '');
}

function hpv_phone_url() { return 'tel:' . preg_replace('/[^0-9+]/', '', hpv_get('phone')); }
function hpv_zalo_url() { return 'https://zalo.me/' . preg_replace('/\D/', '', hpv_get('zalo')); }
function hpv_map_url($address) { return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address); }
/** Accept Google Maps' own embed URL, or an iframe copied from its Share dialog. */
function hpv_map_embed_url($input) {
    if (!is_string($input)) { return ''; }
    if (preg_match('~<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1~is', $input, $match)) { $input = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'); }
    $url = esc_url_raw(trim($input), array('https'));
    $parts = wp_parse_url($url);
    if (!$parts || !isset($parts['scheme'], $parts['host'], $parts['path']) || strtolower($parts['scheme']) !== 'https' || !in_array(strtolower($parts['host']), array('www.google.com', 'google.com'), true) || !preg_match('~^/maps/embed(?:/v1/(?:place|search|view))?/?$~', $parts['path']) || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && (int) $parts['port'] !== 443)) { return ''; }
    return $url;
}
function hpv_page_url($key) {
    $ids = get_option('hpv_page_ids', array());
    if (is_array($ids) && !empty($ids[$key]) && get_post_status(absint($ids[$key])) === 'publish') { return get_permalink(absint($ids[$key])); }
    $slugs = array('about' => 'gioi-thieu', 'contact' => 'lien-he', 'quote' => 'bao-gia', 'news' => 'kien-thuc', 'privacy' => 'chinh-sach-bao-mat');
    if (isset($slugs[$key])) {
        $page = get_page_by_path($slugs[$key]);
        if ($page && $page->post_status === 'publish') { return get_permalink($page); }
    }
    return home_url('/#' . (in_array($key, array('about', 'contact', 'quote', 'news'), true) ? $key : 'contact'));
}
function hpv_products_url() { return get_post_type_archive_link('hpv_product') ?: home_url('/?post_type=hpv_product'); }
function hpv_projects_url() { return get_post_type_archive_link('hpv_project') ?: home_url('/?post_type=hpv_project'); }

/** Preserve unrelated fields when a smaller settings form saves the same option. */
function hpv_sanitize_settings($input) {
    $input = is_array($input) ? $input : array();
    $result = array();
    foreach (hpv_defaults() as $key => $default) {
        $value = isset($input[$key]) && is_scalar($input[$key]) ? $input[$key] : hpv_get($key);
        if (in_array($key, array('hero_image', 'about_image'), true)) {
            $image = absint($value);
            $result[$key] = $image && wp_attachment_is_image($image) ? $image : 0;
        } elseif (in_array($key, array('email', 'recipient'), true)) {
            $result[$key] = sanitize_email($value);
        } elseif (in_array($key, array('description', 'hero_text', 'hero_title'), true)) {
            $result[$key] = sanitize_textarea_field($value);
        } elseif (strpos($key, 'map_embed_') === 0) {
            $result[$key] = hpv_map_embed_url((string) $value);
            if ($value !== '' && $result[$key] === '') { $result[$key] = hpv_get($key); add_settings_error('hpv_settings', $key, 'Bản đồ chưa hợp lệ. Hãy dùng mã nhúng từ Google Maps; hệ thống đã giữ liên kết cũ.'); }
        } elseif ($key === 'zalo') {
            $result[$key] = preg_replace('/\D/', '', $value);
        } else {
            $result[$key] = sanitize_text_field($value);
        }
    }
    if (empty($result['recipient']) || !is_email($result['recipient'])) {
        $previous = hpv_get('recipient');
        $defaults = hpv_defaults();
        $result['recipient'] = is_email($previous) ? $previous : $defaults['recipient'];
        add_settings_error('hpv_settings', 'recipient', 'Email nhận yêu cầu không hợp lệ; đã giữ lại email nhận yêu cầu trước đó.');
    }
    return $result;
}

function hpv_register_settings() {
    register_setting('hpv_settings_group', 'hpv_settings', array('type' => 'array', 'sanitize_callback' => 'hpv_sanitize_settings', 'default' => hpv_defaults(), 'show_in_rest' => false));
}
add_action('admin_init', 'hpv_register_settings');

function hpv_settings_capability() { return 'hpv_manage_business'; }
add_filter('option_page_capability_hpv_settings_group', 'hpv_settings_capability');

/**
 * Create a handover role without expanding the built-in Editor/Author roles.
 * Changes run once per role schema version, never on every front-end request.
 */
function hpv_setup_business_roles() {
    $version = '1.0.0';
    if (get_option('hpv_core_roles_version') === $version && get_role('hpv_site_manager')) { return; }
    $administrator = get_role('administrator');
    if ($administrator) { $administrator->add_cap('hpv_manage_business'); }
    $role = get_role('hpv_site_manager');
    if (!$role) {
        $editor = get_role('editor');
        $caps = $editor ? $editor->capabilities : array('read' => true, 'upload_files' => true, 'edit_posts' => true, 'edit_pages' => true, 'publish_posts' => true, 'publish_pages' => true);
        // Do not inherit operational access from a customized Editor role.
        foreach (array('manage_options', 'install_plugins', 'activate_plugins', 'edit_plugins', 'delete_plugins', 'update_plugins', 'install_themes', 'switch_themes', 'edit_themes', 'delete_themes', 'update_themes', 'edit_users', 'create_users', 'delete_users', 'list_users', 'promote_users', 'remove_users', 'update_core', 'edit_files', 'unfiltered_upload') as $cap) { unset($caps[$cap]); }
        $role = add_role('hpv_site_manager', 'Quản lý website', $caps);
    }
    if ($role) {
        $role->add_cap('edit_theme_options');
        $role->add_cap('hpv_manage_business');
    }
    if ($administrator && $role) { update_option('hpv_core_roles_version', $version, false); }
}
add_action('init', 'hpv_setup_business_roles', 5);

/** Let the handover role edit policy text without granting system settings access. */
function hpv_policy_content_capabilities($caps, $cap, $user_id, $args) {
    if (!in_array($cap, array('edit_post', 'edit_page'), true) || empty($args[0])) { return $caps; }
    $policy_id = absint(get_option('wp_page_for_privacy_policy'));
    if (!$policy_id || absint($args[0]) !== $policy_id || get_post_type($policy_id) !== 'page') { return $caps; }
    foreach ($caps as $index => $required) {
        if ($required === 'manage_options') { $caps[$index] = 'hpv_manage_business'; }
    }
    return array_values(array_unique($caps));
}
add_filter('map_meta_cap', 'hpv_policy_content_capabilities', 10, 4);
}
