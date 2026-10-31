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
 * Rate limiting manager for API calls.
 * Implements token bucket algorithm to prevent exceeding API quotas.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter {
    
    /**
     * Check if an API call can be made within rate limits.
     *
     * @param string $provider The API provider (gemini|ollama|etc)
     * @param int $required_tokens Tokens required for this call (default: 1)
     * @return array Array with 'allowed' bool and 'message' string
     */
    public static function check_rate_limit($provider, $required_tokens = 1) {
        global $DB;
        
        $provider = strtolower(trim((string)$provider));
        
        // Get rate limit settings from config
        $settings = self::get_provider_settings($provider);
        
        if (!$settings['enabled']) {
            return array('allowed' => true, 'message' => 'Rate limiting disabled for this provider');
        }
        
        $max_requests_per_hour = $settings['max_requests_per_hour'];
        $max_daily_requests = $settings['max_daily_requests'];
        
        // Check hourly limit
        $hour_ago = time() - 3600;
        $hourly_count = $DB->count_records_select('local_essay_ai_usage',
            'apitype = ? AND timecreated > ?',
            array($provider, $hour_ago)
        );
        
        if ($hourly_count >= $max_requests_per_hour) {
            $message = "Hourly rate limit exceeded for {$provider}. " .
                      "Max: {$max_requests_per_hour}, Already used: {$hourly_count}";
            logging_manager::warning($message, null, array('provider' => $provider));
            return array('allowed' => false, 'message' => $message);
        }
        
        // Check daily limit
        $day_ago = time() - (24 * 3600);
        $daily_count = $DB->count_records_select('local_essay_ai_usage',
            'apitype = ? AND timecreated > ?',
            array($provider, $day_ago)
        );
        
        if ($daily_count >= $max_daily_requests) {
            $message = "Daily rate limit exceeded for {$provider}. " .
                      "Max: {$max_daily_requests}, Already used: {$daily_count}";
            logging_manager::warning($message, null, array('provider' => $provider));
            return array('allowed' => false, 'message' => $message);
        }
        
        return array('allowed' => true, 'message' => 'Rate limit check passed');
    }
    
    /**
     * Record API usage for rate limiting tracking.
     *
     * @param string $provider The API provider
     * @param int|null $assessmentid Assessment ID if applicable
     * @param int $tokens_used Number of tokens/credits consumed
     * @return void
     */
    public static function record_usage($provider, $assessmentid = null, $tokens_used = 1) {
        global $DB, $USER;
        
        try {
            $record = new \stdClass();
            $record->userid = $USER->id ?? 0;
            $record->assessmentid = $assessmentid;
            $record->apitype = strtolower(trim((string)$provider));
            $record->tokensused = (int)$tokens_used;
            $record->timecreated = time();
            
            $DB->insert_record('local_essay_ai_usage', $record);
            
        } catch (\Throwable $e) {
            logging_manager::warning('Failed to record API usage', $assessmentid,
                array('provider' => $provider, 'error' => $e->getMessage()));
        }
    }
    
    /**
     * Get rate limit settings for a provider from configuration.
     *
     * @param string $provider
     * @return array Settings including 'enabled', 'max_requests_per_hour', 'max_daily_requests'
     */
    private static function get_provider_settings($provider) {
        $provider = strtolower(trim((string)$provider));
        
        // Default settings
        $defaults = array(
            'enabled' => (bool)get_config('local_essay_ai_grader', 'enable_rate_limiting'),
            'max_requests_per_hour' => (int)(get_config('local_essay_ai_grader', 'rate_limit_hourly') ?: 300),
            'max_daily_requests' => (int)(get_config('local_essay_ai_grader', 'rate_limit_daily') ?: 5000),
        );
        
        // Provider-specific overrides
        if ($provider === 'gemini') {
            $defaults['max_requests_per_hour'] = (int)(get_config('local_essay_ai_grader', 'gemini_rate_limit_hourly') ?: 300);
            $defaults['max_daily_requests'] = (int)(get_config('local_essay_ai_grader', 'gemini_rate_limit_daily') ?: 5000);
        }
        
        if ($provider === 'ollama') {
            // Ollama has no rate limits (local) but we track for audit
            $defaults['enabled'] = false;
        }
        
        return $defaults;
    }
    
    /**
     * Get current usage statistics.
     *
     * @param string|null $provider Filter by provider, null for all
     * @return array Usage statistics
     */
    public static function get_usage_stats($provider = null) {
        global $DB;
        
        $where = '';
        $params = array();
        
        if ($provider !== null) {
            $where = 'apitype = ?';
            $params[] = strtolower(trim((string)$provider));
        }
        
        // Hourly stats
        $hour_ago = time() - 3600;
        $hourly = $DB->count_records_select('local_essay_ai_usage',
            ($where ? $where . ' AND ' : '') . 'timecreated > ?',
            array_merge($params, array($hour_ago))
        );
        
        // Daily stats
        $day_ago = time() - (24 * 3600);
        $daily = $DB->count_records_select('local_essay_ai_usage',
            ($where ? $where . ' AND ' : '') . 'timecreated > ?',
            array_merge($params, array($day_ago))
        );
        
        // Total tokens/credits
        $sql = 'SELECT COUNT(*) as count, COALESCE(SUM(tokensused), 0) as tokens ' .
               'FROM {local_essay_ai_usage} ' .
               ($where ? 'WHERE ' . $where : '');
        $totals = $DB->get_record_sql($sql, $params);
        
        return array(
            'hourly_requests' => $hourly,
            'daily_requests' => $daily,
            'total_requests' => $totals->count ?? 0,
            'total_tokens' => $totals->tokens ?? 0,
        );
    }
    
    /**
     * Reset usage statistics (for testing or admin action).
     *
     * @param string|null $provider Reset specific provider or all
     * @return void
     */
    public static function reset_usage_stats($provider = null) {
        global $DB;
        
        if ($provider === null) {
            $DB->delete_records('local_essay_ai_usage');
            logging_manager::info('All API usage statistics reset');
        } else {
            $DB->delete_records('local_essay_ai_usage', array('apitype' => strtolower((string)$provider)));
            logging_manager::info("API usage statistics reset for provider: {$provider}");
        }
    }
}
