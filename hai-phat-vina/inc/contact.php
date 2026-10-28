<?php
defined('ABSPATH') || exit;

function hpv_form_messages() {
    return array('success' => 'Đã nhận yêu cầu của bạn. Hải Phát Vina sẽ liên hệ theo số điện thoại bạn cung cấp.', 'invalid' => 'Vui lòng nhập họ tên, số điện thoại hợp lệ và đồng ý cho phép liên hệ.', 'expired' => 'Phiên gửi đã hết hạn. Vui lòng tải lại trang rồi gửi lại.', 'limited' => 'Bạn vừa gửi yêu cầu. Vui lòng đợi một phút hoặc gọi hotline nếu cần trao đổi ngay.', 'error' => 'Chưa lưu được yêu cầu. Vui lòng thử lại hoặc gọi hotline.');
}

function hpv_quote_form($variant = 'full') {
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
        <div class="form-trap" aria-hidden="true"><label for="<?php echo esc_attr($id); ?>-site">Không điền trường này</label><input type="text" id="<?php echo esc_attr($id); ?>-site" name="company_website" value="" tabindex="-1" autocomplete="off"></div>
        <div class="form-row"><div class="form-field"><label for="<?php echo esc_attr($id); ?>-name">Họ và tên <span aria-hidden="true">*</span></label><input id="<?php echo esc_attr($id); ?>-name" name="hpv_name" autocomplete="name" placeholder="Tên của bạn" required maxlength="100"></div>
        <div class="form-field"><label for="<?php echo esc_attr($id); ?>-phone">Số điện thoại <span aria-hidden="true">*</span></label><input id="<?php echo esc_attr($id); ?>-phone" name="hpv_phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="Số điện thoại để tư vấn" required maxlength="24"></div></div>
        <div class="form-row"><div class="form-field"><label for="<?php echo esc_attr($id); ?>-need">Bạn đang quan tâm</label><select id="<?php echo esc_attr($id); ?>-need" name="hpv_need"><option value="">Chọn sản phẩm / dịch vụ</option><?php foreach (array('Mái xếp & mái hiên', 'Bạt nhựa che phủ', 'Bạt HDPE lót hồ', 'Dù che ngoài trời', 'May ép bạt / phụ kiện', 'Cần tư vấn thêm') as $need) { echo '<option value="' . esc_attr($need) . '">' . esc_html($need) . '</option>'; } ?></select></div><div class="form-field"><label for="<?php echo esc_attr($id); ?>-location">Khu vực cần tư vấn</label><input id="<?php echo esc_attr($id); ?>-location" name="hpv_location" autocomplete="address-level2" placeholder="Quận / huyện, tỉnh / thành" maxlength="200"></div></div>
        <div class="form-field"><label for="<?php echo esc_attr($id); ?>-message">Nhu cầu của bạn</label><textarea id="<?php echo esc_attr($id); ?>-message" name="hpv_message" rows="3" placeholder="Ví dụ: mái xếp cho sân rộng 5 × 8 m, tại Hà Đông…" maxlength="3000"><?php echo $product ? esc_textarea('Tôi quan tâm: ' . $product . '. ') : ''; ?></textarea></div>
        <label class="consent"><input type="checkbox" name="hpv_consent" value="1" required><span>Tôi đồng ý để Hải Phát Vina sử dụng thông tin trên để liên hệ tư vấn. <a href="<?php echo esc_url(hpv_page_url('privacy')); ?>">Chính sách bảo mật</a></span></label>
        <button class="button button--orange form-submit" type="submit"><span>Gửi yêu cầu báo giá</span><?php echo hpv_icon('arrow'); ?></button>
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { hpv_quote_response('invalid', 405); }
    if (!wp_verify_nonce(hpv_contact_value('hpv_nonce'), 'hpv_quote')) { hpv_quote_response('expired', 403); }
    if (hpv_contact_value('company_website') !== '') { hpv_quote_response('invalid', 422); }
    $name = hpv_contact_value('hpv_name', 100);
    $phone = hpv_contact_value('hpv_phone', 24);
    $digits = preg_replace('/\D/', '', $phone);
    if (!$name || !preg_match('/^\+?[0-9() .-]+$/', $phone) || strlen($digits) < 9 || strlen($digits) > 15 || hpv_contact_value('hpv_consent') !== '1') { hpv_quote_response('invalid', 422); }
    $token = hpv_contact_value('hpv_token', 50);
    if (!preg_match('/^[a-f0-9-]{36}$/i', $token)) { hpv_quote_response('invalid', 422); }
    $dedupe = 'hpv_sent_' . md5($token);
    if (get_transient($dedupe)) { hpv_quote_response('success'); }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $rate_key = 'hpv_rate_' . hash_hmac('sha256', $ip, wp_salt('nonce'));
    if (get_transient($rate_key)) { hpv_quote_response('limited', 429); }
    $need = hpv_contact_value('hpv_need');
    $location = hpv_contact_value('hpv_location');
    $message = hpv_contact_value('hpv_message', 3000);
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
