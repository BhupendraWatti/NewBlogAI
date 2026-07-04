<?php
namespace NewsBlogify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class API_Client {
    private static $instance = null;

    private function __construct() {}

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Determine if SSL verification should be enabled based on URL host.
     *
     * @param string $url Target URL.
     * @return bool True if SSL verification should be enabled, false otherwise.
     */
    private function should_verify_ssl( $url ) {
        $host = parse_url( $url, PHP_URL_HOST );
        if ( empty( $host ) ) {
            return true;
        }
        $is_local = in_array( $host, [ '127.0.0.1', 'localhost' ], true ) || 
                    str_ends_with( $host, '.local' ) || 
                    strpos( $host, '.' ) === false;
        return ! $is_local;
    }

    /**
     * Send HTTP requests to Laravel Backend API.
     */
    public function request( $endpoint, $method = 'GET', $body = null, $args = [] ) {
        $backend_url = rtrim( Config::get( 'backend_url', 'http://127.0.0.1:8000' ), '/' );
        $api_token   = Config::get( 'api_token', '' );

        $url = $backend_url . '/api/plugin/' . ltrim( $endpoint, '/' );

        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];

        if ( ! empty( $api_token ) ) {
            $headers['Authorization'] = 'Bearer ' . $api_token;
        }

        $request_args = wp_parse_args( $args, [
            'method'      => $method,
            'headers'     => $headers,
            'timeout'     => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'sslverify'   => $this->should_verify_ssl( $url ),
        ] );

        if ( null !== $body ) {
            $request_args['body'] = json_encode( $body );
        }

        Logger::get_instance()->log( 'debug', sprintf( 'Sending %s request to %s', $method, $url ) );

        $response = wp_remote_request( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            Logger::get_instance()->log( 'error', sprintf( 'Request failed: %s', $response->get_error_message() ) );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        Logger::get_instance()->log( 'debug', sprintf( 'Response status: %d', $status_code ) );

        if ( $status_code < 200 || $status_code >= 300 ) {
            $error_data = json_decode( $response_body, true );
            $error_msg = isset( $error_data['message'] ) ? $error_data['message'] : 'Server returned status ' . $status_code;
            Logger::get_instance()->log( 'error', sprintf( 'API Error status %d: %s', $status_code, $error_msg ) );
            return new \WP_Error( 'api_error', $error_msg, $status_code );
        }

        return json_decode( $response_body, true );
    }

    /**
     * Authenticate user credentials against Laravel Backend.
     */
    public function connect_account( $backend_url, $email, $password ) {
        $url = rtrim( $backend_url, '/' ) . '/api/plugin/login';

        $payload = [
            'email'    => $email,
            'password' => $password,
        ];

        $response = wp_remote_post( $url, [
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body'      => json_encode( $payload ),
            'sslverify' => $this->should_verify_ssl( $url ),
            'timeout'   => 20
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            $err_data = json_decode( $body, true );
            $msg = isset( $err_data['message'] ) ? $err_data['message'] : 'Authentication failed with status code ' . $code;
            return new \WP_Error( 'auth_failed', $msg );
        }

        return json_decode( $body, true );
    }

    /**
     * Register website details on Laravel backend.
     */
    public function register_site( $backend_url, $api_token, $site_name, $site_url, $wp_app_pwd ) {
        $url = rtrim( $backend_url, '/' ) . '/api/plugin/register-website';

        $payload = [
            'domain_url' => $site_url,
            'name'       => $site_name,
            'api_key'    => $wp_app_pwd,
            'slot'       => 'Daily',
        ];

        $response = wp_remote_post( $url, [
            'headers' => [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_token,
            ],
            'body'      => json_encode( $payload ),
            'sslverify' => $this->should_verify_ssl( $url ),
            'timeout'   => 20
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            $err_data = json_decode( $body, true );
            $msg = isset( $err_data['message'] ) ? $err_data['message'] : 'Failed to register site. Status: ' . $code;
            return new \WP_Error( 'register_failed', $msg );
        }

        return json_decode( $body, true );
    }

    /**
     * Send heartbeat to Laravel backend.
     */
    public function send_heartbeat() {
        $site_url = get_site_url();
        $payload = [
            'plugin_version' => NEWSBLOGIFY_VERSION,
            'site_url'       => $site_url,
            'php_version'    => phpversion(),
            'wp_version'     => get_bloginfo( 'version' ),
        ];

        $result = $this->request( '/heartbeat', 'POST', $payload );
        if ( ! is_wp_error( $result ) ) {
            Config::update( 'last_sync_time', current_time( 'mysql' ) );
            Logger::get_instance()->log( 'info', 'Periodic Heartbeat synced successfully.' );
        }
    }
}
