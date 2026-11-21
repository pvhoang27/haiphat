<?php
/**
 * The header menu is managed in Appearance → Menus.
 *
 * The site owner works in the classic Menus screen. When a menu with items is
 * assigned to the "Menu chính" location, the header Navigation block renders
 * that menu; otherwise it keeps its saved block navigation.
 */
defined('ABSPATH') || exit;

/** Products and product groups are always offered in Appearance → Menus. */
function hpv_show_product_menu_boxes($hidden, $screen) {
    if (!$screen || $screen->id !== 'nav-menus' || !is_array($hidden)) { return $hidden; }
    return array_values(array_diff($hidden, array('add-post-type-hpv_product', 'add-hpv_category')));
}
add_filter('hidden_meta_boxes', 'hpv_show_product_menu_boxes', 10, 2);

function hpv_primary_classic_menu() {
    $locations = get_nav_menu_locations();
    if (empty($locations['primary'])) { return null; }
    $menu = wp_get_nav_menu_object($locations['primary']);
    return $menu ? $menu : null;
}

function hpv_classic_menu_blocks($menu) {
    if (!$menu || !class_exists('WP_Classic_To_Block_Menu_Converter')) { return array(); }
    $markup = WP_Classic_To_Block_Menu_Converter::convert($menu);
    if (!is_string($markup) || trim($markup) === '') { return array(); }
    return array_values(array_filter(parse_blocks($markup), static function ($block) { return !empty($block['blockName']); }));
}

/** Swap the header menu's saved items for the classic "Menu chính" items at render time. */
function hpv_render_primary_classic_menu($parsed_block) {
    if (!isset($parsed_block['blockName']) || $parsed_block['blockName'] !== 'core/navigation' || empty($parsed_block['attrs']['ref'])) { return $parsed_block; }
    // Until the deployment has prepared "Menu chính", the saved block menu stays.
    if (!get_option('hpv_classic_menu_v1')) { return $parsed_block; }
    $navigation_id = absint(get_option('hpv_navigation_id', 0));
    if (!$navigation_id || (int) $parsed_block['attrs']['ref'] !== $navigation_id) { return $parsed_block; }
    $inner = hpv_classic_menu_blocks(hpv_primary_classic_menu());
    if (!$inner) { return $parsed_block; }
    unset($parsed_block['attrs']['ref']);
    $parsed_block['innerBlocks'] = $inner;
    $parsed_block['innerHTML'] = '';
    $parsed_block['innerContent'] = array_fill(0, count($inner), null);
    return $parsed_block;
}
add_filter('render_block_data', 'hpv_render_primary_classic_menu');

function hpv_add_classic_menu_item($menu_id, $data, $parent = 0) {
    $data['menu-item-status'] = 'publish';
    $data['menu-item-parent-id'] = (int) $parent;
    return wp_update_nav_menu_item($menu_id, 0, $data);
}

/** Copy one saved navigation block (and its children) into the classic menu. */
function hpv_copy_navigation_block_to_menu($menu_id, $block, $parent, $products) {
    $attrs = $block['attrs'];
    $label = isset($attrs['label']) ? wp_strip_all_tags($attrs['label']) : '';
    $url = isset($attrs['url']) ? $attrs['url'] : '';
    if (!empty($attrs['id']) && isset($attrs['kind']) && $attrs['kind'] === 'post-type' && get_post_status((int) $attrs['id']) === 'publish') {
        $data = array('menu-item-type' => 'post_type', 'menu-item-object' => get_post_type((int) $attrs['id']), 'menu-item-object-id' => (int) $attrs['id'], 'menu-item-title' => $label);
    } elseif (!empty($attrs['id']) && isset($attrs['kind'], $attrs['type']) && $attrs['kind'] === 'taxonomy' && term_exists((int) $attrs['id'], $attrs['type'])) {
        $data = array('menu-item-type' => 'taxonomy', 'menu-item-object' => $attrs['type'], 'menu-item-object-id' => (int) $attrs['id'], 'menu-item-title' => $label);
    } else {
        if ($label === '' || $url === '') { return 0; }
        $data = array('menu-item-type' => 'custom', 'menu-item-url' => $url, 'menu-item-title' => $label);
    }
    $item_id = hpv_add_classic_menu_item($menu_id, $data, $parent);
    if (is_wp_error($item_id)) { return $item_id; }
    if ($block['blockName'] !== 'core/navigation-submenu') { return $item_id; }
    // The product dropdown lists every published product; other submenus keep their items.
    if ($label === 'Sản phẩm') {
        foreach ($products as $product) {
            $child = hpv_add_classic_menu_item($menu_id, array('menu-item-type' => 'post_type', 'menu-item-object' => 'hpv_product', 'menu-item-object-id' => $product->ID), $item_id);
            if (is_wp_error($child)) { return $child; }
        }
        return $item_id;
    }
    foreach ($block['innerBlocks'] as $child_block) {
        $child = hpv_copy_navigation_block_to_menu($menu_id, $child_block, $item_id, $products);
        if (is_wp_error($child)) { return $child; }
    }
    return $item_id;
}

/**
 * Fill "Menu chính" once from the current header menu, listing all published
 * products under "Sản phẩm". A classic menu that already has items is left alone.
 */
function hpv_seed_primary_classic_menu() {
    if (get_option('hpv_classic_menu_v1')) { return true; }
    $menu = hpv_primary_classic_menu();
    if ($menu && wp_get_nav_menu_items($menu->term_id)) {
        update_option('hpv_classic_menu_v1', array('menu' => (int) $menu->term_id, 'seeded' => false), false);
        return true;
    }
    $navigation = get_post(absint(get_option('hpv_navigation_id', 0)));
    if (!$navigation || $navigation->post_type !== 'wp_navigation') { return new WP_Error('hpv_navigation_missing', 'Không tìm thấy menu đầu trang để chuyển sang Giao diện → Menu.'); }
    $menu_id = $menu ? (int) $menu->term_id : wp_create_nav_menu('Menu chính');
    if (is_wp_error($menu_id)) { return $menu_id; }
    $products = get_posts(array('post_type' => 'hpv_product', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => array('menu_order' => 'ASC', 'date' => 'ASC')));
    foreach (parse_blocks($navigation->post_content) as $block) {
        if (!in_array($block['blockName'], array('core/navigation-link', 'core/navigation-submenu'), true)) { continue; }
        $item = hpv_copy_navigation_block_to_menu($menu_id, $block, 0, $products);
        if (is_wp_error($item)) { return $item; }
    }
    $locations = get_theme_mod('nav_menu_locations', array());
    $locations['primary'] = (int) $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
    update_option('hpv_classic_menu_v1', array('menu' => (int) $menu_id, 'seeded' => true), false);
    return true;
}
