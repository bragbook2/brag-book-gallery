<?php
/**
 * Rewrite Debug Tool
 *
 * Provides debugging information for WordPress rewrite rules
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Admin\Debug_Tools;

use BRAGBookGallery\Includes\Core\Slug_Helper;
use BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite Debug class
 *
 * Provides comprehensive debugging information for WordPress rewrite rules,
 * including testing URLs, viewing current rules, and checking query variables.
 *
 * @since 3.0.0
 */
class Rewrite_Debug {

	/**
	 * Get checkmark or error SVG icon
	 *
	 * Returns an inline SVG icon appropriate for success or error states.
	 *
	 * @since 3.0.0
	 * @param bool $success Whether this is a success (true) or error (false) icon.
	 * @return string SVG HTML markup.
	 */
	private function get_check_icon( bool $success = true ): string {
		return match ( $success ) {
			true => '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#4caf50" style="vertical-align: middle; margin-right: 5px;"><path d="m423.23-309.85 268.92-268.92L650-620.92 423.23-394.15l-114-114L267.08-466l156.15 156.15ZM480.07-100q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.92-148.21t81.21-120.68q51.29-51.31 120.63-81.25Q401.1-860 479.93-860q78.84 0 148.21 29.92t120.68 81.21q51.31 51.29 81.25 120.63Q860-558.9 860-480.07q0 78.84-29.92 148.21t-81.21 120.68q-51.29 51.31-120.63 81.25Q558.9-100 480.07-100Z"/></svg>',
			false => '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#f44336" style="vertical-align: middle; margin-right: 5px;"><path d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/></svg>',
		};
	}

	/**
	 * Render the debug tool interface
	 *
	 * Outputs the complete HTML interface for the rewrite debug tool,
	 * including settings display, shortcode pages, rewrite rules, URL tester,
	 * query variables, and action buttons.
	 *
	 * @since 3.0.0
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

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			/**
			 * Initialize Rewrite Debug Tool
			 */
			class RewriteDebugTool {
				constructor() {
					this.initElements();
					this.bindEvents();
				}

				/**
				 * Initialize DOM elements
				 */
				initElements() {
					this.loadRulesBtn = document.getElementById('load-rewrite-rules');
					this.rulesContent = document.getElementById('rewrite-rules-content');
					this.testUrlInput = document.getElementById('test-url-input');
					this.testUrlBtn = document.getElementById('test-url-button');
					this.testUrlResult = document.getElementById('test-url-result');
					this.regenerateBtn = document.getElementById('regenerate-rules');
					this.regenerateResult = document.getElementById('regenerate-result');
				}

				/**
				 * Bind event handlers
				 */
				bindEvents() {
					this.loadRulesBtn?.addEventListener('click', () => this.loadRewriteRules());
					this.testUrlBtn?.addEventListener('click', () => this.testUrl());
					this.testUrlInput?.addEventListener('keypress', (e) => {
						if (e.key === 'Enter') {
							this.testUrl();
						}
					});
					this.regenerateBtn?.addEventListener('click', () => this.regenerateRules());
				}

				/**
				 * Load rewrite rules via AJAX
				 */
				async loadRewriteRules() {
					this.loadRulesBtn.disabled = true;
					this.rulesContent.innerHTML = '<p>Loading...</p>';

					try {
						const formData = new FormData();
						formData.append('action', 'brag_book_gallery_debug_tool');
						formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_debug_tools' ); ?>');
						formData.append('tool', 'rewrite-debug');
						formData.append('tool_action', 'get_rules');

						const response = await fetch(ajaxurl, {
							method: 'POST',
							body: formData
						});

						const data = await response.json();

						if (data.success) {
							this.rulesContent.innerHTML = data.data;
						} else {
							this.rulesContent.innerHTML = `<p style="color: red;">Error: ${data.data || 'Failed to load rules'}</p>`;
						}
					} catch (error) {
						this.rulesContent.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
					} finally {
						this.loadRulesBtn.disabled = false;
					}
				}

				/**
				 * Test URL parsing
				 */
				async testUrl() {
					const testUrl = this.testUrlInput.value.trim();
					if (!testUrl) {
						this.testUrlResult.innerHTML = '<p style="color: red;">Please enter a URL to test</p>';
						return;
					}

					this.testUrlBtn.disabled = true;
					this.testUrlResult.innerHTML = '<p>Testing...</p>';

					try {
						const formData = new FormData();
						formData.append('action', 'brag_book_gallery_debug_tool');
						formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_debug_tools' ); ?>');
						formData.append('tool', 'rewrite-debug');
						formData.append('tool_action', 'test_url');
						formData.append('test_url', testUrl);

						const response = await fetch(ajaxurl, {
							method: 'POST',
							body: formData
						});

						const data = await response.json();

						if (data.success) {
							this.testUrlResult.innerHTML = data.data;
						} else {
							this.testUrlResult.innerHTML = `<p style="color: red;">Error: ${data.data || 'Failed to test URL'}</p>`;
						}
					} catch (error) {
						this.testUrlResult.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
					} finally {
						this.testUrlBtn.disabled = false;
					}
				}

				/**
				 * Regenerate rewrite rules
				 */
				async regenerateRules() {
					if (!confirm('Are you sure you want to regenerate rewrite rules?')) {
						return;
					}

					this.regenerateBtn.disabled = true;
					this.regenerateResult.innerHTML = '<p>Regenerating...</p>';

					try {
						const formData = new FormData();
						formData.append('action', 'brag_book_gallery_debug_tool');
						formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_debug_tools' ); ?>');
						formData.append('tool', 'rewrite-debug');
						formData.append('tool_action', 'regenerate');

						const response = await fetch(ajaxurl, {
							method: 'POST',
							body: formData
						});

						const data = await response.json();

						if (data.success) {
							this.regenerateResult.innerHTML = `<p style="color: green;">${data.data}</p>`;
						} else {
							this.regenerateResult.innerHTML = `<p style="color: red;">Error: ${data.data || 'Failed to regenerate rules'}</p>`;
						}
					} catch (error) {
						this.regenerateResult.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
					} finally {
						this.regenerateBtn.disabled = false;
					}
				}
			}

			// Initialize the tool
			new RewriteDebugTool();
		});
		</script>
		<?php
	}

	/**
	 * Render settings section with enhanced diagnostics
	 *
	 * Displays current plugin settings relevant to rewrite rules with
	 * performance metrics and validation.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_settings(): void {
		$start_time = microtime( true );

		try {
			// Use helper function to get the first slug with caching
			$cache_key = 'settings_data';
			if ( isset( $this->cache[ $cache_key ] ) ) {
				$settings_data = $this->cache[ $cache_key ];
			} else {
				$settings_data = [
					'gallery_slug' => Slug_Helper::get_first_gallery_page_slug( '(not set)' ),
					'permalink_structure' => get_option( 'permalink_structure' ),
					'home_url' => home_url(),
					'site_url' => site_url(),
					'wp_version' => get_bloginfo( 'version' ),
					'plugin_version' => defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : 'Unknown',
				];
				$this->cache[ $cache_key ] = $settings_data;
			}

			$brag_book_gallery_page_slug = $settings_data['gallery_slug'];
			$permalink_structure = $settings_data['permalink_structure'];
			?>
		<div class="rewrite-table-wrapper">
			<table class="rewrite-table settings-table">
				<tbody>
					<tr class="table-row">
						<th class="setting-label"><?php esc_html_e( 'Combine Gallery Slug', 'brag-book-gallery' ); ?></th>
						<td class="setting-value">
							<span class="value-badge"><?php echo esc_html( $brag_book_gallery_page_slug ); ?></span>
						</td>
					</tr>
					<tr class="table-row">
						<th class="setting-label"><?php esc_html_e( 'Permalink Structure', 'brag-book-gallery' ); ?></th>
						<td class="setting-value">
							<span class="value-text"><?php echo esc_html( $permalink_structure ?: 'Plain' ); ?></span>
						</td>
					</tr>
					<tr class="table-row">
						<th class="setting-label"><?php esc_html_e( 'Home URL', 'brag-book-gallery' ); ?></th>
						<td class="setting-value">
							<a href="<?php echo esc_url( home_url() ); ?>" class="value-link" target="_blank"><?php echo esc_url( home_url() ); ?></a>
						</td>
					</tr>
					<tr class="table-row">
						<th class="setting-label"><?php esc_html_e( 'WordPress Version', 'brag-book-gallery' ); ?></th>
						<td class="setting-value">
							<span class="value-text"><?php echo esc_html( $settings_data['wp_version'] ); ?></span>
						</td>
					</tr>
					<tr class="table-row">
						<th class="setting-label"><?php esc_html_e( 'Plugin Version', 'brag-book-gallery' ); ?></th>
						<td class="setting-value">
							<span class="value-badge"><?php echo esc_html( $settings_data['plugin_version'] ); ?></span>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php

			$this->performance_metrics['render_settings'] = microtime( true ) - $start_time;

		} catch ( Exception $e ) {
			error_log( '[BRAGBook Rewrite Debug] Error rendering settings: ' . $e->getMessage() );
			echo '<p class="error">' . esc_html__( 'Error loading settings', 'brag-book-gallery' ) . '</p>';
		}
	}

	/**
	 * Render pages with gallery shortcode
	 *
	 * Displays all pages that contain the gallery shortcode.
	 *
	 * @since 3.0.0
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	private function render_shortcode_pages(): void {
		global $wpdb;

		// Direct query is necessary here for performance (searching shortcode in content)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_title, post_content
				FROM {$wpdb->posts}
				WHERE post_content LIKE %s
				AND post_status = 'publish'
				AND post_type = 'page'",
				'%[brag_book_gallery%'
			)
		);

		if ( empty( $pages ) ) {
			echo '<p>' . esc_html__( 'No pages found with [brag_book_gallery] shortcode.', 'brag-book-gallery' ) . '</p>';
			return;
		}
		?>
		<div class="rewrite-table-wrapper">
			<table class="rewrite-table pages-table">
				<thead>
					<tr>
						<th class="id-column"><?php esc_html_e( 'ID', 'brag-book-gallery' ); ?></th>
						<th class="title-column"><?php esc_html_e( 'Title', 'brag-book-gallery' ); ?></th>
						<th class="slug-column"><?php esc_html_e( 'Slug', 'brag-book-gallery' ); ?></th>
						<th class="actions-column"><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $pages as $page ) : ?>
					<tr class="table-row">
						<td class="id-column">
							<span class="id-badge">#<?php echo esc_html( (string) $page->ID ); ?></span>
						</td>
						<td class="title-column">
							<span class="page-title"><?php echo esc_html( $page->post_title ); ?></span>
						</td>
						<td class="slug-column">
							<span class="page-slug"><?php echo esc_html( $page->post_name ); ?></span>
						</td>
						<td class="actions-column">
							<?php $permalink = get_permalink( (int) $page->ID ); ?>
							<?php if ( $permalink ) : ?>
								<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" class="table-btn table-btn-view">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
										<polyline points="15 3 21 3 21 9"></polyline>
										<line x1="10" y1="14" x2="21" y2="3"></line>
									</svg>
									<span><?php esc_html_e( 'View', 'brag-book-gallery' ); ?></span>
								</a>
							<?php else : ?>
								<span class="no-action"><?php esc_html_e( 'N/A', 'brag-book-gallery' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render URL tester interface
	 *
	 * Provides an interface for testing how URLs are parsed by rewrite rules.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_url_tester(): void {
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = Slug_Helper::get_first_gallery_page_slug( 'before-after' );
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
	 * Render query variables status
	 *
	 * Displays the registration status of gallery-related query variables.
	 *
	 * @since 3.0.0
	 * @global \WP $wp Current WordPress environment instance.
	 * @return void
	 */
	private function render_query_vars(): void {
		global $wp;

		// Query variables actually used by the gallery system
		$gallery_vars = [
			'procedure_title',    // Procedure name in URLs (actively used)
			'case_suffix',       // Case identifier (actively used in URL parsing)
			'filter_procedure',  // Procedure filter (actively used)
			'filter_category',   // Category filter (actively used)
			'favorites_page',    // Favorites page indicator (actively used)
			'brag_book_gallery_view', // URL Router gallery view (actively used)
			'brag_book_gallery_cae', // URL Router gallery case (actively used)
		];

		// Debug information - show all registered query vars
		$current_vars = array_merge( $wp->public_query_vars, $wp->private_query_vars );
		$all_filtered_vars = apply_filters( 'query_vars', $current_vars );
		$gallery_specific_vars = array_filter( $all_filtered_vars, function( $var ) {
			return strpos( $var, 'brag' ) !== false ||
				   strpos( $var, 'procedure' ) !== false ||
				   strpos( $var, 'case' ) !== false ||
				   strpos( $var, 'favorite' ) !== false;
		});
		?>
		<div class="rewrite-table-wrapper">
			<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
			<details style="margin-bottom: 20px;">
				<summary style="cursor: pointer; font-weight: 600; color: #666;">
					<?php esc_html_e( 'Debug: All Gallery-Related Query Variables', 'brag-book-gallery' ); ?>
					<small>(<?php echo count( $gallery_specific_vars ); ?> found)</small>
				</summary>
				<div style="background: #f5f5f5; padding: 10px; margin-top: 10px; font-family: monospace; font-size: 12px;">
					<?php if ( ! empty( $gallery_specific_vars ) ) : ?>
						<strong>Gallery-related query vars:</strong><br>
						<?php foreach ( $gallery_specific_vars as $var ) : ?>
						<code><?php echo esc_html( $var ); ?></code><br>
						<?php endforeach; ?>
					<?php else : ?>
						<em>No gallery-related query variables found.</em>
					<?php endif; ?>
				</div>
			</details>
			<?php endif; ?>

			<table class="rewrite-table query-vars-table">
				<thead>
					<tr>
						<th class="variable-column"><?php esc_html_e( 'Query Variable', 'brag-book-gallery' ); ?></th>
						<th class="status-column"><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $gallery_vars as $var ) : ?>
					<?php
					// Comprehensive check for query variable registration
					$registered_vars = apply_filters( 'query_vars', [] );

					// Check multiple sources for query variable registration
					$is_registered = in_array( $var, $wp->public_query_vars, true ) ||
									in_array( $var, $wp->private_query_vars, true ) ||
									in_array( $var, $registered_vars, true ) ||
									isset( $wp->extra_query_vars[ $var ] );

					// Additional check: Try to get the filtered query vars directly
					if ( ! $is_registered ) {
						$all_query_vars = [];
						// Get all query vars by applying the filter with current vars
						$current_vars = array_merge( $wp->public_query_vars, $wp->private_query_vars );
						$all_filtered_vars = apply_filters( 'query_vars', $current_vars );
						$is_registered = in_array( $var, $all_filtered_vars, true );
					}
					?>
					<tr class="table-row">
						<td class="variable-column">
							<span class="var-name"><?php echo esc_html( $var ); ?></span>
						</td>
						<td class="status-column">
							<?php if ( $is_registered ) : ?>
								<div class="status-badge status-success">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
										<polyline points="22 4 12 14.01 9 11.01"></polyline>
									</svg>
									<span><?php esc_html_e( 'Registered', 'brag-book-gallery' ); ?></span>
								</div>
							<?php else : ?>
								<div class="status-badge status-error">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<circle cx="12" cy="12" r="10"></circle>
										<line x1="15" y1="9" x2="9" y2="15"></line>
										<line x1="9" y1="9" x2="15" y2="15"></line>
									</svg>
									<span><?php esc_html_e( 'Not registered', 'brag-book-gallery' ); ?></span>
								</div>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Execute tool actions via AJAX
	 *
	 * Handles AJAX requests for various debug tool actions.
	 *
	 * @since 3.0.0
	 * @param string $action Action to execute.
	 * @param array  $data   Request data.
	 * @return mixed Response data for the action.
	 * @throws \Exception If an invalid action is provided.
	 */
	public function execute( string $action, array $data ): mixed {
		return match ( $action ) {
			'get_rules' => $this->get_rewrite_rules(),
			'test_url' => $this->test_url( $data['test_url'] ?? '' ),
			'regenerate' => $this->regenerate_rules(),
			default => throw new \Exception( 'Invalid action: ' . $action ),
		};
	}

	/**
	 * Get and format rewrite rules
	 *
	 * Retrieves WordPress rewrite rules and filters for gallery-related rules.
	 *
	 * @since 3.0.0
	 * @global \WP_Rewrite $wp_rewrite WordPress rewrite component.
	 * @return string HTML formatted rewrite rules.
	 */
	private function get_rewrite_rules(): string {
		global $wp_rewrite;

		$rules = $wp_rewrite->wp_rewrite_rules();
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = Slug_Helper::get_first_gallery_page_slug();

		$output = '<div class="rewrite-rules-display">';
		$output .= '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 400px;">';

		$found_rules = false;
		foreach ( $rules as $pattern => $query ) {
			$is_gallery_rule = ( $brag_book_gallery_page_slug && str_contains( $pattern, $brag_book_gallery_page_slug ) ) ||
				str_contains( $pattern, 'gallery' ) ||
				str_contains( $pattern, 'before-after' ) ||
				str_contains( $query, 'filter_procedure' ) ||
				str_contains( $query, 'procedure_title' ) ||
				str_contains( $query, 'case_id' );

			if ( $is_gallery_rule ) {
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
	 * Test URL parsing against rewrite rules
	 *
	 * Tests a given URL against WordPress rewrite rules to determine
	 * which rule matches and what query variables would be set.
	 *
	 * @since 3.0.0
	 * @global \WP_Rewrite $wp_rewrite WordPress rewrite component.
	 * @param string $test_url URL to test.
	 * @return string HTML formatted test results.
	 */
	private function test_url( string $test_url ): string {
		global $wp_rewrite;

		// Ensure test_url is never null for PHP 8.2 compatibility
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
	 * Regenerate WordPress rewrite rules
	 *
	 * Forces regeneration of WordPress rewrite rules, including
	 * custom gallery rules.
	 *
	 * @since 3.0.0
	 * @return string Success message.
	 */
	private function regenerate_rules(): string {
		// Register custom rules
		if ( class_exists( Rewrite_Rules_Handler::class ) ) {
			Rewrite_Rules_Handler::custom_rewrite_rules();
		}

		// Flush rules
		flush_rewrite_rules( true );

		return __( 'Rewrite rules regenerated successfully! Refresh the page to see updated rules.', 'brag-book-gallery' );
	}
}
