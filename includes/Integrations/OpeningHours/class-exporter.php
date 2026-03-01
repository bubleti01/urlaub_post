<?php

namespace UrlaubPost\Integrations\OpeningHours;

if (! defined('ABSPATH')) {
    exit;
}

class Exporter
{
    public static function is_opening_hours_active(): bool
    {
        return class_exists('OpeningHours\\Module\\OpeningHours');
    }

    public static function get_opening_hour_sets(): array
    {
        if (! self::is_opening_hours_active()) {
            return [];
        }

        try {
            $instance = \OpeningHours\Module\OpeningHours::getInstance();
            if (method_exists($instance, 'getSetsOptions')) {
                $sets = $instance->getSetsOptions();
                return is_array($sets) ? $sets : [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    public static function export_active_vacations(): array
    {
        if (! self::is_opening_hours_active()) {
            return ['success' => false, 'message' => __('Opening-Hours ist nicht aktiv.', URLAUB_POST_TEXTDOMAIN)];
        }

        if (! (bool) get_option('urlaub_post_oh_export_enabled', false)) {
            return ['success' => false, 'message' => __('Export ist in den Einstellungen deaktiviert.', URLAUB_POST_TEXTDOMAIN)];
        }

        $set_id = (int) get_option('urlaub_post_oh_set_id', 0);
        if ($set_id <= 0) {
            return ['success' => false, 'message' => __('Bitte wählen Sie ein Opening-Hours Set aus.', URLAUB_POST_TEXTDOMAIN)];
        }

        $target_post = get_post($set_id);
        if (! $target_post || $target_post->post_type !== 'op-set') {
            return ['success' => false, 'message' => __('Ungültiges Opening-Hours Set.', URLAUB_POST_TEXTDOMAIN)];
        }

        $root_post = self::resolve_root_set($target_post);

        $vacations = self::load_active_vacations();
        $holidays = [];

        foreach ($vacations as $vacation) {
            $from = (string) get_post_meta($vacation->ID, 'von_datum', true);
            $to = (string) get_post_meta($vacation->ID, 'bis_datum', true);
            if (! self::is_valid_date($from) || ! self::is_valid_date($to) || $to < $from) {
                continue;
            }

            $name = get_the_title($vacation);

            if ($name === '') {
                $name = __('Urlaub', URLAUB_POST_TEXTDOMAIN);
            }

            $holidays[] = [
                'name' => $name,
                'dateStart' => $from,
                'dateEnd' => $to,
            ];
        }

        $saved = self::save_holidays_to_opening_hours($root_post, $holidays);
        if (! $saved) {
            return ['success' => false, 'message' => __('Export fehlgeschlagen: Opening-Hours Persistence konnte nicht angesprochen werden.', URLAUB_POST_TEXTDOMAIN)];
        }

        return ['success' => true, 'message' => __('Export erfolgreich abgeschlossen.', URLAUB_POST_TEXTDOMAIN)];
    }

    private static function load_active_vacations(): array
    {
        $query = new \WP_Query([
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
        ]);

        return $query->posts;
    }

    private static function resolve_root_set(\WP_Post $post): \WP_Post
    {
        $current = $post;
        while ($current->post_parent > 0) {
            $parent = get_post($current->post_parent);
            if (! $parent || $parent->post_type !== 'op-set') {
                break;
            }
            $current = $parent;
        }

        return $current;
    }

    private static function save_holidays_to_opening_hours(\WP_Post $set_post, array $holidays): bool
    {
        $candidates = [
            'OpeningHours\\Module\\OpeningHours\\Persistence\\Persistence',
            'OpeningHours\\Module\\OpeningHours\\Model\\Persistence',
            'OpeningHours\\Module\\OpeningHours\\Persistence',
        ];

        foreach ($candidates as $class) {
            if (! class_exists($class)) {
                continue;
            }

            try {
                $instance = new $class($set_post);
                if (! method_exists($instance, 'saveHolidays')) {
                    continue;
                }

                $instance->saveHolidays($holidays);
                return true;
            } catch (\Throwable $e) {
                continue;
            }
        }

        return false;
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
