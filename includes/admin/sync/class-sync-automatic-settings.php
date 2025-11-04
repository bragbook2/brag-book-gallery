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
	 * Displays radio buttons for weekly or custom scheduling with date/time pickers.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render_sync_frequency_field(): void {
		$settings    = $this->get_settings();
		$value       = $settings['sync_frequency'] ?? 'weekly';
		$custom_date = $settings['sync_custom_date'] ?? '';
		$custom_time = $settings['sync_custom_time'] ?? '12:00';

		$frequencies = array(
			'weekly' => array(
				'label'       => __( 'Weekly', 'brag-book-gallery' ),
				'description' => __( 'Sync once per week at a specified time', 'brag-book-gallery' ),
			),
			'custom' => array(
				'label'       => __( 'Custom Date/Time', 'brag-book-gallery' ),
				'description' => __( 'Schedule a one-time sync at a specific date and time', 'brag-book-gallery' ),
			),
		);
		?>
		<div class="sync-frequency-wrapper">
			<?php foreach ( $frequencies as $freq_value => $freq_data ) : ?>
				<div class="sync-frequency-option">
					<input type="radio"
						   name="<?php echo esc_attr( $this->option_name ); ?>[sync_frequency]"
						   value="<?php echo esc_attr( $freq_value ); ?>"
						   class="sync-frequency-radio"
						   id="sync_frequency_<?php echo esc_attr( $freq_value ); ?>"
						   <?php checked( $value, $freq_value ); ?> />
					<label for="sync_frequency_<?php echo esc_attr( $freq_value ); ?>">
						<span class="sync-frequency-label" data-description="<?php echo esc_attr( $freq_data['description'] ); ?>">
							<?php echo esc_html( $freq_data['label'] ); ?>
						</span>
					</label>
				</div>
			<?php endforeach; ?>

			<div class="sync-custom-schedule" style="<?php echo 'custom' === $value ? 'display: block;' : 'display: none;'; ?>">
				<div class="sync-custom-fields">
					<div class="sync-custom-field">
						<label for="sync_custom_date"><?php esc_html_e( 'Date:', 'brag-book-gallery' ); ?></label>
						<input type="date"
							   name="<?php echo esc_attr( $this->option_name ); ?>[sync_custom_date]"
							   id="sync_custom_date"
							   value="<?php echo esc_attr( $custom_date ); ?>"
							   class="sync-custom-date" />
					</div>
					<div class="sync-custom-field">
						<label for="sync_custom_time"><?php esc_html_e( 'Time:', 'brag-book-gallery' ); ?></label>
						<input type="time"
							   name="<?php echo esc_attr( $this->option_name ); ?>[sync_custom_time]"
							   id="sync_custom_time"
							   value="<?php echo esc_attr( $custom_time ); ?>"
							   class="sync-custom-time" />
					</div>
				</div>
			</div>
		</div>
		<p class="description">
			<?php esc_html_e( 'Choose how often procedures should be automatically synced from the API. Use "Custom Date/Time" to schedule a one-time sync at a specific date and time.', 'brag-book-gallery' ); ?>
		</p>
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
