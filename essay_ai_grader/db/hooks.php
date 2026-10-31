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
 * Hook definitions for Essay AI Grader plugin (Moodle 5+).
 * 
 * This plugin extends assignment forms to provide AI grading configuration options.
 *
 * @package    local_essay_ai_grader
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// For Moodle 5+, we use traditional callback functions in lib.php
// The assignment module will call local_essay_ai_grader_coursemodule_standard_elements
// when building the assignment edit form

// No hooks.php configuration needed - function naming convention is used
// Hook name: coursemodule_standard_elements
// Plugin callback: local_essay_ai_grader_coursemodule_standard_elements (in lib.php)
