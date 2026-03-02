<?php
/**
 * Plugin Name: WP Urlaub Post
 * Plugin URI: https://igo2web.com/de/wordpress-plugins-von-igw-design/wp-urlaub-post
 * Description: Erstellen/Verwalten Sie Urlaubszeiten Posts in WordPress und zeigen Sie diese in vielen verschiedenen Widgets und Shortcodes an.
 * Version: 1.0.3
 * Requires at least: 6.0
 * Author: IGW Design
 * Author URI: https://igo2web.com
 * Text Domain: igw_wp_urlaub_post
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}


if (! function_exists('igw_urlaub_post_format_date_de')) {
    function igw_urlaub_post_format_date_de(string $ymd): string
    {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return '';
        }

        $dt = date_create_immutable($ymd);
        if ($dt === false) {
            return '';
        }

        $timestamp = $dt->getTimestamp();
        return wp_date('d.m.Y', $timestamp);
    }
}

final class IGW_WP_Urlaub_Post_Plugin
{
    private const POST_TYPE = 'urlaub_post';
    private const META_VON = 'von_datum';
    private const META_BIS = 'bis_datum';
    private const META_ACTIVE = 'active';

    private const OPT_PRE_DAYS = 'igw_wp_urlaub_post_pre_days';
    private const OPT_MIGRATION_DONE = 'igw_wp_urlaub_post_migration_done';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'load_textdomain']);
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'register_meta']);
        add_action('init', [__CLASS__, 'register_shortcodes']);
        add_action('init', [__CLASS__, 'register_block']);

        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_meta_boxes']);

        add_filter('wp_insert_post_data', [__CLASS__, 'inject_slug_from_dates'], 20, 2);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'render_column'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [__CLASS__, 'sortable_columns']);
        add_action('pre_get_posts', [__CLASS__, 'handle_columns_sort']);

        add_action('admin_menu', [__CLASS__, 'register_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_init', [__CLASS__, 'maybe_migrate_legacy_data']);
        add_action('admin_post_igw_wp_urlaub_post_export_ferien', [__CLASS__, 'handle_export_ferien']);

        add_filter('template_include', [__CLASS__, 'single_template_override']);
    }

    public static function activate(): void
    {
        self::register_post_type();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public static function load_textdomain(): void
    {
        load_plugin_textdomain('igw_wp_urlaub_post', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public static function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Urlaube', 'igw_wp_urlaub_post'),
                'singular_name' => __('Urlaub', 'igw_wp_urlaub_post'),
                'menu_name' => __('Urlaub', 'igw_wp_urlaub_post'),
                'add_new' => __('Neu hinzufügen', 'igw_wp_urlaub_post'),
                'add_new_item' => __('Urlaub hinzufügen', 'igw_wp_urlaub_post'),
                'edit_item' => __('Urlaub bearbeiten', 'igw_wp_urlaub_post'),
                'new_item' => __('Neuer Urlaub', 'igw_wp_urlaub_post'),
                'view_item' => __('Urlaub ansehen', 'igw_wp_urlaub_post'),
                'all_items' => __('Alle Urlaube', 'igw_wp_urlaub_post'),
                'search_items' => __('Urlaube durchsuchen', 'igw_wp_urlaub_post'),
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'urlaub'],
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author'],
            'taxonomies' => ['category', 'post_tag'],
            'menu_icon' => 'dashicons-calendar-alt',
        ]);
    }

    public static function register_meta(): void
    {
        register_post_meta(self::POST_TYPE, self::META_VON, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta(self::POST_TYPE, self::META_BIS, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta(self::POST_TYPE, self::META_ACTIVE, [
            'type' => 'integer',
            'single' => true,
            'default' => 1,
            'show_in_rest' => true,
            'auth_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box(
            'igw_wp_urlaub_post_dates',
            __('Urlaubszeitraum', 'igw_wp_urlaub_post'),
            [__CLASS__, 'render_dates_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    public static function render_dates_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('igw_wp_urlaub_post_dates_meta', 'igw_wp_urlaub_post_dates_meta_nonce');

        $von = (string) get_post_meta($post->ID, self::META_VON, true);
        $bis = (string) get_post_meta($post->ID, self::META_BIS, true);
        $active = (int) get_post_meta($post->ID, self::META_ACTIVE, true);

        echo '<p><label for="igw_wp_urlaub_post_von"><strong>' . esc_html__('Von', 'igw_wp_urlaub_post') . '</strong></label><br />';
        echo '<input type="date" id="igw_wp_urlaub_post_von" name="igw_wp_urlaub_post_von" value="' . esc_attr($von) . '" style="width:100%;" /></p>';

        echo '<p><label for="igw_wp_urlaub_post_bis"><strong>' . esc_html__('Bis', 'igw_wp_urlaub_post') . '</strong></label><br />';
        echo '<input type="date" id="igw_wp_urlaub_post_bis" name="igw_wp_urlaub_post_bis" value="' . esc_attr($bis) . '" style="width:100%;" /></p>';

        echo '<p><label><input type="checkbox" name="igw_wp_urlaub_post_active" value="1" ' . checked($active !== 0 ? 1 : 0, 1, false) . ' /> ' . esc_html__('Aktiv', 'igw_wp_urlaub_post') . '</label></p>';
    }

    public static function save_meta_boxes(int $post_id): void
    {
        if (! isset($_POST['igw_wp_urlaub_post_dates_meta_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['igw_wp_urlaub_post_dates_meta_nonce'])), 'igw_wp_urlaub_post_dates_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $von = isset($_POST['igw_wp_urlaub_post_von']) ? sanitize_text_field(wp_unslash($_POST['igw_wp_urlaub_post_von'])) : '';
        $bis = isset($_POST['igw_wp_urlaub_post_bis']) ? sanitize_text_field(wp_unslash($_POST['igw_wp_urlaub_post_bis'])) : '';
        $active = isset($_POST['igw_wp_urlaub_post_active']) ? 1 : 0;

        update_post_meta($post_id, self::META_VON, self::normalize_date($von));
        update_post_meta($post_id, self::META_BIS, self::normalize_date($bis));
        update_post_meta($post_id, self::META_ACTIVE, $active);
    }

    public static function inject_slug_from_dates(array $data, array $postarr): array
    {
        if (($data['post_type'] ?? '') !== self::POST_TYPE) {
            return $data;
        }

        $title = (string) ($data['post_title'] ?? '');
        $status = (string) ($data['post_status'] ?? '');

        if ($title === '' || in_array($status, ['auto-draft', 'inherit'], true)) {
            return $data;
        }

        $von = isset($_POST['igw_wp_urlaub_post_von']) ? self::normalize_date(sanitize_text_field(wp_unslash($_POST['igw_wp_urlaub_post_von']))) : '';
        $bis = isset($_POST['igw_wp_urlaub_post_bis']) ? self::normalize_date(sanitize_text_field(wp_unslash($_POST['igw_wp_urlaub_post_bis']))) : '';

        $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        if ($von === '' && $post_id > 0) {
            $von = self::normalize_date((string) get_post_meta($post_id, self::META_VON, true));
        }
        if ($bis === '' && $post_id > 0) {
            $bis = self::normalize_date((string) get_post_meta($post_id, self::META_BIS, true));
        }

        if ($von === '' || $bis === '') {
            return $data;
        }

        $base_slug = sanitize_title($title . '-vom-' . $von . '-bis-' . $bis);
        $data['post_name'] = wp_unique_post_slug($base_slug, $post_id, $status, self::POST_TYPE, (int) ($postarr['post_parent'] ?? 0));

        return $data;
    }

    public static function columns(array $columns): array
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox" />',
            'von_datum' => __('Von', 'igw_wp_urlaub_post'),
            'bis_datum' => __('Bis', 'igw_wp_urlaub_post'),
            'title' => __('Titel', 'igw_wp_urlaub_post'),
            'date' => $columns['date'] ?? __('Datum', 'igw_wp_urlaub_post'),
        ];
    }

    public static function render_column(string $column, int $post_id): void
    {
        if ($column === 'von_datum') {
            echo esc_html(self::format_date_for_display((string) get_post_meta($post_id, self::META_VON, true)));
            return;
        }

        if ($column === 'bis_datum') {
            echo esc_html(self::format_date_for_display((string) get_post_meta($post_id, self::META_BIS, true)));
        }
    }

    public static function sortable_columns(array $columns): array
    {
        $columns['von_datum'] = 'von_datum';
        $columns['bis_datum'] = 'bis_datum';
        return $columns;
    }

    public static function handle_columns_sort(\WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query() || $query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');
        if (in_array($orderby, ['von_datum', 'bis_datum'], true)) {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value');
        }
    }

    public static function register_shortcodes(): void
    {
        add_shortcode('igw_urlaub', [__CLASS__, 'shortcode_full']);
        add_shortcode('igw_vacation_notice', [__CLASS__, 'shortcode_compact']);
    }

    public static function shortcode_full(array $atts = []): string
    {
        return self::render_notices('full', $atts);
    }

    public static function shortcode_compact(array $atts = []): string
    {
        return self::render_notices('compact', $atts);
    }

    public static function register_block(): void
    {
        wp_register_script(
            'igw-wp-urlaub-post-block-editor',
            plugins_url('assets/block.js', __FILE__),
            ['wp-blocks', 'wp-element', 'wp-i18n'],
            '1.0.1',
            true
        );

        register_block_type('igw-wp-urlaub-post/urlaub', [
            'api_version' => 2,
            'editor_script' => 'igw-wp-urlaub-post-block-editor',
            'render_callback' => [__CLASS__, 'render_full_block'],
        ]);
    }

    public static function render_full_block(): string
    {
        return self::shortcode_full([]);
    }

    private static function render_notices(string $mode, array $atts): string
    {
        $atts = shortcode_atts(['limit' => 10], $atts);
        $posts = self::query_active_holidays(max(1, (int) $atts['limit']));

        if ($posts === []) {
            return '';
        }

        $html = '<div class="igw-urlaub-post-list igw-urlaub-post-list--' . esc_attr($mode) . '">';

        foreach ($posts as $post) {
            $von = igw_urlaub_post_format_date_de((string) get_post_meta($post->ID, self::META_VON, true));
            $bis = igw_urlaub_post_format_date_de((string) get_post_meta($post->ID, self::META_BIS, true));

            $date_line = sprintf(
                esc_html__('vom %1$s bis %2$s', 'igw_wp_urlaub_post'),
                esc_html($von),
                esc_html($bis)
            );

            $html .= '<article class="igw-urlaub-post-item">';
            $html .= '<h3 class="igw-urlaub-post-item__title"><a href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h3>';
            $html .= '<p class="igw-urlaub-post-item__dates">' . $date_line . '</p>';

            if ($mode === 'full') {
                if (has_post_thumbnail($post)) {
                    $html .= '<div class="igw-urlaub-post-item__image">' . get_the_post_thumbnail($post, 'medium') . '</div>';
                }
                $html .= '<div class="igw-urlaub-post-item__content">' . apply_filters('the_content', (string) $post->post_content) . '</div>';
            }

            $html .= '</article>';
        }

        $html .= '</div>';

        return $html;
    }

    private static function query_active_holidays(int $limit): array
    {
        $today = wp_date('Y-m-d');
        $pre_days = max(0, (int) get_option(self::OPT_PRE_DAYS, 0));
        $until = wp_date('Y-m-d', strtotime('+' . $pre_days . ' days', strtotime($today)));

        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'meta_value',
            'meta_key' => self::META_VON,
            'order' => 'ASC',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => self::META_ACTIVE,
                    'value' => 1,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => self::META_VON,
                    'value' => $until,
                    'compare' => '<=',
                    'type' => 'DATE',
                ],
                [
                    'key' => self::META_BIS,
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
            ],
        ]);

        return $query->posts;
    }

    public static function register_settings_page(): void
    {
        add_options_page(
            __('Urlaub Post Einstellungen', 'igw_wp_urlaub_post'),
            __('Urlaub Post', 'igw_wp_urlaub_post'),
            'manage_options',
            'igw-wp-urlaub-post-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting('igw_wp_urlaub_post_settings', self::OPT_PRE_DAYS, [
            'type' => 'integer',
            'sanitize_callback' => static function ($value) {
                return max(0, (int) $value);
            },
            'default' => 0,
        ]);
    }

    public static function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Urlaub Post Einstellungen', 'igw_wp_urlaub_post') . '</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields('igw_wp_urlaub_post_settings');
        echo '<table class="form-table"><tr>';
        echo '<th scope="row"><label for="' . esc_attr(self::OPT_PRE_DAYS) . '">' . esc_html__('Veröffentlichen bevor von_datum (nummer)', 'igw_wp_urlaub_post') . '</label></th>';
        echo '<td><input type="number" min="0" class="small-text" id="' . esc_attr(self::OPT_PRE_DAYS) . '" name="' . esc_attr(self::OPT_PRE_DAYS) . '" value="' . esc_attr((string) get_option(self::OPT_PRE_DAYS, 0)) . '" /></td>';
        echo '</tr></table>';
        submit_button();
        echo '</form>';

        echo '<hr /><h2>' . esc_html__('Export zu WP Plugin Öffnungszeiten', 'igw_wp_urlaub_post') . '</h2>';
        echo '<p>' . esc_html__('Schreibt alle aktiven Urlaubsposts in „Ferien (Neue)“ des Plugins igw_wp_open_zeit.', 'igw_wp_urlaub_post') . '</p>';
        $integration_available = self::is_open_zeit_available();

        if (! $integration_available) {
            echo '<p><em>' . esc_html__('Export-API nicht verfügbar. Bitte Plugin igw_wp_open_zeit aktivieren.', 'igw_wp_urlaub_post') . '</em></p>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="igw_wp_urlaub_post_export_ferien" />';
        wp_nonce_field('igw_wp_urlaub_post_export_ferien', 'igw_wp_urlaub_post_export_nonce');
        submit_button(__('Ferien exportieren', 'igw_wp_urlaub_post'), 'secondary', 'submit', false, ['disabled' => ! $integration_available]);
        echo '</form></div>';
    }

    public static function handle_export_ferien(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'igw_wp_urlaub_post'));
        }

        check_admin_referer('igw_wp_urlaub_post_export_ferien', 'igw_wp_urlaub_post_export_nonce');

        $rows = self::build_export_rows();

        $ok = self::export_rows_to_open_zeit($rows, [
            'source' => 'igw_wp_urlaub_post',
            'mode' => 'replace',
        ]);

        if (! $ok) {
            wp_die(esc_html__('Integration nicht verfügbar: Keine kompatible Export-API in igw_wp_open_zeit gefunden.', 'igw_wp_urlaub_post'));
        }

        wp_safe_redirect(admin_url('options-general.php?page=igw-wp-urlaub-post-settings'));
        exit;
    }


    private static function is_open_zeit_available(): bool
    {
        if (function_exists('igw_wp_open_zeit_upsert_ferien')) {
            return true;
        }

        if (has_action('igw_wp_open_zeit_upsert_ferien') || has_filter('igw_wp_open_zeit_upsert_ferien')) {
            return true;
        }

        if (class_exists('IGW_WP_Open_Zeit') && method_exists('IGW_WP_Open_Zeit', 'upsert_ferien')) {
            return true;
        }

        if (class_exists('IGW_WP_Open_Zeit_Integration') && method_exists('IGW_WP_Open_Zeit_Integration', 'upsert_ferien')) {
            return true;
        }

        if (class_exists('IGW_WP_Open_Zeit_Plugin')) {
            $instance = null;
            if (method_exists('IGW_WP_Open_Zeit_Plugin', 'instance')) {
                $instance = \IGW_WP_Open_Zeit_Plugin::instance();
            }
            if (is_object($instance) && method_exists($instance, 'upsert_ferien')) {
                return true;
            }
        }

        return false;
    }

    private static function export_rows_to_open_zeit(array $rows, array $context): bool
    {
        if (function_exists('igw_wp_open_zeit_upsert_ferien')) {
            igw_wp_open_zeit_upsert_ferien($rows, $context);
            return true;
        }

        if (has_filter('igw_wp_open_zeit_upsert_ferien')) {
            apply_filters('igw_wp_open_zeit_upsert_ferien', $rows, $context);
            return true;
        }

        if (has_action('igw_wp_open_zeit_upsert_ferien')) {
            do_action('igw_wp_open_zeit_upsert_ferien', $rows, $context);
            return true;
        }

        if (class_exists('IGW_WP_Open_Zeit') && method_exists('IGW_WP_Open_Zeit', 'upsert_ferien')) {
            \IGW_WP_Open_Zeit::upsert_ferien($rows, $context);
            return true;
        }

        if (class_exists('IGW_WP_Open_Zeit_Integration') && method_exists('IGW_WP_Open_Zeit_Integration', 'upsert_ferien')) {
            \IGW_WP_Open_Zeit_Integration::upsert_ferien($rows, $context);
            return true;
        }

        if (class_exists('IGW_WP_Open_Zeit_Plugin') && method_exists('IGW_WP_Open_Zeit_Plugin', 'instance')) {
            $instance = \IGW_WP_Open_Zeit_Plugin::instance();
            if (is_object($instance) && method_exists($instance, 'upsert_ferien')) {
                $instance->upsert_ferien($rows, $context);
                return true;
            }
        }

        return false;
    }

    private static function build_export_rows(): array
    {
        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => self::META_ACTIVE,
                    'value' => 1,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        $rows = [];
        foreach ($query->posts as $post) {
            $von = self::normalize_date((string) get_post_meta($post->ID, self::META_VON, true));
            $bis = self::normalize_date((string) get_post_meta($post->ID, self::META_BIS, true));
            if ($von === '' || $bis === '') {
                continue;
            }

            $rows[] = [
                'Name' => get_the_title($post),
                'Start' => $von,
                'Ende' => $bis,
            ];
        }

        return $rows;
    }

    public static function maybe_migrate_legacy_data(): void
    {
        if ((int) get_option(self::OPT_MIGRATION_DONE, 0) === 1 || ! current_user_can('manage_options')) {
            return;
        }

        $legacy_options = ['urlaub_post_entries', 'wp_offen_urlaub_post_entries', 'urlaub_entries'];

        foreach ($legacy_options as $option_name) {
            $rows = get_option($option_name, []);
            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                self::migrate_legacy_row(is_array($row) ? $row : []);
            }
        }

        update_option(self::OPT_MIGRATION_DONE, 1, false);
    }

    private static function migrate_legacy_row(array $row): void
    {
        $title = isset($row['title']) ? sanitize_text_field((string) $row['title']) : '';
        $content = isset($row['content']) ? wp_kses_post((string) $row['content']) : '';
        $von = self::normalize_date((string) ($row['von_datum'] ?? ''));
        $bis = self::normalize_date((string) ($row['bis_datum'] ?? ''));

        if ($title === '' || $von === '' || $bis === '') {
            return;
        }

        $id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $content,
        ]);

        if ($id <= 0) {
            return;
        }

        update_post_meta($id, self::META_VON, $von);
        update_post_meta($id, self::META_BIS, $bis);
        update_post_meta($id, self::META_ACTIVE, isset($row['active']) && (int) $row['active'] === 0 ? 0 : 1);

        if (! empty($row['thumbnail_id'])) {
            set_post_thumbnail($id, (int) $row['thumbnail_id']);
        }
    }

    public static function single_template_override(string $template): string
    {
        if (! is_singular(self::POST_TYPE)) {
            return $template;
        }

        $plugin_template = plugin_dir_path(__FILE__) . 'templates/single-urlaub_post.php';
        return file_exists($plugin_template) ? $plugin_template : $template;
    }

    private static function normalize_date(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        $dt = date_create_immutable($date);
        if ($dt === false) {
            return '';
        }

        return $dt->format('Y-m-d');
    }

    private static function format_date_for_display(string $date): string
    {
        $normalized = self::normalize_date($date);
        if ($normalized === '') {
            return '';
        }

        return igw_urlaub_post_format_date_de($normalized);
    }
}

IGW_WP_Urlaub_Post_Plugin::init();
register_activation_hook(__FILE__, ['IGW_WP_Urlaub_Post_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['IGW_WP_Urlaub_Post_Plugin', 'deactivate']);
