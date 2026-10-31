<?php
/**
 * Debug script to check Essay AI Grader configurations in database
 * Access via: http://localhost/local/essay_ai_grader/debug_config_check.php
 */

require_once('../../config.php');

// Check if admin
if (!is_siteadmin()) {
    die('You must be logged in as admin to use this debug script');
}

global $DB, $OUTPUT;

echo $OUTPUT->header();
echo "<h1>Essay AI Grader - Configuration Debug</h1>";

// Check if table exists
$tables = $DB->get_tables();
if (!in_array('local_essay_ai_config', $tables)) {
    echo "<p style='color:red'><strong>ERROR: Table 'local_essay_ai_config' does not exist!</strong></p>";
} else {
    echo "<p style='color:green'><strong>✓ Table exists</strong></p>";
}

// Get all configs with assignment names
$sql = "SELECT c.*, a.name as assignmentname 
        FROM {local_essay_ai_config} c
        LEFT JOIN {assign} a ON a.id = c.assignmentid
        ORDER BY c.timemodified DESC";

$configs = $DB->get_records_sql($sql);

if (empty($configs)) {
    echo "<p style='color:red'><strong>No configurations found in database</strong></p>";
} else {
    echo "<h2>Found " . count($configs) . " Configuration(s)</h2>";
    echo "<table style='border:1px solid #ccc; border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#eee;'>";
    echo "<th style='border:1px solid #ccc; padding:10px;'>Assignment</th>";
    echo "<th style='border:1px solid #ccc; padding:10px;'>Assignment ID</th>";
    echo "<th style='border:1px solid #ccc; padding:10px;'>Enabled</th>";
    echo "<th style='border:1px solid #ccc; padding:10px;'>Max Score</th>";
    echo "<th style='border:1px solid #ccc; padding:10px;'>Rubric (first 60 chars)</th>";
    echo "<th style='border:1px solid #ccc; padding:10px;'>Modified Time</th>";
    echo "</tr>";
    
    foreach ($configs as $cfg) {
        echo "<tr>";
        echo "<td style='border:1px solid #ccc; padding:10px;'>" . htmlspecialchars($cfg->assignmentname ?? 'N/A') . "</td>";
        echo "<td style='border:1px solid #ccc; padding:10px;'>" . $cfg->assignmentid . "</td>";
        echo "<td style='border:1px solid #ccc; padding:10px;'>" . ($cfg->enabled ? '✓ Yes' : '✗ No') . "</td>";
        echo "<td style='border:1px solid #ccc; padding:10px;'>" . ($cfg->maxscore ?? 'N/A') . "</td>";
        echo "<td style='border:1px solid #ccc; padding:10px;'><code style='font-size:11px;'>" . htmlspecialchars(substr($cfg->rubricguide ?? '[EMPTY]', 0, 60)) . "</code></td>";
        echo "<td style='border:1px solid #ccc; padding:10px;'>" . date('Y-m-d H:i:s', $cfg->timemodified) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Full Rubric Content (for latest config)</h2>";
    $latest = reset($configs);
    echo "<div style='background:#f5f5f5; padding:15px; border:1px solid #ddd; border-radius:4px; margin-top:20px;'>";
    echo "<h3>Assignment: " . htmlspecialchars($latest->assignmentname ?? 'N/A') . "</h3>";
    echo "<p><strong>Rubric Guide:</strong></p>";
    echo "<pre style='background:white; padding:10px; border:1px solid #ccc; overflow:auto; max-height:300px;'>";
    echo htmlspecialchars($latest->rubricguide ?? '[EMPTY]');
    echo "</pre>";
    echo "<p><strong>Prompt Template:</strong></p>";
    echo "<pre style='background:white; padding:10px; border:1px solid #ccc; overflow:auto; max-height:300px;'>";
    echo htmlspecialchars($latest->prompttemplate ?? '[EMPTY]');
    echo "</pre>";
    echo "</div>";
}

echo $OUTPUT->footer();
