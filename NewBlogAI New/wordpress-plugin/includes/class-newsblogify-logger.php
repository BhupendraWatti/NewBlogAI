<?php
namespace NewsBlogify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {
    private static $instance = null;
    private $log_file;

    private function __construct() {
        $upload_dir = wp_upload_dir();
        $secure_dir = $upload_dir['basedir'] . '/newsblogify-secure-logs';
        
        if ( ! file_exists( $secure_dir ) ) {
            wp_mkdir_p( $secure_dir );
        }
        
        if ( file_exists( $secure_dir ) ) {
            $htaccess_file = $secure_dir . '/.htaccess';
            if ( ! file_exists( $htaccess_file ) ) {
                @file_put_contents( $htaccess_file, "Order deny,allow\nDeny from all" );
            }
            $index_file = $secure_dir . '/index.php';
            if ( ! file_exists( $index_file ) ) {
                @file_put_contents( $index_file, "<?php // Silence" );
            }
        }

        $this->log_file = $secure_dir . '/activity.log';
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log a message with a specific severity level.
     */
    public function log( $level, $message ) {
        $debug_mode = Config::get( 'debug_mode', '0' );
        if ( 'debug' === $level && ! $debug_mode ) {
            return;
        }

        $timestamp = current_time( 'mysql' );
        $log_entry = sprintf( "[%s] [%s]: %s\n", $timestamp, strtoupper( $level ), $message );
        
        error_log( $log_entry, 3, $this->log_file );
    }

    /**
     * Get the absolute path to the log file.
     */
    public function get_log_file_path() {
        return $this->log_file;
    }

    /**
     * Retrieve the last N lines of the log.
     */
    public function get_logs( $lines = 100 ) {
        if ( ! file_exists( $this->log_file ) ) {
            return __( 'No log entries found.', 'newsblogify-client' );
        }

        $file = fopen( $this->log_file, 'r' );
        if ( ! $file ) {
            return __( 'Could not read log file.', 'newsblogify-client' );
        }

        $buffer = [];
        while ( ! feof( $file ) ) {
            $line = fgets( $file );
            if ( $line ) {
                $buffer[] = $line;
            }
        }
        fclose( $file );

        $output_lines = array_slice( $buffer, - $lines );
        return implode( '', $output_lines );
    }

    /**
     * Empty/clear the log file contents.
     */
    public function clear_logs() {
        if ( file_exists( $this->log_file ) ) {
            unlink( $this->log_file );
        }
        $this->log( 'info', 'Logs cleared by administrator.' );
    }
}
