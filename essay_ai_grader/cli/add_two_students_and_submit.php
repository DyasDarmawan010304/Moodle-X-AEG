<?php
// Create 2 new students, enrol in course, and submit next essays from train CSV.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/essay_ai_grader/lib.php');

$courseid = 2;
$assignmentid = 12;
$csvpath = 'E:\\pengujian sistem\\datasets\\asap2\\train - Copy.csv';
$defaultpassword = 'Siswa#2026';

if (!file_exists($csvpath)) {
    cli_error('CSV not found: ' . $csvpath);
}

$assign = $DB->get_record('assign', ['id' => $assignmentid], '*', MUST_EXIST);
if ((int)$assign->course !== $courseid) {
    cli_error('Assignment ' . $assignmentid . ' is not in course ' . $courseid);
}

$enrol = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$coursectx = context_course::instance($courseid);

// Load essay texts from column 2 starting row 2.
$rows = [];
$fp = fopen($csvpath, 'r');
$line = 0;
while (($data = fgetcsv($fp)) !== false) {
    $line++;
    if ($line < 2) { continue; }
    if (!isset($data[1])) { continue; }
    $txt = trim((string)$data[1]);
    if ($txt === '') { continue; }
    $rows[] = $txt;
}
fclose($fp);
if (count($rows) < 2) {
    cli_error('Not enough essay rows in CSV');
}

$currentcount = $DB->count_records('assign_submission', ['assignment' => $assignmentid]);
$startindex = $currentcount; // continuation after existing submissions.

// Find next username number for siswaNNN.
$existing = $DB->get_records_sql("SELECT username FROM {user} WHERE username LIKE 'siswa%'");
$maxnum = 0;
foreach ($existing as $u) {
    if (preg_match('/^siswa(\d+)$/', $u->username, $m)) {
        $n = (int)$m[1];
        if ($n > $maxnum) { $maxnum = $n; }
    }
}

$now = time();
$created = [];

$transaction = $DB->start_delegated_transaction();
for ($i = 1; $i <= 2; $i++) {
    $num = $maxnum + $i;
    $username = 'siswa' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);

    $user = new stdClass();
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $user->username = $username;
    $user->password = hash_internal_user_password($defaultpassword);
    $user->firstname = 'Siswa';
    $user->lastname = str_pad((string)$num, 3, '0', STR_PAD_LEFT);
    $user->email = $username . '@example.com';
    $user->city = 'Local';
    $user->country = 'ID';
    $user->lang = 'id';
    $user->timecreated = $now;
    $user->timemodified = $now;

    $userid = $DB->insert_record('user', $user);

    // Enrol user manually to course.
    $ue = new stdClass();
    $ue->status = 0;
    $ue->enrolid = $enrol->id;
    $ue->userid = $userid;
    $ue->timestart = 0;
    $ue->timeend = 0;
    $ue->modifierid = 2;
    $ue->timecreated = $now;
    $ue->timemodified = $now;
    $DB->insert_record('user_enrolments', $ue);

    // Role assignment student at course context.
    $ra = new stdClass();
    $ra->roleid = $studentrole->id;
    $ra->contextid = $coursectx->id;
    $ra->userid = $userid;
    $ra->timemodified = $now;
    $ra->modifierid = 2;
    $ra->component = '';
    $ra->itemid = 0;
    $ra->sortorder = 0;
    $DB->insert_record('role_assignments', $ra);

    // Create submission with continuation essay.
    $essay = $rows[($startindex + ($i - 1)) % count($rows)];

    $sub = new stdClass();
    $sub->assignment = $assignmentid;
    $sub->userid = $userid;
    $sub->timecreated = $now;
    $sub->timemodified = $now;
    $sub->status = 'submitted';
    $sub->groupid = 0;
    $sub->attemptnumber = 0;
    $sub->latest = 1;
    $submissionid = $DB->insert_record('assign_submission', $sub);

    $ot = new stdClass();
    $ot->assignment = $assignmentid;
    $ot->submission = $submissionid;
    $ot->onlinetext = $essay;
    $ot->onlineformat = FORMAT_HTML;
    $DB->insert_record('assignsubmission_onlinetext', $ot);

    local_essay_ai_grader_queue_submission($submissionid, $assignmentid, $userid);

    $created[] = [
        'userid' => $userid,
        'username' => $username,
        'submissionid' => $submissionid,
        'essayindex' => $startindex + ($i - 1),
    ];
}
$transaction->allow_commit();

echo 'created_users=2' . PHP_EOL;
foreach ($created as $c) {
    echo $c['userid'] . '|' . $c['username'] . '|submission=' . $c['submissionid'] . '|essay_idx=' . $c['essayindex'] . PHP_EOL;
}

echo 'assignment_submission_count=' . $DB->count_records('assign_submission', ['assignment' => $assignmentid]) . PHP_EOL;
$st = $DB->get_records_sql("SELECT status, COUNT(*) cnt FROM {local_essay_ai_assess} WHERE assignmentid=? GROUP BY status", [$assignmentid]);
foreach ($st as $s) {
    echo 'assess_' . $s->status . '=' . $s->cnt . PHP_EOL;
}
