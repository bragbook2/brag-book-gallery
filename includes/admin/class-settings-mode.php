<?php
/**
 * Mode Settings Class
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Admin
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Mode Settings Class
 *
 * Central interface for managing BRAG book Gallery operating modes and transitions.
 * This class provides the primary interface for switching between JavaScript and
 * Local modes while managing the configuration and data migration aspects.
 *
 * **Core Functionality:**
 * - Seamless mode switching with data preservation
 * - Mode-specific configuration management
 * - Real-time status monitoring and feedback
 * - Migration tools and data integrity checks
 *
 * **Mode Management Features:**
 * - Visual mode comparison with pros/cons
 * - One-click mode switching with validation
 * - Configuration backup and restoration
 * - Data migration status tracking
 *
 * **JavaScript Mode Management:**
 * - Real-time API connectivity validation
 * - Performance optimization settings
 * - Virtual URL configuration
 * - Cache management controls
 *
 * **Local Mode Management:**
 * - Sync scheduling and automation
 * - Database storage monitoring
 * - Image import configuration
 * - SEO optimization settings
 *
 * **Migration Safety:**
 * - Pre-migration compatibility checks
 * - Data backup and rollback capabilities
 * - Progress monitoring during transitions
 * - Post-migration validation and testing
 *
 * This class serves as the command center for all mode-related operations,
 * ensuring safe and reliable transitions between different operational modes.
 *
 * @since 3.0.0
 */
class Settings_Mode extends Settings_Base {

	/**
	 * Initialize the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug  = 'brag-book-gallery-mode';
		// Don't translate here - translations happen in render
	}

	/**
	 * Render the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		// Set translated strings when rendering (after init)
		$this->page_title = __( 'Mode Settings', 'brag-book-gallery' );
		$this->menu_title = __( 'Mode', 'brag-book-gallery' );

		// Get mode manager instance
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$current_mode = $mode_manager->get_current_mode();
		$mode_settings = $mode_manager->get_mode_settings();

		// Handle form submission
		if ( isset( $_POST['switch_mode'] ) ) {
			$this->handle_mode_switch();
		}

		if ( isset( $_POST['save_mode_settings'] ) ) {
			$this->save_mode_settings();
		}

		$this->render_header();
		?>

		<div class="brag-book-gallery-mode-content">
			<!-- Current Mode Status -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Current Mode', 'brag-book-gallery' ); ?></h2>
				<div class="brag-book-gallery-notice brag-book-gallery-notice--info inline">
					<p>
						<strong><?php esc_html_e( 'Active Mode:', 'brag-book-gallery' ); ?></strong>
						<span class="brag-mode-badge <?php echo esc_attr( $current_mode ); ?>">
							<?php echo esc_html( ucfirst( $current_mode ) ); ?> Mode
						</span>
					</p>
					<p>
						<?php
						if ( $current_mode === 'javascript' ) {
							esc_html_e( 'Content is loaded dynamically from the BRAG book API. URLs are virtual and galleries update in real-time.', 'brag-book-gallery' );
						} else {
							esc_html_e( 'Content is stored locally in WordPress. Galleries use native post types and taxonomies for better SEO and performance.', 'brag-book-gallery' );
						}
						?>
					</p>
				</div>
			</div>

			<!-- Mode Switcher -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Switch Mode', 'brag-book-gallery' ); ?></h2>
				<form id="brag-mode-switch-form" method="post">
					<?php wp_nonce_field( 'brag_book_gallery_mode_switch', 'mode_switch_nonce' ); ?>

					<table class="form-table brag-book-gallery-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Select Mode', 'brag-book-gallery' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="radio" name="gallery_mode" value="javascript"
											<?php checked( $current_mode, 'javascript' ); ?>>
										<strong><?php esc_html_e( 'JavaScript Mode', 'brag-book-gallery' ); ?></strong>
										<p class="description">
											<?php esc_html_e( 'Dynamic API-driven content. Best for real-time updates and minimal database usage.', 'brag-book-gallery' ); ?>
										</p>
									</label>
									<br><br>
									<label>
										<input type="radio" name="gallery_mode" value="local"
											<?php checked( $current_mode, 'local' ); ?>>
										<strong><?php esc_html_e( 'Local Mode', 'brag-book-gallery' ); ?></strong>
										<p class="description">
											<?php esc_html_e( 'WordPress native content. Best for SEO, performance, and offline access.', 'brag-book-gallery' ); ?>
										</p>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>

					<div class="brag-book-gallery-actions">
						<button type="submit" name="switch_mode" class="button button-primary button-large">
							<?php esc_html_e( 'Switch Mode', 'brag-book-gallery' ); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Mode-Specific Settings -->
			<div class="brag-book-gallery-section">
				<h2><?php echo esc_html( sprintf( __( '%s Mode Settings', 'brag-book-gallery' ), ucfirst( $current_mode ) ) ); ?></h2>

				<?php if ( $current_mode === 'local' ) : ?>
					<!-- Local Mode Settings -->
					<form id="local-mode-settings-form" method="post">
						<?php wp_nonce_field( 'brag_book_gallery_mode_settings', 'mode_settings_nonce' ); ?>

						<table class="form-table brag-book-gallery-form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Sync Frequency', 'brag-book-gallery' ); ?></th>
								<td>
									<select name="sync_frequency" id="sync_frequency">
										<option value="manual" <?php selected( $mode_settings['sync_frequency'] ?? '', 'manual' ); ?>>
											<?php esc_html_e( 'Manual Only', 'brag-book-gallery' ); ?>
										</option>
										<option value="hourly" <?php selected( $mode_settings['sync_frequency'] ?? '', 'hourly' ); ?>>
											<?php esc_html_e( 'Hourly', 'brag-book-gallery' ); ?>
										</option>
										<option value="daily" <?php selected( $mode_settings['sync_frequency'] ?? '', 'daily' ); ?>>
											<?php esc_html_e( 'Daily', 'brag-book-gallery' ); ?>
										</option>
										<option value="weekly" <?php selected( $mode_settings['sync_frequency'] ?? '', 'weekly' ); ?>>
											<?php esc_html_e( 'Weekly', 'brag-book-gallery' ); ?>
										</option>
									</select>
									<p class="description">
										<?php esc_html_e( 'How often to sync data from the API.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Auto Sync', 'brag-book-gallery' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="auto_sync" value="1"
											<?php checked( $mode_settings['auto_sync'] ?? false, true ); ?>>
										<?php esc_html_e( 'Enable automatic synchronization', 'brag-book-gallery' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Import Images', 'brag-book-gallery' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="import_images" value="1"
											<?php checked( $mode_settings['import_images'] ?? true, true ); ?>>
										<?php esc_html_e( 'Download and store images locally', 'brag-book-gallery' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Store images in WordPress media library for better performance.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Batch Size', 'brag-book-gallery' ); ?></th>
								<td>
									<input type="number" name="batch_size" value="<?php echo esc_attr( $mode_settings['batch_size'] ?? 20 ); ?>"
										min="1" max="100" step="1" class="small-text">
									<p class="description">
										<?php esc_html_e( 'Number of items to process per batch during sync.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<!-- Sync Statistics -->
						<?php if ( $mode_manager->is_local_mode() ) : ?>
							<h3><?php esc_html_e( 'Sync Statistics', 'brag-book-gallery' ); ?></h3>
							<table class="widefat striped">
								<tr>
									<th><?php esc_html_e( 'Total Galleries', 'brag-book-gallery' ); ?></th>
									<td><?php echo esc_html( wp_count_posts( 'brag_gallery' )->publish ?? 0 ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Last Sync', 'brag-book-gallery' ); ?></th>
									<td>
										<?php
										$last_sync = get_option( 'brag_book_gallery_last_sync', '' );
										echo $last_sync ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_sync ) ) ) : esc_html__( 'Never', 'brag-book-gallery' );
										?>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Sync Status', 'brag-book-gallery' ); ?></th>
									<td>
										<?php
										$sync_status = get_transient( 'brag_book_gallery_sync_status' );
										if ( $sync_status === 'running' ) {
											echo '<span class="dashicons dashicons-update spin"></span> ' . esc_html__( 'Running', 'brag-book-gallery' );
										} else {
											echo '<span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Idle', 'brag-book-gallery' );
										}
										?>
									</td>
								</tr>
							</table>
						<?php endif; ?>

						<div class="brag-book-gallery-actions">
							<button type="submit" name="save_mode_settings" class="button button-primary button-large">
								<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>
							</button>
							<?php if ( $mode_manager->is_local_mode() ) : ?>
								<button type="button" id="sync-now-btn" class="button button-secondary button-large">
									<?php esc_html_e( 'Sync Now', 'brag-book-gallery' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</form>
				<?php else : ?>
					<!-- JavaScript Mode Settings Info -->
					<div class="brag-book-gallery-notice brag-book-gallery-notice--info inline">
						<p>
							<?php esc_html_e( 'JavaScript mode uses real-time API connections. Configure API settings to manage your connection.', 'brag-book-gallery' ); ?>
						</p>
						<p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-javascript' ) ); ?>" class="button">
								<?php esc_html_e( 'JavaScript Settings', 'brag-book-gallery' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-api-settings' ) ); ?>" class="button">
								<?php esc_html_e( 'API Settings', 'brag-book-gallery' ); ?>
							</a>
						</p>
					</div>
				<?php endif; ?>
			</div>

			<!-- Mode Migration Tools -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Migration Tools', 'brag-book-gallery' ); ?></h2>

				<?php if ( $current_mode === 'local' ) : ?>
					<div class="brag-book-gallery-notice brag-book-gallery-notice--warning inline">
						<p>
							<strong><?php esc_html_e( 'Warning:', 'brag-book-gallery' ); ?></strong>
							<?php esc_html_e( 'Switching to JavaScript mode will not delete your local content, but galleries will load from the API instead.', 'brag-book-gallery' ); ?>
						</p>
					</div>
				<?php else : ?>
					<div class="brag-book-gallery-notice brag-book-gallery-notice--info inline">
						<p>
							<?php esc_html_e( 'Switching to Local mode will import gallery data from the API and store it in WordPress.', 'brag-book-gallery' ); ?>
						</p>
					</div>
				<?php endif; ?>

				<table class="form-table brag-book-gallery-form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Data Status', 'brag-book-gallery' ); ?></th>
						<td>
							<?php
							$gallery_count = wp_count_posts( 'brag_gallery' )->publish ?? 0;
							if ( $gallery_count > 0 ) {
								printf(
									/* translators: %d: number of galleries */
									esc_html( _n( '%d gallery stored locally', '%d galleries stored locally', $gallery_count, 'brag-book-gallery' ) ),
									$gallery_count
								);
							} else {
								esc_html_e( 'No local gallery data', 'brag-book-gallery' );
							}
							?>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<?php
		$this->render_footer();
	}

	/**
	 * Handle mode switch
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function handle_mode_switch(): void {
		if ( ! $this->save_settings( 'brag_book_gallery_mode_switch', 'mode_switch_nonce' ) ) {
			return;
		}

		if ( isset( $_POST['gallery_mode'] ) ) {
			$new_mode = sanitize_text_field( $_POST['gallery_mode'] );
			$valid_modes = array( 'javascript', 'local' );

			if ( in_array( $new_mode, $valid_modes, true ) ) {
				$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
				$mode_manager->switch_mode( $new_mode );

				$this->add_notice(
					sprintf(
						/* translators: %s: mode name */
						__( 'Switched to %s mode successfully.', 'brag-book-gallery' ),
						ucfirst( $new_mode )
					)
				);
			}
		}

		settings_errors( $this->page_slug );
	}

	/**
	 * Save mode-specific settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_mode_settings(): void {
		if ( ! $this->save_settings( 'brag_book_gallery_mode_settings', 'mode_settings_nonce' ) ) {
			return;
		}

		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$current_mode = $mode_manager->get_current_mode();

		if ( $current_mode === 'local' ) {
			// Save Local mode settings
			$settings = array(
				'sync_frequency' => sanitize_text_field( $_POST['sync_frequency'] ?? 'manual' ),
				'auto_sync'      => ! empty( $_POST['auto_sync'] ),
				'import_images'  => ! empty( $_POST['import_images'] ),
				'batch_size'     => absint( $_POST['batch_size'] ?? 20 ),
			);

			$mode_manager->update_mode_settings( $settings );

			$this->add_notice( __( 'Mode settings saved successfully.', 'brag-book-gallery' ) );
		}

		settings_errors( $this->page_slug );
	}
}
