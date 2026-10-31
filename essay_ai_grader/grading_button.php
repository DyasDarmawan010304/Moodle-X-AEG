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
 * Assignment Grade View - Inject AI Grader button into assignment interface.
 * 
 * This file hooks into the assignment grading view to provide quick access
 * to AI grading configuration and assessment review.
 *
 * @package    local_essay_ai_grader
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Only for AJAX requests or in context of assignment module.
defined('MOODLE_INTERNAL') || die();

/**
 * Add AI Grader button to assignment grading interface.
 * 
 * This function is called by assignment module to inject additional buttons/links
 * into the grading interface.
 *
 * @param stdClass $assignment The assignment object.
 * @param context_module $context The context of the module.
 * @return string HTML for the AI grader button.
 */
function local_essay_ai_grader_get_grading_button($assignment, $context) {
    global $DB, $OUTPUT;
    
    // Check if AI grading is enabled for this assignment.
    $config = $DB->get_record('local_essay_ai_config', array('assignid' => $assignment->id));
    
    if (!$config || !$config->enabled) {
        return '';
    }
    
    // Check capabilities.
    if (!has_capability('mod/assign:grade', $context)) {
        return '';
    }
    
    // Get pending assessment count.
    $pendingcount = $DB->count_records('local_essay_ai_assess', array(
        'assignmentid' => $assignment->id,
        'status' => 'completed'
    ));
    
    $html = html_writer::start_div('essay-ai-grader-button', array('style' => 'margin: 10px 0;'));
    
    // Configure button.
    $configurl = new moodle_url('/local/essay_ai_grader/configure.php', array('cmid' => $assignment->cmid));
    $configbutton = html_writer::link(
        $configurl,
        html_writer::tag('i', '', array('class' => 'fa fa-cog')) . ' ' . get_string('configure', 'local_essay_ai_grader'),
        array('class' => 'btn btn-sm btn-primary')
    );
    
    $html .= $configbutton;
    
    // Review button if there are pending reviews.
    if ($pendingcount > 0) {
        $reviewurl = new moodle_url('/local/essay_ai_grader/review.php', array('assignmentid' => $assignment->id));
        $reviewbutton = html_writer::link(
            $reviewurl,
            html_writer::tag('i', '', array('class' => 'fa fa-list-check')) . ' ' .
            get_string('review', 'local_essay_ai_grader') . ' (' . $pendingcount . ')',
            array('class' => 'btn btn-sm btn-warning')
        );
        $html .= ' ' . $reviewbutton;
    }
    
    $html .= html_writer::end_div();
    
    return $html;
}

// If this is called as part of module rendering, return the button.
if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
    header('Content-Type: application/json');
    echo json_encode(array('button' => ''));
    exit;
}
