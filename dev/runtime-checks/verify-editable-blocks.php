<?php
require '/var/www/html/wp-load.php';
if (get_option('home') !== 'http://127.0.0.1:8787') { throw new RuntimeException('Local QA only.'); }
function hpv_edit_assert($value, $message) {
    if (!$value) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
$manager = get_user_by('login', 'hpv_manager_check');
wp_set_current_user($manager->ID);
$form = do_blocks('<!-- wp:hpv/quote-form {"labels":{"nameLabel":"Tên khách","submitLabel":"Nhận tư vấn"},"needs":["Sản phẩm mới","Tư vấn riêng"]} /-->');
hpv_edit_assert(strpos($form, 'Tên khách') !== false && strpos($form, 'Nhận tư vấn') !== false && strpos($form, 'Sản phẩm mới') !== false, 'Manager-authored form labels and product choices render');
hpv_edit_assert(strpos($form, 'name="hpv_consent"') !== false && strpos($form, 'name="hpv_nonce"') !== false && strpos($form, 'name="hpv_phone"') !== false, 'Editable form copy preserves required server fields and protection');
$dock = do_blocks('<!-- wp:hpv/floating-contact {"labels":{"zaloLabel":"Nhắn tư vấn","phoneLabel":"Hotline {phone}"}} /-->');
hpv_edit_assert(strpos($dock, 'Nhắn tư vấn') !== false && strpos($dock, 'Hotline ' . hpv_get('phone')) !== false, 'Floating contact labels editable without losing phone linkage');
hpv_edit_assert(strpos($dock, 'assets/zalo.svg') !== false && strpos($dock, '<span>Hotline ') !== false, 'Contact actions include recognisable Zalo mark and persistent hotline label');
$contact = do_blocks('<!-- wp:hpv/company-info {"layout":"contact"} /-->');
hpv_edit_assert(strpos($contact, 'assets/zalo.svg') !== false && strpos($contact, 'Nhắn tin qua Zalo') !== false, 'Company contact clearly labels its Zalo link');
$branches = do_blocks('<!-- wp:hpv/company-info {"layout":"footer-branches"} /-->');
hpv_edit_assert(substr_count($branches, 'class="hpv-branch"') === 4 && substr_count($branches, '<iframe ') === 4 && strpos($branches, 'www.google.com/maps?q=') !== false, 'Four editable addresses include expandable Google maps');
hpv_edit_assert(hpv_map_embed_url('<iframe src="https://www.google.com/maps/embed?pb=abc&amp;hl=vi"></iframe>') === 'https://www.google.com/maps/embed?pb=abc&hl=vi', 'Pasted Google Maps iframe accepted');
hpv_edit_assert(hpv_map_embed_url('https://www.google.com.evil.test/maps/embed?pb=bad') === '' && hpv_map_embed_url('javascript:alert(1)') === '', 'Unsafe map embeds rejected');
$phone = do_blocks('<!-- wp:hpv/company-info {"layout":"phone","labels":{"phoneCaption":"Liên hệ kinh doanh"}} /-->');
hpv_edit_assert(strpos($phone, 'Liên hệ kinh doanh') !== false, 'Shared contact block copy editable');
$default = do_blocks('<!-- wp:hpv/company-info {"layout":"phone"} /-->');
hpv_edit_assert(strpos($default, 'Tư vấn') !== false, 'Existing blocks without custom attributes remain compatible');
$malicious = do_blocks('<!-- wp:hpv/floating-contact {"labels":{"zaloLabel":"<script>alert(1)</script>"}} /-->');
hpv_edit_assert(strpos($malicious, '<script>') === false, 'Block copy does not execute arbitrary HTML');
$created = array();
try {
    foreach (array('templates' => 'wp_template', 'template-parts' => 'wp_template_part', 'navigation' => 'wp_navigation') as $route => $type) {
        $request = new WP_REST_Request('POST', '/wp/v2/' . $route);
        $request->set_body_params(array('slug' => 'hpv-qa-' . $route, 'title' => 'QA editable ' . $route, 'content' => '<!-- wp:paragraph --><p>QA direct editor save.</p><!-- /wp:paragraph -->', 'status' => 'publish'));
        $response = rest_do_request($request);
        $data = $response->get_data();
        $id = isset($data['wp_id']) ? $data['wp_id'] : (isset($data['id']) && is_numeric($data['id']) ? $data['id'] : 0);
        if ($id) { $created[] = $id; }
        hpv_edit_assert($response->get_status() === 201 && $id && get_post_type($id) === $type, 'Manager can create and save native ' . $route . ' through REST');
    }
    $global_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
    $global_original = get_post_field('post_content', $global_id);
    try {
        $request = new WP_REST_Request('POST', '/wp/v2/global-styles/' . $global_id);
        $request->set_body_params(array('styles' => array('color' => array('background' => '#f5f5f5'))));
        $response = rest_do_request($request);
        hpv_edit_assert($response->get_status() === 200, 'Manager can save native Global Styles (site colors and typography)');
    } finally { wp_update_post(wp_slash(array('ID' => $global_id, 'post_content' => $global_original))); }
} finally { foreach ($created as $id) { wp_delete_post($id, true); } }
hpv_edit_assert(!current_user_can('manage_options') && !current_user_can('install_plugins') && !current_user_can('edit_themes'), 'A–Z visual editing does not grant server/plugin/code administration');
