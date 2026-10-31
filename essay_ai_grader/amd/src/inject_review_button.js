/**
 * Inject Essay AI Grader review button on assignment view pages
 * This AMD module automatically adds a visible "Review & Validate Assessments" button
 * to the assignment view page for teachers with grading capability.
 *
 * @module local_essay_ai_grader/inject_review_button
 * @package local_essay_ai_grader
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/log'], function($, log) {
    return {
        init: function(config) {
            log.debug('Initializing Essay AI Grader review button injection');
            
            // Get assignment ID from the config
            var assignmentId = config.assignmentId;
            var assignmentName = config.assignmentName || 'AI Assessments';
            var reviewUrl = config.reviewUrl;
            var assessmentCount = config.assessmentCount || 0;
            
            if (!assignmentId || !reviewUrl) {
                log.warn('Missing required config for review button injection');
                return;
            }
            
            // Create the button HTML
            var buttonHtml = '<a href="' + reviewUrl + '" class="btn btn-info ms-2" ' +
                'title="Review and validate AI-generated assessments for this assignment" ' +
                'style="display: inline-block; margin-left: 10px; font-weight: 500;">' +
                '<i class="fa fa-lightbulb-o me-1"></i> Review & Validate Assessments';
            
            if (assessmentCount > 0) {
                buttonHtml += ' <span class="badge bg-primary ms-2">' + assessmentCount + '</span>';
            }
            
            buttonHtml += '</a>';
            
            // Try to find the place to inject the button
            // Look for the assignment header area or action bar
            $(function() {
                // Strategy 1: Try to append to the page-header-headings div (common in modern Moodle themes)
                var headerContainer = $('.page-header-headings');
                if (headerContainer.length > 0) {
                    log.debug('Found page-header-headings, injecting review button');
                    headerContainer.append(buttonHtml);
                    return;
                }
                
                // Strategy 2: Look for h1 element and add button after it
                var h1 = $('h1');
                if (h1.length > 0) {
                    log.debug('Found h1 element, injecting review button after header');
                    h1.after('<div class="mt-2 mb-3">' + buttonHtml + '</div>');
                    return;
                }
                
                // Strategy 3: Look for the assignment panel and add button there
                var assignmentPanel = $('.mod_assign_page');
                if (assignmentPanel.length > 0) {
                    log.debug('Found assignment panel, injecting review button');
                    assignmentPanel.eq(0).before(
                        '<div class="alert alert-info mt-3 mb-3">' +
                        '<strong>Teacher Review:</strong> ' + buttonHtml +
                        '</div>'
                    );
                    return;
                }
                
                // Strategy 4: Add to top of the body content  
                log.warn('Could not find ideal location for review button, adding to body');
                $('body').prepend(
                    '<div class="essay-ai-grader-button-container" style="padding: 15px; background: #e8f4f8; margin: 10px 0; border-left: 4px solid #0066cc;">' +
                    '<strong>📋 AI Assessment Review:</strong> ' + buttonHtml +
                    '</div>'
                );
            });
        }
    };
});
