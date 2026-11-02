<?php
// Deliberately unguarded, like the production 1.x theme.
function hpv_defaults() { return array('brand' => 'Legacy fixture'); }
add_action('after_setup_theme', function () { register_nav_menus(array('primary' => 'Menu chính')); });
add_action('init', function () {
    register_post_type('hpv_product', array('public' => true));
    register_post_type('hpv_request', array('public' => false));
});
