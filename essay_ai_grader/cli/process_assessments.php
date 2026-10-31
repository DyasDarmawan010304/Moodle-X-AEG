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
 * CLI script to process pending essay assessments.
 * Usage: php process_assessments.php [--assignment=ID] [--limit=NUM]
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Handle both Unix and Windows path separators
$dir = str_replace('\\', '/', __DIR__);
$configpath = preg_replace('#/local/essay_ai_grader/cli$#', '/config.php', $dir);

// If that didn't work, try absolute path
if (!file_exists($configpath)) {
    $configpath = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'config.php';
}

// Last resort: try from current directory
if (!file_exists($configpath)) {
    $configpath = getcwd() . DIRECTORY_SEPARATOR . 'config.php';
}

if (!file_exists($configpath)) {
    fwrite(STDERR, "ERROR: Cannot find config.php at: $configpath\n");
    fwrite(STDERR, "Current dir: " . getcwd() . "\n");
    fwrite(STDERR, "Script dir: " . __DIR__ . "\n");
    exit(1);
}

require($configpath);

// Load Moodle CLI functions
require_once($CFG->libdir . '/clilib.php');

global $DB;

use local_essay_ai_grader\assessment_manager;

// Parse arguments.
$assignmentid = getopt('', array('assignment:', 'limit:'))['assignment'] ?? null;
$limit = getopt('', array('assignment:', 'limit:'))['limit'] ?? 100;

$limit = (int) $limit;

// Get pending assessments.
if ($assignmentid) {
    $pending = $DB->get_records('local_essay_ai_assess', array(
        'assignmentid' => $assignmentid,
        'status' => 'pending',
    ), 'created ASC', '*', 0, $limit);
} else {
    $pending = $DB->get_records('local_essay_ai_assess', array(
        'status' => 'pending',
    ), 'created ASC', '*', 0, $limit);
}

if (empty($pending)) {
    cli_writeln('No pending assessments to process.');
    exit(0);
}

cli_writeln('Processing ' . count($pending) . ' pending assessments...');

$processed = 0;
$failed = 0;

foreach ($pending as $assessment) {
    try {
        // Update status to processing.
        $DB->set_field('local_essay_ai_assess', 'status', 'processing', array(
            'id' => $assessment->id,
        ));
        
        // Process the assessment.
        if (assessment_manager::process_assessment($assessment->id)) {
            $processed++;
            cli_writeln('✓ Assessment ' . $assessment->id . ' completed');
        } else {
            $failed++;
            $current = $DB->get_record('local_essay_ai_assess', array('id' => $assessment->id),
                'id,status,error_message', IGNORE_MISSING);
            if ($current && $current->status !== 'error') {
                $update = new stdClass();
                $update->id = $assessment->id;
                $update->status = 'error';
                $update->score = null;
                $update->error_message = !empty($current->error_message)
                    ? $current->error_message
                    : 'Processing failed without explicit error detail.';
                $update->timemodified = time();
                $update->modified = time();
                $DB->update_record('local_essay_ai_assess', $update);
            }
            cli_writeln('✗ Assessment ' . $assessment->id . ' failed');
        }
    } catch (Exception $e) {
        $failed++;
        $update = new stdClass();
        $update->id = $assessment->id;
        $update->status = 'error';
        $update->score = null;
        $update->error_message = $e->getMessage();
        $update->timemodified = time();
        $update->modified = time();
        $DB->update_record('local_essay_ai_assess', $update);
        cli_writeln('✗ Assessment ' . $assessment->id . ' error: ' . $e->getMessage());
    }
}

cli_writeln('');
cli_writeln('Complete: ' . $processed . ' processed, ' . $failed . ' failed');
exit($failed > 0 ? 1 : 0);
