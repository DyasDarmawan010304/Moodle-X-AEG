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

require_once('../../config.php');

$cmid = optional_param('cmid', 0, PARAM_INT);
$assignid = optional_param('id', 0, PARAM_INT);
$purge = optional_param('purge', 0, PARAM_INT);

if ($cmid) {
    $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
    $assignid = (int)$cm->instance;
} else if ($assignid) {
    $cm = get_coursemodule_from_instance('assign', $assignid, 0, false, MUST_EXIST);
    $cmid = (int)$cm->id;
} else {
    print_error('missingparam');
}

$assignment = $DB->get_record('assign', array('id' => $assignid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $assignment->course), '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('moodle/site:config', context_system::instance());

if ($purge) {
    require_sesskey();
    purge_all_caches();
    redirect(new moodle_url('/local/essay_ai_grader/debug_assignment_config.php', array(
        'cmid' => $cmid,
    )), 'Caches purged successfully.', 1);
}

$config = $DB->get_record('local_essay_ai_config', array('assignmentid' => $assignid));

$PAGE->set_url('/local/essay_ai_grader/debug_assignment_config.php', array('cmid' => $cmid));
$PAGE->set_title('Debug Assignment Config');
$PAGE->set_heading('Debug Assignment Config');

echo $OUTPUT->header();

echo html_writer::tag('h3', 'Assignment Config Debug');
echo html_writer::tag('p', 'Assignment: ' . format_string($assignment->name) . ' (ID: ' . $assignid . ', CMID: ' . $cmid . ')');

$purgeurl = new moodle_url('/local/essay_ai_grader/debug_assignment_config.php', array(
    'cmid' => $cmid,
    'purge' => 1,
    'sesskey' => sesskey(),
));
echo html_writer::link($purgeurl, 'Purge caches', array('class' => 'btn btn-secondary mb-3'));

if ($config) {
    echo $OUTPUT->notification('Config record found.', \core\output\notification::NOTIFY_SUCCESS);
    echo html_writer::tag('pre', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
} else {
    echo $OUTPUT->notification('No config record found for this assignment.', \core\output\notification::NOTIFY_WARNING);
}

echo html_writer::tag('p', 'Open configure page: ' .
    html_writer::link(new moodle_url('/local/essay_ai_grader/configure.php', array('cmid' => $cmid)),
    '/local/essay_ai_grader/configure.php?cmid=' . $cmid));

echo $OUTPUT->footer();
