<?php

namespace NewsBlogify;

if (! defined('ABSPATH')) {
    exit;
}

class Admin
{
    private static $instance = null;

    private function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_init', [$this, 'handle_form_submissions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Enqueue standard assets.
     */
    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'newsblogify') === false) {
            return;
        }

        wp_enqueue_style('newsblogify-admin-css', NEWSBLOGIFY_URL.'assets/css/admin.css', [], NEWSBLOGIFY_VERSION);
        wp_enqueue_script('newsblogify-admin-js', NEWSBLOGIFY_URL.'assets/js/admin.js', ['jquery'], NEWSBLOGIFY_VERSION, false);
    }

    /**
     * Register target menu in WP Admin sidebar.
     */
    public function register_admin_pages()
    {
        add_menu_page(
            __('NewsBlogify', 'newsblogify-client'),
            __('NewsBlogify', 'newsblogify-client'),
            'manage_options',
            'newsblogify',
            [$this, 'render_dashboard'],
            'dashicons-admin-network',
            80
        );
    }

    private function validate_local_app_password($username, $password)
    {
        $user = get_user_by('login', $username);
        if (! $user) {
            $user = get_user_by('email', $username);
        }

        if (! $user) {
            return new \WP_Error('invalid_username', __('WordPress username not found.', 'newsblogify-client'));
        }

        if (! class_exists('WP_Application_Passwords')) {
            return new \WP_Error('disabled_app_passwords', __('Application passwords are not supported or active on this site.', 'newsblogify-client'));
        }

        $passwords = \WP_Application_Passwords::get_user_application_passwords($user->ID);
        $validated = false;

        foreach ($passwords as $app_password) {
            if (wp_check_password($password, $app_password['password'], $user->ID)) {
                $validated = true;
                break;
            }
        }

        if (! $validated) {
            if (wp_check_password($password, $user->user_pass, $user->ID)) {
                $validated = true;
                Logger::get_instance()->log('info', 'Validation succeeded using main WordPress login credentials.');
            }
        }

        if (! $validated) {
            return new \WP_Error('invalid_app_password', __('WordPress validation failed. Please check your username and password.', 'newsblogify-client'));
        }

        return true;
    }

    /**
     * Handle form submissions and administrative commands.
     */
    public function handle_form_submissions()
    {
        if (! isset($_POST['newsblogify_action'])) {
            return;
        }

        check_admin_referer('newsblogify_admin_nonce_action', 'newsblogify_admin_nonce');

        if (! current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'newsblogify-client'));
        }

        $action = sanitize_text_field($_POST['newsblogify_action']);

        if ($action === 'wizard_step1') {
            $backend_url = esc_url_raw($_POST['backend_url']);
            $email = sanitize_email($_POST['email']);
            $password = $_POST['password'];

            // Connect using user's own SaaS account
            $res = API_Client::get_instance()->connect_account($backend_url, $email, $password);

            if (is_wp_error($res)) {
                $this->redirect_with_query(['error_msg' => urlencode($res->get_error_message())]);
            }

            // Securely store the issued personal access token
            Config::update('backend_url', $backend_url);
            Config::update('plugin_token', $res['access_token']);
            Config::update('account_email', $email);
            Config::update('wizard_step', '2');

            Logger::get_instance()->log('info', 'Setup Wizard Step 1: Connected account '.$email);
            $this->redirect_with_query([]);
        }

        if ($action === 'wizard_step2') {
            $site_name = sanitize_text_field($_POST['site_name']);
            $wp_username = sanitize_text_field($_POST['wp_username']);
            $wp_app_pwd = sanitize_text_field($_POST['wp_app_pwd']);
            $site_url = get_site_url();
            $backend_url = Config::get('backend_url', '');
            $api_token = Config::get('plugin_token', '');

            // Remove spaces from Application Password if any
            $wp_app_pwd = str_replace(' ', '', $wp_app_pwd);

            // Validate application password locally first
            $validation = $this->validate_local_app_password($wp_username, $wp_app_pwd);
            if (is_wp_error($validation)) {
                $this->redirect_with_query(['error_msg' => urlencode($validation->get_error_message())]);
            }

            // Register site on backend using client API
            $res = API_Client::get_instance()->register_site($backend_url, $api_token, $site_name, $site_url, $wp_app_pwd);

            if (is_wp_error($res)) {
                $this->redirect_with_query(['error_msg' => urlencode($res->get_error_message())]);
            }

            $site_id = isset($res['site_id']) ? $res['site_id'] : '';
            $config = isset($res['configuration']) ? $res['configuration'] : [];

            Config::update('site_id', $site_id);
            Config::update('site_name', $site_name);
            Config::update('wp_username', $wp_username);
            Config::update('wp_app_pwd', hash('sha256', $wp_app_pwd));
            Config::update('wp_user_id', get_current_user_id());
            Config::update('posting_slot', isset($config['slot']) ? $config['slot'] : 'Daily');
            Config::update('selected_topics', isset($config['selected_topics']) ? $config['selected_topics'] : []);
            Config::update('connection_status', 'connected');
            Config::update('last_sync_time', current_time('mysql'));
            Config::update('wizard_step', 'completed');

            Logger::get_instance()->log('info', 'Setup Wizard completed. Site registered: '.$site_id);
            $this->redirect_with_query([]);
        }

        if ($action === 'wizard_reset' || $action === 'disconnect') {
            $site_id = Config::get('site_id', '');
            if (! empty($site_id)) {
                API_Client::get_instance()->request('/disconnect', 'POST');
            }

            Config::clear();

            Logger::get_instance()->log('info', 'Plugin state reset. Disconnected from backend.');
            $this->redirect_with_query(['disconnected' => '1']);
        }

        if ($action === 'test_connection') {
            $site_url = get_site_url();
            $res = API_Client::get_instance()->request('/status?site_url='.urlencode($site_url));
            if (is_wp_error($res)) {
                Config::update('connection_status', 'disconnected');
                $this->redirect_with_query(['error_msg' => urlencode($res->get_error_message())]);
            }

            Config::update('connection_status', 'connected');
            $this->redirect_with_query(['test_success' => '1']);
        }

        if ($action === 'force_sync') {
            $site_url = get_site_url();
            $res = API_Client::get_instance()->request('/sync?site_url='.urlencode($site_url), 'POST');
            if (is_wp_error($res)) {
                $this->redirect_with_query(['error_msg' => urlencode($res->get_error_message())]);
            }

            $config = isset($res['configuration']) ? $res['configuration'] : [];
            Config::update('posting_slot', isset($config['slot']) ? $config['slot'] : 'Daily');
            Config::update('selected_topics', isset($config['selected_topics']) ? $config['selected_topics'] : []);
            Config::update('last_sync_time', current_time('mysql'));

            Logger::get_instance()->log('info', 'Forced configuration sync complete.');
            $this->redirect_with_query(['sync_success' => '1']);
        }

        if ($action === 'refresh_token') {
            $res = API_Client::get_instance()->request('/refresh-token', 'POST');
            if (is_wp_error($res)) {
                $this->redirect_with_query(['error_msg' => urlencode($res->get_error_message())]);
            }

            if (isset($res['access_token'])) {
                Config::update('plugin_token', $res['access_token']);
            }

            Logger::get_instance()->log('info', 'Token refreshed successfully.');
            $this->redirect_with_query(['token_refreshed' => '1']);
        }

        if ($action === 'clear_logs') {
            Logger::get_instance()->clear_logs();
            $this->redirect_with_query(['logs_cleared' => '1']);
        }

        if ($action === 'save_advanced') {
            $debug_mode = isset($_POST['debug_mode']) ? '1' : '0';
            Config::update('debug_mode', $debug_mode);
            $this->redirect_with_query(['settings_saved' => '1']);
        }
    }

    private function redirect_with_query($params)
    {
        $url = add_query_arg($params, admin_url('admin.php?page=newsblogify'));
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Render main dashboard view.
     */
    public function render_dashboard()
    {
        $wizard_step = Config::get('wizard_step', '1');

        echo '<div class="newsblogify-wrap">';

        if ($wizard_step !== 'completed') {
            $this->render_wizard_wizard($wizard_step);
        } else {
            $this->render_dashboard_panels();
        }

        echo '</div>';
    }

    /**
     * Render Setup Wizard Screens.
     */
    private function render_wizard_wizard($step)
    {
        $error_msg = isset($_GET['error_msg']) ? sanitize_text_field(urldecode($_GET['error_msg'])) : '';

        if ($step === '1') {
            include NEWSBLOGIFY_PATH.'templates/wizard-step-1.php';
        } else {
            include NEWSBLOGIFY_PATH.'templates/wizard-step-2.php';
        }
    }

    /**
     * Render Completed Dashboard and Settings panels.
     */
    private function render_dashboard_panels()
    {
        $is_connected = Config::get('connection_status', 'disconnected') === 'connected';
        $backend_url = Config::get('backend_url', '');
        $email = Config::get('account_email', '');
        $site_id = Config::get('site_id', '');
        $site_name = Config::get('site_name', '');
        $slot = Config::get('posting_slot', 'Daily');
        $topics = Config::get('selected_topics', []);
        $last_sync = Config::get('last_sync_time', 'Never');
        $debug_mode = Config::get('debug_mode', '0');

        include NEWSBLOGIFY_PATH.'templates/admin-dashboard.php';
    }
}
