<?php
/** Hải Phát Vina theme bootstrap. */
defined('ABSPATH') || exit;
define('HPV_VERSION', '1.1.0');
foreach (array('config', 'content', 'admin', 'contact', 'demo', 'editor') as $hpv_module) {
    require_once get_template_directory() . '/inc/' . $hpv_module . '.php';
}

function hpv_setup() {
    load_theme_textdomain('hai-phat-vina', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', array('search-form', 'gallery', 'caption', 'style', 'script', 'comment-form', 'comment-list'));
    add_theme_support('custom-logo', array('height' => 90, 'width' => 300, 'flex-width' => true, 'flex-height' => true));
    add_theme_support('editor-styles');
    add_theme_support('align-wide');
    add_editor_style('assets/css/editor.css');
    register_nav_menus(array('primary' => 'Menu chính', 'footer' => 'Menu chân trang'));
    add_image_size('hpv-card', 760, 560, true);
    add_image_size('hpv-wide', 1440, 900, true);
}
add_action('after_setup_theme', 'hpv_setup');

function hpv_assets() {
    wp_enqueue_style('hpv-theme', get_stylesheet_uri(), array(), HPV_VERSION);
    wp_enqueue_style('hpv-main', get_template_directory_uri() . '/assets/css/main.css', array('hpv-theme'), HPV_VERSION);
    wp_enqueue_script('hpv-main', get_template_directory_uri() . '/assets/js/main.js', array(), HPV_VERSION, true);
    wp_add_inline_script('hpv-main', 'window.hpvConfig = ' . wp_json_encode(array('ajaxUrl' => admin_url('admin-ajax.php'))) . ';', 'before');
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'hpv_assets');

function hpv_after_switch() {
    hpv_register_content();
    flush_rewrite_rules();
    set_transient('hpv_welcome', 1, DAY_IN_SECONDS);
}
add_action('after_switch_theme', 'hpv_after_switch');

function hpv_head_metadata() {
    if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION')) { return; }
    if (is_search() || is_404()) { return; }
    $description = hpv_get('description');
    if (is_singular() && !is_front_page()) {
        $excerpt = get_post_field('post_excerpt', get_queried_object_id());
        $description = $excerpt ? wp_strip_all_tags($excerpt) : wp_trim_words(wp_strip_all_tags(get_post_field('post_content', get_queried_object_id())), 35);
    }
    if ($description) { echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n"; }
    if (is_front_page()) {
        $schema = array('@context' => 'https://schema.org', '@type' => 'Organization', 'name' => hpv_get('legal_name'), 'url' => home_url('/'), 'telephone' => hpv_get('phone'), 'email' => hpv_get('email'), 'taxID' => hpv_get('tax_id'));
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>' . "\n";
    }
}
add_action('wp_head', 'hpv_head_metadata', 5);
