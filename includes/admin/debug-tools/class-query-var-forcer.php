<?php
/**
 * Query Var Forcer Debug Tool
 *
 * Forces query vars for gallery pages to prevent 404 errors during development.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Admin\Debug_Tools;

use BRAGBookGallery\Includes\Core\Slug_Helper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Var Forcer Class
 *
 * Forces proper query variables for gallery pages that may be experiencing
 * 404 errors due to rewrite rule issues. This is primarily a development
 * and debugging tool to ensure gallery pages load correctly.
 *
 * @since 3.0.0
 */
class Query_Var_Forcer {

	/**
	 * Initialize the query var forcer
	 *
	 * Hooks into WordPress request lifecycle to intercept and modify
	 * query variables for gallery pages.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function init(): void {
		// Hook early to catch 404s
		add_action( 'template_redirect', [ __CLASS__, 'force_gallery_query_vars' ], 1 );
		add_filter( 'request', [ __CLASS__, 'modify_request_vars' ], 1 );
		add_action( 'parse_request', [ __CLASS__, 'debug_parse_request' ], 1 );
	}

	/**
	 * Force gallery query vars on 404 errors
	 *
	 * Intercepts 404 errors for gallery pages and forces proper query
	 * variables to allow the page to load correctly.
	 *
	 * @since 3.0.0
	 * @global \WP_Query $wp_query The main WordPress query object.
	 * @return void
	 */
	public static function force_gallery_query_vars(): void {
		global $wp_query;

		if ( ! is_404() ) {
			return;
		}

		// Get request URI and parse path parts
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) 
			? trim( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/' )
			: '';
			
		if ( empty( $request_uri ) ) {
			return;
		}
		
		$path_parts = explode( '/', $request_uri );

		// Check if first part matches a gallery slug
		if ( empty( $path_parts[0] ) ) {
			return;
		}

		$gallery_slugs = Slug_Helper::get_all_gallery_page_slugs();
		
		if ( ! in_array( $path_parts[0], $gallery_slugs, true ) ) {
			return;
		}

		// We have a gallery page that's 404ing - force it!
		$gallery_slug = $path_parts[0];
		
		// Find the page with this slug
		$page = get_page_by_path( $gallery_slug );
		
		if ( ! $page ) {
			// Page doesn't exist - check for gallery page ID option
			$combine_page_id = get_option( 'brag_book_gallery_page_id' );
			if ( $combine_page_id ) {
				$page = get_post( (int) $combine_page_id );
			}
		}

		if ( ! $page ) {
			return;
		}

		// Reset the query to show this page
		$wp_query->is_404 = false;
		$wp_query->is_page = true;
		$wp_query->is_singular = true;
		$wp_query->queried_object = $page;
		$wp_query->queried_object_id = $page->ID;
		
		// Set query vars based on URL structure using match expression
		$query_var_result = match ( true ) {
			isset( $path_parts[1], $path_parts[2] ) && is_numeric( $path_parts[2] ) => [
				// Case detail: /gallery/procedure-name/case-id
				'procedure_title' => $path_parts[1],
				'case_id' => $path_parts[2],
			],
			isset( $path_parts[1] ) => [
				// Procedure page: /gallery/procedure-name
				'filter_procedure' => $path_parts[1],
			],
			default => [],
		};

		// Apply query vars
		foreach ( $query_var_result as $key => $value ) {
			set_query_var( $key, $value );
		}

		// Force the page to load with 200 status
		status_header( 200 );
		
		// Add debug notice for administrators
		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'wp_footer', static function() use ( $gallery_slug, $path_parts ): void {
				?>
				<div style="position: fixed; bottom: 0; right: 0; background: #ff9800; color: white; padding: 10px; z-index: 99999; max-width: 400px;">
					<strong>🔧 Query Var Forcer Active</strong><br>
					Forced gallery page: <?php echo esc_html( $gallery_slug ); ?><br>
					<?php if ( isset( $path_parts[1] ) ) : ?>
						Procedure: <?php echo esc_html( $path_parts[1] ); ?><br>
					<?php endif; ?>
					<?php if ( isset( $path_parts[2] ) ) : ?>
						Case ID: <?php echo esc_html( $path_parts[2] ); ?><br>
					<?php endif; ?>
					<small>Flush rewrite rules to fix permanently</small>
				</div>
				<?php
			} );
		}
	}

	/**
	 * Modify request vars before query execution
	 *
	 * Filters the request query variables to ensure gallery pages
	 * are properly recognized and loaded.
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $query_vars The current query variables.
	 * @return array<string, mixed> Modified query variables.
	 */
	public static function modify_request_vars( array $query_vars ): array {
		// Get the request path
		$request_path = $query_vars['pagename'] ?? '';
		
		if ( empty( $request_path ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$request_path = trim( (string) parse_url( $request_uri, PHP_URL_PATH ), '/' );
		}

		if ( empty( $request_path ) ) {
			return $query_vars;
		}

		$path_parts = explode( '/', $request_path );
		$gallery_slugs = Slug_Helper::get_all_gallery_page_slugs();

		// Check if this is a gallery page
		if ( ! in_array( $path_parts[0], $gallery_slugs, true ) ) {
			return $query_vars;
		}

		// Find the page
		$page = get_page_by_path( $path_parts[0] );
		if ( ! $page ) {
			$combine_page_id = get_option( 'brag_book_gallery_page_id' );
			if ( $combine_page_id ) {
				$page = get_post( (int) $combine_page_id );
			}
		}

		if ( $page ) {
			// Set the page_id
			$query_vars['page_id'] = $page->ID;
			$query_vars['pagename'] = '';
			
			// Add custom query vars using match expression
			$custom_vars = match ( true ) {
				isset( $path_parts[1], $path_parts[2] ) && is_numeric( $path_parts[2] ) => [
					'procedure_title' => $path_parts[1],
					'case_id' => $path_parts[2],
				],
				isset( $path_parts[1] ) => [
					'filter_procedure' => $path_parts[1],
				],
				default => [],
			};

			// Merge custom vars into query vars
			$query_vars = array_merge( $query_vars, $custom_vars );
		}

		return $query_vars;
	}

	/**
	 * Debug parse request for gallery pages
	 *
	 * Logs debug information about gallery page requests when
	 * WP_DEBUG is enabled and user has appropriate permissions.
	 *
	 * @since 3.0.0
	 * @param \WP $wp The WordPress environment instance.
	 * @return void
	 */
	public static function debug_parse_request( \WP $wp ): void {
		if ( ! current_user_can( 'manage_options' ) || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) 
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
			
		$path_parts = explode( '/', trim( $request_uri, '/' ) );
		$gallery_slugs = Slug_Helper::get_all_gallery_page_slugs();

		if ( ! empty( $path_parts[0] ) && in_array( $path_parts[0], $gallery_slugs, true ) ) {
			// Use error_log for debug output (WP VIP compatible)
			error_log( 'Gallery Request Debug:' );
			error_log( '  Request URI: ' . $request_uri );
			error_log( '  Query Vars: ' . print_r( $wp->query_vars, true ) );
			error_log( '  Matched Rule: ' . ( $wp->matched_rule ?? 'none' ) );
			error_log( '  Matched Query: ' . ( $wp->matched_query ?? 'none' ) );
		}
	}
}