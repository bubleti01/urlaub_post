<?php

namespace UrlaubPost\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

class Renderer
{
    public static function init(): void
    {
        add_shortcode('urlaub', [__CLASS__, 'shortcode']);
        add_shortcode('vacation_notice', [__CLASS__, 'shortcode']);
    }

    public static function shortcode(array $atts = []): string
    {
        $atts = shortcode_atts([
            'show_image' => '1',
            'show_dates' => '1',
            'limit' => '0',
            'id' => '0',
        ], $atts, 'urlaub');

        return self::render([
            'show_image' => $atts['show_image'] !== '0',
            'show_dates' => $atts['show_dates'] !== '0',
            'limit' => max(0, (int) $atts['limit']),
            'id' => max(0, (int) $atts['id']),
        ]);
    }

    public static function render_block(array $attributes = []): string
    {
        return self::render([
            'show_image' => isset($attributes['showImage']) ? (bool) $attributes['showImage'] : true,
            'show_dates' => isset($attributes['showDates']) ? (bool) $attributes['showDates'] : true,
            'limit' => isset($attributes['limit']) ? max(0, (int) $attributes['limit']) : 0,
            'id' => isset($attributes['id']) ? max(0, (int) $attributes['id']) : 0,
        ]);
    }

    private static function render(array $args): string
    {
        $items = self::get_active_items($args['id']);

        if ($args['limit'] > 0) {
            $items = array_slice($items, 0, $args['limit']);
        }

        if ($items === []) {
            return '';
        }

        ob_start();
        foreach ($items as $post) {
            $von = (string) get_post_meta($post->ID, 'von_datum', true);
            $bis = (string) get_post_meta($post->ID, 'bis_datum', true);
            ?>
            <div class="urlaub-post-notice">
                <h3><?php echo esc_html(get_the_title($post)); ?></h3>
                <?php if ($args['show_dates']) : ?>
                    <p class="urlaub-post-dates">
                        <?php
                        echo esc_html(
                            sprintf(
                                __('von %1$s bis %2$s', URLAUB_POST_TEXTDOMAIN),
                                $von,
                                $bis
                            )
                        );
                        ?>
                    </p>
                <?php endif; ?>
                <?php if ($args['show_image'] && has_post_thumbnail($post)) : ?>
                    <div class="urlaub-post-image"><?php echo get_the_post_thumbnail($post, 'large'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
                <div class="urlaub-post-content">
                    <?php echo apply_filters('the_content', $post->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
            <?php
        }

        return (string) ob_get_clean();
    }

    private static function get_active_items(int $id = 0): array
    {
        $query_args = [
            'post_type' => 'urlaub_post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_key' => 'von_datum',
            'meta_query' => [
                [
                    'key' => 'active',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ];

        if ($id > 0) {
            $query_args['p'] = $id;
        }

        $query = new \WP_Query($query_args);
        if (! $query->have_posts()) {
            return [];
        }

        $pre_days = max(0, (int) get_option('urlaub_post_pre_days', 0));
        $now = current_time('timestamp');
        $matches = [];

        foreach ($query->posts as $post) {
            $von = (string) get_post_meta($post->ID, 'von_datum', true);
            $bis = (string) get_post_meta($post->ID, 'bis_datum', true);

            if (! self::is_valid_date($von) || ! self::is_valid_date($bis) || $von > $bis) {
                continue;
            }

            $start = strtotime($von . ' 00:00:00');
            $end = strtotime($bis . ' 23:59:59');
            $start = strtotime('-' . $pre_days . ' days', $start);

            if ($start <= $now && $now <= $end) {
                $matches[] = $post;
            }
        }

        return $matches;
    }

    private static function is_valid_date(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $obj = \DateTime::createFromFormat('Y-m-d', $date);
        return $obj && $obj->format('Y-m-d') === $date;
    }
}
