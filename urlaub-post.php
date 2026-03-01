<?php
/**
 * Plugin Name: urlaub_post
 * Plugin URI: https://github.com/bubleti01/urlaub_post
 * Description: Verwalten Sie die Urlaubszeiten Ihres Veranstaltungsortes in WordPress und zeigen Sie diese in vielen verschiedenen Widgets und Shortcodes an.
 * Version: 1.0.0
 * Author: IGW Design
 * Author URI: https://igo2web.com
 * Text Domain: urlaub_post
 * Domain Path: /language
 */

if (! defined('ABSPATH')) {
    exit;
}

define('URLAUB_POST_VERSION', '1.0.0');
define('URLAUB_POST_FILE', __FILE__);
define('URLAUB_POST_PATH', plugin_dir_path(__FILE__));
define('URLAUB_POST_URL', plugin_dir_url(__FILE__));
define('URLAUB_POST_TEXTDOMAIN', 'urlaub_post');

require_once URLAUB_POST_PATH . 'includes/class-plugin.php';

\UrlaubPost\Plugin::init();

register_activation_hook(URLAUB_POST_FILE, ['\\UrlaubPost\\Plugin', 'activate']);
register_deactivation_hook(URLAUB_POST_FILE, ['\\UrlaubPost\\Plugin', 'deactivate']);
