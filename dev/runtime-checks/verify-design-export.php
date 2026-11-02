<?php
$_SERVER['HTTP_HOST'] = '127.0.0.1:8787';
$_SERVER['REQUEST_METHOD'] = 'GET';
require '/var/www/html/wp-load.php';
if (get_option('home') !== 'http://127.0.0.1:8787') { throw new RuntimeException('Disposable local QA only.'); }
require_once WP_PLUGIN_DIR . '/hai-phat-vina-core/includes/design-export.php';
function hpv_design_assert($value, $message) {
    if (!$value) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
$created = array();
function hpv_design_fixture($args) {
    global $created;
    $id = wp_insert_post(wp_slash(array_merge(array('post_status' => 'publish', 'post_title' => 'Export QA'), $args)), true);
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    $created[] = $id;
    return $id;
}
$global_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
$original_global = get_post_field('post_content', $global_id);
try {
    $baseline = hpv_export_design();
    hpv_design_assert(isset($baseline['files']['templates/front-page.html'], $baseline['files']['parts/header.html']), 'Effective native templates and header exported as block source');
    hpv_design_assert(strpos($baseline['files']['parts/header.html'], 'wp:pattern ') === false, 'Theme patterns expanded without rendering the website');
    foreach (array(array('page', 'draft', ''), array('page', 'private', ''), array('page', 'publish', 'secret'), array('post', 'publish', ''), array('hpv_product', 'publish', ''), array('hpv_request', 'private', ''), array('attachment', 'inherit', '')) as $fixture) {
        hpv_design_fixture(array('post_type' => $fixture[0], 'post_status' => $fixture[1], 'post_password' => $fixture[2], 'post_content' => 'DO-NOT-EXPORT-PRIVATE-OR-BUSINESS-CONTENT'));
    }
    hpv_design_assert(hpv_export_design() === $baseline, 'Drafts, private/password pages, articles, products, leads and media do not change the design export');
    $navigation = hpv_design_fixture(array('post_type' => 'wp_navigation', 'post_content' => '<!-- wp:navigation-link {"label":"Public menu","url":"/public/"} /-->'));
    $unused = hpv_design_fixture(array('post_type' => 'wp_navigation', 'post_content' => 'UNREFERENCED-MENU'));
    $pattern = hpv_design_fixture(array('post_type' => 'wp_block', 'post_content' => '<!-- wp:paragraph --><p>Synced public pattern.</p><!-- /wp:paragraph -->'));
    $content = '<!-- wp:paragraph --><p>Public design copy; \'quotes\' & images stay links.</p><!-- /wp:paragraph --><!-- wp:navigation {"ref":' . $navigation . '} /--><!-- wp:block {"ref":' . $pattern . '} /--><!-- wp:hpv/quote-form /-->';
    $page = hpv_design_fixture(array('post_type' => 'page', 'post_content' => $content));
    update_post_meta($page, 'secret-api-key', 'NEVER-EXPORT-POST-META');
    $export = hpv_export_design();
    hpv_design_assert(isset($export['files']['pages/' . $page . '.html'], $export['files']['navigation/' . $navigation . '.html'], $export['files']['patterns/' . $pattern . '.html']), 'Public page edits and referenced menu/pattern sources exported');
    hpv_design_assert(!isset($export['files']['navigation/' . $unused . '.html']) && strpos(hpv_design_json($export), 'NEVER-EXPORT-POST-META') === false, 'Unreferenced menus and post metadata excluded');
    hpv_design_assert(strpos($export['files']['pages/' . $page . '.html'], 'wp:hpv/quote-form') !== false && strpos($export['files']['pages/' . $page . '.html'], 'hpv_nonce') === false, 'Dynamic blocks remain source; no rendered nonce/customer data');
    hpv_design_assert(hpv_export_design() === $export, 'Unchanged database exports byte-identical design source');
    wp_update_post(array('ID' => $page, 'post_status' => 'draft'));
    hpv_design_assert(!isset(hpv_export_design()['files']['pages/' . $page . '.html']), 'Unpublished page removed from current design export');
    wp_update_post(array('ID' => $page, 'post_status' => 'publish'));
    wp_update_post(array('ID' => $pattern, 'post_status' => 'private'));
    $rejected = false;
    try { hpv_export_design(); } catch (RuntimeException $error) { $rejected = true; }
    hpv_design_assert($rejected, 'Private shared-pattern reference blocks export instead of exposing its source');
    wp_update_post(array('ID' => $pattern, 'post_status' => 'publish'));
    wp_update_post(wp_slash(array('ID' => $global_id, 'post_content' => hpv_design_json(array('version' => 3, 'isGlobalStylesUserThemeJSON' => true, 'styles' => array('color' => array('background' => '#123456')))))));
    hpv_design_assert(strpos(hpv_export_design()['files']['global-styles.json'], '#123456') !== false, 'Saved global style changes exported');
    $template = hpv_design_fixture(array('post_type' => 'wp_template_part', 'post_name' => 'export-qa', 'post_content' => '<!-- wp:paragraph --><p>Edited template part.</p><!-- /wp:paragraph -->'));
    wp_set_object_terms($template, 'hai-phat-vina', 'wp_theme');
    hpv_design_assert(isset(hpv_export_design()['files']['parts/export-qa.html']), 'Active-theme saved template part exported');
    wp_set_object_terms($template, 'another-theme', 'wp_theme');
    hpv_design_assert(!isset(hpv_export_design()['files']['parts/export-qa.html']), 'Other-theme overrides excluded');
} finally {
    foreach ($created as $id) { wp_delete_post($id, true); }
    wp_update_post(wp_slash(array('ID' => $global_id, 'post_content' => $original_global)));
}
