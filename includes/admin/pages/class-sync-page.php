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
use BRAGBookGallery\Includes\Admin\Sync\Sync_Manual_Controls;
use BRAGBookGallery\Includes\Admin\Sync\Sync_Automatic_Settings;
use BRAGBookGallery\Includes\Admin\Sync\Sync_History_Manager;
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
	 * Manual sync controls component
	 *
	 * @since 3.3.0
	 * @var Sync_Manual_Controls
	 */
	private Sync_Manual_Controls $manual_controls;

	/**
	 * Automatic sync settings component
	 *
	 * @since 3.3.0
	 * @var Sync_Automatic_Settings
	 */
	private Sync_Automatic_Settings $automatic_settings;

	/**
	 * Sync history manager component
	 *
	 * @since 3.3.0
	 * @var Sync_History_Manager
	 */
	private Sync_History_Manager $history_manager;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		parent::__construct();

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
		add_action( 'wp_ajax_brag_book_view_sync_log', [ $this, 'handle_view_sync_log' ] );
		add_action( 'wp_ajax_brag_book_delete_sync_records', [ $this, 'handle_delete_sync_records' ] );
		add_action( 'wp_ajax_brag_book_clear_sync_history', [ $this, 'handle_clear_sync_history' ] );
		add_action( 'wp_ajax_brag_book_validate_procedure_assignments', [ $this, 'handle_validate_procedure_assignments' ] );
		add_action( 'wp_ajax_brag_book_get_sync_report', [ $this, 'handle_get_sync_report' ] );
		add_action( 'wp_ajax_brag_book_gallery_test_cron', [ $this, 'handle_test_cron' ] );

		// Register automatic sync cron hook
		add_action( 'brag_book_gallery_automatic_sync', [ $this, 'handle_automatic_sync_cron' ] );

		// Register REST API endpoint for remote sync triggering
		add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );

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

		// Initialize component classes
		$this->manual_controls = new Sync_Manual_Controls();
		$this->automatic_settings = new Sync_Automatic_Settings( $this->page_config['option_name'] );
		$this->history_manager = new Sync_History_Manager();

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

				<?php $this->manual_controls->render(); ?>
			</div>

			<!-- Auto Sync Settings Section -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Automatic Sync Settings', 'brag-book-gallery' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Configure automatic synchronization of procedures from the BRAG book API.', 'brag-book-gallery' ); ?>
				</p>

				<!-- Server Time Display -->
				<div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin: 15px 0; border-left: 4px solid #2271b1;">
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
						<div>
							<strong style="display: block; margin-bottom: 5px; color: #1d2327;">
								<?php esc_html_e( 'Server Time:', 'brag-book-gallery' ); ?>
							</strong>
							<span id="server-time" style="font-size: 16px; font-family: monospace; color: #2271b1;">
								<?php echo esc_html( wp_date( 'Y-m-d H:i:s' ) ); ?>
							</span>
						</div>
						<div>
							<strong style="display: block; margin-bottom: 5px; color: #1d2327;">
								<?php esc_html_e( 'Server Timezone:', 'brag-book-gallery' ); ?>
							</strong>
							<span style="font-size: 14px; color: #50575e;">
								<?php echo esc_html( wp_timezone_string() ); ?>
							</span>
						</div>
						<div>
							<strong style="display: block; margin-bottom: 5px; color: #1d2327;">
								<?php esc_html_e( 'Current Browser Time:', 'brag-book-gallery' ); ?>
							</strong>
							<span id="browser-time" style="font-size: 16px; font-family: monospace; color: #2271b1;"></span>
						</div>
					</div>
				</div>

				<script type="text/javascript">
				(function() {
					function updateTimes() {
						// Update server time
						var serverTimeEl = document.getElementById('server-time');
						if (serverTimeEl) {
							// Get initial server time from PHP
							var initialServerTime = new Date('<?php echo esc_js( wp_date( 'c' ) ); ?>');
							var now = new Date();
							var elapsed = Math.floor((now - pageLoadTime) / 1000);
							var currentServerTime = new Date(initialServerTime.getTime() + (elapsed * 1000));

							var year = currentServerTime.getFullYear();
							var month = String(currentServerTime.getMonth() + 1).padStart(2, '0');
							var day = String(currentServerTime.getDate()).padStart(2, '0');
							var hours = String(currentServerTime.getHours()).padStart(2, '0');
							var minutes = String(currentServerTime.getMinutes()).padStart(2, '0');
							var seconds = String(currentServerTime.getSeconds()).padStart(2, '0');

							serverTimeEl.textContent = year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
						}

						// Update browser time
						var browserTimeEl = document.getElementById('browser-time');
						if (browserTimeEl) {
							var now = new Date();
							var year = now.getFullYear();
							var month = String(now.getMonth() + 1).padStart(2, '0');
							var day = String(now.getDate()).padStart(2, '0');
							var hours = String(now.getHours()).padStart(2, '0');
							var minutes = String(now.getMinutes()).padStart(2, '0');
							var seconds = String(now.getSeconds()).padStart(2, '0');

							browserTimeEl.textContent = year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
						}
					}

					// Store page load time for server time calculation
					var pageLoadTime = new Date();

					// Update immediately
					updateTimes();

					// Update every second
					setInterval(updateTimes, 1000);
				})();
				</script>

				<table class="form-table brag-book-gallery-form-table">
					<tr>
						<th scope="row">
							<label for="auto_sync_enabled"><?php esc_html_e( 'Enable Automatic Sync', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<?php $this->automatic_settings->render_auto_sync_field(); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="sync_day"><?php esc_html_e( 'Sync Schedule', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<?php $this->automatic_settings->render_sync_frequency_field(); ?>
						</td>
					</tr>
				</table>

				<!-- Test Cron Button -->
				<div style="margin-top: 20px;">
					<button type="button" class="button" id="test-cron-sync" style="margin-right: 10px;">
						<?php esc_html_e( 'Test Cron Now', 'brag-book-gallery' ); ?>
					</button>
					<span class="description">
						<?php esc_html_e( 'Manually trigger the automatic sync cron job for testing', 'brag-book-gallery' ); ?>
					</span>
					<div id="test-cron-result" style="margin-top: 10px;"></div>
				</div>

				<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Helper function to auto-dismiss notices after 5 seconds
					function autoDismissNotice($notice) {
						setTimeout(function() {
							$notice.fadeOut(300, function() {
								$(this).remove();
							});
						}, 5000);
					}

					$('#test-cron-sync').on('click', function() {
						var $button = $(this);
						var $result = $('#test-cron-result');

						$button.prop('disabled', true).text('<?php esc_html_e( 'Running...', 'brag-book-gallery' ); ?>');
						$result.html('<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Triggering cron job...', 'brag-book-gallery' ); ?></p></div>');
						autoDismissNotice($result.find('.notice'));

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'brag_book_gallery_test_cron',
								nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_gallery_sync' ) ); ?>'
							},
							success: function(response) {
								$button.prop('disabled', false).text('<?php esc_html_e( 'Test Cron Now', 'brag-book-gallery' ); ?>');
								if (response.success) {
									$result.html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
								} else {
									$result.html('<div class="notice notice-error is-dismissible"><p>' + (response.data || '<?php esc_html_e( 'Test failed', 'brag-book-gallery' ); ?>') + '</p></div>');
								}
								autoDismissNotice($result.find('.notice'));
							},
							error: function() {
								$button.prop('disabled', false).text('<?php esc_html_e( 'Test Cron Now', 'brag-book-gallery' ); ?>');
								$result.html('<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'AJAX request failed', 'brag-book-gallery' ); ?></p></div>');
								autoDismissNotice($result.find('.notice'));
							}
						});
					});
				});
				</script>
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

		// Update sync mode
		if ( isset( $_POST['sync_mode'] ) ) {
			$allowed_modes = [ 'standard', 'wp_engine' ];
			$mode = sanitize_text_field( $_POST['sync_mode'] );
			if ( in_array( $mode, $allowed_modes, true ) ) {
				$new_settings['sync_mode'] = $mode;
			}
		}

		// Update auto sync setting
		$new_settings['auto_sync_enabled'] = isset( $_POST[ $this->page_config['option_name'] ]['auto_sync_enabled'] );

		// Update sync day (day of week: 0-6, where 0 is Sunday)
		if ( isset( $_POST[ $this->page_config['option_name'] ]['sync_day'] ) ) {
			$sync_day = sanitize_text_field( $_POST[ $this->page_config['option_name'] ]['sync_day'] );
			// Validate day is 0-6
			if ( in_array( $sync_day, [ '0', '1', '2', '3', '4', '5', '6' ], true ) ) {
				$new_settings['sync_day'] = $sync_day;
			}
		}

		// Update sync time
		if ( isset( $_POST[ $this->page_config['option_name'] ]['sync_time'] ) ) {
			$sync_time = sanitize_text_field( $_POST[ $this->page_config['option_name'] ]['sync_time'] );
			// Validate time format (HH:MM)
			if ( preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $sync_time ) ) {
				$new_settings['sync_time'] = $sync_time;
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
			'sync_day'             => '0',      // Sunday
			'sync_time'            => '02:00',  // 2:00 AM
			'last_sync_time'       => '',
			'sync_status'          => 'never',
			'sync_mode'            => 'wp_engine', // Default to WP Engine optimized
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

							<!-- Stage-Based Sync Controls -->
							<style>
							@keyframes progress-bar-stripes {
								from {
									background-position: 20px 0;
								}
								to {
									background-position: 0 0;
								}
							}

							#stage-progress {
								transition: opacity 0.3s ease;
							}

							#stage-progress.visible {
								display: block !important;
								opacity: 1;
							}

							#stage-progress.hidden {
								opacity: 0;
							}

							.stage-progress-bar {
								position: relative;
								margin-bottom: 5px;
							}
							</style>
							<div class="stage-sync-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
								<h4 style="margin: 0 0 15px 0;"><?php esc_html_e( 'Stage-Based Sync', 'brag-book-gallery' ); ?></h4>

								<!-- File Status Indicators -->
								<div class="stage-file-status" style="margin-bottom: 15px; font-size: 13px;">
									<div style="display: flex; gap: 20px; margin-bottom: 10px; align-items: center;">
										<div style="display: flex; align-items: center; gap: 5px;">
											<span id="sync-data-status" class="file-status" style="display: inline-flex; align-items: center; gap: 5px;">
												<span class="status-icon" style="display: inline-flex; width: 20px; height: 20px;">
													<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ccc"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
												</span>
												<?php esc_html_e( 'Sync Data', 'brag-book-gallery' ); ?>
											</span>
											<span id="sync-data-date" style="color: #666; font-size: 11px;"></span>
											<a id="sync-data-link" href="#" target="_blank" style="display: none; color: #2271b1; text-decoration: none; font-size: 11px;" title="<?php esc_attr_e( 'View JSON file', 'brag-book-gallery' ); ?>">
												<?php esc_html_e( '[View]', 'brag-book-gallery' ); ?>
											</a>
										</div>
										<div style="display: flex; align-items: center; gap: 5px;">
											<span id="manifest-status" class="file-status" style="display: inline-flex; align-items: center; gap: 5px;">
												<span class="status-icon" style="display: inline-flex; width: 20px; height: 20px;">
													<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ccc"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
												</span>
												<?php esc_html_e( 'Manifest', 'brag-book-gallery' ); ?>
											</span>
											<span id="manifest-date" style="color: #666; font-size: 11px;"></span>
											<a id="manifest-link" href="#" target="_blank" style="display: none; color: #2271b1; text-decoration: none; font-size: 11px;" title="<?php esc_attr_e( 'View JSON file', 'brag-book-gallery' ); ?>">
												<?php esc_html_e( '[View]', 'brag-book-gallery' ); ?>
											</a>
										</div>
									</div>
								</div>

								<!-- Stage Buttons -->
								<div class="stage-sync-buttons" style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
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

								<!-- Stage Progress -->
								<div id="stage-progress" style="display: none; margin-top: 15px;">
									<div class="stage-progress-bar" style="height: 24px; background: #f0f0f0; border-radius: 4px; overflow: hidden; position: relative; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
										<div id="stage-progress-fill" style="height: 100%; background: linear-gradient(90deg, #2196f3, #42a5f5); width: 0%; transition: width 0.5s ease; position: relative;">
											<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent); background-size: 20px 20px; animation: progress-bar-stripes 1s linear infinite;"></div>
										</div>
									</div>
									<div id="stage-progress-text" style="margin-top: 8px; font-size: 13px; color: #555; font-weight: 500;"></div>
								</div>

								<!-- Stage 1 Status -->
								<div id="stage1-status" style="display: none; margin-top: 15px; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 3px;">
									<h5 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Stage 1 Status', 'brag-book-gallery' ); ?></h5>
									<div id="stage1-status-content" style="font-size: 12px;"></div>
									<button type="button" id="delete-sync-data-btn" class="button button-link-delete" style="margin-top: 10px; font-size: 12px;" title="<?php esc_attr_e( 'Delete procedures.json file', 'brag-book-gallery' ); ?>">
										<span class="dashicons dashicons-trash" style="font-size: 14px; line-height: 20px; margin-right: 3px;"></span>
										<?php esc_html_e( 'Delete Sync Data', 'brag-book-gallery' ); ?>
									</button>
								</div>

								<!-- Manifest Preview -->
								<div id="manifest-preview" style="display: none; margin-top: 15px; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 3px;">
									<h5 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Manifest Preview', 'brag-book-gallery' ); ?></h5>
									<div id="manifest-preview-content" style="font-size: 12px; font-family: monospace;"></div>
									<button type="button" id="delete-manifest-btn" class="button button-link-delete" style="margin-top: 10px; font-size: 12px;" title="<?php esc_attr_e( 'Delete manifest.json file', 'brag-book-gallery' ); ?>">
										<span class="dashicons dashicons-trash" style="font-size: 14px; line-height: 20px; margin-right: 3px;"></span>
										<?php esc_html_e( 'Delete Manifest', 'brag-book-gallery' ); ?>
									</button>
								</div>

								<!-- Stage 3 Status -->
								<div id="stage3-status" style="display: none; margin-top: 15px; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 3px;">
									<h5 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Stage 3 Status', 'brag-book-gallery' ); ?></h5>
									<div id="stage3-status-content" style="font-size: 12px;"></div>
								</div>

								<!-- Full Sync Controls -->
								<div class="full-sync-controls" style="display: flex; gap: 10px; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
									<button type="button" id="full-sync-btn" class="button button-hero" style="background: #0f172a; color: white; border-color: #0f172a;" title="<?php esc_attr_e( 'Run all three stages sequentially', 'brag-book-gallery' ); ?>">
										<?php esc_html_e( 'Full Sync', 'brag-book-gallery' ); ?>
									</button>
									<button type="button" id="stop-sync-btn" class="button button-link-delete" style="display: none;" title="<?php esc_attr_e( 'Stop the running sync process', 'brag-book-gallery' ); ?>">
										<span class="dashicons dashicons-no" style="font-size: 16px; line-height: 28px; margin-right: 5px;"></span>
										<?php esc_html_e( 'Stop', 'brag-book-gallery' ); ?>
									</button>
								</div>
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
		<?php $this->render_cron_status(); ?>
		<?php
	}

	/**
	 * Render cron status display
	 *
	 * Shows the current cron schedule status for debugging
	 * and confirmation that the cron is properly scheduled.
	 *
	 * @since 3.3.0
	 * @return void
	 */
	public function render_cron_status(): void {
		$next_run = wp_next_scheduled( 'brag_book_gallery_automatic_sync' );

		if ( $next_run ) {
			$human_time = human_time_diff( time(), $next_run );
			$exact_time = wp_date( 'Y-m-d H:i:s', $next_run );
			?>
			<div class="notice notice-info inline" style="margin-top: 10px;">
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
	 * @since 3.0.0
	 * @return void
	 */
	public function render_sync_frequency_field(): void {
		$settings = $this->get_settings();
		$value = $settings['sync_frequency'] ?? 'weekly';
		$custom_date = $settings['sync_custom_date'] ?? '';
		$custom_time = $settings['sync_custom_time'] ?? '12:00';

		$frequencies = [
			'weekly'  => [
				'label' => __( 'Weekly', 'brag-book-gallery' ),
				'description' => __( 'Sync once per week at a specified time', 'brag-book-gallery' )
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

		// Increase error reporting for debugging
		error_reporting(E_ALL);
		ini_set('display_errors', 0); // Don't display, just log

		// Clear any output that might interfere
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Set proper headers
		header( 'Content-Type: application/json' );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_sync' ) ) {
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

			// Initialize sync manager (with built-in file-based logging)
			$sync_manager = new Data_Sync();
			error_log( 'BRAG book Gallery Sync: Data_Sync constructor completed successfully!' );
			error_log( 'BRAG book Gallery Sync: Starting full synchronization with file logging' );

			// Now run the actual sync
			error_log( 'BRAG book Gallery Sync: Running actual two-stage sync...' );
			$result = $sync_manager->run_two_stage_sync();
			error_log( 'BRAG book Gallery Sync: Sync completed with result: ' . wp_json_encode( $result ) );

			// Check if sync needs to resume (paused due to time/memory limits)
			if ( isset( $result['needs_resume'] ) && $result['needs_resume'] ) {
				error_log( 'BRAG book Gallery Sync: Sync paused for resource limits - will resume on next call' );

				// Return special response indicating sync should continue
				wp_send_json_success( [
					'needs_resume' => true,
					'message' => $result['message'] ?? 'Sync paused - will resume automatically',
					'created' => $result['created'] ?? 0,
					'updated' => $result['updated'] ?? 0,
					'cases_created' => $result['cases_created'] ?? 0,
					'cases_updated' => $result['cases_updated'] ?? 0,
					'total_processed' => $result['total_processed'] ?? 0,
					'progress' => isset($result['total_processed']) && isset($result['total_expected'])
						? round(($result['total_processed'] / max($result['total_expected'], 1)) * 100, 1)
						: 0
				] );
				return;
			}

			// Debug: Check if total_api_cases is in the result
			if (isset($result['total_api_cases'])) {
				error_log( 'BRAG book Gallery Sync: NEW FEATURE WORKING - total_api_cases: ' . $result['total_api_cases'] );
			} else {
				error_log( 'BRAG book Gallery Sync: NEW FEATURE MISSING - total_api_cases not found in result' );
			}

			// Log completion
			if ( $result['success'] ) {
				error_log( 'BRAG book Gallery Sync: 🎉 Synchronization completed successfully!' );
			} else {
				error_log( 'BRAG book Gallery Sync: Sync completed with errors: ' . implode( ', ', $result['errors'] ?? [] ) );
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
					'session_id' => uniqid( 'sync_', true ), // Generate session ID since we removed the logger
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
					error_log( 'BRAG book Gallery Sync: Stored sync results in database with ID: ' . $log_id );
				} else {
					error_log( 'BRAG book Gallery Sync: Failed to store sync results in database' );
				}
			} catch (Exception $e) {
				error_log( 'BRAG book Gallery Sync: Database storage error: ' . $e->getMessage() );
			}
			// Sync completion is now logged in Data_Sync class itself

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
				'session_id' => uniqid( 'sync_', true ), // Include session ID for monitoring
			];

		} catch ( \Throwable $e ) {
			// Check if this is a memory error
			$is_memory_error = (
				stripos( $e->getMessage(), 'memory' ) !== false ||
				stripos( $e->getMessage(), 'bytes exhausted' ) !== false ||
				stripos( $e->getMessage(), 'allowed memory size' ) !== false
			);

			if ( $is_memory_error ) {
				// Log detailed memory information
				$current_memory = memory_get_usage( true );
				$peak_memory = memory_get_peak_usage( true );
				$memory_limit = ini_get( 'memory_limit' );

				error_log( 'BRAG book Gallery Sync: MEMORY EXHAUSTED ERROR' );
				error_log( sprintf( 'BRAG book Gallery Sync: Current memory: %s', wp_convert_bytes_to_hr( $current_memory ) ) );
				error_log( sprintf( 'BRAG book Gallery Sync: Peak memory: %s', wp_convert_bytes_to_hr( $peak_memory ) ) );
				error_log( sprintf( 'BRAG book Gallery Sync: Memory limit: %s', $memory_limit ) );

				// Try to get progress information
				$progress = get_transient( 'brag_book_gallery_sync_progress' );
				if ( $progress ) {
					error_log( sprintf( 'BRAG book Gallery Sync: Failed at: %s', $progress['message'] ?? 'Unknown step' ) );
					error_log( sprintf( 'BRAG book Gallery Sync: Progress: %d%%', $progress['progress'] ?? 0 ) );
					error_log( sprintf( 'BRAG book Gallery Sync: Current procedure: %s', $progress['current_procedure'] ?? 'Unknown' ) );
				}

				$error_message = sprintf(
					__( 'Memory limit exceeded at %s%% completion. Current memory: %s, Peak: %s, Limit: %s. Last step: %s', 'brag-book-gallery' ),
					$progress['progress'] ?? 0,
					wp_convert_bytes_to_hr( $current_memory ),
					wp_convert_bytes_to_hr( $peak_memory ),
					$memory_limit,
					$progress['message'] ?? 'Unknown'
				);
			} else {
				error_log( 'BRAG book Gallery Sync: Fatal error during sync: ' . $e->getMessage() );
				error_log( 'BRAG book Gallery Sync: Error file: ' . $e->getFile() . ' on line ' . $e->getLine() );
				error_log( 'BRAG book Gallery Sync: Error trace: ' . $e->getTraceAsString() );

				$error_message = sprintf(
					__( 'Full sync failed: %s (File: %s, Line: %d)', 'brag-book-gallery' ),
					$e->getMessage(),
					basename( $e->getFile() ),
					$e->getLine()
				);
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
					'message' => $error_message,
					'created' => 0,
					'updated' => 0,
					'cases_created' => 0,
					'cases_updated' => 0,
					'errors'  => [
						$error_message,
						$is_memory_error ? sprintf( 'Memory Usage: Current %s, Peak %s, Limit %s',
							wp_convert_bytes_to_hr( memory_get_usage( true ) ),
							wp_convert_bytes_to_hr( memory_get_peak_usage( true ) ),
							ini_get( 'memory_limit' )
						) : 'File: ' . $e->getFile() . ', Line: ' . $e->getLine()
					],
					'debug_info' => [
						'error_type' => get_class( $e ),
						'error_file' => $e->getFile(),
						'error_line' => $e->getLine(),
						'is_memory_error' => $is_memory_error,
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

		// Send proper JSON response
		if ( $response['success'] ) {
			wp_send_json_success( $response['data'] );
		} else {
			wp_send_json_error( $response['data'] );
		}
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
	 * Handle test cron AJAX request
	 *
	 * Manually triggers the automatic sync cron job for testing purposes.
	 *
	 * @since 3.3.0
	 * @return void
	 */
	public function handle_test_cron(): void {
		// Check nonce and permissions
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		// Log the test
		error_log( 'BRAG Book Gallery: Manual cron test triggered via admin interface' );

		// Run the cron handler directly
		ob_start();
		$this->handle_automatic_sync_cron();
		$output = ob_get_clean();

		// Check if sync completed
		$settings = $this->get_settings();
		$message = sprintf(
			__( 'Cron job test completed. Last sync: %s', 'brag-book-gallery' ),
			$settings['last_sync_time'] ?? __( 'Never', 'brag-book-gallery' )
		);

		wp_send_json_success( [
			'message' => $message,
			'output' => $output,
		] );
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

		$sync_success = false;
		$sync_source = 'cron'; // Track if triggered by REST API or cron

		// Check if this was triggered via REST API
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$sync_source = 'rest_api';
			error_log( 'BRAG Book Gallery: Sync triggered via REST API' );
		}

		// Get database instance for logging
		$setup = \BRAGBookGallery\Includes\Core\Setup::get_instance();
		$database = $setup->get_service( 'database' );
		$log_id = null;

		// Create initial log entry
		if ( $database ) {
			$log_id = $database->log_sync_operation( 'full', 'started', 0, 0, '', $sync_source );
			error_log( 'BRAG Book Gallery: Sync log created with ID: ' . $log_id );
		}

		try {
			error_log( 'BRAG Book Gallery: Starting automatic sync via cron using Stage-Based Sync' );
			error_log( 'BRAG Book Gallery: 🕒 Automatic sync started via ' . $sync_source );

			// Use the new stage-based sync
			$sync = new \BRAGBookGallery\Includes\Sync\Chunked_Data_Sync();

			// Run Stage 1: Fetch procedures
			error_log( 'BRAG Book Gallery: Running Stage 1 - Fetching procedures' );
			$stage1_result = $sync->execute_stage_1();

			if ( ! $stage1_result['success'] ) {
				error_log( 'BRAG Book Gallery: Stage 1 failed: ' . ($stage1_result['message'] ?? 'Unknown error') );

				// Update sync log with failure
				if ( $database && $log_id ) {
					$database->update_sync_log( $log_id, 'failed', 0, 0, $stage1_result['message'] ?? 'Stage 1 failed' );
				}

				// Update sync settings with error status
				$settings['last_sync_time'] = current_time( 'mysql' );
				$settings['sync_status']    = 'error';
				$this->update_settings( $settings );

				// Also update separate options for REST API access
				update_option( 'brag_book_gallery_last_sync_time', $settings['last_sync_time'] );
				update_option( 'brag_book_gallery_last_sync_status', 'error' );

				return;
			}

			error_log( 'BRAG Book Gallery: Stage 1 completed - ' .
				($stage1_result['procedures_created'] ?? 0) . ' created, ' .
				($stage1_result['procedures_updated'] ?? 0) . ' updated' );

			// Run Stage 2: Build manifest
			error_log( 'BRAG Book Gallery: Running Stage 2 - Building manifest' );
			$stage2_result = $sync->execute_stage_2();

			if ( ! $stage2_result['success'] ) {
				error_log( 'BRAG Book Gallery: Stage 2 failed: ' . ($stage2_result['message'] ?? 'Unknown error') );

				// Update sync log with failure
				if ( $database && $log_id ) {
					$database->update_sync_log( $log_id, 'failed', 0, 0, $stage2_result['message'] ?? 'Stage 2 failed' );
				}

				// Update sync settings with error status
				$settings['last_sync_time'] = current_time( 'mysql' );
				$settings['sync_status']    = 'error';
				$this->update_settings( $settings );

				// Also update separate options for REST API access
				update_option( 'brag_book_gallery_last_sync_time', $settings['last_sync_time'] );
				update_option( 'brag_book_gallery_last_sync_status', 'error' );

				return;
			}

			error_log( 'BRAG Book Gallery: Stage 2 completed - ' .
				($stage2_result['procedure_count'] ?? 0) . ' procedures, ' .
				($stage2_result['case_count'] ?? 0) . ' cases in manifest' );

			// Run Stage 3: Process cases in batches
			error_log( 'BRAG Book Gallery: Running Stage 3 - Processing cases in batches' );

			$total_created = 0;
			$total_updated = 0;
			$total_failed  = 0;
			$total_processed = 0;
			$total_cases = 0;
			$needs_continue = true;
			$batch_count = 0;
			$max_batches = 1000; // Safety limit to prevent infinite loops

			while ( $needs_continue && $batch_count < $max_batches ) {
				$batch_count++;
				error_log( "BRAG Book Gallery: Stage 3 - Processing batch {$batch_count}" );

				$stage3_result = $sync->execute_stage_3();

				if ( ! $stage3_result['success'] ) {
					error_log( 'BRAG Book Gallery: Stage 3 failed: ' . ($stage3_result['message'] ?? 'Unknown error') );

					// Update sync log with failure
					if ( $database && $log_id ) {
						$database->update_sync_log( $log_id, 'failed', $total_processed, $total_failed, $stage3_result['message'] ?? 'Stage 3 failed' );
					}

					// Update sync settings with error status
					$settings['last_sync_time'] = current_time( 'mysql' );
					$settings['sync_status']    = 'error';
					$this->update_settings( $settings );

					// Also update separate options for REST API access
					update_option( 'brag_book_gallery_last_sync_time', $settings['last_sync_time'] );
					update_option( 'brag_book_gallery_last_sync_status', 'error' );

					break; // Exit the loop on error
				}

				// Update totals
				$total_created   = $stage3_result['created_posts'] ?? 0;
				$total_updated   = $stage3_result['updated_posts'] ?? 0;
				$total_failed    = $stage3_result['failed_cases'] ?? 0;
				$total_processed = $stage3_result['processed_cases'] ?? 0;
				$total_cases     = $stage3_result['total_cases'] ?? 0;
				$needs_continue  = $stage3_result['needs_continue'] ?? false;

				error_log( "BRAG Book Gallery: Stage 3 batch {$batch_count} - Progress: {$total_processed}/{$total_cases} cases ({$total_created} created, {$total_updated} updated, {$total_failed} failed)" );

				// Check if we need to continue
				if ( ! $needs_continue ) {
					error_log( 'BRAG Book Gallery: Stage 3 completed - All cases processed' );
					$sync_success = true;
					break;
				}

				// Brief pause between batches to avoid overwhelming the server
				usleep( 500000 ); // 0.5 second pause
			}

			if ( $batch_count >= $max_batches ) {
				error_log( "BRAG Book Gallery: Stage 3 stopped - Reached maximum batch limit ({$max_batches})" );
			}

			error_log( 'BRAG Book Gallery: Stage 3 final totals - ' .
				"{$total_created} created, {$total_updated} updated, {$total_failed} failed ({$total_processed}/{$total_cases} cases)" );

			error_log( 'BRAG Book Gallery: 🎉 Automatic sync completed successfully' );

			// Update sync log with completion
			if ( $database && $log_id ) {
				$status = $sync_success ? 'completed' : 'partial';
				$database->update_sync_log( $log_id, $status, $total_processed, $total_failed );
				error_log( 'BRAG Book Gallery: Sync log updated - Status: ' . $status . ', Processed: ' . $total_processed . ', Failed: ' . $total_failed );
			}

			// Update sync settings with success status
			$settings['last_sync_time'] = current_time( 'mysql' );
			$settings['sync_status']    = $sync_success ? 'success' : 'partial';
			$update_result              = $this->update_settings( $settings );

			// Also update separate options for REST API access
			update_option( 'brag_book_gallery_last_sync_time', $settings['last_sync_time'] );
			update_option( 'brag_book_gallery_last_sync_status', $settings['sync_status'] );

			if ( ! $update_result ) {
				error_log( 'BRAG Book Gallery: Failed to update sync settings after cron sync' );
			} else {
				error_log( 'BRAG Book Gallery: Successfully updated sync settings - Status: ' . $settings['sync_status'] . ', Time: ' . $settings['last_sync_time'] );
			}

		} catch ( \Exception $e ) {
			$error_message = 'Automatic sync failed: ' . $e->getMessage();
			error_log( 'BRAG Book Gallery: ❌ ' . $error_message );

			// Update sync log with error
			if ( $database && $log_id ) {
				$database->update_sync_log( $log_id, 'failed', 0, 0, $error_message );
			}

			// Update sync settings with error status
			$settings['last_sync_time'] = current_time( 'mysql' );
			$settings['sync_status']    = 'error';
			$this->update_settings( $settings );

			// Also update separate options for REST API access
			update_option( 'brag_book_gallery_last_sync_time', $settings['last_sync_time'] );
			update_option( 'brag_book_gallery_last_sync_status', 'error' );
		}
	}

	/**
	 * Register REST API endpoints for remote sync triggering
	 *
	 * @since 3.3.0
	 * @return void
	 */
	public function register_rest_endpoints(): void {
		register_rest_route(
			'brag-book-gallery/v1',
			'/sync/trigger',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_rest_trigger_sync' ],
				'permission_callback' => [ $this, 'validate_sync_token' ],
				'args'                => [
					'token' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					],
				],
			]
		);

		register_rest_route(
			'brag-book-gallery/v1',
			'/sync/update',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_rest_get_update' ],
				'permission_callback' => [ $this, 'validate_sync_token' ],
				'args'                => [
					'token' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					],
				],
			]
		);
	}

	/**
	 * Validate sync token for REST API authentication
	 *
	 * Uses the BRAGbook API token for authentication - same token used for API calls.
	 *
	 * @since 3.3.0
	 * @param \WP_REST_Request $request The REST request object
	 * @return bool|WP_Error True if valid, WP_Error otherwise
	 */
	public function validate_sync_token( \WP_REST_Request $request ) {
		$provided_token = $request->get_param( 'token' );

		if ( empty( $provided_token ) ) {
			return new \WP_Error(
				'missing_token',
				__( 'Authentication token is required.', 'brag-book-gallery' ),
				[ 'status' => 401 ]
			);
		}

		// Get the BRAGbook API tokens from settings (stored as array)
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );

		// Ensure it's an array
		if ( ! is_array( $api_tokens ) ) {
			$api_tokens = array( $api_tokens );
		}

		// Remove empty tokens
		$api_tokens = array_filter( $api_tokens );

		if ( empty( $api_tokens ) ) {
			error_log( 'BRAG Book Gallery: BRAGbook API token not configured' );
			return new \WP_Error(
				'token_not_configured',
				__( 'BRAGbook API token is not configured. Please configure it in the API Settings page.', 'brag-book-gallery' ),
				[ 'status' => 500 ]
			);
		}

		// Check if provided token matches any of the configured API tokens
		$token_valid = false;
		foreach ( $api_tokens as $stored_token ) {
			if ( hash_equals( $stored_token, $provided_token ) ) {
				$token_valid = true;
				break;
			}
		}

		if ( ! $token_valid ) {
			error_log( 'BRAG Book Gallery: Invalid BRAGbook API token provided to REST API' );
			return new \WP_Error(
				'invalid_token',
				__( 'Invalid authentication token.', 'brag-book-gallery' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Handle REST API sync trigger request
	 *
	 * @since 3.3.0
	 * @param \WP_REST_Request $request The REST request object
	 * @return \WP_REST_Response|\WP_Error Response object or error
	 */
	public function handle_rest_trigger_sync( \WP_REST_Request $request ) {
		error_log( 'BRAG Book Gallery: Sync triggered via REST API' );

		// Schedule the sync to run asynchronously in the background
		// This ensures the REST API response returns immediately
		wp_schedule_single_event( time(), 'brag_book_gallery_automatic_sync' );

		return rest_ensure_response( [
			'success' => true,
			'message' => __( 'Sync has been triggered and will run in the background', 'brag-book-gallery' ),
		] );
	}

	/**
	 * Handle REST API get update request
	 *
	 * Returns website URL, plugin details, and last sync information.
	 *
	 * @since 3.3.0
	 * @param \WP_REST_Request $request The REST request object
	 * @return \WP_REST_Response|\WP_Error Response object or error
	 */
	public function handle_rest_get_update( \WP_REST_Request $request ) {
		// Get plugin data
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = dirname( dirname( dirname( __DIR__ ) ) ) . '/brag-book-gallery.php';
		$plugin_data = get_plugin_data( $plugin_file );

		// Get last sync time and status from options
		$last_sync_time = get_option( 'brag_book_gallery_last_sync_time', null );
		$last_sync_status = get_option( 'brag_book_gallery_last_sync_status', 'unknown' );

		// Build response
		$response = [
			'success'     => true,
			'website_url' => home_url(),
			'plugin'      => [
				'version'      => $plugin_data['Version'] ?? null,
				'name'         => $plugin_data['Name'] ?? 'BRAGBook Gallery',
				'last_updated' => $plugin_data['Version'] ? filemtime( $plugin_file ) : null,
			],
			'last_sync'   => [
				'time'   => $last_sync_time,
				'status' => $last_sync_status,
			],
		];

		return rest_ensure_response( $response );
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

		// Check which column names are in use for cleanup
		$columns = $wpdb->get_col( "DESCRIBE {$table_name}" );
		$has_items_processed = in_array( 'items_processed', $columns, true );
		$has_processed = in_array( 'processed', $columns, true );
		$has_items_failed = in_array( 'items_failed', $columns, true );
		$has_failed = in_array( 'failed', $columns, true );
		$has_error_messages = in_array( 'error_messages', $columns, true );
		$has_details = in_array( 'details', $columns, true );

		// Use appropriate column names for cleanup
		$processed_col = $has_items_processed ? 'items_processed' : ($has_processed ? 'processed' : null);
		$failed_col = $has_items_failed ? 'items_failed' : ($has_failed ? 'failed' : null);
		$details_col = $has_error_messages ? 'error_messages' : ($has_details ? 'details' : null);

		// Delete records that have no meaningful data
		if ( $processed_col && $failed_col && $details_col ) {
			$deleted = $wpdb->query( "
				DELETE FROM {$table_name}
				WHERE ({$processed_col} = 0 OR {$processed_col} IS NULL)
				AND ({$failed_col} = 0 OR {$failed_col} IS NULL)
				AND ({$details_col} = '' OR {$details_col} IS NULL OR {$details_col} = '[]' OR {$details_col} = '{}')
			" );
		} else {
			$deleted = 0;
			error_log( 'BRAG Book Gallery: Could not clean up log records - column names not found' );
		}

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

		// Verify nonce - check the correct nonce name
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_sync_delete' ) ) {
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

		// Get record ID - check both possible parameter names for compatibility
		$record_id = sanitize_text_field( $_POST['sync_id'] ?? $_POST['record_id'] ?? '' );
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
			// Enqueue jQuery for WP Engine sync script
			wp_enqueue_script( 'jquery' );

			// Get plugin paths for file versioning
			$plugin_path = \BRAGBookGallery\Includes\Core\Setup::get_plugin_path();
			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

			// Get file modification times for cache busting
			$sync_admin_file = $plugin_path . 'assets/js/brag-book-gallery-sync-admin' . $suffix . '.js';
			$sync_admin_version = file_exists( $sync_admin_file ) ? filemtime( $sync_admin_file ) : '3.3.2';

			$stage_sync_file = $plugin_path . 'assets/js/brag-book-gallery-stage-sync' . $suffix . '.js';
			$stage_sync_version = file_exists( $stage_sync_file ) ? filemtime( $stage_sync_file ) : '3.3.0';

			// Enqueue main sync admin script
			wp_enqueue_script(
				'brag-book-file-sync-admin',
				plugins_url( 'assets/js/brag-book-gallery-sync-admin' . $suffix . '.js', dirname( __DIR__, 2 ) ),
				[],
				$sync_admin_version,
				true
			);

			// Enqueue stage sync script
			wp_enqueue_script(
				'brag-book-stage-sync',
				plugins_url( 'assets/js/brag-book-gallery-stage-sync' . $suffix . '.js', dirname( __DIR__, 2 ) ),
				[ 'jquery' ],
				$stage_sync_version,
				true
			);

			// Add sync status file URL
			$upload_dir = wp_upload_dir();
			$sync_status_url = $upload_dir['baseurl'] . '/brag-book-sync/sync-status.json';

			// Get current settings
			$settings = $this->get_settings();

			// Localize data for both sync scripts
			$localize_data = [
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'sync_status_url'   => $sync_status_url,
				'sync_nonce'        => wp_create_nonce( 'brag_book_gallery_sync' ),
				'nonce'             => wp_create_nonce( 'brag_book_gallery_settings_nonce' ), // For stop sync and progress
				'test_auto_nonce'   => wp_create_nonce( 'brag_book_test_automatic_sync' ),
				'clear_log_nonce'   => wp_create_nonce( 'brag_book_clear_sync_log' ),
				'delete_nonce'      => wp_create_nonce( 'brag_book_sync_delete' ),
				'sync_mode'         => $settings['sync_mode'] ?? 'wp_engine',
				'messages'          => [
					'sync_starting'     => __( 'Starting sync...', 'brag-book-gallery' ),
					'sync_in_progress'  => __( 'Sync in progress...', 'brag-book-gallery' ),
					'sync_complete'     => __( 'Sync completed successfully!', 'brag-book-gallery' ),
					'sync_error'        => __( 'Sync failed. Please try again.', 'brag-book-gallery' ),
					'confirm_clear_log' => __( 'Are you sure you want to clear the sync log?', 'brag-book-gallery' ),
					'confirm_delete_record' => __( 'Are you sure you want to delete this sync record?', 'brag-book-gallery' ),
				],
			];

			// Localize for both scripts
			wp_localize_script( 'brag-book-file-sync-admin', 'bragBookSync', $localize_data );
			wp_localize_script( 'brag-book-stage-sync', 'bragBookSync', $localize_data );

			// Add inline JavaScript to ensure stage progress elements exist
			wp_add_inline_script( 'brag-book-stage-sync', "
				document.addEventListener('DOMContentLoaded', function() {
					// Verify stage progress elements exist
					const stageProgress = document.getElementById('stage-progress');
					const stageProgressFill = document.getElementById('stage-progress-fill');
					const stageProgressText = document.getElementById('stage-progress-text');

					if (!stageProgress) {
						console.error('Stage progress element not found in DOM');
					} else {
						console.log('Stage progress element found:', stageProgress);
					}

					if (!stageProgressFill) {
						console.error('Stage progress fill element not found in DOM');
					} else {
						console.log('Stage progress fill element found:', stageProgressFill);
					}

					if (!stageProgressText) {
						console.error('Stage progress text element not found in DOM');
					} else {
						console.log('Stage progress text element found:', stageProgressText);
					}
				});
			" );

			// Add inline JavaScript for sync frequency controls
			wp_add_inline_script( 'brag-book-file-sync-admin', "
				document.addEventListener('DOMContentLoaded', function() {
					// Sync frequency controls
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
			$allowed_frequencies = [ 'weekly', 'custom' ];
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
			$sync_day  = $settings['sync_day'] ?? '0';     // Default to Sunday
			$sync_time = $settings['sync_time'] ?? '02:00'; // Default to 2:00 AM

			// Parse the time
			$time_parts = explode( ':', $sync_time );
			$hour       = (int) $time_parts[0];
			$minute     = (int) ( $time_parts[1] ?? 0 );

			// Calculate next occurrence of the specified day and time
			$start_time = $this->calculate_next_weekly_occurrence( (int) $sync_day, $hour, $minute );

			// Schedule the recurring weekly event
			$result = wp_schedule_event( $start_time, 'weekly', $hook_name );

			if ( false === $result ) {
				error_log( 'BRAG Book Gallery: Failed to schedule automatic sync cron job' );
			} else {
				$next_time = wp_date( 'l, F j, Y \a\t g:i A', $start_time );
				$day_name  = date( 'l', $start_time );
				error_log( "BRAG Book Gallery: Scheduled weekly automatic sync for {$day_name} at {$sync_time}, next run: {$next_time}" );
			}
		} else {
			error_log( 'BRAG Book Gallery: Automatic sync disabled, cron job cleared' );
		}
	}

	/**
	 * Calculate next occurrence of a specific day of week and time
	 *
	 * @since 3.3.2-beta10
	 * @param int $target_day   Day of week (0=Sunday, 6=Saturday)
	 * @param int $target_hour  Hour (0-23)
	 * @param int $target_minute Minute (0-59)
	 * @return int Unix timestamp of next occurrence
	 */
	private function calculate_next_weekly_occurrence( int $target_day, int $target_hour, int $target_minute ): int {
		$current_time = time();
		$current_day  = (int) date( 'w', $current_time ); // 0 (Sunday) through 6 (Saturday)

		// Calculate days until target day
		$days_until_target = ( $target_day - $current_day + 7 ) % 7;

		// If it's today, check if the time has passed
		if ( $days_until_target === 0 ) {
			$today_target_time = mktime( $target_hour, $target_minute, 0 );
			if ( $today_target_time > $current_time ) {
				// Time hasn't passed today, schedule for today
				return $today_target_time;
			} else {
				// Time has passed today, schedule for next week
				$days_until_target = 7;
			}
		}

		// Calculate the timestamp for the target day and time
		$next_occurrence = strtotime( "+{$days_until_target} days", $current_time );
		$next_occurrence = mktime( $target_hour, $target_minute, 0, date( 'm', $next_occurrence ), date( 'd', $next_occurrence ), date( 'Y', $next_occurrence ) );

		return $next_occurrence;
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

		// Get sync start time for execution time calculation
		$sync_start_time = get_transient( 'brag_book_gallery_sync_start_time' );
		$execution_time = $sync_start_time ? time() - $sync_start_time : 0;

		// Calculate real memory usage
		$memory_usage = memory_get_usage( true );
		$memory_peak = memory_get_peak_usage( true );
		$memory_limit = $this->get_memory_limit_bytes();

		// First check the transient used by Data_Sync
		$transient_progress = get_transient( 'brag_book_gallery_sync_progress' );

		// Then check the option for backward compatibility
		$option_progress = get_option( 'brag_book_gallery_detailed_progress', false );

		// Use whichever has data, preferring transient (Data_Sync)
		$progress = $transient_progress ?: $option_progress;

		error_log( 'BRAG book Gallery Sync: AJAX detailed progress request - found data: ' . ( $progress ? 'YES' : 'NO' ) );
		if ( $progress ) {
			error_log( 'BRAG book Gallery Sync: Detailed progress data: ' . wp_json_encode( $progress ) );
		}

		if ( $progress ) {
			// If this is from Data_Sync transient, format it for the frontend
			if ( $transient_progress ) {
				$formatted_progress = [
					'stage' => $progress['stage'] ?? 'processing',
					'overall_percentage' => $progress['progress'] ?? 0,
					'current_procedure' => $progress['current_procedure'] ?? '',
					'procedure_progress' => [
						'current' => $progress['current_item'] ?? 0,
						'total' => $progress['total_items'] ?? 0,
						'percentage' => $progress['item_progress'] ?? 0,
					],
					'case_progress' => [
						'current' => $progress['cases_processed'] ?? 0,
						'total' => $progress['total_cases'] ?? 0,
						'percentage' => $progress['case_progress'] ?? 0,
					],
					'current_step' => $progress['message'] ?? 'Processing...',
					'recent_cases' => $progress['recent_cases'] ?? [],
					// Add real resource monitoring data
					'execution_time' => $execution_time,
					'memory_usage' => $memory_usage,
					'memory_peak' => $memory_peak,
					'memory_limit' => $memory_limit,
				];
				wp_send_json_success( $formatted_progress );
			} else {
				// Option format - add resource monitoring data
				$progress['execution_time'] = $execution_time;
				$progress['memory_usage'] = $memory_usage;
				$progress['memory_peak'] = $memory_peak;
				$progress['memory_limit'] = $memory_limit;
				wp_send_json_success( $progress );
			}
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
				// Add default resource monitoring data
				'execution_time' => 0,
				'memory_usage' => 0,
				'memory_peak' => 0,
				'memory_limit' => $memory_limit,
			] );
		}
	}

	/**
	 * Get memory limit in bytes
	 *
	 * @since 3.3.0
	 * @return int Memory limit in bytes
	 */
	private function get_memory_limit_bytes(): int {
		// First check WordPress constants
		if ( defined( 'WP_MEMORY_LIMIT' ) && WP_MEMORY_LIMIT ) {
			$memory_limit = WP_MEMORY_LIMIT;
		} else {
			$memory_limit = ini_get( 'memory_limit' );
		}

		// Convert to bytes
		if ( preg_match( '/^(\d+)(.)$/i', $memory_limit, $matches ) ) {
			$unit = strtoupper( $matches[2] );
			$value = (int) $matches[1];
			if ( $unit === 'G' ) {
				return $value * 1024 * 1024 * 1024; // GB to bytes
			} elseif ( $unit === 'M' ) {
				return $value * 1024 * 1024; // MB to bytes
			} elseif ( $unit === 'K' ) {
				return $value * 1024; // KB to bytes
			}
		}
		return (int) $memory_limit;
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

		// Clear progress tracking for both Data_Sync and legacy
		delete_transient( 'brag_book_gallery_sync_progress' ); // Data_Sync uses transient
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

	/**
	 * Handle AJAX request to view sync log details
	 *
	 * @since 3.3.3
	 * @return void
	 */
	public function handle_view_sync_log(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_sync_log' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'brag-book-gallery' ) ] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ) ] );
		}

		$sync_id = absint( $_POST['sync_id'] ?? 0 );
		if ( ! $sync_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid sync ID.', 'brag-book-gallery' ) ] );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $sync_id )
		);

		if ( ! $record ) {
			wp_send_json_error( [ 'message' => __( 'Sync record not found.', 'brag-book-gallery' ) ] );
		}

		// Parse the details field - handle both old and new column names
		$details_json = $record->error_messages ?? $record->details ?? '{}';
		$details = json_decode( $details_json, true );

		// Map columns for compatibility
		$started_time = $record->started_at ?? $record->created_at ?? null;
		$completed_time = $record->completed_at ?? $record->updated_at ?? null;
		$sync_type = $record->sync_type ?? $record->item_type ?? 'unknown';
		$sync_status = $record->sync_status ?? $record->status ?? 'unknown';
		$items_processed = $record->items_processed ?? $record->processed ?? 0;
		$items_failed = $record->items_failed ?? $record->failed ?? 0;

		ob_start();
		?>
		<div class="sync-log-details">
			<h3><?php esc_html_e( 'Sync Information', 'brag-book-gallery' ); ?></h3>
			<table class="widefat">
				<tr>
					<th><?php esc_html_e( 'Started At:', 'brag-book-gallery' ); ?></th>
					<td><?php echo $started_time ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $started_time ) ) ) : '—'; ?></td>
				</tr>
				<?php if ( $completed_time ) : ?>
				<tr>
					<th><?php esc_html_e( 'Completed At:', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $completed_time ) ) ); ?></td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Type:', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( ucfirst( $sync_type ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Status:', 'brag-book-gallery' ); ?></th>
					<td><span class="sync-status sync-status--<?php echo esc_attr( $sync_status ); ?>"><?php echo esc_html( ucfirst( $sync_status ) ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Source:', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( ucfirst( $record->sync_source ?? 'manual' ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Items Processed:', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( number_format( $items_processed ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Items Failed:', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( number_format( $items_failed ) ); ?></td>
				</tr>
			</table>

			<?php if ( ! empty( $details ) ) : ?>
				<h3><?php esc_html_e( 'Sync Summary', 'brag-book-gallery' ); ?></h3>

				<?php if ( isset( $details['duration'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Duration:', 'brag-book-gallery' ); ?></strong> <?php echo esc_html( $details['duration'] ); ?></p>
				<?php endif; ?>

				<?php if ( isset( $details['sync_session_id'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Session ID:', 'brag-book-gallery' ); ?></strong> <?php echo esc_html( $details['sync_session_id'] ); ?></p>
				<?php endif; ?>

				<?php if ( isset( $details['created'] ) || isset( $details['updated'] ) ) : ?>
					<h4><?php esc_html_e( 'Procedures Processed:', 'brag-book-gallery' ); ?></h4>
					<ul>
						<li><?php echo esc_html( sprintf( __( 'Created: %d', 'brag-book-gallery' ), $details['created'] ?? 0 ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Updated: %d', 'brag-book-gallery' ), $details['updated'] ?? 0 ) ); ?></li>
					</ul>
				<?php endif; ?>

				<?php if ( isset( $details['cases_created'] ) || isset( $details['cases_updated'] ) || isset( $details['cases_attempted'] ) ) : ?>
					<h4><?php esc_html_e( 'Cases Processed:', 'brag-book-gallery' ); ?></h4>
					<ul>
						<?php if ( isset( $details['cases_attempted'] ) && $details['cases_attempted'] > 0 ) : ?>
							<li><?php echo esc_html( sprintf( __( 'Attempted: %d', 'brag-book-gallery' ), $details['cases_attempted'] ) ); ?></li>
						<?php endif; ?>
						<?php if ( isset( $details['duplicate_occurrences'] ) && $details['duplicate_occurrences'] > 0 ) : ?>
							<li style="color: #856404;">
								<?php
								$dup_msg = sprintf( __( 'Duplicates Skipped: %d occurrences', 'brag-book-gallery' ), $details['duplicate_occurrences'] );
								if ( isset( $details['duplicate_count'] ) && $details['duplicate_count'] > 0 ) {
									$dup_msg .= sprintf( __( ' (from %d unique IDs)', 'brag-book-gallery' ), $details['duplicate_count'] );
								}
								echo esc_html( $dup_msg );
								?>
							</li>
						<?php endif; ?>
						<li><?php echo esc_html( sprintf( __( 'Created: %d', 'brag-book-gallery' ), $details['cases_created'] ?? 0 ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Updated: %d', 'brag-book-gallery' ), $details['cases_updated'] ?? 0 ) ); ?></li>
					</ul>
				<?php endif; ?>

				<?php if ( ! empty( $details['warnings'] ) ) : ?>
					<h4><?php esc_html_e( 'Warnings:', 'brag-book-gallery' ); ?></h4>
					<div class="sync-warnings" style="background: #fff3cd; padding: 10px; border: 1px solid #ffc107; border-radius: 3px;">
						<ul style="margin: 0;">
							<?php foreach ( $details['warnings'] as $warning ) : ?>
								<li><?php echo esc_html( $warning ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $details['errors'] ) ) : ?>
					<h4><?php esc_html_e( 'Errors:', 'brag-book-gallery' ); ?></h4>
					<div class="sync-errors" style="background: #f8d7da; padding: 10px; border: 1px solid #f5c2c7; border-radius: 3px;">
						<ul style="margin: 0;">
							<?php foreach ( $details['errors'] as $error ) : ?>
								<li><?php echo esc_html( $error ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $details['activity_log'] ) && is_array( $details['activity_log'] ) ) : ?>
					<h4><?php esc_html_e( 'Activity Log:', 'brag-book-gallery' ); ?></h4>
					<div class="sync-activity-log" style="background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 3px; max-height: 400px; overflow-y: auto;">
						<table class="widefat striped" style="font-size: 12px;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Time', 'brag-book-gallery' ); ?></th>
									<th><?php esc_html_e( 'Type', 'brag-book-gallery' ); ?></th>
									<th><?php esc_html_e( 'Operation', 'brag-book-gallery' ); ?></th>
									<th><?php esc_html_e( 'Item', 'brag-book-gallery' ); ?></th>
									<th><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $details['activity_log'] as $log_item ) : ?>
									<tr>
										<td><?php echo esc_html( date( 'H:i:s', strtotime( $log_item['time'] ?? '' ) ) ); ?></td>
										<td><?php echo esc_html( $log_item['type'] ?? '' ); ?></td>
										<td><?php echo esc_html( $log_item['operation'] ?? '' ); ?></td>
										<td><?php echo esc_html( $log_item['item'] ?? '' ); ?></td>
										<td>
											<span class="sync-status sync-status--<?php echo esc_attr( $log_item['status'] ?? '' ); ?>">
												<?php echo esc_html( $log_item['status'] ?? '' ); ?>
											</span>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<h4><?php esc_html_e( 'Raw Details:', 'brag-book-gallery' ); ?></h4>
					<pre style="background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 3px; max-height: 300px; overflow-y: auto;"><?php echo esc_html( json_encode( $details, JSON_PRETTY_PRINT ) ); ?></pre>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php

		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Handle AJAX request to delete multiple sync records
	 *
	 * @since 3.3.3
	 * @return void
	 */
	public function handle_delete_sync_records(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_sync_delete' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'brag-book-gallery' ) ] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ) ] );
		}

		$sync_ids = array_map( 'absint', $_POST['sync_ids'] ?? [] );
		if ( empty( $sync_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No records selected.', 'brag-book-gallery' ) ] );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		$placeholders = implode( ',', array_fill( 0, count( $sync_ids ), '%d' ) );
		$deleted = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table_name} WHERE id IN ({$placeholders})", ...$sync_ids )
		);

		if ( $deleted ) {
			wp_send_json_success( [
				'message' => sprintf(
					__( '%d sync record(s) deleted successfully.', 'brag-book-gallery' ),
					$deleted
				)
			] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to delete sync records.', 'brag-book-gallery' ) ] );
		}
	}

	/**
	 * Handle AJAX request to clear all sync history
	 *
	 * @since 3.3.3
	 * @return void
	 */
	public function handle_clear_sync_history(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_sync_delete' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'brag-book-gallery' ) ] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ) ] );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		$deleted = $wpdb->query( "TRUNCATE TABLE {$table_name}" );

		if ( $deleted !== false ) {
			// Also clear any file-based logs
			$upload_dir = wp_upload_dir();
			$sync_dir = $upload_dir['basedir'] . '/brag-book-sync';
			if ( is_dir( $sync_dir ) ) {
				$files = glob( $sync_dir . '/*.{json,log}', GLOB_BRACE );
				if ( $files ) {
					foreach ( $files as $file ) {
						unlink( $file );
					}
				}
			}

			wp_send_json_success( [ 'message' => __( 'All sync history has been cleared.', 'brag-book-gallery' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to clear sync history.', 'brag-book-gallery' ) ] );
		}
	}

	/**
	 * Render sync history table
	 *
	 * @since 3.3.3
	 * @return void
	 */
	private function render_sync_history_table(): void {
		global $wpdb;

		// Get sync records from database
		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		// Check if table exists first
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
		if ( ! $table_exists ) {
			echo '<p>' . esc_html__( 'Sync history table not found. Please re-activate the plugin.', 'brag-book-gallery' ) . '</p>';
			error_log( 'BRAGBook Sync History: Table does not exist: ' . $table_name );
			return;
		}

		// Check which columns exist (handle different schema versions)
		$columns = $wpdb->get_col( "DESCRIBE {$table_name}" );
		$has_started_at = in_array( 'started_at', $columns, true );
		$has_created_at = in_array( 'created_at', $columns, true );

		error_log( 'BRAGBook Sync History: Table columns: ' . implode( ', ', $columns ) );

		// Use appropriate column for ordering based on what exists
		$order_column = 'id'; // Default fallback
		if ( $has_started_at ) {
			$order_column = 'started_at';
		} elseif ( $has_created_at ) {
			$order_column = 'created_at';
		}

		// Get all sync records
		$query = "SELECT * FROM {$table_name} ORDER BY {$order_column} DESC LIMIT 50";
		error_log( 'BRAGBook Sync History: Executing query: ' . $query );

		$records = $wpdb->get_results( $query );

		error_log( 'BRAGBook Sync History: Found ' . count( $records ) . ' records' );

		if ( empty( $records ) ) {
			// Show count for debugging
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
			echo '<p>' . esc_html__( 'No sync history available.', 'brag-book-gallery' ) . ' (Total records in table: ' . esc_html( $count ) . ')</p>';
			error_log( 'BRAGBook Sync History: No records returned. Total in table: ' . $count );
			return;
		}
		?>

		<div class="brag-book-sync-history-wrapper">
			<div class="tablenav top">
				<div class="alignleft actions">
					<button type="button" id="delete-selected-sync-records" class="button" disabled>
						<?php esc_html_e( 'Delete Selected', 'brag-book-gallery' ); ?>
					</button>
					<button type="button" id="clear-all-sync-history" class="button">
						<?php esc_html_e( 'Clear All History', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="check-column">
							<input type="checkbox" id="select-all-sync-records" />
						</td>
						<th><?php esc_html_e( 'Date/Time', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Type', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Source', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Processed', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Failed', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Duration', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $records as $record ) :
						// Handle both old and new column names
						$details_json = $record->error_messages ?? $record->details ?? '{}';
						$details = json_decode( $details_json, true );

						// Get started time from available columns
						$started_time = $record->started_at ?? $record->created_at ?? null;
						$started = $started_time ? strtotime( $started_time ) : time();

						// Get completed time from available columns
						$completed_time = $record->completed_at ?? $record->updated_at ?? null;
						$completed = $completed_time ? strtotime( $completed_time ) : null;

						// Extract duration from details if available, otherwise calculate
						$duration_str = $details['duration'] ?? '';
						if ( empty( $duration_str ) && $completed ) {
							$duration_seconds = $completed - $started;
							if ( $duration_seconds < 60 ) {
								$duration_str = $duration_seconds . ' seconds';
							} else {
								$minutes = floor( $duration_seconds / 60 );
								$seconds = $duration_seconds % 60;
								$duration_str = $minutes . ' minute' . ( $minutes > 1 ? 's' : '' ) . ' ' . $seconds . ' second' . ( $seconds !== 1 ? 's' : '' );
							}
						}

						// Map old column names to new ones if needed
						$sync_type = $record->sync_type ?? $record->item_type ?? 'Full Sync';
						$sync_status = $record->sync_status ?? $record->status ?? 'unknown';

						// Get items processed/failed - combine procedures and cases
						$procedures_created = $details['created'] ?? 0;
						$procedures_updated = $details['updated'] ?? 0;
						$cases_created = $details['cases_created'] ?? 0;
						$cases_updated = $details['cases_updated'] ?? 0;
						$cases_attempted = $details['cases_attempted'] ?? 0;
						$duplicate_count = $details['duplicate_count'] ?? 0; // Unique duplicate IDs
						$duplicate_occurrences = $details['duplicate_occurrences'] ?? 0; // Total duplicate skips

						$items_processed = $record->items_processed ?? $record->processed ??
										  ($procedures_created + $procedures_updated + $cases_created + $cases_updated);
						$items_failed = (int) ( $record->items_failed ?? $record->failed ?? 0 );

						// Build a more informative display
						$processed_display = '';
						if ( $procedures_created || $procedures_updated ) {
							$processed_display .= '<strong>Procedures:</strong> ' . $procedures_created . ' created, ' . $procedures_updated . ' updated';
						}
						if ( $cases_created || $cases_updated || $cases_attempted ) {
							if ( $processed_display ) $processed_display .= '<br>';
							$processed_display .= '<strong>Cases:</strong> ';

							// If we have the attempted count, show detailed breakdown
							if ( $cases_attempted > 0 ) {
								$processed_display .= $cases_attempted . ' attempted';
								if ( $duplicate_occurrences > 0 ) {
									$processed_display .= ' (' . $duplicate_occurrences . ' duplicate occurrences from ' . $duplicate_count . ' unique IDs skipped)';
								}
								$processed_display .= '<br>&nbsp;&nbsp;&nbsp;&nbsp;→ ' . $cases_created . ' created, ' . $cases_updated . ' updated';
							} else {
								// Fallback to simple display
								$processed_display .= $cases_created . ' created, ' . $cases_updated . ' updated';
							}
						}
						if ( empty( $processed_display ) ) {
							$processed_display = number_format( $items_processed );
						}
						?>
						<tr data-sync-id="<?php echo esc_attr( $record->id ); ?>">
							<th scope="row" class="check-column">
								<input type="checkbox" name="sync_records[]" value="<?php echo esc_attr( $record->id ); ?>" />
							</th>
							<td>
								<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $started ) ); ?>
							</td>
							<td><?php echo esc_html( str_replace( '_', ' ', ucwords( $sync_type ) ) ); ?></td>
							<td>
								<span class="sync-status sync-status--<?php echo esc_attr( $sync_status ); ?>">
									<?php echo esc_html( ucfirst( $sync_status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( ucfirst( $record->sync_source ?? 'manual' ) ); ?></td>
							<td><?php echo wp_kses_post( $processed_display ); ?></td>
							<td><?php echo esc_html( number_format( $items_failed ) ); ?></td>
							<td>
								<?php echo ! empty( $duration_str ) ? esc_html( $duration_str ) : '—'; ?>
							</td>
							<td>
								<button type="button" class="button button-small view-sync-log" data-sync-id="<?php echo esc_attr( $record->id ); ?>">
									<?php esc_html_e( 'View Log', 'brag-book-gallery' ); ?>
								</button>
								<button type="button" class="button button-small button-link-delete delete-sync-record" data-sync-id="<?php echo esc_attr( $record->id ); ?>">
									<?php esc_html_e( 'Delete', 'brag-book-gallery' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Log Viewer Modal -->
		<div id="sync-log-modal" class="brag-book-modal" style="display: none;">
			<div class="brag-book-modal-content">
				<div class="brag-book-modal-header">
					<h2><?php esc_html_e( 'Sync Log Details', 'brag-book-gallery' ); ?></h2>
					<button type="button" class="brag-book-modal-close">&times;</button>
				</div>
				<div class="brag-book-modal-body">
					<div id="sync-log-content">
						<!-- Log content will be loaded here -->
					</div>
				</div>
			</div>
		</div>

		<?php
		$this->render_sync_history_styles();
		$this->render_sync_history_scripts();
	}

	/**
	 * Render sync history table styles
	 *
	 * @since 3.3.3
	 * @return void
	 */
	private function render_sync_history_styles(): void {
		?>
		<style>
		.sync-status--completed { color: #00a32a; font-weight: 600; }
		.sync-status--failed { color: #d63638; font-weight: 600; }
		.sync-status--started { color: #dba617; font-weight: 600; }

		.brag-book-modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.5);
			z-index: 100000;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.brag-book-modal-content {
			background: #fff;
			width: 90%;
			max-width: 800px;
			max-height: 80vh;
			border-radius: 4px;
			box-shadow: 0 5px 15px rgba(0,0,0,0.3);
			display: flex;
			flex-direction: column;
		}

		.brag-book-modal-header {
			padding: 15px 20px;
			border-bottom: 1px solid #ddd;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.brag-book-modal-header h2 {
			margin: 0;
			font-size: 20px;
		}

		.brag-book-modal-close {
			background: none;
			border: none;
			font-size: 24px;
			cursor: pointer;
			padding: 0;
			width: 30px;
			height: 30px;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.brag-book-modal-body {
			padding: 20px;
			overflow-y: auto;
			flex: 1;
		}

		#sync-log-content pre {
			background: #f6f7f7;
			padding: 15px;
			border: 1px solid #ddd;
			border-radius: 3px;
			overflow-x: auto;
			font-size: 13px;
			line-height: 1.5;
		}

		.brag-book-sync-history-wrapper {
			margin-top: 20px;
		}

		.sync-log-details table.widefat th {
			width: 30%;
		}
		</style>
		<?php
	}

	/**
	 * Render sync history table scripts
	 *
	 * @since 3.3.3
	 * @return void
	 */
	private function render_sync_history_scripts(): void {
		?>
		<script>
		jQuery(document).ready(function($) {
			// Select all checkboxes
			$('#select-all-sync-records').on('change', function() {
				$('input[name="sync_records[]"]').prop('checked', this.checked);
				updateDeleteButton();
			});

			// Individual checkbox change
			$('input[name="sync_records[]"]').on('change', function() {
				updateDeleteButton();
			});

			// Update delete button state
			function updateDeleteButton() {
				const hasChecked = $('input[name="sync_records[]"]:checked').length > 0;
				$('#delete-selected-sync-records').prop('disabled', !hasChecked);
			}

			// View sync log
			$('.view-sync-log').on('click', function() {
				const syncId = $(this).data('sync-id');

				$.post(ajaxurl, {
					action: 'brag_book_view_sync_log',
					sync_id: syncId,
					nonce: '<?php echo wp_create_nonce( 'brag_book_sync_log' ); ?>'
				}, function(response) {
					if (response.success) {
						$('#sync-log-content').html(response.data.html);
						$('#sync-log-modal').fadeIn();
					} else {
						alert(response.data.message || 'Failed to load log');
					}
				});
			});

			// Close modal
			$('.brag-book-modal-close, #sync-log-modal').on('click', function(e) {
				if (e.target === this) {
					$('#sync-log-modal').fadeOut();
				}
			});

			// Delete single record
			$('.delete-sync-record').on('click', function() {
				if (!confirm('<?php esc_html_e( 'Are you sure you want to delete this sync record?', 'brag-book-gallery' ); ?>')) {
					return;
				}

				const syncId = $(this).data('sync-id');
				const $row = $(this).closest('tr');

				$.post(ajaxurl, {
					action: 'brag_book_delete_sync_record',
					sync_id: syncId,
					nonce: '<?php echo wp_create_nonce( 'brag_book_sync_delete' ); ?>'
				}, function(response) {
					if (response.success) {
						$row.fadeOut(400, function() {
							$(this).remove();
							updateDeleteButton();
						});
					} else {
						alert(response.data.message || 'Failed to delete record');
					}
				});
			});

			// Delete selected records
			$('#delete-selected-sync-records').on('click', function() {
				const selectedIds = [];
				$('input[name="sync_records[]"]:checked').each(function() {
					selectedIds.push($(this).val());
				});

				if (selectedIds.length === 0) return;

				const confirmMsg = '<?php esc_html_e( 'Are you sure you want to delete', 'brag-book-gallery' ); ?> ' + selectedIds.length + ' <?php esc_html_e( 'sync record(s)?', 'brag-book-gallery' ); ?>';
				if (!confirm(confirmMsg)) {
					return;
				}

				$.post(ajaxurl, {
					action: 'brag_book_delete_sync_records',
					sync_ids: selectedIds,
					nonce: '<?php echo wp_create_nonce( 'brag_book_sync_delete' ); ?>'
				}, function(response) {
					if (response.success) {
						selectedIds.forEach(function(id) {
							$('tr[data-sync-id="' + id + '"]').fadeOut(400, function() {
								$(this).remove();
							});
						});
						$('#select-all-sync-records').prop('checked', false);
						updateDeleteButton();
					} else {
						alert(response.data.message || 'Failed to delete records');
					}
				});
			});

			// Clear all history
			$('#clear-all-sync-history').on('click', function() {
				if (!confirm('<?php esc_html_e( 'Are you sure you want to clear ALL sync history? This action cannot be undone.', 'brag-book-gallery' ); ?>')) {
					return;
				}

				$.post(ajaxurl, {
					action: 'brag_book_clear_sync_history',
					nonce: '<?php echo wp_create_nonce( 'brag_book_sync_delete' ); ?>'
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to clear history');
					}
				});
			});
		});
		</script>
		<?php
	}
}
