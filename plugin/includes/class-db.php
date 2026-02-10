<?php

if (!defined('ABSPATH')) {
    exit;
}

class CSC_DB
{
    public static function posts_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'csc_posts';
    }

    public static function tokens_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'csc_tokens';
    }

    public static function install()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $posts_table     = self::posts_table();
        $tokens_table    = self::tokens_table();

        $sql_posts = "CREATE TABLE {$posts_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            platform VARCHAR(10) NOT NULL,
            payload LONGTEXT NOT NULL,
            publish_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
            attempts INT NOT NULL DEFAULT 0,
            last_error LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status_publish_at (status, publish_at)
        ) {$charset_collate};";

        $sql_tokens = "CREATE TABLE {$tokens_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            provider VARCHAR(20) NOT NULL,
            access_token LONGTEXT NOT NULL,
            refresh_token LONGTEXT NULL,
            expires_at DATETIME NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY provider (provider)
        ) {$charset_collate};";

        dbDelta($sql_posts);
        dbDelta($sql_tokens);
    }
}
