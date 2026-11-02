<?php
defined('ABSPATH') || exit;

function hpv_admin_capability() { return defined('HPV_CORE_VERSION') ? 'hpv_manage_business' : 'manage_options'; }

function hpv_admin_menu() {
    $capability = hpv_admin_capability();
    add_menu_page('Quản lý Hải Phát Vina', 'Hải Phát Vina', $capability, 'hpv-settings', 'hpv_dashboard_page', 'dashicons-admin-home', 3);
    add_submenu_page('hpv-settings', 'Bắt đầu quản lý website', 'Bắt đầu', $capability, 'hpv-settings', 'hpv_dashboard_page');
    add_submenu_page('hpv-settings', 'Thông tin công ty', 'Thông tin công ty', $capability, 'hpv-company', 'hpv_settings_page');
    add_submenu_page('hpv-settings', 'Hướng dẫn sử dụng', 'Hướng dẫn sử dụng', $capability, 'hpv-handover', 'hpv_handover_page');
}
add_action('admin_menu', 'hpv_admin_menu');

function hpv_order_admin_submenus() {
    global $submenu;
    if (empty($submenu['hpv-settings'])) { return; }
    $priority = array('hpv-settings' => 0, 'hpv-company' => 1, 'edit.php?post_type=hpv_request' => 2, 'hpv-handover' => 3);
    usort($submenu['hpv-settings'], function ($a, $b) use ($priority) {
        return (isset($priority[$a[2]]) ? $priority[$a[2]] : 10) <=> (isset($priority[$b[2]]) ? $priority[$b[2]] : 10);
    });
}
add_action('admin_menu', 'hpv_order_admin_submenus', 100);

// Keep the original settings accessible during installation of the companion plugin.
if (!function_exists('hpv_register_settings')) {
    function hpv_register_settings() {
        register_setting('hpv_settings_group', 'hpv_settings', array('type' => 'array', 'sanitize_callback' => 'hpv_theme_sanitize_settings', 'default' => hpv_defaults()));
    }
    function hpv_theme_sanitize_settings($input) {
        $input = is_array($input) ? $input : array();
        $result = array();
        foreach (hpv_defaults() as $key => $default) {
            $value = isset($input[$key]) && is_scalar($input[$key]) ? $input[$key] : hpv_get($key);
            if (in_array($key, array('hero_image', 'about_image'), true)) { $result[$key] = absint($value); }
            elseif (in_array($key, array('email', 'recipient'), true)) { $result[$key] = sanitize_email($value); }
            elseif (in_array($key, array('description', 'hero_title', 'hero_text'), true)) { $result[$key] = sanitize_textarea_field($value); }
            elseif (strpos($key, 'map_embed_') === 0) { $result[$key] = hpv_map_embed_url((string) $value); if ($value !== '' && $result[$key] === '') { $result[$key] = hpv_get($key); add_settings_error('hpv_settings', $key, 'Bản đồ chưa hợp lệ; hệ thống đã giữ liên kết cũ.'); } }
            elseif ($key === 'zalo') { $result[$key] = preg_replace('/\D/', '', $value); }
            else { $result[$key] = sanitize_text_field($value); }
        }
        if (!is_email($result['recipient'])) { $result['recipient'] = hpv_get('recipient'); add_settings_error('hpv_settings', 'recipient', 'Email nhận yêu cầu chưa hợp lệ. Email cũ đã được giữ lại.'); }
        return $result;
    }
    add_action('admin_init', 'hpv_register_settings');
}

function hpv_admin_assets($hook) {
    if (strpos($hook, 'hpv-') === false) { return; }
    wp_enqueue_style('hpv-admin', get_template_directory_uri() . '/assets/css/admin.css', array(), HPV_VERSION);
}
add_action('admin_enqueue_scripts', 'hpv_admin_assets');

function hpv_admin_notice() {
    if (!current_user_can('manage_options')) { return; }
    if (!defined('HPV_CORE_VERSION')) {
        echo '<div class="notice notice-warning"><p><strong>Hải Phát Vina:</strong> Cài và kích hoạt plugin <strong>Hải Phát Vina Core</strong> đi kèm để sử dụng đầy đủ các khối chức năng và vai trò Quản lý website. <a href="' . esc_url(admin_url('plugin-install.php?tab=upload')) . '">Tải plugin lên</a>.</p></div>';
    } elseif (!get_option('hpv_block_setup_complete')) {
        echo '<div class="notice notice-info"><p><strong>Hải Phát Vina 2.0:</strong> Mở <a href="' . esc_url(admin_url('admin.php?page=hpv-settings')) . '">Bắt đầu quản lý website</a> để chuẩn bị các trang có thể chỉnh sửa trực quan.</p></div>';
    }
}
add_action('admin_notices', 'hpv_admin_notice');

function hpv_admin_task($title, $description, $url, $action) {
    echo '<article class="hpv-admin-task"><h2>' . esc_html($title) . '</h2><p>' . esc_html($description) . '</p><a class="button button-secondary" href="' . esc_url($url) . '">' . esc_html($action) . '</a></article>';
}

function hpv_dashboard_page() {
    if (!current_user_can(hpv_admin_capability())) { return; }
    $front = absint(get_option('page_on_front'));
    ?>
    <div class="wrap hpv-admin">
        <h1>Quản lý website Hải Phát Vina</h1>
        <p class="hpv-admin-intro">Chọn việc bạn muốn làm. Nội dung, hình ảnh và các khối trang được chỉnh ngay trên giao diện WordPress.</p>
        <?php if (isset($_GET['hpv_setup']) && $_GET['hpv_setup'] === 'done') : ?><div class="notice notice-success inline"><p>Các trang đã sẵn sàng để chỉnh sửa. Mở Trang chủ bên dưới để bắt đầu.</p></div><?php endif; ?>
        <div class="hpv-admin-task-grid">
            <?php
            hpv_admin_task('Sửa trang chủ', 'Thay banner, sửa tiêu đề và kéo các phần nội dung lên hoặc xuống trong Danh sách khối.', $front ? admin_url('post.php?post=' . $front . '&action=edit') : admin_url('edit.php?post_type=page'), 'Mở trang chủ');
            hpv_admin_task('Sửa các trang / tạo trang mới', 'Sửa Giới thiệu, Liên hệ hoặc tạo trang mới từ bộ mẫu Hải Phát Vina.', admin_url('edit.php?post_type=page'), 'Quản lý trang');
            if (current_user_can('edit_theme_options')) {
                hpv_admin_task('Logo, menu và đầu trang', 'Mở phần Đầu trang. Chọn logo hoặc Menu để thay ảnh, thêm liên kết và sắp xếp các mục.', hpv_site_editor_url('header'), 'Sửa đầu trang');
                hpv_admin_task('Chân trang và chính sách', 'Đổi nội dung, liên kết chính sách và bố cục chân trang dùng chung trên website.', hpv_site_editor_url('footer'), 'Sửa chân trang');
            }
            hpv_admin_task('Thông tin công ty', 'Cập nhật hotline, email, cơ sở và tài khoản ngân hàng một lần cho các vị trí dùng chung.', admin_url('admin.php?page=hpv-company'), 'Sửa thông tin');
            hpv_admin_task('Sản phẩm & công trình', 'Đăng sản phẩm, sửa giá / quy cách và bổ sung ảnh công trình thực tế.', admin_url('edit.php?post_type=hpv_product'), 'Quản lý sản phẩm');
            hpv_admin_task('Bài viết kiến thức', 'Viết bài, chọn ảnh đại diện, chỉnh tiêu đề H2/H3 và xem trước trước khi đăng.', admin_url('edit.php'), 'Quản lý bài viết');
            hpv_admin_task('Yêu cầu báo giá', 'Xem thông tin khách đã gửi để liên hệ tư vấn. Nội dung chỉ dành cho người được phân quyền.', admin_url('edit.php?post_type=hpv_request'), 'Xem yêu cầu');
            ?>
        </div>
        <p><a class="button" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer">Xem website</a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=hpv-handover')); ?>">Đọc hướng dẫn sử dụng</a></p>
        <?php if (current_user_can('manage_options')) : ?>
        <section class="hpv-admin-panel">
            <h2>Chuẩn bị bàn giao</h2>
            <?php if (!get_option('hpv_block_setup_complete')) : ?>
            <p>Nếu website đã có nội dung từ phiên bản cũ, chuyển các trang có sẵn sang block. Hệ thống lưu bản gốc trước khi chuyển, giữ nguyên thông tin công ty và nhập menu cũ sang Menu của trình sửa website.</p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="hpv_block_upgrade"><?php wp_nonce_field('hpv_block_upgrade'); ?><?php submit_button('Chuyển các trang hiện có sang chỉnh trực quan', 'primary', 'submit', false, defined('HPV_CORE_VERSION') ? array() : array('disabled' => 'disabled')); ?></form>
            <?php else : ?><p>Website đã được chuẩn bị cho trình sửa trực quan. Các lần cập nhật giao diện giữ nguyên nội dung khách đã chỉnh.</p><?php endif; ?>
            <?php if (!get_option('hpv_setup_complete')) : ?>
            <h3>Website mới chưa có nội dung</h3><p>Tạo các trang, menu, sản phẩm và bài viết minh họa. Dữ liệu sẵn có được giữ lại; ảnh và nội dung mẫu cần được rà soát trước khi sử dụng chính thức.</p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="hpv_setup_demo"><?php wp_nonce_field('hpv_setup_demo'); ?><?php submit_button('Tạo nội dung mẫu cho website mới', 'secondary', 'submit', false, defined('HPV_CORE_VERSION') ? array() : array('disabled' => 'disabled')); ?></form>
            <?php endif; ?>
            <?php if (get_option('hpv_block_migration_backup')) : ?><p><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=hpv_download_backup'), 'hpv_download_backup')); ?>">Tải bản nội dung gốc trước chuyển đổi (JSON)</a>. Đây là bản lưu nội dung chuyển đổi; bản sao lưu đầy đủ website cần bao gồm cả cơ sở dữ liệu và thư mục uploads.</p><?php endif; ?>
            <p>Tại <strong>Thành viên → Thêm mới</strong>, chọn vai trò <strong>Quản lý website</strong> cho người vận hành. Vai trò này được sửa nội dung, đầu/chân trang, thông tin công ty và xem yêu cầu; không được cài plugin hoặc sửa mã nguồn.</p>
        </section>
        <?php endif; ?>
    </div>
    <?php
}

function hpv_settings_field($key, $label, $type = 'text', $hint = '') {
    echo '<div class="hpv-setting-field"><label for="hpv-setting-' . esc_attr($key) . '">' . esc_html($label) . '</label>';
    if ($type === 'textarea') { echo '<textarea id="hpv-setting-' . esc_attr($key) . '" name="hpv_settings[' . esc_attr($key) . ']" rows="3">' . esc_textarea(hpv_get($key)) . '</textarea>'; }
    else { echo '<input type="' . esc_attr($type) . '" id="hpv-setting-' . esc_attr($key) . '" name="hpv_settings[' . esc_attr($key) . ']" value="' . esc_attr(hpv_get($key)) . '">'; }
    if ($hint) { echo '<p class="description">' . esc_html($hint) . '</p>'; }
    echo '</div>';
}

function hpv_settings_page() {
    if (!current_user_can(hpv_admin_capability())) { return; }
    ?>
    <div class="wrap hpv-admin"><h1>Thông tin công ty</h1><p class="hpv-admin-intro">Các khối Thông tin công ty, hotline và nút Zalo tự lấy dữ liệu từ đây. Chữ và ảnh trên các trang được sửa trực tiếp trong mục Trang.</p>
        <?php settings_errors(); ?>
        <form action="options.php" method="post"><?php settings_fields('hpv_settings_group'); ?>
            <section class="hpv-admin-panel"><h2>Doanh nghiệp & liên hệ</h2><div class="hpv-setting-grid"><?php
                hpv_settings_field('brand', 'Tên thương hiệu'); hpv_settings_field('tagline', 'Mô tả ngắn');
                hpv_settings_field('legal_name', 'Tên pháp lý'); hpv_settings_field('tax_id', 'Mã số thuế');
                hpv_settings_field('phone', 'Hotline', 'tel'); hpv_settings_field('zalo', 'Số Zalo', 'tel', 'Chỉ nhập số, ví dụ 0868201868.');
                hpv_settings_field('email', 'Email hiển thị', 'email'); hpv_settings_field('recipient', 'Email nhận yêu cầu báo giá', 'email');
            ?></div></section>
            <section class="hpv-admin-panel"><h2>Các cơ sở</h2><p>Trên Google Maps, chọn cơ sở → Chia sẻ → Nhúng bản đồ → Sao chép HTML. Dán mã đó hoặc riêng đường dẫn nhúng vào ô tương ứng. Khi chưa có mã, nút chỉ đường sẽ mở Google Maps theo địa chỉ đã nhập.</p><div class="hpv-setting-grid"><?php for ($i = 1; $i <= 4; $i++) { hpv_settings_field('address_' . $i, 'Địa chỉ cơ sở ' . $i); hpv_settings_field('map_embed_' . $i, 'Mã nhúng Google Maps cơ sở ' . $i, 'textarea'); } ?></div></section>
            <section class="hpv-admin-panel"><h2>Thông tin chuyển khoản</h2><div class="hpv-setting-grid"><?php hpv_settings_field('bank_account', 'Số tài khoản'); hpv_settings_field('bank_name', 'Ngân hàng / chi nhánh'); ?></div></section>
            <section class="hpv-admin-panel"><h2>Mô tả website</h2><?php hpv_settings_field('description', 'Mô tả mặc định', 'textarea', 'Nếu đang dùng Rank Math, Yoast SEO hoặc AIOSEO, sửa mô tả tìm kiếm trong plugin SEO đó.'); ?><p>Tên website và mô tả dưới logo là các khối riêng trong <a href="<?php echo esc_url(hpv_site_editor_url('header')); ?>">Đầu trang</a>. Đổi tên thương hiệu ở đây không thay chữ bạn đã nhập trong nội dung trang.</p></section>
            <?php submit_button('Lưu thông tin công ty'); ?>
        </form>
    </div>
    <?php
}

function hpv_handover_page() {
    if (!current_user_can(hpv_admin_capability())) { return; }
    ?>
    <div class="wrap hpv-admin hpv-guide"><h1>Hướng dẫn sử dụng website</h1>
        <section class="hpv-admin-panel"><h2>Sửa chữ, ảnh và tiêu đề</h2><ol><li>Vào <strong>Trang</strong>, chọn trang và bấm <strong>Chỉnh sửa</strong>.</li><li>Bấm vào chữ để nhập; chọn ảnh rồi bấm <strong>Thay thế → Thư viện</strong> để đổi ảnh.</li><li>Chọn khối Tiêu đề để sửa nội dung hoặc cấp H2/H3. Tiêu đề chính của trang đã là H1; trang chủ có H1 trong banner.</li><li>Bấm <strong>Xem trước</strong>, kiểm tra máy tính và điện thoại rồi <strong>Lưu / Cập nhật</strong>.</li></ol></section>
        <section class="hpv-admin-panel"><h2>Thêm trang và kéo thả các phần</h2><ol><li>Vào <strong>Trang → Thêm trang mới</strong> và nhập tên trang.</li><li>Mở nút <strong>+ → Mẫu → Hải Phát Vina — Bố cục website</strong>. Chọn mẫu giới thiệu, liên hệ hoặc trang giới thiệu sản phẩm/dịch vụ.</li><li>Với trang nhiều phần toàn chiều rộng, mở cài đặt Trang → Mẫu giao diện và chọn <strong>Trang toàn chiều rộng</strong>.</li><li>Mở <strong>Danh sách khối</strong> ở góc trên bên trái. Các phần đã có tên như Banner, Sản phẩm, Giới thiệu; kéo cả phần để đổi thứ tự, hoặc dùng mũi tên lên/xuống.</li><li>Sửa chữ, ảnh và liên kết nút. Nếu mẫu có tiêu đề lớn, kiểm tra để trang chỉ có một H1.</li><li>Đăng trang, sau đó thêm liên kết vào Menu nếu cần hiển thị trong điều hướng.</li></ol></section>
        <section class="hpv-admin-panel"><h2>Đầu trang, menu và chân trang</h2><p>Từ <strong>Hải Phát Vina → Bắt đầu</strong>, chọn <strong>Sửa đầu trang</strong> hoặc <strong>Sửa chân trang</strong>. Chọn khối Menu để thêm trang, sửa tên mục, kéo đổi thứ tự hoặc tạo menu con. Logo dùng khối Logo website. Những phần này dùng chung nên thay đổi sẽ xuất hiện trên tất cả trang đang dùng chúng.</p><p>Số điện thoại, email và danh sách cơ sở trong khối <strong>Thông tin công ty</strong> được sửa tại <strong>Hải Phát Vina → Thông tin công ty</strong>. Trong thanh cài đặt của khối có nút mở đúng màn hình này.</p></section>
        <section class="hpv-admin-panel"><h2>Sản phẩm, công trình và bài viết</h2><p><strong>Sản phẩm → Thêm sản phẩm:</strong> nhập tên, mô tả, ảnh đại diện, nhóm và Thông tin sản phẩm. Giá bỏ trống sẽ hiển thị “Liên hệ báo giá”. Danh sách trên trang chủ và danh mục tự cập nhật.</p><p><strong>Công trình &amp; ứng dụng:</strong> đăng ảnh và thông tin thực tế. <strong>Bài viết:</strong> đăng bài kiến thức; có khung bài và bảng so sánh tại Mẫu → Hải Phát Vina — nội dung.</p><p>Ảnh minh họa đi kèm cần được thay bằng ảnh thật. Điền văn bản thay thế mô tả ảnh. Không nhập giá hoặc thời hạn bảo hành khi chưa xác nhận.</p></section>
        <section class="hpv-admin-panel"><h2>SEO và xuất bản</h2><p>Chia mục bằng H2, mục nhỏ bằng H3. Đặt tiêu đề và đường dẫn mô tả đúng nội dung. Nếu website có Rank Math hoặc Yoast SEO, dùng phần SEO của plugin để sửa tiêu đề tìm kiếm và mô tả; không cài nhiều plugin SEO có chức năng trùng nhau.</p><p>Lưu nháp khi chưa hoàn thiện. Sau khi cập nhật, mở trang ở ngoài website để kiểm tra lại. Nếu sửa nhầm nội dung, mở <strong>Bản sửa đổi / Revisions</strong> trong cài đặt trang để xem và khôi phục bản trước.</p></section>
        <section class="hpv-admin-panel"><h2>Yêu cầu báo giá và tài khoản</h2><p>Khách gửi form sẽ được lưu ở <strong>Hải Phát Vina → Yêu cầu báo giá</strong>. Quản lý website và Quản trị viên được xem; Biên tập viên/Tác giả thông thường không có quyền xem thông tin khách.</p><p>Email thông báo cần được người phụ trách kỹ thuật cấu hình SMTP và gửi thử. Đổi email nhận tại Thông tin công ty. Chủ website giữ tài khoản Quản trị viên và giao tài khoản Quản lý website cho người vận hành.</p></section>
    </div>
    <?php
}

function hpv_dashboard_widget() {
    if (current_user_can(hpv_admin_capability())) { wp_add_dashboard_widget('hpv-manage-site', 'Quản lý website Hải Phát Vina', 'hpv_dashboard_widget_content'); }
}
function hpv_dashboard_widget_content() {
    echo '<p>Sửa trang chủ, đầu/chân trang, sản phẩm và thông tin công ty từ một màn hình.</p><p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=hpv-settings')) . '">Mở quản lý website</a> <a href="' . esc_url(admin_url('admin.php?page=hpv-handover')) . '">Hướng dẫn sử dụng</a></p>';
}
add_action('wp_dashboard_setup', 'hpv_dashboard_widget');
