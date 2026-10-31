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
 * Settings for Essay AI Grader plugin.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create settings page.
    $settings = new admin_settingpage('local_essay_ai_grader', get_string('pluginname', 'local_essay_ai_grader'));

    // Provider selection.
    $providers = array(
        'gemini' => get_string('provider_gemini', 'local_essay_ai_grader'),
        'ollama' => get_string('provider_ollama', 'local_essay_ai_grader'),
    );

    $settings->add(new admin_setting_configselect(
        'local_essay_ai_grader/provider',
        get_string('provider', 'local_essay_ai_grader'),
        get_string('provider_desc', 'local_essay_ai_grader'),
        'gemini',
        $providers
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/auto_enable_new_assignments',
        get_string('auto_enable_new_assignments', 'local_essay_ai_grader'),
        get_string('auto_enable_new_assignments_desc', 'local_essay_ai_grader'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/auto_publish_grade',
        get_string('auto_publish_grade', 'local_essay_ai_grader'),
        get_string('auto_publish_grade_desc', 'local_essay_ai_grader'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/auto_publish_feedback',
        get_string('auto_publish_feedback', 'local_essay_ai_grader'),
        get_string('auto_publish_feedback_desc', 'local_essay_ai_grader'),
        1
    ));

    // Quick links for easier navigation from settings page.
    $dashboardurl = (new moodle_url('/local/essay_ai_grader/dashboard.php'))->out(false);
    $monitorurl = (new moodle_url('/local/essay_ai_grader/monitor.php'))->out(false);
    $historyurl = (new moodle_url('/local/essay_ai_grader/assessment_history.php'))->out(false);
    $quicklinkshtml = html_writer::link($dashboardurl, get_string('viewdashboard', 'local_essay_ai_grader'),
        array('class' => 'btn btn-secondary me-2'))
        . ' ' .
        html_writer::link($monitorurl, get_string('monitor_title', 'local_essay_ai_grader'),
            array('class' => 'btn btn-primary me-2'))
        . ' ' .
        html_writer::link($historyurl, 'Assessment History',
            array('class' => 'btn btn-info'));
    $settings->add(new admin_setting_heading(
        'local_essay_ai_grader/quicklinks',
        get_string('quicklinks_title', 'local_essay_ai_grader'),
        get_string('quicklinks_desc', 'local_essay_ai_grader') . '<br><br>' . $quicklinkshtml
    ));
    
    // API Key Name.
    $settings->add(new admin_setting_heading(
        'local_essay_ai_grader/gemini_header',
        get_string('gemini_settings', 'local_essay_ai_grader'),
        get_string('gemini_settings_desc', 'local_essay_ai_grader')
    ));

    // Gemini API Key.
    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/gemini_api_key',
        get_string('gemini_api_key', 'local_essay_ai_grader'),
        get_string('gemini_api_key_desc', 'local_essay_ai_grader'),
        '',
        PARAM_RAW
    ));

    // Model selection.
    $models = array(
        'gemini-flash-latest' => 'Gemini Flash Latest (Recommended)',
        'gemini-2.0-flash' => 'Gemini 2.0 Flash',
        'gemini-2.5-flash' => 'Gemini 2.5 Flash',
        'gemini-pro-latest' => 'Gemini Pro Latest',
        'gemini-2.5-pro' => 'Gemini 2.5 Pro',
        'gemini-1.5-pro' => 'Gemini 1.5 Pro',
        'gemini-3-pro-preview' => 'Gemini 3 Pro Preview',
        'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro Preview',
    );
    
    $settings->add(new admin_setting_configselect(
        'local_essay_ai_grader/gemini_model',
        get_string('gemini_model', 'local_essay_ai_grader'),
        get_string('gemini_model_desc', 'local_essay_ai_grader'),
        'gemini-flash-latest',
        $models
    ));

    // Ollama settings.
    $settings->add(new admin_setting_heading(
        'local_essay_ai_grader/ollama_header',
        get_string('ollama_settings', 'local_essay_ai_grader'),
        get_string('ollama_settings_desc', 'local_essay_ai_grader')
    ));

    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/ollama_url',
        get_string('ollama_url', 'local_essay_ai_grader'),
        get_string('ollama_url_desc', 'local_essay_ai_grader'),
        'http://127.0.0.1:11434',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/ollama_model',
        get_string('ollama_model', 'local_essay_ai_grader'),
        get_string('ollama_model_desc', 'local_essay_ai_grader'),
        'llama3.1:8b',
        PARAM_TEXT
    ));

    // Default max score.
    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/default_max_score',
        get_string('default_max_score', 'local_essay_ai_grader'),
        get_string('default_max_score_desc', 'local_essay_ai_grader'),
        '100',
        PARAM_INT
    ));

    // Enable automatic grading on submission.
    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/auto_grade_on_submit',
        get_string('auto_grade_on_submit', 'local_essay_ai_grader'),
        get_string('auto_grade_on_submit_desc', 'local_essay_ai_grader'),
        0
    ));

    // OCR settings for image submissions.
    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/ocr_enabled',
        get_string('ocr_enabled', 'local_essay_ai_grader'),
        get_string('ocr_enabled_desc', 'local_essay_ai_grader'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/tesseract_path',
        get_string('tesseract_path', 'local_essay_ai_grader'),
        get_string('tesseract_path_desc', 'local_essay_ai_grader'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/ocr_languages',
        get_string('ocr_languages', 'local_essay_ai_grader'),
        get_string('ocr_languages_desc', 'local_essay_ai_grader'),
        'ind+eng',
        PARAM_TEXT
    ));

    // Default evaluation criteria.
    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/eval_grammar',
        get_string('eval_grammar', 'local_essay_ai_grader'),
        get_string('eval_grammar_desc', 'local_essay_ai_grader'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/eval_content',
        get_string('eval_content', 'local_essay_ai_grader'),
        get_string('eval_content_desc', 'local_essay_ai_grader'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/eval_organization',
        get_string('eval_organization', 'local_essay_ai_grader'),
        get_string('eval_organization_desc', 'local_essay_ai_grader'),
        1
    ));

    // Logging and Monitoring Settings
    $settings->add(new admin_setting_heading(
        'local_essay_ai_grader/logging_header',
        get_string('logging_settings', 'local_essay_ai_grader'),
        get_string('logging_settings_desc', 'local_essay_ai_grader')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/enable_logging',
        get_string('enable_logging', 'local_essay_ai_grader'),
        get_string('enable_logging_desc', 'local_essay_ai_grader'),
        1
    ));

    // Rate Limiting Settings
    $settings->add(new admin_setting_heading(
        'local_essay_ai_grader/ratelimit_header',
        get_string('rate_limit_settings', 'local_essay_ai_grader'),
        get_string('rate_limit_settings_desc', 'local_essay_ai_grader')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/enable_rate_limiting',
        get_string('enable_rate_limiting', 'local_essay_ai_grader'),
        get_string('enable_rate_limiting_desc', 'local_essay_ai_grader'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/rate_limit_hourly',
        get_string('rate_limit_hourly', 'local_essay_ai_grader'),
        get_string('rate_limit_hourly_desc', 'local_essay_ai_grader'),
        '300',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/rate_limit_daily',
        get_string('rate_limit_daily', 'local_essay_ai_grader'),
        get_string('rate_limit_daily_desc', 'local_essay_ai_grader'),
        '5000',
        PARAM_INT
    ));

    // Gemini-specific rate limits
    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/gemini_rate_limit_hourly',
        get_string('gemini_rate_limit_hourly', 'local_essay_ai_grader'),
        get_string('gemini_rate_limit_hourly_desc', 'local_essay_ai_grader'),
        '300',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/gemini_rate_limit_daily',
        get_string('gemini_rate_limit_daily', 'local_essay_ai_grader'),
        get_string('gemini_rate_limit_daily_desc', 'local_essay_ai_grader'),
        '5000',
        PARAM_INT
    ));

    // Retry Settings
    $settings->add(new admin_setting_heading(
        'local_essay_ai_grader/retry_header',
        get_string('retry_settings', 'local_essay_ai_grader'),
        get_string('retry_settings_desc', 'local_essay_ai_grader')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/enable_retries',
        get_string('enable_retries', 'local_essay_ai_grader'),
        get_string('enable_retries_desc', 'local_essay_ai_grader'),
        1
    ));

    // FASE 2: Cache Settings
    $settings->add(new admin_setting_heading(
        'local_essay_ai_grader/cache_header',
        get_string('caching_settings', 'local_essay_ai_grader'),
        get_string('caching_settings_desc', 'local_essay_ai_grader')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/enable_caching',
        get_string('enable_caching', 'local_essay_ai_grader'),
        get_string('enable_caching_desc', 'local_essay_ai_grader'),
        1
    ));

    $cache_ttl_options = array(
        '900' => '15 minutes',
        '1800' => '30 minutes',
        '3600' => '1 hour',
        '7200' => '2 hours',
        '86400' => '1 day',
        '604800' => '1 week',
    );

    $settings->add(new admin_setting_configselect(
        'local_essay_ai_grader/config_cache_ttl',
        get_string('config_cache_ttl', 'local_essay_ai_grader'),
        get_string('config_cache_ttl_desc', 'local_essay_ai_grader'),
        '3600',
        $cache_ttl_options
    ));

    $model_cache_options = array(
        '3600' => '1 hour',
        '21600' => '6 hours',
        '86400' => '1 day',
        '604800' => '1 week',
        '2592000' => '30 days',
    );

    $settings->add(new admin_setting_configselect(
        'local_essay_ai_grader/model_cache_ttl',
        get_string('model_cache_ttl', 'local_essay_ai_grader'),
        get_string('model_cache_ttl_desc', 'local_essay_ai_grader'),
        '86400',
        $model_cache_options
    ));

    // FASE 2: Cost Tracking Settings
    $settings->add(new admin_setting_heading(
        'local_essay_ai_grader/costtracking_header',
        get_string('cost_tracking_settings', 'local_essay_ai_grader'),
        get_string('cost_tracking_settings_desc', 'local_essay_ai_grader')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/enable_cost_tracking',
        get_string('enable_cost_tracking', 'local_essay_ai_grader'),
        get_string('enable_cost_tracking_desc', 'local_essay_ai_grader'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/cost_warning_threshold',
        get_string('cost_warning_threshold', 'local_essay_ai_grader'),
        get_string('cost_warning_threshold_desc', 'local_essay_ai_grader'),
        '100',
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_essay_ai_grader/cost_warning_emails',
        get_string('cost_warning_email', 'local_essay_ai_grader'),
        get_string('cost_warning_email_desc', 'local_essay_ai_grader'),
        '',
        PARAM_RAW
    ));

    // FASE 2: Analytics Settings
    $settings->add(new admin_setting_heading(
        'local_essay_ai_grader/analytics_header',
        get_string('analytics_dashboard', 'local_essay_ai_grader'),
        get_string('analytics_settings_desc', 'local_essay_ai_grader', '')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_essay_ai_grader/enable_analytics',
        get_string('enable_analytics', 'local_essay_ai_grader'),
        get_string('enable_analytics_desc', 'local_essay_ai_grader'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_essay_ai_grader/analytics_retention_days',
        get_string('analytics_retention_days', 'local_essay_ai_grader'),
        get_string('analytics_retention_days_desc', 'local_essay_ai_grader'),
        '365',
        PARAM_INT
    ));

    // Analytics dashboard link
    $analyticsurl = (new moodle_url('/local/essay_ai_grader/analytics.php'))->out(false);
    $analyticslink = html_writer::link($analyticsurl, get_string('analytics_dashboard', 'local_essay_ai_grader'),
        array('class' => 'btn btn-primary'));
    $settings->add(new admin_setting_heading(
        'local_essay_ai_grader/analytics_link',
        get_string('analytics_dashboard', 'local_essay_ai_grader'),
        $analyticslink
    ));

    // Add settings to admin menu.
    $ADMIN->add('localplugins', $settings);
}
