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
 * Review AI Assessments for an Assignment
 * Lists all assessments for a given assignment so teachers can review and validate them.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

global $DB, $OUTPUT, $PAGE, $USER;

$cmid = required_param('aid', PARAM_INT);

// Get course module
$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$assignment = $DB->get_record('assign', array('id' => $cm->instance), '*', MUST_EXIST);
$assignmentid = $assignment->id;

// Check permissions
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/assign:grade', $context);

// Setup page
$PAGE->set_url(new moodle_url('/local/essay_ai_grader/review_assessments.php', array('aid' => $cmid)));
$PAGE->set_title('Review AI Assessments - ' . $assignment->name);
$PAGE->set_heading('Review AI Assessments - ' . $assignment->name);
$PAGE->set_context($context);

// Handle form submission for approval/rejection
$action = optional_param('action', '', PARAM_ALPHA);
$assessmentid = optional_param('assessmentid', 0, PARAM_INT);
$gradeparam = optional_param('grade', '', PARAM_RAW);

if ($action && $assessmentid && confirm_sesskey()) {
    if ($action === 'approve') {
        try {
            // Approve the AI grade
            $assessment = $DB->get_record('local_essay_ai_assess', 
                array('id' => $assessmentid, 'assignmentid' => $assignmentid), '*', MUST_EXIST);
            
            $update = new stdClass();
            $update->id = $assessmentid;
            $update->status = 'approved';
            $update->approver_id = $USER->id;
            $update->approval_time = time();
            $update->timemodified = time();
            $DB->update_record('local_essay_ai_assess', $update);
            
            // Update assignment grade
            update_assignment_grade($assignment, $assessment->userid, floatval($assessment->score), 
                'AI Grade Approved', $context);
            
            redirect(new moodle_url('/local/essay_ai_grader/review_assessments.php', array('aid' => $cmid)),
                'Grade approved successfully!', null, 'success');
        } catch (Exception $e) {
            redirect(new moodle_url('/local/essay_ai_grader/review_assessments.php', array('aid' => $cmid)),
                'Error: ' . $e->getMessage(), null, 'error');
        }
    } else if ($action === 'reject' && !empty($gradeparam)) {
        try {
            $gradevalue = floatval($gradeparam);
            if ($gradevalue < 0) $gradevalue = 0;
            if ($gradevalue > 100) $gradevalue = 100;
            
            // Reject AI grade and use manual grade
            $assessment = $DB->get_record('local_essay_ai_assess',
                array('id' => $assessmentid, 'assignmentid' => $assignmentid), '*', MUST_EXIST);
            
            $update = new stdClass();
            $update->id = $assessmentid;
            $update->status = 'rejected';
            $update->override_grade = $gradevalue;
            $update->approver_id = $USER->id;
            $update->approval_time = time();
            $update->timemodified = time();
            $DB->update_record('local_essay_ai_assess', $update);
            
            // Update assignment grade with manual override
            update_assignment_grade($assignment, $assessment->userid, $gradevalue,
                'Manual Grade Override', $context);
            
            redirect(new moodle_url('/local/essay_ai_grader/review_assessments.php', array('aid' => $cmid)),
                'Grade override saved successfully!', null, 'success');
        } catch (Exception $e) {
            redirect(new moodle_url('/local/essay_ai_grader/review_assessments.php', array('aid' => $cmid)),
                'Error: ' . $e->getMessage(), null, 'error');
        }
    }
}

/**
 * Update assignment grade in gradebook
 */
function update_assignment_grade($assignment, $userid, $grade, $feedback = '', $context = null) {
    global $DB, $CFG;
    
    try {
        $grademax = isset($assignment->grade) ? $assignment->grade : 100;
        
        // Convert percentage to assignment grade scale
        if ($grademax > 0) {
            $actualgrade = ($grade / 100) * $grademax;
        } else {
            $actualgrade = $grade;
        }
        
        // Get or create grade record
        $graderecord = $DB->get_record('assign_grades',
            array('assignment' => $assignment->id, 'userid' => $userid));
        
        if (!$graderecord) {
            $graderecord = new stdClass();
            $graderecord->assignment = $assignment->id;
            $graderecord->userid = $userid;
            $graderecord->timecreated = time();
        }
        
        $graderecord->grade = $actualgrade;
        $graderecord->grader = 0;
        $graderecord->timemodified = time();
        $graderecord->feedbacktext = $feedback;
        $graderecord->feedbackformat = FORMAT_HTML;
        
        if (isset($graderecord->id)) {
            $DB->update_record('assign_grades', $graderecord);
        } else {
            $DB->insert_record('assign_grades', $graderecord);
        }
        
        // Update gradebook
        require_once($CFG->libdir . '/gradelib.php');
        $grades = array();
        $grades[$userid] = new stdClass();
        $grades[$userid]->rawgrade = $actualgrade;
        $grades[$userid]->feedback = $feedback;
        
        grade_update('mod/assign', $assignment->course, 'mod', 'assign',
            $assignment->id, 0, $grades);
        
        return true;
    } catch (Exception $e) {
        debugging('Error updating grade: ' . $e->getMessage());
        return false;
    }
}

echo $OUTPUT->header();

// Get assessments - simple and safe query
$sql = "SELECT a.id, a.userid, a.submissionid, a.status, a.score, a.feedback, 
               a.override_grade, a.approver_id, a.approval_time,
               u.firstname, u.lastname
        FROM {local_essay_ai_assess} a
        JOIN {user} u ON u.id = a.userid
        WHERE a.assignmentid = ?
        ORDER BY u.lastname, u.firstname";

$assessments = $DB->get_records_sql($sql, array($assignmentid));



if (empty($assessments)) {
    echo html_writer::div('No AI assessments found for this assignment.', 'alert alert-info');
} else {
    echo '<div class="assessment-list">';
    
    foreach ($assessments as $assess) {
        // Normalize status - if NULL or anything else, treat as pending
        $status = $assess->status;
        if (empty($status)) {
            $status = 'pending';
        }
        
        $statusbadge = '';
        $actionbuttons = '';
        
        if ($status === 'approved') {
            $statusbadge = '<span class="badge badge-success">✓ Approved</span>';
        } else if ($status === 'rejected') {
            $statusbadge = '<span class="badge badge-danger">✗ Rejected</span>';
        } else {
            $statusbadge = '<span class="badge badge-warning">⏳ Pending</span>';
        }
        
        // Build action buttons - show for pending approval
        if ($status !== 'approved' && $status !== 'rejected') {
            // Pending status - show Approve and Override buttons
            $approvebtn = '<a class="btn btn-sm btn-success" href="' . 
                new moodle_url('/local/essay_ai_grader/review_assessments.php',
                    array('aid' => $cmid, 'action' => 'approve', 'assessmentid' => $assess->id, 
                          'sesskey' => sesskey())) . '" style="margin-right: 5px;">✓ Approve Grade</a>';
            
            $rejectbtn = '<button class="btn btn-sm btn-danger" type="button" 
                onclick="showGradeForm(' . $assess->id . ')">✗ Override Grade</button>';
            
            $actionbuttons = $approvebtn . $rejectbtn;
        }
        
        // Student name
        $studentname = $assess->firstname . ' ' . $assess->lastname;
        
        echo '<div class="assessment-item" style="border-left: 4px solid #' . 
             ($status === 'approved' ? '28a745' : ($status === 'rejected' ? 'dc3545' : 'ffc107')) . 
             '; margin: 15px 0; padding: 15px; border-radius: 4px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
        
        // Header with name and status
        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
        echo '<h4 style="margin: 0; font-size: 18px;">' . htmlspecialchars($studentname) . '</h4>';
        echo $statusbadge;
        echo '</div>';
        
        // Grade info
        echo '<div style="margin-bottom: 15px; background: #f9f9f9; padding: 12px; border-radius: 3px;">';
        echo '<strong>AI Grade:</strong> <span style="font-size: 1.2em; color: #0066cc; font-weight: bold;">' . 
             htmlspecialchars($assess->score) . '%</span><br>';
        if ($assess->override_grade !== null && $assess->override_grade !== '') {
            echo '<strong>Final Grade (Override):</strong> <span style="font-size: 1.2em; color: #009900; font-weight: bold;">' . 
                 htmlspecialchars($assess->override_grade) . '%</span><br>';
        }
        echo '</div>';
        
        // Feedback section
        echo '<div style="margin-bottom: 15px;">';
        echo '<strong style="display: block; margin-bottom: 8px;">AI Feedback:</strong>';
        echo '<div style="background: #f9f9f9; padding: 12px; border-radius: 3px; border-left: 3px solid #0066cc;">';
        echo '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-family: inherit; font-size: 0.95em;">' . 
             htmlspecialchars(substr($assess->feedback, 0, 500)) . 
             (strlen($assess->feedback) > 500 ? '...' : '') . '</pre>';
        echo '</div>';
        echo '</div>';
        
        // Action buttons
        if (!empty($actionbuttons)) {
            echo '<div style="margin-bottom: 15px; padding-top: 10px; border-top: 1px solid #eee;">';
            echo '<strong style="display: block; margin-bottom: 10px;">Teacher Decision:</strong>';
            echo '<div>' . $actionbuttons . '</div>';
            echo '</div>';
            
            // Hidden override form (shown via JavaScript)
            echo '<div id="grade-form-' . $assess->id . '" style="display:none; background: #f0f0f0; padding: 15px; border-radius: 3px; margin-top: 10px; border: 1px solid #ddd;">';
            echo '<h5 style="margin-top: 0; margin-bottom: 15px;">Enter Custom Grade</h5>';
            echo '<form method="POST">';
            echo '<input type="hidden" name="aid" value="' . $cmid . '">';
            echo '<input type="hidden" name="action" value="reject">';
            echo '<input type="hidden" name="assessmentid" value="' . $assess->id . '">';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
            echo '<div style="margin-bottom: 12px;">';
            echo '<label for="grade-' . $assess->id . '" style="display: block; margin-bottom: 8px; font-weight: bold;">Grade Value (0-100):</label>';
            echo '<input type="number" class="form-control" id="grade-' . $assess->id . '" name="grade" min="0" max="100" step="0.01" placeholder="e.g. 75.5" required style="padding: 8px; border: 1px solid #ccc; border-radius: 3px; width: 100%; font-size: 14px;">';
            echo '</div>';
            echo '<div>';
            echo '<button type="submit" class="btn btn-primary btn-sm" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; margin-right: 8px;">✓ Submit Grade</button> ';
            echo '<button type="button" class="btn btn-secondary btn-sm" onclick="hideGradeForm(' . $assess->id . ')" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;">✕ Cancel</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    echo '</div>';
}

// Back button
echo '<div style="margin-top: 20px;">';
$backurl = new moodle_url('/mod/assign/view.php', array('id' => $cm->id));
echo '<a href="' . $backurl . '" class="btn btn-secondary">← Back to Assignment</a>';
echo '</div>';

// JavaScript for form toggle
echo '<script type="text/javascript">
function showGradeForm(assessmentId) {
    var form = document.getElementById("grade-form-" + assessmentId);
    if (form) {
        form.style.display = "block";
    }
}

function hideGradeForm(assessmentId) {
    var form = document.getElementById("grade-form-" + assessmentId);
    if (form) {
        form.style.display = "none";
    }
}
</script>';

echo $OUTPUT->footer();

