/**
 * Nudity Warning Manager
 * Handles nudity warnings and acceptance state
 */
class NudityWarningManager {
	constructor() {
		this.nudityAccepted = false;
		this.storageKey = 'brag-book-nudity-accepted';

		// Check acceptance status BEFORE DOM loads to prevent flash
		this.checkInitialAcceptance();
		this.init();
	}

	checkInitialAcceptance() {
		// Check localStorage immediately
		try {
			const stored = localStorage.getItem(this.storageKey);
			this.nudityAccepted = stored === 'true';

			// Add class to body immediately if accepted
			if (this.nudityAccepted) {
				document.body.classList.add('nudity-accepted');
			}
		} catch (e) {
			console.warn('Could not load nudity acceptance status from localStorage:', e);
			this.nudityAccepted = false;
		}
	}

	init() {
		this.setupEventListeners();

		// Add console message for how to reset
		console.log('%cTo reset nudity warnings, type: nudityManager.resetAcceptance()',
			'background: #333; color: #fff; padding: 5px; border-radius: 3px;');
	}

	saveAcceptanceStatus() {
		try {
			localStorage.setItem(this.storageKey, 'true');
		} catch (e) {
			console.warn('Could not save nudity acceptance status to localStorage:', e);
		}
	}

	setupEventListeners() {
		// Add click event listeners to all Proceed buttons in nudity warnings
		document.addEventListener('click', (e) => {
			if (e.target.matches('.brag-book-gallery-nudity-warning-button')) {
				this.handleProceedButtonClick(e.target);
			}
		});
	}

	handleProceedButtonClick(button) {
		// Mark nudity as accepted globally
		this.nudityAccepted = true;
		this.saveAcceptanceStatus();

		// Add class to body for CSS hiding
		document.body.classList.add('nudity-accepted');

		// Animate the removal for smooth transition
		this.animateRemoval();
	}

	animateRemoval() {
		const allNudityWarnings = document.querySelectorAll('.brag-book-gallery-nudity-warning');
		const allBlurredImages = document.querySelectorAll('.brag-book-gallery-nudity-blur');

		allNudityWarnings.forEach(nudityWarning => {
			if (typeof gsap !== 'undefined') {
				gsap.to(nudityWarning, {
					opacity: 0,
					duration: 0.5,
					ease: "power2.out",
					onComplete: () => {
						nudityWarning.style.display = 'none';
					}
				});
			} else {
				// Fallback without GSAP
				nudityWarning.style.transition = 'opacity 0.5s ease-out';
				nudityWarning.style.opacity = '0';
				setTimeout(() => {
					nudityWarning.style.display = 'none';
				}, 500);
			}
		});

		allBlurredImages.forEach(blurredImage => {
			if (typeof gsap !== 'undefined') {
				gsap.to(blurredImage, {
					filter: 'blur(0px)',
					duration: 0.5,
					ease: "power2.out"
				});
			} else {
				// Fallback without GSAP
				blurredImage.style.transition = 'filter 0.5s ease-out';
				blurredImage.style.filter = 'blur(0px)';
			}
		});
	}

	// Method to reset acceptance - call this from browser console
	resetAcceptance() {
		this.nudityAccepted = false;
		try {
			localStorage.removeItem(this.storageKey);
			document.body.classList.remove('nudity-accepted');
			console.log('✅ Nudity warning acceptance has been reset. Refresh the page to see warnings again.');
		} catch (e) {
			console.warn('Could not remove nudity acceptance status from localStorage:', e);
		}
	}
}

/**
 * Phone Number Formatter
 * Formats phone inputs to (000) 000-0000 format
 */
class PhoneFormatter {
	constructor() {
		this.init();
	}

	init() {
		// Find all phone inputs with data-phone-format attribute
		const phoneInputs = document.querySelectorAll('[data-phone-format="true"]');

		phoneInputs.forEach(input => {
			this.setupPhoneInput(input);
		});
	}

	setupPhoneInput(input) {
		// Format on input
		input.addEventListener('input', (e) => {
			this.formatPhoneNumber(e.target);
		});

		// Handle paste
		input.addEventListener('paste', (e) => {
			setTimeout(() => {
				this.formatPhoneNumber(e.target);
			}, 0);
		});

		// Prevent non-numeric input except for formatting characters
		input.addEventListener('keypress', (e) => {
			const char = String.fromCharCode(e.which);
			if (!/[0-9]/.test(char) && e.which !== 8 && e.which !== 46) {
				e.preventDefault();
			}
		});
	}

	formatPhoneNumber(input) {
		// Remove all non-digits
		let value = input.value.replace(/\D/g, '');

		// Limit to 10 digits
		value = value.substring(0, 10);

		// Format the number
		let formattedValue = '';

		if (value.length > 0) {
			if (value.length <= 3) {
				formattedValue = `(${value}`;
			} else if (value.length <= 6) {
				formattedValue = `(${value.substring(0, 3)}) ${value.substring(3)}`;
			} else {
				formattedValue = `(${value.substring(0, 3)}) ${value.substring(3, 6)}-${value.substring(6, 10)}`;
			}
		}

		// Update input value
		input.value = formattedValue;

		// Update validity
		if (value.length === 10) {
			input.setCustomValidity('');
		} else if (input.hasAttribute('required') && value.length > 0) {
			input.setCustomValidity('Please enter a complete 10-digit phone number');
		}
	}
}

export { NudityWarningManager, PhoneFormatter };
