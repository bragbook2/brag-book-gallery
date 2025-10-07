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
			this.bindSyncControls();
			this.bindHistoryControls();
			this.bindProgressHandlers();

			// Setup auto-dismiss for notices
			this.setupNoticeObserver();

			// Setup exit intent warning
			this.setupExitWarning();

			// Check for existing sync on page load
			this.checkExistingSync();
		}

		/**
		 * Bind sync control event listeners
		 */
		bindSyncControls() {
				// Legacy sync buttons removed - using Stage-Based Sync instead
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
					const recordId = e.target.getAttribute('data-sync-id');
					// Try to get the date from the row
					const row = e.target.closest('tr');
					const dateCell = row ? row.querySelector('td:first-child') : null;
					const recordDate = dateCell ? dateCell.textContent.trim() : 'this sync record';
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

				} else {
					// Update syncs in progress to show no active syncs
					this.updateSyncsInProgress([]);
					// Ensure sync in progress flag is false
					this.setSyncInProgress(false);
				}

			} catch (error) {
				console.error('BRAGbook Sync Admin: Error checking existing sync:', error);
				// Don't show error to user as this is just a background check
				this.updateSyncsInProgress([]);
				// Ensure sync in progress flag is false on error
				this.setSyncInProgress(false);
			}
		}

		/**
		 * Resume sync operation after pause
		 */
		async resumeSync() {

			try {
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
				console.error('Resume sync error:', error);
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
				formData.append('sync_id', recordId); // Changed from record_id to sync_id

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
			this.syncInProgress = inProgress;

			// Legacy sync button updates removed - using Stage-Based Sync instead

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
			const progressSection = document.getElementById('sync-progress');
			if (progressSection) {
				progressSection.style.display = 'block';
			}

			// Also show the progress details section
			const progressDetails = document.getElementById('sync-progress-details');
			if (progressDetails) {
				progressDetails.style.display = 'block';
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
			const statusText = document.getElementById('sync-status-text');
			const timeElapsed = document.getElementById('sync-time-elapsed');



			if (overallFill) {
				overallFill.style.width = overall + '%';
				// Add data-percentage attribute for CSS display
				overallFill.setAttribute('data-percentage', Math.round(overall) + '%');
			}
			if (overallPercentage) {
				// Just show percentage without time
				overallPercentage.textContent = Math.round(overall) + '%';
			}

			// Always update time and resource monitoring
			// Use real data from server if available, otherwise calculate locally
			const elapsed = this.syncStartTime ? Math.floor((Date.now() - this.syncStartTime) / 1000) : 0;

			if (timeElapsed) {
				const minutes = Math.floor(elapsed / 60);
				const seconds = elapsed % 60;
				let timeStr = 'Time: ';
				if (minutes > 0) {
					timeStr += `${minutes}m ${seconds}s`;
				} else {
					timeStr += `${seconds}s`;
				}
				timeElapsed.textContent = timeStr;
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

			// Update status text based on progress
			if (statusText) {
				if (overall === 100) {
					statusText.textContent = 'Completed';
					statusText.style.color = '#22c55e'; // Green for completed
				} else if (overall > 0) {
					statusText.textContent = 'In Progress';
					statusText.style.color = '#D94540'; // Brand red for in progress
				} else {
					statusText.textContent = 'Ready';
					statusText.style.color = '#1e293b'; // Default dark color
				}
			}

			// Update sync progress overview class for state-based styling
			const progressOverview = document.querySelector('.sync-progress-overview');
			if (progressOverview) {
				progressOverview.classList.remove('sync-in-progress', 'sync-completed', 'sync-error');
				if (overall === 100) {
					progressOverview.classList.add('sync-completed');
				} else if (overall > 0) {
					progressOverview.classList.add('sync-in-progress');
				}
			}



			// Update sync status badge
			const statusBadge = document.querySelector('.sync-status-badge');
			if (statusBadge) {
				if (overall === 100) {
					statusBadge.className = 'sync-status-badge completed';
					statusBadge.innerHTML = '<span class="status-indicator"></span>Completed';
				} else if (overall > 0) {
					statusBadge.className = 'sync-status-badge syncing';
					statusBadge.innerHTML = '<span class="status-indicator active"></span>Syncing';
				}
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
				// Legacy element not found - silently skip
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
		}

		/**
		 * Update the progress log with recent cases (enhanced version)
		 */
		updateProgressLog(recentCases) {
			// Add recent cases as individual entries (don't clear existing log)
			if (recentCases && recentCases.length > 0) {
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
				// Check if sync needs to resume (paused for resource limits)
				if (result.data && result.data.needs_resume) {
					this.addProgressLogEntry('⏸ Sync paused to prevent timeout - resuming...', 'warning');

					// Update progress display
					const progress = result.data.progress || 0;
					this.updateProgress(result.data.message || 'Resuming sync...', progress, progress, []);

					// Log current stats
					if (result.data.created > 0 || result.data.updated > 0) {
						this.addProgressLogEntry(`Progress so far:`, 'info');
						if (result.data.created > 0) this.addProgressLogEntry(`   • Procedures Created: ${result.data.created}`, 'info');
						if (result.data.updated > 0) this.addProgressLogEntry(`   • Procedures Updated: ${result.data.updated}`, 'info');
						if (result.data.cases_created > 0) this.addProgressLogEntry(`   • Cases Created: ${result.data.cases_created}`, 'info');
						if (result.data.cases_updated > 0) this.addProgressLogEntry(`   • Cases Updated: ${result.data.cases_updated}`, 'info');
					}

					// Wait a moment then resume sync automatically
					setTimeout(() => {
						this.addProgressLogEntry('↻ Continuing sync...', 'info');
						this.resumeSync();
					}, 2000);

					// Keep sync in progress
					return;
				}

				// Normal completion
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

			// Auto-dismiss after 5 seconds
			this.autoDismissNotice(notice);
		}

		/**
		 * Auto-dismiss a notice after 5 seconds with fade effect
		 */
		autoDismissNotice(notice) {
			setTimeout(() => {
				if (notice.parentNode) {
					notice.style.transition = 'opacity 0.3s ease';
					notice.style.opacity = '0';
					setTimeout(() => {
						if (notice.parentNode) {
							notice.parentNode.removeChild(notice);
						}
					}, 300);
				}
			}, 5000);
		}

		/**
		 * Setup notice auto-dismiss observer for dynamically added notices
		 */
		setupNoticeObserver() {
			const noticesContainer = document.querySelector('.brag-book-gallery-notices');
			if (!noticesContainer) {
				return;
			}

			// Auto-dismiss any existing notices on page load
			noticesContainer.querySelectorAll('.notice').forEach(notice => {
				this.autoDismissNotice(notice);
			});

			// Watch for new notices being added
			const observer = new MutationObserver((mutations) => {
				mutations.forEach((mutation) => {
					mutation.addedNodes.forEach((node) => {
						if (node.nodeType === 1 && node.classList && node.classList.contains('notice')) {
							this.autoDismissNotice(node);
						}
					});
				});
			});

			observer.observe(noticesContainer, {
				childList: true,
				subtree: true
			});
		}

		/**
		 * Setup exit warning when sync is in progress
		 */
		setupExitWarning() {
			// Verify wrap element exists
			const wrap = document.querySelector('.brag-book-gallery-admin-wrap');
			if (!wrap) {
				return;
			}

			// Create exit warning dialog
			this.createExitWarningDialog();

			// Bind beforeunload event
			this.boundBeforeUnload = (e) => {
				if (this.syncInProgress) {
					e.preventDefault();
					e.returnValue = '';
					return '';
				}
			};

			window.addEventListener('beforeunload', this.boundBeforeUnload);

			// Track if mouse leaves the main wrap area
			let mouseOutsideWrap = false;

			document.addEventListener('mousemove', (e) => {
				if (!this.syncInProgress) {
					return;
				}

				const wrap = document.querySelector('.brag-book-gallery-admin-wrap');
				if (!wrap) {
					return;
				}

				const rect = wrap.getBoundingClientRect();
				const isOutside = (
					e.clientX < rect.left ||
					e.clientX > rect.right ||
					e.clientY < rect.top ||
					e.clientY > rect.bottom
				);

				if (isOutside && !mouseOutsideWrap) {
					mouseOutsideWrap = true;
				}
			});

			// Intercept clicks outside the wrap area
			document.addEventListener('click', (e) => {

				if (!this.syncInProgress) {
					mouseOutsideWrap = false;
					return;
				}

				const wrap = document.querySelector('.brag-book-gallery-admin-wrap');
				if (!wrap) {
					console.error('BRAGbook Sync Admin: wrap not found during click');
					return;
				}

				// Check if click is outside the wrap
				const rect = wrap.getBoundingClientRect();
				const isOutside = (
					e.clientX < rect.left ||
					e.clientX > rect.right ||
					e.clientY < rect.top ||
					e.clientY > rect.bottom
				);

				if (isOutside) {
					e.preventDefault();
					e.stopPropagation();
					e.stopImmediatePropagation();

					// Check if it's a link
					const link = e.target.closest('a');
					const targetUrl = link && link.href ? link.href : null;

					this.showExitWarningDialog(targetUrl);
					return false;
				}

				// Also intercept link clicks even inside wrap if navigating away
				const link = e.target.closest('a');
				if (link && link.href && !link.target && !link.href.startsWith('#')) {
					const currentPage = window.location.href.split('?')[0];
					const targetPage = link.href.split('?')[0];

					if (currentPage !== targetPage) {
						e.preventDefault();
						e.stopPropagation();
						e.stopImmediatePropagation();
						this.showExitWarningDialog(link.href);
						return false;
					}
				}
			}, true); // Use capture phase to catch event early
		}

		/**
		 * Create the exit warning dialog element
		 */
		createExitWarningDialog() {
			const dialog = document.createElement('dialog');
			dialog.id = 'sync-exit-warning-dialog';
			dialog.className = 'sync-exit-warning-dialog';

			dialog.innerHTML = `
				<div style="
					background: white;
					border-radius: 0.5rem;
					box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
					max-width: 28rem;
					width: 100%;
				">
					<div style="padding: 1.5rem;">
						<div style="display: flex; align-items: flex-start;">
							<div style="
								flex-shrink: 0;
								display: flex;
								align-items: center;
								justify-content: center;
								height: 3rem;
								width: 3rem;
								border-radius: 9999px;
								background-color: #FEF2F2;
								margin-right: 1rem;
							">
								<svg style="height: 1.5rem; width: 1.5rem; color: #DC2626;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
								</svg>
							</div>
							<div style="flex: 1; margin-top: 0;">
								<h3 style="
									font-size: 1.125rem;
									font-weight: 600;
									color: #111827;
									margin: 0 0 0.5rem 0;
								">Sync In Progress</h3>
								<p style="
									font-size: 0.875rem;
									color: #6B7280;
									margin: 0;
								">A data synchronization is currently running. If you leave this page, the sync will be interrupted and may leave your data in an incomplete state.</p>
							</div>
						</div>
					</div>
					<div style="
						background-color: #F9FAFB;
						padding: 1rem 1.5rem;
						display: flex;
						gap: 0.75rem;
						justify-content: flex-end;
						border-bottom-left-radius: 0.5rem;
						border-bottom-right-radius: 0.5rem;
					">
						<button id="sync-exit-cancel" type="button" style="
							padding: 0.5rem 1rem;
							border: 1px solid #D1D5DB;
							border-radius: 0.375rem;
							background-color: white;
							font-size: 0.875rem;
							font-weight: 500;
							color: #374151;
							cursor: pointer;
							transition: background-color 0.2s;
						">Stay on Page</button>
						<button id="sync-exit-confirm" type="button" style="
							padding: 0.5rem 1rem;
							border: none;
							border-radius: 0.375rem;
							background-color: #DC2626;
							font-size: 0.875rem;
							font-weight: 500;
							color: white;
							cursor: pointer;
							transition: background-color 0.2s;
						">Leave Anyway</button>
					</div>
				</div>
			`;

			// Add hover effects via inline event handlers
			const cancelBtn = dialog.querySelector('#sync-exit-cancel');
			const confirmBtn = dialog.querySelector('#sync-exit-confirm');

			cancelBtn.addEventListener('mouseenter', () => {
				cancelBtn.style.backgroundColor = '#F3F4F6';
			});
			cancelBtn.addEventListener('mouseleave', () => {
				cancelBtn.style.backgroundColor = 'white';
			});

			confirmBtn.addEventListener('mouseenter', () => {
				confirmBtn.style.backgroundColor = '#B91C1C';
			});
			confirmBtn.addEventListener('mouseleave', () => {
				confirmBtn.style.backgroundColor = '#DC2626';
			});

			// Bind button handlers
			cancelBtn.addEventListener('click', () => {
				dialog.close();
				this.pendingNavigation = null;
			});

			confirmBtn.addEventListener('click', () => {
				dialog.close();
				if (this.pendingNavigation) {
					window.location.href = this.pendingNavigation;
				}
			});

			// Add backdrop styles
			const style = document.createElement('style');
			style.textContent = `
				.sync-exit-warning-dialog::backdrop {
					background-color: rgba(0, 0, 0, 0.5);
					backdrop-filter: blur(4px);
				}
			`;
			document.head.appendChild(style);

			document.body.appendChild(dialog);
			this.exitWarningDialog = dialog;
		}

		/**
		 * Show exit warning dialog
		 */
		showExitWarningDialog(targetUrl = null) {
			if (!this.exitWarningDialog) {
				return;
			}

			this.pendingNavigation = targetUrl;
			this.exitWarningDialog.showModal();
		}
	};

	// Initialize when the script loads
	new window.BRAGbookSyncAdmin();
}
