<?php

/**
 * Logger – Structured file-based logging for the NewsBlogify Client plugin.
 *
 * Writes plain-text log lines to a protected file inside the WordPress
 * uploads directory.  A separate activity log stores structured JSON events
 * (activity.jsonl) for audit/reporting purposes.
 *
 * The log directory is protected with an .htaccess file and an empty
 * index.php to prevent direct web access.
 *
 * @since   2.0.0
 */

namespace NewsBlogify;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Logger
 *
 * Singleton.  Obtain the instance via Logger::get_instance().
 *
 * Usage:
 *   Logger::get_instance()->log( Logger::LOG_INFO, 'Something happened.' );
 *   Logger::get_instance()->log_activity( 'post_published', 'Post 42 published', [ 'post_id' => 42 ] );
 *
 * @since   2.0.0
 */
class Logger
{
    // ---------------------------------------------------------------------------
    // Log-level constants
    // ---------------------------------------------------------------------------

    /** Debug-level message – highly verbose, for development. */
    const LOG_DEBUG = 'debug';

    /** Informational message – normal operation events. */
    const LOG_INFO = 'info';

    /** Warning-level message – something unexpected but recoverable. */
    const LOG_WARN = 'warn';

    /** Error-level message – a failure that requires attention. */
    const LOG_ERROR = 'error';

    // ---------------------------------------------------------------------------
    // Internal constants
    // ---------------------------------------------------------------------------

    /** Plain-text log filename. */
    const LOG_FILE = 'newsblogify.log';

    /** Structured activity log filename (JSON Lines format). */
    const ACTIVITY_FILE = 'activity.jsonl';

    /** Maximum log file size in bytes before rotation (2 MB). */
    const MAX_LOG_SIZE = 2097152;

    // ---------------------------------------------------------------------------
    // Singleton
    // ---------------------------------------------------------------------------

    /** @var Logger|null Singleton instance. */
    private static ?Logger $instance = null;

    /** @var string Absolute path to the secured log directory. */
    private string $log_dir;

    /** @var string Absolute path to the plain-text log file. */
    private string $log_file;

    /** @var string Absolute path to the activity JSON Lines file. */
    private string $activity_file;

    /**
     * Private constructor – use get_instance().
     */
    private function __construct()
    {
        $this->log_dir = $this->resolve_log_dir();
        $this->log_file = $this->log_dir.'/'.self::LOG_FILE;
        $this->activity_file = $this->log_dir.'/'.self::ACTIVITY_FILE;
        $this->ensure_secure_dir();
    }

    /**
     * Returns (and lazily creates) the singleton Logger instance.
     */
    public static function get_instance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    // ---------------------------------------------------------------------------
    // Directory helpers
    // ---------------------------------------------------------------------------

    /**
     * Resolves the absolute path for the log directory.
     *
     * Uses the WordPress uploads directory so that logs are persisted across
     * plugin updates.
     */
    private function resolve_log_dir(): string
    {
        $upload_dir = wp_upload_dir();

        return rtrim($upload_dir['basedir'], '/\\').'/newsblogify-logs';
    }

    /**
     * Creates the log directory and places protective files inside it.
     */
    private function ensure_secure_dir(): void
    {
        if (! is_dir($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }

        // Block direct web access via Apache/LiteSpeed.
        $htaccess = $this->log_dir.'/.htaccess';
        if (! file_exists($htaccess)) {
            file_put_contents($htaccess, "Order Deny,Allow\nDeny from all\n");
        }

        // Prevent directory listing on servers without .htaccess support.
        $index = $this->log_dir.'/index.php';
        if (! file_exists($index)) {
            file_put_contents($index, "<?php // Silence is golden.\n");
        }
    }

    // ---------------------------------------------------------------------------
    // Plain-text log
    // ---------------------------------------------------------------------------

    /**
     * Writes a plain-text log entry to newsblogify.log.
     *
     * Format:  [YYYY-MM-DD HH:MM:SS UTC] [LEVEL] Message
     *
     * @param  string  $level  One of the LOG_* constants.
     * @param  string  $message  Human-readable log message.
     */
    public function log(string $level, string $message): void
    {
        // Rotate the log file if it has grown too large.
        if (file_exists($this->log_file) && filesize($this->log_file) >= self::MAX_LOG_SIZE) {
            $this->rotate_log();
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        $level_uc = strtoupper($level);
        $line = "[{$timestamp} UTC] [{$level_uc}] {$message}".PHP_EOL;

        file_put_contents($this->log_file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Returns the last N lines from the plain-text log file.
     *
     * @param  int  $lines  Number of lines to retrieve. Defaults to 100.
     * @return string[] Array of raw log lines (newest last).
     */
    public function get_logs(int $lines = 100): array
    {
        if (! file_exists($this->log_file)) {
            return [];
        }

        $all = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($all) || empty($all)) {
            return [];
        }

        return array_slice($all, -$lines);
    }

    /**
     * Deletes the plain-text log file.
     */
    public function clear_logs(): void
    {
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
    }

    /**
     * Rotates the log file by renaming the current file with a timestamp suffix.
     */
    private function rotate_log(): void
    {
        $rotated = $this->log_file.'.'.gmdate('YmdHis').'.bak';
        rename($this->log_file, $rotated);
    }

    // ---------------------------------------------------------------------------
    // Structured activity log (JSON Lines)
    // ---------------------------------------------------------------------------

    /**
     * Appends a structured JSON event to activity.jsonl.
     *
     * Each line is a self-contained JSON object with the fields:
     *   timestamp   ISO-8601 UTC timestamp.
     *   event_type  Caller-supplied event identifier (e.g. 'post_published').
     *   message     Human-readable description.
     *   context     Optional key/value metadata supplied by the caller.
     *
     * @param  string  $event_type  Short machine-readable event identifier.
     * @param  string  $message  Human-readable description of the event.
     * @param  array<string, mixed>  $context  Optional additional metadata.
     */
    public function log_activity(string $event_type, string $message, array $context = []): void
    {
        $entry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'event_type' => $event_type,
            'message' => $message,
            'context' => $context,
        ];

        $line = wp_json_encode($entry);
        if ($line === false) {
            // Fallback if encoding fails (e.g. non-UTF-8 strings in context).
            $safe_entry = $entry;
            $safe_entry['context'] = [];
            $line = wp_json_encode($safe_entry);
        }

        file_put_contents($this->activity_file, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Reads the last N activity entries from activity.jsonl.
     *
     * Each entry is decoded from JSON back into an associative array.
     * Malformed lines are silently skipped.
     *
     * @param  int  $limit  Maximum number of entries to return. Defaults to 50.
     * @return array<int, array<string, mixed>> Array of decoded activity entries.
     */
    public function get_activity_log(int $limit = 50): array
    {
        if (! file_exists($this->activity_file)) {
            return [];
        }

        $raw_lines = file($this->activity_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($raw_lines) || empty($raw_lines)) {
            return [];
        }

        // Take the last $limit lines.
        $raw_lines = array_slice($raw_lines, -$limit);

        $entries = [];
        foreach ($raw_lines as $line) {
            $decoded = json_decode(trim($line), true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }

    /**
     * Deletes the activity.jsonl file.
     */
    public function clear_activity_log(): void
    {
        if (file_exists($this->activity_file)) {
            unlink($this->activity_file);
        }
    }

    // ---------------------------------------------------------------------------
    // Utility
    // ---------------------------------------------------------------------------

    /**
     * Returns the absolute path to the log directory.
     *
     * Useful for displaying the log path in the admin UI.
     */
    public function get_log_dir(): string
    {
        return $this->log_dir;
    }
}
