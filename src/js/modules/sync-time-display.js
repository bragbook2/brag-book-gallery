/**
 * Sync Time Display Module
 *
 * Handles real-time display of server time, browser time, and timezone information
 * for the sync settings page. Updates every second to show synchronized clocks.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Sync
 * @since      3.3.0
 */

/**
 * Sync Time Display Class
 *
 * Manages the display and updating of time information including:
 * - Server time (calculated from initial PHP timestamp)
 * - Browser/local time
 * - Server timezone display
 *
 * @since 3.3.0
 */
export class SyncTimeDisplay {
	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 *
	 * @param {string} initialServerTime - ISO 8601 formatted server time from PHP
	 */
	constructor( initialServerTime ) {
		this.pageLoadTime = new Date();
		this.initialServerTime = new Date( initialServerTime );
		this.updateInterval = null;
		this.elements = {
			serverTime: null,
			browserTime: null,
		};
	}

	/**
	 * Initialize time display
	 *
	 * Finds DOM elements and starts the update timer.
	 *
	 * @since 3.3.0
	 *
	 * @return {void}
	 */
	init() {
		// Get DOM elements
		this.elements.serverTime = document.getElementById( 'server-time' );
		this.elements.browserTime = document.getElementById( 'browser-time' );

		// Update immediately
		this.updateTimes();

		// Update every second
		this.updateInterval = setInterval( () => this.updateTimes(), 1000 );
	}

	/**
	 * Update time displays
	 *
	 * Calculates current server time and browser time,
	 * then updates the display elements.
	 *
	 * @since 3.3.0
	 *
	 * @return {void}
	 */
	updateTimes() {
		this.updateServerTime();
		this.updateBrowserTime();
	}

	/**
	 * Update server time display
	 *
	 * Calculates the current server time based on elapsed time since
	 * page load and the initial server timestamp from PHP.
	 *
	 * @since 3.3.0
	 *
	 * @return {void}
	 */
	updateServerTime() {
		if ( ! this.elements.serverTime ) {
			return;
		}

		const now = new Date();
		const elapsed = Math.floor( ( now - this.pageLoadTime ) / 1000 );
		const currentServerTime = new Date(
			this.initialServerTime.getTime() + elapsed * 1000
		);

		this.elements.serverTime.textContent = this.formatDateTime( currentServerTime );
	}

	/**
	 * Update browser time display
	 *
	 * Displays the current local browser time.
	 *
	 * @since 3.3.0
	 *
	 * @return {void}
	 */
	updateBrowserTime() {
		if ( ! this.elements.browserTime ) {
			return;
		}

		const now = new Date();
		this.elements.browserTime.textContent = this.formatDateTime( now );
	}

	/**
	 * Format date/time for display
	 *
	 * Formats a Date object as YYYY-MM-DD HH:MM:SS
	 *
	 * @since 3.3.0
	 *
	 * @param {Date} date - Date object to format
	 *
	 * @return {string} Formatted date/time string
	 */
	formatDateTime( date ) {
		const year = date.getFullYear();
		const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
		const day = String( date.getDate() ).padStart( 2, '0' );
		const hours = String( date.getHours() ).padStart( 2, '0' );
		const minutes = String( date.getMinutes() ).padStart( 2, '0' );
		const seconds = String( date.getSeconds() ).padStart( 2, '0' );

		return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
	}

	/**
	 * Stop time updates
	 *
	 * Clears the update interval. Call this when unmounting or leaving the page.
	 *
	 * @since 3.3.0
	 *
	 * @return {void}
	 */
	destroy() {
		if ( this.updateInterval ) {
			clearInterval( this.updateInterval );
			this.updateInterval = null;
		}
	}
}

/**
 * Initialize time display on DOM ready
 *
 * Looks for the server time element and initializes the time display
 * if the initial server time is available.
 *
 * @since 3.3.0
 *
 * @return {void}
 */
export function initSyncTimeDisplay() {
	// Check if we're on the sync page
	const serverTimeEl = document.getElementById( 'server-time' );
	if ( ! serverTimeEl ) {
		return;
	}

	// Get initial server time from data attribute or global variable
	const initialServerTime =
		serverTimeEl.dataset.initialTime ||
		( typeof bragBookSyncData !== 'undefined' && bragBookSyncData.serverTime );

	if ( ! initialServerTime ) {
		console.warn( 'BRAG book Sync: Initial server time not available' );
		return;
	}

	// Initialize time display
	const timeDisplay = new SyncTimeDisplay( initialServerTime );
	timeDisplay.init();

	// Store instance for potential cleanup
	window.bragBookSyncTimeDisplay = timeDisplay;
}

// Auto-initialize if DOM is ready
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initSyncTimeDisplay );
} else {
	initSyncTimeDisplay();
}
