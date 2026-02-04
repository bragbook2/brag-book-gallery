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
		<table class="form-table brag-book-gallery-form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Control Center', 'brag-book-gallery' ); ?></th>
				<td>
					<!-- Enhanced sync control container with modern design -->
					<div id="sync-control-center" class="sync-control-center">

						<!-- Main Control Panel -->
						<div class="sync-control-panel">

							<!-- Header with Status Badge -->
							<div class="sync-control-header">
								<div class="sync-control-title">
									<h3><?php esc_html_e( 'Data Synchronization', 'brag-book-gallery' ); ?></h3>
								</div>
							</div>

							<!-- BRAG Book Sync Status -->
							<?php $this->render_bragbook_sync_status(); ?>

							<!-- Stage-Based Sync Controls -->
							<div class="stage-sync-section">
								<h4><?php esc_html_e( 'Stage-Based Sync', 'brag-book-gallery' ); ?></h4>

								<!-- File Status Indicators -->
								<?php $this->render_file_status_indicators(); ?>

								<!-- Stage Buttons -->
								<?php $this->render_stage_buttons(); ?>

								<!-- Stage Progress -->
								<?php $this->render_stage_progress(); ?>

								<!-- Stage Status Panels -->
								<?php $this->render_stage_status_panels(); ?>

								<!-- Full Sync Controls -->
								<?php $this->render_full_sync_controls(); ?>
							</div>
						</div>
					</div>
				</td>
			</tr>
		</table>

		<!-- Results Section (Hidden by default) -->
		<div id="sync-results" class="brag-book-gallery-section" style="display:none;">
			<h3><?php esc_html_e( 'Sync Results', 'brag-book-gallery' ); ?></h3>
			<div id="sync-results-content" class="brag-book-gallery-results-content"></div>
		</div>
		<?php
	}

	/**
	 * Render file status indicators
	 *
	 * Displays status for sync data and manifest files with links and timestamps.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_file_status_indicators(): void {
		?>
		<div class="stage-file-status">
			<div class="file-status-grid">
				<div class="file-status-item">
					<span id="sync-data-status" class="file-status">
						<span class="status-icon">
							<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ccc"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
						</span>
						<?php esc_html_e( 'Sync Data', 'brag-book-gallery' ); ?>
					</span>
					<span id="sync-data-date" class="file-date"></span>
					<a id="sync-data-link" href="#" target="_blank" class="file-link" style="display: none;" title="<?php esc_attr_e( 'View JSON file', 'brag-book-gallery' ); ?>">
						<?php esc_html_e( '[View]', 'brag-book-gallery' ); ?>
					</a>
				</div>
				<div class="file-status-item">
					<span id="manifest-status" class="file-status">
						<span class="status-icon">
							<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ccc"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
						</span>
						<?php esc_html_e( 'Manifest', 'brag-book-gallery' ); ?>
					</span>
					<span id="manifest-date" class="file-date"></span>
					<a id="manifest-link" href="#" target="_blank" class="file-link" style="display: none;" title="<?php esc_attr_e( 'View JSON file', 'brag-book-gallery' ); ?>">
						<?php esc_html_e( '[View]', 'brag-book-gallery' ); ?>
					</a>
				</div>
			</div>
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
			<button type="button" id="stage-1-btn" class="button button-primary stage-button" title="<?php esc_attr_e( 'Fetch sidebar data and process procedures', 'brag-book-gallery' ); ?>">
				<?php esc_html_e( 'Stage 1: Procedures', 'brag-book-gallery' ); ?>
			</button>
			<button type="button" id="stage-2-btn" class="button stage-button" title="<?php esc_attr_e( 'Build case ID manifest', 'brag-book-gallery' ); ?>">
				<?php esc_html_e( 'Stage 2: Build Manifest', 'brag-book-gallery' ); ?>
			</button>
			<button type="button" id="stage-3-btn" class="button stage-button" title="<?php esc_attr_e( 'Process cases from manifest', 'brag-book-gallery' ); ?>">
				<?php esc_html_e( 'Stage 3: Process Cases', 'brag-book-gallery' ); ?>
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
			<div id="stage-progress-text" class="stage-progress-text"></div>
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
		<div id="stage1-status" class="stage-status-panel" style="display: none;">
			<h5><?php esc_html_e( 'Stage 1 Status', 'brag-book-gallery' ); ?></h5>
			<div id="stage1-status-content" class="stage-status-content"></div>
			<button type="button" id="delete-sync-data-btn" class="button button-link-delete" title="<?php esc_attr_e( 'Delete procedures.json file', 'brag-book-gallery' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="currentColor" style="vertical-align: middle; margin-right: 4px;"><path d="m376-313.85 104-104 104 104L626.15-356l-104-104 104-104L584-606.15l-104 104-104-104L333.85-564l104 104-104 104L376-313.85ZM292.31-140Q262-140 241-161q-21-21-21-51.31V-720h-40v-60h180v-35.38h240V-780h180v60h-40v507.69Q740-182 719-161q-21 21-51.31 21H292.31Z"/></svg>
				<?php esc_html_e( 'Delete Sync Data', 'brag-book-gallery' ); ?>
			</button>
		</div>

		<!-- Manifest Preview -->
		<div id="manifest-preview" class="stage-status-panel" style="display: none;">
			<h5><?php esc_html_e( 'Manifest Preview', 'brag-book-gallery' ); ?></h5>
			<div id="manifest-preview-content" class="stage-status-content"></div>
			<button type="button" id="delete-manifest-btn" class="button button-link-delete" title="<?php esc_attr_e( 'Delete manifest.json file', 'brag-book-gallery' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="currentColor" style="vertical-align: middle; margin-right: 4px;"><path d="m376-313.85 104-104 104 104L626.15-356l-104-104 104-104L584-606.15l-104 104-104-104L333.85-564l104 104-104 104L376-313.85ZM292.31-140Q262-140 241-161q-21-21-21-51.31V-720h-40v-60h180v-35.38h240V-780h180v60h-40v507.69Q740-182 719-161q-21 21-51.31 21H292.31Z"/></svg>
				<?php esc_html_e( 'Delete Manifest', 'brag-book-gallery' ); ?>
			</button>
		</div>

		<!-- Stage 3 Status -->
		<div id="stage3-status" class="stage-status-panel" style="display: none;">
			<h5><?php esc_html_e( 'Stage 3 Status', 'brag-book-gallery' ); ?></h5>
			<div id="stage3-status-content" class="stage-status-content"></div>
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
			<button type="button" id="full-sync-btn" class="button button-hero button-primary-dark" title="<?php esc_attr_e( 'Run all three stages sequentially', 'brag-book-gallery' ); ?>">
				<?php esc_html_e( 'Full Sync', 'brag-book-gallery' ); ?>
			</button>
			<button type="button" id="stop-sync-btn" class="button button-link-delete" style="display: none;" title="<?php esc_attr_e( 'Stop the running sync process', 'brag-book-gallery' ); ?>">
				<span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'Stop', 'brag-book-gallery' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render BRAG Book sync status card
	 *
	 * Displays the current sync job status and last report from BRAG Book API.
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
				<span class="status-label"><?php esc_html_e( 'BRAG Book Status:', 'brag-book-gallery' ); ?></span>
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
