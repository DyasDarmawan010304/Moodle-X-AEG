<?php
/**
 * CLI script to inspect assignment 13 answers that are short/high-scoring or missing answer-key terms.
 */

define('CLI_SCRIPT', true);

// Handle both Unix and Windows path separators.
$dir = str_replace('\\', '/', __DIR__);
$configpath = preg_replace('#/local/essay_ai_grader/cli$#', '/config.php', $dir);
if (!file_exists($configpath)) {
    $configpath = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'config.php';
}
if (!file_exists($configpath)) {
    $configpath = getcwd() . DIRECTORY_SEPARATOR . 'config.php';
}
if (!file_exists($configpath)) {
    fwrite(STDERR, "ERROR: Cannot find config.php at: $configpath\n");
    exit(1);
}

require($configpath);

global $DB;
$assignmentid = 13;
$config = $DB->get_record('local_essay_ai_config', ['assignid' => $assignmentid]);
$answerkey = $config->answerkey ?? $config->expected_answer ?? '';

$sql = 'SELECT a.id, a.submissionid, a.userid, a.status, a.score, a.criteria_scores, s.timemodified AS submission_time, t.onlinetext
        FROM {local_essay_ai_assess} a
        LEFT JOIN {assign_submission} s ON s.id = a.submissionid
        LEFT JOIN {assignsubmission_onlinetext} t ON t.submission = s.id
        WHERE a.assignmentid = ?
        ORDER BY COALESCE(s.timemodified, a.created) ASC, a.id ASC';

$rows = $DB->get_records_sql($sql, [$assignmentid]);
if (empty($rows)) {
    echo "No records found for assignment {$assignmentid}.\n";
    exit(0);
}

echo "id,submissionid,userid,status,score,length,words,has_answer_key,short_answer,suspicious,criteria_scores,excerpt\n";
foreach ($rows as $row) {
    $plain = trim(strip_tags($row->onlinetext ?? ''));
    $length = mb_strlen($plain, 'UTF-8');
    $words = count(preg_split('/\s+/u', trim($plain), -1, PREG_SPLIT_NO_EMPTY));
    $haskey = essay_contains_answer_key_terms($plain, $answerkey) ? 'yes' : 'no';
    $short = ($length < 120 || $words < 20) ? 'yes' : 'no';
    $score = is_numeric($row->score) ? (float)$row->score : 0.0;
    $suspicious = ($row->status === 'completed' && $score >= 20 && ($short === 'yes' || $haskey === 'no')) ? 'yes' : 'no';
    $excerpt = preg_replace('/\s+/', ' ', mb_substr($plain, 0, 120, 'UTF-8'));
    echo implode(",", [
        $row->id,
        $row->submissionid,
        $row->userid,
        $row->status,
        number_format($score, 2),
        $length,
        $words,
        $haskey,
        $short,
        $suspicious,
        json_encode(json_decode($row->criteria_scores, true)),
        '"' . str_replace('"', '""', $excerpt) . '"'
    ]) . "\n";
}

function essay_contains_answer_key_terms($essaytext, $answerkey) {
    $answerkey = trim((string)$answerkey);
    if ($answerkey === '') {
        return false;
    }
    $essay = strtolower(trim((string)$essaytext));
    $keytext = strtolower(trim($answerkey));
    $keytext = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $keytext);
    $words = preg_split('/\s+/u', $keytext, -1, PREG_SPLIT_NO_EMPTY);
    $keywords = [];
    foreach ($words as $word) {
        if (mb_strlen($word, 'UTF-8') >= 4) {
            $keywords[$word] = true;
        }
    }
    foreach (array_keys($keywords) as $keyword) {
        if (strpos($essay, $keyword) !== false) {
            return true;
        }
    }
    return false;
}
