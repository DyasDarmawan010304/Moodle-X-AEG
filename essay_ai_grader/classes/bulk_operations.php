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
 * Bulk operations handler for Essay AI Grader.
 * Handles batch acceptance, rejection, and export of assessments.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_operations {
    
    /**
     * Bulk accept AI grades for multiple assessments.
     *
     * @param array $assessmentids Array of assessment IDs
     * @param bool $auto_publish Whether to auto-publish to gradebook
     * @return array Result with success count and errors
     */
    public static function bulk_accept_grades($assessmentids, $auto_publish = true) {
        global $DB, $USER;
        
        if (empty($assessmentids)) {
            return array(
                'success' => false,
                'error' => 'No assessments specified',
                'accepted' => 0,
                'errors' => array(),
            );
        }
        
        $results = array(
            'success' => true,
            'accepted' => 0,
            'skipped' => 0,
            'errors' => array(),
        );
        
        try {
            $assessmentids = array_map('intval', $assessmentids);
            
            // Get all assessments
            list($sql, $params) = $DB->get_in_or_equal($assessmentids, SQL_PARAMS_NAMED);
            $assessments = $DB->get_records_select('local_essay_ai_assess',
                "id {$sql} AND status = 'completed' AND teacher_accepted IS NULL",
                $params
            );
            
            foreach ($assessments as $assessment) {
                try {
                    // Mark as accepted
                    $update = new \stdClass();
                    $update->id = $assessment->id;
                    $update->teacher_accepted = 1;
                    $update->teacher_finalgrade = $assessment->score;
                    $update->timemodified = time();
                    
                    $DB->update_record('local_essay_ai_assess', $update);
                    
                    // Publish to gradebook if enabled
                    if ($auto_publish && $assessment->score !== null) {
                        try {
                            assessment_manager::save_grade_to_assignment(
                                $assessment->assignmentid,
                                $assessment->userid,
                                $assessment->score
                            );
                        } catch (\Throwable $e) {
                            logging_manager::warning('Gradebook publish failed', $assessment->id,
                                array('error' => $e->getMessage()));
                        }
                    }
                    
                    $results['accepted']++;
                    logging_manager::info('Grade bulk-accepted', $assessment->id,
                        array('teacher' => $USER->id, 'auto_publish' => $auto_publish));
                    
                } catch (\Throwable $e) {
                    $results['errors'][] = "Assessment {$assessment->id}: {$e->getMessage()}";
                    logging_manager::error('Bulk accept failed', $assessment->id,
                        array('error' => $e->getMessage()));
                }
            }
            
            $results['skipped'] = count($assessmentids) - $results['accepted'];
            
        } catch (\Throwable $e) {
            $results['success'] = false;
            $results['error'] = "Bulk operation failed: {$e->getMessage()}";
            logging_manager::log_exception($e, 'Bulk accept grades failed');
        }
        
        return $results;
    }
    
    /**
     * Bulk reject AI grades (revert to teacher grading).
     *
     * @param array $assessmentids Array of assessment IDs
     * @param string $reason Rejection reason
     * @return array Result
     */
    public static function bulk_reject_grades($assessmentids, $reason = '') {
        global $DB, $USER;
        
        if (empty($assessmentids)) {
            return array(
                'success' => false,
                'error' => 'No assessments specified',
                'rejected' => 0,
            );
        }
        
        $results = array(
            'success' => true,
            'rejected' => 0,
            'errors' => array(),
        );
        
        try {
            $assessmentids = array_map('intval', $assessmentids);
            
            // Get all assessments
            list($sql, $params) = $DB->get_in_or_equal($assessmentids, SQL_PARAMS_NAMED);
            $assessments = $DB->get_records_select('local_essay_ai_assess',
                "id {$sql}",
                $params
            );
            
            $reason = !empty($reason) ? " Reason: {$reason}" : '';
            
            foreach ($assessments as $assessment) {
                try {
                    // Mark as not accepted
                    $update = new \stdClass();
                    $update->id = $assessment->id;
                    $update->teacher_accepted = 0;
                    $update->teacher_feedback = ($assessment->teacher_feedback ?? '') . 
                        "\n\n[Rejected by {$USER->firstname} {$USER->lastname}]{$reason}";
                    $update->timemodified = time();
                    
                    $DB->update_record('local_essay_ai_assess', $update);
                    
                    $results['rejected']++;
                    logging_manager::info('Grade bulk-rejected', $assessment->id,
                        array('teacher' => $USER->id, 'reason' => $reason));
                    
                } catch (\Throwable $e) {
                    $results['errors'][] = "Assessment {$assessment->id}: {$e->getMessage()}";
                    logging_manager::error('Bulk reject failed', $assessment->id);
                }
            }
            
        } catch (\Throwable $e) {
            $results['success'] = false;
            $results['error'] = "Bulk rejection failed: {$e->getMessage()}";
            logging_manager::log_exception($e, 'Bulk reject grades failed');
        }
        
        return $results;
    }
    
    /**
     * Export assessments to CSV format.
     *
     * @param array $assessmentids Array of assessment IDs or empty for all
     * @param int|null $assignmentid Filter by assignment  
     * @param string $period Filter by time period
     * @return string CSV content
     */
    public static function export_to_csv($assessmentids = array(), $assignmentid = null, $period = 'all') {
        global $DB;
        
        // Build query
        $where = '1=1';
        $params = array();
        
        if (!empty($assessmentids)) {
            $assessmentids = array_map('intval', $assessmentids);
            list($sql, $in_params) = $DB->get_in_or_equal($assessmentids, SQL_PARAMS_NAMED);
            $where .= " AND id {$sql}";
            $params = array_merge($params, $in_params);
        }
        
        if ($assignmentid !== null) {
            $where .= ' AND assignmentid = ?';
            $params[] = (int)$assignmentid;
        }
        
        // Time filter
        $cutoff = self::get_period_cutoff($period);
        if ($cutoff > 0) {
            $where .= ' AND timecreated >= ?';
            $params[] = $cutoff;
        }
        
        $assessments = $DB->get_records_select('local_essay_ai_assess', $where, $params);
        
        // Build CSV
        $csv = "Assessment ID,Assignment ID,Student ID,Status,AI Score,Teacher Grade,Accepted,Created Date\n";
        
        foreach ($assessments as $assessment) {
            $status = $assessment->status;
            $ai_score = $assessment->score !== null ? number_format($assessment->score, 2) : 'N/A';
            $teacher_grade = $assessment->teacher_finalgrade !== null ? 
                number_format($assessment->teacher_finalgrade, 2) : 'N/A';
            $accepted = $assessment->teacher_accepted === 1 ? 'Yes' : 
                       ($assessment->teacher_accepted === 0 ? 'No' : 'Pending');
            $created = date('Y-m-d H:i:s', $assessment->timecreated);
            
            $csv .= "{$assessment->id},{$assessment->assignmentid}," .
                   "{$assessment->userid},{$status}," .
                   "{$ai_score},{$teacher_grade},{$accepted},{$created}\n";
        }
        
        return $csv;
    }
    
    /**
     * Bulk update feedback for multiple assessments.
     *
     * @param array $assessmentids Array of assessment IDs
     * @param string $feedback Additional feedback to append
     * @return array Result
     */
    public static function bulk_add_feedback($assessmentids, $feedback) {
        global $DB;
        
        if (empty($assessmentids) || empty($feedback)) {
            return array(
                'success' => false,
                'error' => 'No assessments or feedback provided',
                'updated' => 0,
            );
        }
        
        $results = array(
            'success' => true,
            'updated' => 0,
            'errors' => array(),
        );
        
        try {
            $assessmentids = array_map('intval', $assessmentids);
            $feedback = '\n\n[Bulk feedback]: ' . $feedback;
            
            foreach ($assessmentids as $id) {
                try {
                    $assessment = $DB->get_record('local_essay_ai_assess', array('id' => $id));
                    if (!$assessment) {
                        continue;
                    }
                    
                    $update = new \stdClass();
                    $update->id = $id;
                    $update->teacher_feedback = ($assessment->teacher_feedback ?? '') . $feedback;
                    $update->timemodified = time();
                    
                    $DB->update_record('local_essay_ai_assess', $update);
                    $results['updated']++;
                    
                } catch (\Throwable $e) {
                    $results['errors'][] = "Assessment {$id}: {$e->getMessage()}";
                }
            }
            
        } catch (\Throwable $e) {
            $results['success'] = false;
            $results['error'] = "Bulk feedback update failed: {$e->getMessage()}";
        }
        
        return $results;
    }
    
    /**
     * Get time filter cutoff.
     *
     * @param string $period
     * @return int Unix timestamp or 0 for no filter
     */
    private static function get_period_cutoff($period) {
        switch (strtolower($period)) {
            case 'today':
                return strtotime('today midnight');
            case 'week':
                return time() - (7 * 24 * 60 * 60);
            case 'month':
                return time() - (30 * 24 * 60 * 60);
            case 'all':
            default:
                return 0;
        }
    }
}
