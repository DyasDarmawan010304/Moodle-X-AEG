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
 * Essay AI Grader button and controls.
 * Displays buttons/links for Essay AI Grader functionality.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$cmid = required_param('cmid', PARAM_INT);

// Get course module and assignment.
$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$assignment = $DB->get_record('assign', array('id' => $cm->instance), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $assignment->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Require login and permission.
require_login($course, true, $cm);
require_capability('mod/assign:grade', $context);

// Check if AI grading is enabled for this assignment.
$config = \local_essay_ai_grader\criteria_config_form::get_config_for_assignment((int)$assignment->id);

// Setup page.
$PAGE->set_url('/local/essay_ai_grader/assignment_button.php', array('cmid' => $cmid));
$PAGE->set_title($assignment->name . ' - ' . get_string('pluginname', 'local_essay_ai_grader'));
$PAGE->set_heading($assignment->name . ' - ' . get_string('pluginname', 'local_essay_ai_grader'));
$PAGE->set_pagelayout('incourse');

// Output.
echo $OUTPUT->header();

// Display status and buttons.
echo html_writer::start_div('container-fluid mt-4');

// Title
echo html_writer::tag('h3', get_string('pluginname', 'local_essay_ai_grader'));

if ($config && $config->enabled) {
    echo html_writer::tag('p', 'AI Grading is <strong>ENABLED</strong> for this assignment', 
                          array('class' => 'alert alert-success'));
    
    // Button group.
    echo html_writer::start_div('btn-group', array('role' => 'group'));
    
    // Configure button.
    $config_url = new moodle_url('/local/essay_ai_grader/configure.php', array('cmid' => $cmid));
    echo html_writer::tag('a',
        html_writer::tag('i', '', array('class' => 'icon fa fa-cog')) . ' Configure',
        array(
            'href' => $config_url->out(),
            'class' => 'btn btn-primary',
        )
    );
    
    // Review button.
    $review_url = new moodle_url('/local/essay_ai_grader/review.php', array('assignmentid' => $assignment->id));
    echo html_writer::tag('a',
        html_writer::tag('i', '', array('class' => 'icon fa fa-eye')) . ' Review Grades',
        array(
            'href' => $review_url->out(),
            'class' => 'btn btn-info',
        )
    );
    
    // Dashboard button.
    $dashboard_url = new moodle_url('/local/essay_ai_grader/dashboard.php');
    echo html_writer::tag('a',
        html_writer::tag('i', '', array('class' => 'icon fa fa-dashboard')) . ' Dashboard',
        array(
            'href' => $dashboard_url->out(),
            'class' => 'btn btn-secondary',
        )
    );
    
    echo html_writer::end_div();
    
    // Pending assessments info.
    $pending = $DB->count_records('local_essay_ai_assess', array(
        'assignmentid' => $assignment->id,
        'status' => 'pending'
    ));
    $completed = $DB->count_records('local_essay_ai_assess', array(
        'assignmentid' => $assignment->id,
        'status' => 'completed'
    ));
    
    echo html_writer::start_div('mt-4');
    echo html_writer::tag('h5', 'Assessment Status');
    echo html_writer::tag('p', 'Pending: <strong>' . $pending . '</strong> | Completed: <strong>' . $completed . '</strong>');
    echo html_writer::end_div();
    
} else {
    echo html_writer::tag('p', 'AI Grading is <strong>NOT ENABLED</strong> for this assignment', 
                          array('class' => 'alert alert-warning'));
    
    $config_url = new moodle_url('/local/essay_ai_grader/configure.php', array('cmid' => $cmid));
    echo html_writer::tag('a',
        'Click here to enable',
        array(
            'href' => $config_url->out(),
            'class' => 'btn btn-primary',
        )
    );
}

echo html_writer::end_div();

// Back button.
$back_url = new moodle_url('/mod/assign/view.php', array('id' => $cmid));
echo html_writer::tag('a', 'Back to Assignment', array(
    'href' => $back_url->out(),
    'class' => 'btn btn-secondary mt-3',
));

echo $OUTPUT->footer();
