<?php
/**
 * Plugin Name: Customer Social Connector
 * Description: On-prem social connector plugin for scheduled posting via REST API.
 * Version: 0.1.1
 * Author: Customer Social Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CSC_VERSION', '0.1.1');
define('CSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CSC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CSC_PLUGIN_DIR . 'includes/class-db.php';
require_once CSC_PLUGIN_DIR . 'includes/class-auth.php';
require_once CSC_PLUGIN_DIR . 'includes/class-meta.php';
require_once CSC_PLUGIN_DIR . 'includes/class-admin.php';
require_once CSC_PLUGIN_DIR . 'includes/class-rest.php';
require_once CSC_PLUGIN_DIR . 'includes/class-scheduler.php';

function csc_add_cron_schedules($schedules)
{
    $schedules['csc_minute'] = [
        'interval' => 60,
        'display'  => __('Every Minute (CSC)', 'customer-social-connector'),
    ];

    return $schedules;
}
add_filter('cron_schedules', 'csc_add_cron_schedules');

function csc_activate_plugin()
{
    CSC_DB::install();

    if (!wp_next_scheduled('csc_cron_tick')) {
        wp_schedule_event(time(), 'csc_minute', 'csc_cron_tick');
    }
}
register_activation_hook(__FILE__, 'csc_activate_plugin');

function csc_deactivate_plugin()
{
    $timestamp = wp_next_scheduled('csc_cron_tick');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'csc_cron_tick');
    }
}
register_deactivation_hook(__FILE__, 'csc_deactivate_plugin');

function csc_init_plugin_components()
{
    CSC_Admin::init();
    CSC_REST::init();
    CSC_Scheduler::init();
}
add_action('plugins_loaded', 'csc_init_plugin_components');
