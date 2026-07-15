/**
 * Case meta box tabs.
 *
 * Activates the tabbed interface in the "API Case Data" meta box on the case
 * editor. The markup (nav-tab links pointing at #api-* panels) is rendered by
 * Post_Types::render_case_api_data_meta_box(); this script wires the click
 * behaviour and the CSS hides inactive panels.
 *
 * Vanilla ES2020+, no dependencies.
 *
 * @package BRAGBookGallery
 * @since   3.3.3
 */

( () => {
	'use strict';

	/**
	 * Wire up a single tab group.
	 *
	 * @param {HTMLElement} group The .brag-book-api-data-tabs container.
	 */
	const initGroup = ( group ) => {
		const tabs = [ ...group.querySelectorAll( '.nav-tab-wrapper .nav-tab' ) ];
		if ( tabs.length === 0 ) {
			return;
		}

		const panels = [ ...group.querySelectorAll( '.tab-content' ) ];

		const activate = ( tab ) => {
			const targetId = ( tab.getAttribute( 'href' ) ?? '' ).replace( /^#/, '' );
			const panel = targetId ? group.querySelector( `#${ targetId }` ) : null;
			if ( ! panel ) {
				return;
			}

			tabs.forEach( ( item ) => item.classList.toggle( 'nav-tab-active', item === tab ) );
			panels.forEach( ( content ) => content.classList.toggle( 'active', content === panel ) );
		};

		tabs.forEach( ( tab ) => {
			tab.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				activate( tab );
			} );
		} );
	};

	const init = () => {
		document.querySelectorAll( '.brag-book-api-data-tabs' ).forEach( initGroup );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
