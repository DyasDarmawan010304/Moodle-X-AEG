<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
global $DB;
$config = $DB->get_record('local_essay_ai_config', ['assignid' => 13]);
if (!$config) {
    echo "No config for assignment 13\n";
    exit(0);
}
echo "id={$config->id}\n";
echo "assignid={$config->assignid}\n";
echo "enabled={$config->enabled}\n";
echo "maxscore={$config->maxscore}\n";
echo "criteria_config=" . substr($config->criteria_config, 0, 400) . "\n";
echo "criteria=" . substr($config->criteria, 0, 400) . "\n";
echo "prompttemplate=" . substr($config->prompttemplate, 0, 400) . "\n";
echo "answerkey=" . substr($config->answerkey, 0, 400) . "\n";
echo "rubricguide=" . substr($config->rubricguide, 0, 400) . "\n";
