<?php defined('ABSPATH') || exit; get_header(); ?>
<section class="container not-found"><span class="section-kicker">Trang chưa có ở đây</span><span class="error-code" aria-hidden="true">404</span><h1>Chúng ta tìm lại nhé.</h1><p>Đường dẫn có thể đã thay đổi. Bạn có thể tìm sản phẩm bên dưới hoặc trở về trang chủ.</p><?php get_search_form(); ?><a class="text-link" href="<?php echo esc_url(home_url('/')); ?>">Về trang chủ <?php echo hpv_icon('arrow'); ?></a></section>
<?php get_footer(); ?>
