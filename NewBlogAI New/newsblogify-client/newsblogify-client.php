<?php

use NewsBlogify\Admin;
use NewsBlogify\Cron;
use NewsBlogify\Logger;
use NewsBlogify\REST_Controller;

/**
 * Plugin Name:       NewsBlogify Client
 * Plugin URI:        https://newsblogify.com
 * Description:       Lightweight WordPress execution agent for the NewsBlogify SaaS platform.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            NewsBlogify Team
 * Author URI:        https://newsblogify.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       newsblogify-client
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

if (! defined('NEWSBLOGIFY_VERSION')) {
    define('NEWSBLOGIFY_VERSION', '2.0.0');
}

if (! defined('NEWSBLOGIFY_PATH')) {
    define('NEWSBLOGIFY_PATH', plugin_dir_path(__FILE__));
}

if (! defined('NEWSBLOGIFY_URL')) {
    define('NEWSBLOGIFY_URL', plugin_dir_url(__FILE__));
}

if (! defined('NEWSBLOGIFY_BASENAME')) {
    define('NEWSBLOGIFY_BASENAME', plugin_basename(__FILE__));
}

// ---------------------------------------------------------------------------
// Autoloader
// ---------------------------------------------------------------------------

/**
 * PSR-4-style autoloader for the NewsBlogify namespace.
 *
 * Maps the NewsBlogify namespace to the includes/ folder using the naming
 * convention: class-newsblogify-{classname-lowercased-with-dashes}.php
 *
 * Example:
 *   NewsBlogify\Logger          -> includes/class-newsblogify-logger.php
 *   NewsBlogify\REST_Controller -> includes/class-newsblogify-rest-controller.php
 *   NewsBlogify\API_Client      -> includes/class-newsblogify-api-client.php
 *
 * @param  string  $class  Fully-qualified class name.
 * @return void
 */
spl_autoload_register(function (string $class): void {
    // Only handle the NewsBlogify namespace.
    $prefix = 'NewsBlogify\\';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Extract the bare class name after the namespace prefix.
    $relative_class = substr($class, $len);

    /*
     * Convert the class name to the WordPress file-naming convention:
     *   1. Replace underscores and backslashes with hyphens.
     *   2. Lowercase the whole string.
     *   e.g. REST_Controller -> rest-controller
     *        API_Client       -> api-client
     */
    $slug = strtolower(str_replace(['_', '\\'], '-', $relative_class));
    $filename = NEWSBLOGIFY_PATH.'includes/class-newsblogify-'.$slug.'.php';

    if (file_exists($filename)) {
        require_once $filename;
    }
});

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

/**
 * Boots the NewsBlogify Client plugin.
 *
 * Hooked onto plugins_loaded so that all plugins are available and
 * WordPress translations are ready.
 */
function run_newsblogify_client(): void
{
    // Load plugin translations.
    load_plugin_textdomain(
        'newsblogify-client',
        false,
        dirname(NEWSBLOGIFY_BASENAME).'/languages'
    );

    // Initialise the logger singleton first so every subsequent component can log.
    Logger::get_instance();

    // Register the REST API endpoints.
    REST_Controller::register();

    // Initialise the Cron scheduler singleton.
    Cron::get_instance();

    // Initialise the Admin UI only on the back-end.
    if (is_admin()) {
        Admin::get_instance();
    }
}
add_action('plugins_loaded', 'run_newsblogify_client');

// ---------------------------------------------------------------------------
// Activation hook
// ---------------------------------------------------------------------------

/**
 * Runs on plugin activation.
 *
 * - Schedules all Cron events.
 * - Migrates legacy settings to the v2 schema.
 * - Logs the activation event.
 */
function newsblogify_activate(): void
{
    // Schedule cron events (requires the autoloader to be active).
    Cron::get_instance()->schedule_events();

    // Migrate any pre-2.0 settings.
    migrate_legacy_settings();

    // Log the activation.
    Logger::get_instance()->log(
        Logger::LOG_INFO,
        sprintf(
            'NewsBlogify Client v%s activated on site: %s',
            NEWSBLOGIFY_VERSION,
            get_site_url()
        )
    );
}
register_activation_hook(__FILE__, 'newsblogify_activate');

// ---------------------------------------------------------------------------
// Deactivation hook
// ---------------------------------------------------------------------------

/**
 * Runs on plugin deactivation.
 *
 * - Clears all scheduled Cron events.
 * - Logs the deactivation event.
 */
function newsblogify_deactivate(): void
{
    // Unschedule all plugin cron events.
    Cron::get_instance()->clear_events();

    // Log the deactivation.
    Logger::get_instance()->log(
        Logger::LOG_INFO,
        sprintf(
            'NewsBlogify Client v%s deactivated on site: %s',
            NEWSBLOGIFY_VERSION,
            get_site_url()
        )
    );
}
register_deactivation_hook(__FILE__, 'newsblogify_deactivate');

// ---------------------------------------------------------------------------
// Legacy settings migration
// ---------------------------------------------------------------------------

/**
 * Migrates legacy (pre-2.0) plugin settings to the v2 schema.
 *
 * Key mapping:
 *   api_token       -> plugin_token
 *   posting_slot    -> publishing_mode
 *   selected_topics -> synced_topics
 *
 * All other keys are preserved unchanged.
 */
function migrate_legacy_settings(): void
{
    $option_name = 'newsblogify_settings';
    $settings = get_option($option_name, []);

    // Nothing to migrate if the option does not exist.
    if (empty($settings) || ! is_array($settings)) {
        return;
    }

    $migrated = false;

    // api_token -> plugin_token
    if (isset($settings['api_token']) && ! isset($settings['plugin_token'])) {
        $settings['plugin_token'] = $settings['api_token'];
        unset($settings['api_token']);
        $migrated = true;
    }

    // posting_slot -> publishing_mode
    if (isset($settings['posting_slot']) && ! isset($settings['publishing_mode'])) {
        $settings['publishing_mode'] = $settings['posting_slot'];
        unset($settings['posting_slot']);
        $migrated = true;
    }

    // selected_topics -> synced_topics
    if (isset($settings['selected_topics']) && ! isset($settings['synced_topics'])) {
        $settings['synced_topics'] = $settings['selected_topics'];
        unset($settings['selected_topics']);
        $migrated = true;
    }

    if ($migrated) {
        update_option($option_name, $settings);

        // Log if the logger is already available (activation flow loads it first).
        if (class_exists('\NewsBlogify\Logger')) {
            Logger::get_instance()->log(
                Logger::LOG_INFO,
                'Legacy settings migrated to NewsBlogify v2 schema successfully.'
            );
        }
    }
}
