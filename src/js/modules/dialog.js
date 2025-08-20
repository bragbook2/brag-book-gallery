/**
 * Dialog Component
 * Reusable dialog/modal component with backdrop support
 */
class Dialog {
	constructor(dialogId, options = {}) {
		this.dialog = document.getElementById(dialogId);
		this.backdrop = document.getElementById(options.backdropId || 'dialogBackdrop');
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

		// Backdrop click
		if (this.options.closeOnBackdrop) {
			this.dialog?.addEventListener('click', (e) => {
				if (e.target === this.dialog) {
					this.close();
				}
			});

			this.backdrop?.addEventListener('click', () => this.close());
		}

		// Escape key
		if (this.options.closeOnEscape) {
			document.addEventListener('keydown', (e) => {
				if (e.key === 'Escape' && this.isOpen()) {
					this.close();
				}
			});
		}
	}

	open() {
		if (!this.dialog) return;

		console.log('Opening dialog...');

		// Show backdrop
		if (this.backdrop) {
			this.backdrop.classList.add('active');
		}

		// Open dialog
		try {
			if (typeof this.dialog.showModal === 'function') {
				this.dialog.showModal();
			} else {
				this.dialog.setAttribute('open', '');
				this.dialog.style.display = 'block';
			}
		} catch (e) {
			this.dialog.setAttribute('open', '');
			this.dialog.style.display = 'block';
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

			// Hide backdrop
			if (this.backdrop) {
				this.backdrop.classList.remove('active');
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
