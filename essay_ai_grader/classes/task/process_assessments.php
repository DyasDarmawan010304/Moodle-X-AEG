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

namespace local_essay_ai_grader\task;

defined('MOODLE_INTERNAL') || die();

use \core\task\scheduled_task;
use local_essay_ai_grader\assessment_manager;
use local_essay_ai_grader\logging_manager;
use local_essay_ai_grader\retry_manager;

/**
 * Scheduled task to process pending essay assessments with Gemini AI.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_assessments extends scheduled_task {
    
    /**
     * Get a descriptive name for this task (shown in admin screens).
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_process_assessments', 'local_essay_ai_grader');
    }
    
    /**
     * Executes the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        logging_manager::info('Assessment processing task started');

        // Requeue stale processing records (likely interrupted runs).
        $stuckthreshold = time() - (15 * 60);
        $stuck = $DB->get_records_select('local_essay_ai_assess',
            'status = ? AND timemodified < ?',
            array('processing', $stuckthreshold),
            'timemodified ASC',
            'id',
            0,
            100);
        foreach ($stuck as $stuckrow) {
            $DB->set_field('local_essay_ai_assess', 'status', 'pending', array('id' => $stuckrow->id));
            logging_manager::warning('Stale processing assessment requeued', $stuckrow->id);
        }
        if (!empty($stuck)) {
            mtrace('Requeued ' . count($stuck) . ' stuck processing assessments.');
        }

        // Requeue retryable error assessments so manual queue runs can recover
        // from transient failures such as submission content not being ready yet.
        $errors = $DB->get_records('local_essay_ai_assess', array(
            'status' => 'error',
        ), 'timemodified ASC', '*', 0, 100);
        foreach ($errors as $errorassessment) {
            $retrycheck = retry_manager::should_retry($errorassessment);
            if ($retrycheck['should_retry']) {
                retry_manager::prepare_retry($errorassessment->id, $errorassessment->error_message);
            }
        }

        // Get pending assessments.
        $pending = $DB->get_records('local_essay_ai_assess', array(
            'status' => 'pending',
        ), 'created ASC', '*', 0, 100); // Process max 100 at a time to avoid timeout.
        
        if (empty($pending)) {
            mtrace('No pending assessments to process.');
            logging_manager::info('Assessment processing task completed. No pending assessments.');
            return;
        }
        
        mtrace('Processing ' . count($pending) . ' pending essay assessments...');
        logging_manager::info('Starting processing of ' . count($pending) . ' assessments');
        
        $processed = 0;
        $failed = 0;
        $retried = 0;
        
        foreach ($pending as $assessment) {
            try {
                // Check if this assessment is a retry (has error_message from previous attempt).
                if (!empty($assessment->error_message) && strpos($assessment->error_message, 'Retry attempt #') !== false) {
                    $retry_check = retry_manager::should_retry($assessment);
                    if (!$retry_check['should_retry']) {
                        mtrace('  ⏸ Assessment ' . $assessment->id . ' skipped: ' . $retry_check['message']);
                        logging_manager::debug('Assessment skipped from retry', $assessment->id,
                            array('reason' => $retry_check['message']));
                        continue;
                    }
                    $retried++;
                }
                
                // Update status to processing.
                $processing_update = new \stdClass();
                $processing_update->id = $assessment->id;
                $processing_update->status = 'processing';
                $processing_update->timemodified = time();
                $processing_update->modified = time();
                $DB->update_record('local_essay_ai_assess', $processing_update);
                
                logging_manager::debug('Assessment processing started', $assessment->id);
                
                // Process the assessment.
                if (assessment_manager::process_assessment($assessment->id)) {
                    $processed++;
                    mtrace('  ✓ Assessment ' . $assessment->id . ' completed.');
                    logging_manager::info('Assessment completed successfully', $assessment->id);
                } else {
                    $failed++;
                    $current = $DB->get_record('local_essay_ai_assess', array('id' => $assessment->id),
                        'id,status,error_message', IGNORE_MISSING);
                    if ($current) {
                        if ($current->status === 'pending') {
                            // Transient error - already requeued by assessment_manager
                            mtrace('  ↻ Assessment ' . $assessment->id . ' transient error, queued for retry.');
                            logging_manager::info('Assessment requeued for retry (transient error)', $assessment->id,
                                array('error' => $current->error_message));
                        } else if ($current->status === 'error') {
                            // Permanent error or max retries exceeded
                            mtrace('  ✗ Assessment ' . $assessment->id . ' failed: ' . substr($current->error_message, 0, 50) . '...');
                            logging_manager::warning('Assessment failed', $assessment->id,
                                array('error' => $current->error_message));
                        }
                    } else {
                        mtrace('  ✗ Assessment ' . $assessment->id . ' failed (record not found).');
                        logging_manager::warning('Assessment failed - record not found', $assessment->id);
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                $update = new \stdClass();
                $update->id = $assessment->id;
                $update->status = 'error';
                $update->score = null;
                $update->error_message = $e->getMessage();
                $update->timemodified = time();
                $update->modified = time();
                $DB->update_record('local_essay_ai_assess', $update);
                mtrace('  ✗ Assessment ' . $assessment->id . ' error: ' . $e->getMessage());
                
                logging_manager::log_exception($e, 'Exception in assessment processing', $assessment->id);
            }
        }
        
        // Log completion summary
        $message = 'Assessment processing complete: ' . $processed . ' processed, ' . 
                   $failed . ' failed, ' . $retried . ' retried.';
        mtrace($message);
        logging_manager::info($message);
        
        // Cleanup old logs
        logging_manager::cleanup_old_logs();
    }
}
