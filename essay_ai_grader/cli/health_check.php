#!/usr/bin/env php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Operational health check for Essay AI Grader.
 *
 * @package   local_essay_ai_grader
 */

define('CLI_SCRIPT', true);

$dir = str_replace('\\', '/', __DIR__);
$configpath = preg_replace('#/local/essay_ai_grader/cli$#', '/config.php', $dir);
if (!file_exists($configpath)) {
    $configpath = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'config.php';
}
require($configpath);
require_once($CFG->libdir . '/clilib.php');

global $DB;

cli_writeln('=== ESSAY AI GRADER HEALTH CHECK ===');
cli_writeln('');

$provider = get_config('local_essay_ai_grader', 'provider') ?: 'gemini';
$autogradeonsubmit = !empty(get_config('local_essay_ai_grader', 'auto_grade_on_submit')) ? 'ON' : 'OFF';
$autoenable = get_config('local_essay_ai_grader', 'auto_enable_new_assignments');
$autoenable = ($autoenable === false || !empty($autoenable)) ? 'ON' : 'OFF';
$autopublish = get_config('local_essay_ai_grader', 'auto_publish_grade');
$autopublish = ($autopublish === false || !empty($autopublish)) ? 'ON' : 'OFF';

cli_writeln('Provider: ' . $provider);
cli_writeln('Auto-grade on submit: ' . $autogradeonsubmit);
cli_writeln('Auto-enable new assignments: ' . $autoenable);
cli_writeln('Auto-publish gradebook: ' . $autopublish);
if ($provider === 'ollama') {
    cli_writeln('Ollama URL: ' . (get_config('local_essay_ai_grader', 'ollama_url') ?: 'http://127.0.0.1:11434'));
    cli_writeln('Ollama model: ' . (get_config('local_essay_ai_grader', 'ollama_model') ?: 'llama3.1:8b'));
} else {
    cli_writeln('Gemini model: ' . (get_config('local_essay_ai_grader', 'gemini_model') ?: 'gemini-flash-latest'));
}
cli_writeln('');

$task = $DB->get_record('task_scheduled', array('classname' => '\local_essay_ai_grader\task\process_assessments'));
if ($task) {
    cli_writeln('Task schedule: ' . $task->minute . ' ' . $task->hour . ' ' . $task->day . ' ' . $task->month . ' ' . $task->dayofweek);
    cli_writeln('Task disabled: ' . (!empty($task->disabled) ? 'YES' : 'NO'));
}
cli_writeln('');

$statuses = array('pending', 'processing', 'completed', 'error');
cli_writeln('Queue status:');
foreach ($statuses as $status) {
    $count = $DB->count_records('local_essay_ai_assess', array('status' => $status));
    cli_writeln('  - ' . $status . ': ' . $count);
}

$stuckthreshold = time() - (15 * 60);
$stuck = $DB->count_records_select('local_essay_ai_assess',
    'status = ? AND timemodified < ?',
    array('processing', $stuckthreshold));
cli_writeln('  - stuck_processing(>15m): ' . $stuck);
cli_writeln('');

$latest = $DB->get_records('local_essay_ai_assess', array(), 'id DESC', 'id,assignmentid,submissionid,status,score,error_message,timemodified', 0, 5);
cli_writeln('Latest assessments:');
if (empty($latest)) {
    cli_writeln('  - none');
} else {
    foreach ($latest as $a) {
        $line = '  - id=' . $a->id
            . ' assignment=' . $a->assignmentid
            . ' submission=' . $a->submissionid
            . ' status=' . $a->status
            . ' score=' . ($a->score !== null ? $a->score : 'N/A');
        cli_writeln($line);
        if ($a->status === 'error' && !empty($a->error_message)) {
            cli_writeln('    error: ' . $a->error_message);
        }
    }
}
cli_writeln('');
cli_writeln('=== END HEALTH CHECK ===');
