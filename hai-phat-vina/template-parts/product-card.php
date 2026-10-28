<?php
defined('ABSPATH') || exit;
$terms = hpv_product_terms(get_the_ID());
$slugs = wp_list_pluck($terms, 'slug');
$price = get_post_meta(get_the_ID(), '_hpv_price', true);
?>
<article class="product-card" data-category="<?php echo esc_attr(implode(' ', $slugs)); ?>">
    <a class="product-image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php hpv_image(get_the_ID()); ?><?php if (hpv_is_illustration(get_the_ID())) : ?><span class="image-note">Ảnh minh họa</span><?php endif; ?></a>
    <div class="product-info"><span class="product-category"><?php echo $terms ? esc_html($terms[0]->name) : 'Sản phẩm'; ?></span><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 21)); ?></p><div class="product-bottom"><span><?php echo esc_html($price ?: 'Liên hệ báo giá'); ?></span><a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr('Xem ' . get_the_title()); ?>"><?php echo hpv_icon('arrow'); ?></a></div></div>
</article>
