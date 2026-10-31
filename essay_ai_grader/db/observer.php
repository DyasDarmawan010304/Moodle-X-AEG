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
 * Event observer for Essay AI Grader.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Observer for assignment submission events.
 */
class local_essay_ai_grader_observer {
    /**
     * Auto-enable AI grading config when a new assignment module is created.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        // Assignment AI config is persisted via coursemodule_edit_post_actions() in lib.php.
        // Avoid writing here as well because duplicate inserts can break the create transaction.
        return;
    }

    /**
     * Save form data when assignment module is updated.
     *
     * @param \core\event\course_module_updated $event
     * @return void
     */
    public static function course_module_updated(\core\event\course_module_updated $event) {
        // Fallback save path for assignment edit forms.
        // Some installations do not reliably execute the local callback
        // coursemodule_edit_post_actions(), so we normalize the current
        // request and persist it through the same helper.
        if ($event->modulename !== 'assign' || empty($event->instance)) {
            return;
        }

        $hasessayfields = isset($_POST['essay_ai_enabled']) || isset($_POST['essay_ai_maxscore']) ||
            isset($_POST['essay_ai_answerkey']) || isset($_POST['essay_ai_rubricguide']);

        if (!$hasessayfields) {
            return;
        }

        $data = new \stdClass();
        $data->essay_ai_enabled = !empty($_POST['essay_ai_enabled']) ? 1 : 0;
        $data->essay_ai_maxscore = isset($_POST['essay_ai_maxscore']) ? (int)$_POST['essay_ai_maxscore'] : 100;
        $data->essay_ai_answerkey = $_POST['essay_ai_answerkey'] ?? '';
        $data->essay_ai_rubricguide = $_POST['essay_ai_rubricguide'] ?? '';

        foreach (array('content', 'organization', 'grammar', 'vocabulary', 'spelling', 'relevance') as $criterionid) {
            $checkbox = 'essay_ai_crit_' . $criterionid;
            $weight = 'essay_ai_weight_' . $criterionid;
            $data->$checkbox = !empty($_POST[$checkbox]) ? 1 : 0;
            $data->$weight = isset($_POST[$weight]) ? (int)$_POST[$weight] : 0;
        }

        try {
            \local_essay_ai_grader\criteria_config_form::save_criteria_from_form(
                (object)array('id' => (int)$event->instance),
                $data
            );
        } catch (\Throwable $e) {
            error_log('[local_essay_ai_grader] observer course_module_updated save failed: ' . $e->getMessage());
        }
    }

    
    /**
     * Handle submission_created event.
     *
     * @param \mod_assign\event\submission_created $event The event object.
     * @return void
     */
    public static function submission_created(\mod_assign\event\submission_created $event) {
        self::queue_submission($event);
    }
    
    /**
     * Handle submission_updated event.
     *
     * @param \mod_assign\event\submission_updated $event The event object.
     * @return void
     */
    public static function submission_updated(\mod_assign\event\submission_updated $event) {
        self::queue_submission($event);
    }
    
    /**
     * Queue a submission for assessment.
     *
     * @param object $event The event object.
     * @return void
     */
    private static function queue_submission($event) {
        global $DB;

        try {
            // Get submission data from assign event payload.
            $submissionid = !empty($event->other['submissionid']) ? (int)$event->other['submissionid'] : 0;
            $assignmentid = 0;
            if (method_exists($event, 'get_assign')) {
                $assignobj = $event->get_assign();
                if ($assignobj && $assignobj->get_instance()) {
                    $assignmentid = (int)$assignobj->get_instance()->id;
                }
            }
            if (!$assignmentid && !empty($event->other['assignid'])) {
                // Backward-compatible fallback for custom events that provide assignid in other.
                $assignmentid = (int)$event->other['assignid'];
            }
            if (!$assignmentid && !empty($event->contextinstanceid)) {
                // Fallback: context instance id for assignment events
                $assignmentid = (int)$event->contextinstanceid;
            }
            $userid = $event->userid;

            if ($submissionid <= 0 || $assignmentid <= 0 || $userid <= 0) {
                return;
            }

            // Check if this assignment has AI grading enabled.
            $config = $DB->get_record('local_essay_ai_config', array('assignid' => $assignmentid));
            if (!$config || empty($config->enabled)) {
                return;
            }

            // Prevent duplicate queue records for same submission, but allow
            // re-queueing failed attempts when the student updates/submits again.
            $existing = $DB->get_record('local_essay_ai_assess', array('submissionid' => $submissionid));
            if ($existing) {
                if ($existing->status === 'error') {
                    $update = new stdClass();
                    $update->id = $existing->id;
                    $update->status = 'pending';
                    $update->error_message = null;
                    $update->timemodified = time();
                    $update->modified = time();
                    $DB->update_record('local_essay_ai_assess', $update);
                }
                return;
            }

            // Create pending assessment row.
            $now = time();
            $assessment = new stdClass();
            $assessment->assignmentid = $assignmentid;
            $assessment->submissionid = $submissionid;
            $assessment->userid = $userid;
            $assessment->status = 'pending';
            $assessment->timecreated = $now;
            $assessment->timemodified = $now;
            $assessment->created = $now;
            $assessment->modified = $now;

            $assessmentid = $DB->insert_record('local_essay_ai_assess', $assessment);

            // Optional immediate processing. If disabled, keep queue async via scheduled task.
            $autogradeonsubmit = get_config('local_essay_ai_grader', 'auto_grade_on_submit');
            if (!empty($autogradeonsubmit)) {
                try {
                    \local_essay_ai_grader\assessment_manager::process_assessment($assessmentid);
                } catch (Exception $e) {
                    // Keep as pending/error fallback handled by scheduled task and retries.
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\local_essay_ai_grader\logging_manager')) {
                \local_essay_ai_grader\logging_manager::log_exception($e, 'observer queue_submission');
            } else {
                error_log('[local_essay_ai_grader] observer queue_submission: ' . $e->getMessage());
            }
        }
    }
}
