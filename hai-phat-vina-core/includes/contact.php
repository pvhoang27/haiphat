<?php
defined('ABSPATH') || exit;
if (!function_exists('hpv_quote_form')) {

function hpv_form_messages() {
    return array('success' => 'Đã nhận yêu cầu của bạn. Hải Phát Vina sẽ liên hệ theo số điện thoại bạn cung cấp.', 'invalid' => 'Vui lòng nhập họ tên, số điện thoại hợp lệ và đồng ý cho phép liên hệ.', 'expired' => 'Phiên gửi đã hết hạn. Vui lòng tải lại trang rồi gửi lại.', 'limited' => 'Bạn vừa gửi yêu cầu. Vui lòng đợi một phút hoặc gọi hotline nếu cần trao đổi ngay.', 'error' => 'Chưa lưu được yêu cầu. Vui lòng thử lại hoặc gọi hotline.');
}

function hpv_quote_form($variant = 'full', $labels = array(), $needs = null) {
    $defaults = array('nameLabel' => 'Họ và tên', 'phoneLabel' => 'Số điện thoại', 'needLabel' => 'Bạn đang quan tâm', 'locationLabel' => 'Khu vực cần tư vấn', 'messageLabel' => 'Nhu cầu của bạn', 'consentText' => 'Tôi đồng ý để Hải Phát Vina sử dụng thông tin trên để liên hệ tư vấn.', 'privacyLabel' => 'Chính sách bảo mật', 'submitLabel' => 'Gửi yêu cầu báo giá', 'namePlaceholder' => 'Tên của bạn', 'phonePlaceholder' => 'Số điện thoại để tư vấn', 'needPlaceholder' => 'Chọn sản phẩm / dịch vụ', 'locationPlaceholder' => 'Quận / huyện, tỉnh / thành', 'messagePlaceholder' => 'Ví dụ: mái xếp cho sân rộng 5 × 8 m, tại Hà Đông…');
    $labels = is_array($labels) ? $labels : array();
    foreach ($defaults as $key => $value) { $defaults[$key] = isset($labels[$key]) && is_string($labels[$key]) && trim($labels[$key]) !== '' ? sanitize_text_field($labels[$key]) : $value; }
    $copy = $defaults;
    $needs = is_array($needs) ? array_slice(array_values(array_filter(array_map(function ($need) { return is_string($need) ? sanitize_text_field($need) : ''; }, $needs))), 0, 30) : array('Mái xếp & mái hiên', 'Bạt nhựa che phủ', 'Bạt HDPE lót hồ', 'Dù che ngoài trời', 'May ép bạt / phụ kiện', 'Cần tư vấn thêm');
    if (function_exists('hpv_enqueue_contact_assets')) { hpv_enqueue_contact_assets(); }
    static $count = 0;
    $count++;
    $id = 'hpv-form-' . $count;
    $status = isset($_GET['hpv_status']) && is_string($_GET['hpv_status']) ? sanitize_key(wp_unslash($_GET['hpv_status'])) : '';
    $messages = hpv_form_messages();
    $product = is_singular('hpv_product') ? get_the_title() : '';
    ?>
    <form class="quote-form <?php echo esc_attr('quote-form--' . $variant); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-quote-form>
        <input type="hidden" name="action" value="hpv_quote">
        <input type="hidden" name="hpv_return" value="<?php echo esc_url(hpv_page_url('contact')); ?>">
        <input type="hidden" name="hpv_token" value="<?php echo esc_attr(wp_generate_uuid4()); ?>">
        <?php wp_nonce_field('hpv_quote', 'hpv_nonce', false); ?>
        <div class="form-trap" hidden aria-hidden="true"><label for="<?php echo esc_attr($id); ?>-site">Không điền trường này</label><input type="text" id="<?php echo esc_attr($id); ?>-site" name="company_website" value="" tabindex="-1" autocomplete="off"></div>
        <div class="form-row"><div class="form-field"><label for="<?php echo esc_attr($id); ?>-name"><?php echo esc_html($copy['nameLabel']); ?> <span aria-hidden="true">*</span></label><input id="<?php echo esc_attr($id); ?>-name" name="hpv_name" autocomplete="name" placeholder="<?php echo esc_attr($copy['namePlaceholder']); ?>" required maxlength="100"></div>
        <div class="form-field"><label for="<?php echo esc_attr($id); ?>-phone"><?php echo esc_html($copy['phoneLabel']); ?> <span aria-hidden="true">*</span></label><input id="<?php echo esc_attr($id); ?>-phone" name="hpv_phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="<?php echo esc_attr($copy['phonePlaceholder']); ?>" required maxlength="24"></div></div>
        <div class="form-row"><div class="form-field"><label for="<?php echo esc_attr($id); ?>-need"><?php echo esc_html($copy['needLabel']); ?></label><select id="<?php echo esc_attr($id); ?>-need" name="hpv_need"><option value=""><?php echo esc_html($copy['needPlaceholder']); ?></option><?php foreach ($needs as $need) { echo '<option value="' . esc_attr($need) . '">' . esc_html($need) . '</option>'; } ?></select></div><div class="form-field"><label for="<?php echo esc_attr($id); ?>-location"><?php echo esc_html($copy['locationLabel']); ?></label><input id="<?php echo esc_attr($id); ?>-location" name="hpv_location" autocomplete="address-level2" placeholder="<?php echo esc_attr($copy['locationPlaceholder']); ?>" maxlength="200"></div></div>
        <div class="form-field"><label for="<?php echo esc_attr($id); ?>-message"><?php echo esc_html($copy['messageLabel']); ?></label><textarea id="<?php echo esc_attr($id); ?>-message" name="hpv_message" rows="3" placeholder="<?php echo esc_attr($copy['messagePlaceholder']); ?>" maxlength="3000"><?php echo $product ? esc_textarea('Tôi quan tâm: ' . $product . '. ') : ''; ?></textarea></div>
        <label class="consent"><input type="checkbox" name="hpv_consent" value="1" required><span><?php echo esc_html($copy['consentText']); ?> <a href="<?php echo esc_url(hpv_page_url('privacy')); ?>"><?php echo esc_html($copy['privacyLabel']); ?></a></span></label>
        <button class="button button--orange form-submit" type="submit"><span><?php echo esc_html($copy['submitLabel']); ?></span><?php if (function_exists('hpv_icon')) { echo hpv_icon('arrow'); } ?></button>
        <div class="form-status <?php echo $status === 'success' ? 'is-success' : ''; ?>" role="status" aria-live="polite" tabindex="-1"><?php echo isset($messages[$status]) ? esc_html($messages[$status]) : ''; ?></div>
        <noscript><p>Form vẫn gửi được khi tắt JavaScript. Bạn sẽ được chuyển tới trang thông báo kết quả.</p></noscript>
    </form>
    <?php
}

function hpv_nonce_refresh() {
    nocache_headers();
    wp_send_json_success(array('nonce' => wp_create_nonce('hpv_quote')));
}
add_action('wp_ajax_hpv_form_nonce', 'hpv_nonce_refresh');
add_action('wp_ajax_nopriv_hpv_form_nonce', 'hpv_nonce_refresh');

function hpv_quote_response($status, $http = 200) {
    $messages = hpv_form_messages();
    if (isset($_POST['hpv_ajax']) && $_POST['hpv_ajax'] === '1') {
        $data = array('message' => isset($messages[$status]) ? $messages[$status] : $messages['error'], 'status' => $status);
        if ($status === 'success') { wp_send_json_success($data, $http); }
        wp_send_json_error($data, $http);
    }
    $return = isset($_POST['hpv_return']) && is_string($_POST['hpv_return']) ? wp_unslash($_POST['hpv_return']) : hpv_page_url('contact');
    $return = wp_validate_redirect($return, hpv_page_url('contact'));
    wp_safe_redirect(add_query_arg('hpv_status', $status, remove_query_arg('hpv_status', $return)) . '#quote-result');
    exit;
}

function hpv_contact_value($key, $max = 200) {
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) { return ''; }
    $value = wp_unslash($_POST[$key]);
    $value = $key === 'hpv_message' ? sanitize_textarea_field($value) : sanitize_text_field($value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function hpv_handle_quote() {
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') { hpv_quote_response('invalid', 405); }
    if (!wp_verify_nonce(hpv_contact_value('hpv_nonce'), 'hpv_quote')) { hpv_quote_response('expired', 403); }
    if (hpv_contact_value('company_website') !== '') { hpv_quote_response('invalid', 422); }
    $name = hpv_contact_value('hpv_name', 100);
    $phone = hpv_contact_value('hpv_phone', 24);
    $digits = preg_replace('/\D/', '', $phone);
    if (!$name || !preg_match('/^\+?[0-9() .-]+$/', $phone) || strlen($digits) < 9 || strlen($digits) > 15 || hpv_contact_value('hpv_consent') !== '1') { hpv_quote_response('invalid', 422); }
    $token = hpv_contact_value('hpv_token', 50);
    if (!preg_match('/^[a-f0-9-]{36}$/i', $token)) { hpv_quote_response('invalid', 422); }
    $need = hpv_contact_value('hpv_need');
    $location = hpv_contact_value('hpv_location');
    $message = hpv_contact_value('hpv_message', 3000);
    // Cached pages can share a hidden token: only deduplicate the same payload.
    $dedupe = 'hpv_sent_' . hash_hmac('sha256', implode("\0", array($token, $name, $phone, $need, $location, $message)), wp_salt('nonce'));
    if (get_transient($dedupe)) { hpv_quote_response('success'); }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $rate_key = 'hpv_rate_' . hash_hmac('sha256', $ip, wp_salt('nonce'));
    if (get_transient($rate_key)) { hpv_quote_response('limited', 429); }
    $body = "Họ tên: {$name}\nĐiện thoại: {$phone}\nQuan tâm: {$need}\nKhu vực: {$location}\n\nNội dung:\n{$message}\n\nKhách đã đồng ý cho phép liên hệ tư vấn.\nThời gian: " . current_time('mysql');
    $post_id = wp_insert_post(wp_slash(array('post_type' => 'hpv_request', 'post_status' => 'private', 'post_title' => $name . ' — ' . $phone, 'post_content' => $body, 'meta_input' => array('_hpv_phone' => $phone, '_hpv_need' => $need, '_hpv_consent' => '1'))), true);
    if (is_wp_error($post_id) || !$post_id) { hpv_quote_response('error', 500); }
    set_transient($rate_key, 1, MINUTE_IN_SECONDS);
    set_transient($dedupe, $post_id, DAY_IN_SECONDS);
    $mail_sent = wp_mail(hpv_get('recipient'), '[Hải Phát Vina] Yêu cầu báo giá từ ' . $name, $body . "\n\nXem trong quản trị: " . admin_url('post.php?post=' . $post_id . '&action=edit'));
    update_post_meta($post_id, '_hpv_mail_sent', $mail_sent ? '1' : '0');
    hpv_quote_response('success');
}
add_action('admin_post_hpv_quote', 'hpv_handle_quote');
add_action('admin_post_nopriv_hpv_quote', 'hpv_handle_quote');

/** Usable in the native editor and after switching to a different theme. */
function hpv_quote_shortcode($attributes = array()) {
    $attributes = shortcode_atts(array('variant' => 'full'), $attributes, 'hpv_quote_form');
    ob_start();
    hpv_quote_form(sanitize_key($attributes['variant']));
    return ob_get_clean();
}
add_shortcode('hpv_quote_form', 'hpv_quote_shortcode');

}

function hpv_register_contact_assets() {
    $base = HPV_CORE_URL . 'assets/';
    wp_register_style('hpv-contact-form', $base . 'contact.css', array(), HPV_CORE_VERSION);
    wp_register_script('hpv-contact-form', $base . 'contact.js', array(), HPV_CORE_VERSION, true);
    wp_add_inline_script('hpv-contact-form', 'window.hpvContactConfig = ' . wp_json_encode(array('ajaxUrl' => admin_url('admin-ajax.php'))) . ';', 'before');
}
add_action('init', 'hpv_register_contact_assets');

function hpv_enqueue_contact_assets() {
    wp_enqueue_style('hpv-contact-form');
    wp_enqueue_script('hpv-contact-form');
}

/** Classic themes print their head before rendering a shortcode in page content. */
function hpv_contact_content_assets() {
    $post = get_queried_object();
    if ($post instanceof WP_Post && (has_shortcode($post->post_content, 'hpv_quote_form') || has_block('hpv/quote-form', $post->post_content))) {
        hpv_enqueue_contact_assets();
    }
}
add_action('wp_enqueue_scripts', 'hpv_contact_content_assets');
