<?php
/** Integration check: run only in the disposable local WordPress with Rank Math active. */
require '/var/www/html/wp-load.php';
if (!in_array(wp_parse_url(home_url(), PHP_URL_HOST), array('127.0.0.1', 'localhost'), true) || !defined('RANK_MATH_VERSION')) {
    throw new RuntimeException('Local WordPress with Rank Math required.');
}
function hpv_sitemap_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
function hpv_sitemap_fetch($path) {
    // Apache is on port 80 inside the container; the site's public local port is 8787.
    $response = wp_remote_get('http://127.0.0.1' . $path, array('timeout' => 20, 'headers' => array('Host' => wp_parse_url(home_url(), PHP_URL_HOST) . ':8787')));
    hpv_sitemap_assert(!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200, $path . ' returns HTTP 200');
    $body = wp_remote_retrieve_body($response);
    hpv_sitemap_assert(simplexml_load_string($body) !== false, $path . ' contains valid XML');
    return $body;
}
$settings_before = get_option('rank-math-options-sitemap');
$version_before = get_option('hpv_sitemap_setup_version');
$public_before = get_option('blog_public');
$ids = array();
try {
    // Rank Math intentionally disables public sitemaps on a private test site.
    update_option('blog_public', '1');
    $settings = is_array($settings_before) ? $settings_before : array();
    $settings['pt_post_sitemap'] = 'off';
    $settings['pt_page_sitemap'] = 'off';
    unset($settings['pt_hpv_product_sitemap'], $settings['pt_hpv_project_sitemap']);
    update_option('rank-math-options-sitemap', $settings);
    delete_option('hpv_sitemap_setup_version');
    hpv_enable_article_sitemap();
    $actual = get_option('rank-math-options-sitemap');
    hpv_sitemap_assert($actual['pt_post_sitemap'] === 'on' && $actual['pt_page_sitemap'] === 'off', 'Enables posts while preserving deliberate page exclusion');
    hpv_sitemap_assert($actual['pt_hpv_product_sitemap'] === 'on' && $actual['pt_hpv_project_sitemap'] === 'on', 'Initializes missing custom content group settings');
    hpv_enable_article_sitemap();
    hpv_sitemap_assert(get_option('rank-math-options-sitemap') === $actual, 'Repeated setup is stable');

    $published = wp_insert_post(array('post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'QA sitemap public ' . wp_generate_uuid4()), true);
    hpv_sitemap_assert(!is_wp_error($published), 'Creates isolated test article');
    $ids[] = $published;
    foreach (array('draft', 'private', 'future') as $status) {
        $id = wp_insert_post(array('post_type' => 'post', 'post_status' => $status, 'post_title' => 'QA sitemap ' . $status, 'post_date' => $status === 'future' ? gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS * 7) : current_time('mysql')), true);
        hpv_sitemap_assert(!is_wp_error($id), 'Creates ' . $status . ' fixture');
        $ids[] = $id;
    }
    $noindex = wp_insert_post(array('post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'QA sitemap noindex', 'meta_input' => array('rank_math_robots' => array('noindex'))), true);
    hpv_sitemap_assert(!is_wp_error($noindex), 'Creates noindex fixture');
    $ids[] = $noindex;
    foreach (array('hpv_product', 'hpv_project') as $type) {
        $id = wp_insert_post(array('post_type' => $type, 'post_status' => 'publish', 'post_title' => 'QA sitemap ' . $type . ' ' . wp_generate_uuid4()), true);
        hpv_sitemap_assert(!is_wp_error($id), 'Creates ' . $type . ' fixture');
        $ids[] = $id;
    }
    \RankMath\Sitemap\Cache::invalidate_storage();
    $index = hpv_sitemap_fetch('/sitemap_index.xml');
    hpv_sitemap_assert(strpos($index, '/post-sitemap.xml') !== false, 'Index includes article group after publication');
    $posts = hpv_sitemap_fetch('/post-sitemap.xml');
    hpv_sitemap_assert(strpos($posts, esc_xml(get_permalink($published))) !== false, 'Published article appears');
    foreach (array_slice($ids, 1, 4) as $id) {
        hpv_sitemap_assert(strpos($posts, esc_xml(get_permalink($id))) === false, get_post_status($id) . ' excluded (ID ' . $id . ')');
    }

    foreach (array('product', 'project') as $name) {
        hpv_sitemap_assert(strpos($index, '/' . $name . '-sitemap.xml') !== false && strpos($index, '/hpv_' . $name . '-sitemap') === false, 'Index uses clean ' . $name . ' sitemap name');
        $xml = hpv_sitemap_fetch('/' . $name . '-sitemap.xml');
        $fixture = $ids[$name === 'product' ? 5 : 6];
        hpv_sitemap_assert(strpos($xml, esc_xml(get_permalink($fixture))) !== false && get_post_type($fixture) === 'hpv_' . $name, 'Clean URL serves original ' . $name . ' data');
        $response = wp_remote_get('http://127.0.0.1/hpv_' . $name . '-sitemap.xml', array('redirection' => 0, 'headers' => array('Host' => '127.0.0.1:8787')));
        hpv_sitemap_assert(!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 301 && wp_remote_retrieve_header($response, 'location') === home_url('/' . $name . '-sitemap.xml'), 'Legacy ' . $name . ' URL redirects permanently');
    }
    $entry = hpv_sitemap_public_names(array('loc' => home_url('/hpv_product-sitemap2.xml'), 'lastmod' => '2026-11-01'));
    hpv_sitemap_assert($entry['loc'] === home_url('/product-sitemap2.xml') && $entry['lastmod'] === '2026-11-01', 'Paginated sitemap names preserve page number and metadata');
    hpv_sitemap_assert(hpv_sitemap_request_alias(array('sitemap' => 'product', 'sitemap_n' => '2')) === array('sitemap' => 'hpv_product', 'sitemap_n' => '2'), 'Pagination routes to original provider');

    $image = array('src' => home_url('/wp-content/uploads/product.webp'), 'title' => 'Product');
    $filtered = apply_filters('rank_math/sitemap/urlimages', array($image, $image, array('src' => home_url('/wp-content/plugins/hai-phat-vina-core/assets/zalo.svg'))), $published);
    hpv_sitemap_assert($filtered === array($image), 'Keeps content image metadata, removes duplicate and bundled chat icon');
} finally {
    foreach ($ids as $id) { wp_delete_post($id, true); }
    if ($settings_before === false) { delete_option('rank-math-options-sitemap'); } else { update_option('rank-math-options-sitemap', $settings_before); }
    if ($version_before === false) { delete_option('hpv_sitemap_setup_version'); } else { update_option('hpv_sitemap_setup_version', $version_before); }
    update_option('blog_public', $public_before);
    \RankMath\Sitemap\Cache::invalidate_storage();
}
