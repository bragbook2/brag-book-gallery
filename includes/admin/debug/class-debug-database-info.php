<?php
/**
 * Debug Database Info Component
 *
 * Handles rendering of database-related information in the debug interface.
 * Displays plugin options, their sizes, and gallery post/taxonomy counts for
 * diagnostic purposes. Utilizes modern PHP 8.2+ syntax and WordPress VIP coding
 * standards.
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
 * Debug Database Info class
 *
 * Provides comprehensive database statistics and information for troubleshooting
 * and optimization. Displays option sizes, post counts, and taxonomy counts
 * related to the BRAGBook Gallery plugin.
 *
 * ## Features:
 * - Top 10 largest plugin options display
 * - Published gallery count
 * - Draft gallery count
 * - Category count
 * - Procedure taxonomy count
 * - Human-readable size formatting
 *
 * ## Database Queries:
 * - Uses direct $wpdb queries for option size analysis
 * - Uses WordPress core functions for post and term counts
 * - All queries are optimized with LIMIT clauses
 *
 * @since 3.3.0
 */
final class Debug_Database_Info {

	/**
	 * Render the database information tables
	 *
	 * Displays plugin options with their sizes and gallery data statistics.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		global $wpdb;

		// Get option sizes.
		$options = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) as size
			FROM {$wpdb->options}
			WHERE option_name LIKE 'brag_book_gallery_%'
			ORDER BY size DESC
			LIMIT 10"
		);

		// Get post counts.
		$gallery_count = wp_count_posts( 'brag_gallery' )->publish ?? 0;
		$draft_count   = wp_count_posts( 'brag_gallery' )->draft ?? 0;
		?>
		<h3><?php esc_html_e( 'Plugin Options (Top 10 by Size)', 'brag-book-gallery' ); ?></h3>
		<?php if ( ! empty( $options ) ) : ?>
			<div class="debug-db-table-wrapper">
				<div class="debug-db-table-head">
					<span class="debug-db-table-th debug-db-table-th--name"><?php esc_html_e( 'Option Name', 'brag-book-gallery' ); ?></span>
					<span class="debug-db-table-th debug-db-table-th--size"><?php esc_html_e( 'Size', 'brag-book-gallery' ); ?></span>
				</div>
				<?php foreach ( $options as $index => $option ) : ?>
					<div class="debug-db-table-row<?php echo $index % 2 !== 0 ? ' debug-db-table-row--alt' : ''; ?>">
						<span class="debug-db-table-cell debug-db-table-cell--name"><code><?php echo esc_html( $option->option_name ); ?></code></span>
						<span class="debug-db-table-cell debug-db-table-cell--size"><?php echo esc_html( size_format( $option->size ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="debug-logs-empty">
				<p><?php esc_html_e( 'No plugin options found.', 'brag-book-gallery' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $gallery_count > 0 || $draft_count > 0 ) : ?>
			<h3><?php esc_html_e( 'Gallery Data', 'brag-book-gallery' ); ?></h3>
			<div class="system-status-rows">
				<div class="system-status-row">
					<span class="system-status-label"><?php esc_html_e( 'Published Galleries', 'brag-book-gallery' ); ?></span>
					<span class="system-status-value"><?php echo esc_html( (string) $gallery_count ); ?></span>
				</div>
				<div class="system-status-row system-status-row--alt">
					<span class="system-status-label"><?php esc_html_e( 'Draft Galleries', 'brag-book-gallery' ); ?></span>
					<span class="system-status-value"><?php echo esc_html( (string) $draft_count ); ?></span>
				</div>
				<div class="system-status-row">
					<span class="system-status-label"><?php esc_html_e( 'Categories', 'brag-book-gallery' ); ?></span>
					<span class="system-status-value"><?php echo esc_html( (string) wp_count_terms( 'brag_category' ) ); ?></span>
				</div>
				<div class="system-status-row system-status-row--alt">
					<span class="system-status-label"><?php esc_html_e( 'Procedures', 'brag-book-gallery' ); ?></span>
					<span class="system-status-value"><?php echo esc_html( (string) wp_count_terms( 'brag_procedure' ) ); ?></span>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Get total size of all plugin options
	 *
	 * Calculates the total database size used by all plugin options.
	 *
	 * @since 3.3.0
	 *
	 * @return int Total size in bytes
	 */
	public function get_total_options_size(): int {
		global $wpdb;

		$result = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) as total_size
			FROM {$wpdb->options}
			WHERE option_name LIKE 'brag_book_gallery_%'"
		);

		return (int) $result;
	}

	/**
	 * Get count of all plugin options
	 *
	 * Returns the total number of options stored by the plugin.
	 *
	 * @since 3.3.0
	 *
	 * @return int Number of options
	 */
	public function get_options_count(): int {
		global $wpdb;

		$result = $wpdb->get_var(
			"SELECT COUNT(*) as count
			FROM {$wpdb->options}
			WHERE option_name LIKE 'brag_book_gallery_%'"
		);

		return (int) $result;
	}

	/**
	 * Get all plugin options with their sizes
	 *
	 * Returns an array of all plugin options with their names and sizes.
	 *
	 * @since 3.3.0
	 *
	 * @param int $limit Maximum number of options to return. Default 10.
	 *
	 * @return array Array of objects with option_name and size properties
	 */
	public function get_options_by_size( int $limit = 10 ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, LENGTH(option_value) as size
				FROM {$wpdb->options}
				WHERE option_name LIKE 'brag_book_gallery_%'
				ORDER BY size DESC
				LIMIT %d",
				$limit
			)
		);

		return $results ?? [];
	}
}
