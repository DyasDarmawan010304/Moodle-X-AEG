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
 * Student feedback view - Display LLM feedback and score to student.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

global $CFG, $DB, $USER, $OUTPUT, $PAGE;

// Required parameters
$assignmentid = required_param('assignmentid', PARAM_INT);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

// Get assignment
$assignment = $DB->get_record('assign', array('id' => $assignmentid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $assignment->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id, false, MUST_EXIST);

// Check login
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Get assignment config for criteria weights
$config = $DB->get_record('local_essay_ai_config', array('assignid' => $assignment->id));
$max_score = $assignment->grade ?? 100;
$criteria_weights = array();

if ($config && !empty($config->criteria_config)) {
    $criteria_weights = \local_essay_ai_grader\default_criteria::parse_criteria_config($config->criteria_config);
    if (!empty($config->maxscore)) {
        $max_score = $config->maxscore;
    }
}

// Get submission
if ($submissionid == 0) {
    // Get latest submission for current user
    $submission = $DB->get_record_sql(
        'SELECT s.* FROM {assign_submission} s 
         WHERE s.assignment = ? AND s.userid = ?
         ORDER BY s.timemodified DESC
         LIMIT 1',
        array($assignmentid, $USER->id)
    );
} else {
    // Get specific submission
    $submission = $DB->get_record('assign_submission', array('id' => $submissionid), '*', MUST_EXIST);
    
    // Permission check: Student can only view their own submission
    if ($submission->userid != $USER->id) {
        require_capability('mod/assign:grade', $context);  // Teachers can view all
    }
}

if (!$submission) {
    print_error('No submission found', 'local_essay_ai_grader');
}

// Get assessment record from our plugin
$assessment = $DB->get_record('local_essay_ai_assess', array(
    'submissionid' => $submission->id,
    'userid' => $submission->userid
));

if (!$assessment) {
    // No AI assessment yet
    $assessment = (object) array(
        'status' => 'pending',
        'score' => null,
        'feedback' => get_string('no_assessment_yet', 'local_essay_ai_grader'),
        'criteria_scores' => null,
        'essaytext' => null,
    );
    $has_assessment = false;
} else {
    $has_assessment = true;
}

// Set up page
$PAGE->set_url('/local/essay_ai_grader/view_feedback.php', array(
    'assignmentid' => $assignmentid,
    'submissionid' => $submission->id
));
$PAGE->set_title(get_string('title_view_feedback', 'local_essay_ai_grader'));
$PAGE->set_heading(get_string('title_view_feedback', 'local_essay_ai_grader'));
$PAGE->set_context($context);
$PAGE->requires->css('/local/essay_ai_grader/styles/feedback-display.css');

// Start output
echo $OUTPUT->header();

// Breadcrumb
$breadcrumb = array(
    array('name' => $course->shortname, 'url' => new moodle_url('/course/view.php', array('id' => $course->id))),
    array('name' => $assignment->name, 'url' => new moodle_url('/mod/assign/view.php', array('id' => $cm->id))),
    array('name' => get_string('title_view_feedback', 'local_essay_ai_grader'), 'url' => null)
);

echo '<div class="local-essay-ai-breadcrumb">';
foreach ($breadcrumb as $item) {
    if ($item['url']) {
        echo '<a href="' . $item['url'] . '">' . $item['name'] . '</a> > ';
    } else {
        echo $item['name'];
    }
}
echo '</div>';

// Main content
?>

<div class="local-essay-ai-feedback-container">
    <div class="row">
        <div class="col-md-8">
            <?php if ($has_assessment) : ?>
                
                <!-- Score Card -->
                <div class="card mb-3 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <?php echo get_string('llm_score', 'local_essay_ai_grader'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <div class="score-display text-center">
                                    <span class="score-value">
                                        <?php echo isset($assessment->score) ? round($assessment->score, 1) : 'N/A'; ?>
                                    </span>
                                    <div class="score-label">/ <?php echo isset($assignment->grade) ? $assignment->grade : '100'; ?></div>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="score-info">
                                    <p><strong><?php echo get_string('score_label', 'local_essay_ai_grader'); ?></strong></p>
                                    <p><?php echo get_string('score_explanation', 'local_essay_ai_grader'); ?></p>
                                    <?php if ($assessment->status === 'completed') : ?>
                                        <span class="badge badge-success">
                                            <?php echo get_string('status_completed', 'local_essay_ai_grader'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback Card -->
                <div class="card mb-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <?php echo get_string('llm_feedback', 'local_essay_ai_grader'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="feedback-content">
                            <?php 
                            if (!empty($assessment->feedback)) {
                                // Format feedback with line breaks
                                echo nl2br(htmlspecialchars($assessment->feedback));
                            } else {
                                echo '<em>' . get_string('no_feedback', 'local_essay_ai_grader') . '</em>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Criteria Scores (if applicable) -->
                <?php if (!empty($assessment->criteria_scores)) : ?>
                    <?php 
                    $criteria = json_decode($assessment->criteria_scores, true);
                    if (is_array($criteria) && !empty($criteria)) :
                        // Load CSS
                        $PAGE->requires->css('/local/essay_ai_grader/styles/criteria-display.css');
                        
                        // Use the new display helper
                        $display_helper = new \local_essay_ai_grader\criteria_display_helper();
                        // Convert point scores back to 1-5 scale for display
                        $display_criteria = $display_helper->convert_points_to_scale($criteria, $criteria_weights, $max_score);
                    ?>
                        <div class="card mb-3">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="card-title mb-0">
                                    <?php echo get_string('criteria_scores', 'local_essay_ai_grader'); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php echo $display_helper->render_criteria_table_student($display_criteria, true); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Improvement Suggestions -->
                <?php 
                $raw_api_response = !empty($assessment->gemini_response_raw) ? $assessment->gemini_response_raw : $assessment->gemini_response;
                if (!empty($raw_api_response)) : ?>
                    <?php 
                    $raw_response = json_decode($raw_api_response, true);
                    $suggestions = [];
                    if (is_array($raw_response) && !empty($raw_response['improvement_suggestions'])) {
                        if (is_array($raw_response['improvement_suggestions'])) {
                            $suggestions = $raw_response['improvement_suggestions'];
                        } else {
                            $suggestions = array((string)$raw_response['improvement_suggestions']);
                        }
                    }
                    if (!empty($suggestions)) :
                    ?>
                        <div class="card mb-3 border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <?php echo get_string('improvement_suggestions', 'local_essay_ai_grader'); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">
                                    <?php echo get_string('improvement_suggestions_help', 'local_essay_ai_grader'); ?>
                                </p>
                                <ol>
                                    <?php foreach ($suggestions as $suggestion) : ?>
                                        <li class="mb-2">
                                            <?php echo nl2br(htmlspecialchars($suggestion)); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php else : ?>
                <!-- No Assessment Yet -->
                <div class="alert alert-info" role="alert">
                    <h4 class="alert-heading">
                        <?php echo get_string('assessment_pending', 'local_essay_ai_grader'); ?>
                    </h4>
                    <p><?php echo get_string('assessment_pending_message', 'local_essay_ai_grader'); ?></p>
                    <hr>
                    <p class="mb-0">
                        <?php echo get_string('check_back_later', 'local_essay_ai_grader'); ?>
                    </p>
                </div>
            <?php endif; ?>

        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Teacher Review Section (for graders only) -->
            <?php 
            // Check if current user is a teacher/grader
            if (has_capability('mod/assign:grade', $context)) {
                // Safe parameter passing
                $review_url = new moodle_url('/local/essay_ai_grader/review_assessments.php', array('aid' => $assignment->id));
            ?>
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="card-title mb-0">
                            <?php echo get_string('teacher_review', 'local_essay_ai_grader'); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="small mb-3">
                            <?php echo get_string('teacher_review_desc', 'local_essay_ai_grader'); ?>
                        </p>
                        <a href="<?php echo $review_url; ?>" 
                           class="btn btn-warning btn-sm btn-block">
                            <?php echo get_string('review_assessment', 'local_essay_ai_grader'); ?>
                        </a>
                    </div>
                </div>
            <?php 
            } // end if has_capability
            ?>


            <!-- Assignment Info -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <?php echo get_string('assignment_info', 'local_essay_ai_grader'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <p>
                        <strong><?php echo get_string('assignment', 'local_essay_ai_grader'); ?>:</strong><br>
                        <?php echo htmlspecialchars($assignment->name); ?>
                    </p>
                    <p>
                        <strong><?php echo get_string('submitted', 'local_essay_ai_grader'); ?>:</strong><br>
                        <?php echo userdate($submission->timemodified, '%d %B %Y %H:%M'); ?>
                    </p>
                </div>
            </div>

            <!-- AI Assessment Info -->
            <?php if ($has_assessment && !empty($assessment->timecreated)) : ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <?php echo get_string('assessment_info', 'local_essay_ai_grader'); ?>  
                        </h6>
                    </div>
                    <div class="card-body">
                        <p>
                            <strong><?php echo get_string('assessed', 'local_essay_ai_grader'); ?>:</strong><br>
                            <?php echo userdate($assessment->timecreated, '%d %B %Y %H:%M'); ?>
                        </p>
                        <p>
                            <strong><?php echo get_string('status', 'local_essay_ai_grader'); ?>:</strong><br>
                            <?php echo htmlspecialchars(ucfirst($assessment->status)); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Help Box -->
            <div class="card bg-light">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <?php echo get_string('about_ai_feedback', 'local_essay_ai_grader'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small">
                        <?php echo get_string('ai_feedback_explanation', 'local_essay_ai_grader'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.local-essay-ai-breadcrumb {
    margin-bottom: 20px;
    padding: 10px 0;
    font-size: 0.9em;
}

.local-essay-ai-feedback-container {
    padding: 20px 0;
}

.score-display {
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.score-value {
    font-size: 3em;
    font-weight: bold;
    color: #007bff;
}

.score-label {
    font-size: 1.2em;
    color: #666;
    margin-top: 10px;
}

.feedback-content {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    line-height: 1.6;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,0.125);
}
</style>

<?php

// Footer
echo $OUTPUT->footer();
