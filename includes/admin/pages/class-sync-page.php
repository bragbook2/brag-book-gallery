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
use BRAGBookGallery\Includes\Sync\Sync_Api;
use BRAGBookGallery\Includes\Core\Updater;
use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;
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
		add_action( 'wp_ajax_brag_book_get_bragbook_sync_status', [ $this, 'handle_get_bragbook_sync_status' ] );
		add_action( 'wp_ajax_brag_book_toggle_api_environment', [ $this, 'handle_toggle_api_environment' ] );
		add_action( 'wp_ajax_brag_book_delete_all_data', [ $this, 'handle_delete_all_data' ] );

		// Register REST API endpoint for remote sync triggering
		add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );

		// Register cron hook for REST-triggered sync execution (one-time events only)
		// This allows the REST API to return immediately while sync runs asynchronously
		add_action( 'brag_book_gallery_rest_sync', [ $this, 'handle_rest_sync_execution' ] );

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

			<?php $this->render_automatic_sync_section(); ?>
		</form>

		<?php $this->render_delete_all_data_section(); ?>

		<?php
		$this->render_footer();
	}

	/**
	 * Render automatic sync scheduling section
	 *
	 * Displays options for scheduling weekly automatic syncs.
	 * Only shown after a successful full sync has been completed.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	private function render_automatic_sync_section(): void {
		// Check if a full sync has been completed
		$last_sync_time   = get_option( 'brag_book_gallery_last_sync_time', '' );
		$last_sync_status = get_option( 'brag_book_gallery_last_sync_status', '' );

		// Only show if a sync has been completed successfully
		$has_completed_sync = ! empty( $last_sync_time ) && in_array( $last_sync_status, [ 'success', 'partial' ], true );

		if ( ! $has_completed_sync ) {
			return;
		}

		$sync_settings       = get_option( 'brag_book_gallery_sync_settings', [] );
		$auto_enabled        = ! empty( $sync_settings['auto_sync_enabled'] );
		$sync_day            = $sync_settings['sync_day'] ?? '0';
		$sync_time           = $sync_settings['sync_time'] ?? '02:00';
		$next_scheduled_opt  = get_option( 'brag_book_gallery_next_scheduled_sync', [] );
		$next_scheduled      = $next_scheduled_opt['timestamp'] ?? null;
		$last_sync_source    = get_option( 'brag_book_gallery_last_sync_source', '' );

		$days_of_week = [
			'0' => __( 'Sunday', 'brag-book-gallery' ),
			'1' => __( 'Monday', 'brag-book-gallery' ),
			'2' => __( 'Tuesday', 'brag-book-gallery' ),
			'3' => __( 'Wednesday', 'brag-book-gallery' ),
			'4' => __( 'Thursday', 'brag-book-gallery' ),
			'5' => __( 'Friday', 'brag-book-gallery' ),
			'6' => __( 'Saturday', 'brag-book-gallery' ),
		];
		?>

		<!-- Automatic Sync Section -->
		<div class="brag-book-gallery-section" id="automatic-sync-section">
			<h2><?php esc_html_e( 'Automatic Weekly Sync', 'brag-book-gallery' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure automatic weekly synchronization. After enabling, syncs will run automatically on your chosen day and time.', 'brag-book-gallery' ); ?>
			</p>

			<div class="brag-book-gallery-card auto-sync-card">
				<!-- BRAG book Sync Status Header -->
				<div class="auto-sync-header">
					<span class="auto-sync-header__title"><?php esc_html_e( 'BRAG book Sync Status', 'brag-book-gallery' ); ?></span>
					<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" class="auto-sync-header__icon"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm-40-360v240h80v-240h-80Zm0-160v80h80v-80h-80Z"/></svg>
				</div>

				<!-- Last Sync Stats -->
				<div class="auto-sync-field">
					<div class="auto-sync-status-row">
						<div class="auto-sync-status-item">
							<span class="auto-sync-status-item__label"><?php esc_html_e( 'Last Sync Reported', 'brag-book-gallery' ); ?></span>
							<span class="auto-sync-status-item__value">
								<?php
								$last_time = strtotime( $last_sync_time );
								echo esc_html( wp_date( 'F j, Y \a\t g:i A', $last_time ) );
								echo ' (' . esc_html( human_time_diff( $last_time, time() ) ) . ' ' . esc_html__( 'ago', 'brag-book-gallery' ) . ')';
								?>
							</span>
						</div>
						<div class="auto-sync-status-item">
							<span class="auto-sync-status-item__label"><?php esc_html_e( 'Cases Synced', 'brag-book-gallery' ); ?></span>
							<span class="auto-sync-status-item__value">
								<?php
								$last_cases = get_option( 'brag_book_gallery_last_sync_cases', 0 );
								echo esc_html( number_format( (int) $last_cases ) );
								?>
							</span>
						</div>
					</div>
				</div>

				<!-- Enable Auto Sync -->
				<div class="auto-sync-field">
					<div class="brag-book-gallery-toggle-wrapper">
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" class="auto-sync-toggle-icon"><path d="M160-160v-80h110l-16-14q-52-46-73-105t-21-119q0-111 66.5-197.5T400-790v84q-72 26-116 88.5T240-478q0 45 17 87.5t53 78.5l10 10v-98h80v240H160Zm400-10v-84q72-26 116-88.5T720-482q0-45-17-87.5T650-648l-10-10v98h-80v-240h240v80H690l16 14q49 49 71.5 106.5T800-482q0 111-66.5 197.5T560-170Z"/></svg>
						<label class="brag-book-gallery-toggle">
							<input type="hidden" name="brag_book_gallery_sync_settings[auto_sync_enabled]" value="0" />
							<input type="checkbox"
								name="brag_book_gallery_sync_settings[auto_sync_enabled]"
								id="auto_sync_enabled"
								value="1"
								<?php checked( $auto_enabled ); ?> />
							<span class="brag-book-gallery-toggle-slider"></span>
						</label>
						<div class="brag-book-gallery-toggle-text">
							<span class="brag-book-gallery-toggle-label"><?php esc_html_e( 'Enable Automatic Sync', 'brag-book-gallery' ); ?></span>
							<span class="description"><?php esc_html_e( 'Schedule weekly automatic synchronization', 'brag-book-gallery' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Schedule Settings (shown when enabled) -->
				<div class="auto-sync-schedule-row auto-sync-field" <?php echo ! $auto_enabled ? 'style="display: none;"' : ''; ?>>
					<div class="sync-schedule-fields">
						<div class="sync-schedule-field">
							<label for="sync_day"><?php esc_html_e( 'Day of Week', 'brag-book-gallery' ); ?></label>
							<select name="brag_book_gallery_sync_settings[sync_day]" id="sync_day" class="sync-day-select">
								<?php foreach ( $days_of_week as $day_value => $day_label ) : ?>
									<option value="<?php echo esc_attr( $day_value ); ?>" <?php selected( $sync_day, $day_value ); ?>>
										<?php echo esc_html( $day_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="sync-schedule-field">
							<label for="sync_time"><?php esc_html_e( 'Time', 'brag-book-gallery' ); ?></label>
							<input type="time"
								name="brag_book_gallery_sync_settings[sync_time]"
								id="sync_time"
								value="<?php echo esc_attr( $sync_time ); ?>"
								class="sync-time-input" />
						</div>
					</div>
					<p class="description">
						<?php esc_html_e( 'Syncs will run weekly on the selected day and time. Times are in your WordPress timezone.', 'brag-book-gallery' ); ?>
						<br>
						<strong><?php esc_html_e( 'Current timezone:', 'brag-book-gallery' ); ?></strong> <?php echo esc_html( wp_timezone_string() ); ?>
					</p>
				</div>

				<?php if ( $next_scheduled ) : ?>
					<div class="auto-sync-schedule-row auto-sync-field" <?php echo ! $auto_enabled ? 'style="display: none;"' : ''; ?>>
						<div class="next-sync-info">
							<?php
							$human_time = human_time_diff( time(), $next_scheduled );
							$exact_time = wp_date( 'l, F j, Y \a\t g:i A', $next_scheduled );

							if ( time() > $next_scheduled ) {
								printf(
									'<span class="sync-overdue">%s <strong>%s</strong></span>',
									esc_html__( 'Overdue - was scheduled for', 'brag-book-gallery' ),
									esc_html( $exact_time )
								);
							} else {
								printf(
									'<span class="sync-scheduled"><strong>%s</strong> <span class="time-until">(%s)</span></span>',
									esc_html( $exact_time ),
									sprintf( esc_html__( 'in %s', 'brag-book-gallery' ), esc_html( $human_time ) )
								);
							}
							?>
						</div>
					</div>
				<?php endif; ?>

				<div class="auto-sync-actions">
					<?php submit_button( __( 'Save Schedule Settings', 'brag-book-gallery' ), 'primary button-primary-dark', 'save_auto_sync_settings', false ); ?>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Toggle schedule fields visibility
			$('#auto_sync_enabled').on('change', function() {
				if ($(this).is(':checked')) {
					$('.auto-sync-schedule-row').slideDown();
				} else {
					$('.auto-sync-schedule-row').slideUp();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render Delete All Data danger zone section
	 *
	 * Displays a destructive action section that allows administrators
	 * to delete all synced procedures, doctors, and cases.
	 *
	 * @since 3.4.0
	 * @return void
	 */
	private function render_delete_all_data_section(): void {
		?>
		<div class="brag-book-gallery-section brag-book-gallery-danger-zone">
			<h2><?php esc_html_e( 'Danger Zone', 'brag-book-gallery' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Irreversible actions that will permanently remove data from this site.', 'brag-book-gallery' ); ?>
			</p>

			<div class="danger-zone-action" style="margin-top: 16px;">
				<div class="danger-zone-info">
					<strong><?php esc_html_e( 'Delete All Synced Data', 'brag-book-gallery' ); ?></strong>
					<p class="description">
						<?php esc_html_e( 'Permanently delete all synced cases, procedures, and doctors from this WordPress site. This does not affect data on the BRAG book API.', 'brag-book-gallery' ); ?>
					</p>
				</div>
				<button type="button" class="button button-danger" id="brag-book-delete-all-data">
					<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Delete All Data', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</div>

		<!-- Delete All Data Confirmation Dialog -->
		<dialog class="brag-book-gallery-dialog brag-book-gallery-dialog-danger" id="brag-book-delete-all-dialog">
			<div class="brag-book-gallery-dialog-content">
				<div class="brag-book-gallery-dialog-header">
					<h3 class="brag-book-gallery-dialog-title">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Delete All Synced Data', 'brag-book-gallery' ); ?>
					</h3>
					<button type="button" class="brag-book-gallery-dialog-close" id="brag-book-delete-dialog-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="brag-book-gallery-dialog-body">
					<div class="brag-book-gallery-dialog-icon">
						<span class="dashicons dashicons-trash"></span>
					</div>
					<div class="brag-book-gallery-dialog-message">
						<p><strong><?php esc_html_e( 'Are you sure? This action cannot be undone.', 'brag-book-gallery' ); ?></strong></p>
						<p><?php esc_html_e( 'This will permanently delete all synced:', 'brag-book-gallery' ); ?></p>
						<ul style="margin: 8px 0 8px 20px; list-style: disc;">
							<li><?php esc_html_e( 'Cases (posts)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Procedures (taxonomy terms)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctors (taxonomy terms)', 'brag-book-gallery' ); ?></li>
						</ul>
						<p>
							<?php
							printf(
								/* translators: %s: the word DELETE in bold */
								esc_html__( 'Type %s below to confirm.', 'brag-book-gallery' ),
								'<strong>DELETE</strong>'
							);
							?>
						</p>
						<input type="text" id="brag-book-delete-confirm-input" placeholder="DELETE" autocomplete="off" style="width: 100%; margin-top: 8px; padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 6px;" />
					</div>
				</div>
				<div class="brag-book-gallery-dialog-footer">
					<button type="button" class="button" id="brag-book-delete-dialog-cancel">
						<?php esc_html_e( 'Cancel', 'brag-book-gallery' ); ?>
					</button>
					<button type="button" class="button button-danger" id="brag-book-delete-confirm-btn" disabled>
						<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
						<?php esc_html_e( 'Delete Everything', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</div>
		</dialog>

		<script>
		jQuery(document).ready(function($) {
			var $dialog = document.getElementById('brag-book-delete-all-dialog');
			var $confirmInput = $('#brag-book-delete-confirm-input');
			var $confirmBtn = $('#brag-book-delete-confirm-btn');

			// Open dialog
			$('#brag-book-delete-all-data').on('click', function() {
				$confirmInput.val('');
				$confirmBtn.prop('disabled', true);
				$dialog.showModal();
			});

			// Close dialog
			$('#brag-book-delete-dialog-close, #brag-book-delete-dialog-cancel').on('click', function() {
				$dialog.close();
			});

			// Enable confirm button when DELETE is typed
			$confirmInput.on('input', function() {
				$confirmBtn.prop('disabled', $(this).val() !== 'DELETE');
			});

			// Handle deletion
			$confirmBtn.on('click', function() {
				var $btn = $(this);
				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Deleting...', 'brag-book-gallery' ) ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'brag_book_delete_all_data',
						nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_delete_all_data' ) ); ?>'
					},
					success: function(response) {
						$dialog.close();
						if (response.success) {
							var msg = response.data.message || '<?php echo esc_js( __( 'All data has been deleted.', 'brag-book-gallery' ) ); ?>';
							alert(msg);
							location.reload();
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Failed to delete data.', 'brag-book-gallery' ) ); ?>');
						}
					},
					error: function() {
						$dialog.close();
						alert('<?php echo esc_js( __( 'An error occurred. Please try again.', 'brag-book-gallery' ) ); ?>');
					},
					complete: function() {
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> <?php echo esc_js( __( 'Delete Everything', 'brag-book-gallery' ) ); ?>');
					}
				});
			});

			// Close on backdrop click
			$dialog.addEventListener('click', function(e) {
				if (e.target === $dialog) {
					$dialog.close();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle AJAX request to toggle API environment
	 *
	 * @since 3.4.0
	 * @return void
	 */
	public function handle_toggle_api_environment(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_toggle_api_environment' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'brag-book-gallery' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ) ] );
		}

		$environment = sanitize_text_field( $_POST['environment'] ?? '' );

		if ( $environment === 'staging' ) {
			update_option( 'brag_book_gallery_api_base_url', 'https://staging.bragbookgallery.com' );
		} else {
			delete_option( 'brag_book_gallery_api_base_url' );
		}

		wp_send_json_success( [
			'message'     => sprintf(
				/* translators: %s: environment name */
				__( 'API environment switched to %s.', 'brag-book-gallery' ),
				$environment
			),
			'environment' => $environment,
		] );
	}

	/**
	 * Handle AJAX request to delete all synced data
	 *
	 * Deletes all cases (posts), procedures (taxonomy terms),
	 * and doctors (taxonomy terms) from the WordPress site.
	 *
	 * @since 3.4.0
	 * @return void
	 */
	public function handle_delete_all_data(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_delete_all_data' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'brag-book-gallery' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ) ] );
		}

		$deleted_cases      = 0;
		$deleted_procedures = 0;
		$deleted_doctors    = 0;

		// Delete all cases
		$cases = get_posts( [
			'post_type'      => Post_Types::POST_TYPE_CASES,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		] );

		foreach ( $cases as $case_id ) {
			if ( wp_delete_post( $case_id, true ) ) {
				$deleted_cases++;
			}
		}

		// Delete all procedure terms
		$procedures = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'hide_empty' => false,
			'fields'     => 'ids',
		] );

		if ( ! is_wp_error( $procedures ) ) {
			foreach ( $procedures as $term_id ) {
				if ( wp_delete_term( $term_id, Taxonomies::TAXONOMY_PROCEDURES ) ) {
					$deleted_procedures++;
				}
			}
		}

		// Delete all doctor terms
		$doctors = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_DOCTORS,
			'hide_empty' => false,
			'fields'     => 'ids',
		] );

		if ( ! is_wp_error( $doctors ) ) {
			foreach ( $doctors as $term_id ) {
				if ( wp_delete_term( $term_id, Taxonomies::TAXONOMY_DOCTORS ) ) {
					$deleted_doctors++;
				}
			}
		}

		// Clear related transients and sync status
		delete_option( 'brag_book_gallery_last_sync_time' );
		delete_option( 'brag_book_gallery_last_sync_status' );
		delete_option( 'brag_book_gallery_last_sync_source' );

		// Clear API cache
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_brag_book_gallery_transient_%'
			OR option_name LIKE '_transient_timeout_brag_book_gallery_transient_%'"
		);

		wp_cache_flush();

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: 1: number of cases, 2: number of procedures, 3: number of doctors */
				__( 'Successfully deleted %1$d cases, %2$d procedures, and %3$d doctors.', 'brag-book-gallery' ),
				$deleted_cases,
				$deleted_procedures,
				$deleted_doctors
			),
			'deleted' => [
				'cases'      => $deleted_cases,
				'procedures' => $deleted_procedures,
				'doctors'    => $deleted_doctors,
			],
		] );
	}

	/**
	 * Render custom notices for the sync page
	 *
	 * Overrides parent to display settings errors/notices that were
	 * added via add_notice() method.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function render_custom_notices(): void {
		// Display any settings errors/notices
		settings_errors( $this->page_slug );

		// Call parent for additional notices (factory reset, etc.)
		parent::render_custom_notices();
	}

	/**
	 * Save automatic sync settings from form submission
	 *
	 * Handles the form submission for automatic sync scheduling settings.
	 * Validates input, saves settings, and schedules/unschedules the sync accordingly.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	private function save_settings_form(): void {
		// Check if this is the auto sync settings form
		if ( ! isset( $_POST['save_auto_sync_settings'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! $this->save_settings( 'brag_book_gallery_sync_settings', 'brag_book_gallery_sync_nonce' ) ) {
			$this->add_notice( __( 'Security check failed. Please try again.', 'brag-book-gallery' ), 'error' );
			return;
		}

		// Get current settings
		$settings = get_option( 'brag_book_gallery_sync_settings', [] );

		// Get form data
		$form_data = isset( $_POST['brag_book_gallery_sync_settings'] ) ? $_POST['brag_book_gallery_sync_settings'] : [];

		// Sanitize and save auto sync enabled
		$auto_enabled = ! empty( $form_data['auto_sync_enabled'] ) && '1' === $form_data['auto_sync_enabled'];
		$settings['auto_sync_enabled'] = $auto_enabled;

		// Sanitize and save sync day (0-6)
		if ( isset( $form_data['sync_day'] ) ) {
			$sync_day = absint( $form_data['sync_day'] );
			if ( $sync_day >= 0 && $sync_day <= 6 ) {
				$settings['sync_day'] = (string) $sync_day;
			}
		}

		// Sanitize and save sync time (HH:MM format)
		if ( isset( $form_data['sync_time'] ) ) {
			$sync_time = sanitize_text_field( $form_data['sync_time'] );
			if ( preg_match( '/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $sync_time ) ) {
				$settings['sync_time'] = $sync_time;
			}
		}

		// Save settings
		update_option( 'brag_book_gallery_sync_settings', $settings );

		// Handle scheduling based on enabled state
		if ( $auto_enabled ) {
			// Calculate the next scheduled sync time and register with BRAG book API
			$schedule_result = $this->calculate_next_sync_time();
			if ( $schedule_result ) {
				$next_sync_datetime = wp_date( 'l, F j, Y \a\t g:i A', $schedule_result['timestamp'] );

				// Register the scheduled sync with BRAG book API (only if no active job)
				$sync_api = new Sync_Api();
				if ( ! $sync_api->has_active_job() ) {
					$registration_result = $sync_api->register_sync(
						Sync_Api::SYNC_TYPE_AUTO,
						$schedule_result['iso_datetime']
					);

					if ( is_wp_error( $registration_result ) ) {
						error_log( 'BRAG book Gallery: Failed to register scheduled sync with API: ' . $registration_result->get_error_message() );
						$this->add_notice(
							__( 'Automatic sync settings saved, but failed to register with BRAG book API.', 'brag-book-gallery' ),
							'warning'
						);
					} else {
						error_log( 'BRAG book Gallery: Scheduled sync registered with API for ' . $schedule_result['iso_datetime'] );
						$this->add_notice(
							sprintf(
								/* translators: %s: formatted date/time of next scheduled sync */
								__( 'Automatic sync enabled. Next sync scheduled for %s.', 'brag-book-gallery' ),
								$next_sync_datetime
							),
							'success'
						);
					}
				} else {
					error_log( 'BRAG book Gallery: Skipped API registration - active job exists. Will register when current job completes.' );
					$this->add_notice(
						sprintf(
							/* translators: %s: formatted date/time of next scheduled sync */
							__( 'Automatic sync settings saved. Next sync will be scheduled for %s after current sync completes.', 'brag-book-gallery' ),
							$next_sync_datetime
						),
						'success'
					);
				}

				// Store the scheduled time for UI display
				update_option( 'brag_book_gallery_next_scheduled_sync', [
					'timestamp'    => $schedule_result['timestamp'],
					'datetime'     => wp_date( 'Y-m-d H:i:s', $schedule_result['timestamp'] ),
					'iso_datetime' => $schedule_result['iso_datetime'],
					'sync_day'     => $settings['sync_day'] ?? '0',
					'sync_time'    => $settings['sync_time'] ?? '02:00',
					'scheduled_at' => current_time( 'mysql' ),
				] );
			} else {
				$this->add_notice( __( 'Failed to calculate automatic sync schedule. Please try again.', 'brag-book-gallery' ), 'error' );
			}
		} else {
			delete_option( 'brag_book_gallery_next_scheduled_sync' );
			$this->add_notice( __( 'Automatic sync disabled.', 'brag-book-gallery' ), 'success' );
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
			'last_sync_time' => '',
			'sync_status'    => 'never',
			'sync_mode'      => 'wp_engine', // Default to WP Engine optimized
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
	 * Handle sync execution triggered by REST API
	 *
	 * This method executes the sync process when triggered externally via REST API.
	 * Registers as AUTO sync type and schedules the next weekly sync after completion.
	 *
	 * @since 3.3.0
	 * @since 4.3.0 Refactored to use shared execute_full_sync method and schedule weekly sync
	 * @return void
	 */
	public function handle_rest_sync_execution(): void {
		error_log( 'BRAG book Gallery: ========== REST API SYNC EXECUTION STARTED ==========' );
		error_log( 'BRAG book Gallery: Sync triggered via REST API' );

		$sync_source = 'rest_api';

		// Store active sync information for UI display
		update_option( 'brag_book_gallery_active_sync', [
			'source'     => $sync_source,
			'started_at' => current_time( 'mysql' ),
			'status'     => 'in_progress',
			'message'    => 'Sync triggered via REST API',
			'triggered_by' => 'rest_api',
		] );

		// Initialize Sync API for reporting
		// Note: The sync job was already registered in handle_rest_trigger_sync()
		$sync_api = new Sync_Api();

		// Report sync as IN_PROGRESS (updates the existing job registered by handle_rest_trigger_sync)
		$sync_api->report_sync(
			Sync_Api::STATUS_IN_PROGRESS,
			0,
			'Starting sync via REST API'
		);

		// Execute the sync using shared method
		$this->execute_full_sync( $sync_source, $sync_api );

		// After sync completes, register the NEXT scheduled sync if auto sync is enabled
		$settings = get_option( 'brag_book_gallery_sync_settings', [] );
		if ( ! empty( $settings['auto_sync_enabled'] ) ) {
			$next_sync = $this->calculate_next_sync_time();
			if ( $next_sync ) {
				error_log( 'BRAG book Gallery: Next automatic sync calculated for ' . wp_date( 'Y-m-d H:i:s', $next_sync['timestamp'] ) );

				// Register the NEXT scheduled sync with BRAG book API using scheduledTime
				$registration_result = $sync_api->register_sync(
					Sync_Api::SYNC_TYPE_AUTO,
					$next_sync['iso_datetime']
				);

				if ( is_wp_error( $registration_result ) ) {
					error_log( 'BRAG book Gallery: Failed to register next scheduled sync with API: ' . $registration_result->get_error_message() );
				} else {
					error_log( 'BRAG book Gallery: Next scheduled sync registered with API for ' . $next_sync['iso_datetime'] );

					// Update the stored schedule info
					update_option( 'brag_book_gallery_next_scheduled_sync', [
						'timestamp'    => $next_sync['timestamp'],
						'datetime'     => wp_date( 'Y-m-d H:i:s', $next_sync['timestamp'] ),
						'iso_datetime' => $next_sync['iso_datetime'],
						'sync_day'     => $settings['sync_day'] ?? '0',
						'sync_time'    => $settings['sync_time'] ?? '02:00',
						'scheduled_at' => current_time( 'mysql' ),
					] );
				}
			}
		}

		error_log( 'BRAG book Gallery: ========== REST API SYNC EXECUTION COMPLETED ==========' );
	}

	/**
	 * Legacy sync execution handler (kept for backwards compatibility)
	 *
	 * This method contains the original sync execution logic. It is kept for reference
	 * but the actual execution now uses execute_full_sync().
	 *
	 * @since 3.3.0
	 * @deprecated 4.3.0 Use execute_full_sync() instead
	 * @return void
	 */
	private function handle_rest_sync_execution_legacy(): void {
		$settings = $this->get_settings();
		$sync_success = false;
		$sync_source = 'rest_api';

		// Initialize Sync API for registration and reporting
		$sync_api = new Sync_Api();

		// Get database instance for logging
		$setup = \BRAGBookGallery\Includes\Core\Setup::get_instance();
		$database = $setup->get_service( 'database' );
		$log_id = null;

		// Create initial log entry
		if ( $database ) {
			$log_id = $database->log_sync_operation( 'full', 'started', 0, 0, '', $sync_source );
			error_log( 'BRAG book Gallery: Sync log created with ID: ' . $log_id );
		}

		try {
			error_log( 'BRAG book Gallery: Starting REST-triggered sync using Stage-Based Sync' );

			// Use the new stage-based sync
			$sync = new \BRAGBookGallery\Includes\Sync\Chunked_Data_Sync();

			// Run Stage 1: Fetch procedures
			error_log( 'BRAG book Gallery: Running Stage 1 - Fetching procedures' );
			$stage1_result = $sync->execute_stage_1();

			if ( ! $stage1_result['success'] ) {
				error_log( 'BRAG book Gallery: Stage 1 failed: ' . ($stage1_result['message'] ?? 'Unknown error') );

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

			error_log( 'BRAG book Gallery: Stage 1 completed - ' .
				($stage1_result['procedures_created'] ?? 0) . ' created, ' .
				($stage1_result['procedures_updated'] ?? 0) . ' updated' );

			// Run Stage 2: Build manifest
			error_log( 'BRAG book Gallery: Running Stage 2 - Building manifest' );
			$stage2_result = $sync->execute_stage_2();

			if ( ! $stage2_result['success'] ) {
				error_log( 'BRAG book Gallery: Stage 2 failed: ' . ($stage2_result['message'] ?? 'Unknown error') );

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

			error_log( 'BRAG book Gallery: Stage 2 completed - ' .
				($stage2_result['procedure_count'] ?? 0) . ' procedures, ' .
				($stage2_result['case_count'] ?? 0) . ' cases in manifest' );

			// Run Stage 3: Process cases in batches
			error_log( 'BRAG book Gallery: Running Stage 3 - Processing cases in batches' );

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
				error_log( "BRAG book Gallery: Stage 3 - Processing batch {$batch_count}" );

				$stage3_result = $sync->execute_stage_3();

				if ( ! $stage3_result['success'] ) {
					error_log( 'BRAG book Gallery: Stage 3 failed: ' . ($stage3_result['message'] ?? 'Unknown error') );

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

				error_log( "BRAG book Gallery: Stage 3 batch {$batch_count} - Progress: {$total_processed}/{$total_cases} cases ({$total_created} created, {$total_updated} updated, {$total_failed} failed)" );

				// Check if we need to continue
				if ( ! $needs_continue ) {
					error_log( 'BRAG book Gallery: Stage 3 completed - All cases processed' );
					$sync_success = true;
					break;
				}

				// Brief pause between batches to avoid overwhelming the server
				usleep( 500000 ); // 0.5 second pause
			}

			if ( $batch_count >= $max_batches ) {
				error_log( "BRAG book Gallery: Stage 3 stopped - Reached maximum batch limit ({$max_batches})" );
			}

			error_log( 'BRAG book Gallery: Stage 3 final totals - ' .
				"{$total_created} created, {$total_updated} updated, {$total_failed} failed ({$total_processed}/{$total_cases} cases)" );

			error_log( 'BRAG book Gallery: 🎉 Sync completed successfully' );

			// Update sync log with completion
			if ( $database && $log_id ) {
				$status = $sync_success ? 'completed' : 'partial';
				$database->update_sync_log( $log_id, $status, $total_processed, $total_failed );
				error_log( 'BRAG book Gallery: Sync log updated - Status: ' . $status . ', Processed: ' . $total_processed . ', Failed: ' . $total_failed );
			}

			// Update sync settings with success status
			$settings['last_sync_time'] = current_time( 'mysql' );
			$settings['sync_status']    = $sync_success ? 'success' : 'partial';
			$update_result              = $this->update_settings( $settings );

			// Also update separate options for REST API access
			update_option( 'brag_book_gallery_last_sync_time', $settings['last_sync_time'] );
			update_option( 'brag_book_gallery_last_sync_status', $settings['sync_status'] );

			if ( ! $update_result ) {
				error_log( 'BRAG book Gallery: Failed to update sync settings after REST sync' );
			} else {
				error_log( 'BRAG book Gallery: Successfully updated sync settings - Status: ' . $settings['sync_status'] . ', Time: ' . $settings['last_sync_time'] );
			}

			// Report sync completion to BRAG book API
			$cases_synced = $total_created + $total_updated;
			$report_status = $sync_success ? Sync_Api::STATUS_SUCCESS : Sync_Api::STATUS_PARTIAL;
			$status_message = sprintf(
				'Sync completed: %d cases processed (%d created, %d updated, %d failed)',
				$total_processed,
				$total_created,
				$total_updated,
				$total_failed
			);

			$sync_api->report_sync(
				$report_status,
				$cases_synced,
				$status_message,
				$total_failed > 0 ? "Failed cases: {$total_failed}" : ''
			);

		} catch ( \Exception $e ) {
			$error_message = 'Sync failed: ' . $e->getMessage();
			error_log( 'BRAG book Gallery: ❌ ' . $error_message );

			// Report failure to BRAG book API
			$sync_api->report_sync(
				Sync_Api::STATUS_FAILED,
				0,
				'Sync failed with exception',
				$e->getMessage()
			);

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
			error_log( 'BRAG book Gallery: BRAGbook API token not configured' );
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
			error_log( 'BRAG book Gallery: Invalid BRAGbook API token provided to REST API' );
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
		error_log( 'BRAG book Gallery: Sync triggered via REST API' );

		// Register sync with BRAG book API before scheduling
		$sync_api = new Sync_Api();
		$registration_result = $sync_api->register_sync( Sync_Api::SYNC_TYPE_MANUAL );

		$job_id = null;
		if ( ! is_wp_error( $registration_result ) && isset( $registration_result['job_id'] ) ) {
			$job_id = $registration_result['job_id'];
			error_log( 'BRAG book Gallery: Sync registered with BRAG book API - Job ID: ' . $job_id );
		} else {
			$error_message = is_wp_error( $registration_result ) ? $registration_result->get_error_message() : 'Unknown error';
			error_log( 'BRAG book Gallery: Failed to register sync with BRAG book API: ' . $error_message );
			// Continue anyway - graceful degradation
		}

		// Schedule the sync to run asynchronously in the background
		// This ensures the REST API response returns immediately
		wp_schedule_single_event( time(), 'brag_book_gallery_rest_sync' );

		$response = [
			'success' => true,
			'message' => __( 'Sync has been triggered and will run in the background', 'brag-book-gallery' ),
		];

		if ( $job_id ) {
			$response['job_id'] = $job_id;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Handle REST API get update request
	 *
	 * Returns website URL, plugin details, and last sync information.
	 * Also accepts sync_day (0-6) and sync_time (HH:MM) parameters to configure weekly sync schedule.
	 *
	 * @since 3.3.0
	 * @since 4.3.0 Added sync_day and sync_time parameters for weekly scheduling
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
		$last_sync_time   = get_option( 'brag_book_gallery_last_sync_time', null );
		$last_sync_status = get_option( 'brag_book_gallery_last_sync_status', 'unknown' );

		// Check for sync schedule configuration parameters
		$sync_day  = $request->get_param( 'sync_day' );
		$sync_time = $request->get_param( 'sync_time' );

		$schedule_updated = false;
		$next_sync_time   = null;

		// Update sync schedule if parameters provided
		if ( null !== $sync_day || null !== $sync_time ) {
			$settings = get_option( 'brag_book_gallery_sync_settings', [] );

			// Update sync day (0-6, Sunday to Saturday)
			if ( null !== $sync_day ) {
				$sync_day = absint( $sync_day );
				if ( $sync_day >= 0 && $sync_day <= 6 ) {
					$settings['sync_day'] = (string) $sync_day;
					error_log( 'BRAG book Gallery: Updated sync_day to ' . $sync_day . ' via REST API' );
				}
			}

			// Update sync time (HH:MM format)
			if ( null !== $sync_time ) {
				$sync_time = sanitize_text_field( $sync_time );
				if ( preg_match( '/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $sync_time ) ) {
					$settings['sync_time'] = $sync_time;
					error_log( 'BRAG book Gallery: Updated sync_time to ' . $sync_time . ' via REST API' );
				}
			}

			// Enable auto sync if not already enabled
			$settings['auto_sync_enabled'] = true;

			update_option( 'brag_book_gallery_sync_settings', $settings );
			$schedule_updated = true;

			// Calculate the next sync time
			$next_sync_result = $this->calculate_next_sync_time();

			// Register the scheduled sync with BRAG book API using scheduledTime (only if no active job)
			if ( $next_sync_result ) {
				$sync_api = new Sync_Api();
				if ( ! $sync_api->has_active_job() ) {
					$registration_result = $sync_api->register_sync(
						Sync_Api::SYNC_TYPE_AUTO,
						$next_sync_result['iso_datetime']
					);

					if ( is_wp_error( $registration_result ) ) {
						error_log( 'BRAG book Gallery: Failed to register scheduled sync via REST API: ' . $registration_result->get_error_message() );
					} else {
						error_log( 'BRAG book Gallery: Scheduled sync registered with API for ' . $next_sync_result['iso_datetime'] . ' via REST API' );
					}
				} else {
					error_log( 'BRAG book Gallery: Skipped API registration - active job exists. Schedule will be registered after current sync completes.' );
				}

				// Store the scheduled time for UI display
				update_option( 'brag_book_gallery_next_scheduled_sync', [
					'timestamp'    => $next_sync_result['timestamp'],
					'datetime'     => wp_date( 'Y-m-d H:i:s', $next_sync_result['timestamp'] ),
					'iso_datetime' => $next_sync_result['iso_datetime'],
					'sync_day'     => $settings['sync_day'] ?? '0',
					'sync_time'    => $settings['sync_time'] ?? '02:00',
					'scheduled_at' => current_time( 'mysql' ),
				] );
			}
		}

		// Get current schedule information
		$settings           = get_option( 'brag_book_gallery_sync_settings', [] );
		$next_scheduled_opt = get_option( 'brag_book_gallery_next_scheduled_sync', [] );

		// Build response
		$response = [
			'success'     => true,
			'website_url' => home_url(),
			'plugin'      => [
				'version'      => $plugin_data['Version'] ?? null,
				'name'         => $plugin_data['Name'] ?? 'BRAG book Gallery',
				'last_updated' => $plugin_data['Version'] ? filemtime( $plugin_file ) : null,
			],
			'last_sync'   => [
				'time'   => $last_sync_time,
				'status' => $last_sync_status,
			],
			'schedule'    => [
				'enabled'        => ! empty( $settings['auto_sync_enabled'] ),
				'sync_day'       => $settings['sync_day'] ?? '0',
				'sync_time'      => $settings['sync_time'] ?? '02:00',
				'next_scheduled' => $next_scheduled_opt['iso_datetime'] ?? null,
			],
		];

		if ( $schedule_updated ) {
			$response['schedule_updated'] = true;
			if ( isset( $next_sync_result ) && $next_sync_result ) {
				$response['schedule']['next_scheduled'] = $next_sync_result['iso_datetime'];
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Handle AJAX request to get BRAG book sync status
	 *
	 * Returns the current sync job status and last report data from BRAG book API.
	 *
	 * @since 4.0.2
	 * @return void
	 */
	public function handle_get_bragbook_sync_status(): void {
		// Check nonce and permissions
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		try {
			$sync_api = new Sync_Api();

			$current_job = $sync_api->get_current_job();
			$last_report = $sync_api->get_last_report();
			$has_active_job = $sync_api->has_active_job();

			wp_send_json_success( [
				'has_active_job' => $has_active_job,
				'current_job'    => $current_job,
				'last_report'    => $last_report,
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => 'Failed to get sync status: ' . $e->getMessage(),
			] );
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
			error_log( 'BRAG book Gallery: Could not clean up log records - column names not found' );
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

		// Return true if option was updated OR if it already had the same value
		return $result || get_option( $this->page_config['option_name'] ) === $settings;
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

	/**
	 * Calculate the next sync time based on settings
	 *
	 * Calculates the next occurrence of the configured day/time (7 days from now minimum).
	 * This is used to communicate the schedule to the BRAGBook API, which handles the actual triggering.
	 *
	 * @since 4.3.0
	 *
	 * @return array{timestamp: int, iso_datetime: string}|false Array with timestamp and ISO 8601 datetime, or false on failure
	 */
	public function calculate_next_sync_time() {
		$settings = get_option( 'brag_book_gallery_sync_settings', [] );

		// Get configured day and time (defaults: Sunday at 2:00 AM)
		$sync_day  = isset( $settings['sync_day'] ) ? absint( $settings['sync_day'] ) : 0;
		$sync_time = $settings['sync_time'] ?? '02:00';

		// Parse the time
		$time_parts = explode( ':', $sync_time );
		$sync_hour  = isset( $time_parts[0] ) ? absint( $time_parts[0] ) : 2;
		$sync_min   = isset( $time_parts[1] ) ? absint( $time_parts[1] ) : 0;

		// Get the WordPress timezone
		$timezone = wp_timezone();

		// Calculate the next occurrence of the specified day/time
		$now = new \DateTime( 'now', $timezone );

		// Find the next occurrence of the specified day
		$next_sync = new \DateTime( 'now', $timezone );
		$next_sync->setTime( $sync_hour, $sync_min, 0 );

		// Calculate days until next occurrence of sync_day
		$current_day = (int) $now->format( 'w' ); // 0 = Sunday, 6 = Saturday
		$days_until  = ( $sync_day - $current_day + 7 ) % 7;

		// If it's the same day (days_until = 0), check if the time has passed
		if ( $days_until === 0 ) {
			if ( $now >= $next_sync ) {
				// Time has passed today, schedule for next week
				$days_until = 7;
			}
			// else: time is later today, schedule for today (days_until stays 0)
		}

		$next_sync->modify( "+{$days_until} days" );

		$timestamp = $next_sync->getTimestamp();

		// Convert to UTC for API - use ISO 8601 format with Z suffix
		$utc_datetime = new \DateTime( '@' . $timestamp, new \DateTimeZone( 'UTC' ) );
		$iso_datetime = $utc_datetime->format( 'Y-m-d\TH:i:s\Z' ); // ISO 8601 UTC format

		error_log( 'BRAG book Gallery: Calculated next sync time: ' . wp_date( 'Y-m-d H:i:s', $timestamp ) . ' (in ' . $days_until . ' days)' );
		error_log( 'BRAG book Gallery: ISO 8601 datetime for API (UTC): ' . $iso_datetime );

		return [
			'timestamp'    => $timestamp,
			'iso_datetime' => $iso_datetime,
		];
	}

	/**
	 * Schedule the next weekly sync (legacy wrapper)
	 *
	 * This method is kept for backward compatibility but now delegates to calculate_next_sync_time().
	 * The BRAGBook API handles the actual sync triggering.
	 *
	 * @since 4.3.0
	 * @deprecated 4.3.0 Use calculate_next_sync_time() instead
	 *
	 * @return array{timestamp: int, iso_datetime: string}|false Array with timestamp and ISO 8601 datetime, or false on failure
	 */
	public function schedule_next_weekly_sync() {
		$settings = get_option( 'brag_book_gallery_sync_settings', [] );

		// Check if auto sync is enabled
		if ( empty( $settings['auto_sync_enabled'] ) ) {
			error_log( 'BRAG book Gallery: Auto sync not enabled, skipping sync scheduling' );
			return false;
		}

		return $this->calculate_next_sync_time();
	}

	/**
	 * Handle automatic sync execution
	 *
	 * This method executes the sync process when triggered by the weekly cron schedule.
	 * Registers as AUTO sync type and communicates that it was an automatic sync.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function handle_automatic_sync_execution(): void {
		error_log( 'BRAG book Gallery: ========== AUTOMATIC SYNC EXECUTION STARTED ==========' );
		error_log( 'BRAG book Gallery: Sync triggered by weekly cron schedule' );

		$sync_source = 'automatic';

		// Store active sync information for UI display
		update_option( 'brag_book_gallery_active_sync', [
			'source'     => $sync_source,
			'started_at' => current_time( 'mysql' ),
			'status'     => 'in_progress',
			'message'    => 'Automatic weekly sync in progress',
		] );

		// Initialize Sync API for registration and reporting
		$sync_api = new Sync_Api();

		// Check if there's already an active job (registered when scheduled)
		// If so, just report IN_PROGRESS. If not, register a new job.
		if ( ! $sync_api->has_active_job() ) {
			// No existing job - register a new one
			$registration_result = $sync_api->register_sync( Sync_Api::SYNC_TYPE_AUTO );

			if ( ! is_wp_error( $registration_result ) && isset( $registration_result['job_id'] ) ) {
				error_log( 'BRAG book Gallery: Auto sync registered with BRAG book API - Job ID: ' . $registration_result['job_id'] );
			} else {
				$error_message = is_wp_error( $registration_result ) ? $registration_result->get_error_message() : 'Unknown error';
				error_log( 'BRAG book Gallery: Failed to register auto sync: ' . $error_message );
			}
		} else {
			error_log( 'BRAG book Gallery: Using existing registered sync job (was scheduled in advance)' );
		}

		// Report sync as IN_PROGRESS (updates existing job status)
		$sync_api->report_sync(
			Sync_Api::STATUS_IN_PROGRESS,
			0,
			'Starting automatic weekly sync'
		);

		// Execute the sync
		$this->execute_full_sync( $sync_source, $sync_api );

		// Schedule the next weekly sync after completion
		// Note: The job registration for the NEXT sync happens after execute_full_sync
		// reports SUCCESS/PARTIAL, which clears the current job
		$next_sync = $this->schedule_next_weekly_sync();
		if ( $next_sync ) {
			error_log( 'BRAG book Gallery: Next automatic sync scheduled for ' . wp_date( 'Y-m-d H:i:s', $next_sync['timestamp'] ) );

			// Register the NEXT scheduled sync with BRAGBook API using scheduledTime
			// This works because execute_full_sync already reported completion and cleared the job
			$registration_result = $sync_api->register_sync(
				Sync_Api::SYNC_TYPE_AUTO,
				$next_sync['iso_datetime']
			);

			if ( is_wp_error( $registration_result ) ) {
				error_log( 'BRAG book Gallery: Failed to register next scheduled sync with API: ' . $registration_result->get_error_message() );
			} else {
				error_log( 'BRAG book Gallery: Next scheduled sync registered with API for ' . $next_sync['iso_datetime'] );
			}
		}

		error_log( 'BRAG book Gallery: ========== AUTOMATIC SYNC EXECUTION COMPLETED ==========' );
	}

	/**
	 * Execute full sync operation
	 *
	 * Shared sync execution logic used by both REST API and automatic triggers.
	 *
	 * @since 4.3.0
	 *
	 * @param string   $sync_source Source of the sync (automatic, rest_api, manual)
	 * @param Sync_Api $sync_api    Sync API instance for reporting
	 * @return void
	 */
	private function execute_full_sync( string $sync_source, Sync_Api $sync_api ): void {
		$settings     = $this->get_settings();
		$sync_success = false;

		// Get database instance for logging
		$setup    = \BRAGBookGallery\Includes\Core\Setup::get_instance();
		$database = $setup->get_service( 'database' );
		$log_id   = null;

		// Create initial log entry
		if ( $database ) {
			$log_id = $database->log_sync_operation( 'full', 'started', 0, 0, '', $sync_source );
			error_log( 'BRAG book Gallery: Sync log created with ID: ' . $log_id . ' (source: ' . $sync_source . ')' );
		}

		try {
			error_log( 'BRAG book Gallery: Starting sync via ' . $sync_source );

			// Use the stage-based sync
			$sync = new \BRAGBookGallery\Includes\Sync\Chunked_Data_Sync();

			// Run Stage 1: Fetch procedures
			error_log( 'BRAG book Gallery: Running Stage 1 - Fetching procedures' );
			$stage1_result = $sync->execute_stage_1();

			if ( ! $stage1_result['success'] ) {
				$this->handle_sync_stage_failure( $stage1_result, 'Stage 1', $database, $log_id, $settings, $sync_api, $sync_source );
				return;
			}

			error_log( 'BRAG book Gallery: Stage 1 completed' );

			// Run Stage 2: Build manifest
			error_log( 'BRAG book Gallery: Running Stage 2 - Building manifest' );
			$stage2_result = $sync->execute_stage_2();

			if ( ! $stage2_result['success'] ) {
				$this->handle_sync_stage_failure( $stage2_result, 'Stage 2', $database, $log_id, $settings, $sync_api, $sync_source );
				return;
			}

			error_log( 'BRAG book Gallery: Stage 2 completed' );

			// Run Stage 3: Process cases in batches
			error_log( 'BRAG book Gallery: Running Stage 3 - Processing cases' );

			$total_created   = 0;
			$total_updated   = 0;
			$total_failed    = 0;
			$total_processed = 0;
			$total_cases     = 0;
			$needs_continue  = true;
			$batch_count     = 0;
			$max_batches     = 1000;

			while ( $needs_continue && $batch_count < $max_batches ) {
				$batch_count++;
				$stage3_result = $sync->execute_stage_3();

				if ( ! $stage3_result['success'] ) {
					if ( $database && $log_id ) {
						$database->update_sync_log( $log_id, 'failed', $total_processed, $total_failed, $stage3_result['message'] ?? 'Stage 3 failed' );
					}
					break;
				}

				$total_created   = $stage3_result['created_posts'] ?? 0;
				$total_updated   = $stage3_result['updated_posts'] ?? 0;
				$total_failed    = $stage3_result['failed_cases'] ?? 0;
				$total_processed = $stage3_result['processed_cases'] ?? 0;
				$total_cases     = $stage3_result['total_cases'] ?? 0;
				$needs_continue  = $stage3_result['needs_continue'] ?? false;

				if ( ! $needs_continue ) {
					$sync_success = true;
					break;
				}

				usleep( 500000 ); // 0.5 second pause
			}

			error_log( 'BRAG book Gallery: Stage 3 final - ' . $total_created . ' created, ' . $total_updated . ' updated, ' . $total_failed . ' failed' );

			// Update sync log with completion
			if ( $database && $log_id ) {
				$status = $sync_success ? 'completed' : 'partial';
				$database->update_sync_log( $log_id, $status, $total_processed, $total_failed );
			}

			// Update sync settings
			$settings['last_sync_time'] = current_time( 'mysql' );
			$settings['sync_status']    = $sync_success ? 'success' : 'partial';
			$this->update_settings( $settings );

			update_option( 'brag_book_gallery_last_sync_time', $settings['last_sync_time'] );
			update_option( 'brag_book_gallery_last_sync_status', $settings['sync_status'] );
			update_option( 'brag_book_gallery_last_sync_source', $sync_source );

			// Update active sync status
			update_option( 'brag_book_gallery_active_sync', [
				'source'       => $sync_source,
				'started_at'   => get_option( 'brag_book_gallery_active_sync' )['started_at'] ?? current_time( 'mysql' ),
				'completed_at' => current_time( 'mysql' ),
				'status'       => $sync_success ? 'completed' : 'partial',
				'message'      => $sync_source === 'automatic'
					? 'Automatic weekly sync completed successfully'
					: 'Sync completed via ' . $sync_source,
				'cases_synced' => $total_created + $total_updated,
			] );

			// Report sync completion to BRAG book API
			$cases_synced   = $total_created + $total_updated;
			$report_status  = $sync_success ? Sync_Api::STATUS_SUCCESS : Sync_Api::STATUS_PARTIAL;
			$status_message = sprintf(
				'%s sync completed: %d cases processed (%d created, %d updated, %d failed)',
				ucfirst( $sync_source ),
				$total_processed,
				$total_created,
				$total_updated,
				$total_failed
			);

			$sync_api->report_sync(
				$report_status,
				$cases_synced,
				$status_message,
				$total_failed > 0 ? "Failed cases: {$total_failed}" : ''
			);

			error_log( 'BRAG book Gallery: ' . ucfirst( $sync_source ) . ' sync completed successfully' );

		} catch ( \Exception $e ) {
			$error_message = 'Sync failed: ' . $e->getMessage();
			error_log( 'BRAG book Gallery: ' . $error_message );

			$sync_api->report_sync(
				Sync_Api::STATUS_FAILED,
				0,
				ucfirst( $sync_source ) . ' sync failed with exception',
				$e->getMessage()
			);

			if ( $database && $log_id ) {
				$database->update_sync_log( $log_id, 'failed', 0, 0, $error_message );
			}

			$settings['last_sync_time'] = current_time( 'mysql' );
			$settings['sync_status']    = 'error';
			$this->update_settings( $settings );

			update_option( 'brag_book_gallery_last_sync_time', $settings['last_sync_time'] );
			update_option( 'brag_book_gallery_last_sync_status', 'error' );
			update_option( 'brag_book_gallery_last_sync_source', $sync_source );

			// Update active sync with error
			update_option( 'brag_book_gallery_active_sync', [
				'source'       => $sync_source,
				'completed_at' => current_time( 'mysql' ),
				'status'       => 'failed',
				'message'      => $error_message,
			] );
		}
	}

	/**
	 * Handle sync stage failure
	 *
	 * Updates logs and options when a sync stage fails.
	 *
	 * @since 4.3.0
	 *
	 * @param array    $result      Stage result array
	 * @param string   $stage_name  Name of the failed stage
	 * @param mixed    $database    Database service instance
	 * @param int|null $log_id      Sync log ID
	 * @param array    $settings    Current settings
	 * @param Sync_Api $sync_api    Sync API instance
	 * @param string   $sync_source Source of the sync
	 * @return void
	 */
	private function handle_sync_stage_failure( array $result, string $stage_name, $database, ?int $log_id, array $settings, Sync_Api $sync_api, string $sync_source ): void {
		$error_message = $result['message'] ?? $stage_name . ' failed';
		error_log( 'BRAG book Gallery: ' . $stage_name . ' failed: ' . $error_message );

		if ( $database && $log_id ) {
			$database->update_sync_log( $log_id, 'failed', 0, 0, $error_message );
		}

		$settings['last_sync_time'] = current_time( 'mysql' );
		$settings['sync_status']    = 'error';
		$this->update_settings( $settings );

		update_option( 'brag_book_gallery_last_sync_time', $settings['last_sync_time'] );
		update_option( 'brag_book_gallery_last_sync_status', 'error' );
		update_option( 'brag_book_gallery_last_sync_source', $sync_source );

		// Report failure to BRAG book API
		$sync_api->report_sync(
			Sync_Api::STATUS_FAILED,
			0,
			ucfirst( $sync_source ) . ' sync failed at ' . $stage_name,
			$error_message
		);

		// Update active sync with error
		update_option( 'brag_book_gallery_active_sync', [
			'source'       => $sync_source,
			'completed_at' => current_time( 'mysql' ),
			'status'       => 'failed',
			'message'      => $error_message,
		] );
	}
}
