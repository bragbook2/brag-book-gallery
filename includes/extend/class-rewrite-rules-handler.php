<?php
/**
 * Rewrite Rules Handler Class
 *
 * Manages URL rewriting and query variables for gallery pages.
 * Handles SEO-friendly URLs and page routing for the gallery system.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Extend;

use WP_Post;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite Rules Handler Class
 *
 * Handles all URL rewriting and query variables for the BRAGBookGallery plugin.
 * Provides SEO-friendly URLs for gallery pages, procedures, and case details.
 *
 * @since 3.0.0
 */
class Rewrite_Rules_Handler {

	/**
	 * Custom query variables used by the plugin
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const QUERY_VARS = array(
		'procedure_title',
		'case_suffix',
		'favorites_section',
		'filter_category',
		'filter_procedure',
		'favorites_page',
	);

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
	 * Register rewrite rules and hooks
	 *
	 * Sets up all necessary hooks for URL rewriting functionality.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'custom_rewrite_rules' ), 5 );
		add_action( 'init', array( __CLASS__, 'custom_rewrite_flush' ), 10 );
		add_action( 'init', array( __CLASS__, 'register_query_vars' ), 10 );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ), 10, 1 );
		add_action( 'admin_notices', array( __CLASS__, 'rewrite_debug_notice' ) );
		
		// Handle AJAX flush request.
		add_action( 'wp_ajax_bragbook_flush_rewrite', array( __CLASS__, 'ajax_flush_rewrite' ) );
	}

	/**
	 * Handle AJAX rewrite flush request
	 *
	 * Processes AJAX requests to flush rewrite rules from admin notices.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function ajax_flush_rewrite(): void {
		// Verify nonce for security.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bragbook_flush' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'brag-book-gallery' ) ) );
			return;
		}
		
		// Verify user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'brag-book-gallery' ) ) );
			return;
		}
		
		// Flush rewrite rules.
		flush_rewrite_rules();
		
		// Clear the transient.
		delete_transient( 'bragbook_show_rewrite_notice' );
		
		// Clear cache.
		wp_cache_delete( self::CACHE_KEY_GALLERY_PAGES, self::CACHE_GROUP );
		
		wp_send_json_success( array( 'message' => __( 'Rewrite rules flushed successfully.', 'brag-book-gallery' ) ) );
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
	 * Add custom query vars to WordPress
	 *
	 * Filters the list of public query variables to include custom ones.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string> $vars Existing query vars.
	 *
	 * @return array<string> Modified query vars including custom ones.
	 */
	public static function add_query_vars( array $vars ): array {
		return array_merge( $vars, self::QUERY_VARS );
	}

	/**
	 * Define custom rewrite rules for gallery pages
	 *
	 * Creates rewrite rules for SEO-friendly URLs based on gallery page detection.
	 * Supports multiple detection methods for flexibility and backwards compatibility.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function custom_rewrite_rules(): void {
		// Track processed slugs to avoid duplicates.
		$processed_slugs = array();
		
		// First, auto-detect pages with the [brag_book_gallery] shortcode.
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
	 * Find pages containing the gallery shortcode
	 *
	 * Queries the database for pages containing the gallery shortcode.
	 * Results are cached for performance optimization.
	 *
	 * @since 3.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return array Array of page objects containing the shortcode.
	 */
	private static function find_pages_with_gallery_shortcode(): array {
		global $wpdb;
		
		// Check cache first.
		$cached_pages = wp_cache_get( self::CACHE_KEY_GALLERY_PAGES, self::CACHE_GROUP );
		if ( false !== $cached_pages ) {
			return $cached_pages;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Needed for LIKE query with shortcode detection.
		$pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_title, post_content
				FROM {$wpdb->posts}
				WHERE post_type = 'page'
				AND post_status = 'publish'
				AND post_content LIKE %s",
				'%[brag_book_gallery%'
			)
		);

		$result = is_array( $pages ) ? $pages : array();
		
		// Cache for 1 hour.
		wp_cache_set( self::CACHE_KEY_GALLERY_PAGES, $result, self::CACHE_GROUP, HOUR_IN_SECONDS );
		
		return $result;
	}

	/**
	 * Get page slug from page path
	 *
	 * Retrieves the slug for a page given its hierarchical path.
	 *
	 * @since 3.0.0
	 *
	 * @param string $page_path The page path to look up.
	 *
	 * @return string Page slug or empty string if not found.
	 */
	private static function get_page_slug_from_path( string $page_path ): string {
		// Sanitize the page path.
		$page_path = sanitize_text_field( $page_path );
		
		if ( empty( $page_path ) ) {
			return '';
		}
		
		$page = get_page_by_path( $page_path, OBJECT, 'page' );

		if ( ! $page instanceof WP_Post ) {
			return '';
		}

		$post = get_post( $page->ID );

		return ( $post instanceof WP_Post && isset( $post->post_name ) )
			? sanitize_title( $post->post_name )
			: '';
	}

	/**
	 * Add rewrite rules for a specific gallery page
	 *
	 * Creates SEO-friendly URL patterns for a gallery page including
	 * favorites, procedure filtering, and case detail views.
	 *
	 * @since 3.0.0
	 *
	 * @param string $page_slug The page slug to add rules for.
	 *
	 * @return void
	 */
	private static function add_gallery_rewrite_rules( string $page_slug ): void {
		// Sanitize the slug.
		$page_slug = sanitize_title( $page_slug );
		
		if ( empty( $page_slug ) ) {
			return;
		}
		
		// Try to find the page by slug.
		$page = get_page_by_path( $page_slug );

		if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
			// Use page_id for existing pages to avoid conflicts.
			$base_query = sprintf( 'index.php?page_id=%d', absint( $page->ID ) );
		} else {
			// Check if this matches brag_book_gallery_page_slug and has a page_id set.
			$is_gallery_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::is_gallery_slug( $page_slug );
			$brag_book_gallery_page_id = get_option( 'brag_book_gallery_page_id', 0 );

			if ( $is_gallery_slug && ! empty( $brag_book_gallery_page_id ) ) {
				$base_query = sprintf( 'index.php?page_id=%d', absint( $brag_book_gallery_page_id ) );
			} else {
				// Fallback to pagename (this might cause 404s if page doesn't exist).
				$base_query = sprintf( 'index.php?pagename=%s', $page_slug );
			}
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
	 * Get page slug from page ID
	 *
	 * Retrieves the slug for a page given its ID.
	 *
	 * @since 3.0.0
	 *
	 * @param int $page_id The page ID to look up.
	 *
	 * @return string Page slug or empty string if not found.
	 */
	private static function get_page_slug_from_id( int $page_id ): string {
		if ( $page_id <= 0 ) {
			return '';
		}
		
		$post = get_post( $page_id );

		return ( $post instanceof WP_Post && isset( $post->post_name ) )
			? sanitize_title( $post->post_name )
			: '';
	}

	/**
	 * Flush rewrite rules with custom rules applied
	 *
	 * Conditionally flushes rewrite rules when requested via option.
	 * This avoids performance issues from frequent flushing.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function custom_rewrite_flush(): void {
		// Ensure the custom rewrite rules are registered.
		self::custom_rewrite_rules();

		// Only flush when explicitly needed (avoids performance issues).
		if ( get_option( 'brag_book_gallery_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'brag_book_gallery_flush_rewrite_rules' );
			
			// Clear cache after flush.
			wp_cache_delete( self::CACHE_KEY_GALLERY_PAGES, self::CACHE_GROUP );
		}
	}

	/**
	 * Display admin notice for rewrite rules debugging
	 *
	 * Shows a warning notice when rewrite rules may need to be flushed.
	 * Provides a button to flush rules via AJAX.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function rewrite_debug_notice(): void {
		// Only show on admin pages and to administrators.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if we should show the notice.
		if ( ! get_transient( 'bragbook_show_rewrite_notice' ) ) {
			return;
		}

		$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', array() );
		$rewrite_rules = get_option( 'rewrite_rules', array() );

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
}
