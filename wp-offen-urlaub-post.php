<?php
/**
 * Plugin Name: WP Urlaub Post
 * Plugin URI: https://igo2web.com/de/wordpress-plugins-von-igw-design/wp-urlaub-post
 * Description: Erstellen/Verwalten Sie Urlaubszeiten Posts in WordPress und zeigen Sie diese in vielen verschiedenen Widgets und Shortcodes an.
 * Version: 1.0.1
 * Requires at least: 6.0
 * Author: IGW Design
 * Author URI: https://igo2web.com
 * Text Domain: urlaub_post
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

final class Urlaub_Post_Plugin
{
    private const POST_TYPE = 'urlaub_post';
    private const META_VON = 'von_datum';
    private const META_BIS = 'bis_datum';
    private const META_ACTIVE = 'active';
    private const OPT_PRE_DAYS = 'urlaub_post_pre_days';
    private const OPT_OPENING_SET_ID = 'urlaub_post_opening_set_id';
    private const OPT_MIGRATION_DONE = 'urlaub_post_migration_done';

    public static function init(): void
    {
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

        add_action('admin_post_urlaub_post_export_opening_hours', [__CLASS__, 'handle_opening_hours_export']);

        add_action('admin_init', [__CLASS__, 'maybe_migrate_legacy_data']);
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

    public static function register_post_type(): void
    {
        $labels = [
            'name' => __('Urlaube', 'urlaub_post'),
            'singular_name' => __('Urlaub', 'urlaub_post'),
            'menu_name' => __('Urlaub', 'urlaub_post'),
            'name_admin_bar' => __('Urlaub', 'urlaub_post'),
            'add_new' => __('Neu hinzufügen', 'urlaub_post'),
            'add_new_item' => __('Urlaub hinzufügen', 'urlaub_post'),
            'new_item' => __('Neuer Urlaub', 'urlaub_post'),
            'edit_item' => __('Urlaub bearbeiten', 'urlaub_post'),
            'view_item' => __('Urlaub ansehen', 'urlaub_post'),
            'all_items' => __('Alle Urlaube', 'urlaub_post'),
            'search_items' => __('Urlaube durchsuchen', 'urlaub_post'),
            'not_found' => __('Keine Urlaube gefunden.', 'urlaub_post'),
            'not_found_in_trash' => __('Keine Urlaube im Papierkorb gefunden.', 'urlaub_post'),
        ];

        register_post_type(
            self::POST_TYPE,
            [
                'labels' => $labels,
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'show_in_rest' => true,
                'has_archive' => true,
                'rewrite' => ['slug' => 'urlaub'],
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
                'taxonomies' => ['category', 'post_tag'],
                'menu_icon' => 'dashicons-calendar-alt',
            ]
        );
    }

    public static function register_meta(): void
    {
        $meta_args = [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ];

        register_post_meta(self::POST_TYPE, self::META_VON, $meta_args);
        register_post_meta(self::POST_TYPE, self::META_BIS, $meta_args);
        register_post_meta(
            self::POST_TYPE,
            self::META_ACTIVE,
            [
                'type' => 'integer',
                'single' => true,
                'show_in_rest' => true,
                'default' => 1,
                'auth_callback' => static function () {
                    return current_user_can('edit_posts');
                },
            ]
        );
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box(
            'urlaub_post_dates',
            __('Urlaubszeitraum', 'urlaub_post'),
            [__CLASS__, 'render_dates_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    public static function render_dates_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('urlaub_post_dates_meta', 'urlaub_post_dates_meta_nonce');

        $von = (string) get_post_meta($post->ID, self::META_VON, true);
        $bis = (string) get_post_meta($post->ID, self::META_BIS, true);
        $active = (int) get_post_meta($post->ID, self::META_ACTIVE, true);
        if ($active !== 0) {
            $active = 1;
        }

        echo '<p><label for="urlaub_post_von"><strong>' . esc_html__('Von', 'urlaub_post') . '</strong></label><br />';
        echo '<input type="date" id="urlaub_post_von" name="urlaub_post_von" value="' . esc_attr($von) . '" style="width:100%;" /></p>';

        echo '<p><label for="urlaub_post_bis"><strong>' . esc_html__('Bis', 'urlaub_post') . '</strong></label><br />';
        echo '<input type="date" id="urlaub_post_bis" name="urlaub_post_bis" value="' . esc_attr($bis) . '" style="width:100%;" /></p>';

        echo '<p><label>';
        echo '<input type="checkbox" name="urlaub_post_active" value="1" ' . checked($active, 1, false) . ' /> ';
        echo esc_html__('Aktiv', 'urlaub_post');
        echo '</label></p>';
    }

    public static function save_meta_boxes(int $post_id): void
    {
        if (! isset($_POST['urlaub_post_dates_meta_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['urlaub_post_dates_meta_nonce'])), 'urlaub_post_dates_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $von = isset($_POST['urlaub_post_von']) ? sanitize_text_field(wp_unslash($_POST['urlaub_post_von'])) : '';
        $bis = isset($_POST['urlaub_post_bis']) ? sanitize_text_field(wp_unslash($_POST['urlaub_post_bis'])) : '';
        $active = isset($_POST['urlaub_post_active']) ? 1 : 0;

        update_post_meta($post_id, self::META_VON, self::normalize_date($von));
        update_post_meta($post_id, self::META_BIS, self::normalize_date($bis));
        update_post_meta($post_id, self::META_ACTIVE, $active);
    }

    public static function inject_slug_from_dates(array $data, array $postarr): array
    {
        if (($data['post_type'] ?? '') !== self::POST_TYPE) {
            return $data;
        }

        $post_status = $data['post_status'] ?? '';
        if (in_array($post_status, ['auto-draft', 'inherit'], true)) {
            return $data;
        }

        $title = $data['post_title'] ?? '';
        if ($title === '') {
            return $data;
        }

        $von = '';
        $bis = '';

        if (isset($_POST['urlaub_post_von'])) {
            $von = self::normalize_date(sanitize_text_field(wp_unslash($_POST['urlaub_post_von'])));
        }
        if (isset($_POST['urlaub_post_bis'])) {
            $bis = self::normalize_date(sanitize_text_field(wp_unslash($_POST['urlaub_post_bis'])));
        }

        if ($von === '' || $bis === '') {
            $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
            if ($post_id > 0) {
                if ($von === '') {
                    $von = self::normalize_date((string) get_post_meta($post_id, self::META_VON, true));
                }
                if ($bis === '') {
                    $bis = self::normalize_date((string) get_post_meta($post_id, self::META_BIS, true));
                }
            }
        }

        if ($von === '' || $bis === '') {
            return $data;
        }

        $base_slug = sanitize_title($title . '-vom-' . $von . '-bis-' . $bis);
        $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        $parent_id = isset($postarr['post_parent']) ? (int) $postarr['post_parent'] : 0;

        $data['post_name'] = wp_unique_post_slug(
            $base_slug,
            $post_id,
            $post_status,
            self::POST_TYPE,
            $parent_id
        );

        return $data;
    }

    public static function columns(array $columns): array
    {
        $new = [];
        $new['cb'] = $columns['cb'] ?? '<input type="checkbox" />';
        $new['title'] = __('Titel', 'urlaub_post');
        $new['von_datum'] = __('Von', 'urlaub_post');
        $new['bis_datum'] = __('Bis', 'urlaub_post');
        $new['date'] = $columns['date'] ?? __('Datum', 'urlaub_post');

        return $new;
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
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if (($query->get('post_type') ?? '') !== self::POST_TYPE) {
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
        add_shortcode('urlaub', [__CLASS__, 'shortcode_urlaub']);
        add_shortcode('vacation_notice', [__CLASS__, 'shortcode_vacation_notice']);
    }

    public static function shortcode_urlaub(array $atts = []): string
    {
        return self::render_notices('full', $atts);
    }

    public static function shortcode_vacation_notice(array $atts = []): string
    {
        return self::render_notices('compact', $atts);
    }

    public static function register_block(): void
    {
        wp_register_script(
            'urlaub-post-block-editor',
            plugins_url('assets/block.js', __FILE__),
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor'],
            self::plugin_version(),
            true
        );

        register_block_type('urlaub-post/notice', [
            'api_version' => 2,
            'editor_script' => 'urlaub-post-block-editor',
            'render_callback' => [__CLASS__, 'render_block'],
            'attributes' => [
                'view' => [
                    'type' => 'string',
                    'default' => 'full',
                ],
            ],
        ]);
    }

    public static function render_block(array $attributes): string
    {
        $view = isset($attributes['view']) && $attributes['view'] === 'compact' ? 'compact' : 'full';
        return self::render_notices($view, []);
    }

    private static function render_notices(string $view, array $atts): string
    {
        $defaults = [
            'limit' => 10,
        ];

        $atts = shortcode_atts($defaults, $atts);
        $limit = max(1, (int) $atts['limit']);

        $posts = self::query_active_holidays($limit);

        if ($posts === []) {
            return '';
        }

        $out = '<div class="urlaub-post-notices urlaub-post-notices--' . esc_attr($view) . '">';

        foreach ($posts as $post) {
            $von = self::format_date_for_display((string) get_post_meta($post->ID, self::META_VON, true));
            $bis = self::format_date_for_display((string) get_post_meta($post->ID, self::META_BIS, true));

            $out .= '<article class="urlaub-post-item">';
            $out .= '<h3 class="urlaub-post-item__title"><a href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h3>';
            $out .= '<p class="urlaub-post-item__date">' . esc_html($von . ' - ' . $bis) . '</p>';

            if ($view === 'full') {
                if (has_post_thumbnail($post)) {
                    $out .= '<div class="urlaub-post-item__thumb">' . get_the_post_thumbnail($post, 'medium') . '</div>';
                }
                $content = apply_filters('the_content', (string) $post->post_content);
                $out .= '<div class="urlaub-post-item__content">' . $content . '</div>';
            }

            $out .= '</article>';
        }

        $out .= '</div>';

        return $out;
    }

    private static function query_active_holidays(int $limit = 10): array
    {
        $today = wp_date('Y-m-d');
        $pre_days = max(0, (int) get_option(self::OPT_PRE_DAYS, 0));
        $start = wp_date('Y-m-d', strtotime('-' . $pre_days . ' days', strtotime($today)));

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
                    'key' => self::META_BIS,
                    'value' => $start,
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
                [
                    'key' => self::META_VON,
                    'value' => $today,
                    'compare' => '<=',
                    'type' => 'DATE',
                ],
            ],
        ]);

        return $query->posts;
    }

    public static function register_settings_page(): void
    {
        add_options_page(
            __('Urlaub Post Einstellungen', 'urlaub_post'),
            __('Urlaub Post', 'urlaub_post'),
            'manage_options',
            'urlaub-post-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting('urlaub_post_settings', self::OPT_PRE_DAYS, [
            'type' => 'integer',
            'sanitize_callback' => static function ($value) {
                return max(0, (int) $value);
            },
            'default' => 0,
        ]);

        register_setting('urlaub_post_settings', self::OPT_OPENING_SET_ID, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
    }

    public static function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $set_id = (string) get_option(self::OPT_OPENING_SET_ID, '');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Urlaub Post Einstellungen', 'urlaub_post') . '</h1>';

        settings_errors('urlaub_post_messages');

        echo '<form method="post" action="options.php">';
        settings_fields('urlaub_post_settings');
        echo '<table class="form-table" role="presentation">';

        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr(self::OPT_PRE_DAYS) . '">' . esc_html__('Pre-Days', 'urlaub_post') . '</label></th>';
        echo '<td><input name="' . esc_attr(self::OPT_PRE_DAYS) . '" type="number" min="0" id="' . esc_attr(self::OPT_PRE_DAYS) . '" value="' . esc_attr((string) get_option(self::OPT_PRE_DAYS, 0)) . '" class="small-text" />';
        echo '<p class="description">' . esc_html__('Tage vor Startdatum, ab denen ein Urlaub bereits angezeigt wird.', 'urlaub_post') . '</p></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr(self::OPT_OPENING_SET_ID) . '">' . esc_html__('WP-Opening-Hours Set-ID', 'urlaub_post') . '</label></th>';
        echo '<td><input name="' . esc_attr(self::OPT_OPENING_SET_ID) . '" type="text" id="' . esc_attr(self::OPT_OPENING_SET_ID) . '" value="' . esc_attr($set_id) . '" class="regular-text" /></td>';
        echo '</tr>';

        echo '</table>';
        submit_button();
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__('Export zu WP-Opening-Hours', 'urlaub_post') . '</h2>';
        echo '<p>' . esc_html__('Exportiert alle aktiven veröffentlichten Urlaubsposts und überschreibt Holidays im gewählten Set.', 'urlaub_post') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="urlaub_post_export_opening_hours" />';
        wp_nonce_field('urlaub_post_export_opening_hours', 'urlaub_post_export_nonce');
        submit_button(__('Jetzt exportieren', 'urlaub_post'), 'secondary', 'submit', false);
        echo '</form>';

        echo '</div>';
    }

    public static function handle_opening_hours_export(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'urlaub_post'));
        }

        check_admin_referer('urlaub_post_export_opening_hours', 'urlaub_post_export_nonce');

        $set_id = (string) get_option(self::OPT_OPENING_SET_ID, '');
        $result = self::export_to_opening_hours($set_id);

        if ($result['ok']) {
            add_settings_error('urlaub_post_messages', 'urlaub_post_export_ok', $result['message'], 'updated');
        } else {
            add_settings_error('urlaub_post_messages', 'urlaub_post_export_fail', $result['message'], 'error');
        }

        set_transient('settings_errors', get_settings_errors(), 30);

        wp_safe_redirect(admin_url('options-general.php?page=urlaub-post-settings'));
        exit;
    }

    private static function export_to_opening_hours(string $set_id): array
    {
        if ($set_id === '') {
            return ['ok' => false, 'message' => __('Set-ID fehlt.', 'urlaub_post')];
        }

        if (! class_exists('WP_Opening_Hours')) {
            return ['ok' => false, 'message' => __('WP-Opening-Hours nicht gefunden.', 'urlaub_post')];
        }

        $holidays = [];
        $posts = self::query_all_active_holidays();

        foreach ($posts as $post) {
            $von = self::normalize_date((string) get_post_meta($post->ID, self::META_VON, true));
            $bis = self::normalize_date((string) get_post_meta($post->ID, self::META_BIS, true));
            if ($von === '' || $bis === '') {
                continue;
            }

            $holidays[] = [
                'start' => $von,
                'end' => $bis,
                'label' => get_the_title($post),
            ];
        }

        if (function_exists('wp_opening_hours_set_holidays')) {
            wp_opening_hours_set_holidays($set_id, $holidays);
            return ['ok' => true, 'message' => __('Export erfolgreich abgeschlossen.', 'urlaub_post')];
        }

        if (class_exists('WP_Opening_Hours_Holiday_Manager') && method_exists('WP_Opening_Hours_Holiday_Manager', 'replace_holidays')) {
            \WP_Opening_Hours_Holiday_Manager::replace_holidays($set_id, $holidays);
            return ['ok' => true, 'message' => __('Export erfolgreich abgeschlossen.', 'urlaub_post')];
        }

        return [
            'ok' => false,
            'message' => __('Keine kompatible Export-Schnittstelle in WP-Opening-Hours gefunden.', 'urlaub_post'),
        ];
    }

    private static function query_all_active_holidays(): array
    {
        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => self::META_VON,
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => self::META_ACTIVE,
                    'value' => 1,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        return $query->posts;
    }

    public static function maybe_migrate_legacy_data(): void
    {
        if ((int) get_option(self::OPT_MIGRATION_DONE, 0) === 1) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        $legacy_rows = self::load_legacy_rows();
        foreach ($legacy_rows as $row) {
            self::migrate_legacy_row($row);
        }

        update_option(self::OPT_MIGRATION_DONE, 1, false);
    }

    private static function load_legacy_rows(): array
    {
        $rows = [];

        $candidates = [
            'urlaub_post_entries',
            'wp_offen_urlaub_post_entries',
            'urlaub_entries',
        ];

        foreach ($candidates as $option_name) {
            $value = get_option($option_name, null);
            if (is_array($value) && $value !== []) {
                $rows = array_merge($rows, $value);
            }
        }

        /**
         * Ermöglicht externen Code, Legacy-Einträge für die Migration zu liefern.
         *
         * @param array<int, array<string, mixed>> $rows
         */
        return apply_filters('urlaub_post_legacy_rows', $rows);
    }

    private static function migrate_legacy_row(array $row): void
    {
        $title = isset($row['title']) ? sanitize_text_field((string) $row['title']) : '';
        $content = isset($row['content']) ? wp_kses_post((string) $row['content']) : '';
        $von = isset($row['von_datum']) ? self::normalize_date((string) $row['von_datum']) : '';
        $bis = isset($row['bis_datum']) ? self::normalize_date((string) $row['bis_datum']) : '';
        $active = isset($row['active']) && (int) $row['active'] === 0 ? 0 : 1;
        $thumbnail_id = isset($row['thumbnail_id']) ? (int) $row['thumbnail_id'] : 0;

        if ($title === '' || $von === '' || $bis === '') {
            return;
        }

        $existing = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => self::META_VON,
                    'value' => $von,
                    'compare' => '=',
                ],
                [
                    'key' => self::META_BIS,
                    'value' => $bis,
                    'compare' => '=',
                ],
            ],
            'title' => $title,
        ]);

        if ($existing !== []) {
            return;
        }

        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $content,
        ], true);

        if (is_wp_error($post_id)) {
            return;
        }

        update_post_meta($post_id, self::META_VON, $von);
        update_post_meta($post_id, self::META_BIS, $bis);
        update_post_meta($post_id, self::META_ACTIVE, $active);

        if ($thumbnail_id > 0) {
            set_post_thumbnail($post_id, $thumbnail_id);
        }

        wp_update_post([
            'ID' => $post_id,
            'post_title' => $title,
        ]);
    }

    private static function normalize_date(string $date): string
    {
        if ($date === '') {
            return '';
        }

        $date = trim($date);
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

        $timestamp = strtotime($normalized . ' 00:00:00');
        if ($timestamp === false) {
            return '';
        }

        return wp_date('d.m.Y', $timestamp);
    }

    private static function plugin_version(): string
    {
        if (! function_exists('get_file_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $data = get_file_data(__FILE__, ['Version' => 'Version']);
        return (string) ($data['Version'] ?? '1.0.1');
    }
}

Urlaub_Post_Plugin::init();
register_activation_hook(__FILE__, ['Urlaub_Post_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Urlaub_Post_Plugin', 'deactivate']);
