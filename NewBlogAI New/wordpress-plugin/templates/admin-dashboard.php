<?php

use NewsBlogify\Logger;

/**
 * Template for NewsBlogify Admin Settings Dashboard.
 */
if (! defined('ABSPATH')) {
    exit;
}
?>
<?php if (isset($_GET['test_success'])) { ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Connection verified successfully! Staging endpoint verified.', 'newsblogify-client'); ?></p></div>
<?php } elseif (isset($_GET['sync_success'])) { ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e('WordPress scheduling and topic configuration updated.', 'newsblogify-client'); ?></p></div>
<?php } elseif (isset($_GET['token_refreshed'])) { ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e('OAuth token refreshed successfully.', 'newsblogify-client'); ?></p></div>
<?php } elseif (isset($_GET['settings_saved'])) { ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Advanced settings saved.', 'newsblogify-client'); ?></p></div>
<?php } elseif (isset($_GET['error_msg'])) { ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html(urldecode($_GET['error_msg'])); ?></p></div>
<?php } ?>

<div class="newsblogify-header-banner">
    <div class="newsblogify-brand">
        <span class="dashicons dashicons-admin-network newsblogify-logo-icon"></span>
        <div>
            <h1><?php esc_html_e('NewsBlogify Client Settings', 'newsblogify-client'); ?></h1>
            <p class="newsblogify-tagline"><?php esc_html_e('Automated SEO Content Generation Dashboard', 'newsblogify-client'); ?></p>
        </div>
    </div>
    <div>
        <?php if ($is_connected) { ?>
            <span class="newsblogify-badge-active"><span class="dashicons dashicons-yes" style="font-size:14px; width:14px; height:14px; vertical-align:middle; margin-right:4px;"></span> <?php esc_html_e('Connected', 'newsblogify-client'); ?></span>
        <?php } else { ?>
            <span class="newsblogify-badge-inactive"><span class="dashicons dashicons-warning" style="font-size:14px; width:14px; height:14px; vertical-align:middle; margin-right:4px;"></span> <?php esc_html_e('Disconnected', 'newsblogify-client'); ?></span>
        <?php } ?>
    </div>
</div>

<div class="newsblogify-nav-tab-wrapper nav-tab-wrapper">
    <a href="#" class="nav-tab nav-tab-active newsblogify-nav-tab" data-tab="status"><?php esc_html_e('Status &amp; Telemetry', 'newsblogify-client'); ?></a>
    <a href="#" class="nav-tab newsblogify-nav-tab" data-tab="connection"><?php esc_html_e('Connection', 'newsblogify-client'); ?></a>
    <a href="#" class="nav-tab newsblogify-nav-tab" data-tab="publishing"><?php esc_html_e('Publishing', 'newsblogify-client'); ?></a>
    <a href="#" class="nav-tab newsblogify-nav-tab" data-tab="sync"><?php esc_html_e('Synchronization', 'newsblogify-client'); ?></a>
    <a href="#" class="nav-tab newsblogify-nav-tab" data-tab="logs"><?php esc_html_e('System Logs', 'newsblogify-client'); ?></a>
    <a href="#" class="nav-tab newsblogify-nav-tab" data-tab="advanced"><?php esc_html_e('Advanced', 'newsblogify-client'); ?></a>
</div>

<div id="tab-status" class="newsblogify-tab-content active">
    <div class="newsblogify-grid">
        <div class="newsblogify-card">
            <h2><span class="dashicons dashicons-index-card"></span> <?php esc_html_e('Site Identifier', 'newsblogify-client'); ?></h2>
            <div class="newsblogify-metric-value">
                <?php echo esc_html($site_id); ?>
            </div>
            <div class="newsblogify-metric-desc"><?php esc_html_e('Registered site index ID.', 'newsblogify-client'); ?></div>
        </div>
        <div class="newsblogify-card">
            <h2><span class="dashicons dashicons-backup"></span> <?php esc_html_e('Last Synchronized', 'newsblogify-client'); ?></h2>
            <div class="newsblogify-metric-value" style="font-size: 20px;">
                <?php echo esc_html($last_sync); ?>
            </div>
            <div class="newsblogify-metric-desc"><?php esc_html_e('Last category &amp; settings sync.', 'newsblogify-client'); ?></div>
        </div>
        <div class="newsblogify-card">
            <h2><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Posting Frequency', 'newsblogify-client'); ?></h2>
            <div class="newsblogify-metric-value"><?php echo esc_html($slot); ?></div>
            <div class="newsblogify-metric-desc"><?php esc_html_e('Scheduled automation publishing slot.', 'newsblogify-client'); ?></div>
        </div>
        <div class="newsblogify-card">
            <h2><span class="dashicons dashicons-lock"></span> <?php esc_html_e('Subscription', 'newsblogify-client'); ?></h2>
            <div class="newsblogify-metric-value" style="color: #107c10;"><?php esc_html_e('ACTIVE', 'newsblogify-client'); ?></div>
            <div class="newsblogify-metric-desc"><?php esc_html_e('Plan limit: Unlimited (Dev mode)', 'newsblogify-client'); ?></div>
        </div>
    </div>

    <div class="newsblogify-card" style="margin-bottom: 25px;">
        <h2><span class="dashicons dashicons-category"></span> <?php esc_html_e('Synced Topic Clusters', 'newsblogify-client'); ?></h2>
        <?php if (empty($topics)) { ?>
            <p style="color: #646970; font-style: italic;"><?php esc_html_e('No topic configurations downloaded. Run manual synchronization.', 'newsblogify-client'); ?></p>
        <?php } else { ?>
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                <?php foreach ($topics as $topic) { ?>
                    <span style="background: #f0f0f1; border: 1px solid #ccd0d4; padding: 4px 10px; border-radius: 4px; font-size:12px; color: #2c3338;">
                        <?php echo esc_html($topic); ?>
                    </span>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <div class="newsblogify-card">
        <h2><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Operational Quick Actions', 'newsblogify-client'); ?></h2>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <form method="post" action="" style="display:inline;">
                <?php wp_nonce_field('newsblogify_admin_nonce_action', 'newsblogify_admin_nonce'); ?>
                <input type="hidden" name="newsblogify_action" value="test_connection" />
                <button type="submit" class="button button-secondary"><?php esc_html_e('Test Backend Connection', 'newsblogify-client'); ?></button>
            </form>

            <form method="post" action="" style="display:inline;">
                <?php wp_nonce_field('newsblogify_admin_nonce_action', 'newsblogify_admin_nonce'); ?>
                <input type="hidden" name="newsblogify_action" value="force_sync" />
                <button type="submit" class="button button-primary"><?php esc_html_e('Sync Now', 'newsblogify-client'); ?></button>
            </form>

            <a href="<?php echo esc_url($backend_url); ?>" target="_blank" class="button button-secondary"><?php esc_html_e('Open SaaS Dashboard', 'newsblogify-client'); ?> <span class="dashicons dashicons-external" style="font-size:14px; width:14px; line-height:28px;"></span></a>
        </div>
    </div>
</div>

<div id="tab-connection" class="newsblogify-tab-content">
    <div class="newsblogify-card">
        <h2><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Authentication Connection', 'newsblogify-client'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Connected User', 'newsblogify-client'); ?></th>
                <td><strong><?php echo esc_html($email); ?></strong></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Backend API Location', 'newsblogify-client'); ?></th>
                <td><code><?php echo esc_url($backend_url); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Authentication Bearer', 'newsblogify-client'); ?></th>
                <td><code style="background:#f6f7f7; color: #2c3338;">••••••••••••••••••••••••••••••••••••••••</code></td>
            </tr>
        </table>

        <div style="margin-top:20px; display: flex; gap: 10px;">
            <form method="post" action="" style="display:inline;">
                <?php wp_nonce_field('newsblogify_admin_nonce_action', 'newsblogify_admin_nonce'); ?>
                <input type="hidden" name="newsblogify_action" value="refresh_token" />
                <button type="submit" class="button button-secondary"><?php esc_html_e('Refresh OAuth Token', 'newsblogify-client'); ?></button>
            </form>

            <form method="post" action="" style="display:inline;" onsubmit="return confirm('Disconnect website and remove local configuration?');">
                <?php wp_nonce_field('newsblogify_admin_nonce_action', 'newsblogify_admin_nonce'); ?>
                <input type="hidden" name="newsblogify_action" value="disconnect" />
                <button type="submit" class="button button-link" style="color:#d11a2a;"><?php esc_html_e('Disconnect Website', 'newsblogify-client'); ?></button>
            </form>
        </div>
    </div>
</div>

<div id="tab-publishing" class="newsblogify-tab-content">
    <div class="newsblogify-card">
        <h2><span class="dashicons dashicons-admin-post"></span> <?php esc_html_e('Post Publishing Settings', 'newsblogify-client'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Publishing Mode', 'newsblogify-client'); ?></th>
                <td>
                    <label>
                        <span class="newsblogify-badge-active" style="background:#e7f4e9; color:#107c10;"><?php esc_html_e('REST Enabled', 'newsblogify-client'); ?></span>
                    </label>
                    <p class="description"><?php esc_html_e('Standard WordPress REST endpoints (`/wp-json/wp/v2/posts`) receive draft payloads directly.', 'newsblogify-client'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Default Status', 'newsblogify-client'); ?></th>
                <td>
                    <select disabled>
                        <option selected><?php esc_html_e('Draft', 'newsblogify-client'); ?></option>
                        <option><?php esc_html_e('Published', 'newsblogify-client'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('For safety and SEO checking, all incoming AI articles are originally generated as drafts.', 'newsblogify-client'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Category Mapping', 'newsblogify-client'); ?></th>
                <td>
                    <span class="newsblogify-badge-active" style="background:#f0f0f1; border: 1px solid #ccd0d4; color:#2c3338;"><?php esc_html_e('Dynamic Mapping', 'newsblogify-client'); ?></span>
                    <p class="description"><?php esc_html_e('Incoming posts map automatically to matching categories based on AI-selected topic categories.', 'newsblogify-client'); ?></p>
                </td>
            </tr>
        </table>
    </div>
</div>

<div id="tab-sync" class="newsblogify-tab-content">
    <div class="newsblogify-card">
        <h2><span class="dashicons dashicons-update-alt"></span> <?php esc_html_e('Synchronization Queue Settings', 'newsblogify-client'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('WordPress Cron Status', 'newsblogify-client'); ?></th>
                <td><span class="newsblogify-badge-active"><?php esc_html_e('Online', 'newsblogify-client'); ?></span></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Cron Interval', 'newsblogify-client'); ?></th>
                <td>Heartbeat: <code><?php esc_html_e('Hourly', 'newsblogify-client'); ?></code> | Settings Pull: <code><?php esc_html_e('Twice Daily', 'newsblogify-client'); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Last Heartbeat Execution', 'newsblogify-client'); ?></th>
                <td><code><?php echo esc_html($last_sync); ?></code></td>
            </tr>
        </table>
    </div>
</div>

<div id="tab-logs" class="newsblogify-tab-content">
    <div class="newsblogify-card">
        <h2>
            <span class="dashicons dashicons-media-text"></span> <?php esc_html_e('Diagnostic Activity Log', 'newsblogify-client'); ?>
            <form method="post" action="" style="display:inline; margin-left: auto;" onsubmit="return confirm('Clear activity log?');">
                <?php wp_nonce_field('newsblogify_admin_nonce_action', 'newsblogify_admin_nonce'); ?>
                <input type="hidden" name="newsblogify_action" value="clear_logs" />
                <button type="submit" class="button button-link" style="color:#d11a2a; font-size:12px;"><?php esc_html_e('Clear Log History', 'newsblogify-client'); ?></button>
            </form>
        </h2>
        <textarea class="newsblogify-log-textarea" readonly><?php echo esc_textarea(Logger::get_instance()->get_logs(200)); ?></textarea>
    </div>
</div>

<div id="tab-advanced" class="newsblogify-tab-content">
    <form method="post" action="">
        <?php wp_nonce_field('newsblogify_admin_nonce_action', 'newsblogify_admin_nonce'); ?>
        <input type="hidden" name="newsblogify_action" value="save_advanced" />

        <div class="newsblogify-card">
            <h2><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('Advanced Settings', 'newsblogify-client'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Debug Logging', 'newsblogify-client'); ?></th>
                    <td>
                        <label for="debug_mode">
                            <input type="checkbox" name="debug_mode" id="debug_mode" value="1" <?php checked($debug_mode, '1'); ?> />
                            <?php esc_html_e('Enable verbose debug logging', 'newsblogify-client'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Write outbound API details and payload responses into the activity logs.', 'newsblogify-client'); ?></p>
                    </td>
                </tr>
            </table>
            <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'newsblogify-client'); ?></button>
        </div>
    </form>
</div>
