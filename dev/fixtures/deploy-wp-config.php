<?php
// Disposable integration site only. Source folders deployed to production never contain this file.
define('DB_NAME', getenv('WORDPRESS_DB_NAME'));
define('DB_USER', getenv('WORDPRESS_DB_USER'));
define('DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD'));
define('DB_HOST', getenv('WORDPRESS_DB_HOST'));
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
define('AUTH_KEY', 'disposable-deployment-fixture');
define('SECURE_AUTH_KEY', 'disposable-deployment-fixture-secure');
define('LOGGED_IN_KEY', 'disposable-deployment-fixture-login');
define('NONCE_KEY', 'disposable-deployment-fixture-nonce');
if (!defined('DISABLE_WP_CRON')) { define('DISABLE_WP_CRON', true); }
$table_prefix = 'hpv_deploy_qa_';
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
require_once ABSPATH . 'wp-settings.php';
