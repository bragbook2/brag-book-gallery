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
	 * Shows the current cron schedule status for debugging and confirmation
	 * that the cron is properly scheduled.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render_cron_status(): void {
		$next_run = wp_next_scheduled( 'brag_book_gallery_automatic_sync' );

		if ( $next_run ) {
			$human_time = human_time_diff( time(), $next_run );
			$exact_time = wp_date( 'Y-m-d H:i:s', $next_run );
			?>
			<div class="notice notice-info inline">
				<p>
					<strong><?php esc_html_e( 'Next Scheduled Sync:', 'brag-book-gallery' ); ?></strong>
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
							esc_html__( 'In %1$s (%2$s)', 'brag-book-gallery' ),
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
