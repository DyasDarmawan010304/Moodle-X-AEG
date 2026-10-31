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
 * Language strings for Essay AI Grader plugin.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Essay AI Grader';
$string['plugindesc'] = 'Automatic essay grading using Gemini AI';

// Settings.
$string['provider'] = 'AI Provider';
$string['provider_desc'] = 'Select which AI backend is used for essay grading';
$string['provider_gemini'] = 'Google Gemini API';
$string['provider_ollama'] = 'Ollama (local/self-hosted)';
$string['auto_enable_new_assignments'] = 'Auto-enable new assignments';
$string['auto_enable_new_assignments_desc'] = 'Automatically create and enable AI grading config when a new assignment activity is created';
$string['auto_publish_grade'] = 'Auto-publish AI grade to gradebook';
$string['auto_publish_grade_desc'] = 'When AI assessment is completed, automatically write the suggested score to assignment gradebook';
$string['auto_publish_feedback'] = 'Auto-publish AI feedback to feedback column';
$string['auto_publish_feedback_desc'] = 'When AI assessment is completed, automatically publish LLM analysis and criteria scores to the assignment feedback column, visible to students';
$string['criteria_breakdown'] = 'Criteria Breakdown';

$string['gemini_settings'] = 'Gemini AI Settings';
$string['gemini_settings_desc'] = 'Configure Gemini API settings for essay evaluation';
$string['gemini_api_key'] = 'Gemini API Key';
$string['gemini_api_key_desc'] = 'Enter your Google Gemini API key';
$string['gemini_model'] = 'Gemini Model';
$string['gemini_model_desc'] = 'Select the Gemini model to use for essay grading';

$string['ollama_settings'] = 'Ollama Settings';
$string['ollama_settings_desc'] = 'Configure local Ollama endpoint and model';
$string['ollama_url'] = 'Ollama Base URL';
$string['ollama_url_desc'] = 'Ollama server URL, for example http://127.0.0.1:11434';
$string['ollama_model'] = 'Ollama Model';
$string['ollama_model_desc'] = 'Model tag to use, for example llama3.1:8b';
$string['default_max_score'] = 'Default Maximum Score';
$string['default_max_score_desc'] = 'Default maximum score for essays (default: 100)';
$string['auto_grade_on_submit'] = 'Automatic Grading on Submission';
$string['auto_grade_on_submit_desc'] = 'Automatically grade essays when students submit (requires scheduled task enabled)';
$string['ocr_enabled'] = 'Enable OCR for image files';
$string['ocr_enabled_desc'] = 'If enabled, the system will try to extract text from image uploads (jpg/png) using Tesseract OCR.';
$string['tesseract_path'] = 'Tesseract Path (Optional)';
$string['tesseract_path_desc'] = 'Leave empty to use "tesseract" from PATH. Set full binary path if needed, e.g. C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
$string['ocr_languages'] = 'OCR Languages';
$string['ocr_languages_desc'] = 'Tesseract language codes, e.g. ind+eng';

$string['eval_grammar'] = 'Evaluate Grammar & Sentence Structure';
$string['eval_grammar_desc'] = 'Check for grammar, spelling, and sentence clarity';
$string['eval_content'] = 'Evaluate Content Quality';
$string['eval_content_desc'] = 'Assess relevance, accuracy, and depth of content';
$string['eval_organization'] = 'Evaluate Organization & Structure';
$string['eval_organization_desc'] = 'Check essay organization, flow, and structure';

$string['max_score_label'] = 'Maximum Score';
$string['answer_key_label'] = 'Answer Key / Expected Answer';
$string['rubric_guide_label'] = 'Rubric Guide';
$string['enable_ai_grading'] = 'Enable AI Essay Grading';

// Pages.
$string['reviewassessments'] = 'Review AI Assessments';
$string['title_review_assessments'] = 'Review AI-Generated Assessments';
$string['ai_grading_not_enabled'] = 'AI grading is not enabled for this assignment';

// Assessment status.
$string['status_pending'] = 'Pending';
$string['status_processing'] = 'Processing...';
$string['status_completed'] = 'Completed';
$string['status_error'] = 'Error';

// Assessment feedback.
$string['ai_score'] = 'AI Suggested Score';
$string['ai_feedback'] = 'AI Feedback';
$string['teacher_feedback'] = 'Teacher Feedback';
$string['teacher_score'] = 'Final Grade';
$string['accept_grade'] = 'Accept Grade';
$string['reject_grade'] = 'Reject & Edit';
$string['edit_grade'] = 'Edit Grade';
$string['edit_grade_help'] = 'Adjust score and optional feedback before saving to gradebook';
$string['criteria_scores'] = 'Criteria Scores';
$string['criteria_subtotal'] = 'Criteria Subtotal (Raw)';
$string['llm_raw_score'] = 'LLM Raw Score (Before Normalization)';
$string['llm_raw_response'] = 'View Raw LLM Response';
$string['improvement_suggestions'] = 'Improvement Suggestions';
$string['improvement_suggestions_help'] = 'Here are the specific suggestions from the AI to help you improve your essay for next time.';

// Messages.
$string['grade_accepted'] = 'Grade accepted and saved';
$string['grade_rejected'] = 'Grade rejected. You can edit the grade manually';
$string['grade_updated'] = 'Grade updated and saved to gradebook';
$string['assessment_error'] = 'Error during assessment: {$a}';
$string['no_assessments'] = 'No pending assessments found';

// Email.
$string['assessment_ready_subject'] = 'AI Assessment Ready for Review - {$a}';
$string['assessment_ready_body'] = 'An AI assessment is ready for review. Assignment: {$a->assignmentname}, Student: {$a->studentname}';

// Scheduled tasks.
$string['task_process_assessments'] = 'Process pending essay assessments with Gemini AI';

// Configuration page.
$string['enable_ai_grading'] = 'Enable AI Grading for this assignment';
$string['max_score_label'] = 'Maximum Score';
$string['eval_criteria_label'] = 'Evaluation Criteria';
$string['custom_prompt_label'] = 'Custom Evaluation Prompt (Optional)';
$string['custom_prompt_help'] = 'Leave empty to use default prompt. You can customize the evaluation instructions here.';
$string['answer_key_label'] = 'Answer Key / Expected Points (Optional)';
$string['answer_key_help'] = 'Provide expected key points for the ideal answer so the AI can compare student responses against them.';
$string['rubric_guide_label'] = 'Rubric Guide (Optional)';
$string['rubric_guide_help'] = 'Define scoring aspects and weights, for example: content 50%, argument quality 30%, language 20%.';
$string['btn_save'] = 'Save Configuration';
$string['btn_cancel'] = 'Cancel';
$string['configsaved'] = 'Configuration saved successfully';

// Error messages.
$string['apikey_not_configured'] = 'Gemini API key is not configured. Please configure it in plugin settings.';
$string['essaytext_empty'] = 'Essay text is empty';
$string['api_call_failed'] = 'Failed to call Gemini API. Please check your API key and try again.';
$string['submission_not_found'] = 'Submission not found';
$string['file_not_supported'] = 'File submission type not yet supported. Please use online text submissions.';

// Dashboard.
$string['dashboarddesc'] = 'Manage all assignments with AI grading enabled';
$string['noaiassignments'] = 'No assignments with AI grading enabled yet';
$string['assignment'] = 'Assignment';
$string['course'] = 'Course';
$string['assessments'] = 'Assessments';
$string['pending'] = 'Pending Reviews';
$string['action'] = 'Actions';
$string['configure'] = 'Configure';
$string['review'] = 'Review';
$string['adminsettings'] = 'Admin Settings';$string['configure_ai_grading'] = 'Configure AI Grading';
$string['viewdashboard'] = 'View Dashboard';
$string['dashboard'] = 'Dashboard';
$string['monitor_title'] = 'AI Grading Monitor';
$string['monitor_task_status'] = 'Scheduled Task Status';
$string['monitor_queue_status'] = 'Queue Status';
$string['monitor_latest'] = 'Latest Assessments';
$string['monitor_run_queue_now'] = 'Run AI Queue Now';
$string['monitor_queue_run_done'] = 'AI queue processing executed.';
$string['monitor_reset_stuck_done'] = 'Reset stuck assessments back to pending';
$string['monitor_reset_all_errors_done'] = 'Reset all error assessments back to pending';
$string['monitor_task_not_found'] = 'Scheduled task not found. Please verify plugin task registration.';

// Assessment History
$string['assessment_history_title'] = 'Assessment History';
$string['assessment_history_desc'] = 'View complete history of all AI assessments with filtering and sorting capabilities';

$string['quicklinks_title'] = 'Quick Access';
$string['quicklinks_desc'] = 'Open operational pages without searching through menus.';

// Logging Settings
$string['logging_settings'] = 'Logging & Monitoring';
$string['logging_settings_desc'] = 'Configure detailed logging for debugging and audit trails';
$string['enable_logging'] = 'Enable Comprehensive Logging';
$string['enable_logging_desc'] = 'Log all assessment processing steps, errors, and API calls to files for debugging and audit purposes';

// Rate Limiting Settings
$string['rate_limit_settings'] = 'API Rate Limiting';
$string['rate_limit_settings_desc'] = 'Configure rate limits to prevent exceeding API quotas';
$string['enable_rate_limiting'] = 'Enable Rate Limiting';
$string['enable_rate_limiting_desc'] = 'Check and enforce API request limits before making calls to prevent hitting quota restrictions';
$string['rate_limit_hourly'] = 'Default Hourly Limit';
$string['rate_limit_hourly_desc'] = 'Maximum API requests per hour (default: 300)';
$string['rate_limit_daily'] = 'Default Daily Limit';
$string['rate_limit_daily_desc'] = 'Maximum API requests per day (default: 5000)';
$string['gemini_rate_limit_hourly'] = 'Gemini Hourly Limit';
$string['gemini_rate_limit_hourly_desc'] = 'Specific hourly rate limit for Gemini API (default: 300)';
$string['gemini_rate_limit_daily'] = 'Gemini Daily Limit';
$string['gemini_rate_limit_daily_desc'] = 'Specific daily rate limit for Gemini API (default: 5000)';

// Retry Settings
$string['retry_settings'] = 'Error Retry & Recovery';
$string['retry_settings_desc'] = 'Configure automatic retry mechanism for failed assessments';
$string['enable_retries'] = 'Enable Automatic Retries';
$string['enable_retries_desc'] = 'Automatically retry failed assessments with exponential backoff to handle transient errors';

// FASE 2: Cache Manager Settings
$string['caching_settings'] = 'Caching Configuration';
$string['caching_settings_desc'] = 'Configure data caching to improve performance';
$string['enable_caching'] = 'Enable Caching';
$string['enable_caching_desc'] = 'Cache frequently accessed data (configuration, models) to reduce database load';
$string['config_cache_ttl'] = 'Configuration Cache TTL';
$string['config_cache_ttl_desc'] = 'Time to live for cached assignment configurations (in seconds, default: 3600 = 1 hour)';
$string['model_cache_ttl'] = 'Model List Cache TTL';
$string['model_cache_ttl_desc'] = 'Time to live for cached model lists (in seconds, default: 86400 = 24 hours)';

// FASE 2: Cost Tracking Settings
$string['cost_tracking_settings'] = 'Cost Tracking & Financial Monitoring';
$string['cost_tracking_settings_desc'] = 'Monitor API usage costs and budget spending';
$string['enable_cost_tracking'] = 'Enable Cost Tracking';
$string['enable_cost_tracking_desc'] = 'Track the cost of all API calls for budget monitoring and financial analysis';
$string['cost_warning_threshold'] = 'Monthly Cost Warning Threshold';
$string['cost_warning_threshold_desc'] = 'Alert administrators when monthly costs exceed this amount in USD (0 to disable)';
$string['cost_warning_email'] = 'Alert Email Recipients';
$string['cost_warning_email_desc'] = 'Email addresses to notify when cost threshold is exceeded (one per line)';

// FASE 2: Bulk Operations Strings
$string['bulk_operations_title'] = 'Bulk Assessment Operations';
$string['select_all'] = 'Select All';
$string['deselect_all'] = 'Deselect All';
$string['accept_selected'] = 'Accept Selected Grades';
$string['reject_selected'] = 'Reject Selected Grades';
$string['export_as_csv'] = 'Export as CSV';
$string['feedback_for_rejection'] = 'Feedback for Rejected Grades';
$string['assessment_accepted_bulk'] = '{$a} assessments accepted successfully';
$string['assessment_rejected_bulk'] = '{$a} assessments rejected successfully';
$string['assessment_exported'] = 'Assessments exported successfully';
$string['bulk_operation_confirm'] = 'Are you sure you want to {$a} the selected assessments?';
$string['no_assessments_selected'] = 'Please select at least one assessment';
$string['bulk_operation_error'] = 'Error during bulk operation: {$a}';
$string['bulk_operation_partial'] = 'Bulk operation partially completed. {$a->succeeded} succeeded, {$a->failed} failed';

// FASE 2: Analytics Dashboard Strings
$string['analytics_dashboard'] = 'Analytics Dashboard';
$string['analytics_disabled'] = 'Analytics dashboard is disabled. Please enable it in plugin settings.';
$string['analytics_data_error'] = 'Error loading analytics data: {$a}';
$string['enable_analytics'] = 'Enable Analytics Dashboard';
$string['enable_analytics_desc'] = 'Enable the analytics dashboard for viewing system metrics and performance insights';
$string['analytics_retention_days'] = 'Analytics Data Retention (Days)';
$string['analytics_retention_days_desc'] = 'How many days of analytics data to retain (old data will be archived or deleted)';

// FASE 3: Assessment Standards System (Official Standards with Kemendikbud basis)
$string['assessment_standards'] = 'Assessment Standards';
$string['manage_standards'] = 'Manage Assessment Standards';
$string['official_standards'] = 'Official Assessment Standards';
$string['standards_help_text'] = 'Manage official assessment standards for essay evaluation. Standards from Kemendikbud (Ministry of Education) take priority over regional or custom standards. The system automatically uses the highest-priority standard available.';
$string['source'] = 'Source';
$string['source_kemendikbud'] = 'Kemendikbud (Ministry of Education)';
$string['source_kabupaten'] = 'Kabupaten (Regency)';
$string['source_kota'] = 'Kota (City)';
$string['source_artikel'] = 'Artikel (Article/Research)';
$string['source_jurnal'] = 'Jurnal (Journal)';
$string['source_custom'] = 'Custom (Teacher-created)';
$string['education_level'] = 'Education Level';
$string['standard_name'] = 'Standard Name';
$string['standard_name_placeholder'] = 'e.g., Paragraph Structure (SMA) or Descriptive Essay (JHS)';
$string['author'] = 'Author/Organization';
$string['no_standards_found'] = 'No assessment standards found.';
$string['add_new_standard'] = 'Add New Standard';
$string['add_standard_help'] = 'Create a new assessment standard for essay evaluation. Select source (Kemendikbud, regional, article, etc.) and provide rubric guidance. The system will automatically use Kemendikbud standards first, then fall back to lower priority sources if unavailable.';
$string['rubric_guide'] = 'Rubric Guide';
$string['rubric_placeholder'] = 'Detailed scoring rubric with grade ranges and expectations. Example: "Thesis (20%): Clear statement... Supporting Evidence (30%): Examples must..."';
$string['enable_standard'] = 'Enable this standard for use';
$string['add_standard'] = 'Add Standard';
$string['view'] = 'View';
$string['edit'] = 'Edit';
$string['delete'] = 'Delete';
$string['standard_deleted'] = 'Assessment standard deleted successfully';
$string['standard_added'] = 'Assessment standard added successfully';
$string['standard_updated'] = 'Assessment standard updated successfully';

// Custom Criteria Configuration (NEW - April 2026)
$string['enable_ai_grading'] = 'Enable AI-Powered Grading with Custom Criteria';
$string['enable_ai_grading_help'] = 'When enabled, allows you to configure custom evaluation criteria, weights, and question context for this assignment\'s AI-powered grading.';
$string['enable_ai_grading_custom'] = 'Use Custom Evaluation Criteria';
$string['enable_ai_grading_custom_help'] = 'Check this box to enable custom evaluation criteria for this assignment. You can then select which criteria to use and set their weights.';

$string['evaluation_criteria'] = 'Evaluation Criteria Configuration';
$string['evaluation_criteria_help'] = 'Select which criteria to evaluate and assign weights to each (total will be normalized to 100%). The 6 available criteria are: Content Quality, Organization & Structure, Grammar & Sentence Structure, Vocabulary & Word Choice, Spelling & Punctuation, and Relevance to Topic.';

$string['criterion_content'] = 'Content Quality';
$string['criterion_content_desc'] = 'Quality, accuracy, and relevance of content; depth of ideas';

$string['criterion_organization'] = 'Organization & Structure';
$string['criterion_organization_desc'] = 'Logical flow, clear structure, transitions between ideas';

$string['criterion_grammar'] = 'Grammar & Sentence Structure';
$string['criterion_grammar_desc'] = 'Correct grammar, sentence variety, proper syntax';

$string['criterion_vocabulary'] = 'Vocabulary & Word Choice';
$string['criterion_vocabulary_desc'] = 'Appropriate, varied, and precise vocabulary usage';

$string['criterion_spelling'] = 'Spelling & Punctuation';
$string['criterion_spelling_desc'] = 'Correct spelling, punctuation, and capitalization';

$string['criterion_relevance'] = 'Relevance to Topic/Question';
$string['criterion_relevance_desc'] = 'How well the response addresses the question/topic';

$string['criteria_weight'] = 'Weight %';
$string['criteria_select'] = 'Select';
$string['criteria_weight_note'] = 'Note: Weights will be normalized to 100% if they don\'t sum exactly.';

$string['question_answer_reference'] = 'Question & Answer Reference (Optional)';
$string['question_answer_reference_help'] = 'Provide the question text and expected answer to help guide the AI evaluation. This helps the AI evaluator understand the context and expectations.';

$string['question_text'] = 'Question / Prompt Text';
$string['question_text_help'] = 'Enter the original question or prompt that students were asked to answer. This helps the AI evaluator understand the context and assess how well students addressed it.';

$string['expected_answer'] = 'Expected Answer / Answer Key';
$string['expected_answer_help'] = 'Optionally provide the expected/ideal answer or key points. This helps the AI evaluator assess student responses against a reference and identify missing points or concepts.';

$string['max_score'] = 'Maximum Score';
$string['max_score_help'] = 'The maximum points available for this essay assignment (default: 100).';

$string['custom_prompt'] = 'Additional Custom Instructions';
$string['custom_prompt_help'] = 'Add any additional instructions for the AI evaluator, such as special emphasis areas, instructions for specific topics, or grading considerations not covered by the standard criteria.';

$string['other_ai_settings'] = 'Other AI Grading Settings';

// Default Criteria Management (NEW - April 2026)
$string['manage_criteria'] = 'Manage Default Evaluation Criteria';
$string['manage_criteria_help'] = 'Configure the default weights for the 6 evaluation criteria. These weights will be used as the default for all new essay assignments. Individual assignments can override these defaults.';
$string['criteria_weights_updated'] = 'Criteria weights updated successfully';
$string['changes_saved'] = 'Changes saved successfully';
$string['standard_priority'] = 'Priority';
$string['standard_priority_help'] = 'Standards are automatically selected in priority order: Kemendikbud (1st), Kabupaten (2nd), Kota (3rd), Articles (4th), Journals (5th), Custom (6th)';
$string['using_standard'] = 'Using standard: {$a}';
$string['standard_source'] = 'Standard Source: {$a}';
$string['reference'] = 'Reference';
$string['reference_url'] = 'Reference URL or Document';
$string['description'] = 'Description';
$string['choose'] = 'Choose...';
$string['optional'] = 'Optional';
$string['enabled'] = 'Enabled';
$string['disabled'] = 'Disabled';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['cancel'] = 'Cancel';
$string['k13_standards_preloaded'] = 'Kurikulum 2013 (K13) Kemendikbud standards have been pre-loaded and are ready for use';
$string['standards_integration_info'] = 'Assessment standards are automatically integrated into Gemini prompt generation. When available, standards provide official rubrics and evaluation criteria from the Ministry of Education.';
$string['standards_fallback_info'] = 'Fallback Hierarchy: If an assignment has no Kemendikbud standard, the system will automatically use regional (Kabupaten/Kota) standards, then research articles/journals if available.';

// Criteria Display Enhancement (April 2026)
$string['criterion'] = 'Evaluation Criterion';
$string['score'] = 'Score';
$string['max_score'] = 'Maximum Score';
$string['percentage'] = 'Percentage';
$string['weight'] = 'Weight';
$string['performance'] = 'Performance';
$string['excellent'] = 'Excellent';
$string['good'] = 'Good';
$string['fair'] = 'Fair';
$string['poor'] = 'Poor';
$string['no_criteria_data'] = 'No evaluation criteria data available';
$string['evaluation_criteria_breakdown'] = 'Evaluation Criteria Breakdown';
$string['criteria_table_admin'] = 'Detailed Criteria Scores (Admin View)';
$string['criteria_table_student'] = 'Your Performance by Criteria';
$string['overall_score'] = 'Overall Score';
$string['criteria_summary'] = 'Criteria Summary';

// Analytics tab titles
$string['tab_overview'] = 'Overview';
$string['tab_cost_analytics'] = 'Cost Analytics';
$string['tab_provider_performance'] = 'Provider Performance';
$string['tab_error_analysis'] = 'Error Analysis';

// Analytics metrics
$string['assessment_metrics'] = 'Assessment Metrics';
$string['total_assessments'] = 'Total Assessments';
$string['completed'] = 'Completed';
$string['pending'] = 'Pending';
$string['errors'] = 'Errors';
$string['success_rate'] = 'Success Rate';
$string['average_score'] = 'Average Score';

// Analytics trending and trends
$string['assessment_trends'] = 'Assessment Trends';
$string['assessment_trend_last_30_days'] = 'Assessment Trends (Last 30 Days)';
$string['date'] = 'Date';
$string['assessments'] = 'Assessments';
$string['filter_by'] = 'Filter By';
$string['filter_days'] = 'Time Period';
$string['update'] = 'Update';
$string['days'] = 'days';
$string['year'] = 'year';

// Cost tracking metrics
$string['cost_analytics'] = 'Cost Analytics';
$string['total_cost'] = 'Total Cost';
$string['average_cost_per_assessment'] = 'Average Cost per Assessment';
$string['estimated_monthly_cost'] = 'Estimated Monthly Cost';
$string['cost_by_provider'] = 'Cost Breakdown by Provider';
$string['daily_cost_breakdown'] = 'Daily Cost Breakdown';
$string['cost'] = 'Cost';
$string['cost_currency'] = 'Currency';
$string['percentage'] = 'Percentage';

// Provider performance
$string['provider_performance'] = 'Provider Performance';
$string['provider'] = 'Provider';
$string['average_response_time'] = 'Avg Response Time';

// Error analysis
$string['error_analysis'] = 'Error Analysis';
$string['error_message'] = 'Error Message';
$string['frequency_all_time'] = 'Total Frequency';
$string['frequency_period'] = 'Frequency in {$a}';
$string['last_occurrence'] = 'Last Occurred';
$string['no_errors'] = 'No errors recorded in this period';

// FASE 2: Error Messages
$string['error_invalid_session'] = 'Invalid session token. Please refresh the page and try again.';
$string['cache_clear_failed'] = 'Failed to clear cache: {$a}';

// FASE 2: Capabilities (for role definitions)
$string['essay_ai_grader:viewanalytics'] = 'View analytics dashboard';
$string['essay_ai_grader:viewcosts'] = 'View cost tracking data';
$string['essay_ai_grader:bulkoperations'] = 'Perform bulk assessment operations';
$string['essay_ai_grader:managecache'] = 'Manage plugin cache';

// Student Feedback View - LLM Score & Feedback Display
$string['title_view_feedback'] = 'View AI Feedback & Score';
$string['llm_score'] = 'AI Assessment Score';
$string['llm_feedback'] = 'AI Feedback & Comments';
$string['score_label'] = 'Your Score';
$string['score_explanation'] = 'This score was generated by AI analysis of your essay. Please review the feedback below for specific suggestions on how to improve your work. Your teacher may review and adjust this score.';
$string['no_assessment_yet'] = 'Assessment Not Yet Available';
$string['no_feedback'] = 'No feedback available at this time.';
$string['assessment_pending'] = 'Your essay is being assessed...';
$string['assessment_pending_message'] = 'Your essay has been submitted and is currently being processed by the AI assessment system. The feedback and score will appear here once the assessment is complete.';
$string['check_back_later'] = 'Please check back in a few moments to see your results.';
$string['assignment_info'] = 'Assignment Information';
$string['assignment'] = 'Assignment';
$string['submitted'] = 'Submitted';
$string['assessment_info'] = 'Assessment Information';
$string['assessed'] = 'Assessed On';
$string['status'] = 'Status';
$string['about_ai_feedback'] = 'About This AI Assessment';
$string['ai_feedback_explanation'] = 'This assessment was generated by AI (Gemini) based on your essay content. The feedback provides constructive comments to help you improve your writing. Your teacher may review and provide additional comments or adjust the score.';
$string['criteria'] = 'Evaluation Criteria';
$string['criterion'] = 'Criterion';
$string['score'] = 'Score';
$string['criteria_scores'] = 'Score Breakdown by Criteria';
$string['no_assessment'] = 'No Assessment Available';

// Teacher Review Section
$string['teacher_review'] = 'Teacher Assessment Review';
$string['teacher_review_desc'] = 'Review and validate the AI assessment before finalizing the student\'s grade. You can accept the AI score or make adjustments as needed.';
$string['review_assessment'] = 'Review This Assessment';


