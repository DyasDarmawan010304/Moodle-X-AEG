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
 * Dashboard for managing AI-graded assignments.
 * Quick access to all assignments with AI grading enabled.
 *
 * @package    local_essay_ai_grader
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');

global $USER, $DB, $CFG, $PAGE, $OUTPUT;

// Only for logged-in users.
require_login();

// Page setup.
$PAGE->set_url(new moodle_url('/local/essay_ai_grader/dashboard.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_essay_ai_grader'));
$PAGE->set_heading(get_string('pluginname', 'local_essay_ai_grader'));

// Check if user has any grading capability.
$hasaccess = false;
if (has_capability('mod/assign:grade', context_system::instance())) {
    $hasaccess = true;
} else {
    // Check if user has grading capability in any enrolled course
    $courses = enrol_get_all_users_courses($USER->id);
    foreach ($courses as $course) {
        if (has_capability('mod/assign:grade', context_course::instance($course->id))) {
            $hasaccess = true;
            break;
        }
    }
}

if (!$hasaccess) {
    redirect($CFG->wwwroot);
}

echo $OUTPUT->header();

echo html_writer::tag('h1', get_string('pluginname', 'local_essay_ai_grader'));
echo html_writer::tag('p', get_string('dashboarddesc', 'local_essay_ai_grader', 'Kelola semua assignment dengan AI grading'));

echo html_writer::start_div('essay-ai-dashboard');

// Get all assignments with AI grading enabled for courses user can grade.
// We need to join with assess table to count assessments
$sql = "SELECT a.id, a.name, a.id as assignmentid, cm.id as cmid, c.id as courseid, c.fullname as coursename, 
               COUNT(DISTINCT assess.id) as assessmentcount,
               SUM(CASE WHEN assess.status = 'completed' THEN 1 ELSE 0 END) as pending_reviews
        FROM {assign} a
        INNER JOIN {course} c ON a.course = c.id
        INNER JOIN {course_modules} cm ON cm.instance = a.id
        INNER JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
        INNER JOIN {local_essay_ai_config} config ON a.id = config.assignid
        LEFT JOIN {local_essay_ai_assess} assess ON a.id = assess.assignmentid
        WHERE config.enabled = 1
        GROUP BY a.id, a.name, cm.id, c.id, c.fullname
        ORDER BY c.fullname, a.name";

try {
    $assignments = $DB->get_records_sql($sql);
} catch (Exception $e) {
    echo html_writer::tag('p', 'Error: ' . $e->getMessage(), array('class' => 'alert alert-danger'));
    echo $OUTPUT->footer();
    exit;
}

if (empty($assignments)) {
    echo html_writer::tag('p', get_string('noaiassignments', 'local_essay_ai_grader', 'Belum ada assignment dengan AI grading yang diaktifkan'));
} else {
    // Create table.
    $table = new html_table();
    $table->head = array(
        get_string('assignment', 'local_essay_ai_grader'),
        get_string('course', 'local_essay_ai_grader'),
        get_string('assessments', 'local_essay_ai_grader'),
        get_string('pending', 'local_essay_ai_grader'),
        get_string('action', 'local_essay_ai_grader')
    );
    $table->attributes['class'] = 'table table-striped table-hover';

    foreach ($assignments as $assign) {
        $assignurl = new moodle_url('/mod/assign/view.php', array('id' => $assign->cmid));
        $assignlink = html_writer::link($assignurl, $assign->name);
        
        $courseurl = new moodle_url('/course/view.php', array('id' => $assign->courseid));
        $courselink = html_writer::link($courseurl, $assign->coursename);
        
        // Configure link.
        $configurl = new moodle_url('/local/essay_ai_grader/configure.php', array('cmid' => $assign->cmid));
        $configlink = html_writer::link($configurl, get_string('configure', 'local_essay_ai_grader'), array('class' => 'btn btn-sm btn-primary'));
        
        // Review link if there are pending reviews.
        if ($assign->pending_reviews > 0) {
            $reviewurl = new moodle_url('/local/essay_ai_grader/review.php', array('assignmentid' => $assign->id));
            $reviewlink = html_writer::link(
                $reviewurl,
                get_string('review', 'local_essay_ai_grader') . ' (' . $assign->pending_reviews . ')',
                array('class' => 'btn btn-sm btn-warning')
            );
            $actions = $configlink . ' ' . $reviewlink;
        } else {
            $actions = $configlink;
        }

        $row = new html_table_row(array(
            $assignlink,
            $courselink,
            $assign->assessmentcount,
            $assign->pending_reviews,
            $actions
        ));
        $table->data[] = $row;
    }
    
    echo html_writer::table($table);
}

echo html_writer::end_div();

// Add button to go to admin settings.
echo html_writer::start_div('', array('style' => 'margin-top: 20px;'));
echo html_writer::link(
    new moodle_url('/local/essay_ai_grader/monitor.php'),
    get_string('monitor_title', 'local_essay_ai_grader'),
    array('class' => 'btn btn-primary me-2')
);
echo ' ';
echo html_writer::link(
    new moodle_url('/admin/settings.php', array('section' => 'local_essay_ai_grader')),
    get_string('adminsettings', 'local_essay_ai_grader'),
    array('class' => 'btn btn-secondary')
);
echo html_writer::end_div();

echo $OUTPUT->footer();
