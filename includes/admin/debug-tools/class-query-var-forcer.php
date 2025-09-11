<?php
/**
 * Query Var Forcer Debug Tool - Enterprise-grade query variable management
 *
 * Advanced query variable forcing system for gallery pages to prevent 404 errors
 * during development and production. Provides intelligent URL parsing, automatic
 * page detection, and comprehensive debugging capabilities.
 *
 * Features:
 * - Automatic 404 recovery for gallery pages
 * - Intelligent URL pattern matching
 * - Query variable injection and modification
 * - Real-time debugging for administrators
 * - Performance-optimized caching
 * - WordPress VIP compliant architecture
 * - Modern PHP 8.2+ features and type safety
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 * @author     BRAGBook Development Team
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Admin\Debug_Tools;

use BRAGBookGallery\Includes\Core\Slug_Helper;
use Exception;
use WP;
use WP_Query;
use WP_Post;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enterprise Query Variable Forcer Class
 *
 * Orchestrates intelligent query variable management for gallery pages:
 *
 * Core Responsibilities:
 * - 404 error interception and recovery
 * - Query variable injection and modification
 * - URL pattern recognition and parsing
 * - Debug information logging
 * - Performance optimization through caching
 * - Security validation and sanitization
 *
 * @since 3.0.0
 */
class Query_Var_Forcer {

	/**
	 * Configuration constants
	 *
	 * @since 3.0.0
	 */
	private const CACHE_GROUP = 'brag_book_query_vars';
	private const CACHE_TTL = 300; // 5 minutes
	private const MAX_URL_DEPTH = 5;
	private const DEBUG_LOG_PREFIX = '[BRAGBook Query Var Forcer]';

	/**
	 * Static cache for expensive operations
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private static array $cache = [];

	/**
	 * Debug mode flag
	 *
	 * @since 3.0.0
	 * @var bool
	 */
	private static bool $debug_mode = false;

	/**
	 * Initialize the query var forcer with enhanced capabilities
	 *
	 * Hooks into WordPress request lifecycle to intercept and modify
	 * query variables for gallery pages with performance optimization.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function init(): void {
		try {
			// Set debug mode based on environment
			self::$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;

			// Hook early to catch 404s with priority
			add_action( 'template_redirect', [ __CLASS__, 'force_gallery_query_vars' ], 1 );
			add_filter( 'request', [ __CLASS__, 'modify_request_vars' ], 1 );
			add_action( 'parse_request', [ __CLASS__, 'debug_parse_request' ], 1 );

			// Additional hooks for comprehensive coverage
			add_filter( 'query_vars', [ __CLASS__, 'register_custom_query_vars' ] );
			add_action( 'pre_get_posts', [ __CLASS__, 'modify_main_query' ], 5 );

			// Clear cache on relevant actions
			add_action( 'save_post_page', [ __CLASS__, 'clear_cache' ] );
			add_action( 'update_option_brag_book_gallery_page_id', [ __CLASS__, 'clear_cache' ] );
			add_action( 'update_option_brag_book_gallery_page_slug', [ __CLASS__, 'clear_cache' ] );

		} catch ( Exception $e ) {
			self::log_error( 'Initialization failed', $e->getMessage() );
		}
	}

	/**
	 * Force gallery query vars on 404 errors with enhanced logic
	 *
	 * Intercepts 404 errors for gallery pages and forces proper query
	 * variables to allow the page to load correctly with caching.
	 *
	 * @since 3.0.0
	 * @global WP_Query $wp_query The main WordPress query object.
	 * @return void
	 */
	public static function force_gallery_query_vars(): void {
		try {
			global $wp_query;

			if ( ! is_404() ) {
				return;
			}

			// Get and validate request URI
			$request_uri = self::get_sanitized_request_uri();
			if ( empty( $request_uri ) ) {
				return;
			}

			// Check cache first
			$cache_key = 'brag_book-gallery_transient_forced_vars_' . $request_uri;
			if ( isset( self::$cache[ $cache_key ] ) ) {
				self::apply_forced_query_vars( $wp_query, self::$cache[ $cache_key ] );
				return;
			}

			$path_parts = explode( '/', $request_uri );

			// Validate path depth
			if ( count( $path_parts ) > self::MAX_URL_DEPTH || empty( $path_parts[0] ) ) {
				return;
			}

			// Caching disabled
			$gallery_slugs = Slug_Helper::get_all_gallery_page_slugs();

			if ( ! in_array( $path_parts[0], $gallery_slugs, true ) ) {
				return;
			}

			// We have a gallery page that's 404ing - force it!
			$gallery_slug = sanitize_title( $path_parts[0] );

			// Find the page with enhanced logic
			$page = self::find_gallery_page( $gallery_slug );

			if ( ! $page ) {
				return;
			}

			// Prepare forced data
			$forced_data = [
				'page' => $page,
				'query_vars' => self::extract_query_vars( $path_parts ),
				'gallery_slug' => $gallery_slug,
				'path_parts' => $path_parts,
			];

			// Cache the forced data
			self::$cache[ $cache_key ] = $forced_data;

			// Apply forced query vars
			self::apply_forced_query_vars( $wp_query, $forced_data );

			// Log successful force
			self::log_debug( 'Successfully forced gallery page', [
				'slug' => $gallery_slug,
				'page_id' => $page->ID,
				'query_vars' => $forced_data['query_vars'],
			] );

			// Add debug notice for administrators
			if ( current_user_can( 'manage_options' ) && self::$debug_mode ) {
				self::add_admin_debug_notice( $forced_data );
			}

		} catch ( Exception $e ) {
			self::log_error( 'Failed to force gallery query vars', $e->getMessage() );
		}
	}

	/**
	 * Modify request vars before query execution with caching
	 *
	 * Filters the request query variables to ensure gallery pages
	 * are properly recognized and loaded with performance optimization.
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $query_vars The current query variables.
	 * @return array<string, mixed> Modified query variables.
	 */
	public static function modify_request_vars( array $query_vars ): array {
		try {
			// Get the request path with validation
			$request_path = self::extract_request_path( $query_vars );
			if ( empty( $request_path ) ) {
				return $query_vars;
			}

			// Caching disabled

			$path_parts = explode( '/', $request_path );

			// Caching disabled
			$gallery_slugs = Slug_Helper::get_all_gallery_page_slugs();

			// Check if this is a gallery page
			if ( ! in_array( $path_parts[0], $gallery_slugs, true ) ) {
				return $query_vars;
			}

			// Find the page with enhanced logic
			$page = self::find_gallery_page( $path_parts[0] );

			if ( $page ) {
				// Set the page_id
				$query_vars['page_id'] = $page->ID;
				$query_vars['pagename'] = '';

				// Add custom query vars
				$custom_vars = self::extract_query_vars( $path_parts );

				// Caching disabled

				// Merge custom vars into query vars
				$query_vars = array_merge( $query_vars, $custom_vars );

				self::log_debug( 'Modified request vars', [
					'path' => $request_path,
					'custom_vars' => $custom_vars,
				] );
			}

			return $query_vars;

		} catch ( Exception $e ) {
			self::log_error( 'Failed to modify request vars', $e->getMessage() );
			return $query_vars;
		}
	}

	/**
	 * Debug parse request for gallery pages with enhanced logging
	 *
	 * Logs comprehensive debug information about gallery page requests
	 * when debug mode is enabled and user has appropriate permissions.
	 *
	 * @since 3.0.0
	 * @param WP $wp The WordPress environment instance.
	 * @return void
	 */
	public static function debug_parse_request( WP $wp ): void {
		if ( ! self::$debug_mode || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		try {
			$request_uri = self::get_sanitized_request_uri();
			if ( empty( $request_uri ) ) {
				return;
			}

			$path_parts = explode( '/', $request_uri );

			// Caching disabled
			$gallery_slugs = Slug_Helper::get_all_gallery_page_slugs();

			if ( ! empty( $path_parts[0] ) && in_array( $path_parts[0], $gallery_slugs, true ) ) {
				self::log_debug( 'Gallery Request Parse', [
					'request_uri' => $request_uri,
					'query_vars' => $wp->query_vars,
					'matched_rule' => $wp->matched_rule ?? 'none',
					'matched_query' => $wp->matched_query ?? 'none',
					'path_parts' => $path_parts,
					'gallery_slugs' => $gallery_slugs,
				] );
			}

		} catch ( Exception $e ) {
			self::log_error( 'Debug parse request failed', $e->getMessage() );
		}
	}

	/**
	 * Register custom query variables
	 *
	 * @since 3.0.0
	 * @param array<string> $vars Existing query variables.
	 * @return array<string> Modified query variables.
	 */
	public static function register_custom_query_vars( array $vars ): array {
		$custom_vars = [
			'filter_procedure',
			'procedure_title',
			'case_id',
			'favorites_page',
			'gallery_page',
		];

		return array_merge( $vars, $custom_vars );
	}

	/**
	 * Modify main query for gallery pages
	 *
	 * @since 3.0.0
	 * @param WP_Query $query The query object.
	 * @return void
	 */
	public static function modify_main_query( WP_Query $query ): void {
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}

		try {
			// Check if this is a gallery page query
			$page_id = $query->get( 'page_id' );
			if ( ! $page_id ) {
				return;
			}

			$gallery_page_id = absint( get_option( 'brag_book_gallery_page_id', 0 ) );
			if ( $page_id === $gallery_page_id ) {
				// Ensure proper query flags
				$query->is_page = true;
				$query->is_singular = true;
				$query->is_archive = false;
				$query->is_404 = false;

				self::log_debug( 'Modified main query for gallery page', [
					'page_id' => $page_id,
					'query_vars' => $query->query_vars,
				] );
			}

		} catch ( Exception $e ) {
			self::log_error( 'Failed to modify main query', $e->getMessage() );
		}
	}

	/**
	 * Find gallery page by slug with caching
	 *
	 * @since 3.0.0
	 * @param string $slug The page slug to find.
	 * @return WP_Post|null The found page or null.
	 */
	private static function find_gallery_page( string $slug ): ?WP_Post {
		// Check memory cache
		$cache_key = 'page_' . $slug;
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		// Caching disabled

		// Find the page
		$page = get_page_by_path( $slug );

		if ( ! $page ) {
			// Fallback: check for gallery page ID option
			$page_id = absint( get_option( 'brag_book_gallery_page_id', 0 ) );
			if ( $page_id ) {
				$page = get_post( $page_id );
				if ( $page && $page->post_name === $slug ) {
					// Caching disabled
					self::$cache[ $cache_key ] = $page;
					return $page;
				}
			}
			return null;
		}

		// Caching disabled
		self::$cache[ $cache_key ] = $page;

		return $page;
	}

	/**
	 * Extract query variables from path parts
	 *
	 * @since 3.0.0
	 * @param array<string> $path_parts The URL path parts.
	 * @return array<string, mixed> Extracted query variables.
	 */
	private static function extract_query_vars( array $path_parts ): array {
		// Sanitize path parts
		$path_parts = array_map( 'sanitize_title', $path_parts );

		return match ( true ) {
			// Case detail: /gallery/procedure-name/case-id
			isset( $path_parts[1], $path_parts[2] ) && is_numeric( $path_parts[2] ) => [
				'procedure_title' => $path_parts[1],
				'case_id' => absint( $path_parts[2] ),
				'gallery_page' => 'case_detail',
			],
			// Favorites page: /gallery/myfavorites
			isset( $path_parts[1] ) && 'myfavorites' === $path_parts[1] => [
				'favorites_page' => 1,
				'gallery_page' => 'favorites',
			],
			// Procedure page: /gallery/procedure-name
			isset( $path_parts[1] ) => [
				'filter_procedure' => $path_parts[1],
				'gallery_page' => 'procedure',
			],
			// Main gallery page
			default => [
				'gallery_page' => 'main',
			],
		};
	}

	/**
	 * Apply forced query variables to WP_Query
	 *
	 * @since 3.0.0
	 * @param WP_Query $wp_query The query object to modify.
	 * @param array<string, mixed> $forced_data The forced data.
	 * @return void
	 */
	private static function apply_forced_query_vars( WP_Query $wp_query, array $forced_data ): void {
		$page = $forced_data['page'];
		$query_vars = $forced_data['query_vars'];

		// Reset the query to show this page
		$wp_query->is_404 = false;
		$wp_query->is_page = true;
		$wp_query->is_singular = true;
		$wp_query->is_archive = false;
		$wp_query->queried_object = $page;
		$wp_query->queried_object_id = $page->ID;

		// Apply query vars
		foreach ( $query_vars as $key => $value ) {
			set_query_var( $key, $value );
		}

		// Force the page to load with 200 status
		status_header( 200 );
	}

	/**
	 * Get sanitized request URI
	 *
	 * @since 3.0.0
	 * @return string The sanitized request URI.
	 */
	private static function get_sanitized_request_uri(): string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
		$request_uri = filter_var( $request_uri, FILTER_SANITIZE_URL );
		$request_uri = trim( parse_url( $request_uri, PHP_URL_PATH ) ?? '', '/' );

		return sanitize_text_field( $request_uri );
	}

	/**
	 * Extract request path from query vars
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $query_vars The query variables.
	 * @return string The extracted request path.
	 */
	private static function extract_request_path( array $query_vars ): string {
		// Try to get from query vars first
		$request_path = $query_vars['pagename'] ?? '';

		// Fallback to request URI
		if ( empty( $request_path ) ) {
			$request_path = self::get_sanitized_request_uri();
		}

		return $request_path;
	}

	/**
	 * Add admin debug notice
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $forced_data The forced data.
	 * @return void
	 */
	private static function add_admin_debug_notice( array $forced_data ): void {
		$gallery_slug = $forced_data['gallery_slug'] ?? '';
		$query_vars = $forced_data['query_vars'] ?? [];

		add_action( 'wp_footer', static function() use ( $gallery_slug, $query_vars ): void {
			?>
			<div style="position: fixed; bottom: 0; right: 0; background: #ff9800; color: white; padding: 15px; z-index: 99999; max-width: 450px; border-radius: 4px 0 0 0; box-shadow: -2px -2px 5px rgba(0,0,0,0.2);">
				<strong>🔧 Query Var Forcer Active</strong>
				<button onclick="this.parentElement.style.display='none'" style="float: right; background: transparent; border: none; color: white; cursor: pointer; font-size: 20px; line-height: 1;">×</button>
				<div style="margin-top: 8px; font-size: 13px;">
					<div>Gallery: <code style="background: rgba(255,255,255,0.2); padding: 2px 4px;"><?php echo esc_html( $gallery_slug ); ?></code></div>
					<?php if ( ! empty( $query_vars ) ) : ?>
						<div style="margin-top: 5px;">Query Vars:</div>
						<ul style="margin: 5px 0 0 20px; padding: 0; font-size: 12px;">
							<?php foreach ( $query_vars as $key => $value ) : ?>
								<li><?php echo esc_html( $key ); ?>: <code style="background: rgba(255,255,255,0.2); padding: 1px 3px;"><?php echo esc_html( (string) $value ); ?></code></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<div style="margin-top: 8px; font-size: 11px; opacity: 0.9;">
						💡 Flush rewrite rules to fix permanently
					</div>
				</div>
			</div>
			<?php
		} );
	}

	/**
	 * Clear all caches
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$cache = [];
		// Caching disabled
		self::log_debug( 'Caches cleared' );
	}

	/**
	 * Log debug message
	 *
	 * @since 3.0.0
	 * @param string $message The debug message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	private static function log_debug( string $message, array $context = [] ): void {
		if ( ! self::$debug_mode ) {
			return;
		}

		$log_message = self::DEBUG_LOG_PREFIX . ' ' . $message;
		if ( ! empty( $context ) ) {
			$log_message .= ' | Context: ' . wp_json_encode( $context );
		}

		error_log( $log_message );
	}

	/**
	 * Log error message
	 *
	 * @since 3.0.0
	 * @param string $operation The operation that failed.
	 * @param string $error The error message.
	 * @return void
	 */
	private static function log_error( string $operation, string $error ): void {
		error_log( self::DEBUG_LOG_PREFIX . ' ERROR in ' . $operation . ': ' . $error );
	}
}
