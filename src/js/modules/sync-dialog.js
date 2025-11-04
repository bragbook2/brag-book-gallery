/**
 * Sync Dialog Module
 *
 * Creates and manages dialog notifications for sync operations.
 * Replaces WordPress-style notices with native HTML <dialog> elements
 * for better UX and consistency.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Sync
 * @since      3.3.0
 */

import Dialog from './dialog.js';

/**
 * Sync Dialog Manager Class
 *
 * Handles creation and display of dialog notifications for sync operations
 * including success, error, warning, and info messages.
 *
 * @since 3.3.0
 */
export class SyncDialog {
	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	constructor() {
		this.activeDialogs = new Map();
		this.dialogCounter = 0;
	}

	/**
	 * Show a dialog notification
	 *
	 * @since 3.3.0
	 *
	 * @param {string} type - Dialog type (success, error, warning, info)
	 * @param {string} title - Dialog title
	 * @param {string} message - Dialog message
	 * @param {Object} options - Additional options
	 * @param {boolean} options.autoClose - Auto-close after delay (default: true for success)
	 * @param {number} options.delay - Auto-close delay in ms (default: 5000)
	 * @param {Function} options.onClose - Callback when dialog closes
	 *
	 * @return {string} Dialog ID
	 */
	show( type, title, message, options = {} ) {
		// Generate unique dialog ID
		const dialogId = `sync-dialog-${++this.dialogCounter}`;

		// Default options
		const defaultOptions = {
			autoClose: type === 'success',
			delay: 5000,
			onClose: null,
		};

		const settings = { ...defaultOptions, ...options };

		// Create dialog element
		const dialogElement = this.createDialogElement( dialogId, type, title, message );

		// Append to body
		document.body.appendChild( dialogElement );

		// Initialize Dialog instance
		const dialog = new Dialog( dialogId, {
			closeOnBackdrop: true,
			closeOnEscape: true,
			onClose: () => {
				// Remove dialog from DOM after closing
				setTimeout( () => {
					dialogElement.remove();
					this.activeDialogs.delete( dialogId );
				}, 300 );

				// Call custom onClose callback
				if ( settings.onClose ) {
					settings.onClose();
				}
			},
		} );

		// Store dialog instance
		this.activeDialogs.set( dialogId, dialog );

		// Open dialog
		dialog.open();

		// Auto-close if configured
		if ( settings.autoClose ) {
			setTimeout( () => {
				if ( dialog.isOpen() ) {
					dialog.close();
				}
			}, settings.delay );
		}

		return dialogId;
	}

	/**
	 * Show success dialog
	 *
	 * @since 3.3.0
	 *
	 * @param {string} title - Dialog title
	 * @param {string} message - Dialog message
	 * @param {Object} options - Additional options
	 *
	 * @return {string} Dialog ID
	 */
	showSuccess( title, message, options = {} ) {
		return this.show( 'success', title, message, options );
	}

	/**
	 * Show error dialog
	 *
	 * @since 3.3.0
	 *
	 * @param {string} title - Dialog title
	 * @param {string} message - Dialog message
	 * @param {Object} options - Additional options
	 *
	 * @return {string} Dialog ID
	 */
	showError( title, message, options = {} ) {
		return this.show( 'error', title, message, { autoClose: false, ...options } );
	}

	/**
	 * Show warning dialog
	 *
	 * @since 3.3.0
	 *
	 * @param {string} title - Dialog title
	 * @param {string} message - Dialog message
	 * @param {Object} options - Additional options
	 *
	 * @return {string} Dialog ID
	 */
	showWarning( title, message, options = {} ) {
		return this.show( 'warning', title, message, { autoClose: false, ...options } );
	}

	/**
	 * Show info dialog
	 *
	 * @since 3.3.0
	 *
	 * @param {string} title - Dialog title
	 * @param {string} message - Dialog message
	 * @param {Object} options - Additional options
	 *
	 * @return {string} Dialog ID
	 */
	showInfo( title, message, options = {} ) {
		return this.show( 'info', title, message, options );
	}

	/**
	 * Show confirmation dialog
	 *
	 * @since 3.3.0
	 *
	 * @param {string} title - Dialog title
	 * @param {string} message - Dialog message
	 * @param {Object} options - Additional options
	 * @param {Function} options.onConfirm - Callback when confirmed
	 * @param {Function} options.onCancel - Callback when cancelled
	 * @param {string} options.confirmText - Confirm button text (default: 'Confirm')
	 * @param {string} options.cancelText - Cancel button text (default: 'Cancel')
	 *
	 * @return {string} Dialog ID
	 */
	showConfirm( title, message, options = {} ) {
		// Generate unique dialog ID
		const dialogId = `sync-confirm-dialog-${++this.dialogCounter}`;

		// Default options
		const defaultOptions = {
			onConfirm: null,
			onCancel: null,
			confirmText: 'Confirm',
			cancelText: 'Cancel',
			confirmButtonClass: 'button-danger',
		};

		const settings = { ...defaultOptions, ...options };

		// Create confirm dialog element
		const dialogElement = this.createConfirmDialogElement(
			dialogId,
			title,
			message,
			settings
		);

		// Append to body
		document.body.appendChild( dialogElement );

		// Initialize Dialog instance
		const dialog = new Dialog( dialogId, {
			closeOnBackdrop: true,
			closeOnEscape: true,
			onClose: () => {
				// Remove dialog from DOM after closing
				setTimeout( () => {
					dialogElement.remove();
					this.activeDialogs.delete( dialogId );
				}, 300 );
			},
		} );

		// Store dialog instance
		this.activeDialogs.set( dialogId, dialog );

		// Bind confirm/cancel buttons
		const confirmBtn = dialogElement.querySelector( '[data-action="confirm"]' );
		const cancelBtn = dialogElement.querySelector( '[data-action="cancel"]' );

		if ( confirmBtn ) {
			confirmBtn.addEventListener( 'click', () => {
				if ( settings.onConfirm ) {
					settings.onConfirm();
				}
				dialog.close();
			} );
		}

		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', () => {
				if ( settings.onCancel ) {
					settings.onCancel();
				}
				dialog.close();
			} );
		}

		// Open dialog
		dialog.open();

		return dialogId;
	}

	/**
	 * Close a specific dialog
	 *
	 * @since 3.3.0
	 *
	 * @param {string} dialogId - Dialog ID to close
	 *
	 * @return {void}
	 */
	close( dialogId ) {
		const dialog = this.activeDialogs.get( dialogId );
		if ( dialog ) {
			dialog.close();
		}
	}

	/**
	 * Close all active dialogs
	 *
	 * @since 3.3.0
	 *
	 * @return {void}
	 */
	closeAll() {
		this.activeDialogs.forEach( ( dialog ) => {
			dialog.close();
		} );
	}

	/**
	 * Create dialog HTML element
	 *
	 * @since 3.3.0
	 * @private
	 *
	 * @param {string} dialogId - Dialog ID
	 * @param {string} type - Dialog type (success, error, warning, info)
	 * @param {string} title - Dialog title
	 * @param {string} message - Dialog message
	 *
	 * @return {HTMLDialogElement} Dialog element
	 */
	createDialogElement( dialogId, type, title, message ) {
		const dialog = document.createElement( 'dialog' );
		dialog.id = dialogId;
		dialog.className = `brag-book-gallery-dialog brag-book-gallery-dialog-${type}`;

		// Get icon for dialog type
		const icon = this.getDialogIcon( type );

		dialog.innerHTML = `
			<div class="brag-book-gallery-dialog-content">
				<div class="brag-book-gallery-dialog-header">
					<h3 class="brag-book-gallery-dialog-title">${this.escapeHtml( title )}</h3>
					<button type="button" class="brag-book-gallery-dialog-close" data-action="close" aria-label="Close dialog">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="brag-book-gallery-dialog-body">
					<div class="brag-book-gallery-dialog-icon">
						<span class="dashicons dashicons-${icon}"></span>
					</div>
					<div class="brag-book-gallery-dialog-message">
						${this.escapeHtml( message )}
					</div>
				</div>
				<div class="brag-book-gallery-dialog-footer">
					<button type="button" class="button button-primary" data-action="close">OK</button>
				</div>
			</div>
		`;

		return dialog;
	}

	/**
	 * Create confirmation dialog HTML element
	 *
	 * @since 3.3.0
	 * @private
	 *
	 * @param {string} dialogId - Dialog ID
	 * @param {string} title - Dialog title
	 * @param {string} message - Dialog message
	 * @param {Object} options - Dialog options
	 *
	 * @return {HTMLDialogElement} Dialog element
	 */
	createConfirmDialogElement( dialogId, title, message, options ) {
		const dialog = document.createElement( 'dialog' );
		dialog.id = dialogId;
		dialog.className = 'brag-book-gallery-dialog brag-book-gallery-dialog-danger';

		dialog.innerHTML = `
			<div class="brag-book-gallery-dialog-content">
				<div class="brag-book-gallery-dialog-header">
					<h3 class="brag-book-gallery-dialog-title">${this.escapeHtml( title )}</h3>
					<button type="button" class="brag-book-gallery-dialog-close" data-action="close" aria-label="Close dialog">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="brag-book-gallery-dialog-body">
					<div class="brag-book-gallery-dialog-icon">
						<span class="dashicons dashicons-warning"></span>
					</div>
					<div class="brag-book-gallery-dialog-message">
						${this.escapeHtml( message )}
					</div>
				</div>
				<div class="brag-book-gallery-dialog-footer">
					<button type="button" class="button button-secondary" data-action="cancel">
						${this.escapeHtml( options.cancelText )}
					</button>
					<button type="button" class="button ${options.confirmButtonClass}" data-action="confirm">
						${this.escapeHtml( options.confirmText )}
					</button>
				</div>
			</div>
		`;

		return dialog;
	}

	/**
	 * Get dashicon name for dialog type
	 *
	 * @since 3.3.0
	 * @private
	 *
	 * @param {string} type - Dialog type
	 *
	 * @return {string} Dashicon name
	 */
	getDialogIcon( type ) {
		const icons = {
			success: 'yes-alt',
			error: 'dismiss',
			warning: 'warning',
			info: 'info',
		};

		return icons[ type ] || 'info';
	}

	/**
	 * Escape HTML to prevent XSS
	 *
	 * @since 3.3.0
	 * @private
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
 * Global sync dialog instance
 *
 * @since 3.3.0
 * @type {SyncDialog}
 */
export const syncDialog = new SyncDialog();

export default SyncDialog;
