<?php
/**
 * Sync Manual Controls Component
 *
 * Handles rendering of manual sync controls including stage-based sync buttons,
 * progress tracking, and file status indicators. Provides the UI for triggering
 * manual synchronization of procedures and cases from the BRAGBook API.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Sync
 * @since      3.3.0
 * @version    3.3.0
 *
 * @author     BRAGBook Team
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Sync;

use BRAGBookGallery\Includes\Sync\Sync_Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync Manual Controls class
 *
 * Renders the manual sync control center with stage-based synchronization,
 * progress tracking, and file status monitoring. Supports three-stage sync
 * process: procedures fetch, manifest building, and case processing.
 *
 * ## Features:
 * - Stage 1: Procedures sync with sidebar data
 * - Stage 2: Case ID manifest building
 * - Stage 3: Case processing from manifest
 * - Full sync (all stages sequentially)
 * - Real-time progress tracking
 * - File status indicators
 * - Sync data/manifest deletion
 *
 * ## UI Components:
 * - Stage buttons with tooltips
 * - Progress bars with animations
 * - File status badges
 * - Start/Stop controls
 * - Results display area
 *
 * @since 3.3.0
 */
final class Sync_Manual_Controls {

	/**
	 * Render the manual sync section
	 *
	 * Displays the sync control center with stage-based controls, progress
	 * indicators, and file status monitoring.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		?>
		<!-- BRAG book Sync Status -->
		<?php $this->render_bragbook_sync_status(); ?>

		<!-- Stage-Based Sync Controls -->
		<h2><?php esc_html_e( 'Data Synchronization', 'brag-book-gallery' ); ?></h2>
		<div class="stage-sync-section">

			<!-- Stage Buttons -->
			<?php $this->render_stage_buttons(); ?>

			<!-- Stage Progress -->
			<?php $this->render_stage_progress(); ?>

			<!-- Stage Status Panels -->
			<?php $this->render_stage_status_panels(); ?>

			<!-- Full Sync Controls -->
			<?php $this->render_full_sync_controls(); ?>
		</div>
		<?php
	}

	/**
	 * Render stage buttons
	 *
	 * Displays the three stage sync buttons with tooltips.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_stage_buttons(): void {
		?>
		<div class="stage-sync-buttons">
			<button type="button" id="stage-1-btn" class="button stage-button stage-button--1" title="<?php esc_attr_e( 'Fetch sidebar data and process procedures', 'brag-book-gallery' ); ?>">
				<span class="stage-btn-number">1</span>
				<span class="stage-btn-body">
					<span class="stage-btn-label"><?php esc_html_e( 'Procedures', 'brag-book-gallery' ); ?></span>
				</span>
			</button>
			<button type="button" id="stage-2-btn" class="button stage-button stage-button--2" title="<?php esc_attr_e( 'Build case ID manifest', 'brag-book-gallery' ); ?>">
				<span class="stage-btn-number">2</span>
				<span class="stage-btn-body">
					<span class="stage-btn-label"><?php esc_html_e( 'Build Manifest', 'brag-book-gallery' ); ?></span>
				</span>
			</button>
			<button type="button" id="stage-3-btn" class="button stage-button stage-button--3" title="<?php esc_attr_e( 'Process cases from manifest', 'brag-book-gallery' ); ?>">
				<span class="stage-btn-number">3</span>
				<span class="stage-btn-body">
					<span class="stage-btn-label"><?php esc_html_e( 'Process Cases', 'brag-book-gallery' ); ?></span>
				</span>
			</button>
		</div>
		<?php
	}

	/**
	 * Render stage progress indicator
	 *
	 * Displays animated progress bar with status text.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_stage_progress(): void {
		?>
		<div id="stage-progress" class="stage-progress" style="display: none;">
			<div class="stage-progress-bar">
				<div id="stage-progress-fill" class="stage-progress-fill">
					<div class="stage-progress-stripes"></div>
				</div>
			</div>
			<div class="stage-progress-footer">
				<div id="stage-progress-text" class="stage-progress-text"></div>
				<span id="stage-progress-timer" class="stage-progress-timer"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render stage status panels
	 *
	 * Displays status information for each stage including data previews.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_stage_status_panels(): void {
		?>
		<!-- Stage 1 Status -->
		<div id="stage1-status" class="stage-status-panel stage-status-panel--1" style="display: none;">
			<div class="stage-status-panel-header">
				<span class="stage-status-step">1</span>
				<h5><?php esc_html_e( 'Stage 1 — Procedures', 'brag-book-gallery' ); ?></h5>
				<span id="sync-data-status" class="file-status-pill" style="margin-left: auto;">
					<span class="status-icon"></span>
					<span class="file-status-label"><?php esc_html_e( 'Sync Data', 'brag-book-gallery' ); ?></span>
				</span>
				<span id="sync-data-date" class="file-date"></span>
				<a id="sync-data-link" href="#" target="_blank" class="file-link" style="display: none;" title="<?php esc_attr_e( 'View JSON file', 'brag-book-gallery' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h560v-280h80v280q0 33-23.5 56.5T760-120H200Zm188-212-56-56 372-372H560v-80h280v280h-80v-144L388-332Z"/></svg>
					<?php esc_html_e( 'View', 'brag-book-gallery' ); ?>
				</a>
			</div>
			<div id="stage1-status-content" class="stage-status-content"></div>
			<div class="stage-status-panel-actions">
				<button type="button" id="delete-sync-data-btn" class="button button-danger-outline button-small" title="<?php esc_attr_e( 'Delete procedures.json file', 'brag-book-gallery' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor" class="sync-btn-icon"><path d="M292.31-140Q262-140 241-161q-21-21-21-51.31V-720h-40v-60h180v-35.38h240V-780h180v60h-40v507.69Q740-182 719-161q-21 21-51.31 21H292.31ZM680-720H280v507.69q0 5.39 3.46 8.85 3.47 3.46 8.85 3.46h375.38q4.62 0 8.46-3.46 3.85-3.46 3.85-8.85V-720ZM376-280h60v-360h-60v360Zm148 0h60v-360h-60v360ZM280-720v520-520Z"/></svg>
					<?php esc_html_e( 'Delete Sync Data', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</div>

		<!-- Manifest -->
		<div id="manifest-preview" class="stage-status-panel stage-status-panel--2" style="display: none;">
			<div class="stage-status-panel-header">
				<span class="stage-status-step">2</span>
				<h5><?php esc_html_e( 'Stage 2 — Manifest', 'brag-book-gallery' ); ?></h5>
				<span id="manifest-status" class="file-status-pill" style="margin-left: auto;">
					<span class="status-icon"></span>
					<span class="file-status-label"><?php esc_html_e( 'Manifest', 'brag-book-gallery' ); ?></span>
				</span>
				<span id="manifest-date" class="file-date"></span>
				<a id="manifest-link" href="#" target="_blank" class="file-link" style="display: none;" title="<?php esc_attr_e( 'View JSON file', 'brag-book-gallery' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h560v-280h80v280q0 33-23.5 56.5T760-120H200Zm188-212-56-56 372-372H560v-80h280v280h-80v-144L388-332Z"/></svg>
					<?php esc_html_e( 'View', 'brag-book-gallery' ); ?>
				</a>
			</div>
			<div id="manifest-preview-content" class="stage-status-content"></div>
			<div class="stage-status-panel-actions">
				<button type="button" id="delete-manifest-btn" class="button button-danger-outline button-small" title="<?php esc_attr_e( 'Delete manifest.json file', 'brag-book-gallery' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor" class="sync-btn-icon"><path d="M292.31-140Q262-140 241-161q-21-21-21-51.31V-720h-40v-60h180v-35.38h240V-780h180v60h-40v507.69Q740-182 719-161q-21 21-51.31 21H292.31ZM680-720H280v507.69q0 5.39 3.46 8.85 3.47 3.46 8.85 3.46h375.38q4.62 0 8.46-3.46 3.85-3.46 3.85-8.85V-720ZM376-280h60v-360h-60v360Zm148 0h60v-360h-60v360ZM280-720v520-520Z"/></svg>
					<?php esc_html_e( 'Delete Manifest', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</div>

		<!-- Stage 3 Status -->
		<div id="stage3-status" class="stage-status-panel stage-status-panel--3" style="display: none;">
			<div class="stage-status-panel-header">
				<span class="stage-status-step">3</span>
				<h5><?php esc_html_e( 'Stage 3 — Cases', 'brag-book-gallery' ); ?></h5>
			</div>
			<div id="stage3-status-content" class="stage-status-content"></div>
		</div>

		<!-- Orphan Detection Panel -->
		<div id="orphan-detection-panel" class="stage-status-panel stage-status-panel--orphan" style="display: none;">
			<div class="stage-status-panel-header">
				<span class="stage-status-step stage-status-step--warning">
					<svg xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor"><path d="M109.23-160 480-800l370.77 640H109.23ZM178-200h604L480-720 178-200Zm302-55.38q10.31 0 17.46-7.16 7.16-7.15 7.16-17.46 0-10.31-7.16-17.46-7.15-7.16-17.46-7.16-10.31 0-17.46 7.16-7.16 7.15-7.16 17.46 0 10.31 7.16 17.46 7.15 7.16 17.46 7.16Zm-20-90.77h40v-160h-40v160ZM480-480Z"/></svg>
				</span>
				<h5><?php esc_html_e( 'Orphan Detection', 'brag-book-gallery' ); ?></h5>
			</div>
			<div id="orphan-detection-content" class="stage-status-content">
				<p class="description"><?php esc_html_e( 'Scanning for orphaned items...', 'brag-book-gallery' ); ?></p>
			</div>
			<div id="orphan-actions" class="stage-status-panel-actions" style="display: none;">
				<button type="button" id="delete-orphans-btn" class="button button-danger-outline button-small">
					<svg xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor" class="sync-btn-icon"><path d="M292.31-140Q262-140 241-161q-21-21-21-51.31V-720h-40v-60h180v-35.38h240V-780h180v60h-40v507.69Q740-182 719-161q-21 21-51.31 21H292.31ZM680-720H280v507.69q0 5.39 3.46 8.85 3.47 3.46 8.85 3.46h375.38q4.62 0 8.46-3.46 3.85-3.46 3.85-8.85V-720ZM376-280h60v-360h-60v360Zm148 0h60v-360h-60v360ZM280-720v520-520Z"/></svg>
					<?php esc_html_e( 'Delete Orphans', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" id="skip-orphans-btn" class="button button-secondary button-small">
					<?php esc_html_e( 'Skip', 'brag-book-gallery' ); ?>
				</button>
			</div>
			<div id="orphan-result" style="display: none; margin-top: 10px;"></div>
		</div>
		<?php
	}

	/**
	 * Render full sync controls
	 *
	 * Displays buttons for full sync and stop operations.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_full_sync_controls(): void {
		?>
		<div class="full-sync-controls">
			<button type="button" id="full-sync-btn" class="button button-full-sync" title="<?php esc_attr_e( 'Run all three stages sequentially', 'brag-book-gallery' ); ?>">
				<?php esc_html_e( 'Full Sync', 'brag-book-gallery' ); ?>
			</button>
			<button type="button" id="stop-sync-btn" class="button button-stop-sync" style="display: none;" title="<?php esc_attr_e( 'Stop the running sync process', 'brag-book-gallery' ); ?>">
				<?php esc_html_e( 'Stop Sync', 'brag-book-gallery' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render BRAG book sync status card
	 *
	 * Displays the current sync job status and last report from BRAG book API.
	 *
	 * @since 4.0.2
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_bragbook_sync_status(): void {
		// Get current job and last report data
		$sync_api    = new Sync_Api();
		$current_job = $sync_api->get_current_job();
		$last_report = $sync_api->get_last_report();

		// Determine connection status
		$is_connected = ! empty( get_option( 'brag_book_gallery_api_token', [] ) ) &&
		                ! empty( get_option( 'brag_book_gallery_website_property_id', [] ) );

		// Determine status class and text
		$status_class = 'status-inactive';
		$status_text  = __( 'Not Connected', 'brag-book-gallery' );

		if ( $is_connected ) {
			if ( $current_job && in_array( $current_job['status'] ?? '', [ 'PENDING', 'IN_PROGRESS' ], true ) ) {
				$status_class = 'status-syncing';
				$status_text  = $current_job['status'] === 'IN_PROGRESS'
					? __( 'Syncing...', 'brag-book-gallery' )
					: __( 'Pending', 'brag-book-gallery' );
			} elseif ( $last_report ) {
				$last_status = $last_report['status'] ?? '';
				switch ( $last_status ) {
					case 'SUCCESS':
						$status_class = 'status-success';
						$status_text  = __( 'Connected', 'brag-book-gallery' );
						break;
					case 'PARTIAL':
						$status_class = 'status-warning';
						$status_text  = __( 'Partial Sync', 'brag-book-gallery' );
						break;
					case 'FAILED':
					case 'TIMEOUT':
						$status_class = 'status-error';
						$status_text  = __( 'Last Sync Failed', 'brag-book-gallery' );
						break;
					default:
						$status_class = 'status-idle';
						$status_text  = __( 'Connected', 'brag-book-gallery' );
				}
			} else {
				$status_class = 'status-idle';
				$status_text  = __( 'Connected', 'brag-book-gallery' );
			}
		}
		?>
		<div class="bragbook-sync-status-card" id="bragbook-sync-status-card">
			<div class="status-card-header">
				<span class="status-icon <?php echo esc_attr( $status_class ); ?>"></span>
				<span class="status-label"><?php esc_html_e( 'BRAG book Status:', 'brag-book-gallery' ); ?></span>
				<span class="status-text <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span>
			</div>

			<?php if ( $is_connected ) : ?>
				<div class="status-card-details">
					<?php if ( $current_job && ! empty( $current_job['job_id'] ) ) : ?>
						<div class="status-detail">
							<span class="detail-label"><?php esc_html_e( 'Job ID:', 'brag-book-gallery' ); ?></span>
							<span class="detail-value"><?php echo esc_html( $current_job['job_id'] ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( $last_report ) : ?>
						<?php if ( ! empty( $last_report['reported_at'] ) ) : ?>
							<div class="status-detail">
								<span class="detail-label"><?php esc_html_e( 'Last Reported:', 'brag-book-gallery' ); ?></span>
								<span class="detail-value">
									<?php
									$reported_time = strtotime( $last_report['reported_at'] );
									echo esc_html( human_time_diff( $reported_time, time() ) . ' ' . __( 'ago', 'brag-book-gallery' ) );
									?>
								</span>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $last_report['cases_synced'] ) ) : ?>
							<div class="status-detail">
								<span class="detail-label"><?php esc_html_e( 'Cases Synced:', 'brag-book-gallery' ); ?></span>
								<span class="detail-value"><?php echo esc_html( number_format( $last_report['cases_synced'] ) ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $last_report['next_sync']['scheduledAt'] ) ) : ?>
							<div class="status-detail">
								<span class="detail-label"><?php esc_html_e( 'Next Sync:', 'brag-book-gallery' ); ?></span>
								<span class="detail-value">
									<?php
									$next_time = strtotime( $last_report['next_sync']['scheduledAt'] );
									echo esc_html( wp_date( 'M j, Y g:i A', $next_time ) );
									?>
								</span>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="status-card-message">
					<p><?php esc_html_e( 'Configure API credentials in the API Settings to enable sync registration.', 'brag-book-gallery' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
