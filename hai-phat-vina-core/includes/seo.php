<?php
/** Keep public content grouped by type using Rank Math's native sitemap provider. */
defined('ABSPATH') || exit;

function hpv_enable_article_sitemap() {
    if (!defined('RANK_MATH_VERSION') || get_option('hpv_sitemap_setup_version') === '3') { return; }

    $settings = get_option('rank-math-options-sitemap', array());
    if (!is_array($settings)) { return; }

    $settings['pt_post_sitemap'] = 'on';
    // Supply defaults for public pages/products without overriding deliberate exclusions.
    foreach (array('page', 'hpv_product', 'hpv_project') as $type) {
        $key = 'pt_' . $type . '_sitemap';
        if (!array_key_exists($key, $settings)) { $settings[$key] = 'on'; }
    }
    update_option('rank-math-options-sitemap', $settings);

    if (get_option('rank-math-options-sitemap') !== $settings) { return; }

    if (class_exists('RankMath\\Sitemap\\Cache')) {
        \RankMath\Sitemap\Cache::invalidate_storage();
    }

    // Run once so future deliberate changes in Rank Math remain editable.
    update_option('hpv_sitemap_setup_version', '3', false);
}
add_action('init', 'hpv_enable_article_sitemap', 20);

/** Remove the bundled chat icon and exact duplicates, retaining content images. */
function hpv_sitemap_content_images($images) {
    if (!is_array($images)) { return $images; }
    $result = array();
    $seen = array();
    foreach ($images as $image) {
        if (!is_array($image) || empty($image['src']) || !is_string($image['src'])) {
            $result[] = $image;
            continue;
        }
        $src = $image['src'];
        $path = wp_parse_url($src, PHP_URL_PATH);
        if (is_string($path) && preg_match('~/wp-content/(?:plugins/hai-phat-vina-core|themes/hai-phat-vina)/assets/zalo\.svg$~', $path)) { continue; }
        if (isset($seen[$src])) { continue; }
        $seen[$src] = true;
        $result[] = $image;
    }
    return $result;
}
add_filter('rank_math/sitemap/urlimages', 'hpv_sitemap_content_images');

/** Public sitemap names are independent of the stored WordPress post types. */
function hpv_sitemap_public_names($entry) {
    if (is_array($entry) && isset($entry['loc'])) {
        $entry['loc'] = preg_replace('~([/?=])hpv_(product|project)(-sitemap[0-9]*\.xml|(?=&|$))~', '$1$2$3', $entry['loc']);
    }
    return $entry;
}
add_filter('rank_math/sitemap/index/entry', 'hpv_sitemap_public_names');

function hpv_sitemap_request_alias($vars) {
    if (!defined('RANK_MATH_VERSION') || empty($vars['sitemap'])) { return $vars; }
    if (in_array($vars['sitemap'], array('product', 'project'), true)) {
        $vars['sitemap'] = 'hpv_' . $vars['sitemap'];
    }
    return $vars;
}
add_filter('request', 'hpv_sitemap_request_alias');

/** Redirect old sitemap paths before Rank Math emits XML in parse_query. */
function hpv_redirect_legacy_sitemap($wp) {
    if (!defined('RANK_MATH_VERSION')) { return; }
    if (!preg_match('~^(?:index\.php/)?hpv_(product|project)-sitemap([0-9]*)\.xml$~', $wp->request, $matches)) { return; }
    if (!class_exists('RankMath\\Sitemap\\Router')) { return; }
    $target = \RankMath\Sitemap\Router::get_base_url($matches[1] . '-sitemap' . $matches[2] . '.xml');
    wp_safe_redirect($target, 301, 'Hai Phat Vina');
    exit;
}
add_action('parse_request', 'hpv_redirect_legacy_sitemap');
