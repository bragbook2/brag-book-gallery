<?php
/**
 * Debug Diagnostic Tools Component
 *
 * Handles rendering of diagnostic tools in the debug interface. Provides access
 * to advanced troubleshooting tools including gallery checker and system analysis.
 * Utilizes modern PHP 8.2+ syntax and WordPress VIP coding standards.
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

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Diagnostic Tools class
 *
 * Provides access to advanced diagnostic and troubleshooting tools for analyzing
 * gallery functionality, page detection, shortcode validation, and other
 * gallery-specific issues.
 *
 * ## Features:
 * - Gallery page checker tool integration
 * - Advanced diagnostic capabilities
 * - Troubleshooting utilities
 *
 * ## Available Tools:
 * - Gallery Checker: Validates gallery pages and shortcodes
 *
 * @since 3.3.0
 */
final class Debug_Diagnostic_Tools {

	/**
	 * Render the diagnostic tools interface
	 *
	 * Displays available diagnostic tools with descriptions and controls.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		?>
		<div id="diagnostic-tools" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Diagnostic Tools', 'brag-book-gallery' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Advanced diagnostic tools for troubleshooting and debugging gallery functionality.', 'brag-book-gallery' ); ?>
			</p>

			<!-- Database Tables Check -->
			<div class="diagnostic-section database-tables-section">
				<?php $this->render_database_tables_check(); ?>
			</div>

			<!-- Gallery Checker Section -->
			<div class="diagnostic-section gallery-checker-section">
				<?php
				$gallery_checker_tool = ( new Gallery_Checker() )->render();
				?>
			</div>

		</div>
		<?php
	}

	/**
	 * Render the database tables check
	 *
	 * Verifies that required plugin database tables exist and reports row counts.
	 *
	 * @since 4.3.3
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_database_tables_check(): void {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'brag_sync_registry' => __( 'Sync Registry', 'brag-book-gallery' ),
			$wpdb->prefix . 'brag_sync_log'      => __( 'Sync Log', 'brag-book-gallery' ),
		];

		?>
		<h3><?php esc_html_e( 'Database Tables', 'brag-book-gallery' ); ?></h3>
		<div class="system-status-rows">
			<?php
			$index = 0;
			foreach ( $tables as $table_name => $label ) :
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
				$row_count = 0;

				if ( $exists ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" );
				}
				?>
				<div class="system-status-row<?php echo $index % 2 !== 0 ? ' system-status-row--alt' : ''; ?>">
					<span class="system-status-label"><?php echo esc_html( $label ); ?></span>
					<span class="system-status-value"><code><?php echo esc_html( $table_name ); ?></code></span>
					<span class="system-status-value"><?php echo $exists ? esc_html( number_format_i18n( $row_count ) ) . ' ' . esc_html__( 'rows', 'brag-book-gallery' ) : '—'; ?></span>
					<span class="system-status-indicator">
						<?php if ( $exists ) : ?>
							<span class="status-badge status-badge--success">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
							</span>
						<?php else : ?>
							<span class="status-badge status-badge--error">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
							</span>
						<?php endif; ?>
					</span>
				</div>
				<?php
				++$index;
			endforeach;
			?>
		</div>
		<?php
	}

	/**
	 * Get available diagnostic tools
	 *
	 * Returns an array of available diagnostic tools with their metadata.
	 *
	 * @since 3.3.0
	 *
	 * @return array Array of diagnostic tools
	 */
	public function get_available_tools(): array {
		return array(
			'gallery_checker' => array(
				'name'        => __( 'Gallery Checker', 'brag-book-gallery' ),
				'description' => __( 'Validates gallery pages, shortcodes, and configuration.', 'brag-book-gallery' ),
				'class'       => Gallery_Checker::class,
			),
		);
	}
}
