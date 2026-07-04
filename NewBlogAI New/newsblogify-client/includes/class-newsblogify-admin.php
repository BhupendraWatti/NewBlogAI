<?php
namespace NewsBlogify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {
    private static $instance = null;

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_pages' ] );
        add_action( 'admin_init', [ $this, 'handle_form_submissions' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enqueue standard assets.
     */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'newsblogify' ) === false ) {
            return;
        }

        wp_enqueue_style( 'newsblogify-admin-css', NEWSBLOGIFY_URL . 'assets/css/admin.css', [], NEWSBLOGIFY_VERSION );
        wp_enqueue_script( 'newsblogify-admin-js', NEWSBLOGIFY_URL . 'assets/js/admin.js', [ 'jquery' ], NEWSBLOGIFY_VERSION, true );
    }

    /**
     * Register target menu in WP Admin sidebar.
     */
    public function register_admin_pages() {
        add_menu_page(
            __( 'NewsBlogify', 'newsblogify-client' ),
            __( 'NewsBlogify', 'newsblogify-client' ),
            'manage_options',
            'newsblogify',
            [ $this, 'render_dashboard' ],
            'dashicons-admin-network',
            80
        );
    }

    private function validate_local_app_password( $username, $password ) {
        $user = get_user_by( 'login', $username );
        if ( ! $user ) {
            $user = get_user_by( 'email', $username );
        }

        if ( ! $user ) {
            return new \WP_Error( 'invalid_username', __( 'WordPress username not found.', 'newsblogify-client' ) );
        }

        if ( ! class_exists( 'WP_Application_Passwords' ) ) {
            return new \WP_Error( 'disabled_app_passwords', __( 'Application passwords are not supported or active on this site.', 'newsblogify-client' ) );
        }

        $passwords = \WP_Application_Passwords::get_user_application_passwords( $user->ID );
        $validated = false;

        foreach ( $passwords as $app_password ) {
            if ( wp_check_password( $password, $app_password['password'], $user->ID ) ) {
                $validated = true;
                break;
            }
        }

        if ( ! $validated ) {
            if ( wp_check_password( $password, $user->user_pass, $user->ID ) ) {
                $validated = true;
                Logger::get_instance()->log( 'info', 'Validation succeeded using main WordPress login credentials.' );
            }
        }

        if ( ! $validated ) {
            return new \WP_Error( 'invalid_app_password', __( 'WordPress validation failed. Please check your username and password.', 'newsblogify-client' ) );
        }

        return true;
    }

    /**
     * Handle form submissions and administrative commands.
     */
    public function handle_form_submissions() {
        if ( ! isset( $_POST['newsblogify_action'] ) ) {
            return;
        }

        check_admin_referer( 'newsblogify_admin_nonce_action', 'newsblogify_admin_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized access.', 'newsblogify-client' ) );
        }

        $action = sanitize_text_field( $_POST['newsblogify_action'] );

        if ( 'wizard_step1' === $action ) {
            $backend_url = esc_url_raw( $_POST['backend_url'] );
            $email       = sanitize_email( $_POST['email'] );
            $password    = $_POST['password'];

            // Connect using user's own SaaS account
            $res = API_Client::get_instance()->connect_account( $backend_url, $email, $password );

            if ( is_wp_error( $res ) ) {
                $this->redirect_with_query( [ 'error_msg' => urlencode( $res->get_error_message() ) ] );
            }

            // Securely store the issued personal access token
            Config::update( 'backend_url', $backend_url );
            Config::update( 'api_token', $res['access_token'] );
            Config::update( 'account_email', $email );
            Config::update( 'wizard_step', '2' );

            Logger::get_instance()->log( 'info', 'Setup Wizard Step 1: Connected account ' . $email );
            $this->redirect_with_query( [] );
        }

        if ( 'wizard_step2' === $action ) {
            $site_name   = sanitize_text_field( $_POST['site_name'] );
            $wp_username = sanitize_text_field( $_POST['wp_username'] );
            $wp_app_pwd  = sanitize_text_field( $_POST['wp_app_pwd'] );
            $site_url    = get_site_url();
            $backend_url = Config::get( 'backend_url', '' );
            $api_token   = Config::get( 'api_token', '' );

            // Remove spaces from Application Password if any
            $wp_app_pwd  = str_replace( ' ', '', $wp_app_pwd );

            // Validate application password locally first
            $validation = $this->validate_local_app_password( $wp_username, $wp_app_pwd );
            if ( is_wp_error( $validation ) ) {
                $this->redirect_with_query( [ 'error_msg' => urlencode( $validation->get_error_message() ) ] );
            }

            // Register site on backend using client API
            $res = API_Client::get_instance()->register_site( $backend_url, $api_token, $site_name, $site_url, $wp_app_pwd );

            if ( is_wp_error( $res ) ) {
                $this->redirect_with_query( [ 'error_msg' => urlencode( $res->get_error_message() ) ] );
            }

            $site_id = isset( $res['site_id'] ) ? $res['site_id'] : '';
            $config  = isset( $res['configuration'] ) ? $res['configuration'] : [];

            Config::update( 'site_id', $site_id );
            Config::update( 'site_name', $site_name );
            Config::update( 'wp_username', $wp_username );
            Config::update( 'wp_app_pwd', hash( 'sha256', $wp_app_pwd ) );
            Config::update( 'wp_user_id', get_current_user_id() );
            Config::update( 'posting_slot', isset( $config['slot'] ) ? $config['slot'] : 'Daily' );
            Config::update( 'selected_topics', isset( $config['selected_topics'] ) ? $config['selected_topics'] : [] );
            Config::update( 'connection_status', 'connected' );
            Config::update( 'last_sync_time', current_time( 'mysql' ) );
            Config::update( 'wizard_step', 'completed' );

            Logger::get_instance()->log( 'info', 'Setup Wizard completed. Site registered: ' . $site_id );
            $this->redirect_with_query( [] );
        }

        if ( 'wizard_reset' === $action || 'disconnect' === $action ) {
            $site_id = Config::get( 'site_id', '' );
            if ( ! empty( $site_id ) ) {
                API_Client::get_instance()->request( '/disconnect', 'POST' );
            }

            Config::clear();

            Logger::get_instance()->log( 'info', 'Plugin state reset. Disconnected from backend.' );
            $this->redirect_with_query( [ 'disconnected' => '1' ] );
        }

        if ( 'test_connection' === $action ) {
            $site_url = get_site_url();
            $res = API_Client::get_instance()->request( '/status?site_url=' . urlencode( $site_url ) );
            if ( is_wp_error( $res ) ) {
                Config::update( 'connection_status', 'disconnected' );
                $this->redirect_with_query( [ 'error_msg' => urlencode( $res->get_error_message() ) ] );
            }

            Config::update( 'connection_status', 'connected' );
            $this->redirect_with_query( [ 'test_success' => '1' ] );
        }

        if ( 'force_sync' === $action ) {
            $site_url = get_site_url();
            $res = API_Client::get_instance()->request( '/sync?site_url=' . urlencode( $site_url ), 'POST' );
            if ( is_wp_error( $res ) ) {
                $this->redirect_with_query( [ 'error_msg' => urlencode( $res->get_error_message() ) ] );
            }

            $config = isset( $res['configuration'] ) ? $res['configuration'] : [];
            Config::update( 'posting_slot', isset( $config['slot'] ) ? $config['slot'] : 'Daily' );
            Config::update( 'selected_topics', isset( $config['selected_topics'] ) ? $config['selected_topics'] : [] );
            Config::update( 'last_sync_time', current_time( 'mysql' ) );

            Logger::get_instance()->log( 'info', 'Forced configuration sync complete.' );
            $this->redirect_with_query( [ 'sync_success' => '1' ] );
        }

        if ( 'refresh_token' === $action ) {
            $res = API_Client::get_instance()->request( '/refresh-token', 'POST' );
            if ( is_wp_error( $res ) ) {
                $this->redirect_with_query( [ 'error_msg' => urlencode( $res->get_error_message() ) ] );
            }

            if ( isset( $res['access_token'] ) ) {
                Config::update( 'api_token', $res['access_token'] );
            }

            Logger::get_instance()->log( 'info', 'Token refreshed successfully.' );
            $this->redirect_with_query( [ 'token_refreshed' => '1' ] );
        }

        if ( 'clear_logs' === $action ) {
            Logger::get_instance()->clear_logs();
            $this->redirect_with_query( [ 'logs_cleared' => '1' ] );
        }

        if ( 'save_advanced' === $action ) {
            $debug_mode = isset( $_POST['debug_mode'] ) ? '1' : '0';
            Config::update( 'debug_mode', $debug_mode );
            $this->redirect_with_query( [ 'settings_saved' => '1' ] );
        }
    }

    private function redirect_with_query( $params ) {
        $url = add_query_arg( $params, admin_url( 'admin.php?page=newsblogify' ) );
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Render main dashboard view.
     */
    public function render_dashboard() {
        $wizard_step = Config::get( 'wizard_step', '1' );

        echo '<div class="newsblogify-wrap">';

        if ( 'completed' !== $wizard_step ) {
            $this->render_wizard_wizard( $wizard_step );
        } else {
            $this->render_dashboard_panels();
        }

        echo '</div>';
    }

    /**
     * Render Setup Wizard Screens.
     */
    private function render_wizard_wizard( $step ) {
        ?>
        <div class="newsblogify-wizard-container">
            <div class="newsblogify-wizard-header">
                <h2><?php esc_html_e( 'Welcome to NewsBlogify', 'newsblogify-client' ); ?></h2>
                <div style="font-size: 13px; margin-top: 5px; opacity: 0.85;"><?php esc_html_e( 'AI WordPress Content Automation Onboarding', 'newsblogify-client' ); ?></div>
            </div>

            <div class="newsblogify-wizard-steps">
                <div class="newsblogify-wizard-step <?php echo '1' === $step ? 'active' : 'completed'; ?>">
                    <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( '1. Connect Account', 'newsblogify-client' ); ?>
                </div>
                <div class="newsblogify-wizard-step <?php echo '2' === $step ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e( '2. Register Site', 'newsblogify-client' ); ?>
                </div>
            </div>

            <div class="newsblogify-wizard-body">
                <?php if ( isset( $_GET['error_msg'] ) ) : ?>
                    <div class="notice notice-error is-dismissible" style="margin-left:0; margin-bottom: 20px;">
                        <p><?php echo esc_html( urldecode( $_GET['error_msg'] ) ); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ( '1' === $step ) : ?>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'newsblogify_admin_nonce_action', 'newsblogify_admin_nonce' ); ?>
                        <input type="hidden" name="newsblogify_action" value="wizard_step1" />

                        <p style="margin-top:0; color: #646970; font-size: 14px;"><?php esc_html_e( 'Connect this WordPress installation to your NewsBlogify SaaS dashboard account to begin.', 'newsblogify-client' ); ?></p>
                        
                        <table class="newsblogify-wizard-table">
                            <tr>
                                <td class="label-column"><label><?php esc_html_e( 'Backend Service URL', 'newsblogify-client' ); ?></label></td>
                                <td><input type="text" class="newsblogify-wizard-input" name="backend_url" value="http://127.0.0.1:8000" required /></td>
                            </tr>
                            <tr>
                                <td class="label-column"><label><?php esc_html_e( 'Account Email', 'newsblogify-client' ); ?></label></td>
                                <td><input type="email" class="newsblogify-wizard-input" name="email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" placeholder="e.g. user@mycompany.com" required /></td>
                            </tr>
                            <tr>
                                <td class="label-column"><label><?php esc_html_e( 'Account Password', 'newsblogify-client' ); ?></label></td>
                                <td><input type="password" class="newsblogify-wizard-input" name="password" required /></td>
                            </tr>
                        </table>

                        <div style="text-align: right; margin-top: 25px;">
                            <button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Connect &amp; Authenticate', 'newsblogify-client' ); ?></button>
                        </div>
                    </form>

                <?php else : ?>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'newsblogify_admin_nonce_action', 'newsblogify_admin_nonce' ); ?>
                        <input type="hidden" name="newsblogify_action" value="wizard_step2" />

                        <p style="margin-top:0; color: #646970; font-size: 14px;"><?php esc_html_e( 'Review and submit your local site metadata to establish remote posting verification.', 'newsblogify-client' ); ?></p>

                        <table class="newsblogify-wizard-table">
                            <tr>
                                <td class="label-column"><label><?php esc_html_e( 'Website Name', 'newsblogify-client' ); ?></label></td>
                                <td><input type="text" class="newsblogify-wizard-input" name="site_name" value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" required /></td>
                            </tr>
                            <tr>
                                <td class="label-column"><label><?php esc_html_e( 'Website Public URL', 'newsblogify-client' ); ?></label></td>
                                <td><input type="text" class="newsblogify-wizard-input" value="<?php echo esc_url( get_site_url() ); ?>" readonly /></td>
                            </tr>
                            <tr>
                                <td class="label-column"><label><?php esc_html_e( 'WordPress Username', 'newsblogify-client' ); ?></label></td>
                                <td><input type="text" class="newsblogify-wizard-input" name="wp_username" value="admin" required /></td>
                            </tr>
                            <tr>
                                <td class="label-column"><label><?php esc_html_e( 'WordPress App Password', 'newsblogify-client' ); ?></label></td>
                                <td>
                                    <input type="password" class="newsblogify-wizard-input" name="wp_app_pwd" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" required />
                                    <p class="description" style="margin-top: 6px; font-size: 11px;">
                                        <strong><?php esc_html_e( 'Why is this required?', 'newsblogify-client' ); ?></strong><br>
                                        <?php esc_html_e( 'WordPress disallows using your regular password to call REST APIs. You must generate an Application Password under Users -> Profile -> Application Passwords.', 'newsblogify-client' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <div style="display: flex; justify-content: space-between; margin-top: 25px;">
                            <button type="submit" name="newsblogify_action" value="wizard_reset" class="button button-link" style="color: #d11a2a;"><?php esc_html_e( 'Reset Wizard', 'newsblogify-client' ); ?></button>
                            <button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Register &amp; Finish Setup', 'newsblogify-client' ); ?></button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Completed Dashboard and Settings panels.
     */
    private function render_dashboard_panels() {
        $is_connected = Config::get( 'connection_status', 'disconnected' ) === 'connected';
        $backend_url  = Config::get( 'backend_url', '' );
        $email        = Config::get( 'account_email', '' );
        $site_id      = Config::get( 'site_id', '' );
        $site_name    = Config::get( 'site_name', '' );
        $slot         = Config::get( 'posting_slot', 'Daily' );
        $topics       = Config::get( 'selected_topics', [] );
        $last_sync    = Config::get( 'last_sync_time', 'Never' );
        $debug_mode   = Config::get( 'debug_mode', '0' );

        if ( isset( $_GET['test_success'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Connection verified successfully! Staging endpoint verified.', 'newsblogify-client' ); ?></p></div>
        <?php elseif ( isset( $_GET['sync_success'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'WordPress scheduling and topic configuration updated.', 'newsblogify-client' ); ?></p></div>
        <?php elseif ( isset( $_GET['token_refreshed'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'OAuth token refreshed successfully.', 'newsblogify-client' ); ?></p></div>
        <?php elseif ( isset( $_GET['settings_saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Advanced settings saved.', 'newsblogify-client' ); ?></p></div>
        <?php elseif ( isset( $_GET['error_msg'] ) ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error_msg'] ) ); ?></p></div>
        <?php endif; ?>

        <div class="newsblogify-header-banner">
            <div class="newsblogify-brand">
                <span class="dashicons dashicons-admin-network newsblogify-logo-icon"></span>
                <div>
                    <h1><?php esc_html_e( 'NewsBlogify Client Settings', 'newsblogify-client' ); ?></h1>
                    <p class="newsblogify-tagline"><?php esc_html_e( 'Automated SEO Content Generation Dashboard', 'newsblogify-client' ); ?></p>
                </div>
            </div>
            <div>
                <?php if ( $is_connected ) : ?>
                    <span class="newsblogify-badge-active"><span class="dashicons dashicons-yes" style="font-size:14px; width:14px; height:14px; vertical-align:middle; margin-right:4px;"></span> <?php esc_html_e( 'Connected', 'newsblogify-client' ); ?></span>
                <?php else : ?>
                    <span class="newsblogify-badge-inactive"><span class="dashicons dashicons-warning" style="font-size:14px; width:14px; height:14px; vertical-align:middle; margin-right:4px;"></span> <?php esc_html_e( 'Disconnected', 'newsblogify-client' ); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="newsblogify-nav-tab-wrapper nav-tab-wrapper">
            <a href="#" class="nav-tab nav-tab-active newsblogify-nav-tab" data-tab="status"><?php esc_html_e( 'Status &amp; Telemetry', 'newsblogify-client' ); ?></a>
            <a href="#" class="nav-tab newsblogify-nav-tab" data-tab="connection"><?php esc_html_e( 'Connection', 'newsblogify-client' ); ?></a>
            <a href="#" class="nav-tab newsblogify-nav-tab" data-tab="publishing"><?php esc_html_e( 'Publishing', 'newsblogify-client' ); ?></a>
            <a href="#" class="nav-tab newsblogify-nav-tab" data-tab="sync"><?php esc_html_e( 'Synchronization', 'newsblogify-client' ); ?></a>
            <a href="#" class="nav-tab newsblogify-nav-tab" data-tab="logs"><?php esc_html_e( 'System Logs', 'newsblogify-client' ); ?></a>
            <a href="#" class="nav-tab newsblogify-nav-tab" data-tab="advanced"><?php esc_html_e( 'Advanced', 'newsblogify-client' ); ?></a>
        </div>

        <div id="tab-status" class="newsblogify-tab-content active">
            <div class="newsblogify-grid">
                <div class="newsblogify-card">
                    <h2><span class="dashicons dashicons-index-card"></span> <?php esc_html_e( 'Site Identifier', 'newsblogify-client' ); ?></h2>
                    <div class="newsblogify-metric-value">
                        <?php echo esc_html( $site_id ); ?>
                    </div>
                    <div class="newsblogify-metric-desc"><?php esc_html_e( 'Registered site index ID.', 'newsblogify-client' ); ?></div>
                </div>
                <div class="newsblogify-card">
                    <h2><span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'Last Synchronized', 'newsblogify-client' ); ?></h2>
                    <div class="newsblogify-metric-value" style="font-size: 20px;">
                        <?php echo esc_html( $last_sync ); ?>
                    </div>
                    <div class="newsblogify-metric-desc"><?php esc_html_e( 'Last category &amp; settings sync.', 'newsblogify-client' ); ?></div>
                </div>
                <div class="newsblogify-card">
                    <h2><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Posting Frequency', 'newsblogify-client' ); ?></h2>
                    <div class="newsblogify-metric-value"><?php echo esc_html( $slot ); ?></div>
                    <div class="newsblogify-metric-desc"><?php esc_html_e( 'Scheduled automation publishing slot.', 'newsblogify-client' ); ?></div>
                </div>
                <div class="newsblogify-card">
                    <h2><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Subscription', 'newsblogify-client' ); ?></h2>
                    <div class="newsblogify-metric-value" style="color: #107c10;"><?php esc_html_e( 'ACTIVE', 'newsblogify-client' ); ?></div>
                    <div class="newsblogify-metric-desc"><?php esc_html_e( 'Plan limit: Unlimited (Dev mode)', 'newsblogify-client' ); ?></div>
                </div>
            </div>

            <div class="newsblogify-card" style="margin-bottom: 25px;">
                <h2><span class="dashicons dashicons-category"></span> <?php esc_html_e( 'Synced Topic Clusters', 'newsblogify-client' ); ?></h2>
                <?php if ( empty( $topics ) ) : ?>
                    <p style="color: #646970; font-style: italic;"><?php esc_html_e( 'No topic configurations downloaded. Run manual synchronization.', 'newsblogify-client' ); ?></p>
                <?php else : ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ( $topics as $topic ) : ?>
                            <span style="background: #f0f0f1; border: 1px solid #ccd0d4; padding: 4px 10px; border-radius: 4px; font-size:12px; color: #2c3338;">
                                <?php echo esc_html( $topic ); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="newsblogify-card">
                <h2><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Operational Quick Actions', 'newsblogify-client' ); ?></h2>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <form method="post" action="" style="display:inline;">
                        <?php wp_nonce_field( 'newsblogify_admin_nonce_action', 'newsblogify_admin_nonce' ); ?>
                        <input type="hidden" name="newsblogify_action" value="test_connection" />
                        <button type="submit" class="button button-secondary"><?php esc_html_e( 'Test Backend Connection', 'newsblogify-client' ); ?></button>
                    </form>

                    <form method="post" action="" style="display:inline;">
                        <?php wp_nonce_field( 'newsblogify_admin_nonce_action', 'newsblogify_admin_nonce' ); ?>
                        <input type="hidden" name="newsblogify_action" value="force_sync" />
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Sync Now', 'newsblogify-client' ); ?></button>
                    </form>

                    <a href="<?php echo esc_url( $backend_url ); ?>" target="_blank" class="button button-secondary"><?php esc_html_e( 'Open SaaS Dashboard', 'newsblogify-client' ); ?> <span class="dashicons dashicons-external" style="font-size:14px; width:14px; line-height:28px;"></span></a>
                </div>
            </div>
        </div>

        <div id="tab-connection" class="newsblogify-tab-content">
            <div class="newsblogify-card">
                <h2><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Authentication Connection', 'newsblogify-client' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Connected User', 'newsblogify-client' ); ?></th>
                        <td><strong><?php echo esc_html( $email ); ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Backend API Location', 'newsblogify-client' ); ?></th>
                        <td><code><?php echo esc_url( $backend_url ); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Authentication Bearer', 'newsblogify-client' ); ?></th>
                        <td><code style="background:#f6f7f7; color: #2c3338;">••••••••••••••••••••••••••••••••••••••••</code></td>
                    </tr>
                </table>

                <div style="margin-top:20px; display: flex; gap: 10px;">
                    <form method="post" action="" style="display:inline;">
                        <?php wp_nonce_field( 'newsblogify_admin_nonce_action', 'newsblogify_admin_nonce' ); ?>
                        <input type="hidden" name="newsblogify_action" value="refresh_token" />
                        <button type="submit" class="button button-secondary"><?php esc_html_e( 'Refresh OAuth Token', 'newsblogify-client' ); ?></button>
                    </form>

                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('Disconnect website and remove local configuration?');">
                        <?php wp_nonce_field( 'newsblogify_admin_nonce_action', 'newsblogify_admin_nonce' ); ?>
                        <input type="hidden" name="newsblogify_action" value="disconnect" />
                        <button type="submit" class="button button-link" style="color:#d11a2a;"><?php esc_html_e( 'Disconnect Website', 'newsblogify-client' ); ?></button>
                    </form>
                </div>
            </div>
        </div>

        <div id="tab-publishing" class="newsblogify-tab-content">
            <div class="newsblogify-card">
                <h2><span class="dashicons dashicons-admin-post"></span> <?php esc_html_e( 'Post Publishing Settings', 'newsblogify-client' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Publishing Mode', 'newsblogify-client' ); ?></th>
                        <td>
                            <label>
                                <span class="newsblogify-badge-active" style="background:#e7f4e9; color:#107c10;"><?php esc_html_e( 'REST Enabled', 'newsblogify-client' ); ?></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Standard WordPress REST endpoints (`/wp-json/wp/v2/posts`) receive draft payloads directly.', 'newsblogify-client' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Default Status', 'newsblogify-client' ); ?></th>
                        <td>
                            <select disabled>
                                <option selected><?php esc_html_e( 'Draft', 'newsblogify-client' ); ?></option>
                                <option><?php esc_html_e( 'Published', 'newsblogify-client' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'For safety and SEO checking, all incoming AI articles are originally generated as drafts.', 'newsblogify-client' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Category Mapping', 'newsblogify-client' ); ?></th>
                        <td>
                            <span class="newsblogify-badge-active" style="background:#f0f0f1; border: 1px solid #ccd0d4; color:#2c3338;"><?php esc_html_e( 'Dynamic Mapping', 'newsblogify-client' ); ?></span>
                            <p class="description"><?php esc_html_e( 'Incoming posts map automatically to matching categories based on AI-selected topic categories.', 'newsblogify-client' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div id="tab-sync" class="newsblogify-tab-content">
            <div class="newsblogify-card">
                <h2><span class="dashicons dashicons-update-alt"></span> <?php esc_html_e( 'Synchronization Queue Settings', 'newsblogify-client' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'WordPress Cron Status', 'newsblogify-client' ); ?></th>
                        <td><span class="newsblogify-badge-active"><?php esc_html_e( 'Online', 'newsblogify-client' ); ?></span></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Cron Interval', 'newsblogify-client' ); ?></th>
                        <td>Heartbeat: <code><?php esc_html_e( 'Hourly', 'newsblogify-client' ); ?></code> | Settings Pull: <code><?php esc_html_e( 'Twice Daily', 'newsblogify-client' ); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Last Heartbeat Execution', 'newsblogify-client' ); ?></th>
                        <td><code><?php echo esc_html( $last_sync ); ?></code></td>
                    </tr>
                </table>
            </div>
        </div>

        <div id="tab-logs" class="newsblogify-tab-content">
            <div class="newsblogify-card">
                <h2>
                    <span class="dashicons dashicons-media-text"></span> <?php esc_html_e( 'Diagnostic Activity Log', 'newsblogify-client' ); ?>
                    <form method="post" action="" style="display:inline; margin-left: auto;" onsubmit="return confirm('Clear activity log?');">
                        <?php wp_nonce_field( 'newsblogify_admin_nonce_action', 'newsblogify_admin_nonce' ); ?>
                        <input type="hidden" name="newsblogify_action" value="clear_logs" />
                        <button type="submit" class="button button-link" style="color:#d11a2a; font-size:12px;"><?php esc_html_e( 'Clear Log History', 'newsblogify-client' ); ?></button>
                    </form>
                </h2>
                <textarea class="newsblogify-log-textarea" readonly><?php echo esc_textarea( Logger::get_instance()->get_logs( 200 ) ); ?></textarea>
            </div>
        </div>

        <div id="tab-advanced" class="newsblogify-tab-content">
            <form method="post" action="">
                <?php wp_nonce_field( 'newsblogify_admin_nonce_action', 'newsblogify_admin_nonce' ); ?>
                <input type="hidden" name="newsblogify_action" value="save_advanced" />

                <div class="newsblogify-card">
                    <h2><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Advanced Settings', 'newsblogify-client' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Debug Logging', 'newsblogify-client' ); ?></th>
                            <td>
                                <label for="debug_mode">
                                    <input type="checkbox" name="debug_mode" id="debug_mode" value="1" <?php checked( $debug_mode, '1' ); ?> />
                                    <?php esc_html_e( 'Enable verbose debug logging', 'newsblogify-client' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Write outbound API details and payload responses into the activity logs.', 'newsblogify-client' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'newsblogify-client' ); ?></button>
                </div>
            </form>
        </div>
        <?php
    }
}
