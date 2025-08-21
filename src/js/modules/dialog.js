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
			animateWithGsap: typeof gsap !== 'undefined',
			onOpen: options.onOpen || (() => {}),
			onClose: options.onClose || (() => {}),
			...options
		};

		if (this.dialog) {
			this.init();
		}
	}

	init() {
		this.checkDialogSupport();
		this.setupEventListeners();
	}

	checkDialogSupport() {
		const testDialog = document.createElement('dialog');
		if (!testDialog.showModal) {
			console.log('Dialog element not fully supported, using polyfill');

			if (!HTMLDialogElement.prototype.showModal) {
				HTMLDialogElement.prototype.showModal = function() {
					this.setAttribute('open', '');
					this.style.display = 'block';
				};
			}
			if (!HTMLDialogElement.prototype.close) {
				HTMLDialogElement.prototype.close = function() {
					this.removeAttribute('open');
					this.style.display = 'none';
				};
			}
		}
	}

	setupEventListeners() {
		// Close buttons
		this.closeButtons?.forEach(button => {
			button.addEventListener('click', () => this.close());
		});

		// Backdrop click - clicking outside the dialog content
		if (this.options.closeOnBackdrop) {
			this.dialog?.addEventListener('click', (e) => {
				// Get the dialog dimensions
				const rect = this.dialog.getBoundingClientRect();
				// Check if click was outside the dialog content (on the backdrop)
				if (
					e.clientX < rect.left ||
					e.clientX > rect.right ||
					e.clientY < rect.top ||
					e.clientY > rect.bottom
				) {
					this.close();
				}
			});
		}

		// Escape key is handled natively by dialog element
		// but we can add custom handling if needed
		if (this.options.closeOnEscape) {
			this.dialog?.addEventListener('cancel', (e) => {
				e.preventDefault();
				this.close();
			});
		}
	}

	open() {
		if (!this.dialog) return;

		console.log('Opening dialog...');

		// Ensure dialog is visible before showing modal
		this.dialog.style.display = 'block';
		
		// Small delay to ensure styles are applied
		requestAnimationFrame(() => {
			// Open dialog using native showModal which automatically handles backdrop
			try {
				if (typeof this.dialog.showModal === 'function') {
					this.dialog.showModal();
				} else {
					this.dialog.setAttribute('open', '');
				}
			} catch (e) {
				this.dialog.setAttribute('open', '');
			}

			// Animate if GSAP is available
			if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
				gsap.from(this.dialog, {
					scale: 0.9,
					opacity: 0,
					duration: 0.3,
					ease: "back.out(1.7)"
				});
			}

			// Prevent body scroll
			document.body.style.overflow = 'hidden';

			// Callback
			this.options.onOpen();
		});
	}

	close() {
		if (!this.dialog) return;

		console.log('Closing dialog...');

		const cleanup = () => {
			try {
				if (typeof this.dialog.close === 'function') {
					this.dialog.close();
				} else {
					this.dialog.removeAttribute('open');
					this.dialog.style.display = 'none';
				}
			} catch (e) {
				this.dialog.removeAttribute('open');
				this.dialog.style.display = 'none';
			}

			// Restore body scroll
			document.body.style.overflow = '';

			// Reset animation state if using GSAP
			if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
				gsap.set(this.dialog, { scale: 1, opacity: 1 });
			}

			// Callback
			this.options.onClose();
		};

		// Animate if GSAP is available
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			gsap.to(this.dialog, {
				scale: 0.9,
				opacity: 0,
				duration: 0.2,
				ease: "power2.in",
				onComplete: cleanup
			});
		} else {
			cleanup();
		}
	}

	isOpen() {
		return this.dialog?.open || this.dialog?.hasAttribute('open');
	}
}

export default Dialog;
