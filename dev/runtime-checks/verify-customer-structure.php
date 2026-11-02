<?php
$_SERVER['HTTP_HOST'] = '127.0.0.1:8787';
$_SERVER['REQUEST_METHOD'] = 'GET';
require '/var/www/html/wp-load.php';
if (get_option('home') !== 'http://127.0.0.1:8787' || get_option('hpv_customer_structure_v1')) { throw new RuntimeException('Fresh disposable QA site required.'); }
function hpv_structure_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
$admin = get_user_by('login', 'hpv_check');
wp_set_current_user($admin->ID);
$wpdb->query('START TRANSACTION');
try {
    $draft_id = wp_insert_post(wp_slash(array('post_type' => 'page', 'post_status' => 'draft', 'post_name' => 'bat-xay-dung', 'post_title' => 'Bản nháp riêng', 'post_content' => 'Nội dung riêng đang soạn.')), true);
    if (is_wp_error($draft_id)) { throw new RuntimeException($draft_id->get_error_message()); }
    $before_pages = array();
    foreach (hpv_customer_structure_pages() as $slug => $title) {
        $page = get_page_by_path($slug);
        if ($page && $page->post_status === 'publish' && $page->post_password === '') { $before_pages[$slug] = array($page->ID, $page->post_content); }
    }
    $front_id = (int) get_option('page_on_front');
    $front_content = get_post_field('post_content', $front_id);
    $previous_header = get_block_template(get_stylesheet() . '//header', 'wp_template_part');
    $custom_header = serialize_blocks(resolve_pattern_blocks(parse_blocks($previous_header->content))) . '<!-- wp:paragraph --><p>Dấu đầu trang khách đã sửa.</p><!-- /wp:paragraph -->';
    $custom_id = hpv_customer_save_part('header', 'Đầu trang', $custom_header);
    if (is_wp_error($custom_id)) { throw new RuntimeException($custom_id->get_error_message()); }
    $previous_header = get_block_template(get_stylesheet() . '//header', 'wp_template_part');
    $result = hpv_setup_customer_structure();
    if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
    $saved = get_option('hpv_customer_structure_v1');
    foreach (hpv_customer_structure_pages() as $slug => $title) {
        $page = get_post($saved['pages'][$slug]);
        hpv_structure_assert($page && $page->post_status === 'publish' && ($before_pages[$slug][1] ?? '') === $page->post_content, 'Existing page preserved or newly created blank: ' . $slug);
    }
    hpv_structure_assert(get_post_field('post_content', $front_id) === $front_content, 'Existing homepage remains unchanged');
    hpv_structure_assert(get_post_status($draft_id) === 'draft' && get_post_field('post_content', $draft_id) === 'Nội dung riêng đang soạn.' && (int) $saved['pages']['bat-xay-dung'] !== (int) $draft_id, 'Existing draft stays private and a separate blank page is created');
    hpv_structure_assert(get_option('hpv_customer_structure_backup_v1')['header'] === $previous_header->content, 'Original header stored for recovery');
    $menu = get_post($saved['navigation']);
    foreach (array_slice(hpv_customer_structure_pages(), 0, 7, true) as $slug => $title) {
        hpv_structure_assert(strpos($menu->post_content, $title) !== false && strpos($menu->post_content, get_permalink($saved['pages'][$slug])) !== false, 'Product submenu links to page: ' . $title);
    }
    $header = get_block_template(get_stylesheet() . '//header', 'wp_template_part');
    $footer = get_block_template(get_stylesheet() . '//footer', 'wp_template_part');
    hpv_structure_assert(has_block('core/navigation', $header->content) && strpos($header->content, '"ref":' . $saved['navigation']) !== false, 'Header uses editable native menu');
    hpv_structure_assert(strpos($header->content, 'Dấu đầu trang khách đã sửa') !== false, 'Existing header blocks preserved around the menu');
    $rendered_header = do_blocks($header->content);
    foreach (array_slice(hpv_customer_structure_pages(), 0, 7, true) as $slug => $title) {
        hpv_structure_assert(strpos($rendered_header, $title) !== false, 'Public header renders submenu item: ' . $title);
    }
    foreach (array('Thông tin công ty', 'Danh mục sản phẩm chính', 'Chính sách bảo mật', 'Chính sách bán hàng', 'Chính sách bảo hành - đổi trả', 'Danh sách chi nhánh') as $text) {
        hpv_structure_assert(strpos($footer->content, $text) !== false, 'Footer contains requested section: ' . $text);
    }
    hpv_structure_assert(strpos(hpv_site_patterns()['footer'], 'Danh mục sản phẩm chính') !== false, 'Resetting footer restores requested default structure');
    $editable = $saved['pages']['bat-xay-dung'];
    wp_update_post(wp_slash(array('ID' => $editable, 'post_content' => '<!-- wp:paragraph --><p>Nội dung khách tự viết.</p><!-- /wp:paragraph -->')));
    $new_header = $header->content . '<!-- wp:paragraph --><p>Khách chỉnh đầu trang.</p><!-- /wp:paragraph -->';
    wp_update_post(wp_slash(array('ID' => $saved['header'], 'post_content' => $new_header)));
    hpv_structure_assert(hpv_setup_customer_structure() === true && strpos(get_post_field('post_content', $editable), 'Nội dung khách tự viết') !== false && get_post_field('post_content', $saved['header']) === $new_header, 'Repeated deployment preserves customer edits');
} finally {
    $wpdb->query('ROLLBACK');
    wp_cache_flush();
}
