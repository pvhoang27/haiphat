<?php
/** Private minute worker installed outside the web root. No content polling. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = isset($argv[1]) ? rtrim($argv[1], '/') : '';
if (!preg_match('~\A/[a-zA-Z0-9_./-]+\z~', $root) || strpos($root, '..') !== false || !is_file($root . '/wp-load.php')) { exit(1); }
if (is_file($root . '/.maintenance')) { exit; }
define('DISABLE_WP_CRON', true);
define('HPV_DESIGN_SYNC_SUPPRESS', true);
define('DOING_CRON', true);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
require $root . '/wp-load.php';
if (!function_exists('hpv_design_sync_process') || hpv_design_sync_generation() === '') { exit; }
if (!wp_next_scheduled(HPV_DESIGN_SYNC_CRON_HOOK)) { hpv_design_sync_schedule(5); }
$due = wp_next_scheduled(HPV_DESIGN_SYNC_CRON_HOOK);
if ($due && $due <= time()) {
    wp_unschedule_event($due, HPV_DESIGN_SYNC_CRON_HOOK);
    hpv_design_sync_process();
}
