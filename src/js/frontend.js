/**
 * BRAG Book Gallery - Main Entry Point
 *
 * This file serves as the main entry point for the BRAG Book Gallery application.
 * It imports and initializes all components in the correct order.
 */

// Import all components and utilities
import BRAGbookGalleryApp from './modules/main-app.js';
import Carousel from './modules/carousel.js';
import Dialog from './modules/dialog.js';
import FilterSystem from './modules/filter-system.js';
import MobileMenu from './modules/mobile-menu.js';
import FavoritesManager from './modules/favorites-manager.js';
import ShareManager from './modules/share-manager.js';
import SearchAutocomplete from './modules/search-autocomplete.js';
import { NudityWarningManager, PhoneFormatter } from './modules/utilities.js';

// Import global utilities (this will execute the initialization code)
import './modules/global-utilities.js';

// Make components available on window for global access

// Export all components for external use if needed
export {
	BRAGbookGalleryApp,
	Carousel,
	Dialog,
	FilterSystem,
	MobileMenu,
	FavoritesManager,
	ShareManager,
	SearchAutocomplete,
	NudityWarningManager,
	PhoneFormatter
};

// Initialize the main application (this happens automatically when global-utilities.js is imported)
