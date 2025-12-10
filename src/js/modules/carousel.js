/**
 * Carousel Component for BRAGBook Gallery
 * Adapted from Blocksmith carousel with BRAGBook-specific selectors
 */
class Carousel {
	constructor(options) {
		this.wrappers = {};
		this.options = {
			wrapper: '.brag-book-gallery-carousel-wrapper',
			nav: '[data-direction]',
			pagination: '.brag-book-gallery-carousel-pagination',
			track: '.brag-book-gallery-carousel-track',
			direction: 'horizontal',
			autoplay: false,
			autoplayInterval: 5000,
			itemPerPage: 1,
			itemPerGroup: 1,
			...options
		};

		this.state = {
			currentSlide: 0,
			totalSlides: 0,
			progress: 0
		};

		this.setupIntersectionObserver();
		this.init();
	}

	Wrapper = class {
		constructor(w, index, options) {
			this.wrapper = w;
			this.nav = w.querySelectorAll(options.nav);
			this.pagination = w.querySelector(options.pagination);
			this.grid = w.querySelector(options.track);
			this.index = index;

			if (this.grid) {
				this.grid.setAttribute('data-wrapper-id', this.index);
				this.items = this.grid.children;

				const computedStyle = window.getComputedStyle(this.grid);
				this.columnGap = parseInt(computedStyle.columnGap, 10);
				this.rowGap = parseInt(computedStyle.rowGap, 10);

				this.itemPerPageMobile = parseInt(
					this.grid.getAttribute('data-mobile-items') || 1,
					10
				);
				this.itemPerPageTablet = parseInt(
					this.grid.getAttribute('data-tablet-items') || 2,
					10
				);
				this.itemPerPageDesktop = parseInt(
					this.grid.getAttribute('data-desktop-items') || 3,
					10
				);
			}

			this.isDragging = false;
			this.startX = 0;
			this.scrollLeft = 0;

			this.autoplay =
				this.wrapper.getAttribute('data-carousel-autoplay') === 'true';
			this.autoplayInterval = parseInt(
				w.getAttribute('data-carousel-interval') || 5000,
				10
			);
			this.autoplayTimer = null;
			this.isHovered = false;
		}
	};

	startAutoplay = (object) => {
		if (!object.autoplay) return;

		// Clear any existing timer
		this.stopAutoplay(object);

		object.autoplayTimer = setInterval(() => {
			if (!object.isHovered) {
				const scroll = object.grid;
				const maxScrollLeft = scroll.scrollWidth - scroll.offsetWidth;

				if (scroll.scrollLeft >= maxScrollLeft) {
					// If we're at the end, go back to start
					scroll.scrollTo({
						left: 0,
						behavior: 'smooth'
					});
				} else {
					// Otherwise, move to next slide
					const firstItem = object.items[0];
					if (!firstItem) return;

					const itemWidth = firstItem.offsetWidth;
					const slideWidth = itemWidth + object.columnGap;

					scroll.scrollTo({
						left: scroll.scrollLeft + slideWidth,
						behavior: 'smooth'
					});
				}
			}
		}, object.autoplayInterval);
	};

	stopAutoplay = (object) => {
		if (object.autoplayTimer) {
			clearInterval(object.autoplayTimer);
			object.autoplayTimer = null;
		}
	};

	setupAutoplay = (object) => {
		if (!object.autoplay) return;

		// Start autoplay
		this.startAutoplay(object);

		// Pause on hover
		object.wrapper.addEventListener('mouseenter', () => {
			object.isHovered = true;
		});

		object.wrapper.addEventListener('mouseleave', () => {
			object.isHovered = false;
		});

		// Pause on focus within
		object.wrapper.addEventListener('focusin', () => {
			object.isHovered = true;
		});

		object.wrapper.addEventListener('focusout', () => {
			object.isHovered = false;
		});

		// Pause on touch
		object.wrapper.addEventListener('touchstart', () => {
			object.isHovered = true;
		});

		object.wrapper.addEventListener('touchend', () => {
			object.isHovered = false;
		});
	};

	updateSlideStates = (object) => {
		if (!object || !object.grid || !object.items.length) return;

		const currentIndex = this.getCurrentSlideIndex(object);

		// Remove all state classes first
		Array.from(object.items).forEach((slide) => {
			slide.classList.remove(
				'is-active-slide',
				'is-next-slide',
				'is-prev-slide',
				'is-next-next-slide',
				'is-prev-prev-slide'
			);
		});

		// Add appropriate classes
		Array.from(object.items).forEach((slide, index) => {
			// Active slide
			if (index === currentIndex) {
				slide.classList.add('is-active-slide');
			}

			// Next slide
			if (index === currentIndex + 1) {
				slide.classList.add('is-next-slide');
			}

			// Previous slide
			if (index === currentIndex - 1) {
				slide.classList.add('is-prev-slide');
			}

			// Next next slide
			if (index === currentIndex + 2) {
				slide.classList.add('is-next-next-slide');
			}

			// Previous previous slide
			if (index === currentIndex - 2) {
				slide.classList.add('is-prev-prev-slide');
			}
		});
	};

	setupTrackDrag = (object) => {
		if (!object.grid) return;

		let isDragging = false;
		let startX = 0;
		let startScrollLeft = 0;
		let lastMouseX = 0;
		let velocity = 0;
		let animationFrameId = null;

		const onMouseDown = (e) => {
			isDragging = true;
			startX = e.pageX;
			lastMouseX = startX;
			startScrollLeft = object.grid.scrollLeft;

			object.grid.style.cursor = 'grabbing';
			object.grid.style.userSelect = 'none';
			object.grid.style.scrollBehavior = 'auto';

			document.addEventListener('mousemove', onMouseMove);
			document.addEventListener('mouseup', onMouseUp);
		};

		const onMouseMove = (e) => {
			if (!isDragging) return;
			e.preventDefault();

			const currentX = e.pageX;
			const deltaX = currentX - lastMouseX;
			lastMouseX = currentX;

			// Calculate velocity for momentum scrolling
			velocity = deltaX * 2;

			// Update scroll position with improved sensitivity
			object.grid.scrollLeft = object.grid.scrollLeft - deltaX;
		};

		const onMouseUp = () => {
			if (!isDragging) return;
			isDragging = false;

			object.grid.style.cursor = '';
			object.grid.style.userSelect = '';

			// Apply momentum scrolling
			const applyMomentum = () => {
				if (Math.abs(velocity) > 0.5) {
					object.grid.scrollLeft -= velocity;
					velocity *= 0.95; // Decay factor
					animationFrameId = requestAnimationFrame(applyMomentum);
				} else {
					cancelAnimationFrame(animationFrameId);
					object.grid.style.scrollBehavior = 'smooth';
					this.snapToClosestSlide(object.grid);
				}
			};

			applyMomentum();

			document.removeEventListener('mousemove', onMouseMove);
			document.removeEventListener('mouseup', onMouseUp);
		};

		object.grid.addEventListener('mousedown', onMouseDown);
	};

	initializePagination = (object) => {
		if (!object.pagination || !object.items.length) return;

		// Clear existing pagination dots
		object.pagination.innerHTML = '';

		// Calculate number of pages based on items per page and total items
		let itemsPerPage;
		const width = window.innerWidth;

		if (width < 768) {
			itemsPerPage = object.itemPerPageMobile;
		} else if (width < 992) {
			itemsPerPage = object.itemPerPageTablet;
		} else {
			itemsPerPage = object.itemPerPageDesktop;
		}

		const totalPages = Math.ceil(object.items.length / itemsPerPage);

		// Create pagination dots
		for (let i = 0; i < totalPages; i++) {
			const dot = document.createElement('button');
			dot.className = 'brag-book-gallery-pagination-dot';
			dot.setAttribute('role', 'tab');
			dot.setAttribute('aria-label', `Go to slide ${i + 1}`);
			dot.setAttribute('tabindex', '0');

			// Set first dot as active
			if (i === 0) {
				dot.classList.add('brag-book-gallery-active');
				dot.setAttribute('aria-selected', 'true');
			} else {
				dot.setAttribute('aria-selected', 'false');
			}

			// Store click handler reference
			dot.clickHandler = () => {
				// Calculate actual item width including gap
				const firstItem = object.items[0];
				if (!firstItem) return;

				const itemWidth = firstItem.offsetWidth;
				const slideWidth = itemWidth + object.columnGap;

				// Scroll to the page (each page shows multiple items)
				const targetScroll = i * object.grid.clientWidth;
				this.smoothScrollTo(object.grid, targetScroll);
			};

			dot.addEventListener('click', dot.clickHandler);
			object.pagination.appendChild(dot);
		}

		// Set initial active state
		this.updatePaginationState(object);
	};

	updatePaginationState = (object) => {
		if (!object.pagination) return;

		const dots = object.pagination.querySelectorAll('.brag-book-gallery-pagination-dot');
		if (!dots.length) return;

		let itemsPerPage;
		const width = window.innerWidth;

		if (width < 768) {
			itemsPerPage = object.itemPerPageMobile;
		} else if (width < 992) {
			itemsPerPage = object.itemPerPageTablet;
		} else {
			itemsPerPage = object.itemPerPageDesktop;
		}

		// Calculate the current index based on scroll position and total width
		const maxScroll = object.grid.scrollWidth - object.grid.clientWidth;
		const currentPosition = object.grid.scrollLeft;
		const slideWidth = object.grid.clientWidth;
		const totalSlides = object.items.length;
		const totalGroups = Math.ceil(totalSlides / itemsPerPage);

		// Calculate the current group index
		let currentIndex;
		if (currentPosition >= maxScroll) {
			// If we're at the end, select the last group
			currentIndex = totalGroups - 1;
		} else {
			// Otherwise calculate the current group based on scroll position
			currentIndex = Math.round(currentPosition / slideWidth);
		}

		dots.forEach((dot, index) => {
			if (index === currentIndex) {
				dot.classList.add('brag-book-gallery-active');
				dot.setAttribute('aria-selected', 'true');
			} else {
				dot.classList.remove('brag-book-gallery-active');
				dot.setAttribute('aria-selected', 'false');
			}
		});
	};

	setupIntersectionObserver = () => {
		this.observer = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting) {
						entry.target.classList.add('is-visible');
						entry.target.querySelectorAll('img[data-src]').forEach((img) => {
							img.src = img.dataset.src;
							img.removeAttribute('data-src');
						});
					}
				});
			},
			{
				root: null,
				rootMargin: '50px',
				threshold: 0.1
			}
		);
	};

	handleScroll = (object) => {
		if (!object || !object.grid) return;

		const currentPosition = object.grid.scrollLeft;
		const totalWidth = object.grid.scrollWidth - object.grid.clientWidth;

		// Update slide states
		this.updateSlideStates(object);

		// Update navigation buttons (disable at start/end)
		if (object.nav.length >= 2) {
			const prevButton = object.nav[0];
			const nextButton = object.nav[1];

			const isAtStart = currentPosition <= 0;
			const isAtEnd = currentPosition >= totalWidth - 1;

			if (prevButton) {
				prevButton.disabled = isAtStart;
				if (isAtStart) {
					prevButton.setAttribute('disabled', 'disabled');
				} else {
					prevButton.removeAttribute('disabled');
				}
			}

			if (nextButton) {
				nextButton.disabled = isAtEnd;
				if (isAtEnd) {
					nextButton.setAttribute('disabled', 'disabled');
				} else {
					nextButton.removeAttribute('disabled');
				}
			}
		}

		// Update pagination
		this.updatePaginationState(object);

		// Update ARIA labels
		this.updateAriaLabels(object);
	};

	updateAriaLabels = (object) => {
		const currentIndex = this.getCurrentSlideIndex(object);
		const totalSlides = object.items.length;

		object.wrapper.setAttribute(
			'aria-label',
			`Carousel with ${totalSlides} slides`
		);

		Array.from(object.items).forEach((slide, index) => {
			const isHidden = index !== currentIndex;
			slide.setAttribute('aria-label', `Slide ${index + 1} of ${totalSlides}`);
			slide.setAttribute('aria-hidden', isHidden.toString());

			// Manage focusable elements within hidden slides to prevent
			// aria-hidden elements from containing focusable descendants
			const focusableElements = slide.querySelectorAll(
				'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
			);
			focusableElements.forEach((el) => {
				if (isHidden) {
					el.setAttribute('tabindex', '-1');
				} else {
					el.removeAttribute('tabindex');
				}
			});
		});
	};

	handleNext = (object) => {
		if (!object || !object.grid) {
			console.log('handleNext: no object or grid');
			return;
		}

		const scroll = object.grid;
		const maxScrollLeft = scroll.scrollWidth - scroll.offsetWidth;

		// Calculate actual slide width including gap
		const firstItem = object.items[0];
		if (!firstItem) return;

		const itemWidth = firstItem.offsetWidth;
		const slideWidth = itemWidth + object.columnGap;

		if (scroll.scrollLeft < maxScrollLeft) {
			const targetScroll = scroll.scrollLeft + slideWidth;
			this.smoothScrollTo(scroll, targetScroll);
		}
	};

	handlePrev = (object) => {
		if (!object || !object.grid) {
			console.log('handlePrev: no object or grid');
			return;
		}

		const scroll = object.grid;

		// Calculate actual slide width including gap
		const firstItem = object.items[0];
		if (!firstItem) return;

		const itemWidth = firstItem.offsetWidth;
		const slideWidth = itemWidth + object.columnGap;

		if (scroll.scrollLeft > 0) {
			const targetScroll = scroll.scrollLeft - slideWidth;
			this.smoothScrollTo(scroll, targetScroll);
		}
	};

	/**
	 * Firefox-compatible smooth scrolling that works with scroll-snap
	 */
	smoothScrollTo = (element, targetScroll) => {

		// Temporarily disable scroll-snap for Firefox compatibility
		const originalSnapType = element.style.scrollSnapType;
		element.style.scrollSnapType = 'none';

		// Try using scrollTo with behavior smooth
		try {
			element.scrollTo({
				left: targetScroll,
				behavior: 'smooth'
			});
		} catch (e) {
			// Fallback for older browsers
			element.scrollLeft = targetScroll;
		}

		// Re-enable scroll-snap after scroll completes
		setTimeout(() => {
			element.style.scrollSnapType = originalSnapType || 'x mandatory';
		}, 500);
	};

	setupTouchEvents = (grid, object) => {
		let touchStartX;
		let touchStartY;
		let initialScrollLeft;

		const handleTouchStart = (e) => {
			touchStartX = e.touches[0].clientX;
			touchStartY = e.touches[0].clientY;
			initialScrollLeft = grid.scrollLeft;
		};

		const handleTouchMove = (e) => {
			if (!touchStartX || !touchStartY) return;

			const touchCurrentX = e.touches[0].clientX;
			const touchCurrentY = e.touches[0].clientY;
			const deltaX = touchStartX - touchCurrentX;
			const deltaY = Math.abs(touchStartY - touchCurrentY);

			if (Math.abs(deltaX) > deltaY) {
				e.preventDefault();
				grid.scrollLeft = initialScrollLeft + deltaX;
				this.handleScroll(object);
			}
		};

		const handleTouchEnd = () => {
			touchStartX = null;
			touchStartY = null;
			this.snapToClosestSlide(grid);
		};

		grid.addEventListener('touchstart', handleTouchStart, { passive: true });
		grid.addEventListener('touchmove', handleTouchMove, { passive: false });
		grid.addEventListener('touchend', handleTouchEnd);
	};

	snapToClosestSlide = (grid) => {
		if (!grid.children[0]) return;

		const slideWidth = grid.children[0].offsetWidth;
		const scrollLeft = grid.scrollLeft;
		const slideIndex = Math.round(scrollLeft / slideWidth);

		grid.style.scrollBehavior = 'smooth';
		grid.scrollTo({
			left: slideIndex * slideWidth
		});
	};

	getCurrentSlideIndex = (object) => {
		if (!object.items[0]) return 0;

		const scrollLeft = object.grid.scrollLeft;
		const slideWidth = object.items[0].offsetWidth;
		return Math.round(scrollLeft / slideWidth);
	};

	init = () => {
		const carousels = document.querySelectorAll(this.options.wrapper);

		carousels.forEach((wrapper, index) => {
			this.wrappers[index] = new this.Wrapper(wrapper, index, this.options);
			const object = this.wrappers[index];

			if (!object.grid) return;

			// Store bound event handlers
			object.handleScroll = () => this.handleScroll(object);
			object.handleResize = () => {
				if (object.pagination) {
					this.initializePagination(object);
				}
			};

			object.grid.addEventListener('scroll', object.handleScroll, {
				passive: true
			});

			// Initialize both touch and mouse drag
			this.setupTouchEvents(object.grid, object);
			this.setupTrackDrag(object);

			if (object.pagination) {
				this.initializePagination(object);
				// Update pagination on window resize
				window.addEventListener('resize', object.handleResize);
			}

			// Setup navigation buttons
			const prevButton = wrapper.querySelector('[data-direction="prev"]');
			const nextButton = wrapper.querySelector('[data-direction="next"]');

			if (prevButton) {
				prevButton.addEventListener('click', (e) => {
					e.preventDefault();
					this.handlePrev(object);
				});
			}

			if (nextButton) {
				nextButton.addEventListener('click', (e) => {
					e.preventDefault();
					this.handleNext(object);
				});
			}

			// Initial state update
			this.handleScroll(object);

			// Setup autoplay
			this.setupAutoplay(object);
		});
	};

	destroy = () => {
		Object.values(this.wrappers).forEach((object) => {
			if (this.observer) {
				this.observer.disconnect();
			}

			this.stopAutoplay(object);

			if (object.grid) {
				object.grid.removeEventListener('scroll', object.handleScroll);
			}

			if (object.pagination) {
				const dots = object.pagination.querySelectorAll('.brag-book-gallery-pagination-dot');
				dots.forEach((dot) => {
					if (dot.clickHandler) {
						dot.removeEventListener('click', dot.clickHandler);
					}
				});
			}

			if (object.handleResize) {
				window.removeEventListener('resize', object.handleResize);
			}
		});

		this.wrappers = {};
	};
}

export default Carousel;
