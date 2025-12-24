<?php
/**
 * Sync Automatic Settings Component
 *
 * Handles rendering and management of automatic sync configuration including
 * toggle controls, frequency settings, cron scheduling, and timing configuration.
 * Provides UI for configuring automated procedure synchronization from the
 * BRAGBook API.
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
 * Sync Automatic Settings class
 *
 * Manages automatic sync configuration UI including enable/disable toggles,
 * frequency settings (weekly or custom), and cron status display. Handles
 * the presentation of sync scheduling options with server/browser time display.
 *
 * ## Features:
 * - Auto-sync enable/disable toggle
 * - Weekly or custom date/time scheduling
 * - Cron status display with next run time
 * - Server and browser time synchronization
 * - Timezone information display
 * - Date and time pickers
 *
 * ## Frequency Options:
 * - Weekly: Syncs once per week at specified time
 * - Custom: One-time sync at specific date/time
 *
 * @since 3.3.0
 */
final class Sync_Automatic_Settings {

	/**
	 * Option name for settings
	 *
	 * @since 3.3.0
	 * @var string
	 */
	private string $option_name;

	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 *
	 * @param string $option_name The option name for settings storage.
	 */
	public function __construct( string $option_name = 'brag_book_gallery_sync_settings' ) {
		$this->option_name = $option_name;
	}

	/**
	 * Render auto sync field
	 *
	 * Displays toggle control for enabling/disabling automatic sync with
	 * description and cron status.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render_auto_sync_field(): void {
		$settings = $this->get_settings();
		$value    = $settings['auto_sync_enabled'] ?? false;
		?>
		<div class="brag-book-gallery-toggle-wrapper">
			<label class="brag-book-gallery-toggle">
				<input type="hidden" name="<?php echo esc_attr( $this->option_name ); ?>[auto_sync_enabled]" value="0" />
				<input type="checkbox"
					   name="<?php echo esc_attr( $this->option_name ); ?>[auto_sync_enabled]"
					   value="1"
					   id="auto_sync_enabled"
					   <?php checked( $value ); ?> />
				<span class="brag-book-gallery-toggle-slider"></span>
			</label>
			<span class="brag-book-gallery-toggle-label">
				<?php esc_html_e( 'Enable automatic synchronization of procedures', 'brag-book-gallery' ); ?>
			</span>
		</div>
		<p class="description">
			<?php esc_html_e( 'When enabled, procedures will be automatically synced based on the frequency setting below.', 'brag-book-gallery' ); ?>
		</p>
		<?php $this->render_cron_status(); ?>
		<?php
	}

	/**
	 * Render cron status display
	 *
	 * Shows the current cron schedule status and BragBook sync status.
	 *
	 * @since 3.3.0
	 * @since 4.0.2 Added BragBook sync status display
	 *
	 * @return void Outputs HTML directly
	 */
	public function render_cron_status(): void {
		// Render BragBook sync status first
		$this->render_bragbook_sync_status();

		// Then show local WordPress cron status
		$next_run = wp_next_scheduled( 'brag_book_gallery_automatic_sync' );

		if ( $next_run ) {
			$human_time = human_time_diff( time(), $next_run );
			$exact_time = wp_date( 'Y-m-d H:i:s', $next_run );
			?>
			<div class="notice notice-info inline" style="margin-top: 10px;">
				<p>
					<strong><?php esc_html_e( 'WordPress Cron:', 'brag-book-gallery' ); ?></strong>
					<?php
					if ( time() > $next_run ) {
						printf(
							/* translators: %s: exact date and time */
							esc_html__( 'Overdue (was scheduled for %s)', 'brag-book-gallery' ),
							esc_html( $exact_time )
						);
					} else {
						printf(
							/* translators: 1: human readable time, 2: exact date and time */
							esc_html__( 'Next run in %1$s (%2$s)', 'brag-book-gallery' ),
							esc_html( $human_time ),
							esc_html( $exact_time )
						);
					}
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Render BragBook sync status
	 *
	 * Displays the current sync registration status with BragBook including
	 * last sync, next scheduled sync, and current job status.
	 *
	 * @since 4.0.2
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_bragbook_sync_status(): void {
		$sync_api    = new Sync_Api();
		$current_job = $sync_api->get_current_job();
		$last_report = $sync_api->get_last_report();

		// Check if API is configured
		$api_tokens     = get_option( 'brag_book_gallery_api_token', [] );
		$property_ids   = get_option( 'brag_book_gallery_website_property_id', [] );
		$is_configured  = ! empty( $api_tokens ) && ! empty( $property_ids );

		if ( ! $is_configured ) {
			?>
			<div class="bragbook-auto-sync-status status-not-configured">
				<div class="status-header">
					<span class="status-icon"></span>
					<span class="status-title"><?php esc_html_e( 'BragBook Sync Status', 'brag-book-gallery' ); ?></span>
				</div>
				<div class="status-content">
					<p class="status-message">
						<?php esc_html_e( 'API credentials not configured. Configure your API token and Website Property ID in the API Settings to enable sync registration with BragBook.', 'brag-book-gallery' ); ?>
					</p>
				</div>
			</div>
			<?php
			return;
		}

		// Determine overall status
		$status_class = 'status-idle';
		$status_label = __( 'Ready', 'brag-book-gallery' );

		if ( $current_job && in_array( $current_job['status'] ?? '', [ 'PENDING', 'IN_PROGRESS' ], true ) ) {
			$status_class = 'status-active';
			$status_label = $current_job['status'] === 'IN_PROGRESS'
				? __( 'Syncing', 'brag-book-gallery' )
				: __( 'Pending', 'brag-book-gallery' );
		} elseif ( $last_report ) {
			switch ( $last_report['status'] ?? '' ) {
				case 'SUCCESS':
					$status_class = 'status-success';
					$status_label = __( 'Last Sync Successful', 'brag-book-gallery' );
					break;
				case 'PARTIAL':
					$status_class = 'status-warning';
					$status_label = __( 'Last Sync Partial', 'brag-book-gallery' );
					break;
				case 'FAILED':
				case 'TIMEOUT':
					$status_class = 'status-error';
					$status_label = __( 'Last Sync Failed', 'brag-book-gallery' );
					break;
			}
		}
		?>
		<div class="bragbook-auto-sync-status <?php echo esc_attr( $status_class ); ?>" id="bragbook-auto-sync-status">
			<div class="status-header">
				<span class="status-icon"></span>
				<span class="status-title"><?php esc_html_e( 'BragBook Sync Status', 'brag-book-gallery' ); ?></span>
				<span class="status-badge"><?php echo esc_html( $status_label ); ?></span>
			</div>

			<div class="status-content">
				<div class="status-grid">
					<?php if ( $last_report ) : ?>
						<!-- Last Sync Info -->
						<div class="status-item">
							<span class="item-label"><?php esc_html_e( 'Last Sync Reported', 'brag-book-gallery' ); ?></span>
							<span class="item-value">
								<?php
								if ( ! empty( $last_report['reported_at'] ) ) {
									$reported_time = strtotime( $last_report['reported_at'] );
									echo esc_html( wp_date( 'M j, Y g:i A', $reported_time ) );
									echo ' <span class="time-ago">(' . esc_html( human_time_diff( $reported_time, time() ) ) . ' ' . esc_html__( 'ago', 'brag-book-gallery' ) . ')</span>';
								} else {
									esc_html_e( 'Never', 'brag-book-gallery' );
								}
								?>
							</span>
						</div>

						<!-- Cases Synced -->
						<?php if ( isset( $last_report['cases_synced'] ) && $last_report['cases_synced'] > 0 ) : ?>
							<div class="status-item">
								<span class="item-label"><?php esc_html_e( 'Cases Synced', 'brag-book-gallery' ); ?></span>
								<span class="item-value"><?php echo esc_html( number_format( $last_report['cases_synced'] ) ); ?></span>
							</div>
						<?php endif; ?>

						<!-- Next Scheduled Sync from BragBook -->
						<?php if ( ! empty( $last_report['next_sync']['scheduledAt'] ) ) : ?>
							<div class="status-item next-sync">
								<span class="item-label"><?php esc_html_e( 'Next BragBook Sync', 'brag-book-gallery' ); ?></span>
								<span class="item-value">
									<?php
									$next_time = strtotime( $last_report['next_sync']['scheduledAt'] );
									echo esc_html( wp_date( 'M j, Y g:i A', $next_time ) );

									if ( $next_time > time() ) {
										echo ' <span class="time-until">(in ' . esc_html( human_time_diff( time(), $next_time ) ) . ')</span>';
									}

									if ( ! empty( $last_report['next_sync']['jobId'] ) ) {
										echo ' <span class="job-id">Job #' . esc_html( $last_report['next_sync']['jobId'] ) . '</span>';
									}
									?>
								</span>
							</div>
						<?php endif; ?>

						<!-- Manual Sync Required Warning -->
						<?php if ( ! empty( $last_report['manual_sync_required'] ) ) : ?>
							<div class="status-item status-warning-item">
								<span class="item-label"><?php esc_html_e( 'Action Required', 'brag-book-gallery' ); ?></span>
								<span class="item-value warning-text">
									<?php esc_html_e( 'Manual sync required due to previous failures.', 'brag-book-gallery' ); ?>
								</span>
							</div>
						<?php endif; ?>

					<?php else : ?>
						<div class="status-item">
							<span class="item-label"><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></span>
							<span class="item-value"><?php esc_html_e( 'No sync has been reported to BragBook yet. Run a sync to register with the BragBook application.', 'brag-book-gallery' ); ?></span>
						</div>
					<?php endif; ?>

					<!-- Active Job Info -->
					<?php if ( $current_job && ! empty( $current_job['job_id'] ) ) : ?>
						<div class="status-item active-job">
							<span class="item-label"><?php esc_html_e( 'Active Job', 'brag-book-gallery' ); ?></span>
							<span class="item-value">
								<?php
								printf(
									/* translators: 1: job ID, 2: job status */
									esc_html__( 'Job #%1$s - %2$s', 'brag-book-gallery' ),
									esc_html( $current_job['job_id'] ),
									esc_html( $current_job['status'] ?? 'PENDING' )
								);
								?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render sync frequency field
	 *
	 * Displays day of week and time selection for weekly sync scheduling.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render_sync_frequency_field(): void {
		$settings    = $this->get_settings();
		$sync_day    = $settings['sync_day'] ?? '0'; // Default to Sunday
		$sync_time   = $settings['sync_time'] ?? '02:00'; // Default to 2:00 AM

		$days_of_week = array(
			'0' => __( 'Sunday', 'brag-book-gallery' ),
			'1' => __( 'Monday', 'brag-book-gallery' ),
			'2' => __( 'Tuesday', 'brag-book-gallery' ),
			'3' => __( 'Wednesday', 'brag-book-gallery' ),
			'4' => __( 'Thursday', 'brag-book-gallery' ),
			'5' => __( 'Friday', 'brag-book-gallery' ),
			'6' => __( 'Saturday', 'brag-book-gallery' ),
		);
		?>
		<div class="sync-frequency-wrapper">
			<div class="sync-schedule-fields">
				<div class="sync-schedule-field">
					<label for="sync_day"><?php esc_html_e( 'Day of Week:', 'brag-book-gallery' ); ?></label>
					<select name="<?php echo esc_attr( $this->option_name ); ?>[sync_day]"
							id="sync_day"
							class="sync-day-select">
						<?php foreach ( $days_of_week as $day_value => $day_label ) : ?>
							<option value="<?php echo esc_attr( $day_value ); ?>"
									<?php selected( $sync_day, $day_value ); ?>>
								<?php echo esc_html( $day_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sync-schedule-field">
					<label for="sync_time"><?php esc_html_e( 'Time:', 'brag-book-gallery' ); ?></label>
					<input type="time"
						   name="<?php echo esc_attr( $this->option_name ); ?>[sync_time]"
						   id="sync_time"
						   value="<?php echo esc_attr( $sync_time ); ?>"
						   class="sync-time" />
				</div>
			</div>
		</div>
		<p class="description">
			<?php esc_html_e( 'Procedures will be automatically synced once per week on the selected day and time.', 'brag-book-gallery' ); ?>
		</p>
		<style>
			.sync-schedule-fields {
				display: flex;
				gap: 20px;
				align-items: flex-end;
				margin-top: 10px;
			}
			.sync-schedule-field {
				display: flex;
				flex-direction: column;
				gap: 5px;
			}
			.sync-schedule-field label {
				font-weight: 600;
				margin: 0;
			}
			.sync-day-select {
				min-width: 150px;
			}
			.sync-time {
				min-width: 150px;
				padding: 0 8px;
				height: 30px;
				font-size: 14px;
				line-height: 2;
			}
		</style>
		<?php
	}

	/**
	 * Render server time display
	 *
	 * Displays server time, timezone, and browser time for sync scheduling reference.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render_server_time_display(): void {
		?>
		<div class="server-time-display">
			<div class="time-display-grid">
				<div class="time-display-item">
					<strong><?php esc_html_e( 'Server Time:', 'brag-book-gallery' ); ?></strong>
					<span id="server-time" class="time-value">
						<?php echo esc_html( wp_date( 'Y-m-d H:i:s' ) ); ?>
					</span>
				</div>
				<div class="time-display-item">
					<strong><?php esc_html_e( 'Server Timezone:', 'brag-book-gallery' ); ?></strong>
					<span class="time-value">
						<?php echo esc_html( wp_timezone_string() ); ?>
					</span>
				</div>
				<div class="time-display-item">
					<strong><?php esc_html_e( 'Current Browser Time:', 'brag-book-gallery' ); ?></strong>
					<span id="browser-time" class="time-value"></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get settings
	 *
	 * Retrieves the current sync settings from database.
	 *
	 * @since 3.3.0
	 *
	 * @return array Settings array
	 */
	private function get_settings(): array {
		return get_option( $this->option_name, array() );
	}
}
