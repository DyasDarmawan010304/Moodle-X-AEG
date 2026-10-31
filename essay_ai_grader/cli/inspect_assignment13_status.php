<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
global $DB;
$stats = $DB->get_records_sql('SELECT status, COUNT(*) AS cnt FROM {local_essay_ai_assess} WHERE assignmentid = ? GROUP BY status', [13]);
foreach ($stats as $s) {
    echo $s->status . ': ' . $s->cnt . "\n";
}
