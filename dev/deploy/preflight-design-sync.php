<?php
/** SSH stdin only. Check availability; never load WP, write files or edit cron. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = isset($argv[1]) ? rtrim($argv[1], '/') : '';
try {
    if (!preg_match('~\A/[a-zA-Z0-9_./-]+\z~', $root) || strpos($root, '..') !== false || !is_readable($root . '/wp-load.php')) { throw new RuntimeException('WordPress root is unavailable.'); }
    if (version_compare(PHP_VERSION, '7.4', '<') || !extension_loaded('mysqli') || !function_exists('flock') || !function_exists('proc_open')) { throw new RuntimeException('PHP CLI needs version 7.4+, mysqli, flock and proc_open.'); }
    if (is_file($root . '/.maintenance')) { throw new RuntimeException('WordPress is in maintenance; inspect the active deployment first.'); }
    $pipes = array();
    $process = proc_open(array('crontab', '-l'), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, array_merge(getenv(), array('LC_ALL' => 'C')));
    if (!is_resource($process)) { throw new RuntimeException('Cannot execute hosting crontab.'); }
    fclose($pipes[0]);
    // Existing cron lines can contain credentials; never print or persist them.
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 && !($status === 1 && strpos(strtolower($error), 'no crontab') !== false)) { throw new RuntimeException('Hosting does not allow this account to read its crontab.'); }
    echo "PASS: SSH, WordPress path, PHP CLI and hosting crontab are available. No production state changed.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
