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
 * Assessment History page for Essay AI Grader.
 * Displays complete history of all assessments performed by the AI system.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $DB, $OUTPUT, $PAGE, $USER;

require_login();

// Access control: user must be able to grade in at least one context.
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

// Filter parameters
$filter_student = optional_param('student', 0, PARAM_INT);
$filter_assignment = optional_param('assignment', 0, PARAM_INT);
$filter_status = optional_param('status', '', PARAM_ALPHA);
$filter_datefrom = optional_param('datefrom', 0, PARAM_INT);
$filter_dateto = optional_param('dateto', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$sorted_by = optional_param('sortby', 'id', PARAM_ALPHA); // id, student, assignment, score, status, date
$sort_dir = optional_param('sortdir', 'desc', PARAM_ALPHA); // asc or desc

$PAGE->set_url(new moodle_url('/local/essay_ai_grader/assessment_history.php',
    array(
        'student' => $filter_student,
        'assignment' => $filter_assignment,
        'status' => $filter_status,
        'datefrom' => $filter_datefrom,
        'dateto' => $filter_dateto,
        'page' => $page,
        'sortby' => $sorted_by,
        'sortdir' => $sort_dir,
    )
));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('assessment_history_title', 'local_essay_ai_grader'));
$PAGE->set_heading(get_string('assessment_history_title', 'local_essay_ai_grader'));

// Build filter conditions
$where = array();
$params = array();

if ($filter_student > 0) {
    $where[] = 'a.userid = ?';
    $params[] = $filter_student;
}

if ($filter_assignment > 0) {
    $where[] = 'a.assignmentid = ?';
    $params[] = $filter_assignment;
}

if (!empty($filter_status)) {
    $where[] = 'a.status = ?';
    $params[] = $filter_status;
}

if ($filter_datefrom > 0) {
    $where[] = 'a.timemodified >= ?';
    $params[] = $filter_datefrom;
}

if ($filter_dateto > 0) {
    $where[] = 'a.timemodified <= ?';
    $params[] = $filter_dateto;
}

$wheresql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Count total records for pagination
$countsql = "SELECT COUNT(*) FROM {local_essay_ai_assess} a 
    JOIN {assign} asg ON asg.id = a.assignmentid
    JOIN {course} c ON c.id = asg.course
    JOIN {user} u ON u.id = a.userid
    " . $wheresql;
$totalcount = $DB->count_records_sql($countsql, $params);

// Safety mapping for sort columns
$allowed_sortby = array('id', 'firstname', 'lastname', 'assignmentname', 'score', 'status', 'timemodified');
if (!in_array($sorted_by, $allowed_sortby)) {
    $sorted_by = 'id';
}
if (!in_array($sort_dir, array('asc', 'desc'))) {
    $sort_dir = 'desc';
}

// Sort mapping
$sort_map = array(
    'id' => 'a.id',
    'firstname' => 'u.firstname',
    'lastname' => 'u.lastname',
    'assignmentname' => 'asg.name',
    'score' => 'a.score',
    'status' => 'a.status',
    'timemodified' => 'a.timemodified',
);

$ordersql = $sort_map[$sorted_by] . ' ' . strtoupper($sort_dir);

// Pagination
$perpage = 50;
$offset = $page * $perpage;

// Fetch assessment records
$sql = "
    SELECT a.id, a.status, a.score, a.feedback, a.criteria_scores, a.error_message,
           a.timemodified, a.assignmentid, a.userid, a.submissionid,
           asg.name AS assignmentname, u.firstname, u.lastname,
           c.id AS courseid, c.shortname AS courseshortname
      FROM {local_essay_ai_assess} a
      JOIN {assign} asg ON asg.id = a.assignmentid
      JOIN {course} c ON c.id = asg.course
      JOIN {user} u ON u.id = a.userid
      {$wheresql}
  ORDER BY {$ordersql}
  LIMIT {$perpage} OFFSET {$offset}
";

$records = $DB->get_records_sql($sql, $params);

// Get unique assignments for filter dropdown
$assignments = $DB->get_records_sql("
    SELECT DISTINCT asg.id, asg.name, c.shortname
      FROM {assign} asg
      JOIN {course} c ON c.id = asg.course
      WHERE EXISTS (SELECT 1 FROM {local_essay_ai_assess} WHERE assignmentid = asg.id)
  ORDER BY c.shortname, asg.name
");

// Get unique students for filter dropdown (top 50 most recent)
$students = $DB->get_records_sql("
    SELECT DISTINCT u.id, u.firstname, u.lastname
      FROM {user} u
      WHERE EXISTS (SELECT 1 FROM {local_essay_ai_assess} WHERE userid = u.id)
  ORDER BY u.firstname, u.lastname
  LIMIT 50
");

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assessment_history_title', 'local_essay_ai_grader'));

// Navigation links
echo html_writer::start_div('mb-3');
$monitorurl = new moodle_url('/local/essay_ai_grader/monitor.php');
$dashboardurl = new moodle_url('/local/essay_ai_grader/dashboard.php');
echo html_writer::link($monitorurl, get_string('monitor_title', 'local_essay_ai_grader'), array('class' => 'btn btn-secondary'))
    . ' ' .
    html_writer::link($dashboardurl, get_string('viewdashboard', 'local_essay_ai_grader'), array('class' => 'btn btn-secondary'));
echo html_writer::end_div();

// Filter Form
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', 'Filters', array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');

$filterform = new moodle_url('/local/essay_ai_grader/assessment_history.php');

echo html_writer::start_tag('form', array('method' => 'get', 'action' => $filterform->out(false), 'class' => 'row g-2 mb-2'));

// Student filter
echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', 'Student:', array('class' => 'form-label', 'for' => 'student_select'));
echo html_writer::start_tag('select', array('name' => 'student', 'id' => 'student_select', 'class' => 'form-select'));
echo html_writer::tag('option', 'All Students', array('value' => 0, 'selected' => ($filter_student == 0)));
foreach ($students as $student) {
    $label = fullname((object)array('firstname' => $student->firstname, 'lastname' => $student->lastname))
        . ' (#' . $student->id . ')';
    echo html_writer::tag('option', $label, array('value' => $student->id, 'selected' => ($filter_student == $student->id)));
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Assignment filter
echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', 'Assignment:', array('class' => 'form-label', 'for' => 'assignment_select'));
echo html_writer::start_tag('select', array('name' => 'assignment', 'id' => 'assignment_select', 'class' => 'form-select'));
echo html_writer::tag('option', 'All Assignments', array('value' => 0, 'selected' => ($filter_assignment == 0)));
foreach ($assignments as $assignment) {
    $label = $assignment->shortname . ' - ' . $assignment->name . ' (#' . $assignment->id . ')';
    echo html_writer::tag('option', $label, array('value' => $assignment->id, 'selected' => ($filter_assignment == $assignment->id)));
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Status filter
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', 'Status:', array('class' => 'form-label', 'for' => 'status_select'));
echo html_writer::start_tag('select', array('name' => 'status', 'id' => 'status_select', 'class' => 'form-select'));
echo html_writer::tag('option', 'All Status', array('value' => '', 'selected' => (empty($filter_status))));
foreach (array('completed', 'pending', 'processing', 'error') as $stat) {
    echo html_writer::tag('option', ucfirst($stat), array('value' => $stat, 'selected' => ($filter_status === $stat)));
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Submit button
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', '&nbsp;', array('class' => 'form-label', 'style' => 'display:block;'));
echo html_writer::tag('button', 'Filter', array('type' => 'submit', 'class' => 'btn btn-primary w-100'));
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

// Statistics
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', 'Statistics', array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');

$stats = $DB->get_record_sql("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
           SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
           ROUND(AVG(CASE WHEN score IS NOT NULL THEN score ELSE NULL END), 2) as avg_score,
           MIN(score) as min_score,
           MAX(score) as max_score
    FROM {local_essay_ai_assess} a
    JOIN {assign} asg ON asg.id = a.assignmentid
    {$wheresql}
", $params);

echo html_writer::tag('p', 'Total Assessments: <strong>' . $stats->total . '</strong>');
echo html_writer::tag('p', 'Completed: <strong>' . ($stats->completed ?? 0) . '</strong> | '
    . 'Errors: <strong>' . ($stats->errors ?? 0) . '</strong> | '
    . 'Pending: <strong>' . ($stats->pending ?? 0) . '</strong>');
echo html_writer::tag('p', 'Average Score: <strong>' . ($stats->avg_score ?? 'N/A') . '</strong> | '
    . 'Min: <strong>' . ($stats->min_score ?? 'N/A') . '</strong> | '
    . 'Max: <strong>' . ($stats->max_score ?? 'N/A') . '</strong>');

echo html_writer::end_div();
echo html_writer::end_div();

// Assessment records table
echo html_writer::start_div('card');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', 'Assessment History (' . $totalcount . ' total)', array('class' => 'mb-0'));
echo html_writer::end_div();
echo html_writer::start_div('card-body');

if (empty($records)) {
    echo $OUTPUT->notification(get_string('no_assessments', 'local_essay_ai_grader'), 'info');
} else {
    $table = new html_table();
    $table->attributes = array('class' => 'table table-striped table-hover');
    
    // Sort icons
    $upsort = ' ↑';
    $downsort = ' ↓';
    
    // Header with sort links
    $table->head = array(
        html_writer::link(new moodle_url($PAGE->url, array('sortby' => 'id', 'sortdir' => ($sorted_by === 'id' && $sort_dir === 'desc') ? 'asc' : 'desc')), 'ID' . (($sorted_by === 'id') ? ($sort_dir === 'desc' ? $downsort : $upsort) : '')),
        html_writer::link(new moodle_url($PAGE->url, array('sortby' => 'firstname', 'sortdir' => ($sorted_by === 'firstname' && $sort_dir === 'desc') ? 'asc' : 'desc')), 'Student' . (($sorted_by === 'firstname') ? ($sort_dir === 'desc' ? $downsort : $upsort) : '')),
        html_writer::link(new moodle_url($PAGE->url, array('sortby' => 'assignmentname', 'sortdir' => ($sorted_by === 'assignmentname' && $sort_dir === 'desc') ? 'asc' : 'desc')), 'Assignment' . (($sorted_by === 'assignmentname') ? ($sort_dir === 'desc' ? $downsort : $upsort) : '')),
        html_writer::link(new moodle_url($PAGE->url, array('sortby' => 'status', 'sortdir' => ($sorted_by === 'status' && $sort_dir === 'desc') ? 'asc' : 'desc')), 'Status' . (($sorted_by === 'status') ? ($sort_dir === 'desc' ? $downsort : $upsort) : '')),
        html_writer::link(new moodle_url($PAGE->url, array('sortby' => 'score', 'sortdir' => ($sorted_by === 'score' && $sort_dir === 'desc') ? 'asc' : 'desc')), 'Score' . (($sorted_by === 'score') ? ($sort_dir === 'desc' ? $downsort : $upsort) : '')),
        html_writer::link(new moodle_url($PAGE->url, array('sortby' => 'timemodified', 'sortdir' => ($sorted_by === 'timemodified' && $sort_dir === 'desc') ? 'asc' : 'desc')), 'Assessed' . (($sorted_by === 'timemodified') ? ($sort_dir === 'desc' ? $downsort : $upsort) : '')),
        'Details'
    );
    
    foreach ($records as $row) {
        $studentname = fullname((object)array('firstname' => $row->firstname, 'lastname' => $row->lastname));
        $statusbadge = '';
        switch ($row->status) {
            case 'completed':
                $statusbadge = html_writer::tag('span', ucfirst($row->status), array('class' => 'badge bg-success'));
                break;
            case 'error':
                $statusbadge = html_writer::tag('span', ucfirst($row->status), array('class' => 'badge bg-danger'));
                break;
            case 'pending':
                $statusbadge = html_writer::tag('span', ucfirst($row->status), array('class' => 'badge bg-warning'));
                break;
            case 'processing':
                $statusbadge = html_writer::tag('span', ucfirst($row->status), array('class' => 'badge bg-info'));
                break;
            default:
                $statusbadge = html_writer::tag('span', ucfirst($row->status), array('class' => 'badge bg-secondary'));
        }
        
        $score_display = $row->score !== null ? format_float((float)$row->score, 2) . '%' : 'N/A';
        
        // Details link
        $detailslink = html_writer::link(
            new moodle_url('/local/essay_ai_grader/view_assessment_detail.php', array('id' => $row->id)),
            'View',
            array('class' => 'btn btn-sm btn-outline-primary')
        );
        
        $table->data[] = array(
            $row->id,
            $studentname . ' (#' . $row->userid . ')',
            s($row->assignmentname) . ' (#' . $row->assignmentid . ')',
            $statusbadge,
            $score_display,
            userdate($row->timemodified),
            $detailslink
        );
    }
    
    echo html_writer::table($table);
    
    // Pagination
    echo html_writer::start_div('d-flex justify-content-between align-items-center mt-3');
    echo html_writer::tag('span', 'Showing ' . ($offset + 1) . ' - ' . min($offset + $perpage, $totalcount) . ' of ' . $totalcount . ' assessments');
    
    // Pagination links
    echo html_writer::start_div('');
    if ($page > 0) {
        echo html_writer::link(
            new moodle_url($PAGE->url, array('page' => $page - 1)),
            'Previous',
            array('class' => 'btn btn-sm btn-outline-secondary')
        ) . ' ';
    }
    
    // Page numbers
    $total_pages = ceil($totalcount / $perpage);
    for ($i = 0; $i < $total_pages && $i < 10; $i++) {
        $class = $i === $page ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-secondary';
        echo html_writer::link(
            new moodle_url($PAGE->url, array('page' => $i)),
            $i + 1,
            array('class' => $class)
        ) . ' ';
    }
    
    if ($page < $total_pages - 1) {
        echo html_writer::link(
            new moodle_url($PAGE->url, array('page' => $page + 1)),
            'Next',
            array('class' => 'btn btn-sm btn-outline-secondary')
        );
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
