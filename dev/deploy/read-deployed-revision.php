<?php
/** SSH stdin only: stdout is a validated healthy revision or an empty string. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = isset($argv[1]) ? rtrim($argv[1], '/') : '';
if (!preg_match('~\A/[a-zA-Z0-9_./-]+\z~', $root) || strpos($root, '..') !== false || !is_file($root . '/wp-load.php')) { exit(1); }
// An interrupted release can leave old DB revision metadata beside partially
// switched code. Never treat that old healthy marker as an unchanged site.
if (is_file($root . '/.maintenance')) { fwrite(STDERR, "Production is in maintenance; inspect the interrupted release before comparing code.\n"); exit(1); }
define('DISABLE_WP_CRON', true);
define('HPV_DESIGN_SYNC_SUPPRESS', true);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    require $root . '/wp-load.php';
    $revision = get_option('hpv_deployed_revision', '');
    $path = rtrim(rawurldecode((string) parse_url(site_url(), PHP_URL_PATH)), '/');
    $document_root = $root;
    if ($path !== '' && substr($root, -strlen($path)) === $path) { $document_root = substr($root, 0, -strlen($path)); }
    $file = dirname($document_root) . '/haiphat-deploy-backups/' . $revision . '/state.json';
    $healthy = false;
    if (is_string($revision) && preg_match('/\A[a-f0-9]{40}\z/', $revision) && is_file($file)) {
        $state = json_decode(file_get_contents($file), true);
        $healthy = isset($state['status']) && $state['status'] === 'healthy';
    }
    ob_end_clean();
    if ($healthy) { echo $revision; }
} catch (Throwable $error) {
    if (ob_get_level()) { ob_end_clean(); }
    fwrite(STDERR, "Cannot read the healthy production revision.\n"); exit(1);
}
