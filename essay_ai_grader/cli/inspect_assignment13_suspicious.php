<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
global $DB;

$rows = $DB->get_records_sql(
    'SELECT a.id,a.submissionid,a.userid,a.status,a.score,a.criteria_scores,a.gemini_response,a.gemini_response_raw,a.timemodified,s.timemodified AS submodified, t.onlinetext
     FROM {local_essay_ai_assess} a
     LEFT JOIN {assign_submission} s ON s.id = a.submissionid
     LEFT JOIN {assignsubmission_onlinetext} t ON t.submission = s.id
     WHERE a.assignmentid = ?
     ORDER BY s.timemodified ASC, a.id ASC',
    [13]
);

echo "TOTAL=" . count($rows) . "\n";
$count = 0;
foreach ($rows as $r) {
    $plain = trim(strip_tags($r->onlinetext));
    $len = strlen($plain);
    $cs = json_decode($r->criteria_scores, true);
    $criteria_json = json_encode($cs);
    if ($r->status === 'completed' && $r->score >= 24 && $len <= 120) {
        echo "ID={$r->id} sub={$r->submissionid} user={$r->userid} score={$r->score} len={$len} crits={$criteria_json}\n";
        echo "text=" . preg_replace('/\s+/', ' ', substr($plain, 0, 200)) . "\n----\n";
        $count++;
    }
}
echo "SUSPICIOUS_COUNT={$count}\n";
