<?php
/** Isolated crontab executable; never modifies the host/container real crontab. */
define('DISABLE_WP_CRON', true);
define('HPV_DESIGN_SYNC_SUPPRESS', true);
$_SERVER['HTTP_HOST'] = '127.0.0.1:8787';
$_SERVER['REQUEST_METHOD'] = 'GET';
require '/var/www/html/wp-load.php';
if (get_option('home') !== 'http://127.0.0.1:8787') { throw new RuntimeException('Disposable local QA only.'); }
$temp = sys_get_temp_dir() . '/hpv-sync-config-qa-' . bin2hex(random_bytes(6));
mkdir($temp, 0700); mkdir($temp . '/bin', 0700); mkdir($temp . '/private', 0700);
$saved = array();
foreach (array('cron', 'hpv_design_sync_configured', HPV_DESIGN_SYNC_DIRTY_OPTION) as $name) { $saved[$name] = get_option($name, null); }
$fake = <<<'PHP'
#!/usr/bin/env php
<?php
$file = getenv('HPV_QA_CRONTAB');
if ($argv[1] === '-l') { echo file_get_contents($file); exit; }
if ($argv[1] === '-') { file_put_contents($file, stream_get_contents(STDIN)); exit; }
exit(2);
PHP;
file_put_contents($temp . '/bin/crontab', $fake); chmod($temp . '/bin/crontab', 0700);
$original_cron = "17 3 * * * /usr/bin/true # existing customer task\n";
file_put_contents($temp . '/crontab', $original_cron);
$env = array_merge(getenv(), array('PATH' => $temp . '/bin:' . getenv('PATH'), 'HPV_QA_CRONTAB' => $temp . '/crontab', 'HPV_QA_PRIVATE' => $temp . '/private'));
$payload = json_encode(array('token' => str_repeat('qa_fake_', 6), 'repository' => 'example/qa'));
$invoke = function ($input, $preflight = false) use ($env) {
    $code = 'define("HPV_DESIGN_SYNC_PRIVATE_ROOT", getenv("HPV_QA_PRIVATE")); $argv = array("configure", "/var/www/html"' . ($preflight ? ', "--check-host"' : '') . '); require "/opt/hpv-deploy-tools/configure-design-sync.php";';
    $pipes = array();
    $process = proc_open(array(PHP_BINARY, '-r', $code), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, $env);
    fwrite($pipes[0], $input); fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return array(proc_close($process), $output . $error);
};
function hpv_sync_config_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
try {
    delete_option('hpv_design_sync_configured');
    $preflight = $invoke($payload, true);
    hpv_sync_config_assert($preflight[0] === 0 && !file_exists($temp . '/private/design-sync-config.php') && file_get_contents($temp . '/crontab') === $original_cron, 'Hosting preflight checks prerequisites without writing credentials or cron');
    $first = $invoke($payload);
    hpv_sync_config_assert($first[0] === 0, 'Private sync installer succeeds with a hosting crontab: ' . trim($first[1]));
    $configured_cron = file_get_contents($temp . '/crontab');
    hpv_sync_config_assert(strpos($configured_cron, $original_cron) === 0 && substr_count($configured_cron, '# hai-phat-vina-design-sync-') === 1, 'Installing queue worker preserves all existing hosting cron entries');
    $config_file = $temp . '/private/design-sync-config.php';
    hpv_sync_config_assert((fileperms($config_file) & 0777) === 0600 && (fileperms($temp . '/private/run-design-sync.php') & 0777) === 0600, 'Credential and private worker files are owner-readable only');
    wp_cache_flush_runtime();
    $generation = hpv_design_sync_generation();
    hpv_sync_config_assert($generation !== '' && wp_next_scheduled(HPV_DESIGN_SYNC_CRON_HOOK), 'First configuration queues one catch-up snapshot');
    $second = $invoke($payload);
    wp_cache_flush_runtime();
    hpv_sync_config_assert($second[0] === 0 && file_get_contents($temp . '/crontab') === $configured_cron && hpv_design_sync_generation() === $generation, 'Repeated configuration is idempotent and does not enqueue another catch-up');
    $before = file_get_contents($config_file);
    $invalid = $invoke('{"token":"invalid"}');
    hpv_sync_config_assert($invalid[0] !== 0 && file_get_contents($config_file) === $before && file_get_contents($temp . '/crontab') === $configured_cron, 'Invalid configuration leaves installed credentials and cron unchanged');
} finally {
    foreach ($saved as $name => $value) { if ($value === null) { delete_option($name); } else { update_option($name, $value); } }
    foreach (glob($temp . '/private/*') as $file) { unlink($file); }
    unlink($temp . '/bin/crontab'); unlink($temp . '/crontab'); rmdir($temp . '/private'); rmdir($temp . '/bin'); rmdir($temp);
}
