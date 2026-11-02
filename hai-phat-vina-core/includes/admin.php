<?php
defined('ABSPATH') || exit;

/** Keep the data screens usable when a different theme is active. */
function hpv_core_admin_menu() {
    if (function_exists('hpv_admin_menu')) { return; }
    add_menu_page('Thông tin Hải Phát Vina', 'Hải Phát Vina', 'hpv_manage_business', 'hpv-settings', 'hpv_core_settings_page', 'dashicons-admin-home', 3);
    add_submenu_page('hpv-settings', 'Thông tin công ty', 'Thông tin công ty', 'hpv_manage_business', 'hpv-company', 'hpv_core_settings_page');
}
add_action('admin_menu', 'hpv_core_admin_menu', 20);

function hpv_core_settings_page() {
    if (!current_user_can('hpv_manage_business')) { return; }
    $fields = array(
        'brand' => 'Tên thương hiệu', 'tagline' => 'Mô tả ngắn', 'legal_name' => 'Tên pháp lý',
        'tax_id' => 'Mã số thuế', 'phone' => 'Hotline', 'zalo' => 'Số điện thoại Zalo',
        'email' => 'Email hiển thị', 'recipient' => 'Email nhận yêu cầu báo giá',
        'address_1' => 'Cơ sở 1', 'address_2' => 'Cơ sở 2', 'address_3' => 'Cơ sở 3', 'address_4' => 'Cơ sở 4',
        'bank_account' => 'Số tài khoản', 'bank_name' => 'Ngân hàng / chi nhánh',
    );
    ?>
    <div class="wrap">
        <h1>Thông tin Hải Phát Vina</h1>
        <p>Thông tin công ty, sản phẩm, công trình và yêu cầu khách hàng được lưu trong plugin, tiếp tục sử dụng được khi đổi giao diện.</p>
        <?php settings_errors(); ?>
        <p>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=hpv_product')); ?>">Quản lý sản phẩm</a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=hpv_project')); ?>">Quản lý công trình</a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=hpv_request')); ?>">Yêu cầu đã nhận</a>
        </p>
        <form action="options.php" method="post">
            <?php settings_fields('hpv_settings_group'); ?>
            <table class="form-table" role="presentation">
                <?php foreach ($fields as $key => $label) : ?>
                    <tr>
                        <th scope="row"><label for="hpv-core-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td><input class="regular-text" id="hpv-core-<?php echo esc_attr($key); ?>" name="hpv_settings[<?php echo esc_attr($key); ?>]" type="<?php echo in_array($key, array('email', 'recipient'), true) ? 'email' : 'text'; ?>" value="<?php echo esc_attr(hpv_get($key)); ?>"></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button('Lưu thông tin công ty'); ?>
        </form>
        <h2>Form nhận yêu cầu báo giá</h2>
        <p>Chèn block <strong>Form báo giá Hải Phát</strong> hoặc shortcode <code>[hpv_quote_form]</code> vào trang. Yêu cầu được lưu riêng tư trước khi gửi thông báo email; cấu hình SMTP và gửi thử để kiểm tra việc nhận thư.</p>
        <p>Khi bàn giao tài khoản, chọn vai trò <strong>Quản lý website</strong> để chỉnh nội dung, giao diện và thông tin công ty. Vai trò này không có quyền cài plugin hay quản lý tài khoản khác.</p>
    </div>
    <?php
}
