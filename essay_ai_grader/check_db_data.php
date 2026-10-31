<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

global $DB;

$assessments = $DB->get_records('local_essay_ai_assess', array('id' => 4));
if ($assessments) {
    $assessment = reset($assessments);
    echo 'Assessment ID: ' . $assessment->id . PHP_EOL;
    echo 'Assignment ID: ' . $assessment->assignmentid . PHP_EOL;
    echo 'Criteria Scores JSON: ' . $assessment->criteria_scores . PHP_EOL;
    $criteria = json_decode($assessment->criteria_scores, true);
    print_r($criteria);

    // Get assignment max score
    $config = $DB->get_record('local_essay_ai_config', array('assignmentid' => $assessment->assignmentid));
    if ($config) {
        echo 'Assignment max score: ' . $config->maxscore . PHP_EOL;
    } else {
        echo 'Config not found' . PHP_EOL;
    }
} else {
    echo 'Assessment not found' . PHP_EOL;
}