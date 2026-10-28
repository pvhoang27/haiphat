<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Chuyển đến nội dung</a>
<div class="topbar"><div class="container topbar-inner"><span><?php echo esc_html(hpv_get('legal_name')); ?></span><a href="mailto:<?php echo esc_attr(hpv_get('email')); ?>"><?php echo hpv_icon('mail'); ?><?php echo esc_html(hpv_get('email')); ?></a><a href="<?php echo esc_url(hpv_page_url('contact')); ?>"><?php echo hpv_icon('pin'); ?>Địa chỉ các cơ sở</a></div></div>
<header class="site-header"><div class="container header-inner">
    <?php hpv_brand(); ?>
    <button class="menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false"><span class="menu-open-icon"><?php echo hpv_icon('menu'); ?></span><span class="menu-close-icon"><?php echo hpv_icon('close'); ?></span><span class="sr-only">Mở / đóng menu</span></button>
    <nav id="primary-navigation" class="primary-navigation" aria-label="Menu chính">
        <?php
        if (has_nav_menu('primary')) {
            wp_nav_menu(array('theme_location' => 'primary', 'container' => false, 'menu_class' => 'primary-menu', 'depth' => 2));
        } else {
            echo '<ul class="primary-menu">';
            foreach (array('Trang chủ' => home_url('/'), 'Sản phẩm' => hpv_products_url(), 'Báo giá' => hpv_page_url('quote'), 'Ứng dụng' => hpv_projects_url(), 'Về Hải Phát' => hpv_page_url('about'), 'Liên hệ' => hpv_page_url('contact')) as $label => $url) { echo '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>'; }
            echo '</ul>';
        }
        ?>
    </nav>
    <a class="header-phone" href="<?php echo esc_url(hpv_phone_url()); ?>"><?php echo hpv_icon('phone'); ?><span><small>Tư vấn & báo giá</small><strong><?php echo esc_html(hpv_get('phone')); ?></strong></span></a>
</div></header>
<main id="main" tabindex="-1">
