<?php
/** Native WordPress editorial tools; no extra editor or SEO plugin is required to write. */
defined('ABSPATH') || exit;

function hpv_reading_minutes($post_id = 0) {
    $post_id = $post_id ?: get_the_ID();
    $text = trim(wp_strip_all_tags(strip_shortcodes(get_post_field('post_content', $post_id))));
    $words = $text === '' ? array() : preg_split('/\s+/u', $text);
    return max(1, (int) ceil(count($words) / 250));
}

function hpv_category_label($category) {
    return $category->slug === 'uncategorized' ? 'Kiến thức lựa chọn' : $category->name;
}

function hpv_editor_menu() {
    add_submenu_page('edit.php', 'Hướng dẫn viết bài & SEO', 'Hướng dẫn viết bài & SEO', 'edit_posts', 'hpv-editor-guide', 'hpv_editor_guide');
}
add_action('admin_menu', 'hpv_editor_menu');

function hpv_editor_guide() {
    if (!current_user_can('edit_posts')) { return; }
    ?>
    <div class="wrap" style="max-width:900px;line-height:1.9">
        <h1>Viết bài cho Hải Phát Vina</h1>
        <p>Website dùng trình soạn thảo block có sẵn của WordPress. Nội dung được xuất bản sẽ tự xuất hiện ở trang Kiến thức và khu vực bài mới trên trang chủ.</p>
        <p><a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php')); ?>">Viết bài mới</a> <a class="button" href="<?php echo esc_url(admin_url('edit.php')); ?>">Quản lý bài viết</a></p>
        <h2>Quy trình đăng bài</h2>
        <ol><li>Mở <strong>Bài viết → Viết bài mới</strong>, đặt tiêu đề cụ thể theo nhu cầu người đọc.</li><li>Viết đoạn giới thiệu ngắn. Chia phần nội dung bằng Tiêu đề H2 và H3; tiêu đề bài đã là H1.</li><li>Chọn chuyên mục, ảnh đại diện, điền đoạn tóm tắt và đường dẫn ngắn gọn.</li><li>Thêm văn bản thay thế mô tả đúng ảnh. Chèn liên kết tới sản phẩm hoặc bài liên quan khi có ích.</li><li>Xem trước trên máy tính và điện thoại. Lưu nháp, gửi duyệt hoặc đăng / đặt lịch tùy quyền tài khoản.</li></ol>
        <h2>Mẫu nội dung dùng ngay</h2>
        <p>Trong trình soạn thảo, mở nút <strong>+</strong>, chọn <strong>Mẫu / Patterns → Hải Phát Vina — nội dung</strong>. Có sẵn khung bài hướng dẫn, bảng so sánh và khối liên hệ cuối bài. Các phần đều sửa bằng block WordPress thông thường.</p>
        <p>Bài có từ hai tiêu đề H2/H3 sẽ được tạo mục lục trên giao diện khi bật JavaScript. Thời gian đọc hiển thị là ước tính theo độ dài nội dung.</p>
        <h2>SEO cho từng bài</h2>
        <p>Theme có cấu trúc tiêu đề, trang chuyên mục, đường dẫn, mô tả cơ bản và giao diện bài đọc. Nếu cần ô chỉnh SEO title, meta description, từ khóa mục tiêu và xem trước kết quả tìm kiếm, quản trị viên có thể cài <strong>một</strong> plugin như Yoast SEO hoặc Rank Math.</p>
        <p>Plugin SEO được cấu hình trong quản trị và có thể yêu cầu quyền riêng. Theme tự ngừng xuất mô tả / schema cơ bản khi phát hiện Yoast SEO, Rank Math hoặc AIOSEO để tránh trùng lặp. Không có cam kết tự động tăng thứ hạng.</p>
        <h2>Phân quyền cho nhóm</h2>
        <ul><li><strong>Biên tập viên / Editor:</strong> quản lý và xuất bản bài của nhóm.</li><li><strong>Tác giả / Author:</strong> viết, tải ảnh và xuất bản bài của mình.</li><li><strong>Cộng tác viên / Contributor:</strong> viết nháp, gửi người có quyền duyệt.</li></ul>
        <p>Quản trị viên tạo tài khoản tại <strong>Thành viên → Thêm mới</strong>. Không cần cấp quyền quản trị chỉ để viết bài. Yêu cầu báo giá riêng tư của khách vẫn chỉ dành cho tài khoản có quyền quản lý cài đặt.</p>
        <p>Giờ đăng theo múi giờ tại <strong>Cài đặt → Tổng quan</strong>. Đăng theo lịch phụ thuộc cơ chế tác vụ của WordPress / hosting.</p>
        <h2>Nội dung trước khi đăng</h2>
        <p>Xác nhận giá, quy cách, chính sách bảo hành và nguồn ảnh. Nội dung mẫu và ảnh minh họa trong theme cần được thay hoặc rà soát theo thông tin thực tế của công ty.</p>
        <p>Tài liệu: <a href="https://wordpress.org/documentation/article/wordpress-block-editor/" target="_blank" rel="noopener noreferrer">Trình soạn thảo WordPress</a> · <a href="https://wordpress.org/documentation/article/roles-and-capabilities/" target="_blank" rel="noopener noreferrer">Vai trò & phân quyền</a></p>
    </div>
    <?php
}

function hpv_register_editor_patterns() {
    if (!function_exists('register_block_pattern')) { return; }
    register_block_pattern_category('hai-phat-vina', array('label' => 'Hải Phát Vina — nội dung'));
    register_block_pattern('hai-phat-vina/guide', array(
        'title' => 'Khung bài hướng dẫn lựa chọn',
        'description' => 'Giới thiệu, nhu cầu, quy cách, điều kiện báo giá và câu hỏi thường gặp.',
        'categories' => array('hai-phat-vina'),
        'content' => '<!-- wp:paragraph {"fontSize":"large"} --><p class="has-large-font-size">Giới thiệu nhu cầu thực tế mà bài viết sẽ giúp người đọc giải quyết.</p><!-- /wp:paragraph -->' .
            '<!-- wp:heading --><h2 class="wp-block-heading">Sản phẩm phù hợp với nhu cầu nào?</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Mô tả mục đích sử dụng, mặt bằng và những điều cần cân nhắc.</p><!-- /wp:paragraph -->' .
            '<!-- wp:heading --><h2 class="wp-block-heading">Cần lựa chọn những quy cách gì?</h2><!-- /wp:heading --><!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Kích thước và số lượng.</li><!-- /wp:list-item --><!-- wp:list-item --><li>Vật liệu và điều kiện sử dụng.</li><!-- /wp:list-item --><!-- wp:list-item --><li>Phạm vi vận chuyển hoặc lắp đặt.</li><!-- /wp:list-item --></ul><!-- /wp:list -->' .
            '<!-- wp:heading --><h2 class="wp-block-heading">Thông tin giá và điều kiện cung cấp</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Điền báo giá đã xác nhận, đơn vị tính, điều kiện áp dụng và thời điểm cập nhật. Nếu chưa có giá, dẫn người đọc tới form tư vấn.</p><!-- /wp:paragraph -->' .
            '<!-- wp:heading --><h2 class="wp-block-heading">Câu hỏi thường gặp</h2><!-- /wp:heading --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Khách cần chuẩn bị thông tin gì?</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Trả lời ngắn gọn dựa trên quy trình cung cấp thực tế.</p><!-- /wp:paragraph -->',
    ));
    register_block_pattern('hai-phat-vina/comparison', array(
        'title' => 'Bảng so sánh vật liệu / phương án', 'categories' => array('hai-phat-vina'),
        'content' => '<!-- wp:heading --><h2 class="wp-block-heading">So sánh phương án</h2><!-- /wp:heading --><!-- wp:table --><figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th>Tiêu chí</th><th>Phương án A</th><th>Phương án B</th></tr></thead><tbody><tr><td>Mục đích sử dụng</td><td>Điền thông tin đã xác nhận</td><td>Điền thông tin đã xác nhận</td></tr><tr><td>Quy cách vật liệu</td><td>Điền thông số</td><td>Điền thông số</td></tr><tr><td>Điều kiện cung cấp</td><td>Điền phạm vi</td><td>Điền phạm vi</td></tr></tbody></table></figure><!-- /wp:table -->',
    ));
    register_block_pattern('hai-phat-vina/contact', array(
        'title' => 'Liên hệ tư vấn cuối bài', 'categories' => array('hai-phat-vina'),
        'content' => '<!-- wp:group {"backgroundColor":"mist","layout":{"type":"constrained"}} --><div class="wp-block-group has-mist-background-color has-background"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Cần một phương án cho không gian của bạn?</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Gửi kích thước, vị trí và nhu cầu sử dụng để cùng Hải Phát Vina trao đổi quy cách.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"sun","textColor":"forest"} --><div class="wp-block-button"><a class="wp-block-button__link has-forest-color has-sun-background-color has-text-color has-background wp-element-button" href="' . esc_url(hpv_page_url('contact')) . '">Liên hệ Hải Phát Vina</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->',
    ));
}
add_action('init', 'hpv_register_editor_patterns', 15);
