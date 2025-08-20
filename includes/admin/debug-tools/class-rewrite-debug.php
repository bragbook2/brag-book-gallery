<?php
/**
 * Rewrite Debug Tool
 *
 * Provides debugging information for WordPress rewrite rules
 *
 * @package BragBookGallery
 * @since 3.0.0
 */

namespace BragBookGallery\Admin\Debug_Tools;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite Debug class
 *
 * @since 3.0.0
 */
class Rewrite_Debug {

	/**
	 * Get checkmark SVG icon
	 *
	 * @param bool $success Whether this is a success (true) or error (false) icon.
	 * @return string SVG HTML.
	 */
	private function get_check_icon( bool $success = true ): string {
		if ( $success ) {
			// Green checkmark circle for success
			return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#4caf50" style="vertical-align: middle; margin-right: 5px;"><path d="m423.23-309.85 268.92-268.92L650-620.92 423.23-394.15l-114-114L267.08-466l156.15 156.15ZM480.07-100q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.92-148.21t81.21-120.68q51.29-51.31 120.63-81.25Q401.1-860 479.93-860q78.84 0 148.21 29.92t120.68 81.21q51.31 51.29 81.25 120.63Q860-558.9 860-480.07q0 78.84-29.92 148.21t-81.21 120.68q-51.29 51.31-120.63 81.25Q558.9-100 480.07-100Z"/></svg>';
		} else {
			// Red X for error/not set
			return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#f44336" style="vertical-align: middle; margin-right: 5px;"><path d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/></svg>';
		}
	}

	/**
	 * Render the debug tool interface
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="rewrite-debug-tool">
			<h2><?php esc_html_e( 'Rewrite Rules Debug', 'brag-book-gallery' ); ?></h2>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Current Settings', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_settings(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Pages with Gallery Shortcode', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_shortcode_pages(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Gallery Rewrite Rules', 'brag-book-gallery' ); ?></h3>
				<button class="button" id="load-rewrite-rules">
					<?php esc_html_e( 'Load Rewrite Rules', 'brag-book-gallery' ); ?>
				</button>
				<div id="rewrite-rules-content"></div>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Test URL Parsing', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_url_tester(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Query Variables', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_query_vars(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></h3>
				<button class="button button-primary" id="regenerate-rules">
					<?php esc_html_e( 'Force Regenerate Rewrite Rules', 'brag-book-gallery' ); ?>
				</button>
				<div id="regenerate-result"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings section
	 *
	 * @return void
	 */
	private function render_settings(): void {
		// Use helper function to get the first slug
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug( '(not set)' );
		$permalink_structure  = get_option( 'permalink_structure' );
		?>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Combine Gallery Slug', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $brag_book_gallery_page_slug ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Permalink Structure', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $permalink_structure ?: 'Plain' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Home URL', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_url( home_url() ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render pages with shortcode
	 *
	 * @return void
	 */
	private function render_shortcode_pages(): void {
		global $wpdb;

		$pages = $wpdb->get_results(
			"SELECT ID, post_name, post_title, post_content
			FROM {$wpdb->posts}
			WHERE post_content LIKE '%[brag_book_gallery%'
			AND post_status = 'publish'
			AND post_type = 'page'"
		);

		if ( empty( $pages ) ) {
			echo '<p>' . esc_html__( 'No pages found with [brag_book_gallery] shortcode.', 'brag-book-gallery' ) . '</p>';
			return;
		}
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Title', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'View', 'brag-book-gallery' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pages as $page ) : ?>
				<tr>
					<td><?php echo esc_html( $page->ID ); ?></td>
					<td><?php echo esc_html( $page->post_title ); ?></td>
					<td><?php echo esc_html( $page->post_name ); ?></td>
					<td>
						<?php $permalink = get_permalink( $page->ID ); ?>
						<?php if ( $permalink ) : ?>
							<a href="<?php echo esc_url( $permalink ); ?>" target="_blank">
								<?php esc_html_e( 'View', 'brag-book-gallery' ); ?>
							</a>
						<?php else : ?>
							<?php esc_html_e( 'N/A', 'brag-book-gallery' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render URL tester
	 *
	 * @return void
	 */
	private function render_url_tester(): void {
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug( 'before-after' );
		?>
		<div class="url-tester">
			<p><?php esc_html_e( 'Test how URLs are parsed by the rewrite rules:', 'brag-book-gallery' ); ?></p>
			<input type="text"
				   id="test-url-input"
				   class="regular-text"
				   placeholder="/<?php echo esc_attr( $brag_book_gallery_page_slug ); ?>/tummy-tuck/12345"
				   value="/<?php echo esc_attr( $brag_book_gallery_page_slug ); ?>/tummy-tuck">
			<button class="button" id="test-url-button">
				<?php esc_html_e( 'Test URL', 'brag-book-gallery' ); ?>
			</button>
			<div id="test-url-result"></div>
		</div>
		<?php
	}

	/**
	 * Render query variables
	 *
	 * @return void
	 */
	private function render_query_vars(): void {
		global $wp;

		$gallery_vars = [
			'procedure_title',
			'case_id',
			'filter_procedure',
			'filter_category',
			'favorites_section',
		];
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Query Variable', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $gallery_vars as $var ) : ?>
				<?php
				// Check both in WP object and through the query_vars filter
				$registered_vars = apply_filters( 'query_vars', [] );
				$is_registered = in_array( $var, $wp->public_query_vars, true ) ||
								in_array( $var, $wp->private_query_vars, true ) ||
								in_array( $var, $registered_vars, true ) ||
								isset( $wp->extra_query_vars[ $var ] );
				?>
				<tr>
					<td><?php echo esc_html( $var ); ?></td>
					<td>
						<?php if ( $is_registered ) : ?>
							<?php echo $this->get_check_icon( true ); ?>
							<span style="color: green;"><?php esc_html_e( 'Registered', 'brag-book-gallery' ); ?></span>
						<?php else : ?>
							<?php echo $this->get_check_icon( false ); ?>
							<span style="color: red;"><?php esc_html_e( 'Not registered', 'brag-book-gallery' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Execute tool actions via AJAX
	 *
	 * @param string $action Action to execute.
	 * @param array  $data   Request data.
	 * @return mixed
	 */
	public function execute( string $action, array $data ) {
		switch ( $action ) {
			case 'get_rules':
				return $this->get_rewrite_rules();

			case 'test_url':
				return $this->test_url( $data['test_url'] ?? '' );

			case 'regenerate':
				return $this->regenerate_rules();

			default:
				throw new \Exception( 'Invalid action' );
		}
	}

	/**
	 * Get and format rewrite rules
	 *
	 * @return string
	 */
	private function get_rewrite_rules(): string {
		global $wp_rewrite;

		$rules = $wp_rewrite->wp_rewrite_rules();
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();

		$output = '<div class="rewrite-rules-display">';
		$output .= '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 400px;">';

		$found_rules = false;
		foreach ( $rules as $pattern => $query ) {
			if (
				( $brag_book_gallery_page_slug && strpos( $pattern, $brag_book_gallery_page_slug ) !== false ) ||
				strpos( $pattern, 'gallery' ) !== false ||
				strpos( $pattern, 'before-after' ) !== false ||
				strpos( $query, 'filter_procedure' ) !== false ||
				strpos( $query, 'procedure_title' ) !== false ||
				strpos( $query, 'case_id' ) !== false
			) {
				$output .= htmlspecialchars( $pattern ) . "\n    => " . htmlspecialchars( $query ) . "\n\n";
				$found_rules = true;
			}
		}

		if ( ! $found_rules ) {
			$output .= "No gallery-related rewrite rules found!\n";
		}

		$output .= '</pre></div>';

		return $output;
	}

	/**
	 * Test URL parsing
	 *
	 * @param string $test_url URL to test.
	 * @return string
	 */
	private function test_url( string $test_url ): string {
		global $wp_rewrite;

		// Ensure test_url is never null for PHP 8.2 compatibility
		$test_url = $test_url ?: '';
		$test_url = ltrim( $test_url, '/' );
		$rules = $wp_rewrite->wp_rewrite_rules();

		$output = '<div class="url-test-result">';
		$output .= '<h4>Testing: ' . esc_html( $test_url ) . '</h4>';

		$matched = false;
		foreach ( $rules as $pattern => $query ) {
			if ( preg_match( '#' . $pattern . '#', $test_url, $matches ) ) {
				$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . 'Matches pattern: <code>' . esc_html( $pattern ) . '</code></p>';
				$output .= '<p>Query: <code>' . esc_html( $query ) . '</code></p>';

				// Show resolved query
				$query_with_matches = $query;
				foreach ( $matches as $i => $match ) {
					$query_with_matches = str_replace( '$matches[' . $i . ']', $match, $query_with_matches );
				}
				$output .= '<p>Resolved query: <code>' . esc_html( $query_with_matches ) . '</code></p>';

				$matched = true;
				break;
			}
		}

		if ( ! $matched ) {
			$output .= '<p style="color: red;">' . $this->get_check_icon( false ) . 'No matching rewrite rule found!</p>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Regenerate rewrite rules
	 *
	 * @return string
	 */
	private function regenerate_rules(): string {
		// Register custom rules
		if ( class_exists( 'BragBookGallery\Extend\Shortcodes' ) ) {
			\BragBookGallery\Extend\Shortcodes::custom_rewrite_rules();
		}

		// Flush rules
		flush_rewrite_rules( true );

		return __( 'Rewrite rules regenerated successfully! Refresh the page to see updated rules.', 'brag-book-gallery' );
	}
}
