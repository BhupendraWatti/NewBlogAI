<?php
/**
 * Template for Setup Wizard Step 2: Register Site.
 */
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="newsblogify-wizard-container">
    <div class="newsblogify-wizard-header">
        <h2><?php esc_html_e('Welcome to NewsBlogify', 'newsblogify-client'); ?></h2>
        <div style="font-size: 13px; margin-top: 5px; opacity: 0.85;"><?php esc_html_e('AI WordPress Content Automation Onboarding', 'newsblogify-client'); ?></div>
    </div>

    <div class="newsblogify-wizard-steps">
        <div class="newsblogify-wizard-step completed">
            <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('1. Connect Account', 'newsblogify-client'); ?>
        </div>
        <div class="newsblogify-wizard-step active">
            <span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e('2. Register Site', 'newsblogify-client'); ?>
        </div>
    </div>

    <div class="newsblogify-wizard-body">
        <?php if (! empty($error_msg)) { ?>
            <div class="notice notice-error is-dismissible" style="margin-left:0; margin-bottom: 20px;">
                <p><?php echo esc_html($error_msg); ?></p>
            </div>
        <?php } ?>

        <form method="post" action="">
            <?php wp_nonce_field('newsblogify_admin_nonce_action', 'newsblogify_admin_nonce'); ?>
            <input type="hidden" name="newsblogify_action" value="wizard_step2" />

            <p style="margin-top:0; color: #646970; font-size: 14px;"><?php esc_html_e('Review and submit your local site metadata to establish remote posting verification.', 'newsblogify-client'); ?></p>

            <table class="newsblogify-wizard-table">
                <tr>
                    <td class="label-column"><label><?php esc_html_e('Website Name', 'newsblogify-client'); ?></label></td>
                    <td><input type="text" class="newsblogify-wizard-input" name="site_name" value="<?php echo esc_attr(get_bloginfo('name')); ?>" required /></td>
                </tr>
                <tr>
                    <td class="label-column"><label><?php esc_html_e('Website Public URL', 'newsblogify-client'); ?></label></td>
                    <td><input type="text" class="newsblogify-wizard-input" value="<?php echo esc_url(get_site_url()); ?>" readonly /></td>
                </tr>
                <tr>
                    <td class="label-column"><label><?php esc_html_e('WordPress Username', 'newsblogify-client'); ?></label></td>
                    <td><input type="text" class="newsblogify-wizard-input" name="wp_username" value="admin" required /></td>
                </tr>
                <tr>
                    <td class="label-column"><label><?php esc_html_e('WordPress App Password', 'newsblogify-client'); ?></label></td>
                    <td>
                        <input type="password" class="newsblogify-wizard-input" name="wp_app_pwd" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" required />
                        <p class="description" style="margin-top: 6px; font-size: 11px;">
                            <strong><?php esc_html_e('Why is this required?', 'newsblogify-client'); ?></strong><br>
                            <?php esc_html_e('WordPress disallows using your regular password to call REST APIs. You must generate an Application Password under Users -> Profile -> Application Passwords.', 'newsblogify-client'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div style="display: flex; justify-content: space-between; flex-direction: row-reverse; margin-top: 25px;">
                <button type="submit" class="button button-primary button-large"><?php esc_html_e('Register &amp; Finish Setup', 'newsblogify-client'); ?></button>
                <button type="submit" name="newsblogify_action" value="wizard_reset" class="button button-link" style="color: #d11a2a;"><?php esc_html_e('Reset Wizard', 'newsblogify-client'); ?></button>
            </div>
        </form>
    </div>
</div>
