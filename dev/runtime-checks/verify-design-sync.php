<?php
/** Disposable real-WordPress tests. Every external HTTP request is intercepted. */
define('DISABLE_WP_CRON', true);
define('HPV_DESIGN_SYNC_TOKEN', str_repeat('local_fake_', 5));
define('HPV_DESIGN_SYNC_REPOSITORY', 'example/qa');
define('HPV_DESIGN_SYNC_PRIVATE_ROOT', sys_get_temp_dir() . '/hpv-sync-qa-' . bin2hex(random_bytes(6)));
$_SERVER['HTTP_HOST'] = '127.0.0.1:8787';
$_SERVER['REQUEST_METHOD'] = 'GET';
require '/var/www/html/wp-load.php';
if (get_option('home') !== 'http://127.0.0.1:8787') { throw new RuntimeException('Disposable local QA only.'); }
mkdir(HPV_DESIGN_SYNC_PRIVATE_ROOT, 0700);
function hpv_sync_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
function hpv_sync_event_count() {
    $count = 0;
    foreach (_get_cron_array() as $events) { if (isset($events[HPV_DESIGN_SYNC_CRON_HOOK])) { $count += count($events[HPV_DESIGN_SYNC_CRON_HOOK]); } }
    return $count;
}
function hpv_sync_reset() {
    foreach (array(HPV_DESIGN_SYNC_DIRTY_OPTION, HPV_DESIGN_SYNC_ATTEMPT_OPTION, HPV_DESIGN_SYNC_ERROR_OPTION) as $name) { delete_option($name); }
    wp_clear_scheduled_hook(HPV_DESIGN_SYNC_CRON_HOOK);
    update_option(HPV_DESIGN_SYNC_ACK_OPTION, hpv_design_sync_snapshot_hash(), false);
}
function hpv_sync_run_due() {
    $generation = hpv_design_sync_generation();
    if ($generation !== '') { update_option(HPV_DESIGN_SYNC_DIRTY_OPTION, (time() - 120) . ':' . substr($generation, 11), false); }
    // Match WP-Cron: the due event is consumed before invoking its callback.
    wp_clear_scheduled_hook(HPV_DESIGN_SYNC_CRON_HOOK);
    hpv_design_sync_process();
}
$option_names = array('cron', 'page_on_front', 'page_for_posts', 'show_on_front', HPV_DESIGN_SYNC_DIRTY_OPTION, HPV_DESIGN_SYNC_ACK_OPTION, HPV_DESIGN_SYNC_ATTEMPT_OPTION, HPV_DESIGN_SYNC_ERROR_OPTION);
$saved = array();
foreach ($option_names as $name) { $saved[$name] = get_option($name, null); }
$created = array(); $requests = array(); $failure = false; $during_request = null; $status = 204;
$http = function ($pre, $args, $url) use (&$requests, &$failure, &$during_request, &$status) {
    if ($url !== 'https://api.github.com/repos/example/qa/actions/workflows/sync-design.yml/dispatches') { return new WP_Error('qa_blocked', 'External network blocked.'); }
    $requests[] = $args;
    if ($during_request) { $callback = $during_request; $during_request = null; $callback(); }
    if ($failure) { return new WP_Error('qa_outage', 'Simulated outage.'); }
    return array('headers' => array(), 'body' => '', 'response' => array('code' => $status, 'message' => 'QA'), 'cookies' => array());
};
add_filter('pre_http_request', $http, PHP_INT_MAX, 3);
try {
    $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
    wp_set_current_user($admins[0]);
    $page = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Sync QA', 'post_content' => '<p>Initial</p>'), true);
    if (is_wp_error($page)) { throw new RuntimeException($page->get_error_message()); }
    $created[] = $page;
    hpv_sync_reset();
    $baseline = hpv_design_sync_snapshot_hash();
    hpv_sync_assert($baseline === hpv_design_sync_snapshot_hash(), 'Unchanged authoritative exports have identical hashes');
    hpv_design_sync_process();
    hpv_sync_assert(!$requests && hpv_sync_event_count() === 0, 'Idle website performs no dispatch and has no sync cron');

    wp_update_post(array('ID' => $page, 'post_content' => '<p>Customer saved this</p>'));
    hpv_sync_assert(get_post_field('post_content', $page) === '<p>Customer saved this</p>' && !$requests, 'Customer save persists immediately without waiting for GitHub');
    wp_update_post(array('ID' => $page, 'post_title' => 'Second save'));
    wp_update_post(array('ID' => $page, 'post_title' => 'Third save'));
    hpv_sync_assert(hpv_sync_event_count() === 1, 'Successive saves coalesce into one background event');
    hpv_design_sync_process();
    hpv_sync_assert(!$requests, 'Debounce avoids dispatching during a series of saves');
    hpv_sync_run_due();
    $hash = hpv_design_sync_snapshot_hash(); $generation = hpv_design_sync_generation();
    hpv_sync_assert(count($requests) === 1 && hpv_sync_event_count() === 1 && $generation !== '', 'Accepted dispatch retains pending state until workflow ACK');
    $body = json_decode($requests[0]['body'], true);
    hpv_sync_assert($body['ref'] === 'main' && $body['inputs']['snapshot_hash'] === $hash, 'Dispatch targets main and sends only the snapshot hash');
    hpv_sync_run_due();
    hpv_sync_assert(count($requests) === 1, 'Watchdog does not duplicate a recently accepted dispatch');
    $generation = hpv_design_sync_generation();
    hpv_design_sync_acknowledge($hash, $generation);
    hpv_sync_assert(hpv_design_sync_generation() === '', 'Successful snapshot ACK clears its pending generation');

    hpv_sync_reset(); $requests = array();
    wp_update_post(array('ID' => $page, 'post_content' => get_post_field('post_content', $page)));
    hpv_sync_run_due();
    hpv_sync_assert(!$requests && hpv_design_sync_generation() === '', 'Saving unchanged content does not call GitHub');
    $revision = wp_insert_post(array('post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $page, 'post_content' => 'Revision only'));
    $created[] = $revision;
    require_once ABSPATH . 'wp-admin/includes/post.php';
    $autosave = wp_create_post_autosave(array('post_ID' => $page, 'post_type' => 'page', 'post_title' => 'Autosave', 'post_content' => 'Not published', 'post_excerpt' => ''));
    hpv_sync_assert(!is_wp_error($autosave) && wp_is_post_autosave($autosave), 'Fixture creates a real WordPress autosave');
    $created[] = $autosave;
    foreach (array('post', 'hpv_product') as $type) { $created[] = wp_insert_post(array('post_type' => $type, 'post_status' => 'publish', 'post_content' => 'Excluded')); }
    $created[] = wp_insert_post(array('post_type' => 'page', 'post_status' => 'draft', 'post_content' => 'Draft'));
    hpv_sync_assert(hpv_design_sync_generation() === '' && hpv_sync_event_count() === 0, 'Autosaves, revisions, drafts and untracked content do not queue sync');

    wp_update_post(array('ID' => $page, 'post_status' => 'draft'));
    hpv_sync_assert(hpv_design_sync_generation() !== '' && hpv_design_sync_snapshot_hash() !== $hash, 'Unpublishing an exported page queues its removal');
    hpv_sync_reset();
    wp_update_post(array('ID' => $page, 'post_status' => 'publish'));
    hpv_sync_assert(hpv_sync_event_count() === 1, 'Publishing queues the newly public design');
    hpv_sync_reset();
    update_post_meta($page, '_wp_page_template', 'page-full-width');
    hpv_sync_assert(hpv_sync_event_count() === 1, 'Page template metadata changes queue sync independently');
    hpv_sync_reset();
    update_option('page_on_front', $page);
    hpv_sync_assert(hpv_sync_event_count() === 1, 'Front page selection queues sync without saving a page');
    hpv_sync_reset();
    $part = wp_insert_post(array('post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => 'sync-qa', 'post_content' => '<p>Part</p>'));
    $created[] = $part;
    wp_set_object_terms($part, 'hai-phat-vina', 'wp_theme');
    hpv_sync_assert(isset(hpv_export_design()['files']['parts/sync-qa.html']) && hpv_sync_event_count() === 1, 'Saved template parts and their theme association queue sync');
    hpv_sync_reset();
    wp_delete_post($part, true);
    hpv_sync_assert(hpv_sync_event_count() === 1, 'Deleting a published template part queues removal');

    hpv_sync_reset(); $requests = array(); $failure = true;
    wp_update_post(array('ID' => $page, 'post_content' => '<p>Pending during outage</p>'));
    $ack = get_option(HPV_DESIGN_SYNC_ACK_OPTION);
    hpv_sync_run_due();
    hpv_sync_assert(count($requests) === 1 && hpv_design_sync_generation() !== '' && hpv_sync_event_count() === 1 && get_option(HPV_DESIGN_SYNC_ACK_OPTION) === $ack, 'GitHub outage retains the edit and schedules a retry');
    hpv_sync_assert(strpos(wp_json_encode(get_option(HPV_DESIGN_SYNC_ERROR_OPTION)), HPV_DESIGN_SYNC_TOKEN) === false, 'Persisted diagnostics never contain the dispatch credential');
    $failure = false; $status = 200;
    hpv_sync_run_due();
    hpv_sync_assert(count($requests) === 2 && hpv_design_sync_generation() !== '', 'Retry accepts HTTP 200 but still waits for workflow completion');
    $attempt = get_option(HPV_DESIGN_SYNC_ATTEMPT_OPTION); $attempt['accepted_at'] = time() - 1000;
    update_option(HPV_DESIGN_SYNC_ATTEMPT_OPTION, $attempt, false);
    hpv_sync_run_due();
    hpv_sync_assert(count($requests) === 3, 'A failed or cancelled workflow is retried when no ACK arrives');

    hpv_sync_reset(); $requests = array();
    wp_update_post(array('ID' => $page, 'post_content' => '<p>First in-flight edit</p>'));
    $old_hash = hpv_design_sync_snapshot_hash(); $old_generation = '';
    $during_request = function () use ($page, &$old_generation) {
        $old_generation = hpv_design_sync_generation();
        wp_update_post(array('ID' => $page, 'post_content' => '<p>Newer edit during dispatch</p>'));
    };
    hpv_sync_run_due();
    $new_generation = hpv_design_sync_generation();
    hpv_design_sync_acknowledge($old_hash, $old_generation);
    hpv_sync_assert($new_generation !== $old_generation && hpv_design_sync_generation() === $new_generation && hpv_sync_event_count() === 1, 'Old workflow ACK cannot clear a newer customer edit');
    hpv_sync_run_due();
    hpv_sync_assert(count($requests) === 2, 'Newer snapshot dispatches even while the older ACK watchdog exists');

    // An ACK in another PHP request deletes the row while this request can
    // still have the old option in its own object cache.
    hpv_sync_reset();
    hpv_design_sync_mark_dirty();
    $cached_generation = hpv_design_sync_generation();
    global $wpdb;
    $wpdb->delete($wpdb->options, array('option_name' => HPV_DESIGN_SYNC_DIRTY_OPTION));
    wp_cache_set(HPV_DESIGN_SYNC_DIRTY_OPTION, $cached_generation, 'options');
    hpv_design_sync_mark_dirty();
    wp_cache_flush_runtime();
    hpv_sync_assert(hpv_design_sync_generation() !== '' && hpv_design_sync_generation() !== $cached_generation, 'Save with a stale option cache survives a concurrent ACK deleting the previous generation');

    hpv_sync_reset(); $requests = array();
    $deploy_lock = fopen(HPV_DESIGN_SYNC_PRIVATE_ROOT . '/deploy.lock', 'c');
    flock($deploy_lock, LOCK_EX);
    try {
        wp_update_post(array('ID' => $page, 'post_content' => '<p>Saved while deployment holds its lock</p>'));
        hpv_sync_run_due();
        hpv_sync_assert(!$requests && hpv_design_sync_generation() !== '' && hpv_sync_event_count() === 1, 'Active deployment postpones export without losing the saved customer edit');
    } finally { flock($deploy_lock, LOCK_UN); fclose($deploy_lock); }

    hpv_sync_reset(); $requests = array(); $status = 403;
    wp_update_post(array('ID' => $page, 'post_content' => '<p>Waiting for corrected token permissions</p>'));
    hpv_sync_run_due();
    $error = get_option(HPV_DESIGN_SYNC_ERROR_OPTION);
    hpv_sync_assert(count($requests) === 1 && $error['code'] === 'dispatch_http_403' && hpv_design_sync_generation() !== '', 'GitHub permission failure remains retryable and records only a diagnostic code');

    hpv_sync_reset(); $requests = array(); $status = 204;
    wp_update_post(array('ID' => $page, 'post_content' => '<!-- wp:block {"ref":999999999} /-->'));
    hpv_sync_run_due();
    $error = get_option(HPV_DESIGN_SYNC_ERROR_OPTION);
    hpv_sync_assert(!$requests && $error['code'] === 'snapshot_export_failed' && hpv_sync_event_count() === 1, 'Invalid shared content stops dispatch without dropping the pending edit');
} finally {
    foreach (array_reverse($created) as $id) { if (is_numeric($id) && get_post($id)) { wp_delete_post($id, true); } }
    foreach ($saved as $name => $value) { if ($name !== 'cron') { if ($value === null) { delete_option($name); } else { update_option($name, $value); } } }
    update_option('cron', $saved['cron']);
    remove_filter('pre_http_request', $http, PHP_INT_MAX);
    foreach (glob(HPV_DESIGN_SYNC_PRIVATE_ROOT . '/*') as $file) { unlink($file); }
    rmdir(HPV_DESIGN_SYNC_PRIVATE_ROOT);
}
