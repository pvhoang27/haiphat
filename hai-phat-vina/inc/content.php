<?php
defined('ABSPATH') || exit;

function hpv_register_content() {
    register_post_type('hpv_product', array(
        'labels' => array('name' => 'Sản phẩm', 'singular_name' => 'Sản phẩm', 'add_new' => 'Thêm sản phẩm', 'add_new_item' => 'Thêm sản phẩm', 'edit_item' => 'Sửa sản phẩm', 'all_items' => 'Tất cả sản phẩm'),
        'public' => true, 'show_in_rest' => true, 'has_archive' => 'san-pham', 'rewrite' => array('slug' => 'san-pham', 'with_front' => false), 'menu_icon' => 'dashicons-store',
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'), 'taxonomies' => array('hpv_category'),
    ));
    register_taxonomy('hpv_category', array('hpv_product'), array(
        'labels' => array('name' => 'Nhóm sản phẩm', 'singular_name' => 'Nhóm sản phẩm'), 'hierarchical' => true, 'public' => true, 'show_in_rest' => true, 'show_admin_column' => true, 'rewrite' => array('slug' => 'nhom-san-pham', 'with_front' => false),
    ));
    register_post_type('hpv_project', array(
        'labels' => array('name' => 'Công trình & ứng dụng', 'singular_name' => 'Công trình / ứng dụng', 'add_new_item' => 'Thêm công trình / ứng dụng', 'edit_item' => 'Sửa công trình / ứng dụng'),
        'public' => true, 'show_in_rest' => true, 'has_archive' => 'ung-dung', 'rewrite' => array('slug' => 'ung-dung', 'with_front' => false), 'menu_icon' => 'dashicons-building',
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'),
    ));
    register_post_type('hpv_request', array(
        'labels' => array('name' => 'Yêu cầu báo giá', 'singular_name' => 'Yêu cầu báo giá', 'edit_item' => 'Xem yêu cầu', 'all_items' => 'Yêu cầu đã nhận'),
        'public' => false, 'publicly_queryable' => false, 'show_ui' => true, 'show_in_rest' => false, 'exclude_from_search' => true, 'show_in_nav_menus' => false, 'show_in_menu' => 'hpv-settings', 'supports' => array('title'), 'map_meta_cap' => true,
        'capability_type' => array('hpv_request', 'hpv_requests'),
        'capabilities' => array('edit_post' => 'edit_hpv_request', 'read_post' => 'read_hpv_request', 'delete_post' => 'delete_hpv_request', 'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options', 'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_private_posts' => 'manage_options', 'delete_published_posts' => 'manage_options', 'delete_others_posts' => 'manage_options', 'edit_private_posts' => 'manage_options', 'edit_published_posts' => 'manage_options', 'create_posts' => 'do_not_allow'),
    ));
}
add_action('init', 'hpv_register_content');

function hpv_meta_boxes() {
    add_meta_box('hpv-product-details', 'Thông tin sản phẩm', 'hpv_product_meta_box', 'hpv_product', 'normal', 'high');
    add_meta_box('hpv-project-details', 'Thông tin công trình / ứng dụng', 'hpv_project_meta_box', 'hpv_project', 'side');
    add_meta_box('hpv-request-details', 'Nội dung khách hàng đã gửi', 'hpv_request_meta_box', 'hpv_request', 'normal', 'high');
}
add_action('add_meta_boxes', 'hpv_meta_boxes');

function hpv_product_meta_box($post) {
    wp_nonce_field('hpv_save_product', 'hpv_product_nonce');
    $fields = array('price' => 'Giá hiển thị (để trống = Liên hệ báo giá)', 'unit' => 'Đơn vị (ví dụ: m², bộ, cuộn)', 'material' => 'Chất liệu / quy cách', 'application' => 'Ứng dụng phù hợp', 'warranty' => 'Chính sách bảo hành cụ thể (nếu đã xác nhận)');
    echo '<p>Thay ảnh minh họa bằng <strong>Ảnh đại diện</strong> của sản phẩm. Giá là văn bản để bạn ghi rõ điều kiện áp dụng.</p>';
    foreach ($fields as $key => $label) {
        echo '<p><label for="hpv-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br><input class="widefat" id="hpv-' . esc_attr($key) . '" name="hpv_' . esc_attr($key) . '" value="' . esc_attr(get_post_meta($post->ID, '_hpv_' . $key, true)) . '"></p>';
    }
}

function hpv_project_meta_box($post) {
    wp_nonce_field('hpv_save_project', 'hpv_project_nonce');
    echo '<p><label><input type="checkbox" name="hpv_demo" value="1" ' . checked(get_post_meta($post->ID, '_hpv_demo', true), '1', false) . '> Đây là ứng dụng minh họa</label></p><p>Bỏ chọn khi đăng công trình thật và bổ sung ảnh, địa điểm, quy cách, thời gian thực hiện trong nội dung bài.</p>';
}

function hpv_save_content_meta($post_id) {
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) { return; }
    if (get_post_type($post_id) === 'hpv_product' && isset($_POST['hpv_product_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hpv_product_nonce'])), 'hpv_save_product')) {
        foreach (array('price', 'unit', 'material', 'application', 'warranty') as $key) {
            if (isset($_POST['hpv_' . $key]) && is_string($_POST['hpv_' . $key])) { update_post_meta($post_id, '_hpv_' . $key, sanitize_text_field(wp_unslash($_POST['hpv_' . $key]))); }
        }
    }
    if (get_post_type($post_id) === 'hpv_project' && isset($_POST['hpv_project_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hpv_project_nonce'])), 'hpv_save_project')) {
        update_post_meta($post_id, '_hpv_demo', isset($_POST['hpv_demo']) ? '1' : '0');
    }
}
add_action('save_post', 'hpv_save_content_meta');

function hpv_request_meta_box($post) {
    echo '<div style="white-space:pre-wrap;line-height:1.8">' . esc_html($post->post_content) . '</div>';
    $sent = get_post_meta($post->ID, '_hpv_mail_sent', true);
    echo '<hr><p><strong>Thông báo email:</strong> ' . ($sent === '1' ? 'Máy chủ đã tiếp nhận lệnh gửi. Hãy kiểm tra hộp thư / thư rác để xác nhận nhận được.' : 'Chưa gửi được. Yêu cầu vẫn được lưu tại đây; kiểm tra cấu hình SMTP trên hosting.') . '</p>';
}

function hpv_request_columns($columns) {
    return array('cb' => isset($columns['cb']) ? $columns['cb'] : '', 'title' => 'Khách hàng', 'hpv_phone' => 'Số điện thoại', 'hpv_need' => 'Sản phẩm quan tâm', 'date' => 'Ngày nhận');
}
add_filter('manage_hpv_request_posts_columns', 'hpv_request_columns');
function hpv_request_column($column, $post_id) {
    if ($column === 'hpv_phone') { echo esc_html(get_post_meta($post_id, '_hpv_phone', true)); }
    if ($column === 'hpv_need') { echo esc_html(get_post_meta($post_id, '_hpv_need', true)); }
}
add_action('manage_hpv_request_posts_custom_column', 'hpv_request_column', 10, 2);

function hpv_archive_query($query) {
    if (is_admin() || !$query->is_main_query()) { return; }
    if ($query->is_post_type_archive('hpv_product') || $query->is_tax('hpv_category')) {
        $query->set('posts_per_page', 12);
        $query->set('orderby', array('menu_order' => 'ASC', 'date' => 'DESC'));
        if ($query->is_post_type_archive('hpv_product') && !empty($_GET['nhom']) && is_string($_GET['nhom'])) {
            $query->set('tax_query', array(array('taxonomy' => 'hpv_category', 'field' => 'slug', 'terms' => sanitize_title(wp_unslash($_GET['nhom'])))));
        }
    }
    if ($query->is_post_type_archive('hpv_project')) { $query->set('posts_per_page', 9); }
}
add_action('pre_get_posts', 'hpv_archive_query');
add_filter('get_the_archive_title_prefix', '__return_empty_string');
