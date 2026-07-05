<?php

namespace NewsBlogify;

if (! defined('ABSPATH')) {
    exit;
}

class REST_Controller
{
    public static function register()
    {
        $instance = new self;
        add_action('rest_api_init', [$instance, 'register_routes']);
        add_filter('determine_current_user', [$instance, 'authenticate_bearer_token'], 15);
    }

    /**
     * Register target endpoints under namespace newsblogify/v1.
     */
    public function register_routes()
    {
        register_rest_route('newsblogify/v1', '/ping', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ping'],
            'permission_callback' => [$this, 'verify_api_key'],
        ]);

        register_rest_route('newsblogify/v1', '/sync-data', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_sync'],
            'permission_callback' => [$this, 'verify_api_key'],
        ]);
    }

    /**
     * Authenticate incoming calls from Laravel using the stored API Token.
     */
    public function verify_api_key(\WP_REST_Request $request)
    {
        $auth_header = $request->get_header('Authorization');
        $token = '';

        if (! empty($auth_header) && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = trim($matches[1]);
        }

        if (empty($token)) {
            $token = $request->get_param('api_key');
        }

        $stored_token = Config::get('plugin_token', '');

        if (empty($stored_token)) {
            return true;
        }

        if (empty($token) || hash_equals($stored_token, $token) === false) {
            return current_user_can('manage_options');
        }

        return true;
    }

    /**
     * Callback for POST /wp-json/newsblogify/v1/ping.
     */
    public function handle_ping(\WP_REST_Request $request)
    {
        Logger::get_instance()->log('info', 'Received ping from Laravel backend.');

        return new \WP_REST_Response([
            'status' => 'success',
            'version' => NEWSBLOGIFY_VERSION,
            'message' => 'Connection validated successfully!',
        ], 200);
    }

    /**
     * Callback for POST /wp-json/newsblogify/v1/sync-data.
     */
    public function handle_sync(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        Logger::get_instance()->log('info', 'Received synchronization data payload from Laravel.');

        $selected_topics = isset($params['selected_topics']) ? $params['selected_topics'] : [];
        $slot = isset($params['slot']) ? $params['slot'] : 'Daily';

        Config::update('selected_topics', $selected_topics);
        Config::update('posting_slot', $slot);
        Config::update('last_sync_time', current_time('mysql'));

        Logger::get_instance()->log('info', sprintf('Sync success: configured slot %s with %d topic clusters.', $slot, count($selected_topics)));

        return new \WP_REST_Response([
            'status' => 'success',
            'message' => 'WordPress settings sync completed successfully.',
        ], 200);
    }

    /**
     * Authenticate incoming REST API requests using the Bearer token.
     *
     * @param  int|false  $user_id  Current user ID resolved by WordPress.
     * @return int|false Authenticated user ID, or false if not matched.
     */
    public function authenticate_bearer_token($user_id)
    {
        if (! empty($user_id)) {
            return $user_id;
        }

        $auth_header = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $auth_header = $headers['Authorization'];
            }
        }

        $token = '';
        if (! empty($auth_header) && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = trim($matches[1]);
        }

        if (empty($token) && isset($_REQUEST['api_key'])) {
            $token = sanitize_text_field($_REQUEST['api_key']);
        }

        if (empty($token)) {
            return $user_id;
        }

        $stored_app_pwd = Config::get('wp_app_pwd', '');
        $stored_api_token = Config::get('plugin_token', '');
        $mapped_user_id = Config::get('wp_user_id', 0);

        if (empty($mapped_user_id)) {
            return $user_id;
        }

        if ((! empty($stored_app_pwd) && hash_equals($stored_app_pwd, hash('sha256', $token))) ||
             (! empty($stored_api_token) && hash_equals($stored_api_token, $token))) {

            Logger::get_instance()->log('debug', 'Request successfully authenticated via Bearer token.');

            return (int) $mapped_user_id;
        }

        return $user_id;
    }
}
