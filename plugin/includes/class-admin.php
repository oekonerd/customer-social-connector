<?php

if (!defined('ABSPATH')) {
    exit;
}

class CSC_Admin
{
    const OPTION_GROUP = 'csc_settings_group';
    const OPTION_NAME  = 'csc_settings';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function add_menu()
    {
        add_options_page(
            'Social Connector',
            'Social Connector',
            'manage_options',
            'csc-social-connector',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings()
    {
        register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
            'default'           => [
                'csc_meta_app_id'      => '',
                'csc_meta_app_secret'  => '',
                'csc_meta_api_version' => 'v19.0',
                'csc_encryption_key'   => '',
                'csc_github_repo'      => 'oekonerd/customer-social-connector',
                'csc_github_token'     => '',
            ],
        ]);

        add_settings_section(
            'csc_main_section',
            'Meta Configuration',
            '__return_false',
            'csc-social-connector'
        );

        self::add_field('csc_meta_app_id', 'Meta App ID');
        self::add_field('csc_meta_app_secret', 'Meta App Secret', 'password');
        self::add_field('csc_meta_api_version', 'Meta API Version');
        self::add_field('csc_encryption_key', 'Encryption Key (optional)', 'password');
        self::add_field('csc_github_repo', 'GitHub Repository (owner/repo)');
        self::add_field('csc_github_token', 'GitHub Token (for updates)', 'password');
    }

    private static function add_field($field_key, $label, $type = 'text')
    {
        add_settings_field(
            $field_key,
            $label,
            [__CLASS__, 'render_field'],
            'csc-social-connector',
            'csc_main_section',
            [
                'key'  => $field_key,
                'type' => $type,
            ]
        );
    }

    public static function sanitize_settings($input)
    {
        $output = [];
        $output['csc_meta_app_id']      = isset($input['csc_meta_app_id']) ? sanitize_text_field($input['csc_meta_app_id']) : '';
        $output['csc_meta_app_secret']  = isset($input['csc_meta_app_secret']) ? sanitize_text_field($input['csc_meta_app_secret']) : '';
        $output['csc_meta_api_version'] = isset($input['csc_meta_api_version']) ? sanitize_text_field($input['csc_meta_api_version']) : 'v19.0';
        $output['csc_encryption_key']   = isset($input['csc_encryption_key']) ? sanitize_text_field($input['csc_encryption_key']) : '';
        $output['csc_github_token']     = isset($input['csc_github_token']) ? sanitize_text_field($input['csc_github_token']) : '';
        $output['csc_github_repo']      = isset($input['csc_github_repo']) ? sanitize_text_field($input['csc_github_repo']) : 'oekonerd/customer-social-connector';

        if (empty($output['csc_meta_api_version'])) {
            $output['csc_meta_api_version'] = 'v19.0';
        }

        if (empty($output['csc_github_repo']) || !preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $output['csc_github_repo'])) {
            $output['csc_github_repo'] = 'oekonerd/customer-social-connector';
        }

        return $output;
    }

    public static function render_field($args)
    {
        $settings = get_option(self::OPTION_NAME, []);
        $key      = $args['key'];
        $type     = $args['type'];
        $value    = isset($settings[$key]) ? (string) $settings[$key] : '';

        printf(
            '<input type="%s" name="%s[%s]" value="%s" class="regular-text" autocomplete="off" />',
            esc_attr($type),
            esc_attr(self::OPTION_NAME),
            esc_attr($key),
            esc_attr($value)
        );
    }

    public static function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $rest_base = rest_url('csc/v1');
        ?>
        <div class="wrap csc-admin">
            <h1>Social Connector</h1>
            <p><strong>REST Base URL:</strong> <code><?php echo esc_html($rest_base); ?></code></p>
            <p>REST-Auth via HTTP Basic Auth: username:application_password</p>
            <p>Plugin updates can be fetched from GitHub Releases. For private repositories, provide a GitHub token below.</p>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections('csc-social-connector');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function enqueue_assets($hook)
    {
        if ($hook !== 'settings_page_csc-social-connector') {
            return;
        }

        wp_enqueue_style('csc-admin', CSC_PLUGIN_URL . 'assets/admin.css', [], CSC_VERSION);
        wp_enqueue_script('csc-admin', CSC_PLUGIN_URL . 'assets/admin.js', ['jquery'], CSC_VERSION, true);
    }
}
