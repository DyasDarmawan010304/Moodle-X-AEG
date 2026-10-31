// This file contains the JavaScript module for bulk assessment operations
// Include this in review.php or assessment review interface

define(['jquery'], function($) {
    'use strict';
    
    var BulkOperations = function(options) {
        this.options = options || {};
        this.selectedIds = [];
        this.init();
    };
    
    BulkOperations.prototype.init = function() {
        var self = this;
        
        // Select all checkbox
        $(document).on('change', '#select-all-assessments', function() {
            var checked = $(this).prop('checked');
            $('.select-assessment[data-assessment-id!="all"]').prop('checked', checked);
            self.updateButtonStates();
        });
        
        // Individual checkbox changes
        $(document).on('change', '.select-assessment[data-assessment-id!="all"]', function() {
            // Update "select all" checkbox state
            var total = $('.select-assessment[data-assessment-id!="all"]').length;
            var checked = $('.select-assessment[data-assessment-id!="all"]:checked').length;
            
            if (checked === total) {
                $('#select-all-assessments').prop('checked', true).prop('indeterminate', false);
            } else if (checked === 0) {
                $('#select-all-assessments').prop('checked', false).prop('indeterminate', false);
            } else {
                $('#select-all-assessments').prop('checked', false).prop('indeterminate', true);
            }
            
            self.updateButtonStates();
        });
        
        // Accept all button
        $(document).on('click', '#btn-accept-all', function() {
            self.handleBulkAccept();
        });
        
        // Reject all button
        $(document).on('click', '#btn-reject-all', function() {
            self.handleBulkReject();
        });
        
        // Export CSV button
        $(document).on('click', '#btn-export-csv', function() {
            self.handleExportCSV();
        });
        
        this.updateButtonStates();
    };
    
    BulkOperations.prototype.getSelectedIds = function() {
        return $('.select-assessment[data-assessment-id!="all"]:checked')
            .map(function() { return $(this).data('assessment-id'); })
            .get();
    };
    
    BulkOperations.prototype.updateButtonStates = function() {
        var selected = this.getSelectedIds();
        var hasSelection = selected.length > 0;
        
        $('#btn-accept-all').prop('disabled', !hasSelection)
            .toggleClass('disabled', !hasSelection);
        $('#btn-reject-all').prop('disabled', !hasSelection)
            .toggleClass('disabled', !hasSelection);
        $('#btn-export-csv').prop('disabled', !hasSelection)
            .toggleClass('disabled', !hasSelection);
        
        // Update count display if available
        var countDisplay = $('#bulk-selection-count');
        if (countDisplay.length) {
            if (hasSelection) {
                countDisplay.text(selected.length + ' assessment(s) selected').show();
            } else {
                countDisplay.hide();
            }
        }
    };
    
    BulkOperations.prototype.handleBulkAccept = function() {
        var self = this;
        var selected = this.getSelectedIds();
        
        if (selected.length === 0) {
            alert('Please select at least one assessment');
            return;
        }
        
        if (!confirm('Accept ' + selected.length + ' assessment(s)? This action cannot be undone.')) {
            return;
        }
        
        this.showLoadingIndicator('Accepting assessments...');
        
        $.ajax({
            url: this.options.bulkAcceptUrl || '/local/essay_ai_grader/ajax/bulk_accept.php',
            type: 'POST',
            data: {
                assessmentids: selected.join(','),
                assignmentid: this.options.assignmentid,
                sesskey: this.options.sesskey
            },
            dataType: 'json',
            timeout: 30000, // 30 second timeout
            success: function(response) {
                self.hideLoadingIndicator();
                
                if (response.success) {
                    self.showSuccessMessage(
                        'Success! ' + response.report.accepted + ' assessment(s) accepted.' +
                        (response.report.failed > 0 ? ' (' + response.report.failed + ' failed)' : '')
                    );
                    
                    // Refresh the page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    self.showErrorMessage('Error: ' + (response.error || response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                self.hideLoadingIndicator();
                
                var errorMsg = 'Error: ';
                if (xhr.status === 403) {
                    errorMsg += 'You do not have permission to grade these assessments';
                } else if (xhr.status === 404) {
                    errorMsg += 'Assignment not found';
                } else if (status === 'timeout') {
                    errorMsg += 'Request timed out. Please try again with fewer items.';
                } else {
                    errorMsg += error || 'Network error';
                }
                
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch (e) {
                    // Fallback to default error message
                }
                
                self.showErrorMessage(errorMsg);
            }
        });
    };
    
    BulkOperations.prototype.handleBulkReject = function() {
        var self = this;
        var selected = this.getSelectedIds();
        
        if (selected.length === 0) {
            alert('Please select at least one assessment');
            return;
        }
        
        // Prompt for feedback
        var feedback = prompt(
            'Enter feedback for rejection (will be sent to students):\n\n' +
            '(You are rejecting ' + selected.length + ' assessment(s))',
            ''
        );
        
        if (feedback === null) {
            return; // User cancelled
        }
        
        if (feedback.trim().length === 0) {
            if (!confirm('Reject without feedback? Students will not receive detailed feedback.')) {
                return;
            }
        }
        
        this.showLoadingIndicator('Rejecting assessments...');
        
        $.ajax({
            url: this.options.bulkRejectUrl || '/local/essay_ai_grader/ajax/bulk_reject.php',
            type: 'POST',
            data: {
                assessmentids: selected.join(','),
                assignmentid: this.options.assignmentid,
                feedback: feedback,
                sesskey: this.options.sesskey
            },
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                self.hideLoadingIndicator();
                
                if (response.success) {
                    self.showSuccessMessage(
                        'Success! ' + response.report.rejected + ' assessment(s) rejected.' +
                        (response.report.failed > 0 ? ' (' + response.report.failed + ' failed)' : '')
                    );
                    
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    self.showErrorMessage('Error: ' + (response.error || response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                self.hideLoadingIndicator();
                
                var errorMsg = 'Error: ';
                if (xhr.status === 403) {
                    errorMsg += 'You do not have permission to grade these assessments';
                } else if (xhr.status === 404) {
                    errorMsg += 'Assignment not found';
                } else if (status === 'timeout') {
                    errorMsg += 'Request timed out. Please try again with fewer items.';
                } else {
                    errorMsg += error || 'Network error';
                }
                
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch (e) {
                    // Fallback
                }
                
                self.showErrorMessage(errorMsg);
            }
        });
    };
    
    BulkOperations.prototype.handleExportCSV = function() {
        var selected = this.getSelectedIds();
        
        if (selected.length === 0) {
            alert('Please select at least one assessment');
            return;
        }
        
        // Create form and submit for file download
        var form = $('<form>')
            .attr('method', 'POST')
            .attr('action', this.options.exportUrl || '/local/essay_ai_grader/ajax/export_csv.php')
            .appendTo('body');
        
        $('<input>')
            .attr('type', 'hidden')
            .attr('name', 'assessmentids')
            .val(selected.join(','))
            .appendTo(form);
        
        $('<input>')
            .attr('type', 'hidden')
            .attr('name', 'assignmentid')
            .val(this.options.assignmentid)
            .appendTo(form);
        
        $('<input>')
            .attr('type', 'hidden')
            .attr('name', 'sesskey')
            .val(this.options.sesskey)
            .appendTo(form);
        
        form.submit().remove();
    };
    
    BulkOperations.prototype.showLoadingIndicator = function(message) {
        var indicator = $('<div>')
            .attr('id', 'bulk-loading-indicator')
            .attr('class', 'alert alert-info')
            .html('<div class="spinner-border spinner-border-sm me-2"></div> ' + message)
            .prependTo('body');
        
        $('html, body').animate({scrollTop: 0}, 300);
    };
    
    BulkOperations.prototype.hideLoadingIndicator = function() {
        $('#bulk-loading-indicator').fadeOut(300, function() {
            $(this).remove();
        });
    };
    
    BulkOperations.prototype.showSuccessMessage = function(message) {
        var alert = $('<div>')
            .attr('class', 'alert alert-success alert-dismissible fade show me-3 mt-3')
            .attr('role', 'alert')
            .html(message + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>')
            .prependTo('body');
        
        $('html, body').animate({scrollTop: 0}, 300);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            alert.fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    };
    
    BulkOperations.prototype.showErrorMessage = function(message) {
        var alert = $('<div>')
            .attr('class', 'alert alert-danger alert-dismissible fade show me-3 mt-3')
            .attr('role', 'alert')
            .html(message + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>')
            .prependTo('body');
        
        $('html, body').animate({scrollTop: 0}, 300);
    };
    
    return BulkOperations;
});
