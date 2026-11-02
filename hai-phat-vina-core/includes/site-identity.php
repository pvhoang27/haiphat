<?php
/**
 * Native Site Title, Tagline and Site Logo blocks use /wp/v2/settings.
 * Core protects that entire endpoint with manage_options; there is no
 * per-setting REST capability. Expose only site identity to the handover role.
 */
defined('ABSPATH') || exit;

function hpv_site_identity_fields() {
    return array(
        'title' => array('option' => 'blogname', 'type' => 'string'),
        'description' => array('option' => 'blogdescription', 'type' => 'string'),
        'site_logo' => array('option' => 'site_logo', 'type' => 'integer', 'minimum' => 0),
        'site_icon' => array('option' => 'site_icon', 'type' => 'integer', 'minimum' => 0),
    );
}

function hpv_site_identity_response() {
    $data = array();
    foreach (hpv_site_identity_fields() as $name => $field) {
        $value = get_option($field['option'], $field['type'] === 'integer' ? 0 : '');
        $data[$name] = $field['type'] === 'integer' ? absint($value) : (string) $value;
    }
    return new WP_REST_Response($data, 200, array('Allow' => 'GET, HEAD, POST, PUT, PATCH, OPTIONS'));
}

function hpv_site_identity_rest($result, $server, $request) {
    if (null !== $result || !in_array($request->get_route(), array('/wp/v2/settings', '/wp/v2/settings/'), true)) { return $result; }
    // Leave the standard administrator endpoint and every other permission intact.
    if (current_user_can('manage_options') || !is_user_logged_in() || !current_user_can('hpv_manage_business') || !current_user_can('edit_theme_options')) { return $result; }

    $method = $request->get_method();
    if ($method === 'OPTIONS') {
        // Gutenberg checks Allow before making Site Title/Logo editable. Core's
        // default OPTIONS handler uses the broad manage_options permission.
        $properties = array();
        foreach (hpv_site_identity_fields() as $name => $field) {
            unset($field['option']);
            $properties[$name] = $field;
        }
        return new WP_REST_Response(array(
            'namespace' => 'wp/v2',
            'methods' => array('GET', 'HEAD', 'POST', 'PUT', 'PATCH'),
            'schema' => array('title' => 'settings', 'type' => 'object', 'properties' => $properties, 'additionalProperties' => false),
        ), 200, array('Allow' => 'GET, HEAD, POST, PUT, PATCH, OPTIONS'));
    }
    if (in_array($method, array('GET', 'HEAD'), true)) { return hpv_site_identity_response(); }
    if (!in_array($method, array('POST', 'PUT', 'PATCH'), true)) { return $result; }

    // Pre-dispatch runs before core parameter validation, so validate JSON here.
    $valid = $request->has_valid_params();
    if (is_wp_error($valid)) { return $valid; }
    $json = $request->get_json_params();
    if (null !== $json && !is_array($json)) {
        return new WP_Error('hpv_identity_invalid_body', 'Nội dung cập nhật nhận diện phải là một đối tượng JSON.', array('status' => 400));
    }
    if ($request->get_file_params()) {
        return new WP_Error('hpv_identity_invalid_upload', 'Chọn logo từ Thư viện; trường nhận diện chỉ nhận mã ảnh.', array('status' => 400));
    }

    $fields = hpv_site_identity_fields();
    $params = $request->get_params();
    $transport = array('_locale', '_fields', '_embed', '_envelope', '_pretty', '_jsonp', '_wpnonce', '_method', 'context', 'rest_route');
    $updates = array();

    // Reject mixed allowed/forbidden updates as a whole before any option changes.
    foreach ($params as $name => $value) {
        if (in_array($name, $transport, true)) { continue; }
        if (!isset($fields[$name])) {
            return new WP_Error('hpv_identity_forbidden_setting', 'Bạn chỉ được thay tên website, mô tả ngắn, logo và biểu tượng website.', array('status' => 403));
        }
        $field = $fields[$name];
        $valid = rest_validate_value_from_schema($value, $field, $name);
        if (is_wp_error($valid)) {
            return new WP_Error('hpv_identity_invalid_value', $valid->get_error_message(), array('status' => 400, 'param' => $name));
        }
        if ($field['type'] === 'integer') {
            $value = absint($value);
            if ($value && (!wp_attachment_is_image($value) || get_post_status($value) === 'trash' || !current_user_can('edit_post', $value))) {
                return new WP_Error('hpv_identity_invalid_image', 'Logo và biểu tượng phải là ảnh hợp lệ trong Thư viện mà bạn được phép sử dụng.', array('status' => 400, 'param' => $name));
            }
        } else {
            $value = sanitize_text_field($value);
        }
        $updates[$field['option']] = $value;
    }

    foreach ($updates as $option => $value) { update_option($option, $value); }
    return hpv_site_identity_response();
}
add_filter('rest_pre_dispatch', 'hpv_site_identity_rest', 9, 3);
