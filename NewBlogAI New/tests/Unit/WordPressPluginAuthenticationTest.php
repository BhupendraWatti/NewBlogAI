<?php

namespace {
    if (! defined('ABSPATH')) {
        define('ABSPATH', sys_get_temp_dir());
    }

    if (! function_exists('get_option')) {
        function get_option($option, $default = false)
        {
            global $mock_wp_options;

            return $mock_wp_options[$option] ?? $default;
        }
    }

    if (! function_exists('update_option')) {
        function update_option($option, $value)
        {
            global $mock_wp_options;
            $mock_wp_options[$option] = $value;

            return true;
        }
    }

    if (! function_exists('current_user_can')) {
        function current_user_can($capability)
        {
            return false;
        }
    }

    if (! function_exists('__')) {
        function __($text, $domain = 'default')
        {
            return $text;
        }
    }

    if (! class_exists('WP_Error')) {
        class WP_Error
        {
            public function __construct(
                private string $code,
                private string $message,
                private array $data = [],
            ) {}

            public function get_error_code(): string
            {
                return $this->code;
            }
        }
    }

    if (! class_exists('WP_REST_Request')) {
        class WP_REST_Request
        {
            public function __construct(
                private array $headers = [],
                private array $params = [],
            ) {}

            public function get_header($name)
            {
                return $this->headers[$name] ?? '';
            }

            public function get_param($name)
            {
                return $this->params[$name] ?? null;
            }
        }
    }
}

namespace Tests\Unit {
    use NewsBlogify\REST_Controller;
    use PHPUnit\Framework\TestCase;

    class WordPressPluginAuthenticationTest extends TestCase
    {
        protected function setUp(): void
        {
            global $mock_wp_options;
            $mock_wp_options = [];

            require_once dirname(__DIR__, 2).'/wordpress-plugin/includes/class-newsblogify-config.php';
            require_once dirname(__DIR__, 2).'/wordpress-plugin/includes/class-newsblogify-rest-controller.php';
        }

        public function test_incoming_requests_accept_the_per_site_token(): void
        {
            global $mock_wp_options;
            $mock_wp_options['newsblogify_settings'] = [
                'plugin_token' => 'account-token',
                'site_token' => 'site-token',
            ];

            $this->assertTrue((new REST_Controller)->verify_api_key(
                new \WP_REST_Request(['Authorization' => 'Bearer site-token'])
            ));
        }

        public function test_registration_screen_does_not_request_wordpress_credentials(): void
        {
            $template = file_get_contents(dirname(__DIR__, 2).'/wordpress-plugin/templates/wizard-step-2.php');
            $admin = file_get_contents(dirname(__DIR__, 2).'/wordpress-plugin/includes/class-newsblogify-admin.php');
            $restController = file_get_contents(dirname(__DIR__, 2).'/wordpress-plugin/includes/class-newsblogify-rest-controller.php');

            $this->assertStringNotContainsString('name="wp_username"', $template);
            $this->assertStringNotContainsString('name="wp_app_pwd"', $template);
            $this->assertStringContainsString("Config::get('site_token'", $admin);
            $this->assertStringContainsString('wp_generate_password(64, false, false)', $admin);
            $this->assertStringContainsString("Config::get('site_token'", $restController);
        }
    }
}
