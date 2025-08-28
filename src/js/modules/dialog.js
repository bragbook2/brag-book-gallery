/**
 * Dialog Component
 * Reusable dialog/modal component using native HTML dialog element
 * Provides accessibility, keyboard navigation, and backdrop click handling
 */
class Dialog {
	/**
	 * Initialize a new dialog instance
	 * @param {string} dialogId - The ID of the dialog element
	 * @param {Object} options - Configuration options
	 * @param {boolean} options.closeOnBackdrop - Close dialog when clicking backdrop (default: true)
	 * @param {boolean} options.closeOnEscape - Close dialog when pressing ESC (default: true)
	 * @param {Function} options.onOpen - Callback when dialog opens
	 * @param {Function} options.onClose - Callback when dialog closes
	 */
	constructor(dialogId, options = {}) {
		// Get dialog element from DOM
		this.dialog = document.getElementById(dialogId);
		
		// Find all close buttons within the dialog
		this.closeButtons = this.dialog?.querySelectorAll('[data-action*="close"]');

		// Merge options with defaults
		this.options = {
			closeOnBackdrop: options.closeOnBackdrop !== false, // Default: true
			closeOnEscape: options.closeOnEscape !== false, // Default: true
			onOpen: options.onOpen || (() => {}), // Open callback
			onClose: options.onClose || (() => {}), // Close callback
			...options
		};

		// Initialize only if dialog element exists
		if (this.dialog) {
			this.init();
		}
	}

	/**
	 * Initialize the dialog - sets up all event listeners
	 */
	init() {
		this.setupEventListeners();
	}

	/**
	 * Set up all event listeners for dialog interaction
	 */
	setupEventListeners() {
		// Handle close button clicks
		this.closeButtons?.forEach(button => {
			button.addEventListener('click', (e) => {
				// Prevent default behavior and event bubbling
				e.preventDefault();
				e.stopPropagation();
				this.close();
			});
		});

		// Handle backdrop clicks (light dismiss)
		if (this.options.closeOnBackdrop && this.dialog) {
			// Native dialog elements pass backdrop clicks to the dialog element
			// Only close if clicking the dialog itself, not its children
			this.dialog.addEventListener('click', (e) => {
				if (e.target === this.dialog) {
					this.close();
				}
			});
		}

		// Handle ESC key press using native dialog 'cancel' event
		if (this.options.closeOnEscape && this.dialog) {
			this.dialog.addEventListener('cancel', (e) => {
				// Prevent default ESC behavior and handle it ourselves
				e.preventDefault();
				this.close();
			});
		}
	}

	/**
	 * Open the dialog modal
	 */
	open() {
		// Exit early if dialog doesn't exist
		if (!this.dialog) return;

		try {
			// Use native showModal() for proper modal behavior
			// This handles backdrop, focus trapping, and accessibility
			this.dialog.showModal();
			
			// Prevent background scrolling during modal display
			document.body.style.overflow = 'hidden';
			
			// Execute open callback
			this.options.onOpen();
		} catch (error) {
			// Fallback for older browsers without native dialog support
			console.error('Error opening dialog:', error);
			this.dialog.setAttribute('open', '');
			this.dialog.style.display = 'block';
			document.body.style.overflow = 'hidden';
			this.options.onOpen();
		}
	}

	/**
	 * Close the dialog modal
	 */
	close() {
		// Exit early if dialog doesn't exist
		if (!this.dialog) return;

		try {
			// Use native close() method for proper cleanup
			// This handles focus restoration and accessibility
			this.dialog.close();
			
			// Restore background scrolling
			document.body.style.overflow = '';
			
			// Clear any forced display styles
			this.dialog.style.display = '';
			
			// Execute close callback
			this.options.onClose();
		} catch (error) {
			// Fallback for older browsers without native dialog support
			console.error('Error closing dialog:', error);
			this.dialog.removeAttribute('open');
			this.dialog.style.display = 'none';
			document.body.style.overflow = '';
			this.options.onClose();
		}
	}

	/**
	 * Check if the dialog is currently open
	 * @returns {boolean} True if dialog is open
	 */
	isOpen() {
		// Check both native 'open' property and fallback attribute
		return this.dialog?.open || this.dialog?.hasAttribute('open');
	}
}

export default Dialog;
