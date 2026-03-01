<?php

namespace UrlaubPost;

if (! defined('ABSPATH')) {
    exit;
}

require_once URLAUB_POST_PATH . 'includes/Admin/class-cpt.php';
require_once URLAUB_POST_PATH . 'includes/Admin/class-settings.php';
require_once URLAUB_POST_PATH . 'includes/Frontend/class-renderer.php';
require_once URLAUB_POST_PATH . 'includes/Integrations/OpeningHours/class-exporter.php';

use UrlaubPost\Admin\CPT;
use UrlaubPost\Admin\Settings;
use UrlaubPost\Frontend\Renderer;

class Plugin
{
    public static function init(): void
    {
        add_action('plugins_loaded', [__CLASS__, 'load_textdomain']);
        add_action('init', [__CLASS__, 'register_block']);

        CPT::init();
        Settings::init();
        Renderer::init();
    }

    public static function load_textdomain(): void
    {
        load_plugin_textdomain(
            URLAUB_POST_TEXTDOMAIN,
            false,
            dirname(plugin_basename(URLAUB_POST_FILE)) . '/languages'
        );
    }

    public static function register_block(): void
    {
        $block_path = URLAUB_POST_PATH . 'blocks/vacation-notice';

        if (file_exists($block_path . '/block.json')) {
            register_block_type($block_path, [
                'render_callback' => [Renderer::class, 'render_block'],
            ]);
        }
    }

    public static function activate(): void
    {
        CPT::register();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
