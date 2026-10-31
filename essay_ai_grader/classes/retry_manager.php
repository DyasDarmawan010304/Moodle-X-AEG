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
 * Retry manager for failed assessments.
 * Implements exponential backoff algorithm for robust error recovery.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retry_manager {
    
    const MAX_RETRIES = 5;
    const INITIAL_BACKOFF_SECONDS = 60;      // 1 minute
    const MAX_BACKOFF_SECONDS = 3600;         // 1 hour
    const BACKOFF_MULTIPLIER = 2;             // Exponential: 1m, 2m, 4m, 8m, 16m, 32m...
    
    /**
     * Check if an assessment should be retried based on failure count and backoff timing.
     *
     * @param object $assessment Assessment record from local_essay_ai_assess
     * @return array Array with 'should_retry' bool and 'message' string
     */
    public static function should_retry($assessment) {
        // Count is tracked in a custom field (we'll add this to DB if needed)
        $retry_count = self::get_retry_count($assessment->id);
        $nextattempt = $retry_count + 1;
        
        // Check max retries
        if ($retry_count >= self::MAX_RETRIES) {
            return array(
                'should_retry' => false,
                'message' => "Max retries ({$retry_count}) exceeded",
            );
        }
        
        // Calculate backoff time
        $backoff_seconds = self::calculate_backoff($retry_count);
        $time_since_last_attempt = time() - $assessment->timemodified;
        
        if ($time_since_last_attempt < $backoff_seconds) {
            $wait_seconds = $backoff_seconds - $time_since_last_attempt;
            return array(
                'should_retry' => false,
                'message' => "Backoff in progress. Retry in {$wait_seconds} seconds",
                'next_retry_time' => $assessment->timemodified + $backoff_seconds,
            );
        }
        
        return array(
            'should_retry' => true,
            'message' => "Ready to retry. Attempt #{$nextattempt}",
        );
    }
    
    /**
     * Get current retry count for an assessment.
     * Counts error state records with same submissionid.
     *
     * @param int $assessmentid
     * @return int
     */
    public static function get_retry_count($assessmentid) {
        global $DB;
        
        $assessment = $DB->get_record('local_essay_ai_assess', array('id' => $assessmentid), 'error_message');
        if (!$assessment || empty($assessment->error_message)) {
            return 0;
        }
        
        // Count how many "Retry attempt #" occurrences in error_message to track actual retry count
        $pattern_count = substr_count($assessment->error_message, 'Retry attempt #');
        
        // Return the number of actual retry attempts
        return (int)$pattern_count;
    }
    
    /**
     * Calculate backoff time using exponential backoff formula.
     *
     * @param int $retry_count Number of retries attempted so far
     * @return int Backoff time in seconds
     */
    private static function calculate_backoff($retry_count) {
        $backoff = self::INITIAL_BACKOFF_SECONDS * pow(self::BACKOFF_MULTIPLIER, $retry_count);
        // Cap at max backoff time
        return min((int)$backoff, self::MAX_BACKOFF_SECONDS);
    }
    
    /**
     * Prepare an assessment for retry.
     *
     * @param int $assessmentid Assessment ID
     * @param string|null $error_reason Reason for error (for audit trail)
     * @return bool Success
     */
    public static function prepare_retry($assessmentid, $error_reason = null) {
        global $DB;
        
        try {
            $assessment = $DB->get_record('local_essay_ai_assess', array('id' => $assessmentid));
            if (!$assessment) {
                return false;
            }
            
            $retry_count = self::get_retry_count($assessmentid);
            $nextattempt = $retry_count + 1;
            
            if ($retry_count >= self::MAX_RETRIES) {
                logging_manager::warning(
                    "Assessment marked for final failure. Max retries exceeded.",
                    $assessmentid,
                    array('retry_count' => $retry_count, 'reason' => $error_reason)
                );
                return false;
            }
            
            // Mark as pending for next retry
            $update = new \stdClass();
            $update->id = $assessmentid;
            $update->status = 'pending';
            $update->timemodified = time();
            $update->modified = time();
            $update->error_message = "Retry attempt #{$nextattempt}";

            if ($error_reason) {
                $update->error_message .= " Reason: " . $error_reason;
            }
            
            $DB->update_record('local_essay_ai_assess', $update);
            
            logging_manager::info(
                "Assessment queued for retry",
                $assessmentid,
                array(
                    'retry_count' => $nextattempt,
                    'max_retries' => self::MAX_RETRIES,
                    'next_backoff_seconds' => self::calculate_backoff($nextattempt),
                    'reason' => $error_reason
                )
            );
            
            return true;
            
        } catch (\Throwable $e) {
            logging_manager::error("Failed to prepare retry", $assessmentid,
                array('error' => $e->getMessage()));
            return false;
        }
    }
    
    /**
     * Mark assessment as permanently failed (max retries exceeded).
     *
     * @param int $assessmentid
     * @param string $final_error Error message
     * @return void
     */
    public static function mark_final_failure($assessmentid, $final_error) {
        global $DB;
        
        try {
            $update = new \stdClass();
            $update->id = $assessmentid;
            $update->status = 'error';
            $update->error_message = $final_error . ' [FINAL: Max retries exceeded]';
            $update->timemodified = time();
            
            $DB->update_record('local_essay_ai_assess', $update);
            
            logging_manager::critical(
                "Assessment permanently failed after max retries",
                $assessmentid,
                array('final_error' => $final_error)
            );
            
        } catch (\Throwable $e) {
            logging_manager::error("Failed to mark final failure", $assessmentid);
        }
    }
    
    /**
     * Get retry statistics for monitoring/dashboard.
     *
     * @return array Statistics
     */
    public static function get_retry_statistics() {
        global $DB;
        
        // Count assessments in retry state
        $retrying = $DB->count_records_select('local_essay_ai_assess',
            'status = ? AND error_message LIKE ?',
            array('pending', '%Retry%')
        );
        
        // Count permanently failed
        $failed = $DB->count_records_select('local_essay_ai_assess',
            'status = ? AND error_message LIKE ?',
            array('error', '%FINAL%')
        );
        
        // Count total errors
        $total_errors = $DB->count_records('local_essay_ai_assess', array('status' => 'error'));
        
        return array(
            'currently_retrying' => $retrying,
            'permanently_failed' => $failed,
            'total_errors' => $total_errors,
            'max_retries_configured' => self::MAX_RETRIES,
        );
    }
}
