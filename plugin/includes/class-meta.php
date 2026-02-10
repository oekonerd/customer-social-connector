<?php

if (!defined('ABSPATH')) {
    exit;
}

class CSC_Meta
{
    public static function publish($platform, $payload)
    {
        unset($platform, $payload);

        throw new Exception('Meta publish not implemented');
    }

    public static function get_token()
    {
        global $wpdb;

        $table = CSC_DB::tokens_table();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE provider = %s ORDER BY id DESC LIMIT 1", 'meta'),
            ARRAY_A
        );
    }
}
