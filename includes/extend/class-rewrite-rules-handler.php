<?php
/**
 * Rewrite rules handler for BRAGBookGallery plugin.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

use WP_Post;

/**
 * Class Rewrite_Rules_Handler
 *
 * Handles all URL rewriting and query variables for the BRAGBookGallery plugin.
 *
 * @since 3.0.0
 */
class Rewrite_Rules_Handler {

	/**
	 * Register rewrite rules and hooks.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', [ __CLASS__, 'custom_rewrite_rules' ], 5 );
		add_action( 'init', [ __CLASS__, 'custom_rewrite_flush' ], 10 );
		add_action( 'init', [ __CLASS__, 'register_query_vars' ], 10 );
		add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ], 10, 1 );
		add_action( 'admin_notices', [ __CLASS__, 'rewrite_debug_notice' ] );
	}

	/**
	 * Register custom query vars with WordPress.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function register_query_vars(): void {
		global $wp;

		$query_vars = [ 'procedure_title', 'case_suffix', 'favorites_section', 'filter_category', 'filter_procedure', 'favorites_page' ];

		foreach ( $query_vars as $var ) {
			$wp->add_query_var( $var );
		}
	}

	/**
	 * Add custom query vars to WordPress.
	 *
	 * @since 3.0.0
	 * @param array<string> $vars Existing query vars.
	 * @return array<string> Modified query vars.
	 */
	public static function add_query_vars( array $vars ): array {
		return [
			...$vars,
			'procedure_title',
			'case_suffix',
			'favorites_section',
			'filter_category',
			'filter_procedure',
			'favorites_page',
		];
	}

	/**
	 * Define custom rewrite rules for gallery pages.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function custom_rewrite_rules(): void {
		// First, auto-detect pages with the [brag_book_gallery] shortcode
		$pages_with_shortcode = self::find_pages_with_gallery_shortcode();
		$processed_slugs = [];

		// Add rewrite rules for each detected page
		foreach ( $pages_with_shortcode as $page ) {
			if ( ! empty( $page->post_name ) && ! in_array( $page->post_name, $processed_slugs ) ) {
				self::add_gallery_rewrite_rules( $page->post_name );
				$processed_slugs[] = $page->post_name;
			}
		}

		// Check for brag_book_gallery_page_slug option (used in settings)
		// Use the helper to get all slugs (handles both array and string formats)
		$gallery_page_slugs = \BRAGBookGallery\Includes\Core\Slug_Helper::get_all_gallery_page_slugs();
		foreach ( $gallery_page_slugs as $slug ) {
			if ( ! empty( $slug ) && ! in_array( $slug, $processed_slugs ) ) {
				self::add_gallery_rewrite_rules( $slug );
				$processed_slugs[] = $slug;
			}
		}

		// Also check saved options as fallback (legacy support)
		$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug' ) ?: [];

		// Ensure we have an array
		if ( ! is_array( $gallery_slugs ) ) {
			// Fallback to stored pages method
			$stored_pages = get_option( 'brag_book_gallery_stored_pages' ) ?: [];

			if ( ! is_array( $stored_pages ) ) {
				// If no stored configuration and no pages found with shortcode, we're done
				if ( empty( $pages_with_shortcode ) ) {
					return;
				}
			} else {
				// Process each stored page
				foreach ( $stored_pages as $page_path ) {
					$page_slug = self::get_page_slug_from_path( $page_path );

					if ( ! empty( $page_slug ) && ! in_array( $page_slug, $processed_slugs ) ) {
						self::add_gallery_rewrite_rules( $page_slug );
						$processed_slugs[] = $page_slug;
					}
				}
			}
		} else {
			// Process gallery slugs directly
			foreach ( $gallery_slugs as $page_slug ) {
				if ( ! empty( $page_slug ) && is_string( $page_slug ) && ! in_array( $page_slug, $processed_slugs ) ) {
					self::add_gallery_rewrite_rules( $page_slug );
					$processed_slugs[] = $page_slug;
				}
			}
		}

		// Add combined gallery rules
		self::add_combined_gallery_rewrite_rules();
	}

	/**
	 * Find pages containing the gallery shortcode.
	 *
	 * @since 3.0.0
	 * @return array Array of page objects.
	 */
	private static function find_pages_with_gallery_shortcode(): array {
		global $wpdb;

		// Query for pages containing our shortcode
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

		return $pages ?: [];
	}

	/**
	 * Get page slug from page path.
	 *
	 * @since 3.0.0
	 * @param string $page_path The page path.
	 * @return string Page slug or empty string if not found.
	 */
	private static function get_page_slug_from_path( string $page_path ): string {
		$page = get_page_by_path( $page_path, OBJECT, 'page' );

		if ( ! $page instanceof WP_Post ) {
			return '';
		}

		$post = get_post( $page->ID );

		return ( $post instanceof WP_Post && isset( $post->post_name ) )
			? $post->post_name
			: '';
	}

	/**
	 * Add rewrite rules for a specific gallery page.
	 *
	 * @since 3.0.0
	 * @param string $page_slug The page slug.
	 * @return void
	 */
	private static function add_gallery_rewrite_rules( string $page_slug ): void {
		// Try to find the page by slug
		$page = get_page_by_path( $page_slug );

		if ( $page && $page->post_status === 'publish' ) {
			// Use page_id for existing pages to avoid conflicts
			$base_query = sprintf( 'index.php?page_id=%d', $page->ID );
		} else {
			// Check if this matches brag_book_gallery_page_slug and has a page_id set
			$is_gallery_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::is_gallery_slug( $page_slug );
			$brag_book_gallery_page_id = get_option( 'brag_book_gallery_page_id' );

			if ( $is_gallery_slug && ! empty( $brag_book_gallery_page_id ) ) {
				$base_query = sprintf( 'index.php?page_id=%d', absint( $brag_book_gallery_page_id ) );
			} else {
				// Fallback to pagename (this might cause 404s if page doesn't exist)
				$base_query = sprintf( 'index.php?pagename=%s', $page_slug );
			}
		}

		$rewrite_rules = [
			// My Favorites page: /gallery/myfavorites
			[
				'regex' => "^{$page_slug}/myfavorites/?$",
				'query' => "{$base_query}&favorites_page=1",
			],
			// Case detail: /gallery/procedure-name/identifier
			// This single pattern captures both numeric IDs and SEO suffixes
			// The identifier can contain letters, numbers, hyphens, underscores, and dots
			[
				'regex' => "^{$page_slug}/([^/]+)/([a-zA-Z0-9\-_\.]+)/?$",
				'query' => "{$base_query}&procedure_title=\$matches[1]&case_suffix=\$matches[2]",
			],
			// Procedure page: /gallery/procedure-name
			[
				'regex' => "^{$page_slug}/([^/]+)/?$",
				'query' => "{$base_query}&filter_procedure=\$matches[1]",
			],
		];

		foreach ( $rewrite_rules as $rule ) {
			add_rewrite_rule(
				$rule['regex'],
				$rule['query'],
				'top'
			);
		}
	}

	/**
	 * Add rewrite rules for combined gallery pages.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private static function add_combined_gallery_rewrite_rules(): void {
		$brag_book_gallery_page_id = get_option( 'brag_book_gallery_page_id' );

		if ( empty( $brag_book_gallery_page_id ) ) {
			return;
		}

		$page_slug = self::get_page_slug_from_id( absint( $brag_book_gallery_page_id ) );

		if ( ! empty( $page_slug ) ) {
			self::add_gallery_rewrite_rules( $page_slug );
		}
	}

	/**
	 * Get page slug from page ID.
	 *
	 * @since 3.0.0
	 * @param int $page_id The page ID.
	 * @return string Page slug or empty string if not found.
	 */
	private static function get_page_slug_from_id( int $page_id ): string {
		$post = get_post( $page_id );

		return ( $post instanceof WP_Post && isset( $post->post_name ) )
			? $post->post_name
			: '';
	}

	/**
	 * Flush rewrite rules with custom rules applied.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function custom_rewrite_flush(): void {
		// Ensure the custom rewrite rules are registered
		self::custom_rewrite_rules();

		// Only flush when explicitly needed (avoids performance issues)
		if ( get_option( 'brag_book_gallery_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'brag_book_gallery_flush_rewrite_rules' );
		}
	}

	/**
	 * Display admin notice for rewrite rules debugging.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function rewrite_debug_notice(): void {
		// Only show on admin pages and to administrators
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if we should show the notice
		if ( ! get_transient( 'bragbook_show_rewrite_notice' ) ) {
			return;
		}

		$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
		$rewrite_rules = get_option( 'rewrite_rules', [] );

		// Check if our rules exist
		$rules_exist = false;
		if ( ! empty( $gallery_slugs ) && is_array( $gallery_slugs ) ) {
			foreach ( $gallery_slugs as $slug ) {
				if ( isset( $rewrite_rules["^{$slug}/([^/]+)/([0-9]+)/?$"] ) ) {
					$rules_exist = true;
					break;
				}
			}
		}

		if ( ! $rules_exist ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p><strong>BRAGBook Gallery:</strong> Rewrite rules may need to be flushed.</p>
				<p>Gallery slugs: <?php echo esc_html( implode( ', ', $gallery_slugs ) ); ?></p>
				<p>
					<button class="button button-primary" id="bragbook-flush-rewrite">Flush Rewrite Rules</button>
				</p>
			</div>
			<script>
			jQuery(document).ready(function($) {
				$('#bragbook-flush-rewrite').on('click', function() {
					var button = $(this);
					button.prop('disabled', true).text('Flushing...');

					$.post(ajaxurl, {
						action: 'bragbook_flush_rewrite',
						_wpnonce: '<?php echo wp_create_nonce( 'bragbook_flush' ); ?>'
					}, function(response) {
						if (response.success) {
							button.text('Success!');
							location.reload();
						} else {
							button.text('Error').prop('disabled', false);
						}
					});
				});
			});
			</script>
			<?php
		}
	}
}
