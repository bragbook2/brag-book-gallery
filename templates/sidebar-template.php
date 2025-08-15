<?php
/**
 * Template Name: Sidebar Template
 *
 * Renders the sidebar navigation with procedures menu and favorites.
 * Uses modern PHP 8.2 features and WP VIP coding standards.
 *
 * @package BRAGBook
 * @since   3.0.0
 */

declare(strict_types=1);

use BRAGBookGallery\Includes\REST\Endpoints;
use BRAGBookGallery\Includes\Core\Setup;

// Prevent direct access
if ( ! defined( constant_name: 'ABSPATH' ) ) {
	exit;
}

/**
 * Get current page information
 */
$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
$case_url = strtok( $request_uri, '?' );
$case_url_clean = trim( $case_url, '/' );
$url_parts = explode( '/', $case_url_clean );
$current_page_slug = $url_parts[0] ?? '';

/**
 * Get gallery configuration
 */
$combine_gallery_page_id = get_option( option: 'combine_gallery_page_id', default_value: 0 );
$combine_gallery_slug = get_option( option: 'combine_gallery_slug', default_value: '' );
$current_page = get_page_by_path( $current_page_slug );
$current_page_id = $current_page instanceof WP_Post ? $current_page->ID : 0;

/**
 * Get sidebar data for the current page
 */
$sidebar_data = get_sidebar_data_for_page( $current_page_slug, $combine_gallery_slug );

/**
 * Get favorites count
 */
$favorites_count = absint( get_option( 'brag_book_gallery_favorite_case_ids_count', 0 ) );

?>
<div class="brag-book-gallery-sidebar">
	<div class="brag-book-gallery-sidebar-wrapper">
		<button type="button"
		        class="brag-book-gallery-sidebar-toggle brag-book-gallery-sidebar-head-toggle"
		        aria-label="<?php esc_attr_e( 'Toggle sidebar', 'brag-book-gallery' ); ?>"
		        aria-expanded="false">
			<img src="<?php echo esc_url( Setup::get_asset_url( asset_path: 'assets/images/caret-right-sm.svg' ) ); ?>"
			     alt="toggle">
		</button>

		<form class="search-container" role="search">
			<label for="search-bar" class="screen-reader-text">
				<?php esc_html_e( 'Search procedures', 'brag-book-gallery' ); ?>
			</label>
			<input type="text"
			       id="search-bar"
			       placeholder="<?php esc_attr_e( 'Search...', 'brag-book-gallery' ); ?>"
			       aria-describedby="search-help">
			<img src="<?php echo esc_url( Setup::get_asset_url( asset_path: 'assets/images/search-svgrepo-com.svg' ) ); ?>"
			     class="brag-book-gallery-search-icon"
			     alt="search"
			     aria-hidden="true">
			<span id="search-help" class="screen-reader-text">
				<?php esc_html_e( 'Type to search procedures', 'brag-book-gallery' ); ?>
			</span>
			<ul id="search-suggestions" class="search-suggestions" role="listbox"></ul>
		</form>

		<nav class="brag-book-gallery-nav-accordion" aria-label="<?php esc_attr_e( 'Procedures navigation', 'brag-book-gallery' ); ?>">
			<?php if ( ! empty( $sidebar_data ) && is_array( $sidebar_data ) ) : ?>
				<?php foreach ( $sidebar_data as $category_key => $category_data ) : ?>
					<?php
					if ( ! is_array( $category_data ) || empty( $category_data['name'] ) ) {
						continue;
					}

					$category_name = esc_html( $category_data['name'] );
					$total_cases = absint( $category_data['totalCase'] ?? 0 );
					$procedures = $category_data['procedures'] ?? [];
					?>

					<div class="brag-book-gallery-accordion-section">
						<button type="button"
						        class="brag-book-gallery-accordion"
						        data-category="<?php echo esc_attr( $category_name ); ?>"
						        aria-expanded="false"
						        aria-controls="panel-<?php echo esc_attr( $category_key ); ?>">
							<h3>
								<?php echo $category_name; ?>
								<span class="case-count">(<?php echo $total_cases; ?>)</span>
							</h3>
							<img src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/plus-icon.svg' ) ); ?>"
							     alt="expand"
							     class="accordion-icon"
							     aria-hidden="true">
						</button>

						<div class="brag-book-gallery-panel"
						     id="panel-<?php echo esc_attr( $category_key ); ?>"
						     style="display: none;"
						     aria-hidden="true">
							<ul class="procedures-list">
								<?php foreach ( $procedures as $procedure ) : ?>
									<?php
									if ( ! is_array( $procedure ) ) {
										continue;
									}

									$procedure_name = esc_html( $procedure['name'] ?? '' );
									$procedure_slug = esc_attr( $procedure['slugName'] ?? '' );
									$procedure_ids = $procedure['ids'] ?? [];
									$procedure_count = absint( $procedure['totalCase'] ?? 0 );
									$api_token = esc_attr( $sidebar_data['api_token'] ?? '' );
									$website_id = esc_attr( $sidebar_data['website_id'] ?? '' );

									if ( empty( $procedure_name ) || empty( $procedure_slug ) ) {
										continue;
									}
									?>
									<li>
										<a href="<?php echo esc_url( "/{$current_page_slug}/{$procedure_slug}/" ); ?>"
										   id="<?php echo esc_attr( implode( ',', array_map( 'absint', (array) $procedure_ids ) ) ); ?>"
										   data-count="1"
										   data-api-token="<?php echo $api_token; ?>"
										   data-website-property-id="<?php echo $website_id; ?>">
											<?php echo $procedure_name; ?>
											<span class="case-count">(<?php echo $procedure_count; ?>)</span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="brag-book-gallery-no-procedures">
					<p><?php esc_html_e( 'No procedures available at this time.', 'brag-book-gallery' ); ?></p>
				</div>
			<?php endif; ?>

			<ul class="brag-book-gallery-favorites-section">
				<li>
					<a class="brag-book-gallery-sidebar_favorites"
					   href="<?php echo esc_url( "/{$current_page_slug}/favorites/" ); ?>">
						<h3>
							<?php esc_html_e( 'My Favorites', 'brag-book-gallery' ); ?>
							<span id="brag_book_gallery_favorite_caseIds_count" class="case-count">
								(<?php echo $favorites_count; ?>)
							</span>
						</h3>
					</a>
				</li>
			</ul>
		</nav>
	</div>

	<a href="<?php echo esc_url( "/{$current_page_slug}/consultation/" ); ?>"
	   class="brag-book-gallery-sidebar-btn">
		<?php esc_html_e( 'REQUEST A CONSULTATION', 'brag-book-gallery' ); ?>
	</a>

	<p class="request-promo">
		<?php esc_html_e( 'Ready for the next step?', 'brag-book-gallery' ); ?><br>
		<?php esc_html_e( 'Contact us to request your consultation.', 'brag-book-gallery' ); ?>
	</p>
</div>

<script>
/**
 * Sidebar functionality
 */
document.addEventListener( 'DOMContentLoaded', function() {
	// Accordion functionality
	const accordions = document.querySelectorAll( '.brag-book-gallery-accordion' );

	accordions.forEach( accordion => {
		accordion.addEventListener( 'click', function() {
			const panel = this.nextElementSibling;
			const icon = this.querySelector( '.accordion-icon' );
			const isExpanded = this.getAttribute( 'aria-expanded' ) === 'true';

			// Toggle panel
			if ( panel ) {
				panel.style.display = isExpanded ? 'none' : 'block';
				panel.setAttribute( 'aria-hidden', isExpanded ? 'true' : 'false' );
			}

			// Update button state
			this.setAttribute( 'aria-expanded', isExpanded ? 'false' : 'true' );

			// Rotate icon
			if ( icon ) {
				icon.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(45deg)';
			}
		});
	});

	// Search functionality
	const searchBar = document.getElementById( 'search-bar' );
	const suggestions = document.getElementById( 'search-suggestions' );

	if ( searchBar && suggestions ) {
		searchBar.addEventListener( 'input', function() {
			const searchText = this.value.toLowerCase().trim();
			suggestions.innerHTML = '';

			if ( searchText.length < 2 ) {
				suggestions.style.display = 'none';
				return;
			}

			// Search through procedures
			const procedures = document.querySelectorAll( '.procedures-list a' );
			let hasResults = false;

			procedures.forEach( procedure => {
				const text = procedure.textContent.toLowerCase();
				if ( text.includes( searchText ) ) {
					const li = document.createElement( 'li' );
					li.setAttribute( 'role', 'option' );

					const link = document.createElement( 'a' );
					link.href = procedure.href;
					link.textContent = procedure.textContent;

					li.appendChild( link );
					suggestions.appendChild( li );
					hasResults = true;
				}
			});

			suggestions.style.display = hasResults ? 'block' : 'none';
		});

		// Hide suggestions when clicking outside
		document.addEventListener( 'click', function( e ) {
			if ( ! searchBar.contains( e.target ) && ! suggestions.contains( e.target ) ) {
				suggestions.style.display = 'none';
			}
		});
	}

	// Sidebar toggle
	const sidebarToggle = document.querySelector( '.brag-book-gallery-sidebar-head-toggle' );
	const sidebar = document.querySelector( '.brag-book-gallery-sidebar' );

	if ( sidebarToggle && sidebar ) {
		sidebarToggle.addEventListener( 'click', function() {
			const isExpanded = this.getAttribute( 'aria-expanded' ) === 'true';
			sidebar.classList.toggle( 'expanded' );
			this.setAttribute( 'aria-expanded', isExpanded ? 'false' : 'true' );
		});
	}
});
</script>

<?php
/**
 * Helper function to get sidebar data for the current page
 *
 * @param string $page_slug Current page slug
 * @param string $combine_slug Combined gallery slug
 * @return array Sidebar data
 */
function get_sidebar_data_for_page( string $page_slug, string $combine_slug ): array {
	// Get API configuration
	$api_tokens = get_option( 'bragbook_api_token', [] );
	$website_property_ids = get_option( 'bragbook_website_property_id', [] );
	$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );

	if ( ! is_array( $api_tokens ) || ! is_array( $website_property_ids ) || ! is_array( $gallery_slugs ) ) {
		return [];
	}

	// Find matching configuration
	foreach ( $api_tokens as $index => $api_token ) {
		$website_property_id = $website_property_ids[$index] ?? '';
		$gallery_slug = $gallery_slugs[$index] ?? '';

		// Check if this configuration matches current page
		if ( ( $gallery_slug === $page_slug || $combine_slug === $page_slug ) &&
		     ! empty( $api_token ) &&
		     ! empty( $website_property_id ) ) {

			// Check for cached data
			$transient_key = 'brag_book_gallery_sidebar_' . md5( $api_token );
			$cached_data = get_transient( $transient_key );

			if ( $cached_data !== false ) {
				$sidebar_data = json_decode( $cached_data, true );
				if ( is_array( $sidebar_data ) ) {
					$sidebar_data['api_token'] = $api_token;
					$sidebar_data['website_id'] = $website_property_id;
					return $sidebar_data['data'] ?? [];
				}
			}

			// Fetch fresh data
			try {
				$endpoints = new Endpoints();
				$data = $endpoints->get_api_sidebar( $api_token );

				if ( ! empty( $data ) ) {
					// Cache for 30 minutes
					set_transient( $transient_key, $data, 1800 );

					$sidebar_data = json_decode( $data, true );
					if ( is_array( $sidebar_data ) ) {
						$sidebar_data['api_token'] = $api_token;
						$sidebar_data['website_id'] = $website_property_id;
						return $sidebar_data['data'] ?? [];
					}
				}
			} catch ( Exception $e ) {
				error_log( 'BragBook Sidebar Error: ' . $e->getMessage() );
			}
		}
	}

	return [];
}
