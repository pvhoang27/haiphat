<?php
/**
 * Native block patterns used by the Site Editor and the guided migration.
 *
 * Building patterns never creates or updates posts, menus, or settings. Text,
 * links, images, columns and section order remain editable with core blocks.
 */
defined('ABSPATH') || exit;

/** Serialize with WordPress so JSON attributes and block comments stay valid. */
function hpv_pattern_block($name, $attributes = array(), $content = '') {
    $content = is_array($content) ? implode("\n", $content) : $content;
    return serialize_block(array(
        'blockName' => $name,
        'attrs' => $attributes,
        'innerBlocks' => array(),
        'innerHTML' => $content,
        'innerContent' => $content === '' ? array() : array($content),
    ));
}

/** Core Group save markup, without editor-only or frontend-generated classes. */
function hpv_pattern_group($children, $class_name = '', $name = '', $attributes = array()) {
    $attributes = array_merge(array('layout' => array('type' => 'constrained')), $attributes);
    if ($class_name !== '') { $attributes['className'] = $class_name; }
    if ($name !== '') { $attributes['metadata'] = array('name' => $name); }
    $tag = isset($attributes['tagName']) ? $attributes['tagName'] : 'div';
    $classes = 'wp-block-group';
    if (!empty($attributes['align'])) { $classes .= ' align' . $attributes['align']; }
    if ($class_name !== '') { $classes .= ' ' . $class_name; }
    $anchor = empty($attributes['anchor']) ? '' : ' id="' . esc_attr($attributes['anchor']) . '"';
    $content = '<' . $tag . $anchor . ' class="' . esc_attr($classes) . '">' . "\n" . implode("\n", (array) $children) . "\n" . '</' . $tag . '>';
    return hpv_pattern_block('core/group', $attributes, $content);
}

function hpv_pattern_paragraph($text, $class_name = '') {
    return hpv_pattern_block('core/paragraph', $class_name === '' ? array() : array('className' => $class_name), '<p' . ($class_name === '' ? '' : ' class="' . esc_attr($class_name) . '"') . '>' . $text . '</p>');
}

/** $text is trusted theme copy, or escaped by the caller for saved settings. */
function hpv_pattern_heading($text, $level = 2, $class_name = '') {
    $attributes = array('level' => $level);
    if ($class_name !== '') { $attributes['className'] = $class_name; }
    return hpv_pattern_block('core/heading', $attributes, '<h' . $level . ' class="wp-block-heading' . ($class_name === '' ? '' : ' ' . esc_attr($class_name)) . '">' . $text . '</h' . $level . '>');
}

function hpv_pattern_link($label, $url) {
    return '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
}

function hpv_pattern_button($label, $url, $outline = false) {
    $class_name = $outline ? 'is-style-outline' : 'hpv-button-primary';
    return hpv_pattern_block('core/button', array('className' => $class_name), '<div class="wp-block-button ' . esc_attr($class_name) . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url($url) . '">' . esc_html($label) . '</a></div>');
}

function hpv_pattern_buttons($buttons) {
    return hpv_pattern_block('core/buttons', array(), '<div class="wp-block-buttons">' . "\n" . implode("\n", $buttons) . "\n" . '</div>');
}

/** Each entry is an array of serialized child blocks for a native Column. */
function hpv_pattern_columns($columns, $class_name = '', $vertical_alignment = '') {
    $children = array();
    foreach ($columns as $column) {
        $children[] = hpv_pattern_block('core/column', array(), '<div class="wp-block-column">' . "\n" . implode("\n", $column) . "\n" . '</div>');
    }
    $attributes = array();
    $classes = 'wp-block-columns';
    if ($vertical_alignment !== '') {
        $attributes['verticalAlignment'] = $vertical_alignment;
        $classes .= ' are-vertically-aligned-' . $vertical_alignment;
    }
    if ($class_name !== '') { $attributes['className'] = $class_name; $classes .= ' ' . $class_name; }
    return hpv_pattern_block('core/columns', $attributes, '<div class="' . esc_attr($classes) . '">' . "\n" . implode("\n", $children) . "\n" . '</div>');
}

/** Reuse uploaded images and attachment IDs; bundled images remain replaceable. */
function hpv_pattern_image_data($setting, $fallback, $fallback_alt) {
    $id = absint(hpv_get($setting));
    $url = $id ? wp_get_attachment_image_url($id, 'full') : false;
    if (!$url) {
        $media = get_option('hpv_block_media', array());
        $id = is_array($media) && isset($media[$fallback]) ? absint($media[$fallback]) : 0;
        $url = $id ? wp_get_attachment_image_url($id, 'full') : false;
    }
    if (!$url) { $id = 0; $url = hpv_asset('images/' . $fallback . '.webp'); }
    $alt = $id ? get_post_meta($id, '_wp_attachment_image_alt', true) : $fallback_alt;
    return array('id' => $id, 'url' => $url, 'alt' => $alt);
}

function hpv_pattern_image($image, $class_name = '') {
    $attributes = array('sizeSlug' => 'full', 'linkDestination' => 'none');
    if (!empty($image['id'])) { $attributes['id'] = $image['id']; }
    if ($class_name !== '') { $attributes['className'] = $class_name; }
    $image_class = empty($image['id']) ? '' : ' class="wp-image-' . absint($image['id']) . '"';
    return hpv_pattern_block('core/image', $attributes, '<figure class="wp-block-image size-full' . ($class_name === '' ? '' : ' ' . esc_attr($class_name)) . '"><img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt']) . '"' . $image_class . '/></figure>');
}

function hpv_pattern_section($name, $children, $class_name = '', $anchor = '') {
    $attributes = array('tagName' => 'section', 'align' => 'full');
    if ($anchor !== '') { $attributes['anchor'] = $anchor; }
    return hpv_pattern_group(array(hpv_pattern_group($children, 'hpv-section-inner', '', array('align' => 'wide', 'layout' => array('type' => 'default')))), 'hpv-section' . ($class_name === '' ? '' : ' ' . $class_name), $name, $attributes);
}

function hpv_pattern_section_heading($kicker, $heading, $description = '', $link_label = '', $link_url = '') {
    $children = array(hpv_pattern_group(array(hpv_pattern_paragraph($kicker, 'hpv-kicker'), hpv_pattern_heading($heading)), 'hpv-section-heading-copy'));
    if ($description !== '' || $link_label !== '') {
        $side = array();
        if ($description !== '') { $side[] = hpv_pattern_paragraph($description); }
        if ($link_label !== '') { $side[] = hpv_pattern_paragraph(hpv_pattern_link($link_label, $link_url), 'hpv-text-link'); }
        $children[] = hpv_pattern_group($side, 'hpv-section-heading-side');
    }
    return hpv_pattern_group($children, 'hpv-section-heading', '', array('layout' => array('type' => 'flex', 'flexWrap' => 'wrap', 'justifyContent' => 'space-between', 'verticalAlignment' => 'bottom')));
}

/**
 * A real Query block: editors can change count, order, columns and card layout.
 * Product prices retain their single source in the product's management form.
 */
function hpv_pattern_query($post_type, $per_page = 3, $columns = 3, $query_id = 1) {
    $is_product = $post_type === 'hpv_product';
    $card_children = array(
        hpv_pattern_block('core/post-featured-image', array('isLink' => true, 'aspectRatio' => '4/3', 'sizeSlug' => 'hpv-card')),
        hpv_pattern_block('core/post-title', array('level' => 3, 'isLink' => true)),
        hpv_pattern_block('core/post-excerpt', array('moreText' => '', 'excerptLength' => $is_product ? 22 : 28, 'showMoreOnNewLine' => false)),
    );
    if ($is_product) { $card_children[] = hpv_pattern_block('hpv/product-price'); }
    $card_children[] = hpv_pattern_block('core/read-more', array('content' => $is_product ? 'Xem sản phẩm' : 'Xem chi tiết', 'className' => 'hpv-text-link'));
    $template = hpv_pattern_block('core/post-template', array('className' => 'hpv-card-grid', 'layout' => array('type' => 'grid', 'columnCount' => $columns)), hpv_pattern_group($card_children, 'hpv-card'));
    $empty = hpv_pattern_block('core/query-no-results', array(), hpv_pattern_paragraph('Nội dung đang được cập nhật. Bạn có thể liên hệ Hải Phát Vina để được tư vấn.', 'hpv-empty-state'));
    return hpv_pattern_block('core/query', array(
        'queryId' => $query_id,
        'query' => array('perPage' => $per_page, 'pages' => 0, 'offset' => 0, 'postType' => $post_type, 'order' => 'desc', 'orderBy' => 'date', 'author' => '', 'search' => '', 'exclude' => array(), 'sticky' => '', 'inherit' => false),
        'className' => 'hpv-query',
    ), '<div class="wp-block-query hpv-query">' . "\n" . $template . "\n" . $empty . "\n" . '</div>');
}

/** Resolve only published policy pages, avoiding dead placeholder links. */
function hpv_pattern_policy_links() {
    $links = array();
    foreach (array(
        'chinh-sach-bao-mat' => 'Chính sách bảo mật',
        'chinh-sach-ban-hang' => 'Chính sách bán hàng',
        'chinh-sach-bao-hanh-doi-tra' => 'Chính sách bảo hành - đổi trả',
        'chinh-sach-bao-hanh' => 'Chính sách bảo hành',
    ) as $slug => $label) {
        $page = get_page_by_path($slug);
        if ($page && $page->post_status === 'publish') { $links[] = hpv_pattern_paragraph(hpv_pattern_link($label, get_permalink($page))); }
    }
    return $links;
}

/** A saved Navigation post takes precedence over the initial editable links. */
function hpv_pattern_navigation() {
    $attributes = array('overlayMenu' => 'mobile', 'layout' => array('type' => 'flex', 'justifyContent' => 'right'));
    $navigation_id = absint(get_option('hpv_navigation_id', 0));
    if ($navigation_id && get_post_type($navigation_id) === 'wp_navigation' && get_post_status($navigation_id) === 'publish') {
        $attributes['ref'] = $navigation_id;
        return hpv_pattern_block('core/navigation', $attributes);
    }
    $items = array(
        hpv_pattern_block('core/navigation-link', array('label' => 'Trang chủ', 'url' => home_url('/'), 'kind' => 'custom')),
        hpv_pattern_block('core/navigation-link', array('label' => 'Giới thiệu', 'url' => hpv_page_url('about'), 'kind' => 'custom')),
    );
    $product_links = array(hpv_pattern_block('core/navigation-link', array('label' => 'Tất cả sản phẩm', 'url' => hpv_products_url(), 'kind' => 'custom')));
    $terms = get_terms(array('taxonomy' => 'hpv_category', 'hide_empty' => false));
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $url = get_term_link($term);
            if (!is_wp_error($url)) {
                $product_links[] = hpv_pattern_block('core/navigation-link', array('label' => $term->name, 'type' => 'hpv_category', 'id' => $term->term_id, 'url' => $url, 'kind' => 'taxonomy'));
            }
        }
    }
    $items[] = hpv_pattern_block('core/navigation-submenu', array('label' => 'Sản phẩm', 'url' => hpv_products_url(), 'kind' => 'custom'), $product_links);
    foreach (array('Công trình & ứng dụng' => hpv_projects_url(), 'Kiến thức' => hpv_page_url('news'), 'Liên hệ' => hpv_page_url('contact')) as $label => $url) {
        $items[] = hpv_pattern_block('core/navigation-link', array('label' => $label, 'url' => $url, 'kind' => 'custom'));
    }
    return hpv_pattern_block('core/navigation', $attributes, $items);
}

/** Public source for the inserter, template parts and explicit migrations. */
function hpv_site_patterns() {
    $patterns = array();
    $hero_image = hpv_pattern_image_data('hero_image', 'hero', 'Ảnh minh họa mái xếp che khoảng sân ngoài trời');
    $about_image = hpv_pattern_image_data('about_image', 'tarpaulin', 'Ảnh minh họa bạt nhựa và cuộn vật liệu');

    $brand = hpv_pattern_group(array(
        hpv_pattern_block('core/site-logo', array('width' => 320, 'shouldSyncIcon' => false)),
    ), 'hpv-brand', '', array('layout' => array('type' => 'flex', 'flexWrap' => 'nowrap')));
    $patterns['header'] = hpv_pattern_group(array(
        hpv_pattern_group(array(hpv_pattern_group(array(
            hpv_pattern_paragraph('Mái che & bạt công trình · Tư vấn theo nhu cầu'),
            hpv_pattern_paragraph(hpv_pattern_link('Địa chỉ các cơ sở', hpv_page_url('contact') . '#contact-locations')),
        ), '', '', array('align' => 'wide', 'layout' => array('type' => 'flex', 'flexWrap' => 'wrap', 'justifyContent' => 'space-between')))), 'hpv-topbar', '', array('align' => 'full')),
        hpv_pattern_group(array($brand, hpv_pattern_navigation(), hpv_pattern_group(array(hpv_pattern_block('hpv/company-info', array('layout' => 'phone'))), 'hpv-header-phone')), 'hpv-header-inner', 'Logo, menu và hotline', array('align' => 'wide', 'layout' => array('type' => 'flex', 'flexWrap' => 'nowrap', 'justifyContent' => 'space-between'))),
    ), 'hpv-site-header', 'Đầu trang', array('align' => 'full'));

    $footer_products = array(
        hpv_pattern_paragraph(hpv_pattern_link('Danh mục sản phẩm', hpv_products_url())),
        hpv_pattern_paragraph(hpv_pattern_link('Thông tin báo giá', hpv_page_url('quote'))),
        hpv_pattern_paragraph(hpv_pattern_link('Công trình & ứng dụng', hpv_projects_url())),
        hpv_pattern_paragraph(hpv_pattern_link('Kiến thức lựa chọn', hpv_page_url('news'))),
    );
    $footer_policies = array_merge(array(hpv_pattern_paragraph(hpv_pattern_link('Về Hải Phát Vina', hpv_page_url('about')))), hpv_pattern_policy_links());
    $footer_navigation_id = absint(get_option('hpv_footer_navigation_id', 0));
    if ($footer_navigation_id && get_post_type($footer_navigation_id) === 'wp_navigation' && get_post_status($footer_navigation_id) === 'publish') {
        $footer_policies[] = hpv_pattern_block('core/navigation', array('ref' => $footer_navigation_id, 'overlayMenu' => 'never', 'className' => 'hpv-footer-navigation', 'layout' => array('type' => 'flex', 'orientation' => 'vertical', 'justifyContent' => 'left')));
    }
    $patterns['footer'] = hpv_pattern_group(array(
        hpv_pattern_group(array(
            hpv_pattern_group(array(hpv_pattern_block('core/site-title', array('level' => 0)), hpv_pattern_paragraph('Giải pháp mái che và bạt công trình.<br>Trao đổi đúng nhu cầu, lựa chọn đúng quy cách.')), 'hpv-footer-brand'),
            hpv_pattern_block('hpv/company-info', array('layout' => 'phone')),
        ), 'hpv-footer-top', '', array('align' => 'wide', 'layout' => array('type' => 'flex', 'flexWrap' => 'wrap', 'justifyContent' => 'space-between'))),
        hpv_pattern_group(array(hpv_pattern_columns(array(
            array(hpv_pattern_heading('Thông tin công ty', 2), hpv_pattern_block('hpv/company-info', array('layout' => 'footer'))),
            array(hpv_pattern_heading('Sản phẩm & ứng dụng', 2), hpv_pattern_group($footer_products, 'hpv-footer-links')),
            array(hpv_pattern_heading('Thông tin & chính sách', 2), hpv_pattern_group($footer_policies, 'hpv-footer-links')),
            array(hpv_pattern_heading('Các cơ sở liên hệ', 2), hpv_pattern_block('hpv/company-info', array('layout' => 'branches'))),
        ), 'hpv-footer-grid')), '', '', array('align' => 'wide', 'layout' => array('type' => 'default'))),
        hpv_pattern_group(array(hpv_pattern_block('hpv/company-info', array('layout' => 'bank'))), 'hpv-footer-bank', '', array('align' => 'wide', 'layout' => array('type' => 'default'))),
        hpv_pattern_group(array(hpv_pattern_paragraph('© Hải Phát Vina.'), hpv_pattern_paragraph('Thông tin quy cách và giá được xác nhận theo yêu cầu thực tế.')), 'hpv-footer-bottom', '', array('align' => 'wide', 'layout' => array('type' => 'flex', 'flexWrap' => 'wrap', 'justifyContent' => 'space-between'))),
    ), 'hpv-site-footer', 'Chân trang', array('align' => 'full'));
    $customer_structure = get_option('hpv_customer_structure_v1');
    if (is_array($customer_structure) && !empty($customer_structure['pages']) && function_exists('hpv_customer_footer_markup')) {
        $patterns['footer'] = hpv_customer_footer_markup($customer_structure['pages']);
    }

    $hero_attributes = array('url' => $hero_image['url'], 'alt' => $hero_image['alt'], 'dimRatio' => 60, 'overlayColor' => 'forest', 'isUserOverlayColor' => true, 'minHeight' => 640, 'minHeightUnit' => 'px', 'align' => 'full', 'className' => 'hpv-hero', 'metadata' => array('name' => 'Ảnh bìa và tiêu đề trang chủ'));
    if ($hero_image['id']) { $hero_attributes['id'] = $hero_image['id']; }
    $hero_content = hpv_pattern_group(array(
        hpv_pattern_paragraph('Mái che & bạt công trình', 'hpv-kicker'),
        hpv_pattern_heading(nl2br(esc_html(hpv_get('hero_title'))), 1, 'hpv-hero-title'),
        hpv_pattern_paragraph(esc_html(hpv_get('hero_text')), 'hpv-hero-description'),
        hpv_pattern_buttons(array(hpv_pattern_button('Tìm giải pháp của bạn', '#products'), hpv_pattern_button('Trao đổi & nhận báo giá', hpv_page_url('quote'), true))),
        hpv_pattern_paragraph('Cho nhà ở, kinh doanh và không gian sản xuất', 'hpv-hero-footnote'),
    ), 'hpv-hero-copy', '', array('align' => 'wide', 'layout' => array('type' => 'default')));
    $patterns['hero'] = hpv_pattern_block('core/cover', $hero_attributes,
        '<div class="wp-block-cover alignfull hpv-hero" style="min-height:640px"><img class="wp-block-cover__image-background' . ($hero_image['id'] ? ' wp-image-' . $hero_image['id'] : '') . '" alt="' . esc_attr($hero_image['alt']) . '" src="' . esc_url($hero_image['url']) . '" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-forest-background-color has-background-dim-60 has-background-dim"></span><div class="wp-block-cover__inner-container">' . "\n" . $hero_content . "\n" . '</div></div>');

    $solutions = array();
    foreach (array(
        array('mai-che', 'Mái xếp & mái hiên', 'Linh hoạt khoảng che'),
        array('bat-nhua', 'Bạt nhựa che phủ', 'Bảo vệ hàng hóa, vật tư'),
        array('bat-hdpe', 'Bạt HDPE lót hồ', 'Vật liệu cho ao & hồ'),
        array('du-che', 'Dù che ngoài trời', 'Thêm bóng mát sân vườn'),
    ) as $solution) {
        $term = get_term_by('slug', $solution[0], 'hpv_category');
        $url = $term ? get_term_link($term) : hpv_products_url();
        if (is_wp_error($url)) { $url = hpv_products_url(); }
        $solutions[] = array(hpv_pattern_group(array(hpv_pattern_heading(hpv_pattern_link($solution[1], $url), 2), hpv_pattern_paragraph($solution[2])), 'hpv-solution-card'));
    }
    $patterns['solutions'] = hpv_pattern_section('Nhóm giải pháp', array(hpv_pattern_columns($solutions, 'hpv-solution-grid')), 'hpv-solutions-section');

    $patterns['products-section'] = hpv_pattern_section('Danh mục sản phẩm', array(
        hpv_pattern_section_heading('Danh mục sản phẩm', 'Một danh mục.<br>Nhiều cách ứng dụng.', 'Chọn theo vật liệu hoặc bắt đầu từ không gian bạn muốn che phủ. Quy cách được trao đổi theo nhu cầu thực tế.', 'Khám phá toàn bộ sản phẩm', hpv_products_url()),
        hpv_pattern_query('hpv_product', 8, 4, 11),
        hpv_pattern_paragraph('Chưa biết chọn vật liệu nào? ' . hpv_pattern_link('Cùng chúng tôi tìm phương án', '#contact'), 'hpv-catalog-foot'),
    ), 'hpv-products-section', 'products');

    $patterns['about-section'] = hpv_pattern_section('Giới thiệu Hải Phát Vina', array(hpv_pattern_columns(array(
        array(hpv_pattern_paragraph('Hải Phát Vina', 'hpv-kicker'), hpv_pattern_heading('Hiểu không gian.<br>Chọn đúng vật liệu.'), hpv_pattern_paragraph('Một khoảng sân cần thoáng. Một kiện hàng cần được che phủ. Một hồ chứa cần phương án lót phù hợp. Mỗi nhu cầu bắt đầu từ một cách lựa chọn khác nhau.'), hpv_pattern_paragraph('Hải Phát Vina cùng bạn làm rõ kích thước, vật liệu và phạm vi cung cấp trước khi thống nhất báo giá.'), hpv_pattern_paragraph('<strong>Quy cách rõ ràng · Trao đổi trực tiếp</strong>'), hpv_pattern_buttons(array(hpv_pattern_button('Về Hải Phát Vina', hpv_page_url('about'), true)))),
        array(hpv_pattern_image($about_image, 'hpv-about-image'), hpv_pattern_paragraph('Chất liệu là điểm bắt đầu.<br>Kích thước & quy cách theo nhu cầu.', 'hpv-material-note')),
    ), 'hpv-about-layout', 'center')), 'hpv-about-section', 'about');

    $patterns['projects-section'] = hpv_pattern_section('Công trình và ứng dụng', array(
        hpv_pattern_section_heading('Không gian & ứng dụng', 'Bạn muốn che phủ<br>không gian nào?', '', 'Tham khảo các ứng dụng', hpv_projects_url()),
        hpv_pattern_query('hpv_project', 3, 3, 12),
    ), 'hpv-projects-section');

    $steps = array();
    foreach (array(
        array('01', 'Chia sẻ không gian', 'Gửi kích thước, mục đích sử dụng và ảnh vị trí thực tế.'),
        array('02', 'Chọn quy cách', 'Trao đổi vật liệu, kết cấu và phạm vi công việc cần thiết.'),
        array('03', 'Làm rõ báo giá', 'Xác nhận số lượng, các khoản chi phí và thời gian dự kiến.'),
        array('04', 'Thống nhất thực hiện', 'Chốt phương án cung cấp, giao nhận và điều kiện bàn giao.'),
    ) as $step) {
        $steps[] = array(hpv_pattern_group(array(hpv_pattern_paragraph($step[0], 'hpv-step-number'), hpv_pattern_heading($step[1], 3), hpv_pattern_paragraph($step[2])), 'hpv-process-step', 'Bước ' . $step[0] . ': ' . $step[1]));
    }
    $patterns['process-section'] = hpv_pattern_section('Quy trình tư vấn', array(
        hpv_pattern_section_heading('Bắt đầu thật đơn giản', 'Từ một ý tưởng,<br>đến phương án cụ thể.'),
        hpv_pattern_columns($steps, 'hpv-process-grid'),
    ), 'hpv-process-section');

    $patterns['news-section'] = hpv_pattern_section('Kiến thức và cẩm nang', array(
        hpv_pattern_section_heading('Cẩm nang lựa chọn', 'Biết thêm một chút.<br>Chọn đúng hơn nhiều.', '', 'Đọc cẩm nang', hpv_page_url('news')),
        hpv_pattern_query('post', 3, 3, 13),
    ), 'hpv-news-section', 'news');

    $patterns['contact-section'] = hpv_pattern_section('Tư vấn và nhận báo giá', array(hpv_pattern_columns(array(
        array(hpv_pattern_paragraph('Tư vấn cùng Hải Phát Vina', 'hpv-kicker'), hpv_pattern_paragraph('Kể chúng tôi nghe<br>bạn đang cần gì.'), hpv_pattern_paragraph('Một vài thông tin về kích thước và vị trí sử dụng sẽ giúp cuộc trao đổi đi đúng hướng. Chúng tôi tiếp nhận nhu cầu để tư vấn quy cách và báo giá phù hợp.'), hpv_pattern_block('hpv/company-info', array('layout' => 'contact'))),
        array(hpv_pattern_group(array(hpv_pattern_heading('Thông tin của bạn', 3), hpv_pattern_paragraph('Điền các mục có dấu * để chúng tôi có thể liên hệ.'), hpv_pattern_block('hpv/quote-form', array('variant' => 'compact'))), 'hpv-form-panel', 'Biểu mẫu nhận tư vấn')),
    ), 'hpv-contact-layout')), 'hpv-contact-section', 'contact');

    $patterns['home'] = implode("\n\n", array($patterns['hero'], $patterns['solutions'], $patterns['products-section'], $patterns['about-section'], $patterns['projects-section'], $patterns['process-section'], $patterns['news-section'], $patterns['contact-section']));

    $patterns['about-page'] = hpv_pattern_section('Nội dung giới thiệu', array(hpv_pattern_columns(array(
        array(hpv_pattern_paragraph('Về Hải Phát Vina', 'hpv-kicker'), hpv_pattern_heading('Mái che, bạt công trình<br>& sự lựa chọn phù hợp.'), hpv_pattern_paragraph('Hải Phát Vina cung cấp thông tin và tư vấn lựa chọn mái xếp, mái hiên, bạt nhựa, màng HDPE và dù che ngoài trời.'), hpv_pattern_paragraph('Chúng tôi cùng bạn trao đổi mục đích sử dụng, kích thước và quy cách để làm rõ phương án cung cấp trước khi thống nhất báo giá.'), hpv_pattern_buttons(array(hpv_pattern_button('Liên hệ Hải Phát Vina', hpv_page_url('contact'))))),
        array(hpv_pattern_image($hero_image, 'hpv-about-image')),
    ), 'hpv-about-layout', 'center')), 'hpv-about-page') . "\n\n" . hpv_pattern_section('Thông tin doanh nghiệp', array(hpv_pattern_heading('Thông tin doanh nghiệp'), hpv_pattern_block('hpv/company-info', array('layout' => 'contact')), hpv_pattern_block('hpv/company-info', array('layout' => 'branches'))), 'hpv-company-section') . "\n\n" . $patterns['contact-section'];

    $patterns['contact-page'] = hpv_pattern_section('Liên hệ và tư vấn', array(
        hpv_pattern_paragraph('Để lại nhu cầu của bạn để được tư vấn quy cách và nhận báo giá phù hợp.', 'hpv-page-lead'),
        hpv_pattern_columns(array(
            array(hpv_pattern_group(array(hpv_pattern_heading('Thông tin của bạn'), hpv_pattern_paragraph('Điền các mục có dấu * để chúng tôi có thể liên hệ.'), hpv_pattern_block('hpv/quote-form', array('variant' => 'full'))), 'hpv-form-panel', 'Biểu mẫu liên hệ')),
            array(hpv_pattern_heading('Trao đổi trực tiếp'), hpv_pattern_paragraph('Gọi hoặc nhắn Zalo nếu bạn muốn trao đổi ngay về sản phẩm và quy cách.'), hpv_pattern_block('hpv/company-info', array('layout' => 'contact'))),
        ), 'hpv-contact-layout'),
    ), 'hpv-contact-page', 'contact') . "\n\n" . hpv_pattern_section('Địa chỉ các cơ sở', array(hpv_pattern_heading('Ghé thăm Hải Phát Vina'), hpv_pattern_paragraph('Vui lòng gọi trước để xác nhận vị trí và sắp xếp người hỗ trợ.'), hpv_pattern_block('hpv/company-info', array('layout' => 'branches'))), 'hpv-locations-section', 'contact-locations') . "\n\n" . hpv_pattern_section('Thông tin chuyển khoản', array(hpv_pattern_heading('Thông tin chuyển khoản'), hpv_pattern_block('hpv/company-info', array('layout' => 'bank'))), 'hpv-payment-section');

    $questions = array();
    foreach (array(
        'Cần gửi những thông tin gì?' => 'Loại sản phẩm, mục đích sử dụng, kích thước dài × rộng, số lượng và địa điểm giao hoặc lắp đặt. Nếu có thể, hãy gửi thêm ảnh qua Zalo.',
        'Báo giá đã bao gồm những khoản nào?' => 'Từng báo giá cần ghi rõ vật tư, phụ kiện, vận chuyển, thi công và thuế. Hãy yêu cầu xác nhận phạm vi trước khi so sánh hoặc đặt hàng.',
        'Chính sách bảo hành được xác nhận ra sao?' => 'Thời hạn, phạm vi và điều kiện bảo hành cần được xác nhận cho từng sản phẩm, vật liệu và phương án cung cấp cụ thể.',
        'Có thể xem mẫu tại cơ sở không?' => 'Vui lòng liên hệ trước để xác nhận mẫu đang có và cơ sở phù hợp trước khi tới.',
    ) as $question => $answer) {
        $questions[] = hpv_pattern_block('core/details', array(), '<details class="wp-block-details"><summary>' . esc_html($question) . '</summary>' . "\n" . hpv_pattern_paragraph($answer) . "\n" . '</details>');
    }
    $patterns['quote-page'] = hpv_pattern_section('Thông tin báo giá', array(
        hpv_pattern_paragraph('Thông tin báo giá', 'hpv-kicker'),
        hpv_pattern_heading('Rõ quy cách.<br>Dễ trao đổi chi phí.'),
        hpv_pattern_paragraph('Giá phụ thuộc vật liệu, kích thước, số lượng và địa điểm cung cấp. Gửi nhu cầu cụ thể để nhận phương án báo giá phù hợp.'),
        hpv_pattern_query('hpv_product', 24, 3, 14),
        hpv_pattern_paragraph('Khi có đơn giá cụ thể, vui lòng xác nhận đơn vị tính, phạm vi vật tư, vận chuyển, thi công và thuế trước khi đặt hàng.', 'hpv-table-note'),
    ), 'hpv-quote-page') . "\n\n" . hpv_pattern_section('Câu hỏi trước khi nhận báo giá', array(hpv_pattern_section_heading('Trước khi nhận báo giá', 'Thông tin rõ hơn,<br>phương án sát hơn.'), hpv_pattern_group($questions, 'hpv-faq-list')), 'hpv-faq-section') . "\n\n" . $patterns['contact-section'];

    $patterns['landing-page'] = hpv_pattern_section('Giới thiệu giải pháp', array(
        hpv_pattern_paragraph('Giải pháp cho không gian của bạn', 'hpv-kicker'),
        hpv_pattern_heading('Chọn vật liệu phù hợp.<br>Trao đổi quy cách rõ ràng.'),
        hpv_pattern_paragraph('Khám phá sản phẩm, tham khảo ứng dụng và gửi nhu cầu để Hải Phát Vina tư vấn phương án phù hợp.'),
        hpv_pattern_buttons(array(hpv_pattern_button('Khám phá sản phẩm', '#products'), hpv_pattern_button('Nhận tư vấn', '#contact', true))),
    ), 'hpv-page-intro') . "\n\n" . $patterns['products-section'] . "\n\n" . $patterns['process-section'] . "\n\n" . $patterns['contact-section'];

    $patterns['policy-page'] = hpv_pattern_section('Nội dung chính sách', array(
        hpv_pattern_heading('Thông tin được tiếp nhận'),
        hpv_pattern_paragraph('Khi gửi biểu mẫu tư vấn, bạn cung cấp họ tên, số điện thoại, sản phẩm quan tâm, khu vực và nội dung nhu cầu. Bạn có thể gọi hotline để trao đổi trực tiếp.'),
        hpv_pattern_heading('Mục đích sử dụng'),
        hpv_pattern_paragraph('Hải Phát Vina tiếp nhận thông tin để liên hệ theo yêu cầu, tư vấn quy cách và báo giá. Yêu cầu được lưu trong hệ thống quản trị và có thể được gửi tới email tiếp nhận của công ty.'),
        hpv_pattern_heading('Quản lý thông tin'),
        hpv_pattern_paragraph('Bạn có thể liên hệ công ty để đề nghị kiểm tra, sửa hoặc xóa thông tin đã gửi. Phạm vi xử lý và thời gian lưu giữ được xác nhận theo yêu cầu thực tế và nghĩa vụ áp dụng.'),
        hpv_pattern_heading('Liên kết bên ngoài'),
        hpv_pattern_paragraph('Liên kết Zalo và Google Maps đưa bạn sang dịch vụ bên ngoài. Các dịch vụ đó có chính sách riêng.'),
        hpv_pattern_heading('Liên hệ hỗ trợ'),
        hpv_pattern_block('hpv/company-info', array('layout' => 'contact')),
    ), 'hpv-policy-page');

    return $patterns;
}

/** Offer normal, unsynced patterns; inserting one gives the editor its own copy. */
function hpv_register_site_patterns() {
    if (!function_exists('register_block_pattern')) { return; }
    register_block_pattern_category('hai-phat-vina-site', array('label' => 'Hải Phát Vina — Bố cục website'));
    $titles = array(
        'header' => 'Đầu trang — Logo, menu và hotline',
        'footer' => 'Chân trang — Công ty, chính sách và cơ sở',
        'hero' => 'Ảnh bìa — Tiêu đề chính trang chủ',
        'solutions' => 'Bốn nhóm giải pháp',
        'products-section' => 'Danh mục sản phẩm tự cập nhật',
        'about-section' => 'Giới thiệu — Nội dung và hình ảnh',
        'projects-section' => 'Công trình và ứng dụng tự cập nhật',
        'process-section' => 'Quy trình tư vấn bốn bước',
        'news-section' => 'Kiến thức mới nhất tự cập nhật',
        'contact-section' => 'Tư vấn và biểu mẫu nhận báo giá',
        'home' => 'Trang chủ đầy đủ',
        'about-page' => 'Trang giới thiệu',
        'contact-page' => 'Trang liên hệ',
        'quote-page' => 'Trang báo giá và câu hỏi thường gặp',
        'landing-page' => 'Trang giới thiệu một giải pháp',
        'policy-page' => 'Trang chính sách bảo mật',
    );
    foreach (hpv_site_patterns() as $slug => $content) {
        $properties = array('title' => $titles[$slug], 'categories' => array('hai-phat-vina-site'), 'content' => $content, 'viewportWidth' => 1440);
        if ($slug === 'header' || $slug === 'footer') { $properties['blockTypes'] = array('core/template-part/' . $slug); }
        if (in_array($slug, array('about-page', 'contact-page', 'quote-page', 'landing-page', 'policy-page'), true)) { $properties['postTypes'] = array('page'); }
        register_block_pattern('hai-phat-vina/' . $slug, $properties);
    }
}
add_action('init', 'hpv_register_site_patterns', 20);
