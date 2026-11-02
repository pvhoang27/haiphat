<?php
/** Template Name: Liên hệ Hải Phát Vina */
defined('ABSPATH') || exit;
get_header(); ?>
<div class="contact-page">
    <div class="container"><?php hpv_breadcrumbs(); ?></div>

    <header class="container contact-page-heading">
        <h1>Liên hệ Hải Phát Vina</h1>
        <p>Để lại nhu cầu của bạn để được tư vấn quy cách và nhận báo giá phù hợp.</p>
    </header>

    <section class="container contact-page-layout" id="contact" aria-label="Liên hệ và tư vấn">
        <div class="contact-form-panel" id="quote-result" aria-labelledby="contact-form-title">
            <div class="contact-form-heading">
                <h2 id="contact-form-title">Thông tin của bạn</h2>
                <p>Điền các mục có dấu <span aria-hidden="true">*</span> để chúng tôi có thể liên hệ.</p>
            </div>
            <?php hpv_quote_form('contact'); ?>
        </div>

        <aside class="contact-page-sidebar" aria-label="Thông tin Hải Phát Vina">
            <section class="contact-direct" aria-labelledby="contact-direct-title">
                <h2 id="contact-direct-title">Trao đổi trực tiếp</h2>
                <p>Gọi hoặc nhắn Zalo nếu bạn muốn trao đổi ngay về sản phẩm và quy cách.</p>
                <a class="contact-hotline" href="<?php echo esc_url(hpv_phone_url()); ?>"><?php echo hpv_icon('phone'); ?><span><small>Hotline tư vấn</small><strong><?php echo esc_html(hpv_get('phone')); ?></strong></span></a>
                <a class="contact-zalo-link" href="<?php echo esc_url(hpv_zalo_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo hpv_icon('chat'); ?>Nhắn Zalo<?php echo hpv_icon('arrow'); ?></a>
                <a class="contact-email-link" href="<?php echo esc_url('mailto:' . hpv_get('email')); ?>"><?php echo hpv_icon('mail'); ?><span><?php echo esc_html(hpv_get('email')); ?></span></a>
            </section>

            <section class="contact-business" aria-labelledby="contact-business-title">
                <h2 id="contact-business-title">Thông tin doanh nghiệp</h2>
                <p class="contact-business-name"><?php echo esc_html(hpv_get('legal_name')); ?></p>
                <dl><div><dt>Mã số thuế</dt><dd><?php echo esc_html(hpv_get('tax_id')); ?></dd></div></dl>
                <a class="contact-address-link" href="#contact-locations"><?php echo hpv_icon('pin'); ?>Xem địa chỉ 4 cơ sở<?php echo hpv_icon('chevron'); ?></a>
            </section>
        </aside>
    </section>

    <?php while (have_posts()) : the_post(); ?>
        <?php if (get_the_content()) : ?>
            <div class="container contact-page-extra"><div class="prose"><?php the_content(); wp_link_pages(); ?></div></div>
        <?php endif; ?>
    <?php endwhile; ?>

    <section class="contact-locations-section" id="contact-locations" aria-labelledby="contact-locations-title">
        <div class="container">
            <div class="contact-locations-heading">
                <h2 id="contact-locations-title">Ghé thăm Hải Phát Vina</h2>
                <p>Vui lòng gọi trước để xác nhận vị trí và sắp xếp người hỗ trợ.</p>
            </div>
            <div class="contact-branches">
                <?php for ($i = 1; $i <= 4; $i++) : ?>
                    <article class="contact-branch">
                        <div class="contact-branch-label"><?php echo hpv_icon('pin'); ?><span>Cơ sở <?php echo (int) $i; ?></span></div>
                        <h3><?php echo esc_html(hpv_get('address_' . $i)); ?></h3>
                        <a href="<?php echo esc_url(hpv_map_url(hpv_get('address_' . $i))); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr('Mở Google Maps cơ sở ' . $i); ?>">Mở Google Maps<?php echo hpv_icon('arrow'); ?></a>
                    </article>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <section class="container contact-payment" aria-labelledby="contact-payment-title">
        <h2 id="contact-payment-title">Thông tin chuyển khoản</h2>
        <div class="contact-payment-details">
            <div class="contact-account-copy"><strong><?php echo esc_html(hpv_get('bank_account')); ?></strong><button class="contact-copy-account" type="button" data-copy="<?php echo esc_attr(hpv_get('bank_account')); ?>" aria-label="Sao chép số tài khoản"><?php echo hpv_icon('copy'); ?></button><span class="copy-status sr-only" role="status" aria-live="polite"></span></div>
            <p><?php echo esc_html(hpv_get('bank_name')); ?></p>
        </div>
        <p class="contact-payment-note">Vui lòng xác nhận đơn hàng và nội dung thanh toán qua hotline trước khi chuyển khoản.</p>
    </section>
</div>
<?php get_footer(); ?>
