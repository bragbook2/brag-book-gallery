<?php
/**
 * Sync Settings Page Class
 *
 * Handles the sync settings page for procedure synchronization from the BRAG book API.
 * Provides interface for syncing procedures and managing sync history.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Admin\Pages
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Pages;

use BRAGBookGallery\Includes\Admin\Core\Settings_Base;
use BRAGBookGallery\Includes\Admin\UI\Traits\Trait_Ajax_Handler;
use BRAGBookGallery\Includes\Sync\Data_Sync;
use Exception;
use Error;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Sync Settings Page Class
 *
 * Manages the sync settings page interface and handles sync operations.
 *
 * @since 3.0.0
 */
class Sync_Page extends Settings_Base {

	use Trait_Ajax_Handler;

	/**
	 * Page configuration
	 *
	 * @since 3.0.0
	 * @var array
	 */
	protected array $page_config = [
		'page_title'    => 'Sync Settings',
		'menu_title'    => 'Sync',
		'capability'    => 'manage_options',
		'menu_slug'     => 'brag-book-gallery-sync',
		'option_group'  => 'brag_book_gallery_sync',
		'option_name'   => 'brag_book_gallery_sync_settings',
		'nonce_action'  => 'brag_book_gallery_sync_nonce',
	];

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		parent::__construct();

		error_log( 'BRAG book Gallery Sync: Sync_Page constructor - registering AJAX actions' );

		// Register AJAX actions directly
		add_action( 'wp_ajax_brag_book_sync_procedures', [ $this, 'handle_sync_procedures' ] );
		add_action( 'wp_ajax_brag_book_full_sync', [ $this, 'handle_full_sync' ] );
		add_action( 'wp_ajax_brag_book_test_automatic_sync', [ $this, 'handle_test_automatic_sync' ] );
		add_action( 'wp_ajax_brag_book_test_database_log', [ $this, 'handle_test_database_log' ] );
		add_action( 'wp_ajax_brag_book_cleanup_empty_logs', [ $this, 'handle_cleanup_empty_logs' ] );
		add_action( 'wp_ajax_brag_book_get_case_progress', [ $this, 'handle_get_case_progress' ] );
		add_action( 'wp_ajax_brag_book_get_detailed_progress', [ $this, 'handle_get_detailed_progress' ] );
		add_action( 'wp_ajax_brag_book_stop_sync', [ $this, 'handle_stop_sync' ] );
		add_action( 'wp_ajax_brag_book_force_clear_sync_state', [ $this, 'handle_force_clear_sync_state' ] );
		add_action( 'wp_ajax_brag_book_clear_sync_log', [ $this, 'handle_clear_sync_log' ] );
		add_action( 'wp_ajax_brag_book_delete_sync_record', [ $this, 'handle_delete_sync_record' ] );
		add_action( 'wp_ajax_brag_book_validate_procedure_assignments', [ $this, 'handle_validate_procedure_assignments' ] );
		add_action( 'wp_ajax_brag_book_get_sync_report', [ $this, 'handle_get_sync_report' ] );

		// Register automatic sync cron hook
		add_action( 'brag_book_gallery_automatic_sync', [ $this, 'handle_automatic_sync_cron' ] );

		error_log( 'BRAG book Gallery Sync: AJAX actions registered, including test_automatic_sync' );
		error_log( 'BRAG book Gallery Sync: Action registered: wp_ajax_brag_book_test_automatic_sync -> handle_test_automatic_sync' );
	}

	/**
	 * Initialize the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		// Set page slug for Settings_Base
		$this->page_slug = $this->page_config['menu_slug'];

		// Register settings
		register_setting(
			$this->page_config['option_group'],
			$this->page_config['option_name'],
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => $this->get_default_settings(),
			]
		);

		// Enqueue admin assets
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Render the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		// Set localized page titles now that translation functions are available
		$this->page_title = __( 'Sync Settings', 'brag-book-gallery' );
		$this->menu_title = __( 'Sync', 'brag-book-gallery' );

		// Process form submission if data was posted
		if ( isset( $_POST['submit'] ) || isset( $_POST['brag_book_gallery_sync_form_submitted'] ) ) {
			$this->save_settings_form();
		}

		$this->render_header();
		?>

		<!-- Custom Notices Section -->
		<div class="brag-book-gallery-notices">
			<?php $this->render_custom_notices(); ?>
		</div>

		<form method="post" action="" id="brag-book-gallery-sync-settings-form">
			<?php wp_nonce_field( 'brag_book_gallery_sync_settings', 'brag_book_gallery_sync_nonce' ); ?>
			<input type="hidden" name="brag_book_gallery_sync_form_submitted" value="1" />

			<!-- Manual Sync Section -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Sync from BRAG book API', 'brag-book-gallery' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Synchronize procedures and cases from the BRAG book API. This process may take several minutes depending on the amount of data.', 'brag-book-gallery' ); ?>
				</p>

				<?php $this->render_manual_sync_section(); ?>
			</div>

			<!-- Auto Sync Settings Section -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Automatic Sync Settings', 'brag-book-gallery' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Configure automatic synchronization of procedures from the BRAG book API.', 'brag-book-gallery' ); ?>
				</p>

				<table class="form-table brag-book-gallery-form-table">
					<tr>
						<th scope="row">
							<label for="auto_sync_enabled"><?php esc_html_e( 'Enable Automatic Sync', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<?php $this->render_auto_sync_field(); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="sync_frequency"><?php esc_html_e( 'Sync Frequency', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<?php $this->render_sync_frequency_field(); ?>
						</td>
					</tr>
				</table>
			</div>


			<?php submit_button( __( 'Save Settings', 'brag-book-gallery' ) ); ?>
		</form>

		<?php
		$this->render_footer();
	}

	/**
	 * Save settings form data
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_settings_form(): void {
		// Verify nonce
		if ( ! isset( $_POST['brag_book_gallery_sync_nonce'] ) ||
			 ! wp_verify_nonce( $_POST['brag_book_gallery_sync_nonce'], 'brag_book_gallery_sync_settings' ) ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_settings = $this->get_settings();
		$new_settings = $current_settings;

		// Update auto sync setting
		$new_settings['auto_sync_enabled'] = isset( $_POST[ $this->page_config['option_name'] ]['auto_sync_enabled'] );

		// Update sync frequency
		if ( isset( $_POST[ $this->page_config['option_name'] ]['sync_frequency'] ) ) {
			$allowed_frequencies = [ 'daily', 'weekly', 'monthly', 'custom' ];
			$frequency = sanitize_text_field( $_POST[ $this->page_config['option_name'] ]['sync_frequency'] );
			if ( in_array( $frequency, $allowed_frequencies, true ) ) {
				$new_settings['sync_frequency'] = $frequency;
			}
		}

		// Update custom sync date (only if custom frequency is selected)
		if ( isset( $_POST[ $this->page_config['option_name'] ]['sync_custom_date'] ) ) {
			$custom_date = sanitize_text_field( $_POST[ $this->page_config['option_name'] ]['sync_custom_date'] );
			// Validate date format (YYYY-MM-DD)
			if ( empty( $custom_date ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $custom_date ) ) {
				$new_settings['sync_custom_date'] = $custom_date;
			}
		}

		// Update custom sync time (only if custom frequency is selected)
		if ( isset( $_POST[ $this->page_config['option_name'] ]['sync_custom_time'] ) ) {
			$custom_time = sanitize_text_field( $_POST[ $this->page_config['option_name'] ]['sync_custom_time'] );
			// Validate time format (HH:MM)
			if ( preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $custom_time ) ) {
				$new_settings['sync_custom_time'] = $custom_time;
			}
		}


		// Save settings
		if ( $this->update_settings( $new_settings ) ) {
			add_settings_error(
				'brag_book_gallery_sync_settings',
				'settings_saved',
				__( 'Settings saved successfully.', 'brag-book-gallery' ),
				'success'
			);
		} else {
			add_settings_error(
				'brag_book_gallery_sync_settings',
				'settings_error',
				__( 'Failed to save settings.', 'brag-book-gallery' ),
				'error'
			);
		}
	}

	/**
	 * Get default settings
	 *
	 * @since 3.0.0
	 * @return array Default settings array
	 */
	protected function get_default_settings(): array {
		return [
			'auto_sync_enabled'    => false,
			'sync_frequency'       => 'weekly',
			'sync_custom_date'     => '',
			'sync_custom_time'     => '02:00',
			'last_sync_time'       => '',
			'sync_status'          => 'never',
		];
	}


	/**
	 * Render manual sync section
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render_manual_sync_section(): void {
		$settings = $this->get_settings();

		// Get the most recent sync from the history table (more reliable than settings)
		$latest_sync_from_history = $this->get_latest_sync_from_history();

		if ( $latest_sync_from_history ) {
			$display_time = $latest_sync_from_history->sync_time ?? $latest_sync_from_history->created_at ?? $latest_sync_from_history->started_at ?? '';
			if ( $display_time ) {
				$last_sync = wp_date(
					get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					strtotime( $display_time )
				);
			} else {
				$last_sync = __( 'Unknown', 'brag-book-gallery' );
			}
			$sync_status = $latest_sync_from_history->status;
		} else {
			$last_sync = __( 'Never', 'brag-book-gallery' );
			$sync_status = 'never';
		}

		// Get latest sync details
		$latest_sync = $this->get_latest_sync_info();
		?>
		<table class="form-table brag-book-gallery-form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Status', 'brag-book-gallery' ); ?></th>
				<td>
					<div class="brag-book-gallery-status-card">
						<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
							<div>
								<p style="margin: 0 0 5px 0;"><strong><?php esc_html_e( 'Last Full Sync:', 'brag-book-gallery' ); ?></strong> <span id="last-sync-time"><?php echo esc_html( $last_sync ); ?></span></p>
								<p style="margin: 0;"><strong><?php esc_html_e( 'Status:', 'brag-book-gallery' ); ?></strong>
									<span id="sync-status" class="brag-book-gallery-status brag-book-gallery-status--<?php echo esc_attr( $sync_status ); ?>">
										<?php echo esc_html( ucfirst( $sync_status ) ); ?>
									</span>
								</p>
							</div>
							<?php if ( $latest_sync ) : ?>
								<div style="text-align: right; font-size: 12px; color: #666;">
									<div><strong><?php echo esc_html( number_format( $latest_sync['procedures_total'] ) ); ?></strong> procedures</div>
									<div><strong><?php echo esc_html( number_format( $latest_sync['cases_total'] ) ); ?></strong> cases</div>
									<?php if ( ! empty( $latest_sync['duration'] ) ) : ?>
										<div><?php echo esc_html( $latest_sync['duration'] ); ?></div>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>

						<?php if ( $latest_sync && ! empty( $latest_sync['warnings'] ) ) : ?>
							<div style="margin-top: 10px; padding: 8px; background: #fff3cd; border-left: 3px solid #ffc107; font-size: 12px;">
								<strong><?php esc_html_e( 'Last sync completed with warnings:', 'brag-book-gallery' ); ?></strong>
								<ul style="margin: 5px 0 0 15px;">
									<?php foreach ( $latest_sync['warnings'] as $warning ) : ?>
										<li><?php echo esc_html( $warning ); ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>

						<?php if ( $sync_status === 'never' ) : ?>
							<div style="margin-top: 10px; padding: 8px; background: #e3f2fd; border-left: 3px solid #2196f3; font-size: 12px;">
								<?php esc_html_e( 'No sync has been performed yet. Click "Start Full Sync" to synchronize your procedures and cases from the BRAG book API.', 'brag-book-gallery' ); ?>
							</div>
						<?php endif; ?>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Control', 'brag-book-gallery' ); ?></th>
				<td>
					<div class="sync-actions">
						<button type="button" id="sync-procedures-btn" class="button button-primary">
							<?php esc_html_e( 'Start Sync', 'brag-book-gallery' ); ?>
						</button>
						<button type="button" id="stop-sync-btn" class="button button-secondary" style="display: none;">
							<?php esc_html_e( 'Stop Sync', 'brag-book-gallery' ); ?>
						</button>
						<button type="button" id="force-clear-sync-btn" class="button button-link-delete" style="margin-left: 10px;" title="Clear stuck sync state">
							<?php esc_html_e( 'Force Clear Sync State', 'brag-book-gallery' ); ?>
						</button>
					</div>
				</td>
			</tr>
		</table>

		<!-- File-Based Sync Progress Section -->
		<div id="sync-progress" class="brag-book-gallery-section" style="display:none;">
			<h3><?php esc_html_e( 'Real-Time Sync Progress', 'brag-book-gallery' ); ?></h3>

			<!-- Progress Overview -->
			<div class="sync-progress-overview">
				<div class="progress-stats">
					<div class="stat-item">
						<strong>Status:</strong> <span id="sync-status-text">Ready</span>
					</div>
					<div class="stat-item">
						<strong>Progress:</strong> <span id="sync-overall-percentage">0%</span>
					</div>
					<div class="stat-item">
						<strong>Stage:</strong> <span id="sync-current-operation">Waiting</span>
					</div>
				</div>

				<!-- Overall Progress Bar -->
				<div class="progress-bar-container">
					<div class="progress-bar">
						<div id="sync-overall-fill" class="progress-fill" style="width: 0%;"></div>
					</div>
				</div>
			</div>

			<!-- Real-Time Activity Log -->
			<div class="sync-activity-log">
				<h4><?php esc_html_e( 'Activity Log', 'brag-book-gallery' ); ?></h4>
				<div id="sync-log-container" class="log-container">
					<ul id="sync-progress-items" class="log-entries"></ul>
				</div>
			</div>
		</div>
		</div>

		<!-- Results Section (Hidden by default) -->
		<div id="sync-results" class="brag-book-gallery-section" style="display:none;">
			<h3><?php esc_html_e( 'Sync Results', 'brag-book-gallery' ); ?></h3>
			<div id="sync-results-content" class="brag-book-gallery-results-content"></div>
		</div>
		<?php
	}


	/**
	 * Render auto sync enabled field
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render_auto_sync_field(): void {
		$settings = $this->get_settings();
		$value = $settings['auto_sync_enabled'] ?? false;
		?>
		<div class="brag-book-gallery-toggle-wrapper">
			<label class="brag-book-gallery-toggle">
				<input type="hidden" name="<?php echo esc_attr( $this->page_config['option_name'] ); ?>[auto_sync_enabled]" value="0" />
				<input type="checkbox"
					   name="<?php echo esc_attr( $this->page_config['option_name'] ); ?>[auto_sync_enabled]"
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
		<?php
	}

	/**
	 * Render sync frequency field
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render_sync_frequency_field(): void {
		$settings = $this->get_settings();
		$value = $settings['sync_frequency'] ?? 'weekly';
		$custom_date = $settings['sync_custom_date'] ?? '';
		$custom_time = $settings['sync_custom_time'] ?? '12:00';

		$frequencies = [
			'daily'   => [
				'label' => __( 'Daily', 'brag-book-gallery' ),
				'description' => __( 'Sync every day at a specified time', 'brag-book-gallery' )
			],
			'weekly'  => [
				'label' => __( 'Weekly', 'brag-book-gallery' ),
				'description' => __( 'Sync once per week at a specified time', 'brag-book-gallery' )
			],
			'monthly' => [
				'label' => __( 'Monthly', 'brag-book-gallery' ),
				'description' => __( 'Sync once per month (runs weekly due to WordPress limitations)', 'brag-book-gallery' )
			],
			'custom'  => [
				'label' => __( 'Custom Date/Time', 'brag-book-gallery' ),
				'description' => __( 'Schedule a one-time sync at a specific date and time', 'brag-book-gallery' )
			],
		];
		?>
		<div class="sync-frequency-wrapper">
			<?php foreach ( $frequencies as $freq_value => $freq_data ) : ?>
				<div class="sync-frequency-option">
					<input type="radio"
						   name="<?php echo esc_attr( $this->page_config['option_name'] ); ?>[sync_frequency]"
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

			<div class="sync-custom-schedule" style="<?php echo $value === 'custom' ? 'display: block;' : 'display: none;'; ?>">
				<div class="sync-custom-fields">
					<div class="sync-custom-field">
						<label for="sync_custom_date"><?php esc_html_e( 'Date:', 'brag-book-gallery' ); ?></label>
						<input type="date"
							   name="<?php echo esc_attr( $this->page_config['option_name'] ); ?>[sync_custom_date]"
							   id="sync_custom_date"
							   value="<?php echo esc_attr( $custom_date ); ?>"
							   class="sync-custom-date" />
					</div>
					<div class="sync-custom-field">
						<label for="sync_custom_time"><?php esc_html_e( 'Time:', 'brag-book-gallery' ); ?></label>
						<input type="time"
							   name="<?php echo esc_attr( $this->page_config['option_name'] ); ?>[sync_custom_time]"
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
	 * Handle AJAX sync procedures request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_sync_procedures(): void {
		// Clear any output that might interfere
		if ( ob_get_level() ) {
			ob_clean();
		}

		// Set proper headers
		header( 'Content-Type: application/json' );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_sync_procedures' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ),
			] );
		}

		try {
			$sync = new Data_Sync();
			$result = $sync->sync_procedures();

			// Update sync settings
			$settings = $this->get_settings();
			$settings['last_sync_time'] = current_time( 'mysql' );
			$settings['sync_status'] = $result['success'] ? 'success' : 'error';

			$update_result = $this->update_settings( $settings );

			if ( ! $update_result ) {
				error_log( 'BRAG book Gallery Sync: Failed to update sync settings' );
			} else {
				error_log( 'BRAG book Gallery Sync: Successfully updated sync settings - Status: ' . $settings['sync_status'] . ', Time: ' . $settings['last_sync_time'] );
			}

			$response = [
				'success' => $result['success'],
				'data'    => $result,
			];

		} catch ( Exception $e ) {
			// Update sync settings with error status
			$settings = $this->get_settings();
			$settings['last_sync_time'] = current_time( 'mysql' );
			$settings['sync_status'] = 'error';

			$update_result = $this->update_settings( $settings );

			if ( ! $update_result ) {
				error_log( 'BRAG book Gallery Sync: Failed to update sync settings after error' );
			} else {
				error_log( 'BRAG book Gallery Sync: Successfully updated sync settings after error - Status: error, Time: ' . $settings['last_sync_time'] );
			}

			$response = [
				'success' => false,
				'data'    => [
					'message' => sprintf(
						__( 'Sync failed: %s', 'brag-book-gallery' ),
						$e->getMessage()
					),
					'created' => 0,
					'updated' => 0,
					'errors'  => [ $e->getMessage() ],
				],
			];
		}

		echo wp_json_encode( $response );
		exit;
	}

	/**
	 * Handle AJAX full sync request (two-stage sync)
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_full_sync(): void {
		error_log( 'BRAG book Gallery Sync: handle_full_sync method called - NEW DEBUG' );

		// Clear any output that might interfere
		if ( ob_get_level() ) {
			ob_clean();
		}

		// Set proper headers
		header( 'Content-Type: application/json' );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_sync_procedures' ) ) {
			error_log( 'BRAG book Gallery Sync: Nonce verification FAILED' );
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		error_log( 'BRAG book Gallery Sync: Nonce verification PASSED' );

		// Check permissions
		error_log( 'BRAG book Gallery Sync: Checking permissions...' );
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( 'BRAG book Gallery Sync: Permission check FAILED' );
			wp_send_json_error( [
				'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ),
			] );
		}
		error_log( 'BRAG book Gallery Sync: Permission check PASSED' );

		try {
			error_log( 'BRAG book Gallery Sync: About to test Data_Sync constructor...' );

			// Check if the class exists
			if ( ! class_exists( '\BRAGBookGallery\Includes\Sync\Data_Sync' ) ) {
				error_log( 'BRAG book Gallery Sync: Data_Sync class does not exist!' );
				throw new Exception( 'Data_Sync class not found. Please check the plugin installation.' );
			}

			// Initialize file-based logging for real-time monitoring
			$file_logger = new \BRAGBookGallery\Includes\Admin\Sync_Progress_Logger();
			$session_id = $file_logger->get_session_id();

			$sync_manager = new Data_Sync();
			error_log( 'BRAG book Gallery Sync: Data_Sync constructor completed successfully!' );

			// Hook the file logger into the sync process
			add_action( 'brag_book_sync_progress', [ $file_logger, 'handle_sync_progress' ] );
			add_action( 'brag_book_sync_message', [ $file_logger, 'handle_sync_message' ] );

			$file_logger->write_log( 'info', '🚀 Starting full synchronization with file logging' );
			$file_logger->write_log( 'info', "📋 Session ID: {$session_id}" );

			// Now run the actual sync
			error_log( 'BRAG book Gallery Sync: Running actual two-stage sync...' );
			$result = $sync_manager->run_two_stage_sync();
			error_log( 'BRAG book Gallery Sync: Sync completed with result: ' . wp_json_encode( $result ) );

			// Debug: Check if total_api_cases is in the result
			if (isset($result['total_api_cases'])) {
				error_log( 'BRAG book Gallery Sync: ✅ NEW FEATURE WORKING - total_api_cases: ' . $result['total_api_cases'] );
			} else {
				error_log( 'BRAG book Gallery Sync: ❌ NEW FEATURE MISSING - total_api_cases not found in result' );
			}

			// Log completion to file
			if ( $result['success'] ) {
				$file_logger->write_log( 'success', '🎉 Synchronization completed successfully!' );
			} else {
				$file_logger->write_log( 'error', 'Sync completed with errors: ' . implode( ', ', $result['errors'] ?? [] ) );
			}

			// Also store the final sync results in the database for sync history
			try {
				$database = new \BRAGBookGallery\Includes\Data\Database();
				$database->check_database_version();

				// Prepare detailed sync results for database storage
				$sync_details = [
					'success' => $result['success'],
					'created' => $result['created'] ?? 0,
					'updated' => $result['updated'] ?? 0,
					'cases_created' => $result['cases_created'] ?? 0,
					'cases_updated' => $result['cases_updated'] ?? 0,
					'total_cases_processed' => $result['total_cases_processed'] ?? 0,
					'total_api_cases' => $result['total_api_cases'] ?? 0,
					'warnings' => $result['warnings'] ?? [],
					'session_id' => $session_id,
					'duration' => isset($result['duration_seconds']) ? $result['duration_seconds'] . ' seconds' : '',
				];

				// Store sync completion in database
				$log_id = $database->log_sync_operation(
					'full',
					$result['success'] ? 'completed' : 'failed',
					($result['created'] ?? 0) + ($result['updated'] ?? 0), // procedures processed
					count($result['errors'] ?? []), // failed items
					wp_json_encode($sync_details), // detailed results
					'manual'
				);

				if ($log_id) {
					error_log( 'BRAG book Gallery Sync: ✅ Stored sync results in database with ID: ' . $log_id );
				} else {
					error_log( 'BRAG book Gallery Sync: ❌ Failed to store sync results in database' );
				}
			} catch (Exception $e) {
				error_log( 'BRAG book Gallery Sync: ❌ Database storage error: ' . $e->getMessage() );
			}
			$file_logger->write_completion( $result );

			// Update sync settings (keep for backward compatibility and other features)
			$settings = $this->get_settings();
			$settings['last_sync_time'] = current_time( 'mysql' );
			$settings['sync_status'] = $result['success'] ? 'success' : 'error';

			$update_result = $this->update_settings( $settings );

			if ( ! $update_result ) {
				error_log( 'BRAG book Gallery Sync: Failed to update sync settings (display now uses history table)' );
			} else {
				error_log( 'BRAG book Gallery Sync: Successfully updated sync settings - Status: ' . $settings['sync_status'] . ', Time: ' . $settings['last_sync_time'] );
			}

			$response = [
				'success' => $result['success'],
				'data'    => $result,
				'session_id' => $session_id, // Include session ID for file monitoring
			];

		} catch ( \Throwable $e ) {
			error_log( 'BRAG book Gallery Sync: Fatal error during sync: ' . $e->getMessage() );
			error_log( 'BRAG book Gallery Sync: Error file: ' . $e->getFile() . ' on line ' . $e->getLine() );
			error_log( 'BRAG book Gallery Sync: Error trace: ' . $e->getTraceAsString() );

			// Log error to file if logger exists
			if ( isset( $file_logger ) ) {
				$file_logger->write_log( 'error', 'Sync failed: ' . $e->getMessage() );
				$file_logger->write_completion( [ 'success' => false, 'errors' => [ $e->getMessage() ] ] );
			}

			// Update sync settings with error status
			$settings = $this->get_settings();
			$settings['last_sync_time'] = current_time( 'mysql' );
			$settings['sync_status'] = 'error';

			$update_result = $this->update_settings( $settings );

			if ( ! $update_result ) {
				error_log( 'BRAG book Gallery Sync: Failed to update sync settings after error' );
			} else {
				error_log( 'BRAG book Gallery Sync: Successfully updated sync settings after error - Status: error, Time: ' . $settings['last_sync_time'] );
			}

			$response = [
				'success' => false,
				'data'    => [
					'message' => sprintf(
 						__( 'Full sync failed: %s (File: %s, Line: %d)', 'brag-book-gallery' ),
						$e->getMessage(),
						basename( $e->getFile() ),
						$e->getLine()
					),
					'created' => 0,
					'updated' => 0,
					'cases_created' => 0,
					'cases_updated' => 0,
					'errors'  => [
						$e->getMessage(),
						'File: ' . $e->getFile(),
						'Line: ' . $e->getLine()
					],
					'debug_info' => [
						'error_type' => get_class( $e ),
						'error_file' => $e->getFile(),
						'error_line' => $e->getLine(),
					],
				],
			];
		} catch ( Error $e ) {
			error_log( 'BRAG book Gallery Sync: PHP Error during sync: ' . $e->getMessage() );
			error_log( 'BRAG book Gallery Sync: Error file: ' . $e->getFile() . ' on line ' . $e->getLine() );
			error_log( 'BRAG book Gallery Sync: Error trace: ' . $e->getTraceAsString() );

			// Update sync settings with error status
			$settings = $this->get_settings();
			$settings['last_sync_time'] = current_time( 'mysql' );
			$settings['sync_status'] = 'error';

			$update_result = $this->update_settings( $settings );

			$response = [
				'success' => false,
				'data'    => [
					'message' => sprintf(
						__( 'Full sync failed: %s (File: %s, Line: %d)', 'brag-book-gallery' ),
						$e->getMessage(),
						basename( $e->getFile() ),
						$e->getLine()
					),
					'created' => 0,
					'updated' => 0,
					'cases_created' => 0,
					'cases_updated' => 0,
					'errors'  => [
						$e->getMessage(),
						'File: ' . $e->getFile(),
						'Line: ' . $e->getLine()
					],
					'debug_info' => [
						'error_type' => get_class( $e ),
						'error_file' => $e->getFile(),
						'error_line' => $e->getLine(),
					],
				],
			];
		}

		echo wp_json_encode( $response );
		exit;
	}

	/**
	 * Handle AJAX test automatic sync request
	 *
	 * Simulates an automatic sync by calling the sync manager with 'automatic' source.
	 * This allows testing the automatic sync functionality without waiting for cron.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_test_automatic_sync(): void {
		error_log( 'BRAG Book Gallery: handle_test_automatic_sync method called' );

		// Basic security checks without complex headers
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_test_automatic_sync' ) ) {
			error_log( 'BRAG Book Gallery: Test automatic sync - nonce verification failed' );
			wp_send_json_error( [ 'message' => 'Security check failed.' ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( 'BRAG Book Gallery: Test automatic sync - insufficient permissions' );
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
		}

		error_log( 'BRAG Book Gallery: Test automatic sync - security checks passed' );

		// Test accessing Setup and Sync Manager
		try {
			error_log( 'BRAG Book Gallery: Test automatic sync - step 1: creating Database instance' );
			$database = new \BRAGBookGallery\Includes\Data\Database();
			error_log( 'BRAG Book Gallery: Test automatic sync - step 1: Database created OK' );

			error_log( 'BRAG Book Gallery: Test automatic sync - step 2: getting Setup instance' );
			$setup = \BRAGBookGallery\Includes\Core\Setup::get_instance();
			error_log( 'BRAG Book Gallery: Test automatic sync - step 2: Setup instance OK' );

			error_log( 'BRAG Book Gallery: Test automatic sync - step 3: getting sync_manager service' );
			$sync_manager = $setup->get_service( 'sync_manager' );
			error_log( 'BRAG Book Gallery: Test automatic sync - step 3: sync_manager = ' . ( $sync_manager ? 'FOUND' : 'NULL' ) );

			if ( ! $sync_manager ) {
				wp_send_json_error( [
					'message' => 'Sync manager not available - this might mean the plugin is not in Local mode',
					'debug' => 'sync_manager service is null'
				] );
				return;
			}

			error_log( 'BRAG Book Gallery: Test automatic sync - step 4: forcing database update' );
			$database->check_database_version();
			error_log( 'BRAG Book Gallery: Test automatic sync - step 4: database update complete' );

			error_log( 'BRAG Book Gallery: Test automatic sync - step 5: testing cron job functionality' );

			// Check current cron schedule
			$hook_name = 'brag_book_gallery_automatic_sync';
			$scheduled_time = wp_next_scheduled( $hook_name );

			error_log( 'BRAG Book Gallery: Test automatic sync - checking cron schedule: ' . ( $scheduled_time ? date( 'Y-m-d H:i:s', $scheduled_time ) : 'Not scheduled' ) );

			// Test the cron function directly
			error_log( 'BRAG Book Gallery: Test automatic sync - step 6: calling cron handler directly' );
			$this->handle_automatic_sync_cron();

			error_log( 'BRAG Book Gallery: Test automatic sync - step 6: cron handler completed' );

			wp_send_json_success( [
				'message' => 'Test automatic sync completed! The cron function was executed manually. Check sync history and server logs for results.',
				'success' => true,
				'reload' => true,
				'cron_scheduled' => $scheduled_time ? true : false,
				'next_run' => $scheduled_time ? date( 'Y-m-d H:i:s', $scheduled_time ) : 'Not scheduled'
			] );

		} catch ( \Exception $e ) {
			error_log( 'BRAG Book Gallery: Test automatic sync - Setup/SyncManager failed: ' . $e->getMessage() );
			wp_send_json_error( [
				'message' => 'Component access failed: ' . $e->getMessage(),
				'debug' => 'Setup or sync_manager failed'
			] );
		}
	}

	/**
	 * Handle automatic sync cron job
	 *
	 * This method is called by WordPress cron when automatic sync is enabled.
	 *
	 * @since 3.3.0
	 * @return void
	 */
	public function handle_automatic_sync_cron(): void {
		error_log( 'BRAG Book Gallery: Automatic sync cron job triggered' );

		// Check if automatic sync is still enabled
		$settings = $this->get_settings();
		if ( empty( $settings['auto_sync_enabled'] ) ) {
			error_log( 'BRAG Book Gallery: Automatic sync is disabled, skipping cron execution' );
			return;
		}

		try {
			// Get the sync manager from the Setup service
			$setup = \BRAGBookGallery\Includes\Core\Setup::get_instance();
			$sync_manager = $setup->get_service( 'sync_manager' );

			if ( ! $sync_manager ) {
				error_log( 'BRAG Book Gallery: Sync manager not available for automatic sync' );
				return;
			}

			error_log( 'BRAG Book Gallery: Starting automatic sync via cron' );

			// Initialize the sync progress logger for background logging
			$logger = new \BRAGBookGallery\Includes\Admin\Sync_Progress_Logger();

			// Hook into sync events for logging
			add_action( 'brag_book_sync_progress', [ $logger, 'handle_sync_progress' ] );
			add_action( 'brag_book_sync_message', [ $logger, 'handle_sync_message' ] );

			$logger->write_log( 'info', '🕒 Automatic sync started via cron job' );

			// Run the sync
			$result = $sync_manager->run_two_stage_sync();

			if ( $result['success'] ) {
				$logger->write_log( 'success', '🎉 Automatic sync completed successfully' );
				error_log( 'BRAG Book Gallery: Automatic sync completed successfully' );
			} else {
				$error_message = 'Automatic sync completed with errors: ' . implode( ', ', $result['errors'] );
				$logger->write_log( 'error', '❌ ' . $error_message );
				error_log( 'BRAG Book Gallery: ' . $error_message );
			}

			// Write completion to log
			$logger->write_completion( $result );

		} catch ( \Exception $e ) {
			$error_message = 'Automatic sync failed: ' . $e->getMessage();
			error_log( 'BRAG Book Gallery: ' . $error_message );

			// Try to log the error if logger is available
			if ( isset( $logger ) ) {
				$logger->write_log( 'error', '❌ ' . $error_message );
				$logger->write_completion( [ 'success' => false, 'errors' => [ $e->getMessage() ] ] );
			}
		}
	}

	/**
	 * Handle AJAX test database log request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_test_database_log(): void {
		// Check nonce and permissions
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		try {
			// Get database instance
			$setup = Setup::get_instance();
			$database = $setup->get_service( 'database' );

			if ( ! $database ) {
				wp_send_json_error( [
					'message' => 'Database service not available',
					'debug' => 'database service is null'
				] );
				return;
			}

			// Force database update to ensure table exists with correct schema
			$database->check_database_version();

			// Create a test log entry with manual source
			$log_id = $database->log_sync_operation( 'full', 'completed', 2, 0, 'Test manual sync log entry', 'manual' );

			if ( $log_id ) {
				wp_send_json_success( [
					'message' => 'Test database log entry created successfully! Log ID: ' . $log_id . '. Check sync history.',
					'log_id' => $log_id,
					'reload' => true
				] );
			} else {
				wp_send_json_error( [
					'message' => 'Failed to create test log entry',
					'debug' => 'log_sync_operation returned false'
				] );
			}

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => 'Database test failed: ' . $e->getMessage(),
				'debug' => 'Exception during database test'
			] );
		}
	}

	/**
	 * Handle AJAX cleanup empty logs request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_cleanup_empty_logs(): void {
		// Check nonce and permissions
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		// Delete records that have no meaningful data
		$deleted = $wpdb->query( $wpdb->prepare("
			DELETE FROM {$table_name}
			WHERE (processed = 0 OR processed IS NULL)
			AND (failed = 0 OR failed IS NULL)
			AND (details = '' OR details IS NULL OR details = '[]' OR details = '{}')
		") );

		if ( $deleted !== false ) {
			wp_send_json_success( [
				'message' => sprintf( 'Successfully deleted %d empty sync records.', $deleted ),
				'deleted_count' => $deleted,
				'reload' => true
			] );
		} else {
			wp_send_json_error( [
				'message' => 'Failed to delete empty records. Database error occurred.',
				'deleted_count' => 0
			] );
		}
	}

	/**
	 * Handle AJAX clear sync log request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_clear_sync_log(): void {
		// Clear any output that might interfere
		if ( ob_get_level() ) {
			ob_clean();
		}

		// Set proper headers
		header( 'Content-Type: application/json' );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_clear_sync_log' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ),
			] );
		}

		$cleared_items = 0;
		$errors = [];

		// Clear database sync logs
		global $wpdb;
		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		// Check if table exists before trying to clear it
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
			$db_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
			$result = $wpdb->query( "TRUNCATE TABLE {$table_name}" );

			if ( $result !== false ) {
				$cleared_items += $db_count;
			} else {
				$errors[] = __( 'Failed to clear database sync logs.', 'brag-book-gallery' );
			}
		}

		// Clear file-based sync logs
		$upload_dir = wp_upload_dir();
		$log_files = glob( $upload_dir['basedir'] . '/brag-book-sync-*.log' );

		if ( $log_files ) {
			foreach ( $log_files as $log_file ) {
				if ( unlink( $log_file ) ) {
					$cleared_items++;
				} else {
					$errors[] = sprintf(
						__( 'Failed to delete log file: %s', 'brag-book-gallery' ),
						basename( $log_file )
					);
				}
			}
		}

		if ( empty( $errors ) ) {
			wp_send_json_success( [
				'message' => sprintf(
					__( 'Sync log cleared successfully. Removed %d items.', 'brag-book-gallery' ),
					$cleared_items
				),
			] );
		} else {
			wp_send_json_error( [
				'message' => sprintf(
					__( 'Partially cleared sync logs. Removed %d items. Errors: %s', 'brag-book-gallery' ),
					$cleared_items,
					implode( ', ', $errors )
				),
			] );
		}
	}

	/**
	 * Handle AJAX delete sync record request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_delete_sync_record(): void {
		// Clear any output that might interfere
		if ( ob_get_level() ) {
			ob_clean();
		}

		// Set proper headers
		header( 'Content-Type: application/json' );

		// Debug: Log the incoming request
		// error_log( 'Delete sync record request: ' . print_r( $_POST, true ) );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_delete_sync_record' ) ) {
			error_log( 'Delete sync record: Nonce verification failed' );
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ),
			] );
		}

		// Get record ID
		$record_id = sanitize_text_field( $_POST['record_id'] ?? '' );
		if ( empty( $record_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid record ID.', 'brag-book-gallery' ),
			] );
		}

		// Check if this is a file-based log
		if ( strpos( $record_id, 'file_' ) === 0 ) {
			// Handle file-based log deletion
			$session_id = str_replace( 'file_', '', $record_id );
			$upload_dir = wp_upload_dir();
			$log_file = $upload_dir['basedir'] . '/brag-book-sync-' . $session_id . '.log';

			if ( ! file_exists( $log_file ) ) {
				wp_send_json_error( [
					'message' => __( 'Log file not found.', 'brag-book-gallery' ),
				] );
			}

			// Delete the log file
			$deleted = unlink( $log_file );

			if ( ! $deleted ) {
				wp_send_json_error( [
					'message' => __( 'Failed to delete log file.', 'brag-book-gallery' ),
				] );
			}
			wp_send_json_success( [
				'message' => __( 'Sync log deleted successfully.', 'brag-book-gallery' ),
			] );
		} else {
			// Handle database record deletion (legacy)
			$record_id = absint( $record_id );
			if ( ! $record_id ) {
				wp_send_json_error( [
					'message' => __( 'Invalid record ID.', 'brag-book-gallery' ),
				] );
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'brag_book_sync_log';

			// Check if record exists
			$record = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$record_id
			) );

			if ( ! $record ) {
				wp_send_json_error( [
					'message' => __( 'Record not found.', 'brag-book-gallery' ),
				] );
			}

			// Delete the record
			$deleted = $wpdb->delete( $table_name, [ 'id' => $record_id ], [ '%d' ] );

			if ( $deleted === false ) {
				wp_send_json_error( [
					'message' => __( 'Failed to delete record.', 'brag-book-gallery' ),
				] );
			}

			wp_send_json_success( [
				'message' => __( 'Sync record deleted successfully.', 'brag-book-gallery' ),
			] );
		}
	}

	/**
	 * Handle procedure assignment validation AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_validate_procedure_assignments(): void {
		// Clear any output that might interfere
		if ( ob_get_level() ) {
			ob_clean();
		}

		// Set proper headers
		header( 'Content-Type: application/json' );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_sync' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ),
			] );
		}

		try {
			// Initialize sync class and run validation
			$sync = new Data_Sync();
			$report = $sync->validate_and_fix_unassigned_cases();

			// Format response
			$response = [
				'message' => sprintf(
					__( 'Validation complete. Found: %d, Fixed: %d, Failed: %d', 'brag-book-gallery' ),
					$report['total_found'],
					$report['fixed_count'],
					$report['failed_count']
				),
				'data' => [
					'total_found' => $report['total_found'],
					'fixed_count' => $report['fixed_count'],
					'failed_count' => $report['failed_count'],
					'details' => $report['details'],
				],
			];

			wp_send_json_success( $response );

		} catch ( Exception $e ) {
			wp_send_json_error( [
				'message' => sprintf(
					__( 'Validation failed: %s', 'brag-book-gallery' ),
					$e->getMessage()
				),
			] );
		}
	}

	/**
	 * Get latest sync information for status display using file-based logs
	 *
	 * @since 3.3.0
	 * @return array|null Latest sync info or null if no sync found
	 */
	private function get_latest_sync_info(): ?array {
		// Get the latest sync from file-based logs (current system)
		$latest_sync_record = $this->get_latest_file_based_sync();

		if ( ! $latest_sync_record ) {
			return null;
		}

		// Extract details from the file-based sync record
		$details = json_decode( $latest_sync_record->details, true );
		if ( ! $details ) {
			return null;
		}

		// Format warnings for display
		$warnings = [];
		if ( ! empty( $details['warnings'] ) ) {
			$warnings = $details['warnings'];
		}

		// Add duplicate case count to warnings if present
		if ( ! empty( $details['duplicate_case_count'] ) && $details['duplicate_case_count'] > 0 ) {
			$warnings[] = "Found {$details['duplicate_case_count']} duplicate case IDs";
		}

		return [
			'procedures_total' => $latest_sync_record->procedures_count ?? 0,
			'cases_total' => $latest_sync_record->cases_count ?? 0,
			'duration' => $latest_sync_record->duration ?? '',
			'warnings' => $warnings,
			'status' => $latest_sync_record->status,
			'created_at' => $latest_sync_record->created_at,
			'has_warnings' => $details['has_warnings'] ?? false,
		];
	}


	/**
	 * Get the most recent sync from file-based logs (primary) or database (fallback)
	 *
	 * @since 3.3.0
	 * @return object|null Latest sync record or null if none found
	 */
	private function get_latest_sync_from_history(): ?object {
		// First, try to get sync status from file-based logs (current system)
		$file_sync = $this->get_latest_file_based_sync();
		if ( $file_sync ) {
			return $file_sync;
		}

		// Fallback to database for legacy sync records
		global $wpdb;
		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
			return null;
		}

		// Get the most recent completed sync from database
		return $wpdb->get_row(
			"SELECT * FROM {$table_name}
			WHERE operation = 'complete'
			AND item_type = 'sync_session'
			ORDER BY created_at DESC
			LIMIT 1"
		);
	}

	/**
	 * Get the most recent sync from file-based logs
	 *
	 * @since 3.3.0
	 * @return object|null Latest sync record or null if none found
	 */
	private function get_latest_file_based_sync(): ?object {
		$upload_dir = wp_upload_dir();
		$log_files = glob( $upload_dir['basedir'] . '/brag-book-sync-*.log' );

		if ( ! $log_files ) {
			return null;
		}

		// Sort by modification time (newest first)
		usort( $log_files, function( $a, $b ) {
			return filemtime( $b ) - filemtime( $a );
		} );

		// Parse the most recent log file
		$latest_log = $log_files[0];
		$sync_data = $this->parse_log_file_for_status( $latest_log );

		if ( ! $sync_data ) {
			return null;
		}

		// Create a standardized sync record object
		return (object) [
			'id' => 'file_' . basename( $latest_log, '.log' ),
			'status' => $sync_data['status'],
			'sync_time' => $sync_data['sync_time'],
			'created_at' => $sync_data['sync_time'],
			'started_at' => $sync_data['start_time'],
			'updated_at' => $sync_data['end_time'],
			'procedures_count' => $sync_data['procedures_count'],
			'cases_count' => $sync_data['cases_count'],
			'duration' => $sync_data['duration'],
			'details' => wp_json_encode( $sync_data['details'] ),
			'is_file_based' => true,
			'session_id' => str_replace( 'brag-book-sync-', '', basename( $latest_log, '.log' ) )
		];
	}

	/**
	 * Parse a log file to extract sync status and details
	 *
	 * @since 3.3.0
	 * @param string $log_file Path to the log file
	 * @return array|null Sync data or null if parsing failed
	 */
	private function parse_log_file_for_status( string $log_file ): ?array {
		if ( ! file_exists( $log_file ) ) {
			return null;
		}

		$content = file_get_contents( $log_file );
		if ( ! $content ) {
			return null;
		}

		$lines = explode( "\n", trim( $content ) );
		if ( empty( $lines ) ) {
			return null;
		}

		// Initialize data
		$status = 'running';
		$start_time = null;
		$end_time = null;
		$procedures_created = 0;
		$procedures_updated = 0;
		$cases_created = 0;
		$cases_updated = 0;
		$total_cases_processed = 0;
		$total_api_cases = 0;
		$warnings = [];
		$duplicate_case_count = 0;
		$has_warnings = false;
		$final_step = '';

		// Parse each log entry
		foreach ( $lines as $line ) {
			if ( empty( $line ) ) {
				continue;
			}

			$entry = json_decode( $line, true );
			if ( ! $entry ) {
				continue;
			}

			// Track timing
			if ( ! $start_time && isset( $entry['microtime'] ) ) {
				$start_time = $entry['microtime'];
			}
			if ( isset( $entry['microtime'] ) ) {
				$end_time = $entry['microtime'];
			}

			// Look for completion status
			if ( $entry['type'] === 'complete' ) {
				$status = isset( $entry['success'] ) && $entry['success'] ? 'success' : 'failed';
			}

			// Track final step from progress
			if ( $entry['type'] === 'progress' && isset( $entry['current_step'] ) ) {
				$final_step = $entry['current_step'];
			}

			// Extract counts and warnings from messages
			if ( $entry['type'] === 'message' && isset( $entry['message'] ) ) {
				$message = $entry['message'];

				// Extract procedure counts
				if ( preg_match( '/Created (\d+) procedures/', $message, $matches ) ) {
					$procedures_created = max( $procedures_created, (int) $matches[1] );
				}
				if ( preg_match( '/Updated (\d+) procedures/', $message, $matches ) ) {
					$procedures_updated = max( $procedures_updated, (int) $matches[1] );
				}

				// Extract case counts
				if ( preg_match( '/Created (\d+) cases/', $message, $matches ) ) {
					$cases_created = max( $cases_created, (int) $matches[1] );
				}
				if ( preg_match( '/Updated (\d+) cases/', $message, $matches ) ) {
					$cases_updated = max( $cases_updated, (int) $matches[1] );
				}
				if ( preg_match( '/(\d+) cases processed/', $message, $matches ) ) {
					$total_cases_processed = max( $total_cases_processed, (int) $matches[1] );
				}

				// Extract total API cases including duplicates
				if ( preg_match( '/Total API cases.*?(\d+)/', $message, $matches ) ) {
					$total_api_cases = max( $total_api_cases, (int) $matches[1] );
				}

				// Extract warnings and duplicate information
				if ( preg_match( '/Total warnings:\s*(\d+)/', $message, $matches ) ) {
					$has_warnings = (int) $matches[1] > 0;
				}

				// Extract duplicate case count from warning messages
				if ( preg_match( '/Found (\d+) duplicate case IDs/', $message, $matches ) ) {
					$duplicate_case_count = max( $duplicate_case_count, (int) $matches[1] );
					$warnings[] = $message;
					$has_warnings = true;
				}

				// Capture other warning messages
				if ( strpos( $message, 'Case count mismatch' ) !== false ) {
					$warnings[] = $message;
					$has_warnings = true;
				}
			}
		}

		// Count actual procedures created by counting [CREATED] procedure entries
		$procedure_count = 0;
		foreach ( $lines as $line ) {
			if ( strpos( $line, '[CREATED] Procedure:' ) !== false ) {
				$procedure_count++;
			}
		}

		// Count actual cases created by counting [CREATE] case entries
		$case_count = 0;
		foreach ( $lines as $line ) {
			if ( strpos( $line, '[CREATE]' ) !== false && strpos( $line, 'Case Id:' ) !== false ) {
				$case_count++;
			}
		}

		// Calculate file modification time for sync time
		$file_time = filemtime( $log_file );
		$sync_time = date( 'Y-m-d H:i:s', $file_time );

		// Calculate duration
		$duration = '';
		if ( $start_time && $end_time ) {
			$duration_seconds = round( $end_time - $start_time );
			if ( $duration_seconds > 0 ) {
				$minutes = floor( $duration_seconds / 60 );
				$seconds = $duration_seconds % 60;
				$duration = sprintf( '%02d:%02d', $minutes, $seconds );
			}
		}

		// Build details array
		$details = [
			'created' => max( $procedures_created, $procedure_count ),
			'updated' => $procedures_updated,
			'cases_created' => max( $cases_created, $case_count ),
			'cases_updated' => $cases_updated,
			'total_cases_processed' => max( $total_cases_processed, $case_count ),
			'total_api_cases' => max( $total_api_cases, $case_count ),
			'duration' => $duration,
			'current_step' => $final_step,
			'start_time' => $start_time ? date( 'Y-m-d H:i:s', (int) $start_time ) : null,
			'end_time' => $end_time ? date( 'Y-m-d H:i:s', (int) $end_time ) : null,
			'warnings' => $warnings,
			'has_warnings' => $has_warnings,
			'duplicate_case_count' => $duplicate_case_count,
		];

		return [
			'status' => $status,
			'sync_time' => $sync_time,
			'start_time' => $start_time ? date( 'Y-m-d H:i:s', (int) $start_time ) : $sync_time,
			'end_time' => $end_time ? date( 'Y-m-d H:i:s', (int) $end_time ) : $sync_time,
			'procedures_count' => max( $procedures_created, $procedure_count ),
			'cases_count' => max( $total_cases_processed, $case_count ),
			'duration' => $duration,
			'details' => $details,
			'has_warnings' => $has_warnings,
			'warnings' => $warnings,
		];
	}

	/**
	 * Reset all sync status and clear log files
	 *
	 * @since 3.3.0
	 * @return array Result array with success status and message
	 */
	public function reset_sync_status(): array {
		$cleared_items = [];

		// 1. Clear all sync log files
		$upload_dir = wp_upload_dir();
		$log_files = glob( $upload_dir['basedir'] . '/brag-book-sync-*.log' );
		if ( $log_files ) {
			foreach ( $log_files as $log_file ) {
				if ( unlink( $log_file ) ) {
					$cleared_items[] = 'Log file: ' . basename( $log_file );
				}
			}
		}

		// 2. Reset sync status in settings
		$settings = $this->get_settings();
		$settings['last_sync_time'] = '';
		$settings['sync_status'] = 'never';
		$this->update_settings( $settings );
		$cleared_items[] = 'Settings: Reset sync status to "never"';

		// 3. Clear any sync-related transients
		$transient_keys = [
			'brag_book_gallery_procedures_sidebar',
			'brag_book_gallery_cases',
			'brag_book_gallery_api_test',
			'brag_book_gallery_sync_progress',
			'brag_book_gallery_sync_status'
		];

		foreach ( $transient_keys as $key ) {
			if ( delete_transient( $key ) ) {
				$cleared_items[] = 'Transient: ' . $key;
			}
		}

		// 4. Clear any pending sync options
		global $wpdb;
		$pending_sync_options = $wpdb->get_results(
			"SELECT option_name FROM {$wpdb->options}
			WHERE option_name LIKE 'brag_book_sync_pending_%'"
		);

		foreach ( $pending_sync_options as $option ) {
			delete_option( $option->option_name );
			$cleared_items[] = 'Pending sync: ' . $option->option_name;
		}

		// 5. Clear old database sync records (legacy system)
		$log_table = $wpdb->prefix . 'brag_book_sync_log';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$log_table}'" ) === $log_table ) {
			$deleted_count = $wpdb->query( "DELETE FROM {$log_table}" );
			if ( $deleted_count > 0 ) {
				$cleared_items[] = "Database records: {$deleted_count} old sync records";
			}
		}

		return [
			'success' => true,
			'message' => 'Sync status reset successfully',
			'cleared_items' => $cleared_items,
			'total_cleared' => count( $cleared_items )
		];
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function enqueue_admin_assets(): void {
		$screen = get_current_screen();

		if ( $screen && strpos( $screen->id, $this->page_config['menu_slug'] ) !== false ) {
			wp_enqueue_script(
				'brag-book-file-sync-admin',
				plugins_url( 'assets/js/brag-book-gallery-file-sync-admin.js', dirname( __DIR__, 2 ) ),
				[],
				'3.3.2',
				true
			);

			wp_localize_script( 'brag-book-file-sync-admin', 'bragBookSync', [
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'sync_nonce'        => wp_create_nonce( 'brag_book_sync_procedures' ),
				'nonce'             => wp_create_nonce( 'brag_book_gallery_settings_nonce' ), // For stop sync and progress
				'test_auto_nonce'   => wp_create_nonce( 'brag_book_test_automatic_sync' ),
				'messages'          => [
					'sync_starting'     => __( 'Starting sync...', 'brag-book-gallery' ),
					'sync_in_progress'  => __( 'Sync in progress...', 'brag-book-gallery' ),
					'sync_complete'     => __( 'Sync completed successfully!', 'brag-book-gallery' ),
					'sync_error'        => __( 'Sync failed. Please try again.', 'brag-book-gallery' ),
				],
			] );

			// Add inline JavaScript for sync frequency controls
			wp_add_inline_script( 'brag-book-file-sync-admin', "
				document.addEventListener('DOMContentLoaded', function() {
					const frequencyRadios = document.querySelectorAll('.sync-frequency-radio');
					const customSchedule = document.querySelector('.sync-custom-schedule');

					function toggleCustomSchedule() {
						const customSelected = document.querySelector('.sync-frequency-radio[value=\"custom\"]:checked');

						// Show/hide custom schedule
						if (customSchedule) {
							customSchedule.style.display = customSelected ? 'block' : 'none';
						}
					}

					// Initial setup
					toggleCustomSchedule();

					// Add event listeners
					frequencyRadios.forEach(function(radio) {
						radio.addEventListener('change', toggleCustomSchedule);
					});
				});
			" );
		}
	}

	/**
	 * Sanitize settings input
	 *
	 * @since 3.0.0
	 * @param array $input Settings input to sanitize
	 * @return array Sanitized settings
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = [];

		if ( isset( $input['auto_sync_enabled'] ) ) {
			$sanitized['auto_sync_enabled'] = (bool) $input['auto_sync_enabled'];
		}

		if ( isset( $input['sync_frequency'] ) ) {
			$allowed_frequencies = [ 'daily', 'weekly', 'monthly', 'custom' ];
			$sanitized['sync_frequency'] = in_array( $input['sync_frequency'], $allowed_frequencies, true )
				? $input['sync_frequency']
				: 'weekly';
		}

		// Custom date/time fields
		if ( isset( $input['sync_custom_date'] ) ) {
			$custom_date = sanitize_text_field( $input['sync_custom_date'] );
			$sanitized['sync_custom_date'] = ( empty( $custom_date ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $custom_date ) ) ? $custom_date : '';
		}

		if ( isset( $input['sync_custom_time'] ) ) {
			$custom_time = sanitize_text_field( $input['sync_custom_time'] );
			$sanitized['sync_custom_time'] = preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $custom_time ) ? $custom_time : '02:00';
		}

		// Preserve existing values
		$existing_settings = $this->get_settings();
		$sanitized['last_sync_time'] = $existing_settings['last_sync_time'] ?? '';
		$sanitized['sync_status'] = $existing_settings['sync_status'] ?? 'never';

		return $sanitized;
	}

	/**
	 * Get current settings
	 *
	 * @since 3.0.0
	 * @return array Current settings
	 */
	protected function get_settings(): array {
		return get_option( $this->page_config['option_name'], $this->get_default_settings() );
	}

	/**
	 * Update settings
	 *
	 * @since 3.0.0
	 * @param array $settings Settings to update
	 * @return bool True if successful
	 */
	protected function update_settings( array $settings ): bool {
		$result = update_option( $this->page_config['option_name'], $settings );

		// Update cron jobs when automatic sync settings change
		if ( $result ) {
			$this->update_automatic_sync_schedule( $settings );
		}

		return $result;
	}

	/**
	 * Update automatic sync schedule based on settings
	 *
	 * @since 3.3.0
	 * @param array $settings Current settings array
	 * @return void
	 */
	private function update_automatic_sync_schedule( array $settings ): void {
		$hook_name = 'brag_book_gallery_automatic_sync';

		// Clear any existing scheduled events
		$scheduled_time = wp_next_scheduled( $hook_name );
		if ( $scheduled_time ) {
			wp_unschedule_event( $scheduled_time, $hook_name );
		}

		// If auto sync is enabled, schedule new event
		if ( ! empty( $settings['auto_sync_enabled'] ) ) {
			$frequency = $settings['sync_frequency'] ?? 'weekly';

			// Handle custom date/time scheduling
			if ( $frequency === 'custom' ) {
				$custom_date = $settings['sync_custom_date'] ?? '';
				$custom_time = $settings['sync_custom_time'] ?? '02:00';

				if ( ! empty( $custom_date ) && ! empty( $custom_time ) ) {
					// Create timestamp from custom date and time
					$custom_datetime = $custom_date . ' ' . $custom_time . ':00';
					$timestamp = strtotime( $custom_datetime );

					if ( $timestamp && $timestamp > time() ) {
						// Schedule a one-time event for the custom date/time
						$result = wp_schedule_single_event( $timestamp, $hook_name );

						if ( false === $result ) {
							error_log( 'BRAG Book Gallery: Failed to schedule custom automatic sync' );
						} else {
							error_log( "BRAG Book Gallery: Scheduled automatic sync for custom date/time: {$custom_datetime}" );
						}
					} else {
						error_log( 'BRAG Book Gallery: Invalid custom date/time or date in the past' );
					}
				} else {
					error_log( 'BRAG Book Gallery: Custom frequency selected but date/time not provided' );
				}
			} else {
				// Handle standard frequencies
				$start_time = time();

				// Convert monthly to a WordPress-supported frequency (use weekly for now)
				if ( $frequency === 'monthly' ) {
					$frequency = 'weekly';
				}

				// For daily/weekly/hourly, optionally apply custom time
				$custom_time = $settings['sync_custom_time'] ?? '02:00';
				if ( ! empty( $custom_time ) && in_array( $frequency, [ 'daily', 'weekly' ], true ) ) {
					// Calculate next occurrence at the specified time
					$time_parts = explode( ':', $custom_time );
					$hour = (int) $time_parts[0];
					$minute = (int) ( $time_parts[1] ?? 0 );

					if ( $frequency === 'daily' ) {
						// Next daily occurrence at the specified time
						$next_run = mktime( $hour, $minute, 0 );
						if ( $next_run <= time() ) {
							$next_run = mktime( $hour, $minute, 0 ) + DAY_IN_SECONDS;
						}
						$start_time = $next_run;
					} elseif ( $frequency === 'weekly' ) {
						// Next weekly occurrence at the specified time (same day of week)
						$next_run = mktime( $hour, $minute, 0 );
						if ( $next_run <= time() ) {
							$next_run = mktime( $hour, $minute, 0 ) + WEEK_IN_SECONDS;
						}
						$start_time = $next_run;
					}
				}

				// Schedule the recurring event
				$result = wp_schedule_event( $start_time, $frequency, $hook_name );

				if ( false === $result ) {
					error_log( 'BRAG Book Gallery: Failed to schedule automatic sync cron job' );
				} else {
					$next_time = date( 'Y-m-d H:i:s', $start_time );
					error_log( "BRAG Book Gallery: Scheduled automatic sync with frequency: {$frequency}, next run: {$next_time}" );
				}
			}
		} else {
			error_log( 'BRAG Book Gallery: Automatic sync disabled, cron job cleared' );
		}
	}

	/**
	 * Handle test sync request (for debugging)
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_test_sync(): void {
		// Simple test response
		wp_send_json_success( [
			'message' => 'Test sync endpoint working',
			'created' => 0,
			'updated' => 0,
			'errors'  => [],
		] );
	}

	/**
	 * Handle AJAX request for case progress updates
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_get_case_progress(): void {
		// Verify nonce
		$this->verify_ajax_request();

		// Get case progress from WordPress option
		$progress = get_option( 'brag_book_gallery_case_progress', false );

		if ( $progress ) {
			wp_send_json_success( $progress );
		} else {
			wp_send_json_success( [
				'total' => 0,
				'processed' => 0,
				'percentage' => 0,
				'current_step' => 'No case sync in progress',
				'stage' => 'idle'
			] );
		}
	}

	/**
	 * Handle detailed progress AJAX request
	 *
	 * @since 3.0.0
	 */
	public function handle_get_detailed_progress(): void {
		// Verify nonce
		$this->verify_ajax_request();

		// Get detailed progress from WordPress option
		$progress = get_option( 'brag_book_gallery_detailed_progress', false );

		error_log( 'BRAG book Gallery Sync: AJAX detailed progress request - found data: ' . ( $progress ? 'YES' : 'NO' ) );
		if ( $progress ) {
			error_log( 'BRAG book Gallery Sync: Detailed progress data: ' . wp_json_encode( $progress ) );
		}

		if ( $progress ) {
			wp_send_json_success( $progress );
		} else {
			wp_send_json_success( [
				'stage' => 'idle',
				'overall_percentage' => 0,
				'current_procedure' => '',
				'procedure_progress' => [
					'current' => 0,
					'total' => 0,
					'percentage' => 0,
				],
				'case_progress' => [
					'current' => 0,
					'total' => 0,
					'percentage' => 0,
				],
				'current_step' => 'No sync in progress',
				'recent_cases' => [],
			] );
		}
	}

	/**
	 * Handle stop sync AJAX request
	 *
	 * @since 3.0.0
	 */
	public function handle_stop_sync(): void {
		// Verify nonce
		$this->verify_ajax_request();

		// Set stop flag
		update_option( 'brag_book_gallery_sync_stop_flag', true, false );

		// Clear progress tracking
		delete_option( 'brag_book_gallery_case_progress' );
		delete_option( 'brag_book_gallery_detailed_progress' );

		error_log( 'BRAG book Gallery Sync: Stop flag set by user' );

		wp_send_json_success( [
			'message' => 'Sync stop requested. The process will stop at the next safe checkpoint.',
		] );
	}

	/**
	 * Handle AJAX request to force clear sync state
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_force_clear_sync_state(): void {
		// Verify nonce
		$this->verify_ajax_request();

		try {
			// Clear all sync-related options
			delete_option( 'brag_book_gallery_sync_stop_flag' );
			delete_option( 'brag_book_gallery_case_progress' );
			delete_option( 'brag_book_gallery_detailed_progress' );
			delete_option( 'brag_book_gallery_sync_progress' );

			error_log( 'BRAG book Gallery Sync: Force cleared all sync state options' );

			wp_send_json_success( [
				'message' => 'Sync state forcefully cleared. You can now start a new sync.',
			] );

		} catch ( Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Error force clearing sync state: ' . $e->getMessage() );
			wp_send_json_error( [
				'message' => 'Failed to clear sync state: ' . $e->getMessage(),
			] );
		}
	}

	/**
	 * Handle AJAX request for detailed sync report
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_get_sync_report(): void {
		// Security checks
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_sync_report' ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
		}

		$sync_id = sanitize_text_field( $_POST['sync_id'] ?? '' );
		if ( empty( $sync_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid sync ID.' ] );
		}

		global $wpdb;

		// Get sync log details
		$sync_log = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}brag_book_sync_log WHERE id = %d",
			$sync_id
		) );

		if ( ! $sync_log ) {
			wp_send_json_error( [ 'message' => 'Sync record not found.' ] );
		}

		// Parse sync details
		$details = json_decode( $sync_log->details, true ) ?: [];
		$display_time = $sync_log->sync_time ?? $sync_log->created_at ?? $sync_log->started_at ?? '';
		if ( $display_time ) {
			$sync_date = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $display_time ) );
		} else {
			$sync_date = __( 'Unknown', 'brag-book-gallery' );
		}

		// Get actual case count from database
		$actual_case_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
			'brag_book_case'
		) );

		// Get reported case count from sync details
		$reported_count = $details['total_cases_processed'] ?? 0;
		$discrepancy = $reported_count - $actual_case_count;

		// Get cases that were created during this sync (if we can determine the timeframe)
		$sync_cases = $wpdb->get_results( $wpdb->prepare(
			"SELECT
				p.ID as wordpress_id,
				p.post_title,
				p.post_date,
				m1.meta_value as case_id,
				m2.meta_value as procedure_id
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = 'brag_book_gallery_case_id'
			LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = 'brag_book_gallery_procedure_id'
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND p.post_date >= %s
			ORDER BY p.ID DESC
			LIMIT 50",
			'brag_book_case',
			$sync_log->created_at
		) );

		// Generate HTML report
		ob_start();
		?>
		<div class="brag-book-sync-report">
			<h3><?php printf( esc_html__( 'Sync Report - %s', 'brag-book-gallery' ), esc_html( $sync_date ) ); ?></h3>

			<div class="report-section">
				<h4><?php esc_html_e( 'Sync Summary', 'brag-book-gallery' ); ?></h4>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></th>
						<td><span class="status-<?php echo esc_attr( $sync_log->status ); ?>"><?php echo esc_html( ucfirst( $sync_log->status ) ); ?></span></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Actual Cases in Database', 'brag-book-gallery' ); ?></th>
						<td><strong style="color: #0073aa;"><?php echo esc_html( number_format( $actual_case_count ) ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Reported Cases Processed', 'brag-book-gallery' ); ?></th>
						<td><strong style="color: <?php echo $discrepancy > 0 ? '#d63638' : '#00a32a'; ?>"><?php echo esc_html( number_format( $reported_count ) ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Discrepancy', 'brag-book-gallery' ); ?></th>
						<td>
							<strong style="color: <?php echo $discrepancy != 0 ? '#d63638' : '#00a32a'; ?>">
								<?php echo $discrepancy > 0 ? '+' : ''; ?><?php echo esc_html( number_format( $discrepancy ) ); ?>
							</strong>
							<?php if ( $discrepancy > 0 ) : ?>
								<span style="color: #d63638;"><?php esc_html_e( '(Over-reported)', 'brag-book-gallery' ); ?></span>
							<?php elseif ( $discrepancy < 0 ) : ?>
								<span style="color: #d63638;"><?php esc_html_e( '(Under-reported)', 'brag-book-gallery' ); ?></span>
							<?php else : ?>
								<span style="color: #00a32a;"><?php esc_html_e( '(Accurate)', 'brag-book-gallery' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<?php if ( ! empty( $sync_cases ) ) : ?>
			<div class="report-section">
				<h4><?php printf( esc_html__( 'Cases Created During This Sync (%d)', 'brag-book-gallery' ), count( $sync_cases ) ); ?></h4>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'WP ID', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Case ID', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Procedure ID', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Title', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Created Date', 'brag-book-gallery' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sync_cases as $case ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $case->wordpress_id ); ?></strong></td>
							<td>
								<?php if ( $case->case_id ) : ?>
									<code><?php echo esc_html( $case->case_id ); ?></code>
								<?php else : ?>
									<span style="color: #d63638;">N/A</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $case->procedure_id ) : ?>
									<code><?php echo esc_html( $case->procedure_id ); ?></code>
								<?php else : ?>
									<span style="color: #d63638;">N/A</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $case->post_title ); ?></td>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $case->post_date ) ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $details ) ) : ?>
			<div class="report-section">
				<h4><?php esc_html_e( 'Technical Details', 'brag-book-gallery' ); ?></h4>
				<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px; font-size: 12px; max-height: 300px; overflow-y: auto;"><?php echo esc_html( wp_json_encode( $details, JSON_PRETTY_PRINT ) ); ?></pre>
			</div>
			<?php endif; ?>
		</div>

		<style>
		.brag-book-sync-report .report-section {
			margin-bottom: 20px;
		}
		.brag-book-sync-report h4 {
			margin-bottom: 10px;
			color: #23282d;
		}
		.status-completed { color: #00a32a; font-weight: bold; }
		.status-failed { color: #d63638; font-weight: bold; }
		.status-running { color: #dba617; font-weight: bold; }
		</style>
		<?php

		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => $html ] );
	}
}
