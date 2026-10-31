<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

global $DB;

$tables = $DB->get_tables();
$essay_tables = array_filter($tables, function($table) {
    return strpos($table, 'essay') !== false;
});
echo 'Essay-related tables:' . PHP_EOL;
print_r($essay_tables);

// Check if local_essay_ai_config exists
if (in_array('local_essay_ai_config', $tables)) {
    echo 'local_essay_ai_config exists' . PHP_EOL;
    $configs = $DB->get_records('local_essay_ai_config', array(), '', '*', 0, 5);
    print_r($configs);
} else {
    echo 'local_essay_ai_config does not exist' . PHP_EOL;
}