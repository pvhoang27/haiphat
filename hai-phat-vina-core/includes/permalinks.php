<?php
/**
 * Product URLs live at the site root: /bat-xay-dung/ instead of /san-pham/bat-xay-dung/.
 *
 * Rank Math can only strip the base of WordPress/WooCommerce categories, not of
 * a custom post type, so the base is removed here. The /san-pham/ archive stays,
 * and old product URLs answer with a permanent redirect.
 */
defined('ABSPATH') || exit;

const HPV_PRODUCT_BASE = 'san-pham';

function hpv_product_root_link($url, $post, $leavename = false) {
    if (!$post || $post->post_type !== 'hpv_product') { return $url; }
    // Only rewrite pretty URLs; drafts and plain permalinks keep their query form.
    $base = home_url('/' . HPV_PRODUCT_BASE . '/');
    if (strpos($url, $base) !== 0) { return $url; }
    // A page or post already owns /{slug}/: keep the product under /san-pham/.
    if (!$leavename && hpv_root_slug_taken($post->post_name)) { return $url; }
    return home_url(user_trailingslashit($leavename ? '%hpv_product%' : $post->post_name));
}
add_filter('post_type_link', 'hpv_product_root_link', 10, 3);

function hpv_root_slug_taken($slug) {
    foreach (array('page', 'post') as $type) {
        $existing = get_page_by_path($slug, OBJECT, $type);
        if ($existing && $existing->post_status === 'publish') { return true; }
    }
    return false;
}

/** A published page or post with the same slug keeps its URL; products only fill gaps. */
function hpv_product_root_request($wp) {
    $path = trim((string) $wp->request, '/');
    if ($path === '' || strpos($path, '/') !== false || !empty($wp->query_vars['post_type'])) { return; }
    $resolved = '';
    foreach (array('pagename', 'name', 'attachment', 'category_name') as $var) {
        if (!empty($wp->query_vars[$var])) { $resolved = (string) $wp->query_vars[$var]; break; }
    }
    if ($resolved === '' || urldecode($resolved) !== urldecode($path)) { return; }
    if (hpv_root_slug_taken($path)) { return; }
    $product = get_page_by_path($path, OBJECT, 'hpv_product');
    if (!$product || $product->post_status !== 'publish') { return; }
    $vars = array('post_type' => 'hpv_product', 'hpv_product' => $product->post_name, 'name' => $product->post_name);
    foreach (array('page', 'cpage', 'preview') as $keep) {
        if (isset($wp->query_vars[$keep]) && $wp->query_vars[$keep] !== '') { $vars[$keep] = $wp->query_vars[$keep]; }
    }
    $wp->query_vars = $vars;
    $wp->matched_query = http_build_query($vars);
}
add_action('parse_request', 'hpv_product_root_request', 5);

/** Old /san-pham/{slug}/ links (bookmarks, search results, saved menus) move permanently. */
function hpv_redirect_legacy_product_url() {
    global $wp;
    if (!is_singular('hpv_product') || is_preview() || !get_option('permalink_structure')) { return; }
    if (strpos(trim((string) $wp->request, '/') . '/', HPV_PRODUCT_BASE . '/') !== 0) { return; }
    $target = get_permalink(get_queried_object_id());
    if (!$target || strpos($target, '/' . HPV_PRODUCT_BASE . '/') !== false) { return; }
    if (!empty($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING'])) {
        $target .= '?' . sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING']));
    }
    wp_safe_redirect($target, 301, 'Hai Phat Vina');
    exit;
}
add_action('template_redirect', 'hpv_redirect_legacy_product_url', 1);

/** Rewrite saved menus and header/footer links once so visitors skip the redirect. */
function hpv_migrate_product_root_urls() {
    if (get_option('hpv_product_root_urls_v1')) { return true; }
    $replacements = array();
    $products = get_posts(array('post_type' => 'hpv_product', 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => false));
    foreach ($products as $product) {
        $new = get_permalink($product);
        $old = home_url('/' . HPV_PRODUCT_BASE . '/' . $product->post_name . '/');
        if ($new && $new !== $old) { $replacements[$old] = $new; }
    }
    if ($replacements) {
        $sources = get_posts(array('post_type' => array('wp_navigation', 'wp_template_part', 'wp_template'), 'post_status' => 'publish', 'numberposts' => -1));
        foreach ($sources as $source) {
            $content = strtr($source->post_content, $replacements);
            if ($content === $source->post_content) { continue; }
            wp_save_post_revision($source->ID);
            $saved = wp_update_post(wp_slash(array('ID' => $source->ID, 'post_content' => $content)), true);
            if (is_wp_error($saved)) { return $saved; }
        }
        $items = get_posts(array('post_type' => 'nav_menu_item', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_menu_item_type', 'meta_value' => 'custom'));
        foreach ($items as $item) {
            $url = get_post_meta($item->ID, '_menu_item_url', true);
            if (is_string($url) && isset($replacements[$url])) { update_post_meta($item->ID, '_menu_item_url', esc_url_raw($replacements[$url])); }
        }
    }
    if (class_exists('RankMath\\Sitemap\\Cache')) { \RankMath\Sitemap\Cache::invalidate_storage(); }
    update_option('hpv_product_root_urls_v1', 1, false);
    return true;
}
