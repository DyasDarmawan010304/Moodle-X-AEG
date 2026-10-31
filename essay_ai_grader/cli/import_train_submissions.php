<?php
// Bulk import student assignment answers from CSV (converted from XLS) column #2.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/essay_ai_grader/lib.php');

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'assignmentid' => 12,
    'csv' => '',
    'startrow' => 2,
    'col' => 2,
], [
    'h' => 'help',
    'a' => 'assignmentid',
    'c' => 'csv',
]);

if ($options['help'] || empty($options['csv'])) {
    echo "Usage: php import_train_submissions.php --assignmentid=12 --csv=PATH [--startrow=2] [--col=2]\n";
    exit(0);
}

$assignmentid = (int)$options['assignmentid'];
$csvpath = $options['csv'];
$startrow = max(1, (int)$options['startrow']);
$colidx = max(1, (int)$options['col']) - 1;

if (!file_exists($csvpath)) {
    cli_error('CSV file not found: ' . $csvpath);
}

$assign = $DB->get_record('assign', ['id' => $assignmentid], '*', MUST_EXIST);
$courseid = (int)$assign->course;

$config = $DB->get_record('local_essay_ai_config', ['assignmentid' => $assignmentid]);
if (!$config || empty($config->enabled)) {
    mtrace('WARNING: AI grading config for assignment ' . $assignmentid . ' is missing/disabled. Submissions will still be created.');
}

$sql = "SELECT DISTINCT u.id, u.username
          FROM {user} u
          JOIN {user_enrolments} ue ON ue.userid = u.id
          JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = ?
          JOIN {role_assignments} ra ON ra.userid = u.id
          JOIN {context} cx ON cx.id = ra.contextid AND cx.contextlevel = 50 AND cx.instanceid = ?
          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
         WHERE u.deleted = 0 AND u.suspended = 0
      ORDER BY u.id";
$students = array_values($DB->get_records_sql($sql, [$courseid, $courseid]));
if (empty($students)) {
    cli_error('No active students enrolled in course ' . $courseid);
}

$rows = [];
$fp = fopen($csvpath, 'r');
if (!$fp) {
    cli_error('Failed to open CSV: ' . $csvpath);
}

$line = 0;
while (($data = fgetcsv($fp)) !== false) {
    $line++;
    if ($line < $startrow) {
        continue;
    }
    if (!isset($data[$colidx])) {
        continue;
    }
    $text = trim((string)$data[$colidx]);
    if ($text === '') {
        continue;
    }
    $rows[] = $text;
}
fclose($fp);

if (empty($rows)) {
    cli_error('No essay text found from selected column/row.');
}

$now = time();
$total = count($students);
$available = count($rows);
mtrace("students={$total}, essays_available={$available}, assignmentid={$assignmentid}");

$created = 0;
$updated = 0;
$queued = 0;

for ($i = 0; $i < $total; $i++) {
    $student = $students[$i];
    $essay = $rows[$i % $available];

    try {
        // Reuse existing submission if it already exists for this user/assignment.
        $existing = $DB->get_record('assign_submission', [
            'assignment' => $assignmentid,
            'userid' => $student->id,
            'groupid' => 0,
            'attemptnumber' => 0,
        ]);

        if ($existing) {
            $submissionid = $existing->id;

            $existing->timemodified = $now;
            $existing->status = 'submitted';
            $DB->update_record('assign_submission', $existing);

            // Replace existing onlinetext for this submission.
            $DB->delete_records('assignsubmission_onlinetext', [
                'assignment' => $assignmentid,
                'submission' => $submissionid,
            ]);

            $updated++;
        } else {
            $submission = new stdClass();
            $submission->assignment = $assignmentid;
            $submission->userid = $student->id;
            $submission->timecreated = $now;
            $submission->timemodified = $now;
            $submission->status = 'submitted';
            $submission->groupid = 0;
            $submission->attemptnumber = 0;

            $submissionid = $DB->insert_record('assign_submission', $submission);
            $created++;
        }

        $onlinetext = new stdClass();
        $onlinetext->assignment = $assignmentid;
        $onlinetext->submission = $submissionid;
        $onlinetext->onlinetext = $essay;
        $onlinetext->onlineformat = FORMAT_HTML;
        $DB->insert_record('assignsubmission_onlinetext', $onlinetext);

        // Queue into AI grader.
        if (function_exists('local_essay_ai_grader_queue_submission')) {
            local_essay_ai_grader_queue_submission($submissionid, $assignmentid, $student->id);
            $queued++;
        }
    } catch (dml_write_exception $e) {
        $msg = 'DB write failed for user ' . $student->id . ': ' . $e->getMessage();
        if (!empty($e->debuginfo)) {
            $msg .= ' | DEBUG: ' . $e->debuginfo;
        }
        cli_error($msg);
    }
}

mtrace("created_submissions={$created}");
mtrace("updated_submissions={$updated}");
mtrace("queued_assessments={$queued}");
