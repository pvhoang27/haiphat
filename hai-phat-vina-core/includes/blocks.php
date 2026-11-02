<?php
/** Business blocks remain available when the site changes its theme. */
defined('ABSPATH') || exit;

function hpv_register_business_blocks() {
    $base = plugin_dir_url(dirname(__FILE__));
    wp_register_script('hpv-business-blocks', $base . 'assets/blocks.js', array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n'), HPV_CORE_VERSION, true);
    wp_localize_script('hpv-business-blocks', 'hpvBlockEditor', array('settingsUrl' => admin_url('admin.php?page=hpv-company')));
    $definitions = array(
        'company-info' => array('title' => 'Thông tin công ty', 'attributes' => array('layout' => array('type' => 'string', 'default' => 'contact', 'enum' => array('contact', 'footer', 'branches', 'bank', 'phone')))),
        'quote-form' => array('title' => 'Form yêu cầu báo giá', 'attributes' => array('variant' => array('type' => 'string', 'default' => 'compact'), 'needs' => array('type' => 'array', 'items' => array('type' => 'string')))),
        'product-price' => array('title' => 'Giá sản phẩm', 'uses_context' => array('postId', 'postType')),
        'product-specs' => array('title' => 'Quy cách sản phẩm', 'uses_context' => array('postId', 'postType')),
        'floating-contact' => array('title' => 'Nút gọi điện và Zalo'),
        'article-toc' => array('title' => 'Mục lục bài viết'),
    );
    foreach ($definitions as $slug => $args) {
        $args['attributes'] = isset($args['attributes']) ? $args['attributes'] : array();
        $args['attributes']['labels'] = array('type' => 'object', 'default' => new stdClass());
        register_block_type('hpv/' . $slug, array_merge(array(
            'api_version' => 3,
            'category' => 'widgets',
            'editor_script' => 'hpv-business-blocks',
            'supports' => array('html' => false, 'align' => array('wide', 'full'), 'spacing' => array('margin' => true, 'padding' => true), 'color' => array('text' => true, 'background' => true, 'link' => true), 'typography' => array('fontSize' => true, 'lineHeight' => true, '__experimentalFontFamily' => true)),
            'render_callback' => 'hpv_render_' . str_replace('-', '_', $slug),
            'style_handles' => $slug === 'quote-form' ? array('hpv-contact-form') : array(),
        ), $args));
    }
}
add_action('init', 'hpv_register_business_blocks', 12);

/** Public copy is editable; the form's security/validation rules remain server-owned. */
function hpv_block_label($attributes, $key, $fallback) {
    $labels = isset($attributes['labels']) ? (array) $attributes['labels'] : array();
    return isset($labels[$key]) && is_string($labels[$key]) && trim($labels[$key]) !== '' ? sanitize_text_field($labels[$key]) : $fallback;
}

function hpv_business_block_categories($categories) {
    array_unshift($categories, array('slug' => 'hai-phat-vina-business', 'title' => 'Hải Phát Vina — chức năng'));
    return $categories;
}
add_filter('block_categories_all', 'hpv_business_block_categories');

function hpv_render_company_info($attributes) {
    $layout = isset($attributes['layout']) ? $attributes['layout'] : 'contact';
    $wrapper = get_block_wrapper_attributes(array('class' => 'hpv-company-info hpv-company-info--' . sanitize_html_class($layout)));
    ob_start();
    ?>
    <div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes wrapper attributes. ?>>
    <?php if ($layout === 'phone') : ?>
        <a href="<?php echo esc_url(hpv_phone_url()); ?>"><small><?php echo esc_html(hpv_block_label($attributes, 'phoneCaption', 'Tư vấn & báo giá')); ?></small><strong><?php echo esc_html(hpv_get('phone')); ?></strong></a>
    <?php elseif ($layout === 'branches') : ?>
        <div class="hpv-branches">
        <?php for ($i = 1; $i <= 4; $i++) : $address = hpv_get('address_' . $i); if (!$address) { continue; } ?>
            <div class="hpv-branch"><strong><?php echo esc_html(hpv_block_label($attributes, 'branchPrefix', 'Cơ sở')) . ' ' . (int) $i; ?></strong><p><?php echo esc_html($address); ?></p><a href="<?php echo esc_url(hpv_map_url($address)); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(hpv_block_label($attributes, 'directionsLabel', 'Xem đường đi')); ?><span class="screen-reader-text"> đến cơ sở <?php echo (int) $i; ?> (mở tab mới)</span></a></div>
        <?php endfor; ?>
        </div>
    <?php elseif ($layout === 'bank') : ?>
        <dl><div><dt><?php echo esc_html(hpv_block_label($attributes, 'bankLabel', 'Ngân hàng')); ?></dt><dd><?php echo esc_html(hpv_get('bank_name')); ?></dd></div><div><dt><?php echo esc_html(hpv_block_label($attributes, 'ownerLabel', 'Chủ tài khoản')); ?></dt><dd><?php echo esc_html(hpv_get('legal_name')); ?></dd></div><div><dt><?php echo esc_html(hpv_block_label($attributes, 'accountLabel', 'Số tài khoản')); ?></dt><dd><strong><?php echo esc_html(hpv_get('bank_account')); ?></strong><?php if (function_exists('hpv_icon') && hpv_get('bank_account')) : ?> <button type="button" class="hpv-copy-account" data-copy="<?php echo esc_attr(hpv_get('bank_account')); ?>" aria-label="Sao chép số tài khoản"><?php echo hpv_icon('copy'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted theme icon. ?></button><span class="copy-status screen-reader-text" role="status" aria-live="polite"></span><?php endif; ?></dd></div></dl>
        <p><small><?php echo esc_html(hpv_block_label($attributes, 'paymentNote', 'Vui lòng xác nhận thông tin thanh toán qua hotline trước khi chuyển khoản.')); ?></small></p>
    <?php elseif ($layout === 'footer') : ?>
        <p><strong><?php echo esc_html(hpv_get('legal_name')); ?></strong></p>
        <p><?php echo esc_html(hpv_block_label($attributes, 'taxLabel', 'Mã số thuế')); ?>: <?php echo esc_html(hpv_get('tax_id')); ?></p>
        <p><a href="<?php echo esc_url(hpv_phone_url()); ?>"><?php echo esc_html(hpv_get('phone')); ?></a><br><a href="<?php echo esc_url('mailto:' . hpv_get('email')); ?>"><?php echo esc_html(hpv_get('email')); ?></a></p>
        <p><a href="<?php echo esc_url(hpv_zalo_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(hpv_block_label($attributes, 'zaloLabel', 'Trao đổi qua Zalo')); ?></a></p>
    <?php else : ?>
        <dl>
            <div><dt><?php echo esc_html(hpv_block_label($attributes, 'companyLabel', 'Doanh nghiệp')); ?></dt><dd><?php echo esc_html(hpv_get('legal_name')); ?></dd></div>
            <div><dt><?php echo esc_html(hpv_block_label($attributes, 'taxLabel', 'Mã số thuế')); ?></dt><dd><?php echo esc_html(hpv_get('tax_id')); ?></dd></div>
            <div><dt><?php echo esc_html(hpv_block_label($attributes, 'hotlineLabel', 'Hotline')); ?></dt><dd><a href="<?php echo esc_url(hpv_phone_url()); ?>"><?php echo esc_html(hpv_get('phone')); ?></a></dd></div>
            <div><dt><?php echo esc_html(hpv_block_label($attributes, 'emailLabel', 'Email')); ?></dt><dd><a href="<?php echo esc_url('mailto:' . hpv_get('email')); ?>"><?php echo esc_html(hpv_get('email')); ?></a></dd></div>
            <div><dt>Zalo</dt><dd><a href="<?php echo esc_url(hpv_zalo_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(hpv_block_label($attributes, 'zaloLabel', 'Nhắn tin tư vấn')); ?></a></dd></div>
        </dl>
    <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function hpv_render_quote_form($attributes) {
    $variant = isset($attributes['variant']) && $attributes['variant'] === 'full' ? 'full' : 'compact';
    ob_start();
    echo '<div ' . get_block_wrapper_attributes(array('class' => 'hpv-form-block')) . '>';
    hpv_quote_form($variant, isset($attributes['labels']) ? $attributes['labels'] : array(), isset($attributes['needs']) ? $attributes['needs'] : null);
    echo '</div>';
    return ob_get_clean();
}

function hpv_block_product_id($block) {
    $id = isset($block->context['postId']) ? absint($block->context['postId']) : get_the_ID();
    return get_post_type($id) === 'hpv_product' ? $id : 0;
}

function hpv_render_product_price($attributes, $content, $block) {
    $id = hpv_block_product_id($block);
    if (!$id) { return ''; }
    $price = get_post_meta($id, '_hpv_price', true);
    $unit = get_post_meta($id, '_hpv_unit', true);
    return '<p ' . get_block_wrapper_attributes(array('class' => 'hpv-price')) . '>' . esc_html($price ?: hpv_block_label($attributes, 'fallbackLabel', 'Liên hệ báo giá')) . ($price && $unit ? ' <small>/ ' . esc_html($unit) . '</small>' : '') . '</p>';
}

function hpv_render_product_specs($attributes, $content, $block) {
    $id = hpv_block_product_id($block);
    if (!$id) { return ''; }
    $rows = '';
    foreach (array('material' => 'Chất liệu / quy cách', 'application' => 'Ứng dụng', 'warranty' => 'Bảo hành') as $key => $label) {
        $value = get_post_meta($id, '_hpv_' . $key, true);
        if ($value) { $rows .= '<div><dt>' . esc_html(hpv_block_label($attributes, $key . 'Label', $label)) . '</dt><dd>' . esc_html($value) . '</dd></div>'; }
    }
    return $rows ? '<dl ' . get_block_wrapper_attributes(array('class' => 'hpv-product-specs')) . '>' . $rows . '</dl>' : '';
}

function hpv_render_floating_contact($attributes) {
    $phone_label = str_replace('{phone}', hpv_get('phone'), hpv_block_label($attributes, 'phoneLabel', 'Gọi {phone}'));
    $chat_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.8 7.5 8 8 0 0 1-3.1-.7L4 20l1.5-4.2A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8.5 11.5h7"/></svg>';
    $phone_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.6 3.5 9 3l2 4.4-1.7 1.5a14 14 0 0 0 5.8 5.8l1.5-1.7 4.4 2-.5 2.4a2.5 2.5 0 0 1-2.6 2C10.7 18.7 5.3 13.3 4.6 6.1a2.5 2.5 0 0 1 2-2.6Z"/></svg>';
    return '<nav ' . get_block_wrapper_attributes(array('class' => 'hpv-contact-dock')) . ' aria-label="Liên hệ nhanh"><a href="' . esc_url(hpv_zalo_url()) . '" target="_blank" rel="noopener noreferrer">' . $chat_icon . '<span>' . esc_html(hpv_block_label($attributes, 'zaloLabel', 'Chat Zalo')) . '</span></a><a href="' . esc_url(hpv_phone_url()) . '">' . $phone_icon . '<span>' . esc_html($phone_label) . '</span></a></nav>';
}

function hpv_render_article_toc($attributes) {
    return '<aside ' . get_block_wrapper_attributes(array('class' => 'hpv-article-toc')) . ' data-article-toc hidden><details open><summary>' . esc_html(hpv_block_label($attributes, 'titleLabel', 'Trong bài viết này')) . '</summary><nav aria-label="Mục lục bài viết"></nav></details></aside>';
}
