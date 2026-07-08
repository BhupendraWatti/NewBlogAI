<?php

namespace {
    // Define WordPress mock functions in the global namespace if they don't exist yet
    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir() {
            return ['basedir' => sys_get_temp_dir()];
        }
    }

    if (!function_exists('get_option')) {
        function get_option($option, $default = false) {
            global $mock_wp_options;
            return isset($mock_wp_options[$option]) ? $mock_wp_options[$option] : $default;
        }
    }

    if (!function_exists('update_option')) {
        function update_option($option, $value) {
            global $mock_wp_options;
            $mock_wp_options[$option] = $value;
            return true;
        }
    }

    if (!function_exists('wp_generate_password')) {
        function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
            return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data) {
            return json_encode($data);
        }
    }

    if (!defined('ABSPATH')) {
        define('ABSPATH', sys_get_temp_dir());
    }

    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p($path) {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
    }
}

namespace Tests\Feature {

    use Tests\TestCase;

    class WordPressPluginLoggerTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            global $mock_wp_options;
            $mock_wp_options = [];

            // Load the Logger class
            require_once base_path('wordpress-plugin/includes/class-newsblogify-logger.php');

            // Reset Singleton of NewsBlogify\Logger since it keeps state
            $ref = new \ReflectionClass(\NewsBlogify\Logger::class);
            $instance_prop = $ref->getProperty('instance');
            $instance_prop->setAccessible(true);
            $instance_prop->setValue(null, null);
        }

        public function test_logger_generates_random_hash_and_stores_in_options()
        {
            global $mock_wp_options;
            $this->assertEmpty(get_option('newsblogify_log_hash'));

            // Instantiate/Get Logger
            $logger = \NewsBlogify\Logger::get_instance();

            // Assert hash was generated and stored
            $hash = get_option('newsblogify_log_hash');
            $this->assertNotEmpty($hash);
            $this->assertEquals(16, strlen($hash));

            // Assert filenames contain the hash
            $logger->log('info', 'Test log message');
            $logger->log_activity('test_event', 'Test activity message');

            $log_dir = $logger->get_log_dir();
            $expected_log_file = $log_dir . '/newsblogify-' . $hash . '.log';
            $expected_activity_file = $log_dir . '/activity-' . $hash . '.jsonl';

            $this->assertFileExists($expected_log_file);
            $this->assertFileExists($expected_activity_file);

            $this->assertStringContainsString('Test log message', file_get_contents($expected_log_file));
            $this->assertStringContainsString('Test activity message', file_get_contents($expected_activity_file));

            // Clean up
            @unlink($expected_log_file);
            @unlink($expected_activity_file);
        }
    }
}
