/**
 * BRAG book Gallery - Carousel-only Entry Point
 *
 * Used by the [brag_book_carousel] shortcode when it's the only BRAGbook
 * shortcode on the page (e.g., a homepage hero carousel). Bundles just the
 * Carousel class plus the small NudityWarningManager and PhoneFormatter
 * utilities — main-app.js, the four lazy modules, and global-utilities are
 * all skipped, dropping the JS payload from ~136 KB to ~30 KB.
 *
 * If the page also has [brag_book_gallery] / [brag_book_gallery_cases] /
 * [brag_book_gallery_favorites] / [brag_book_gallery_sidebar], the PHP
 * carousel handler enqueues the full brag-book-gallery.min.js bundle
 * instead, and this file is never loaded.
 */
import Carousel from './modules/carousel.js';
import { NudityWarningManager, PhoneFormatter } from './modules/utilities.js';

document.addEventListener('DOMContentLoaded', function () {
	const carouselElements = document.querySelectorAll('.brag-book-gallery-carousel-wrapper');
	if (carouselElements.length > 0) {
		new Carousel({});
	}

	new NudityWarningManager();
	new PhoneFormatter();
});
