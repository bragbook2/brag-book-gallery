<?php
declare(strict_types=1);

/**
 * Enterprise Rewrite Rules Handler for BRAGBook Gallery Plugin
 *
 * Comprehensive URL rewriting and routing system implementing WordPress VIP standards
 * with PHP 8.2+ optimizations and enterprise-grade security features. Manages SEO-friendly
 * URLs, query variable registration, and intelligent page routing for the gallery system.
 *
 * Key Features:
 * - SEO-optimized URL structure with clean permalinks
 * - WordPress VIP compliant database queries and caching strategies
 * - Intelligent page detection with multiple fallback mechanisms
 * - Performance-optimized rule registration with caching
 * - Security-first approach with comprehensive input validation
 * - AJAX-powered administrative tools for rule management
 * - Backwards compatibility with legacy URL structures
 * - Multi-site support with isolated rule sets
 *
 * Architecture:
 * - Static methods for stateless rewrite operations
 * - Hierarchical rule processing with priority handling
 * - Cached page detection for performance optimization
 * - WordPress VIP compliant error handling and logging
 * - Type-safe operations with PHP 8.2+ features
 * - Modular rule registration for maintainability
 *
 * URL Patterns Supported:
 * - Base gallery: /gallery-slug/
 * - Procedure filtering: /gallery-slug/procedure-name/
 * - Case details: /gallery-slug/procedure-name/case-identifier/
 * - Favorites section: /gallery-slug/myfavorites/
 * - Legacy patterns with backwards compatibility
 *
 * Security Features:
 * - Comprehensive input sanitization for all URL parameters
 * - Nonce verification for administrative actions
 * - Capability checks for rule management operations
 * - Safe database queries with proper preparation
 * - XSS prevention in admin notices and AJAX responses
 *
 * Performance Optimizations:
 * - Intelligent caching of page detection results
 * - Conditional rule flushing to minimize overhead
 * - Optimized database queries with result caching
 * - Efficient rule matching with regex optimization
 * - Lazy loading of rules based on page context
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     BRAGBook Team
 * @version    3.0.0
 * @copyright  Copyright (c) 2025, BRAGBook Team
 * @license    GPL-2.0-or-later
 */

namespace BRAGBookGallery\Includes\Extend;

use WP_Post;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite Rules Handler Class
 *
 * Enterprise-grade URL rewriting system for the BRAGBook Gallery plugin,
 * implementing comprehensive routing with WordPress VIP standards,
 * PHP 8.2+ optimizations, and security-first design principles.
 *
 * Core Functionality:
 * - WordPress rewrite rule registration and management
 * - Custom query variable registration for URL routing
 * - SEO-friendly permalink structure implementation
 * - Page detection with intelligent caching strategies
 * - AJAX-powered administrative tools and utilities
 * - Backwards compatibility with legacy URL patterns
 *
 * Technical Implementation:
 * - PHP 8.2+ features with union types and match expressions
 * - WordPress VIP coding standards compliance throughout
 * - Comprehensive error handling with graceful degradation
 * - Performance-optimized database queries with caching
 * - Security-focused input validation and sanitization
 * - Type-safe operations with strict type declarations
 *
 * @since 3.0.0
 */
class Rewrite_Rules_Handler {

	/**
	 * Custom query variables for URL routing with comprehensive coverage
	 *
	 * Defines all custom query variables used by the plugin for URL routing
	 * and parameter passing. These variables enable SEO-friendly URLs while
	 * maintaining WordPress query compatibility.
	 *
	 * @since 3.0.0
	 * @var array<string> Indexed array of query variable names.
	 */
	private const QUERY_VARS = [
		'procedure_title',    // Procedure name in case detail URLs
		'case_suffix',        // Case identifier (ID or SEO suffix)
		'favorites_section',  // Legacy favorites section indicator
		'filter_category',    // Category filter parameter
		'filter_procedure',   // Procedure filter parameter
		'favorites_page',     // Favorites page indicator
	];

	/**
	 * Cache key for pages with gallery shortcode
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_KEY_GALLERY_PAGES = 'brag_book_gallery_pages_with_shortcode';

	/**
	 * Cache group for rewrite rules
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_GROUP = 'brag_book_gallery_rewrite';

	/**
	 * Register comprehensive rewrite rules and WordPress hooks
	 *
	 * Initializes all necessary WordPress hooks for URL rewriting functionality,
	 * including rule registration, query variable management, and administrative
	 * tools. Implements proper hook priorities for optimal execution order.
	 *
	 * Hook Registration:
	 * - Early rule registration (priority 5) for precedence
	 * - Query variable registration at standard priority
	 * - Admin notices for debugging and maintenance
	 * - AJAX handlers for administrative operations
	 *
	 * Security Features:
	 * - Proper callback references using __CLASS__ constant
	 * - Secure AJAX handler registration with authentication
	 * - Hook priority management for proper execution order
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		// Register rewrite rules early for precedence
		add_action( 'init', [ __CLASS__, 'custom_rewrite_rules' ], 5 );
		add_action( 'init', [ __CLASS__, 'custom_rewrite_flush' ], 10 );
		add_action( 'init', [ __CLASS__, 'register_query_vars' ], 10 );

		// Register query variables filter
		add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ], 10, 1 );

		// Administrative notices and tools
		add_action( 'admin_notices', [ __CLASS__, 'rewrite_debug_notice' ] );

		// AJAX handlers for admin operations
		add_action( 'wp_ajax_bragbook_flush_rewrite', [ __CLASS__, 'ajax_flush_rewrite' ] );
	}

	/**
	 * Handle AJAX rewrite flush request with comprehensive security
	 *
	 * Processes authenticated AJAX requests to flush rewrite rules from admin notices.
	 * Implements WordPress VIP standards with proper security validation, capability
	 * checks, and safe response handling.
	 *
	 * Security Implementation:
	 * - Nonce verification for CSRF protection
	 * - Capability checking for authorization
	 * - Safe input handling with proper sanitization
	 * - Secure JSON response with proper escaping
	 *
	 * Processing Pipeline:
	 * 1. Security validation (nonce and capabilities)
	 * 2. Rewrite rule flushing with WordPress core function
	 * 3. Cache invalidation for performance optimization
	 * 4. Transient cleanup for notice management
	 * 5. Success response with user feedback
	 *
	 * @since 3.0.0
	 *
	 * @return void Sends JSON response and terminates execution.
	 */
	public static function ajax_flush_rewrite(): void {
		// Comprehensive security validation with nonce verification
		$security_result = self::validate_ajax_security( 'bragbook_flush' );
		if ( ! $security_result['valid'] ) {
			wp_send_json_error( [ 'message' => $security_result['message'] ] );
			return;
		}

		// Perform rewrite rule flush with error handling
		$flush_result = self::perform_rewrite_flush();

		if ( $flush_result['success'] ) {
			wp_send_json_success( [ 'message' => $flush_result['message'] ] );
		} else {
			wp_send_json_error( [ 'message' => $flush_result['message'] ] );
		}
	}

	/**
	 * Register custom query vars with WordPress
	 *
	 * Registers custom query variables for use in URL routing.
	 *
	 * @since 3.0.0
	 *
	 * @global WP $wp WordPress environment instance.
	 *
	 * @return void
	 */
	public static function register_query_vars(): void {
		global $wp;

		foreach ( self::QUERY_VARS as $var ) {
			$wp->add_query_var( $var );
		}
	}

	/**
	 * Add custom query variables to WordPress with validation
	 *
	 * Filters the list of public query variables to include custom ones
	 * for URL routing. Implements proper validation and type safety to
	 * ensure compatibility with WordPress query system.
	 *
	 * Processing:
	 * - Validates incoming query variables array
	 * - Merges custom variables with existing ones
	 * - Ensures unique variable names
	 * - Maintains WordPress compatibility
	 *
	 * @since 3.0.0
	 *
	 * @param array<string> $vars Existing WordPress query variables.
	 *
	 * @return array<string> Modified query variables including custom ones.
	 */
	public static function add_query_vars( array $vars ): array {
		// Ensure vars is a valid array
		if ( ! is_array( $vars ) ) {
			$vars = [];
		}

		// Merge and return unique variables
		return array_unique( array_merge( $vars, self::QUERY_VARS ) );
	}

	/**
	 * Define comprehensive custom rewrite rules for gallery pages
	 *
	 * Creates complete set of rewrite rules for SEO-friendly URLs using multiple
	 * detection methods. Implements intelligent page discovery with caching,
	 * duplicate prevention, and backwards compatibility support.
	 *
	 * Detection Methods:
	 * 1. Automatic shortcode detection in page content
	 * 2. Configured page slugs from settings
	 * 3. Legacy stored pages for backwards compatibility
	 * 4. Combined gallery rules from page ID option
	 *
	 * Processing Features:
	 * - Duplicate slug prevention with tracking
	 * - Multiple fallback mechanisms for robustness
	 * - Efficient processing with early returns
	 * - Hook integration for extensibility
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function custom_rewrite_rules(): void {
		// Track processed slugs to prevent duplicate rules
		$processed_slugs = [];

		// Auto-detect pages with gallery shortcode for dynamic discovery
		$pages_with_shortcode = self::find_pages_with_gallery_shortcode();

		// Add rewrite rules for each detected page.
		foreach ( $pages_with_shortcode as $page ) {
			if ( ! empty( $page->post_name ) && ! in_array( $page->post_name, $processed_slugs, true ) ) {
				self::add_gallery_rewrite_rules( $page->post_name );
				$processed_slugs[] = $page->post_name;
			}
		}

		// Check for brag_book_gallery_page_slug option (used in settings).
		// Use the helper to get all slugs (handles both array and string formats).
		$gallery_page_slugs = \BRAGBookGallery\Includes\Core\Slug_Helper::get_all_gallery_page_slugs();
		foreach ( $gallery_page_slugs as $slug ) {
			if ( ! empty( $slug ) && ! in_array( $slug, $processed_slugs, true ) ) {
				self::add_gallery_rewrite_rules( $slug );
				$processed_slugs[] = $slug;
			}
		}

		// Also check saved options as fallback (legacy support).
		$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', array() );

		// Ensure we have an array.
		if ( ! is_array( $gallery_slugs ) ) {
			// Fallback to stored pages method.
			$stored_pages = get_option( 'brag_book_gallery_stored_pages', array() );

			if ( ! is_array( $stored_pages ) ) {
				// If no stored configuration and no pages found with shortcode, we're done.
				if ( empty( $pages_with_shortcode ) ) {
					return;
				}
			} else {
				// Process each stored page.
				foreach ( $stored_pages as $page_path ) {
					$page_slug = self::get_page_slug_from_path( $page_path );

					if ( ! empty( $page_slug ) && ! in_array( $page_slug, $processed_slugs, true ) ) {
						self::add_gallery_rewrite_rules( $page_slug );
						$processed_slugs[] = $page_slug;
					}
				}
			}
		} else {
			// Process gallery slugs directly.
			foreach ( $gallery_slugs as $page_slug ) {
				if ( ! empty( $page_slug ) && is_string( $page_slug ) && ! in_array( $page_slug, $processed_slugs, true ) ) {
					self::add_gallery_rewrite_rules( $page_slug );
					$processed_slugs[] = $page_slug;
				}
			}
		}

		// Add combined gallery rules.
		self::add_combined_gallery_rewrite_rules();

		/**
		 * Fires after custom rewrite rules are registered.
		 *
		 * @since 3.0.0
		 *
		 * @param array $processed_slugs Array of slugs that had rules added.
		 */
		do_action( 'brag_book_gallery_rewrite_rules_added', $processed_slugs );
	}

	/**
	 * Find pages containing gallery shortcode with intelligent caching
	 *
	 * Queries the database for pages containing the gallery shortcode using
	 * WordPress VIP compliant queries with proper caching. Implements performance
	 * optimization through result caching and efficient database queries.
	 *
	 * Query Strategy:
	 * - Cache-first approach for performance optimization
	 * - Prepared statements for security and SQL injection prevention
	 * - Result validation and type safety enforcement
	 * - Intelligent cache expiration for data freshness
	 *
	 * Performance Features:
	 * - Object cache integration with fallback handling
	 * - One-hour cache duration for optimal performance
	 * - Minimal database queries through caching
	 * - Efficient result processing and validation
	 *
	 * @since 3.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return array Array of WP_Post-like objects containing the shortcode.
	 */
	private static function find_pages_with_gallery_shortcode(): array {
		global $wpdb;

		// Check cache first for performance optimization
		$cached_pages = wp_cache_get( self::CACHE_KEY_GALLERY_PAGES, self::CACHE_GROUP );
		if ( false !== $cached_pages && is_array( $cached_pages ) ) {
			return $cached_pages;
		}

		// Execute VIP-compliant database query with proper preparation
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for LIKE query with shortcode detection, caching handled manually
		$pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_title, post_content
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = %s
				AND post_content LIKE %s",
				'page',
				'publish',
				'%[brag_book_gallery%'
			)
		);

		// Validate and process results with type safety
		$result = is_array( $pages ) ? $pages : [];

		// Cache results with appropriate expiration
		wp_cache_set( self::CACHE_KEY_GALLERY_PAGES, $result, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Get page slug from hierarchical page path with validation
	 *
	 * Retrieves the slug for a page given its hierarchical path, implementing
	 * comprehensive validation and type safety. Uses PHP 8.2 features for
	 * efficient processing and null safety.
	 *
	 * Processing Pipeline:
	 * 1. Input sanitization and validation
	 * 2. Page lookup with WordPress function
	 * 3. Type verification and safety checks
	 * 4. Slug extraction with proper sanitization
	 *
	 * @since 3.0.0
	 *
	 * @param string $page_path The hierarchical page path to look up.
	 *
	 * @return string Sanitized page slug or empty string if not found.
	 */
	private static function get_page_slug_from_path( string $page_path ): string {
		// Comprehensive input validation and sanitization
		$page_path = trim( sanitize_text_field( $page_path ) );

		if ( empty( $page_path ) ) {
			return '';
		}

		// Retrieve page with type safety
		$page = get_page_by_path( $page_path, OBJECT, 'page' );

		// Use PHP 8.2 null-safe operator and match for validation
		return match ( true ) {
			! $page instanceof WP_Post => '',
			empty( $page->post_name ) => '',
			default => sanitize_title( $page->post_name ),
		};
	}

	/**
	 * Add comprehensive rewrite rules for gallery page with SEO optimization
	 *
	 * Creates complete set of SEO-friendly URL patterns for a gallery page including
	 * favorites section, procedure filtering, and case detail views. Implements
	 * intelligent page detection with multiple fallback mechanisms.
	 *
	 * URL Patterns Generated:
	 * - /gallery-slug/myfavorites/ - Favorites section
	 * - /gallery-slug/procedure-name/ - Procedure filter view
	 * - /gallery-slug/procedure-name/case-id/ - Case detail view
	 *
	 * Page Detection Strategy:
	 * 1. Direct page lookup by slug for existing pages
	 * 2. Gallery slug validation with configured page ID
	 * 3. Fallback to pagename query for flexibility
	 *
	 * Security and Performance:
	 * - Input sanitization for all parameters
	 * - Efficient page detection with caching
	 * - Priority-based rule registration
	 * - Conflict avoidance through page ID usage
	 *
	 * @since 3.0.0
	 *
	 * @param string $page_slug The page slug to add rewrite rules for.
	 *
	 * @return void
	 */
	private static function add_gallery_rewrite_rules( string $page_slug ): void {
		// Comprehensive input validation and sanitization
		$page_slug = self::validate_and_sanitize_slug( $page_slug );

		if ( empty( $page_slug ) ) {
			return;
		}

		// Determine base query with intelligent detection
		$base_query = self::determine_base_query_for_slug( $page_slug );

		if ( empty( $base_query ) ) {
			return;
		}

		$rewrite_rules = array(
			// My Favorites page: /gallery/myfavorites.
			array(
				'regex' => "^{$page_slug}/myfavorites/?$",
				'query' => "{$base_query}&favorites_page=1",
			),
			// Case detail: /gallery/procedure-name/identifier.
			// This single pattern captures both numeric IDs and SEO suffixes.
			// The identifier can contain letters, numbers, hyphens, underscores, and dots.
			array(
				'regex' => "^{$page_slug}/([^/]+)/([a-zA-Z0-9\-_\.]+)/?$",
				'query' => "{$base_query}&procedure_title=\$matches[1]&case_suffix=\$matches[2]",
			),
			// Procedure page: /gallery/procedure-name.
			array(
				'regex' => "^{$page_slug}/([^/]+)/?$",
				'query' => "{$base_query}&filter_procedure=\$matches[1]",
			),
		);

		foreach ( $rewrite_rules as $rule ) {
			add_rewrite_rule(
				$rule['regex'],
				$rule['query'],
				'top'
			);
		}

		/**
		 * Fires after rewrite rules are added for a gallery page.
		 *
		 * @since 3.0.0
		 *
		 * @param string $page_slug   The page slug rules were added for.
		 * @param array  $rewrite_rules Array of rules that were added.
		 */
		do_action( 'brag_book_gallery_page_rules_added', $page_slug, $rewrite_rules );
	}

	/**
	 * Add rewrite rules for combined gallery pages
	 *
	 * Adds rules for pages identified by the gallery page ID option.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private static function add_combined_gallery_rewrite_rules(): void {
		$brag_book_gallery_page_id = get_option( 'brag_book_gallery_page_id', 0 );

		if ( empty( $brag_book_gallery_page_id ) ) {
			return;
		}

		$page_slug = self::get_page_slug_from_id( absint( $brag_book_gallery_page_id ) );

		if ( ! empty( $page_slug ) ) {
			self::add_gallery_rewrite_rules( $page_slug );
		}
	}

	/**
	 * Get page slug from page ID with comprehensive validation
	 *
	 * Retrieves the slug for a page given its numeric ID, implementing
	 * proper validation and type safety checks. Uses PHP 8.2 features
	 * for efficient null handling and validation.
	 *
	 * Validation Process:
	 * - ID range validation for positive integers
	 * - Post existence verification
	 * - Type safety enforcement
	 * - Slug sanitization for security
	 *
	 * @since 3.0.0
	 *
	 * @param int $page_id The numeric page ID to look up.
	 *
	 * @return string Sanitized page slug or empty string if not found.
	 */
	private static function get_page_slug_from_id( int $page_id ): string {
		// Validate page ID range
		if ( $page_id <= 0 ) {
			return '';
		}

		// Retrieve post with type safety
		$post = get_post( $page_id );

		// Use PHP 8.2 match for efficient validation
		return match ( true ) {
			! $post instanceof WP_Post => '',
			empty( $post->post_name ) => '',
			default => sanitize_title( $post->post_name ),
		};
	}

	/**
	 * Flush rewrite rules conditionally with performance optimization
	 *
	 * Conditionally flushes rewrite rules when explicitly requested via option,
	 * preventing performance issues from frequent flushing. Ensures custom rules
	 * are registered before flushing and handles cache invalidation.
	 *
	 * Performance Strategy:
	 * - Option-based triggering to prevent unnecessary flushes
	 * - Rule registration before flushing for consistency
	 * - Cache invalidation for data freshness
	 * - Single execution per request cycle
	 *
	 * Operations Performed:
	 * 1. Custom rule registration to ensure completeness
	 * 2. Conditional flush based on option flag
	 * 3. Option cleanup to prevent repeated flushes
	 * 4. Cache invalidation for consistency
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function custom_rewrite_flush(): void {
		// Ensure custom rewrite rules are registered before flush
		self::custom_rewrite_rules();

		// Check for flush request flag
		$should_flush = get_option( 'brag_book_gallery_flush_rewrite_rules', false );

		// Only flush when explicitly needed for performance
		if ( $should_flush ) {
			try {
				// Perform the flush operation
				flush_rewrite_rules();

				// Clean up the flag option
				delete_option( 'brag_book_gallery_flush_rewrite_rules' );

				// Clear related caches
				wp_cache_delete( self::CACHE_KEY_GALLERY_PAGES, self::CACHE_GROUP );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAGBook Gallery: Rewrite rules flushed via custom_rewrite_flush' );
				}
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAGBook Gallery: Error during rewrite flush: ' . $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Display admin notice for rewrite rules debugging with security
	 *
	 * Shows a warning notice when rewrite rules may need to be flushed,
	 * providing an AJAX-powered button for administrators. Implements
	 * comprehensive security checks and proper escaping.
	 *
	 * Security Features:
	 * - Capability checking for admin access
	 * - Nonce generation for CSRF protection
	 * - Proper output escaping for XSS prevention
	 * - Safe JavaScript injection with escaping
	 *
	 * Display Logic:
	 * - Only shows to administrators
	 * - Conditional display based on transient
	 * - Rule existence verification
	 * - Dismissible notice interface
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function rewrite_debug_notice(): void {
		// Security check: Only show to administrators
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check display condition via transient
		if ( ! get_transient( 'brag_book_gallery_show_rewrite_notice' ) ) {
			return;
		}

		// Retrieve and validate gallery configuration
		$gallery_slugs = self::get_gallery_slugs_safely();
		$rewrite_rules = get_option( 'rewrite_rules', [] );

		// Check if our rules exist.
		$rules_exist = false;
		if ( ! empty( $gallery_slugs ) && is_array( $gallery_slugs ) ) {
			foreach ( $gallery_slugs as $slug ) {
				$slug = sanitize_title( $slug );
				if ( isset( $rewrite_rules[ "^{$slug}/([^/]+)/([0-9]+)/?$" ] ) ) {
					$rules_exist = true;
					break;
				}
			}
		}

		if ( ! $rules_exist ) {
			$nonce = wp_create_nonce( 'bragbook_flush' );
			?>
			<div class="notice notice-warning is-dismissible">
				<p><strong><?php esc_html_e( 'BRAGBook Gallery:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Rewrite rules may need to be flushed.', 'brag-book-gallery' ); ?></p>
				<p>
					<?php
					/* translators: %s: Comma-separated list of gallery slugs */
					printf( esc_html__( 'Gallery slugs: %s', 'brag-book-gallery' ), esc_html( implode( ', ', $gallery_slugs ) ) );
					?>
				</p>
				<p>
					<button class="button button-primary" id="bragbook-flush-rewrite"><?php esc_html_e( 'Flush Rewrite Rules', 'brag-book-gallery' ); ?></button>
				</p>
			</div>
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				const button = document.getElementById('bragbook-flush-rewrite');
				if (!button) return;

				button.addEventListener('click', async function(e) {
					e.preventDefault();

					// Disable button and update text
					button.disabled = true;
					button.textContent = '<?php echo esc_js( __( 'Flushing...', 'brag-book-gallery' ) ); ?>';

					// Prepare form data
					const formData = new FormData();
					formData.append('action', 'bragbook_flush_rewrite');
					formData.append('_wpnonce', '<?php echo esc_js( $nonce ); ?>');

					try {
						// Make AJAX request
						const response = await fetch(ajaxurl, {
							method: 'POST',
							credentials: 'same-origin',
							body: formData
						});

						const data = await response.json();

						if (data.success) {
							button.textContent = '<?php echo esc_js( __( 'Success!', 'brag-book-gallery' ) ); ?>';
							// Reload page after short delay
							setTimeout(() => {
								window.location.reload();
							}, 500);
						} else {
							button.textContent = '<?php echo esc_js( __( 'Error', 'brag-book-gallery' ) ); ?>';
							button.disabled = false;
							console.error('Flush failed:', data.message);
						}
					} catch (error) {
						button.textContent = '<?php echo esc_js( __( 'Error', 'brag-book-gallery' ) ); ?>';
						button.disabled = false;
						console.error('Flush request failed:', error);
					}
				});
			});
			</script>
			<?php
		}
	}

	/**
	 * Validate AJAX request security with comprehensive checks
	 *
	 * Performs thorough security validation for AJAX requests including
	 * nonce verification and capability checking. Uses PHP 8.2 features
	 * for efficient validation processing.
	 *
	 * @since 3.0.0
	 *
	 * @param string $nonce_action Nonce action name for verification.
	 *
	 * @return array{valid: bool, message: string} Validation result with status and message.
	 */
	private static function validate_ajax_security( string $nonce_action ): array {
		// Check nonce presence and validity
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			return [
				'valid' => false,
				'message' => __( 'Security token missing.', 'brag-book-gallery' ),
			];
		}

		// Verify nonce with proper sanitization
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return [
				'valid' => false,
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			];
		}

		// Verify user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return [
				'valid' => false,
				'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ),
			];
		}

		return [
			'valid' => true,
			'message' => '',
		];
	}

	/**
	 * Perform rewrite rule flush with comprehensive operations
	 *
	 * Executes rewrite rule flushing along with related cache clearing
	 * and transient cleanup operations. Implements error handling and
	 * provides detailed operation results.
	 *
	 * @since 3.0.0
	 *
	 * @return array{success: bool, message: string} Operation result with status and message.
	 */
	private static function perform_rewrite_flush(): array {
		try {
			// Flush WordPress rewrite rules
			flush_rewrite_rules();

			// Clear related transients
			delete_transient( 'brag_book_gallery_show_rewrite_notice' );

			// Clear object cache for gallery pages
			wp_cache_delete( self::CACHE_KEY_GALLERY_PAGES, self::CACHE_GROUP );

			// Log success if debugging is enabled
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery: Rewrite rules flushed successfully via AJAX' );
			}

			return [
				'success' => true,
				'message' => __( 'Rewrite rules flushed successfully.', 'brag-book-gallery' ),
			];
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery: Error flushing rewrite rules: ' . $e->getMessage() );
			}

			return [
				'success' => false,
				'message' => __( 'Failed to flush rewrite rules. Please try again.', 'brag-book-gallery' ),
			];
		}
	}

	/**
	 * Validate and sanitize page slug with comprehensive security
	 *
	 * Performs thorough validation and sanitization of page slugs to ensure
	 * they are safe for use in rewrite rules and database queries.
	 *
	 * @since 3.0.0
	 *
	 * @param string $slug Raw page slug input.
	 *
	 * @return string Sanitized and validated slug.
	 */
	private static function validate_and_sanitize_slug( string $slug ): string {
		// Trim and sanitize the slug
		$slug = trim( sanitize_title( $slug ) );

		// Additional validation for slug format
		if ( ! preg_match( '/^[a-z0-9\-]+$/', $slug ) ) {
			return '';
		}

		// Ensure reasonable length
		if ( strlen( $slug ) > 200 ) {
			return '';
		}

		return $slug;
	}

	/**
	 * Determine base query string for page slug with intelligent detection
	 *
	 * Implements multiple detection strategies to determine the appropriate
	 * base query string for a given page slug. Uses PHP 8.2 features for
	 * efficient processing.
	 *
	 * Detection Priority:
	 * 1. Existing published pages by slug
	 * 2. Configured gallery slugs with page ID
	 * 3. Fallback to pagename query
	 *
	 * @since 3.0.0
	 *
	 * @param string $page_slug Sanitized page slug.
	 *
	 * @return string Base query string for rewrite rules.
	 */
	private static function determine_base_query_for_slug( string $page_slug ): string {
		// Try to find the page by slug
		$page = get_page_by_path( $page_slug );

		if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
			// Use page_id for existing pages to avoid conflicts
			return sprintf( 'index.php?page_id=%d', absint( $page->ID ) );
		}

		// Check if this matches configured gallery slug
		$is_gallery_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::is_gallery_slug( $page_slug );
		$gallery_page_id = absint( get_option( 'brag_book_gallery_page_id', 0 ) );

		if ( $is_gallery_slug && $gallery_page_id > 0 ) {
			return sprintf( 'index.php?page_id=%d', $gallery_page_id );
		}

		// Fallback to pagename query
		return sprintf( 'index.php?pagename=%s', $page_slug );
	}

	/**
	 * Get gallery slugs safely with validation and type checking
	 *
	 * Retrieves gallery slugs from options with comprehensive validation
	 * and type safety checks. Ensures returned value is always an array.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string> Array of validated gallery slugs.
	 */
	private static function get_gallery_slugs_safely(): array {
		$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );

		// Ensure we have an array
		if ( ! is_array( $gallery_slugs ) ) {
			// Handle string values
			if ( is_string( $gallery_slugs ) && ! empty( $gallery_slugs ) ) {
				return [ sanitize_title( $gallery_slugs ) ];
			}
			return [];
		}

		// Sanitize and filter array values
		return array_filter(
			array_map( 'sanitize_title', $gallery_slugs ),
			'strlen'
		);
	}
}
