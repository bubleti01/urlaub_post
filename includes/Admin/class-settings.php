<?php

namespace UrlaubPost\Admin;

if (! defined('ABSPATH')) {
    exit;
}

require_once URLAUB_POST_PATH . 'includes/Integrations/OpeningHours/class-exporter.php';

use UrlaubPost\Integrations\OpeningHours\Exporter;

class Settings
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_post_urlaub_post_export_opening_hours', [__CLASS__, 'handle_export']);
        add_action('admin_notices', [__CLASS__, 'render_notices']);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=urlaub_post',
            __('Urlaub Einstellungen', URLAUB_POST_TEXTDOMAIN),
            __('Einstellungen', URLAUB_POST_TEXTDOMAIN),
            'manage_options',
            'urlaub-post-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting('urlaub_post_settings', 'urlaub_post_pre_days', [
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => static function ($value): int {
                return max(0, (int) $value);
            },
        ]);

        register_setting('urlaub_post_settings', 'urlaub_post_oh_export_enabled', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => static function ($value): bool {
                return (bool) $value;
            },
        ]);

        register_setting('urlaub_post_settings', 'urlaub_post_oh_set_id', [
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => static function ($value): int {
                return (int) $value;
            },
        ]);

        register_setting('urlaub_post_settings', 'urlaub_post_oh_name_source', [
            'type' => 'string',
            'default' => 'generated',
            'sanitize_callback' => static function ($value): string {
                $value = sanitize_text_field((string) $value);
                return in_array($value, ['generated', 'urlaub_titel'], true) ? $value : 'generated';
            },
        ]);
    }

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $pre_days = (int) get_option('urlaub_post_pre_days', 0);
        $oh_enabled = Exporter::is_opening_hours_active() ? (bool) get_option('urlaub_post_oh_export_enabled', false) : false;
        $set_id = (int) get_option('urlaub_post_oh_set_id', 0);
        $name_source = (string) get_option('urlaub_post_oh_name_source', 'generated');
        $sets = Exporter::get_opening_hour_sets();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Urlaub Einstellungen', URLAUB_POST_TEXTDOMAIN); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('urlaub_post_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="urlaub_post_pre_days"><?php esc_html_e('Veröffentlichen bevor von_datum (nummer)', URLAUB_POST_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="number" min="0" id="urlaub_post_pre_days" name="urlaub_post_pre_days" value="<?php echo esc_attr((string) $pre_days); ?>">
                        </td>
                    </tr>
                    <?php if (Exporter::is_opening_hours_active()) : ?>
                        <tr>
                            <th scope="row">
                                <label for="urlaub_post_oh_export_enabled"><?php esc_html_e('Export to Opening Hours', URLAUB_POST_TEXTDOMAIN); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="urlaub_post_oh_export_enabled" name="urlaub_post_oh_export_enabled" value="1" <?php checked($oh_enabled); ?>>
                                    <?php esc_html_e('Export aktivieren', URLAUB_POST_TEXTDOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="urlaub_post_oh_set_id"><?php esc_html_e('Set auswählen', URLAUB_POST_TEXTDOMAIN); ?></label></th>
                            <td>
                                <select id="urlaub_post_oh_set_id" name="urlaub_post_oh_set_id">
                                    <option value="0"><?php esc_html_e('Bitte wählen', URLAUB_POST_TEXTDOMAIN); ?></option>
                                    <?php foreach ($sets as $id => $label) : ?>
                                        <option value="<?php echo esc_attr((string) $id); ?>" <?php selected((int) $id, $set_id); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="urlaub_post_oh_name_source"><?php esc_html_e('Holiday-Name Quelle', URLAUB_POST_TEXTDOMAIN); ?></label></th>
                            <td>
                                <select id="urlaub_post_oh_name_source" name="urlaub_post_oh_name_source">
                                    <option value="generated" <?php selected($name_source, 'generated'); ?>><?php esc_html_e('Generated (post_title)', URLAUB_POST_TEXTDOMAIN); ?></option>
                                    <option value="urlaub_titel" <?php selected($name_source, 'urlaub_titel'); ?>><?php esc_html_e('urlaub_titel', URLAUB_POST_TEXTDOMAIN); ?></option>
                                </select>
                            </td>
                        </tr>
                    <?php else : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('Opening Hours', URLAUB_POST_TEXTDOMAIN); ?></th>
                            <td><p><?php esc_html_e('WP-Opening-Hours ist nicht aktiv.', URLAUB_POST_TEXTDOMAIN); ?></p></td>
                        </tr>
                    <?php endif; ?>
                </table>
                <?php submit_button(); ?>
            </form>

            <?php if (Exporter::is_opening_hours_active()) : ?>
                <hr>
                <h2><?php esc_html_e('Export', URLAUB_POST_TEXTDOMAIN); ?></h2>
                <p><strong><?php esc_html_e('Warnung:', URLAUB_POST_TEXTDOMAIN); ?></strong> <?php esc_html_e('Dieser Export überschreibt alle Holidays im gewählten Opening-Hours Set.', URLAUB_POST_TEXTDOMAIN); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('urlaub_post_export_oh', 'urlaub_post_export_nonce'); ?>
                    <input type="hidden" name="action" value="urlaub_post_export_opening_hours">
                    <?php submit_button(__('Export zu Opening Hours', URLAUB_POST_TEXTDOMAIN), 'primary', 'submit', false); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_export(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Nicht erlaubt.', URLAUB_POST_TEXTDOMAIN));
        }

        if (! isset($_POST['urlaub_post_export_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['urlaub_post_export_nonce'])), 'urlaub_post_export_oh')) {
            wp_die(esc_html__('Ungültiger Aufruf.', URLAUB_POST_TEXTDOMAIN));
        }

        $result = Exporter::export_active_vacations();

        if ($result['success']) {
            self::add_notice('success', $result['message']);
        } else {
            self::add_notice('error', $result['message']);
        }

        wp_safe_redirect(admin_url('edit.php?post_type=urlaub_post&page=urlaub-post-settings'));
        exit;
    }

    private static function add_notice(string $type, string $message): void
    {
        set_transient('urlaub_post_settings_notice', [
            'type' => $type,
            'message' => $message,
        ], MINUTE_IN_SECONDS);
    }

    public static function render_notices(): void
    {
        if (! isset($_GET['page']) || $_GET['page'] !== 'urlaub-post-settings') {
            return;
        }

        $notice = get_transient('urlaub_post_settings_notice');
        if (! is_array($notice) || empty($notice['message'])) {
            return;
        }

        delete_transient('urlaub_post_settings_notice');

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($notice['type'] === 'success' ? 'success' : 'error'),
            esc_html((string) $notice['message'])
        );
    }
}
