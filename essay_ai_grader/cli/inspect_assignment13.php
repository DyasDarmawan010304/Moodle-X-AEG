<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
global $DB;

$config = $DB->get_record('local_essay_ai_config', ['assignid' => 13]);
echo "CONFIG\n";
if ($config) {
    echo "id={$config->id} enabled={$config->enabled} maxscore={$config->maxscore}\n";
    echo "rubricguide=" . substr($config->rubricguide, 0, 800) . "\n";
    echo "answerkey=" . substr($config->answerkey, 0, 800) . "\n";
    echo "criteria_config=" . substr($config->criteria_config, 0, 1000) . "\n";
} else {
    echo "no config\n";
}
echo "---\n";

$records = $DB->get_records('local_essay_ai_assess', ['assignmentid' => 13, 'status' => 'completed']);
echo "ASSESSMENTS total=" . count($records) . "\n";
foreach ($records as $a) {
    $cs = json_decode($a->criteria_scores, true);
    $text = '';
    $sub = $DB->get_record('assign_submission', ['id' => $a->submissionid]);
    if ($sub) {
        $t = $DB->get_record('assignsubmission_onlinetext', ['submission' => $sub->id]);
        if ($t) {
            $text = $t->onlinetext;
        }
    }
    $plain = trim(strip_tags($text));
    $len = strlen($plain);
    if ($len <= 120 && $a->score >= 20) {
        $criteria = is_array($cs) ? json_encode($cs) : '[]';
        $textpreview = preg_replace('/\s+/', ' ', substr($plain, 0, 300));
        echo "ID={$a->id} sub={$a->submissionid} user={$a->userid} score={$a->score} len={$len} criteria={$criteria}\n";
        echo "text={$textpreview}\n";
        echo "----\n";
    }
}
