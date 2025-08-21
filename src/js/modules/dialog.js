/**
 * Dialog Component
 * Reusable dialog/modal component using native dialog element
 */
class Dialog {
	constructor(dialogId, options = {}) {
		this.dialog = document.getElementById(dialogId);
		// Look for any close button with data-action containing "close"
		this.closeButtons = this.dialog?.querySelectorAll('[data-action*="close"]');

		this.options = {
			closeOnBackdrop: options.closeOnBackdrop !== false,
			closeOnEscape: options.closeOnEscape !== false,
			onOpen: options.onOpen || (() => {}),
			onClose: options.onClose || (() => {}),
			...options
		};

		if (this.dialog) {
			this.init();
		}
	}

	init() {
		this.setupEventListeners();
	}

	setupEventListeners() {
		// Close buttons - use event delegation to avoid issues
		this.closeButtons?.forEach(button => {
			button.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();
				this.close();
			});
		});

		// Native backdrop click using light dismiss
		if (this.options.closeOnBackdrop && this.dialog) {
			// For native dialog, clicking the ::backdrop triggers a click event on the dialog
			// We check if the click target is the dialog itself (not its children)
			this.dialog.addEventListener('click', (e) => {
				if (e.target === this.dialog) {
					this.close();
				}
			});
		}

		// Handle ESC key using the native 'cancel' event
		if (this.options.closeOnEscape && this.dialog) {
			this.dialog.addEventListener('cancel', (e) => {
				e.preventDefault();
				this.close();
			});
		}
	}

	open() {
		if (!this.dialog) return;

		try {
			// Use native showModal - it handles everything including backdrop
			this.dialog.showModal();
			
			// Prevent body scroll (native dialog should handle this but just in case)
			document.body.style.overflow = 'hidden';
			
			// Callback
			this.options.onOpen();
		} catch (error) {
			console.error('Error opening dialog:', error);
			// Fallback for browsers without dialog support
			this.dialog.setAttribute('open', '');
			this.dialog.style.display = 'block';
			document.body.style.overflow = 'hidden';
			this.options.onOpen();
		}
	}

	close() {
		if (!this.dialog) return;

		try {
			// Use native close method - it properly handles all states
			this.dialog.close();
			
			// Restore body scroll
			document.body.style.overflow = '';
			
			// Ensure display is not stuck as block
			this.dialog.style.display = '';
			
			// Callback
			this.options.onClose();
		} catch (error) {
			console.error('Error closing dialog:', error);
			// Fallback for browsers without dialog support
			this.dialog.removeAttribute('open');
			this.dialog.style.display = 'none';
			document.body.style.overflow = '';
			this.options.onClose();
		}
	}

	isOpen() {
		return this.dialog?.open || this.dialog?.hasAttribute('open');
	}
}

export default Dialog;
