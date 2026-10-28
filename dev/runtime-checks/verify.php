<?php
require '/var/www/html/wp-load.php';
function hpv_assert($condition, $message) {
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL); exit(1); }
    echo 'PASS: ' . $message . PHP_EOL;
}
$admin = get_user_by('login', 'hpv_check');
wp_set_current_user($admin->ID);
hpv_assert(current_user_can('manage_options'), 'Administrator retains manage_options capability');
$counts = array();
foreach (array('hpv_product', 'hpv_project', 'page', 'post', 'nav_menu_item') as $type) { $counts[$type] = (int) wp_count_posts($type)->publish; }
hpv_assert($counts['hpv_product'] === 8, 'Eight products created');
hpv_assert($counts['hpv_project'] === 3, 'Three illustrative applications created');
hpv_assert($counts['post'] === 3, 'Three starter articles created');
$first = get_posts(array('post_type' => 'hpv_product', 'numberposts' => 1))[0];
$original_title = $first->post_title;
$original_content = $first->post_content;
$original_terms = wp_get_object_terms($first->ID, 'hpv_category', array('fields' => 'ids'));
wp_update_post(array('ID' => $first->ID, 'post_title' => 'QA edited product title', 'post_content' => 'QA edited product body'));
wp_set_object_terms($first->ID, array(), 'hpv_category');
$original_front = get_option('page_on_front');
$original_blog = get_option('page_for_posts');
update_option('page_on_front', hpv_create_demo_content()['about']);
hpv_create_demo_content();
foreach ($counts as $type => $count) { hpv_assert((int) wp_count_posts($type)->publish === $count, 'Installer does not duplicate ' . $type); }
hpv_assert(get_post($first->ID)->post_title === 'QA edited product title' && get_post($first->ID)->post_content === 'QA edited product body', 'Installer preserves edited product text');
hpv_assert(count(wp_get_object_terms($first->ID, 'hpv_category')) === 0, 'Installer preserves edited product categories');
hpv_assert((int) get_option('page_on_front') === (int) hpv_create_demo_content()['about'], 'Installer preserves an existing front page');
hpv_assert((int) get_option('page_for_posts') === (int) $original_blog, 'Installer preserves existing posts page');
wp_update_post(array('ID' => $first->ID, 'post_title' => $original_title, 'post_content' => $original_content));
wp_set_object_terms($first->ID, $original_terms, 'hpv_category');
update_option('page_on_front', $original_front);
$leads = get_posts(array('post_type' => 'hpv_request', 'post_status' => 'private', 'numberposts' => 10));
hpv_assert(count($leads) >= 1, 'Browser form submission persisted as a private request');
$request = $leads[0];
hpv_assert(current_user_can('edit_post', $request->ID), 'Administrator can open a stored request');
$editor_id = username_exists('hpv_editor_check');
if (!$editor_id) { $editor_id = wp_insert_user(array('user_login' => 'hpv_editor_check', 'user_pass' => wp_generate_password(30), 'role' => 'editor', 'user_email' => 'hpv-editor@example.test')); }
wp_set_current_user($editor_id);
hpv_assert(current_user_can('edit_posts') && current_user_can('publish_posts'), 'Editorial account can write and publish normal WordPress posts');
hpv_assert(!current_user_can('edit_post', $request->ID) && !current_user_can('read_post', $request->ID), 'Editors cannot view or edit customer requests');
wp_set_current_user(0);
hpv_assert(!current_user_can('read_post', $request->ID), 'Guests cannot read customer requests');
$type = get_post_type_object('hpv_request');
hpv_assert(!$type->publicly_queryable && !$type->show_in_rest && $type->exclude_from_search, 'Request type has no public archive, REST exposure, or search indexing');
foreach (array('brand', 'tax_id', 'phone', 'email', 'bank_account') as $key) { hpv_assert(hpv_get($key) !== '', 'Company setting exists: ' . $key); }
hpv_assert(hpv_get('tax_id') === '0601326528' && hpv_get('bank_account') === '9868201868', 'Tax and account IDs remain strings without truncation');
foreach (array('guide', 'comparison', 'contact') as $pattern) {
    hpv_assert(WP_Block_Patterns_Registry::get_instance()->is_registered('hai-phat-vina/' . $pattern), 'Native editorial block pattern registered: ' . $pattern);
}
echo 'WordPress ' . get_bloginfo('version') . ' / PHP ' . PHP_VERSION . PHP_EOL;
