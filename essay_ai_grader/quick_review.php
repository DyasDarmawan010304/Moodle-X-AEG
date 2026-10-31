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
 * Quick access to AI assessment review from assignment module.
 * Redirects to the review page or shows available assessments.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $CFG, $DB, $USER, $OUTPUT, $PAGE;

$cmid = required_param('cmid', PARAM_INT);

// Get course module and assignment.
$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$assignment = $DB->get_record('assign', array('id' => $cm->instance), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $assignment->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Require login and permission.
require_login($course, true, $cm);
require_capability('mod/assign:grade', $context);

// Get completed assessment count.
$completedcount = $DB->count_records('local_essay_ai_assess', array(
    'assignmentid' => $assignment->id,
    'status' => 'completed'
));

// If there are completed assessments, redirect to review page.
if ($completedcount > 0) {
    redirect(
        new moodle_url('/local/essay_ai_grader/review.php', array('assignmentid' => $assignment->id))
    );
}

// Otherwise, show assignment menu page.
$PAGE->set_url('/local/essay_ai_grader/quick_review.php', array('cmid' => $cmid));
$PAGE->set_title($assignment->name . ' - ' . get_string('reviewassessments', 'local_essay_ai_grader'));
$PAGE->set_heading($assignment->name . ' - ' . get_string('reviewassessments', 'local_essay_ai_grader'));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

// Check if AI grading is configured.
$config = $DB->get_record('local_essay_ai_config', array('assignid' => $assignment->id));

if (!$config || !$config->enabled) {
    echo $OUTPUT->notification(
        get_string('ai_grading_not_enabled', 'local_essay_ai_grader') ?? 'AI grading is not enabled for this assignment.',
        'warning'
    );
    echo $OUTPUT->footer();
    exit;
}

// Get assessment counts.
$pendingcount = $DB->count_records('local_essay_ai_assess', array(
    'assignmentid' => $assignment->id,
    'status' => 'pending'
));
$processingcount = $DB->count_records('local_essay_ai_assess', array(
    'assignmentid' => $assignment->id,
    'status' => 'processing'
));
$completedcount = $DB->count_records('local_essay_ai_assess', array(
    'assignmentid' => $assignment->id,
    'status' => 'completed'
));
$errorcount = $DB->count_records('local_essay_ai_assess', array(
    'assignmentid' => $assignment->id,
    'status' => 'error'
));

// Display status information.
echo html_writer::start_div('container-fluid mt-4');

echo html_writer::tag('h3', $assignment->name);
echo html_writer::tag('p', get_string('reviewassessments', 'local_essay_ai_grader'), array('class' => 'text-muted'));

// Status cards.
echo html_writer::start_div('row');

// Pending card.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card border-info');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $pendingcount, array('class' => 'card-title text-info'));
echo html_writer::tag('p', 'Pending', array('class' => 'card-text text-muted'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Processing card.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card border-warning');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', $processingcount, array('class' => 'card-title text-warning'));
echo html_writer::tag('p', 'Processing', array('class' => 'card-text text-muted'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Completed card - PROMINENT.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card border-success border-2');
echo html_writer::start_div('card-body bg-light');
echo html_writer::tag('h5', $completedcount, array('class' => 'card-title text-success fw-bold'));
echo html_writer::tag('p', 'Ready to Review', array('class' => 'card-text text-muted'));

if ($completedcount > 0) {
    $reviewurl = new moodle_url('/local/essay_ai_grader/review.php', array('assignmentid' => $assignment->id));
    echo html_writer::link(
        $reviewurl,
        html_writer::tag('i', '', array('class' => 'fa fa-arrow-right')) . ' Review Now →',
        array('class' => 'btn btn-success btn-sm mt-2')
    );
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Error card.
if ($errorcount > 0) {
    echo html_writer::start_div('col-md-3');
    echo html_writer::start_div('card border-danger');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', $errorcount, array('class' => 'card-title text-danger'));
    echo html_writer::tag('p', 'Errors', array('class' => 'card-text text-muted'));
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div(); // row
echo html_writer::end_div(); // container

// Show message if no assessments.
if ($completedcount == 0 && $pendingcount == 0 && $processingcount == 0) {
    echo $OUTPUT->notification('No assessments yet. Assessments will appear here as student submissions are processed.', 'info');
}

echo $OUTPUT->footer();
