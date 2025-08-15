<?php
/**
 * Template Name: Case List Page Template
 *
 * Displays a filterable list of gallery cases for a specific procedure.
 * Includes advanced filtering options with modern PHP 8.2 features.
 *
 * @package BRAGBook
 * @since   3.0.0
 */

declare(strict_types=1);

use BRAGBookGallery\Includes\Core\Setup;

// Prevent direct access
if ( ! defined( constant_name: 'ABSPATH' ) ) {
	exit;
}

get_header();

/**
 * Get procedure variables from the main template
 * These should be set by brag-book-gallery-brag.php before including this template
 */
$procedure_title = $procedure_title ?? '';
$procedure_id = $procedure_id ?? '';
$page_id = $page_id_via_slug ?? 0;

// Sanitize all variables.
$procedure_title = sanitize_text_field( $procedure_title );
$procedure_id = sanitize_text_field( $procedure_id );
$page_id = absint( $page_id );

?>
<div class="brag-book-gallery-container-main">
	<main class="brag-book-gallery-main">
		<?php
		// Include sidebar template
		include plugin_dir_path( __FILE__ ) . 'sidebar-template.php';
		?>

		<div class="brag-book-gallery-content-area">
			<div class="brag-book-gallery-filter-attic brag-book-gallery-filter-attic-borderless">
				<button type="button" class="brag-book-gallery-sidebar-toggle" aria-label="Toggle sidebar">
					<img src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/menu-icon.svg' ) ); ?>"
					     style="padding:3px;"
					     alt="toggle sidebar">
				</button>

				<div class="brag-book-gallery-search-container-outer">
					<form class="search-container mobile-search-container">
						<label for="mobile-search-bar" class="screen-reader-text">
							<?php esc_html_e( 'Search Procedures', 'brag-book-gallery' ); ?>
						</label>
						<input type="text"
						       id="mobile-search-bar"
						       placeholder="<?php esc_attr_e( 'Search Procedures', 'brag-book-gallery' ); ?>">
						<img src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/search-svgrepo-com.svg' ) ); ?>"
						     class="brag-book-gallery-search-icon"
						     alt="search">
						<ul id="mobile-search-suggestions" class="search-suggestions"></ul>
					</form>

					<h1 id="procedure-title">
						<span><?php echo esc_html( $procedure_title ); ?></span>
					</h1>
				</div>

				<div class="actions-box">
					<div class="action-box actions-filter">
						<button type="button"
						        class="action-box-toggle toggle-on-click toggle-actions-filter-box"
						        aria-label="<?php esc_attr_e( 'Toggle filters', 'brag-book-gallery' ); ?>"
						        aria-expanded="false">
							<svg xmlns="http://www.w3.org/2000/svg"
							     fill="none"
							     viewBox="2 2 20 20"
							     width="20"
							     height="20"
							     aria-hidden="true">
								<path d="M3 4.6C3 4.03995 3 3.75992 3.10899 3.54601C3.20487 3.35785 3.35785 3.20487 3.54601 3.10899C3.75992 3 4.03995 3 4.6 3H19.4C19.9601 3 20.2401 3 20.454 3.10899C20.6422 3.20487 20.7951 3.35785 20.891 3.54601C21 3.75992 21 4.03995 21 4.6V6.33726C21 6.58185 21 6.70414 20.9724 6.81923C20.9479 6.92127 20.9075 7.01881 20.8526 7.10828C20.7908 7.2092 20.7043 7.29568 20.5314 7.46863L14.4686 13.5314C14.2957 13.7043 14.2092 13.7908 14.1474 13.8917C14.0925 13.9812 14.0521 14.0787 14.0276 14.1808C14 14.2959 14 14.4182 14 14.6627V17L10 21V14.6627C10 14.4182 10 14.2959 9.97237 14.1808C9.94787 14.0787 9.90747 13.9812 9.85264 13.8917C9.7908 13.7908 9.70432 13.7043 9.53137 13.5314L3.46863 7.46863C3.29568 7.29568 3.2092 7.2092 3.14736 7.10828C3.09253 7.01881 3.05213 6.92127 3.02763 6.81923C3 6.70414 3 6.58185 3 6.33726V4.6Z"
								      stroke="#000000"
								      stroke-width="2"
								      stroke-linecap="round"
								      stroke-linejoin="round">
								</path>
							</svg>
							<span><?php esc_html_e( 'Filter', 'brag-book-gallery' ); ?></span>
						</button>

						<div class="actions-filter-box brag-book-gallery-filter-content brag-book-gallery-filter-content-mobile"
						     style="display: none;"
						     aria-hidden="true">
							<div>
								<div class="brag-book-gallery-filter-content-attic">
									<div class="attic-col-left">
										<button type="button"
										        class="toggle-on-click toggle-actions-filter-box toggle-action-box-toggle"
										        aria-label="<?php esc_attr_e( 'Close filters', 'brag-book-gallery' ); ?>">
											<img src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/cross-icon-new.svg' ) ); ?>"
											     alt="close">
										</button>
									</div>
									<div class="attic-col-right">
										<button type="button"
										        class="clear-attic"
										        id="clearButton">
											<?php esc_html_e( 'Clear All', 'brag-book-gallery' ); ?>
										</button>
									</div>
								</div>

								<div class="brag-book-gallery-filter-content-inner" id="filter-options">
									<?php
									/**
									 * Filter options will be loaded dynamically
									 * based on available case attributes
									 */
									?>
									<div class="brag-book-gallery-loading-filters">
										<p><?php esc_html_e( 'Loading filter options...', 'brag-book-gallery' ); ?></p>
									</div>
								</div>
							</div>

							<div class="actions-filter-box-inner actions-filter-box2">
								<div class="brag-book-gallery-filter-content-attic">
									<div class="attic-col-left">
										<button type="button"
										        class="toggle-on-click-multiple-close toggle-actions-filter-box2"
										        aria-label="<?php esc_attr_e( 'Back', 'brag-book-gallery' ); ?>">
											<img src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/arrow-left.svg' ) ); ?>"
											     alt="back">
										</button>
										<span><?php esc_html_e( 'Filter by:', 'brag-book-gallery' ); ?></span>
									</div>
									<div class="attic-col-right">
										<button type="button"
										        class="clear-attic-2"
										        id="clearButton2">
											<?php esc_html_e( 'Clear', 'brag-book-gallery' ); ?>
										</button>
									</div>
								</div>

								<div class="brag-book-gallery-filter-content-inner-2" id="filter-details">
									<?php
									/**
									 * Detailed filter options will be loaded here
									 */
									?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="brag-book-gallery-content-boxes" id="cases-container">
				<?php
				/**
				 * Case items will be loaded dynamically via JavaScript
				 */
				?>
				<div class="brag-book-gallery-loading-cases">
					<p><?php esc_html_e( 'Loading cases...', 'brag-book-gallery' ); ?></p>
				</div>
			</div>

			<div class="brag-book-gallery-pagination" id="cases-pagination">
				<?php
				/**
				 * Pagination will be added dynamically if needed
				 */
				?>
			</div>
		</div>
	</main>
</div>

<script>
/**
 * Case list page initialization and filtering
 */
document.addEventListener( 'DOMContentLoaded', function() {
	// Page data
	const procedureTitle = <?php echo wp_json_encode( $procedure_title ); ?>;
	const procedureId = <?php echo wp_json_encode( $procedure_id ); ?>;
	const pageId = <?php echo wp_json_encode( $page_id ); ?>;

	// Filter elements
	const filterToggle = document.querySelector( '.toggle-actions-filter-box' );
	const filterBox = document.querySelector( '.actions-filter-box' );
	const clearButton = document.getElementById( 'clearButton' );
	const clearButton2 = document.getElementById( 'clearButton2' );

	// Toggle filter panel
	if ( filterToggle && filterBox ) {
		filterToggle.addEventListener( 'click', function() {
			const isVisible = filterBox.style.display !== 'none';
			filterBox.style.display = isVisible ? 'none' : 'block';
			filterBox.setAttribute( 'aria-hidden', isVisible ? 'true' : 'false' );
			this.setAttribute( 'aria-expanded', isVisible ? 'false' : 'true' );
		});
	}

	// Clear all filters
	if ( clearButton ) {
		clearButton.addEventListener( 'click', function() {
			// Clear all selected filters
			const checkboxes = document.querySelectorAll( '.brag-book-gallery-filter-content input[type="checkbox"]' );
			checkboxes.forEach( checkbox => {
				checkbox.checked = false;
			});

			// Reload cases without filters
			loadCases();
		});
	}

	// Clear specific filter
	if ( clearButton2 ) {
		clearButton2.addEventListener( 'click', function() {
			// Clear filters in the secondary panel
			const checkboxes = document.querySelectorAll( '.brag-book-gallery-filter-content-inner-2 input[type="checkbox"]' );
			checkboxes.forEach( checkbox => {
				checkbox.checked = false;
			});
		});
	}

	/**
	 * Load cases based on current filters
	 */
	function loadCases( page = 1 ) {
		const container = document.getElementById( 'cases-container' );
		if ( ! container ) return;

		// Show loading state
		container.innerHTML = '<div class="brag-book-gallery-loading-cases"><p><?php echo esc_js( __( 'Loading cases...', 'brag-book-gallery' ) ); ?></p></div>';

		// Collect filter values
		const filters = collectFilters();

		// Prepare request data
		const formData = new FormData();
		formData.append( 'action', 'load_gallery_cases' );
		formData.append( 'procedure_id', procedureId );
		formData.append( 'page', page.toString() );
		formData.append( 'filters', JSON.stringify( filters ) );
		formData.append( 'nonce', '<?php echo wp_create_nonce( 'load_gallery_cases' ); ?>' );

		// Fetch cases
		fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
		.then( response => response.json() )
		.then( data => {
			if ( data.success ) {
				container.innerHTML = data.data.html;
				updatePagination( data.data.pagination );
			} else {
				container.innerHTML = '<p class="brag-book-gallery-error"><?php echo esc_js( __( 'Error loading cases. Please try again.', 'brag-book-gallery' ) ); ?></p>';
			}
		})
		.catch( error => {
			console.error( 'Error loading cases:', error );
			container.innerHTML = '<p class="brag-book-gallery-error"><?php echo esc_js( __( 'Failed to load cases.', 'brag-book-gallery' ) ); ?></p>';
		});
	}

	/**
	 * Collect all active filters
	 */
	function collectFilters() {
		const filters = {};
		const checkboxes = document.querySelectorAll( '.brag-book-gallery-filter-content input[type="checkbox"]:checked' );

		checkboxes.forEach( checkbox => {
			const filterType = checkbox.dataset.filterType;
			const filterValue = checkbox.value;

			if ( ! filters[filterType] ) {
				filters[filterType] = [];
			}
			filters[filterType].push( filterValue );
		});

		return filters;
	}

	/**
	 * Update pagination controls
	 */
	function updatePagination( paginationHtml ) {
		const paginationContainer = document.getElementById( 'cases-pagination' );
		if ( paginationContainer ) {
			paginationContainer.innerHTML = paginationHtml || '';

			// Attach click handlers to pagination links
			const paginationLinks = paginationContainer.querySelectorAll( 'a[data-page]' );
			paginationLinks.forEach( link => {
				link.addEventListener( 'click', function( e ) {
					e.preventDefault();
					const page = parseInt( this.dataset.page, 10 );
					if ( ! isNaN( page ) ) {
						loadCases( page );
					}
				});
			});
		}
	}

	// Initial load
	loadCases();

	// Apply filters on change
	document.addEventListener( 'change', function( e ) {
		if ( e.target.matches( '.brag-book-gallery-filter-content input[type="checkbox"]' ) ) {
			loadCases();
		}
	});
});
</script>

<?php
get_footer();
