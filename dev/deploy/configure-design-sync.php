<?php
/** Configure private dispatch credentials and one isolated hosting cron entry. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
$root = isset($argv[1]) ? rtrim($argv[1], '/') : '';
if (!preg_match('~\A/[a-zA-Z0-9_./-]+\z~', $root) || strpos($root, '..') !== false || !is_file($root . '/wp-load.php')) { fwrite(STDERR, "Invalid WordPress root.\n"); exit(1); }
define('DISABLE_WP_CRON', true);
define('HPV_DESIGN_SYNC_SUPPRESS', true);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
function hpv_sync_crontab($args, $input = '') {
    if (!function_exists('proc_open')) { throw new RuntimeException('PHP CLI must allow proc_open to manage the hosting queue worker.'); }
    $pipes = array();
    $process = proc_open(array_merge(array('crontab'), $args), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, array_merge(getenv(), array('LC_ALL' => 'C')));
    if (!is_resource($process)) { throw new RuntimeException('Cannot run hosting crontab.'); }
    fwrite($pipes[0], $input); fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return array(proc_close($process), $output, $error);
}
function hpv_sync_read_crontab() {
    $existing = hpv_sync_crontab(array('-l'));
    if ($existing[0] !== 0 && !($existing[0] === 1 && strpos(strtolower($existing[2]), 'no crontab') !== false)) { throw new RuntimeException('Hosting crontab is unavailable; configure the documented hosting cron before cutover.'); }
    return $existing[0] === 0 ? $existing[1] : '';
}
try {
    $input = json_decode(stream_get_contents(STDIN, 4096), true);
    if (!is_array($input) || !isset($input['token'], $input['repository'])
        || !is_string($input['token']) || !preg_match('/\A[A-Za-z0-9_]{30,512}\z/', $input['token'])
        || !is_string($input['repository']) || !preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~', $input['repository'])) {
        throw new RuntimeException('Missing or invalid DESIGN_SYNC_DISPATCH_TOKEN/repository.');
    }
    if (isset($argv[2]) && $argv[2] === '--check-host') {
        hpv_sync_read_crontab();
        echo "Hosting queue prerequisites verified; no configuration changed.\n";
        exit;
    }
    require $root . '/wp-load.php';
    if (!function_exists('hpv_design_sync_private_root')) { throw new RuntimeException('Deploy the event-driven Core plugin first.'); }
    $private = hpv_design_sync_private_root();
    if (!is_dir($private) || is_link($private)) { throw new RuntimeException('Private deployment directory is unavailable.'); }
    $lock = fopen($private . '/deploy.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Deployment is busy; retry sync configuration.'); }
    $old_cron = hpv_sync_read_crontab();
    $marker = '# hai-phat-vina-design-sync-' . substr(hash('sha256', $root), 0, 12);
    $lines = array_filter(explode("\n", rtrim($old_cron, "\n")), function ($line) use ($marker) { return strpos($line, $marker) === false; });
    $runner = $private . '/run-design-sync.php';
    $lines[] = '* * * * * ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' ' . escapeshellarg($root) . ' >/dev/null 2>&1 ' . $marker;
    $new_cron = implode("\n", $lines) . "\n";
    $config = "<?php\nreturn " . var_export(array('token' => $input['token'], 'repository' => $input['repository']), true) . ";\n";
    $temp = tempnam($private, '.design-sync-');
    if ($temp === false || file_put_contents($temp, $config, LOCK_EX) === false || !chmod($temp, 0600) || !rename($temp, $private . '/design-sync-config.php')) { throw new RuntimeException('Cannot write private sync credentials.'); }
    if (!copy(__DIR__ . '/run-design-sync.php', $runner) || !chmod($runner, 0600)) { throw new RuntimeException('Cannot install private sync worker.'); }
    if ($new_cron !== $old_cron) {
        file_put_contents($private . '/crontab-before-design-sync.txt', $old_cron, LOCK_EX);
        $installed = hpv_sync_crontab(array('-'), $new_cron);
        if ($installed[0] !== 0) { throw new RuntimeException('Cannot install hosting cron; existing entries were preserved.'); }
    }
    if (!get_option('hpv_design_sync_configured')) {
        if (!hpv_design_sync_write_generation(time() . ':' . bin2hex(random_bytes(16)))) { throw new RuntimeException('Cannot enqueue initial design snapshot.'); }
        hpv_design_sync_schedule(90);
        update_option('hpv_design_sync_configured', true, false);
    }
    flock($lock, LOCK_UN); fclose($lock);
    echo "Private dispatch credential and hosting queue worker configured.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
