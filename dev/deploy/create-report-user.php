<?php
/** Explicit operator action only. Credentials arrive on stdin, never in source. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = $argv[1] ?? '';
if (!$root || !is_file($root . '/wp-load.php')) { fwrite(STDERR, "Invalid WordPress root.\n"); exit(1); }
$credentials = json_decode(stream_get_contents(STDIN), true);
if (!is_array($credentials) || !is_string($credentials['username'] ?? null) || !is_string($credentials['password'] ?? null) || strlen($credentials['password']) < 8) { fwrite(STDERR, "Invalid account input.\n"); exit(1); }
require $root . '/wp-load.php';
if (!function_exists('hpv_analytics_install')) { fwrite(STDERR, "Reporting must be deployed first.\n"); exit(1); }
hpv_analytics_install();
$login = sanitize_user($credentials['username'], true);
if ($login !== $credentials['username'] || !$login) { fwrite(STDERR, "Invalid username.\n"); exit(1); }
if (username_exists($login)) { fwrite(STDERR, "Account exists; no password or role changes made.\n"); exit(1); }
$id = wp_insert_user(array('user_login' => $login, 'user_pass' => $credentials['password'], 'display_name' => $login, 'role' => 'hpv_report_viewer'));
unset($credentials);
if (is_wp_error($id)) { fwrite(STDERR, "Account creation failed.\n"); exit(1); }
$user = get_user_by('id', $id);
if (!user_can($user, 'hpv_view_reports') || user_can($user, 'manage_options') || user_can($user, 'edit_posts')) { fwrite(STDERR, "Unexpected account permissions.\n"); exit(1); }
echo "Report account created with read-only access.\n";
