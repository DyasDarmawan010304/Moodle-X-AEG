<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_essay_ai_grader;

defined('MOODLE_INTERNAL') || die();

/**
 * Comprehensive logging manager for Essay AI Grader.
 * Handles detailed error logging, audit trails, and debugging.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logging_manager {
    
    const LOG_LEVEL_DEBUG = 'DEBUG';
    const LOG_LEVEL_INFO = 'INFO';
    const LOG_LEVEL_WARNING = 'WARNING';
    const LOG_LEVEL_ERROR = 'ERROR';
    const LOG_LEVEL_CRITICAL = 'CRITICAL';
    
    private static $logfile = null;
    private static $enabled = null;
    
    /**
     * Initialize logging with proper log file path.
     *
     * @return string Path to log file
     */
    private static function get_log_file() {
        if (self::$logfile === null) {
            global $CFG;
            
            // Create or get logs directory
            $logdir = $CFG->dataroot . '/essay_ai_grader_logs';
            if (!is_dir($logdir)) {
                mkdir($logdir, 0755, true);
            }
            
            // Create daily log file
            $logfile = $logdir . '/essay_ai_' . date('Y-m-d') . '.log';
            self::$logfile = $logfile;
        }
        
        return self::$logfile;
    }
    
    /**
     * Check if logging is enabled.
     *
     * @return bool
     */
    private static function is_enabled() {
        if (self::$enabled === null) {
            self::$enabled = (bool)get_config('local_essay_ai_grader', 'enable_logging');
        }
        return self::$enabled;
    }
    
    /**
     * Write log entry to file and Moodle debug log.
     *
     * @param string $level Log level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
     * @param string $message Log message
     * @param int|null $assessmentid Assessment ID if applicable
     * @param array $context Additional context data
     * @return void
     */
    public static function log($level, $message, $assessmentid = null, $context = array()) {
        if (!self::is_enabled()) {
            return;
        }
        
        try {
            // Format log entry
            $timestamp = date('Y-m-d H:i:s');
            $user = $GLOBALS['USER']->id ?? 0;
            $assessmentstr = $assessmentid ? " [Assessment: {$assessmentid}]" : '';
            $contextstr = !empty($context) ? ' [Context: ' . json_encode($context) . ']' : '';
            
            $logline = "[{$timestamp}] [{$level}] User:{$user}{$assessmentstr} - {$message}{$contextstr}";
            
            // Write to log file
            $logfile = self::get_log_file();
            @error_log($logline . PHP_EOL, 3, $logfile);
            
            // Also send to Moodle debugging
            $debug_level = self::get_debug_level($level);
            if ($debug_level !== null) {
                debugging($message . $contextstr, $debug_level);
            }
            
        } catch (\Throwable $e) {
            // Fail silently to avoid breaking assessment processing
            error_log('Logging manager error: ' . $e->getMessage());
        }
    }
    
    /**
     * Map log level to Moodle debug level.
     *
     * @param string $level
     * @return int|null
     */
    private static function get_debug_level($level) {
        $map = array(
            self::LOG_LEVEL_DEBUG => DEBUG_DEVELOPER,
            self::LOG_LEVEL_INFO => DEBUG_NORMAL,
            self::LOG_LEVEL_WARNING => DEBUG_NORMAL,
            self::LOG_LEVEL_ERROR => DEBUG_NORMAL,
            self::LOG_LEVEL_CRITICAL => DEBUG_ALL,
        );
        return $map[$level] ?? null;
    }
    
    /**
     * Log debug message.
     *
     * @param string $message
     * @param int|null $assessmentid
     * @param array $context
     * @return void
     */
    public static function debug($message, $assessmentid = null, $context = array()) {
        self::log(self::LOG_LEVEL_DEBUG, $message, $assessmentid, $context);
    }
    
    /**
     * Log info message.
     *
     * @param string $message
     * @param int|null $assessmentid
     * @param array $context
     * @return void
     */
    public static function info($message, $assessmentid = null, $context = array()) {
        self::log(self::LOG_LEVEL_INFO, $message, $assessmentid, $context);
    }
    
    /**
     * Log warning message.
     *
     * @param string $message
     * @param int|null $assessmentid
     * @param array $context
     * @return void
     */
    public static function warning($message, $assessmentid = null, $context = array()) {
        self::log(self::LOG_LEVEL_WARNING, $message, $assessmentid, $context);
    }
    
    /**
     * Log error message.
     *
     * @param string $message
     * @param int|null $assessmentid
     * @param array $context
     * @return void
     */
    public static function error($message, $assessmentid = null, $context = array()) {
        self::log(self::LOG_LEVEL_ERROR, $message, $assessmentid, $context);
    }
    
    /**
     * Log critical error message.
     *
     * @param string $message
     * @param int|null $assessmentid
     * @param array $context
     * @return void
     */
    public static function critical($message, $assessmentid = null, $context = array()) {
        self::log(self::LOG_LEVEL_CRITICAL, $message, $assessmentid, $context);
    }
    
    /**
     * Log exception with full context.
     *
     * @param \Throwable $exception
     * @param string $context_message Additional context message
     * @param int|null $assessmentid
     * @return void
     */
    public static function log_exception(\Throwable $exception, $context_message = '', $assessmentid = null) {
        $context = array(
            'exception_type' => get_class($exception),
            'error_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        );
        
        $message = 'Exception: ' . $exception->getMessage();
        if (!empty($context_message)) {
            $message .= ' [Context: ' . $context_message . ']';
        }
        
        self::log(self::LOG_LEVEL_ERROR, $message, $assessmentid, $context);
    }
    
    /**
     * Get recent logs for display in monitoring dashboard.
     *
     * @param int $lines Number of lines to retrieve
     * @return array
     */
    public static function get_recent_logs($lines = 50) {
        $logfile = self::get_log_file();
        
        if (!file_exists($logfile)) {
            return array();
        }
        
        try {
            $content = file_get_contents($logfile);
            $entries = array_reverse(explode(PHP_EOL, $content));
            return array_splice($entries, 0, $lines);
        } catch (\Throwable $e) {
            return array();
        }
    }
    
    /**
     * Clean old log files (older than 30 days).
     *
     * @return void
     */
    public static function cleanup_old_logs() {
        try {
            global $CFG;
            $logdir = $CFG->dataroot . '/essay_ai_grader_logs';
            
            if (!is_dir($logdir)) {
                return;
            }
            
            $cutoff = time() - (30 * 24 * 60 * 60); // 30 days
            $files = glob($logdir . '/*.log');
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    @unlink($file);
                }
            }
        } catch (\Throwable $e) {
            // Fail silently
        }
    }
}
