<?php

if (!defined('ABSPATH')) {
    exit;
}

class CSC_Scheduler
{
    public static function init()
    {
        add_action('csc_cron_tick', [__CLASS__, 'tick']);
    }

    public static function tick()
    {
        global $wpdb;

        $table   = CSC_DB::posts_table();
        $now_utc = current_time('mysql', 1);

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND publish_at <= %s ORDER BY publish_at ASC LIMIT %d",
                'scheduled',
                $now_utc,
                10
            ),
            ARRAY_A
        );

        foreach ($posts as $post) {
            $post_id = (int) $post['id'];

            $wpdb->update(
                $table,
                [
                    'status'     => 'processing',
                    'updated_at' => current_time('mysql', 1),
                ],
                ['id' => $post_id],
                ['%s', '%s'],
                ['%d']
            );

            try {
                $payload = json_decode((string) $post['payload'], true);
                if (!is_array($payload)) {
                    $payload = [];
                }

                CSC_Meta::publish((string) $post['platform'], $payload);

                $wpdb->update(
                    $table,
                    [
                        'status'     => 'sent',
                        'updated_at' => current_time('mysql', 1),
                    ],
                    ['id' => $post_id],
                    ['%s', '%s'],
                    ['%d']
                );
            } catch (Throwable $e) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table}
                         SET status = %s,
                             attempts = attempts + 1,
                             last_error = %s,
                             updated_at = %s
                         WHERE id = %d",
                        'failed',
                        $e->getMessage(),
                        current_time('mysql', 1),
                        $post_id
                    )
                );
            }
        }
    }
}
