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
 * AJAX endpoint for bulk accepting assessment grades
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
$assessmentids = required_param('assessmentids', PARAM_TEXT);
$sesskey = optional_param('sesskey', '', PARAM_TEXT);

// Verify session key for CSRF protection
if (!confirm_sesskey($sesskey)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => get_string('error_invalid_session', 'local_essay_ai_grader', 'Invalid session token'),
    ]);
    exit;
}

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

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
    $ids = array_filter(array_map(function($id) {
        $id = intval(trim($id));
        return $id > 0 ? $id : null;
    }, explode(',', $assessmentids)));
    
    if (empty($ids)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No valid assessment IDs provided',
        ]);
        exit;
    }
    
    // Limit to 500 items per request to prevent abuse
    if (count($ids) > 500) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Too many assessments (max 500 per request)',
        ]);
        exit;
    }
    
    // Use bulk_operations to accept multiple grades
    $report = \local_essay_ai_grader\bulk_operations::accept_multiple_grades(
        $ids,
        $assignmentid,
        $context
    );
    
    // Return success response with report
    echo json_encode([
        'success' => true,
        'message' => get_string('assessment_accepted_bulk', 'local_essay_ai_grader', $report['accepted']),
        'report' => [
            'total' => $report['total'],
            'accepted' => $report['accepted'],
            'failed' => $report['failed'],
            'already_processed' => $report['already_processed'] ?? 0,
        ],
    ]);
    
} catch (required_param_missing $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameter: ' . $e->getMessage(),
    ]);
} catch (require_login_exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required',
    ]);
} catch (required_capability_exception $e) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'You do not have permission to grade this assignment',
    ]);
} catch (dml_missing_record_exception $e) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Assignment not found',
    ]);
} catch (\Throwable $e) {
    // Log the error for debugging
    \local_essay_ai_grader\logging_manager::log_exception(
        $e,
        'AJAX bulk_accept endpoint error',
        0
    );
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
    ]);
}
