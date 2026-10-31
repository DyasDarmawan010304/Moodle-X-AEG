<?php
/**
 * CLI script to rerun only suspicious assignment 13 assessments.
 * Suspicious assessments are short/high-scoring completed answers or errors.
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
require_once($CFG->libdir . '/clilib.php');

use local_essay_ai_grader\assessment_manager;
global $DB;
$assignmentid = 13;

$sql = 'SELECT a.id, a.submissionid, a.userid, a.status, a.score, t.onlinetext, c.answerkey, c.expected_answer
        FROM {local_essay_ai_assess} a
        LEFT JOIN {assign_submission} s ON s.id = a.submissionid
        LEFT JOIN {assignsubmission_onlinetext} t ON t.submission = s.id
        LEFT JOIN {local_essay_ai_config} c ON c.assignid = a.assignmentid
        WHERE a.assignmentid = ?
        ORDER BY COALESCE(s.timemodified, a.created) ASC, a.id ASC';

$rows = $DB->get_records_sql($sql, [$assignmentid]);
if (empty($rows)) {
    cli_writeln("No assessments found for assignment {$assignmentid}.");
    exit(0);
}

$suspicious = [];
foreach ($rows as $row) {
    $plain = trim(strip_tags($row->onlinetext ?? ''));
    $length = mb_strlen($plain, 'UTF-8');
    $words = count(preg_split('/\s+/u', trim($plain), -1, PREG_SPLIT_NO_EMPTY));
    $answerkey = trim($row->answerkey ?? $row->expected_answer ?? '');
    $haskey = essay_contains_answer_key_terms($plain, $answerkey);
    $short = ($length < 120 || $words < 20);
    $score = is_numeric($row->score) ? (float)$row->score : 0.0;

    if ($row->status !== 'completed' || ($row->status === 'completed' && $score >= 20 && ($short || !$haskey))) {
        $suspicious[$row->id] = [
            'id' => $row->id,
            'submissionid' => $row->submissionid,
            'userid' => $row->userid,
            'status' => $row->status,
            'score' => $score,
            'length' => $length,
            'words' => $words,
            'has_key' => $haskey ? 'yes' : 'no',
            'short' => $short ? 'yes' : 'no',
        ];
    }
}

if (empty($suspicious)) {
    cli_writeln('No suspicious assignment 13 assessments found to rerun.');
    exit(0);
}

cli_writeln('Suspicious assessments to rerun: ' . count($suspicious));
foreach ($suspicious as $item) {
    cli_writeln('  id=' . $item['id'] . ' submission=' . $item['submissionid'] . ' user=' . $item['userid'] . ' status=' . $item['status'] . ' score=' . number_format($item['score'], 2) . ' len=' . $item['length'] . ' words=' . $item['words'] . ' has_key=' . $item['has_key'] . ' short=' . $item['short']);
}

cli_writeln('Resetting suspicious assessments to pending...');
foreach ($suspicious as $item) {
    $update = new stdClass();
    $update->id = $item['id'];
    $update->status = 'pending';
    $update->error_message = null;
    $update->timemodified = time();
    $update->modified = time();
    $DB->update_record('local_essay_ai_assess', $update);
}

$processed = 0;
$failed = 0;
foreach ($suspicious as $item) {
    cli_writeln('Reprocessing assessment id=' . $item['id']);
    $success = assessment_manager::process_assessment($item['id']);
    $current = $DB->get_record('local_essay_ai_assess', ['id' => $item['id']]);
    if ($success && $current && $current->status === 'completed') {
        $processed++;
        cli_writeln('✓ Completed id=' . $item['id'] . ' new_score=' . number_format($current->score, 2) . ' status=' . $current->status);
    } else {
        $failed++;
        $status = $current ? $current->status : 'unknown';
        cli_writeln('✗ Failed id=' . $item['id'] . ' status=' . $status);
    }
}

cli_writeln('Rerun complete. Processed: ' . $processed . ', Failed: ' . $failed);
exit($failed > 0 ? 1 : 0);

function essay_contains_answer_key_terms($essaytext, $answerkey) {
    $answerkey = trim((string)$answerkey);
    if ($answerkey === '') {
        return false;
    }
    $essay = strtolower(trim((string)$essaytext));
    $answerkey = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', strtolower($answerkey));
    $words = preg_split('/\s+/u', trim($answerkey), -1, PREG_SPLIT_NO_EMPTY);
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
