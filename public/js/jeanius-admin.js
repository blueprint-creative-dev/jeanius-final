/**
 * Jeanius Admin JavaScript
 * Handles the admin interface for Jeanius report generation
 */
(function ($) {
    'use strict';

    // Store the status check timer
    let statusCheckTimer = null;
    let isProcessing = false;

    // Initialize when the document is ready
    $(document).ready(function () {
        initJeaniusAdmin();
    });

    /**
     * Initialize the Jeanius admin functionality
     */
    function initJeaniusAdmin() {
        // Set up the regenerate report button
        $('#jeanius-regenerate-report').on('click', function (e) {
            e.preventDefault();
            jeaniusRegenerateReport();
        });

        // Set up the check status button
        $('#jeanius-check-status').on('click', function (e) {
            e.preventDefault();
            checkReportStatus();
        });

        // Make the regenerate function globally available for the ACF button
        window.jeaniusRegenerateReport = jeaniusRegenerateReport;

        // If a report is in progress, start the status check timer
        const statusContainer = $('#jeanius-status-metabox');
        if (statusContainer.hasClass('status-in-progress') || statusContainer.hasClass('status-waiting')) {
            startStatusCheckTimer();
        }
    }

    /**
     * Regenerate the Jeanius report
     */
    function jeaniusRegenerateReport() {
        if (isProcessing) {
            return;
        }

        isProcessing = true;

        // Update UI
        const $button = $('#jeanius-regenerate-report');
        const originalText = $button.text();
        $button.text(JeaniusAdmin.labels.regenerating).prop('disabled', true);

        // Show visual indication that we're processing
        $('#jeanius-status-metabox')
            .removeClass('status-complete status-error status-not-started')
            .addClass('status-in-progress');

        $('.jeanius-status-message').text(JeaniusAdmin.labels.regenerating);

        // Make the AJAX call
        $.ajax({
            url: JeaniusAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jeanius_regenerate_report',
                post_id: JeaniusAdmin.postId,
                nonce: JeaniusAdmin.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Update status display
                    updateStatusDisplay(response.data.status);

                    // Start the status check timer
                    startStatusCheckTimer();
                } else {
                    // Show error
                    $('.jeanius-status-message').text(response.data.message || JeaniusAdmin.labels.errorOccurred);
                    $('#jeanius-status-metabox')
                        .removeClass('status-in-progress')
                        .addClass('status-error');
                }
            },
            error: function () {
                // Show error
                $('.jeanius-status-message').text(JeaniusAdmin.labels.errorOccurred);
                $('#jeanius-status-metabox')
                    .removeClass('status-in-progress')
                    .addClass('status-error');
            },
            complete: function () {
                // Re-enable the button
                $button.text(originalText).prop('disabled', false);
                isProcessing = false;
            }
        });
    }

    /** 
     * Check the current report generation status
     */
    function checkReportStatus() {
        if (isProcessing) {
            return;
        }

        isProcessing = true;

        // Update UI
        const $button = $('#jeanius-check-status');
        const originalText = $button.text();
        $button.text(JeaniusAdmin.labels.statusUpdating).prop('disabled', true);

        // Make the AJAX call
        $.ajax({
            url: JeaniusAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jeanius_check_status',
                post_id: JeaniusAdmin.postId,
                nonce: JeaniusAdmin.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Update status display
                    updateStatusDisplay(response.data);

                    // If still in progress, continue checking
                    if (response.data.status === 'in_progress' || response.data.status === 'waiting') {
                        startStatusCheckTimer();
                    } else {
                        stopStatusCheckTimer();
                    }
                } else {
                    // Show error
                    $('.jeanius-status-message').text(response.data.message || JeaniusAdmin.labels.errorOccurred);
                    $('#jeanius-status-metabox')
                        .removeClass('status-in-progress status-waiting')
                        .addClass('status-error');

                    stopStatusCheckTimer();
                }
            },
            error: function () {
                // Show error
                $('.jeanius-status-message').text(JeaniusAdmin.labels.errorOccurred);
                $('#jeanius-status-metabox')
                    .removeClass('status-in-progress status-waiting')
                    .addClass('status-error');

                stopStatusCheckTimer();
            },
            complete: function () {
                // Re-enable the button
                $button.text(originalText).prop('disabled', false);
                isProcessing = false;
            }
        });
    }

    /**
     * Update the status display with the current status
     */
    function updateStatusDisplay(status) {
        // Update progress bar
        $('.jeanius-progress-bar').css('width', status.progress + '%');

        // Update status class
        const $container = $('#jeanius-status-metabox');
        $container.removeClass('status-complete status-in-progress status-error status-waiting status-not-started');

        // Add appropriate class
        switch (status.status) {
            case 'complete':
                $container.addClass('status-complete');
                break;
            case 'in_progress':
                $container.addClass('status-in-progress');
                break;
            case 'error':
                $container.addClass('status-error');
                break;
            case 'waiting':
                $container.addClass('status-waiting');
                break;
            case 'not_started':
                $container.addClass('status-not-started');
                break;
            default:
                $container.addClass('status-unknown');
        }

        // Update status label
        $('.jeanius-status-label').text(status.status);

        // Update message
        $('.jeanius-status-message').text(status.message);

        // Update errors if present
        if (status.errors) {
            $('.jeanius-status-errors').text(status.errors).show();
        } else {
            $('.jeanius-status-errors').hide();
        }

        // Update last activity if present
        if (status.last_activity) {
            const date = new Date(status.last_activity * 1000);
            const formattedDate = date.toLocaleString();
            $('.jeanius-status-last-activity').text('Last activity: ' + formattedDate).show();
        } else {
            $('.jeanius-status-last-activity').hide();
        }

        // If complete, reload the page to show the new report
        if (status.status === 'complete') {
            setTimeout(function () {
                window.location.reload();
            }, 2000);
        }
    }

    /**
     * Start the status check timer
     */
    function startStatusCheckTimer() {
        // Clear any existing timer
        stopStatusCheckTimer();

        // Start a new timer
        statusCheckTimer = setInterval(function () {
            if (!isProcessing) {
                checkReportStatus();
            }
        }, JeaniusAdmin.statusCheckInterval);
    }

    /**
     * Stop the status check timer
     */
    function stopStatusCheckTimer() {
        if (statusCheckTimer) {
            clearInterval(statusCheckTimer);
            statusCheckTimer = null;
        }
    }

})(jQuery);