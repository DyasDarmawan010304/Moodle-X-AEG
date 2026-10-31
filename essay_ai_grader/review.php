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
 * Review AI-generated assessments page.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

global $CFG, $DB, $USER, $OUTPUT, $PAGE;

$assignmentid = required_param('assignmentid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$assessmentid = optional_param('assessmentid', 0, PARAM_INT);

// Get assignment.
$assignment = $DB->get_record('assign', array('id' => $assignmentid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $assignment->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id, false, MUST_EXIST);

// Check permissions.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/assign:grade', $context); // Allow any teacher who can grade assignments

// Set up page.
$PAGE->set_url('/local/essay_ai_grader/review.php', array('assignmentid' => $assignmentid));
$PAGE->set_title($assignment->name . ' - ' . get_string('title_review_assessments', 'local_essay_ai_grader'));
$PAGE->set_heading($assignment->name . ' - ' . get_string('title_review_assessments', 'local_essay_ai_grader'));
$PAGE->set_pagelayout('incourse');

/**
 * Remove markdown code-fences and decode JSON text safely.
 *
 * @param string $text
 * @return array|null
 */
function local_essay_ai_grader_decode_json_text($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return null;
    }

    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $text = trim($text);

    $decoded = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $candidate = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

/**
 * Extract parsed model output from raw provider response JSON.
 *
 * @param string $rawresponse
 * @return array{score:float|null,criteria:array,text:string}
 */
function local_essay_ai_grader_extract_model_output($rawresponse) {
    $result = array(
        'score' => null,
        'criteria' => array(),
        'text' => '',
    );

    $decoded = json_decode((string)$rawresponse, true);
    if (!is_array($decoded)) {
        return $result;
    }

    $text = '';
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        $text = (string)$decoded['candidates'][0]['content']['parts'][0]['text'];
    } else if (isset($decoded['message']['content']) && is_string($decoded['message']['content'])) {
        $text = (string)$decoded['message']['content'];
    }
    $result['text'] = trim($text);

    $assessment = local_essay_ai_grader_decode_json_text($text);
    if (!is_array($assessment)) {
        return $result;
    }

    if (isset($assessment['score']) && is_numeric($assessment['score'])) {
        $result['score'] = (float)$assessment['score'];
    }

    if (!empty($assessment['criteria_scores']) && is_array($assessment['criteria_scores'])) {
        foreach ($assessment['criteria_scores'] as $name => $value) {
            if (is_numeric($value)) {
                $result['criteria'][$name] = (float)$value;
            }
        }
    }

    return $result;
}

// Handle actions.
if ($action === 'accept' && $assessmentid > 0) {
    require_sesskey();
    
    $assessment = $DB->get_record('local_essay_ai_assess', array('id' => $assessmentid));
    if ($assessment) {
        \local_essay_ai_grader\assessment_manager::review_assessment($assessmentid, true, null, '');
        
        // Save grade to assignment if accepted.
        \local_essay_ai_grader\assessment_manager::save_grade_to_assignment(
            $assessment->assignmentid, 
            $assessment->userid, 
            $assessment->score
        );
        
        \core\notification::success(get_string('grade_accepted', 'local_essay_ai_grader'));
    }
} else if ($action === 'reject' && $assessmentid > 0) {
    require_sesskey();
    
    $assessment = $DB->get_record('local_essay_ai_assess', array('id' => $assessmentid));
    if ($assessment) {
        \local_essay_ai_grader\assessment_manager::review_assessment($assessmentid, false, null, '');
        
        \core\notification::info(get_string('grade_rejected', 'local_essay_ai_grader'));
    }
} else if ($action === 'update' && $assessmentid > 0) {
    require_sesskey();

    $finalgrade = optional_param('finalgrade', null, PARAM_FLOAT);
    $teacherfeedback = optional_param('teacher_feedback', '', PARAM_RAW);

    $assessment = $DB->get_record('local_essay_ai_assess', array('id' => $assessmentid));
    if ($assessment && $finalgrade !== null) {
        \local_essay_ai_grader\assessment_manager::review_assessment($assessmentid, false, $finalgrade, $teacherfeedback);
        \local_essay_ai_grader\assessment_manager::save_grade_to_assignment(
            $assessment->assignmentid,
            $assessment->userid,
            $finalgrade
        );

        \core\notification::success(get_string('grade_updated', 'local_essay_ai_grader'));
    }
}

// Output page.
echo $OUTPUT->header();

// Get pending assessments.
$assessments = \local_essay_ai_grader\assessment_manager::get_completed_assessments($assignmentid);

if (empty($assessments)) {
    echo $OUTPUT->notification(get_string('no_assessments', 'local_essay_ai_grader'), 'info');
} else {
    echo $OUTPUT->heading(count($assessments) . ' ' . get_string('reviewassessments', 'local_essay_ai_grader'));
    
    foreach ($assessments as $assessment) {
        // Get student info.
        $student = $DB->get_record('user', array('id' => $assessment->userid));
        
        // Display assessment card.
        $output = '';
        $output .= html_writer::start_div('card mb-3');
        $output .= html_writer::start_div('card-header');
        $output .= html_writer::tag('h5', fullname($student), array('class' => 'card-title'));
        $output .= html_writer::end_div();
        
        $output .= html_writer::start_div('card-body');
        
        // AI Score.
        $output .= html_writer::tag('p', 
            html_writer::tag('strong', get_string('ai_score', 'local_essay_ai_grader') . ': ') . 
            round($assessment->score, 2),
            array('class' => 'card-text')
        );

        $modeloutput = local_essay_ai_grader_extract_model_output((string)($assessment->gemini_response ?? ''));
        if (!is_null($modeloutput['score'])) {
            $output .= html_writer::tag('p',
                html_writer::tag('strong', get_string('llm_raw_score', 'local_essay_ai_grader') . ': ') .
                format_float((float)$modeloutput['score'], 2),
                array('class' => 'card-text')
            );
        }
        
        // AI Feedback.
        $output .= html_writer::tag('p',
            html_writer::tag('strong', get_string('ai_feedback', 'local_essay_ai_grader') . ':'),
            array('class' => 'card-text')
        );
        $output .= html_writer::tag('p', $assessment->feedback, array('class' => 'card-text'));
        
        // Criteria scores if available.
        $criteriasubtotal = null;
        if (!empty($assessment->criteria_scores)) {
            $criteria = json_decode($assessment->criteria_scores, true);
            if (is_array($criteria)) {
                $criteriasubtotal = 0.0;
                $output .= html_writer::tag('p', 
                    html_writer::tag('strong', get_string('criteria_scores', 'local_essay_ai_grader') . ':'),
                    array('class' => 'card-text')
                );
                
                foreach ($criteria as $name => $score) {
                    if (!is_null($score) && $score !== '') {
                        if (is_numeric($score)) {
                            $criteriasubtotal += (float)$score;
                        }
                        $output .= html_writer::tag('p', 
                            ucfirst($name) . ': ' . round($score, 2),
                            array('class' => 'card-text ml-3')
                        );
                    }
                }
            }
        }
        if (!is_null($criteriasubtotal)) {
            $output .= html_writer::tag('p',
                html_writer::tag('strong', get_string('criteria_subtotal', 'local_essay_ai_grader') . ': ') .
                format_float((float)$criteriasubtotal, 2),
                array('class' => 'card-text')
            );
        }

        if (!empty($assessment->gemini_response)) {
            $summary = get_string('llm_raw_response', 'local_essay_ai_grader');
            $rawpre = html_writer::tag('pre', s($assessment->gemini_response), array(
                'style' => 'white-space: pre-wrap; max-height: 320px; overflow:auto; background:#f8f9fa; padding:8px; border:1px solid #ddd;'
            ));
            $details = html_writer::tag('summary', s($summary), array('style' => 'cursor:pointer; font-weight:600;')) . $rawpre;
            $output .= html_writer::tag('details', $details, array('class' => 'mt-2'));
        }
        
        $output .= html_writer::end_div();
        
        // Card footer with actions.
        $output .= html_writer::start_div('card-footer bg-light');
        
        $accepturl = new moodle_url($PAGE->url, array(
            'action' => 'accept',
            'assessmentid' => $assessment->id,
            'sesskey' => sesskey(),
        ));
        $rejecturl = new moodle_url($PAGE->url, array(
            'action' => 'reject',
            'assessmentid' => $assessment->id,
            'sesskey' => sesskey(),
        ));
        
        $output .= html_writer::tag('div', 
            html_writer::link($accepturl, 
                html_writer::tag('i', '', array('class' => 'fa fa-check')) . ' ' . get_string('accept_grade', 'local_essay_ai_grader'),
                array('class' => 'btn btn-success btn-sm me-2')
            ) . 
            html_writer::link($rejecturl,
                html_writer::tag('i', '', array('class' => 'fa fa-edit')) . ' ' . get_string('reject_grade', 'local_essay_ai_grader'),
                array('class' => 'btn btn-warning btn-sm')
            ),
            array('class' => 'mb-3')
        );

        // Manual override form for teacher correction - PROMINENT.
        $output .= html_writer::tag('div', '', array('class' => 'border-top pt-3'));
        $output .= html_writer::tag('h6', get_string('edit_grade_help', 'local_essay_ai_grader'), array('class' => 'mb-2'));
        
        $output .= html_writer::start_tag('form', array(
            'method' => 'post',
            'action' => $PAGE->url->out(false),
            'class' => 'row g-2'
        ));
        $output .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ));
        $output .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'update'
        ));
        $output .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'assessmentid',
            'value' => $assessment->id
        ));
        
        // Grade input column
        $output .= html_writer::start_div('col-md-3');
        $output .= html_writer::tag('label', get_string('teacher_score', 'local_essay_ai_grader'), array(
            'for' => 'finalgrade_' . $assessment->id,
            'class' => 'form-label'
        ));
        $output .= html_writer::empty_tag('input', array(
            'type' => 'number',
            'step' => '0.01',
            'min' => '0',
            'name' => 'finalgrade',
            'id' => 'finalgrade_' . $assessment->id,
            'value' => round((float)$assessment->score, 2),
            'class' => 'form-control',
            'placeholder' => 'Enter grade'
        ));
        $output .= html_writer::end_div();
        
        // Feedback column
        $output .= html_writer::start_div('col-md-6');
        $output .= html_writer::tag('label', get_string('teacher_feedback', 'local_essay_ai_grader'), array(
            'for' => 'teacher_feedback_' . $assessment->id,
            'class' => 'form-label'
        ));
        $output .= html_writer::tag('textarea', '', array(
            'name' => 'teacher_feedback',
            'id' => 'teacher_feedback_' . $assessment->id,
            'rows' => '2',
            'class' => 'form-control',
            'placeholder' => 'Enter your feedback (optional)'
        ));
        $output .= html_writer::end_div();
        
        // Submit button column
        $output .= html_writer::start_div('col-md-3 d-flex align-items-end');
        $output .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'value' => get_string('edit_grade', 'local_essay_ai_grader'),
            'class' => 'btn btn-primary btn-sm w-100'
        ));
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_tag('form');
        
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_div();
        
        echo $output;
    }
}

echo $OUTPUT->footer();
