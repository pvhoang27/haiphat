<?php
/** Explicit, recoverable upgrade from the original PHP theme. */
defined('ABSPATH') || exit;

function hpv_site_editor_url($part = '') {
    $args = $part ? array('postType' => 'wp_template_part', 'postId' => get_stylesheet() . '//' . $part, 'canvas' => 'edit') : array();
    return add_query_arg($args, admin_url('site-editor.php'));
}

function hpv_hide_legacy_page_templates($templates) {
    if (wp_is_block_theme()) {
        foreach (array('page-about.php', 'page-contact.php', 'page-quote.php') as $legacy) { unset($templates[$legacy]); }
    }
    return $templates;
}
add_filter('theme_page_templates', 'hpv_hide_legacy_page_templates');

/** Convert a classic menu without changing the original menu or its order. */
function hpv_migrate_navigation($location = 'primary') {
    $option = $location === 'footer' ? 'hpv_footer_navigation_id' : 'hpv_navigation_id';
    $existing = absint(get_option($option));
    if ($existing && get_post_type($existing) === 'wp_navigation' && get_post_status($existing) === 'publish') { return $existing; }
    $locations = get_theme_mod('nav_menu_locations', array());
    if (empty($locations[$location])) { return 0; }
    $menu = wp_get_nav_menu_object($locations[$location]);
    if (!$menu || !class_exists('WP_Classic_To_Block_Menu_Converter')) { return 0; }
    $content = WP_Classic_To_Block_Menu_Converter::convert($menu);
    if (is_wp_error($content) || !$content) { return 0; }
    $id = wp_insert_post(wp_slash(array('post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => $menu->name, 'post_content' => $content)), true);
    if (is_wp_error($id)) { return $id; }
    update_option($option, $id, false);
    return $id;
}

/** Put bundled illustration media into Media Library for editable core blocks. */
function hpv_import_block_media() {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    if (!has_custom_logo()) {
        $logo_id = absint(get_option('hpv_brand_logo_id'));
        if (!$logo_id || get_post_type($logo_id) !== 'attachment') {
            $logo_file = get_template_directory() . '/assets/brand/hai-phat-vina-icon-512.png';
            if (is_readable($logo_file)) {
                $upload = wp_upload_bits('hai-phat-vina-logo.png', null, file_get_contents($logo_file));
                if (empty($upload['error'])) {
                    $logo_id = wp_insert_attachment(array('post_title' => 'Logo Hải Phát Vina', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'), $upload['file'], 0, true);
                    if (!is_wp_error($logo_id)) {
                        update_post_meta($logo_id, '_wp_attachment_image_alt', hpv_get('brand'));
                        wp_update_attachment_metadata($logo_id, wp_generate_attachment_metadata($logo_id, $upload['file']));
                        update_option('hpv_brand_logo_id', $logo_id, false);
                    }
                }
            }
        }
        if ($logo_id && !is_wp_error($logo_id) && get_post_type($logo_id) === 'attachment') { set_theme_mod('custom_logo', $logo_id); }
    }
    $assets = get_option('hpv_block_media', array());
    foreach (array('hero', 'tarpaulin', 'hdpe', 'awning', 'construction', 'umbrella', 'parasol', 'liner') as $key) {
        if (!empty($assets[$key]) && get_post_type($assets[$key]) === 'attachment') { continue; }
        $source = get_template_directory() . '/assets/images/' . $key . '.webp';
        if (!is_readable($source)) { continue; }
        $file = wp_upload_bits('hai-phat-minh-hoa-' . $key . '.webp', null, file_get_contents($source));
        if (!empty($file['error'])) { return new WP_Error('hpv_media_import', 'Không thể nhập ảnh minh họa vào Thư viện. Kiểm tra quyền ghi thư mục uploads: ' . $file['error']); }
        $id = wp_insert_attachment(array('post_title' => 'Ảnh minh họa Hải Phát — ' . $key, 'post_mime_type' => 'image/webp', 'post_status' => 'inherit', 'post_excerpt' => 'Ảnh AI minh họa, chưa phải ảnh công trình thực tế.'), $file['file'], 0, true);
        if (is_wp_error($id)) { return $id; }
        update_post_meta($id, '_wp_attachment_image_alt', 'Ảnh minh họa mái che và vật liệu bạt Hải Phát Vina');
        update_post_meta($id, '_hpv_illustration', '1');
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file['file']));
        $assets[$key] = $id;
        update_option('hpv_block_media', $assets, false);
    }
    $posts = get_posts(array('post_type' => array('hpv_product', 'hpv_project', 'post'), 'numberposts' => -1, 'post_status' => array('publish', 'draft', 'pending', 'private'), 'meta_key' => '_hpv_image'));
    foreach ($posts as $post) {
        $key = get_post_meta($post->ID, '_hpv_image', true);
        if (!has_post_thumbnail($post->ID) && !empty($assets[$key])) { set_post_thumbnail($post->ID, $assets[$key]); }
    }
    return $assets;
}

/** Keep existing text intact; legacy HTML becomes a supported Classic block. */
function hpv_preserve_page_content($content) {
    if (!trim($content)) { return ''; }
    return has_blocks($content) ? $content : '<!-- wp:freeform -->' . $content . '<!-- /wp:freeform -->';
}

function hpv_prepare_block_site($page_ids = array()) {
    if (!defined('HPV_CORE_VERSION')) { return new WP_Error('hpv_core_required', 'Hãy cài và kích hoạt plugin Hải Phát Vina Core trước khi chuyển giao diện.'); }
    if (!function_exists('hpv_site_patterns')) { return new WP_Error('hpv_patterns_missing', 'Chưa tìm thấy bộ mẫu trang. Hãy tải lại đầy đủ gói giao diện.'); }
    $page_ids = $page_ids ?: get_option('hpv_page_ids', array());
    $page_ids = is_array($page_ids) ? $page_ids : array();
    foreach (array('home' => 'trang-chu', 'about' => 'gioi-thieu', 'contact' => 'lien-he', 'quote' => 'bao-gia') as $key => $slug) {
        if (!empty($page_ids[$key]) && get_post_type(absint($page_ids[$key])) === 'page') { continue; }
        $existing_page = get_page_by_path($slug);
        if ($existing_page) { $page_ids[$key] = $existing_page->ID; }
    }
    // The front page may have been selected manually after initial installation.
    $front = absint(get_option('page_on_front'));
    if ($front && get_post_type($front) === 'page') { $page_ids['home'] = $front; }
    $initial_reading = array('page_on_front' => $front, 'page_for_posts' => get_option('page_for_posts'), 'show_on_front' => get_option('show_on_front'));
    $created_home = 0;
    if (empty($page_ids['home'])) {
        // The old PHP homepage also worked before a static Page was assigned.
        $created_home = wp_insert_post(array('post_type' => 'page', 'post_title' => 'Trang chủ', 'post_name' => 'trang-chu', 'post_status' => 'publish'), true);
        if (is_wp_error($created_home)) { return $created_home; }
        $page_ids['home'] = $created_home;
    }
    $legacy_templates = array('page-about.php' => 'about', 'page-contact.php' => 'contact', 'page-quote.php' => 'quote');
    $targets = array();
    foreach (array('home', 'about', 'contact', 'quote') as $key) {
        if (!empty($page_ids[$key]) && !isset($targets[absint($page_ids[$key])])) { $targets[absint($page_ids[$key])] = $key; }
    }
    // Editors can create additional pages using legacy templates outside the seed map.
    $legacy_pages = get_posts(array('post_type' => 'page', 'post_status' => array('publish', 'private', 'draft', 'pending', 'future'), 'numberposts' => -1, 'meta_query' => array(array('key' => '_wp_page_template', 'value' => array_keys($legacy_templates), 'compare' => 'IN'))));
    foreach ($legacy_pages as $legacy_page) {
        if (!isset($targets[$legacy_page->ID])) { $targets[$legacy_page->ID] = $legacy_templates[get_page_template_slug($legacy_page->ID)]; }
        $page_ids['legacy_' . $legacy_page->ID] = $legacy_page->ID;
    }
    $backup = get_option('hpv_block_migration_backup', array());
    if (!$backup) {
        $backup = array_merge(array('created' => current_time('mysql'), 'theme_version' => HPV_VERSION, 'pages' => array(), 'settings' => get_option('hpv_settings', array()), 'theme_mods' => get_theme_mods()), $initial_reading);
    }
    if ($created_home) { $backup['created_pages'][] = $created_home; }
    foreach ($page_ids as $key => $id) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'page' || isset($backup['pages'][$id])) { continue; }
        $backup['pages'][$id] = array('ID' => $id, 'title' => $post->post_title, 'content' => $post->post_content, 'template' => get_post_meta($id, '_wp_page_template', true));
    }
    update_option('hpv_block_migration_backup', $backup, false);
    $media = hpv_import_block_media();
    if (is_wp_error($media)) { return $media; }
    foreach (array('primary', 'footer') as $location) {
        $navigation = hpv_migrate_navigation($location);
        if (is_wp_error($navigation)) { return $navigation; }
    }
    $patterns = hpv_site_patterns();
    $pattern_names = array('home' => 'home', 'about' => 'about-page', 'contact' => 'contact-page', 'quote' => 'quote-page');
    foreach ($targets as $id => $key) {
        $pattern = $pattern_names[$key];
        if (!isset($patterns[$pattern])) { continue; }
        $post = get_post($id);
        if (!$post || $post->post_type !== 'page' || get_post_meta($id, '_hpv_block_migrated', true)) { continue; }
        $old = hpv_preserve_page_content($post->post_content);
        $legacy_template = get_page_template_slug($id);
        $uses_legacy_template = isset($legacy_templates[$legacy_template]);
        // A legacy contact/quote template supplied its form/catalog outside the
        // saved content, even when that content already contained Gutenberg blocks.
        // Preserve independently built block pages; rebuild only legacy-owned parts.
        $has_functional_content = ($key === 'contact' && has_block('hpv/quote-form', $post->post_content)) || ($key === 'quote' && has_block('core/query', $post->post_content));
        if (has_blocks($post->post_content) && (!$uses_legacy_template || $has_functional_content)) {
            update_post_meta($id, '_hpv_block_migrated', HPV_VERSION);
            if ($uses_legacy_template) { update_post_meta($id, '_wp_page_template', 'page-full-width'); }
            continue;
        }
        wp_save_post_revision($id);
        if ($key === 'about' && $old) {
            // Preserve the company introduction, then supply editable supporting sections.
            $content = $old . $patterns['about-section'] . $patterns['contact-section'];
        } else {
            $content = $patterns[$pattern] . $old;
        }
        // Supply the replacement template in the same update: legacy PHP
        // templates are intentionally no longer offered by the block theme.
        $result = wp_update_post(wp_slash(array('ID' => $id, 'post_content' => $content, 'page_template' => $key === 'home' ? 'default' : 'page-full-width')), true);
        if (is_wp_error($result)) { return $result; }
        update_post_meta($id, '_hpv_block_migrated', HPV_VERSION);
        if ($key !== 'home') { update_post_meta($id, '_wp_page_template', 'page-full-width'); }
    }
    if (!$front && !empty($page_ids['home'])) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', absint($page_ids['home']));
    }
    $saved_page_ids = get_option('hpv_page_ids', array());
    $saved_page_ids = is_array($saved_page_ids) ? $saved_page_ids : array();
    foreach (array('home', 'about', 'contact', 'quote') as $key) { if (!empty($page_ids[$key])) { $saved_page_ids[$key] = $page_ids[$key]; } }
    update_option('hpv_page_ids', $saved_page_ids, false);
    update_option('hpv_block_setup_complete', HPV_VERSION, false);
    flush_rewrite_rules();
    return $page_ids;
}

function hpv_handle_block_upgrade() {
    if (!current_user_can('manage_options')) { wp_die('Bạn không có quyền chuyển đổi giao diện.', '', array('response' => 403)); }
    check_admin_referer('hpv_block_upgrade');
    $result = hpv_prepare_block_site();
    if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), 'Chưa chuyển đổi được', array('back_link' => true)); }
    wp_safe_redirect(admin_url('admin.php?page=hpv-settings&hpv_setup=done'));
    exit;
}
add_action('admin_post_hpv_block_upgrade', 'hpv_handle_block_upgrade');

function hpv_download_migration_backup() {
    if (!current_user_can('manage_options')) { wp_die('Bạn không có quyền tải bản gốc.', '', array('response' => 403)); }
    check_admin_referer('hpv_download_backup');
    $backup = get_option('hpv_block_migration_backup');
    if (!$backup) { wp_die('Chưa có bản lưu chuyển đổi.'); }
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="hai-phat-noi-dung-truoc-chuyen-doi.json"');
    echo wp_json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
add_action('admin_post_hpv_download_backup', 'hpv_download_migration_backup');

function hpv_illustration_block_caption($content, $block, $instance) {
    $id = isset($instance->context['postId']) ? absint($instance->context['postId']) : get_the_ID();
    $thumbnail = get_post_thumbnail_id($id);
    if ($thumbnail && get_post_meta($thumbnail, '_hpv_illustration', true) === '1' && strpos($content, '</figure>') !== false) {
        $content = str_replace('</figure>', '<figcaption class="hpv-illustration-note">Ảnh minh họa</figcaption></figure>', $content);
    }
    return $content;
}
add_filter('render_block_core/post-featured-image', 'hpv_illustration_block_caption', 10, 3);
