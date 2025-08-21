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
			if (!actionsContainer.querySelector('.brag-book-gallery-favorite-button')) {
				const existingHeart = slide.querySelector('.brag-book-gallery-favorite-button');
				if (existingHeart) {
					actionsContainer.appendChild(existingHeart);
				} else {
					const heartBtn = document.createElement('button');
					heartBtn.className = 'brag-book-gallery-favorite-button';
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
			if (!actionsContainer.querySelector('.brag-book-gallery-share-button') &&
			    typeof bragBookGalleryConfig !== 'undefined' &&
			    bragBookGalleryConfig.enableSharing === 'yes') {
				const shareBtn = document.createElement('button');
				shareBtn.className = 'brag-book-gallery-share-button';
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
		if (width <= 768) return 2;
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

export default Carousel;
