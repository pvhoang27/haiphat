<?php
/** Trusted script supplied over SSH stdin, never a public endpoint. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = isset($argv[1]) ? rtrim($argv[1], '/') : '';
$hash = isset($argv[2]) ? $argv[2] : '';
$generation = isset($argv[3]) ? $argv[3] : '';
if (!preg_match('~\A/[a-zA-Z0-9_./-]+\z~', $root) || strpos($root, '..') !== false || !is_file($root . '/wp-load.php')
    || !preg_match('/\A[a-f0-9]{64}\z/', $hash) || ($generation !== '' && !preg_match('/\A[0-9]{10}:[a-f0-9]{32}\z/', $generation))) {
    fwrite(STDERR, "Invalid sync acknowledgement.\n"); exit(1);
}
define('DISABLE_WP_CRON', true);
define('HPV_DESIGN_SYNC_SUPPRESS', true);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
try {
    require $root . '/wp-load.php';
    if (!function_exists('hpv_design_sync_acknowledge')) { echo "Legacy exporter; no pending event to acknowledge.\n"; exit; }
    $locks = hpv_design_sync_acquire_locks();
    if ($locks === false) { throw new RuntimeException('Deployment or sync worker is active; retry acknowledgement.'); }
    try { hpv_design_sync_acknowledge($hash, $generation); }
    finally { hpv_design_sync_release_locks($locks); }
    echo "Design snapshot acknowledged after successful Git/PR update.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
