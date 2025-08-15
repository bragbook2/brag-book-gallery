/**
 * BRAGBook Gallery Carousel JavaScript
 *
 * Handles carousel navigation, auto-play functionality, touch gestures,
 * and responsive behavior for the BRAGBook Gallery carousel component.
 *
 * @package    BRAGBookGallery
 * @subpackage Assets\JS
 * @since      3.0.0
 */

(function() {
	'use strict';

	/**
	 * Carousel class for managing carousel functionality
	 */
	class BragBookCarousel {
		/**
		 * Constructor
		 * @param {HTMLElement} container - The carousel container element
		 * @param {Object} options - Carousel configuration options
		 */
		constructor(container, options = {}) {
			this.container = container;
			this.track = container.querySelector('.carousel-track');
			this.items = Array.from(container.querySelectorAll('.carousel-item'));
			this.prevBtn = container.querySelector('.carousel-controls.prev');
			this.nextBtn = container.querySelector('.carousel-controls.next');
			this.pagination = container.querySelector('.carousel-pagination');
			this.dots = Array.from(container.querySelectorAll('.carousel-dot'));

			// Configuration options
			this.options = {
				autoPlay: options.autoPlay !== false,
				autoPlayInterval: parseInt(options.autoPlayInterval) || 5000,
				showControls: options.showControls !== false,
				showPagination: options.showPagination !== false,
				itemsPerView: parseInt(options.itemsPerView) || this.calculateItemsPerView(),
				gap: parseInt(options.gap) || 16,
				touchEnabled: options.touchEnabled !== false,
				keyboard: options.keyboard !== false,
				loop: options.loop !== false,
				...options
			};

			// State
			this.currentIndex = 0;
			this.isTransitioning = false;
			this.autoPlayTimer = null;
			this.touchStartX = 0;
			this.touchEndX = 0;

			// Initialize
			this.init();
		}

		/**
		 * Initialize the carousel
		 */
		init() {
			if (!this.track || this.items.length === 0) {
				console.warn('BragBook Carousel: Invalid carousel structure');
				return;
			}

			this.setupLayout();
			this.bindEvents();
			this.updateNavigation();

			if (this.options.autoPlay) {
				this.startAutoPlay();
			}

			// Mark as initialized
			this.container.classList.add('carousel-initialized');

			// Fire ready event
			this.dispatchEvent('carouselReady', { carousel: this });
		}

		/**
		 * Setup initial layout and sizing
		 */
		setupLayout() {
			const containerWidth = this.container.clientWidth;
			const itemsPerView = this.options.itemsPerView;
			const gap = this.options.gap;

			// Calculate item width
			const itemWidth = (containerWidth - (gap * (itemsPerView + 1))) / itemsPerView;

			// Apply styles to items
			this.items.forEach(item => {
				item.style.width = `${itemWidth}px`;
				item.style.marginRight = `${gap}px`;
			});

			// Remove margin from last item
			if (this.items.length > 0) {
				this.items[this.items.length - 1].style.marginRight = '0';
			}

			// Set track width
			const trackWidth = this.items.length * (itemWidth + gap);
			this.track.style.width = `${trackWidth}px`;
		}

		/**
		 * Calculate optimal items per view based on container size
		 */
		calculateItemsPerView() {
			const containerWidth = this.container.clientWidth;

			if (containerWidth < 480) {
				return 1;
			} else if (containerWidth < 768) {
				return 2;
			} else if (containerWidth < 1024) {
				return 3;
			} else {
				return 4;
			}
		}

		/**
		 * Bind event listeners
		 */
		bindEvents() {
			// Navigation buttons
			if (this.prevBtn && this.options.showControls) {
				this.prevBtn.addEventListener('click', () => this.goToPrevious());
			}

			if (this.nextBtn && this.options.showControls) {
				this.nextBtn.addEventListener('click', () => this.goToNext());
			}

			// Pagination dots
			if (this.dots.length > 0 && this.options.showPagination) {
				this.dots.forEach((dot, index) => {
					dot.addEventListener('click', () => this.goToSlide(index));
				});
			}

			// Touch events
			if (this.options.touchEnabled) {
				this.track.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
				this.track.addEventListener('touchend', (e) => this.handleTouchEnd(e), { passive: true });
			}

			// Keyboard navigation
			if (this.options.keyboard) {
				this.container.addEventListener('keydown', (e) => this.handleKeydown(e));
				this.container.setAttribute('tabindex', '0');
			}

			// Pause auto-play on hover
			if (this.options.autoPlay) {
				this.container.addEventListener('mouseenter', () => this.pauseAutoPlay());
				this.container.addEventListener('mouseleave', () => this.startAutoPlay());
			}

			// Resize handler
			window.addEventListener('resize', () => this.handleResize());

			// Visibility change (pause when tab is hidden)
			document.addEventListener('visibilitychange', () => {
				if (document.hidden) {
					this.pauseAutoPlay();
				} else if (this.options.autoPlay) {
					this.startAutoPlay();
				}
			});

			// Image lazy loading
			this.setupLazyLoading();
		}

		/**
		 * Handle touch start event
		 */
		handleTouchStart(event) {
			this.touchStartX = event.touches[0].clientX;
			this.pauseAutoPlay();
		}

		/**
		 * Handle touch end event
		 */
		handleTouchEnd(event) {
			this.touchEndX = event.changedTouches[0].clientX;
			this.handleSwipe();

			if (this.options.autoPlay) {
				this.startAutoPlay();
			}
		}

		/**
		 * Handle swipe gesture
		 */
		handleSwipe() {
			const swipeThreshold = 50;
			const diff = this.touchStartX - this.touchEndX;

			if (Math.abs(diff) > swipeThreshold) {
				if (diff > 0) {
					this.goToNext();
				} else {
					this.goToPrevious();
				}
			}
		}

		/**
		 * Handle keyboard navigation
		 */
		handleKeydown(event) {
			switch (event.key) {
				case 'ArrowLeft':
					event.preventDefault();
					this.goToPrevious();
					break;
				case 'ArrowRight':
					event.preventDefault();
					this.goToNext();
					break;
				case 'Home':
					event.preventDefault();
					this.goToSlide(0);
					break;
				case 'End':
					event.preventDefault();
					this.goToSlide(this.getMaxIndex());
					break;
			}
		}

		/**
		 * Handle window resize
		 */
		handleResize() {
			// Debounce resize events
			clearTimeout(this.resizeTimer);
			this.resizeTimer = setTimeout(() => {
				this.options.itemsPerView = this.calculateItemsPerView();
				this.setupLayout();
				this.updateTransform();
				this.updateNavigation();
			}, 250);
		}

		/**
		 * Go to previous slide
		 */
		goToPrevious() {
			if (this.isTransitioning) return;

			const newIndex = this.currentIndex - this.options.itemsPerView;

			if (newIndex < 0) {
				if (this.options.loop) {
					this.goToSlide(this.getMaxIndex());
				}
			} else {
				this.goToSlide(newIndex);
			}
		}

		/**
		 * Go to next slide
		 */
		goToNext() {
			if (this.isTransitioning) return;

			const newIndex = this.currentIndex + this.options.itemsPerView;

			if (newIndex > this.getMaxIndex()) {
				if (this.options.loop) {
					this.goToSlide(0);
				}
			} else {
				this.goToSlide(newIndex);
			}
		}

		/**
		 * Go to specific slide
		 */
		goToSlide(index) {
			if (this.isTransitioning || index === this.currentIndex) return;

			const maxIndex = this.getMaxIndex();
			this.currentIndex = Math.max(0, Math.min(index, maxIndex));

			this.updateTransform();
			this.updateNavigation();
			this.updatePagination();

			// Fire slide change event
			this.dispatchEvent('slideChange', {
				currentIndex: this.currentIndex,
				carousel: this
			});
		}

		/**
		 * Get maximum index based on items per view
		 */
		getMaxIndex() {
			return Math.max(0, this.items.length - this.options.itemsPerView);
		}

		/**
		 * Update transform position
		 */
		updateTransform() {
			if (!this.track) return;

			this.isTransitioning = true;
			this.track.classList.add('transitioning');

			const itemWidth = this.items[0]?.clientWidth || 0;
			const gap = this.options.gap;
			const offset = this.currentIndex * (itemWidth + gap);

			this.track.style.transform = `translateX(-${offset}px)`;

			// Remove transitioning class after animation
			setTimeout(() => {
				this.isTransitioning = false;
				this.track.classList.remove('transitioning');
			}, 500);
		}

		/**
		 * Update navigation button states
		 */
		updateNavigation() {
			if (!this.options.showControls) return;

			const maxIndex = this.getMaxIndex();

			// Previous button
			if (this.prevBtn) {
				this.prevBtn.disabled = !this.options.loop && this.currentIndex === 0;
			}

			// Next button
			if (this.nextBtn) {
				this.nextBtn.disabled = !this.options.loop && this.currentIndex >= maxIndex;
			}
		}

		/**
		 * Update pagination dots
		 */
		updatePagination() {
			if (!this.options.showPagination || this.dots.length === 0) return;

			this.dots.forEach((dot, index) => {
				const isActive = index === Math.floor(this.currentIndex / this.options.itemsPerView);
				dot.classList.toggle('active', isActive);
			});
		}

		/**
		 * Start auto-play
		 */
		startAutoPlay() {
			if (!this.options.autoPlay) return;

			this.pauseAutoPlay();
			this.autoPlayTimer = setInterval(() => {
				this.goToNext();
			}, this.options.autoPlayInterval);
		}

		/**
		 * Pause auto-play
		 */
		pauseAutoPlay() {
			if (this.autoPlayTimer) {
				clearInterval(this.autoPlayTimer);
				this.autoPlayTimer = null;
			}
		}

		/**
		 * Setup lazy loading for images
		 */
		setupLazyLoading() {
			const images = this.container.querySelectorAll('img[data-src]');

			if ('IntersectionObserver' in window) {
				const imageObserver = new IntersectionObserver((entries) => {
					entries.forEach(entry => {
						if (entry.isIntersecting) {
							const img = entry.target;
							img.src = img.dataset.src;
							img.removeAttribute('data-src');
							img.classList.remove('loading');
							imageObserver.unobserve(img);
						}
					});
				});

				images.forEach(img => {
					img.classList.add('loading');
					imageObserver.observe(img);
				});
			} else {
				// Fallback for older browsers
				images.forEach(img => {
					img.src = img.dataset.src;
					img.removeAttribute('data-src');
				});
			}
		}

		/**
		 * Dispatch custom event
		 */
		dispatchEvent(eventName, detail) {
			const event = new CustomEvent(`bragbook:${eventName}`, {
				detail,
				bubbles: true
			});
			this.container.dispatchEvent(event);
		}

		/**
		 * Destroy carousel instance
		 */
		destroy() {
			this.pauseAutoPlay();

			// Remove event listeners
			window.removeEventListener('resize', this.handleResize);
			document.removeEventListener('visibilitychange', this.visibilityChangeHandler);

			// Clear timers
			if (this.resizeTimer) {
				clearTimeout(this.resizeTimer);
			}

			// Fire destroy event
			this.dispatchEvent('carouselDestroy', { carousel: this });
		}
	}

	/**
	 * Initialize carousels when DOM is ready
	 */
	function initCarousels() {
		const carousels = document.querySelectorAll('.brag-book-carousel');

		carousels.forEach(container => {
			// Skip if already initialized
			if (container.classList.contains('carousel-initialized')) {
				return;
			}

			// Get options from data attributes
			const options = {
				autoPlay: container.dataset.autoPlay !== 'false',
				autoPlayInterval: parseInt(container.dataset.autoPlayInterval) || 5000,
				showControls: container.dataset.showControls !== 'false',
				showPagination: container.dataset.showPagination !== 'false',
				itemsPerView: parseInt(container.dataset.itemsPerView) || null,
				touchEnabled: container.dataset.touchEnabled !== 'false',
				keyboard: container.dataset.keyboard !== 'false',
				loop: container.dataset.loop !== 'false'
			};

			// Create carousel instance
			const carousel = new BragBookCarousel(container, options);

			// Store reference for external access
			container.bragBookCarousel = carousel;
		});
	}

	/**
	 * Global API for external carousel control
	 */
	window.BragBookCarousel = {
		init: initCarousels,
		create: (container, options) => new BragBookCarousel(container, options),
		destroyAll: () => {
			document.querySelectorAll('.brag-book-carousel').forEach(container => {
				if (container.bragBookCarousel) {
					container.bragBookCarousel.destroy();
				}
			});
		}
	};

	// Auto-initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCarousels);
	} else {
		initCarousels();
	}

	// Re-initialize on dynamic content changes
	if (window.MutationObserver) {
		const observer = new MutationObserver(mutations => {
			mutations.forEach(mutation => {
				if (mutation.type === 'childList') {
					mutation.addedNodes.forEach(node => {
						if (node.nodeType === 1) {
							const carousels = node.classList?.contains('brag-book-carousel')
								? [node]
								: node.querySelectorAll?.('.brag-book-carousel') || [];

							Array.from(carousels).forEach(carousel => {
								if (!carousel.classList.contains('carousel-initialized')) {
									const options = {
										autoPlay: carousel.dataset.autoPlay !== 'false',
										autoPlayInterval: parseInt(carousel.dataset.autoPlayInterval) || 5000,
										showControls: carousel.dataset.showControls !== 'false',
										showPagination: carousel.dataset.showPagination !== 'false',
										touchEnabled: carousel.dataset.touchEnabled !== 'false',
										keyboard: carousel.dataset.keyboard !== 'false',
										loop: carousel.dataset.loop !== 'false'
									};

									carousel.bragBookCarousel = new BragBookCarousel(carousel, options);
								}
							});
						}
					});
				}
			});
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
	}

})();
