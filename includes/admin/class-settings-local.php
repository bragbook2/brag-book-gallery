<?php
/**
 * Local Mode Settings Class
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
 * Local Mode Settings Class
 *
 * Comprehensive configuration interface for Local mode operations in BRAG Book Gallery.
 * This class manages all aspects of Local mode functionality where gallery data
 * is synchronized from the API and stored locally in WordPress for optimal performance.
 *
 * **Core Configuration Areas:**
 * - Synchronization scheduling and frequency controls
 * - Image import and processing settings
 * - Data preservation and storage options
 * - Batch processing configuration for large datasets
 *
 * **Sync Management Features:**
 * - Configurable sync frequency (manual, hourly, daily, weekly)
 * - Real-time sync status monitoring with progress indicators
 * - Manual sync trigger for immediate data updates
 * - Batch size controls for memory-efficient processing
 *
 * **Image Handling:**
 * - Local image import to WordPress media library
 * - Multiple image size generation options
 * - Configurable image formats and quality settings
 * - Storage optimization for large image collections
 *
 * **Data Management:**
 * - Original API data preservation for reference
 * - WordPress post type integration for SEO benefits
 * - Custom taxonomy support for categories and procedures
 * - Database statistics and storage monitoring
 *
 * Local mode provides the best SEO and performance characteristics by storing
 * all gallery data directly in WordPress while maintaining sync with the API.
 *
 * @since 3.0.0
 */
class Settings_Local extends Settings_Base {

	/**
	 * Initialize the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug  = 'brag-book-gallery-local';
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
		$this->page_title = __( 'Local Mode Settings', 'brag-book-gallery' );
		$this->menu_title = __( 'Local Settings', 'brag-book-gallery' );

		// Handle form submission
		if ( isset( $_POST['submit'] ) ) {
			$this->save();
		}

		// Handle sync action
		if ( isset( $_POST['sync_now'] ) ) {
			$this->trigger_sync();
		}

		// Get current settings
		$sync_frequency  = get_option( 'brag_book_gallery_sync_frequency', 'daily' );
		$sync_time      = get_option( 'brag_book_gallery_sync_time', '02:00' );
		$batch_size     = get_option( 'brag_book_gallery_batch_size', 20 );
		$import_images  = get_option( 'brag_book_gallery_import_images', 'yes' );
		$image_sizes    = get_option( 'brag_book_gallery_image_sizes', array( 'thumbnail', 'medium', 'large' ) );
		$auto_sync      = get_option( 'brag_book_gallery_auto_sync', 'no' );
		$preserve_data  = get_option( 'brag_book_gallery_preserve_api_data', 'yes' );

		// Get sync status
		$last_sync      = get_option( 'brag_book_gallery_last_sync', '' );
		$sync_status    = get_transient( 'brag_book_gallery_sync_status' );

		$this->render_header();

		// Show notice if not in Local mode
		if ( ! $this->is_local_mode() ) {
			?>
			<div class="brag-book-gallery-notice brag-book-gallery-notice-warning">
				<p>
					<?php esc_html_e( 'These settings only apply when Local mode is active.', 'brag-book-gallery' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-mode' ) ); ?>">
						<?php esc_html_e( 'Switch to Local Mode', 'brag-book-gallery' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
		?>

		<form method="post" action="" id="brag-book-gallery-local-settings-form">
			<?php wp_nonce_field( 'brag_book_gallery_local_settings', 'brag_book_gallery_local_nonce' ); ?>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Sync Settings', 'brag-book-gallery' ); ?></h2>

				<?php if ( $sync_status === 'running' ) : ?>
					<div class="brag-book-gallery-notice brag-book-gallery-notice-info inline">
						<p>
							<span class="spinner is-active" style="float: left; margin: 0 10px 0 0;"></span>
							<?php esc_html_e( 'Sync is currently running...', 'brag-book-gallery' ); ?>
						</p>
					</div>
				<?php endif; ?>

				<table class="form-table brag-book-gallery-form-table" role="presentation">
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Sync Status', 'brag-book-gallery' ); ?>
					</th>
					<td>
						<?php if ( $last_sync ) : ?>
							<p>
								<?php
								printf(
									/* translators: %s: last sync date/time */
									esc_html__( 'Last sync: %s', 'brag-book-gallery' ),
									esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_sync ) ) )
								);
								?>
							</p>
						<?php else : ?>
							<p><?php esc_html_e( 'Never synced', 'brag-book-gallery' ); ?></p>
						<?php endif; ?>

						<?php if ( $this->is_local_mode() && $sync_status !== 'running' ) : ?>
							<p>
								<button type="submit"
								        name="sync_now"
								        class="button button-secondary">
									<?php esc_html_e( 'Sync Now', 'brag-book-gallery' ); ?>
								</button>
							</p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="auto_sync">
							<?php esc_html_e( 'Automatic Sync', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox"
							       id="auto_sync"
							       name="auto_sync"
							       value="yes"
							       <?php checked( $auto_sync, 'yes' ); ?>>
							<?php esc_html_e( 'Enable automatic synchronization', 'brag-book-gallery' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Automatically sync gallery data from the API.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="sync_frequency">
							<?php esc_html_e( 'Sync Frequency', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<select id="sync_frequency" name="sync_frequency">
							<option value="manual" <?php selected( $sync_frequency, 'manual' ); ?>>
								<?php esc_html_e( 'Manual Only', 'brag-book-gallery' ); ?>
							</option>
							<option value="hourly" <?php selected( $sync_frequency, 'hourly' ); ?>>
								<?php esc_html_e( 'Hourly', 'brag-book-gallery' ); ?>
							</option>
							<option value="twicedaily" <?php selected( $sync_frequency, 'twicedaily' ); ?>>
								<?php esc_html_e( 'Twice Daily', 'brag-book-gallery' ); ?>
							</option>
							<option value="daily" <?php selected( $sync_frequency, 'daily' ); ?>>
								<?php esc_html_e( 'Daily', 'brag-book-gallery' ); ?>
							</option>
							<option value="weekly" <?php selected( $sync_frequency, 'weekly' ); ?>>
								<?php esc_html_e( 'Weekly', 'brag-book-gallery' ); ?>
							</option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="sync_time">
							<?php esc_html_e( 'Sync Time', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<input type="time"
						       id="sync_time"
						       name="sync_time"
						       value="<?php echo esc_attr( $sync_time ); ?>">
						<p class="description">
							<?php esc_html_e( 'Preferred time for daily sync (server time).', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="batch_size">
							<?php esc_html_e( 'Batch Size', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<input type="number"
						       id="batch_size"
						       name="batch_size"
						       value="<?php echo esc_attr( $batch_size ); ?>"
						       min="5"
						       max="100"
						       step="5"
						       class="small-text">
						<p class="description">
							<?php esc_html_e( 'Number of items to process per batch during sync.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
				</table>
			</div>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Image Settings', 'brag-book-gallery' ); ?></h2>
				<table class="form-table brag-book-gallery-form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="import_images">
							<?php esc_html_e( 'Import Images', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox"
							       id="import_images"
							       name="import_images"
							       value="yes"
							       <?php checked( $import_images, 'yes' ); ?>>
							<?php esc_html_e( 'Download and store images locally', 'brag-book-gallery' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Import images to WordPress media library for better performance.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Image Sizes', 'brag-book-gallery' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox"
								       name="image_sizes[]"
								       value="thumbnail"
								       <?php checked( in_array( 'thumbnail', $image_sizes, true ) ); ?>>
								<?php esc_html_e( 'Thumbnail', 'brag-book-gallery' ); ?>
							</label><br>
							<label>
								<input type="checkbox"
								       name="image_sizes[]"
								       value="medium"
								       <?php checked( in_array( 'medium', $image_sizes, true ) ); ?>>
								<?php esc_html_e( 'Medium', 'brag-book-gallery' ); ?>
							</label><br>
							<label>
								<input type="checkbox"
								       name="image_sizes[]"
								       value="large"
								       <?php checked( in_array( 'large', $image_sizes, true ) ); ?>>
								<?php esc_html_e( 'Large', 'brag-book-gallery' ); ?>
							</label><br>
							<label>
								<input type="checkbox"
								       name="image_sizes[]"
								       value="full"
								       <?php checked( in_array( 'full', $image_sizes, true ) ); ?>>
								<?php esc_html_e( 'Full Size', 'brag-book-gallery' ); ?>
							</label>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Select which image sizes to generate when importing.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
				</table>
			</div>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Data Settings', 'brag-book-gallery' ); ?></h2>
				<table class="form-table brag-book-gallery-form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="preserve_data">
							<?php esc_html_e( 'Preserve API Data', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox"
							       id="preserve_data"
							       name="preserve_data"
							       value="yes"
							       <?php checked( $preserve_data, 'yes' ); ?>>
							<?php esc_html_e( 'Keep original API data for reference', 'brag-book-gallery' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Store original API response data in post meta for debugging and migration.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
				</table>
			</div>

			<div class="brag-book-gallery-actions">
				<button type="submit"
				        name="submit"
				        id="submit"
				        class="button button-primary button-large">
					<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</form>

		<?php if ( $this->is_local_mode() ) : ?>
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Gallery Statistics', 'brag-book-gallery' ); ?></h2>
				<?php $this->render_statistics(); ?>
			</div>
		<?php endif; ?>

		<?php
		$this->render_footer();
	}

	/**
	 * Save settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save(): void {
		if ( ! $this->save_settings( 'brag_book_gallery_local_settings', 'brag_book_gallery_local_nonce' ) ) {
			return;
		}

		// Save sync settings
		$auto_sync = isset( $_POST['auto_sync'] ) && $_POST['auto_sync'] === 'yes' ? 'yes' : 'no';
		update_option( 'brag_book_gallery_auto_sync', $auto_sync );

		if ( isset( $_POST['sync_frequency'] ) ) {
			$valid_frequencies = array( 'manual', 'hourly', 'twicedaily', 'daily', 'weekly' );
			$frequency = in_array( $_POST['sync_frequency'], $valid_frequencies, true )
				? $_POST['sync_frequency']
				: 'daily';
			update_option( 'brag_book_gallery_sync_frequency', $frequency );
		}

		if ( isset( $_POST['sync_time'] ) ) {
			update_option( 'brag_book_gallery_sync_time', sanitize_text_field( $_POST['sync_time'] ) );
		}

		if ( isset( $_POST['batch_size'] ) ) {
			update_option( 'brag_book_gallery_batch_size', absint( $_POST['batch_size'] ) );
		}

		// Save image settings
		$import_images = isset( $_POST['import_images'] ) && $_POST['import_images'] === 'yes' ? 'yes' : 'no';
		update_option( 'brag_book_gallery_import_images', $import_images );

		if ( isset( $_POST['image_sizes'] ) && is_array( $_POST['image_sizes'] ) ) {
			$valid_sizes = array( 'thumbnail', 'medium', 'large', 'full' );
			$sizes = array_intersect( $_POST['image_sizes'], $valid_sizes );
			update_option( 'brag_book_gallery_image_sizes', $sizes );
		}

		// Save data settings
		$preserve_data = isset( $_POST['preserve_data'] ) && $_POST['preserve_data'] === 'yes' ? 'yes' : 'no';
		update_option( 'brag_book_gallery_preserve_api_data', $preserve_data );

		$this->add_notice( __( 'Local mode settings saved successfully.', 'brag-book-gallery' ) );
		settings_errors( $this->page_slug );
	}

	/**
	 * Trigger manual sync
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function trigger_sync(): void {
		if ( ! $this->save_settings( 'brag_book_gallery_local_settings', 'brag_book_gallery_local_nonce' ) ) {
			return;
		}

		// Set sync status
		set_transient( 'brag_book_gallery_sync_status', 'running', HOUR_IN_SECONDS );

		// Schedule immediate sync
		wp_schedule_single_event( time(), 'brag_book_gallery_sync_data' );

		$this->add_notice( __( 'Sync has been initiated. This may take a few minutes.', 'brag-book-gallery' ), 'info' );
		settings_errors( $this->page_slug );
	}

	/**
	 * Render gallery statistics
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_statistics(): void {
		// Get post type if it exists
		$post_type = 'brag_gallery';

		if ( ! post_type_exists( $post_type ) ) {
			?>
			<p><?php esc_html_e( 'Gallery post type not found. Please ensure Local mode is active.', 'brag-book-gallery' ); ?></p>
			<?php
			return;
		}

		$posts_count = wp_count_posts( $post_type );
		$categories_count = wp_count_terms( 'brag_category' );
		$procedures_count = wp_count_terms( 'brag_procedure' );
		?>
		<table class="widefat striped">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Total Galleries', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $posts_count->publish ?? 0 ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Draft Galleries', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $posts_count->draft ?? 0 ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Medical Categories', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $categories_count ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Procedures', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $procedures_count ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}
