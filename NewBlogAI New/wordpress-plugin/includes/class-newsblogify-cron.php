<?php
namespace NewsBlogify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cron {
    private static $instance = null;

    private function __construct() {
        add_action( 'newsblogify_heartbeat_cron', [ $this, 'run_heartbeat' ] );
        add_action( 'newsblogify_sync_cron', [ $this, 'run_configuration_sync' ] );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Schedule the recurring background cron tasks.
     */
    public function schedule_events() {
        if ( ! wp_next_scheduled( 'newsblogify_heartbeat_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'newsblogify_heartbeat_cron' );
        }
        if ( ! wp_next_scheduled( 'newsblogify_sync_cron' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'newsblogify_sync_cron' );
        }
    }

    /**
     * Clear scheduled events on deactivation.
     */
    public function clear_events() {
        $timestamp_heartbeat = wp_next_scheduled( 'newsblogify_heartbeat_cron' );
        if ( $timestamp_heartbeat ) {
            wp_unschedule_event( $timestamp_heartbeat, 'newsblogify_heartbeat_cron' );
        }

        $timestamp_sync = wp_next_scheduled( 'newsblogify_sync_cron' );
        if ( $timestamp_sync ) {
            wp_unschedule_event( $timestamp_sync, 'newsblogify_sync_cron' );
        }
    }

    /**
     * Trigger API heartbeat request.
     */
    public function run_heartbeat() {
        Logger::get_instance()->log( 'info', 'Executing background heartbeat check.' );
        API_Client::get_instance()->send_heartbeat();
    }

    /**
     * Pull latest configurations from Laravel.
     */
    public function run_configuration_sync() {
        Logger::get_instance()->log( 'info', 'Starting background configuration sync.' );

        $site_url = get_site_url();
        $response = API_Client::get_instance()->request( '/configuration?site_url=' . urlencode( $site_url ) );
        if ( is_wp_error( $response ) ) {
            Logger::get_instance()->log( 'error', 'Background configuration sync failed: ' . $response->get_error_message() );
            return;
        }

        $data = isset( $response['data'] ) ? $response['data'] : $response;

        if ( isset( $data['selected_topics'] ) ) {
            Config::update( 'selected_topics', $data['selected_topics'] );
        }
        if ( isset( $data['slot'] ) ) {
            Config::update( 'posting_slot', $data['slot'] );
        }
        
        Config::update( 'last_sync_time', current_time( 'mysql' ) );
        Logger::get_instance()->log( 'info', 'Background configuration sync succeeded.' );
    }
}
