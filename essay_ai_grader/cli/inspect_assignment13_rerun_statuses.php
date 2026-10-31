<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
global $DB;
$rows = $DB->get_records('local_essay_ai_assess', ['assignmentid' => 13]);
foreach ($rows as $r) {
    echo 'id=' . $r->id . ' status=' . $r->status . ' score=' . $r->score . ' error=' . ($r->error_message ?? 'NULL') . "\n";
}
