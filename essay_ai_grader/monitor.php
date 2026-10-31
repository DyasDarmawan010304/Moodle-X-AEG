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
 * Operational monitor page for Essay AI Grader.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $DB, $OUTPUT, $PAGE, $USER;

require_login();

// Access control follows dashboard rule: user must be able to grade in at least one context.
$hasaccess = false;
if (has_capability('mod/assign:grade', context_system::instance()) ||
    has_capability('moodle/site:config', context_system::instance())) {
    $hasaccess = true;
} else {
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

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'runqueue') {
    require_sesskey();
    $task = new \local_essay_ai_grader\task\process_assessments();
    $task->execute();
    redirect(new moodle_url('/local/essay_ai_grader/monitor.php'),
        get_string('monitor_queue_run_done', 'local_essay_ai_grader'));
} else if ($action === 'resetstuck') {
    require_sesskey();
    // Requeue stale processing records (likely interrupted runs)
    $stuckthreshold = time() - (15 * 60);
    $stuck = $DB->get_records_select('local_essay_ai_assess',
        'status = ? AND timemodified < ?',
        array('processing', $stuckthreshold));
    foreach ($stuck as $stuckrow) {
        $update = new stdClass();
        $update->id = $stuckrow->id;
        $update->status = 'pending';
        $update->timemodified = time();
        $update->modified = time();
        $DB->update_record('local_essay_ai_assess', $update);
    }
    $message = 'Reset ' . count($stuck) . ' stuck assessment(s) back to pending';
    redirect(new moodle_url('/local/essay_ai_grader/monitor.php'), $message);
} else if ($action === 'resetallerrors') {
    require_sesskey();
    // Reset ALL error assessments back to pending for reprocessing
    $errors = $DB->get_records('local_essay_ai_assess', array('status' => 'error'));
    foreach ($errors as $error) {
        $update = new stdClass();
        $update->id = $error->id;
        $update->status = 'pending';
        $update->error_message = null;
        $update->timemodified = time();
        $update->modified = time();
        $DB->update_record('local_essay_ai_assess', $update);
    }
    $message = 'Reset ' . count($errors) . ' error assessment(s) back to pending';
    redirect(new moodle_url('/local/essay_ai_grader/monitor.php'), $message);
}

$PAGE->set_url(new moodle_url('/local/essay_ai_grader/monitor.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('monitor_title', 'local_essay_ai_grader'));
$PAGE->set_heading(get_string('monitor_title', 'local_essay_ai_grader'));

$task = $DB->get_record('task_scheduled', array(
    'classname' => '\\local_essay_ai_grader\\task\\process_assessments'
));

$counts = array(
    'pending' => (int)$DB->count_records('local_essay_ai_assess', array('status' => 'pending')),
    'processing' => (int)$DB->count_records('local_essay_ai_assess', array('status' => 'processing')),
    'completed' => (int)$DB->count_records('local_essay_ai_assess', array('status' => 'completed')),
    'error' => (int)$DB->count_records('local_essay_ai_assess', array('status' => 'error')),
    'stuck' => (int)$DB->count_records_select('local_essay_ai_assess',
        'status = ? AND timemodified < ?',
        array('processing', time() - (15 * 60))
    ),
);

$latest = $DB->get_records_sql("
    SELECT a.id, a.status, a.score, a.timemodified, a.assignmentid, a.userid, asg.name AS assignmentname,
           u.firstname, u.lastname
      FROM {local_essay_ai_assess} a
      JOIN {assign} asg ON asg.id = a.assignmentid
      JOIN {user} u ON u.id = a.userid
  ORDER BY a.id DESC", array(), 0, 10);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('monitor_title', 'local_essay_ai_grader'));

echo html_writer::start_div('mb-3');
$runurl = new moodle_url('/local/essay_ai_grader/monitor.php', array(
    'action' => 'runqueue',
    'sesskey' => sesskey(),
));
echo html_writer::link($runurl, get_string('monitor_run_queue_now', 'local_essay_ai_grader'),
    array('class' => 'btn btn-primary'));

if ($counts['stuck'] > 0) {
    echo ' ';
    $resetstuckurl = new moodle_url('/local/essay_ai_grader/monitor.php', array(
        'action' => 'resetstuck',
        'sesskey' => sesskey(),
    ));
    echo html_writer::link($resetstuckurl, 'Reset Stuck (' . $counts['stuck'] . ')',
        array('class' => 'btn btn-warning'));
}

if ($counts['error'] > 0) {
    echo ' ';
    $resetallurl = new moodle_url('/local/essay_ai_grader/monitor.php', array(
        'action' => 'resetallerrors',
        'sesskey' => sesskey(),
    ));
    echo html_writer::link($resetallurl, 'Reset All Errors (' . $counts['error'] . ')',
        array('class' => 'btn btn-danger'));
}
echo html_writer::end_div();

echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', get_string('monitor_task_status', 'local_essay_ai_grader'), array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');
if ($task) {
    $schedule = "{$task->minute} {$task->hour} {$task->day} {$task->month} {$task->dayofweek}";
    echo html_writer::tag('p', 'Schedule: <strong>' . s($schedule) . '</strong>');
    echo html_writer::tag('p', 'Disabled: <strong>' . ($task->disabled ? 'YES' : 'NO') . '</strong>');
    echo html_writer::tag('p', 'Last run: <strong>' . ($task->lastruntime ? userdate($task->lastruntime) : '-') . '</strong>');
    echo html_writer::tag('p', 'Next run: <strong>' . ($task->nextruntime ? userdate($task->nextruntime) : '-') . '</strong>');
} else {
    echo $OUTPUT->notification(get_string('monitor_task_not_found', 'local_essay_ai_grader'), 'warning');
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', get_string('monitor_queue_status', 'local_essay_ai_grader'), array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::tag('p', 'Pending: <strong>' . $counts['pending'] . '</strong>');
echo html_writer::tag('p', 'Processing: <strong>' . $counts['processing'] . '</strong>');
echo html_writer::tag('p', 'Completed: <strong>' . $counts['completed'] . '</strong>');
echo html_writer::tag('p', 'Error: <strong>' . $counts['error'] . '</strong>');
echo html_writer::tag('p', 'Stuck (>15m): <strong>' . $counts['stuck'] . '</strong>');
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('card');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', get_string('monitor_latest', 'local_essay_ai_grader'), array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');
if (empty($latest)) {
    echo $OUTPUT->notification(get_string('no_assessments', 'local_essay_ai_grader'), 'info');
} else {
    $table = new html_table();
    $table->head = array('ID', 'Assignment', 'Student', 'Status', 'Score', 'Updated');
    foreach ($latest as $row) {
        $table->data[] = array(
            $row->id,
            s($row->assignmentname) . ' (#' . $row->assignmentid . ')',
            fullname((object)array('firstname' => $row->firstname, 'lastname' => $row->lastname)) . ' (#' . $row->userid . ')',
            s($row->status),
            $row->score === null ? 'N/A' : format_float((float)$row->score, 2),
            userdate($row->timemodified),
        );
    }
    echo html_writer::table($table);
}
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();

