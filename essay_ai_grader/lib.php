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
 * Plugin lib functions for Essay AI Grader.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Explicit require to ensure classes are loaded
require_once(dirname(__FILE__) . '/classes/criteria_config_form.php');

/**
 * Lightweight file logger for assignment config save debugging.
 *
 * @param string $message
 * @param array $context
 * @return void
 */
function local_essay_ai_grader_debug_save_log($message, array $context = array()) {
    $logfile = __DIR__ . '/save_debug.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    @file_put_contents($logfile, $line . PHP_EOL, FILE_APPEND);
}

/**
 * Log uncaught exceptions.
 */
function local_essay_ai_grader_log_callback_exception(\Throwable $e, $context) {
    return;
}

/**
 * Extend navigation - DISABLED to prevent infinite loop issue.
 * Navigation loops were causing recursive hook execution.
 * Use /local/essay_ai_grader/review_assessments.php directly instead.
 */
function local_essay_ai_grader_extend_navigation_module($a, $b, $c) {
    return;
}

/**
 * Extend main navigation - DISABLED to prevent infinite loop issue.
 * Use /local/essay_ai_grader/dashboard.php directly instead.
 */
function local_essay_ai_grader_extend_navigation($navigation) {
    return;
}

/**
 * Queue submission for AI assessment - handled by observer.php instead.
 * Submissions are automatically queued when submitted via observer events.
 */
function local_essay_ai_grader_queue_submission($a, $b, $c) {
    return;
}

/**
 * Get assessment status for a submission.
 *
 * @param int $assignmentid The assignment ID
 * @param int $userid The user ID
 * @return object|null The assessment status object or null
 */
function local_essay_ai_grader_get_assessment_status($assignmentid, $userid) {
    global $DB;
    
    if (!$assignmentid || !$userid) {
        return null;
    }
    
    // Get the assessment record
    $assessment = $DB->get_record('local_essay_ai_assess', array(
        'assignmentid' => $assignmentid,
        'userid' => $userid
    ), '*', IGNORE_MISSING);
    
    return $assessment;
}

/**
 * Render the AI grader review button.
 * 
 * @param int $assignmentid The assignment ID
 * @return string HTML for the button
 */
function local_essay_ai_grader_render_button($assignmentid = null, $b = null, $c = null) {
    if (!$assignmentid) {
        return '';
    }
    
    $helper = new \local_essay_ai_grader\assignment_renderer_helper();
    return $helper->get_review_button($assignmentid);
}

/**
 * Inject grading interface hook - handled by output fragments.
 */
function local_essay_ai_grader_inject_grading_interface($hook) {
    return;
}

/**
 * Extend the course module standard elements to include Essay AI Grader configuration.
 * This hook is called by Moodle during form building and allows plugins to add custom form elements.
 *
 * This function is called automatically by Moodle when rendering the assignment edit form.
 * The function name follows the pattern: local_<pluginname>_coursemodule_standard_elements
 *
 * @param object $formwrapper The form wrapper object
 * @param object $mform The Moodle form object
 */
function local_essay_ai_grader_coursemodule_standard_elements($formwrapper, $mform) {
    global $COURSE, $DB;
    
    // Debug: Always log hook call
    error_log('AI FORM HOOK CALLED: ' . get_class($formwrapper) . ' for assignmentid: ' . ($assignmentid ?? 'unknown'));
    
    // More reliable check - try multiple methods
    $class = get_class($formwrapper);
    $is_assign = (strpos($class, 'mod_assign') !== false || 
                  strpos($class, 'assign') !== false || 
                  isset($formwrapper->modname) && $formwrapper->modname === 'assign');
    
    if (!$is_assign) {
        error_log('AI FORM SKIPPED - not assign: ' . $class);
        return;
    }
    
    // Get the assignment id from the form (works for both new and existing)
    $assignmentid = null;
    $instance = $mform->getElementValue('instance');
    if (!empty($instance)) {
        $assignmentid = $instance[0];
    }
    
    // Get assignment object if exists
    $assignment = null;
    if (!empty($assignmentid)) {
        $assignment = $DB->get_record('assign', array('id' => $assignmentid));
    }
    
    // For new assignments, create a temporary object
    if (empty($assignment)) {
        $assignment = new stdClass();
        $assignment->id = 0;
        $assignment->courseid = $COURSE->id;
    }
    
    try {
        // Detect which rubric type is being used
        $rubric_type = \local_essay_ai_grader\criteria_config_form::detect_rubric_type($assignment->id);
        
        error_log('DEBUG: essay_ai_grader hook called - assignmentid=' . $assignment->id . ', rubric_type=' . ($rubric_type ?? 'null'));
        
        // Default to 5-aspect rubric (new/updated rubric system)
        // Only use 6-aspect if explicitly detected
        if ($rubric_type === 'six_aspects') {
            error_log('DEBUG: Adding 6-aspect form for assignment ' . $assignment->id);
            \local_essay_ai_grader\criteria_config_form::add_criteria_fields_to_form($mform, $assignment);
            error_log('DEBUG: 6-aspect form added successfully');
        } else {
            // For new assignments (null) or 5-aspect assignments, use 5-aspect form
            error_log('DEBUG: Adding 5-aspect form for assignment ' . $assignment->id . ' (rubric_type=' . ($rubric_type ?? 'new') . ')');
            \local_essay_ai_grader\criteria_config_form::add_five_aspects_rubric_fields_to_form($mform, $assignment);
            error_log('DEBUG: 5-aspect form added successfully');
        }
        
    } catch (Exception $e) {
        // Log the exception
        error_log('ERROR in essay_ai_grader hook - Exception: ' . $e->getMessage());
        return;
    } catch (Throwable $e) {
        // Log the error
        error_log('ERROR in essay_ai_grader hook - Throwable: ' . $e->getMessage());
        return;
    }
}

// NOTE: coursemodule_definition is NOT called by Moodle 5
// The correct hook is coursemodule_standard_elements (see above)

/**
 * Auto-enable AI grading for new assignments.
 * Handle post-action when assignment is edited/created.
 * Saves Essay AI Grader configuration from form data.
 * AUTO-ENABLES for new assignments.
 *
 * @param object $data The form data
 * @param object $assignment The assignment object
 */
function local_essay_ai_grader_coursemodule_edit_post_actions($data, $assignment) {
    global $DB;

    // Get assignment ID - works for both new and existing
    $assignmentid = null;
    if (!empty($data->instance)) {
        $assignmentid = $data->instance;
    } elseif (!empty($assignment->instance)) {
        $assignmentid = $assignment->instance;
    } elseif (!empty($assignment->id)) {
        $assignmentid = $assignment->id;
    }
    
    if (empty($assignmentid)) {
        local_essay_ai_grader_debug_save_log('post_actions_missing_assignmentid', array(
            'data_instance' => $data->instance ?? null,
            'assignment_id' => $assignment->id ?? null,
            'assignment_instance' => $assignment->instance ?? null,
        ));
        return $data;
    }

    try {
        local_essay_ai_grader_debug_save_log('post_actions_enter', array(
            'assignmentid' => $assignmentid,
            'enabled' => $data->essay_ai_enabled ?? null,
            'maxscore' => $data->essay_ai_maxscore ?? null,
            'answerkey_len' => isset($data->essay_ai_answerkey) ? core_text::strlen((string)$data->essay_ai_answerkey) : null,
            'rubricguide_len' => isset($data->essay_ai_rubricguide) ? core_text::strlen((string)$data->essay_ai_rubricguide) : null,
        ));

        // Detect which rubric type is being used (5-aspect or 6-aspect)
        $rubric_type = \local_essay_ai_grader\criteria_config_form::detect_rubric_type($assignmentid);
        
        // Check if form has 5-aspect rubric data (check for 5-aspect specific fields with NEW key names)
        $has_five_aspect_data = !empty($data->essay_ai_five_aspects_enabled) || 
                                !empty($data->essay_ai_five_aspect_relevansi_ketepatan_isi_enabled) ||
                                !empty($data->essay_ai_five_aspect_relevansi_ketepatan_isi_weight) ||
                                !empty($data->essay_ai_five_aspect_kelengkapan_konsep_enabled) ||
                                !empty($data->essay_ai_five_aspect_kesesuaian_konteks_enabled) ||
                                !empty($data->essay_ai_question_text) || 
                                !empty($data->essay_ai_expected_answer);
        
        if ($has_five_aspect_data) {
            // Save as 5-aspect rubric
            $saved = \local_essay_ai_grader\criteria_config_form::save_five_aspects_rubric_from_form(
                (object)array('id' => (int)$assignmentid),
                $data
            );
        } else {
            // Save as 6-aspect rubric (legacy)
            $saved = \local_essay_ai_grader\criteria_config_form::save_criteria_from_form(
                (object)array('id' => (int)$assignmentid),
                $data
            );
        }

        $record = \local_essay_ai_grader\criteria_config_form::get_config_for_assignment((int)$assignmentid);
        local_essay_ai_grader_debug_save_log('post_actions_saved', array(
            'assignmentid' => $assignmentid,
            'saved' => (bool)$saved,
            'record_found' => (bool)$record,
            'record_enabled' => $record->enabled ?? null,
            'record_maxscore' => $record->maxscore ?? null,
            'record_answerkey_len' => isset($record->answerkey) ? core_text::strlen((string)$record->answerkey) : null,
            'record_rubricguide_len' => isset($record->rubricguide) ? core_text::strlen((string)$record->rubricguide) : null,
            'record_criteria_config' => !empty($record->criteria_config),
        ));

        if (!$saved) {
            error_log('Essay AI Grader save returned false for assignment ' . $assignmentid);
        }
    } catch (Exception $e) {
        local_essay_ai_grader_debug_save_log('post_actions_exception', array(
            'assignmentid' => $assignmentid,
            'message' => $e->getMessage(),
        ));
        error_log('Essay AI Grader save error: ' . $e->getMessage());
    } catch (Throwable $e) {
        local_essay_ai_grader_debug_save_log('post_actions_throwable', array(
            'assignmentid' => $assignmentid,
            'message' => $e->getMessage(),
        ));
        error_log('Essay AI Grader fatal error: ' . $e->getMessage());
    }
    
    return $data;  // IMPORTANT: Hook must return the data object
}

/**
 * Output a fragment for the essay AI grader review button.
 *
 * @param array $args The args for the fragment
 * @return string The HTML output
 */
function local_essay_ai_grader_output_fragment_essay_ai_grader_review_button($args) {
    if (empty($args['assignmentid'])) {
        return '';
    }
    
    $helper = new \local_essay_ai_grader\assignment_renderer_helper();
    return $helper->get_review_button((int)$args['assignmentid']);
}
?>
