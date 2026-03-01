<?php

namespace UrlaubPost\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class CPT
{
    private const POST_TYPE = 'urlaub_post';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register']);
        add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_meta'], 10, 2);
        add_filter('default_title', [__CLASS__, 'default_title'], 10, 2);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [__CLASS__, 'sortable_columns']);
        add_action('pre_get_posts', [__CLASS__, 'handle_sorting']);

        add_action('admin_notices', [__CLASS__, 'render_notices']);
    }

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Urlaub', URLAUB_POST_TEXTDOMAIN),
                'singular_name' => __('Urlaub', URLAUB_POST_TEXTDOMAIN),
                'add_new' => __('Neu hinzufügen', URLAUB_POST_TEXTDOMAIN),
                'add_new_item' => __('Neuen Urlaub hinzufügen', URLAUB_POST_TEXTDOMAIN),
                'edit_item' => __('Urlaub bearbeiten', URLAUB_POST_TEXTDOMAIN),
                'new_item' => __('Neuer Urlaub', URLAUB_POST_TEXTDOMAIN),
                'view_item' => __('Urlaub ansehen', URLAUB_POST_TEXTDOMAIN),
                'search_items' => __('Urlaub durchsuchen', URLAUB_POST_TEXTDOMAIN),
                'not_found' => __('Keine Urlaube gefunden.', URLAUB_POST_TEXTDOMAIN),
                'menu_name' => __('Urlaub', URLAUB_POST_TEXTDOMAIN),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['editor', 'thumbnail', 'title'],
            'has_archive' => false,
            'rewrite' => false,
        ]);
    }

    public static function add_metaboxes(): void
    {
        add_meta_box(
            'urlaub_post_data',
            __('Urlaubsdaten', URLAUB_POST_TEXTDOMAIN),
            [__CLASS__, 'render_data_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_data_metabox(\WP_Post $post): void
    {
        wp_nonce_field('urlaub_post_save_meta', 'urlaub_post_meta_nonce');

        $von_datum   = (string) get_post_meta($post->ID, 'von_datum', true);
        $bis_datum   = (string) get_post_meta($post->ID, 'bis_datum', true);
        $urlaub_titel = (string) get_post_meta($post->ID, 'urlaub_titel', true);
        $title_mode  = (string) get_post_meta($post->ID, 'title_mode', true);
        $active      = get_post_meta($post->ID, 'active', true);

        if ($title_mode !== 'manual') {
            $title_mode = 'auto';
        }

        if ($active === '') {
            $active = '1';
        }
        ?>
        <p>
            <label for="von_datum"><strong><?php esc_html_e('Von (Datum)', URLAUB_POST_TEXTDOMAIN); ?></strong></label><br>
            <input type="date" id="von_datum" name="von_datum" value="<?php echo esc_attr($von_datum); ?>" required>
        </p>
        <p>
            <label for="bis_datum"><strong><?php esc_html_e('Bis (Datum)', URLAUB_POST_TEXTDOMAIN); ?></strong></label><br>
            <input type="date" id="bis_datum" name="bis_datum" value="<?php echo esc_attr($bis_datum); ?>" required>
        </p>
        <p>
            <label for="urlaub_titel"><strong><?php esc_html_e('Titel', URLAUB_POST_TEXTDOMAIN); ?></strong></label><br>
            <input type="text" id="urlaub_titel" name="urlaub_titel" class="widefat" value="<?php echo esc_attr($urlaub_titel); ?>">
        </p>
        <p>
            <label for="title_mode"><strong><?php esc_html_e('Titelform', URLAUB_POST_TEXTDOMAIN); ?></strong></label><br>
            <select id="title_mode" name="title_mode">
                <option value="auto" <?php selected($title_mode, 'auto'); ?>><?php esc_html_e('Auto', URLAUB_POST_TEXTDOMAIN); ?></option>
                <option value="manual" <?php selected($title_mode, 'manual'); ?>><?php esc_html_e('Manuell', URLAUB_POST_TEXTDOMAIN); ?></option>
            </select>
        </p>
        <p>
            <label>
                <input type="checkbox" name="active" value="1" <?php checked((string) $active, '1'); ?>>
                <?php esc_html_e('Aktiv', URLAUB_POST_TEXTDOMAIN); ?>
            </label>
        </p>
        <?php
    }

    public static function save_meta(int $post_id, \WP_Post $post): void
    {
        if (! isset($_POST['urlaub_post_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['urlaub_post_meta_nonce'])), 'urlaub_post_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $von = isset($_POST['von_datum']) ? self::sanitize_date(wp_unslash($_POST['von_datum'])) : '';
        $bis = isset($_POST['bis_datum']) ? self::sanitize_date(wp_unslash($_POST['bis_datum'])) : '';

        if ($von === '' || $bis === '') {
            self::add_notice('error', __('Bitte setzen Sie gültige Werte für Von und Bis.', URLAUB_POST_TEXTDOMAIN));
            return;
        }

        if ($von > $bis) {
            self::add_notice('error', __('Das Von-Datum muss vor oder am Bis-Datum liegen.', URLAUB_POST_TEXTDOMAIN));
            return;
        }

        $urlaub_titel = isset($_POST['urlaub_titel']) ? sanitize_text_field(wp_unslash($_POST['urlaub_titel'])) : '';
        $title_mode = isset($_POST['title_mode']) ? sanitize_text_field(wp_unslash($_POST['title_mode'])) : 'auto';
        $title_mode = $title_mode === 'manual' ? 'manual' : 'auto';
        $active = isset($_POST['active']) ? '1' : '0';

        update_post_meta($post_id, 'von_datum', $von);
        update_post_meta($post_id, 'bis_datum', $bis);
        update_post_meta($post_id, 'urlaub_titel', $urlaub_titel);
        update_post_meta($post_id, 'title_mode', $title_mode);
        update_post_meta($post_id, 'active', $active);

        if ($title_mode === 'auto') {
            $suffix = $urlaub_titel !== '' ? $urlaub_titel : __('Urlaub', URLAUB_POST_TEXTDOMAIN);
            $new_title = sprintf('Urlaub-vom-%s-bis-%s-%s', $von, $bis, $suffix);

            if ($post->post_title !== $new_title) {
                remove_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_meta'], 10);
                wp_update_post([
                    'ID' => $post_id,
                    'post_title' => $new_title,
                ]);
                add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_meta'], 10, 2);
            }
        }
    }

    public static function default_title(string $title, \WP_Post $post): string
    {
        if ($post->post_type !== self::POST_TYPE) {
            return $title;
        }

        return __('Urlaubstitel (wird bei Auto überschrieben)', URLAUB_POST_TEXTDOMAIN);
    }

    public static function columns(array $columns): array
    {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'] ?? '<input type="checkbox" />';
        $new_columns['von_datum'] = __('Von', URLAUB_POST_TEXTDOMAIN);
        $new_columns['bis_datum'] = __('Bis', URLAUB_POST_TEXTDOMAIN);
        $new_columns['title'] = __('Title', URLAUB_POST_TEXTDOMAIN);
        $new_columns['date'] = __('Datum', URLAUB_POST_TEXTDOMAIN);

        return $new_columns;
    }

    public static function column_content(string $column, int $post_id): void
    {
        if ($column === 'von_datum') {
            echo esc_html((string) get_post_meta($post_id, 'von_datum', true));
        }

        if ($column === 'bis_datum') {
            echo esc_html((string) get_post_meta($post_id, 'bis_datum', true));
        }
    }

    public static function sortable_columns(array $columns): array
    {
        $columns['von_datum'] = 'von_datum';
        $columns['bis_datum'] = 'bis_datum';

        return $columns;
    }

    public static function handle_sorting(\WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');
        if ($orderby === 'von_datum' || $orderby === 'bis_datum') {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value');
        }
    }

    private static function sanitize_date(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            return '';
        }

        return $value;
    }

    private static function add_notice(string $type, string $message): void
    {
        $notices = get_transient('urlaub_post_admin_notices');
        if (! is_array($notices)) {
            $notices = [];
        }

        $notices[] = [
            'type' => $type,
            'message' => $message,
        ];

        set_transient('urlaub_post_admin_notices', $notices, MINUTE_IN_SECONDS);
    }

    public static function render_notices(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || strpos((string) $screen->id, self::POST_TYPE) === false) {
            return;
        }

        $notices = get_transient('urlaub_post_admin_notices');
        if (! is_array($notices) || $notices === []) {
            return;
        }

        delete_transient('urlaub_post_admin_notices');

        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%1$s"><p>%2$s</p></div>',
                esc_attr($notice['type'] === 'error' ? 'error' : 'success'),
                esc_html((string) $notice['message'])
            );
        }
    }
}
