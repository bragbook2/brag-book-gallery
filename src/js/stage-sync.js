/**
 * Stage-Based Sync Handler
 *
 * Handles stage-based synchronization UI and interactions
 *
 * @package BRAGBookGallery
 * @since 3.3.0
 */

'use strict';

/**
 * Stage Sync Manager
 */
class StageSyncManager {
	constructor() {
		// UI Elements
		this.stage1Btn = document.getElementById('stage-1-btn');
		this.stage2Btn = document.getElementById('stage-2-btn');
		this.stage3Btn = document.getElementById('stage-3-btn');
		this.fullSyncBtn = document.getElementById('full-sync-btn');
		this.stopSyncBtn = document.getElementById('stop-sync-btn');
		this.deleteSyncDataBtn = document.getElementById('delete-sync-data-btn');
		this.deleteManifestBtn = document.getElementById('delete-manifest-btn');

		this.syncDataStatus = document.getElementById('sync-data-status');
		this.syncDataDate = document.getElementById('sync-data-date');
		this.manifestStatus = document.getElementById('manifest-status');
		this.manifestDate = document.getElementById('manifest-date');

		this.stageProgress = document.getElementById('stage-progress');
		this.stageProgressFill = document.getElementById('stage-progress-fill');
		this.stageProgressText = document.getElementById('stage-progress-text');

		// Debug: Check if progress elements are found
		if (!this.stageProgress) console.error('Stage progress element not found');
		if (!this.stageProgressFill) console.error('Stage progress fill element not found');
		if (!this.stageProgressText) console.error('Stage progress text element not found');

		this.manifestPreview = document.getElementById('manifest-preview');
		this.manifestPreviewContent = document.getElementById('manifest-preview-content');

		this.stage1Status = document.getElementById('stage1-status');
		this.stage1StatusContent = document.getElementById('stage1-status-content');

		this.stage3Status = document.getElementById('stage3-status');
		this.stage3StatusContent = document.getElementById('stage3-status-content');

		this.isRunning = false;
		this.shouldStop = false;
		this.currentProgressInterval = null;

		this.init();
	}

	/**
	 * Initialize event handlers
	 */
	init() {
		// Check file status on load
		this.checkFileStatus();

		// Bind event handlers
		if (this.stage1Btn) {
			this.stage1Btn.addEventListener('click', () => this.executeStage1());
		}
		if (this.stage2Btn) {
			this.stage2Btn.addEventListener('click', () => this.executeStage2());
		}
		if (this.stage3Btn) {
			this.stage3Btn.addEventListener('click', () => this.executeStage3());
		}
		if (this.fullSyncBtn) {
			this.fullSyncBtn.addEventListener('click', () => this.executeFullSync());
		}
		if (this.stopSyncBtn) {
			this.stopSyncBtn.addEventListener('click', () => this.stopSync());
		}
		if (this.deleteSyncDataBtn) {
			this.deleteSyncDataBtn.addEventListener('click', () => this.deleteSyncData());
		}
		if (this.deleteManifestBtn) {
			this.deleteManifestBtn.addEventListener('click', () => this.deleteManifest());
		}

		// Auto-refresh file status every 30 seconds
		setInterval(() => {
			if (!this.isRunning) {
				this.checkFileStatus(true); // Silent check
			}
		}, 30000);
	}

	/**
	 * Check file status
	 */
	async checkFileStatus(silent = false) {
		try {
			const response = await this.makeAjaxRequest('brag_book_sync_check_files');

			if (response.success) {
				const data = response.data;

				// SVG icons
				const checkIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#46b450"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>';
				const cancelIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ccc"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>';

				// Update sync data status
				if (data.sync_data.exists) {
					const statusIcon = this.syncDataStatus?.querySelector('.status-icon');
					if (statusIcon) statusIcon.innerHTML = checkIcon;
					if (this.syncDataDate) this.syncDataDate.textContent = `(${data.sync_data.date})`;

					// Show link to JSON file if URL is provided
					const syncDataLink = document.getElementById('sync-data-link');
					if (syncDataLink) {
						if (data.sync_data.url) {
							syncDataLink.href = data.sync_data.url;
							syncDataLink.style.display = 'inline';
						} else {
							syncDataLink.style.display = 'none';
						}
					}

					// Display Stage 1 status if available
					if (data.stage1_info) {
						this.displayStage1Status(data.stage1_info);
					} else if (this.stage1Status) {
						// Show basic status if sync data exists but no detailed info
						this.stage1StatusContent.innerHTML = '<div>Sync data file exists</div>';
						this.stage1Status.style.display = 'block';
					}
				} else {
					const statusIcon = this.syncDataStatus?.querySelector('.status-icon');
					if (statusIcon) statusIcon.innerHTML = cancelIcon;
					if (this.syncDataDate) this.syncDataDate.textContent = '';
					const syncDataLink = document.getElementById('sync-data-link');
					if (syncDataLink) syncDataLink.style.display = 'none';
					// Hide Stage 1 status when sync data doesn't exist
					if (this.stage1Status) this.stage1Status.style.display = 'none';
				}

				// Update manifest status
				if (data.manifest.exists) {
					const statusIcon = this.manifestStatus?.querySelector('.status-icon');
					if (statusIcon) statusIcon.innerHTML = checkIcon;
					if (this.manifestDate) this.manifestDate.textContent = `(${data.manifest.date})`;

					// Show link to JSON file if URL is provided
					const manifestLink = document.getElementById('manifest-link');
					if (manifestLink) {
						if (data.manifest.url) {
							manifestLink.href = data.manifest.url;
							manifestLink.style.display = 'inline';
						} else {
							manifestLink.style.display = 'none';
						}
					}

					// Load manifest preview
					this.loadManifestPreview();
				} else {
					const statusIcon = this.manifestStatus?.querySelector('.status-icon');
					if (statusIcon) statusIcon.innerHTML = cancelIcon;
					if (this.manifestDate) this.manifestDate.textContent = '';
					const manifestLink = document.getElementById('manifest-link');
					if (manifestLink) manifestLink.style.display = 'none';
					if (this.manifestPreview) this.manifestPreview.style.display = 'none';
				}

				// Load Stage 3 status if available
				if (data.stage3_status && data.stage3_status.completed_at) {
					this.displayStage3Status(data.stage3_status);
				} else {
					if (this.stage3Status) this.stage3Status.style.display = 'none';
				}

				// Update button states
				this.updateButtonStates(data);
			}
		} catch (error) {
			if (!silent) {
				console.error('Failed to check file status:', error);
			}
		}
	}

	/**
	 * Update button states based on file status
	 */
	updateButtonStates(fileStatus) {
		// Reset all button styles first
		if (this.stage1Btn) {
			this.stage1Btn.classList.remove('button-primary');
			this.stage1Btn.classList.add('button');
		}
		if (this.stage2Btn) {
			this.stage2Btn.classList.remove('button-primary');
			this.stage2Btn.classList.add('button');
		}
		if (this.stage3Btn) {
			this.stage3Btn.classList.remove('button-primary');
			this.stage3Btn.classList.add('button');
		}

		// Determine which stage should be active (black)
		if (!fileStatus.sync_data.exists) {
			// Stage 1 should be active (black)
			if (this.stage1Btn) {
				this.stage1Btn.classList.remove('button');
				this.stage1Btn.classList.add('button-primary');
			}
			// Stage 2 is disabled
			if (this.stage2Btn) {
				this.stage2Btn.disabled = true;
				this.stage2Btn.title = 'Stage 1 must be completed first';
			}
			// Stage 3 is disabled
			if (this.stage3Btn) {
				this.stage3Btn.disabled = true;
				this.stage3Btn.title = 'Stage 1 and Stage 2 must be completed first';
			}
		} else if (!fileStatus.manifest.exists) {
			// Stage 2 should be active (black) since sync data exists
			if (this.stage2Btn) {
				this.stage2Btn.classList.remove('button');
				this.stage2Btn.classList.add('button-primary');
				this.stage2Btn.disabled = false;
				this.stage2Btn.title = 'Build case ID manifest';
			}
			// Stage 3 is disabled
			if (this.stage3Btn) {
				this.stage3Btn.disabled = true;
				this.stage3Btn.title = 'Stage 2 must be completed first';
			}
		} else {
			// Both sync data and manifest exist
			if (this.stage2Btn) {
				this.stage2Btn.disabled = false;
				this.stage2Btn.title = 'Build case ID manifest';
			}
			// Stage 3 should be active (black) and enabled since both files exist
			if (this.stage3Btn) {
				this.stage3Btn.classList.remove('button');
				this.stage3Btn.classList.add('button-primary');
				this.stage3Btn.disabled = false;
				this.stage3Btn.title = 'Process cases from manifest';
			}
		}
	}

	/**
	 * Execute Stage 1
	 */
	async executeStage1() {
		if (this.isRunning) return;

		if (!confirm('Execute Stage 1: Fetch sidebar data and process procedures?')) {
			return;
		}

		this.isRunning = true;
		if (this.stage1Btn) this.stage1Btn.disabled = true;
		this.showProgress('Stage 1: Fetching sidebar data...');

		try {
			const response = await this.makeAjaxRequest('brag_book_sync_stage_1');

			if (response.success) {
				const data = response.data;
				this.showProgress('Stage 1 completed', 100);

				// Display Stage 1 status
				this.displayStage1Status(data);

				// Show success message
				this.showNotice('success', `Stage 1 completed: ${data.procedures_created} procedures created, ${data.procedures_updated} updated`);

				// Refresh file status
				await this.checkFileStatus();

				// Hide progress after 2 seconds
				setTimeout(() => {
					this.fadeOut(this.stageProgress);
				}, 2000);
			} else {
				throw new Error(response.data?.message || 'Stage 1 failed');
			}
		} catch (error) {
			console.error('Stage 1 error:', error);
			this.showNotice('error', `Stage 1 failed: ${error.message}`);
			if (this.stageProgress) this.stageProgress.style.display = 'none';
		} finally {
			this.isRunning = false;
			if (this.stage1Btn) this.stage1Btn.disabled = false;
		}
	}

	/**
	 * Execute Stage 2
	 */
	async executeStage2() {
		if (this.isRunning) return;

		if (!confirm('Execute Stage 2: Build case ID manifest? This may take several minutes.')) {
			return;
		}

		this.isRunning = true;
		if (this.stage2Btn) this.stage2Btn.disabled = true;
		this.showProgress('Stage 2: Starting manifest creation...');

		// Start progress polling
		const progressInterval = setInterval(async () => {
			try {
				const progressResponse = await this.makeAjaxRequest('brag_book_sync_get_progress');
				if (progressResponse.success && progressResponse.data.active) {
					const progress = progressResponse.data;
					const percentage = progress.percentage || 0;
					const message = progress.message || 'Processing...';
					this.showProgress(`Stage 2: ${message}`, percentage);
				}
			} catch (error) {
				console.error('Progress polling error:', error);
			}
		}, 2000); // Poll every 2 seconds

		try {
			// Start the sync with a longer timeout
			const response = await this.makeAjaxRequest('brag_book_sync_stage_2', {}, 300000); // 5 minute timeout

			// Stop progress polling
			clearInterval(progressInterval);

			if (response.success) {
				const data = response.data;
				this.showProgress('Stage 2 completed', 100);

				// Show success message
				const message = data.file_exists
					? `Manifest already exists: ${data.procedure_count} procedures, ${data.case_count} cases`
					: `Stage 2 completed: ${data.procedure_count} procedures, ${data.case_count} cases mapped`;

				this.showNotice('success', message);

				// Refresh file status
				await this.checkFileStatus();

				// Hide progress after 2 seconds
				setTimeout(() => {
					this.fadeOut(this.stageProgress);
				}, 2000);
			} else {
				throw new Error(response.data?.message || 'Stage 2 failed');
			}
		} catch (error) {
			// Stop progress polling
			clearInterval(progressInterval);

			console.error('Stage 2 error:', error);
			this.showNotice('error', `Stage 2 failed: ${error.message}`);
			if (this.stageProgress) this.stageProgress.style.display = 'none';
		} finally {
			this.isRunning = false;
			if (this.stage2Btn) this.stage2Btn.disabled = false;
		}
	}

	/**
	 * Execute Stage 3
	 */
	async executeStage3() {
		if (this.isRunning) return;

		if (!confirm('Execute Stage 3: Process cases from manifest? This may take a long time depending on the number of cases.')) {
			return;
		}

		this.isRunning = true;
		if (this.stage3Btn) this.stage3Btn.disabled = true;
		this.showProgress('Stage 3: Starting case processing...');

		// Start progress polling
		const progressInterval = setInterval(async () => {
			try {
				const progressResponse = await this.makeAjaxRequest('brag_book_sync_get_progress');
				if (progressResponse.success && progressResponse.data.active) {
					const progress = progressResponse.data;
					const percentage = progress.percentage || 0;
					const message = progress.message || 'Processing...';
					this.showProgress(`Stage 3: ${message}`, percentage);
				}
			} catch (error) {
				console.error('Progress polling error:', error);
			}
		}, 2000); // Poll every 2 seconds

		try {
			// Start the sync with a very long timeout (cases can take a while)
			const response = await this.makeAjaxRequest('brag_book_sync_stage_3', {}, 600000); // 10 minute timeout

			// Stop progress polling
			clearInterval(progressInterval);

			if (response.success) {
				const data = response.data;
				this.showProgress('Stage 3 completed', 100);

				// Show success message with proper stats
				const created = data.created_posts || 0;
				const updated = data.updated_posts || 0;
				const failed = data.failed_cases || 0;
				const processed = data.processed_cases || 0;
				const total = data.total_cases || 0;

				const message = `Stage 3 completed: ${created} created, ${updated} updated${failed > 0 ? `, ${failed} failed` : ''} (${processed}/${total} processed)`;

				this.showNotice('success', message);

				// Show any errors
				if (data.errors && data.errors.length > 0) {
					for (const error of data.errors.slice(0, 5)) {
						this.showNotice('error', error);
					}
					if (data.errors.length > 5) {
						this.showNotice('warning', `...and ${data.errors.length - 5} more errors`);
					}
				}

				// Refresh file status to show Stage 3 status
				await this.checkFileStatus();

				// Hide progress after 2 seconds
				setTimeout(() => {
					this.fadeOut(this.stageProgress);
				}, 2000);
			} else {
				throw new Error(response.data?.message || 'Stage 3 failed');
			}
		} catch (error) {
			// Stop progress polling
			clearInterval(progressInterval);

			console.error('Stage 3 error:', error);
			this.showNotice('error', `Stage 3 failed: ${error.message}`);
			if (this.stageProgress) this.stageProgress.style.display = 'none';
		} finally {
			this.isRunning = false;
			if (this.stage3Btn) this.stage3Btn.disabled = false;
			// Refresh button states
			await this.checkFileStatus();
		}
	}

	/**
	 * Execute Full Sync (all three stages)
	 */
	async executeFullSync() {
		if (this.isRunning) return;

		if (!confirm('Execute Full Sync? This will run all three stages sequentially and may take a considerable amount of time.')) {
			return;
		}

		this.isRunning = true;
		this.shouldStop = false;
		this.setAllButtonsDisabled(true);
		this.showStopButton(true);

		try {
			// Stage 1
			this.showProgress('Full Sync - Stage 1: Fetching procedures...', 0);
			this.showNotice('info', 'Starting Full Sync - Stage 1: Procedures');

			const stage1Response = await this.makeAjaxRequest('brag_book_sync_stage_1');
			if (!stage1Response.success) {
				throw new Error(stage1Response.data?.message || 'Stage 1 failed');
			}

			const stage1Data = stage1Response.data;
			this.showNotice('success', `Stage 1 completed: ${stage1Data.procedures_created} procedures created, ${stage1Data.procedures_updated} updated`);
			this.showProgress('Full Sync - Stage 1 completed', 33);

			// Refresh file status
			await this.checkFileStatus(true);

			// Check if should stop
			if (this.shouldStop) {
				throw new Error('Sync stopped by user');
			}

			// Stage 2
			this.showProgress('Full Sync - Stage 2: Building manifest...', 33);
			this.showNotice('info', 'Starting Full Sync - Stage 2: Building manifest');

			// Start progress polling for Stage 2
			this.currentProgressInterval = setInterval(async () => {
				try {
					const progressResponse = await this.makeAjaxRequest('brag_book_sync_get_progress');
					if (progressResponse.success && progressResponse.data.active) {
						const progress = progressResponse.data;
						const percentage = progress.percentage || 0;
						const adjustedPercentage = 33 + (percentage * 0.33); // Scale to 33-66%
						const message = progress.message || 'Processing...';
						this.showProgress(`Full Sync - Stage 2: ${message}`, adjustedPercentage);
					}
				} catch (error) {
					console.error('Progress polling error:', error);
				}
			}, 2000);

			const stage2Response = await this.makeAjaxRequest('brag_book_sync_stage_2', {}, 300000);
			clearInterval(this.currentProgressInterval);
			this.currentProgressInterval = null;

			if (!stage2Response.success) {
				throw new Error(stage2Response.data?.message || 'Stage 2 failed');
			}

			const stage2Data = stage2Response.data;
			const stage2Message = stage2Data.file_exists
				? `Manifest already exists: ${stage2Data.procedure_count} procedures, ${stage2Data.case_count} cases`
				: `Stage 2 completed: ${stage2Data.procedure_count} procedures, ${stage2Data.case_count} cases mapped`;
			this.showNotice('success', stage2Message);
			this.showProgress('Full Sync - Stage 2 completed', 66);

			// Refresh file status
			await this.checkFileStatus(true);

			// Check if should stop
			if (this.shouldStop) {
				throw new Error('Sync stopped by user');
			}

			// Stage 3
			this.showProgress('Full Sync - Stage 3: Processing cases...', 66);
			this.showNotice('info', 'Starting Full Sync - Stage 3: Processing cases');

			// Start progress polling for Stage 3
			this.currentProgressInterval = setInterval(async () => {
				try {
					const progressResponse = await this.makeAjaxRequest('brag_book_sync_get_progress');
					if (progressResponse.success && progressResponse.data.active) {
						const progress = progressResponse.data;
						const percentage = progress.percentage || 0;
						const adjustedPercentage = 66 + (percentage * 0.34); // Scale to 66-100%
						const message = progress.message || 'Processing...';
						this.showProgress(`Full Sync - Stage 3: ${message}`, adjustedPercentage);
					}
				} catch (error) {
					console.error('Progress polling error:', error);
				}
			}, 2000);

			const stage3Response = await this.makeAjaxRequest('brag_book_sync_stage_3', {}, 600000);
			clearInterval(this.currentProgressInterval);
			this.currentProgressInterval = null;

			if (!stage3Response.success) {
				throw new Error(stage3Response.data?.message || 'Stage 3 failed');
			}

			const stage3Data = stage3Response.data;
			const created = stage3Data.created_posts || 0;
			const updated = stage3Data.updated_posts || 0;
			const failed = stage3Data.failed_cases || 0;
			const processed = stage3Data.processed_cases || 0;
			const total = stage3Data.total_cases || 0;

			const stage3Message = `Stage 3 completed: ${created} created, ${updated} updated${failed > 0 ? `, ${failed} failed` : ''} (${processed}/${total} processed)`;
			this.showNotice('success', stage3Message);

			// Show any errors
			if (stage3Data.errors && stage3Data.errors.length > 0) {
				for (const error of stage3Data.errors.slice(0, 5)) {
					this.showNotice('error', error);
				}
				if (stage3Data.errors.length > 5) {
					this.showNotice('warning', `...and ${stage3Data.errors.length - 5} more errors`);
				}
			}

			// Final success message
			this.showProgress('Full Sync completed successfully!', 100);
			this.showNotice('success', 'Full Sync completed successfully! All three stages have been executed.');

			// Refresh file status to show final state
			await this.checkFileStatus();

			// Hide progress after 3 seconds
			setTimeout(() => {
				this.fadeOut(this.stageProgress);
			}, 3000);

		} catch (error) {
			// Clear any active progress intervals
			if (this.currentProgressInterval) {
				clearInterval(this.currentProgressInterval);
				this.currentProgressInterval = null;
			}

			if (this.shouldStop) {
				this.showNotice('warning', 'Full Sync stopped by user');
				this.showProgress('Sync stopped', 0);
			} else {
				console.error('Full Sync error:', error);
				this.showNotice('error', `Full Sync failed: ${error.message}`);
			}

			setTimeout(() => {
				if (this.stageProgress) this.stageProgress.style.display = 'none';
			}, 3000);
		} finally {
			this.isRunning = false;
			this.shouldStop = false;
			this.setAllButtonsDisabled(false);
			this.showStopButton(false);
			// Refresh button states
			await this.checkFileStatus();
		}
	}

	/**
	 * Stop sync process
	 */
	stopSync() {
		if (!this.isRunning) return;

		if (confirm('Are you sure you want to stop the sync process?')) {
			this.shouldStop = true;
			this.showNotice('info', 'Stopping sync process...');

			// Clear any active progress interval
			if (this.currentProgressInterval) {
				clearInterval(this.currentProgressInterval);
				this.currentProgressInterval = null;
			}
		}
	}

	/**
	 * Show/hide stop button
	 */
	showStopButton(show) {
		if (this.stopSyncBtn) {
			this.stopSyncBtn.style.display = show ? 'inline-flex' : 'none';
		}
	}

	/**
	 * Set all buttons disabled state
	 */
	setAllButtonsDisabled(disabled) {
		if (this.stage1Btn) this.stage1Btn.disabled = disabled;
		if (this.stage2Btn) this.stage2Btn.disabled = disabled;
		if (this.stage3Btn) this.stage3Btn.disabled = disabled;
		if (this.fullSyncBtn) this.fullSyncBtn.disabled = disabled;
	}

	/**
	 * Delete sync data file
	 */
	async deleteSyncData() {
		if (this.isRunning) {
			this.showNotice('error', 'Cannot delete files while sync is running');
			return;
		}

		if (!confirm('Are you sure you want to delete the sync data file? This will require running Stage 1 again.')) {
			return;
		}

		try {
			const response = await this.makeAjaxRequest('brag_book_sync_delete_file', { file: 'sync_data' });

			if (response.success) {
				this.showNotice('success', 'Sync data file deleted successfully');
				await this.checkFileStatus();
			} else {
				this.showNotice('error', response.data?.message || 'Failed to delete sync data file');
			}
		} catch (error) {
			this.showNotice('error', `Error deleting sync data: ${error.message}`);
		}
	}

	/**
	 * Delete manifest file
	 */
	async deleteManifest() {
		if (this.isRunning) {
			this.showNotice('error', 'Cannot delete files while sync is running');
			return;
		}

		if (!confirm('Are you sure you want to delete the manifest file? This will require running Stage 2 again.')) {
			return;
		}

		try {
			const response = await this.makeAjaxRequest('brag_book_sync_delete_file', { file: 'manifest' });

			if (response.success) {
				this.showNotice('success', 'Manifest file deleted successfully');
				await this.checkFileStatus();
			} else {
				this.showNotice('error', response.data?.message || 'Failed to delete manifest file');
			}
		} catch (error) {
			this.showNotice('error', `Error deleting manifest: ${error.message}`);
		}
	}

	/**
	 * Load manifest preview
	 */
	async loadManifestPreview() {
		try {
			const response = await this.makeAjaxRequest('brag_book_sync_get_manifest_preview');

			if (response.success && response.data.exists) {
				const data = response.data;
				let html = `<div style="margin-bottom: 10px;">`;
				html += `<strong>Date:</strong> ${data.date}<br>`;
				html += `<strong>Total Procedures:</strong> ${data.total_procedures}<br>`;
				html += `<strong>Total Cases:</strong> ${data.total_cases}<br>`;
				html += `</div>`;

				if (data.preview) {
					html += `<div style="border-top: 1px solid #ddd; padding-top: 10px;">`;
					html += `<strong>Preview (first 5):</strong><br>`;

					for (const [procedureId, info] of Object.entries(data.preview)) {
						html += `<div style="margin: 5px 0;">`;
						html += `Procedure ${procedureId}: ${info.case_count} cases`;
						if (info.sample_ids && info.sample_ids.length > 0) {
							html += ` (${info.sample_ids.join(', ')}...)`;
						}
						html += `</div>`;
					}
					html += `</div>`;
				}

				if (this.manifestPreviewContent) this.manifestPreviewContent.innerHTML = html;
				if (this.manifestPreview) this.manifestPreview.style.display = 'block';
			}
		} catch (error) {
			console.error('Failed to load manifest preview:', error);
		}
	}

	/**
	 * Display Stage 1 status
	 */
	displayStage1Status(data) {
		if (!data || !this.stage1Status || !this.stage1StatusContent) {
			if (this.stage1Status) this.stage1Status.style.display = 'none';
			return;
		}

		let html = `<div style="margin-bottom: 10px;">`;
		html += `<strong>Sync data file created successfully</strong><br>`;
		html += `</div>`;

		html += `<div style="border-top: 1px solid #ddd; padding-top: 10px;">`;
		html += `<strong>Results:</strong><br>`;
		html += `<div style="margin: 5px 0;">`;
		html += `Created: ${data.procedures_created || 0} procedures<br>`;
		html += `Updated: ${data.procedures_updated || 0} procedures<br>`;
		html += `Total: ${data.total_procedures || (data.procedures_created + data.procedures_updated) || 0} procedures`;
		html += `</div>`;
		html += `</div>`;

		this.stage1StatusContent.innerHTML = html;
		this.stage1Status.style.display = 'block';
	}

	/**
	 * Display Stage 3 status
	 */
	displayStage3Status(status) {
		if (!status || !this.stage3Status || !this.stage3StatusContent) {
			if (this.stage3Status) this.stage3Status.style.display = 'none';
			return;
		}

		let html = `<div style="margin-bottom: 10px;">`;
		html += `<strong>Last Run:</strong> ${status.completed_at}<br>`;
		html += `</div>`;

		html += `<div style="border-top: 1px solid #ddd; padding-top: 10px;">`;
		html += `<strong>Results:</strong><br>`;
		html += `<div style="margin: 5px 0;">`;
		html += `Created: ${status.created_posts || 0} cases<br>`;
		html += `Updated: ${status.updated_posts || 0} cases<br>`;
		if (status.failed_cases > 0) {
			html += `Failed: ${status.failed_cases} cases<br>`;
		}
		html += `Total Processed: ${status.processed_cases || 0} / ${status.total_cases || 0} cases`;
		html += `</div>`;
		html += `</div>`;

		this.stage3StatusContent.innerHTML = html;
		this.stage3Status.style.display = 'block';
	}

	/**
	 * Show progress
	 */
	showProgress(message, percentage = 0) {
		console.log('showProgress called:', message, percentage); // Debug log
		if (this.stageProgress) {
			this.stageProgress.style.display = 'block';
			this.stageProgress.style.opacity = '1'; // Ensure opacity is set to 1
			this.stageProgress.style.transition = 'opacity 0.3s ease-in'; // Smooth fade in
			console.log('Progress bar shown'); // Debug log
		}
		if (this.stageProgressFill) {
			this.stageProgressFill.style.width = `${percentage}%`;
		}
		if (this.stageProgressText) {
			this.stageProgressText.textContent = message;
		}
	}

	/**
	 * Show notice
	 */
	showNotice(type, message) {
		const noticesContainer = document.querySelector('.brag-book-gallery-notices');
		if (!noticesContainer) return;

		const noticeClass = type === 'error' ? 'notice-error' :
						   type === 'success' ? 'notice-success' :
						   type === 'info' ? 'notice-info' : 'notice-warning';

		const notice = document.createElement('div');
		notice.className = `notice ${noticeClass} is-dismissible`;
		notice.innerHTML = `
			<p>${message}</p>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text">Dismiss this notice.</span>
			</button>
		`;

		noticesContainer.appendChild(notice);

		// Handle dismiss
		const dismissBtn = notice.querySelector('.notice-dismiss');
		if (dismissBtn) {
			dismissBtn.addEventListener('click', () => {
				this.fadeOut(notice, () => notice.remove());
			});
		}

		// Auto-dismiss after 10 seconds for success messages
		if (type === 'success') {
			setTimeout(() => {
				this.fadeOut(notice, () => notice.remove());
			}, 10000);
		}
	}

	/**
	 * Fade out element
	 */
	fadeOut(element, callback) {
		if (!element) return;

		element.style.transition = 'opacity 0.3s ease-out';
		element.style.opacity = '0';

		setTimeout(() => {
			element.style.display = 'none';
			// Reset opacity for next time (important for progress bar)
			element.style.opacity = '1';
			if (callback) callback();
		}, 300);
	}

	/**
	 * Make AJAX request
	 */
	async makeAjaxRequest(action, data = {}, timeout = 30000) {
		// Build form data
		const formData = new FormData();
		formData.append('action', action);
		formData.append('nonce', window.bragBookSync?.sync_nonce || '');

		// Add any additional data
		for (const [key, value] of Object.entries(data)) {
			formData.append(key, value);
		}

		// Create abort controller for timeout
		const controller = new AbortController();
		const timeoutId = setTimeout(() => controller.abort(), timeout);

		try {
			const response = await fetch(window.ajaxurl || window.bragBookSync?.ajax_url || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData,
				signal: controller.signal
			});

			clearTimeout(timeoutId);

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const result = await response.json();
			return result;
		} catch (error) {
			clearTimeout(timeoutId);

			if (error.name === 'AbortError') {
				throw new Error('Request timeout');
			}
			throw error;
		}
	}
}

// Add spin animation CSS and button styling
const addStyles = () => {
	const style = document.createElement('style');
	style.textContent = `
		.spin {
			animation: spin 1s linear infinite;
		}
		@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
		.stage-sync-section .dashicons-yes {
			font-size: 16px;
			line-height: 20px;
			height: 20px;
			width: 16px;
		}
		.stage-sync-section .dashicons-no {
			font-size: 16px;
			line-height: 20px;
			height: 20px;
			width: 16px;
		}
		/* Stage buttons - non-active buttons are white */
		.stage-sync-buttons .stage-button.button {
			background: white !important;
			border-color: #ddd !important;
			color: #333 !important;
		}
		.stage-sync-buttons .stage-button.button:hover:not(:disabled) {
			background: #f0f0f0 !important;
			border-color: #ccc !important;
			color: #000 !important;
		}
		/* Stage buttons - active button (button-primary) is black */
		.stage-sync-buttons .stage-button.button-primary {
			background: #0f172a !important;
			border-color: #0f172a !important;
			color: white !important;
		}
		.stage-sync-buttons .stage-button.button-primary:hover:not(:disabled) {
			background: #1e293b !important;
			border-color: #1e293b !important;
		}
		/* Full sync button hover state */
		#full-sync-btn:hover:not(:disabled) {
			background: #1e293b !important;
			border-color: #1e293b !important;
		}
		/* Disabled button styles */
		.stage-sync-buttons .stage-button:disabled {
			opacity: 0.5;
			cursor: not-allowed;
			background: #f5f5f5 !important;
			color: #999 !important;
			border-color: #ddd !important;
		}
	`;
	document.head.appendChild(style);
};

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
	// Only initialize on sync page
	if (document.getElementById('stage-1-btn')) {
		addStyles();
		new StageSyncManager();
	}
});