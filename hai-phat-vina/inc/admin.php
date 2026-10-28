<?php
defined('ABSPATH') || exit;

function hpv_admin_menu() {
    add_menu_page('Thiết lập Hải Phát Vina', 'Hải Phát Vina', 'manage_options', 'hpv-settings', 'hpv_settings_page', 'dashicons-admin-home', 3);
    add_submenu_page('hpv-settings', 'Thiết lập website', 'Thiết lập website', 'manage_options', 'hpv-settings', 'hpv_settings_page');
}
add_action('admin_menu', 'hpv_admin_menu');

function hpv_sanitize_settings($input) {
    $result = array();
    foreach (hpv_defaults() as $key => $default) {
        $value = isset($input[$key]) && is_scalar($input[$key]) ? $input[$key] : $default;
        if (in_array($key, array('hero_image', 'about_image'), true)) { $result[$key] = absint($value); }
        elseif (in_array($key, array('email', 'recipient'), true)) { $result[$key] = sanitize_email($value); }
        elseif (in_array($key, array('description', 'hero_text', 'hero_title'), true)) { $result[$key] = sanitize_textarea_field($value); }
        elseif ($key === 'zalo') { $result[$key] = preg_replace('/\D/', '', $value); }
        else { $result[$key] = sanitize_text_field($value); }
    }
    if (empty($result['recipient']) || !is_email($result['recipient'])) {
        $result['recipient'] = hpv_defaults()['recipient'];
        add_settings_error('hpv_settings', 'recipient', 'Email nhận yêu cầu không hợp lệ; đã dùng lại email mặc định.');
    }
    return $result;
}
function hpv_register_settings() {
    register_setting('hpv_settings_group', 'hpv_settings', array('type' => 'array', 'sanitize_callback' => 'hpv_sanitize_settings', 'default' => hpv_defaults()));
}
add_action('admin_init', 'hpv_register_settings');

function hpv_admin_assets($hook) {
    if ($hook !== 'toplevel_page_hpv-settings') { return; }
    wp_enqueue_media();
    wp_enqueue_style('hpv-admin', get_template_directory_uri() . '/assets/css/admin.css', array(), HPV_VERSION);
    wp_enqueue_script('hpv-admin', get_template_directory_uri() . '/assets/js/admin.js', array('jquery'), HPV_VERSION, true);
}
add_action('admin_enqueue_scripts', 'hpv_admin_assets');

function hpv_admin_notice() {
    if (!current_user_can('manage_options') || get_option('hpv_setup_complete')) { return; }
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_hpv-settings') { return; }
    echo '<div class="notice notice-info"><p><strong>Hải Phát Vina đã sẵn sàng.</strong> Mở <a href="' . esc_url(admin_url('admin.php?page=hpv-settings')) . '">Thiết lập website</a> để tạo trang, menu và nội dung mẫu bằng một lần bấm.</p></div>';
}
add_action('admin_notices', 'hpv_admin_notice');

function hpv_settings_field($key, $label, $type = 'text', $hint = '') {
    echo '<div class="hpv-setting-field"><label for="hpv-setting-' . esc_attr($key) . '">' . esc_html($label) . '</label>';
    if ($type === 'textarea') {
        echo '<textarea id="hpv-setting-' . esc_attr($key) . '" name="hpv_settings[' . esc_attr($key) . ']" rows="3">' . esc_textarea(hpv_get($key)) . '</textarea>';
    } else {
        echo '<input type="' . esc_attr($type) . '" id="hpv-setting-' . esc_attr($key) . '" name="hpv_settings[' . esc_attr($key) . ']" value="' . esc_attr(hpv_get($key)) . '">';
    }
    if ($hint) { echo '<p class="description">' . esc_html($hint) . '</p>'; }
    echo '</div>';
}

function hpv_settings_page() {
    if (!current_user_can('manage_options')) { return; }
    $done = get_option('hpv_setup_complete');
    ?>
    <div class="wrap hpv-admin">
        <h1>Website Hải Phát Vina</h1>
        <p class="hpv-admin-intro">Bố cục đã được code sẵn. Bạn chỉ cần cập nhật thông tin, thay ảnh và đăng sản phẩm.</p>
        <?php settings_errors(); ?>
        <?php if (isset($_GET['hpv_setup']) && $_GET['hpv_setup'] === 'done') : ?>
            <div class="notice notice-success inline"><p>Đã tạo nội dung và menu. <a href="<?php echo esc_url(home_url('/')); ?>">Xem website</a></p></div>
        <?php endif; ?>
        <section class="hpv-admin-panel">
            <h2><?php echo $done ? 'Nội dung đã sẵn sàng' : 'Bắt đầu trong một lần bấm'; ?></h2>
            <p>Bộ khởi tạo tạo trang chủ, giới thiệu, liên hệ, báo giá, kiến thức, chính sách bảo mật; 8 sản phẩm, 3 ứng dụng và 3 bài hướng dẫn mẫu. Các trang mẫu được xuất bản để website dùng được ngay.</p>
            <p>Nếu website đang có nội dung, dữ liệu cũ được giữ lại. Bộ khởi tạo chỉ đặt trang chủ / trang bài viết khi chưa cấu hình và chỉ gắn menu vào vị trí còn trống. Không nhập lặp nội dung đã tạo.</p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="hpv_setup_demo">
                <?php wp_nonce_field('hpv_setup_demo'); ?>
                <?php submit_button($done ? 'Kiểm tra / bổ sung nội dung mẫu còn thiếu' : 'Tạo website mẫu Hải Phát Vina', 'primary', 'submit', false); ?>
                <a class="button" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener">Xem website</a>
            </form>
            <p class="description">Ảnh đi kèm do AI tạo để minh họa sản phẩm; chưa phải ảnh công trình của công ty. Giá và cam kết bảo hành chưa được điền sẵn. Đọc lại nội dung trước khi giới thiệu website tới khách hàng.</p>
        </section>
        <form action="options.php" method="post">
            <?php settings_fields('hpv_settings_group'); ?>
            <section class="hpv-admin-panel">
                <h2>Thông tin công ty</h2><div class="hpv-setting-grid">
                <?php
                hpv_settings_field('brand', 'Tên thương hiệu'); hpv_settings_field('tagline', 'Mô tả ngắn dưới logo');
                hpv_settings_field('legal_name', 'Tên pháp lý'); hpv_settings_field('tax_id', 'Mã số thuế');
                hpv_settings_field('phone', 'Hotline'); hpv_settings_field('zalo', 'Số Zalo', 'text', 'Chỉ nhập số điện thoại, ví dụ 0868201868. Hãy xác nhận số này có sử dụng Zalo.');
                hpv_settings_field('email', 'Email hiển thị', 'email'); hpv_settings_field('recipient', 'Email nhận yêu cầu báo giá', 'email');
                hpv_settings_field('address_1', 'Cơ sở 1'); hpv_settings_field('address_2', 'Cơ sở 2'); hpv_settings_field('address_3', 'Cơ sở 3'); hpv_settings_field('address_4', 'Cơ sở 4');
                hpv_settings_field('bank_account', 'Số tài khoản'); hpv_settings_field('bank_name', 'Ngân hàng / chi nhánh');
                ?></div>
            </section>
            <section class="hpv-admin-panel">
                <h2>Trang chủ</h2><div class="hpv-setting-grid">
                <?php hpv_settings_field('hero_title', 'Tiêu đề chính', 'textarea', 'Xuống dòng để tách dòng tiêu đề.'); hpv_settings_field('hero_text', 'Lời giới thiệu', 'textarea'); hpv_settings_field('description', 'Mô tả SEO mặc định', 'textarea'); ?>
                <?php foreach (array('hero_image' => 'Ảnh đầu trang', 'about_image' => 'Ảnh phần giới thiệu') as $image_key => $image_label) : ?>
                <div class="hpv-setting-field"><label><?php echo esc_html($image_label); ?></label><input type="hidden" id="hpv-image-<?php echo esc_attr($image_key); ?>" name="hpv_settings[<?php echo esc_attr($image_key); ?>]" value="<?php echo esc_attr(hpv_get($image_key)); ?>"><div class="hpv-image-preview" id="hpv-preview-<?php echo esc_attr($image_key); ?>"><?php if (hpv_get($image_key)) { echo wp_get_attachment_image(hpv_get($image_key), 'medium'); } ?></div><p><button type="button" class="button hpv-choose-image" data-image-key="<?php echo esc_attr($image_key); ?>">Chọn ảnh từ thư viện</button> <button type="button" class="button hpv-clear-image" data-image-key="<?php echo esc_attr($image_key); ?>">Dùng ảnh minh họa</button></p></div>
                <?php endforeach; ?>
                </div>
                <p>Đổi logo tại <a href="<?php echo esc_url(admin_url('customize.php?autofocus[section]=title_tagline')); ?>">Giao diện → Tùy biến → Nhận diện website</a>. Logo ban đầu là nhận diện chữ và biểu tượng có sẵn trong theme.</p>
            </section>
            <?php submit_button('Lưu thông tin website'); ?>
        </form>
        <section class="hpv-admin-panel">
            <h2>Quản lý nội dung</h2>
            <div class="hpv-admin-links"><a href="<?php echo esc_url(admin_url('edit.php?post_type=hpv_product')); ?>">Sản phẩm & quy cách</a><a href="<?php echo esc_url(admin_url('edit.php?post_type=hpv_project')); ?>">Công trình & ứng dụng</a><a href="<?php echo esc_url(admin_url('edit.php')); ?>">Bài viết kiến thức</a><a href="<?php echo esc_url(admin_url('edit.php?post_type=hpv_request')); ?>">Yêu cầu đã nhận</a><a href="<?php echo esc_url(admin_url('nav-menus.php')); ?>">Menu</a><a href="<?php echo esc_url(admin_url('edit.php?post_type=page')); ?>">Các trang</a></div>
            <p>Form lưu yêu cầu trong WordPress trước khi thử gửi thông báo email. Để email đến ổn định, cấu hình SMTP theo dịch vụ email/hosting của bạn và gửi thử. Website không yêu cầu plugin để lưu form.</p>
            <p>Khi đổi sang theme khác, dữ liệu vẫn nằm trong cơ sở dữ liệu nhưng màn hình sản phẩm và yêu cầu của theme này sẽ tạm ẩn. Hãy sao lưu hoặc chuyển dữ liệu sang plugin nếu muốn thay theme.</p>
        </section>
    </div>
    <?php
}
