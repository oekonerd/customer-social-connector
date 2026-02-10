<?php

if (!defined('ABSPATH')) {
    exit;
}

class CSC_REST
{
    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes()
    {
        register_rest_route('csc/v1', '/health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'health'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('csc/v1', '/posts', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_post_item'],
            'permission_callback' => ['CSC_Auth', 'permission_callback'],
        ]);

        register_rest_route('csc/v1', '/posts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'list_posts'],
            'permission_callback' => ['CSC_Auth', 'permission_callback'],
        ]);

        register_rest_route('csc/v1', '/posts/(?P<id>\d+)/retry', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'retry_post'],
            'permission_callback' => ['CSC_Auth', 'permission_callback'],
        ]);

        register_rest_route('csc/v1', '/comments/sync', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'comments_sync'],
            'permission_callback' => ['CSC_Auth', 'permission_callback'],
        ]);

        register_rest_route('csc/v1', '/comments/(?P<comment_id>\d+)/reply', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'comment_reply'],
            'permission_callback' => ['CSC_Auth', 'permission_callback'],
        ]);
    }

    public static function health()
    {
        return new WP_REST_Response([
            'ok'      => true,
            'version' => CSC_VERSION,
        ], 200);
    }

    public static function create_post_item(WP_REST_Request $request)
    {
        global $wpdb;

        $platform   = (string) $request->get_param('platform');
        $payload    = $request->get_param('payload');
        $publish_at = (string) $request->get_param('publish_at');

        if (!in_array($platform, ['fb', 'ig'], true)) {
            return new WP_Error('csc_validation', 'Invalid platform', ['status' => 400]);
        }

        if (!is_array($payload) && !is_object($payload)) {
            return new WP_Error('csc_validation', 'Payload must be an object', ['status' => 400]);
        }

        $timestamp = strtotime($publish_at);
        if ($timestamp === false) {
            return new WP_Error('csc_validation', 'Invalid publish_at format', ['status' => 400]);
        }

        $publish_at_utc = gmdate('Y-m-d H:i:s', $timestamp);
        $now_utc        = current_time('mysql', 1);
        $table          = CSC_DB::posts_table();

        $inserted = $wpdb->insert(
            $table,
            [
                'platform'   => $platform,
                'payload'    => wp_json_encode($payload),
                'publish_at' => $publish_at_utc,
                'status'     => 'scheduled',
                'attempts'   => 0,
                'last_error' => null,
                'created_at' => $now_utc,
                'updated_at' => $now_utc,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return new WP_Error('csc_db', 'Failed to create post', ['status' => 500]);
        }

        return new WP_REST_Response([
            'ok' => true,
            'id' => (int) $wpdb->insert_id,
        ], 201);
    }

    public static function list_posts(WP_REST_Request $request)
    {
        global $wpdb;

        $allowed_statuses = ['scheduled', 'sent', 'failed', 'processing'];
        $status           = $request->get_param('status');
        $limit            = (int) $request->get_param('limit');
        $offset           = (int) $request->get_param('offset');

        if ($limit <= 0 || $limit > 200) {
            $limit = 20;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        $table = CSC_DB::posts_table();

        if ($status && in_array($status, $allowed_statuses, true)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY publish_at ASC LIMIT %d OFFSET %d",
                $status,
                $limit,
                $offset
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY publish_at ASC LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        }

        $items = $wpdb->get_results($query, ARRAY_A);

        foreach ($items as &$item) {
            $decoded = json_decode((string) $item['payload'], true);
            $item['payload'] = is_array($decoded) ? $decoded : [];
        }

        return new WP_REST_Response([
            'ok'    => true,
            'items' => $items,
        ], 200);
    }

    public static function retry_post(WP_REST_Request $request)
    {
        global $wpdb;

        $id    = (int) $request->get_param('id');
        $table = CSC_DB::posts_table();

        $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!$post) {
            return new WP_Error('csc_not_found', 'Post not found', ['status' => 404]);
        }

        if ($post['status'] !== 'failed') {
            return new WP_Error('csc_state', 'Only failed posts can be retried', ['status' => 400]);
        }

        $updated = $wpdb->update(
            $table,
            [
                'status'     => 'scheduled',
                'updated_at' => current_time('mysql', 1),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('csc_db', 'Failed to retry post', ['status' => 500]);
        }

        return new WP_REST_Response([
            'ok' => true,
            'id' => $id,
        ], 200);
    }

    public static function comments_sync()
    {
        return new WP_REST_Response([
            'ok'      => true,
            'message' => 'not implemented',
        ], 200);
    }

    public static function comment_reply(WP_REST_Request $request)
    {
        $comment_id = (int) $request->get_param('comment_id');

        return new WP_REST_Response([
            'ok'         => true,
            'comment_id' => $comment_id,
            'message'    => 'not implemented',
        ], 200);
    }
}
