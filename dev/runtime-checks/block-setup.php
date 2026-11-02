<?php
/** Disposable local environment only; never ship in either installable ZIP. */
$_SERVER['HTTP_HOST'] = '127.0.0.1:8787';
$_SERVER['REQUEST_METHOD'] = 'GET';
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$result = activate_plugin('hai-phat-vina-core/hai-phat-vina-core.php');
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
// Activation happens after theme loading in this CLI request; use a fresh request below.
echo "Companion plugin activated. Run block-install.php in a fresh PHP process.\n";
