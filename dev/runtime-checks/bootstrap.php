<?php
// Local disposable WordPress only. Never included in the theme ZIP.
define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST'] = '127.0.0.1:8787';
$_SERVER['REQUEST_METHOD'] = 'GET';
@mkdir('/var/www/html/wp-content/mu-plugins', 0755, true);
file_put_contents('/var/www/html/wp-content/mu-plugins/hpv-local-mail.php', "<?php\n// Prevent external email during local QA.\nadd_filter('pre_wp_mail', '__return_true');\n");
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if (!is_blog_installed()) {
    wp_install('Hải Phát Vina', 'hpv_check', 'hpv-check@example.test', false, '', 'Hpv-local-2026!setup');
    foreach (array(array('hello-world', 'post'), array('sample-page', 'page')) as $item) {
        $post = get_page_by_path($item[0], OBJECT, $item[1]);
        if ($post) { wp_delete_post($post->ID, true); }
    }
}
update_option('home', 'http://127.0.0.1:8787');
update_option('siteurl', 'http://127.0.0.1:8787');
update_option('blogdescription', 'Mái che & bạt công trình');
update_option('timezone_string', 'Asia/Ho_Chi_Minh');
update_option('default_comment_status', 'closed');
update_option('default_ping_status', 'closed');
$wp_rewrite->set_permalink_structure('/%postname%/');
switch_theme('hai-phat-vina');
flush_rewrite_rules();
echo 'Local WordPress installed. Version: ' . get_bloginfo('version') . PHP_EOL;
echo 'Theme: ' . get_stylesheet() . PHP_EOL;
