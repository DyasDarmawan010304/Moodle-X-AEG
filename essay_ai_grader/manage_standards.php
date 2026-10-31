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
// along with Moodle.  If not, see <http://www.gnu.org/copyleft/gpl.html>.

/**
 * Manage Assessment Standards
 *
 * Allows administrators to view, add, edit, and delete assessment standards
 * for essay evaluation. Standards include Kemendikbud, regional, and custom sources.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/local/essay_ai_grader/classes/assessment_standard.php');

// Check capabilities
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url('/local/essay_ai_grader/manage_standards.php');
$PAGE->set_title(get_string('manage_standards', 'local_essay_ai_grader'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_standards', 'local_essay_ai_grader'));

// Get all standards
$allstandards = $DB->get_records('local_essay_ai_standard', array(), 'source ASC, priority ASC');

?>
<div class="local-essay-ai-standards">
    
    <div class="standards-section">
        <h3><?php echo get_string('official_standards', 'local_essay_ai_grader'); ?></h3>
        <p class="help-text"><?php echo get_string('standards_help_text', 'local_essay_ai_grader'); ?></p>
        
        <?php if ($allstandards) : ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php echo get_string('standard_name'); ?></th>
                        <th><?php echo get_string('source'); ?></th>
                        <th><?php echo get_string('education_level'); ?></th>
                        <th><?php echo get_string('author'); ?></th>
                        <th><?php echo get_string('status'); ?></th>
                        <th><?php echo get_string('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allstandards as $standard) : ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($standard->standard_name); ?></strong>
                                <?php if ($standard->description) : ?>
                                    <div class="small text-muted">
                                        <?php echo htmlspecialchars($standard->description); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo get_string('source_' . htmlspecialchars($standard->source), 'local_essay_ai_grader'); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($standard->kemendikbud_level ?? '—'); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($standard->author ?? '—'); ?>
                            </td>
                            <td>
                                <?php if ($standard->enabled) : ?>
                                    <span class="badge badge-success"><?php echo get_string('enabled'); ?></span>
                                <?php else : ?>
                                    <span class="badge badge-danger"><?php echo get_string('disabled'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?" class="btn btn-sm btn-secondary"><?php echo get_string('view'); ?></a>
                                <a href="?" class="btn btn-sm btn-primary"><?php echo get_string('edit'); ?></a>
                                <a href="?" class="btn btn-sm btn-danger"><?php echo get_string('delete'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="alert alert-info">
                <p><?php echo get_string('no_standards_found', 'local_essay_ai_grader'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <hr>
    
    <div class="add-standard-section mt-4">
        <h4><?php echo get_string('add_new_standard', 'local_essay_ai_grader'); ?></h4>
        <p><?php echo get_string('add_standard_help', 'local_essay_ai_grader'); ?></p>
        
        <form method="post" action="?" class="form">
            <div class="form-group">
                <label for="standardname"><?php echo get_string('standard_name'); ?></label>
                <input type="text" id="standardname" name="standard_name" class="form-control" 
                       placeholder="<?php echo get_string('standard_name_placeholder', 'local_essay_ai_grader'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="source"><?php echo get_string('source'); ?></label>
                <select id="source" name="source" class="form-control" required>
                    <option value=""><?php echo get_string('choose'); ?></option>
                    <option value="kemendikbud"><?php echo get_string('source_kemendikbud', 'local_essay_ai_grader'); ?></option>
                    <option value="kabupaten"><?php echo get_string('source_kabupaten', 'local_essay_ai_grader'); ?></option>
                    <option value="kota"><?php echo get_string('source_kota', 'local_essay_ai_grader'); ?></option>
                    <option value="artikel"><?php echo get_string('source_artikel', 'local_essay_ai_grader'); ?></option>
                    <option value="jurnal"><?php echo get_string('source_jurnal', 'local_essay_ai_grader'); ?></option>
                    <option value="custom"><?php echo get_string('source_custom', 'local_essay_ai_grader'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description"><?php echo get_string('description'); ?></label>
                <textarea id="description" name="description" class="form-control" rows="3" 
                          placeholder="<?php echo get_string('optional'); ?>"></textarea>
            </div>
            
            <div class="form-group">
                <label for="author"><?php echo get_string('author'); ?></label>
                <input type="text" id="author" name="author" class="form-control" 
                       placeholder="e.g., Kementerian Pendidikan dan Kebudayaan">
            </div>
            
            <div class="form-group">
                <label for="rubric"><?php echo get_string('rubric_guide', 'local_essay_ai_grader'); ?></label>
                <textarea id="rubric" name="rubric" class="form-control" rows="8" 
                          placeholder="<?php echo get_string('rubric_placeholder', 'local_essay_ai_grader'); ?>" required></textarea>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="enabled" value="1" checked>
                    <?php echo get_string('enable_standard', 'local_essay_ai_grader'); ?>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary" name="action" value="add">
                <?php echo get_string('add_standard', 'local_essay_ai_grader'); ?>
            </button>
            <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=local_essay_ai_grader" class="btn btn-secondary">
                <?php echo get_string('cancel'); ?>
            </a>
        </form>
    </div>
    
</div>

<style>
.local-essay-ai-standards {
    max-width: 1200px;
}

.help-text {
    font-size: 0.95em;
    color: #666;
    margin-bottom: 20px;
}

.standards-section {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.add-standard-section {
    background-color: #e7f3ff;
    padding: 20px;
    border-radius: 4px;
    border-left: 4px solid #0066cc;
}

table {
    font-size: 0.95em;
}

.badge {
    padding: 4px 8px;
    font-size: 0.85em;
}
</style>

<?php
echo $OUTPUT->footer();
