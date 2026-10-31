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
 * Upgrade steps for local_essay_ai_grader.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_essay_ai_grader_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026022700) {
        $table = new xmldb_table('local_essay_ai_config');

        $answerkey = new xmldb_field('answerkey', XMLDB_TYPE_TEXT, null, null, null, null, null, 'prompttemplate');
        if (!$dbman->field_exists($table, $answerkey)) {
            $dbman->add_field($table, $answerkey);
        }

        $rubricguide = new xmldb_field('rubricguide', XMLDB_TYPE_TEXT, null, null, null, null, null, 'answerkey');
        if (!$dbman->field_exists($table, $rubricguide)) {
            $dbman->add_field($table, $rubricguide);
        }

        upgrade_plugin_savepoint(true, 2026022700, 'local', 'essay_ai_grader');
    }

    // Add cost tracking fields to local_essay_ai_usage table (v2026.02.27)
    if ($oldversion < 2026022701) {
        $table = new xmldb_table('local_essay_ai_usage');

        // Add 'cost' field if it doesn't exist
        $field = new xmldb_field('cost', XMLDB_TYPE_NUMBER, '15,6', null, false, false, null, 'tokensused');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add 'costcurrency' field if it doesn't exist
        $field = new xmldb_field('costcurrency', XMLDB_TYPE_CHAR, '3', null, false, false, 'USD', 'cost');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add index on assessmentid if it doesn't exist
        $index = new xmldb_index('assessmentid', XMLDB_INDEX_NOTUNIQUE, array('assessmentid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Add index on apitype if it doesn't exist
        $index = new xmldb_index('apitype', XMLDB_INDEX_NOTUNIQUE, array('apitype'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026022701, 'local', 'essay_ai_grader');
    }

    if ($oldversion < 2026052000) {
        $table = new xmldb_table('local_essay_ai_assess');

        $field = new xmldb_field('gemini_response_raw', XMLDB_TYPE_TEXT, null, null, null, null, null, 'gemini_response');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026052000, 'local', 'essay_ai_grader');
    }

    // Create local_essay_ai_standard table for assessment standards (v2026.04.05)
    if ($oldversion < 2026040501) {
        $table = new xmldb_table('local_essay_ai_standard');

        // Drop and recreate if table exists to fix constraint issues
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('assignmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('standard_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            
            $table->add_field('source', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'custom');
            $table->add_field('priority', XMLDB_TYPE_INTEGER, '3', null, null, null, '6');
            
            $table->add_field('criteria', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('grade_scale', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('rubric', XMLDB_TYPE_TEXT, null, null, null, null, null);
            
            $table->add_field('author', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('reference_url', XMLDB_TYPE_CHAR, '500', null, null, null, null);
            $table->add_field('kemendikbud_subject', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('kemendikbud_level', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, null, null, '1');
            
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('assignmentsource', XMLDB_KEY_UNIQUE, array('assignmentid', 'standard_name'));
            
            // Add indexes
            $table->add_index('source', XMLDB_INDEX_NOTUNIQUE, array('source'));
            $table->add_index('enabled', XMLDB_INDEX_NOTUNIQUE, array('enabled'));
            $table->add_index('kemlevel', XMLDB_INDEX_NOTUNIQUE, array('kemendikbud_level'));
            $table->add_index('created', XMLDB_INDEX_NOTUNIQUE, array('timecreated'));
            
            $dbman->create_table($table);
        }

        // Insert Kemendikbud K13 sample standards
        $now = time();
        
        // Standard 1: Paragraph Essay (SMA)
        $standard1 = new stdClass();
        $standard1->assignmentid = 0;
        $standard1->standard_name = 'Paragraph Structure and Argumentation (SMA)';
        $standard1->description = 'Kurikulum 2013 standard for evaluating well-structured paragraphs with clear arguments';
        $standard1->source = 'kemendikbud';
        $standard1->priority = 1;
        $standard1->criteria = json_encode(array(
            'thesis_clarity' => 'Clear statement of main idea or thesis',
            'supporting_ideas' => 'Well-developed supporting ideas with evidence',
            'organization' => 'Logical flow and coherence between ideas',
            'language' => 'Grammar, vocabulary, and sentence structure',
            'conclusion' => 'Strong concluding statement that reinforces thesis'
        ));
        $standard1->grade_scale = json_encode(array(
            '90-100' => 'Excellent - Meets all criteria with strong mastery',
            '80-89' => 'Good - Meets most criteria with solid performance',
            '70-79' => 'Satisfactory - Meets basic criteria with minor issues',
            '60-69' => 'Fair - Limited performance with several weaknesses',
            '0-59' => 'Poor - Does not meet basic criteria'
        ));
        $standard1->rubric = 'Thesis Statement & Topic Development (20%): Student must present clear thesis statement in opening sentence with logical development of ideas in supporting sentences.

Supporting Evidence (25%): Each supporting idea must include specific examples, facts, or explanations. Evidence must directly support the thesis.

Organization & Coherence (20%): Ideas must be arranged logically with clear transitions between sentences. Paragraph must have identifiable opening, development, and closing.

Language Use (20%): Demonstrate appropriate academic vocabulary, varied sentence structures, and correct grammar throughout the paragraph.

Conclusion (15%): Concluding sentence must reinforce main idea and create sense of closure without introducing new information.';
        $standard1->author = 'Kementerian Pendidikan dan Kebudayaan';
        $standard1->reference_url = 'https://www.kemdikbud.go.id/kurikulum-2013';
        $standard1->kemendikbud_subject = 'Indonesian Language Arts (Bahasa Indonesia)';
        $standard1->kemendikbud_level = 'SMA (Senior High School)';
        $standard1->enabled = 1;
        $standard1->timecreated = $now;
        $standard1->timemodified = $now;
        
        if (!$DB->record_exists('local_essay_ai_standard', array('assignmentid' => 0, 'standard_name' => 'Paragraph Structure and Argumentation (SMA)'))) {
            $DB->insert_record('local_essay_ai_standard', $standard1);
        }
        
        // Standard 2: Descriptive Essay (JHS)
        $standard2 = new stdClass();
        $standard2->assignmentid = 0;
        $standard2->standard_name = 'Descriptive Essay Text Structure (JHS)';
        $standard2->description = 'Kurikulum 2013 standard for evaluating descriptive writing with detailed sensory language';
        $standard2->source = 'kemendikbud';
        $standard2->priority = 1;
        $standard2->criteria = json_encode(array(
            'identification' => 'Clear identification of object or topic being described',
            'description' => 'Detailed sensory descriptions (sight, sound, smell, taste, touch)',
            'organization' => 'Logical arrangement of descriptive details',
            'vocabulary' => 'Appropriate and varied descriptive vocabulary',
            'mechanics' => 'Grammar, spelling, and punctuation'
        ));
        $standard2->grade_scale = json_encode(array(
            '85-100' => 'Excellent - Rich details with vivid sensory language',
            '70-84' => 'Good - Clear descriptions with adequate detail',
            '55-69' => 'Satisfactory - Basic descriptions with some sensory elements',
            '40-54' => 'Fair - Limited descriptions with weak sensory appeal',
            '0-39' => 'Poor - Minimal or unclear descriptions'
        ));
        $standard2->rubric = 'Identification (20%): The object, person, place, or animal being described must be clearly identified in the opening. Identification should establish context and perspective.

Description Quality (30%): Use of specific, sensory details that appeal to the reader\'s senses. Descriptions should be concrete rather than abstract. Include details about appearance, texture, smell, sound, or other sensory qualities.

Organization (20%): Details should be arranged in a logical order (spatial, temporal, or by importance). Transitions between details should be clear and natural.

Vocabulary (20%): Use of vivid, descriptive words and phrases. Avoid repetitive language. Demonstrate mastery of adjectives and descriptive verbs.

Technical Proficiency (10%): Correct grammar, spelling, and punctuation. Sentences should be varied in structure and length.';
        $standard2->author = 'Kementerian Pendidikan dan Kebudayaan';
        $standard2->reference_url = 'https://www.kemdikbud.go.id/kurikulum-2013';
        $standard2->kemendikbud_subject = 'Indonesian Language Arts (Bahasa Indonesia)';
        $standard2->kemendikbud_level = 'JHS (Junior High School)';
        $standard2->enabled = 1;
        $standard2->timecreated = $now;
        $standard2->timemodified = $now;
        
        if (!$DB->record_exists('local_essay_ai_standard', array('assignmentid' => 0, 'standard_name' => 'Descriptive Essay Text Structure (JHS)'))) {
            $DB->insert_record('local_essay_ai_standard', $standard2);
        }
        
        upgrade_plugin_savepoint(true, 2026040501, 'local', 'essay_ai_grader');
    }

    // Add question context and criteria configuration fields (v2026.04.10)
    if ($oldversion < 2026041001) {
        $table = new xmldb_table('local_essay_ai_config');

        // Add question_text field
        $field = new xmldb_field('question_text', XMLDB_TYPE_TEXT, null, null, false, false, null, 'rubricguide');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add expected_answer field
        $field = new xmldb_field('expected_answer', XMLDB_TYPE_TEXT, null, null, false, false, null, 'question_text');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add criteria_config field for per-criterion weights and configuration
        $field = new xmldb_field('criteria_config', XMLDB_TYPE_TEXT, null, null, false, false, null, 'expected_answer');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026041001, 'local', 'essay_ai_grader');
    }

    // Add approval workflow fields (v2026.04.11)
    if ($oldversion < 2026041101) {
        $table = new xmldb_table('local_essay_ai_assess');

        // Add approval_time field
        $field = new xmldb_field('approval_time', XMLDB_TYPE_INTEGER, '10', null, false, false, null, 'timemodified');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add approver_id field  
        $field = new xmldb_field('approver_id', XMLDB_TYPE_INTEGER, '10', null, false, false, null, 'approval_time');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add override_grade field
        $field = new xmldb_field('override_grade', XMLDB_TYPE_NUMBER, '10,2', null, false, false, null, 'approver_id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026041101, 'local', 'essay_ai_grader');
    }

    // Rename assignid field to assignmentid for consistency (v2026.04.11)
    if ($oldversion < 2026041102) {
        $table = new xmldb_table('local_essay_ai_config');
        
        // Drop old unique key if exists
        $index = new xmldb_index('assignid', XMLDB_INDEX_UNIQUE, array('assignid'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Rename field if old field exists and new one doesn't
        $old_field = new xmldb_field('assignid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $new_field = new xmldb_field('assignmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        
        if ($dbman->field_exists($table, $old_field) && !$dbman->field_exists($table, $new_field)) {
            $dbman->rename_field($table, $old_field, 'assignmentid');
        }

        // Add new unique key on assignmentid
        $new_index = new xmldb_index('assignmentid', XMLDB_INDEX_UNIQUE, array('assignmentid'));
        if (!$dbman->index_exists($table, $new_index)) {
            $dbman->add_index($table, $new_index);
        }

        upgrade_plugin_savepoint(true, 2026041102, 'local', 'essay_ai_grader');
    }

    return true;
}

