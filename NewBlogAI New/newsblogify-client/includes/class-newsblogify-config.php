<?php

/**
 * Config – Centralized settings store for the NewsBlogify Client plugin.
 *
 * Provides a thin static wrapper around a single WordPress option so that
 * every other component can read and write plugin settings through a
 * consistent API without having to know the underlying option name.
 *
 * @since   2.0.0
 */

namespace NewsBlogify;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Config
 *
 * All plugin settings are stored in the WordPress options table under the
 * option name defined by OPTION_NAME.  Each individual setting is a key
 * inside that serialised array.
 *
 * Usage examples:
 *   Config::get( 'plugin_token' );
 *   Config::update( 'connection_status', 'connected' );
 *   Config::update_many( [ 'site_id' => '123', 'wizard_step' => 3 ] );
 *   Config::is_connected();
 *
 * @since   2.0.0
 */
class Config
{
    // ---------------------------------------------------------------------------
    // Constants
    // ---------------------------------------------------------------------------

    /**
     * The WordPress option name under which all plugin settings are stored.
     */
    const OPTION_NAME = 'newsblogify_settings';

    // ---------------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------------

    /**
     * Retrieves the full settings array from the database.
     *
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        $settings = get_option(self::OPTION_NAME, []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Persists the full settings array to the database.
     *
     * @param  array<string, mixed>  $settings  The settings array to save.
     * @return bool True if the option was updated, false otherwise.
     */
    private static function save(array $settings): bool
    {
        return update_option(self::OPTION_NAME, $settings);
    }

    // ---------------------------------------------------------------------------
    // Public CRUD API
    // ---------------------------------------------------------------------------

    /**
     * Retrieves a single setting value.
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $default  Value to return when the key does not exist.
     * @return mixed The stored value or $default.
     */
    public static function get(string $key, $default = '')
    {
        $settings = self::load();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * Updates a single setting value.
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $value  The new value.
     * @return bool True on success.
     */
    public static function update(string $key, $value): bool
    {
        $settings = self::load();
        $settings[$key] = $value;

        return self::save($settings);
    }

    /**
     * Updates multiple settings keys atomically in a single database write.
     *
     * @param  array<string, mixed>  $data  Associative array of key => value pairs.
     * @return bool True on success.
     */
    public static function update_many(array $data): bool
    {
        $settings = self::load();
        foreach ($data as $key => $value) {
            $settings[$key] = $value;
        }

        return self::save($settings);
    }

    /**
     * Removes a single setting key from the stored options.
     *
     * @param  string  $key  The setting key to remove.
     * @return bool True on success.
     */
    public static function delete(string $key): bool
    {
        $settings = self::load();
        if (! array_key_exists($key, $settings)) {
            return true; // Nothing to delete.
        }
        unset($settings[$key]);

        return self::save($settings);
    }

    /**
     * Removes ALL plugin settings from the database.
     *
     * Use with caution – this is irreversible.
     *
     * @return bool True on success.
     */
    public static function clear(): bool
    {
        return delete_option(self::OPTION_NAME);
    }

    /**
     * Returns the full settings array.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::load();
    }

    // ---------------------------------------------------------------------------
    // Specialised read methods
    // ---------------------------------------------------------------------------

    /**
     * Returns a structured snapshot of all connection-related settings.
     *
     * This is the canonical set of keys that describe how the plugin is
     * connected to the NewsBlogify backend.
     *
     * @return array{
     *     backend_url: string,
     *     plugin_token: string,
     *     account_email: string,
     *     site_id: string,
     *     site_url: string,
     *     wizard_step: int,
     *     connection_status: string,
     *     config_version: int,
     *     config_hash: string
     * }
     */
    public static function get_connection_data(): array
    {
        $settings = self::load();

        return [
            'backend_url' => $settings['backend_url'] ?? '',
            'plugin_token' => $settings['plugin_token'] ?? '',
            'account_email' => $settings['account_email'] ?? '',
            'site_id' => $settings['site_id'] ?? '',
            'site_url' => $settings['site_url'] ?? get_site_url(),
            'wizard_step' => (int) ($settings['wizard_step'] ?? 0),
            'connection_status' => $settings['connection_status'] ?? 'disconnected',
            'config_version' => (int) ($settings['config_version'] ?? 0),
            'config_hash' => $settings['config_hash'] ?? '',
        ];
    }

    /**
     * Checks whether the plugin is currently connected to the backend.
     *
     * Returns true only when ALL three conditions are met:
     *   1. connection_status is exactly 'connected'.
     *   2. plugin_token is non-empty.
     *   3. site_id is non-empty.
     */
    public static function is_connected(): bool
    {
        $settings = self::load();

        return
            ($settings['connection_status'] ?? '') === 'connected'
            && ! empty($settings['plugin_token'])
            && ! empty($settings['site_id']);
    }
}
