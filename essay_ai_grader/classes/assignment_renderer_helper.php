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
 * Custom renderer for injecting Essay AI Grader buttons into assignment view.
 * 
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_essay_ai_grader;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that injects AI grader button into assignment pages
 */
class assignment_renderer_helper {
    
    /**
     * Get the HTML for the AI Grader review button
     * 
     * @param int $assignmentid The assignment ID
     * @return string HTML for the button
     */
    public static function get_review_button($assignmentid) {
        global $DB, $OUTPUT, $USER;
        
        if (!$assignmentid) {
            return '';
        }
        
        // Get assignment
        $assignment = $DB->get_record('assign', array('id' => $assignmentid));
        if (!$assignment) {
            return '';
        }
        
        // Get course module
        $cm = get_coursemodule_from_instance('assign', $assignmentid);
        if (!$cm) {
            return '';
        }
        
        // Check permission
        if (!has_capability('mod/assign:grade', \context_module::instance($cm->id))) {
            return '';
        }
        
        // Count assessments
        $completedcount = $DB->count_records('local_essay_ai_assess', array(
            'assignmentid' => $assignmentid,
            'status' => 'completed'
        ));
        
        // Build button HTML
        $review_url = new \moodle_url('/local/essay_ai_grader/review_assessments.php', 
            array('aid' => $assignmentid));
        
        $badge = '';
        if ($completedcount > 0) {
            $badge = ' <span class="badge bg-primary ms-1">' . $completedcount . '</span>';
        }
        
        $html = html_writer::tag('a',
            html_writer::tag('i', '', array('class' => 'fa fa-lightbulb-o me-1')) . 
            'AI Assessments' . $badge,
            array(
                'href' => $review_url->out(),
                'class' => 'btn btn-warning',
                'title' => 'Review and validate AI assessments',
                'style' => 'margin-left: 10px;'
            )
        );
        
        return $html;
    }
}
