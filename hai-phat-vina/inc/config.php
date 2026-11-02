<?php
defined('ABSPATH') || exit;

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
    $defaults = hpv_defaults();
    return isset($saved[$key]) ? $saved[$key] : (isset($defaults[$key]) ? $defaults[$key] : '');
}

function hpv_phone_url() { return 'tel:' . preg_replace('/[^0-9+]/', '', hpv_get('phone')); }
function hpv_zalo_url() { return 'https://zalo.me/' . preg_replace('/\D/', '', hpv_get('zalo')); }
function hpv_asset($file) { return get_template_directory_uri() . '/assets/' . ltrim($file, '/'); }
function hpv_map_url($address) { return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address); }
function hpv_page_url($key) {
    $ids = get_option('hpv_page_ids', array());
    if (!empty($ids[$key]) && get_post_status($ids[$key]) === 'publish') { return get_permalink($ids[$key]); }
    $slugs = array('about' => 'gioi-thieu', 'contact' => 'lien-he', 'quote' => 'bao-gia', 'news' => 'kien-thuc', 'privacy' => 'chinh-sach-bao-mat');
    if (isset($slugs[$key])) {
        $page = get_page_by_path($slugs[$key]);
        if ($page && $page->post_status === 'publish') { return get_permalink($page); }
    }
    return home_url('/#' . (in_array($key, array('about', 'contact', 'quote', 'news'), true) ? $key : 'contact'));
}
function hpv_products_url() { return get_post_type_archive_link('hpv_product') ?: home_url('/?post_type=hpv_product'); }
function hpv_projects_url() { return get_post_type_archive_link('hpv_project') ?: home_url('/?post_type=hpv_project'); }

/** Small, code-native icon set. Never accepts arbitrary SVG markup. */
function hpv_icon($name, $class = '') {
    $paths = array(
        'phone' => '<path d="M8 3H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-4l-5-2-2 2a15 15 0 0 1-6-6l2-2-2-5Z"/>',
        'arrow' => '<path d="M4 12h15m-6-6 6 6-6 6"/>',
        'pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'roof' => '<path d="m2 10 10-7 10 7M5 9v12h14V9M9 21v-7h6v7"/>',
        'fabric' => '<path d="M4 4h16v16H4zM4 9h16M4 15h16M9 4v16M15 4v16"/>',
        'water' => '<path d="M12 2S5 10 5 15a7 7 0 0 0 14 0c0-5-7-13-7-13Z"/><path d="M8 15a4 4 0 0 0 4 4"/>',
        'umbrella' => '<path d="M2 12a10 10 0 0 1 20 0H2Zm10 0v7a2 2 0 0 0 4 0M12 2v2"/>',
        'measure' => '<path d="m3 17 14-14 4 4L7 21l-4-4Zm5-5 2 2m2-6 2 2m2-6 2 2"/>',
        'truck' => '<path d="M2 5h12v12H2zM14 10h4l4 4v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'chat' => '<path d="M21 11a9 9 0 0 1-9 9H3l2-5a9 9 0 1 1 16-4Z"/><path d="M8 10h8M8 14h5"/>',
        'copy' => '<rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V3H3v13h5"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
    );
    $path = isset($paths[$name]) ? $paths[$name] : $paths['arrow'];
    return '<svg class="icon ' . esc_attr($class) . '" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

function hpv_brand($use_custom_logo = true) {
    if ($use_custom_logo && has_custom_logo()) { the_custom_logo(); return; }
    echo '<a class="brand" href="' . esc_url(home_url('/')) . '" aria-label="' . esc_attr(hpv_get('brand') . ' — Trang chủ') . '">';
    echo '<svg class="brand-mark" width="54" height="48" viewBox="0 0 56 48" aria-hidden="true"><path d="m3 16 11-7v25L3 41Zm14-9 10-6v25l-10 6Zm13-6 10 6v25l-10-6Zm13 8 10 7v25l-10-7Z" fill="currentColor"/><path d="m17 35 10-6 13 7v6l-13-7-10 6Z" fill="#f1c75b"/></svg>';
    echo '<span><strong>' . esc_html(hpv_get('brand')) . '</strong><small>' . esc_html(hpv_get('tagline')) . '</small></span></a>';
}

function hpv_image($post_id, $size = 'hpv-card', $class = '', $eager = false) {
    if (has_post_thumbnail($post_id)) {
        echo get_the_post_thumbnail($post_id, $size, array('class' => $class, 'loading' => $eager ? 'eager' : 'lazy', 'decoding' => 'async'));
        return;
    }
    $key = get_post_meta($post_id, '_hpv_image', true);
    $valid = array('hero', 'tarpaulin', 'hdpe', 'umbrella', 'awning', 'construction', 'liner', 'parasol');
    // Upgrade only bundled illustration choices; uploaded featured images take precedence.
    $variants = array('mai-hien-di-dong' => array('hero', 'awning'), 'bat-che-phu-cong-trinh' => array('tarpaulin', 'construction'), 'mang-chong-tham-hdpe' => array('hdpe', 'liner'), 'du-che-san-vuon' => array('umbrella', 'parasol'));
    $slug = get_post_field('post_name', $post_id);
    if (get_post_type($post_id) === 'hpv_product' && isset($variants[$slug]) && $key === $variants[$slug][0]) { $key = $variants[$slug][1]; }
    if (!in_array($key, $valid, true)) { $key = 'hero'; }
    echo '<img class="' . esc_attr($class) . '" src="' . esc_url(hpv_asset('images/' . $key . '.webp')) . '" alt="' . esc_attr(get_the_title($post_id) . ' — ảnh minh họa') . '" width="960" height="640" loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async">';
}

function hpv_is_illustration($post_id) { return !has_post_thumbnail($post_id); }
function hpv_product_terms($post_id) {
    $terms = get_the_terms($post_id, 'hpv_category');
    return is_array($terms) ? $terms : array();
}

function hpv_breadcrumbs() {
    echo '<nav class="breadcrumbs" aria-label="Đường dẫn"><a href="' . esc_url(home_url('/')) . '">Trang chủ</a><span aria-hidden="true">/</span>';
    if (is_singular('hpv_product')) { echo '<a href="' . esc_url(hpv_products_url()) . '">Sản phẩm</a><span aria-hidden="true">/</span>'; }
    if (is_singular('hpv_project')) { echo '<a href="' . esc_url(hpv_projects_url()) . '">Ứng dụng</a><span aria-hidden="true">/</span>'; }
    $title = is_search() ? 'Kết quả tìm kiếm' : (is_404() ? 'Không tìm thấy trang' : (is_archive() ? get_the_archive_title() : get_the_title()));
    echo '<span aria-current="page">' . esc_html(wp_strip_all_tags($title)) . '</span></nav>';
}
