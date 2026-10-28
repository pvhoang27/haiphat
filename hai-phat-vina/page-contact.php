<?php
/** Template Name: Liên hệ Hải Phát Vina */
defined('ABSPATH') || exit;
get_header(); ?>
<div class="container"><?php hpv_breadcrumbs(); ?></div>
<section class="page-intro container"><span class="section-kicker">Kết nối cùng Hải Phát Vina</span><h1>Cùng trao đổi<br>về nhu cầu của bạn.</h1><p>Gọi hotline, nhắn Zalo hoặc gửi thông tin để được tư vấn sản phẩm và quy cách phù hợp.</p></section>
<section class="container contact-options"><a href="<?php echo esc_url(hpv_phone_url()); ?>"><?php echo hpv_icon('phone'); ?><span>Hotline<strong><?php echo esc_html(hpv_get('phone')); ?></strong></span></a><a href="mailto:<?php echo esc_attr(hpv_get('email')); ?>"><?php echo hpv_icon('mail'); ?><span>Email<strong><?php echo esc_html(hpv_get('email')); ?></strong></span></a><a href="<?php echo esc_url(hpv_zalo_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo hpv_icon('chat'); ?><span>Trao đổi qua Zalo<strong>Nhắn tin cho Hải Phát Vina</strong></span></a></section>
<?php while (have_posts()) { the_post(); if (get_the_content()) { echo '<div class="container prose page-extra">'; the_content(); echo '</div>'; } } ?>
<?php get_template_part('template-parts/contact-section'); ?>
<section class="section locations-section"><div class="container"><div class="section-heading"><div><span class="section-kicker">Các cơ sở liên hệ</span><h2>Hẹn gặp tại Hải Phát Vina.</h2></div><p>Vui lòng gọi trước để xác nhận vị trí và sắp xếp người hỗ trợ.</p></div><div class="locations-grid"><?php for ($i = 1; $i <= 4; $i++) : ?><article class="location-card"><?php echo hpv_icon('pin'); ?><span>Cơ sở <?php echo (int) $i; ?></span><h3><?php echo esc_html(hpv_get('address_' . $i)); ?></h3><a class="text-link" href="<?php echo esc_url(hpv_map_url(hpv_get('address_' . $i))); ?>" target="_blank" rel="noopener noreferrer">Mở Google Maps <?php echo hpv_icon('arrow'); ?></a></article><?php endfor; ?></div></div></section>
<?php get_footer(); ?>
