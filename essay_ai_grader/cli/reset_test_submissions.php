<?php
// Reset all assignment submissions and related grading/AI assessment data.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$manager = $DB->get_manager();

function table_exists_local($manager, $name) {
    return $manager->table_exists(new xmldb_table($name));
}

$targets = [
    'assign_submission',
    'assignsubmission_onlinetext',
    'assignsubmission_file',
    'assign_grades',
    'grade_grades',
    'files',
    'local_essay_ai_grader_assessments',
    'local_essay_ai_grader_queue',
];

mtrace('=== BEFORE COUNTS ===');
foreach ($targets as $t) {
    if (table_exists_local($manager, $t)) {
        $count = $DB->count_records($t);
        mtrace($t . ': ' . $count);
    } else {
        mtrace($t . ': (table not found)');
    }
}

$transaction = $DB->start_delegated_transaction();

// Delete AI grader data first (if tables exist).
if (table_exists_local($manager, 'local_essay_ai_grader_assessments')) {
    $DB->delete_records('local_essay_ai_grader_assessments');
}
if (table_exists_local($manager, 'local_essay_ai_grader_queue')) {
    $DB->delete_records('local_essay_ai_grader_queue');
}

// Delete assign submission plugin content and core submissions/grades.
if (table_exists_local($manager, 'assignsubmission_onlinetext')) {
    $DB->delete_records('assignsubmission_onlinetext');
}
if (table_exists_local($manager, 'assignsubmission_file')) {
    $DB->delete_records('assignsubmission_file');
}
if (table_exists_local($manager, 'assign_grades')) {
    $DB->delete_records('assign_grades');
}
if (table_exists_local($manager, 'assign_submission')) {
    $DB->delete_records('assign_submission');
}

// Remove assignment gradebook grades only (keep grade items).
if (table_exists_local($manager, 'grade_grades') && table_exists_local($manager, 'grade_items')) {
    $sql = "itemid IN (SELECT id FROM {grade_items} WHERE itemmodule = ?)";
    $DB->delete_records_select('grade_grades', $sql, ['assign']);
}

// Remove assignment submission/feedback files to avoid orphan files.
if (table_exists_local($manager, 'files')) {
    $DB->delete_records_select('files', "component IN (?, ?, ?)", [
        'assignsubmission_file',
        'assignsubmission_onlinetext',
        'assignfeedback_comments'
    ]);
}

$transaction->allow_commit();

mtrace('=== AFTER COUNTS ===');
foreach ($targets as $t) {
    if (table_exists_local($manager, $t)) {
        $count = $DB->count_records($t);
        mtrace($t . ': ' . $count);
    } else {
        mtrace($t . ': (table not found)');
    }
}

mtrace('Reset selesai. Semua jawaban assignment siswa telah dihapus.');
