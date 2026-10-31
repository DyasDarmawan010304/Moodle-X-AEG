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
 * Assessment Detail View page for Essay AI Grader.
 * Displays detailed information about a specific assessment.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $DB, $OUTPUT, $PAGE, $USER;

try {
    require_login();

    // Access control
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

    $assessmentid = required_param('id', PARAM_INT);

    // Fetch assessment details
    $assessment = $DB->get_record_sql("
        SELECT a.id, a.status, a.score, a.feedback, a.criteria_scores, a.error_message,
               a.timemodified, a.assignmentid, a.userid, a.submissionid, a.gemini_response, a.gemini_response_raw,
               asg.name AS assignmentname,
               u.firstname, u.lastname, u.email,
               c.id AS courseid, c.fullname AS coursefullname
          FROM {local_essay_ai_assess} a
          JOIN {assign} asg ON asg.id = a.assignmentid
          JOIN {user} u ON u.id = a.userid
          JOIN {course} c ON c.id = asg.course
         WHERE a.id = ?
    ", array($assessmentid));

    if (!$assessment) {
        print_error('Assessment not found', 'local_essay_ai_grader');
    }

    $PAGE->set_url(new moodle_url('/local/essay_ai_grader/view_assessment_detail.php', array('id' => $assessmentid)));
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title('Assessment #' . $assessmentid);
    $PAGE->set_heading('Assessment #' . $assessmentid);

    echo $OUTPUT->header();
    echo $OUTPUT->heading('Assessment Detail #' . $assessmentid);

    // Back link
    echo html_writer::start_div('mb-3');
    echo html_writer::link(
        new moodle_url('/local/essay_ai_grader/assessment_history.php'),
        '← Back to History',
        array('class' => 'btn btn-secondary')
    );
    echo html_writer::end_div();

    // Basic Information Card
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-header');
    echo html_writer::tag('h5', 'Basic Information', array('class' => 'mb-0'));
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');

    $table = new html_table();
    $table->attributes = array('class' => 'table table-borderless');
    $statusclass = $assessment->status === 'completed' ? 'bg-success' : 
                   ($assessment->status === 'error' ? 'bg-danger' : 
                    ($assessment->status === 'pending' ? 'bg-warning' : 'bg-info'));
    
    $table->data = array(
        array(html_writer::tag('strong', 'Assessment ID:'), $assessment->id),
        array(html_writer::tag('strong', 'Status:'), html_writer::tag('span', ucfirst($assessment->status), 
            array('class' => 'badge ' . $statusclass))),
        array(html_writer::tag('strong', 'Course:'), s($assessment->coursefullname)),
        array(html_writer::tag('strong', 'Assignment:'), s($assessment->assignmentname)),
        array(html_writer::tag('strong', 'Student:'), fullname((object)array('firstname' => $assessment->firstname, 'lastname' => $assessment->lastname)) . ' (' . s($assessment->email) . ')'),
        array(html_writer::tag('strong', 'Assessment Time:'), userdate($assessment->timemodified)),
    );

    echo html_writer::table($table);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Score & Feedback Card
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-header');
    echo html_writer::tag('h5', 'Assessment Results', array('class' => 'mb-0'));
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');

    echo html_writer::start_div('row');

    // Score
    echo html_writer::start_div('col-md-4');
    echo html_writer::tag('h6', 'Score');
    $score_display = $assessment->score !== null ? format_float((float)$assessment->score, 2) . '%' : 'Not Scored';
    echo html_writer::tag('p', $score_display, array('class' => 'fs-4 fw-bold text-primary'));
    echo html_writer::end_div();

    // Status Badge
    echo html_writer::start_div('col-md-4');
    echo html_writer::tag('h6', 'Assessment Status');
    echo html_writer::tag('span', ucfirst($assessment->status), array('class' => 'badge ' . $statusclass . ' fs-6'));
    echo html_writer::end_div();

    // Submission ID
    echo html_writer::start_div('col-md-4');
    echo html_writer::tag('h6', 'Submission ID');
    echo html_writer::tag('p', !empty($assessment->submissionid) ? $assessment->submissionid : 'N/A');
    echo html_writer::end_div();

    echo html_writer::end_div();

    // Feedback
    if (!empty($assessment->feedback)) {
        echo html_writer::tag('h6', 'AI Feedback');
        echo html_writer::tag('div', $assessment->feedback, array('class' => 'alert alert-info'));
    }

    // Error Message
    if (!empty($assessment->error_message)) {
        echo html_writer::tag('h6', 'Error Message');
        echo html_writer::tag('div', s($assessment->error_message), array('class' => 'alert alert-danger'));
    }

    echo html_writer::end_div();
    echo html_writer::end_div();

    // Criteria Scores Card
    if (!empty($assessment->criteria_scores)) {
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-header');
        echo html_writer::tag('h5', 'Criteria Scores', array('class' => 'mb-0'));
        echo html_writer::end_div();
        echo html_writer::start_div('card-body');
        
        $criteria_data = json_decode($assessment->criteria_scores, true);
        if (is_array($criteria_data)) {
            $crit_table = new html_table();
            $crit_table->attributes = array('class' => 'table table-bordered');
            $crit_table->head = array('Criterion', 'Score', 'Max Score', 'Percentage');
            
            foreach ($criteria_data as $criterion => $data) {
                if (is_array($data)) {
                    $score = isset($data['score']) ? $data['score'] : 'N/A';
                    $max_score = isset($data['max_score']) ? $data['max_score'] : 'N/A';
                    $percentage = (is_numeric($score) && is_numeric($max_score) && $max_score > 0) 
                        ? format_float(($score / $max_score) * 100, 1) . '%' 
                        : 'N/A';
                    
                    $crit_table->data[] = array(
                        s($criterion),
                        s($score),
                        s($max_score),
                        $percentage
                    );
                }
            }
            
            echo html_writer::table($crit_table);
        } else {
            echo html_writer::tag('p', s($assessment->criteria_scores));
        }
        
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    // API Response Card (for debugging)
    $raw_api_response = !empty($assessment->gemini_response_raw) ? $assessment->gemini_response_raw : $assessment->gemini_response;
    if (!empty($raw_api_response)) {
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-header');
        echo html_writer::tag('h5', 'API Response (Raw)', array('class' => 'mb-0'));
        echo html_writer::end_div();
        echo html_writer::start_div('card-body');
        
        if (is_string($raw_api_response)) {
            $api_data = json_decode($raw_api_response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo html_writer::tag('pre', json_encode($api_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 
                    array('class' => 'bg-light p-3 rounded'));
            } else {
                echo html_writer::tag('pre', s($raw_api_response), array('class' => 'bg-light p-3 rounded'));
            }
        }
        
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    // Submission Content Card (with safe field access)
    $submissiontext = '';
    if (!empty($assessment->submissionid)) {
        $submissiontext = $DB->get_field('assignsubmission_onlinetext', 'onlinetext', 
            array('submission' => $assessment->submissionid), IGNORE_MISSING);
    }

    if (!empty($submissiontext)) {
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-header');
        echo html_writer::tag('h5', 'Student Submission', array('class' => 'mb-0'));
        echo html_writer::end_div();
        echo html_writer::start_div('card-body');
        
        // Limit display to first 500 characters with show more option
        $preview_length = 500;
        if (strlen($submissiontext) > $preview_length) {
            echo html_writer::tag('div', substr(s($submissiontext), 0, $preview_length) . '...', array('id' => 'submission_preview'));
            echo html_writer::tag('button', 'Show Full Submission', array(
                'class' => 'btn btn-sm btn-outline-primary mt-2',
                'onclick' => "document.getElementById('submission_preview').style.display='none'; document.getElementById('submission_full').style.display='block'; this.style.display='none';"
            ));
            echo html_writer::tag('div', nl2br(s($submissiontext)), array(
                'id' => 'submission_full',
                'style' => 'display:none;',
                'class' => 'mt-3'
            ));
        } else {
            echo html_writer::tag('div', nl2br(s($submissiontext)));
        }
        
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    // Links
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-header');
    echo html_writer::tag('h5', 'Quick Links', array('class' => 'mb-0'));
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');

    echo html_writer::link(
        new moodle_url('/mod/assign/view.php', array('id' => $assessment->assignmentid)),
        'View Assignment',
        array('class' => 'btn btn-sm btn-outline-primary me-2', 'target' => '_blank')
    );

    echo html_writer::link(
        new moodle_url('/mod/assign/view.php', array('id' => $assessment->assignmentid, 'action' => 'grade', 'userid' => $assessment->userid)),
        'Grade Submission',
        array('class' => 'btn btn-sm btn-outline-primary me-2', 'target' => '_blank')
    );

    echo html_writer::link(
        new moodle_url('/user/profile.php', array('id' => $assessment->userid)),
        'View Student Profile',
        array('class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank')
    );

    echo html_writer::end_div();
    echo html_writer::end_div();

    echo $OUTPUT->footer();

} catch (Exception $e) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading('Error');
    echo html_writer::tag('div', 'Error: ' . s($e->getMessage()), array('class' => 'alert alert-danger'));
    echo $OUTPUT->footer();
}
