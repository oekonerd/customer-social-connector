<?php

if (!defined('ABSPATH')) {
    exit;
}

class CSC_Updater
{
    public static function init()
    {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_updates']);
        add_filter('plugins_api', [__CLASS__, 'plugins_api'], 10, 3);
        add_filter('http_request_args', [__CLASS__, 'inject_github_auth_header'], 10, 2);
    }

    public static function check_for_updates($transient)
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $plugin_file = plugin_basename(CSC_PLUGIN_FILE);
        $release     = self::get_latest_release();

        if (is_wp_error($release)) {
            return $transient;
        }

        $latest_version = self::normalize_version($release['tag_name']);
        if (!$latest_version) {
            return $transient;
        }

        if (version_compare($latest_version, CSC_VERSION, '>')) {
            $update              = new stdClass();
            $update->slug        = 'customer-social-connector';
            $update->plugin      = $plugin_file;
            $update->new_version = $latest_version;
            $update->url         = self::get_repo_url();
            $update->package     = $release['zipball_url'];

            $transient->response[$plugin_file] = $update;
        } else {
            unset($transient->response[$plugin_file]);
        }

        return $transient;
    }

    public static function plugins_api($result, $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'customer-social-connector') {
            return $result;
        }

        $release = self::get_latest_release();
        if (is_wp_error($release)) {
            return $result;
        }

        $version = self::normalize_version($release['tag_name']);
        if (!$version) {
            return $result;
        }

        $info                = new stdClass();
        $info->name          = 'Customer Social Connector';
        $info->slug          = 'customer-social-connector';
        $info->version       = $version;
        $info->author        = '<a href="https://github.com/' . esc_attr(self::get_repo()) . '">Customer Social Connector</a>';
        $info->homepage      = self::get_repo_url();
        $info->download_link = $release['zipball_url'];
        $info->sections      = [
            'description' => 'On-prem social connector plugin for scheduled posting via REST API.',
            'changelog'   => !empty($release['body']) ? wp_kses_post(wpautop($release['body'])) : 'No changelog provided.',
        ];

        return $info;
    }

    public static function inject_github_auth_header($args, $url)
    {
        $repo = self::get_repo();
        if (!$repo) {
            return $args;
        }

        $api_prefix = 'https://api.github.com/repos/' . $repo . '/';
        if (strpos($url, $api_prefix) !== 0) {
            return $args;
        }

        if (!isset($args['headers']) || !is_array($args['headers'])) {
            $args['headers'] = [];
        }

        $args['headers']['Accept'] = 'application/vnd.github+json';
        $args['headers']['User-Agent'] = 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/');

        $token = self::get_token();
        if (!empty($token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        return $args;
    }

    private static function get_latest_release()
    {
        $repo = self::get_repo();
        if (!$repo) {
            return new WP_Error('csc_updater', 'GitHub repository not configured.');
        }

        $cache_key = 'csc_release_' . md5($repo);
        $cached    = get_site_transient($cache_key);
        if (is_array($cached) && !empty($cached['tag_name']) && !empty($cached['zipball_url'])) {
            return $cached;
        }

        $response = wp_remote_get('https://api.github.com/repos/' . $repo . '/releases/latest', [
            'timeout' => 15,
            'headers' => self::build_github_headers(),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return new WP_Error('csc_updater', 'Failed to fetch latest release from GitHub.');
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['tag_name']) || empty($data['zipball_url'])) {
            return new WP_Error('csc_updater', 'Invalid GitHub release response.');
        }

        set_site_transient($cache_key, $data, 300);

        return $data;
    }

    private static function build_github_headers()
    {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
        ];

        $token = self::get_token();
        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private static function normalize_version($tag)
    {
        $version = ltrim((string) $tag, 'vV');

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return null;
        }

        return $version;
    }

    private static function get_settings()
    {
        $settings = get_option(CSC_Admin::OPTION_NAME, []);
        return is_array($settings) ? $settings : [];
    }

    private static function get_token()
    {
        $settings = self::get_settings();
        return isset($settings['csc_github_token']) ? trim((string) $settings['csc_github_token']) : '';
    }

    private static function get_repo()
    {
        $settings = self::get_settings();
        $repo     = isset($settings['csc_github_repo']) ? trim((string) $settings['csc_github_repo']) : '';

        if ($repo === '') {
            $repo = 'oekonerd/customer-social-connector';
        }

        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
            return '';
        }

        return $repo;
    }

    private static function get_repo_url()
    {
        return 'https://github.com/' . self::get_repo();
    }
}
