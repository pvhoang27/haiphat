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
    $with_branches = $footer->content . '<!-- wp:paragraph --><p>Liên kết khách tự thêm.</p><!-- /wp:paragraph --><!-- wp:hpv/company-info {"layout":"branches"} /-->';
    wp_update_post(wp_slash(array('ID' => $saved['footer'], 'post_content' => $with_branches)));
    $compact = hpv_compact_customer_footer();
    if (is_wp_error($compact)) { throw new RuntimeException($compact->get_error_message()); }
    $compact_footer = get_post_field('post_content', $saved['footer']);
    hpv_structure_assert(strpos($compact_footer, 'Liên kết khách tự thêm') !== false && strpos($compact_footer, '"layout":"branches"') === false && strpos($compact_footer, 'Xem danh sách chi nhánh') !== false, 'Compact footer removes only branch list and keeps client links');
    hpv_structure_assert(get_option('hpv_compact_footer_backup_v1')['content'] === $with_branches, 'Previous footer source saved before compacting');
    hpv_structure_assert(hpv_compact_customer_footer() === true && get_post_field('post_content', $saved['footer']) === $compact_footer, 'Repeated deployments do not rewrite customer footer');
    // Simulate the previously deployed compact footer and a customer-added link.
    $without_branches = str_replace('<!-- wp:hpv/company-info {"layout":"footer-branches"} /-->', '', $compact_footer);
    wp_update_post(wp_slash(array('ID' => $saved['footer'], 'post_content' => $without_branches)));
    $restored = hpv_restore_customer_footer_branches();
    if (is_wp_error($restored)) { throw new RuntimeException($restored->get_error_message()); }
    $restored_footer = get_post_field('post_content', $saved['footer']);
    hpv_structure_assert(strpos($restored_footer, '"layout":"footer-branches"') !== false && strpos($restored_footer, 'Liên kết khách tự thêm') !== false, 'Branch list restored without overwriting other footer edits');
    hpv_structure_assert(get_option('hpv_footer_branches_backup_v1')['content'] === $without_branches, 'Footer saved before adding the branch block');
    hpv_structure_assert(hpv_restore_customer_footer_branches() === true && get_post_field('post_content', $saved['footer']) === $restored_footer, 'Repeated deployments preserve restored footer');
    $editable = $saved['pages']['bat-xay-dung'];
    wp_update_post(wp_slash(array('ID' => $editable, 'post_content' => '<!-- wp:paragraph --><p>Nội dung khách tự viết.</p><!-- /wp:paragraph -->')));
    $new_header = $header->content . '<!-- wp:paragraph --><p>Khách chỉnh đầu trang.</p><!-- /wp:paragraph -->';
    wp_update_post(wp_slash(array('ID' => $saved['header'], 'post_content' => $new_header)));
    hpv_structure_assert(hpv_setup_customer_structure() === true && strpos(get_post_field('post_content', $editable), 'Nội dung khách tự viết') !== false && get_post_field('post_content', $saved['header']) === $new_header, 'Repeated deployment preserves customer edits');

    // Footer SEO request: no H2 column titles; product links are H3; other edits kept.
    $legacy_footer = get_post_field('post_content', $saved['footer']);
    $legacy_footer = preg_replace('~<!-- wp:paragraph \{"className":"hpv-footer-title"\} -->\s*<p class="hpv-footer-title">(.*?)</p>\s*<!-- /wp:paragraph -->~s', "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$1</h2>\n<!-- /wp:heading -->", $legacy_footer);
    $legacy_footer = preg_replace('~<!-- wp:heading \{"level":3,"className":"hpv-footer-product"\} -->\s*<h3 class="wp-block-heading hpv-footer-product">(.*?)</h3>\s*<!-- /wp:heading -->~s', "<!-- wp:paragraph -->\n<p>$1</p>\n<!-- /wp:paragraph -->", $legacy_footer);
    wp_update_post(wp_slash(array('ID' => $saved['footer'], 'post_content' => $legacy_footer)));
    hpv_structure_assert(substr_count($legacy_footer, '<h2') === 4 && strpos($legacy_footer, '<h3') === false, 'Fixture reproduces the deployed footer with H2 titles');
    $headings = hpv_footer_heading_levels();
    if (is_wp_error($headings)) { throw new RuntimeException($headings->get_error_message()); }
    $footer_now = get_post_field('post_content', $saved['footer']);
    hpv_structure_assert(strpos($footer_now, '<h2') === false && substr_count($footer_now, '<p class="hpv-footer-title">') === 4, 'Footer column titles are plain text, not H2');
    hpv_structure_assert(substr_count($footer_now, '<h3 class="wp-block-heading hpv-footer-product"><a') === 7, 'Each footer product link is an H3');
    hpv_structure_assert(strpos($footer_now, '<h3 class="wp-block-heading hpv-footer-product"><a href="' . get_permalink($saved['pages']['chinh-sach-bao-mat'])) === false && strpos($footer_now, 'Liên kết khách tự thêm') !== false, 'Policy links and customer additions are not changed');
    hpv_structure_assert(get_option('hpv_footer_headings_backup_v1')['content'] === $legacy_footer && hpv_footer_heading_levels() === true && get_post_field('post_content', $saved['footer']) === $footer_now, 'Footer saved before change and not rewritten again');
    hpv_structure_assert(strpos(hpv_site_patterns()['footer'], '<h2') === false && substr_count(hpv_site_patterns()['footer'], 'hpv-footer-product') >= 7, 'Resetting the footer keeps the new heading levels');

    // Appearance → Menus drives the header and offers products.
    $extra = wp_insert_post(array('post_type' => 'hpv_product', 'post_status' => 'publish', 'post_title' => 'Bạt sọc kiểm thử', 'post_name' => 'bat-soc-kiem-thu'));
    // Production: the owner created an empty "Main Menu" for the primary location.
    $empty_menu = wp_create_nav_menu('Main Menu');
    set_theme_mod('nav_menu_locations', array_merge((array) get_theme_mod('nav_menu_locations', array()), array('primary' => $empty_menu)));
    hpv_structure_assert(strpos(do_blocks(get_block_template(get_stylesheet() . '//header', 'wp_template_part')->content), 'Bạt xây dựng') !== false, 'Header keeps its menu while Menu chính is empty');
    $seeded = hpv_seed_primary_classic_menu();
    if (is_wp_error($seeded)) { throw new RuntimeException($seeded->get_error_message()); }
    $locations = get_nav_menu_locations();
    hpv_structure_assert((int) $locations['primary'] === (int) $empty_menu, 'The owner\'s own menu is filled rather than replaced');
    $items = wp_get_nav_menu_items($locations['primary']);
    $titles = wp_list_pluck($items, 'title');
    hpv_structure_assert(in_array('Bạt sọc kiểm thử', $titles, true) && in_array('Mái xếp lượn sóng', $titles, true) && in_array('Kiến thức', $titles, true) && in_array('Liên hệ', $titles, true), 'Primary menu seeded with header links and every published product');
    $rendered = do_blocks(get_block_template(get_stylesheet() . '//header', 'wp_template_part')->content);
    hpv_structure_assert(strpos($rendered, 'Bạt sọc kiểm thử') !== false && strpos($rendered, 'wp-block-navigation-submenu') !== false, 'Header renders the Appearance → Menus menu with its product dropdown');
    wp_update_nav_menu_item($locations['primary'], 0, array('menu-item-title' => 'Mục khách tự thêm', 'menu-item-url' => home_url('/khach-them/'), 'menu-item-status' => 'publish'));
    hpv_structure_assert(strpos(do_blocks(get_block_template(get_stylesheet() . '//header', 'wp_template_part')->content), 'Mục khách tự thêm') !== false, 'Later edits in Appearance → Menus appear in the header');
    hpv_structure_assert(hpv_seed_primary_classic_menu() === true && count(wp_get_nav_menu_items($locations['primary'])) === count($items) + 1, 'Repeated deployment does not refill the customer menu');
    $hidden = apply_filters('hidden_meta_boxes', array('add-post-type-hpv_product', 'add-hpv_category', 'add-post_tag'), (object) array('id' => 'nav-menus'));
    hpv_structure_assert(!in_array('add-post-type-hpv_product', $hidden, true) && !in_array('add-hpv_category', $hidden, true), 'Products and product groups are visible in Appearance → Menus');
    hpv_structure_assert(get_permalink($extra) === home_url('/bat-soc-kiem-thu/'), 'Product URL has no /san-pham/ base');
    wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Trùng slug', 'post_name' => 'trung-slug-kiem-thu'));
    $clash = wp_insert_post(array('post_type' => 'hpv_product', 'post_status' => 'publish', 'post_title' => 'Trùng slug', 'post_name' => 'trung-slug-kiem-thu'));
    hpv_structure_assert(get_permalink($clash) === home_url('/san-pham/trung-slug-kiem-thu/'), 'A product sharing a page slug keeps its /san-pham/ URL');
} finally {
    $wpdb->query('ROLLBACK');
    wp_cache_flush();
}
