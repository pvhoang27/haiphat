<?php
/** One-time setup of the customer-approved navigation; later edits belong to WordPress. */
defined('ABSPATH') || exit;

function hpv_customer_structure_pages() {
    return array(
        'bat-xay-dung' => 'Bạt xây dựng',
        'bat-xanh-cam' => 'Bạt xanh cam',
        'bat-che-nang-mua' => 'Bạt che nắng mưa',
        'bat-lot-nong-nghiep' => 'Bạt lót nông nghiệp',
        'bat-lot-ao-ho' => 'Bạt lót ao hồ',
        'luoi-cong-trinh' => 'Lưới công trình',
        'o-du-che-nang' => 'Ô dù che nắng',
        'kien-thuc' => 'Kiến thức',
        'chinh-sach-bao-mat' => 'Chính sách bảo mật',
        'chinh-sach-ban-hang' => 'Chính sách bán hàng',
        'chinh-sach-bao-hanh-doi-tra' => 'Chính sách bảo hành - đổi trả',
        'danh-sach-chi-nhanh' => 'Danh sách chi nhánh',
    );
}

function hpv_customer_nav_link($label, $url, $id = 0) {
    $attrs = array('label' => $label, 'url' => $url, 'kind' => $id ? 'post-type' : 'custom');
    if ($id) { $attrs['type'] = 'page'; $attrs['id'] = (int) $id; }
    return hpv_pattern_block('core/navigation-link', $attrs);
}

/** Keep all other header blocks; point its navigation at a new editable menu. */
function hpv_customer_replace_navigation(&$blocks, $menu_id, &$count) {
    foreach ($blocks as &$block) {
        if ($block['blockName'] === 'core/navigation') {
            $block['attrs']['ref'] = (int) $menu_id;
            $block['innerBlocks'] = array(); $block['innerHTML'] = ''; $block['innerContent'] = array();
            $count++;
        } elseif (!empty($block['innerBlocks'])) {
            hpv_customer_replace_navigation($block['innerBlocks'], $menu_id, $count);
        }
    }
    unset($block);
}

function hpv_customer_save_part($slug, $title, $content) {
    $existing = get_block_template(get_stylesheet() . '//' . $slug, 'wp_template_part');
    $post_id = $existing && !empty($existing->wp_id) ? (int) $existing->wp_id : 0;
    $data = array('post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content);
    if ($post_id) { $data['ID'] = $post_id; wp_save_post_revision($post_id); }
    $result = wp_insert_post(wp_slash($data), true);
    if (is_wp_error($result)) { return $result; }
    $terms = wp_set_object_terms($result, get_stylesheet(), 'wp_theme');
    if (is_wp_error($terms)) { return $terms; }
    $terms = wp_set_object_terms($result, $slug, 'wp_template_part_area');
    if (is_wp_error($terms)) { return $terms; }
    return $result;
}

/** The same editable footer is also the theme default after a user resets it. */
function hpv_customer_footer_markup($ids) {
    $footer_products = array();
    foreach (array_slice(hpv_customer_structure_pages(), 0, 7, true) as $slug => $title) {
        $footer_products[] = hpv_pattern_paragraph(hpv_pattern_link($title, get_permalink($ids[$slug])));
    }
    $policies = array();
    foreach (array('chinh-sach-bao-mat', 'chinh-sach-ban-hang', 'chinh-sach-bao-hanh-doi-tra') as $slug) {
        $policies[] = hpv_pattern_paragraph(hpv_pattern_link(hpv_customer_structure_pages()[$slug], get_permalink($ids[$slug])));
    }
    return hpv_pattern_group(array(
        hpv_pattern_group(array(hpv_pattern_columns(array(
            array(hpv_pattern_heading('Thông tin công ty', 2), hpv_pattern_block('hpv/company-info', array('layout' => 'footer'))),
            array(hpv_pattern_heading('Danh mục sản phẩm chính', 2), hpv_pattern_group($footer_products, 'hpv-footer-links')),
            array(hpv_pattern_heading('Chính sách', 2), hpv_pattern_group($policies, 'hpv-footer-links')),
            array(hpv_pattern_heading('Danh sách chi nhánh', 2), hpv_pattern_block('hpv/company-info', array('layout' => 'footer-branches')), hpv_pattern_paragraph(hpv_pattern_link('Xem danh sách chi nhánh', get_permalink($ids['danh-sach-chi-nhanh'])))),
        ), 'hpv-footer-grid')), '', '', array('align' => 'wide', 'layout' => array('type' => 'default'))),
        hpv_pattern_group(array(hpv_pattern_paragraph('© Hải Phát Vina.')), 'hpv-footer-bottom', '', array('align' => 'wide', 'layout' => array('type' => 'flex'))),
    ), 'hpv-site-footer', 'Chân trang', array('align' => 'full'));
}

/** Remove only the long generated branch list, preserving all other footer edits. */
function hpv_compact_footer_blocks($blocks, &$removed) {
    $result = array();
    foreach ($blocks as $block) {
        if ($block['blockName'] === 'hpv/company-info' && isset($block['attrs']['layout']) && $block['attrs']['layout'] === 'branches') { $removed++; continue; }
        if (!empty($block['innerBlocks'])) {
            $new_children = array(); $new_content = array(); $index = 0;
            foreach ($block['innerContent'] as $part) {
                if ($part !== null) { $new_content[] = $part; continue; }
                $child = $block['innerBlocks'][$index++];
                $kept = hpv_compact_footer_blocks(array($child), $removed);
                if ($kept) { $new_children[] = $kept[0]; $new_content[] = null; }
            }
            $block['innerBlocks'] = $new_children; $block['innerContent'] = $new_content;
        }
        $result[] = $block;
    }
    return $result;
}

function hpv_compact_customer_footer() {
    if (get_option('hpv_compact_footer_v1')) { return true; }
    $footer = get_block_template(get_stylesheet() . '//footer', 'wp_template_part');
    if (!$footer) { return new WP_Error('hpv_footer_missing', 'Không tìm thấy chân trang để thu gọn.'); }
    $removed = 0;
    $blocks = parse_blocks($footer->content);
    if (empty($footer->wp_id)) { $blocks = resolve_pattern_blocks($blocks); }
    $source = serialize_blocks(hpv_compact_footer_blocks($blocks, $removed));
    if ($removed) {
        update_option('hpv_compact_footer_backup_v1', array('content' => $footer->content, 'post_id' => $footer->wp_id), false);
        $saved = hpv_customer_save_part('footer', 'Chân trang', $source);
        if (is_wp_error($saved)) { return $saved; }
    }
    update_option('hpv_compact_footer_v1', 1, false);
    return true;
}

function hpv_footer_has_branches($blocks) {
    foreach ($blocks as $block) {
        if ($block['blockName'] === 'hpv/company-info' && in_array(isset($block['attrs']['layout']) ? $block['attrs']['layout'] : '', array('branches', 'footer-branches'), true)) { return true; }
        if (!empty($block['innerBlocks']) && hpv_footer_has_branches($block['innerBlocks'])) { return true; }
    }
    return false;
}

/** Insert the branch block in the existing branch column, retaining other edits. */
function hpv_add_footer_branches_to_blocks(&$blocks, &$found, &$already_present) {
    foreach ($blocks as &$block) {
        if ($block['blockName'] === 'core/column' && strpos(serialize_block($block), 'Xem danh sách chi nhánh') !== false) {
            $found++;
            if (!hpv_footer_has_branches($block['innerBlocks'])) {
                $block['innerBlocks'][] = parse_blocks(hpv_pattern_block('hpv/company-info', array('layout' => 'footer-branches')))[0];
                $last = count($block['innerContent']) - 1;
                if ($last < 0 || !is_string($block['innerContent'][$last]) || strpos($block['innerContent'][$last], '</div>') === false) { return new WP_Error('hpv_branch_column', 'Không thể chèn danh sách chi nhánh an toàn.'); }
                array_splice($block['innerContent'], $last, 0, array(null));
            } else { $already_present = true; }
        } elseif (!empty($block['innerBlocks'])) {
            $result = hpv_add_footer_branches_to_blocks($block['innerBlocks'], $found, $already_present);
            if (is_wp_error($result)) { return $result; }
        }
    }
    unset($block);
    return true;
}

function hpv_restore_customer_footer_branches() {
    if (get_option('hpv_footer_branches_v1')) { return true; }
    $footer = get_block_template(get_stylesheet() . '//footer', 'wp_template_part');
    if (!$footer) { return new WP_Error('hpv_footer_missing', 'Không tìm thấy chân trang.'); }
    $blocks = parse_blocks($footer->content);
    if (empty($footer->wp_id)) { $blocks = resolve_pattern_blocks($blocks); }
    $found = 0; $already_present = false;
    $result = hpv_add_footer_branches_to_blocks($blocks, $found, $already_present);
    if (is_wp_error($result)) { return $result; }
    if ($found !== 1) { return new WP_Error('hpv_branch_column', 'Không tìm thấy đúng một cột danh sách chi nhánh; cần kiểm tra chân trang.'); }
    if (!$already_present) {
        update_option('hpv_footer_branches_backup_v1', array('content' => $footer->content, 'post_id' => $footer->wp_id), false);
        $saved = hpv_customer_save_part('footer', 'Chân trang', serialize_blocks($blocks));
        if (is_wp_error($saved)) { return $saved; }
    }
    update_option('hpv_footer_branches_v1', 1, false);
    return true;
}

function hpv_setup_customer_structure() {
    if (get_option('hpv_customer_structure_v1')) { return true; }
    if (!wp_is_block_theme() || !function_exists('hpv_pattern_columns')) { return new WP_Error('hpv_structure_theme', 'Cần kích hoạt giao diện block Hải Phát Vina.'); }

    $header = get_block_template(get_stylesheet() . '//header', 'wp_template_part');
    $footer = get_block_template(get_stylesheet() . '//footer', 'wp_template_part');
    if (!$header || !$footer) { return new WP_Error('hpv_structure_parts', 'Không tìm thấy đầu trang hoặc chân trang hiện tại.'); }
    // Preserve the exact pre-change sources and old menu separately for recovery.
    if (!get_option('hpv_customer_structure_backup_v1')) {
        update_option('hpv_customer_structure_backup_v1', array('header' => $header->content, 'footer' => $footer->content, 'header_id' => $header->wp_id, 'footer_id' => $footer->wp_id, 'navigation_id' => get_option('hpv_navigation_id')), false);
    }
    $ids = array();
    foreach (hpv_customer_structure_pages() as $slug => $title) {
        $page = get_page_by_path($slug, OBJECT, 'page');
        if ($page && $page->post_status === 'publish' && $page->post_password === '') {
            $ids[$slug] = (int) $page->ID;
            continue;
        }
        // A draft/private/password-protected page may contain the customer's
        // unfinished copy. Leave it untouched and create a separate blank page.
        $new_slug = $page ? $slug . '-trang-moi' : $slug;
        $id = wp_insert_post(wp_slash(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $new_slug, 'post_content' => '')), true);
        if (is_wp_error($id)) { return $id; }
        $ids[$slug] = (int) $id;
    }
    $products = array_slice(hpv_customer_structure_pages(), 0, 7, true);
    $children = array();
    foreach ($products as $slug => $title) {
        $url = get_permalink($ids[$slug]);
        $children[] = hpv_customer_nav_link($title, $url, $ids[$slug]);
    }
    $items = array(
        hpv_customer_nav_link('Trang chủ', home_url('/')),
        hpv_customer_nav_link('Giới thiệu', hpv_page_url('about')),
        hpv_pattern_block('core/navigation-submenu', array('label' => 'Sản phẩm', 'url' => hpv_products_url(), 'kind' => 'custom'), $children),
        hpv_customer_nav_link('Kiến thức', get_permalink($ids['kien-thuc']), $ids['kien-thuc']),
        hpv_customer_nav_link('Liên hệ', hpv_page_url('contact')),
    );
    $menu_id = wp_insert_post(wp_slash(array('post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Menu chính Hải Phát Vina', 'post_content' => implode("\n", $items))), true);
    if (is_wp_error($menu_id)) { return $menu_id; }
    $header_blocks = resolve_pattern_blocks(parse_blocks($header->content));
    $nav_count = 0;
    hpv_customer_replace_navigation($header_blocks, $menu_id, $nav_count);
    if ($nav_count !== 1) { return new WP_Error('hpv_structure_header', 'Không xác định được một menu chính trong đầu trang; giữ lại bản cũ để kiểm tra.'); }
    $saved_header = hpv_customer_save_part('header', 'Đầu trang', serialize_blocks($header_blocks));
    if (is_wp_error($saved_header)) { return $saved_header; }

    $footer_content = hpv_customer_footer_markup($ids);
    $saved_footer = hpv_customer_save_part('footer', 'Chân trang', $footer_content);
    if (is_wp_error($saved_footer)) { return $saved_footer; }
    update_option('hpv_navigation_id', (int) $menu_id, false);
    update_option('hpv_customer_structure_v1', array('pages' => $ids, 'navigation' => (int) $menu_id, 'header' => (int) $saved_header, 'footer' => (int) $saved_footer), false);
    return true;
}
