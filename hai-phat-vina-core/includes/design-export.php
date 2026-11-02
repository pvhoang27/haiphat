<?php
/** Read-only source export. Never renders blocks or queries business records. */
defined('ABSPATH') || exit;

function hpv_design_json($value) {
    $json = wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { throw new RuntimeException('Cannot encode design source.'); }
    return $json . "\n";
}

function hpv_design_public_post($post, $type) {
    return $post && $post->post_type === $type && $post->post_status === 'publish' && $post->post_password === '';
}

/** Collect only saved navigation/synced patterns referenced by exported blocks. */
function hpv_design_dependencies($content, &$files, &$seen, $depth = 0) {
    if ($depth > 40) { throw new RuntimeException('Design reference nesting exceeds the export limit.'); }
    foreach (parse_blocks($content) as $block) {
        $type = $block['blockName'] === 'core/navigation' ? 'wp_navigation' : ($block['blockName'] === 'core/block' ? 'wp_block' : '');
        if ($type && !empty($block['attrs']['ref'])) {
            $id = absint($block['attrs']['ref']);
            $key = $type . ':' . $id;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $post = get_post($id);
                if (!hpv_design_public_post($post, $type)) {
                    throw new RuntimeException('A design references unpublished or missing shared content; publish or remove the reference before syncing.');
                }
                $folder = $type === 'wp_navigation' ? 'navigation' : 'patterns';
                $source = hpv_design_source($post->post_content);
                $files[$folder . '/' . $id . '.html'] = $source;
                hpv_design_dependencies($source, $files, $seen, $depth + 1);
            }
        }
        if (!empty($block['innerBlocks'])) {
            hpv_design_dependencies(serialize_blocks($block['innerBlocks']), $files, $seen, $depth + 1);
        }
    }
}

/** Resolve theme patterns to editable block source without rendering queries. */
function hpv_design_source($content) {
    return serialize_blocks(resolve_pattern_blocks(parse_blocks($content)));
}

/** Public pages + active-theme design; no posts, media files, meta or options dump. */
function hpv_export_design() {
    if (get_stylesheet() !== 'hai-phat-vina' || !wp_is_block_theme()) {
        throw new RuntimeException('Design export requires the Hai Phat Vina block theme.');
    }
    $files = array(); $seen = array();
    $index = array('schema' => 1, 'theme' => 'hai-phat-vina', 'site' => home_url('/'), 'pages' => array());
    $pages = get_posts(array('post_type' => 'page', 'post_status' => 'publish', 'has_password' => false, 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true));
    foreach ($pages as $page) {
        if (!hpv_design_public_post($page, 'page')) { continue; }
        // IDs are stable on the source site; slugs may contain Unicode or change.
        $file = 'pages/' . $page->ID . '.html';
        $files[$file] = hpv_design_source($page->post_content);
        $index['pages'][] = array('file' => $file, 'path' => get_page_uri($page), 'title' => $page->post_title, 'template' => get_page_template_slug($page) ?: 'default', 'front_page' => (int) get_option('page_on_front') === $page->ID, 'posts_page' => (int) get_option('page_for_posts') === $page->ID);
        hpv_design_dependencies($files[$file], $files, $seen);
    }
    foreach (array('wp_template' => 'templates', 'wp_template_part' => 'parts') as $type => $folder) {
        $templates = get_block_templates(array(), $type);
        foreach ($templates as $template) {
            if ($template->theme !== get_stylesheet() || $template->status !== 'publish') { continue; }
            if (!empty($template->wp_id) && !hpv_design_public_post(get_post($template->wp_id), $type)) { continue; }
            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $template->slug)) { throw new RuntimeException('Unsupported template slug; export stopped without publishing partial files.'); }
            $file = $folder . '/' . $template->slug . '.html';
            $files[$file] = hpv_design_source($template->content);
            hpv_design_dependencies($files[$file], $files, $seen);
        }
    }
    // false explicitly avoids creating a styles record when none exists.
    $global = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles(wp_get_theme(), false);
    $styles = array('version' => 3);
    if ($global && hpv_design_public_post(get_post($global['ID']), 'wp_global_styles')) {
        $decoded = json_decode($global['post_content'], true);
        if (!is_array($decoded)) { throw new RuntimeException('Invalid saved global styles JSON.'); }
        foreach (array('styles', 'settings') as $key) { if (isset($decoded[$key])) { $styles[$key] = $decoded[$key]; } }
    }
    $files['global-styles.json'] = hpv_design_json($styles);
    $files['manifest.json'] = hpv_design_json($index);
    ksort($files, SORT_STRING);
    // No timestamps: an unchanged site produces byte-identical source exports.
    return array('schema' => 1, 'files' => $files);
}
