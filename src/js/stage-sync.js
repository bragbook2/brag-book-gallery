/**
 * Stage-Based Sync Handler
 *
 * Handles stage-based synchronization UI and interactions
 *
 * @package BRAGBookGallery
 * @since 3.3.0
 */

'use strict';

import { syncDialog } from './modules/sync-dialog.js';

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
		this.clearStage3StatusBtn = document.getElementById('clear-stage3-status-btn');

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

		// Orphan detection elements
		this.orphanPanel = document.getElementById('orphan-detection-panel');
		this.orphanContent = document.getElementById('orphan-detection-content');
		this.orphanActions = document.getElementById('orphan-actions');
		this.deleteOrphansBtn = document.getElementById('delete-orphans-btn');
		this.skipOrphansBtn = document.getElementById('skip-orphans-btn');
		this.orphanResult = document.getElementById('orphan-result');

		// Stored orphan data for deletion
		this.detectedOrphans = [];

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
		if (this.clearStage3StatusBtn) {
			this.clearStage3StatusBtn.addEventListener('click', () => this.clearStage3Status());
		}
		if (this.deleteOrphansBtn) {
			this.deleteOrphansBtn.addEventListener('click', () => this.deleteOrphans());
		}
		if (this.skipOrphansBtn) {
			this.skipOrphansBtn.addEventListener('click', () => this.hideOrphanPanel());
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

		// Show confirmation dialog
		syncDialog.showConfirm(
			'Execute Stage 1',
			'Fetch sidebar data and process procedures?',
			{
				confirmText: 'Execute',
				cancelText: 'Cancel',
				confirmButtonClass: 'button-primary',
				onConfirm: () => this.executeStage1Confirmed(),
			}
		);
	}

	/**
	 * Execute Stage 1 after confirmation
	 */
	async executeStage1Confirmed() {

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

		// Show confirmation dialog
		syncDialog.showConfirm(
			'Execute Stage 2',
			'Build case ID manifest? This may take several minutes.',
			{
				confirmText: 'Execute',
				cancelText: 'Cancel',
				confirmButtonClass: 'button-primary',
				onConfirm: () => this.executeStage2Confirmed(),
			}
		);
	}

	/**
	 * Execute Stage 2 after confirmation
	 */
	async executeStage2Confirmed() {

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

		// Show confirmation dialog
		syncDialog.showConfirm(
			'Execute Stage 3',
			'Process cases from manifest? This will process cases in batches.',
			{
				confirmText: 'Execute',
				cancelText: 'Cancel',
				confirmButtonClass: 'button-primary',
				onConfirm: () => this.executeStage3Confirmed(),
			}
		);
	}

	/**
	 * Execute Stage 3 after confirmation
	 */
	async executeStage3Confirmed() {
		if (false) {
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
			// Process Stage 3 in batches
			await this.processStage3Batches(progressInterval);

			// Stop progress polling
			clearInterval(progressInterval);

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
	 * Process Stage 3 in batches with automatic resumption
	 */
	async processStage3Batches(progressInterval) {
		let needsContinue = true;
		let totalProcessed = 0;
		let totalCreated = 0;
		let totalUpdated = 0;
		let totalFailed = 0;
		let totalCases = 0;
		let lastProcessed = -1;
		let stuckCount = 0;

		while (needsContinue) {
			// Process a batch (3 minute timeout per batch)
			const response = await this.makeAjaxRequest('brag_book_sync_stage_3', {}, 180000);

			if (!response.success) {
				throw new Error(response.data?.message || response.data?.error || 'Stage 3 batch failed');
			}

			const data = response.data;
			totalProcessed = data.processed_cases || 0;
			totalCreated = data.created_posts || 0;
			totalUpdated = data.updated_posts || 0;
			totalFailed = data.failed_cases || 0;
			totalCases = data.total_cases || 0;
			needsContinue = data.needs_continue || false;

			// Detect infinite loop - if no progress after 3 attempts, stop
			if (totalProcessed === lastProcessed) {
				stuckCount++;
				console.warn(`Stage 3: No progress detected (attempt ${stuckCount}/3)`);
				if (stuckCount >= 3) {
					this.showNotice('warning', `Processing stopped at ${totalProcessed}/${totalCases} cases - no further progress possible`);
					needsContinue = false;
					break;
				}
			} else {
				stuckCount = 0;
			}
			lastProcessed = totalProcessed;

			// Update progress
			const progress = data.progress || ((totalProcessed / totalCases) * 100);
			this.showProgress(
				`Stage 3: Processing cases... ${totalProcessed}/${totalCases}`,
				progress
			);

			// Brief pause between batches
			if (needsContinue) {
				await new Promise(resolve => setTimeout(resolve, 500));
			}
		}

		// All batches complete
		this.showProgress('Stage 3 completed', 100);

		const message = `Stage 3 completed: ${totalCreated} created, ${totalUpdated} updated${totalFailed > 0 ? `, ${totalFailed} failed` : ''} (${totalProcessed}/${totalCases} processed)`;

		this.showNotice('success', message);

		// Refresh file status to show Stage 3 status
		await this.checkFileStatus();

		// Auto-detect orphans after Stage 3 completes
		await this.detectOrphans();

		// Hide progress after 2 seconds
		setTimeout(() => {
			this.fadeOut(this.stageProgress);
		}, 2000);
	}

	/**
	 * Execute Full Sync (all three stages)
	 */
	async executeFullSync() {
		if (this.isRunning) return;

		// Show confirmation dialog
		syncDialog.showConfirm(
			'Execute Full Sync',
			'This will run all three stages sequentially and may take a considerable amount of time. Continue?',
			{
				confirmText: 'Execute',
				cancelText: 'Cancel',
				confirmButtonClass: 'button-primary',
				onConfirm: () => this.executeFullSyncConfirmed(),
			}
		);
	}

	/**
	 * Execute Full Sync after confirmation
	 */
	async executeFullSyncConfirmed() {

		this.isRunning = true;
		this.shouldStop = false;
		this.setAllButtonsDisabled(true);
		this.showStopButton(true);

		try {
			// Stage 1
			this.showProgress('Full Sync - Stage 1: Fetching procedures...', 0);

			const stage1Response = await this.makeAjaxRequest('brag_book_sync_stage_1');
			if (!stage1Response.success) {
				throw new Error(stage1Response.data?.message || 'Stage 1 failed');
			}

			const stage1Data = stage1Response.data;
			this.showProgress('Full Sync - Stage 1 completed', 33);

			// Refresh file status
			await this.checkFileStatus(true);

			// Check if should stop
			if (this.shouldStop) {
				throw new Error('Sync stopped by user');
			}

			// Stage 2
			this.showProgress('Full Sync - Stage 2: Building manifest...', 33);

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
			this.showProgress('Full Sync - Stage 2 completed', 66);

			// Refresh file status
			await this.checkFileStatus(true);

			// Check if should stop
			if (this.shouldStop) {
				throw new Error('Sync stopped by user');
			}

			// Stage 3 (with batch processing)
			this.showProgress('Full Sync - Stage 3: Processing cases...', 66);

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

			// Process Stage 3 in batches
			let needsContinue = true;
			let created = 0;
			let updated = 0;
			let failed = 0;
			let processed = 0;
			let total = 0;
			let lastProcessed = -1;
			let stuckCount = 0;

			while (needsContinue) {
				// Check if should stop
				if (this.shouldStop) {
					throw new Error('Sync stopped by user');
				}

				const stage3Response = await this.makeAjaxRequest('brag_book_sync_stage_3', {}, 180000);

				if (!stage3Response.success) {
					throw new Error(stage3Response.data?.message || stage3Response.data?.error || 'Stage 3 failed');
				}

				const stage3Data = stage3Response.data;
				created = stage3Data.created_posts || 0;
				updated = stage3Data.updated_posts || 0;
				failed = stage3Data.failed_cases || 0;
				processed = stage3Data.processed_cases || 0;
				total = stage3Data.total_cases || 0;
				needsContinue = stage3Data.needs_continue || false;

				// Detect infinite loop - if no progress after 3 attempts, stop
				if (processed === lastProcessed) {
					stuckCount++;
					console.warn(`Full Sync Stage 3: No progress detected (attempt ${stuckCount}/3)`);
					if (stuckCount >= 3) {
						this.showNotice('warning', `Stage 3 processing stopped at ${processed}/${total} cases - no further progress possible`);
						needsContinue = false;
						break;
					}
				} else {
					stuckCount = 0;
				}
				lastProcessed = processed;

				// Update progress (scale from 66% to 100%)
				const batchProgress = total > 0 ? (processed / total) : 1;
				const adjustedPercentage = 66 + (batchProgress * 34);
				this.showProgress(`Full Sync - Stage 3: ${processed}/${total} cases`, adjustedPercentage);

				// Brief pause between batches
				if (needsContinue) {
					await new Promise(resolve => setTimeout(resolve, 500));
				}
			}

			clearInterval(this.currentProgressInterval);
			this.currentProgressInterval = null;

			// Final success message
			this.showProgress('Full Sync completed successfully!', 100);
			const finalMessage = `Full Sync completed successfully! ${created} cases created, ${updated} updated${failed > 0 ? `, ${failed} failed` : ''} (${processed}/${total} total)`;
			this.showNotice('success', finalMessage);

			// Refresh file status to show final state
			await this.checkFileStatus();

			// Auto-detect orphans after full sync completes
			await this.detectOrphans();

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

		syncDialog.showConfirm(
			'Stop Sync',
			'Are you sure you want to stop the sync process?',
			{
				confirmText: 'Stop',
				cancelText: 'Cancel',
				confirmButtonClass: 'button-danger',
				onConfirm: () => {
					this.shouldStop = true;
					this.showNotice('info', 'Stopping sync process...');

					// Clear any active progress interval
					if (this.currentProgressInterval) {
						clearInterval(this.currentProgressInterval);
						this.currentProgressInterval = null;
					}
				},
			}
		);
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

		syncDialog.showConfirm(
			'Delete Sync Data',
			'Are you sure you want to delete the sync data file? This will require running Stage 1 again.',
			{
				confirmText: 'Delete',
				cancelText: 'Cancel',
				confirmButtonClass: 'button-danger',
				onConfirm: () => this.deleteSyncDataConfirmed(),
			}
		);
	}

	/**
	 * Delete sync data file (confirmed)
	 */
	async deleteSyncDataConfirmed() {
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

		syncDialog.showConfirm(
			'Delete Manifest',
			'Are you sure you want to delete the manifest file? This will require running Stage 2 again.',
			{
				confirmText: 'Delete',
				cancelText: 'Cancel',
				confirmButtonClass: 'button-danger',
				onConfirm: () => this.deleteManifestConfirmed(),
			}
		);
	}

	/**
	 * Delete manifest file (confirmed)
	 */
	async deleteManifestConfirmed() {
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
		if (status.completed_at_human) {
			html += `<span style="color: #666; font-size: 12px;">(${status.completed_at_human})</span><br>`;
		}
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

		// Add clear button
		html += `<button type="button" id="clear-stage3-status-btn" class="button button-link-delete" style="margin-top: 10px; font-size: 12px;" title="Clear Stage 3 status">`;
		html += `<svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="currentColor" style="vertical-align: middle; margin-right: 4px;"><path d="m336-293.85 144-144 144 144L666.15-336l-144-144 144-144L624-666.15l-144 144-144-144L293.85-624l144 144-144 144L336-293.85ZM480.07-100q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.92-148.21t81.21-120.68q51.29-51.31 120.63-81.25Q401.1-860 479.93-860q78.84 0 148.21 29.92t120.68 81.21q51.31 51.29 81.25 120.63Q860-558.9 860-480.07q0 78.84-29.92 148.21t-81.21 120.68q-51.29 51.31-120.63 81.25Q558.9-100 480.07-100Z"/></svg>`;
		html += `Clear Status`;
		html += `</button>`;

		this.stage3StatusContent.innerHTML = html;
		this.stage3Status.style.display = 'block';

		// Re-bind the clear button event after adding it to the DOM
		const clearBtn = document.getElementById('clear-stage3-status-btn');
		if (clearBtn) {
			clearBtn.addEventListener('click', () => this.clearStage3Status());
		}
	}

	/**
	 * Clear Stage 3 status
	 */
	async clearStage3Status() {
		syncDialog.showConfirm(
			'Clear Stage 3 Status',
			'Are you sure you want to clear the Stage 3 status? This will not delete the synced posts.',
			{
				confirmText: 'Clear',
				cancelText: 'Cancel',
				confirmButtonClass: 'button-danger',
				onConfirm: () => this.clearStage3StatusConfirmed(),
			}
		);
	}

	/**
	 * Clear Stage 3 status (confirmed)
	 */
	async clearStage3StatusConfirmed() {
		try {
			const response = await this.makeAjaxRequest('brag_book_sync_clear_stage3_status');

			if (response.success) {
				this.showNotice('success', 'Stage 3 status cleared successfully');
				// Hide the status box
				if (this.stage3Status) this.stage3Status.style.display = 'none';
			} else {
				this.showNotice('error', response.data?.message || 'Failed to clear Stage 3 status');
			}
		} catch (error) {
			this.showNotice('error', `Error clearing Stage 3 status: ${error.message}`);
		}
	}

	/**
	 * Detect orphaned items after sync completion
	 */
	async detectOrphans() {
		if (!this.orphanPanel) return;

		// Show the panel with loading state
		this.orphanPanel.style.display = 'block';
		if (this.orphanContent) {
			this.orphanContent.textContent = '';
			const loadingP = document.createElement('p');
			loadingP.className = 'description';
			loadingP.textContent = 'Scanning for orphaned items...';
			this.orphanContent.appendChild(loadingP);
		}
		if (this.orphanActions) this.orphanActions.style.display = 'none';
		if (this.orphanResult) this.orphanResult.style.display = 'none';

		try {
			const response = await this.makeAjaxRequest('brag_book_sync_detect_orphans');

			if (!response.success) {
				this.setOrphanContentText(response.data?.message || 'Detection failed', '#d63638');
				return;
			}

			const data = response.data;
			const report = data.report;

			if (!report || report.total === 0) {
				this.showNoOrphans();
				return;
			}

			this.detectedOrphans = data.orphans;
			this.showOrphanPreview(report);

		} catch (error) {
			console.error('Orphan detection error:', error);
			this.setOrphanContentText('Error detecting orphans: ' + error.message, '#d63638');
		}
	}

	/**
	 * Helper to set orphan content with plain text
	 */
	setOrphanContentText(text, color = '') {
		if (!this.orphanContent) return;
		this.orphanContent.textContent = '';
		const p = document.createElement('p');
		p.className = 'description';
		if (color) p.style.color = color;
		p.textContent = text;
		this.orphanContent.appendChild(p);
	}

	/**
	 * Show orphan preview grouped by type using safe DOM methods
	 */
	showOrphanPreview(report) {
		if (!this.orphanContent) return;
		this.orphanContent.textContent = '';

		const heading = document.createElement('p');
		const strong = document.createElement('strong');
		strong.textContent = `Found ${report.total} orphaned item${report.total !== 1 ? 's' : ''}:`;
		heading.appendChild(strong);
		this.orphanContent.appendChild(heading);

		const list = document.createElement('ul');
		list.style.cssText = 'margin: 8px 0; padding-left: 20px;';

		for (const [type, data] of Object.entries(report.by_type)) {
			if (data.count > 0) {
				const typeLabel = type.charAt(0).toUpperCase() + type.slice(1) + 's';
				const li = document.createElement('li');
				const liStrong = document.createElement('strong');
				liStrong.textContent = `${data.count} ${typeLabel}`;
				li.appendChild(liStrong);

				if (data.items && data.items.length > 0) {
					const subList = document.createElement('ul');
					subList.style.cssText = 'margin: 4px 0; padding-left: 16px; font-size: 12px; color: #646970;';
					const displayItems = data.items.slice(0, 5);
					for (const item of displayItems) {
						const subLi = document.createElement('li');
						subLi.textContent = `${item.name} (API: ${item.api_id}, WP: ${item.wordpress_id})`;
						subList.appendChild(subLi);
					}
					if (data.items.length > 5) {
						const moreLi = document.createElement('li');
						moreLi.textContent = `... and ${data.items.length - 5} more`;
						subList.appendChild(moreLi);
					}
					li.appendChild(subList);
				}

				list.appendChild(li);
			}
		}

		this.orphanContent.appendChild(list);

		const warning = document.createElement('p');
		warning.className = 'description';
		warning.style.color = '#d63638';
		warning.textContent = 'These items no longer exist in the API and should be removed for HIPAA compliance.';
		this.orphanContent.appendChild(warning);

		if (this.orphanActions) this.orphanActions.style.display = 'block';
	}

	/**
	 * Show "no orphans found" message
	 */
	showNoOrphans() {
		this.setOrphanContentText('No orphaned items found. All synced items are up to date.', '#00a32a');
		if (this.orphanActions) this.orphanActions.style.display = 'none';

		// Auto-hide after 5 seconds
		setTimeout(() => {
			this.hideOrphanPanel();
		}, 5000);
	}

	/**
	 * Delete detected orphans after user confirmation
	 */
	async deleteOrphans() {
		if (!this.detectedOrphans || this.detectedOrphans.length === 0) {
			this.showNotice('warning', 'No orphans to delete');
			return;
		}

		const count = this.detectedOrphans.length;
		const confirmed = confirm(`Are you sure you want to delete ${count} orphaned item${count !== 1 ? 's' : ''}? This action cannot be undone.`);
		if (!confirmed) return;

		if (this.deleteOrphansBtn) this.deleteOrphansBtn.disabled = true;
		this.setOrphanContentText('Deleting orphaned items...');
		if (this.orphanActions) this.orphanActions.style.display = 'none';

		try {
			const response = await this.makeAjaxRequest('brag_book_sync_delete_orphans', {
				orphans: JSON.stringify(this.detectedOrphans)
			});

			if (response.success) {
				this.showOrphanDeletionResult(response.data);
			} else {
				this.setOrphanContentText('Deletion failed: ' + (response.data?.message || 'Unknown error'), '#d63638');
			}
		} catch (error) {
			console.error('Orphan deletion error:', error);
			this.setOrphanContentText('Error: ' + error.message, '#d63638');
		} finally {
			if (this.deleteOrphansBtn) this.deleteOrphansBtn.disabled = false;
			this.detectedOrphans = [];
		}
	}

	/**
	 * Show orphan deletion result using safe DOM methods
	 */
	showOrphanDeletionResult(data) {
		if (!this.orphanContent) return;
		this.orphanContent.textContent = '';

		const msgP = document.createElement('p');
		msgP.style.color = '#00a32a';
		const msgStrong = document.createElement('strong');
		msgStrong.textContent = data.message;
		msgP.appendChild(msgStrong);
		this.orphanContent.appendChild(msgP);

		if (data.items && data.items.length > 0) {
			const list = document.createElement('ul');
			list.style.cssText = 'margin: 8px 0; padding-left: 20px; font-size: 12px;';
			for (const item of data.items) {
				const li = document.createElement('li');
				li.textContent = `${item.item_type}: ${item.name} (WP ID: ${item.wordpress_id}) - deleted`;
				list.appendChild(li);
			}
			this.orphanContent.appendChild(list);
		}

		if (data.errors && data.errors.length > 0) {
			const errHeading = document.createElement('p');
			errHeading.style.cssText = 'color: #d63638; margin-top: 8px;';
			const errStrong = document.createElement('strong');
			errStrong.textContent = 'Errors:';
			errHeading.appendChild(errStrong);
			this.orphanContent.appendChild(errHeading);

			const errList = document.createElement('ul');
			errList.style.cssText = 'padding-left: 20px; font-size: 12px; color: #d63638;';
			for (const err of data.errors) {
				const li = document.createElement('li');
				li.textContent = err;
				errList.appendChild(li);
			}
			this.orphanContent.appendChild(errList);
		}

		if (this.orphanActions) this.orphanActions.style.display = 'none';

		// Auto-hide after 10 seconds
		setTimeout(() => {
			this.hideOrphanPanel();
		}, 10000);
	}

	/**
	 * Hide the orphan detection panel
	 */
	hideOrphanPanel() {
		if (this.orphanPanel) {
			this.fadeOut(this.orphanPanel);
		}
		this.detectedOrphans = [];
	}

	/**
	 * Show progress
	 */
	showProgress(message, percentage = 0) {
		if (this.stageProgress) {
			this.stageProgress.style.display = 'block';
			this.stageProgress.style.opacity = '1'; // Ensure opacity is set to 1
			this.stageProgress.style.transition = 'opacity 0.3s ease-in'; // Smooth fade in
		}
		if (this.stageProgressFill) {
			this.stageProgressFill.style.width = `${percentage}%`;
		}
		if (this.stageProgressText) {
			this.stageProgressText.textContent = message;
		}
	}

	/**
	 * Show notice using dialog
	 */
	showNotice(type, message) {
		// Get title based on type
		const titles = {
			success: 'Success',
			error: 'Error',
			warning: 'Warning',
			info: 'Information'
		};

		const title = titles[type] || 'Notification';

		// Show dialog based on type
		switch (type) {
			case 'success':
				syncDialog.showSuccess(title, message);
				break;
			case 'error':
				syncDialog.showError(title, message);
				break;
			case 'warning':
				syncDialog.showWarning(title, message);
				break;
			case 'info':
			default:
				syncDialog.showInfo(title, message);
				break;
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
