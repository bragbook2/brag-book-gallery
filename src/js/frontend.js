/**
 * Carousel Component
 * Reusable carousel with pagination, navigation, and touch/mouse support
 * Structure: wrapper > content > (track + buttons)
 */
class Carousel {
	constructor(element, options = {}) {
		this.wrapper = element;
		this.content = element.querySelector('.brag-book-gallery-carousel-content');
		this.track = element.querySelector('.brag-book-gallery-carousel-track');
		this.prevBtn = element.querySelector('[data-direction="prev"]');
		this.nextBtn = element.querySelector('[data-direction="next"]');
		this.paginationContainer = element.querySelector('.brag-book-gallery-carousel-pagination');

		this.options = {
			itemsPerView: options.itemsPerView || this.getItemsPerView(),
			gap: options.gap || 16,
			animationDuration: options.animationDuration || 0.4,
			slideSelector: options.slideSelector || '.brag-book-gallery-carousel-item',
			infinite: options.infinite !== false, // Default to true
			autoplay: options.autoplay || false,
			autoplayDelay: options.autoplayDelay || 3000,
			pauseOnHover: options.pauseOnHover !== false, // Default to true
			...options
		};

		this.isDown = false;
		this.startX = 0;
		this.scrollLeft = 0;
		this.currentSlide = 0;
		this.autoplayTimer = null;
		this.isPaused = false;

		if (this.track) {
			this.init();
		}
	}

	init() {
		this.createLiveRegion();
		this.addShareButtons();
		this.setupEventListeners();
		this.initializePagination();
		this.updateCarouselButtons();
		this.updateAriaLabels();

		// Start autoplay if enabled
		if (this.options.autoplay) {
			this.startAutoplay();
		}
	}

	addShareButtons() {
		// Add share buttons to all carousel items (preserve existing images and links)
		const slides = this.track.querySelectorAll(this.options.slideSelector);

		slides.forEach((slide, index) => {
			// Check if image already exists (either img or picture element)
			const existingImage = slide.querySelector('.brag-book-gallery-carousel-image, img');

			// Only add placeholder image if no image exists at all
			if (!existingImage) {
				// This is likely a placeholder slide without server-rendered content
				const placeholderUrl = 'https://bragbookgallery.com/nitropack_static/FCmixFCiYNkGgqjxyaUSblqHbCgLrqyJ/assets/images/optimized/rev-407fb37/ngnqwvuungodwrpnrczq.supabase.co/storage/v1/object/sign/brag-photos/org_2vm5nGWtoCYuaQBCP587ez6cYXF/c68b56b086f4f8eef8292f3f23320f1b.Blepharoplasty%20-%20aa239d58-badc-4ded-a26b-f89c2dd059b6.jpg';

				// Remove any existing span text
				const textSpan = slide.querySelector('span');
				if (textSpan) {
					textSpan.remove();
				}

				// Check if this slide should have a case link
				const caseId = slide.dataset.caseId;
				const shouldHaveLink = caseId && caseId !== '';

				// Create wrapper link if needed
				if (shouldHaveLink) {
					// Try to determine procedure slug from data or context
					const procedureSlug = slide.dataset.procedureSlug || 'procedure';
					const basePath = window.location.pathname.replace(/\/[^\/]+\/[^\/]+\/?$/, '');
					const caseUrl = `${basePath}/${procedureSlug}/${caseId}`.replace(/\/+/g, '/');

					const link = document.createElement('a');
					link.href = caseUrl;
					link.className = 'brag-book-gallery-case-link';
					link.dataset.caseId = caseId;

					const img = document.createElement('img');
					img.src = placeholderUrl;
					img.alt = 'Before and after result';
					img.className = 'brag-book-gallery-carousel-image';
					img.loading = 'lazy';

					link.appendChild(img);
					slide.insertBefore(link, slide.firstChild);
				} else {
					// No link needed, just add the image
					const img = document.createElement('img');
					img.src = placeholderUrl;
					img.alt = 'Before and after result';
					img.className = 'brag-book-gallery-carousel-image';
					img.loading = 'lazy';

					slide.insertBefore(img, slide.firstChild);
				}
			}

			// Ensure item actions container exists
			let actionsContainer = slide.querySelector('.brag-book-gallery-item-actions');
			if (!actionsContainer) {
				actionsContainer = document.createElement('div');
				actionsContainer.className = 'brag-book-gallery-item-actions';
				slide.appendChild(actionsContainer);
			}

			// Ensure heart button exists in actions container
			if (!actionsContainer.querySelector('.brag-book-gallery-heart-btn')) {
				const existingHeart = slide.querySelector('.brag-book-gallery-heart-btn');
				if (existingHeart) {
					actionsContainer.appendChild(existingHeart);
				} else {
					const heartBtn = document.createElement('button');
					heartBtn.className = 'brag-book-gallery-heart-btn';
					heartBtn.dataset.favorited = 'false';
					heartBtn.dataset.itemId = slide.dataset.slide;
					heartBtn.setAttribute('aria-label', 'Add to favorites');
					heartBtn.innerHTML = `
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    `;
					actionsContainer.appendChild(heartBtn);
				}
			}

			// Add share button if not present in actions container and sharing is enabled
			if (!actionsContainer.querySelector('.brag-book-gallery-share-btn') && 
				typeof bragBookGalleryConfig !== 'undefined' && 
				bragBookGalleryConfig.enableSharing === 'yes') {
				const shareBtn = document.createElement('button');
				shareBtn.className = 'brag-book-gallery-share-btn';
				shareBtn.dataset.itemId = slide.dataset.slide;
				shareBtn.setAttribute('aria-label', 'Share this image');
				shareBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                        <path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>
                    </svg>
                `;
				actionsContainer.appendChild(shareBtn);
			}
		});
	}

	createLiveRegion() {
		// Create a live region for screen reader announcements
		this.liveRegion = document.createElement('div');
		this.liveRegion.setAttribute('aria-live', 'polite');
		this.liveRegion.setAttribute('aria-atomic', 'true');
		this.liveRegion.className = 'sr-only';
		this.liveRegion.style.position = 'absolute';
		this.liveRegion.style.width = '1px';
		this.liveRegion.style.height = '1px';
		this.liveRegion.style.padding = '0';
		this.liveRegion.style.margin = '-1px';
		this.liveRegion.style.overflow = 'hidden';
		this.liveRegion.style.clip = 'rect(0, 0, 0, 0)';
		this.liveRegion.style.whiteSpace = 'nowrap';
		this.liveRegion.style.border = '0';
		this.wrapper.appendChild(this.liveRegion);
	}

	setupEventListeners() {
		// Scroll events
		this.track.addEventListener('scroll', () => {
			this.updateCarouselButtons();
			this.updatePagination();
		});

		// Navigation buttons
		if (this.prevBtn) {
			this.prevBtn.addEventListener('click', () => {
				this.navigate('prev');
				// Reset autoplay timer on manual navigation
				if (this.options.autoplay) {
					this.stopAutoplay();
					this.startAutoplay();
				}
			});
		}
		if (this.nextBtn) {
			this.nextBtn.addEventListener('click', () => {
				this.navigate('next');
				// Reset autoplay timer on manual navigation
				if (this.options.autoplay) {
					this.stopAutoplay();
					this.startAutoplay();
				}
			});
		}

		// Mouse drag
		this.track.addEventListener('mousedown', (e) => this.handleMouseDown(e));
		this.track.addEventListener('mouseleave', () => this.handleMouseLeave());
		this.track.addEventListener('mouseup', () => this.handleMouseUp());
		this.track.addEventListener('mousemove', (e) => this.handleMouseMove(e));

		// Autoplay pause on hover
		if (this.options.autoplay && this.options.pauseOnHover) {
			this.wrapper.addEventListener('mouseenter', () => this.pauseAutoplay());
			this.wrapper.addEventListener('mouseleave', () => this.resumeAutoplay());
		}

		// Mouse wheel - smooth horizontal scrolling
		this.track.addEventListener('wheel', (e) => this.handleWheel(e), { passive: false });

		// Keyboard navigation - arrow keys and tab
		this.track.addEventListener('keydown', (e) => this.handleKeydown(e));

		// Also listen for keyboard events on the wrapper and document when carousel has focus
		this.wrapper.addEventListener('keydown', (e) => this.handleKeydown(e));

		// Global keyboard listener when carousel or its children have focus
		document.addEventListener('keydown', (e) => {
			if (this.wrapper.contains(document.activeElement)) {
				this.handleKeydown(e);
			}
		});

		// Make track focusable for keyboard navigation
		this.track.tabIndex = 0;
		this.track.setAttribute('aria-label', 'Use arrow keys to navigate through slides');

		// Focus management for slides
		this.setupSlideFocusManagement();
	}

	handleMouseDown(e) {
		this.isDown = true;
		this.track.classList.add('brag-book-gallery-grabbing');
		this.startX = e.pageX - this.track.offsetLeft;
		this.scrollLeft = this.track.scrollLeft;
	}

	handleMouseLeave() {
		this.isDown = false;
		this.track.classList.remove('brag-book-gallery-grabbing');
	}

	handleMouseUp() {
		this.isDown = false;
		this.track.classList.remove('brag-book-gallery-grabbing');
	}

	handleMouseMove(e) {
		if (!this.isDown) return;
		e.preventDefault();
		const x = e.pageX - this.track.offsetLeft;
		const walk = (x - this.startX) * 2;
		this.track.scrollLeft = this.scrollLeft - walk;
	}

	handleKeydown(e) {
		// Don't handle if event is from an input or textarea
		if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
			return;
		}

		const slides = this.track.querySelectorAll(this.options.slideSelector);
		if (slides.length === 0) return;

		const currentIndex = this.getCurrentSlideIndex();
		let handled = false;

		switch(e.key) {
			case 'ArrowLeft':
				e.preventDefault();
				e.stopPropagation();
				this.navigateToSlide(Math.max(0, currentIndex - 1));
				handled = true;
				break;
			case 'ArrowRight':
				e.preventDefault();
				e.stopPropagation();
				this.navigateToSlide(Math.min(slides.length - 1, currentIndex + 1));
				handled = true;
				break;
			case 'Home':
				e.preventDefault();
				e.stopPropagation();
				this.navigateToSlide(0);
				handled = true;
				break;
			case 'End':
				e.preventDefault();
				e.stopPropagation();
				this.navigateToSlide(slides.length - 1);
				handled = true;
				break;
			case 'PageUp':
				e.preventDefault();
				e.stopPropagation();
				const itemsPerView = this.getItemsPerView();
				this.navigateToSlide(Math.max(0, currentIndex - itemsPerView));
				handled = true;
				break;
			case 'PageDown':
				e.preventDefault();
				e.stopPropagation();
				const itemsPerPage = this.getItemsPerView();
				this.navigateToSlide(Math.min(slides.length - 1, currentIndex + itemsPerPage));
				handled = true;
				break;
		}

		// Focus the track if we handled the event and it's not already focused
		if (handled && document.activeElement !== this.track && !this.track.contains(document.activeElement)) {
			this.track.focus();
		}

		// Reset autoplay timer on manual keyboard navigation
		if (handled && this.options.autoplay) {
			this.stopAutoplay();
			this.startAutoplay();
		}
	}

	handleWheel(e) {
		// Prevent vertical scrolling when over carousel
		e.preventDefault();

		// Determine scroll direction and amount
		const delta = e.deltaY || e.deltaX;
		const scrollAmount = Math.abs(delta) > 50 ? delta : delta * 3;

		// Apply smooth scrolling with momentum
		if (this.wheelTimeout) {
			clearTimeout(this.wheelTimeout);
		}

		// Use native smooth scrolling for better performance
		this.track.scrollBy({
			left: scrollAmount,
			behavior: 'smooth'
		});

		// Update buttons after scrolling stops
		this.wheelTimeout = setTimeout(() => {
			this.updateCarouselButtons();
			this.updatePagination();
		}, 150);
	}

	getItemsPerView() {
		const width = window.innerWidth;
		if (width <= 480) return 1;
		if (width <= 1024) return 2;
		return 3;
	}

	navigate(direction) {
		const slides = this.track.querySelectorAll(this.options.slideSelector);
		if (slides.length === 0) return;

		const currentIndex = this.getCurrentSlideIndex();
		let targetIndex;

		if (this.options.infinite) {
			// Infinite loop: wrap around
			if (direction === 'next') {
				targetIndex = currentIndex >= slides.length - 1 ? 0 : currentIndex + 1;
			} else {
				targetIndex = currentIndex <= 0 ? slides.length - 1 : currentIndex - 1;
			}
		} else {
			// Finite: clamp to bounds
			targetIndex = direction === 'next'
				? Math.min(slides.length - 1, currentIndex + 1)
				: Math.max(0, currentIndex - 1);
		}

		this.navigateToSlide(targetIndex);
	}

	navigateToSlide(index) {
		const slides = this.track.querySelectorAll(this.options.slideSelector);
		if (slides.length === 0 || index < 0 || index >= slides.length) return;

		const targetSlide = slides[index];
		const slideWidth = targetSlide.offsetWidth;
		const trackWidth = this.track.offsetWidth;

		// Calculate scroll position to center the slide when possible
		let scrollPosition = targetSlide.offsetLeft - (trackWidth - slideWidth) / 2;

		// Clamp scroll position to valid range
		scrollPosition = Math.max(0, Math.min(scrollPosition, this.track.scrollWidth - trackWidth));

		if (typeof gsap !== 'undefined') {
			gsap.to(this.track, {
				scrollLeft: scrollPosition,
				duration: this.options.animationDuration,
				ease: "power2.inOut",
				onComplete: () => {
					this.updateAriaLabels();
					this.focusSlide(index);
				}
			});
		} else {
			this.track.scrollTo({
				left: scrollPosition,
				behavior: 'smooth'
			});

			setTimeout(() => {
				this.updateAriaLabels();
				this.focusSlide(index);
			}, 300);
		}

		this.currentSlide = index;
	}

	getCurrentSlideIndex() {
		const slides = this.track.querySelectorAll(this.options.slideSelector);
		const trackCenter = this.track.scrollLeft + this.track.offsetWidth / 2;

		let closestIndex = 0;
		let closestDistance = Infinity;

		slides.forEach((slide, index) => {
			const slideCenter = slide.offsetLeft + slide.offsetWidth / 2;
			const distance = Math.abs(slideCenter - trackCenter);

			if (distance < closestDistance) {
				closestDistance = distance;
				closestIndex = index;
			}
		});

		return closestIndex;
	}

	// Autoplay methods
	startAutoplay() {
		if (!this.options.autoplay || this.autoplayTimer) return;

		this.autoplayTimer = setInterval(() => {
			if (!this.isPaused && !this.isDown) {
				this.navigate('next');
			}
		}, this.options.autoplayDelay);
	}

	stopAutoplay() {
		if (this.autoplayTimer) {
			clearInterval(this.autoplayTimer);
			this.autoplayTimer = null;
		}
	}

	pauseAutoplay() {
		this.isPaused = true;
	}

	resumeAutoplay() {
		this.isPaused = false;
	}

	setupSlideFocusManagement() {
		const slides = this.track.querySelectorAll(this.options.slideSelector);

		slides.forEach((slide, index) => {
			// Make slides focusable
			slide.tabIndex = -1;

			// Handle focus events
			slide.addEventListener('focus', () => {
				this.currentSlide = index;
				// Don't auto-navigate on focus to prevent jumpy behavior
			});

			// Handle click to focus
			slide.addEventListener('click', (e) => {
				// Only focus if not clicking on a button
				if (!e.target.closest('button')) {
					slide.focus();
				}
			});
		});

		// Make the wrapper focusable as well for better keyboard navigation
		this.wrapper.tabIndex = -1;
		this.wrapper.style.outline = 'none';

		// Focus wrapper on click (but not on button clicks)
		this.wrapper.addEventListener('click', (e) => {
			if (!e.target.closest('button') && !e.target.closest('[tabindex]')) {
				this.track.focus();
			}
		});
	}

	focusSlide(index) {
		const slides = this.track.querySelectorAll(this.options.slideSelector);
		if (slides[index] && document.activeElement === this.track) {
			slides[index].focus();
		}
	}

	initializePagination() {
		if (!this.paginationContainer) return;

		this.paginationContainer.innerHTML = "";

		const slides = this.track.querySelectorAll(this.options.slideSelector);
		const itemsPerView = this.getItemsPerView();
		const totalPages = Math.ceil(slides.length / itemsPerView);

		for (let i = 0; i < totalPages; i++) {
			const dot = document.createElement('button');
			dot.className = 'brag-book-gallery-pagination-dot';
			dot.role = 'tab';
			if (i === 0) {
				dot.classList.add('brag-book-gallery-active');
				dot.setAttribute('aria-selected', 'true');
			} else {
				dot.setAttribute('aria-selected', 'false');
			}
			dot.setAttribute('data-page', i);
			dot.setAttribute('aria-label', `Go to slide group ${i + 1}`);

			dot.addEventListener('click', () => this.goToPage(i));

			this.paginationContainer.appendChild(dot);
		}
	}

	goToPage(pageIndex) {
		const slides = this.track.querySelectorAll(this.options.slideSelector);
		if (slides.length === 0) return;

		const itemWidth = slides[0].offsetWidth;
		const itemsPerView = this.getItemsPerView();
		const scrollPosition = pageIndex * itemsPerView * (itemWidth + this.options.gap);

		this.currentSlide = pageIndex * itemsPerView;

		if (typeof gsap !== 'undefined') {
			gsap.to(this.track, {
				scrollLeft: scrollPosition,
				duration: 0.5,
				ease: "power2.inOut",
				onComplete: () => this.updateAriaLabels()
			});
		} else {
			this.track.scrollLeft = scrollPosition;
			this.updateAriaLabels();
		}
	}

	updatePagination() {
		if (!this.paginationContainer) return;

		const slides = this.track.querySelectorAll(this.options.slideSelector);
		if (slides.length === 0) return;

		const itemWidth = slides[0].offsetWidth;
		const itemsPerView = this.getItemsPerView();
		const currentScroll = this.track.scrollLeft;
		const currentPage = Math.round(currentScroll / ((itemWidth + this.options.gap) * itemsPerView));

		this.currentSlide = currentPage * itemsPerView;

		const dots = this.paginationContainer.querySelectorAll('.brag-book-gallery-pagination-dot');
		dots.forEach((dot, index) => {
			if (index === currentPage) {
				dot.classList.add('brag-book-gallery-active');
				dot.setAttribute('aria-selected', 'true');
			} else {
				dot.classList.remove('brag-book-gallery-active');
				dot.setAttribute('aria-selected', 'false');
			}
		});
	}

	updateCarouselButtons() {
		// In infinite mode, buttons are never disabled
		if (this.options.infinite) {
			if (this.prevBtn) {
				this.prevBtn.disabled = false;
			}
			if (this.nextBtn) {
				this.nextBtn.disabled = false;
			}
			return;
		}

		// Original finite mode logic
		const scrollLeft = this.track.scrollLeft;
		const scrollWidth = this.track.scrollWidth;
		const clientWidth = this.track.clientWidth;

		if (this.prevBtn) {
			this.prevBtn.disabled = scrollLeft <= 0;
		}

		if (this.nextBtn) {
			this.nextBtn.disabled = scrollLeft >= scrollWidth - clientWidth - 1;
		}
	}

	updateAriaLabels() {
		const slides = this.track.querySelectorAll(this.options.slideSelector);
		const totalSlides = slides.length;
		const currentIndex = this.getCurrentSlideIndex();

		slides.forEach((slide, index) => {
			const slideNumber = index + 1;
			const isCurrent = index === currentIndex;
			slide.setAttribute('aria-label', `Slide ${slideNumber} of ${totalSlides}${isCurrent ? ' (current)' : ''}`);
			slide.setAttribute('aria-current', isCurrent ? 'true' : 'false');
		});

		// Announce current slide to screen readers
		if (this.liveRegion) {
			this.liveRegion.textContent = `Slide ${currentIndex + 1} of ${totalSlides}`;
		}
	}

	refresh() {
		this.options.itemsPerView = this.getItemsPerView();
		this.initializePagination();
		this.updatePagination();
		this.updateAriaLabels();
	}

	goToSlide(slideId) {
		const slides = this.track.querySelectorAll(this.options.slideSelector);
		const targetSlide = Array.from(slides).find(slide => slide.dataset.slide === slideId);

		if (targetSlide) {
			const slideIndex = Array.from(slides).indexOf(targetSlide);
			const itemsPerView = this.getItemsPerView();
			const pageIndex = Math.floor(slideIndex / itemsPerView);
			this.goToPage(pageIndex);
		}
	}
}

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

/**
 * Filter System Component
 * Manages expandable filter groups with animations
 * Supports both JS-based filtering and URL navigation modes
 */
class FilterSystem {
	constructor(container, options = {}) {
		this.container = container;
		this.filterHeaders = container?.querySelectorAll('.brag-book-gallery-filter-header');
		this.activeFilters = new Map();
		this.categories = new Map();
		this.procedures = new Map();

		this.options = {
			mode: options.mode || 'javascript', // 'javascript' or 'navigation'
			baseUrl: options.baseUrl || '/gallery',
			animateWithGsap: typeof gsap !== 'undefined',
			closeOthersOnOpen: options.closeOthersOnOpen !== false,
			onFilterChange: options.onFilterChange || (() => {}),
			onNavigate: options.onNavigate || ((url) => { window.location.href = url; }),
			...options
		};

		if (this.container) {
			this.init();
		}
	}

	init() {
		this.indexFilters();
		this.setupEventListeners();
		this.loadStateFromUrl();

		// Initialize GSAP states if available
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			gsap.set(".brag-book-gallery-filter-content", { height: 0 });
		}
	}

	indexFilters() {
		// Index all categories and procedures
		const categoryGroups = this.container?.querySelectorAll('[data-category]');

		categoryGroups?.forEach(group => {
			const category = group.dataset.category;
			if (!this.categories.has(category)) {
				this.categories.set(category, {
					name: category,
					procedures: new Set(),
					element: group
				});
			}

			// Index procedures within this category
			const filterLinks = group.querySelectorAll('.brag-book-gallery-filter-link');
			filterLinks.forEach(link => {
				const procedure = link.dataset.procedure;
				const category = link.dataset.category;

				if (procedure && category) {
					const procedureData = {
						id: procedure,
						category: category,
						count: parseInt(link.dataset.procedureCount || '0'),
						element: link.parentElement,
						link: link
					};

					this.procedures.set(`${category}:${procedure}`, procedureData);
					this.categories.get(category).procedures.add(procedure);
				}
			});
		});
	}

	setupEventListeners() {
		this.filterHeaders?.forEach(header => {
			header.addEventListener('click', () => this.toggleFilter(header));
		});

		// Filter anchor links - Handle with AJAX to load into #gallery-content
		const filterLinks = this.container?.querySelectorAll('.brag-book-gallery-filter-link');
		filterLinks?.forEach(link => {
			link.addEventListener('click', (e) => {
				e.preventDefault(); // Prevent default navigation
				this.handleFilterClick(e.currentTarget);
			});
		});

		// Handle browser back/forward buttons
		window.addEventListener('popstate', (e) => {
			if (e.state && e.state.caseId) {
				// Navigate to/from case detail
				window.loadCaseDetails(e.state.caseId, e.state.procedureId, e.state.procedureSlug);
			} else if (e.state && e.state.category && e.state.procedure) {
				// Reactivate the filter
				this.reactivateFilter(e.state.category, e.state.procedure);
				this.loadFilteredContent(e.state.category, e.state.procedure, e.state.procedureIds, e.state.hasNudity || false);
			} else {
				// Going back to base page - clear filters and reload
				this.clearAll();
				window.location.reload();
			}
		});
	}

	toggleFilter(button) {
		const isExpanded = button.dataset.expanded === 'true';
		const content = button.nextElementSibling;
		const group = button.closest('.brag-book-gallery-filter-group');

		// Close other filters if option is enabled
		if (!isExpanded && this.options.closeOthersOnOpen) {
			this.closeOtherFilters(button);
		}

		// Toggle state
		button.dataset.expanded = !isExpanded;
		content.dataset.expanded = !isExpanded;
		group.dataset.expanded = !isExpanded;

		// Animate
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			if (!isExpanded) {
				gsap.to(content, {
					height: "auto",
					opacity: 1,
					duration: 0.4,
					ease: "power2.out"
				});
			} else {
				gsap.to(content, {
					height: 0,
					opacity: 0,
					duration: 0.3,
					ease: "power2.in"
				});
			}
		} else {
			content.style.height = !isExpanded ? 'auto' : '0';
			content.style.opacity = !isExpanded ? '1' : '0';
		}
	}

	closeOtherFilters(currentButton) {
		this.filterHeaders?.forEach(header => {
			if (header !== currentButton && header.dataset.expanded === 'true') {
				const content = header.nextElementSibling;
				const group = header.closest('.brag-book-gallery-filter-group');

				header.dataset.expanded = 'false';
				content.dataset.expanded = 'false';
				group.dataset.expanded = 'false';

				if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
					gsap.to(content, {
						height: 0,
						opacity: 0,
						duration: 0.3,
						ease: "power2.in"
					});
				} else {
					content.style.height = '0';
					content.style.opacity = '0';
				}
			}
		});
	}

	closeAllFilters() {
		this.filterHeaders?.forEach(header => {
			if (header.dataset.expanded === 'true') {
				this.toggleFilter(header);
			}
		});
	}

	handleFilterClick(link) {
		const category = link.dataset.category;
		const procedure = link.dataset.procedure;
		const procedureIds = link.dataset.procedureIds;
		const count = parseInt(link.dataset.procedureCount || '0');
		const hasNudity = link.dataset.nudity === 'true'; // Get nudity attribute

		// Clear all active filters first
		this.activeFilters.clear();

		// Remove active class from all links
		this.container?.querySelectorAll('.brag-book-gallery-filter-link').forEach(filterLink => {
			filterLink.classList.remove('brag-book-gallery-active');
		});

		// Add active class to clicked link and its parent item
		link.classList.add('brag-book-gallery-active');
		const filterItem = link.closest('.brag-book-gallery-filter-item');
		if (filterItem) {
			filterItem.classList.add('brag-book-gallery-active');
		}

		// Store in active filters
		this.activeFilters.set(`${category}:${procedure}`, {
			category: category,
			procedure: procedure,
			procedureIds: procedureIds,
			count: count,
			hasNudity: hasNudity // Store nudity flag
		});

		// Get the base URL from the gallery wrapper data attribute
		const galleryWrapper = document.querySelector('.brag-book-gallery-wrapper');
		let basePath = galleryWrapper?.dataset.baseUrl || window.location.pathname;

		// If no base URL in data attribute, try to extract from current path
		if (!galleryWrapper?.dataset.baseUrl && basePath.match(/\/[^\/]+\/?$/)) {
			// Remove the existing filter segment (just procedure now)
			basePath = basePath.replace(/\/[^\/]+\/?$/, '');
		}

		// Create URL appending to current path: /before-after/procedure (no category)
		const filterUrl = `${basePath}/${procedure}`.replace(/\/+/g, '/');

		// Update browser URL
		window.history.pushState(
			{ category, procedure, procedureIds, basePath, hasNudity },
			'',
			filterUrl
		);

		// Load filtered content via AJAX
		this.loadFilteredContent(category, procedure, procedureIds, hasNudity);

		// Animate the selection
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			gsap.to(link, {
				scale: 1.02,
				duration: 0.1,
				yoyo: true,
				repeat: 1,
				ease: "power2.inOut"
			});
		}
	}

	navigateToFilteredPage() {
		// For anchor links, this method is mostly obsolete since clicking the link navigates directly
		// But keeping it for compatibility if needed
		if (this.activeFilters.size > 0) {
			const filter = Array.from(this.activeFilters.values())[0];
			const url = `/${filter.procedure}`; // Procedure only, no category
			this.options.onNavigate(url);
		} else {
			this.options.onNavigate(this.options.baseUrl);
		}
	}

	updateUrlState() {
		// Update URL without reloading (for JS mode)
		if (window.history && window.history.replaceState) {
			const params = new URLSearchParams();

			// Group filters by category
			const filtersByCategory = new Map();

			this.activeFilters.forEach((filter) => {
				if (!filtersByCategory.has(filter.category)) {
					filtersByCategory.set(filter.category, []);
				}
				filtersByCategory.get(filter.category).push(filter.procedure);
			});

			// Add to URL params
			filtersByCategory.forEach((procedures, category) => {
				params.append(category, procedures.join(','));
			});

			const newUrl = params.toString() ? `?${params.toString()}` : window.location.pathname;
			window.history.replaceState({}, '', newUrl);
		}
	}

	reactivateFilter(category, procedure) {
		// Clear all active filters first
		this.activeFilters.clear();

		// Remove active class from all links
		this.container?.querySelectorAll('.brag-book-gallery-filter-link').forEach(filterLink => {
			filterLink.classList.remove('brag-book-gallery-active');
		});

		// Find and activate the matching filter link (match by procedure primarily)
		const filterLink = document.querySelector(
			`.brag-book-gallery-filter-link[data-procedure="${procedure}"]`
		);

		if (filterLink) {
			filterLink.classList.add('brag-book-gallery-active');

			// Store in active filters
			this.activeFilters.set(`${category}:${procedure}`, {
				category: category,
				procedure: procedure,
				procedureIds: filterLink.dataset.procedureIds,
				count: parseInt(filterLink.dataset.procedureCount || '0')
			});
		}
	}

	loadStateFromUrl() {
		const galleryWrapper = document.querySelector('.brag-book-gallery-wrapper');
		const basePath = galleryWrapper?.dataset.baseUrl || '';

		// First check if there's an initial procedure filter from the server
		const initialProcedure = galleryWrapper?.dataset.initialProcedure;

		if (initialProcedure) {
			// The server has passed us an initial procedure filter
			// Find the filter link to get category and procedure IDs
			const filterLink = document.querySelector(
				`.brag-book-gallery-filter-link[data-procedure="${initialProcedure}"]`
			);

			if (filterLink) {
				const category = filterLink.dataset.category || '';
				const hasNudity = filterLink.dataset.nudity === 'true';
				// Use reactivateFilter to set the active state
				this.reactivateFilter(category, initialProcedure);
				// Load the filtered content
				this.loadFilteredContent(category, initialProcedure, filterLink.dataset.procedureIds, hasNudity);
				return; // Exit early since we've applied the filter
			}
		}

		// Otherwise, check the URL path for filter information
		const path = window.location.pathname;

		// Remove base path to get just the filter part
		let filterPath = path;
		if (basePath && path.startsWith(basePath)) {
			filterPath = path.substring(basePath.length);
		}

		// Check for case detail URL pattern: /procedure-slug/case-id/
		const caseMatches = filterPath.match(/^\/([^\/]+)\/(\d+)\/?$/);
		if (caseMatches) {
			const [, procedureSlug, caseId] = caseMatches;
			// This pattern is now handled by the initialCase check above
			// when the server passes the data via data attributes
			return;
		}

		// Match just procedure (single segment)
		const matches = filterPath.match(/^\/([^\/]+)\/?$/);

		if (matches) {
			const [, procedure] = matches;

			// Find the filter link to get category and procedure IDs
			const filterLink = document.querySelector(
				`.brag-book-gallery-filter-link[data-procedure="${procedure}"]`
			);

			if (filterLink) {
				const category = filterLink.dataset.category || '';
				const hasNudity = filterLink.dataset.nudity === 'true';
				// Use reactivateFilter to set the active state
				this.reactivateFilter(category, procedure);
				// Load the filtered content
				this.loadFilteredContent(category, procedure, filterLink.dataset.procedureIds, hasNudity);
			}
		}
	}

	getActiveFilters() {
		return this.activeFilters;
	}

	getFiltersByCategory(category) {
		const filters = [];
		this.activeFilters.forEach((filter) => {
			if (filter.category === category) {
				filters.push(filter);
			}
		});
		return filters;
	}

	getFiltersByProcedure(procedure) {
		const filters = [];
		this.activeFilters.forEach((filter) => {
			if (filter.procedure === procedure) {
				filters.push(filter);
			}
		});
		return filters;
	}

	clearCategory(category) {
		// Clear filter in a category
		const toRemove = [];
		this.activeFilters.forEach((filter, key) => {
			if (filter.category === category) {
				toRemove.push(key);
			}
		});

		toRemove.forEach(key => {
			const procedureData = this.procedures.get(key);
			if (procedureData && procedureData.link) {
				procedureData.link.classList.remove('brag-book-gallery-active');
			}
		});

		// Clear from active filters
		toRemove.forEach(key => this.activeFilters.delete(key));
	}

	clearAll() {
		// Clear all active filters
		const filterLinks = this.container?.querySelectorAll('.brag-book-gallery-filter-link');
		filterLinks?.forEach(link => {
			link.classList.remove('brag-book-gallery-active');
		});
		this.activeFilters.clear();

		// Reset URL to base
		window.history.pushState({}, '', window.location.pathname);
	}

	setMode(mode) {
		this.options.mode = mode;
	}

	loadFilteredContent(category, procedure, procedureIds, hasNudity = false) {
		const galleryContent = document.getElementById('gallery-content');
		if (!galleryContent) return;

		// Show loading state
		galleryContent.innerHTML = '<div class="brag-book-gallery-loading">Loading filtered results...</div>';

		// Get AJAX configuration
		const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
		const nonce = window.bragBookGalleryConfig?.nonce || '';

		// Prepare request data
		const formData = new FormData();
		formData.append('action', 'brag_book_load_filtered_gallery');
		formData.append('nonce', nonce);
		formData.append('category_name', category);
		formData.append('procedure_name', procedure);
		formData.append('procedure_ids', procedureIds || ''); // Send all procedure IDs
		formData.append('procedure_id', procedureIds?.split(',')[0] || ''); // Keep backward compatibility
		formData.append('has_nudity', hasNudity ? '1' : '0'); // Send nudity flag

		// Make AJAX request
		fetch(ajaxUrl, {
			method: 'POST',
			body: formData
		})
			.then(response => response.json())
			.then(result => {
				if (result.success && result.data?.html) {
					galleryContent.innerHTML = result.data.html;

					// Re-initialize carousels if present
					const carousels = galleryContent.querySelectorAll('.brag-book-gallery-carousel-wrapper');
					carousels.forEach(carousel => {
						new Carousel(carousel);
					});

					// Initialize procedure filters after AJAX content loads
					setTimeout(function() {
						initializeProcedureFilters();
					}, 100);

					// Comment out the problematic call for now
					// this.reinitializeCaseLinks();
				} else {
					galleryContent.innerHTML = '<div class="brag-book-gallery-error">No results found for the selected filter.</div>';
				}
			})
			.catch(error => {
				console.error('Filter loading error:', error);
				galleryContent.innerHTML = '<div class="brag-book-gallery-error">Failed to load filtered content. Please try again.</div>';
			});
	}
}

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

/**
 * Favorites Manager
 * Manages favorited items across the gallery
 */
class FavoritesManager {
	constructor(options = {}) {
		this.favorites = new Set();
		this.userInfo = null;
		this.hasShownDialog = false;
		this.options = {
			storageKey: options.storageKey || 'brag-book-favorites',
			userInfoKey: options.userInfoKey || 'brag-book-user-info',
			persistToStorage: options.persistToStorage !== false,
			onUpdate: options.onUpdate || (() => {}),
			...options
		};

		this.init();
	}

	init() {
		this.favoritesDialog = new Dialog('favoritesDialog', {
			backdropId: 'dialogBackdrop',
			onClose: () => {
				// If user closes without submitting, remove the just-added favorite
				if (!this.userInfo && this.lastAddedFavorite) {
					this.removeFavorite(this.lastAddedFavorite);
					this.lastAddedFavorite = null;
				}
			}
		});

		if (this.options.persistToStorage) {
			this.loadFromStorage();
			this.loadUserInfo();
		}
		this.setupEventListeners();
		this.updateUI();
	}

	setupEventListeners() {
		document.addEventListener('click', (e) => {
			const button = e.target.closest('[data-favorited]');
			if (button) {
				e.preventDefault();
				this.toggleFavorite(button);
			}

			// Handle close button for favorites dialog
			if (e.target.closest('[data-action="close-favorites-dialog"]')) {
				this.favoritesDialog.close();
			}
		});

		// Handle favorites form submission
		const favoritesForm = document.querySelector('[data-form="favorites"]');
		if (favoritesForm) {
			favoritesForm.addEventListener('submit', (e) => {
				e.preventDefault();
				this.handleFavoritesFormSubmit(e.target);
			});
		}
	}

	toggleFavorite(button) {
		const itemId = button.dataset.itemId;
		const isFavorited = button.dataset.favorited === 'true';

		// If removing favorite, just remove it
		if (isFavorited) {
			this.removeFavorite(itemId, button);
			return;
		}

		// If adding favorite and no user info, show dialog first
		if (!this.userInfo && !this.hasShownDialog) {
			this.lastAddedFavorite = itemId;
			this.lastAddedButton = button;
			// Add the favorite first so it shows in the dialog
			this.addFavorite(itemId, button);
			this.favoritesDialog.open();
			this.hasShownDialog = true;
			return;
		}

		// Add favorite
		this.addFavorite(itemId, button);
	}

	addFavorite(itemId, button) {
		// Animate heart
		button.dataset.favorited = 'true';

		// Add to favorites set
		this.favorites.add(itemId);

		if (this.options.persistToStorage) {
			this.saveToStorage();
		}

		this.updateUI();
		this.options.onUpdate(this.favorites);
	}

	removeFavorite(itemId, button) {
		// Find button if not provided
		if (!button) {
			button = document.querySelector(`[data-item-id="${itemId}"][data-favorited="true"]`);
		}

		if (button) {
			button.dataset.favorited = 'false';
		}

		// Remove from favorites set
		this.favorites.delete(itemId);

		if (this.options.persistToStorage) {
			this.saveToStorage();
		}

		this.updateUI();
		this.options.onUpdate(this.favorites);
	}

	handleFavoritesFormSubmit(form) {
		const formData = new FormData(form);
		const data = Object.fromEntries(formData.entries());

		// Save user info
		this.userInfo = data;
		if (this.options.persistToStorage) {
			this.saveUserInfo();
		}

		// Add the pending favorite
		if (this.lastAddedFavorite && this.lastAddedButton) {
			this.addFavorite(this.lastAddedFavorite, this.lastAddedButton);
			this.lastAddedFavorite = null;
			this.lastAddedButton = null;
		}

		// Close dialog
		this.favoritesDialog.close();

		// Show success message
		this.showSuccessNotification('Your information has been saved. Keep adding favorites!');

		console.log('User info saved:', data);
	}

	showSuccessNotification(message) {
		// Create notification element if it doesn't exist
		let notification = document.getElementById('favoritesNotification');
		if (!notification) {
			notification = document.createElement('div');
			notification.id = 'favoritesNotification';
			notification.className = 'brag-book-gallery-favorites-notification';
			document.body.appendChild(notification);
		}

		// Update message and show
		notification.textContent = message;
		notification.classList.add('active');

		// Animate if GSAP available
		if (typeof gsap !== 'undefined') {
			gsap.fromTo(notification,
				{ y: -20, opacity: 0 },
				{ y: 0, opacity: 1, duration: 0.3, ease: "back.out(1.7)" }
			);
		}

		// Hide after 3 seconds
		setTimeout(() => {
			if (typeof gsap !== 'undefined') {
				gsap.to(notification, {
					y: -20,
					opacity: 0,
					duration: 0.2,
					ease: "power2.in",
					onComplete: () => {
						notification.classList.remove('active');
					}
				});
			} else {
				notification.classList.remove('active');
			}
		}, 3000);
	}

	updateFavoritesDisplay() {
		const grid = document.getElementById('favorites-grid');
		const emptyMessage = document.getElementById('favorites-empty');

		if (!grid || !emptyMessage) return;

		// Clear existing items
		grid.innerHTML = '';

		if (this.favorites.size === 0) {
			grid.style.display = 'none';
			emptyMessage.style.display = 'block';
		} else {
			grid.style.display = 'grid';
			emptyMessage.style.display = 'none';

			// Add each favorite to the grid
			this.favorites.forEach(itemId => {
				// Find the original image
				const originalItem = document.querySelector(`[data-item-id="${itemId}"]`);
				if (!originalItem) return;

				const carouselItem = originalItem.closest('.brag-book-gallery-carousel-item');
				if (!carouselItem) return;

				const img = carouselItem.querySelector('img');
				if (!img) return;

				// Create favorite item
				const favoriteItem = document.createElement('div');
				favoriteItem.className = 'brag-book-gallery-favorites-item';
				favoriteItem.dataset.itemId = itemId;

				// Clone and add image
				const imgClone = img.cloneNode(true);
				favoriteItem.appendChild(imgClone);

				// Add remove button
				const removeBtn = document.createElement('button');
				removeBtn.className = 'brag-book-gallery-favorites-item-remove';
				removeBtn.innerHTML = '×';
				removeBtn.title = 'Remove from favorites';
				removeBtn.onclick = (e) => {
					e.stopPropagation();
					this.removeFavorite(itemId);
				};

				favoriteItem.appendChild(removeBtn);
				grid.appendChild(favoriteItem);
			});
		}
	}

	updateUI() {
		// Update count
		const countElement = document.querySelector('[data-favorites-count]');
		const count = this.favorites.size;

		if (countElement) {
			if (typeof gsap !== 'undefined') {
				gsap.to(countElement, {
					opacity: 0,
					duration: 0.1,
					onComplete: () => {
						countElement.textContent = `(${count})`;
						gsap.to(countElement, { opacity: 1, duration: 0.1 });
					}
				});
			} else {
				countElement.textContent = `(${count})`;
			}
		}

		// Update favorites grid in sidebar
		this.updateFavoritesDisplay();
	}

	loadFromStorage() {
		try {
			const stored = localStorage.getItem(this.options.storageKey);
			if (stored) {
				const items = JSON.parse(stored);
				this.favorites = new Set(items);

				// Update button states
				items.forEach(itemId => {
					const button = document.querySelector(`[data-item-id="${itemId}"]`);
					if (button) {
						button.dataset.favorited = 'true';
					}
				});
			}
		} catch (e) {
			console.error('Failed to load favorites from storage:', e);
		}
	}

	saveToStorage() {
		try {
			localStorage.setItem(this.options.storageKey, JSON.stringify([...this.favorites]));
		} catch (e) {
			console.error('Failed to save favorites to storage:', e);
		}
	}

	loadUserInfo() {
		try {
			const stored = localStorage.getItem(this.options.userInfoKey);
			if (stored) {
				this.userInfo = JSON.parse(stored);
				this.hasShownDialog = true; // Don't show dialog again if we have user info
			}
		} catch (e) {
			console.error('Failed to load user info from storage:', e);
		}
	}

	saveUserInfo() {
		try {
			localStorage.setItem(this.options.userInfoKey, JSON.stringify(this.userInfo));
		} catch (e) {
			console.error('Failed to save user info to storage:', e);
		}
	}

	getFavorites() {
		return this.favorites;
	}

	clear() {
		this.favorites.clear();
		if (this.options.persistToStorage) {
			this.saveToStorage();
		}
		this.updateUI();
	}
}

/**
 * Share Manager Component
 * Handles sharing functionality for carousel images
 */
class ShareManager {
	constructor(options = {}) {
		this.options = {
			shareMenuId: options.shareMenuId || 'shareMenu',
			animateWithGsap: typeof gsap !== 'undefined',
			onShare: options.onShare || (() => {}),
			...options
		};

		this.shareMenu = document.getElementById(this.options.shareMenuId);
		this.activeButton = null;
		this.activeItem = null;

		this.init();
	}

	init() {
		this.setupEventListeners();
		this.createShareMenu();
	}

	createShareMenu() {
		// If share menu doesn't exist, create it
		if (!this.shareMenu) {
			const menuHtml = `
                <div id="${this.options.shareMenuId}" class="brag-book-gallery-share-dropdown" role="menu">
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="link" role="menuitem">Copy Link</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="email" role="menuitem">Email</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="facebook" role="menuitem">Facebook</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="twitter" role="menuitem">Twitter</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="pinterest" role="menuitem">Pinterest</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="whatsapp" role="menuitem">WhatsApp</button>
                </div>
            `;

			document.body.insertAdjacentHTML('beforeend', menuHtml);
			this.shareMenu = document.getElementById(this.options.shareMenuId);
		}
	}

	setupEventListeners() {
		// Listen for share button clicks (delegated)
		document.addEventListener('click', (e) => {
			const shareButton = e.target.closest('.brag-book-gallery-share-btn');
			if (shareButton) {
				e.preventDefault();
				e.stopPropagation();
				this.toggleShareDropdown(shareButton);
			}

			// Handle dropdown item clicks
			const dropdownItem = e.target.closest('.brag-book-gallery-share-dropdown-item');
			if (dropdownItem) {
				e.preventDefault();
				const shareType = dropdownItem.dataset.shareType;
				if (shareType) {
					this.handleShare(shareType);
					this.hideShareDropdown();
				}
			}

			// Close dropdown when clicking outside
			if (this.shareMenu?.classList.contains('active') &&
			    !this.shareMenu.contains(e.target) &&
			    !e.target.closest('.brag-book-gallery-share-btn')) {
				this.hideShareDropdown();
			}
		});

		// Close on escape
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && this.shareMenu?.classList.contains('active')) {
				this.hideShareDropdown();
			}
		});
	}

	toggleShareDropdown(button) {
		if (this.activeButton === button && this.shareMenu?.classList.contains('active')) {
			this.hideShareDropdown();
		} else {
			this.showShareDropdown(button);
		}
	}

	showShareDropdown(button) {
		if (!this.shareMenu) return;

		// Get the carousel item (slide)
		this.activeItem = button.closest('.brag-book-gallery-carousel-item');
		this.activeButton = button;

		// Add active class to button
		button.classList.add('active');

		// Get positions
		const buttonRect = button.getBoundingClientRect();
		const slideRect = this.activeItem.getBoundingClientRect();
		const dropdownWidth = 120; // Approximate dropdown width
		const dropdownHeight = 240; // Approximate dropdown height (6 items * 40px)

		// Align dropdown's right edge with button's right edge
		let left = buttonRect.right - dropdownWidth;
		let top = buttonRect.bottom + 8;

		// Make sure dropdown stays within the slide boundaries
		// Check if dropdown would go past the left edge of the slide
		if (left < slideRect.left) {
			left = slideRect.left + 8; // Add small padding from left edge
		}

		// Check if dropdown would go past the right edge of the slide
		if (left + dropdownWidth > slideRect.right) {
			left = slideRect.right - dropdownWidth - 8; // Add small padding from right edge
		}

		// Adjust if dropdown would go off bottom of screen
		if (top + dropdownHeight > window.innerHeight - 10) {
			top = buttonRect.top - dropdownHeight - 8;
		}

		// Apply position
		this.shareMenu.style.left = `${left}px`;
		this.shareMenu.style.top = `${top}px`;

		// Show dropdown
		this.shareMenu.classList.add('active');

		// Animate if GSAP available
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			gsap.fromTo(this.shareMenu,
				{ y: -10, opacity: 0 },
				{ y: 0, opacity: 1, duration: 0.2, ease: "power2.out" }
			);
		}
	}

	hideShareDropdown() {
		if (!this.shareMenu) return;

		// Remove active class from button
		if (this.activeButton) {
			this.activeButton.classList.remove('active');
		}

		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			gsap.to(this.shareMenu, {
				y: -10,
				opacity: 0,
				duration: 0.15,
				ease: "power2.in",
				onComplete: () => {
					this.shareMenu.classList.remove('active');
					this.activeButton = null;
					this.activeItem = null;
				}
			});
		} else {
			this.shareMenu.classList.remove('active');
			this.activeButton = null;
			this.activeItem = null;
		}
	}

	handleShare(type) {
		if (!this.activeItem) return;

		// Get image data
		const img = this.activeItem.querySelector('img');
		const imageUrl = img?.src || '';
		const imageAlt = img?.alt || 'Medical procedure result';
		const slideId = this.activeItem.dataset.slide || '';

		// Build share URL (use current page URL with slide anchor)
		const baseUrl = window.location.origin + window.location.pathname;
		const shareUrl = `${baseUrl}#${slideId}`;
		const shareText = `Check out this ${imageAlt}`;

		switch(type) {
			case 'link':
				this.copyToClipboard(shareUrl);
				break;
			case 'email':
				this.shareViaEmail(shareUrl, shareText);
				break;
			case 'facebook':
				this.shareViaFacebook(shareUrl);
				break;
			case 'twitter':
				this.shareViaTwitter(shareUrl, shareText);
				break;
			case 'pinterest':
				this.shareViaPinterest(shareUrl, imageUrl, shareText);
				break;
			case 'whatsapp':
				this.shareViaWhatsApp(shareUrl, shareText);
				break;
		}

		// Dropdown is already hidden after selection

		// Callback
		this.options.onShare({ type, url: shareUrl, text: shareText });
	}

	copyToClipboard(text) {
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(() => {
				this.showNotification('Link copied to clipboard!');
			}).catch(() => {
				this.fallbackCopyToClipboard(text);
			});
		} else {
			this.fallbackCopyToClipboard(text);
		}
	}

	fallbackCopyToClipboard(text) {
		const textArea = document.createElement('textarea');
		textArea.value = text;
		textArea.style.position = 'fixed';
		textArea.style.left = '-999999px';
		document.body.appendChild(textArea);
		textArea.focus();
		textArea.select();

		try {
			document.execCommand('copy');
			this.showNotification('Link copied to clipboard!');
		} catch (err) {
			this.showNotification('Failed to copy link');
		}

		document.body.removeChild(textArea);
	}

	shareViaEmail(url, text) {
		const subject = encodeURIComponent('Check out this medical procedure result');
		const body = encodeURIComponent(`${text}\n\n${url}`);
		window.location.href = `mailto:?subject=${subject}&body=${body}`;
	}

	shareViaFacebook(url) {
		const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
		this.openShareWindow(shareUrl, 'facebook');
	}

	shareViaTwitter(url, text) {
		const shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
		this.openShareWindow(shareUrl, 'twitter');
	}

	shareViaPinterest(url, imageUrl, text) {
		const shareUrl = `https://pinterest.com/pin/create/button/?url=${encodeURIComponent(url)}&media=${encodeURIComponent(imageUrl)}&description=${encodeURIComponent(text)}`;
		this.openShareWindow(shareUrl, 'pinterest');
	}

	shareViaWhatsApp(url, text) {
		const shareUrl = `https://wa.me/?text=${encodeURIComponent(`${text} ${url}`)}`;
		this.openShareWindow(shareUrl, 'whatsapp');
	}

	openShareWindow(url, platform) {
		const width = 600;
		const height = 400;
		const left = (window.innerWidth - width) / 2;
		const top = (window.innerHeight - height) / 2;

		window.open(
			url,
			`share-${platform}`,
			`width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no,scrollbars=yes,resizable=yes`
		);
	}

	showNotification(message) {
		// Create notification element if it doesn't exist
		let notification = document.getElementById('shareNotification');
		if (!notification) {
			notification = document.createElement('div');
			notification.id = 'shareNotification';
			notification.className = 'brag-book-gallery-share-notification';
			document.body.appendChild(notification);
		}

		// Update message and show
		notification.textContent = message;
		notification.classList.add('active');

		// Animate if GSAP available
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			gsap.fromTo(notification,
				{ y: -20, opacity: 0 },
				{ y: 0, opacity: 1, duration: 0.3, ease: "back.out(1.7)" }
			);
		}

		// Hide after 3 seconds
		setTimeout(() => {
			if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
				gsap.to(notification, {
					y: -20,
					opacity: 0,
					duration: 0.2,
					ease: "power2.in",
					onComplete: () => {
						notification.classList.remove('active');
					}
				});
			} else {
				notification.classList.remove('active');
			}
		}, 3000);
	}

	// Web Share API support (for mobile)
	async shareNative(data) {
		if (navigator.share) {
			try {
				await navigator.share({
					title: data.title || 'Medical Procedure Result',
					text: data.text,
					url: data.url
				});
				return true;
			} catch (err) {
				if (err.name !== 'AbortError') {
					console.error('Share failed:', err);
				}
				return false;
			}
		}
		return false;
	}
}

/**
 * Search Autocomplete Component
 * Provides searchable dropdown for procedures
 */
class SearchAutocomplete {
	constructor(wrapper, options = {}) {
		this.wrapper = wrapper;
		this.input = wrapper.querySelector('.brag-book-gallery-search-input');
		this.dropdown = wrapper.querySelector('.brag-book-gallery-search-dropdown');
		this.searchIcon = wrapper.querySelector('.brag-book-gallery-search-icon');

		this.options = {
			minChars: options.minChars || 1,
			debounceDelay: options.debounceDelay || 200,
			maxResults: options.maxResults || 10,
			onSelect: options.onSelect || (() => {}),
			...options
		};

		this.procedures = [];
		this.filteredResults = [];
		this.selectedIndex = -1;
		this.isOpen = false;
		this.debounceTimer = null;

		if (this.input && this.dropdown) {
			this.init();
		}
	}

	init() {
		this.collectProcedures();
		this.setupEventListeners();
	}

	collectProcedures() {
		// Collect all procedures from the filter system
		const filterLinks = document.querySelectorAll('.brag-book-gallery-filter-link');
		const procedureMap = new Map();

		filterLinks.forEach(link => {
			const procedure = link.dataset.procedure;
			const category = link.dataset.category;
			const label = link.querySelector('.brag-book-gallery-filter-option-label')?.textContent || procedure;
			const count = parseInt(link.dataset.procedureCount || '0');

			if (procedure && category) {
				// Create unique key combining category and procedure
				const key = `${category}:${procedure}`;

				if (!procedureMap.has(key)) {
					procedureMap.set(key, {
						id: procedure,
						name: label.trim(),
						category: category,
						count: count,
						searchText: label.toLowerCase(),
						fullName: `${label} (${count})` // Keep full name with count for reference
					});
				}
			}
		});

		this.procedures = Array.from(procedureMap.values());
	}

	setupEventListeners() {
		// Input events
		this.input.addEventListener('input', (e) => this.handleInput(e));
		this.input.addEventListener('focus', () => this.handleFocus());
		this.input.addEventListener('blur', (e) => this.handleBlur(e));

		// Keyboard navigation
		this.input.addEventListener('keydown', (e) => this.handleKeydown(e));

		// Click outside to close
		document.addEventListener('click', (e) => {
			if (!this.wrapper.contains(e.target)) {
				this.close();
			}
		});

		// Dropdown item clicks
		this.dropdown.addEventListener('click', (e) => {
			const item = e.target.closest('.brag-book-gallery-search-item');
			if (item) {
				this.selectItem(item);
			}
		});
	}

	handleInput(e) {
		const query = e.target.value.trim();

		// Clear previous timer
		if (this.debounceTimer) {
			clearTimeout(this.debounceTimer);
		}

		// Debounce the search
		this.debounceTimer = setTimeout(() => {
			if (query.length >= this.options.minChars) {
				this.search(query);
			} else {
				this.close();
			}
		}, this.options.debounceDelay);
	}

	handleFocus() {
		const query = this.input.value.trim();
		if (query.length >= this.options.minChars) {
			this.search(query);
		}
	}

	handleBlur(e) {
		// Delay close to allow click events on dropdown items
		setTimeout(() => {
			if (!this.wrapper.contains(document.activeElement)) {
				this.close();
			}
		}, 200);
	}

	handleKeydown(e) {
		if (!this.isOpen) {
			if (e.key === 'ArrowDown' && this.input.value.trim().length >= this.options.minChars) {
				e.preventDefault();
				this.search(this.input.value.trim());
			}
			return;
		}

		switch(e.key) {
			case 'ArrowDown':
				e.preventDefault();
				this.moveSelection(1);
				break;
			case 'ArrowUp':
				e.preventDefault();
				this.moveSelection(-1);
				break;
			case 'Enter':
				e.preventDefault();
				if (this.selectedIndex >= 0) {
					const selectedItem = this.dropdown.querySelector(`[data-index="${this.selectedIndex}"]`);
					if (selectedItem) {
						this.selectItem(selectedItem);
					}
				}
				break;
			case 'Escape':
				this.close();
				this.input.blur();
				break;
		}
	}

	search(query) {
		const normalizedQuery = query.toLowerCase();

		// Filter procedures
		this.filteredResults = this.procedures
		                           .filter(proc => {
			                           return proc.searchText.includes(normalizedQuery) ||
			                                  proc.category.includes(normalizedQuery);
		                           })
		                           .slice(0, this.options.maxResults);

		// Sort by relevance (exact match first, then starts with, then contains)
		this.filteredResults.sort((a, b) => {
			const aExact = a.searchText === normalizedQuery;
			const bExact = b.searchText === normalizedQuery;
			if (aExact && !bExact) return -1;
			if (!aExact && bExact) return 1;

			const aStarts = a.searchText.startsWith(normalizedQuery);
			const bStarts = b.searchText.startsWith(normalizedQuery);
			if (aStarts && !bStarts) return -1;
			if (!aStarts && bStarts) return 1;

			return 0;
		});

		this.renderResults(query);
		this.open();
	}

	renderResults(query) {
		if (this.filteredResults.length === 0) {
			this.dropdown.innerHTML = `
                <div class="brag-book-gallery-search-no-results">
                    No procedures found for "${this.escapeHtml(query)}"
                </div>
            `;
			return;
		}

		const html = this.filteredResults.map((proc, index) => {
			const highlightedName = this.highlightMatch(proc.name, query);
			const caseText = proc.count === 1 ? 'case' : 'cases';

			return `
                <div class="brag-book-gallery-search-item"
                     role="option"
                     data-index="${index}"
                     data-procedure="${proc.id}"
                     data-category="${proc.category}"
                     aria-selected="${index === this.selectedIndex}">
                    <div class="brag-book-gallery-search-item-content">
                        <span class="brag-book-gallery-search-item-name">${highlightedName}</span>
                        <span class="brag-book-gallery-search-item-count">${proc.count} ${caseText}</span>
                    </div>
                    <span class="brag-book-gallery-search-item-category">${proc.category}</span>
                </div>
            `;
		}).join('');

		this.dropdown.innerHTML = html;
		this.selectedIndex = -1;
	}

	highlightMatch(text, query) {
		const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		const regex = new RegExp(`(${escapedQuery})`, 'gi');
		return text.replace(regex, '<mark>$1</mark>');
	}

	escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	moveSelection(direction) {
		const items = this.dropdown.querySelectorAll('.brag-book-gallery-search-item');
		if (items.length === 0) return;

		// Update selected index
		this.selectedIndex += direction;

		// Wrap around
		if (this.selectedIndex < 0) {
			this.selectedIndex = items.length - 1;
		} else if (this.selectedIndex >= items.length) {
			this.selectedIndex = 0;
		}

		// Update aria-selected and scroll into view
		items.forEach((item, index) => {
			const isSelected = index === this.selectedIndex;
			item.setAttribute('aria-selected', isSelected);

			if (isSelected) {
				item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
			}
		});
	}

	selectItem(item) {
		const procedure = item.dataset.procedure;
		const category = item.dataset.category;
		const nameElement = item.querySelector('.brag-book-gallery-search-item-name');
		const name = nameElement ? nameElement.textContent.replace(/<mark>/g, '').replace(/<\/mark>/g, '') : '';

		// Update input value
		this.input.value = name;

		// Close dropdown
		this.close();

		// Trigger callback
		this.options.onSelect({ procedure, category, name });

		// Find and click the corresponding filter link (by procedure only)
		const filterLink = document.querySelector(`.brag-book-gallery-filter-link[data-procedure="${procedure}"]`);
		if (filterLink) {
			filterLink.click();
		}
	}

	open() {
		if (this.isOpen) return;

		this.isOpen = true;
		this.dropdown.classList.add('active');
		this.wrapper.classList.add('active');
		this.input.setAttribute('aria-expanded', 'true');
	}

	close() {
		if (!this.isOpen) return;

		this.isOpen = false;
		this.dropdown.classList.remove('active');
		this.wrapper.classList.remove('active');
		this.input.setAttribute('aria-expanded', 'false');
		this.selectedIndex = -1;
	}
}

/**
 * Main Application
 * Orchestrates all components
 */
class BragBookGalleryApp {
	constructor() {
		this.components = {};
		this.init();
	}

	async init() {
		// Initialize components
		this.initializeCarousels();
		this.initializeDialogs();
		this.initializeFilters();
		this.initializeMobileMenu();
		this.initializeFavorites();
		this.initializeSearch();
		this.initializeShareManager();
		this.initializeConsultationForm();
		this.initializeCaseLinks();

		console.log("BragBookGallery initialized");
	}

	initializeCarousels() {
		const carousels = document.querySelectorAll('.brag-book-gallery-carousel-wrapper');
		this.components.carousels = [];

		carousels.forEach((carousel, index) => {
			const options = index === 0 ? {
				// First carousel: enable infinite loop and autoplay
				infinite: true,
				autoplay: true,
				autoplayDelay: 4000,
				pauseOnHover: true
			} : {
				// Second carousel: disable infinite loop and autoplay
				infinite: false,
				autoplay: false
			};

			this.components.carousels.push(new Carousel(carousel, options));
		});

		// Handle window resize for carousels
		let resizeTimer;
		window.addEventListener('resize', () => {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(() => {
				this.components.carousels.forEach(carousel => carousel.refresh());
			}, 250);
		});
	}

	initializeDialogs() {
		this.components.consultationDialog = new Dialog('consultationDialog', {
			backdropId: 'dialogBackdrop',
			onOpen: () => console.log('Consultation dialog opened'),
			onClose: () => console.log('Consultation dialog closed')
		});

		// Setup consultation button clicks
		document.querySelectorAll('[data-action="request-consultation"]').forEach(button => {
			button.addEventListener('click', () => {
				this.components.consultationDialog.open();
			});
		});
	}

	initializeFilters() {
		const filterContainer = document.querySelector('.brag-book-gallery-filters');

		// Determine mode from data attribute or default to 'javascript'
		const mode = filterContainer?.dataset.filterMode || 'javascript';

		this.components.filterSystem = new FilterSystem(filterContainer, {
			mode: mode,
			baseUrl: '/gallery', // Customize as needed
			onFilterChange: (activeFilters) => {
				console.log('Active filters:', Array.from(activeFilters.entries()));
				this.applyFilters(activeFilters);
			},
			onNavigate: (url) => {
				// Custom navigation handler if needed
				console.log('Navigating to:', url);
				window.location.href = url;
			}
		});
	}

	initializeMobileMenu() {
		this.components.mobileMenu = new MobileMenu();
	}

	initializeFavorites() {
		this.components.favoritesManager = new FavoritesManager({
			onUpdate: (favorites) => {
				console.log('Favorites updated:', favorites.size);
			}
		});
	}

	initializeSearch() {
		const searchWrapper = document.querySelector('.brag-book-gallery-search-wrapper');
		if (searchWrapper) {
			this.components.searchAutocomplete = new SearchAutocomplete(searchWrapper, {
				minChars: 1,
				debounceDelay: 200,
				maxResults: 10,
				onSelect: (result) => {
					console.log('Selected procedure:', result);
					// The checkbox is automatically checked by the SearchAutocomplete class
				}
			});
		}

		// Also initialize mobile search
		const mobileSearchWrapper = document.querySelector('.brag-book-gallery-mobile-search-wrapper');
		if (mobileSearchWrapper) {
			// Create a modified search autocomplete for mobile
			const mobileInput = mobileSearchWrapper.querySelector('.brag-book-gallery-mobile-search-input');
			const mobileDropdown = mobileSearchWrapper.querySelector('.brag-book-gallery-mobile-search-dropdown');

			// Update the wrapper to have the correct elements
			if (mobileInput && mobileDropdown) {
				// Temporarily rename the mobile elements to match what SearchAutocomplete expects
				mobileInput.classList.add('brag-book-gallery-search-input');
				mobileDropdown.classList.add('brag-book-gallery-search-dropdown');

				this.components.mobileSearchAutocomplete = new SearchAutocomplete(mobileSearchWrapper, {
					minChars: 1,
					debounceDelay: 200,
					maxResults: 10,
					onSelect: (result) => {
						console.log('Selected procedure (mobile):', result);
						// The checkbox is automatically checked by the SearchAutocomplete class
					}
				});
			}
		}
	}

	initializeShareManager() {
		// Only initialize ShareManager if sharing is enabled
		if (typeof bragBookGalleryConfig !== 'undefined' && 
			bragBookGalleryConfig.enableSharing === 'yes') {
			this.components.shareManager = new ShareManager({
				onShare: (data) => {
					console.log('Shared:', data);
				}
			});
		}
	}

	initializeConsultationForm() {
		const form = document.querySelector('[data-form="consultation"]');
		if (form) {
			form.addEventListener('submit', (e) => {
				e.preventDefault();
				this.handleFormSubmit(e.target);
			});
		}
		
		// Clear messages when dialog is opened
		const consultationDialog = document.getElementById('consultationDialog');
		if (consultationDialog) {
			// Listen for when dialog is shown
			const observer = new MutationObserver((mutations) => {
				mutations.forEach((mutation) => {
					if (mutation.type === 'attributes' && mutation.attributeName === 'open') {
						if (consultationDialog.hasAttribute('open')) {
							// Dialog was opened, clear any previous messages
							this.hideModalMessage();
						}
					}
				});
			});
			
			observer.observe(consultationDialog, { attributes: true });
		}
	}

	initializeCaseLinks() {
		// Add click handlers to case links to load content dynamically
		document.addEventListener('click', (e) => {
			const caseLink = e.target.closest('.brag-book-gallery-case-link');
			if (caseLink) {
				e.preventDefault();
				const caseId = caseLink.dataset.caseId;
				const url = caseLink.href;

				if (caseId) {
					this.loadCaseDetails(caseId, url);
				}
			}
		});

		// Handle browser back/forward navigation
		window.addEventListener('popstate', (e) => {
			if (e.state && e.state.caseId) {
				// Load case details from history state
				this.loadCaseDetails(e.state.caseId, window.location.href, false);
			} else {
				// Reload the page to show gallery
				window.location.reload();
			}
		});
	}

	async loadCaseDetails(caseId, url, updateHistory = true) {
		const galleryContent = document.getElementById('gallery-content');
		if (!galleryContent) {
			console.error('Gallery content container not found');
			return;
		}

		console.log('Loading case details for:', caseId);

		// Show loading state
		galleryContent.innerHTML = '<div class="brag-book-gallery-loading">Loading case details...</div>';

		// Update browser URL without page reload (only if not coming from popstate)
		if (updateHistory && window.history && window.history.pushState) {
			window.history.pushState({ caseId: caseId }, '', url);
		}

		try {
			// Check for config
			if (typeof bragBookGalleryConfig === 'undefined') {
				console.error('bragBookGalleryConfig not defined');
				throw new Error('Configuration not loaded');
			}

			console.log('Making AJAX request to:', bragBookGalleryConfig.ajaxUrl);

			// Make AJAX request to load case details
			const response = await fetch(bragBookGalleryConfig.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'brag_book_gallery_load_case',
					case_id: caseId,
					nonce: bragBookGalleryConfig.nonce || ''
				})
			});

			console.log('Response status:', response.status);

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const data = await response.json();
			console.log('Response data:', data);

			if (data.success && data.data) {
				this.displayCaseDetails(data.data);
			} else {
				console.error('API Error:', data);
				throw new Error(data.data?.message || data.message || 'Failed to load case details');
			}
		} catch (error) {
			console.error('Error loading case details:', error);
			let errorMessage = 'Failed to load case details. Please try again.';

			// If we have a more specific error message, show it
			if (error.message) {
				errorMessage += '<br><small>' + error.message + '</small>';
			}

			galleryContent.innerHTML = '<div class="brag-book-gallery-error">' + errorMessage + '</div>';
		}
	}

	displayCaseDetails(caseData) {
		const galleryContent = document.getElementById('gallery-content');
		if (!galleryContent) return;

		// Build the case details HTML
		let html = '<div class="brag-book-gallery-case-details">';

		// Add back button
		html += '<button class="brag-book-gallery-back-button" onclick="history.back()">← Back to Gallery</button>';

		// Case header
		html += '<div class="brag-book-gallery-case-header">';
		html += `<h2>${caseData.procedureName || 'Case Details'}</h2>`;

		// Case metadata
		html += '<div class="case-metadata">';
		if (caseData.caseNumber) {
			html += `<span class="case-number">Case #${caseData.caseNumber}</span>`;
		}
		if (caseData.technique) {
			html += `<span class="case-technique">Technique: ${caseData.technique}</span>`;
		}
		if (caseData.age) {
			html += `<span class="case-age">Age: ${caseData.age}</span>`;
		}
		if (caseData.gender) {
			html += `<span class="case-gender">Gender: ${caseData.gender}</span>`;
		}
		html += '</div>';
		html += '</div>';

		// Before/After images
		if (caseData.photos && caseData.photos.length > 0) {
			html += '<div class="brag-book-gallery-case-images">';

			caseData.photos.forEach((photo, index) => {
				// Skip if both images are missing
				if (!photo.beforeImage && !photo.afterImage) return;

				html += '<div class="brag-book-gallery-case-image-pair">';

				// For processed images, show single combined image
				if (photo.isProcessed && photo.beforeImage) {
					html += '<div class="processed-image">';
					html += `<img src="${photo.beforeImage}" alt="Before and After" />`;
					if (photo.caption) {
						html += `<p class="image-caption">${photo.caption}</p>`;
					}
					html += '</div>';
				} else {
					// Show separate before/after images
					// Before image
					if (photo.beforeImage) {
						html += '<div class="before-image">';
						html += '<h3>Before</h3>';
						html += `<img src="${photo.beforeImage}" alt="Before" loading="lazy" />`;
						html += '</div>';
					}

					// After image
					if (photo.afterImage) {
						html += '<div class="after-image">';
						html += '<h3>After</h3>';
						html += `<img src="${photo.afterImage}" alt="After" loading="lazy" />`;
						html += '</div>';
					}

					if (photo.caption) {
						html += `<p class="image-caption">${photo.caption}</p>`;
					}
				}

				html += '</div>';
			});

			html += '</div>';
		} else {
			html += '<div class="no-images">No images available for this case.</div>';
		}

		// Case details/description
		if (caseData.description) {
			html += '<div class="brag-book-gallery-case-description">';
			html += '<h3>Details</h3>';
			// If description contains HTML, use it directly
			if (caseData.description.includes('<')) {
				html += caseData.description;
			} else {
				html += `<p>${caseData.description}</p>`;
			}
			html += '</div>';
		}

		html += '</div>';

		// Update the gallery content
		galleryContent.innerHTML = html;

		// Scroll to top of content
		galleryContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	handleSearch(query) {
		const normalizedQuery = query.toLowerCase().trim();
		console.log('Searching for:', normalizedQuery);
		// Search implementation would go here
	}

	applyFilters(activeFilters) {
		// Filter implementation would go here
		console.log('Applying filters...');
	}

	async handleFormSubmit(form) {
		const formData = new FormData(form);
		const data = Object.fromEntries(formData.entries());

		console.log('Form submitted:', data);

		// Get submit button and disable it during submission
		const submitBtn = form.querySelector('.brag-book-gallery-form-submit');
		const originalBtnText = submitBtn ? submitBtn.textContent : '';
		
		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.textContent = 'Sending...';
		}

		try {
			// Check if configuration is available
			if (typeof bragBookGalleryConfig === 'undefined') {
				throw new Error('Configuration not loaded. Please refresh the page.');
			}

			// Prepare the data for the API
			const requestData = new URLSearchParams({
				action: 'handle_form_submission',
				nonce: bragBookGalleryConfig.consultation_nonce || bragBookGalleryConfig.nonce,
				name: data.name || '',
				email: data.email || '',
				phone: data.phone || '',
				description: data.message || '' // Map 'message' field to 'description' for API
			});

			// Send the form data to the WordPress AJAX endpoint
			const response = await fetch(bragBookGalleryConfig.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: requestData,
				credentials: 'same-origin'
			});

			const result = await response.json();

			if (result.success) {
				// Show success message in modal
				this.showModalMessage('Thank you for your consultation request! We will contact you soon.', 'success');
				
				// Reset form and optionally close modal after delay
				setTimeout(() => {
					form.reset();
					// Hide the message
					this.hideModalMessage();
					// Optionally close the modal after showing success
					if (this.components && this.components.consultationDialog) {
						setTimeout(() => {
							this.components.consultationDialog.close();
						}, 1000);
					}
				}, 3000);
			} else {
				// Show error message in modal
				const errorMessage = result.data || 'Failed to send consultation request. Please try again.';
				this.showModalMessage(errorMessage, 'error');
				console.error('Form submission error:', errorMessage);
			}
		} catch (error) {
			console.error('Error submitting form:', error);
			this.showModalMessage(error.message || 'An error occurred. Please try again.', 'error');
		} finally {
			// Re-enable the submit button
			if (submitBtn) {
				submitBtn.disabled = false;
				submitBtn.textContent = originalBtnText;
			}
		}
	}

	// Helper method to show messages in the modal
	showModalMessage(message, type = 'info') {
		const messageContainer = document.getElementById('consultationMessage');
		const messageContent = messageContainer?.querySelector('.brag-book-gallery-form-message-content');
		
		if (!messageContainer || !messageContent) {
			// Fallback to notification if modal elements not found
			this.showNotification(message, type);
			return;
		}
		
		// Set the message text
		messageContent.textContent = message;
		
		// Remove all type classes and add the current type
		messageContainer.className = 'brag-book-gallery-form-message';
		messageContainer.classList.add(`brag-book-gallery-form-message-${type}`);
		
		// Show the message container
		messageContainer.style.display = 'block';
		
		// Scroll to top of modal to ensure message is visible
		const dialogContent = messageContainer.closest('.brag-book-gallery-dialog-content');
		if (dialogContent) {
			dialogContent.scrollTop = 0;
		}
	}
	
	// Helper method to hide modal messages
	hideModalMessage() {
		const messageContainer = document.getElementById('consultationMessage');
		if (messageContainer) {
			messageContainer.style.display = 'none';
			const messageContent = messageContainer.querySelector('.brag-book-gallery-form-message-content');
			if (messageContent) {
				messageContent.textContent = '';
			}
		}
	}
	
	// Helper method to show notifications (fallback)
	showNotification(message, type = 'info') {
		// Check if there's an existing notification container
		let notificationContainer = document.querySelector('.brag-book-notification');
		
		if (!notificationContainer) {
			// Create notification container if it doesn't exist
			notificationContainer = document.createElement('div');
			notificationContainer.className = 'brag-book-notification';
			notificationContainer.style.cssText = `
				position: fixed;
				top: 20px;
				right: 20px;
				padding: 15px 20px;
				border-radius: 4px;
				z-index: 10000;
				transition: opacity 0.3s ease;
				max-width: 350px;
			`;
			document.body.appendChild(notificationContainer);
		}

		// Set styles based on type
		const colors = {
			success: '#4caf50',
			error: '#f44336',
			info: '#2196f3'
		};

		notificationContainer.style.backgroundColor = colors[type] || colors.info;
		notificationContainer.style.color = 'white';
		notificationContainer.textContent = message;
		notificationContainer.style.opacity = '1';

		// Auto-hide after 5 seconds
		setTimeout(() => {
			notificationContainer.style.opacity = '0';
			setTimeout(() => {
				if (notificationContainer.parentNode) {
					notificationContainer.parentNode.removeChild(notificationContainer);
				}
			}, 300);
		}, 5000);
	}
}

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

// Global function for grid layout updates
window.updateGridLayout = function(columns) {
	const grid = document.querySelector('.brag-book-gallery-cases-grid');
	if (!grid) return;

	// Update CSS Grid columns via data attribute and style
	grid.setAttribute('data-columns', columns);
	grid.style.gridTemplateColumns = `repeat(${columns}, 1fr)`;

	// Update active state on buttons
	const buttons = document.querySelectorAll('.brag-book-gallery-grid-btn');
	buttons.forEach(btn => {
		const btnCols = parseInt(btn.dataset.columns);
		if (btnCols === columns) {
			btn.classList.add('active');
		} else {
			btn.classList.remove('active');
		}
	});

	// Save preference to localStorage
	localStorage.setItem('bragbook-grid-columns', columns);
};

// Global procedure filter state
window.bragBookProcedureFilters = {
	age: [],
	gender: [],
	ethnicity: [],
	height: [],
	weight: []
};

// Initialize procedure filters on page load or after AJAX
window.initializeProcedureFilters = function() {
	const details = document.getElementById('procedure-filters-details');
	console.log('Initializing procedure filters, details element:', details);
	console.log('Complete dataset available:', window.bragBookGalleryConfig?.completeDataset?.length || 0, 'cases');
	if (details && !details.dataset.initialized) {
		// Generate filter options which will handle showing/hiding
		generateProcedureFilterOptions();
		details.dataset.initialized = 'true';
	}
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
	// Initialize filters after a brief delay to ensure DOM is ready
	setTimeout(function() {
		// Ensure the complete dataset is available
		if (!window.bragBookCompleteDataset && window.bragBookGalleryConfig && window.bragBookGalleryConfig.completeDataset) {
			window.bragBookCompleteDataset = window.bragBookGalleryConfig.completeDataset;
			console.log('Initialized bragBookCompleteDataset with', window.bragBookCompleteDataset.length, 'cases');
		}
		initializeProcedureFilters();
		
		// Check if we need to load a case on initial page load
		const wrapper = document.querySelector('.brag-book-gallery-wrapper');
		if (wrapper && wrapper.dataset.initialCaseId) {
			const caseId = wrapper.dataset.initialCaseId;
			const procedureSlug = window.location.pathname.split('/').filter(s => s)[1] || '';
			console.log('Auto-loading case on page load:', caseId, 'for procedure:', procedureSlug);
			window.loadCaseDetails(caseId, '', procedureSlug);
		}
	}, 100);
});

// Handle details toggle event
document.addEventListener('toggle', function(e) {
	if (e.target.id === 'procedure-filters-details') {
		const details = e.target;
		if (details.open && !details.dataset.initialized) {
			generateProcedureFilterOptions();
			details.dataset.initialized = 'true';
		}
	}
});

// Close details when clicking outside
document.addEventListener('click', function(e) {
	const details = document.getElementById('procedure-filters-details');
	const panel = document.querySelector('.brag-book-gallery-procedure-filters-panel');

	if (details && details.open && panel) {
		if (!details.contains(e.target) && !panel.contains(e.target)) {
			details.open = false;
		}
	}
});

// Generate procedure filter options based on available data
window.generateProcedureFilterOptions = function() {
	const container = document.getElementById('brag-book-gallery-procedure-filters-options');
	if (!container) {
		console.warn('Filter container not found');
		return;
	}

	// Initialize the complete dataset from config if not already set
	if (!window.bragBookCompleteDataset && window.bragBookGalleryConfig && window.bragBookGalleryConfig.completeDataset) {
		window.bragBookCompleteDataset = window.bragBookGalleryConfig.completeDataset;
		console.log('Initialized complete dataset with', window.bragBookCompleteDataset.length, 'cases');
	} else if (!window.bragBookCompleteDataset) {
		console.warn('Complete dataset not available from config');
		console.log('Config object:', window.bragBookGalleryConfig);
	}

	// First try to use the complete dataset if available
	let cards;
	if (window.bragBookCompleteDataset && window.bragBookCompleteDataset.length > 0) {
		console.log('Using complete dataset for filters:', window.bragBookCompleteDataset.length, 'cases');
		// Use the complete dataset for filter generation
		const filterData = {
			age: new Set(),
			gender: new Set(),
			ethnicity: new Set(),
			height: new Set(),
			weight: new Set()
		};

		// Process the complete dataset
		window.bragBookCompleteDataset.forEach(caseData => {
			// Handle both data structures: mapped (age) and raw API (patientAge)
			const age = caseData.age || caseData.patientAge;
			const gender = caseData.gender || caseData.patientGender;
			const ethnicity = caseData.ethnicity || caseData.patientEthnicity;
			const height = caseData.height || caseData.patientHeight;
			const weight = caseData.weight || caseData.patientWeight;
			
			// Debug log to see what data we're working with
			if (console && console.log) {
				console.log('Processing case:', {
					id: caseData.id,
					age: age,
					gender: gender,
					ethnicity: ethnicity,
					height: height,
					weight: weight
				});
			}

			// Age ranges
			if (age) {
				const ageNum = parseInt(age);
				if (ageNum < 25) filterData.age.add('18-24');
				else if (ageNum < 35) filterData.age.add('25-34');
				else if (ageNum < 45) filterData.age.add('35-44');
				else if (ageNum < 55) filterData.age.add('45-54');
				else if (ageNum < 65) filterData.age.add('55-64');
				else filterData.age.add('65+');
			}

			// Gender
			if (gender) filterData.gender.add(gender);

			// Ethnicity
			if (ethnicity) filterData.ethnicity.add(ethnicity);

			// Height ranges
			if (height) {
				const heightNum = parseInt(height);
				if (heightNum < 60) filterData.height.add('Under 5\'0"');
				else if (heightNum < 64) filterData.height.add('5\'0" - 5\'3"');
				else if (heightNum < 68) filterData.height.add('5\'4" - 5\'7"');
				else if (heightNum < 72) filterData.height.add('5\'8" - 5\'11"');
				else filterData.height.add('6\'0" and above');
			}

			// Weight ranges
			if (weight) {
				const weightNum = parseInt(weight);
				if (weightNum < 120) filterData.weight.add('Under 120 lbs');
				else if (weightNum < 150) filterData.weight.add('120-149 lbs');
				else if (weightNum < 180) filterData.weight.add('150-179 lbs');
				else if (weightNum < 210) filterData.weight.add('180-209 lbs');
				else filterData.weight.add('210+ lbs');
			}
		});

		// Debug the filter data before generating HTML
		console.log('Filter data generated:', {
			age: Array.from(filterData.age),
			gender: Array.from(filterData.gender),
			ethnicity: Array.from(filterData.ethnicity),
			height: Array.from(filterData.height),
			weight: Array.from(filterData.weight)
		});

		// Now generate the filter HTML using the complete dataset
		generateFilterHTML(container, filterData);
		return;
	}

	// Fallback to reading from visible cards if no complete dataset
	console.log('Falling back to DOM cards for filters');
	cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
	console.log('Found', cards.length, 'cards in DOM');
	const filterData = {
		age: new Set(),
		gender: new Set(),
		ethnicity: new Set(),
		height: new Set(),
		weight: new Set()
	};

	// Collect unique values from all cards
	cards.forEach(card => {
		// Age ranges
		const age = card.dataset.age;
		if (age) {
			const ageNum = parseInt(age);
			if (ageNum < 30) filterData.age.add('Under 30');
			else if (ageNum < 40) filterData.age.add('30-39');
			else if (ageNum < 50) filterData.age.add('40-49');
			else if (ageNum < 60) filterData.age.add('50-59');
			else filterData.age.add('60+');
		}

		// Gender
		if (card.dataset.gender) {
			filterData.gender.add(card.dataset.gender);
		}

		// Ethnicity
		if (card.dataset.ethnicity) {
			filterData.ethnicity.add(card.dataset.ethnicity);
		}

		// Height ranges (assuming cm)
		const height = card.dataset.height;
		if (height) {
			const heightNum = parseInt(height);
			const unit = card.dataset.heightUnit || 'cm';
			if (unit === 'cm') {
				if (heightNum < 160) filterData.height.add('Under 160cm');
				else if (heightNum < 170) filterData.height.add('160-169cm');
				else if (heightNum < 180) filterData.height.add('170-179cm');
				else filterData.height.add('180cm+');
			} else {
				// Handle feet/inches if needed
				filterData.height.add(card.dataset.heightFull);
			}
		}

		// Weight ranges (assuming lbs)
		const weight = card.dataset.weight;
		if (weight) {
			const weightNum = parseInt(weight);
			const unit = card.dataset.weightUnit || 'lbs';
			if (unit === 'lbs' || unit === 'lb') {
				if (weightNum < 120) filterData.weight.add('Under 120 lbs');
				else if (weightNum < 150) filterData.weight.add('120-149 lbs');
				else if (weightNum < 180) filterData.weight.add('150-179 lbs');
				else if (weightNum < 210) filterData.weight.add('180-209 lbs');
				else filterData.weight.add('210+ lbs');
			} else if (unit === 'kg') {
				if (weightNum < 55) filterData.weight.add('Under 55kg');
				else if (weightNum < 70) filterData.weight.add('55-69kg');
				else if (weightNum < 85) filterData.weight.add('70-84kg');
				else filterData.weight.add('85kg+');
			} else {
				filterData.weight.add(card.dataset.weightFull);
			}
		}
	});

	// Generate the filter HTML
	generateFilterHTML(container, filterData);
};

// Separate function to generate filter HTML from filter data
window.generateFilterHTML = function(container, filterData) {
	console.log('generateFilterHTML called with:', filterData);

	// Build filter HTML
	let html = '';

	// Age filter
	if (filterData.age.size > 0) {
		html += '<details class="brag-book-gallery-procedure-filters-group" open>';
		html += '<summary class="brag-book-gallery-procedure-filters-group-label">';
		html += '<svg class="brag-book-gallery-procedure-filters-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
		html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
		html += '</svg>';
		html += '<span>Age</span>';
		html += '</summary>';
		html += '<ul class="brag-book-gallery-procedure-filters-group-content">';
		Array.from(filterData.age).sort().forEach(value => {
			const id = `procedure-filter-age-${value.replace(/\s+/g, '-')}`;
			html += `<li class="brag-book-gallery-procedure-filters-option">
				<input type="checkbox" id="${id}" value="${value}" data-filter-type="age">
				<label for="${id}">${value}</label>
			</li>`;
		});
		html += '</ul>';
		html += '</details>';
	}

	// Gender filter
	if (filterData.gender.size > 0) {
		html += '<details class="brag-book-gallery-procedure-filters-group" open>';
		html += '<summary class="brag-book-gallery-procedure-filters-group-label">';
		html += '<svg class="brag-book-gallery-procedure-filters-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
		html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
		html += '</svg>';
		html += '<span>Gender</span>';
		html += '</summary>';
		html += '<ul class="brag-book-gallery-procedure-filters-group-content">';
		Array.from(filterData.gender).sort().forEach(value => {
			const id = `procedure-filter-gender-${value}`;
			const displayValue = value.charAt(0).toUpperCase() + value.slice(1);
			html += `<li class="brag-book-gallery-procedure-filters-option">
				<input type="checkbox" id="${id}" value="${value}" data-filter-type="gender">
				<label for="${id}">${displayValue}</label>
			</li>`;
		});
		html += '</ul>';
		html += '</details>';
	}

	// Ethnicity filter
	if (filterData.ethnicity.size > 0) {
		html += '<details class="brag-book-gallery-procedure-filters-group" open>';
		html += '<summary class="brag-book-gallery-procedure-filters-group-label">';
		html += '<svg class="brag-book-gallery-procedure-filters-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
		html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
		html += '</svg>';
		html += '<span>Ethnicity</span>';
		html += '</summary>';
		html += '<ul class="brag-book-gallery-procedure-filters-group-content">';
		Array.from(filterData.ethnicity).sort().forEach(value => {
			const id = `procedure-filter-ethnicity-${value.replace(/\s+/g, '-')}`;
			const displayValue = value.charAt(0).toUpperCase() + value.slice(1);
			html += `<li class="brag-book-gallery-procedure-filters-option">
				<input type="checkbox" id="${id}" value="${value}" data-filter-type="ethnicity">
				<label for="${id}">${displayValue}</label>
			</li>`;
		});
		html += '</ul>';
		html += '</details>';
	}

	// Height filter
	if (filterData.height.size > 0) {
		html += '<details class="brag-book-gallery-procedure-filters-group" open>';
		html += '<summary class="brag-book-gallery-procedure-filters-group-label">';
		html += '<svg class="brag-book-gallery-procedure-filters-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
		html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
		html += '</svg>';
		html += '<span>Height</span>';
		html += '</summary>';
		html += '<ul class="brag-book-gallery-procedure-filters-group-content">';
		Array.from(filterData.height).sort().forEach(value => {
			const id = `procedure-filter-height-${value.replace(/\s+/g, '-')}`;
			html += `<li class="brag-book-gallery-procedure-filters-option">
				<input type="checkbox" id="${id}" value="${value}" data-filter-type="height">
				<label for="${id}">${value}</label>
			</li>`;
		});
		html += '</ul>';
		html += '</details>';
	}

	// Weight filter
	if (filterData.weight.size > 0) {
		html += '<details class="brag-book-gallery-procedure-filters-group" open>';
		html += '<summary class="brag-book-gallery-procedure-filters-group-label">';
		html += '<svg class="brag-book-gallery-procedure-filters-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
		html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
		html += '</svg>';
		html += '<span>Weight</span>';
		html += '</summary>';
		html += '<ul class="brag-book-gallery-procedure-filters-group-content">';
		Array.from(filterData.weight).sort().forEach(value => {
			const id = `procedure-filter-weight-${value.replace(/\s+/g, '-')}`;
			html += `<li class="brag-book-gallery-procedure-filters-option">
				<input type="checkbox" id="${id}" value="${value}" data-filter-type="weight">
				<label for="${id}">${value}</label>
			</li>`;
		});
		html += '</ul>';
		html += '</details>';
	}

	console.log('Generated HTML length:', html.length);
	console.log('HTML preview:', html.substring(0, 200) + (html.length > 200 ? '...' : ''));

	container.innerHTML = html || '<p>No filters available</p>';

	// Add event listeners to all checkboxes
	const filterCheckboxes = container.querySelectorAll('input[type="checkbox"]');
	filterCheckboxes.forEach(checkbox => {
		checkbox.addEventListener('change', function() {
			console.log('Filter changed:', this.dataset.filterType, '=', this.value, 'checked:', this.checked);
			applyProcedureFilters();
		});
	});

	// Show/hide the details element based on whether filters exist
	const details = document.getElementById('procedure-filters-details');
	if (details) {
		const hasFilters = html && html !== '<p>No filters available</p>';
		console.log('Has filters:', hasFilters);
		if (hasFilters) {
			details.style.display = '';
		} else {
			details.style.display = 'none';
		}
	}
};

// Apply procedure filters to cards
window.applyProcedureFilters = function() {
	console.log('Applying procedure filters...');
	const checkboxes = document.querySelectorAll('.brag-book-gallery-procedure-filters-option input:checked');
	console.log('Checked filters:', checkboxes.length);

	// Reset filter state
	window.bragBookProcedureFilters = {
		age: [],
		gender: [],
		ethnicity: [],
		height: [],
		weight: []
	};

	// Collect selected filters
	checkboxes.forEach(checkbox => {
		const filterType = checkbox.dataset.filterType;
		const value = checkbox.value;
		console.log('Filter:', filterType, '=', value);
		if (window.bragBookProcedureFilters[filterType]) {
			window.bragBookProcedureFilters[filterType].push(value);
		}
	});
	
	console.log('Active filters:', window.bragBookProcedureFilters);

	// Check if any filters are selected
	const hasActiveFilters = Object.values(window.bragBookProcedureFilters).some(arr => arr.length > 0);

	if (!hasActiveFilters) {
		// No filters selected, show all currently loaded cards
		const cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
		cards.forEach(card => {
			card.style.display = '';
		});

		// Update count
		updateFilteredCount(cards.length, cards.length);
		return;
	}

	// If we have the complete dataset, we need to filter and potentially load missing cards
	if (window.bragBookCompleteDataset && window.bragBookCompleteDataset.length > 0) {
		// Find all matching cases from the complete dataset
		const matchingCaseIds = [];

		window.bragBookCompleteDataset.forEach(caseData => {
			let matches = true;

			// Handle both data structures: mapped (age) and raw API (patientAge)
			const age = caseData.age || caseData.patientAge;
			const gender = caseData.gender || caseData.patientGender;
			const ethnicity = caseData.ethnicity || caseData.patientEthnicity;
			const height = caseData.height || caseData.patientHeight;
			const weight = caseData.weight || caseData.patientWeight;

			// Check age filter
			if (window.bragBookProcedureFilters.age.length > 0) {
				const ageNum = parseInt(age);
				let ageMatch = false;
				window.bragBookProcedureFilters.age.forEach(range => {
					if (range === '18-24' && ageNum >= 18 && ageNum <= 24) ageMatch = true;
					else if (range === '25-34' && ageNum >= 25 && ageNum <= 34) ageMatch = true;
					else if (range === '35-44' && ageNum >= 35 && ageNum <= 44) ageMatch = true;
					else if (range === '45-54' && ageNum >= 45 && ageNum <= 54) ageMatch = true;
					else if (range === '55-64' && ageNum >= 55 && ageNum <= 64) ageMatch = true;
					else if (range === '65+' && ageNum >= 65) ageMatch = true;
				});
				if (!ageMatch) matches = false;
			}

			// Check gender filter
			if (matches && window.bragBookProcedureFilters.gender.length > 0) {
				if (!window.bragBookProcedureFilters.gender.includes(gender)) {
					matches = false;
				}
			}

			// Check ethnicity filter
			if (matches && window.bragBookProcedureFilters.ethnicity.length > 0) {
				if (!window.bragBookProcedureFilters.ethnicity.includes(ethnicity)) {
					matches = false;
				}
			}

			// Check height filter
			if (matches && window.bragBookProcedureFilters.height.length > 0) {
				const heightNum = parseInt(height);
				let heightMatch = false;
				window.bragBookProcedureFilters.height.forEach(range => {
					if (range === 'Under 5\'0"' && heightNum < 60) heightMatch = true;
					else if (range === '5\'0" - 5\'3"' && heightNum >= 60 && heightNum < 64) heightMatch = true;
					else if (range === '5\'4" - 5\'7"' && heightNum >= 64 && heightNum < 68) heightMatch = true;
					else if (range === '5\'8" - 5\'11"' && heightNum >= 68 && heightNum < 72) heightMatch = true;
					else if (range === '6\'0" and above' && heightNum >= 72) heightMatch = true;
				});
				if (!heightMatch) matches = false;
			}

			// Check weight filter
			if (matches && window.bragBookProcedureFilters.weight.length > 0) {
				const weightNum = parseInt(weight);
				let weightMatch = false;
				window.bragBookProcedureFilters.weight.forEach(range => {
					if (range === 'Under 120 lbs' && weightNum < 120) weightMatch = true;
					else if (range === '120-149 lbs' && weightNum >= 120 && weightNum < 150) weightMatch = true;
					else if (range === '150-179 lbs' && weightNum >= 150 && weightNum < 180) weightMatch = true;
					else if (range === '180-209 lbs' && weightNum >= 180 && weightNum < 210) weightMatch = true;
					else if (range === '210+ lbs' && weightNum >= 210) weightMatch = true;
				});
				if (!weightMatch) matches = false;
			}

			if (matches) {
				matchingCaseIds.push(caseData.id);
			}
		});

		// Now we need to load ALL matching cases if they're not already visible
		loadFilteredCases(matchingCaseIds);

	} else {
		// Fallback: just filter visible cards if no complete dataset
		// Try multiple selectors to find the cards
		let cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
		if (cards.length === 0) {
			cards = document.querySelectorAll('.brag-book-case-card[data-card="true"]');
		}
		if (cards.length === 0) {
			cards = document.querySelectorAll('.brag-book-gallery-case-card, .brag-book-case-card');
		}
		
		console.log('Found cards for filtering:', cards.length);
		
		let visibleCount = 0;
		cards.forEach((card, index) => {
			let show = true;
			
			// Debug card data
			if (index === 0) {
				console.log('First card data attributes:', {
					age: card.dataset.age,
					gender: card.dataset.gender,
					ethnicity: card.dataset.ethnicity,
					height: card.dataset.height,
					weight: card.dataset.weight,
					card: card.dataset.card
				});
			}

			// Check age filter
			if (window.bragBookProcedureFilters.age.length > 0) {
				const cardAge = parseInt(card.dataset.age);
				let ageMatch = false;
				console.log('Age check for card:', cardAge, 'against filters:', window.bragBookProcedureFilters.age);
				window.bragBookProcedureFilters.age.forEach(range => {
					if (range === '18-24' && cardAge >= 18 && cardAge < 25) ageMatch = true;
					else if (range === '25-34' && cardAge >= 25 && cardAge < 35) ageMatch = true;
					else if (range === '35-44' && cardAge >= 35 && cardAge < 45) ageMatch = true;
					else if (range === '45-54' && cardAge >= 45 && cardAge < 55) ageMatch = true;
					else if (range === '55-64' && cardAge >= 55 && cardAge < 65) ageMatch = true;
					else if (range === '65+' && cardAge >= 65) ageMatch = true;
				});
				if (!ageMatch) {
					show = false;
					console.log('Age filter failed for card with age:', cardAge);
				}
			}

			// Check gender filter
			if (show && window.bragBookProcedureFilters.gender.length > 0) {
				const cardGender = (card.dataset.gender || '').toLowerCase();
				const filterGenders = window.bragBookProcedureFilters.gender.map(g => g.toLowerCase());
				
				console.log('Gender check:', {
					cardGender: cardGender,
					filterGenders: filterGenders,
					matches: filterGenders.includes(cardGender)
				});
				
				if (!filterGenders.includes(cardGender)) {
					show = false;
				}
			}

			// Check ethnicity filter
			if (show && window.bragBookProcedureFilters.ethnicity.length > 0) {
				const cardEthnicity = (card.dataset.ethnicity || '').toLowerCase();
				const filterEthnicities = window.bragBookProcedureFilters.ethnicity.map(e => e.toLowerCase());
				
				console.log('Ethnicity check:', {
					cardEthnicity: cardEthnicity,
					filterEthnicities: filterEthnicities,
					matches: filterEthnicities.includes(cardEthnicity)
				});
				
				if (!filterEthnicities.includes(cardEthnicity)) {
					show = false;
				}
			}

			// Check height filter
			if (show && window.bragBookProcedureFilters.height.length > 0) {
				const cardHeight = parseInt(card.dataset.height);
				const unit = card.dataset.heightUnit || 'cm';
				let heightMatch = false;

				window.bragBookProcedureFilters.height.forEach(range => {
					if (unit === 'cm') {
						if (range === 'Under 160cm' && cardHeight < 160) heightMatch = true;
						else if (range === '160-169cm' && cardHeight >= 160 && cardHeight < 170) heightMatch = true;
						else if (range === '170-179cm' && cardHeight >= 170 && cardHeight < 180) heightMatch = true;
						else if (range === '180cm+' && cardHeight >= 180) heightMatch = true;
					} else {
						if (range === card.dataset.heightFull) heightMatch = true;
					}
				});
				if (!heightMatch) show = false;
			}

			// Check weight filter
			if (show && window.bragBookProcedureFilters.weight.length > 0) {
				const cardWeight = parseInt(card.dataset.weight);
				const unit = card.dataset.weightUnit || 'lbs';
				let weightMatch = false;

				window.bragBookProcedureFilters.weight.forEach(range => {
					if (unit === 'lbs' || unit === 'lb') {
						if (range === 'Under 120 lbs' && cardWeight < 120) weightMatch = true;
						else if (range === '120-149 lbs' && cardWeight >= 120 && cardWeight < 150) weightMatch = true;
						else if (range === '150-179 lbs' && cardWeight >= 150 && cardWeight < 180) weightMatch = true;
						else if (range === '180-209 lbs' && cardWeight >= 180 && cardWeight < 210) weightMatch = true;
						else if (range === '210+ lbs' && cardWeight >= 210) weightMatch = true;
					} else if (unit === 'kg') {
						if (range === 'Under 55kg' && cardWeight < 55) weightMatch = true;
						else if (range === '55-69kg' && cardWeight >= 55 && cardWeight < 70) weightMatch = true;
						else if (range === '70-84kg' && cardWeight >= 70 && cardWeight < 85) weightMatch = true;
						else if (range === '85kg+' && cardWeight >= 85) weightMatch = true;
					} else {
						if (range === card.dataset.weightFull) weightMatch = true;
					}
				});
				if (!weightMatch) show = false;
			}

			// Show/hide card
			card.style.display = show ? '' : 'none';
			if (show) visibleCount++;
		});

		// Update results count
		const hasActiveFilters = checkboxes.length > 0;
		const resultsMessage = visibleCount === 0 ? 'No procedures match the selected filters' :
			`Showing ${visibleCount} of ${cards.length} procedures`;

		// Add/update results message if needed
		let resultsEl = document.querySelector('.brag-book-gallery-procedure-filters-results');
		if (!resultsEl) {
			resultsEl = document.createElement('div');
			resultsEl.className = 'brag-book-gallery-procedure-filters-results';
			const grid = document.querySelector('.brag-book-gallery-cases-grid') || 
			            document.querySelector('.brag-book-cases-grid');
			if (grid && grid.parentNode) {
				grid.parentNode.insertBefore(resultsEl, grid);
			}
		}
		resultsEl.textContent = resultsMessage;
		resultsEl.style.display = hasActiveFilters ? 'block' : 'none';

		// Close the details after applying filters
		const details = document.getElementById('procedure-filters-details');
		if (details) {
			details.open = false;
			// Add visual indicator if filters are active
			const toggle = details.querySelector('.brag-book-gallery-procedure-filters-toggle');
			if (toggle) {
				toggle.classList.toggle('has-active-filters', hasActiveFilters);
			}
		}
	}
};

// Helper function to load filtered cases from the complete dataset
window.loadFilteredCases = function(matchingCaseIds) {
	// First, hide all current cards
	const allCards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
	allCards.forEach(card => {
		card.style.display = 'none';
	});

	// Show matching cards that are already loaded
	let visibleCount = 0;
	let needToLoad = [];

	matchingCaseIds.forEach(caseId => {
		const existingCard = document.querySelector(`.brag-book-gallery-case-card[data-case-id="${caseId}"]`);
		if (existingCard) {
			existingCard.style.display = '';
			visibleCount++;
		} else {
			// This case needs to be loaded
			needToLoad.push(caseId);
		}
	});

	// If we need to load more cases, make an AJAX request
	if (needToLoad.length > 0) {
		// Show loading indicator
		const container = document.querySelector('.brag-book-gallery-cases-grid') ||
		                  document.querySelector('.brag-book-cases-grid');

		if (container) {
			// Add loading message
			const loadingMsg = document.createElement('div');
			loadingMsg.className = 'filter-loading-message';
			loadingMsg.textContent = `Loading ${needToLoad.length} additional matching cases...`;
			loadingMsg.style.cssText = 'padding: 20px; text-align: center; font-style: italic;';
			container.appendChild(loadingMsg);

			// Make AJAX request to load the specific cases
			const formData = new FormData();
			formData.append('action', 'brag_book_load_filtered_cases');
			formData.append('case_ids', needToLoad.join(','));
			formData.append('nonce', typeof bragBookAjax !== 'undefined' ? bragBookAjax.nonce : '');

			fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData
			})
				.then(response => response.json())
				.then(data => {
					// Remove loading message
					const loadingMsg = container.querySelector('.filter-loading-message');
					if (loadingMsg) loadingMsg.remove();

					if (data.success && data.data.html) {
						// Add the new cards to the container
						const tempDiv = document.createElement('div');
						tempDiv.innerHTML = data.data.html;

						// Append each new card
						const newCards = tempDiv.querySelectorAll('.brag-book-gallery-case-card');
						newCards.forEach(card => {
							container.appendChild(card);
							visibleCount++;
						});
					}

					// Update the count
					updateFilteredCount(visibleCount, window.bragBookCompleteDataset.length);
				})
				.catch(error => {
					console.error('Error loading filtered cases:', error);
					const loadingMsg = container.querySelector('.filter-loading-message');
					if (loadingMsg) {
						loadingMsg.textContent = 'Failed to load additional cases. Showing only currently loaded matches.';
						setTimeout(() => loadingMsg.remove(), 3000);
					}
				});
		}
	} else {
		// All matching cases are already loaded, just update the count
		updateFilteredCount(visibleCount, window.bragBookCompleteDataset ? window.bragBookCompleteDataset.length : allCards.length);
	}
};

// Helper function to update the filtered count display
window.updateFilteredCount = function(shown, total) {
	// Update the count label
	const countLabel = document.querySelector('.brag-book-gallery-count-label') ||
	                   document.querySelector('.cases-count');
	if (countLabel) {
		countLabel.textContent = `Showing ${shown} of ${total}`;
	}

	// Also update or add a filter results message
	let resultsEl = document.querySelector('.brag-book-gallery-procedure-filters-results');
	if (!resultsEl) {
		resultsEl = document.createElement('div');
		resultsEl.className = 'brag-book-gallery-procedure-filters-results';
		resultsEl.style.cssText = 'padding: 10px; background: #f0f0f0; margin: 10px 0; border-radius: 4px;';
		const grid = document.querySelector('.brag-book-gallery-cases-grid') ||
		             document.querySelector('.brag-book-cases-grid');
		if (grid && grid.parentNode) {
			grid.parentNode.insertBefore(resultsEl, grid);
		}
	}

	if (shown === 0) {
		resultsEl.textContent = 'No procedures match the selected filters';
		resultsEl.style.display = 'block';
	} else if (window.bragBookProcedureFilters && Object.values(window.bragBookProcedureFilters).some(arr => arr.length > 0)) {
		resultsEl.textContent = `Filter applied: Showing ${shown} matching procedures`;
		resultsEl.style.display = 'block';
	} else {
		resultsEl.style.display = 'none';
	}
};

// Clear all procedure filters
window.clearProcedureFilters = function() {
	const checkboxes = document.querySelectorAll('.brag-book-gallery-procedure-filters-option input');
	checkboxes.forEach(checkbox => {
		checkbox.checked = false;
	});

	// Show all cards
	const cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
	cards.forEach(card => {
		card.style.display = '';
	});

	// Hide results message
	const resultsEl = document.querySelector('.brag-book-gallery-procedure-filters-results');
	if (resultsEl) {
		resultsEl.style.display = 'none';
	}

	// Reset filter state
	window.bragBookProcedureFilters = {
		age: [],
		gender: [],
		ethnicity: [],
		height: [],
		weight: []
	};

	// Update toggle button state
	const toggle = document.querySelector('.brag-book-gallery-procedure-filters-toggle');
	if (toggle) {
		toggle.classList.remove('has-active-filters');
	}
};

// Global function to sync image heights within a case
window.syncImageHeights = function(img) {
	// Hide skeleton loader and show image
	img.style.opacity = '1';
	const loader = img.parentElement.querySelector('.brag-book-gallery-skeleton-loader');
	if (loader) loader.style.display = 'none';

	// Get the parent case images container
	const caseContainer = img.closest('.brag-book-gallery-case-images');
	if (!caseContainer) return;

	// Get both images in this case
	const images = caseContainer.querySelectorAll('img');

	// Check if both images are loaded
	let allLoaded = true;
	images.forEach(image => {
		if (!image.complete || !image.naturalHeight) {
			allLoaded = false;
		}
	});

	if (!allLoaded) return; // Wait for both images to load

	// Find the maximum aspect ratio (height/width)
	let maxAspectRatio = 0;
	images.forEach(image => {
		const aspectRatio = image.naturalHeight / image.naturalWidth;
		if (aspectRatio > maxAspectRatio) {
			maxAspectRatio = aspectRatio;
		}
	});

	// Set the padding-bottom based on the tallest image's aspect ratio
	const imageContainers = caseContainer.querySelectorAll('.brag-book-gallery-image-container');
	imageContainers.forEach(container => {
		container.style.paddingBottom = (maxAspectRatio * 100) + '%';
	});
};

// Load case details into gallery content
window.loadCaseDetails = function(caseId, procedureId, procedureSlug) {
	const galleryContent = document.getElementById('gallery-content');
	if (!galleryContent) {
		console.error('Gallery content container not found');
		return;
	}

	// Show loading state
	galleryContent.innerHTML = '<div class="brag-book-gallery-loading">Loading case details...</div>';

	// Update URL to reflect the case being viewed
	if (procedureSlug && window.history && window.history.pushState) {
		// Get the base gallery URL
		const galleryWrapper = document.querySelector('.brag-book-gallery-wrapper');
		let basePath = galleryWrapper?.dataset.baseUrl || window.location.pathname;

		// Clean up the base path - remove any existing procedure/case segments
		basePath = basePath.replace(/\/[^\/]+\/\d+\/?$/, ''); // Remove /procedure/123
		basePath = basePath.replace(/\/[^\/]+\/?$/, ''); // Remove /procedure if present
		basePath = basePath.replace(/\/$/, ''); // Remove trailing slash

		// If we still don't have a proper base, use the first segment
		if (!basePath || basePath === '') {
			const pathSegments = window.location.pathname.split('/').filter(s => s);
			basePath = pathSegments.length > 0 ? '/' + pathSegments[0] : '';
		}

		// Build the new URL: /gallery-page/procedure-slug/case-id
		const newUrl = `${basePath}/${procedureSlug}/${caseId}`;

		// Update browser URL without page reload
		window.history.pushState(
			{ caseId: caseId, procedureId: procedureId, procedureSlug: procedureSlug },
			'',
			newUrl
		);
	}

	// Scroll to top of gallery content
	galleryContent.scrollIntoView({ behavior: 'smooth' });

	// Prepare request parameters
	const requestParams = {
		action: 'load_case_details',
		case_id: caseId,
		nonce: bragBookGalleryConfig.nonce
	};

	// Add procedure ID if provided
	if (procedureId) {
		requestParams.procedure_id = procedureId;
	}

	// Make AJAX request to load case details
	fetch(bragBookGalleryConfig.ajaxUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: new URLSearchParams(requestParams)
	})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				galleryContent.innerHTML = data.data.html;
			} else {
				galleryContent.innerHTML = '<div class="brag-book-gallery-error">Failed to load case details: ' + (data.data || 'Unknown error') + '</div>';
			}
		})
		.catch(error => {
			console.error('Error loading case details:', error);
			galleryContent.innerHTML = '<div class="brag-book-gallery-error">Error loading case details. Please try again.</div>';
		});
};

/**
 * Load more cases via AJAX
 * @param {HTMLElement} button - The Load More button element
 */
window.loadMoreCases = function(button) {
	// Disable button and show loading state
	button.disabled = true;
	const originalText = button.textContent;
	button.textContent = 'Loading...';

	// Get data from button attributes
	const startPage = button.getAttribute('data-start-page');
	const procedureIds = button.getAttribute('data-procedure-ids');

	// Check if there's an active procedure filter with nudity
	let hasNudity = false;
	const activeFilterLink = document.querySelector('.brag-book-gallery-filter-link.brag-book-gallery-active');
	if (activeFilterLink && activeFilterLink.dataset.nudity === 'true') {
		hasNudity = true;
	}

	// Get the nonce from the localized script data
	const nonce = window.bragBookGalleryConfig?.nonce || '';
	const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';

	// Prepare AJAX data
	const formData = new FormData();
	formData.append('action', 'brag_book_load_more_cases');
	formData.append('nonce', nonce);
	formData.append('start_page', startPage);
	formData.append('procedure_ids', procedureIds);
	formData.append('has_nudity', hasNudity ? '1' : '0');

	// Make AJAX request
	fetch(ajaxUrl, {
		method: 'POST',
		body: formData
	})
		.then(response => response.json())
		.then(data => {
			console.log('Load More Response:', data);
			if (data.success) {
				// Find the cases grid container
				let container = document.querySelector('.brag-book-gallery-cases-grid');
				if (!container) {
					container = document.querySelector('.brag-book-cases-container');
				}
				if (!container) {
					// In AJAX filtered view, container might be nested
					container = document.querySelector('.brag-book-cases-grid .brag-book-cases-container');
				}

				if (container) {
					console.log('Adding HTML to container. HTML length:', data.data.html ? data.data.html.length : 0);
					console.log('Debug info:', data.data.debug);
					if (data.data.html) {
						// Find the last case card in the grid
						const lastCard = container.querySelector('.brag-book-gallery-case-card:last-child');
						if (lastCard) {
							// Insert after the last card
							lastCard.insertAdjacentHTML('afterend', data.data.html);
						} else {
							// If no cards exist, add to the container
							container.insertAdjacentHTML('beforeend', data.data.html);
						}
						console.log('Cases in container after insert:', container.querySelectorAll('[data-case-id]').length);
					} else {
						console.error('No HTML received from server');
					}
				} else {
					console.error('Container not found (.brag-book-gallery-cases-grid, .brag-book-cases-container, or .brag-book-cases-grid .brag-book-cases-container)');
				}

				// Update button for next load
				if (data.data.hasMore) {
					// Increment page by 1 since we load 1 page at a time
					button.setAttribute('data-start-page', parseInt(startPage) + 1);
					button.disabled = false;
					button.textContent = originalText;
				} else {
					// No more cases, hide the button
					button.parentElement.style.display = 'none';
				}

				// Update the count display - only if we found the container
				if (container) {
					// Try multiple possible selectors for the count label
					const countLabel = document.querySelector('.brag-book-gallery-count-label') ||
					                   document.querySelector('.cases-count') ||
					                   document.querySelector('[class*="count-label"]');
					if (countLabel) {
						// Count only the gallery case cards, not all elements with data-case-id
						const currentShown = container.querySelectorAll('.brag-book-gallery-case-card').length;
						const match = countLabel.textContent.match(/(\d+) of (\d+)/);
						if (match) {
							const total = match[2];
							countLabel.textContent = 'Showing ' + currentShown + ' of ' + total;
						}
					}
				}
			} else {
				console.error('Failed to load more cases:', data.data ? data.data.message : 'Unknown error');
				button.disabled = false;
				button.textContent = originalText;
				alert('Failed to load more cases. Please try again.');
			}
		})
		.catch(error => {
			console.error('Error loading more cases:', error);
			button.disabled = false;
			button.textContent = originalText;
			alert('Error loading more cases. Please check your connection and try again.');
		});
};

// Initialize app when DOM is ready
let nudityManager; // Make it globally accessible for reset
let phoneFormatter; // Make it globally accessible

document.addEventListener('DOMContentLoaded', () => {
	new BragBookGalleryApp();
	nudityManager = new NudityWarningManager();
	phoneFormatter = new PhoneFormatter();

	// Apply saved grid preference if available
	const savedColumns = localStorage.getItem('bragbook-grid-columns');
	if (savedColumns) {
		window.updateGridLayout(parseInt(savedColumns));
	}
});
