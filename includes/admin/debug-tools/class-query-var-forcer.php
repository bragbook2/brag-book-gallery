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
 * @since 3.0.0
 */
class Query_Var_Forcer {

	/**
	 * Initialize the forcer
	 *
	 * @since 3.0.0
	 */
	public static function init(): void {
		// Hook early to catch 404s
		add_action( 'template_redirect', [ __CLASS__, 'force_gallery_query_vars' ], 1 );
		add_filter( 'request', [ __CLASS__, 'modify_request_vars' ], 1 );
		add_action( 'parse_request', [ __CLASS__, 'debug_parse_request' ], 1 );
	}

	/**
	 * Force gallery query vars on 404
	 *
	 * @since 3.0.0
	 */
	public static function force_gallery_query_vars(): void {
		global $wp_query;

		if ( ! is_404() ) {
			return;
		}

		$request_uri = trim( $_SERVER['REQUEST_URI'], '/' );
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
			// Page doesn't exist - let's check if we have a brag_book_gallery_page_id
			$combine_page_id = get_option( 'brag_book_gallery_page_id' );
			if ( $combine_page_id ) {
				$page = get_post( $combine_page_id );
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
		
		// Set query vars based on URL structure
		if ( isset( $path_parts[1] ) && isset( $path_parts[2] ) && is_numeric( $path_parts[2] ) ) {
			// Case detail: /gallery/procedure-name/case-id
			set_query_var( 'procedure_title', $path_parts[1] );
			set_query_var( 'case_id', $path_parts[2] );
		} elseif ( isset( $path_parts[1] ) ) {
			// Procedure page: /gallery/procedure-name
			set_query_var( 'filter_procedure', $path_parts[1] );
		}

		// Force the page to load
		status_header( 200 );
		
		// Add debug notice for admins
		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'wp_footer', function() use ( $gallery_slug, $path_parts ) {
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
			});
		}
	}

	/**
	 * Modify request vars before query
	 *
	 * @param array $query_vars Query vars
	 * @return array Modified query vars
	 * @since 3.0.0
	 */
	public static function modify_request_vars( $query_vars ) {
		// Get the request path
		$request_path = isset( $query_vars['pagename'] ) ? $query_vars['pagename'] : '';
		
		if ( empty( $request_path ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
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
				$page = get_post( $combine_page_id );
			}
		}

		if ( $page ) {
			// Set the page_id
			$query_vars['page_id'] = $page->ID;
			$query_vars['pagename'] = '';
			
			// Add custom query vars
			if ( isset( $path_parts[1] ) && isset( $path_parts[2] ) && is_numeric( $path_parts[2] ) ) {
				$query_vars['procedure_title'] = $path_parts[1];
				$query_vars['case_id'] = $path_parts[2];
			} elseif ( isset( $path_parts[1] ) ) {
				$query_vars['filter_procedure'] = $path_parts[1];
			}
		}

		return $query_vars;
	}

	/**
	 * Debug parse request
	 *
	 * @param \WP $wp WP object
	 * @since 3.0.0
	 */
	public static function debug_parse_request( $wp ) {
		if ( ! current_user_can( 'manage_options' ) || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$path_parts = explode( '/', trim( $request_uri, '/' ) );
		$gallery_slugs = Slug_Helper::get_all_gallery_page_slugs();

		if ( ! empty( $path_parts[0] ) && in_array( $path_parts[0], $gallery_slugs, true ) ) {
			error_log( 'Gallery Request Debug:' );
			error_log( '  Request URI: ' . $request_uri );
			error_log( '  Query Vars: ' . print_r( $wp->query_vars, true ) );
			error_log( '  Matched Rule: ' . ( $wp->matched_rule ?? 'none' ) );
			error_log( '  Matched Query: ' . ( $wp->matched_query ?? 'none' ) );
		}
	}
}