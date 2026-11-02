<?php
/** SSH/CLI only. stdout is one JSON document, never a web endpoint. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$hpv_export_root = isset($argv[1]) ? rtrim($argv[1], '/') : '';
if (!preg_match('~^/[a-zA-Z0-9_./-]+$~D', $hpv_export_root) || strpos($hpv_export_root, '..') !== false || !is_file($hpv_export_root . '/wp-config.php')) {
    fwrite(STDERR, "Invalid WordPress root.\n"); exit(1);
}
if (is_file($hpv_export_root . '/.maintenance')) { fwrite(STDERR, "Deployment in progress; retry export later.\n"); exit(1); }
define('DISABLE_WP_CRON', true);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    require $hpv_export_root . '/wp-load.php';
    // Same private lock location as release.php, including WordPress subfolders.
    $hpv_url_path = rtrim(rawurldecode((string) parse_url(site_url(), PHP_URL_PATH)), '/');
    $hpv_document_root = $hpv_export_root;
    if ($hpv_url_path !== '' && substr($hpv_export_root, -strlen($hpv_url_path)) === $hpv_url_path) {
        $hpv_document_root = substr($hpv_export_root, 0, -strlen($hpv_url_path));
    }
    $hpv_lock_path = dirname($hpv_document_root) . '/haiphat-deploy-backups/deploy.lock';
    if (!is_file($hpv_lock_path)) { throw new RuntimeException('Deployment lock is not initialized; deploy the site before syncing.'); }
    $hpv_design_lock = fopen($hpv_lock_path, 'r');
    if (!$hpv_design_lock || !flock($hpv_design_lock, LOCK_SH | LOCK_NB)) { throw new RuntimeException('Deployment is active; retry design export later.'); }
    clearstatcache(true, $hpv_export_root . '/.maintenance');
    if (is_file($hpv_export_root . '/.maintenance')) { throw new RuntimeException('Deployment in progress; retry design export later.'); }
    require_once $hpv_export_root . '/wp-content/plugins/hai-phat-vina-core/includes/design-export.php';
    // A consistent database snapshot also covers edits saved while export runs.
    $wpdb->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    if ($wpdb->query('START TRANSACTION WITH CONSISTENT SNAPSHOT') === false) { throw new RuntimeException('Cannot start consistent design snapshot.'); }
    // Clear process-local cached entries before reading the saved design.
    wp_suspend_cache_addition(true);
    wp_cache_flush_runtime();
    $hpv_design_export = hpv_export_design();
    $wpdb->query('COMMIT');
    ob_end_clean();
    $hpv_export_json = hpv_design_json($hpv_design_export);
    if (strlen($hpv_export_json) > 20 * 1024 * 1024) { throw new RuntimeException('Design export exceeds 20 MiB.'); }
    flock($hpv_design_lock, LOCK_UN);
    fclose($hpv_design_lock);
    echo $hpv_export_json;
} catch (Throwable $error) {
    if (isset($wpdb)) { $wpdb->query('ROLLBACK'); }
    if (ob_get_level()) { ob_end_clean(); }
    fwrite(STDERR, $error->getMessage() . "\n"); exit(1);
}
