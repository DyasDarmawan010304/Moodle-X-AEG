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
 * Direct link to review assessments from assignment view.
 * This simple page displays a button/link to access the review page.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $DB, $OUTPUT, $PAGE, $USER;

$assignmentid = required_param('aid', PARAM_INT);

// Get assignment
$assignment = $DB->get_record('assign', array('id' => $assignmentid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $assignment->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id, false, MUST_EXIST);

// Check permissions
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/assign:grade', $context);

// Setup page
$PAGE->set_url(new moodle_url('/local/essay_ai_grader/assignment_review_link.php', array('aid' => $assignmentid)));
$PAGE->set_title('AI Assessments - ' . $assignment->name);
$PAGE->set_heading('AI Assessments - ' . $assignment->name);
$PAGE->set_context($context);

echo $OUTPUT->header();

// Get assessment counts
$completedcount = $DB->count_records('local_essay_ai_assess', array(
    'assignmentid' => $assignment->id,
    'status' => 'completed'
));

$pendingcount = $DB->count_records('local_essay_ai_assess', array(
    'assignmentid' => $assignment->id,
    'status' => 'pending'
));

$totalcount = $completedcount + $pendingcount;

// Display button/link to review page
echo html_writer::start_div('alert alert-info', array('style' => 'padding: 20px; border-radius: 5px;'));
echo html_writer::tag('h3', '📋 Review AI Assessments', array('style' => 'margin-top: 0;'));

if ($totalcount == 0) {
    echo html_writer::tag('p', 'No AI assessments available yet for this assignment.');
} else {
    echo html_writer::tag('p', 'There are ' . $completedcount . ' completed assessment(s) and ' . $pendingcount . ' pending assessment(s).');
}

// Large button to go to review page
$review_url = new moodle_url('/local/essay_ai_grader/review_assessments.php', array('aid' => $assignmentid));
echo html_writer::tag('a',
    html_writer::tag('i', '', array('class' => 'fa fa-clipboard-check me-2')) . 'Go to Review Page',
    array(
        'href' => $review_url->out(),
        'class' => 'btn btn-primary btn-lg',
        'style' => 'display: inline-block; padding: 12px 30px; font-weight: 600; margin-top: 15px;'
    )
);

echo html_writer::end_div();

// Back button
$backurl = new moodle_url('/mod/assign/view.php', array('id' => $cm->id));
echo html_writer::tag('a', '← Back to Assignment', array(
    'href' => $backurl->out(),
    'class' => 'btn btn-secondary mt-3'
));

echo $OUTPUT->footer();
