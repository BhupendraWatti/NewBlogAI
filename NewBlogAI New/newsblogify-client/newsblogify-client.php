<?php

use NewsBlogify\Admin;
use NewsBlogify\Cron;
use NewsBlogify\Logger;
use NewsBlogify\REST_Controller;

/*
Plugin Name: NewsBlogify Integration Client
Plugin URI: https://newsblogify.com
Description: Connects your WordPress site to the NewsBlogify platform for automated SEO article generation, publishing, and scheduling.
Version: 1.0.0
Author: NewsBlogify Team
Author URI: https://newsblogify.com
License: GPL2
Text Domain: newsblogify-client
Domain Path: /languages
*/

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Constants
define('NEWSBLOGIFY_VERSION', '1.0.0');
define('NEWSBLOGIFY_PATH', plugin_dir_path(__FILE__));
define('NEWSBLOGIFY_URL', plugin_dir_url(__FILE__));
define('NEWSBLOGIFY_BASENAME', plugin_basename(__FILE__));

// Autoloader for Plugin Classes
spl_autoload_register(function ($class) {
    $prefix = 'NewsBlogify\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $parts = explode('\\', $relative_class);

    $filename = 'class-newsblogify-'.strtolower(str_replace('_', '-', array_pop($parts))).'.php';

    $subpath = '';
    if (! empty($parts)) {
        $subpath = implode('/', array_map('strtolower', $parts)).'/';
    }

    $file = NEWSBLOGIFY_PATH.'includes/'.$subpath.$filename;

    if (file_exists($file)) {
        require_once $file;
    }
});

// Bootstrap the Plugin
function run_newsblogify_client()
{
    // Load text domain for translation support
    load_plugin_textdomain('newsblogify-client', false, dirname(NEWSBLOGIFY_BASENAME).'/languages');

    // Initialize Logger
    $logger = Logger::get_instance();
    $logger->log('info', 'Plugin loaded.');

    // Initialize REST Controller
    REST_Controller::register();

    // Initialize WP Cron scheduler
    Cron::get_instance();

    // Initialize Admin Dashboard UI and Setup Wizard
    if (is_admin()) {
        Admin::get_instance();
    }
}

// Activation hook
register_activation_hook(__FILE__, function () {
    Cron::get_instance()->schedule_events();
    Logger::get_instance()->log('info', 'Plugin activated.');
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    Cron::get_instance()->clear_events();
    Logger::get_instance()->log('info', 'Plugin deactivated.');
});

// Start Execution
add_action('plugins_loaded', 'run_newsblogify_client');
