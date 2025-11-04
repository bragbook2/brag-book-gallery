<?php
/**
 * Debug Factory Reset Component
 *
 * Handles rendering and processing of factory reset functionality. Provides
 * complete plugin reset capabilities with comprehensive cleanup of all plugin
 * data, settings, and database entries. Utilizes modern PHP 8.2+ syntax and
 * WordPress VIP coding standards.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug
 * @since      3.3.0
 * @version    3.3.0
 *
 * @author     BRAGBook Team
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Debug;

use BRAGBookGallery\Includes\Core\Setup;
use Exception;
use Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Factory Reset class
 *
 * Manages complete plugin reset to factory defaults with secure handling and
 * comprehensive cleanup. Removes all plugin data including settings, transients,
 * custom tables, and log files.
 *
 * ## Features:
 * - Complete plugin settings deletion
 * - Cache and transient clearing
 * - API token removal
 * - Custom database table deletion
 * - Rewrite rules reset
 * - Log file cleanup
 * - Gallery page deletion
 * - Shortcode page cleanup
 * - Plugin reactivation to defaults
 *
 * ## Reset Actions:
 * - Delete all brag_book_gallery_* options
 * - Clear all plugin transients
 * - Drop custom database tables
 * - Delete gallery pages
 * - Remove log files
 * - Flush rewrite rules
 * - Flush WordPress cache
 * - Reinitialize plugin with defaults
 *
 * ## Security:
 * - Nonce verification required
 * - Capability checks (manage_options)
 * - Confirmation dialog required
 * - Comprehensive error handling
 * - Output buffer control
 *
 * @since 3.3.0
 */
final class Debug_Factory_Reset {

	/**
	 * Render the factory reset interface
	 *
	 * Displays warning message and reset button with security nonce.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		?>
		<div id="factory-reset" class="brag-book-gallery-tab-panel">
			<div class="brag-book-gallery-section brag-book-gallery-danger-zone">
				<h2 style="color: #dc3232;"><?php esc_html_e( 'Danger Zone - Factory Reset', 'brag-book-gallery' ); ?></h2>
				<div class="brag-book-gallery-warning-box" style="background: #fff3cd; border-left: 4px solid #dc3232; padding: 12px; margin-bottom: 20px;">
					<p><strong><?php esc_html_e( 'Warning:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'This action cannot be undone. All plugin settings, data, and configurations will be permanently deleted.', 'brag-book-gallery' ); ?></p>
				</div>
				<table class="form-table brag-book-gallery-form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Factory Reset', 'brag-book-gallery' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'This will completely reset the plugin to its initial state by:', 'brag-book-gallery' ); ?>
							</p>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Deleting all plugin settings and configurations', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Clearing all cached data and transients', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Removing all API tokens and credentials', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Deleting custom database tables (if any)', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Resetting rewrite rules', 'brag-book-gallery' ); ?></li>
							</ul>
							<p style="margin-top: 15px;">
								<button type="button" id="brag-book-gallery-factory-reset" class="button button-danger" style="background: #dc3232; color: white; border-color: #dc3232;" data-nonce="<?php echo esc_attr( wp_create_nonce( 'brag_book_gallery_admin' ) ); ?>">
									<?php esc_html_e( 'Factory Reset Plugin', 'brag-book-gallery' ); ?>
								</button>
							</p>
							<?php $this->render_javascript(); ?>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Execute factory reset
	 *
	 * Performs complete plugin reset with comprehensive cleanup and error handling.
	 *
	 * @since 3.3.0
	 *
	 * @return array{success: bool, message: string, redirect?: string} Result array
	 */
	public function execute_reset(): array {
		// Start output buffering to catch any unexpected output.
		ob_start();

		try {
			global $wpdb;

			// Get all plugin options.
			$plugin_options = $this->get_plugin_options();

			// Delete the gallery page if it exists.
			$this->delete_gallery_pages();

			// Delete all plugin options.
			foreach ( $plugin_options as $option ) {
				delete_option( $option );
			}

			// Clear all transients.
			$this->clear_transients();

			// Drop custom database tables.
			$this->drop_custom_tables();

			// Clear rewrite rules.
			flush_rewrite_rules();

			// Clear any cached data.
			wp_cache_flush();

			// Clear logs.
			$this->clear_log_files();

			// Reinitialize the plugin by running activation routine.
			$this->reinitialize_plugin();

			// Clear output buffer and return success.
			ob_end_clean();

			return array(
				'success'  => true,
				'message'  => __( 'Plugin has been successfully reset to factory defaults. The page will reload.', 'brag-book-gallery' ),
				'redirect' => admin_url( 'admin.php?page=brag-book-gallery-settings&reset=success' ),
			);

		} catch ( Exception $e ) {
			// Clear output buffer on error.
			ob_end_clean();

			return array(
				'success' => false,
				/* translators: %s: error message */
				'message' => sprintf( __( 'Factory reset failed: %s', 'brag-book-gallery' ), $e->getMessage() ),
			);
		} catch ( Error $e ) {
			// Catch fatal errors too.
			ob_end_clean();

			return array(
				'success' => false,
				/* translators: %s: error message */
				'message' => sprintf( __( 'Factory reset critical error: %s', 'brag-book-gallery' ), $e->getMessage() ),
			);
		}
	}

	/**
	 * Get list of plugin options to delete
	 *
	 * Returns an array of all plugin option names that should be deleted during reset.
	 *
	 * @since 3.3.0
	 *
	 * @return array List of option names
	 */
	private function get_plugin_options(): array {
		return array(
			'brag_book_gallery_api_token',
			'brag_book_gallery_website_property_id',
			'brag_book_gallery_page_slug',
			'brag_book_gallery_mode',
			'brag_book_gallery_debug_mode',
			'brag_book_gallery_javascript_enabled',
			'brag_book_gallery_log_api_calls',
			'brag_book_gallery_log_errors',
			'brag_book_gallery_log_verbosity',
			'brag_book_gallery_cache_duration',
			'brag_book_gallery_items_per_page',
			'brag_book_gallery_enable_favorites',
			'brag_book_gallery_enable_sharing',
			'brag_book_gallery_enable_nudity_warning',
			'brag_book_gallery_consultation_form_url',
			'brag_book_gallery_consultation_form_type',
			'brag_book_gallery_consultation_custom_html',
			'brag_book_gallery_db_version',
			'brag_book_gallery_version',
			'brag_book_gallery_activation_time',
			'brag_book_gallery_last_sync',
			'brag_book_gallery_page_id',
		);
	}

	/**
	 * Delete gallery pages
	 *
	 * Removes the main gallery page and any pages containing gallery shortcodes.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	private function delete_gallery_pages(): void {
		global $wpdb;

		// Delete the gallery page if it exists.
		$gallery_page_id = get_option( 'brag_book_gallery_page_id' );
		if ( $gallery_page_id ) {
			// Force delete the page (bypass trash).
			wp_delete_post( $gallery_page_id, true );
		}

		// Also check for any pages with the gallery shortcode and optionally delete them.
		$pages_with_shortcode = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_content LIKE '%[brag_book_gallery%'
			AND post_type = 'page'"
		);

		// Delete all pages containing the gallery shortcode.
		foreach ( $pages_with_shortcode as $page_id ) {
			wp_delete_post( (int) $page_id, true );
		}
	}

	/**
	 * Clear all plugin transients
	 *
	 * Removes all transients and transient timeouts from the database.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	private function clear_transients(): void {
		global $wpdb;

		// Clear all transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_brag_book_%'
			OR option_name LIKE '_transient_timeout_brag_book_%'"
		);

		// Clear site transients (for multisite).
		if ( is_multisite() ) {
			$wpdb->query(
				"DELETE FROM {$wpdb->sitemeta}
				WHERE meta_key LIKE '_site_transient_brag_book_%'
				OR meta_key LIKE '_site_transient_timeout_brag_book_%'"
			);
		}
	}

	/**
	 * Drop custom database tables
	 *
	 * Removes any custom database tables created by the plugin.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	private function drop_custom_tables(): void {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'brag_case_map',
			$wpdb->prefix . 'brag_sync_log',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Clear log files
	 *
	 * Removes all log files from the plugin's log directory.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	private function clear_log_files(): void {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/brag-book-gallery-logs';

		if ( is_dir( $log_dir ) ) {
			$files = glob( $log_dir . '/*.log' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						unlink( $file );
					}
				}
			}
		}
	}

	/**
	 * Reinitialize plugin
	 *
	 * Runs the plugin activation routine to restore default settings.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	private function reinitialize_plugin(): void {
		if ( class_exists( '\BRAGBookGallery\Includes\Core\Setup' ) ) {
			$setup = Setup::get_instance();
			if ( method_exists( $setup, 'activate' ) ) {
				$setup->activate();
			}
		}
	}

	/**
	 * Render JavaScript for factory reset handling
	 *
	 * Outputs inline JavaScript for factory reset button functionality.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs script tag
	 */
	private function render_javascript(): void {
		?>
		<script>
			// Ensure nonce is available globally for admin script
			if (typeof brag_book_gallery_admin === 'undefined') {
				window.brag_book_gallery_admin = {
					ajaxurl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
					nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_gallery_admin' ) ); ?>'
				};
			}
		</script>
		<?php
	}
}
