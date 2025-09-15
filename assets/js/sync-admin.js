/**
 * Sync Admin JavaScript
 *
 * Handles the sync settings page interactions and AJAX operations.
 *
 * @package BRAGBookGallery
 * @since 3.0.0
 */

(function() {
    'use strict';

    /**
     * Sync Admin class using ES6 standards
     */
    class SyncAdmin {
        constructor() {
            this.syncInProgress = false;
            this.init();
        }

        /**
         * Initialize sync admin functionality
         */
        init() {
            this.bindEvents();
            this.initFrequencyToggle();
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            document.addEventListener('click', (e) => {
                if (e.target.matches('#sync-procedures-btn')) {
                    this.handleSyncProcedures(e);
                } else if (e.target.matches('#test-automatic-sync-btn')) {
                    this.handleTestAutomaticSync(e);
                } else if (e.target.matches('#test-database-log-btn')) {
                    this.handleTestDatabaseLog(e);
                } else if (e.target.matches('#cleanup-empty-logs-btn')) {
                    this.handleCleanupEmptyLogs(e);
                } else if (e.target.matches('#stop-sync-btn')) {
                    this.handleStopSync(e);
                } else if (e.target.matches('#clear-sync-log-btn')) {
                    this.handleClearSyncLog(e);
                } else if (e.target.matches('#validate-procedures-btn')) {
                    this.handleValidateProcedures(e);
                } else if (e.target.matches('.view-details')) {
                    this.handleViewDetails(e);
                } else if (e.target.matches('.delete-sync-record')) {
                    this.handleDeleteSyncRecord(e);
                }
            });
        }

        /**
         * Handle sync procedures button click
         */
        async handleSyncProcedures(e) {
            e.preventDefault();

            const button = e.target;
            const stopButton = document.getElementById('stop-sync-btn');
            const progress = document.getElementById('sync-progress');
            const results = document.getElementById('sync-results');
            const overallFill = document.getElementById('sync-overall-fill');
            const currentFill = document.getElementById('sync-current-fill');
            const overallPercentage = document.getElementById('sync-overall-percentage');
            const currentPercentage = document.getElementById('sync-current-percentage');
            const currentOperation = document.getElementById('sync-current-operation');
            const progressDetails = document.getElementById('sync-progress-details');
            const progressItems = document.getElementById('sync-progress-items');

            // Always do full sync
            const isFullSync = true;

            // Disable start button, show stop button
            button.disabled = true;
            button.textContent = 'Running Full Sync...';
            stopButton.style.display = 'inline-block';
            progress.style.display = 'block';
            results.style.display = 'none';
            progressDetails.style.display = 'block';
            progressItems.innerHTML = '';

            // Store sync state
            this.syncInProgress = true;

            // Reset progress bars
            overallFill.style.width = '0%';
            currentFill.style.width = '0%';
            overallPercentage.textContent = '0%';
            currentPercentage.textContent = '0%';
            currentOperation.textContent = 'Starting sync...';

            // Update status
            const statusElement = document.getElementById('sync-status');
            statusElement.className = 'brag-book-gallery-status brag-book-gallery-status--running';
            statusElement.textContent = 'Running';

            // Start timer and activity indicator
            this.syncStartTime = Date.now();
            this.startSyncTimer(currentOperation);
            this.addActivityIndicator(overallFill, currentFill);

            // Start progress simulation
            this.simulateProgress(overallFill, currentFill, overallPercentage, currentPercentage, currentOperation, progressItems, isFullSync);

            // Determine action based on sync type
            const ajaxAction = isFullSync ? 'brag_book_full_sync' : 'brag_book_sync_procedures';

            try {
                const response = await this.makeAjaxRequest({
                    url: bragBookSync.ajax_url,
                    data: {
                        action: ajaxAction,
                        nonce: bragBookSync.sync_nonce
                    }
                });

                console.log('AJAX Success Response:', response);

                // Handle both direct success property and nested data.success
                const isSuccess = response.success === true || (response.data && response.data.success === true);

                if (isSuccess) {
                    // Complete progress
                    overallFill.style.width = '100%';
                    currentFill.style.width = '100%';
                    overallPercentage.textContent = '100%';
                    currentPercentage.textContent = '100%';
                    currentOperation.textContent = bragBookSync.messages.sync_complete + ' (100%)';
                    statusElement.className = 'brag-book-gallery-status brag-book-gallery-status--success';
                    statusElement.textContent = 'Success';

                    // Show final summary in progress items
                    this.addProgressItem(progressItems, '✓ Sync completed successfully', 'success');

                    const data = response.data || response;
                    if (data.created) {
                        this.addProgressItem(progressItems, `✓ Created ${data.created} procedures`, 'success');
                    }
                    if (data.updated) {
                        this.addProgressItem(progressItems, `✓ Updated ${data.updated} procedures`, 'success');
                    }
                    if (data.cases_created) {
                        this.addProgressItem(progressItems, `✓ Created ${data.cases_created} cases`, 'success');
                    }
                    if (data.cases_updated) {
                        this.addProgressItem(progressItems, `✓ Updated ${data.cases_updated} cases`, 'success');
                    }
                    if (data.total_cases_processed) {
                        this.addProgressItem(progressItems, `✓ Total cases processed: ${data.total_cases_processed}`, 'info');
                    }

                    // Show detailed case processing information if available
                    if (data.details && data.details.case_processing_log) {
                        this.addProgressItem(progressItems, '📋 Case Processing Details:', 'info');
                        data.details.case_processing_log.forEach(logEntry => {
                            this.addProgressItem(progressItems, `  • ${logEntry}`, 'info');
                        });
                    }

                    // Show detailed results
                    const resultsHtml = this.buildResultsHtml(data);
                    document.getElementById('sync-results-content').innerHTML = resultsHtml;
                    results.style.display = 'block';

                    // Keep progress section visible for review
                    progressDetails.style.display = 'block';

                    // Update last sync time
                    const now = new Date();
                    const timeString = now.toLocaleString();
                    document.getElementById('last-sync-time').textContent = timeString;

                } else {
                    console.log('AJAX Success but response indicates failure:', response);
                    overallFill.style.width = '100%';
                    currentFill.style.width = '100%';
                    overallPercentage.textContent = '100%';
                    currentPercentage.textContent = '100%';
                    currentOperation.textContent = bragBookSync.messages.sync_error;
                    statusElement.className = 'brag-book-gallery-status brag-book-gallery-status--error';
                    statusElement.textContent = 'Error';

                    this.addProgressItem(progressItems, '✗ Sync failed', 'error');

                    const data = response.data || response;
                    const errorHtml = '<div class="brag-book-gallery-notice brag-book-gallery-notice--error">' +
                        '<p>' + (data ? data.message : 'Unknown error occurred') + '</p>' +
                        '</div>';
                    document.getElementById('sync-results-content').innerHTML = errorHtml;
                    results.style.display = 'block';

                    // Keep progress section visible for review even on error
                    progressDetails.style.display = 'block';
                }
            } catch (error) {
                overallFill.style.width = '100%';
                currentFill.style.width = '100%';
                overallPercentage.textContent = '100%';
                currentPercentage.textContent = '100%';
                currentOperation.textContent = bragBookSync.messages.sync_error;
                statusElement.className = 'brag-book-gallery-status brag-book-gallery-status--error';
                statusElement.textContent = 'Error';

                this.addProgressItem(progressItems, '✗ AJAX Error: ' + error.message, 'error');

                const errorHtml = '<div class="brag-book-gallery-notice brag-book-gallery-notice--error">' +
                    '<p>AJAX Error: ' + error.message + '</p>' +
                    '</div>';
                document.getElementById('sync-results-content').innerHTML = errorHtml;
                results.style.display = 'block';
            } finally {
                // Stop timer and remove activity indicators
                this.stopSyncTimer();
                this.removeActivityIndicator(overallFill, currentFill);

                // Re-enable start button, hide stop button
                button.disabled = false;
                button.textContent = 'Start Full Sync';
                stopButton.style.display = 'none';

                // Clear sync state
                this.syncInProgress = false;

                // Reload page after successful sync to update history
                if (statusElement.classList.contains('brag-book-gallery-status--success')) {
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                }
            }
        }

        /**
         * Handle test automatic sync button click
         */
        async handleTestAutomaticSync(e) {
            e.preventDefault();

            if (!confirm('This will run a full sync marked as "Automatic" for testing purposes. Continue?')) {
                return;
            }

            const testButton = e.target;
            const originalText = testButton.textContent;

            try {
                // Disable button and show loading state
                testButton.disabled = true;
                testButton.textContent = 'Testing Automatic Sync...';

                const response = await this.makeAjaxRequest({
                    url: bragBookSync.ajax_url,
                    data: {
                        action: 'brag_book_test_automatic_sync',
                        nonce: bragBookSync.test_auto_nonce
                    }
                });

                if (response.success) {
                    alert(response.data.message);
                    // Reload page if requested to show updated sync history
                    if (response.data.reload) {
                        console.log('Reloading page to show updated sync history...');
                        location.reload();
                    }
                } else {
                    alert('Test failed: ' + (response.data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Test automatic sync error:', error);
                alert('Test automatic sync failed: ' + error.message);
            } finally {
                // Re-enable button
                testButton.disabled = false;
                testButton.textContent = originalText;
            }
        }

        /**
         * Handle test database log button click
         */
        async handleTestDatabaseLog(e) {
            e.preventDefault();

            if (!confirm('This will create a test log entry in the database. Continue?')) {
                return;
            }

            const testButton = e.target;
            const originalText = testButton.textContent;

            try {
                // Disable button and show loading state
                testButton.disabled = true;
                testButton.textContent = 'Testing Database...';

                const response = await this.makeAjaxRequest({
                    url: bragBookSync.ajax_url,
                    data: {
                        action: 'brag_book_test_database_log',
                        nonce: bragBookSync.nonce
                    }
                });

                if (response.success) {
                    alert(response.data.message);
                    // Reload page if requested to show updated sync history
                    if (response.data.reload) {
                        console.log('Reloading page to show updated sync history...');
                        location.reload();
                    }
                } else {
                    alert('Database test failed: ' + (response.data.message || 'Unknown error'));
                }

            } catch (error) {
                console.error('Test database log error:', error);
                alert('Database test failed: ' + error.message);
            } finally {
                // Re-enable button and restore text
                testButton.disabled = false;
                testButton.textContent = originalText;
            }
        }

        /**
         * Handle cleanup empty logs button click
         */
        async handleCleanupEmptyLogs(e) {
            e.preventDefault();

            if (!confirm('This will permanently delete all empty sync records from the database. This action cannot be undone. Continue?')) {
                return;
            }

            const cleanupButton = e.target;
            const originalText = cleanupButton.textContent;

            try {
                // Disable button and show loading state
                cleanupButton.disabled = true;
                cleanupButton.textContent = 'Cleaning Up...';

                const response = await this.makeAjaxRequest({
                    url: bragBookSync.ajax_url,
                    data: {
                        action: 'brag_book_cleanup_empty_logs',
                        nonce: bragBookSync.nonce
                    }
                });

                if (response.success) {
                    alert(response.data.message);
                    // Reload page to show updated sync history
                    if (response.data.reload) {
                        console.log('Reloading page to show cleaned sync history...');
                        location.reload();
                    }
                } else {
                    alert('Cleanup failed: ' + (response.data.message || 'Unknown error'));
                }

            } catch (error) {
                console.error('Cleanup empty logs error:', error);
                alert('Cleanup failed: ' + error.message);
            } finally {
                // Re-enable button and restore text
                cleanupButton.disabled = false;
                cleanupButton.textContent = originalText;
            }
        }

        /**
         * Handle stop sync button click
         */
        async handleStopSync(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to stop the sync? This will interrupt the current operation.')) {
                return;
            }

            const stopButton = e.target;
            const startButton = document.getElementById('sync-procedures-btn');
            const currentOperation = document.getElementById('sync-current-operation');
            const progressItems = document.getElementById('sync-progress-items');

            // Disable stop button
            stopButton.disabled = true;
            stopButton.textContent = 'Stopping...';

            try {
                const response = await this.makeAjaxRequest({
                    url: bragBookSync.ajax_url,
                    data: {
                        action: 'brag_book_stop_sync',
                        nonce: bragBookSync.nonce
                    }
                });

                if (response.success) {
                    // Update progress
                    currentOperation.textContent = 'Sync stopped by user';
                    this.addProgressItem(progressItems, '⚠ Sync stopped by user', 'warning');
                } else {
                    this.addProgressItem(progressItems, '✗ Failed to stop sync: ' + (response.data || 'Unknown error'), 'error');
                }
            } catch (error) {
                this.addProgressItem(progressItems, '✗ Error communicating with server to stop sync', 'error');
            } finally {
                // Clear sync state
                this.syncInProgress = false;

                // Re-enable start button, hide stop button
                setTimeout(() => {
                    startButton.disabled = false;
                    startButton.textContent = 'Start Full Sync';
                    stopButton.style.display = 'none';
                    stopButton.disabled = false;
                    stopButton.textContent = 'Stop Sync';
                }, 1000);
            }
        }

        /**
         * Handle clear sync log button click
         */
        async handleClearSyncLog(e) {
            e.preventDefault();

            if (!confirm(bragBookSync.messages.confirm_clear_log)) {
                return;
            }

            const button = e.target;

            // Disable button
            button.disabled = true;
            button.textContent = 'Clearing...';

            try {
                const response = await this.makeAjaxRequest({
                    url: bragBookSync.ajax_url,
                    data: {
                        action: 'brag_book_clear_sync_log',
                        nonce: bragBookSync.clear_log_nonce
                    }
                });

                if (response.success) {
                    // Reload page to show cleared log
                    location.reload();
                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error occurred'));
                }
            } catch (error) {
                alert('AJAX Error: ' + error.message);
            } finally {
                button.disabled = false;
                button.textContent = 'Clear Sync Log';
            }
        }

        /**
         * Handle delete sync record button click
         */
        async handleDeleteSyncRecord(e) {
            e.preventDefault();

            const button = e.target;
            const recordId = button.dataset.recordId;
            const recordDate = button.dataset.recordDate;

            if (!recordId) {
                alert('Error: No record ID found');
                return;
            }

            // Show confirmation dialog with record date
            const confirmMessage = bragBookSync.messages.confirm_delete_record + '\n\nRecord: ' + recordDate;
            if (!confirm(confirmMessage)) {
                return;
            }

            // Disable button
            button.disabled = true;
            button.textContent = 'Deleting...';

            try {
                const response = await this.makeAjaxRequest({
                    url: bragBookSync.ajax_url,
                    data: {
                        action: 'brag_book_delete_sync_record',
                        nonce: bragBookSync.delete_nonce,
                        record_id: recordId
                    }
                });

                if (response.success) {
                    // Remove the table row with a fade effect
                    const row = button.closest('tr');
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();

                        // Check if there are any remaining rows
                        const tbody = button.closest('tbody');
                        if (tbody.querySelectorAll('tr').length === 0) {
                            // If no rows left, reload the page to show the "no sync operations" message
                            location.reload();
                        }
                    }, 300);
                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error occurred'));
                    button.disabled = false;
                    button.textContent = 'Delete';
                }
            } catch (error) {
                alert('AJAX Error: ' + error.message);
                button.disabled = false;
                button.textContent = 'Delete';
            }
        }

        /**
         * Handle validate procedures button click
         */
        async handleValidateProcedures(e) {
            e.preventDefault();

            const button = e.target;

            // Disable button
            button.disabled = true;
            button.textContent = 'Validating...';

            try {
                const response = await this.makeAjaxRequest({
                    url: bragBookSync.ajax_url,
                    data: {
                        action: 'brag_book_validate_procedure_assignments',
                        nonce: bragBookSync.nonce
                    }
                });

                if (response.success) {
                    const data = response.data.data;

                    // Show results in an alert
                    let message = `Validation Complete!\n\n`;
                    message += `Cases found without procedures: ${data.total_found}\n`;
                    message += `Successfully fixed: ${data.fixed_count}\n`;
                    message += `Failed to fix: ${data.failed_count}`;

                    if (data.total_found === 0) {
                        message += `\n\n✅ All cases have procedures assigned!`;
                    } else if (data.failed_count === 0) {
                        message += `\n\n✅ All unassigned cases were successfully fixed!`;
                    } else {
                        message += `\n\n⚠️ Some cases could not be fixed automatically.`;
                    }

                    alert(message);

                    // If any cases were fixed, reload page to reflect changes
                    if (data.fixed_count > 0) {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error occurred'));
                }
            } catch (error) {
                alert('AJAX Error: ' + error.message);
            } finally {
                button.disabled = false;
                button.textContent = 'Validate Procedures';
            }
        }

        /**
         * Handle view details button click
         */
        handleViewDetails(e) {
            e.preventDefault();

            const details = e.target.dataset.details;
            let formattedDetails = '';

            try {
                // Try to parse JSON details
                const parsedDetails = JSON.parse(details);

                // Format the details nicely
                formattedDetails += '<div class="sync-details-summary">';
                formattedDetails += '<h4>Sync Summary</h4>';
                formattedDetails += '<ul>';
                formattedDetails += '<li><strong>Status:</strong> ' + (parsedDetails.success ? 'Success' : 'Failed') + '</li>';
                formattedDetails += '<li><strong>Items Created:</strong> ' + (parsedDetails.created || 0) + '</li>';
                formattedDetails += '<li><strong>Items Updated:</strong> ' + (parsedDetails.updated || 0) + '</li>';

                if (parsedDetails.details && parsedDetails.details.categories_processed) {
                    formattedDetails += '<li><strong>Categories Processed:</strong> ' + parsedDetails.details.categories_processed + '</li>';
                }

                if (parsedDetails.errors && parsedDetails.errors.length > 0) {
                    formattedDetails += '<li><strong>Errors:</strong> ' + parsedDetails.errors.length + '</li>';
                }
                formattedDetails += '</ul>';
                formattedDetails += '</div>';

                // Show errors if any
                if (parsedDetails.errors && parsedDetails.errors.length > 0) {
                    formattedDetails += '<div class="sync-details-errors">';
                    formattedDetails += '<h4>Errors</h4>';
                    formattedDetails += '<ul>';
                    parsedDetails.errors.forEach(error => {
                        formattedDetails += '<li>' + error + '</li>';
                    });
                    formattedDetails += '</ul>';
                    formattedDetails += '</div>';
                }

                // Show procedure details if available
                if (parsedDetails.details) {
                    if (parsedDetails.details.created_procedures && parsedDetails.details.created_procedures.length > 0) {
                        formattedDetails += '<div class="sync-details-created">';
                        formattedDetails += '<h4>Created Procedures (' + parsedDetails.details.created_procedures.length + ')</h4>';
                        formattedDetails += '<ul>';
                        parsedDetails.details.created_procedures.forEach(proc => {
                            formattedDetails += '<li>' + proc.name + ' (' + proc.slug + ')';
                            if (proc.parent_id) {
                                formattedDetails += ' <em>- child procedure</em>';
                            } else {
                                formattedDetails += ' <em>- parent category</em>';
                            }
                            formattedDetails += '</li>';
                        });
                        formattedDetails += '</ul>';
                        formattedDetails += '</div>';
                    }

                    if (parsedDetails.details.updated_procedures && parsedDetails.details.updated_procedures.length > 0) {
                        formattedDetails += '<div class="sync-details-updated">';
                        formattedDetails += '<h4>Updated Procedures (' + parsedDetails.details.updated_procedures.length + ')</h4>';
                        formattedDetails += '<ul>';
                        parsedDetails.details.updated_procedures.forEach(proc => {
                            formattedDetails += '<li>' + proc.name + ' (' + proc.slug + ')';
                            if (proc.parent_id) {
                                formattedDetails += ' <em>- child procedure</em>';
                            } else {
                                formattedDetails += ' <em>- parent category</em>';
                            }
                            formattedDetails += '</li>';
                        });
                        formattedDetails += '</ul>';
                        formattedDetails += '</div>';
                    }
                }

                // Add raw JSON for debugging
                formattedDetails += '<div class="sync-details-raw">';
                formattedDetails += '<h4>Raw Data</h4>';
                formattedDetails += '<pre>' + JSON.stringify(parsedDetails, null, 2) + '</pre>';
                formattedDetails += '</div>';

            } catch (error) {
                // If parsing fails, show raw details
                formattedDetails = '<div class="sync-details-error">';
                formattedDetails += '<h4>Details (Raw)</h4>';
                formattedDetails += '<pre>' + details + '</pre>';
                formattedDetails += '</div>';
            }

            // Create modal dialog
            this.createModal('Sync Details', formattedDetails);
        }

        /**
         * Create a modal dialog
         */
        createModal(title, content) {
            // Create modal elements
            const modal = document.createElement('div');
            modal.className = 'sync-details-modal';

            const overlay = document.createElement('div');
            overlay.className = 'sync-details-overlay';

            const modalContent = document.createElement('div');
            modalContent.className = 'sync-details-content';

            const header = document.createElement('div');
            header.className = 'sync-details-header';

            const titleElement = document.createElement('h3');
            titleElement.textContent = title;

            const closeButton = document.createElement('button');
            closeButton.className = 'sync-details-close';
            closeButton.innerHTML = '&times;';

            const body = document.createElement('div');
            body.className = 'sync-details-body';
            body.innerHTML = content;

            // Assemble modal
            header.appendChild(titleElement);
            header.appendChild(closeButton);
            modalContent.appendChild(header);
            modalContent.appendChild(body);
            modal.appendChild(overlay);
            modal.appendChild(modalContent);

            // Add modal styles if not already present
            if (!document.getElementById('sync-modal-styles')) {
                const style = document.createElement('style');
                style.id = 'sync-modal-styles';
                style.textContent = `
                    .sync-details-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 999999; }
                    .sync-details-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
                    .sync-details-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-width: 90%; max-height: 90%; }
                    .sync-details-header { padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
                    .sync-details-header h3 { margin: 0; }
                    .sync-details-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
                    .sync-details-close:hover { color: #000; }
                    .sync-details-body { padding: 20px; overflow: auto; max-height: 70vh; }
                    .sync-details-body h4 { margin: 20px 0 10px 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
                    .sync-details-body h4:first-child { margin-top: 0; }
                    .sync-details-body ul { margin: 0 0 15px 20px; }
                    .sync-details-body li { margin: 5px 0; }
                    .sync-details-summary { margin-bottom: 20px; }
                    .sync-details-errors { margin-bottom: 20px; }
                    .sync-details-errors h4 { color: #d63638; }
                    .sync-details-created { margin-bottom: 20px; }
                    .sync-details-created h4 { color: #00a32a; }
                    .sync-details-updated { margin-bottom: 20px; }
                    .sync-details-updated h4 { color: #0073aa; }
                    .sync-details-raw { margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; }
                    .sync-details-body pre { background: #f9f9f9; padding: 15px; border-radius: 4px; overflow: auto; white-space: pre-wrap; word-wrap: break-word; font-size: 12px; }
                `;
                document.head.appendChild(style);
            }

            // Add to page
            document.body.appendChild(modal);

            // Close modal function
            const closeModal = () => {
                modal.remove();
            };

            // Bind close events
            closeButton.addEventListener('click', closeModal);
            overlay.addEventListener('click', closeModal);

            // Close on escape key
            const handleKeyPress = (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                    document.removeEventListener('keydown', handleKeyPress);
                }
            };
            document.addEventListener('keydown', handleKeyPress);
        }

        /**
         * Build results HTML from sync response
         */
        buildResultsHtml(data) {
            let html = '';

            // Check for success in data or assume success if we got here from success handler
            const isSuccess = data.success !== false;

            if (isSuccess) {
                html += '<div class="brag-book-gallery-notice brag-book-gallery-notice--success">';
                html += '<h4>✓ Sync Completed Successfully</h4>';
                html += '<p><strong>Procedures Created:</strong> ' + (data.created || 0) + '</p>';
                html += '<p><strong>Procedures Updated:</strong> ' + (data.updated || 0) + '</p>';

                // Include case information if present
                if (data.cases_created !== undefined) {
                    html += '<p><strong>Cases Created:</strong> ' + (data.cases_created || 0) + '</p>';
                }
                if (data.cases_updated !== undefined) {
                    html += '<p><strong>Cases Updated:</strong> ' + (data.cases_updated || 0) + '</p>';
                }
                if (data.total_cases_processed !== undefined) {
                    html += '<p><strong>Total Cases Processed:</strong> ' + (data.total_cases_processed || 0) + '</p>';
                }

                if (data.message) {
                    html += '<p><strong>Details:</strong> ' + data.message + '</p>';
                }

                if (data.errors && data.errors.length > 0) {
                    html += '<h5>Warnings:</h5>';
                    html += '<ul>';
                    data.errors.forEach(error => {
                        html += '<li>' + error + '</li>';
                    });
                    html += '</ul>';
                }

                html += '</div>';
            } else {
                html += '<div class="brag-book-gallery-notice brag-book-gallery-notice--error">';
                html += '<h4>✗ Sync Failed</h4>';

                if (data.message) {
                    html += '<p>' + data.message + '</p>';
                }

                if (data.errors && data.errors.length > 0) {
                    html += '<h5>Errors:</h5>';
                    html += '<ul>';
                    data.errors.forEach(error => {
                        html += '<li>' + error + '</li>';
                    });
                    html += '</ul>';
                }

                html += '</div>';
            }

            return html;
        }

        /**
         * Simulate progress with two progress bars
         */
        simulateProgress(overallFill, currentFill, overallPercentage, currentPercentage, currentOperation, progressItems, isFullSync = false) {
            // Shorter initial simulation - switch to real progress sooner
            const steps = [
                { overall: 5, current: 100, operation: 'Connecting to BRAGBook API...', delay: 500 },
                { overall: 15, current: 30, operation: 'Fetching procedure data from sidebar...', delay: 800 },
                { overall: 25, current: 80, operation: 'Creating procedure taxonomies...', delay: 600 },
                { overall: 35, current: 100, operation: 'Stage 1 complete - Starting case sync...', delay: 500, transition: true }
            ];

            let currentStep = 0;
            let isPollingProgress = false;

            const executeStep = () => {
                if (currentStep < steps.length) {
                    const step = steps[currentStep];

                    // Update both progress bars
                    overallFill.style.width = step.overall + '%';
                    currentFill.style.width = step.current + '%';

                    // Update percentages
                    overallPercentage.textContent = step.overall + '%';
                    currentPercentage.textContent = step.current + '%';

                    // Update current operation
                    currentOperation.textContent = step.operation;

                    // Add progress item
                    this.addProgressItem(progressItems, `${step.operation} (${step.overall}%)`, 'info');

                    currentStep++;

                    // Check if this is the transition point for detailed progress
                    if (step.transition && !isPollingProgress) {
                        isPollingProgress = true;
                        // Start polling for real detailed progress
                        setTimeout(() => {
                            this.pollDetailedProgress(overallFill, currentFill, overallPercentage, currentPercentage, currentOperation, progressItems);
                        }, step.delay);
                    } else {
                        setTimeout(executeStep, step.delay);
                    }
                } else if (!isPollingProgress) {
                    // Fallback: start polling if we somehow missed the transition
                    isPollingProgress = true;
                    this.pollDetailedProgress(overallFill, currentFill, overallPercentage, currentPercentage, currentOperation, progressItems);
                }
            };

            // Start the simulation
            setTimeout(executeStep, 200);
        }

        /**
         * Poll for real detailed progress updates
         */
        async pollDetailedProgress(overallFill, currentFill, overallPercentage, currentPercentage, currentOperation, progressItems) {
            const pollInterval = 500; // Poll every 500ms for more responsive updates
            let pollCount = 0;
            const maxPolls = 1200; // 10 minutes maximum (increased timeout)
            let lastProgressUpdate = Date.now();

            const checkProgress = async () => {
                if (pollCount >= maxPolls) {
                    console.log('Progress polling timeout reached');
                    return;
                }

                try {
                    const response = await this.makeAjaxRequest({
                        url: bragBookSync.ajax_url,
                        data: {
                            action: 'brag_book_get_detailed_progress',
                            nonce: bragBookSync.nonce
                        }
                    });

                    console.log('BRAG Book Sync: Detailed progress response:', response);

                    if (response.success && response.data) {
                        const progress = response.data;
                        console.log('BRAG Book Sync: Progress data:', progress);

                        if (progress.stage === 'cases') {
                            console.log('BRAG Book Sync: Updating detailed progress display');
                            lastProgressUpdate = Date.now();

                            // Smooth progress bar updates
                            this.updateProgressBars(overallFill, currentFill, overallPercentage, currentPercentage, progress);

                            // Update current operation with timer
                            const baseOperation = progress.current_step || 'Processing cases...';
                            const elapsed = Math.floor((Date.now() - this.syncStartTime) / 1000);
                            const minutes = Math.floor(elapsed / 60);
                            const seconds = elapsed % 60;
                            const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                            currentOperation.textContent = `${baseOperation} [${timeString}]`;

                            // Add progress item for current operation
                            if (progress.recent_cases && progress.recent_cases.length > 0) {
                                const recentCase = progress.recent_cases[progress.recent_cases.length - 1];
                                this.addProgressItem(progressItems, `✓ ${recentCase} created`, 'info');
                            }

                            // Show detailed progress breakdown
                            if (progress.current_procedure) {
                                this.updateDetailedProgress(progressItems, progress);
                            }

                            // Continue polling if not at 100%
                            if (progress.overall_percentage < 100) {
                                setTimeout(checkProgress, pollInterval);
                            } else {
                                console.log('Detailed progress complete');
                            }
                        } else {
                            console.log('BRAG Book Sync: Progress stage not cases, continuing to poll');
                            // Show that we're waiting for case processing to start
                            const elapsed = Math.floor((Date.now() - this.syncStartTime) / 1000);
                            const minutes = Math.floor(elapsed / 60);
                            const seconds = elapsed % 60;
                            const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                            currentOperation.textContent = `Waiting for case processing to start... [${timeString}]`;

                            setTimeout(checkProgress, pollInterval);
                        }
                    } else {
                        console.log('BRAG Book Sync: No valid progress data, continuing to poll');
                        // Continue polling on error
                        setTimeout(checkProgress, pollInterval);
                    }
                    pollCount++;
                } catch (error) {
                    // Continue polling on AJAX error
                    pollCount++;
                    setTimeout(checkProgress, pollInterval);
                }
            };

            // Start polling
            await checkProgress();
        }

        /**
         * Update progress bars with smooth animations
         */
        updateProgressBars(overallFill, currentFill, overallPercentage, currentPercentage, progress) {
            // Smooth width transitions
            overallFill.style.transition = 'width 0.5s ease';
            currentFill.style.transition = 'width 0.5s ease';

            // Update progress bars
            overallFill.style.width = progress.overall_percentage + '%';
            currentFill.style.width = progress.case_progress.percentage + '%';

            // Update percentages
            overallPercentage.textContent = progress.overall_percentage + '%';
            currentPercentage.textContent = progress.case_progress.percentage + '%';

            // Add visual feedback for active progress
            if (progress.overall_percentage > 0) {
                overallFill.style.boxShadow = '0 0 10px rgba(33, 150, 243, 0.5)';
                currentFill.style.boxShadow = '0 0 8px rgba(51, 51, 51, 0.5)';
            }
        }

        /**
         * Update detailed progress display with multiple progress bars
         */
        updateDetailedProgress(progressItems, progress) {
            // Clear existing detailed progress
            const existingDetailedProgress = progressItems.querySelector('.detailed-progress');
            if (existingDetailedProgress) {
                existingDetailedProgress.remove();
            }

            // Create detailed progress container
            const detailedContainer = document.createElement('li');
            detailedContainer.className = 'detailed-progress brag-book-gallery-progress-item brag-book-gallery-progress-item--info';

            // Procedure progress section
            const procedureSection = document.createElement('div');
            procedureSection.className = 'procedure-progress';
            procedureSection.innerHTML = `
                <div class="progress-header">
                    <strong>Procedure: ${progress.current_procedure} (${progress.procedure_progress.current} of ${progress.procedure_progress.total})</strong>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progress.procedure_progress.percentage}%"></div>
                    </div>
                    <span class="progress-percent">${progress.procedure_progress.percentage}%</span>
                </div>
            `;

            // Case progress section
            const caseSection = document.createElement('div');
            caseSection.className = 'case-progress';
            caseSection.style.marginTop = '10px';
            caseSection.innerHTML = `
                <div class="progress-header">
                    <strong>Cases: ${progress.case_progress.current} of ${progress.case_progress.total}</strong>
                    <span class="current-step">${progress.current_step}</span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progress.case_progress.percentage}%"></div>
                    </div>
                    <span class="progress-percent">${progress.case_progress.percentage}%</span>
                </div>
            `;

            // Recent cases section
            if (progress.recent_cases && progress.recent_cases.length > 0) {
                const recentCases = document.createElement('div');
                recentCases.className = 'recent-cases';
                recentCases.style.marginTop = '10px';
                recentCases.innerHTML = `
                    <div class="recent-cases-header">Recently Created:</div>
                    <div class="recent-cases-list">${progress.recent_cases.join(', ')}</div>
                `;
                caseSection.appendChild(recentCases);
            }

            detailedContainer.appendChild(procedureSection);
            detailedContainer.appendChild(caseSection);
            progressItems.appendChild(detailedContainer);

            // Scroll to bottom
            progressItems.scrollTop = progressItems.scrollHeight;
        }

        /**
         * Add progress item to the list
         */
        addProgressItem(container, text, type) {
            const icon = type === 'success' ? '✓' :
                        type === 'error' ? '✗' :
                        type === 'warning' ? '⚠' : '•';

            const className = 'brag-book-gallery-progress-item brag-book-gallery-progress-item--' + type;

            const item = document.createElement('li');
            item.className = className;
            item.textContent = icon + ' ' + text;
            container.appendChild(item);

            // Scroll to bottom of list
            container.scrollTop = container.scrollHeight;
        }

        /**
         * Start sync timer display
         */
        startSyncTimer(currentOperation) {
            this.timerInterval = setInterval(() => {
                if (this.syncInProgress) {
                    const elapsed = Math.floor((Date.now() - this.syncStartTime) / 1000);
                    const minutes = Math.floor(elapsed / 60);
                    const seconds = elapsed % 60;
                    const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`;

                    // Update timer in the current operation display
                    const baseOperation = currentOperation.textContent.replace(/ \[\d+:\d+\]$/, '');
                    currentOperation.textContent = `${baseOperation} [${timeString}]`;
                }
            }, 1000);
        }

        /**
         * Stop sync timer
         */
        stopSyncTimer() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        }

        /**
         * Add visual activity indicator to progress bars
         */
        addActivityIndicator(overallFill, currentFill) {
            // Add pulsing animation class
            overallFill.classList.add('sync-active');
            currentFill.classList.add('sync-active');

            // Add CSS if not already present
            if (!document.getElementById('sync-activity-styles')) {
                const style = document.createElement('style');
                style.id = 'sync-activity-styles';
                style.textContent = `
                    .sync-active {
                        animation: sync-pulse 2s ease-in-out infinite;
                        position: relative;
                    }
                    .sync-active::after {
                        content: '';
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                        animation: sync-shimmer 1.5s ease-in-out infinite;
                    }
                    @keyframes sync-pulse {
                        0%, 100% { opacity: 1; }
                        50% { opacity: 0.8; }
                    }
                    @keyframes sync-shimmer {
                        0% { transform: translateX(-100%); }
                        100% { transform: translateX(100%); }
                    }
                `;
                document.head.appendChild(style);
            }
        }

        /**
         * Remove activity indicator
         */
        removeActivityIndicator(overallFill, currentFill) {
            overallFill.classList.remove('sync-active');
            currentFill.classList.remove('sync-active');
        }

        /**
         * Initialize frequency toggle functionality
         */
        initFrequencyToggle() {
            // Get all frequency radio buttons
            const frequencyRadios = document.querySelectorAll('.sync-frequency-radio');
            const customSchedule = document.querySelector('.sync-custom-schedule');

            if (!customSchedule) return;

            // Function to toggle custom schedule visibility
            const toggleCustomSchedule = () => {
                const customRadio = document.querySelector('.sync-frequency-radio[value="custom"]');
                if (customRadio && customRadio.checked) {
                    customSchedule.style.display = 'block';
                } else {
                    customSchedule.style.display = 'none';
                }
            };

            // Add event listeners to all frequency radio buttons
            frequencyRadios.forEach(radio => {
                radio.addEventListener('change', toggleCustomSchedule);
            });

            // Initialize on page load
            toggleCustomSchedule();
        }

        /**
         * Make AJAX request using fetch API
         */
        async makeAjaxRequest({ url, data }) {
            const formData = new FormData();
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        new SyncAdmin();
    });

})();