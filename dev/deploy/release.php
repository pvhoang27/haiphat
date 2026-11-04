<?php
/** CLI-only release lifecycle. Never deployed inside the active theme/plugin. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
$hpvd_action = isset($argv[1]) ? $argv[1] : '';
$root = isset($argv[2]) ? rtrim($argv[2], '/') : '';
$revision = isset($argv[3]) ? $argv[3] : '';
if (!preg_match('~^/[a-zA-Z0-9_./-]+$~', $root) || strpos($root, '..') !== false || !preg_match('/^[a-f0-9]{40}$/', $revision) || !is_file($root . '/wp-load.php')) {
    fwrite(STDERR, "Invalid WordPress root or release revision.\n"); exit(1);
}
$stage = $root . '/wp-content/.hpv-releases/' . $revision;
$private_root = isset($argv[4]) ? rtrim($argv[4], '/') : dirname($root) . '/haiphat-deploy-backups';
if (!preg_match('~^/[a-zA-Z0-9_./-]+/haiphat-deploy-backups$~', $private_root) || strpos($private_root, '..') !== false) { fwrite(STDERR, "Invalid private backup root.\n"); exit(1); }
$backup = $private_root . '/' . $revision;
$code_backup = $stage . '/previous-code';
$state_file = $backup . '/state.json';
$theme = $root . '/wp-content/themes/hai-phat-vina';
$plugin = $root . '/wp-content/plugins/hai-phat-vina-core';
$maintenance_owned = false;

function hpvd_write_state($file, $state) {
    if (file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) { throw new RuntimeException('Cannot save release state.'); }
    chmod($file, 0600);
}
function hpvd_load_wp($root) {
    if (!defined('DISABLE_WP_CRON')) { define('DISABLE_WP_CRON', true); }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'localhost';
    require $root . '/wp-load.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/theme.php';
}
function hpvd_identifier($name) { return '`' . str_replace('`', '``', $name) . '`'; }
function hpvd_private_root($root) {
    // When WordPress lives in a subdirectory, dirname(ABSPATH) can still be
    // public. Remove the site's URL path before choosing a private sibling.
    $url_path = rtrim(rawurldecode((string) parse_url(site_url(), PHP_URL_PATH)), '/');
    $document_root = $root;
    if ($url_path !== '' && substr($root, -strlen($url_path)) === $url_path) { $document_root = substr($root, 0, -strlen($url_path)); }
    if ($document_root === '' || $document_root === '/') { throw new RuntimeException('Cannot safely resolve a private backup location.'); }
    return dirname($document_root) . '/haiphat-deploy-backups';
}
function hpvd_dump_database($file) {
    global $wpdb;
    if (!($wpdb->dbh instanceof mysqli)) { throw new RuntimeException('Deployment requires the WordPress mysqli driver.'); }
    $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix) . '%'));
    if (!$tables) { throw new RuntimeException('No WordPress tables found; refusing an empty backup.'); }
    $stream = fopen($file, 'xb');
    if (!$stream) { throw new RuntimeException('Cannot create private database backup.'); }
    try {
        fwrite($stream, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
        foreach ($tables as $table) {
            $quoted = hpvd_identifier($table);
            $create = $wpdb->get_row('SHOW CREATE TABLE ' . $quoted, ARRAY_N);
            if (!$create) { throw new RuntimeException('Cannot read a WordPress table definition.'); }
            fwrite($stream, 'DROP TABLE IF EXISTS ' . $quoted . ";\n" . $create[1] . ";\n");
            $rows = $wpdb->dbh->query('SELECT * FROM ' . $quoted, MYSQLI_USE_RESULT);
            if (!$rows) { throw new RuntimeException('Cannot read WordPress data for backup.'); }
            try {
                while ($row = $rows->fetch_row()) {
                    $values = array();
                    foreach ($row as $value) { $values[] = $value === null ? 'NULL' : "'" . $wpdb->dbh->real_escape_string($value) . "'"; }
                    if (fwrite($stream, 'INSERT INTO ' . $quoted . ' VALUES (' . implode(',', $values) . ");\n") === false) { throw new RuntimeException('Database backup write failed.'); }
                }
            } finally { $rows->free(); }
        }
        fwrite($stream, "SET FOREIGN_KEY_CHECKS=1;\n");
        fflush($stream);
    } finally { fclose($stream); }
    chmod($file, 0600);
    return $tables;
}
/** Stream SQL; quoted semicolons and escaped strings do not split statements. */
function hpvd_restore_database($file) {
    global $wpdb;
    $stream = fopen($file, 'rb');
    if (!$stream) { throw new RuntimeException('Cannot open the database rollback snapshot.'); }
    $statement = ''; $quote = ''; $escaped = false;
    try {
        while (($char = fgetc($stream)) !== false) {
            $statement .= $char;
            if ($quote !== '') {
                if ($escaped) { $escaped = false; }
                elseif ($char === '\\') { $escaped = true; }
                elseif ($char === $quote) { $quote = ''; }
            } elseif ($char === "'" || $char === '"' || $char === '`') { $quote = $char; }
            elseif ($char === ';') {
                if ($wpdb->query($statement) === false) { throw new RuntimeException('Database rollback failed; private snapshot retained.'); }
                $statement = '';
            }
        }
        if (trim($statement) !== '') { throw new RuntimeException('Incomplete SQL rollback snapshot.'); }
    } finally { fclose($stream); }
    wp_cache_flush();
}
function hpvd_move($from, $to) {
    if (!rename($from, $to)) { throw new RuntimeException('Could not switch release directory: ' . basename($from)); }
}

try {
    if ($hpvd_action === 'backup-root') {
        hpvd_load_wp($root);
        echo hpvd_private_root($root) . PHP_EOL;
        exit;
    }
    if ($hpvd_action === 'current') {
        if (!is_file($state_file)) { exit(1); }
        $previous = json_decode(file_get_contents($state_file), true);
        hpvd_load_wp($root);
        if ($private_root !== hpvd_private_root($root)) { throw new RuntimeException('Backup directory must be outside the public document root.'); }
        exit(isset($previous['status']) && $previous['status'] === 'healthy' && get_option('hpv_deployed_revision') === $revision ? 0 : 1);
    }
    if ($hpvd_action === 'prepare') {
        if (is_file($root . '/.maintenance')) { throw new RuntimeException('Website is already in maintenance mode.'); }
        if (!is_file($stage . '/theme/templates/index.html') || !is_file($stage . '/plugin/hai-phat-vina-core.php')) { throw new RuntimeException('Incomplete staged release.'); }
        if (is_link($theme) || is_link($plugin)) { throw new RuntimeException('Deployment targets must be real directories, not symlinks.'); }
        hpvd_load_wp($root);
        if ($private_root !== hpvd_private_root($root)) { throw new RuntimeException('Backup directory must be outside the public document root.'); }
        if (get_template() !== 'hai-phat-vina' || version_compare(get_bloginfo('version'), '6.6', '<') || version_compare(PHP_VERSION, '7.4', '<')) { throw new RuntimeException('Active theme or WordPress/PHP version does not meet release requirements.'); }
        if (is_dir($backup)) {
            $previous = is_file($state_file) ? json_decode(file_get_contents($state_file), true) : array();
            if (!isset($previous['status']) || !in_array($previous['status'], array('rolled-back', 'prepare-failed'), true)) { throw new RuntimeException('An unfinished release already has a backup; inspect it before retrying.'); }
            hpvd_move($backup, $backup . '-previous-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3)));
        }
        if (!mkdir($backup, 0700, true)) { throw new RuntimeException('Cannot create a private backup directory outside the document root.'); }
        if (is_dir($code_backup)) { hpvd_move($code_backup, $code_backup . '-previous-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3))); }
        if (!mkdir($code_backup, 0700)) { throw new RuntimeException('Cannot prepare same-filesystem code backup.'); }
        $state = array('revision' => $revision, 'created' => gmdate('c'), 'had_plugin' => is_dir($plugin), 'tables' => array(), 'home' => home_url('/'), 'status' => 'preparing');
        hpvd_write_state($state_file, $state);
        // HTTP receives WordPress's maintenance response; CLI can finish/rollback.
        $maintenance = '<?php $upgrading = PHP_SAPI === "cli" ? 0 : ' . time() . '; // hpv-' . $revision . "\n";
        if (file_put_contents($root . '/.maintenance', $maintenance, LOCK_EX) === false) { throw new RuntimeException('Cannot enable deployment maintenance mode.'); }
        $maintenance_owned = true;
        $state['tables'] = hpvd_dump_database($backup . '/database.sql');
        $state['status'] = 'prepared'; hpvd_write_state($state_file, $state);
        wp_clean_themes_cache(true);
        echo "Private database backup prepared.\n";
        exit;
    }
    if (!is_file($state_file)) { throw new RuntimeException('Missing release state; no deployment changes will be made.'); }
    $state = json_decode(file_get_contents($state_file), true);
    if (!$state || $state['revision'] !== $revision) { throw new RuntimeException('Release state mismatch.'); }
    if ($hpvd_action === 'install') {
        hpvd_move($theme, $code_backup . '/theme');
        if ($state['had_plugin']) { hpvd_move($plugin, $code_backup . '/plugin'); }
        hpvd_move($stage . '/plugin', $plugin);
        hpvd_move($stage . '/theme', $theme);
        $state['status'] = 'installed'; hpvd_write_state($state_file, $state);
        echo "Theme and companion plugin switched.\n";
    } elseif ($hpvd_action === 'finish') {
        hpvd_load_wp($root);
        wp_clean_themes_cache(true);
        $activated = activate_plugin('hai-phat-vina-core/hai-phat-vina-core.php');
        if (is_wp_error($activated)) { throw new RuntimeException($activated->get_error_message()); }
        if (!defined('HPV_CORE_VERSION') || !wp_is_block_theme()) { throw new RuntimeException('The block theme or business plugin did not load.'); }
        hpv_setup_business_roles();
        if (!get_option('hpv_block_setup_complete')) {
            $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
            if (!$admins) { throw new RuntimeException('No website owner exists to authorize page migration.'); }
            wp_set_current_user($admins[0]);
            $migrated = hpv_prepare_block_site();
            if (is_wp_error($migrated)) { throw new RuntimeException($migrated->get_error_message()); }
        }
        if (!get_option('hpv_customer_structure_v1')) {
            $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
            if (!$admins) { throw new RuntimeException('No administrator exists to prepare the requested site structure.'); }
            wp_set_current_user($admins[0]);
            $structure = hpv_setup_customer_structure();
            if (is_wp_error($structure)) { throw new RuntimeException($structure->get_error_message()); }
        }
        if (!get_option('hpv_compact_footer_v1')) {
            $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
            if (!$admins) { throw new RuntimeException('No administrator exists to adjust the footer.'); }
            wp_set_current_user($admins[0]);
            $compacted = hpv_compact_customer_footer();
            if (is_wp_error($compacted)) { throw new RuntimeException($compacted->get_error_message()); }
        }
        if (!get_option('hpv_footer_branches_v1')) {
            $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
            if (!$admins) { throw new RuntimeException('No administrator exists to adjust the footer.'); }
            wp_set_current_user($admins[0]);
            $restored = hpv_restore_customer_footer_branches();
            if (is_wp_error($restored)) { throw new RuntimeException($restored->get_error_message()); }
        }
        $corrected = hpv_correct_customer_structure();
        if (is_wp_error($corrected)) { throw new RuntimeException($corrected->get_error_message()); }
        update_option('hpv_deployed_revision', $revision, false);
        wp_clean_themes_cache(true);
        flush_rewrite_rules();
        wp_cache_flush();
        $state['status'] = 'finished'; hpvd_write_state($state_file, $state);
        echo "Plugin active; existing site pages ready. No demo content was imported.\n";
    } elseif ($hpvd_action === 'open') {
        $maintenance = $root . '/.maintenance';
        if (is_file($maintenance) && strpos(file_get_contents($maintenance), 'hpv-' . $revision) !== false) { unlink($maintenance); }
        echo "Website available.\n";
    } elseif ($hpvd_action === 'health') {
        hpvd_load_wp($root);
        $response = wp_remote_get(add_query_arg('hpv_deploy', $revision, home_url('/')), array('timeout' => 30, 'redirection' => 5, 'headers' => array('Cache-Control' => 'no-cache'), 'user-agent' => 'HaiPhatDeployment/' . $revision));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { throw new RuntimeException('Public homepage health check failed.'); }
        $body = wp_remote_retrieve_body($response);
        if (strpos($body, 'hpv-site-header') === false || strpos($body, 'hpv-site-footer') === false || strpos($body, '<main') === false) { throw new RuntimeException('Public response is not the deployed block theme.'); }
        $state['status'] = 'healthy'; hpvd_write_state($state_file, $state);
        echo 'Deployed revision ' . $revision . " is healthy.\n";
    } elseif ($hpvd_action === 'rollback') {
        // Recover exact previous directories without deleting either release.
        if (is_dir($code_backup . '/theme')) {
            if (is_dir($theme)) { hpvd_move($theme, $code_backup . '/failed-theme'); }
            hpvd_move($code_backup . '/theme', $theme);
        }
        if (is_dir($code_backup . '/plugin')) {
            if (is_dir($plugin)) { hpvd_move($plugin, $code_backup . '/failed-plugin'); }
            hpvd_move($code_backup . '/plugin', $plugin);
        } elseif (!$state['had_plugin'] && !is_dir($stage . '/plugin') && is_dir($plugin)) { hpvd_move($plugin, $code_backup . '/failed-plugin'); }
        hpvd_load_wp($root);
        if (!in_array($state['status'], array('preparing', 'prepare-failed'), true)) { hpvd_restore_database($backup . '/database.sql'); }
        wp_clean_themes_cache(true);
        if (is_file($root . '/.maintenance') && strpos(file_get_contents($root . '/.maintenance'), 'hpv-' . $revision) !== false) { unlink($root . '/.maintenance'); }
        $state['status'] = 'rolled-back'; hpvd_write_state($state_file, $state);
        echo "Previous code and database restored; failed release retained for inspection.\n";
    } else { throw new RuntimeException('Unknown release lifecycle action.'); }
} catch (Throwable $error) {
    if ($hpvd_action === 'prepare' && $maintenance_owned) {
        if (is_file($root . '/.maintenance') && strpos(file_get_contents($root . '/.maintenance'), 'hpv-' . $revision) !== false) { unlink($root . '/.maintenance'); }
        $state['status'] = 'prepare-failed'; hpvd_write_state($state_file, $state);
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
