<?php

if (!defined('ABSPATH')) {
    exit;
}

class CSC_Auth
{
    public static function permission_callback($request)
    {
        unset($request);

        $header = self::get_authorization_header();
        if (!$header || stripos($header, 'Basic ') !== 0) {
            return new WP_Error('csc_auth', 'Unauthorized', ['status' => 401]);
        }

        $encoded = trim(substr($header, 6));
        $decoded = base64_decode($encoded, true);

        if (!$decoded || strpos($decoded, ':') === false) {
            return new WP_Error('csc_auth', 'Unauthorized', ['status' => 401]);
        }

        list($username, $app_password) = explode(':', $decoded, 2);

        if (!function_exists('wp_authenticate_application_password')) {
            return new WP_Error('csc_auth', 'Unauthorized', ['status' => 401]);
        }

        $user = wp_authenticate_application_password(null, $username, $app_password);
        if (is_wp_error($user) || !$user instanceof WP_User) {
            return new WP_Error('csc_auth', 'Unauthorized', ['status' => 401]);
        }

        wp_set_current_user($user->ID);

        if (!current_user_can('manage_options')) {
            return new WP_Error('csc_auth', 'Unauthorized', ['status' => 401]);
        }

        return true;
    }

    private static function get_authorization_header()
    {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
        }

        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (!empty($headers['Authorization'])) {
                return sanitize_text_field($headers['Authorization']);
            }
            if (!empty($headers['authorization'])) {
                return sanitize_text_field($headers['authorization']);
            }
        }

        return null;
    }
}
