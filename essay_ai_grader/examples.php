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
 * Example usage of Essay AI Grader plugin API.
 * This file demonstrates common use cases and integration patterns.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Example 1: Enable AI grading for a specific assignment
// =====================================================
function enable_ai_grading_for_assignment($assignmentid, $maxscore = 100, $use_all_criteria = true) {
    global $DB;
    
    // Check if config already exists
    $existingconfig = $DB->get_record('local_essay_ai_config', array('assignid' => $assignmentid));
    
    if ($existingconfig) {
        echo "AI grading already configured for this assignment.\n";
        return false;
    }
    
    // Create configuration
    $criteria = array();
    if ($use_all_criteria) {
        $criteria = array(
            'grammar' => 1,
            'content' => 1,
            'organization' => 1,
        );
    }
    
    $config = new stdClass();
    $config->assignid = $assignmentid;
    $config->enabled = 1;
    $config->maxscore = $maxscore;
    $config->criteria = json_encode($criteria);
    $config->prompttemplate = '';
    $config->timecreated = time();
    $config->timemodified = time();
    
    $id = $DB->insert_record('local_essay_ai_config', $config);
    echo "AI grading enabled for assignment $assignmentid (config ID: $id)\n";
    
    return true;
}

// Example 2: Process all pending assessments for an assignment
// ===========================================================
function process_assessments_for_assignment($assignmentid) {
    global $DB;
    
    use local_essay_ai_grader\assessment_manager;
    
    $pending = $DB->get_records('local_essay_ai_assess', array(
        'assignmentid' => $assignmentid,
        'status' => 'pending',
    ), 'created ASC');
    
    if (empty($pending)) {
        echo "No pending assessments for assignment $assignmentid\n";
        return 0;
    }
    
    $count = 0;
    foreach ($pending as $assessment) {
        try {
            if (assessment_manager::process_assessment($assessment->id)) {
                $count++;
                echo "✓ Processed assessment {$assessment->id}\n";
            } else {
                echo "✗ Failed to process assessment {$assessment->id}\n";
            }
        } catch (Exception $e) {
            echo "✗ Error processing assessment {$assessment->id}: {$e->getMessage()}\n";
        }
    }
    
    echo "Processed $count assessments\n";
    return $count;
}

// Example 3: Get AI assessment for a specific student submission
// ==============================================================
function get_student_assessment($assignmentid, $userid) {
    global $DB;
    
    $assessment = $DB->get_record('local_essay_ai_assess', array(
        'assignmentid' => $assignmentid,
        'userid' => $userid,
    ));
    
    if (!$assessment) {
        return null;
    }
    
    return array(
        'score' => $assessment->score,
        'feedback' => $assessment->feedback,
        'status' => $assessment->status,
        'criteria_scores' => json_decode($assessment->criteria_scores, true),
        'teacher_accepted' => $assessment->teacher_accepted,
        'final_grade' => $assessment->teacher_finalgrade,
    );
}

// Example 4: Manually assess an essay without using submission
// ===========================================================
function direct_assess_essay($essaytext, $maxscore = 100) {
    use local_essay_ai_grader\gemini\gemini_client;
    
    try {
        $client = new gemini_client();
        
        $criteria = array(
            'grammar' => true,
            'content' => true,
            'organization' => true,
        );
        
        $result = $client->assess_essay($essaytext, $criteria, $maxscore);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'score' => $result['score'],
                'feedback' => $result['summary_feedback'],
                'criteria' => $result['criteria_scores'],
            );
        } else {
            return array(
                'success' => false,
                'error' => $result['error'],
            );
        }
    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => $e->getMessage(),
        );
    }
}

// Example 5: Accept AI grade and save to assignment
// =================================================
function accept_ai_grade($assessmentid) {
    global $DB;
    
    use local_essay_ai_grader\assessment_manager;
    
    $assessment = $DB->get_record('local_essay_ai_assess', array('id' => $assessmentid));
    
    if (!$assessment) {
        echo "Assessment $assessmentid not found\n";
        return false;
    }
    
    // Mark as accepted
    assessment_manager::review_assessment($assessmentid, true);
    
    // Save grade to assignment
    $success = assessment_manager::save_grade_to_assignment(
        $assessment->assignmentid,
        $assessment->userid,
        $assessment->score
    );
    
    if ($success) {
        echo "Grade accepted for user {$assessment->userid}\n";
    } else {
        echo "Failed to save grade\n";
    }
    
    return $success;
}

// Example 6: Override AI grade with teacher's grade
// =================================================
function override_with_teacher_grade($assessmentid, $teachergrade, $feedback = '') {
    global $DB;
    
    use local_essay_ai_grader\assessment_manager;
    
    $assessment = $DB->get_record('local_essay_ai_assess', array('id' => $assessmentid));
    
    if (!$assessment) {
        echo "Assessment $assessmentid not found\n";
        return false;
    }
    
    // Mark as reviewed (rejected AI, using teacher grade)
    assessment_manager::review_assessment($assessmentid, false, $teachergrade, $feedback);
    
    // Save teacher's grade
    $success = assessment_manager::save_grade_to_assignment(
        $assessment->assignmentid,
        $assessment->userid,
        $teachergrade
    );
    
    if ($success) {
        echo "Teacher grade saved for user {$assessment->userid}: $teachergrade\n";
    } else {
        echo "Failed to save teacher grade\n";
    }
    
    return $success;
}

// Example 7: Configure custom evaluation for an assignment
// ========================================================
function configure_custom_evaluation($assignmentid, $custom_prompt) {
    global $DB;
    
    $config = $DB->get_record('local_essay_ai_config', array('assignid' => $assignmentid));
    
    if (!$config) {
        echo "Configuration for assignment $assignmentid not found\n";
        return false;
    }
    
    $config->prompttemplate = $custom_prompt;
    $config->timemodified = time();
    
    $DB->update_record('local_essay_ai_config', $config);
    echo "Custom prompt configured for assignment $assignmentid\n";
    
    return true;
}

// Example 8: Get assessment statistics for an assignment
// ======================================================
function get_assessment_statistics($assignmentid) {
    global $DB;
    
    // Count by status
    $pending = $DB->count_records('local_essay_ai_assess', array(
        'assignmentid' => $assignmentid,
        'status' => 'pending',
    ));
    
    $processing = $DB->count_records('local_essay_ai_assess', array(
        'assignmentid' => $assignmentid,
        'status' => 'processing',
    ));
    
    $completed = $DB->count_records('local_essay_ai_assess', array(
        'assignmentid' => $assignmentid,
        'status' => 'completed',
    ));
    
    $error = $DB->count_records('local_essay_ai_assess', array(
        'assignmentid' => $assignmentid,
        'status' => 'error',
    ));
    
    // Get some grade statistics
    $avggrade = $DB->get_field_sql(
        'SELECT AVG(score) FROM {local_essay_ai_assess} 
         WHERE assignmentid = ? AND status = ?',
        array($assignmentid, 'completed')
    );
    
    $maxgrade = $DB->get_field_sql(
        'SELECT MAX(score) FROM {local_essay_ai_assess} 
         WHERE assignmentid = ? AND status = ?',
        array($assignmentid, 'completed')
    );
    
    $mingrade = $DB->get_field_sql(
        'SELECT MIN(score) FROM {local_essay_ai_assess} 
         WHERE assignmentid = ? AND status = ?',
        array($assignmentid, 'completed')
    );
    
    return array(
        'pending' => $pending,
        'processing' => $processing,
        'completed' => $completed,
        'error' => $error,
        'total' => $pending + $processing + $completed + $error,
        'average_score' => round($avggrade, 2),
        'max_score' => $maxgrade,
        'min_score' => $mingrade,
    );
}

// Example 9: Batch process multiple assignments
// ============================================
function batch_process_all_assignments() {
    global $DB;
    
    use local_essay_ai_grader\assessment_manager;
    
    // Get all assignments with AI grading enabled
    $configs = $DB->get_records('local_essay_ai_config', array('enabled' => 1));
    
    $totalprocessed = 0;
    
    foreach ($configs as $config) {
        $pending = $DB->get_records('local_essay_ai_assess', array(
            'assignmentid' => $config->assignid,
            'status' => 'pending',
        ), 'created ASC', '*', 0, 100);
        
        foreach ($pending as $assessment) {
            try {
                if (assessment_manager::process_assessment($assessment->id)) {
                    $totalprocessed++;
                }
            } catch (Exception $e) {
                echo "Error processing assessment {$assessment->id}: {$e->getMessage()}\n";
            }
        }
    }
    
    echo "Batch processing complete: $totalprocessed assessments processed\n";
    return $totalprocessed;
}

// Example 10: Export assessment results
// ====================================
function export_assessment_results($assignmentid) {
    global $DB;
    
    $assessments = $DB->get_records('local_essay_ai_assess', array(
        'assignmentid' => $assignmentid,
        'status' => 'completed',
    ), 'timecreated DESC');
    
    $data = array();
    
    foreach ($assessments as $assessment) {
        $student = $DB->get_record('user', array('id' => $assessment->userid));
        
        $criteria = json_decode($assessment->criteria_scores, true);
        
        $data[] = array(
            'student_id' => $assessment->userid,
            'student_name' => $student ? fullname($student) : 'Unknown',
            'ai_score' => $assessment->score,
            'teacher_grade' => $assessment->teacher_finalgrade,
            'status' => $assessment->status,
            'accepted' => $assessment->teacher_accepted,
            'grammar_score' => $criteria['grammar'] ?? 'N/A',
            'content_score' => $criteria['content'] ?? 'N/A',
            'organization_score' => $criteria['organization'] ?? 'N/A',
            'feedback_summary' => substr($assessment->feedback, 0, 100),
        );
    }
    
    return $data;
}

?>
