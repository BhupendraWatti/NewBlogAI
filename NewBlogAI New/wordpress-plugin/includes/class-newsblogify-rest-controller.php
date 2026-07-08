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

        register_rest_route('newsblogify/v1', '/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_publish'],
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
            return false;
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
     * Callback for POST /wp-json/newsblogify/v1/publish.
     */
    public function handle_publish(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_params();
        }

        $publishing_log_id = isset($params['publishing_log_id']) ? $params['publishing_log_id'] : null;
        $title             = isset($params['title']) ? $params['title'] : '';
        $content           = isset($params['content']) ? $params['content'] : '';
        $status            = isset($params['status']) ? $params['status'] : 'draft';
        $categories        = isset($params['categories']) ? $params['categories'] : [];
        $tags              = isset($params['tags']) ? $params['tags'] : [];
        $featured_image_url = isset($params['featured_image_url']) ? $params['featured_image_url'] : null;
        $meta              = isset($params['meta']) ? $params['meta'] : [];
        $slug              = isset($params['slug']) ? $params['slug'] : '';
        $scheduled_at      = isset($params['scheduled_at']) ? $params['scheduled_at'] : null;

        Logger::get_instance()->log('info', 'Received publish request for log ID: ' . $publishing_log_id);

        // Idempotency Check: check if a post with meta key `_newsblogify_publishing_log_id` matching `publishing_log_id` already exists.
        if (! empty($publishing_log_id)) {
            $existing_posts = get_posts([
                'post_type'      => 'any',
                'post_status'    => 'any',
                'meta_key'       => '_newsblogify_publishing_log_id',
                'meta_value'     => $publishing_log_id,
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ]);

            if (! empty($existing_posts)) {
                $post_id = $existing_posts[0];
                Logger::get_instance()->log('info', 'Idempotency match found. Post ID: ' . $post_id);
                return new \WP_REST_Response([
                    'status'     => 'success',
                    'wp_post_id' => $post_id,
                    'post_url'   => get_permalink($post_id),
                ], 200);
            }
        }

        // Insert/Update Post:
        // Construct $post_data: post_title => title, post_content => content, post_status => status, post_name => slug.
        $post_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_name'    => $slug,
        ];

        // If status is future and scheduled_at is provided, set post_date to scheduled_at (formatted as Y-m-d H:i:s in WordPress site timezone).
        if ($status === 'future' && ! empty($scheduled_at)) {
            $timestamp = strtotime($scheduled_at);
            if ($timestamp) {
                $gmt_date = gmdate('Y-m-d H:i:s', $timestamp);
                $post_data['post_date'] = get_date_from_gmt($gmt_date, 'Y-m-d H:i:s');
                $post_data['post_date_gmt'] = $gmt_date;
            }
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            Logger::get_instance()->log('error', 'Failed to insert post: ' . $post_id->get_error_message());
            return new \WP_REST_Response([
                'status'  => 'error',
                'message' => $post_id->get_error_message(),
            ], 500);
        }

        // Store meta key _newsblogify_publishing_log_id on the post.
        if (! empty($publishing_log_id)) {
            update_post_meta($post_id, '_newsblogify_publishing_log_id', $publishing_log_id);
        }

        // Taxonomies:
        // Categories: For each category (which could be an ID or a string name), resolve it.
        if (! empty($categories) && is_array($categories)) {
            $cat_ids = [];
            foreach ($categories as $cat) {
                if (is_numeric($cat)) {
                    $cat_ids[] = (int) $cat;
                } elseif (is_string($cat) && trim($cat) !== '') {
                    $cat = trim($cat);
                    $cat_id = get_cat_ID($cat);
                    if ($cat_id === 0) {
                        $cat_id = wp_create_category($cat);
                    }
                    if ($cat_id && ! is_wp_error($cat_id)) {
                        $cat_ids[] = (int) $cat_id;
                    }
                }
            }
            if (! empty($cat_ids)) {
                wp_set_post_categories($post_id, $cat_ids);
            }
        }

        // Tags: Call wp_set_post_tags($post_id, $tags).
        if (! empty($tags)) {
            wp_set_post_tags($post_id, $tags);
        }

        // Featured Image Sideloading:
        if (! empty($featured_image_url)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $desc = ! empty($title) ? $title : '';
            $attachment_id = media_sideload_image($featured_image_url, $post_id, $desc, 'id');
            if (! is_wp_error($attachment_id) && is_numeric($attachment_id)) {
                set_post_thumbnail($post_id, (int) $attachment_id);
            } else {
                if (is_wp_error($attachment_id)) {
                    Logger::get_instance()->log('warning', 'Failed to sideload featured image: ' . $attachment_id->get_error_message());
                }
            }
        }

        // SEO & Meta:
        if (! empty($meta) && is_array($meta)) {
            foreach ($meta as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
        }

        Logger::get_instance()->log('info', 'Successfully published post. ID: ' . $post_id);

        return new \WP_REST_Response([
            'status'     => 'success',
            'wp_post_id' => $post_id,
            'post_url'   => get_permalink($post_id),
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
