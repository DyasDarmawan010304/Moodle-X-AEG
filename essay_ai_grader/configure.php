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
 * Enable/configure AI grading for an assignment.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/formslib.php');

global $CFG, $DB, $USER, $OUTPUT, $PAGE;

// Accept either a course module id (`cmid`) or an assignment id (`id`).
$cmid = optional_param('cmid', 0, PARAM_INT);
$assignid = optional_param('id', 2, PARAM_INT); // Default to assignment 2 for testing

// Resolve course module and assignment record.
if ($cmid) {
    // Get course module by cmid.
    $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
    $assignment = $DB->get_record('assign', array('id' => $cm->instance), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
} else if ($assignid) {
    // Get course module by assignment instance id.
    $cm = get_coursemodule_from_instance('assign', $assignid, 0, false, MUST_EXIST);
    $assignment = $DB->get_record('assign', array('id' => $assignid), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $cmid = $cm->id;
} else {
    print_error('invalidparam', 'local_essay_ai_grader');
}

// Check capabilities.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('local/essay_ai_grader:configure', $context);

// Check AI provider configuration for this page.
$provider = get_config('local_essay_ai_grader', 'provider') ?: 'gemini';
if ($provider === 'gemini') {
    $apikey = get_config('local_essay_ai_grader', 'gemini_api_key');
    if (empty($apikey)) {
        print_error('apikey_not_configured', 'local_essay_ai_grader');
    }
}

// Get existing config.
$config = \local_essay_ai_grader\criteria_config_form::get_config_for_assignment((int)$assignment->id);

// Set up page.
$PAGE->set_url('/local/essay_ai_grader/configure.php', array('cmid' => $cmid));
$PAGE->set_title(get_string('pluginname', 'local_essay_ai_grader'));
$PAGE->set_heading(get_string('pluginname', 'local_essay_ai_grader'));

// Process form.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $formdata = new stdClass();
    $formdata->essay_ai_enabled = optional_param('enabled', 0, PARAM_INT);
    $formdata->essay_ai_maxscore = optional_param('maxscore', 100, PARAM_INT);
    $formdata->essay_ai_answerkey = optional_param('answerkey', '', PARAM_RAW);
    $formdata->essay_ai_rubricguide = optional_param('rubricguide', '', PARAM_RAW);
    $prompt = optional_param('prompttemplate', '', PARAM_RAW);

    // Map legacy 3-criteria configure page to the unified save format.
    $formdata->essay_ai_crit_grammar = optional_param('eval_grammar', 0, PARAM_INT);
    $formdata->essay_ai_crit_content = optional_param('eval_content', 0, PARAM_INT);
    $formdata->essay_ai_crit_organization = optional_param('eval_organization', 0, PARAM_INT);
    $formdata->essay_ai_crit_vocabulary = 0;
    $formdata->essay_ai_crit_spelling = 0;
    $formdata->essay_ai_crit_relevance = 0;

    $formdata->essay_ai_weight_grammar = 34;
    $formdata->essay_ai_weight_content = 33;
    $formdata->essay_ai_weight_organization = 33;
    $formdata->essay_ai_weight_vocabulary = 0;
    $formdata->essay_ai_weight_spelling = 0;
    $formdata->essay_ai_weight_relevance = 0;

    $save_success = false;
    $error_message = '';

    try {
        $save_success = \local_essay_ai_grader\criteria_config_form::save_criteria_from_form(
            (object)['id' => (int)$assignment->id],
            $formdata
        );

        $config = \local_essay_ai_grader\criteria_config_form::get_config_for_assignment((int)$assignment->id);
        if ($config) {
            $config->prompttemplate = $prompt;
            $config->timemodified = time();
            $DB->update_record('local_essay_ai_config', $config);
        }

        if ($save_success) {
            // Verify the save by reading back
            $verify = \local_essay_ai_grader\criteria_config_form::get_config_for_assignment((int)$assignment->id);
            if ($verify &&
                    $verify->maxscore == $formdata->essay_ai_maxscore &&
                    $verify->rubricguide == $formdata->essay_ai_rubricguide) {
                // Success - redirect
                $url = new moodle_url('/mod/assign/view.php', array('id' => $cmid));
                redirect($url, get_string('config_saved', 'local_essay_ai_grader'), 3);
            } else {
                $error_message = 'Configuration saved but verification failed - data may not have persisted';
                error_log('Essay AI Grader verify error for assignment ' . $assignment->id . ': ' . $error_message);
            }
        } else {
            $error_message = 'Failed to save configuration to database';
            error_log('Essay AI Grader save error for assignment ' . $assignment->id . ': ' . $error_message);
        }
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
        error_log('Essay AI Grader exception for assignment ' . $assignment->id . ': ' . $error_message);
    }
}

echo $OUTPUT->header();

// Display error if any
if (!empty($error_message)) {
    echo html_writer::div($error_message, 'alert alert-danger');
}

// Reload config after potential save to show latest values
$config = \local_essay_ai_grader\criteria_config_form::get_config_for_assignment((int)$assignment->id);

// Display form.
echo html_writer::start_div('card');
echo html_writer::start_div('card-header');
echo html_writer::tag('h4', $assignment->name, array('class' => 'card-title'));
echo html_writer::end_div();

echo html_writer::start_div('card-body');

// Quick navigation buttons.
$dashboardurl = new moodle_url('/local/essay_ai_grader/dashboard.php');
$monitorurl = new moodle_url('/local/essay_ai_grader/monitor.php');
echo html_writer::start_div('mb-3');
echo html_writer::link($dashboardurl, get_string('viewdashboard', 'local_essay_ai_grader'),
    array('class' => 'btn btn-secondary me-2'));
echo ' ';
echo html_writer::link($monitorurl, get_string('monitor_title', 'local_essay_ai_grader'),
    array('class' => 'btn btn-primary'));
echo html_writer::end_div();

// Safely decode criteria if config exists.
$criteria = array();
if ($config && !empty($config->criteria_config)) {
    $criteriaconfig = json_decode($config->criteria_config, true) ?? array();
    foreach ($criteriaconfig as $criterionid => $criteriondata) {
        if (!empty($criteriondata['enabled'])) {
            $criteria[$criterionid] = 1;
        }
    }
} else if ($config && !empty($config->criteria)) {
    $criteria = json_decode($config->criteria, true) ?? array();
}

// Re-render form with proper values.
?>
<form method="post" action="">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    
    <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="enabled" name="enabled" value="1" 
               <?php echo ($config && $config->enabled) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="enabled">
            <?php echo get_string('enable_ai_grading', 'local_essay_ai_grader'); ?>
        </label>
    </div>
    
    <div class="form-group mb-3">
        <label for="maxscore"><?php echo get_string('max_score_label', 'local_essay_ai_grader'); ?>:</label>
        <input type="number" class="form-control" id="maxscore" name="maxscore" 
               value="<?php echo $config && !empty($config->maxscore) ? $config->maxscore : (isset($assignment->grade) && !empty($assignment->grade) ? $assignment->grade : 100); ?>" min="1" max="1000" required>
    </div>
    
    <div class="form-group mb-3">
        <label><?php echo get_string('eval_criteria_label', 'local_essay_ai_grader'); ?>:</label>
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="eval_grammar" name="eval_grammar" value="1"
                   <?php echo (empty($config) || isset($criteria['grammar'])) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="eval_grammar">
                <?php echo get_string('eval_grammar', 'local_essay_ai_grader'); ?>
            </label>
        </div>
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="eval_content" name="eval_content" value="1"
                   <?php echo (empty($config) || isset($criteria['content'])) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="eval_content">
                <?php echo get_string('eval_content', 'local_essay_ai_grader'); ?>
            </label>
        </div>
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="eval_organization" name="eval_organization" value="1"
                   <?php echo (empty($config) || isset($criteria['organization'])) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="eval_organization">
                <?php echo get_string('eval_organization', 'local_essay_ai_grader'); ?>
            </label>
        </div>
    </div>
    
    <div class="form-group">
        <label for="prompttemplate"><?php echo get_string('custom_prompt_label', 'local_essay_ai_grader'); ?>:</label>
        <textarea class="form-control" id="prompttemplate" name="prompttemplate" rows="5"><?php echo $config ? htmlspecialchars($config->prompttemplate) : ''; ?></textarea>
        <small class="form-text text-muted"><?php echo get_string('custom_prompt_help', 'local_essay_ai_grader'); ?></small>
    </div>

    <div class="form-group mt-3">
        <label for="answerkey"><?php echo get_string('answer_key_label', 'local_essay_ai_grader'); ?>:</label>
        <textarea class="form-control" id="answerkey" name="answerkey" rows="5"><?php echo $config ? htmlspecialchars($config->answerkey ?? '') : ''; ?></textarea>
        <small class="form-text text-muted"><?php echo get_string('answer_key_help', 'local_essay_ai_grader'); ?></small>
    </div>

    <div class="form-group mt-3">
        <label for="rubricguide"><?php echo get_string('rubric_guide_label', 'local_essay_ai_grader'); ?>:</label>
        <textarea class="form-control" id="rubricguide" name="rubricguide" rows="6"><?php echo $config ? htmlspecialchars($config->rubricguide ?? '') : ''; ?></textarea>
        <small class="form-text text-muted"><?php echo get_string('rubric_guide_help', 'local_essay_ai_grader'); ?></small>
    </div>
    
    <button type="submit" class="btn btn-primary">
        <?php echo get_string('btn_save', 'local_essay_ai_grader'); ?>
    </button>
    <a href="<?php echo new moodle_url('/mod/assign/view.php', array('id' => $cmid)); ?>" class="btn btn-secondary">
        <?php echo get_string('btn_cancel', 'local_essay_ai_grader'); ?>
    </a>
</form>
<?php

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

echo $OUTPUT->footer();
