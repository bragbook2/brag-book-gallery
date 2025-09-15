<?php
/**
 * Sync Settings Page Class
 *
 * Handles the sync settings page for procedure synchronization from the BRAGBook API.
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
use BRAGBookGallery\Includes\Sync\Procedure_Sync;
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
		add_action( 'wp_ajax_brag_book_clear_sync_log', [ $this, 'handle_clear_sync_log' ] );
		add_action( 'wp_ajax_brag_book_delete_sync_record', [ $this, 'handle_delete_sync_record' ] );
		add_action( 'wp_ajax_brag_book_validate_procedure_assignments', [ $this, 'handle_validate_procedure_assignments' ] );

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
				<h2><?php esc_html_e( 'Sync from BRAGBook API', 'brag-book-gallery' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Synchronize procedures and cases from the BRAGBook API. This process may take several minutes depending on the amount of data.', 'brag-book-gallery' ); ?>
				</p>

				<?php $this->render_manual_sync_section(); ?>
			</div>

			<!-- Auto Sync Settings Section -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Automatic Sync Settings', 'brag-book-gallery' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Configure automatic synchronization of procedures from the BRAGBook API.', 'brag-book-gallery' ); ?>
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
					<tr>
						<th scope="row">
							<label for="skip_image_downloads"><?php esc_html_e( 'Performance Mode', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<?php $this->render_performance_mode_field(); ?>
						</td>
					</tr>
				</table>
			</div>

			<!-- Sync History Section -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Full Sync History', 'brag-book-gallery' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'View recent sync operations and their results. The first sync will show procedures created, while subsequent syncs will show procedures updated as they already exist.', 'brag-book-gallery' ); ?>
				</p>

				<?php $this->render_sync_history_section(); ?>
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
			$allowed_frequencies = [ 'hourly', 'daily', 'weekly', 'monthly' ];
			$frequency = sanitize_text_field( $_POST[ $this->page_config['option_name'] ]['sync_frequency'] );
			if ( in_array( $frequency, $allowed_frequencies, true ) ) {
				$new_settings['sync_frequency'] = $frequency;
			}
		}

		// Update performance mode setting (stored separately for global access)
		$skip_image_downloads = isset( $_POST['brag_book_gallery_skip_image_downloads'] ) && '1' === $_POST['brag_book_gallery_skip_image_downloads'];
		update_option( 'brag_book_gallery_skip_image_downloads', $skip_image_downloads );

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
			$last_sync = wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $latest_sync_from_history->created_at )
			);
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
								<?php esc_html_e( 'No sync has been performed yet. Click "Start Full Sync" to synchronize your procedures and cases from the BRAGBook API.', 'brag-book-gallery' ); ?>
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
							<?php esc_html_e( 'Start Full Sync', 'brag-book-gallery' ); ?>
						</button>
						<button type="button" id="test-automatic-sync-btn" class="button button-secondary">
							<?php esc_html_e( 'Test Automatic Sync', 'brag-book-gallery' ); ?>
						</button>
						<button type="button" id="test-database-log-btn" class="button button-secondary">
							<?php esc_html_e( 'Test Database Logging', 'brag-book-gallery' ); ?>
						</button>
						<button type="button" id="cleanup-empty-logs-btn" class="button button-secondary">
							<?php esc_html_e( 'Clean Up Empty Records', 'brag-book-gallery' ); ?>
						</button>
						<button type="button" id="stop-sync-btn" class="button button-secondary" style="display: none;">
							<?php esc_html_e( 'Stop Sync', 'brag-book-gallery' ); ?>
						</button>
						<button type="button" id="clear-sync-log-btn" class="button button-secondary">
							<?php esc_html_e( 'Clear Sync Log', 'brag-book-gallery' ); ?>
						</button>
						<button type="button" id="validate-procedures-btn" class="button button-secondary">
							<?php esc_html_e( 'Validate Procedures', 'brag-book-gallery' ); ?>
						</button>
					</div>
					<p class="description">
						<?php esc_html_e( 'Start Full Sync to synchronize all procedures and cases from the BRAGBook API. This process will fetch fresh sidebar data, create/update procedure taxonomies, then process all available cases from each procedure. No artificial limits are applied - all API data will be synchronized. You can stop the sync at any time.', 'brag-book-gallery' ); ?>
						<br><br>
						<?php esc_html_e( 'Use "Test Automatic Sync" to simulate an automatic sync (marked as "Automatic" in history) without waiting for the scheduled cron job. This helps test that automatic syncing works correctly.', 'brag-book-gallery' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<!-- Progress Section (Hidden by default) -->
		<div id="sync-progress" class="brag-book-gallery-section" style="display:none;">
			<h3><?php esc_html_e( 'Sync Progress', 'brag-book-gallery' ); ?></h3>
			<div class="brag-book-gallery-progress-container">
				<!-- Main Progress Bar (Overall) -->
				<div class="brag-book-gallery-progress-header">
					<span class="brag-book-gallery-progress-label"><?php esc_html_e( 'Overall Progress', 'brag-book-gallery' ); ?></span>
					<span id="sync-overall-percentage" class="brag-book-gallery-progress-percentage">0%</span>
				</div>
				<div class="brag-book-gallery-progress-bar brag-book-gallery-progress-bar--main">
					<div id="sync-overall-fill" class="brag-book-gallery-progress-fill" style="width: 0%;"></div>
				</div>

				<!-- Secondary Progress Bar (Current Operation) -->
				<div class="brag-book-gallery-progress-header brag-book-gallery-progress-header--secondary">
					<span id="sync-current-operation" class="brag-book-gallery-progress-label"><?php esc_html_e( 'Preparing sync...', 'brag-book-gallery' ); ?></span>
					<span id="sync-current-percentage" class="brag-book-gallery-progress-percentage">0%</span>
				</div>
				<div class="brag-book-gallery-progress-bar brag-book-gallery-progress-bar--secondary">
					<div id="sync-current-fill" class="brag-book-gallery-progress-fill brag-book-gallery-progress-fill--secondary" style="width: 0%;"></div>
				</div>

				<!-- Progress Details -->
				<div id="sync-progress-details" class="brag-book-gallery-progress-details" style="display:none;">
					<ul id="sync-progress-items"></ul>
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
	 * Render sync history section
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render_sync_history_section(): void {
		$sync_logs = $this->get_sync_logs();
		?>
		<?php if ( empty( $sync_logs ) ) : ?>
			<div class="brag-book-gallery-notice brag-book-gallery-notice--info">
				<p><?php esc_html_e( 'No sync operations have been performed yet.', 'brag-book-gallery' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped brag-book-gallery-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Date', 'brag-book-gallery' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Sync Type', 'brag-book-gallery' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Source', 'brag-book-gallery' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Procedures', 'brag-book-gallery' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Cases', 'brag-book-gallery' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Duration', 'brag-book-gallery' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Details', 'brag-book-gallery' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sync_logs as $log ) : ?>
						<?php
						// Parse details for better display
						$details = ! empty( $log->details ) ? json_decode( $log->details, true ) : [];
						$sync_type_display = 'Full Sync'; // All syncs are now Full Sync

						// Calculate procedures and cases counts - use database columns if available
						$procedures_total = 0;
						$procedures_created = 0;
						$procedures_updated = 0;

						// Try database columns first, then fall back to JSON details
						if (isset($log->processed) && $log->processed > 0) {
							$procedures_total = $log->processed;
							$procedures_updated = $log->processed; // Assume updated for display
						} else {
							$procedures_created = $details['created'] ?? 0;
							$procedures_updated = $details['updated'] ?? 0;
							$procedures_total = $procedures_created + $procedures_updated;
						}

						$cases_total = 0;
						$cases_created = 0;
						$cases_updated = 0;

						// Try to get cases from details
						$cases_created = $details['cases_created'] ?? 0;
						$cases_updated = $details['cases_updated'] ?? 0;
						$cases_total = $details['total_cases_processed'] ?? ($cases_created + $cases_updated);

						// Calculate duration if available
						$duration = '';
						if ( ! empty( $details['duration'] ) ) {
							$duration = $details['duration'];
						} elseif ( ! empty( $log->updated_at ) && ! empty( $log->created_at ) ) {
							$start = strtotime( $log->created_at );
							$end = strtotime( $log->updated_at );
							$duration_seconds = $end - $start;
							if ( $duration_seconds > 0 ) {
								$minutes = floor( $duration_seconds / 60 );
								$seconds = $duration_seconds % 60;
								$duration = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";
							}
						}
						?>
						<tr>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?></td>
							<td>
								<strong><?php echo esc_html( $sync_type_display ); ?></strong>
								<?php if ( ! empty( $details['warnings'] ) ) : ?>
									<br><small class="description"><?php esc_html_e( 'With warnings', 'brag-book-gallery' ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<?php
								// For old schema compatibility, sync_source might not exist yet
								$sync_source = isset($log->sync_source) ? $log->sync_source : 'manual';
								$source_icon = $sync_source === 'automatic' ? '🔄' : '👤';
								$source_label = $sync_source === 'automatic' ? __( 'Automatic', 'brag-book-gallery' ) : __( 'Manual', 'brag-book-gallery' );
								?>
								<span class="sync-source sync-source--<?php echo esc_attr( $sync_source ); ?>">
									<?php echo esc_html( $source_icon . ' ' . $source_label ); ?>
								</span>
							</td>
							<td>
								<span class="brag-book-gallery-status brag-book-gallery-status--<?php echo esc_attr( $log->status ); ?>">
									<?php echo esc_html( ucfirst( $log->status ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $procedures_total > 0 ) : ?>
									<strong><?php echo esc_html( number_format( $procedures_total ) ); ?></strong>
									<?php if ( $procedures_created > 0 && $procedures_updated > 0 ) : ?>
										<br><small class="description">
											<?php echo esc_html( sprintf( '%d created, %d updated', $procedures_created, $procedures_updated ) ); ?>
										</small>
									<?php elseif ( $procedures_created > 0 ) : ?>
										<br><small class="description"><?php echo esc_html( sprintf( '%d created', $procedures_created ) ); ?></small>
									<?php elseif ( $procedures_updated > 0 ) : ?>
										<br><small class="description"><?php echo esc_html( sprintf( '%d updated', $procedures_updated ) ); ?></small>
									<?php endif; ?>
								<?php else : ?>
									<span class="description">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $cases_total > 0 ) : ?>
									<strong><?php echo esc_html( number_format( $cases_total ) ); ?></strong>
									<?php if ( $cases_created > 0 && $cases_updated > 0 ) : ?>
										<br><small class="description">
											<?php echo esc_html( sprintf( '%d created, %d updated', $cases_created, $cases_updated ) ); ?>
										</small>
									<?php elseif ( $cases_created > 0 ) : ?>
										<br><small class="description"><?php echo esc_html( sprintf( '%d created', $cases_created ) ); ?></small>
									<?php elseif ( $cases_updated > 0 ) : ?>
										<br><small class="description"><?php echo esc_html( sprintf( '%d updated', $cases_updated ) ); ?></small>
									<?php endif; ?>
								<?php else : ?>
									<span class="description">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $duration ) ) : ?>
									<?php echo esc_html( $duration ); ?>
								<?php else : ?>
									<span class="description">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $log->details ) ) : ?>
									<button type="button" class="button button-small view-details" data-details="<?php echo esc_attr( $log->details ); ?>">
										<?php esc_html_e( 'View Details', 'brag-book-gallery' ); ?>
									</button>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'No details', 'brag-book-gallery' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<button type="button" class="button button-small button-link-delete delete-sync-record"
									data-record-id="<?php echo esc_attr( $log->id ); ?>"
									data-record-date="<?php echo esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?>">
									<?php esc_html_e( 'Delete', 'brag-book-gallery' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
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
			'weekly'  => __( 'Weekly', 'brag-book-gallery' ),
			'monthly' => __( 'Monthly', 'brag-book-gallery' ),
			'yearly'  => __( 'Yearly', 'brag-book-gallery' ),
			'custom'  => __( 'Custom', 'brag-book-gallery' ),
		];
		?>
		<div class="sync-frequency-wrapper">
			<?php foreach ( $frequencies as $freq_value => $freq_label ) : ?>
				<div class="sync-frequency-option">
					<label>
						<input type="radio"
							   name="<?php echo esc_attr( $this->page_config['option_name'] ); ?>[sync_frequency]"
							   value="<?php echo esc_attr( $freq_value ); ?>"
							   class="sync-frequency-radio"
							   <?php checked( $value, $freq_value ); ?> />
						<span class="sync-frequency-label"><?php echo esc_html( $freq_label ); ?></span>
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
			<?php esc_html_e( 'How often should procedures be automatically synced from the API.', 'brag-book-gallery' ); ?>
		</p>
		<?php
	}

	/**
	 * Render performance mode field
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render_performance_mode_field(): void {
		$skip_images = get_option( 'brag_book_gallery_skip_image_downloads', false );
		?>
		<div class="brag-book-gallery-toggle-wrapper">
			<label class="brag-book-gallery-toggle">
				<input type="hidden" name="brag_book_gallery_skip_image_downloads" value="0" />
				<input type="checkbox"
					   name="brag_book_gallery_skip_image_downloads"
					   value="1"
					   id="skip_image_downloads"
					   <?php checked( $skip_images ); ?>
					   class="brag-book-gallery-toggle-input" />
				<span class="brag-book-gallery-toggle-slider"></span>
			</label>
			<span class="brag-book-gallery-toggle-label">
				<?php esc_html_e( 'Skip image downloads for faster sync', 'brag-book-gallery' ); ?>
			</span>
		</div>
		<p class="description">
			<?php esc_html_e( 'Enable this to dramatically speed up sync by skipping image downloads. Images URLs will still be saved and can be downloaded later. Recommended for initial syncs or when you have many cases.', 'brag-book-gallery' ); ?>
			<br>
			<strong><?php esc_html_e( 'Performance impact:', 'brag-book-gallery' ); ?></strong>
			<?php esc_html_e( 'Can reduce sync time from 25+ minutes to under 5 minutes.', 'brag-book-gallery' ); ?>
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
			$sync = new Procedure_Sync();
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
			error_log( 'BRAG book Gallery Sync: About to test Procedure_Sync constructor...' );
			$sync_manager = new Procedure_Sync();
			error_log( 'BRAG book Gallery Sync: Procedure_Sync constructor completed successfully!' );

			// Now run the actual sync
			error_log( 'BRAG book Gallery Sync: Running actual two-stage sync...' );
			$result = $sync_manager->run_two_stage_sync();
			error_log( 'BRAG book Gallery Sync: Sync completed with result: ' . json_encode( $result ) );

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
			];

		} catch ( Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Exception during constructor test: ' . $e->getMessage() );
			error_log( 'BRAG book Gallery Sync: Exception trace: ' . $e->getTraceAsString() );

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
						__( 'Full sync failed: %s', 'brag-book-gallery' ),
						$e->getMessage()
					),
					'created' => 0,
					'updated' => 0,
					'cases_created' => 0,
					'cases_updated' => 0,
					'errors'  => [ $e->getMessage() ],
				],
			];
		} catch ( Error $e ) {
			error_log( 'BRAG book Gallery Sync: Error during constructor test: ' . $e->getMessage() );
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
						__( 'Full sync failed: %s', 'brag-book-gallery' ),
						$e->getMessage()
					),
					'created' => 0,
					'updated' => 0,
					'cases_created' => 0,
					'cases_updated' => 0,
					'errors'  => [ $e->getMessage() ],
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

			error_log( 'BRAG Book Gallery: Test automatic sync - step 5: testing database log entry with automatic source' );

			// Test just creating a sync log entry to verify the sync_source tracking works
			$log_id = $database->log_sync_operation( 'full', 'completed', 1, 0, 'Test automatic sync entry', 'automatic' );

			if ( $log_id ) {
				error_log( 'BRAG Book Gallery: Test automatic sync - step 5: log entry created with ID: ' . $log_id );
				wp_send_json_success( [
					'message' => 'Test automatic sync tracking created successfully! Check the sync history to see the test entry marked as "Automatic".',
					'success' => true,
					'reload' => true
				] );
			} else {
				error_log( 'BRAG Book Gallery: Test automatic sync - step 5: log entry creation failed' );
				wp_send_json_error( [
					'message' => 'Test automatic sync tracking failed. Could not create log entry.',
					'success' => false
				] );
			}

		} catch ( \Exception $e ) {
			error_log( 'BRAG Book Gallery: Test automatic sync - Setup/SyncManager failed: ' . $e->getMessage() );
			wp_send_json_error( [
				'message' => 'Component access failed: ' . $e->getMessage(),
				'debug' => 'Setup or sync_manager failed'
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
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_clear_sync_log' ) ) {
			wp_die( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions.', 'brag-book-gallery' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'brag_book_sync_log';
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );

		wp_send_json_success( [
			'message' => __( 'Sync log cleared successfully.', 'brag-book-gallery' ),
		] );
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

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_delete_sync_record' ) ) {
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
		$record_id = absint( $_POST['record_id'] ?? 0 );
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
			$sync = new Procedure_Sync();
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
	 * Get latest sync information for status display
	 *
	 * @since 3.0.0
	 * @return array|null Latest sync info or null if no sync found
	 */
	private function get_latest_sync_info(): ?array {
		global $wpdb;

		$log_table = $wpdb->prefix . 'brag_book_sync_log';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$log_table}'" ) !== $log_table ) {
			return null;
		}

		$latest_log = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$log_table} WHERE sync_type = %s ORDER BY created_at DESC LIMIT 1",
			'procedure_sync'
		) );

		if ( ! $latest_log || empty( $latest_log->details ) ) {
			return null;
		}

		$details = json_decode( $latest_log->details, true );
		if ( ! $details ) {
			return null;
		}

		// Calculate totals
		$procedures_created = $details['created'] ?? 0;
		$procedures_updated = $details['updated'] ?? 0;
		$procedures_total = $procedures_created + $procedures_updated;

		$cases_created = $details['cases_created'] ?? 0;
		$cases_updated = $details['cases_updated'] ?? 0;
		$cases_total = $details['total_cases_processed'] ?? ($cases_created + $cases_updated);

		// Calculate duration
		$duration = '';
		if ( ! empty( $details['duration'] ) ) {
			$duration = $details['duration'];
		} elseif ( ! empty( $latest_log->updated_at ) && ! empty( $latest_log->created_at ) ) {
			$start = strtotime( $latest_log->created_at );
			$end = strtotime( $latest_log->updated_at );
			$duration_seconds = $end - $start;
			if ( $duration_seconds > 0 ) {
				$minutes = floor( $duration_seconds / 60 );
				$seconds = $duration_seconds % 60;
				$duration = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";
			}
		}

		return [
			'procedures_total' => $procedures_total,
			'cases_total' => $cases_total,
			'duration' => $duration,
			'warnings' => $details['warnings'] ?? [],
			'status' => $latest_log->status,
			'created_at' => $latest_log->created_at,
		];
	}

	/**
	 * Get sync logs from database
	 *
	 * @since 3.0.0
	 * @return array Sync log entries
	 */
	private function get_sync_logs(): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
			return [];
		}

		// Get all records and filter out duplicates/empty ones
		$all_records = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC");

		// Filter to only show meaningful sync records (not empty ones)
		$filtered_records = [];
		$seen_timestamps = [];

		foreach ($all_records as $record) {
			// More aggressive filtering - only show records with actual meaningful data
			$has_processed = (isset($record->processed) && $record->processed > 0);
			$has_failed = (isset($record->failed) && $record->failed > 0);
			$has_meaningful_details = false;

			// Check if details contains actual sync data (not just empty or basic info)
			if (isset($record->details) && !empty($record->details)) {
				$details = json_decode($record->details, true);
				if ($details && (
					(isset($details['created']) && $details['created'] > 0) ||
					(isset($details['updated']) && $details['updated'] > 0) ||
					(isset($details['cases_created']) && $details['cases_created'] > 0) ||
					(isset($details['cases_updated']) && $details['cases_updated'] > 0) ||
					(isset($details['total_cases_processed']) && $details['total_cases_processed'] > 0)
				)) {
					$has_meaningful_details = true;
				}
			}

			// Only include records that have actual sync data
			if (!$has_processed && !$has_failed && !$has_meaningful_details) {
				continue;
			}

			// Create a timestamp key to avoid showing multiple records from the same minute
			$timestamp_key = '';
			if (isset($record->created_at)) {
				$timestamp_key = date('Y-m-d H:i', strtotime($record->created_at));
			} elseif (isset($record->started_at)) {
				$timestamp_key = date('Y-m-d H:i', strtotime($record->started_at));
			}

			// If we already have a record for this timestamp, prefer the one with more data
			if (isset($seen_timestamps[$timestamp_key])) {
				$existing = $seen_timestamps[$timestamp_key];
				$existing_data_score = 0;
				if (isset($existing->processed)) $existing_data_score += $existing->processed;
				if (isset($existing->failed)) $existing_data_score += $existing->failed;

				$current_data_score = 0;
				if (isset($record->processed)) $current_data_score += $record->processed;
				if (isset($record->failed)) $current_data_score += $record->failed;

				// Keep the one with more data
				if ($current_data_score > $existing_data_score) {
					// Remove the old one and add the new one
					$filtered_records = array_filter($filtered_records, function($r) use ($existing) {
						return $r->id !== $existing->id;
					});
					$filtered_records[] = $record;
					$seen_timestamps[$timestamp_key] = $record;
				}
			} else {
				$filtered_records[] = $record;
				if ($timestamp_key) {
					$seen_timestamps[$timestamp_key] = $record;
				}
			}
		}

		return array_slice($filtered_records, 0, 20);
	}

	/**
	 * Get the most recent sync from the history table
	 *
	 * @since 3.0.0
	 * @return object|null Latest sync record or null if none found
	 */
	private function get_latest_sync_from_history(): ?object {
		global $wpdb;
		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
			return null;
		}

		// Get the most recent completed sync
		return $wpdb->get_row(
			"SELECT * FROM {$table_name}
			WHERE operation = 'complete'
			AND item_type = 'sync_session'
			ORDER BY created_at DESC
			LIMIT 1"
		);
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
				'brag-book-sync-admin',
				plugins_url( 'assets/js/sync-admin.js', dirname( __DIR__, 2 ) ),
				[ 'jquery' ],
				'3.0.0',
				true
			);

			wp_localize_script( 'brag-book-sync-admin', 'bragBookSync', [
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'sync_nonce'        => wp_create_nonce( 'brag_book_sync_procedures' ),
				'nonce'             => wp_create_nonce( 'brag_book_gallery_settings_nonce' ), // For stop sync and progress
				'test_auto_nonce'   => wp_create_nonce( 'brag_book_test_automatic_sync' ),
				'clear_log_nonce'   => wp_create_nonce( 'brag_book_clear_sync_log' ),
				'delete_nonce'      => wp_create_nonce( 'brag_book_delete_sync_record' ),
				'messages'          => [
					'sync_starting'     => __( 'Starting sync...', 'brag-book-gallery' ),
					'sync_in_progress'  => __( 'Sync in progress...', 'brag-book-gallery' ),
					'sync_complete'     => __( 'Sync completed successfully!', 'brag-book-gallery' ),
					'sync_error'        => __( 'Sync failed. Please try again.', 'brag-book-gallery' ),
					'confirm_clear_log' => __( 'Are you sure you want to clear the sync log? This action cannot be undone.', 'brag-book-gallery' ),
					'confirm_delete_record' => __( 'Are you sure you want to delete this sync record? This action cannot be undone.', 'brag-book-gallery' ),
				],
			] );
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
			$allowed_frequencies = [ 'hourly', 'daily', 'weekly', 'monthly' ];
			$sanitized['sync_frequency'] = in_array( $input['sync_frequency'], $allowed_frequencies, true )
				? $input['sync_frequency']
				: 'weekly';
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
		return update_option( $this->page_config['option_name'], $settings );
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
}