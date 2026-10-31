<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
global $DB;

$ids = [171, 179, 185, 194];
foreach ($ids as $id) {
    $a = $DB->get_record('local_essay_ai_assess', ['id' => $id]);
    if (!$a) {
        echo "Missing id={$id}\n";
        continue;
    }
    echo "ID={$a->id} score={$a->score} criteria={$a->criteria_scores}\n";
    echo "raw_response=" . substr($a->gemini_response, 0, 1200) . "\n";
    echo "raw_response_full=" . substr($a->gemini_response_raw, 0, 1200) . "\n";
    echo "----\n";
}
