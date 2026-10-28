<?php defined('ABSPATH') || exit; get_header(); ?>
<div class="container"><?php hpv_breadcrumbs(); ?></div>
<?php while (have_posts()) : the_post(); ?><article class="container standard-page"><header class="page-intro"><h1><?php the_title(); ?></h1></header><div class="prose"><?php the_content(); wp_link_pages(); ?></div><?php if (comments_open() || get_comments_number()) { comments_template(); } ?></article><?php endwhile; ?>
<?php get_footer(); ?>
