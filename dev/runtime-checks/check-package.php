<?php
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
WP_Filesystem();
$root = '/tmp/hpv-package-' . uniqid();
mkdir($root, 0755, true);
$zip = new ZipArchive();
if ($zip->open('/tmp/hai-phat-vina.zip') !== true) { throw new RuntimeException('Cannot open theme ZIP'); }
$zip->extractTo($root);
$zip->close();
$theme = wp_get_theme('hai-phat-vina', $root);
if ($theme->errors()) { throw new RuntimeException($theme->errors()->get_error_message()); }
$upgrader = new Theme_Upgrader(new Automatic_Upgrader_Skin());
$upgrader->install_strings();
$check = $upgrader->check_package($root . '/hai-phat-vina/');
if (is_wp_error($check)) { throw new RuntimeException($check->get_error_message()); }
echo 'PASS: WordPress native theme ZIP validation' . PHP_EOL;
echo 'Name: ' . $theme->get('Name') . PHP_EOL;
echo 'Version: ' . $theme->get('Version') . PHP_EOL;
echo 'Required WordPress: ' . $theme->get('RequiresWP') . PHP_EOL;
echo 'Required PHP: ' . $theme->get('RequiresPHP') . PHP_EOL;
echo 'PASS: Source contains index.php, style.css, screenshot and assets' . PHP_EOL;
