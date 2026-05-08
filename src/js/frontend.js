/**
 * BRAG book Gallery - Main Entry Point
 *
 * Importing global-utilities triggers DOMContentLoaded bootstrapping, which
 * in turn instantiates BRAGbookGalleryApp. The heavy modules (FilterSystem,
 * FavoritesManager, SearchAutocomplete, ShareManager) are loaded on demand
 * via dynamic import() inside main-app.js, so they don't sit in the main
 * bundle.
 */
import './modules/global-utilities.js';
