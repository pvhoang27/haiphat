<?php
/** Saves enqueue one background job; only changed public design dispatches CI. */
defined('ABSPATH') || exit;

const HPV_DESIGN_SYNC_CRON_HOOK = 'hpv_design_sync_process';
const HPV_DESIGN_SYNC_DIRTY_OPTION = 'hpv_design_sync_dirty_generation';
const HPV_DESIGN_SYNC_ACK_OPTION = 'hpv_design_sync_ack_hash';
const HPV_DESIGN_SYNC_ATTEMPT_OPTION = 'hpv_design_sync_attempt';
const HPV_DESIGN_SYNC_ERROR_OPTION = 'hpv_design_sync_last_error';

function hpv_design_sync_suppressed() {
    return (defined('HPV_DESIGN_SYNC_SUPPRESS') && HPV_DESIGN_SYNC_SUPPRESS)
        || (defined('WP_INSTALLING') && WP_INSTALLING)
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE);
}
function hpv_design_sync_post_supported($post) {
    return $post instanceof WP_Post && $post->post_status === 'publish'
        && in_array($post->post_type, array('page', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_block'), true)
        && !wp_is_post_revision($post->ID) && !wp_is_post_autosave($post->ID);
}
function hpv_design_sync_generation() {
    global $wpdb;
    // Queue ownership must not depend on another PHP request's option cache.
    $value = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", HPV_DESIGN_SYNC_DIRTY_OPTION));
    return is_string($value) ? $value : '';
}
function hpv_design_sync_mark_dirty() {
    if (hpv_design_sync_suppressed()) { return; }
    hpv_design_sync_write_generation(time() . ':' . bin2hex(random_bytes(16)));
    hpv_design_sync_schedule(90);
}
function hpv_design_sync_write_generation($generation) {
    global $wpdb;
    // update_option() can lose a save when an ACK deletes the old row after a
    // different request has cached it. Upsert remains atomic across that race.
    $written = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'no'", HPV_DESIGN_SYNC_DIRTY_OPTION, $generation));
    wp_cache_delete(HPV_DESIGN_SYNC_DIRTY_OPTION, 'options');
    wp_cache_delete('notoptions', 'options');
    return $written !== false;
}
function hpv_design_sync_after_insert($post_id, $post, $update, $before) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
    // Previous published state also catches unpublish/private/trash transitions.
    if (hpv_design_sync_post_supported($post) || hpv_design_sync_post_supported($before)) { hpv_design_sync_mark_dirty(); }
}
function hpv_design_sync_before_delete($post_id, $post) {
    if (hpv_design_sync_post_supported($post)) { hpv_design_sync_mark_dirty(); }
}
function hpv_design_sync_meta_changed($meta_id, $post_id, $key, $value) {
    if ($key === '_wp_page_template' && hpv_design_sync_post_supported(get_post($post_id))) { hpv_design_sync_mark_dirty(); }
}
function hpv_design_sync_terms_changed($object_id, $terms, $tt_ids, $taxonomy) {
    if ($taxonomy === 'wp_theme' && hpv_design_sync_post_supported(get_post($object_id))) { hpv_design_sync_mark_dirty(); }
}
function hpv_design_sync_terms_deleted($object_id, $tt_ids, $taxonomy) {
    if ($taxonomy === 'wp_theme' && hpv_design_sync_post_supported(get_post($object_id))) { hpv_design_sync_mark_dirty(); }
}
function hpv_design_sync_option_changed($option) {
    if (in_array($option, array('show_on_front', 'page_on_front', 'page_for_posts', 'home'), true)) { hpv_design_sync_mark_dirty(); }
}
function hpv_design_sync_schedule($delay) {
    if (hpv_design_sync_generation() === '') { return; }
    $timestamp = time() + max(5, (int) $delay);
    $existing = wp_next_scheduled(HPV_DESIGN_SYNC_CRON_HOOK);
    if ($existing && $existing <= $timestamp + 5) { return; }
    if ($existing) { wp_unschedule_event($existing, HPV_DESIGN_SYNC_CRON_HOOK); }
    wp_schedule_single_event($timestamp, HPV_DESIGN_SYNC_CRON_HOOK);
}
function hpv_design_sync_recover_pending() {
    if (!hpv_design_sync_suppressed() && hpv_design_sync_generation() !== '' && !wp_next_scheduled(HPV_DESIGN_SYNC_CRON_HOOK)) { hpv_design_sync_schedule(90); }
}
function hpv_design_sync_private_root() {
    if (defined('HPV_DESIGN_SYNC_PRIVATE_ROOT')) { return rtrim(HPV_DESIGN_SYNC_PRIVATE_ROOT, '/'); }
    // Identical to release.php: account for WordPress below the document root.
    $root = rtrim(ABSPATH, '/');
    $path = rtrim(rawurldecode((string) parse_url(site_url(), PHP_URL_PATH)), '/');
    if ($path !== '' && substr($root, -strlen($path)) === $path) { $root = substr($root, 0, -strlen($path)); }
    if ($root === '' || $root === '/') { throw new RuntimeException('Cannot resolve private sync directory.'); }
    return dirname($root) . '/haiphat-deploy-backups';
}
function hpv_design_sync_config() {
    $config = array();
    $file = hpv_design_sync_private_root() . '/design-sync-config.php';
    if (is_readable($file)) { $config = require $file; }
    if (!is_array($config)) { $config = array(); }
    if (defined('HPV_DESIGN_SYNC_TOKEN')) { $config['token'] = HPV_DESIGN_SYNC_TOKEN; }
    if (defined('HPV_DESIGN_SYNC_REPOSITORY')) { $config['repository'] = HPV_DESIGN_SYNC_REPOSITORY; }
    $config = array_merge(array('token' => '', 'repository' => 'pvhoang27/haiphat'), $config);
    if (!is_string($config['token']) || !preg_match('/\A[A-Za-z0-9_]+\z/', $config['token'])) { $config['token'] = ''; }
    if (!is_string($config['repository']) || !preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~', $config['repository'])) { $config['repository'] = ''; }
    return $config;
}
function hpv_design_sync_snapshot_hash() {
    require_once __DIR__ . '/design-export.php';
    // Same bytes hashed by export-design.php, including formatting and newline.
    $json = hpv_design_json(hpv_export_design());
    if (strlen($json) > 20 * 1024 * 1024) { throw new RuntimeException('Design export exceeds 20 MiB.'); }
    return hash('sha256', $json);
}
function hpv_design_sync_clear_generation($generation) {
    global $wpdb;
    if (!is_string($generation) || $generation === '') { return false; }
    // Compare-and-delete: a concurrent customer save must survive an older ACK.
    $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", HPV_DESIGN_SYNC_DIRTY_OPTION, $generation));
    wp_cache_delete(HPV_DESIGN_SYNC_DIRTY_OPTION, 'options');
    wp_cache_delete('notoptions', 'options');
    return $deleted === 1;
}
function hpv_design_sync_acknowledge($hash, $generation) {
    if (!is_string($hash) || !preg_match('/\A[a-f0-9]{64}\z/', $hash)) { throw new InvalidArgumentException('Invalid snapshot hash.'); }
    update_option(HPV_DESIGN_SYNC_ACK_OPTION, $hash, false);
    hpv_design_sync_clear_generation($generation);
    delete_option(HPV_DESIGN_SYNC_ERROR_OPTION);
    // Leave any cron entry intact; another save can be scheduling it right now.
    if (hpv_design_sync_generation() !== '') { hpv_design_sync_schedule(90); }
}
function hpv_design_sync_acquire_locks() {
    $root = hpv_design_sync_private_root();
    if (!is_dir($root)) { return false; }
    $handles = array();
    foreach (array('deploy.lock' => LOCK_SH, 'design-sync.lock' => LOCK_EX) as $name => $mode) {
        $handle = @fopen($root . '/' . $name, 'c');
        if (!$handle || !flock($handle, $mode | LOCK_NB)) {
            if (is_resource($handle)) { fclose($handle); }
            hpv_design_sync_release_locks($handles);
            return false;
        }
        $handles[] = $handle;
    }
    return $handles;
}
function hpv_design_sync_release_locks($handles) {
    foreach (array_reverse($handles) as $handle) { flock($handle, LOCK_UN); fclose($handle); }
}
function hpv_design_sync_retry($code, $hash = '') {
    $previous = get_option(HPV_DESIGN_SYNC_ATTEMPT_OPTION, array());
    $failures = is_array($previous) && isset($previous['hash'], $previous['failures']) && $previous['hash'] === $hash ? (int) $previous['failures'] + 1 : 1;
    update_option(HPV_DESIGN_SYNC_ATTEMPT_OPTION, array('hash' => $hash, 'failures' => $failures, 'accepted_at' => 0), false);
    // No tokens, API response bodies or infrastructure exception messages in DB.
    update_option(HPV_DESIGN_SYNC_ERROR_OPTION, array('code' => $code, 'at' => time()), false);
    hpv_design_sync_schedule(min(21600, 60 * (2 ** min(9, $failures - 1))));
}
function hpv_design_sync_process() {
    $generation = hpv_design_sync_generation();
    if ($generation === '') { return; }
    $remaining = 90 - (time() - (int) strtok($generation, ':'));
    if ($remaining > 0) { hpv_design_sync_schedule($remaining); return; }
    $locks = hpv_design_sync_acquire_locks();
    if ($locks === false) { hpv_design_sync_schedule(90); return; }
    $hash = '';
    try {
        if (is_file(ABSPATH . '.maintenance')) { hpv_design_sync_schedule(90); return; }
        $generation = hpv_design_sync_generation();
        if ($generation === '') { return; }
        $remaining = 90 - (time() - (int) strtok($generation, ':'));
        if ($remaining > 0) { hpv_design_sync_schedule($remaining); return; }
        $hash = hpv_design_sync_snapshot_hash();
        if (hash_equals((string) get_option(HPV_DESIGN_SYNC_ACK_OPTION, ''), $hash)) {
            hpv_design_sync_clear_generation($generation);
            delete_option(HPV_DESIGN_SYNC_ATTEMPT_OPTION);
            delete_option(HPV_DESIGN_SYNC_ERROR_OPTION);
            return;
        }
        $attempt = get_option(HPV_DESIGN_SYNC_ATTEMPT_OPTION, array());
        $watchdog = is_array($attempt) && isset($attempt['watchdog']) ? (int) $attempt['watchdog'] : 900;
        if (is_array($attempt) && isset($attempt['hash'], $attempt['accepted_at']) && $attempt['hash'] === $hash && (int) $attempt['accepted_at'] > time() - $watchdog) {
            hpv_design_sync_schedule($watchdog - (time() - (int) $attempt['accepted_at']));
            return;
        }
        $config = hpv_design_sync_config();
        if ($config['token'] === '' || $config['repository'] === '') { hpv_design_sync_retry('configuration_missing', $hash); return; }
        $response = wp_remote_post('https://api.github.com/repos/' . $config['repository'] . '/actions/workflows/sync-design.yml/dispatches', array(
            'timeout' => 15, 'redirection' => 0, 'reject_unsafe_urls' => true,
            'headers' => array('Accept' => 'application/vnd.github+json', 'Authorization' => 'Bearer ' . $config['token'], 'X-GitHub-Api-Version' => '2022-11-28', 'Content-Type' => 'application/json', 'User-Agent' => 'HaiPhat-WordPress-Design-Sync'),
            'body' => wp_json_encode(array('ref' => 'main', 'inputs' => array('snapshot_hash' => $hash))),
        ));
        if (is_wp_error($response)) { hpv_design_sync_retry('dispatch_transport_failed', $hash); return; }
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) { hpv_design_sync_retry('dispatch_http_' . $status, $hash); return; }
        $dispatches = is_array($attempt) && isset($attempt['hash'], $attempt['dispatches']) && $attempt['hash'] === $hash ? (int) $attempt['dispatches'] + 1 : 1;
        $watchdog = min(21600, 900 * (2 ** min(5, $dispatches - 1)));
        update_option(HPV_DESIGN_SYNC_ATTEMPT_OPTION, array('hash' => $hash, 'failures' => 0, 'accepted_at' => time(), 'dispatches' => $dispatches, 'watchdog' => $watchdog), false);
        delete_option(HPV_DESIGN_SYNC_ERROR_OPTION);
        // An accepted API request can still produce a failed/cancelled workflow.
        // Only an SSH ACK after the PR is written removes the dirty generation.
        hpv_design_sync_schedule($watchdog);
    } catch (Throwable $error) {
        hpv_design_sync_retry('snapshot_export_failed', $hash);
    } finally {
        hpv_design_sync_release_locks($locks);
        if (hpv_design_sync_generation() !== '' && !wp_next_scheduled(HPV_DESIGN_SYNC_CRON_HOOK)) { hpv_design_sync_schedule(90); }
    }
}

add_action('wp_after_insert_post', 'hpv_design_sync_after_insert', 10, 4);
add_action('before_delete_post', 'hpv_design_sync_before_delete', 10, 2);
foreach (array('added_post_meta', 'updated_post_meta', 'deleted_post_meta') as $hpv_sync_hook) { add_action($hpv_sync_hook, 'hpv_design_sync_meta_changed', 10, 4); }
add_action('set_object_terms', 'hpv_design_sync_terms_changed', 10, 4);
add_action('deleted_term_relationships', 'hpv_design_sync_terms_deleted', 10, 3);
foreach (array('added_option', 'updated_option', 'deleted_option') as $hpv_sync_hook) { add_action($hpv_sync_hook, 'hpv_design_sync_option_changed', 10, 1); }
unset($hpv_sync_hook);
add_action('switch_theme', 'hpv_design_sync_mark_dirty', 10, 0);
add_action('init', 'hpv_design_sync_recover_pending', 30);
add_action(HPV_DESIGN_SYNC_CRON_HOOK, 'hpv_design_sync_process');
