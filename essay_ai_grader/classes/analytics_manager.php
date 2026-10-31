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

defined('MOODLE_INTERNAL') || die();

/**
 * Analytics and metrics manager for Essay AI Grader.
 * Provides comprehensive statistics and insights for dashboards.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_manager {
    
    /**
     * Get overall system statistics.
     *
     * @return array
     */
    public static function get_system_stats() {
        global $DB;
        
        $stats = array();
        
        // Total assessments
        $stats['total_assessments'] = $DB->count_records('local_essay_ai_assess');
        
        // Status breakdown
        $stats['status'] = array(
            'pending' => $DB->count_records('local_essay_ai_assess', array('status' => 'pending')),
            'processing' => $DB->count_records('local_essay_ai_assess', array('status' => 'processing')),
            'completed' => $DB->count_records('local_essay_ai_assess', array('status' => 'completed')),
            'error' => $DB->count_records('local_essay_ai_assess', array('status' => 'error')),
        );
        
        // Teacher acceptance rate
        $sql = "SELECT 
                COUNT(CASE WHEN teacher_accepted = 1 THEN 1 END) as accepted,
                COUNT(CASE WHEN teacher_accepted = 0 THEN 1 END) as rejected,
                COUNT(CASE WHEN teacher_accepted IS NULL THEN 1 END) as pending_review
                FROM {local_essay_ai_assess}
                WHERE status = 'completed'";
        $acceptance = $DB->get_record_sql($sql);
        
        $stats['acceptance'] = array(
            'accepted' => (int)($acceptance->accepted ?? 0),
            'rejected' => (int)($acceptance->rejected ?? 0),
            'pending_review' => (int)($acceptance->pending_review ?? 0),
        );
        
        $total_completed = $stats['status']['completed'];
        if ($total_completed > 0) {
            $stats['acceptance_rate'] = round(
                ($stats['acceptance']['accepted'] / $total_completed) * 100, 2
            );
        } else {
            $stats['acceptance_rate'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Get assessment statistics for a specific assignment.
     *
     * @param int $assignmentid
     * @return array
     */
    public static function get_assignment_stats($assignmentid) {
        global $DB;
        
        $assessments = $DB->get_records('local_essay_ai_assess', 
            array('assignmentid' => $assignmentid)
        );
        
        $stats = array(
            'assignmentid' => $assignmentid,
            'total' => count($assessments),
            'status_breakdown' => array(
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'error' => 0,
            ),
            'score_stats' => array(
                'average' => 0,
                'median' => 0,
                'min' => 0,
                'max' => 0,
            ),
            'acceptance_stats' => array(
                'accepted' => 0,
                'rejected' => 0,
                'pending' => 0,
            ),
        );
        
        $scores = array();
        
        foreach ($assessments as $assessment) {
            $stats['status_breakdown'][$assessment->status]++;
            
            if ($assessment->status === 'completed' && $assessment->score !== null) {
                $scores[] = $assessment->score;
            }
            
            if ($assessment->teacher_accepted === 1) {
                $stats['acceptance_stats']['accepted']++;
            } else if ($assessment->teacher_accepted === 0) {
                $stats['acceptance_stats']['rejected']++;
            } else if ($assessment->status === 'completed') {
                $stats['acceptance_stats']['pending']++;
            }
        }
        
        if (!empty($scores)) {
            $stats['score_stats']['average'] = round(array_sum($scores) / count($scores), 2);
            $stats['score_stats']['median'] = self::calculate_median($scores);
            $stats['score_stats']['min'] = round(min($scores), 2);
            $stats['score_stats']['max'] = round(max($scores), 2);
        }
        
        return $stats;
    }
    
    /**
     * Get assessment score distribution (histogram data).
     *
     * @param int|null $assignmentid Filter by assignment or null for all
     * @param int $bins Number of histogram bins (default: 10)
     * @return array Distribution data for charting
     */
    public static function get_score_distribution($assignmentid = null, $bins = 10) {
        global $DB;
        
        $where = "status = 'completed' AND score IS NOT NULL";
        $params = array();
        
        if ($assignmentid !== null) {
            $where .= ' AND assignmentid = ?';
            $params[] = (int)$assignmentid;
        }
        
        $scores = $DB->get_records_select('local_essay_ai_assess', $where, $params, '', 'score');
        
        if (empty($scores)) {
            return array(
                'labels' => array(),
                'data' => array(),
            );
        }
        
        $score_values = array_map(function($s) { return $s->score; }, $scores);
        $min_score = min($score_values);
        $max_score = max($score_values);
        
        // Create bins
        $bin_width = ($max_score - $min_score) / $bins;
        if ($bin_width <= 0) {
            $bin_width = 1;
        }
        
        $distribution = array_fill(0, $bins, 0);
        $labels = array();
        
        for ($i = 0; $i < $bins; $i++) {
            $bin_start = $min_score + ($i * $bin_width);
            $bin_end = $bin_start + $bin_width;
            $labels[] = round($bin_start, 1) . '-' . round($bin_end, 1);
            
            foreach ($score_values as $score) {
                if ($score >= $bin_start && $score <= $bin_end) {
                    $distribution[$i]++;
                }
            }
        }
        
        return array(
            'labels' => $labels,
            'data' => array_values($distribution),
            'min_score' => $min_score,
            'max_score' => $max_score,
            'total_assessments' => count($scores),
        );
    }
    
    /**
     * Get timeline of assessments completed.
     *
     * @param int $days Number of days to show (default: 30)
     * @return array Timeline data for charting
     */
    public static function get_timeline($days = 30) {
        global $DB;
        
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        $sql = "SELECT 
                DATE(FROM_UNIXTIME(timecreated)) as date,
                COUNT(*) as count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'error' THEN 1 END) as errors
                FROM {local_essay_ai_assess}
                WHERE timecreated >= ?
                GROUP BY DATE(FROM_UNIXTIME(timecreated))
                ORDER BY date ASC";
        
        $records = $DB->get_records_sql($sql, array($cutoff));
        
        $timeline = array(
            'dates' => array(),
            'total' => array(),
            'completed' => array(),
            'errors' => array(),
        );
        
        foreach ($records as $record) {
            $timeline['dates'][] = $record->date;
            $timeline['total'][] = (int)$record->count;
            $timeline['completed'][] = (int)$record->completed;
            $timeline['errors'][] = (int)$record->errors;
        }
        
        return $timeline;
    }
    
    /**
     * Get top performing and problematic assignments.
     *
     * @return array With 'top_assignments' and 'problematic_assignments'
     */
    public static function get_assignment_insights() {
        global $DB;
        
        // Top performing assignments (high acceptance rate)
        $top_sql = "SELECT 
                        assignmentid,
                        COUNT(*) as total,
                        COUNT(CASE WHEN teacher_accepted = 1 THEN 1 END) as accepted,
                        ROUND(COUNT(CASE WHEN teacher_accepted = 1 THEN 1 END) / 
                              COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100, 1) as acceptance_rate,
                        AVG(CASE WHEN status = 'completed' THEN score ELSE NULL END) as avg_score
                    FROM {local_essay_ai_assess}
                    WHERE status = 'completed'
                    GROUP BY assignmentid
                    HAVING COUNT(*) >= 10
                    ORDER BY acceptance_rate DESC
                    LIMIT 5";
        
        $top_assignments = $DB->get_records_sql($top_sql);
        
        // Problematic assignments (high error rate)
        $problem_sql = "SELECT 
                            assignmentid,
                            COUNT(*) as total,
                            COUNT(CASE WHEN status = 'error' THEN 1 END) as errors,
                            ROUND(COUNT(CASE WHEN status = 'error' THEN 1 END) / 
                                  COUNT(*) * 100, 1) as error_rate
                        FROM {local_essay_ai_assess}
                        GROUP BY assignmentid
                        HAVING COUNT(CASE WHEN status = 'error' THEN 1 END) > 0
                        ORDER BY error_rate DESC
                        LIMIT 5";
        
        $problematic = $DB->get_records_sql($problem_sql);
        
        return array(
            'top_assignments' => array_values($top_assignments),
            'problematic_assignments' => array_values($problematic),
        );
    }
    
    /**
     * Get model performance comparison.
     *
     * @return array Performance stats by model
     */
    public static function get_model_performance() {
        global $DB;
        
        // Get cost stats by model if cost tracking enabled
        try {
            $model_stats = cost_tracker::get_cost_by_model('month');
        } catch (\Throwable $e) {
            $model_stats = array();
        }
        
        return array(
            'models' => $model_stats,
            'period' => 'month',
        );
    }
    
    /**
     * Calculate median of array.
     *
     * @param array $values
     * @return float
     */
    private static function calculate_median($values) {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $count = count($values);
        $middle = intval($count / 2);
        
        if ($count % 2 == 0) {
            return round(($values[$middle - 1] + $values[$middle]) / 2, 2);
        } else {
            return round($values[$middle], 2);
        }
    }
    
    /**
     * Generate insights and recommendations based on analytics.
     *
     * @return array Insights with actionable recommendations
     */
    public static function generate_insights() {
        $stats = self::get_system_stats();
        $insights = array();
        
        // Check for completion backlog
        if ($stats['status']['pending'] > 20) {
            $insights[] = array(
                'type' => 'warning',
                'title' => 'Large Processing Queue',
                'message' => 'There are ' . $stats['status']['pending'] . 
                           ' pending assessments. Queue processing may be slow.',
                'action' => 'Increase scheduled task frequency or check for errors',
            );
        }
        
        // Check error rate
        $error_rate = $stats['total_assessments'] > 0 ? 
            ($stats['status']['error'] / $stats['total_assessments'] * 100) : 0;
        
        if ($error_rate > 10) {
            $insights[] = array(
                'type' => 'error',
                'title' => 'High Error Rate',
                'message' => 'Error rate is ' . round($error_rate, 1) . '%. ' .
                           'This may indicate configuration or API issues.',
                'action' => 'Review error logs and check API configuration',
            );
        }
        
        // Check acceptance rate
        if ($stats['acceptance_rate'] < 50 && $stats['status']['completed'] > 10) {
            $insights[] = array(
                'type' => 'info',
                'title' => 'Low Acceptance Rate',
                'message' => 'Only ' . $stats['acceptance_rate'] . 
                           '% of grades are being accepted by teachers.',
                'action' => 'Consider adjusting evaluation criteria or reviewing feedback quality',
            );
        }
        
        return $insights;
    }
}
