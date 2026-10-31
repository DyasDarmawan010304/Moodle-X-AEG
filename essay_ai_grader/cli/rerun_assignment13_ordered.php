<?php
/**
 * CLI script to rerun AI grading for assignment 13 in submission order.
 * It backs up current assessment results, resets the status to pending,
 * and then processes each assessment sequentially.
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

$assessments = $DB->get_records_sql(
    'SELECT a.* FROM {local_essay_ai_assess} a
     LEFT JOIN {assign_submission} s ON s.id = a.submissionid
     WHERE a.assignmentid = ?
     ORDER BY COALESCE(s.timemodified, a.created) ASC, a.id ASC',
    [$assignmentid]
);

if (empty($assessments)) {
    cli_writeln("No assessments found for assignment {$assignmentid}.");
    exit(0);
}

$backupfile = __DIR__ . DIRECTORY_SEPARATOR . 'assignment13_backup_old_results.csv';
$handle = fopen($backupfile, 'w');
if (!$handle) {
    fwrite(STDERR, "ERROR: Cannot write backup file: {$backupfile}\n");
    exit(1);
}
fputcsv($handle, ['assessmentid', 'submissionid', 'userid', 'old_status', 'old_score', 'old_criteria_scores', 'old_timemodified', 'old_gemini_response']);
foreach ($assessments as $assessment) {
    fputcsv($handle, [
        $assessment->id,
        $assessment->submissionid,
        $assessment->userid,
        $assessment->status,
        $assessment->score,
        $assessment->criteria_scores,
        $assessment->timemodified,
        substr($assessment->gemini_response ?? '', 0, 1000),
    ]);
}
fclose($handle);
cli_writeln('Backup written to: ' . $backupfile);

cli_writeln('Resetting statuses to pending...');
foreach ($assessments as $assessment) {
    $record = new stdClass();
    $record->id = $assessment->id;
    $record->status = 'pending';
    $record->error_message = null;
    $record->timemodified = time();
    $record->modified = time();
    $DB->update_record('local_essay_ai_assess', $record);
}

cli_writeln('Starting rerun in submission order...');

$processed = 0;
$failed = 0;
foreach ($assessments as $assessment) {
    cli_writeln('Processing assessment id=' . $assessment->id . ' submission=' . $assessment->submissionid . ' user=' . $assessment->userid);
    $success = assessment_manager::process_assessment($assessment->id);
    $current = $DB->get_record('local_essay_ai_assess', ['id' => $assessment->id]);
    if ($success) {
        $processed++;
        cli_writeln('✓ Completed id=' . $assessment->id . ' score=' . $current->score . ' status=' . $current->status);
    } else {
        $failed++;
        cli_writeln('✗ Failed id=' . $assessment->id . ' status=' . ($current->status ?? 'unknown'));
    }
}

cli_writeln('Rerun complete. Processed: ' . $processed . ', Failed: ' . $failed);
exit($failed > 0 ? 1 : 0);
