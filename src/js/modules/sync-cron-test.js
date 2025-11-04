/**
 * Sync Cron Test Module
 *
 * Handles manual triggering of the automatic sync cron job for testing purposes.
 * Provides AJAX interface for testing cron functionality without waiting for
 * the scheduled time.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Sync
 * @since      3.3.0
 */

/**
 * Sync Cron Test Class
 *
 * Manages the cron test button and displays results including:
 * - Manual cron job triggering
 * - Success/error notifications
 * - Auto-dismissing notices
 *
 * @since 3.3.0
 */
export class SyncCronTest {
	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 *
	 * @param {Object} config - Configuration object
	 * @param {string} config.ajaxUrl - WordPress AJAX URL
	 * @param {string} config.nonce - Security nonce for AJAX requests
	 * @param {Object} config.messages - Localized messages
	 */
	constructor( config = {} ) {
		this.ajaxUrl = config.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
		this.nonce = config.nonce || '';
		this.messages = config.messages || {
			running: 'Running...',
			triggering: 'Triggering cron job...',
			testFailed: 'Test failed',
			ajaxError: 'AJAX request failed',
			testCronNow: 'Test Cron Now',
		};

		this.elements = {
			button: null,
			resultContainer: null,
		};

		this.autoDismissTimeout = 5000; // 5 seconds
	}

	/**
	 * Initialize cron test
	 *
	 * Finds DOM elements and binds event listeners.
	 *
	 * @since 3.3.0
	 *
	 * @return {void}
	 */
	init() {
		this.elements.button = document.getElementById( 'test-cron-sync' );
		this.elements.resultContainer = document.getElementById( 'test-cron-result' );

		if ( ! this.elements.button ) {
			return;
		}

		this.bindEvents();
	}

	/**
	 * Bind event listeners
	 *
	 * Attaches click handler to the test cron button.
	 *
	 * @since 3.3.0
	 *
	 * @return {void}
	 */
	bindEvents() {
		this.elements.button.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			this.handleTestCron();
		} );
	}

	/**
	 * Handle test cron button click
	 *
	 * Initiates the AJAX request to trigger the cron job.
	 *
	 * @since 3.3.0
	 *
	 * @return {Promise<void>}
	 */
	async handleTestCron() {
		const button = this.elements.button;

		// Disable button and show loading state
		button.disabled = true;
		button.textContent = this.messages.running;

		// Show initial notice
		this.showNotice( this.messages.triggering, 'info' );

		try {
			// Prepare form data
			const formData = new FormData();
			formData.append( 'action', 'brag_book_gallery_test_cron' );
			formData.append( 'nonce', this.nonce );

			// Send AJAX request
			const response = await fetch( this.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );

			const result = await response.json();

			// Re-enable button
			button.disabled = false;
			button.textContent = this.messages.testCronNow;

			// Handle response
			if ( result.success ) {
				this.showNotice( result.data.message, 'success' );
			} else {
				this.showNotice(
					result.data || this.messages.testFailed,
					'error'
				);
			}
		} catch ( error ) {
			// Handle error
			console.error( 'BRAG book Sync: Cron test error:', error );

			button.disabled = false;
			button.textContent = this.messages.testCronNow;

			this.showNotice( this.messages.ajaxError, 'error' );
		}
	}

	/**
	 * Show notice message
	 *
	 * Displays a WordPress-style notice with auto-dismiss functionality.
	 *
	 * @since 3.3.0
	 *
	 * @param {string} message - Message to display
	 * @param {string} type - Notice type (success, error, warning, info)
	 *
	 * @return {void}
	 */
	showNotice( message, type = 'info' ) {
		if ( ! this.elements.resultContainer ) {
			return;
		}

		const notice = document.createElement( 'div' );
		notice.className = `notice notice-${type} is-dismissible`;
		notice.innerHTML = `<p>${this.escapeHtml( message )}</p>`;

		// Clear previous notices
		this.elements.resultContainer.innerHTML = '';

		// Add new notice
		this.elements.resultContainer.appendChild( notice );

		// Auto-dismiss after timeout
		this.autoDismissNotice( notice );
	}

	/**
	 * Auto-dismiss notice
	 *
	 * Fades out and removes a notice after the configured timeout.
	 *
	 * @since 3.3.0
	 *
	 * @param {HTMLElement} notice - Notice element to dismiss
	 *
	 * @return {void}
	 */
	autoDismissNotice( notice ) {
		setTimeout( () => {
			// Fade out animation
			notice.style.transition = 'opacity 300ms';
			notice.style.opacity = '0';

			// Remove from DOM after fade
			setTimeout( () => {
				notice.remove();
			}, 300 );
		}, this.autoDismissTimeout );
	}

	/**
	 * Escape HTML for safe display
	 *
	 * Prevents XSS by escaping HTML special characters.
	 *
	 * @since 3.3.0
	 *
	 * @param {string} text - Text to escape
	 *
	 * @return {string} Escaped text
	 */
	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}
}

/**
 * Initialize cron test on DOM ready
 *
 * Looks for the cron test button and initializes the functionality
 * if configuration is available.
 *
 * @since 3.3.0
 *
 * @return {void}
 */
export function initSyncCronTest() {
	// Check if we're on the sync page
	const button = document.getElementById( 'test-cron-sync' );
	if ( ! button ) {
		return;
	}

	// Get configuration from localized data
	const config = {};

	if ( typeof bragBookSync !== 'undefined' ) {
		config.ajaxUrl = bragBookSync.ajax_url;
		config.nonce = bragBookSync.sync_nonce;
		config.messages = bragBookSync.messages || {};
	}

	// Initialize cron test
	const cronTest = new SyncCronTest( config );
	cronTest.init();

	// Store instance for potential access
	window.bragBookSyncCronTest = cronTest;
}

// Auto-initialize if DOM is ready
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initSyncCronTest );
} else {
	initSyncCronTest();
}
