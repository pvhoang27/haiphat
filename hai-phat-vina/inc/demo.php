<?php
defined('ABSPATH') || exit;

/** Idempotent installer: only creates missing, explicitly keyed demo items. */
function hpv_seed_post($key, $data, $meta = array(), &$created = false) {
    $created = false;
    $ids = get_option('hpv_seed_ids', array());
    if (!empty($ids[$key]) && get_post($ids[$key])) { return (int) $ids[$key]; }
    $existing = get_page_by_path($data['post_name'], OBJECT, $data['post_type']);
    if ($existing) { return $existing->ID; }
    $id = wp_insert_post(wp_slash(array_merge(array('post_status' => 'publish'), $data)), true);
    if (is_wp_error($id)) { return 0; }
    $created = true;
    foreach ($meta as $name => $value) { update_post_meta($id, $name, $value); }
    $ids[$key] = $id;
    update_option('hpv_seed_ids', $ids, false);
    return $id;
}

function hpv_create_demo_content() {
    hpv_register_content();
    $categories = array('mai-che' => 'Mái xếp & mái hiên', 'bat-nhua' => 'Bạt nhựa che phủ', 'bat-hdpe' => 'Bạt HDPE lót hồ', 'du-che' => 'Dù che ngoài trời');
    $term_ids = array();
    foreach ($categories as $slug => $name) {
        $term = term_exists($slug, 'hpv_category');
        if (!$term) { $term = wp_insert_term($name, 'hpv_category', array('slug' => $slug)); }
        if (!is_wp_error($term)) { $term_ids[$slug] = (int) (is_array($term) ? $term['term_id'] : $term); }
    }
    $products = array(
        array('mai-xep-luon-song', 'Mái xếp lượn sóng', 'mai-che', 'hero', 'Giải pháp che nắng mưa cho sân vườn, quán ăn và không gian kinh doanh ngoài trời.', 'Khung và bạt theo phương án lựa chọn', 'Sân vườn, quán cà phê, nhà hàng'),
        array('mai-hien-di-dong', 'Mái hiên di động', 'mai-che', 'awning', 'Mái hiên có thể thu gọn, phù hợp mặt tiền cửa hàng, ban công và hiên nhà.', 'Quy cách tay quay / cơ cấu theo nhu cầu', 'Mặt tiền, hiên nhà, cửa hàng'),
        array('bat-nhua-xanh-cam', 'Bạt nhựa xanh cam', 'bat-nhua', 'tarpaulin', 'Bạt che phủ hàng hóa, vật tư và khu vực thi công với kích thước theo nhu cầu.', 'Trao đổi định lượng, khổ bạt và kiểu gia công', 'Che hàng hóa, vật liệu, công trình'),
        array('bat-che-phu-cong-trinh', 'Bạt che phủ công trình', 'bat-nhua', 'construction', 'Lựa chọn bạt che phù hợp diện tích, cách cố định và điều kiện sử dụng thực tế.', 'Chọn chất liệu và vị trí khoen theo yêu cầu', 'Kho bãi, công trình, khu sản xuất'),
        array('bat-hdpe-lot-ho', 'Bạt HDPE lót hồ', 'bat-hdpe', 'hdpe', 'Tham khảo phương án lót hồ chứa nước, ao nuôi và khu vực cần chống thấm.', 'Độ dày và phương án hàn theo thiết kế', 'Hồ chứa nước, ao nuôi thủy sản'),
        array('mang-chong-tham-hdpe', 'Màng chống thấm HDPE', 'bat-hdpe', 'liner', 'Trao đổi yêu cầu kỹ thuật để chọn màng chống thấm và phương án thi công phù hợp.', 'Xác nhận độ dày, nền và yêu cầu kỹ thuật', 'Chống thấm theo yêu cầu công trình'),
        array('du-lech-tam', 'Dù che lệch tâm', 'du-che', 'umbrella', 'Tạo khoảng che cho bàn ghế sân vườn với trụ đỡ nằm lệch khỏi tâm tán dù.', 'Kích thước tán, khung và đế theo lựa chọn', 'Quán cà phê, sân vườn, khu nghỉ ngơi'),
        array('du-che-san-vuon', 'Dù che sân vườn', 'du-che', 'parasol', 'Tham khảo mẫu dù và màu sắc phù hợp không gian ngoài trời của bạn.', 'Chọn mẫu tán, màu bạt và kiểu chân đế', 'Sân nhà, quán ăn, khu vực ngoài trời'),
    );
    foreach ($products as $i => $p) {
        $body = '<h2>Giải pháp phù hợp với không gian của bạn</h2><p>' . esc_html($p[4]) . '</p><p>Để chọn đúng sản phẩm, cần trao đổi về kích thước, mục đích sử dụng, vị trí lắp đặt và ngân sách dự kiến. Hải Phát Vina tiếp nhận thông tin qua hotline hoặc form báo giá trên website.</p><h2>Thông tin cần chuẩn bị</h2><ul><li>Kích thước chiều dài, chiều rộng và chiều cao (nếu có).</li><li>Ảnh mặt bằng hoặc vị trí dự kiến sử dụng.</li><li>Khu vực giao hàng / lắp đặt và thời gian mong muốn.</li><li>Yêu cầu về màu sắc, vật liệu và phương án gia công.</li></ul><h2>Báo giá và điều kiện cung cấp</h2><p>Báo giá được xác nhận theo quy cách, số lượng, địa điểm và yêu cầu thực tế. Hãy trao đổi rõ phần vật tư, vận chuyển, thi công, thuế và điều kiện bảo hành trước khi đặt hàng.</p><h2>Sử dụng và bảo quản</h2><p>Tuân thủ hướng dẫn của nhà sản xuất cho từng loại vật liệu và cơ cấu. Với sản phẩm lắp đặt ngoài trời, cần thống nhất phương án an toàn khi có gió mạnh và thời tiết bất lợi.</p>';
        $created = false;
        $id = hpv_seed_post('product_' . $p[0], array('post_type' => 'hpv_product', 'post_name' => $p[0], 'post_title' => $p[1], 'post_excerpt' => $p[4], 'post_content' => $body, 'menu_order' => $i), array('_hpv_image' => $p[3], '_hpv_material' => $p[5], '_hpv_application' => $p[6]), $created);
        if ($created && $id && isset($term_ids[$p[2]])) { wp_set_object_terms($id, array($term_ids[$p[2]]), 'hpv_category', true); }
    }
    $applications = array(
        array('mai-che-quan-ca-phe', 'Không gian quán cà phê', 'hero', 'Tham khảo cách bố trí mái che cho khu bàn ghế ngoài trời, cân bằng khoảng che và độ thoáng.'),
        array('ho-chua-nuoc-nong-nghiep', 'Hồ chứa nước & nông nghiệp', 'hdpe', 'Gợi ý các thông tin cần chuẩn bị khi lựa chọn bạt lót hồ và phương án xử lý nền.'),
        array('du-che-san-vuon', 'Sân vườn & khu nghỉ ngơi', 'umbrella', 'Chọn dù che theo vị trí bàn ghế, diện tích tán, chân đế và điều kiện gió thực tế.'),
    );
    foreach ($applications as $p) {
        hpv_seed_post('project_' . $p[0], array('post_type' => 'hpv_project', 'post_name' => $p[0], 'post_title' => $p[1], 'post_excerpt' => $p[3], 'post_content' => '<p>' . esc_html($p[3]) . '</p><h2>Những yếu tố cần khảo sát</h2><ul><li>Diện tích và điều kiện mặt bằng thực tế.</li><li>Nhu cầu sử dụng hằng ngày và cách vận hành.</li><li>Vật liệu, vị trí cố định và yêu cầu an toàn.</li><li>Ngân sách, địa điểm và thời gian dự kiến.</li></ul><p>Bài viết và hình ảnh này minh họa một hướng ứng dụng sản phẩm, chưa phải hồ sơ công trình đã thực hiện của Hải Phát Vina. Liên hệ để trao đổi phương án cho mặt bằng của bạn.</p>'), array('_hpv_image' => $p[2], '_hpv_demo' => '1'));
    }
    $articles = array(
        array('chon-mai-che-cho-quan-ca-phe', 'Chọn mái che cho quán cà phê: bắt đầu từ đâu?', 'hero', 'Từ hướng nắng, vị trí bàn ghế đến cách đóng mở: những điều nên trao đổi trước khi chọn mái che.', '<h2>Quan sát không gian trước khi chọn mẫu</h2><p>Ghi lại thời điểm nắng chiếu vào khu bàn ghế, hướng mưa tạt và các điểm có thể cố định kết cấu. Một vài tấm ảnh chụp toàn cảnh sẽ giúp việc tư vấn sát nhu cầu hơn.</p><h2>Xác định cách sử dụng</h2><p>Bạn muốn che thường xuyên hay cần thu gọn để lấy sáng? Lối đi, cây xanh, vị trí đèn và chiều cao thông thủy đều ảnh hưởng tới lựa chọn.</p><h2>So sánh báo giá trên cùng quy cách</h2><p>Yêu cầu ghi rõ vật liệu bạt, khung, phụ kiện, vận chuyển, lắp đặt và điều kiện bảo hành. Đơn giá trên mét vuông cần được đối chiếu với tổng diện tích và phạm vi công việc.</p>'),
        array('thong-tin-can-co-khi-dat-bat', 'Cần chuẩn bị gì khi đặt bạt theo kích thước?', 'tarpaulin', 'Một danh sách ngắn giúp bạn mô tả đúng nhu cầu và nhận báo giá có đủ quy cách.', '<h2>Ghi kích thước và mục đích sử dụng</h2><p>Gửi chiều dài, chiều rộng và số lượng tấm. Nêu rõ bạt được dùng để che hàng, phủ vật tư hay sử dụng ở một vị trí cố định.</p><h2>Trao đổi cách hoàn thiện mép</h2><p>Nếu cần viền, khoen hoặc nối tấm, hãy thống nhất vị trí và khoảng cách theo cách cố định thực tế. Phân biệt kích thước thành phẩm với khổ vật liệu ban đầu.</p><h2>Xác nhận trước khi đặt hàng</h2><p>Kiểm tra lại chất liệu, màu sắc, định lượng nếu có, số lượng, địa chỉ giao và những khoản chi phí trong báo giá.</p>'),
        array('luu-y-khi-chon-bat-lot-ho', 'Những điều cần trao đổi trước khi chọn bạt lót hồ', 'hdpe', 'Kích thước hồ, điều kiện nền và mục đích sử dụng là thông tin đầu vào cho phương án lót bạt.', '<h2>Đo mặt bằng và độ sâu</h2><p>Cần xác định hình dạng hồ, chiều dài, chiều rộng, độ sâu và các mép neo dự kiến. Diện tích bạt thực tế khác với diện tích mặt nước.</p><h2>Trao đổi điều kiện nền</h2><p>Nêu rõ loại nền, các vị trí có đá nhọn, đường ống xuyên bạt và điều kiện tiếp cận thi công. Phương án xử lý nền, độ dày và mối hàn cần được xác nhận theo thiết kế cụ thể.</p><h2>Thống nhất kiểm tra và bàn giao</h2><p>Yêu cầu mô tả vật liệu, phạm vi công việc, cách kiểm tra mối nối và điều kiện bảo hành trong thỏa thuận cung cấp.</p>'),
    );
    foreach ($articles as $p) { hpv_seed_post('article_' . $p[0], array('post_type' => 'post', 'post_name' => $p[0], 'post_title' => $p[1], 'post_excerpt' => $p[3], 'post_content' => $p[4]), array('_hpv_image' => $p[2])); }

    $privacy = '<h2>Thông tin được tiếp nhận</h2><p>Khi bạn gửi form tư vấn, website tiếp nhận họ tên, số điện thoại, sản phẩm quan tâm, khu vực và nội dung bạn tự cung cấp. Bạn có thể gọi hotline thay vì gửi form.</p><h2>Mục đích sử dụng</h2><p>Hải Phát Vina sử dụng thông tin để liên hệ theo yêu cầu, tư vấn quy cách và báo giá. Yêu cầu được lưu trong hệ thống quản trị và có thể được gửi tới email tiếp nhận của công ty.</p><h2>Quản lý thông tin</h2><p>Chỉ người quản trị được phân quyền mới được xem yêu cầu trong hệ thống. Bạn có thể liên hệ công ty để đề nghị kiểm tra, sửa hoặc xóa thông tin đã gửi. Việc xử lý và thời gian lưu giữ cần được xác nhận theo yêu cầu thực tế và nghĩa vụ áp dụng.</p><h2>Liên kết bên ngoài</h2><p>Liên kết Zalo và Google Maps đưa bạn sang dịch vụ bên ngoài. Các dịch vụ đó có chính sách riêng. Website không nhúng công cụ phân tích hoặc quảng cáo mặc định.</p><h2>Liên hệ</h2><p>Trao đổi yêu cầu về thông tin cá nhân qua email hoặc hotline công ty hiển thị ở cuối trang.</p>';
    $page_specs = array(
        'home' => array('trang-chu', 'Trang chủ', '', ''),
        'about' => array('gioi-thieu', 'Về Hải Phát Vina', 'page-about.php', '<p>Hải Phát Vina là thương hiệu của Công ty TNHH Thương Mại Dịch Vụ Hải Phát Vina, mã số thuế 0601326528. Website giới thiệu nhóm sản phẩm mái che, bạt công trình và các hướng ứng dụng để khách hàng tham khảo trước khi trao đổi nhu cầu cụ thể.</p><p>Hãy liên hệ để xác nhận danh mục đang cung cấp, quy cách, tình trạng hàng và phạm vi hỗ trợ tại khu vực của bạn.</p>'),
        'contact' => array('lien-he', 'Liên hệ Hải Phát Vina', 'page-contact.php', ''),
        'quote' => array('bao-gia', 'Thông tin & yêu cầu báo giá', 'page-quote.php', ''),
        'news' => array('kien-thuc', 'Kiến thức & kinh nghiệm', '', ''),
        'privacy' => array('chinh-sach-bao-mat', 'Chính sách bảo mật thông tin', '', $privacy),
    );
    $page_ids = get_option('hpv_page_ids', array());
    foreach ($page_specs as $key => $p) {
        $page_ids[$key] = hpv_seed_post('page_' . $key, array('post_type' => 'page', 'post_name' => $p[0], 'post_title' => $p[1], 'post_content' => $p[3]), $p[2] ? array('_wp_page_template' => $p[2]) : array());
    }
    update_option('hpv_page_ids', $page_ids, false);
    if (!get_option('page_on_front') && $page_ids['home']) {
        update_option('show_on_front', 'page'); update_option('page_on_front', $page_ids['home']);
    }
    if (!get_option('page_for_posts') && $page_ids['news']) { update_option('page_for_posts', $page_ids['news']); }
    if (get_option('blogname') === 'Just another WordPress site' || get_option('blogname') === 'My WordPress' || get_option('blogname') === '') { update_option('blogname', hpv_get('brand')); }
    $locations = get_theme_mod('nav_menu_locations', array());
    if (empty($locations['primary'])) {
        $menu = wp_get_nav_menu_object('Hải Phát Vina — Menu chính');
        $menu_id = $menu ? $menu->term_id : wp_create_nav_menu('Hải Phát Vina — Menu chính');
        if (!is_wp_error($menu_id)) {
            $items = wp_get_nav_menu_items($menu_id);
            if (!$items) {
                $links = array(array('Trang chủ', home_url('/')), array('Sản phẩm', hpv_products_url()), array('Báo giá', hpv_page_url('quote')), array('Ứng dụng', hpv_projects_url()), array('Về Hải Phát', hpv_page_url('about')), array('Kiến thức', hpv_page_url('news')), array('Liên hệ', hpv_page_url('contact')));
                foreach ($links as $i => $link) { wp_update_nav_menu_item($menu_id, 0, array('menu-item-title' => $link[0], 'menu-item-url' => $link[1], 'menu-item-status' => 'publish', 'menu-item-position' => $i + 1, 'menu-item-type' => 'custom')); }
            }
            $locations['primary'] = $menu_id; set_theme_mod('nav_menu_locations', $locations);
        }
    }
    update_option('hpv_setup_complete', HPV_VERSION, false);
    flush_rewrite_rules();
    return $page_ids;
}

function hpv_handle_setup() {
    if (!current_user_can('manage_options')) { wp_die('Bạn không có quyền thiết lập website.', '', array('response' => 403)); }
    check_admin_referer('hpv_setup_demo');
    hpv_create_demo_content();
    wp_safe_redirect(admin_url('admin.php?page=hpv-settings&hpv_setup=done')); exit;
}
add_action('admin_post_hpv_setup_demo', 'hpv_handle_setup');
