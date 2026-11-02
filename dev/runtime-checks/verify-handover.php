<?php
/** Behavioral migration and handover checks, for the disposable local site only. */
require '/var/www/html/wp-load.php';
if (get_option('home') !== 'http://127.0.0.1:8787') { throw new RuntimeException('Run only on the local QA site.'); }
function hpv_handover_assert($value, $message) {
    if (!$value) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
hpv_handover_assert(wp_is_block_theme(), 'WordPress recognizes a native block theme');
hpv_handover_assert(defined('HPV_CORE_VERSION'), 'Business data plugin is active');
$manager = get_user_by('login', 'hpv_manager_check');
wp_set_current_user($manager->ID);
foreach (array('edit_posts', 'edit_others_posts', 'publish_pages', 'edit_theme_options', 'hpv_manage_business') as $cap) { hpv_handover_assert(current_user_can($cap), 'Manager can ' . $cap); }
foreach (array('manage_options', 'activate_plugins', 'install_plugins', 'edit_themes', 'switch_themes', 'create_users') as $cap) { hpv_handover_assert(!current_user_can($cap), 'Manager cannot ' . $cap); }
$pages = get_option('hpv_page_ids');
$home = get_post($pages['home']);
hpv_handover_assert(has_block('core/cover', $home->post_content) && has_block('core/query', $home->post_content), 'Homepage contains editable core hero and live product queries');
hpv_handover_assert(has_block('hpv/quote-form', $home->post_content), 'Homepage contains functional quote block');
$navigation = get_post(get_option('hpv_navigation_id'));
hpv_handover_assert($navigation && $navigation->post_type === 'wp_navigation' && has_block('core/navigation-link', $navigation->post_content), 'Classic menu converted into editable native Navigation');
hpv_handover_assert(!empty(get_option('hpv_block_migration_backup')['pages']), 'Original page contents backed up before migration');
$original = $home->post_content;
$marker = '<!-- wp:paragraph --><p>QA Nội dung khách đã sửa.</p><!-- /wp:paragraph -->';
wp_update_post(wp_slash(array('ID' => $home->ID, 'post_content' => $original . $marker)));
$before = array('navigation' => count(get_posts(array('post_type' => 'wp_navigation', 'numberposts' => -1))), 'attachments' => count(get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1))));
try {
    $result = hpv_prepare_block_site();
    hpv_handover_assert(!is_wp_error($result), 'Repeated migration completes');
    hpv_handover_assert(get_post($home->ID)->post_content === $original . $marker, 'Repeated migration preserves client-edited page content');
    hpv_handover_assert(count(get_posts(array('post_type' => 'wp_navigation', 'numberposts' => -1))) === $before['navigation'], 'Repeated migration does not duplicate navigation');
    hpv_handover_assert(count(get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1))) === $before['attachments'], 'Repeated migration does not duplicate media');
} finally {
    wp_update_post(wp_slash(array('ID' => $home->ID, 'post_content' => $original)));
}
$settings = get_option('hpv_settings', array());
try {
    $updated = hpv_sanitize_settings(array('phone' => '0900.111.222'));
    update_option('hpv_settings', $updated);
    $output = do_blocks('<!-- wp:hpv/company-info {"layout":"phone"} /-->');
    hpv_handover_assert(strpos($output, '0900.111.222') !== false && strpos($output, 'tel:0900111222') !== false, 'Changing company settings updates live shared contact block');
    hpv_handover_assert(hpv_get('tax_id') === '0601326528' && hpv_get('bank_account') === '9868201868', 'Partial company save preserves tax and bank account strings');
} finally { update_option('hpv_settings', $settings); }
$request = wp_insert_post(array('post_type' => 'hpv_request', 'post_title' => 'QA permission fixture', 'post_status' => 'private'));
try {
    hpv_handover_assert(current_user_can('read_post', $request) && current_user_can('edit_post', $request), 'Manager can open private customer request');
    $editor_id = username_exists('hpv_editor_check');
    if (!$editor_id) { $editor_id = wp_insert_user(array('user_login' => 'hpv_editor_check', 'user_pass' => wp_generate_password(30), 'role' => 'editor', 'user_email' => 'hpv-editor@example.test')); }
    wp_set_current_user($editor_id);
    hpv_handover_assert(!current_user_can('edit_theme_options') && !current_user_can('read_post', $request), 'Existing Editor role is unchanged and cannot read customer request');
    wp_set_current_user(0);
    hpv_handover_assert(!current_user_can('read_post', $request), 'Guest cannot read customer request');
} finally { wp_delete_post($request, true); }

// Legacy PHP templates owned functional output outside the saved page content.
$saved_pages = get_option('hpv_page_ids');
$saved_backup = get_option('hpv_block_migration_backup');
$saved_front = get_option('page_on_front');
$saved_show = get_option('show_on_front');
$fixtures = array();
try {
    $contact_text = '<!-- wp:paragraph --><p>QA ghi chú liên hệ phải giữ.</p><!-- /wp:paragraph -->';
    $contact = wp_insert_post(wp_slash(array('post_type' => 'page', 'post_title' => 'QA legacy contact', 'post_status' => 'draft', 'post_content' => $contact_text, 'meta_input' => array('_wp_page_template' => 'page-contact.php'))));
    $fixtures[] = $contact;
    $quote_text = '<!-- wp:paragraph --><p>QA ghi chú báo giá phải giữ.</p><!-- /wp:paragraph -->';
    $quote = wp_insert_post(wp_slash(array('post_type' => 'page', 'post_title' => 'QA legacy quote', 'post_status' => 'draft', 'post_content' => $quote_text, 'meta_input' => array('_wp_page_template' => 'page-quote.php'))));
    $fixtures[] = $quote;
    $changed_front = wp_insert_post(array('post_type' => 'page', 'post_title' => 'QA manually selected front', 'post_status' => 'publish', 'post_content' => '<p>QA trang chủ do khách chọn.</p>'));
    $fixtures[] = $changed_front;
    update_option('page_on_front', $changed_front);
    $result = hpv_prepare_block_site();
    hpv_handover_assert(!is_wp_error($result), 'Legacy-template and changed-front migration completes' . (is_wp_error($result) ? ': ' . $result->get_error_message() : ''));
    $contact_content = get_post_field('post_content', $contact);
    hpv_handover_assert(has_block('hpv/quote-form', $contact_content) && strpos($contact_content, 'QA ghi chú liên hệ phải giữ.') !== false, 'Manually assigned legacy contact page retains block text and gains missing form');
    $quote_content = get_post_field('post_content', $quote);
    hpv_handover_assert(has_block('core/query', $quote_content) && strpos($quote_content, 'QA ghi chú báo giá phải giữ.') !== false, 'Manually assigned legacy quote page retains block text and gains live catalog');
    hpv_handover_assert(get_page_template_slug($contact) === 'page-full-width' && get_page_template_slug($quote) === 'page-full-width', 'Legacy PHP page templates replaced with editable block template');
    $front_content = get_post_field('post_content', $changed_front);
    hpv_handover_assert(has_block('core/cover', $front_content) && strpos($front_content, 'QA trang chủ do khách chọn.') !== false, 'Actual selected front page migrated despite stale seed homepage mapping');
    hpv_handover_assert((int) get_option('page_on_front') === $changed_front, 'Migration keeps the owner-selected front page');
    hpv_handover_assert(get_post_field('post_content', $home->ID) === $original, 'Previously seeded homepage is not overwritten when another front page is selected');
} finally {
    update_option('page_on_front', $saved_front);
    update_option('show_on_front', $saved_show);
    update_option('hpv_page_ids', $saved_pages);
    update_option('hpv_block_migration_backup', $saved_backup);
    foreach ($fixtures as $fixture) { wp_delete_post($fixture, true); }
}
echo 'Handover checks complete on WordPress ' . get_bloginfo('version') . ' / PHP ' . PHP_VERSION . PHP_EOL;
