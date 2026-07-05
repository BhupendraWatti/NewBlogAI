<?php
/**
 * Template for Setup Wizard Step 1: Connect Account.
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
        <div class="newsblogify-wizard-step active">
            <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('1. Connect Account', 'newsblogify-client'); ?>
        </div>
        <div class="newsblogify-wizard-step">
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
            <input type="hidden" name="newsblogify_action" value="wizard_step1" />

            <p style="margin-top:0; color: #646970; font-size: 14px;"><?php esc_html_e('Connect this WordPress installation to your NewsBlogify SaaS dashboard account to begin.', 'newsblogify-client'); ?></p>
            
            <table class="newsblogify-wizard-table">
                <tr>
                    <td class="label-column"><label><?php esc_html_e('Backend Service URL', 'newsblogify-client'); ?></label></td>
                    <td><input type="text" class="newsblogify-wizard-input" name="backend_url" value="http://127.0.0.1:8000" required /></td>
                </tr>
                <tr>
                    <td class="label-column"><label><?php esc_html_e('Account Email', 'newsblogify-client'); ?></label></td>
                    <td><input type="email" class="newsblogify-wizard-input" name="email" value="<?php echo esc_attr(get_option('admin_email')); ?>" placeholder="e.g. user@mycompany.com" required /></td>
                </tr>
                <tr>
                    <td class="label-column"><label><?php esc_html_e('Account Password', 'newsblogify-client'); ?></label></td>
                    <td><input type="password" class="newsblogify-wizard-input" name="password" required /></td>
                </tr>
            </table>

            <div style="text-align: right; margin-top: 25px;">
                <button type="submit" class="button button-primary button-large"><?php esc_html_e('Connect &amp; Authenticate', 'newsblogify-client'); ?></button>
            </div>
        </form>
    </div>
</div>
