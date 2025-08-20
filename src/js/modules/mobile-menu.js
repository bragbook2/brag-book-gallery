/**
 * Mobile Menu Component
 * Handles mobile navigation sidebar
 */
class MobileMenu {
	constructor(options = {}) {
		this.sidebar = document.querySelector('.brag-book-gallery-sidebar');
		this.overlay = document.querySelector('.brag-book-gallery-mobile-overlay');
		this.menuToggle = document.querySelector('.brag-book-gallery-mobile-menu-toggle');
		this.closeButton = document.querySelector('[data-action="close-menu"]');

		this.options = {
			breakpoint: options.breakpoint || 1024,
			swipeToClose: options.swipeToClose !== false,
			...options
		};

		this.touchStartX = 0;
		this.touchEndX = 0;

		if (this.sidebar && this.menuToggle) {
			this.init();
		}
	}

	init() {
		this.setupEventListeners();
		this.checkMobileView();

		// Handle resize
		let resizeTimer;
		window.addEventListener('resize', () => {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(() => {
				this.checkMobileView();
			}, 250);
		});
	}

	setupEventListeners() {
		this.menuToggle?.addEventListener('click', () => this.toggle());
		this.overlay?.addEventListener('click', () => this.close());
		this.closeButton?.addEventListener('click', () => this.close());

		// Swipe gestures
		if (this.options.swipeToClose) {
			this.setupSwipeGestures();
		}

		// Escape key
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && this.isOpen()) {
				this.close();
			}
		});
	}

	setupSwipeGestures() {
		this.sidebar?.addEventListener('touchstart', (e) => {
			this.touchStartX = e.changedTouches[0].screenX;
		}, { passive: true });

		this.sidebar?.addEventListener('touchend', (e) => {
			this.touchEndX = e.changedTouches[0].screenX;
			this.handleSwipeGesture();
		}, { passive: true });
	}

	handleSwipeGesture() {
		const swipeThreshold = 50;
		const diff = this.touchStartX - this.touchEndX;

		if (diff > swipeThreshold && this.isOpen()) {
			this.close();
		}
	}

	checkMobileView() {
		const isMobile = window.innerWidth <= this.options.breakpoint;
		const sidebarHeader = document.querySelector('.brag-book-gallery-sidebar-header');

		if (sidebarHeader) {
			sidebarHeader.style.display = isMobile ? 'flex' : 'none';
		}

		if (!isMobile) {
			this.close();
		}
	}

	toggle() {
		if (this.isOpen()) {
			this.close();
		} else {
			this.open();
		}
	}

	open() {
		if (!this.sidebar || !this.menuToggle) return;

		this.menuToggle.dataset.menuOpen = 'true';
		this.menuToggle.setAttribute('aria-expanded', 'true');
		this.menuToggle.setAttribute('aria-label', 'Close navigation menu');
		this.sidebar.classList.add('brag-book-gallery-active');
		this.overlay?.classList.add('brag-book-gallery-active');

		// Animate menu icon
		const menuIcon = this.menuToggle.querySelector('svg');
		if (menuIcon && typeof gsap !== 'undefined') {
			gsap.to(menuIcon, {
				rotation: 180,
				duration: 0.3,
				ease: "power2.inOut"
			});

			setTimeout(() => {
				menuIcon.innerHTML = '<path d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/>';
			}, 150);
		} else if (menuIcon) {
			menuIcon.innerHTML = '<path d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/>';
		}

		document.body.style.overflow = 'hidden';
	}

	close() {
		if (!this.sidebar || !this.menuToggle) return;

		this.menuToggle.dataset.menuOpen = 'false';
		this.menuToggle.setAttribute('aria-expanded', 'false');
		this.menuToggle.setAttribute('aria-label', 'Open navigation menu');
		this.sidebar.classList.remove('brag-book-gallery-active');
		this.overlay?.classList.remove('brag-book-gallery-active');

		// Animate menu icon
		const menuIcon = this.menuToggle.querySelector('svg');
		if (menuIcon && typeof gsap !== 'undefined') {
			gsap.to(menuIcon, {
				rotation: 0,
				duration: 0.3,
				ease: "power2.inOut"
			});

			setTimeout(() => {
				menuIcon.innerHTML = '<path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/>';
			}, 150);
		} else if (menuIcon) {
			menuIcon.innerHTML = '<path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/>';
		}

		document.body.style.overflow = '';
	}

	isOpen() {
		return this.menuToggle?.dataset.menuOpen === 'true';
	}
}

export default MobileMenu;
