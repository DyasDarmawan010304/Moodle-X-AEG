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

namespace local_essay_ai_grader;

defined("MOODLE_INTERNAL") || die();

/**
 * Criteria Display Helper
 * Provides rendering methods for criteria scores display in student and admin views.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class criteria_display_helper {

    /**
     * Render criteria table for student view (simplified 2-column with progress bars).
     *
     * @param array $criteria_scores Array of criterion_id => score
     * @param bool $show_weights Whether to show weights
     * @return string HTML output
     */
    public function render_criteria_table_student($criteria_scores, $show_weights = false) {
        global $OUTPUT;

        if (empty($criteria_scores) || !is_array($criteria_scores)) {
            return "<p>" . get_string("no_criteria_scores", "local_essay_ai_grader") . "</p>";
        }

        $all_criteria = default_criteria::get_all_criteria();
        $html = "";

        // Get max possible score for percentage calculation (assuming 5-point scale)
        $max_possible = 5.0;

        foreach ($criteria_scores as $criterion_id => $score) {
            if (!isset($all_criteria[$criterion_id])) {
                continue;
            }

            $criterion = $all_criteria[$criterion_id];
            $percentage = ($score / $max_possible) * 100;
            $level = $this->get_performance_level($score);

            $html .= "<div class=\"criteria-item mb-3\">";
            $html .= "<div class=\"row align-items-center\">";
            $html .= "<div class=\"col-md-6\">";
            $html .= "<strong>" . htmlspecialchars($criterion["name_id"]) . "</strong>";
            $html .= "<div class=\"criteria-description small text-muted\">" . htmlspecialchars($criterion["description_id"]) . "</div>";
            $html .= "</div>";
            $html .= "<div class=\"col-md-6\">";
            $html .= "<div class=\"score-display\">";
            $html .= "<div class=\"progress mb-1\" style=\"height: 20px;\">";
            $html .= "<div class=\"progress-bar " . $level["class"] . "\" role=\"progressbar\" style=\"width: " . $percentage . "%\" ";
            $html .= "aria-valuenow=\"" . $score . "\" aria-valuemin=\"0\" aria-valuemax=\"" . $max_possible . "\">";
            $html .= number_format($score, 1) . "/" . $max_possible;
            $html .= "</div>";
            $html .= "</div>";
            $html .= "<div class=\"score-label " . $level["class"] . "\">" . $level["label"] . "</div>";
            $html .= "</div>";
            $html .= "</div>";
            $html .= "</div>";
            $html .= "</div>";
        }

        return $html;
    }

    /**
     * Convert point scores back to 1-5 scale for display.
     *
     * @param array $point_scores Array of criterion_id => points
     * @param array $criteria_weights Array of criterion_id => weight
     * @param float $max_score Assignment max score
     * @return array Array of criterion_id => scale_score (1-5)
     */
    public function convert_points_to_scale($point_scores, $criteria_weights, $max_score) {
        $scale_scores = array();
        $total_weight = array_sum($criteria_weights);

        foreach ($point_scores as $criterion_id => $points) {
            $weight = isset($criteria_weights[$criterion_id]) ? $criteria_weights[$criterion_id] : 0;
            if ($weight > 0 && $total_weight > 0) {
                $max_points_for_criterion = ($weight / $total_weight) * $max_score;
                if ($max_points_for_criterion > 0) {
                    $scale_score = ($points / $max_points_for_criterion) * 5.0;
                    $scale_scores[$criterion_id] = round($scale_score, 1);
                } else {
                    $scale_scores[$criterion_id] = 0;
                }
            } else {
                $scale_scores[$criterion_id] = 0;
            }
        }

        return $scale_scores;
    }

    /**
     * Get performance level based on score.
     *
     * @param float $score Score value
     * @return array Array with "label" and "class" keys
     */
    private function get_performance_level($score) {
        $max_score = 5.0;
        $percentage = ($score / $max_score) * 100;

        if ($percentage >= 80) {
            return array("label" => get_string("excellent", "local_essay_ai_grader"), "class" => "text-success");
        } elseif ($percentage >= 60) {
            return array("label" => get_string("good", "local_essay_ai_grader"), "class" => "text-info");
        } elseif ($percentage >= 40) {
            return array("label" => get_string("fair", "local_essay_ai_grader"), "class" => "text-warning");
        } else {
            return array("label" => get_string("poor", "local_essay_ai_grader"), "class" => "text-danger");
        }
    }
}
?>

    /**
     * Convert point scores back to 1-5 scale for display.
     *
     * @param array $point_scores Array of criterion_id => points
     * @param array $criteria_weights Array of criterion_id => weight
     * @param float $max_score Assignment max score
     * @return array Array of criterion_id => scale_score (1-5)
     */
    public function convert_points_to_scale($point_scores, $criteria_weights, $max_score) {
        $scale_scores = array();
        $total_weight = array_sum($criteria_weights);

        foreach ($point_scores as $criterion_id => $points) {
            $weight = isset($criteria_weights[$criterion_id]) ? $criteria_weights[$criterion_id] : 0;
            if ($weight > 0 && $total_weight > 0) {
                $max_points_for_criterion = ($weight / $total_weight) * $max_score;
                if ($max_points_for_criterion > 0) {
                    $scale_score = ($points / $max_points_for_criterion) * 5.0;
                    $scale_scores[$criterion_id] = round($scale_score, 1);
                } else {
                    $scale_scores[$criterion_id] = 0;
                }
            } else {
                $scale_scores[$criterion_id] = 0;
            }
        }

        return $scale_scores;
    }


    /**
     * Get performance level based on score.
     *
     * @param float $score Score value
     * @return array Array with "label" and "class" keys
     */
    private function get_performance_level($score) {
        $max_score = 5.0;
        $percentage = ($score / $max_score) * 100;

        if ($percentage >= 80) {
            return array("label" => get_string("excellent", "local_essay_ai_grader"), "class" => "text-success");
        } elseif ($percentage >= 60) {
            return array("label" => get_string("good", "local_essay_ai_grader"), "class" => "text-info");
        } elseif ($percentage >= 40) {
            return array("label" => get_string("fair", "local_essay_ai_grader"), "class" => "text-warning");
        } else {
            return array("label" => get_string("poor", "local_essay_ai_grader"), "class" => "text-danger");
        }
    }

