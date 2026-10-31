<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
global $DB;
$ids = [171, 172, 179, 185, 194];
foreach ($ids as $id) {
    $r = $DB->get_record('local_essay_ai_assess', ['id' => $id]);
    if (!$r) {
        echo "Missing {$id}\n";
        continue;
    }
    echo "ID={$r->id} score={$r->score} status={$r->status} timemodified={$r->timemodified}\n";
    echo "criteria_scores=" . $r->criteria_scores . "\n";
    echo "raw_response=" . substr($r->gemini_response, 0, 1200) . "\n";
    echo "raw_response_full=" . substr($r->gemini_response_raw, 0, 1200) . "\n";
    echo "----\n";
}
