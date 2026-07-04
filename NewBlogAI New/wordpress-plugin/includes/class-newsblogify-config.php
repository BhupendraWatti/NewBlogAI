<?php
namespace NewsBlogify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Config {
    private static $option_name = 'newsblogify_settings';

    /**
     * Retrieve a setting by key.
     *
     * @param string $key Settings key.
     * @param mixed  $default Default value if setting not found.
     * @return mixed Setting value.
     */
    public static function get( $key, $default = '' ) {
        $settings = get_option( self::$option_name, [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Update/insert a setting by key.
     *
     * @param string $key Settings key.
     * @param mixed  $value Setting value.
     * @return bool True if value changed, false otherwise.
     */
    public static function update( $key, $value ) {
        $settings = get_option( self::$option_name, [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }
        $settings[ $key ] = $value;
        return update_option( self::$option_name, $settings );
    }

    /**
     * Delete a setting key.
     *
     * @param string $key Settings key.
     * @return bool True if key existed and was deleted, false otherwise.
     */
    public static function delete( $key ) {
        $settings = get_option( self::$option_name, [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }
        if ( isset( $settings[ $key ] ) ) {
            unset( $settings[ $key ] );
            return update_option( self::$option_name, $settings );
        }
        return false;
    }

    /**
     * Clear all settings from the database.
     *
     * @return bool True if option deleted successfully, false otherwise.
     */
    public static function clear() {
        return delete_option( self::$option_name );
    }
}
