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

/**
 * AJAX endpoint for exporting assessments to CSV
 *
 * @package    local_essay_ai_grader
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/essay_ai_grader/classes/bulk_operations.php');

require_login();

// Get parameters
$assignmentid = required_param('assignmentid', PARAM_INT);
$assessmentids = optional_param('assessmentids', '', PARAM_TEXT);
$sesskey = optional_param('sesskey', '', PARAM_TEXT);

// Verify session key for CSRF protection
if (!confirm_sesskey($sesskey)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Invalid session token';
    exit;
}

try {
    // Get assignment and verify it exists
    global $DB;
    $assignment = $DB->get_record('assign', array('id' => $assignmentid), '*', MUST_EXIST);
    
    // Get course module
    $cm = get_coursemodule_from_instance('assign', $assignmentid, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    
    // Check capability to grade this assignment
    require_capability('mod/assign:grade', $context);
    
    // Parse assessment IDs from comma-separated string
    $ids = array();
    if (!empty($assessmentids)) {
        $ids = array_filter(array_map(function($id) {
            $id = intval(trim($id));
            return $id > 0 ? $id : null;
        }, explode(',', $assessmentids)));
    }
    
    // Build filters based on specified IDs or all for assignment
    $filters = array();
    if (!empty($ids)) {
        $filters['assessment_ids'] = $ids;
    } else {
        $filters['assignmentid'] = $assignmentid;
    }
    
    // Limit to 500 items per request to prevent abuse (or 5000 if all for assignment)
    if (!empty($ids) && count($ids) > 500) {
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Too many assessments (max 500 per request)';
        exit;
    }
    
    // Get CSV content from bulk_operations
    $csv_content = \local_essay_ai_grader\bulk_operations::export_assessments_csv(
        $assignmentid,
        $filters
    );
    
    if (empty($csv_content)) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'No assessments found to export';
        exit;
    }
    
    // Send CSV file as download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="assessments_export_' . date('YmdHis') . '.csv"');
    header('Content-Length: ' . strlen($csv_content));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output BOM for Excel UTF-8 compatibility
    echo "\xEF\xBB\xBF";
    
    // Output CSV content
    echo $csv_content;
    exit;
    
} catch (required_param_missing $e) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Missing required parameter: ' . $e->getMessage();
} catch (require_login_exception $e) {
    http_response_code(401);
    header('Content-Type: text/plain');
    echo 'Authentication required';
} catch (required_capability_exception $e) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'You do not have permission to export this data';
} catch (dml_missing_record_exception $e) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Assignment not found';
} catch (\Throwable $e) {
    // Log the error for debugging
    \local_essay_ai_grader\logging_manager::log_exception(
        $e,
        'AJAX export_csv endpoint error',
        0
    );
    
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Server error: ' . $e->getMessage();
}
