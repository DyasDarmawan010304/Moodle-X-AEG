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

namespace local_essay_ai_grader;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class to integrate custom criteria fields with assignment form.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class criteria_config_form {
    /**
     * Get the assignment foreign-key field used by the current DB schema.
     *
     * @return string
     */
    public static function get_config_assignment_field() {
        global $DB;

        $columns = $DB->get_columns('local_essay_ai_config');
        if (isset($columns['assignmentid'])) {
            return 'assignmentid';
        }
        if (isset($columns['assignid'])) {
            return 'assignid';
        }

        return 'assignmentid';
    }

    /**
     * Fetch config for an assignment across old/new schema variants.
     *
     * @param int $assignmentid
     * @return \stdClass|false
     */
    public static function get_config_for_assignment($assignmentid) {
        global $DB;

        $field = self::get_config_assignment_field();
        return $DB->get_record('local_essay_ai_config', array($field => (int)$assignmentid));
    }
    
    /**
     * Add custom criteria fields to assignment edit form.
     * Called from lib.php hook when assignment form is displayed.
     *
     * @param object $mform The Moodle form object
     * @param object $assignment The assignment object
     */
    public static function add_criteria_fields_to_form(&$mform, $assignment) {
        global $DB, $PAGE;
        
        error_log('DEBUG: add_criteria_fields_to_form called for assignment ' . $assignment->id);
        
        // Get current config if exists
        $config = self::get_config_for_assignment((int)$assignment->id);
        error_log('DEBUG: Config found: ' . ($config ? 'yes' : 'no'));
        
        // ===== ESSAY AI GRADER SECTION HEADER =====
        $mform->addElement('header', 'essay_ai_grader_header', 'Penilaian AI Essay');
        error_log('DEBUG: Header element added');
        
        // ===== 1. ENABLE ESSAY AI GRADER =====
        $mform->addElement('advcheckbox', 'essay_ai_enabled', 
            get_string('enable_ai_grading', 'local_essay_ai_grader'));
        $mform->setType('essay_ai_enabled', PARAM_INT);
        $defaultenabled = ($config && isset($config->enabled)) ? $config->enabled : 1;
        $mform->setDefault('essay_ai_enabled', $defaultenabled);
        error_log('DEBUG: Enable checkbox added, default=' . $defaultenabled);
        
        // ===== 2. MAXIMUM SCORE =====
        $mform->addElement('text', 'essay_ai_maxscore', 
            get_string('max_score_label', 'local_essay_ai_grader'), 
            array('size' => '5'));
        $mform->setType('essay_ai_maxscore', PARAM_INT);
        $mform->setDefault('essay_ai_maxscore', $config ? ($config->maxscore ?: 100) : 100);
        $mform->disabledIf('essay_ai_maxscore', 'essay_ai_enabled', 'notchecked');
        error_log('DEBUG: Max score field added');
        
        // ===== 3. EVALUATION CRITERIA (6 ASPECTS) WITH WEIGHTS =====
        $mform->addElement('static', 'essay_ai_criteria_label', '',
            '<h4 style="margin-top: 20px; margin-bottom: 10px;">' . get_string('evaluation_criteria', 'local_essay_ai_grader') . '</h4>');
        
        // Decode criteria if config exists
        $criteria_config = array();
        if ($config && !empty($config->criteria_config)) {
            $criteria_config = json_decode($config->criteria_config, true) ?? array();
            error_log('DEBUG add_criteria_fields: Loaded criteria_config from DB - count=' . count($criteria_config));
            foreach ($criteria_config as $cid => $cfg) {
                error_log('  - ' . $cid . ': enabled=' . ($cfg['enabled'] ? '1' : '0') . ', weight=' . $cfg['weight']);
            }
        } else {
            error_log('DEBUG add_criteria_fields: No saved criteria_config - will use defaults (all enabled)');
        }
        
        // Get the 6 criteria
        $all_criteria = \local_essay_ai_grader\default_criteria::get_all_criteria();
        
        // Load CSS for table styling
        $PAGE->requires->css('/local/essay_ai_grader/styles/form-criteria.css');
        
        // Build table HTML for saved values
        $html = '<table class="criteria-form-table" id="essay_ai_criteria_table">';
        $html .= '<thead><tr>';
        $html .= '<th class="criteria-enable">Enable</th>';
        $html .= '<th>Criterion & Description</th>';
        $html .= '<th class="criteria-weight">Bobot %</th>';
        $html .= '</tr></thead><tbody>';
        
        // Table rows
        foreach ($all_criteria as $criterion_id => $criterion) {
            $checkboxname = 'essay_ai_crit_' . $criterion_id;
            $weightname = 'essay_ai_weight_' . $criterion_id;
            
            // Check if enabled - default to true if no config exists
            // If config exists, use the explicit enabled status (could be true or false)
            if (empty($criteria_config)) {
                // No config: all default to true
                $is_enabled = true;
                $weight = $criterion['weight'];
            } else {
                // Config exists: use explicit values
                $is_enabled = isset($criteria_config[$criterion_id]) ? $criteria_config[$criterion_id]['enabled'] : false;
                $weight = isset($criteria_config[$criterion_id]) ? ($criteria_config[$criterion_id]['weight'] ?? $criteria_config[$criterion_id]['score'] ?? $criterion['weight']) : $criterion['weight'];
            }
            
            // REGISTER HIDDEN FORM ELEMENTS ONLY
            $mform->addElement('hidden', $checkboxname, $is_enabled ? '1' : '0');
            $mform->setType($checkboxname, PARAM_INT);
            $mform->addElement('hidden', $weightname, $weight);
            $mform->setType($weightname, PARAM_INT);
            
            $checked = $is_enabled ? 'checked="checked"' : '';
            
            // Build HTML table with visible inputs only in the table
            $html .= '<tr class="criteria-row">';
            $html .= '<td class="criteria-enable">';
            $html .= '<input type="checkbox" name="' . htmlspecialchars($checkboxname . '_visible') . '" value="1" ' . $checked . ' class="criterion-checkbox" data-criterion="' . htmlspecialchars($criterion_id) . '">';
            $html .= '</td>';
            $html .= '<td class="criteria-info">';
            $html .= '<div class="criteria-name">' . htmlspecialchars($criterion['name_id']) . '</div>';
            $html .= '<div class="criteria-description">' . htmlspecialchars($criterion['description_id']) . '</div>';
            $html .= '</td>';
            $html .= '<td class="criteria-weight">';
            $html .= '<input type="text" name="' . htmlspecialchars($weightname . '_visible') . '" value="' . htmlspecialchars($weight) . '" class="criteria-weight-input" size="4" data-criterion="' . htmlspecialchars($criterion_id) . '">';
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '<script type="text/javascript">';
        $html .= '(function() {';
        $html .= 'if (document.readyState === "loading") {';
        $html .= 'document.addEventListener("DOMContentLoaded", function() {';
        $html .= 'var checkboxes = document.querySelectorAll(".criterion-checkbox");';
        $html .= 'for (var i = 0; i < checkboxes.length; i++) {';
        $html .= 'var checkbox = checkboxes[i];';
        $html .= 'var criterion = checkbox.getAttribute("data-criterion");';
        $html .= 'var hidden = document.querySelector("input[name=essay_ai_crit_" + criterion + "]");';
        $html .= 'if (hidden) { hidden.value = checkbox.checked ? "1" : "0"; }';
        $html .= 'checkbox.addEventListener("change", function() {';
        $html .= 'if (hidden) { hidden.value = this.checked ? "1" : "0"; }';
        $html .= '});';
        $html .= '}';
        $html .= 'var inputs = document.querySelectorAll(".criteria-weight-input");';
        $html .= 'for (var j = 0; j < inputs.length; j++) {';
        $html .= 'var input = inputs[j];';
        $html .= 'var criterion = input.getAttribute("data-criterion");';
        $html .= 'var hidden = document.querySelector("input[name=essay_ai_weight_" + criterion + "]");';
        $html .= 'if (hidden) { hidden.value = input.value; }';
        $html .= 'input.addEventListener("input", function() {';
        $html .= 'if (hidden) { hidden.value = this.value; }';
        $html .= '});';
        $html .= '}';
        $html .= '});';
        $html .= '} else {';
        $html .= 'var checkboxes = document.querySelectorAll(".criterion-checkbox");';
        $html .= 'for (var i = 0; i < checkboxes.length; i++) {';
        $html .= 'var checkbox = checkboxes[i];';
        $html .= 'var criterion = checkbox.getAttribute("data-criterion");';
        $html .= 'var hidden = document.querySelector("input[name=essay_ai_crit_" + criterion + "]");';
        $html .= 'if (hidden) { hidden.value = checkbox.checked ? "1" : "0"; }';
        $html .= 'checkbox.addEventListener("change", function() {';
        $html .= 'if (hidden) { hidden.value = this.checked ? "1" : "0"; }';
        $html .= '});';
        $html .= '}';
        $html .= 'var inputs = document.querySelectorAll(".criteria-weight-input");';
        $html .= 'for (var j = 0; j < inputs.length; j++) {';
        $html .= 'var input = inputs[j];';
        $html .= 'var criterion = input.getAttribute("data-criterion");';
        $html .= 'var hidden = document.querySelector("input[name=essay_ai_weight_" + criterion + "]");';
        $html .= 'if (hidden) { hidden.value = input.value; }';
        $html .= 'input.addEventListener("input", function() {';
        $html .= 'if (hidden) { hidden.value = this.value; }';
        $html .= '});';
        $html .= '}';
        $html .= '}';
        $html .= '})();';
        $html .= '</script>';
        $mform->addElement('html', $html);
        
        // ===== 4. ANSWER KEY / EXPECTED ANSWER =====
        $mform->addElement('textarea', 'essay_ai_answerkey', 
            get_string('answer_key_label', 'local_essay_ai_grader'),
            array('rows' => 4, 'cols' => 60));
        $mform->setType('essay_ai_answerkey', PARAM_RAW);
        // TODO: Add language strings for help buttons
        // $mform->addHelpButton('essay_ai_answerkey', 'answer_key_help', 'local_essay_ai_grader');
        // ALWAYS set default from config, even if empty - ensures it displays saved value
        $mform->setDefault('essay_ai_answerkey', $config ? ($config->answerkey ?? '') : '');
        $mform->disabledIf('essay_ai_answerkey', 'essay_ai_enabled', 'notchecked');
        
        // ===== 5. RUBRIC GUIDE =====
        $mform->addElement('textarea', 'essay_ai_rubricguide', 
            get_string('rubric_guide_label', 'local_essay_ai_grader'),
            array('rows' => 4, 'cols' => 60));
        $mform->setType('essay_ai_rubricguide', PARAM_RAW);
        // TODO: Add language strings for help buttons
        // $mform->addHelpButton('essay_ai_rubricguide', 'rubric_guide_help', 'local_essay_ai_grader');
        // ALWAYS set default from config, even if empty - ensures it displays saved value
        $mform->setDefault('essay_ai_rubricguide', $config ? ($config->rubricguide ?? '') : '');
        $mform->disabledIf('essay_ai_rubricguide', 'essay_ai_enabled', 'notchecked');
        
        // ===== 6. PROMPT TEMPLATE (CUSTOM INSTRUCTIONS) =====
        $mform->addElement('textarea', 'essay_ai_prompttemplate', 
            'Custom Prompt Instructions',
            array('rows' => 4, 'cols' => 60));
        $mform->setType('essay_ai_prompttemplate', PARAM_RAW);
        // ALWAYS set default from config
        $mform->setDefault('essay_ai_prompttemplate', $config ? ($config->prompttemplate ?? '') : '');
        $mform->disabledIf('essay_ai_prompttemplate', 'essay_ai_enabled', 'notchecked');
    }
    
    /**
     * Add 5-aspect rubric fields to assignment form (for SMK Indonesian essay).
     * Called when using the new 5-aspect rubric.
     *
     * @param object $mform The Moodle form object
     * @param object $assignment The assignment object
     */
    public static function add_five_aspects_rubric_fields_to_form(&$mform, $assignment) {
        global $DB, $PAGE;
        
        error_log('DEBUG: add_five_aspects_rubric_fields_to_form called for assignment ' . $assignment->id);
        
        // Get current config if exists
        $config = self::get_config_for_assignment((int)$assignment->id);
        error_log('DEBUG: Config found: ' . ($config ? 'yes' : 'no'));
        
        // ===== HEADER =====
        $mform->addElement('header', 'essay_ai_five_aspects_header', 'Penilaian AI Esai');
        error_log('DEBUG: Header element added');
        
        // ===== ENABLE =====
        $mform->addElement('advcheckbox', 'essay_ai_five_aspects_enabled', 
            'Gunakan Rubrik 5 Aspek');
        $mform->setType('essay_ai_five_aspects_enabled', PARAM_INT);
        // Check for new key names (relevansi_ketepatan_isi, kelengkapan_konsep, etc.)
        $defaultenabled = ($config && !empty($config->criteria_config) && 
                          (strpos($config->criteria_config, 'relevansi_ketepatan_isi') !== false ||
                           strpos($config->criteria_config, 'kelengkapan_konsep') !== false)) ? 1 : 1; // Default to 1 for new assignments
        $mform->setDefault('essay_ai_five_aspects_enabled', $defaultenabled);
        error_log('DEBUG: Enable checkbox added, default=' . $defaultenabled);
        
        // ===== MAX SCORE =====
        $mform->addElement('text', 'essay_ai_five_aspects_maxscore', 
            'Skor Maksimal Total', 
            array('size' => '5'));
        $mform->setType('essay_ai_five_aspects_maxscore', PARAM_INT);
        $mform->setDefault('essay_ai_five_aspects_maxscore', ($config && !empty($config->maxscore)) ? (int)$config->maxscore : 30);
        $mform->disabledIf('essay_ai_five_aspects_maxscore', 'essay_ai_five_aspects_enabled', 'notchecked');
        
        // ===== QUESTION TEXT =====
        $mform->addElement('textarea', 'essay_ai_question_text', 
            'Soal / Pertanyaan',
            array('rows' => 3, 'cols' => 60));
        $mform->setType('essay_ai_question_text', PARAM_RAW);
        if ($config && !empty($config->question_text)) {
            $mform->setDefault('essay_ai_question_text', $config->question_text);
        }
        $mform->disabledIf('essay_ai_question_text', 'essay_ai_five_aspects_enabled', 'notchecked');
        
        // ===== EXPECTED ANSWER / ANSWER KEY =====
        $mform->addElement('textarea', 'essay_ai_expected_answer', 
            'Kunci Jawaban / Jawaban yang Diharapkan',
            array('rows' => 3, 'cols' => 60));
        $mform->setType('essay_ai_expected_answer', PARAM_RAW);
        if ($config && !empty($config->expected_answer)) {
            $mform->setDefault('essay_ai_expected_answer', $config->expected_answer);
        }
        $mform->disabledIf('essay_ai_expected_answer', 'essay_ai_five_aspects_enabled', 'notchecked');
        
        // ===== 5 ASPECTS WITH WEIGHTS =====
        $aspects = \local_essay_ai_grader\default_criteria::get_five_aspects_rubric();
        
        // Parse existing config
        $aspect_config = array();
        if ($config && !empty($config->criteria_config) && 
            strpos($config->criteria_config, 'relevansi_ketepatan') !== false) {
            $aspect_config = json_decode($config->criteria_config, true) ?? array();
        } else {
            $aspect_config = \local_essay_ai_grader\default_criteria::get_five_aspects_default_config();
        }
        
        // Build HTML table
        $html = '<div style="margin-top: 20px;">';
        $html .= '<h5>Aspek Penilaian dan Bobot</h5>';
        $html .= '<table class="five-aspects-table" style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
        $html .= '<thead><tr style="background-color: #f5f5f5; border-bottom: 2px solid #ccc;">';
        $html .= '<th style="text-align: left; padding: 8px; width: 40px;">Enable</th>';
        $html .= '<th style="text-align: left; padding: 8px; flex: 1;">Aspek Penilaian</th>';
        $html .= '<th style="text-align: center; padding: 8px; width: 120px;">Bobot (poin)</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($aspects as $aspect_id => $aspect) {
            $checkbox_name = 'essay_ai_five_aspect_' . $aspect_id . '_enabled';
            $weight_name = 'essay_ai_five_aspect_' . $aspect_id . '_weight';
            $score_levels_name = 'essay_ai_five_aspect_' . $aspect_id . '_score_levels';
            
            $is_enabled = isset($aspect_config[$aspect_id]) ? $aspect_config[$aspect_id]['enabled'] : true;
            $weight = isset($aspect_config[$aspect_id]) ? $aspect_config[$aspect_id]['weight'] : $aspect['max_points'];
            $score_levels = isset($aspect_config[$aspect_id]['score_levels']) ? $aspect_config[$aspect_id]['score_levels'] : '';
            
            // Register hidden form elements
            $mform->addElement('hidden', $checkbox_name, $is_enabled ? '1' : '0');
            $mform->setType($checkbox_name, PARAM_INT);
            $mform->addElement('hidden', $weight_name, $weight);
            $mform->setType($weight_name, PARAM_INT);
            $mform->addElement('hidden', $score_levels_name, $score_levels);
            $mform->setType($score_levels_name, PARAM_RAW);
            
            $checked = $is_enabled ? 'checked="checked"' : '';
            
            $html .= '<tr style="border-bottom: 1px solid #e0e0e0;">';
            $html .= '<td style="text-align: center; padding: 8px;">';
            $html .= '<input type="checkbox" name="' . htmlspecialchars($checkbox_name . '_visible') . '" ' 
                     . $checked . ' class="aspect-checkbox" data-aspect="' . htmlspecialchars($aspect_id) . '">';
            $html .= '</td>';
            $html .= '<td style="padding: 8px;">';
            $html .= '<strong>' . htmlspecialchars($aspect['name']) . '</strong>';
            $html .= '<br><small style="color: #666;">' . htmlspecialchars($aspect['description']) . '</small>';
            $html .= '</td>';
            $html .= '<td style="text-align: center; padding: 8px;">';
            $html .= '<input type="number" name="' . htmlspecialchars($weight_name . '_visible') . '" '
                     . 'value="' . htmlspecialchars($weight) . '" min="0" '
                     . 'class="aspect-weight-input" data-aspect="' . htmlspecialchars($aspect_id) . '" '
                     . 'style="width: 60px; text-align: center;">';
            $html .= '<small style="display: block; color: #666; margin-top: 4px;">Default: ' . htmlspecialchars($aspect['max_points']) . ' poin</small>';
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';

        // Score level descriptions for each aspect
        $html .= '<div style="margin-top: 20px;">';
        $html .= '<h5>Deskripsi Skor Per Aspek</h5>';
        foreach ($aspects as $aspect_id => $aspect) {
            $score_levels_name = 'essay_ai_five_aspect_' . $aspect_id . '_score_levels';
            $score_levels = isset($aspect_config[$aspect_id]['score_levels']) ? $aspect_config[$aspect_id]['score_levels'] : '';

            $html .= '<div style="margin-bottom: 16px; padding: 12px; border: 1px solid #ddd; border-radius: 6px;">';
            $html .= '<label for="' . htmlspecialchars($score_levels_name) . '" style="font-weight: bold;">' . htmlspecialchars($aspect['name']) . '</label>';
            $html .= '<textarea id="' . htmlspecialchars($score_levels_name) . '" name="' . htmlspecialchars($score_levels_name . '_visible') . '" rows="4" cols="80" style="width: 100%; margin-top: 8px;" class="aspect-score-levels-input" data-aspect="' . htmlspecialchars($aspect_id) . '">' . htmlspecialchars($score_levels) . '</textarea>';
            $html .= '<div style="font-size: 0.9em; color: #555; margin-top: 6px;">Gunakan format: 0: ..., 2: ..., 4: ... atau berikan deskripsi untuk rentang skor sesuai bobot aspek.</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        // Total weight validation
        $html .= '<div style="margin-top: 15px; padding: 10px; background-color: #f0f8ff; border: 1px solid #0088cc; border-radius: 4px;">';
        $html .= '<strong>Total Bobot: <span id="total-weight">30</span> poin</strong>';
        $html .= '<br><small style="color: #666;">Harus sesuai dengan Skor Maksimal Total yang ditetapkan di atas</small>';
        $html .= '<div id="weight-warning" style="color: #d32f2f; margin-top: 8px; display: none; font-weight: bold;">';
        $html .= '⚠️ Peringatan: Total Bobot tidak sesuai dengan Skor Maksimal!';
        $html .= '</div>';
        $html .= '</div>';
        
        // JavaScript untuk sync dan validasi - SAFE ESCAPED VERSION
        $html .= '<script type="text/javascript">';
        $html .= '(function() {';
        
        // SYNC function - update hidden fields from visible inputs
        $html .= 'function syncVisibleToHidden() {';
        $html .= 'console.log("DEBUG: syncVisibleToHidden called");';
        $html .= 'var synced = 0;';
        $html .= 'var checkboxes = document.querySelectorAll(".aspect-checkbox");';
        $html .= 'for (var i = 0; i < checkboxes.length; i++) {';
        $html .= 'var visibleCb = checkboxes[i];';
        $html .= 'var aspect = visibleCb.getAttribute("data-aspect");';
        $html .= 'var hiddenField = document.querySelector("input[name=essay_ai_five_aspect_" + aspect + "_enabled]");';
        $html .= 'if (hiddenField) { hiddenField.value = visibleCb.checked ? "1" : "0"; synced++; }';
        $html .= '}';
        $html .= 'var weights = document.querySelectorAll(".aspect-weight-input");';
        $html .= 'for (var i = 0; i < weights.length; i++) {';
        $html .= 'var visibleInput = weights[i];';
        $html .= 'var aspect = visibleInput.getAttribute("data-aspect");';
        $html .= 'var hiddenField = document.querySelector("input[name=essay_ai_five_aspect_" + aspect + "_weight]");';
        $html .= 'if (hiddenField) { hiddenField.value = visibleInput.value; synced++; }';
        $html .= '}';
        $html .= 'var scorelevels = document.querySelectorAll(".aspect-score-levels-input");';
        $html .= 'for (var i = 0; i < scorelevels.length; i++) {';
        $html .= 'var visibleInput = scorelevels[i];';
        $html .= 'var aspect = visibleInput.getAttribute("data-aspect");';
        $html .= 'var hiddenField = document.querySelector("input[name=essay_ai_five_aspect_" + aspect + "_score_levels]");';
        $html .= 'if (hiddenField) { hiddenField.value = visibleInput.value; synced++; }';
        $html .= '}';
        $html .= 'console.log("DEBUG: syncVisibleToHidden synced " + synced + " fields");';
        $html .= '}';
        
        // WEIGHT UPDATE function
        $html .= 'function updateTotalWeight() {';
        $html .= 'var total = 0;';
        $html .= 'var inputs = document.querySelectorAll(".aspect-weight-input");';
        $html .= 'for (var i = 0; i < inputs.length; i++) {';
        $html .= 'var input = inputs[i];';
        $html .= 'var aspect = input.getAttribute("data-aspect");';
        $html .= 'var checkbox = document.querySelector(".aspect-checkbox[data-aspect=\"" + aspect + "\"]");';
        $html .= 'if (!checkbox || !checkbox.checked) { continue; }';
        $html .= 'var val = parseInt(input.value);';
        $html .= 'if (!isNaN(val)) { total += val; }';
        $html .= '}';
        $html .= 'var elem = document.getElementById("total-weight");';
        $html .= 'if (elem) { elem.textContent = total; }';
        $html .= 'var maxInput = document.querySelector("input[name=essay_ai_five_aspects_maxscore]");';
        $html .= 'var maxScore = maxInput ? parseInt(maxInput.value) : 30;';
        $html .= 'if (isNaN(maxScore)) { maxScore = 30; }';
        $html .= 'var warning = document.getElementById("weight-warning");';
        $html .= 'if (warning) {';
        $html .= 'warning.style.display = (total !== maxScore) ? "block" : "none";';
        $html .= '}';
        $html .= 'syncVisibleToHidden();'; // SYNC after updating
        $html .= '}';
        
        // FORM SUBMISSION hook to ensure sync happens
        $html .= 'function hookFormSubmit() {';
        $html .= 'var form = document.querySelector("form");';
        $html .= 'if (form) {';
        $html .= 'form.addEventListener("submit", function() {';
        $html .= 'console.log("DEBUG: Form submit detected, syncing data");';
        $html .= 'syncVisibleToHidden();'; // Final sync before submit
        $html .= '}, false);';
        $html .= '}';
        $html .= '}';
        
        $html .= 'if (document.readyState === "loading") {';
        $html .= 'document.addEventListener("DOMContentLoaded", function() {';
        $html .= 'console.log("DEBUG: DOMContentLoaded fired, initializing");';
        $html .= 'hookFormSubmit();';
        $html .= 'var checkboxes = document.querySelectorAll(".aspect-checkbox");';
        $html .= 'for (var i = 0; i < checkboxes.length; i++) {';
        $html .= 'checkboxes[i].addEventListener("change", function() { updateTotalWeight(); });';
        $html .= '}';
        $html .= 'var weights = document.querySelectorAll(".aspect-weight-input");';
        $html .= 'for (var i = 0; i < weights.length; i++) {';
        $html .= 'weights[i].addEventListener("input", function() { updateTotalWeight(); });';
        $html .= '}';
        $html .= 'var scorelevels = document.querySelectorAll(".aspect-score-levels-input");';
        $html .= 'for (var i = 0; i < scorelevels.length; i++) {';
        $html .= 'scorelevels[i].addEventListener("input", function() { syncVisibleToHidden(); });';
        $html .= '}';
        $html .= 'var maxInput = document.querySelector("input[name=essay_ai_five_aspects_maxscore]");';
        $html .= 'if (maxInput) { maxInput.addEventListener("input", updateTotalWeight); }';
        $html .= 'updateTotalWeight();';
        $html .= 'syncVisibleToHidden();';
        $html .= '});';
        $html .= '} else {';
        $html .= 'console.log("DEBUG: Document already loaded, initializing immediately");';
        $html .= 'hookFormSubmit();';
        $html .= 'var checkboxes = document.querySelectorAll(".aspect-checkbox");';
        $html .= 'for (var i = 0; i < checkboxes.length; i++) {';
        $html .= 'checkboxes[i].addEventListener("change", function() { updateTotalWeight(); });';
        $html .= '}';
        $html .= 'var weights = document.querySelectorAll(".aspect-weight-input");';
        $html .= 'for (var i = 0; i < weights.length; i++) {';
        $html .= 'weights[i].addEventListener("input", function() { updateTotalWeight(); });';
        $html .= '}';
        $html .= 'var scorelevels = document.querySelectorAll(".aspect-score-levels-input");';
        $html .= 'for (var i = 0; i < scorelevels.length; i++) {';
        $html .= 'scorelevels[i].addEventListener("input", function() { syncVisibleToHidden(); });';
        $html .= '}';
        $html .= 'var maxInput = document.querySelector("input[name=essay_ai_five_aspects_maxscore]");';
        $html .= 'if (maxInput) { maxInput.addEventListener("input", updateTotalWeight); }';
        $html .= 'updateTotalWeight();';
        $html .= 'syncVisibleToHidden();';
        $html .= '}';
        
        $html .= '})();';
        $html .= '</script>';
        $html .= '</div>'; // Close outer div
        
        $mform->addElement('html', $html);
    }
    
    /**
     * Save 5-aspect rubric data from form.
     *
     * @param object $assignment The assignment object
     * @param object $data The form data
     * @return bool Success
     */
    public static function save_five_aspects_rubric_from_form($assignment, $data) {
        global $DB;
        
        if (empty($assignment->id)) {
            return false;
        }
        
        $assignid = (int)$assignment->id;
        $field = self::get_config_assignment_field();
        $config = self::get_config_for_assignment($assignid);

        if (!$config) {
            $config = new \stdClass();
            $config->{$field} = $assignid;
            $config->timecreated = time();
        }
        
        $config->timemodified = time();
        
        // DEBUG: Log what data we received
        error_log('DEBUG save_five_aspects_rubric_from_form: data keys = ' . implode(', ', array_keys((array)$data)));
        error_log('DEBUG essay_ai_five_aspects_enabled = ' . (!empty($data->essay_ai_five_aspects_enabled) ? $data->essay_ai_five_aspects_enabled : 'EMPTY'));
        
        // Check if 5-aspect rubric is enabled
        if (!empty($data->essay_ai_five_aspects_enabled)) {
            $config->enabled = 1;
            $config->maxscore = !empty($data->essay_ai_five_aspects_maxscore) ? (int)$data->essay_ai_five_aspects_maxscore : 30;
            
            // Build criteria config from 5 aspects
            $aspects = \local_essay_ai_grader\default_criteria::get_five_aspects_rubric();
            $criteria_config = array();
            
            foreach ($aspects as $aspect_id => $aspect) {
                $checkbox_key = 'essay_ai_five_aspect_' . $aspect_id . '_enabled';
                $weight_key = 'essay_ai_five_aspect_' . $aspect_id . '_weight';
                $score_levels_key = 'essay_ai_five_aspect_' . $aspect_id . '_score_levels';
                
                $is_enabled = !empty($data->$checkbox_key);
                $weight = !empty($data->$weight_key) ? (int)$data->$weight_key : $aspect['max_points'];
                $score_levels = !empty($data->$score_levels_key) ? trim($data->$score_levels_key) : '';
                
                $criteria_config[$aspect_id] = array(
                    'enabled' => $is_enabled,
                    'weight' => $weight,
                    'name' => $aspect['name'],
                    'description' => $aspect['description'],
                    'max_points' => $weight,
                    'score_levels' => $score_levels,
                );
            }

            $total_weight = 0;
            foreach ($criteria_config as $aspect_cfg) {
                if (!empty($aspect_cfg['enabled'])) {
                    $total_weight += $aspect_cfg['weight'];
                }
            }
            if ($total_weight !== $config->maxscore) {
                error_log('ERROR save_five_aspects_rubric_from_form: total enabled weight ' . $total_weight . ' does not equal maxscore ' . $config->maxscore);
                return false;
            }
            
            $config->criteria_config = json_encode($criteria_config);
            
            // Build legacy criteria format (enabled aspects only)
            $legacycriteria = array();
            foreach ($criteria_config as $aspect_id => $aspect_cfg) {
                if (!empty($aspect_cfg['enabled'])) {
                    $legacycriteria[$aspect_id] = 1;
                }
            }
            $config->criteria = json_encode($legacycriteria);
        } else {
            $config->enabled = 0;
            $config->criteria_config = null;
            $config->criteria = null;
        }
        
        // Save question and answer
        $config->question_text = !empty($data->essay_ai_question_text) ? $data->essay_ai_question_text : '';
        $config->expected_answer = !empty($data->essay_ai_expected_answer) ? $data->essay_ai_expected_answer : '';
        
        // Also save as answer key for compatibility
        $config->answerkey = $config->expected_answer;
        
        // Save to database
        if (isset($config->id)) {
            return $DB->update_record('local_essay_ai_config', $config);
        } else {
            return $DB->insert_record('local_essay_ai_config', $config) > 0;
        }
    }
    
    /**
     * Save 6-aspect rubric configuration from assignment form.
     * Handles the standard 6-evaluation-criteria rubric (Content, Organization, Grammar, etc.)
     *
     * @param object $assignment Assignment object with 'id' property
     * @param object $data Form data containing essay_ai_* fields
     * @return bool True if saved successfully, false otherwise
     */
    public static function save_criteria_from_form($assignment, $data) {
        global $DB;
        
        if (empty($assignment->id)) {
            return false;
        }
        
        $assignid = (int)$assignment->id;
        $field = self::get_config_assignment_field();
        $config = self::get_config_for_assignment($assignid);
        
        if (!$config) {
            $config = new \stdClass();
            $config->{$field} = $assignid;
            $config->timecreated = time();
        }
        
        $config->timemodified = time();
        
        // Set enabled status
        $config->enabled = !empty($data->essay_ai_enabled) ? 1 : 0;
        
        // Set max score
        $config->maxscore = !empty($data->essay_ai_maxscore) ? (int)$data->essay_ai_maxscore : 100;
        
        // Save answer key and rubric guide
        $config->answerkey = !empty($data->essay_ai_answerkey) ? $data->essay_ai_answerkey : '';
        $config->rubricguide = !empty($data->essay_ai_rubricguide) ? $data->essay_ai_rubricguide : '';
        $config->prompttemplate = !empty($data->essay_ai_prompttemplate) ? $data->essay_ai_prompttemplate : '';
        
        // Build 6-aspect criteria configuration
        $criteria_config = array();
        $criteria_enabled = array();
        $aspect_ids = array('content', 'organization', 'grammar', 'vocabulary', 'spelling', 'relevance');
        
        foreach ($aspect_ids as $criterion_id) {
            $checkbox_key = 'essay_ai_crit_' . $criterion_id;
            $weight_key = 'essay_ai_weight_' . $criterion_id;
            
            $is_enabled = !empty($data->$checkbox_key);
            $weight = !empty($data->$weight_key) ? (int)$data->$weight_key : 0;
            
            $criteria_config[$criterion_id] = array(
                'id' => $criterion_id,
                'enabled' => $is_enabled,
                'weight' => $weight,
            );
            
            if ($is_enabled) {
                $criteria_enabled[$criterion_id] = 1;
            }
        }
        
        // Save criteria configuration as JSON
        $config->criteria_config = json_encode($criteria_config);
        $config->criteria = json_encode($criteria_enabled);
        
        // Save to database
        if (isset($config->id)) {
            return $DB->update_record('local_essay_ai_config', $config);
        } else {
            return $DB->insert_record('local_essay_ai_config', $config) > 0;
        }
    }
    
    /**
     * Detect which rubric type is active for an assignment.
     * Returns 'five_aspects' or 'six_aspects' or null if not configured.
     *
     * @param int $assignmentid
     * @return string|null
     */
    public static function detect_rubric_type($assignmentid) {
        $config = self::get_config_for_assignment((int)$assignmentid);
        
        if (!$config) {
            return null;
        }
        
        // Check if criteria_config contains 5-aspect data
        if (!empty($config->criteria_config)) {
            $criteria = json_decode($config->criteria_config, true);
            // Check for NEW 5-aspect rubric indicators (relevansi_ketepatan_isi, kelengkapan_konsep, etc.)
            if (is_array($criteria) && (isset($criteria['relevansi_ketepatan_isi']) || 
                                        isset($criteria['kelengkapan_konsep']) ||
                                        isset($criteria['kesesuaian_konteks']) ||
                                        isset($criteria['ketepatan_bahasa']) ||
                                        isset($criteria['ejaan_tanda_baca']))) {
                return 'five_aspects';
            }
            // Check for OLD 5-aspect rubric (legacy key names)
            if (is_array($criteria) && isset($criteria['relevansi_ketepatan'])) {
                return 'five_aspects';
            }
            // Check for legacy 6-aspect indicators
            if (is_array($criteria) && (isset($criteria['content']) || 
                                       isset($criteria['organization']) ||
                                       isset($criteria['grammar']) ||
                                       isset($criteria['vocabulary']) ||
                                       isset($criteria['spelling']) ||
                                       isset($criteria['relevance']))) {
                return 'six_aspects';
            }
        }
        
        return null;
    }
}



