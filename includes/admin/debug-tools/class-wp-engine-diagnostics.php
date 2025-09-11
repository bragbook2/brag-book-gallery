<?php
/**
 * WP Engine Diagnostics Tool
 *
 * Comprehensive diagnostic tool specifically designed for WP Engine environments
 * to test rewrite rules, query variables, and URL routing functionality.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.2.4
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Debug_Tools;

use BRAGBookGallery\Includes\Core\Slug_Helper;
use BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Engine Diagnostics class
 *
 * Provides comprehensive testing and validation specifically for WP Engine
 * hosting environments including Nginx, object caching, and rewrite rules.
 *
 * @since 3.2.4
 */
class WP_Engine_Diagnostics {

	/**
	 * Run comprehensive WP Engine diagnostics
	 *
	 * Tests all aspects of rewrite rules and query variables specifically
	 * for WP Engine hosting environment.
	 *
	 * @since 3.2.4
	 * @return array Comprehensive diagnostic results
	 */
	public static function run_diagnostics(): array {
		$results = [
			'environment' => self::check_wp_engine_environment(),
			'rewrite_rules' => self::test_rewrite_rules(),
			'query_vars' => self::test_query_variables(),
			'url_routing' => self::test_url_routing(),
			'cache_status' => self::check_cache_status(),
			'recommendations' => [],
		];

		// Generate recommendations based on results
		$results['recommendations'] = self::generate_recommendations( $results );

		return $results;
	}

	/**
	 * Check WP Engine environment specifics
	 *
	 * @since 3.2.4
	 * @return array Environment check results
	 */
	private static function check_wp_engine_environment(): array {
		$checks = [
			'is_wp_engine' => function_exists( 'brag_book_is_wp_engine' ) ? brag_book_is_wp_engine() : false,
			'php_version' => PHP_VERSION,
			'php_sapi' => PHP_SAPI,
			'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
			'wp_cache' => defined( 'WP_CACHE' ) ? WP_CACHE : false,
			'wp_debug' => defined( 'WP_DEBUG' ) ? WP_DEBUG : false,
			'object_cache' => wp_using_ext_object_cache(),
			'memory_limit' => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
		];

		// WP Engine specific constants
		$wp_engine_constants = [
			'WPE_APIKEY' => defined( 'WPE_APIKEY' ),
			'PWP_NAME' => defined( 'PWP_NAME' ),
			'WPE_CLUSTER' => defined( 'WPE_CLUSTER' ) ? WPE_CLUSTER : 'Not defined',
		];

		return [
			'status' => $checks['is_wp_engine'] ? 'WP Engine Detected' : 'Not WP Engine',
			'checks' => $checks,
			'wp_engine_constants' => $wp_engine_constants,
		];
	}

	/**
	 * Test rewrite rules registration and functionality
	 *
	 * @since 3.2.4
	 * @return array Rewrite rules test results
	 */
	private static function test_rewrite_rules(): array {
		global $wp_rewrite;

		$results = [
			'permalink_structure' => get_option( 'permalink_structure' ),
			'rewrite_rules_count' => count( $wp_rewrite->wp_rewrite_rules() ),
			'gallery_rules' => [],
			'rule_conflicts' => [],
		];

		// Get gallery page slug for testing
		$gallery_slug = Slug_Helper::get_first_gallery_page_slug( 'gallery' );
		$results['gallery_slug'] = $gallery_slug;

		// Check for gallery-specific rewrite rules
		$all_rules = $wp_rewrite->wp_rewrite_rules();
		foreach ( $all_rules as $pattern => $query ) {
			if ( strpos( $pattern, $gallery_slug ) !== false || strpos( $query, 'brag_book' ) !== false ) {
				$results['gallery_rules'][$pattern] = $query;
			}
		}

		// Test specific URL patterns
		$test_urls = [
			"$gallery_slug/",
			"$gallery_slug/facelift/",
			"$gallery_slug/facelift/19256/",
			"$gallery_slug/myfavorites/",
		];

		$results['url_tests'] = [];
		foreach ( $test_urls as $test_url ) {
			$results['url_tests'][$test_url] = self::test_url_parsing( $test_url );
		}

		return $results;
	}

	/**
	 * Test query variables registration
	 *
	 * @since 3.2.4
	 * @return array Query variables test results
	 */
	private static function test_query_variables(): array {
		global $wp;

		$required_vars = [
			'filter_procedure',
			'case_suffix',
			'procedure_title',
			'favorites_page',
			'brag_book_gallery_page',
		];

		$results = [
			'registered_vars' => $wp->public_query_vars,
			'required_vars' => [],
			'missing_vars' => [],
		];

		foreach ( $required_vars as $var ) {
			$is_registered = in_array( $var, $wp->public_query_vars, true );
			$results['required_vars'][$var] = $is_registered;
			
			if ( ! $is_registered ) {
				$results['missing_vars'][] = $var;
			}
		}

		return $results;
	}

	/**
	 * Test URL routing functionality
	 *
	 * @since 3.2.4
	 * @return array URL routing test results
	 */
	private static function test_url_routing(): array {
		// Test current request parsing
		$current_url = self::get_current_request_url();
		$parsed = self::parse_url_for_testing( $current_url );

		return [
			'current_url' => $current_url,
			'parsed_url' => $parsed,
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'query_string' => $_SERVER['QUERY_STRING'] ?? '',
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
			'url_parsing' => self::test_url_parsing_methods(),
		];
	}

	/**
	 * Check cache status and functionality
	 *
	 * @since 3.2.4
	 * @return array Cache status results
	 */
	private static function check_cache_status(): array {
		$results = [
			'object_cache_active' => wp_using_ext_object_cache(),
			'wp_cache_available' => function_exists( 'wp_cache_get' ),
			'cache_functions' => [],
		];

		// Test cache functions
		$cache_functions = [
			'wp_cache_get',
			'wp_cache_set',
			'wp_cache_delete',
			'wp_cache_flush',
			'wp_cache_flush_group',
		];

		foreach ( $cache_functions as $func ) {
			$results['cache_functions'][$func] = function_exists( $func );
		}

		// Test WP Engine specific cache
		if ( class_exists( '\WpeCommon' ) ) {
			$results['wpe_common_available'] = true;
			$results['wpe_methods'] = [
				'purge_memcached' => method_exists( '\WpeCommon', 'purge_memcached' ),
				'purge_varnish_cache' => method_exists( '\WpeCommon', 'purge_varnish_cache' ),
			];
		} else {
			$results['wpe_common_available'] = false;
		}

		return $results;
	}

	/**
	 * Test URL parsing for a specific URL
	 *
	 * @since 3.2.4
	 * @param string $test_url URL to test
	 * @return array Test results
	 */
	private static function test_url_parsing( string $test_url ): array {
		global $wp_rewrite;

		// Use WordPress internal URL parsing
		$request = parse_url( $test_url, PHP_URL_PATH );
		$request = trim( $request, '/' );

		$query_vars = [];
		$matched_rule = '';

		// Test against rewrite rules
		$rewrite_rules = $wp_rewrite->wp_rewrite_rules();
		foreach ( $rewrite_rules as $pattern => $query ) {
			if ( preg_match( "#^$pattern#", $request, $matches ) ) {
				$matched_rule = $pattern;
				
				// Parse the query string
				parse_str( $query, $query_vars );
				
				// Replace matches in query vars
				foreach ( $query_vars as $key => $value ) {
					if ( strpos( $value, '$matches[' ) !== false ) {
						for ( $i = 1; $i < count( $matches ); $i++ ) {
							$value = str_replace( "\$matches[$i]", $matches[$i], $value );
						}
						$query_vars[$key] = $value;
					}
				}
				break;
			}
		}

		return [
			'url' => $test_url,
			'request' => $request,
			'matched_rule' => $matched_rule,
			'query_vars' => $query_vars,
			'would_match' => ! empty( $matched_rule ),
		];
	}

	/**
	 * Test different URL parsing methods
	 *
	 * @since 3.2.4
	 * @return array URL parsing methods test results
	 */
	private static function test_url_parsing_methods(): array {
		$methods = [
			'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
			'HTTP_X_ORIGINAL_URL' => $_SERVER['HTTP_X_ORIGINAL_URL'] ?? null,
			'HTTP_X_REWRITE_URL' => $_SERVER['HTTP_X_REWRITE_URL'] ?? null,
			'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
			'PATH_INFO' => $_SERVER['PATH_INFO'] ?? null,
			'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? null,
		];

		return array_filter( $methods, fn( $value ) => $value !== null );
	}

	/**
	 * Get current request URL safely
	 *
	 * @since 3.2.4
	 * @return string Current request URL
	 */
	private static function get_current_request_url(): string {
		$protocol = is_ssl() ? 'https://' : 'http://';
		$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		
		return $protocol . $host . $uri;
	}

	/**
	 * Parse URL for testing purposes
	 *
	 * @since 3.2.4
	 * @param string $url URL to parse
	 * @return array Parsed URL components
	 */
	private static function parse_url_for_testing( string $url ): array {
		$parsed = parse_url( $url );
		
		if ( isset( $parsed['path'] ) ) {
			$path_parts = explode( '/', trim( $parsed['path'], '/' ) );
			$parsed['path_parts'] = array_filter( $path_parts );
		}

		return $parsed;
	}

	/**
	 * Generate recommendations based on diagnostic results
	 *
	 * @since 3.2.4
	 * @param array $results Diagnostic results
	 * @return array Recommendations
	 */
	private static function generate_recommendations( array $results ): array {
		$recommendations = [];

		// Environment recommendations
		if ( ! $results['environment']['checks']['is_wp_engine'] ) {
			$recommendations[] = [
				'type' => 'warning',
				'message' => 'WP Engine environment not detected. Some optimizations may not apply.',
			];
		}

		// Cache recommendations
		if ( ! $results['cache_status']['object_cache_active'] ) {
			$recommendations[] = [
				'type' => 'warning',
				'message' => 'Object cache not active. Enable object caching for better performance.',
			];
		}

		// Rewrite rules recommendations
		if ( empty( $results['rewrite_rules']['gallery_rules'] ) ) {
			$recommendations[] = [
				'type' => 'error',
				'message' => 'No gallery rewrite rules found. Rules may need to be flushed.',
				'action' => 'Use Debug Tools → Rewrite Flush to regenerate rules.',
			];
		}

		// Query variables recommendations
		if ( ! empty( $results['query_vars']['missing_vars'] ) ) {
			$recommendations[] = [
				'type' => 'error',
				'message' => 'Missing required query variables: ' . implode( ', ', $results['query_vars']['missing_vars'] ),
				'action' => 'Check plugin initialization and query variable registration.',
			];
		}

		// URL routing recommendations
		$failed_tests = array_filter( 
			$results['rewrite_rules']['url_tests'], 
			fn( $test ) => ! $test['would_match'] 
		);

		if ( ! empty( $failed_tests ) ) {
			$recommendations[] = [
				'type' => 'warning',
				'message' => 'Some URL patterns are not matching rewrite rules.',
				'action' => 'Check permalink structure and flush rewrite rules.',
			];
		}

		return $recommendations;
	}

	/**
	 * Render diagnostics page
	 *
	 * @since 3.2.4
	 * @return void
	 */
	public static function render_diagnostics_page(): void {
		?>
		<div class="brag-book-gallery-diagnostics-wrapper">
			<div class="brag-book-gallery-section-header">
				<h2><?php esc_html_e( 'WP Engine Diagnostics', 'brag-book-gallery' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Comprehensive testing for rewrite rules and URL routing on WP Engine hosting.', 'brag-book-gallery' ); ?>
				</p>
			</div>

			<div id="wp-engine-diagnostics-container">
				<button type="button" id="run-wp-engine-diagnostics" class="button button-primary">
					<?php esc_html_e( 'Run WP Engine Diagnostics', 'brag-book-gallery' ); ?>
				</button>
				
				<div id="diagnostics-results" style="display: none;">
					<!-- Results will be populated via JavaScript -->
				</div>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const runButton = document.getElementById('run-wp-engine-diagnostics');
			const resultsContainer = document.getElementById('diagnostics-results');

			runButton.addEventListener('click', function() {
				runButton.disabled = true;
				runButton.textContent = '<?php esc_html_e( 'Running Diagnostics...', 'brag-book-gallery' ); ?>';
				
				// AJAX call to run diagnostics
				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'brag_book_gallery_wp_engine_diagnostics',
						_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wp_engine_diagnostics' ) ); ?>'
					})
				})
				.then(response => response.json())
				.then(data => {
					resultsContainer.innerHTML = data.data.html;
					resultsContainer.style.display = 'block';
				})
				.catch(error => {
					console.error('Diagnostics error:', error);
					resultsContainer.innerHTML = '<div class="notice notice-error"><p>Error running diagnostics. Check console for details.</p></div>';
					resultsContainer.style.display = 'block';
				})
				.finally(() => {
					runButton.disabled = false;
					runButton.textContent = '<?php esc_html_e( 'Run WP Engine Diagnostics', 'brag-book-gallery' ); ?>';
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Generate HTML for diagnostics results
	 *
	 * @since 3.2.4
	 * @param array $results Diagnostic results
	 * @return string HTML output
	 */
	public static function generate_results_html( array $results ): string {
		ob_start();
		?>
		<div class="brag-book-gallery-diagnostics-results">
			
			<!-- Recommendations -->
			<?php if ( ! empty( $results['recommendations'] ) ) : ?>
			<div class="diagnostics-section">
				<h3><?php esc_html_e( 'Recommendations', 'brag-book-gallery' ); ?></h3>
				<?php foreach ( $results['recommendations'] as $rec ) : ?>
					<div class="notice notice-<?php echo esc_attr( $rec['type'] ); ?>">
						<p><strong><?php echo esc_html( $rec['message'] ); ?></strong></p>
						<?php if ( isset( $rec['action'] ) ) : ?>
							<p><em><?php echo esc_html( $rec['action'] ); ?></em></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<!-- Environment -->
			<div class="diagnostics-section">
				<h3><?php esc_html_e( 'Environment', 'brag-book-gallery' ); ?></h3>
				<div class="diagnostics-card">
					<p><strong>Status:</strong> <?php echo esc_html( $results['environment']['status'] ); ?></p>
					<table class="widefat striped">
						<tbody>
							<?php foreach ( $results['environment']['checks'] as $key => $value ) : ?>
							<tr>
								<td><?php echo esc_html( $key ); ?></td>
								<td><?php echo esc_html( is_bool( $value ) ? ( $value ? 'Yes' : 'No' ) : $value ); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Rewrite Rules -->
			<div class="diagnostics-section">
				<h3><?php esc_html_e( 'Rewrite Rules', 'brag-book-gallery' ); ?></h3>
				<div class="diagnostics-card">
					<p><strong>Gallery Slug:</strong> <?php echo esc_html( $results['rewrite_rules']['gallery_slug'] ); ?></p>
					<p><strong>Total Rules:</strong> <?php echo esc_html( $results['rewrite_rules']['rewrite_rules_count'] ); ?></p>
					<p><strong>Gallery Rules Found:</strong> <?php echo esc_html( count( $results['rewrite_rules']['gallery_rules'] ) ); ?></p>
					
					<?php if ( ! empty( $results['rewrite_rules']['gallery_rules'] ) ) : ?>
					<details>
						<summary>Gallery Rules</summary>
						<table class="widefat striped">
							<thead>
								<tr>
									<th>Pattern</th>
									<th>Query</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $results['rewrite_rules']['gallery_rules'] as $pattern => $query ) : ?>
								<tr>
									<td><code><?php echo esc_html( $pattern ); ?></code></td>
									<td><code><?php echo esc_html( $query ); ?></code></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</details>
					<?php endif; ?>

					<!-- URL Tests -->
					<h4><?php esc_html_e( 'URL Pattern Tests', 'brag-book-gallery' ); ?></h4>
					<table class="widefat striped">
						<thead>
							<tr>
								<th>URL</th>
								<th>Matches</th>
								<th>Rule</th>
								<th>Query Vars</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $results['rewrite_rules']['url_tests'] as $url => $test ) : ?>
							<tr>
								<td><code><?php echo esc_html( $url ); ?></code></td>
								<td>
									<span class="dashicons dashicons-<?php echo $test['would_match'] ? 'yes-alt' : 'dismiss'; ?>" 
									      style="color: <?php echo $test['would_match'] ? 'green' : 'red'; ?>"></span>
								</td>
								<td><code><?php echo esc_html( $test['matched_rule'] ); ?></code></td>
								<td>
									<?php if ( ! empty( $test['query_vars'] ) ) : ?>
										<details>
											<summary>View Vars</summary>
											<pre><?php echo esc_html( print_r( $test['query_vars'], true ) ); ?></pre>
										</details>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Query Variables -->
			<div class="diagnostics-section">
				<h3><?php esc_html_e( 'Query Variables', 'brag-book-gallery' ); ?></h3>
				<div class="diagnostics-card">
					<table class="widefat striped">
						<thead>
							<tr>
								<th>Variable</th>
								<th>Registered</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $results['query_vars']['required_vars'] as $var => $registered ) : ?>
							<tr>
								<td><code><?php echo esc_html( $var ); ?></code></td>
								<td>
									<span class="dashicons dashicons-<?php echo $registered ? 'yes-alt' : 'dismiss'; ?>" 
									      style="color: <?php echo $registered ? 'green' : 'red'; ?>"></span>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Cache Status -->
			<div class="diagnostics-section">
				<h3><?php esc_html_e( 'Cache Status', 'brag-book-gallery' ); ?></h3>
				<div class="diagnostics-card">
					<p><strong>Object Cache:</strong> <?php echo $results['cache_status']['object_cache_active'] ? 'Active' : 'Inactive'; ?></p>
					<p><strong>WP Cache Functions:</strong> <?php echo $results['cache_status']['wp_cache_available'] ? 'Available' : 'Not Available'; ?></p>
					
					<?php if ( isset( $results['cache_status']['wpe_common_available'] ) && $results['cache_status']['wpe_common_available'] ) : ?>
					<p><strong>WP Engine Cache:</strong> Available</p>
					<?php endif; ?>
				</div>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Execute diagnostic actions
	 *
	 * @since 3.2.5
	 * @param string $action Action to perform
	 * @param array  $data   Action data
	 * @return array|string Action result
	 */
	public function execute( string $action, array $data ): array|string {
		// Security validation
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( __( 'Insufficient permissions', 'brag-book-gallery' ) );
		}

		switch ( $action ) {
			case 'force_rewrite_registration':
				return Rewrite_Rules_Handler::force_rewrite_rules_registration();

			case 'run_diagnostics':
			default:
				return self::run_diagnostics();
		}
	}
}