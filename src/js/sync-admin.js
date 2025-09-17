/**
 * BRAG book Sync Admin JavaScript
 *
 * Handles sync page functionality including:
 * - Manual sync operations
 * - Progress tracking and display
 * - Sync history management
 * - AJAX communication for sync operations
 *
 * @package BRAGBook
 * @since   3.0.0
 */

'use strict';

/**
 * BRAG book Sync Admin Controller Class
 * Manages sync page interactions and AJAX communications
 */
if (typeof window.BRAGbookSyncAdmin === 'undefined') {
	window.BRAGbookSyncAdmin = class {
		/**
		 * Initialize the sync admin interface controller
		 */
		constructor() {
			// Get localized data from PHP
			this.config = typeof bragBookSync !== 'undefined' ? bragBookSync : {};

			this.ajaxUrl = this.config.ajax_url || '/wp-admin/admin-ajax.php';
			this.nonces = {
				sync: this.config.sync_nonce || '',
				general: this.config.nonce || '',
				testAuto: this.config.test_auto_nonce || '',
				clearLog: this.config.clear_log_nonce || '',
				delete: this.config.delete_nonce || ''
			};
			this.messages = this.config.messages || {};

			// Sync state tracking
			this.syncInProgress = false;
			this.progressTimer = null;
			this.syncStartTime = null;

			// Initialize when DOM is ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', () => this.init());
			} else {
				this.init();
			}
		}

		/**
		 * Initialize all event listeners and UI components
		 */
		init() {
			console.log('BRAGbook Sync Admin: Initializing...');
			console.log('BRAGbook Sync Admin: Config:', this.config);
			this.bindSyncControls();
			this.bindHistoryControls();
			this.bindProgressHandlers();

			// Check for existing sync on page load
			this.checkExistingSync();

			console.log('BRAGbook Sync Admin: Initialization complete');
		}

		/**
		 * Bind sync control event listeners
		 */
		bindSyncControls() {
			console.log('BRAGbook Sync Admin: Binding sync controls...');

			// Main sync button
			const syncBtn = document.getElementById('sync-procedures-btn');
			console.log('BRAGbook Sync Admin: Sync button found:', !!syncBtn);
			if (syncBtn) {
				syncBtn.addEventListener('click', () => this.startFullSync());
			}

			// Stop sync button
			const stopBtn = document.getElementById('stop-sync-btn');
			console.log('BRAGbook Sync Admin: Stop button found:', !!stopBtn);
			if (stopBtn) {
				stopBtn.addEventListener('click', () => this.stopSync());
			}

			// Clear sync log button
			const clearLogBtn = document.getElementById('clear-sync-log-btn');
			if (clearLogBtn) {
				clearLogBtn.addEventListener('click', () => this.clearSyncLog());
			}
		}

		/**
		 * Bind history control event listeners
		 */
		bindHistoryControls() {
			// View details buttons
			document.querySelectorAll('.view-details').forEach(btn => {
				btn.addEventListener('click', (e) => {
					const details = e.target.getAttribute('data-details');
					this.showSyncDetails(details);
				});
			});

			// Delete record buttons
			document.querySelectorAll('.delete-sync-record').forEach(btn => {
				btn.addEventListener('click', (e) => {
					const recordId = e.target.getAttribute('data-record-id');
					const recordDate = e.target.getAttribute('data-record-date');
					this.deleteSyncRecord(recordId, recordDate);
				});
			});
		}

		/**
		 * Bind progress tracking handlers
		 */
		bindProgressHandlers() {
			// Progress monitoring will be handled by polling during sync
		}

		/**
		 * Check for existing sync progress on page load
		 */
		async checkExistingSync() {
			console.log('BRAGbook Sync Admin: Checking for existing sync...');

			try {
				const formData = new FormData();
				formData.append('action', 'brag_book_get_detailed_progress');
				formData.append('nonce', this.nonces.general);

				const response = await fetch(this.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const result = await response.json();

				if (result.success && result.data.stage !== 'idle') {
					console.log('BRAGbook Sync Admin: Found existing sync in progress, resuming...');
					console.log('BRAGbook Sync Admin: Sync state:', result.data);

					// Update syncs in progress section
					this.updateSyncsInProgress([result.data]);

					// Resume the sync display
					this.setSyncInProgress(true);
					this.showProgress();

					// Update progress display with current state
					this.updateProgress(
						result.data.current_step,
						result.data.overall_percentage,
						result.data.procedure_progress.percentage,
						result.data.recent_cases || []
					);

					// Show resume notice
					this.showNotice('Resuming sync in progress...', 'info');

				} else {
					console.log('BRAGbook Sync Admin: No active sync found');
					// Update syncs in progress to show no active syncs
					this.updateSyncsInProgress([]);
				}

			} catch (error) {
				console.error('BRAGbook Sync Admin: Error checking existing sync:', error);
				// Don't show error to user as this is just a background check
				this.updateSyncsInProgress([]);
			}
		}

		/**
		 * Start full sync operation
		 */
		async startFullSync() {
			console.log('BRAGbook Sync Admin: Starting full sync...');

			if (this.syncInProgress) {
				console.log('BRAGbook Sync Admin: Sync already in progress, aborting');
				return;
			}

			try {
				console.log('BRAGbook Sync Admin: Setting sync in progress...');
				this.syncStartTime = Date.now(); // Record sync start time
				this.setSyncInProgress(true);
				this.showProgress();

				// Clear any existing log and add startup messages
				const progressItems = document.getElementById('sync-progress-items');
				if (progressItems) {
					progressItems.innerHTML = '';
				}

				// Add detailed startup messages
				this.addProgressLogEntry('Starting full synchronization process', 'info');
				this.addProgressLogEntry('Validating API connection and credentials', 'info');
				this.addProgressLogEntry('Fetching sidebar data for procedures', 'info');

				// Update progress bars
				this.updateProgress('Initializing sync...', 0, 0, []);

				const formData = new FormData();
				formData.append('action', 'brag_book_full_sync');
				formData.append('nonce', this.nonces.sync);

				const response = await fetch(this.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const result = await response.json();
				this.handleSyncResult(result);

			} catch (error) {
				console.error('Sync error:', error);
				this.handleSyncError(error.message);
			}
		}

		/**
		 * Update the syncs in progress section
		 */
		updateSyncsInProgress(activeSyncs) {
			const noActiveSyncs = document.getElementById('no-active-syncs');
			const activeSyncsList = document.getElementById('active-syncs-list');

			if (!noActiveSyncs || !activeSyncsList) {
				console.log('BRAGbook Sync Admin: Syncs in progress elements not found, skipping update');
				return;
			}

			if (activeSyncs.length === 0) {
				// Show "no active syncs" message
				noActiveSyncs.style.display = 'block';
				activeSyncsList.style.display = 'none';
				activeSyncsList.innerHTML = '';
			} else {
				// Show active syncs list
				noActiveSyncs.style.display = 'none';
				activeSyncsList.style.display = 'block';

				// Generate HTML for active syncs
				let html = '';
				activeSyncs.forEach((sync, index) => {
					const syncId = `sync-${index}`;
					const percentage = Math.round(sync.overall_percentage || 0);
					const currentStep = sync.current_step || 'Processing...';

					html += `
						<div class="brag-book-gallery-active-sync" data-sync-id="${syncId}">
							<div class="sync-header">
								<strong>Data Sync in Progress</strong>
								<button type="button" class="button button-secondary button-small stop-individual-sync" data-sync-id="${syncId}">
									Stop Sync
								</button>
							</div>
							<div class="sync-progress-summary">
								<div class="progress-text">
									<span class="current-step">${currentStep}</span>
									<span class="progress-percentage">${percentage}%</span>
								</div>
								<div class="progress-bar-container">
									<div class="progress-bar">
										<div class="progress-fill" style="width: ${percentage}%"></div>
									</div>
								</div>
							</div>
						</div>
					`;
				});

				activeSyncsList.innerHTML = html;

				// Bind stop buttons for individual syncs
				document.querySelectorAll('.stop-individual-sync').forEach(btn => {
					btn.addEventListener('click', () => this.stopSync());
				});
			}
		}

		/**
		 * Stop sync operation
		 */
		async stopSync() {
			try {
				const formData = new FormData();
				formData.append('action', 'brag_book_stop_sync');
				formData.append('nonce', this.nonces.general);

				const response = await fetch(this.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const result = await response.json();

				if (result.success) {
					this.showNotice(result.data.message, 'info');
					this.setSyncInProgress(false);
				} else {
					this.showNotice(result.data.message, 'error');
				}

			} catch (error) {
				console.error('Stop sync error:', error);
				this.showNotice('Failed to stop sync: ' + error.message, 'error');
			}
		}

		/**
		 * Clear sync log
		 */
		async clearSyncLog() {
			if (!confirm(this.messages.confirm_clear_log || 'Are you sure you want to clear the sync log?')) {
				return;
			}

			try {
				const formData = new FormData();
				formData.append('action', 'brag_book_clear_sync_log');
				formData.append('nonce', this.nonces.clearLog);

				const response = await fetch(this.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const result = await response.json();

				if (result.success) {
					this.showNotice(result.data.message, 'success');
					setTimeout(() => window.location.reload(), 1500);
				} else {
					this.showNotice(result.data.message, 'error');
				}

			} catch (error) {
				console.error('Clear log error:', error);
				this.showNotice('Failed to clear sync log: ' + error.message, 'error');
			}
		}


		/**
		 * Delete sync record
		 */
		async deleteSyncRecord(recordId, recordDate) {
			const confirmMsg = this.messages.confirm_delete_record || 'Are you sure you want to delete this sync record?';
			if (!confirm(`${confirmMsg}\n\nRecord: ${recordDate}`)) {
				return;
			}

			try {
				const formData = new FormData();
				formData.append('action', 'brag_book_delete_sync_record');
				formData.append('nonce', this.nonces.delete);
				formData.append('record_id', recordId);

				const response = await fetch(this.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const result = await response.json();

				if (result.success) {
					this.showNotice(result.data.message, 'success');
					setTimeout(() => window.location.reload(), 1500);
				} else {
					this.showNotice(result.data.message, 'error');
				}

			} catch (error) {
				console.error('Delete record error:', error);
				this.showNotice('Failed to delete sync record: ' + error.message, 'error');
			}
		}

		/**
		 * Show sync details in a modal or alert
		 */
		showSyncDetails(detailsJson) {
			try {
				const details = JSON.parse(detailsJson);

				// Check if there's an activity log to display
				if (details.activity_log && typeof details.activity_log === 'object') {
					this.showActivityLogReport(details);
				} else {
					// Fallback to JSON view for older records without activity log
					const formatted = JSON.stringify(details, null, 2);
					alert('Sync Details:\n\n' + formatted);
				}
			} catch (error) {
				alert('Sync Details:\n\n' + detailsJson);
			}
		}

		/**
		 * Show formatted activity log report
		 */
		showActivityLogReport(details) {
			// Create a modal-style dialog
			const modal = document.createElement('div');
			modal.style.cssText = `
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0,0,0,0.8);
				z-index: 10000;
				display: flex;
				align-items: center;
				justify-content: center;
			`;

			const content = document.createElement('div');
			content.style.cssText = `
				background: #fff;
				padding: 20px;
				border-radius: 5px;
				max-width: 80%;
				max-height: 80%;
				overflow-y: auto;
				box-shadow: 0 4px 20px rgba(0,0,0,0.3);
			`;

			// Build the report content
			let reportHtml = '<h2 style="margin-top: 0;">Sync Activity Report</h2>';

			// Summary section
			reportHtml += '<h3>Summary</h3>';
			reportHtml += '<div style="background: #f9f9f9; padding: 10px; border-radius: 3px; margin-bottom: 20px;">';
			reportHtml += `<p><strong>Procedures Created:</strong> ${details.created || 0}</p>`;
			reportHtml += `<p><strong>Procedures Updated:</strong> ${details.updated || 0}</p>`;
			reportHtml += `<p><strong>Cases Created:</strong> ${details.cases_created || 0}</p>`;
			reportHtml += `<p><strong>Cases Updated:</strong> ${details.cases_updated || 0}</p>`;
			reportHtml += `<p><strong>Total Cases Processed:</strong> ${details.total_processed || 0}</p>`;
			if (details.duration) {
				reportHtml += `<p><strong>Duration:</strong> ${details.duration}</p>`;
			}
			reportHtml += '</div>';

			// Activity log section
			if (details.activity_log) {
				reportHtml += '<h3>Activity Log</h3>';
				reportHtml += '<div style="background: #f1f1f1; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;">';

				// Format the current step and progress info
				const log = details.activity_log;
				if (log.current_step) {
					reportHtml += `<div style="margin-bottom: 10px;"><strong>Final Step:</strong> ${log.current_step}</div>`;
				}
				if (log.overall_percentage) {
					reportHtml += `<div style="margin-bottom: 10px;"><strong>Progress:</strong> ${log.overall_percentage}% complete</div>`;
				}
				if (log.updated_at) {
					reportHtml += `<div style="margin-bottom: 10px;"><strong>Completed:</strong> ${log.updated_at}</div>`;
				}

				reportHtml += '</div>';
			}

			// Close button
			reportHtml += '<div style="text-align: right; margin-top: 20px;">';
			reportHtml += '<button id="close-sync-report" style="background: #0073aa; color: white; border: none; padding: 8px 16px; border-radius: 3px; cursor: pointer;">Close</button>';
			reportHtml += '</div>';

			content.innerHTML = reportHtml;
			modal.appendChild(content);
			document.body.appendChild(modal);

			// Add close functionality
			document.getElementById('close-sync-report').addEventListener('click', () => {
				document.body.removeChild(modal);
			});

			// Close on background click
			modal.addEventListener('click', (e) => {
				if (e.target === modal) {
					document.body.removeChild(modal);
				}
			});
		}

		/**
		 * Set sync in progress state
		 */
		setSyncInProgress(inProgress) {
			console.log('BRAGbook Sync Admin: Setting sync in progress:', inProgress);
			this.syncInProgress = inProgress;

			const syncBtn = document.getElementById('sync-procedures-btn');
			const stopBtn = document.getElementById('stop-sync-btn');

			console.log('BRAGbook Sync Admin: Elements found - sync:', !!syncBtn, 'stop:', !!stopBtn);

			if (syncBtn) {
				syncBtn.disabled = inProgress;
				syncBtn.textContent = inProgress ? 'Sync in Progress...' : 'Start Full Sync';
				console.log('BRAGbook Sync Admin: Updated sync button - disabled:', inProgress, 'text:', syncBtn.textContent);
			}

			if (stopBtn) {
				stopBtn.style.display = inProgress ? 'inline-block' : 'none';
				console.log('BRAGbook Sync Admin: Updated stop button display:', stopBtn.style.display);
			}

			if (inProgress) {
				this.startProgressPolling();
			} else {
				this.stopProgressPolling();
			}
		}

		/**
		 * Show progress section
		 */
		showProgress() {
			console.log('BRAGbook Sync Admin: showProgress() called');

			const progressSection = document.getElementById('sync-progress');
			console.log('BRAGbook Sync Admin: Progress section found:', !!progressSection);
			if (progressSection) {
				progressSection.style.display = 'block';
			}

			// Also show the progress details section
			const progressDetails = document.getElementById('sync-progress-details');
			console.log('BRAGbook Sync Admin: Progress details found:', !!progressDetails);
			if (progressDetails) {
				progressDetails.style.display = 'block';
				console.log('BRAGbook Sync Admin: Progress details shown');
			}
		}

		/**
		 * Hide progress section
		 */
		hideProgress() {
			const progressSection = document.getElementById('sync-progress');
			if (progressSection) {
				progressSection.style.display = 'none';
			}

			// Also hide the progress details section
			const progressDetails = document.getElementById('sync-progress-details');
			if (progressDetails) {
				progressDetails.style.display = 'none';
			}
		}

		/**
		 * Update progress display
		 */
		updateProgress(message, overall, current, recentCases = []) {
			const overallFill = document.getElementById('sync-overall-fill');
			const overallPercentage = document.getElementById('sync-overall-percentage');
			const currentOperation = document.getElementById('sync-current-operation');
			const currentFill = document.getElementById('sync-current-fill');
			const currentPercentage = document.getElementById('sync-current-percentage');

			if (overallFill) {
				overallFill.style.width = overall + '%';
			}
			if (overallPercentage) {
				// Add elapsed time to overall percentage display
				let percentageText = Math.round(overall) + '%';
				if (this.syncStartTime && overall > 0) {
					const elapsed = Math.floor((Date.now() - this.syncStartTime) / 1000);
					const minutes = Math.floor(elapsed / 60);
					const seconds = elapsed % 60;
					const timeStr = minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;
					percentageText += ` (${timeStr})`;
				}
				overallPercentage.textContent = percentageText;
			}
			if (currentOperation) {
				currentOperation.textContent = message;
			}
			if (currentFill) {
				currentFill.style.width = current + '%';
			}
			if (currentPercentage) {
				currentPercentage.textContent = Math.round(current) + '%';
			}

			// Update progress details log
			this.updateProgressLog(recentCases);
		}

		/**
		 * Set progress bars to completed state (green color)
		 */
		setProgressBarsCompleted() {
			const overallFill = document.getElementById('sync-overall-fill');
			const currentFill = document.getElementById('sync-current-fill');

			// Change progress bars to green to indicate completion
			if (overallFill) {
				overallFill.style.backgroundColor = '#00a32a'; // WordPress success green
				overallFill.style.transition = 'background-color 0.3s ease';
			}
			if (currentFill) {
				currentFill.style.backgroundColor = '#00a32a'; // WordPress success green
				currentFill.style.transition = 'background-color 0.3s ease';
			}

			// Also update any progress bars in the syncs in progress section
			document.querySelectorAll('.progress-fill').forEach(fill => {
				fill.style.backgroundColor = '#00a32a';
				fill.style.transition = 'background-color 0.3s ease';
			});
		}

		/**
		 * Format stage labels for better readability
		 */
		formatStageLabel(stage) {
			switch (stage) {
				case 'procedures':
					return 'Procedure Synchronization';
				case 'cases':
					return 'Case Creation';
				case 'completed':
					return 'Sync Completed';
				case 'idle':
					return 'Idle';
				default:
					return stage.charAt(0).toUpperCase() + stage.slice(1);
			}
		}

		/**
		 * Add a single entry to the progress log (keeps running history)
		 */
		addProgressLogEntry(message, type = 'success') {
			const progressItems = document.getElementById('sync-progress-items');
			if (!progressItems) {
				console.error('BRAGbook Sync Admin: Progress items element not found!');
				return;
			}

			const li = document.createElement('li');
			li.className = `sync-log-item sync-log-item--${type}`;

			// Add timestamp to entries
			const timestamp = new Date().toLocaleTimeString();
			li.textContent = `[${timestamp}] ${message}`;

			// Style based on type
			switch (type) {
				case 'success':
					li.style.cssText = 'padding: 5px 10px; margin: 1px 0; background: #fff; border-left: 3px solid #00a32a; font-size: 11px; color: #333;';
					break;
				case 'info':
					li.style.cssText = 'padding: 5px 10px; margin: 1px 0; background: #f0f0f1; border-left: 3px solid #72aee6; font-size: 11px; color: #555;';
					break;
				case 'warning':
					li.style.cssText = 'padding: 5px 10px; margin: 1px 0; background: #fff8e1; border-left: 3px solid #ff9800; font-size: 11px; color: #333;';
					break;
				case 'error':
					li.style.cssText = 'padding: 5px 10px; margin: 1px 0; background: #ffebee; border-left: 3px solid #f44336; font-size: 11px; color: #333;';
					break;
			}

			progressItems.appendChild(li);

			// Auto-scroll to bottom to show latest entries
			progressItems.scrollTop = progressItems.scrollHeight;

			console.log('BRAGbook Sync Admin: Added progress log entry:', message);
		}

		/**
		 * Update the progress log with recent cases (enhanced version)
		 */
		updateProgressLog(recentCases) {
			console.log('BRAGbook Sync Admin: updateProgressLog() called with:', recentCases);

			// Add recent cases as individual entries (don't clear existing log)
			if (recentCases && recentCases.length > 0) {
				console.log('BRAGbook Sync Admin: Adding', recentCases.length, 'recent cases to log');
				recentCases.forEach(caseInfo => {
					this.addProgressLogEntry(`✓ ${caseInfo}`, 'success');
				});
			}
		}

		/**
		 * Start progress polling
		 */
		startProgressPolling() {
			if (this.progressTimer) {
				clearInterval(this.progressTimer);
			}

			let lastStage = '';
			let lastStep = '';
			let lastProcedure = '';
			let lastOverallPercentage = 0;

			this.progressTimer = setInterval(async () => {
				try {
					const formData = new FormData();
					formData.append('action', 'brag_book_get_detailed_progress');
					formData.append('nonce', this.nonces.general);

					const response = await fetch(this.ajaxUrl, {
						method: 'POST',
						body: formData
					});

					const result = await response.json();

					if (result.success && result.data.stage !== 'idle') {
						const data = result.data;
						console.log('BRAGbook Sync Admin: Progress polling received data:', data);

						// Check if this is the completion stage
						if (data.stage === 'completed') {
							this.addProgressLogEntry('🎉 Synchronization completed successfully!', 'success');

							// Update progress bars to 100% with completion styling
							this.updateProgress(
								data.current_step,
								data.overall_percentage,
								data.procedure_progress.percentage,
								data.recent_cases || []
							);

							// Apply completion styling to progress bars
							this.setProgressBarsCompleted();

							// Stop polling and update UI
							this.setSyncInProgress(false);
							this.updateSyncsInProgress([]);
							return;
						}

						// Log stage changes with improved formatting
						if (lastStage !== data.stage) {
							const stageLabel = this.formatStageLabel(data.stage);
							this.addProgressLogEntry(`[STAGE] ${stageLabel}`, 'info');
							lastStage = data.stage;
						}

						// Log step changes
						if (lastStep !== data.current_step) {
							this.addProgressLogEntry(`⏳ ${data.current_step}`, 'info');
							lastStep = data.current_step;
						}

						// Log procedure changes
						if (data.current_procedure && lastProcedure !== data.current_procedure) {
							this.addProgressLogEntry(`📋 Processing procedure: ${data.current_procedure}`, 'info');
							lastProcedure = data.current_procedure;
						}

						// Log taxonomy mapping issues if present
						if (data.taxonomy_mapping_issues && data.taxonomy_mapping_issues.length > 0) {
							data.taxonomy_mapping_issues.forEach(issue => {
								this.addProgressLogEntry(`Taxonomy: ${issue}`, 'warning');
							});
						}

						// Log significant progress jumps
						const overallDiff = data.overall_percentage - lastOverallPercentage;
						if (overallDiff >= 5) { // Log every 5% increase
							this.addProgressLogEntry(`Overall progress: ${Math.round(data.overall_percentage)}%`, 'info');
							lastOverallPercentage = data.overall_percentage;
						}

						// Log errors or warnings
						if (data.errors && data.errors.length > 0) {
							data.errors.forEach(error => {
								this.addProgressLogEntry(`Error: ${error}`, 'error');
							});
						}

						if (data.warnings && data.warnings.length > 0) {
							data.warnings.forEach(warning => {
								this.addProgressLogEntry(`Warning: ${warning}`, 'warning');
							});
						}

						// Update main progress display
						this.updateProgress(
							data.current_step,
							data.overall_percentage,
							data.procedure_progress.percentage,
							data.recent_cases || []
						);

						// Update syncs in progress section
						this.updateSyncsInProgress([data]);
					} else if (result.success && result.data.stage === 'idle') {
						this.addProgressLogEntry('Synchronization completed successfully', 'success');

						// Force both progress bars to 100% before completion
						this.updateProgress('Sync Completed', 100, 100, []);
						this.setProgressBarsCompleted();

						// Sync completed
						this.setSyncInProgress(false);
						this.updateSyncsInProgress([]);
					}

				} catch (error) {
					console.error('Progress polling error:', error);
					this.addProgressLogEntry(`Polling error: ${error.message}`, 'error');
				}
			}, 1500); // Poll more frequently for better responsiveness
		}

		/**
		 * Stop progress polling
		 */
		stopProgressPolling() {
			if (this.progressTimer) {
				clearInterval(this.progressTimer);
				this.progressTimer = null;
			}
		}

		/**
		 * Handle sync result
		 */
		handleSyncResult(result) {
			if (result.success) {
				this.addProgressLogEntry('🎉 Sync completed successfully!', 'success');

				// Force both progress bars to 100% and set completion styling
				this.updateProgress('Sync Completed', 100, 100, []);
				this.setProgressBarsCompleted();

				// Log final statistics
				if (result.data) {
					const data = result.data;
					this.addProgressLogEntry(`Final Statistics:`, 'info');
					this.addProgressLogEntry(`   • Procedures Created: ${data.created || 0}`, 'success');
					this.addProgressLogEntry(`   • Procedures Updated: ${data.updated || 0}`, 'success');
					this.addProgressLogEntry(`   • Cases Created: ${data.cases_created || 0}`, 'success');
					this.addProgressLogEntry(`   • Cases Updated: ${data.cases_updated || 0}`, 'success');
					if (data.duration) {
						this.addProgressLogEntry(`   • Total Duration: ${data.duration}`, 'info');
					}
				}

				this.showNotice(this.messages.sync_complete || 'Sync completed successfully!', 'success');
				this.showResults(result.data);
				setTimeout(() => window.location.reload(), 5000);
			} else {
				this.handleSyncError(result.data?.message || 'Unknown error occurred');
			}

			this.setSyncInProgress(false);
		}

		/**
		 * Handle sync error
		 */
		handleSyncError(message) {
			this.setSyncInProgress(false);
			this.addProgressLogEntry(`💥 Sync failed: ${message}`, 'error');
			this.showNotice(this.messages.sync_error + ' ' + message, 'error');
		}

		/**
		 * Show sync results
		 */
		showResults(data) {
			const resultsSection = document.getElementById('sync-results');
			const resultsContent = document.getElementById('sync-results-content');

			if (resultsSection && resultsContent) {
				let html = '<div class="brag-book-gallery-results">';
				html += `<p><strong>Procedures Created:</strong> ${data.created || 0}</p>`;
				html += `<p><strong>Procedures Updated:</strong> ${data.updated || 0}</p>`;
				html += `<p><strong>Cases Created:</strong> ${data.cases_created || 0}</p>`;
				html += `<p><strong>Cases Updated:</strong> ${data.cases_updated || 0}</p>`;
				if (data.duration) {
					html += `<p><strong>Duration:</strong> ${data.duration}</p>`;
				}
				html += '</div>';

				resultsContent.innerHTML = html;
				resultsSection.style.display = 'block';
			}
		}

		/**
		 * Show notification message
		 */
		showNotice(message, type = 'info') {
			const noticesContainer = document.querySelector('.brag-book-gallery-notices');
			if (!noticesContainer) {
				console.log(type.toUpperCase() + ': ' + message);
				return;
			}

			const notice = document.createElement('div');
			notice.className = `notice notice-${type} is-dismissible`;
			notice.innerHTML = `<p>${message}</p>`;

			noticesContainer.appendChild(notice);

			// Auto-remove after 5 seconds
			setTimeout(() => {
				if (notice.parentNode) {
					notice.parentNode.removeChild(notice);
				}
			}, 5000);
		}
	};

	// Initialize when the script loads
	console.log('BRAGbook Sync Admin: Script loaded, creating instance...');
	new window.BRAGbookSyncAdmin();
}