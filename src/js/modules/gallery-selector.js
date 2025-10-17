/**
 * Gallery Selector Navigation
 *
 * Handles the two-level navigation for the gallery selector in tiles view:
 * - Parent categories that open subcategory panels
 * - Back button to return to parent categories
 *
 * @package BragBookGallery
 * @since 3.3.2
 */

/**
 * Initialize gallery selector navigation
 */
export function initGallerySelector() {
	// Handle parent category clicks
	document.addEventListener( 'click', ( e ) => {
		const categoryLink = e.target.closest( '[data-action="show-subcategories"]' );
		if ( categoryLink ) {
			e.preventDefault();
			showSubcategories( categoryLink );
		}

		// Handle back button clicks
		const backButton = e.target.closest( '[data-action="back-to-categories"]' );
		if ( backButton ) {
			e.preventDefault();
			showParentCategories( backButton );
		}
	} );
}

/**
 * Show subcategory panel for selected parent category
 *
 * @param {HTMLElement} categoryLink - The clicked category link button
 */
function showSubcategories( categoryLink ) {
	const categoryId = categoryLink.dataset.categoryId;
	if ( ! categoryId ) {
		return;
	}

	// Find the parent wrapper
	const wrapper = categoryLink.closest( '.brag-book-gallery-category-nav-wrapper' );
	if ( ! wrapper ) {
		return;
	}

	// Hide parent category list
	const parentList = wrapper.querySelector( '[data-level="parent"]' );
	if ( parentList ) {
		parentList.style.display = 'none';
	}

	// Show the corresponding subcategory panel
	const panel = wrapper.querySelector( `.brag-book-gallery-subcategory-panel[data-category-id="${categoryId}"]` );
	if ( panel ) {
		panel.style.display = 'block';
	}
}

/**
 * Show parent categories list (hide subcategory panel)
 *
 * @param {HTMLElement} backButton - The clicked back button
 */
function showParentCategories( backButton ) {
	// Find the parent wrapper
	const wrapper = backButton.closest( '.brag-book-gallery-category-nav-wrapper' );
	if ( ! wrapper ) {
		return;
	}

	// Hide all subcategory panels
	const panels = wrapper.querySelectorAll( '.brag-book-gallery-subcategory-panel' );
	panels.forEach( ( panel ) => {
		panel.style.display = 'none';
	} );

	// Show parent category list
	const parentList = wrapper.querySelector( '[data-level="parent"]' );
	if ( parentList ) {
		parentList.style.display = 'block';
	}
}
