/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/js/modules/carousel.js":
/*!************************************!*\
  !*** ./src/js/modules/carousel.js ***!
  \************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
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
        this.itemPerPageMobile = parseInt(this.grid.getAttribute('data-mobile-items') || 1, 10);
        this.itemPerPageTablet = parseInt(this.grid.getAttribute('data-tablet-items') || 2, 10);
        this.itemPerPageDesktop = parseInt(this.grid.getAttribute('data-desktop-items') || 3, 10);
      }
      this.isDragging = false;
      this.startX = 0;
      this.scrollLeft = 0;
      this.autoplay = this.wrapper.getAttribute('data-carousel-autoplay') === 'true';
      this.autoplayInterval = parseInt(w.getAttribute('data-carousel-interval') || 5000, 10);
      this.autoplayTimer = null;
      this.isHovered = false;
    }
  };
  startAutoplay = object => {
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
  stopAutoplay = object => {
    if (object.autoplayTimer) {
      clearInterval(object.autoplayTimer);
      object.autoplayTimer = null;
    }
  };
  setupAutoplay = object => {
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
  updateSlideStates = object => {
    if (!object || !object.grid || !object.items.length) return;
    const currentIndex = this.getCurrentSlideIndex(object);

    // Remove all state classes first
    Array.from(object.items).forEach(slide => {
      slide.classList.remove('is-active-slide', 'is-next-slide', 'is-prev-slide', 'is-next-next-slide', 'is-prev-prev-slide');
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
  setupTrackDrag = object => {
    if (!object.grid) return;
    let isDragging = false;
    let startX = 0;
    let startScrollLeft = 0;
    let lastMouseX = 0;
    let velocity = 0;
    let animationFrameId = null;
    const onMouseDown = e => {
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
    const onMouseMove = e => {
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
  initializePagination = object => {
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
  updatePaginationState = object => {
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
    this.observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          entry.target.querySelectorAll('img[data-src]').forEach(img => {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
          });
        }
      });
    }, {
      root: null,
      rootMargin: '50px',
      threshold: 0.1
    });
  };
  handleScroll = object => {
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
  updateAriaLabels = object => {
    const currentIndex = this.getCurrentSlideIndex(object);
    const totalSlides = object.items.length;
    object.wrapper.setAttribute('aria-label', `Carousel with ${totalSlides} slides`);
    Array.from(object.items).forEach((slide, index) => {
      const isHidden = index !== currentIndex;
      slide.setAttribute('aria-label', `Slide ${index + 1} of ${totalSlides}`);
      slide.setAttribute('aria-hidden', isHidden.toString());

      // Manage focusable elements within hidden slides to prevent
      // aria-hidden elements from containing focusable descendants
      const focusableElements = slide.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
      focusableElements.forEach(el => {
        if (isHidden) {
          el.setAttribute('tabindex', '-1');
        } else {
          el.removeAttribute('tabindex');
        }
      });
    });
  };
  handleNext = object => {
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
  handlePrev = object => {
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
    const handleTouchStart = e => {
      touchStartX = e.touches[0].clientX;
      touchStartY = e.touches[0].clientY;
      initialScrollLeft = grid.scrollLeft;
    };
    const handleTouchMove = e => {
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
    grid.addEventListener('touchstart', handleTouchStart, {
      passive: true
    });
    grid.addEventListener('touchmove', handleTouchMove, {
      passive: false
    });
    grid.addEventListener('touchend', handleTouchEnd);
  };
  snapToClosestSlide = grid => {
    if (!grid.children[0]) return;
    const slideWidth = grid.children[0].offsetWidth;
    const scrollLeft = grid.scrollLeft;
    const slideIndex = Math.round(scrollLeft / slideWidth);
    grid.style.scrollBehavior = 'smooth';
    grid.scrollTo({
      left: slideIndex * slideWidth
    });
  };
  getCurrentSlideIndex = object => {
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
        prevButton.addEventListener('click', e => {
          e.preventDefault();
          this.handlePrev(object);
        });
      }
      if (nextButton) {
        nextButton.addEventListener('click', e => {
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
    Object.values(this.wrappers).forEach(object => {
      if (this.observer) {
        this.observer.disconnect();
      }
      this.stopAutoplay(object);
      if (object.grid) {
        object.grid.removeEventListener('scroll', object.handleScroll);
      }
      if (object.pagination) {
        const dots = object.pagination.querySelectorAll('.brag-book-gallery-pagination-dot');
        dots.forEach(dot => {
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
/* harmony default export */ __webpack_exports__["default"] = (Carousel);

/***/ }),

/***/ "./src/js/modules/dialog.js":
/*!**********************************!*\
  !*** ./src/js/modules/dialog.js ***!
  \**********************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
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
      closeOnBackdrop: options.closeOnBackdrop !== false,
      // Default: true
      closeOnEscape: options.closeOnEscape !== false,
      // Default: true
      onOpen: options.onOpen || (() => {}),
      // Open callback
      onClose: options.onClose || (() => {}),
      // Close callback
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
      button.addEventListener('click', e => {
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
      this.dialog.addEventListener('click', e => {
        if (e.target === this.dialog) {
          this.close();
        }
      });
    }

    // Handle ESC key press using native dialog 'cancel' event
    if (this.options.closeOnEscape && this.dialog) {
      this.dialog.addEventListener('cancel', e => {
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
/* harmony default export */ __webpack_exports__["default"] = (Dialog);

/***/ }),

/***/ "./src/js/modules/favorites-manager.js":
/*!*********************************************!*\
  !*** ./src/js/modules/favorites-manager.js ***!
  \*********************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _dialog_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./dialog.js */ "./src/js/modules/dialog.js");


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
    this.favoritesDialog = new _dialog_js__WEBPACK_IMPORTED_MODULE_0__["default"]('favoritesDialog', {
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
    document.addEventListener('click', e => {
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

    // Handle favorites form submission - check both selectors for compatibility
    const favoritesForm = document.querySelector('[data-form="favorites"], [data-form="favorites-email"]');
    if (favoritesForm) {
      favoritesForm.addEventListener('submit', e => {
        e.preventDefault();
        this.handleFavoritesFormSubmit(e.target);
      });
    }

    // Handle favorites lookup form submission
    const lookupForm = document.querySelector('.brag-book-gallery-favorites-lookup-form, #favorites-email-form');
    if (lookupForm) {
      lookupForm.addEventListener('submit', e => {
        e.preventDefault();
        this.handleFavoritesLookupSubmit(e.target);
      });
    }
  }
  toggleFavorite(button) {
    let itemId = '';
    let procedureId = '';
    const isFavorited = button.dataset.favorited === 'true';

    // Get case container (card or detail view)
    const caseCard = button.closest('.brag-book-gallery-case-card');
    const caseDetailView = button.closest('.brag-book-gallery-case-detail-view');
    const carouselItem = button.closest('.brag-book-gallery-carousel-item');
    const caseContainer = caseCard || caseDetailView || carouselItem;
    if (caseContainer) {
      // itemId must be the caseProcedureId (junction ID unique to each case-procedure combo)
      // NEVER use currentProcedureId as itemId — it's shared across all cards on taxonomy pages
      itemId = caseContainer.dataset.procedureCaseId || '';

      // Fallback to the button's own data-item-id (set by PHP to the junction ID)
      if (!itemId) {
        itemId = button.dataset.itemId || '';
        if (itemId) {
          const matches = itemId.match(/(\d+)/);
          if (matches) {
            itemId = matches[1];
          }
        }
      }

      // procedureId is a separate value — the taxonomy/API procedure ID for the API call
      procedureId = caseContainer.dataset.currentProcedureId || caseContainer.dataset.procedureId || (caseContainer.dataset.procedureIds ? caseContainer.dataset.procedureIds.split(',')[0] : '');
    } else {
      // No container found, use button's own data attributes
      itemId = button.dataset.itemId || '';
      if (itemId) {
        const matches = itemId.match(/(\d+)/);
        if (matches) {
          itemId = matches[1];
        }
      }
    }

    // Fallback to active nav link for procedureId only (never for itemId)
    if (!procedureId) {
      const activeNavLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
      procedureId = activeNavLink?.dataset.procedureId || '';
    }

    // Update hidden case ID field if it exists
    this.updateHiddenCaseIdField(itemId);
    // Update hidden procedure ID field if it exists
    this.updateHiddenProcedureIdField(procedureId);

    // If removing favorite, just remove it
    if (isFavorited) {
      this.removeFavorite(itemId, button, procedureId);
      return;
    }

    // If no item ID, can't add favorite
    if (!itemId) {
      console.error('No item ID found on button:', button);
      return;
    }

    // Check for user info in localStorage if we don't have it
    if (!this.userInfo || !this.userInfo.email) {
      this.loadUserInfo();
    }

    // If we have user info, submit to API directly
    if (this.userInfo && this.userInfo.email) {
      // Add to local favorites first
      this.addFavorite(itemId, button);
      // Submit to API
      this.submitFavoriteToAPI(itemId, procedureId);
    } else {
      // No user info - show dialog to collect it
      this.lastAddedFavorite = itemId;
      this.lastAddedProcedureId = procedureId;
      this.lastAddedButton = button;
      // Add the favorite first so it shows in the dialog
      this.addFavorite(itemId, button);
      this.favoritesDialog.open();
      this.hasShownDialog = true;
    }
  }

  /**
   * Update the hidden case ID field in the favorites form
   * @param {string} caseId - The case ID to set
   */
  updateHiddenCaseIdField(caseId) {
    const hiddenField = document.querySelector('input[name="fav-case-id"]');
    if (hiddenField && caseId) {
      hiddenField.value = caseId;
    }
  }

  /**
   * Update the hidden procedure ID field in the favorites form
   * @param {string} procedureId - The procedure ID to set
   */
  updateHiddenProcedureIdField(procedureId) {
    const hiddenField = document.querySelector('input[name="fav-procedure-id"]');
    if (hiddenField && procedureId) {
      hiddenField.value = procedureId;
    }
  }
  addFavorite(itemId, button) {
    // Update the clicked button
    if (button) {
      button.dataset.favorited = 'true';
    }

    // Also update any other buttons for the same item
    const allButtons = document.querySelectorAll(`[data-item-id="${itemId}"], [data-case-id="${itemId}"]`);
    allButtons.forEach(btn => {
      if (btn.dataset.favorited !== undefined) {
        btn.dataset.favorited = 'true';
      }
    });

    // Add to internal favorites collection
    this.favorites.add(itemId);

    // Persist to localStorage if enabled
    if (this.options.persistToStorage) {
      this.saveToStorage();
    }
    this.updateUI();
    this.options.onUpdate(this.favorites);

    // Dispatch custom event for other components
    window.dispatchEvent(new CustomEvent('favoritesUpdated', {
      detail: {
        favorites: this.favorites
      }
    }));
  }

  /**
   * Remove an item from favorites and update UI
   * @param {string} itemId - The ID of the item to unfavorite
   * @param {HTMLElement} button - The button that was clicked (optional)
   * @param {string} procedureId - The procedure ID (optional)
   */
  removeFavorite(itemId, button, procedureId = '') {
    // Update button states - find all relevant buttons if none provided
    if (!button) {
      const buttons = document.querySelectorAll(`[data-item-id="${itemId}"][data-favorited="true"], [data-case-id="${itemId}"][data-favorited="true"]`);
      buttons.forEach(btn => {
        btn.dataset.favorited = 'false';
      });
    } else {
      // Update the specific button and find related buttons
      button.dataset.favorited = 'false';
      const otherButtons = document.querySelectorAll(`[data-item-id="${itemId}"], [data-case-id="${itemId}"]`);
      otherButtons.forEach(btn => {
        if (btn !== button && btn.dataset.favorited !== undefined) {
          btn.dataset.favorited = 'false';
        }
      });
    }

    // Remove from internal favorites collection
    this.favorites.delete(itemId);

    // Persist changes to localStorage if enabled
    if (this.options.persistToStorage) {
      this.saveToStorage();
    }

    // Update UI and notify listeners
    this.updateUI();
    this.options.onUpdate(this.favorites);

    // Dispatch global event for other components
    window.dispatchEvent(new CustomEvent('favoritesUpdated', {
      detail: {
        favorites: this.favorites
      }
    }));

    // If user is logged in, also remove from API
    if (this.userInfo && this.userInfo.email) {
      this.removeFavoriteFromAPI(itemId, procedureId);
    }
  }

  /**
   * Remove a favorite from the BRAGBook API
   * @param {string} caseId - The case procedure ID to remove from favorites
   * @param {string} procedureId - The procedure ID
   */
  removeFavoriteFromAPI(caseId, procedureId = '') {
    // Use WordPress AJAX for secure API communication
    const formData = new FormData();
    formData.append('action', 'brag_book_remove_favorite');
    formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
    formData.append('case_id', caseId);
    formData.append('procedure_id', procedureId);
    formData.append('id_type', 'caseProcedureId');
    formData.append('email', this.userInfo.email || '');

    // Submit via WordPress AJAX (API tokens handled securely on server)
    fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData
    }).then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    }).then(response => {
      if (response.success) {
        // Show success notification
        this.showSuccessNotification('Removed from favorites!');

        // Remove the card from the favorites grid on the favorites page
        this.removeCardFromFavoritesGrid(caseId);
      } else {
        // Show error notification
        console.error('Failed to remove favorite:', response.data?.message);
        this.showErrorNotification(response.data?.message || 'Failed to remove favorite. Please try again.');

        // Restore the favorite state since API call failed
        this.restoreFavoriteState(caseId);
      }
    }).catch(error => {
      console.error('Error removing favorite:', error);
      this.showErrorNotification('Error removing favorite. Please try again.');

      // Restore the favorite state since API call failed
      this.restoreFavoriteState(caseId);
    });
  }

  /**
   * Remove a card from the favorites grid on the favorites page
   * @param {string} caseId - The case ID to remove
   */
  removeCardFromFavoritesGrid(caseId) {
    // Find the card element — caseId is the procedureCaseId (junction ID),
    // stored in data-procedure-case-id on the card element
    const card = document.querySelector(`.brag-book-gallery-case-card[data-procedure-case-id="${caseId}"], ` + `.brag-book-gallery-favorites-card[data-procedure-case-id="${caseId}"], ` + `.brag-book-gallery-case-card[data-post-id="${caseId}"], ` + `.brag-book-gallery-case-card[data-case-id="${caseId}"], ` + `.brag-book-gallery-favorites-card[data-post-id="${caseId}"], ` + `.brag-book-gallery-favorites-card[data-case-id="${caseId}"]`);
    if (card) {
      // Add fade-out animation
      card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
      card.style.opacity = '0';
      card.style.transform = 'scale(0.9)';

      // Remove from DOM after animation
      setTimeout(() => {
        card.remove();

        // Check if favorites grid is now empty
        const favoritesGrid = document.querySelector('.brag-book-gallery-favorites-grid, .brag-book-gallery-case-grid');
        if (favoritesGrid && favoritesGrid.children.length === 0) {
          // Show empty state
          const emptyState = document.getElementById('favoritesEmpty');
          const gridContainer = document.getElementById('favoritesGridContainer');
          if (emptyState) {
            emptyState.style.display = 'block';
          }
          if (favoritesGrid) {
            favoritesGrid.style.display = 'none';
          }
        }
      }, 300);
    }
  }

  /**
   * Restore favorite state when API call fails
   * @param {string} caseId - The case ID to restore
   */
  restoreFavoriteState(caseId) {
    // Re-add to internal favorites
    this.favorites.add(caseId);

    // Save to storage
    if (this.options.persistToStorage) {
      this.saveToStorage();
    }

    // Update all buttons back to favorited state
    const buttons = document.querySelectorAll(`[data-item-id="${caseId}"], [data-case-id="${caseId}"], ` + `.brag-book-gallery-case-card[data-post-id="${caseId}"] [data-favorited], ` + `.brag-book-gallery-case-card[data-case-id="${caseId}"] [data-favorited]`);
    buttons.forEach(btn => {
      if (btn.dataset.favorited !== undefined) {
        btn.dataset.favorited = 'true';
      }
    });

    // Update UI
    this.updateUI();
  }

  /**
   * Submit a favorite directly to the BRAGBook API (for users with existing info)
   * @param {string} caseId - The case procedure ID to add to favorites
   * @param {string} procedureId - The procedure ID
   */
  submitFavoriteToAPI(caseId, procedureId = '') {
    // Use WordPress AJAX for secure API communication
    const formData = new FormData();
    formData.append('action', 'brag_book_add_favorite');
    formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
    formData.append('case_id', caseId);
    formData.append('procedure_id', procedureId);
    formData.append('id_type', 'caseProcedureId');
    formData.append('email', this.userInfo.email || '');
    formData.append('phone', this.userInfo.phone || '');
    formData.append('name', this.userInfo.name || '');

    // Submit via WordPress AJAX (API tokens handled securely on server)
    fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData
    }).then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    }).then(response => {
      if (response.success) {
        // Show success notification
        this.showSuccessNotification('Added to favorites!');
      } else {
        // Show error notification and undo local state only (don't call API remove)
        console.error('Failed to save favorite:', response.data?.message);
        this.undoLocalFavorite(caseId);
        this.showErrorNotification('Failed to save favorite. Please try again.');
      }
    }).catch(error => {
      console.error('Error submitting favorite:', error);
      // Undo local state only (don't call API remove — item was never added)
      this.undoLocalFavorite(caseId);
      this.showErrorNotification('Error saving favorite. Please try again.');
    });
  }

  /**
   * Undo a local favorite without calling the API remove endpoint.
   * Used when an add API call fails — the item was never added to the API.
   * @param {string} itemId - The item ID to remove locally
   */
  undoLocalFavorite(itemId) {
    // Update button states
    const buttons = document.querySelectorAll(`[data-item-id="${itemId}"][data-favorited="true"]`);
    buttons.forEach(btn => {
      btn.dataset.favorited = 'false';
    });

    // Remove from internal favorites collection
    this.favorites.delete(itemId);

    // Persist changes to localStorage
    if (this.options.persistToStorage) {
      this.saveToStorage();
    }

    // Update UI
    this.updateUI();
    this.options.onUpdate(this.favorites);
    window.dispatchEvent(new CustomEvent('favoritesUpdated', {
      detail: {
        favorites: this.favorites
      }
    }));
  }

  /**
   * Handle submission of the user info form in the favorites dialog
   * @param {HTMLFormElement} form - The form element that was submitted
   */
  handleFavoritesFormSubmit(form) {
    const formData = new FormData(form);

    // Get case ID from hidden field first, then fallback to other methods
    let caseId = formData.get('fav-case-id') || this.lastAddedFavorite;
    // Get procedure ID from hidden field first, then fallback to stored value
    let procedureId = formData.get('fav-procedure-id') || this.lastAddedProcedureId || '';
    if (!caseId) {
      // Try to find the case ID from the current page context
      // Prioritize WordPress post ID over API case ID
      const articleElement = document.querySelector('.brag-book-gallery-case-card');
      if (articleElement) {
        caseId = articleElement.dataset.postId || articleElement.dataset.caseId;
        // Also try to get procedure ID if not already set
        if (!procedureId) {
          procedureId = articleElement.dataset.currentProcedureId || (articleElement.dataset.procedureIds ? articleElement.dataset.procedureIds.split(',')[0] : '');
        }
      }
    }

    // Fallback to active nav link for procedure ID
    if (!procedureId) {
      const activeNavLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
      procedureId = activeNavLink?.dataset.procedureId || '';
    }
    if (!caseId) {
      // If still no case ID, show error
      this.showDetailedFormError(form, {
        title: 'Case Identification Error',
        message: 'Unable to determine which case to favorite.',
        details: ['This usually happens when the page hasn\'t fully loaded', 'Try refreshing the page and clicking the favorite button again']
      });
      return;
    }

    // Get form field values
    const email = formData.get('fav-email') || '';
    const name = formData.get('fav-name') || '';
    const phone = formData.get('fav-phone') || '';

    // Validate fields individually and collect errors
    const validationErrors = [];
    if (!name) validationErrors.push('Name is required');
    if (!email) validationErrors.push('Email is required');else if (!this.isValidEmail(email)) validationErrors.push('Email address is not valid');
    if (!phone) validationErrors.push('Phone number is required');
    if (validationErrors.length > 0) {
      this.showDetailedFormError(form, {
        title: 'Form Validation Error',
        message: 'Please correct the following issues:',
        details: validationErrors
      });
      return;
    }

    // Show loading state in form
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : '';
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Submitting...';
    }

    // Clear any previous error messages
    const existingError = form.querySelector('.brag-book-gallery-form-error');
    const existingSuccess = form.querySelector('.brag-book-gallery-form-success');
    if (existingError) existingError.remove();
    if (existingSuccess) existingSuccess.remove();

    // Prepare WordPress AJAX request
    formData.append('action', 'brag_book_add_favorite');
    formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
    formData.append('case_id', caseId);
    formData.append('procedure_id', procedureId);
    formData.append('id_type', 'caseProcedureId');
    formData.append('email', email);
    formData.append('phone', phone);
    formData.append('name', name);

    // Submit via WordPress AJAX (API tokens handled securely on server)
    fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData
    }).then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    }).then(response => {
      if (response.success) {
        // Save user info locally
        const userInfo = {
          email,
          name,
          phone
        };
        this.userInfo = userInfo;

        // Save to localStorage for future sessions
        try {
          localStorage.setItem(this.options.userInfoKey, JSON.stringify(userInfo));
        } catch (e) {
          console.error('Failed to save user info to localStorage:', e);
        }

        // Show success message in form
        const successDiv = document.createElement('div');
        successDiv.className = 'brag-book-gallery-form-success';
        successDiv.textContent = response.data.message || 'Your information has been saved. Keep adding favorites!';
        form.appendChild(successDiv);

        // Close dialog after a short delay
        setTimeout(() => {
          this.favoritesDialog.close();
          // Clear the form
          form.reset();
          if (successDiv) successDiv.remove();
        }, 2000);

        // Show success notification
        this.showSuccessNotification(response.data.message || 'Your information has been saved. Keep adding favorites!');

        // Clear pending favorite
        this.lastAddedFavorite = null;
        this.lastAddedButton = null;
      } else {
        // Parse and show detailed error
        this.parseAndShowDetailedError(form, response, 'save');

        // Undo local state only (item was never added to API)
        if (this.lastAddedFavorite) {
          this.undoLocalFavorite(this.lastAddedFavorite);
        }
      }
    }).catch(error => {
      console.error('Error submitting favorites form:', error);

      // Show detailed network error
      this.showDetailedFormError(form, {
        title: 'Connection Error',
        message: 'Unable to communicate with the server.',
        details: ['Check your internet connection', 'The server may be temporarily unavailable', `Technical details: ${error.message}`]
      });

      // Undo local state only (item was never added to API)
      if (this.lastAddedFavorite) {
        this.undoLocalFavorite(this.lastAddedFavorite);
      }
    }).finally(() => {
      // Reset button state
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
      }
    });
  }

  /**
   * Handle submission of the favorites lookup form
   * @param {HTMLFormElement} form - The lookup form element that was submitted
   */
  handleFavoritesLookupSubmit(form) {
    const formData = new FormData(form);
    const email = formData.get('email');
    if (!email) {
      this.showLookupError(form, 'Please enter an email address.');
      return;
    }

    // Show loading state
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : '';
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Checking...';
    }

    // Clear any previous error messages
    this.clearLookupMessages(form);

    // First check localStorage for matching email
    const storedUserInfo = this.getUserInfo();
    if (storedUserInfo && storedUserInfo.email === email) {
      // Email matches localStorage, show favorites
      this.showLookupSuccess(form, email);
      return;
    }

    // If not found in localStorage, check with server
    // Add action and nonce for WordPress AJAX
    formData.append('action', 'brag_book_lookup_favorites');
    formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');

    // Submit via AJAX
    fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData
    }).then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    }).then(response => {
      if (response.success) {
        // Email found, validate and save user data
        if (response.data && response.data.user) {
          const user = response.data.user;
          const favoritesData = response.data.favorites || {};

          // Only create user info if we have essential data (email at minimum)
          if (user.email && user.name && user.phone) {
            const userInfo = {
              email: user.email,
              name: user.name,
              first_name: user.first_name || '',
              last_name: user.last_name || '',
              phone: user.phone,
              id: user.id
            };

            // Save user info to localStorage only if all essential fields are present
            localStorage.setItem('brag-book-user-info', JSON.stringify(userInfo));
            this.userInfo = userInfo;

            // Save favorites data if available — use junction IDs from caseProcedures
            if (favoritesData.cases_data && Object.keys(favoritesData.cases_data).length > 0) {
              // Extract junction IDs (caseProcedures[0].id) — these match
              // data-procedure-case-id on cards and are used for add/remove calls
              const favoriteIds = Object.values(favoritesData.cases_data).map(c => {
                if (c.caseProcedures && c.caseProcedures.length > 0) {
                  return String(c.caseProcedures[0].id);
                }
                return String(c.id || '');
              }).filter(Boolean);

              // Replace localStorage — API is authoritative
              localStorage.setItem('brag-book-favorites', JSON.stringify(favoriteIds));

              // Update internal favorites
              this.favorites = new Set(favoriteIds);

              // Update UI to reflect loaded favorites
              this.updateAllButtonStates();
            }
            this.showLookupSuccess(form, email, userInfo, favoritesData);
          } else {
            // User found but incomplete data - don't create localStorage entries
            this.showLookupError(form, 'Account found but missing required information. Please contact support.');
          }
        } else {
          // No user data returned, but success - this shouldn't happen
          this.showLookupError(form, 'Account found but no details available. Please contact support.');
        }
      } else {
        // Email not found, show error
        this.showLookupError(form, response.data?.message || 'We were unable to locate account details for this email address.');
      }
    }).catch(error => {
      console.error('Error looking up favorites:', error);
      this.showLookupError(form, 'An error occurred while looking up your favorites. Please try again.');
    }).finally(() => {
      // Reset button state
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
      }
    });
  }

  /**
   * Show lookup success and redirect to favorites view
   */
  showLookupSuccess(form, email, userData = null, favoritesData = null) {
    // Only save user data if we have complete information
    if (userData && userData.email && userData.name && userData.phone) {
      this.userInfo = userData;
      this.saveUserInfo();
    } else {
      // Check if we have valid user info in localStorage
      const storedUserInfo = this.getUserInfo();
      if (storedUserInfo && storedUserInfo.email && storedUserInfo.name && storedUserInfo.phone) {
        this.userInfo = storedUserInfo;
      } else {
        // No valid user info - don't proceed
        this.showLookupError(form, 'Unable to load complete account information. Please try again or contact support.');
        return;
      }
    }

    // Update favorites count display
    if (favoritesData && favoritesData.total_count > 0) {
      this.updateUI();
    }

    // Show success message
    const successDiv = document.createElement('div');
    successDiv.className = 'brag-book-gallery-form-success';
    const favCount = favoritesData?.total_count || 0;
    successDiv.textContent = `Account found! Loading ${favCount} favorite${favCount !== 1 ? 's' : ''}...`;
    form.appendChild(successDiv);

    // Redirect to favorites view after a short delay
    setTimeout(() => {
      if (window.bragBookGalleryApp && typeof window.bragBookGalleryApp.initializeFavoritesPage === 'function') {
        window.bragBookGalleryApp.initializeFavoritesPage();
      }
    }, 1000);
  }

  /**
   * Show lookup error message
   */
  showLookupError(form, message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'brag-book-gallery-form-error';
    errorDiv.textContent = message;
    form.appendChild(errorDiv);
  }

  /**
   * Clear lookup messages
   */
  clearLookupMessages(form) {
    const existingError = form.querySelector('.brag-book-gallery-form-error');
    const existingSuccess = form.querySelector('.brag-book-gallery-form-success');
    if (existingError) existingError.remove();
    if (existingSuccess) existingSuccess.remove();
  }

  /**
   * Show error message in form
   */
  showFormError(form, message) {
    // Clear existing messages
    this.clearLookupMessages(form);

    // Show error message in form
    const errorDiv = document.createElement('div');
    errorDiv.className = 'brag-book-gallery-form-error';
    errorDiv.textContent = message;
    form.appendChild(errorDiv);

    // Reset button state if needed
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = false;
    }
  }

  /**
   * Show detailed error message in form with structured information
   * @param {HTMLFormElement} form - The form element
   * @param {Object} errorInfo - Error information object
   * @param {string} errorInfo.title - Error title
   * @param {string} errorInfo.message - Main error message
   * @param {Array<string>} errorInfo.details - Array of detail strings
   */
  showDetailedFormError(form, errorInfo) {
    // Clear existing messages
    this.clearLookupMessages(form);

    // Create error container
    const errorDiv = document.createElement('div');
    errorDiv.className = 'brag-book-gallery-form-error brag-book-gallery-form-error--detailed';

    // Add title if provided
    if (errorInfo.title) {
      const titleElement = document.createElement('strong');
      titleElement.className = 'brag-book-gallery-form-error__title';
      titleElement.textContent = errorInfo.title;
      errorDiv.appendChild(titleElement);
    }

    // Add main message
    if (errorInfo.message) {
      const messageElement = document.createElement('p');
      messageElement.className = 'brag-book-gallery-form-error__message';
      messageElement.textContent = errorInfo.message;
      errorDiv.appendChild(messageElement);
    }

    // Add details list if provided
    if (errorInfo.details && errorInfo.details.length > 0) {
      const detailsList = document.createElement('ul');
      detailsList.className = 'brag-book-gallery-form-error__details';
      errorInfo.details.forEach(detail => {
        const listItem = document.createElement('li');
        listItem.textContent = detail;
        detailsList.appendChild(listItem);
      });
      errorDiv.appendChild(detailsList);
    }
    form.appendChild(errorDiv);

    // Reset button state if needed
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = false;
    }
  }

  /**
   * Parse server error response and show detailed error
   * @param {HTMLFormElement} form - The form element
   * @param {Object} response - Server response object
   * @param {string} action - Action being performed ('save', 'lookup', etc.)
   */
  parseAndShowDetailedError(form, response, action = 'save') {
    const errorMessage = response.data?.message || response.message || 'Unknown error occurred';

    // Categorize error types and provide helpful context
    let errorInfo = {
      title: 'Error',
      message: errorMessage,
      details: []
    };

    // Check for specific error patterns and enhance the message
    const lowerMessage = errorMessage.toLowerCase();
    if (lowerMessage.includes('security') || lowerMessage.includes('verification')) {
      errorInfo.title = 'Security Verification Failed';
      errorInfo.details = ['Your session may have expired', 'Try refreshing the page and submitting again', 'If the problem persists, clear your browser cache'];
    } else if (lowerMessage.includes('required fields') || lowerMessage.includes('fill in')) {
      errorInfo.title = 'Form Validation Error';
      errorInfo.details = ['All fields (Name, Email, Phone) are required', 'Make sure no fields are left empty'];
    } else if (lowerMessage.includes('valid email')) {
      errorInfo.title = 'Invalid Email Address';
      errorInfo.details = ['Please enter a valid email address', 'Example: yourname@example.com'];
    } else if (lowerMessage.includes('case not found') || lowerMessage.includes('not available')) {
      errorInfo.title = 'Case Not Available';
      errorInfo.details = ['This case may have been removed or is no longer available', 'The case might not be properly synced', 'Contact support if you believe this is an error'];
    } else if (lowerMessage.includes('api') && (lowerMessage.includes('token') || lowerMessage.includes('configuration'))) {
      errorInfo.title = 'API Configuration Error';
      errorInfo.message = 'There is a problem with the site\'s API configuration.';
      errorInfo.details = ['This is not an issue with your submission', 'Please contact the site administrator', 'Technical detail: API authentication failed'];
    } else if (lowerMessage.includes('http') || lowerMessage.includes('status')) {
      errorInfo.title = 'Server Communication Error';
      errorInfo.details = ['The server returned an unexpected response', 'This may be a temporary issue', 'Try again in a few moments', `Technical detail: ${errorMessage}`];
    } else if (lowerMessage.includes('timeout') || lowerMessage.includes('network')) {
      errorInfo.title = 'Network Error';
      errorInfo.details = ['Unable to reach the server', 'Check your internet connection', 'The server may be experiencing high traffic'];
    } else {
      // Generic error - provide the message and general troubleshooting
      errorInfo.title = `Failed to ${action === 'save' ? 'Save Favorite' : 'Lookup Favorites'}`;
      errorInfo.details = ['If this problem continues, try:', '• Refreshing the page', '• Clearing your browser cache', '• Trying again in a few minutes', '• Contacting support if the issue persists'];
    }
    this.showDetailedFormError(form, errorInfo);
  }

  /**
   * Validate email address format
   * @param {string} email - Email address to validate
   * @returns {boolean} - Whether the email is valid
   */
  isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  /**
   * Display a success notification to the user
   * @param {string} message - The message to display
   */
  showSuccessNotification(message) {
    this.showNotification(message, 'success');
  }

  /**
   * Display an error notification to the user
   * @param {string} message - The message to display
   */
  showErrorNotification(message) {
    this.showNotification(message, 'error');
  }

  /**
   * Display a notification to the user
   * @param {string} message - The message to display
   * @param {string} type - The type of notification (success or error)
   */
  showNotification(message, type = 'success') {
    // Get or create notification element
    let notification = document.getElementById('favoritesNotification');
    if (!notification) {
      notification = document.createElement('div');
      notification.id = 'favoritesNotification';
      notification.className = 'brag-book-gallery-favorites-notification';
      document.body.appendChild(notification);
    }

    // Update message, type, and show
    notification.textContent = message;
    notification.classList.remove('success', 'error');
    notification.classList.add('active', type);

    // Hide after 3 seconds (or 5 for errors)
    const hideDelay = type === 'error' ? 5000 : 3000;
    setTimeout(() => {
      notification.classList.remove('active', 'success', 'error');
    }, hideDelay);
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
        removeBtn.onclick = e => {
          e.stopPropagation();
          this.removeFavorite(itemId);
        };
        favoriteItem.appendChild(removeBtn);
        grid.appendChild(favoriteItem);
      });
    }
  }

  /**
   * Update all UI elements that display favorites information
   */
  updateUI() {
    // Update favorites count displays throughout the page
    const countElements = document.querySelectorAll('[data-favorites-count]');
    const count = this.favorites.size;
    countElements.forEach(countElement => {
      // Check format: text format ("N favorites"), tiles (N), or default "(N)"
      const format = countElement.dataset.favoritesFormat;
      if (format === 'text') {
        countElement.textContent = `${count} favorite${count !== 1 ? 's' : ''}`;
      } else {
        const isTilesView = countElement.closest('.brag-book-gallery-favorites-link--tiles');
        countElement.textContent = isTilesView ? count : `(${count})`;
      }
    });

    // Update all favorite button states (including dynamically loaded ones)
    this.updateAllButtonStates();

    // Update favorites grid in sidebar
    this.updateFavoritesDisplay();
  }

  /**
   * Update all favorite button states to reflect current favorites
   * This handles dynamically loaded content like carousels
   */
  updateAllButtonStates() {
    // Get all favorite buttons on the page
    const allButtons = document.querySelectorAll('[data-favorited]');
    allButtons.forEach(button => {
      let itemId = '';

      // Use ONLY procedureCaseId from parent container — never currentProcedureId
      // (currentProcedureId is shared across all cards on taxonomy pages)
      const caseCard = button.closest('.brag-book-gallery-case-card, .brag-book-gallery-carousel-item, .brag-book-gallery-case-detail-view');
      if (caseCard) {
        itemId = caseCard.dataset.procedureCaseId || '';
      }

      // Fallback to button's own data-item-id (set by PHP to the junction ID)
      if (!itemId) {
        itemId = button.dataset.itemId || '';
        if (itemId) {
          const matches = itemId.match(/(\d+)/);
          if (matches) {
            itemId = matches[1];
          }
        }
      }

      // Update button state based on whether item is favorited
      if (itemId && this.favorites.has(itemId)) {
        button.dataset.favorited = 'true';
      } else if (itemId) {
        button.dataset.favorited = 'false';
      }
    });
  }

  /**
   * Load favorites from localStorage and update button states
   */
  loadFromStorage() {
    try {
      const stored = localStorage.getItem(this.options.storageKey);
      if (stored) {
        const items = JSON.parse(stored);
        this.favorites = new Set(items);

        // Update UI to reflect loaded favorites
        items.forEach(itemId => {
          // Find all buttons for this item and mark as favorited
          const buttons = document.querySelectorAll(`[data-item-id="${itemId}"], [data-case-id="${itemId}"]`);
          buttons.forEach(button => {
            if (button.dataset.favorited !== undefined) {
              button.dataset.favorited = 'true';
            }
          });
        });
      }
    } catch (e) {
      console.error('Failed to load favorites from storage:', e);
    }
  }

  /**
   * Save favorites to localStorage
   */
  saveToStorage() {
    try {
      localStorage.setItem(this.options.storageKey, JSON.stringify([...this.favorites]));
    } catch (e) {
      console.error('Failed to save favorites to storage:', e);
    }
  }

  /**
   * Load user information from localStorage
   */
  loadUserInfo() {
    try {
      const stored = localStorage.getItem(this.options.userInfoKey);
      if (stored) {
        this.userInfo = JSON.parse(stored);
        this.hasShownDialog = true; // Skip dialog if we already have user info
      }
    } catch (e) {
      console.error('Failed to load user info from storage:', e);
    }
  }

  /**
   * Save user information to localStorage
   */
  saveUserInfo() {
    // Only save if we have complete user information
    if (!this.userInfo || !this.userInfo.email || !this.userInfo.name || !this.userInfo.phone) {
      console.warn('Cannot save incomplete user info to localStorage:', this.userInfo);
      return;
    }
    try {
      localStorage.setItem(this.options.userInfoKey, JSON.stringify(this.userInfo));
    } catch (e) {
      console.error('Failed to save user info to storage:', e);
    }
  }

  /**
   * Get the current favorites set
   * @returns {Set<string>} Set of favorited item IDs
   */
  getFavorites() {
    return this.favorites;
  }

  /**
   * Clear all favorites and update UI
   */
  clear() {
    this.favorites.clear();
    if (this.options.persistToStorage) {
      this.saveToStorage();
    }
    this.updateUI();
  }

  /**
   * Get user information from storage
   * @returns {Object|null} User information or null if not set
   */
  getUserInfo() {
    if (this.userInfo) {
      return this.userInfo;
    }

    // Try to get from localStorage
    try {
      const stored = localStorage.getItem(this.options.userInfoKey);
      if (stored) {
        this.userInfo = JSON.parse(stored);
        return this.userInfo;
      }
    } catch (e) {
      console.error('Failed to retrieve user info from localStorage:', e);
    }
    return null;
  }

  /**
   * Refresh event listeners for favorite buttons
   * Useful after dynamically adding content
   */
  refreshEventListeners() {
    // Re-scan for favorite buttons and set up event listeners
    const favoriteButtons = document.querySelectorAll('.brag-book-gallery-favorite-button');
    favoriteButtons.forEach(button => {
      // Remove existing listeners to avoid duplicates
      button.removeEventListener('click', this.handleFavoriteClick);

      // Add new listener
      button.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        this.toggleFavorite(button);
      });
    });
  }
}
/* harmony default export */ __webpack_exports__["default"] = (FavoritesManager);

/***/ }),

/***/ "./src/js/modules/filter-system.js":
/*!*****************************************!*\
  !*** ./src/js/modules/filter-system.js ***!
  \*****************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/**
 * Filter System Component
 * Manages expandable filter groups with URL routing
 * Handles procedure filtering, browser history, and AJAX content loading
 * Supports both JavaScript-based filtering and URL navigation modes
 */
class FilterSystem {
  /**
   * Initialize the filter system
   * @param {HTMLElement} container - The filter container element
   * @param {Object} options - Configuration options
   * @param {string} options.mode - Filter mode: 'javascript' or 'navigation' (default: 'javascript')
   * @param {string} options.baseUrl - Base URL for navigation (default: '/gallery')
   * @param {boolean} options.closeOthersOnOpen - Close other filters when opening one (default: true)
   * @param {Function} options.onFilterChange - Callback when filters change
   * @param {Function} options.onNavigate - Callback for URL navigation
   */
  constructor(container, options = {}) {
    // Core DOM elements and data structures
    this.container = container;
    this.filterHeaders = container?.querySelectorAll('.brag-book-gallery-nav-button');
    this.activeFilters = new Map(); // Currently active filters
    this.categories = new Map(); // Available filter categories
    this.procedures = new Map(); // Available procedures by category

    // Store original SEO data for restoration when clearing filters
    this.originalTitle = document.title;
    this.originalDescription = document.querySelector('meta[name="description"]')?.content || '';

    // Configuration options with defaults
    this.options = {
      mode: options.mode || 'javascript',
      // Filter mode
      baseUrl: options.baseUrl || '/gallery',
      // Base URL for navigation
      closeOthersOnOpen: options.closeOthersOnOpen === true,
      // Accordion behavior - disabled by default
      onFilterChange: options.onFilterChange || (() => {}),
      // Filter change callback
      onNavigate: options.onNavigate || (url => {
        window.location.href = url;
      }),
      // Navigation callback
      ...options
    };

    // Initialize only if container exists
    if (this.container) {
      this.init();
    }
  }

  /**
   * Initialize the filter system - index filters, set up events, and load initial state
   */
  init() {
    // Build internal filter index for fast lookups
    this.indexFilters();

    // Set up all event handlers
    this.setupEventListeners();

    // Load any existing filter state from URL
    this.loadStateFromUrl();
  }

  /**
   * Build an index of all available filters for fast lookups
   */
  indexFilters() {
    // Find all category groups in the filter container
    const categoryGroups = this.container?.querySelectorAll('[data-category]');

    // Process each category group
    categoryGroups?.forEach(group => {
      const category = group.dataset.category;
      // Create category entry if it doesn't exist
      if (!this.categories.has(category)) {
        this.categories.set(category, {
          name: category,
          procedures: new Set(),
          element: group
        });
      }

      // Index all procedure links within this category
      const filterLinks = group.querySelectorAll('.brag-book-gallery-nav-link');
      filterLinks.forEach(link => {
        const procedure = link.dataset.procedure;
        const category = link.dataset.category;

        // Create procedure data if both procedure and category exist
        if (procedure && category) {
          const procedureData = {
            id: procedure,
            category: category,
            count: parseInt(link.dataset.procedureCount || '0'),
            element: link.parentElement,
            link: link
          };

          // Store procedure data with composite key
          this.procedures.set(`${category}:${procedure}`, procedureData);
          this.categories.get(category).procedures.add(procedure);
        }
      });
    });
  }

  /**
   * Set up all event listeners for filter interactions and browser navigation
   */
  setupEventListeners() {
    // No event listeners needed - nav links now function as normal anchor links
    // This allows WordPress taxonomy pages to load naturally
  }

  // Filter toggle methods removed - native details/summary elements handle this automatically

  /**
   * Handle click on a procedure filter link
   * @param {HTMLElement} link - The filter link that was clicked
   */
  handleFilterClick(link) {
    // Extract filter data from the clicked link
    const category = link.dataset.category;
    const procedure = link.dataset.procedure;
    const procedureIds = link.dataset.procedureIds;
    const count = parseInt(link.dataset.procedureCount || '0');
    const hasNudity = link.dataset.nudity === 'true'; // Content warning flag

    // Clear all active filters first
    this.activeFilters.clear();

    // Remove active class from all links
    this.container?.querySelectorAll('.brag-book-gallery-nav-link').forEach(filterLink => {
      filterLink.classList.remove('brag-book-gallery-active');
    });

    // Add active class to clicked link and its parent item
    link.classList.add('brag-book-gallery-active');
    const filterItem = link.closest('.brag-book-gallery-nav-list-submenu__item');
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

    // Badge updates handled by demographic filters only
    // this.updateFilterBadges();

    // Get the base URL from the gallery wrapper data attribute
    const galleryWrapper = document.querySelector('.brag-book-gallery-wrapper');
    let basePath = galleryWrapper?.dataset.baseUrl || window.location.pathname;

    // If no base URL in data attribute, try to extract from current path
    if (!galleryWrapper?.dataset.baseUrl && basePath.match(/\/[^\/]+\/?$/)) {
      // Remove the existing filter segment (just procedure now)
      basePath = basePath.replace(/\/[^\/]+\/?$/, '');
    }

    // Create URL appending to current path: /before-after/procedure/ (no category)
    const filterUrl = `${basePath}/${procedure}/`.replace(/\/+/g, '/');

    // Update browser URL
    window.history.pushState({
      category,
      procedure,
      procedureIds,
      basePath,
      hasNudity
    }, '', filterUrl);

    // Load filtered content via AJAX
    this.loadFilteredContent(category, procedure, procedureIds, hasNudity);

    // Note: Nudity warnings are now handled at render time based on data-nudity attribute
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
      this.activeFilters.forEach(filter => {
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
    this.container?.querySelectorAll('.brag-book-gallery-nav-link').forEach(filterLink => {
      filterLink.classList.remove('brag-book-gallery-active');
    });

    // Find and activate the matching filter link (match by procedure primarily)
    const filterLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedure}"]`);
    if (filterLink) {
      filterLink.classList.add('brag-book-gallery-active');

      // Store in active filters
      this.activeFilters.set(`${category}:${procedure}`, {
        category: category,
        procedure: procedure,
        procedureIds: filterLink.dataset.procedureIds,
        count: parseInt(filterLink.dataset.procedureCount || '0')
      });

      // Badge updates handled by demographic filters only
      // this.updateFilterBadges();
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
      const filterLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${initialProcedure}"]`);
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
      const filterLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedure}"]`);
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
    this.activeFilters.forEach(filter => {
      if (filter.category === category) {
        filters.push(filter);
      }
    });
    return filters;
  }
  getFiltersByProcedure(procedure) {
    const filters = [];
    this.activeFilters.forEach(filter => {
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
    const filterLinks = this.container?.querySelectorAll('.brag-book-gallery-nav-link');
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

  /**
   * Load filtered gallery content via direct API (optimized) with AJAX fallback
   * @param {string} category - The filter category
   * @param {string} procedure - The procedure slug
   * @param {string} procedureIds - Comma-separated procedure IDs
   * @param {boolean} hasNudity - Whether content has nudity warning
   */
  loadFilteredContent(category, procedure, procedureIds, hasNudity = false) {
    const galleryContent = document.getElementById('gallery-content');
    if (!galleryContent) return;

    // Show loading state
    galleryContent.innerHTML = '<div class="brag-book-gallery-loading">Loading filtered results...</div>';

    // Get the proper procedure display name from the active link
    let procedureName = procedure;
    const activeLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedure}"]`);
    if (activeLink) {
      const label = activeLink.querySelector('.brag-book-gallery-filter-option-label');
      if (label) {
        procedureName = label.textContent.trim();
      }
    }

    // Try direct API first (optimized), then fallback to AJAX
    this.loadFilteredContentDirectly(procedure, procedureIds, hasNudity, procedureName).then(result => {
      if (result.success) {
        // Direct API succeeded
        this.updateGalleryContent(result.html);

        // Update page title and meta description if SEO data is provided
        if (result.seo) {
          if (result.seo.title) {
            document.title = result.seo.title;
          }
          if (result.seo.description) {
            let metaDescription = document.querySelector('meta[name="description"]');
            if (!metaDescription) {
              metaDescription = document.createElement('meta');
              metaDescription.name = 'description';
              document.head.appendChild(metaDescription);
            }
            metaDescription.content = result.seo.description;
          }
        }

        // Carousels removed - no reinitialization needed

        // Regenerate procedure filters after content loads
        setTimeout(function () {
          regenerateProcedureFilters();
        }, 100);

        // Load more buttons are handled by global onclick handlers, no rebinding needed

        // Update URL if it's a procedure filter
        if (category === 'procedure' && procedure) {
          this.updateUrlForProcedure(procedure);
        }

        // Scroll to top of content
        galleryContent.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });

        // Badge updates handled by demographic filters only
        // this.updateFilterBadges();
      } else {
        // Direct API failed, fallback to AJAX
        this.loadFilteredContentViaAjax(category, procedure, procedureIds, hasNudity, procedureName, galleryContent);
      }
    }).catch(error => {
      this.loadFilteredContentViaAjax(category, procedure, procedureIds, hasNudity, procedureName, galleryContent);
    });
  }

  /**
   * Load filtered gallery content directly from API (optimized method)
   * @param {string} procedure - The procedure slug
   * @param {string} procedureIds - Comma-separated procedure IDs
   * @param {boolean} hasNudity - Whether content has nudity warning
   * @param {string} procedureName - Display name for SEO
   * @returns {Promise} Promise that resolves with result object
   */
  async loadFilteredContentDirectly(procedure, procedureIds, hasNudity, procedureName) {
    try {
      // Get API configuration
      const apiToken = window.bragBookGalleryConfig?.apiToken || '';
      const websitePropertyId = window.bragBookGalleryConfig?.websitePropertyId || '';
      const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
      const nonce = window.bragBookGalleryConfig?.nonce || '';
      if (!apiToken || !websitePropertyId || !ajaxUrl || !nonce) {
        return {
          success: false,
          error: 'Missing API configuration'
        };
      }

      // Build API request body similar to PHP implementation
      const requestBody = {
        apiTokens: [apiToken],
        websitePropertyIds: [parseInt(websitePropertyId)],
        count: 1
      };

      // Add procedure IDs if provided
      if (procedureIds) {
        const procedureIdsArray = procedureIds.split(',').map(id => parseInt(id)).filter(id => !isNaN(id));
        if (procedureIdsArray.length > 0) {
          requestBody.procedureIds = procedureIdsArray;
        }
      }

      // Fetch all pages like the PHP implementation using CORS-safe proxy
      let allCases = [];
      let page = 1;
      const maxPages = 20;
      while (page <= maxPages) {
        requestBody.count = page;

        // Use WordPress AJAX proxy to avoid CORS issues
        const formData = new FormData();
        formData.append('action', 'brag_book_api_proxy');
        formData.append('nonce', nonce);
        formData.append('endpoint', '/api/plugin/combine/cases');
        formData.append('method', 'POST');
        formData.append('body', JSON.stringify(requestBody));
        formData.append('timeout', '8');
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

        const response = await fetch(ajaxUrl, {
          method: 'POST',
          body: formData,
          signal: controller.signal
        });
        clearTimeout(timeoutId);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (!result.success || !result.data || !result.data.data) {
          throw new Error(result.data?.message || 'API proxy request failed');
        }
        const pageData = result.data.data;
        if (pageData && pageData.data && Array.isArray(pageData.data) && pageData.data.length > 0) {
          allCases = allCases.concat(pageData.data);

          // If we got less than 10 cases, we've reached the end
          if (pageData.data.length < 10) {
            break;
          }
          page++;
        } else {
          break;
        }
      }

      // Generate HTML on frontend (similar to PHP implementation)
      const html = this.generateFilteredGalleryHTML(allCases, procedureName, procedure, procedureIds);

      // Generate SEO data
      const seo = this.generateSEOData(procedureName);
      return {
        success: true,
        html: html,
        totalCount: allCases.length,
        procedureName: procedureName,
        seo: seo
      };
    } catch (error) {
      console.error('Direct API error:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Fallback method using original AJAX approach
   * @param {string} category - The filter category
   * @param {string} procedure - The procedure slug
   * @param {string} procedureIds - Comma-separated procedure IDs
   * @param {boolean} hasNudity - Whether content has nudity warning
   * @param {string} procedureName - Display name for SEO
   * @param {HTMLElement} galleryContent - Gallery content container
   */
  loadFilteredContentViaAjax(category, procedure, procedureIds, hasNudity, procedureName, galleryContent) {
    // Get AJAX configuration
    const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.bragBookGalleryConfig?.nonce || '';

    // Prepare request data
    const formData = new FormData();
    formData.append('action', 'brag_book_gallery_load_filtered_gallery');
    formData.append('nonce', nonce);
    formData.append('procedure_name', procedureName);
    formData.append('procedure_slug', procedure);
    formData.append('procedure_ids', procedureIds || '');
    formData.append('procedure_id', procedureIds?.split(',')[0] || '');
    formData.append('has_nudity', hasNudity ? '1' : '0');

    // Make AJAX request
    fetch(ajaxUrl, {
      method: 'POST',
      body: formData
    }).then(response => response.json()).then(result => {
      if (result.success && result.data?.html) {
        this.updateGalleryContent(result.data.html);

        // Update page title and meta description if SEO data is provided
        if (result.data.seo) {
          if (result.data.seo.title) {
            document.title = result.data.seo.title;
          }
          if (result.data.seo.description) {
            let metaDescription = document.querySelector('meta[name="description"]');
            if (!metaDescription) {
              metaDescription = document.createElement('meta');
              metaDescription.name = 'description';
              document.head.appendChild(metaDescription);
            }
            metaDescription.content = result.data.seo.description;
          }
        }

        // Carousels removed - no reinitialization needed

        // Regenerate procedure filters after content loads
        setTimeout(function () {
          regenerateProcedureFilters();
        }, 100);

        // Load more buttons are handled by global onclick handlers, no rebinding needed

        // Update URL if it's a procedure filter
        if (category === 'procedure' && procedure) {
          this.updateUrlForProcedure(procedure);
        }

        // Scroll to top of content
        galleryContent.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });

        // Badge updates handled by demographic filters only
        // this.updateFilterBadges();
      } else {
        galleryContent.innerHTML = '<div class="brag-book-gallery-error">No results found for the selected filter.</div>';
      }
    }).catch(error => {
      console.error('AJAX fallback error:', error);
      galleryContent.innerHTML = '<div class="brag-book-gallery-error">Failed to load filtered content. Please try again.</div>';
    });
  }

  /**
   * Generate HTML for filtered gallery results (frontend version of PHP implementation)
   * @param {Array} cases - Array of case data from API
   * @param {string} procedureName - Display name for procedure
   * @param {string} procedure - Procedure slug
   * @param {string} procedureIds - Comma-separated procedure IDs
   * @returns {string} Generated HTML
   */
  generateFilteredGalleryHTML(cases, procedureName, procedure, procedureIds) {
    // Set pagination parameters
    const itemsPerPage = 10;

    // Store all cases in global cache for pagination
    if (typeof allCasesData !== 'undefined') {
      allCasesData = cases;
      currentDisplayedCases = Math.min(itemsPerPage, cases.length);
    }

    // Transform case data to match what HTML rendering expects
    const transformedCases = cases.map(caseData => {
      const transformedCase = {
        ...caseData
      };

      // Extract main image from photoSets
      transformedCase.mainImageUrl = '';
      if (caseData.photoSets && Array.isArray(caseData.photoSets) && caseData.photoSets.length > 0) {
        const firstPhotoset = caseData.photoSets[0];
        transformedCase.mainImageUrl = firstPhotoset.postProcessedImageLocation || firstPhotoset.beforeLocationUrl || firstPhotoset.afterLocationUrl1 || '';
      }

      // Extract procedure title
      transformedCase.procedureTitle = 'Unknown Procedure';
      if (caseData.procedures && Array.isArray(caseData.procedures) && caseData.procedures.length > 0) {
        transformedCase.procedureTitle = caseData.procedures[0].name || 'Unknown Procedure';
      }
      return transformedCase;
    }).filter(caseData => caseData.mainImageUrl || caseData.id);

    // Start building HTML - create the complete structure matching PHP
    let html = '';

    // Main title (matching PHP structure)
    const displayName = this.formatProcedureDisplayName(procedureName);
    html += `<h1 class="brag-book-gallery-content-title"><strong>${this.escapeHtml(displayName)}</strong> Before &amp; After Gallery</h1>`;

    // Complete controls section matching PHP structure
    html += '<div class="brag-book-gallery-controls">';
    html += '<div class="brag-book-gallery-controls-left">';

    // Filter dropdown section
    html += '<details class="brag-book-gallery-filter-dropdown" id="procedure-filters-details" data-initialized="true">';
    html += '<summary class="brag-book-gallery-filter-dropdown__toggle">';
    html += '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">';
    html += '<path d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"></path>';
    html += '</svg>';
    html += '<span>Filters</span>';
    html += '</summary>';
    html += '<div class="brag-book-gallery-filter-dropdown__panel">';
    html += '<div class="brag-book-gallery-filter-content">';
    html += '<div class="brag-book-gallery-filter-section">';
    html += '<div id="brag-book-gallery-filters">';

    // Generate filter sections based on available data
    html += this.generateProcedureFilterSections(transformedCases);
    html += '</div>'; // Close brag-book-gallery-filters
    html += '</div>'; // Close filter-section
    html += '</div>'; // Close filter-content
    html += '<div class="brag-book-gallery-filter-actions">';
    html += '<button class="brag-book-gallery-button brag-book-gallery-button--apply" onclick="applyProcedureFilters()">Apply Filters</button>';
    html += '<button class="brag-book-gallery-button brag-book-gallery-button--clear" onclick="clearProcedureFilters()">Clear All</button>';
    html += '</div>';
    html += '</div>'; // Close filter-dropdown__panel
    html += '</details>';

    // Active filters section (will be populated when demographic filters are applied)
    html += '<div class="brag-book-gallery-active-filters" style="display: none;"></div>';
    html += '</div>'; // Close controls-left

    // Grid selector section
    html += '<div class="brag-book-gallery-grid-selector">';
    html += '<span class="brag-book-gallery-grid-label">View:</span>';
    html += '<div class="brag-book-gallery-grid-buttons">';
    html += '<button class="brag-book-gallery-grid-btn" data-columns="2" onclick="updateGridLayout(2)" aria-label="View in 2 columns">';
    html += '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">';
    html += '<rect x="1" y="1" width="6" height="6"></rect><rect x="9" y="1" width="6" height="6"></rect>';
    html += '<rect x="1" y="9" width="6" height="6"></rect><rect x="9" y="9" width="6" height="6"></rect>';
    html += '</svg>';
    html += '<span class="sr-only">2 Columns</span>';
    html += '</button>';
    html += '<button class="brag-book-gallery-grid-btn active" data-columns="3" onclick="updateGridLayout(3)" aria-label="View in 3 columns">';
    html += '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">';
    html += '<rect x="1" y="1" width="4" height="4"></rect><rect x="6" y="1" width="4" height="4"></rect><rect x="11" y="1" width="4" height="4"></rect>';
    html += '<rect x="1" y="6" width="4" height="4"></rect><rect x="6" y="6" width="4" height="4"></rect><rect x="11" y="6" width="4" height="4"></rect>';
    html += '<rect x="1" y="11" width="4" height="4"></rect><rect x="6" y="11" width="4" height="4"></rect><rect x="11" y="11" width="4" height="4"></rect>';
    html += '</svg>';
    html += '<span class="sr-only">3 Columns</span>';
    html += '</button>';
    html += '</div>';
    html += '</div>';
    html += '</div>'; // Close controls

    // Main content sections (matching PHP structure)
    html += '<div class="brag-book-gallery-sections" id="gallery-sections">';

    // Filtered results section
    html += '<div class="brag-book-gallery-section" aria-label="Filtered Gallery Results">';

    // Add filter results message for filtered content
    const totalCases = transformedCases.length;
    const displayedCases = Math.min(itemsPerPage, totalCases);
    html += `<div class="brag-book-gallery-filter-results" style="display: block;">Filter applied: Showing ${displayedCases} of ${totalCases} matching procedure${totalCases !== 1 ? 's' : ''}</div>`;

    // Add filter badges container
    html += '<div class="brag-book-gallery-filter-badges" data-action="filter-badges"></div>';

    // Add cases container structure (matching PHP exactly)
    html += '<div class="brag-book-gallery-case-grid masonry-layout" data-columns="3">';

    // Only display the first 10 cases initially for pagination
    const casesToDisplay = transformedCases.slice(0, itemsPerPage);
    casesToDisplay.forEach(caseData => {
      html += this.generateCaseHTML(caseData);
    });
    html += '</div>'; // Close case grid

    // Add Load More button if there are more cases than displayed
    if (transformedCases.length > itemsPerPage) {
      html += '<div class="brag-book-gallery-load-more-container">';
      html += `<button type="button" class="brag-book-gallery-button brag-book-gallery-button--load-more" `;
      html += `data-procedure-name="${this.escapeHtml(procedureName)}" `;
      html += `data-start-page="2" data-procedure-ids="${this.escapeHtml(procedureIds)}" `;
      html += `onclick="loadMoreCasesFromCache(this)">Load More</button>`;
      html += '</div>';
    }
    html += '</div>'; // Close section
    html += '</div>'; // Close sections

    return html;
  }

  /**
   * Update the gallery content area with filtered results
   * Replaces the main content while preserving sidebar and structure
   */
  updateGalleryContent(html) {
    const galleryContent = document.getElementById('gallery-content');
    if (galleryContent) {
      // Replace only the main content area, preserving the overall structure
      galleryContent.innerHTML = html;

      // Hide any filter results messages since this is procedure navigation, not demographic filtering
      const filterResults = galleryContent.querySelector('.brag-book-gallery-filter-results');
      if (filterResults) {
        filterResults.style.display = 'none';
      }

      // Set up event listeners for the new filter checkboxes
      this.setupFilterEventListeners();

      // Scroll to gallery wrapper after loading filtered content
      this.scrollToGalleryWrapper();

      // Note: Nudity warnings are now handled at render time based on data-nudity attribute
    }
  }

  /**
   * Set up event listeners for filter checkboxes
   */
  setupFilterEventListeners() {
    const filterCheckboxes = document.querySelectorAll('.brag-book-gallery-filter-option input[type="checkbox"]');

    // Clean up any existing procedure badges first
    const activeFiltersSection = document.querySelector('.brag-book-gallery-active-filters');
    if (activeFiltersSection) {
      activeFiltersSection.style.display = 'none';
      activeFiltersSection.innerHTML = '';
    }
    filterCheckboxes.forEach(checkbox => {
      // Remove any existing event listeners to avoid duplicates
      checkbox.removeEventListener('change', this.handleFilterChange);

      // Add the event listener
      checkbox.addEventListener('change', this.handleFilterChange.bind(this));
    });
  }

  /**
   * Handle filter checkbox change events
   */
  handleFilterChange(event) {
    if (typeof window.applyProcedureFilters === 'function') {
      window.applyProcedureFilters();
    } else {
      console.error('window.applyProcedureFilters is not available');
    }
  }

  /**
   * Generate HTML for a single case (matches PHP structure exactly)
   * @param {Object} caseData - Case data from API
   * @returns {string} Generated HTML for case
   */
  generateCaseHTML(caseData) {
    const caseId = caseData.id || '';
    const procedureTitle = caseData.procedureTitle || 'Unknown Procedure';

    // Prepare data attributes for filtering (matching PHP prepare_case_data_attributes)
    let dataAttrs = 'data-card="true"';
    dataAttrs += ` data-case-id="${this.escapeHtml(caseId)}"`;
    if (caseData.age) {
      dataAttrs += ` data-age="${this.escapeHtml(caseData.age)}"`;
    }
    if (caseData.gender) {
      dataAttrs += ` data-gender="${this.escapeHtml(caseData.gender.toLowerCase())}"`;
    }
    if (caseData.ethnicity) {
      dataAttrs += ` data-ethnicity="${this.escapeHtml(caseData.ethnicity.toLowerCase())}"`;
    }

    // Get procedure IDs
    const procedureIds = caseData.procedureIds ? caseData.procedureIds.join(',') : '';
    if (procedureIds) {
      dataAttrs += ` data-procedure-ids="${this.escapeHtml(procedureIds)}"`;
    }

    // Get current procedure context from active nav link
    const activeProcedureLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
    const currentProcedureId = activeProcedureLink?.dataset.procedureId || '';
    const currentTermId = activeProcedureLink?.dataset.termId || '';
    if (currentProcedureId) {
      dataAttrs += ` data-current-procedure-id="${this.escapeHtml(currentProcedureId)}"`;
    }
    if (currentTermId) {
      dataAttrs += ` data-current-term-id="${this.escapeHtml(currentTermId)}"`;
    }

    // Build case URL (matching PHP structure)
    const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'gallery';
    const procedureSlug = this.extractProcedureSlugFromUrl() || 'case';
    const seoSuffix = caseData.caseDetails && caseData.caseDetails[0] && caseData.caseDetails[0].seoSuffixUrl || caseId;
    const caseUrl = `/${gallerySlug}/${procedureSlug}/${seoSuffix}/`;

    // Get image URL (matching PHP logic)
    let imageUrl = '';
    if (caseData.photoSets && Array.isArray(caseData.photoSets) && caseData.photoSets.length > 0) {
      const firstPhoto = caseData.photoSets[0];
      imageUrl = firstPhoto.postProcessedImageLocation || firstPhoto.afterLocationUrl1 || firstPhoto.beforeLocationUrl || firstPhoto.afterPhoto || firstPhoto.beforePhoto || '';
    }

    // Start building HTML (matching PHP structure exactly)
    let html = `<article class="brag-book-gallery-case-card" ${dataAttrs}>`;

    // Case images section (matching PHP structure)
    html += '<div class="brag-book-gallery-case-images single-image">';
    html += '<div class="brag-book-gallery-image-container">';

    // Skeleton loader
    html += '<div class="brag-book-gallery-skeleton-loader" style="display: none;"></div>';

    // Favorites button (matching PHP structure)
    // Use current procedure ID for favorites, fallback to first procedure ID, then case ID
    const favoriteItemId = currentProcedureId || procedureIds.split(',')[0] || caseId;
    html += '<div class="brag-book-gallery-item-actions">';
    html += `<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="${this.escapeHtml(favoriteItemId)}" aria-label="Add to favorites">`;
    html += '<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">';
    html += '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>';
    html += '</svg>';
    html += '</button>';
    html += '</div>';

    // Case link with image (matching PHP structure)
    html += `<a href="${this.escapeHtml(caseUrl)}" class="brag-book-gallery-case-card-link" data-case-id="${this.escapeHtml(caseId)}" data-procedure-ids="${this.escapeHtml(procedureIds)}">`;
    if (imageUrl) {
      html += '<picture class="brag-book-gallery-picture">';
      html += `<img src="${this.escapeHtml(imageUrl)}" alt="${this.escapeHtml(procedureTitle)} - Case ${this.escapeHtml(caseId)}" loading="lazy" data-image-type="single" data-image-url="${this.escapeHtml(imageUrl)}" onload="this.closest('.brag-book-gallery-image-container').querySelector('.brag-book-gallery-skeleton-loader').style.display='none';">`;
      html += '</picture>';
    }
    html += '</a>'; // Close case link

    // Add nudity warning only if the active procedure has data-nudity="true"
    const activeLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
    const shouldAddNudityWarning = activeLink && activeLink.dataset.nudity === 'true';
    if (shouldAddNudityWarning) {
      html += '<div class="brag-book-gallery-nudity-warning">';
      html += '<div class="brag-book-gallery-nudity-warning-content">';
      html += '<p class="brag-book-gallery-nudity-warning-title">Nudity Warning</p>';
      html += '<p class="brag-book-gallery-nudity-warning-caption">This procedure may contain nudity or sensitive content. Click to proceed if you wish to view.</p>';
      html += '<button class="brag-book-gallery-nudity-warning-button" type="button">Proceed</button>';
      html += '</div>';
      html += '</div>';
    }
    html += '</div>'; // Close image-container
    html += '</div>'; // Close case-images

    // Case details section (matching PHP structure)
    html += '<details class="brag-book-gallery-case-card-details">';
    html += '<summary class="brag-book-gallery-case-card-summary">';

    // Summary info
    html += '<div class="brag-book-gallery-case-card-summary-info">';
    html += `<span class="brag-book-gallery-case-card-summary-info__name">${this.escapeHtml(procedureTitle)}</span>`;
    html += `<span class="brag-book-gallery-case-card-summary-info__case-number">Case #${this.escapeHtml(caseId)}</span>`;
    html += '</div>';

    // Summary details
    html += '<div class="brag-book-gallery-case-card-summary-details">';
    html += '<p class="brag-book-gallery-case-card-summary-details__more">';
    html += '<strong>More Details</strong>';
    html += '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
    html += '<path d="M444-288h72v-156h156v-72H516v-156h-72v156H288v72h156v156Zm36.28 192Q401-96 331-126t-122.5-82.5Q156-261 126-330.96t-30-149.5Q96-560 126-629.5q30-69.5 82.5-122T330.96-834q69.96-30 149.5-30t149.04 30q69.5 30 122 82.5T834-629.28q30 69.73 30 149Q864-401 834-331t-82.5 122.5Q699-156 629.28-126q-69.73 30-149 30Z"></path>';
    html += '</svg>';
    html += '</p>';
    html += '</div>';
    html += '</summary>';

    // Details content
    html += '<div class="brag-book-gallery-case-card-details-content">';
    html += '<p class="brag-book-gallery-case-card-details-content__title">Procedures Performed:</p>';
    html += '<ul class="brag-book-gallery-case-card-procedures-list">';

    // Generate procedure list
    if (caseData.procedures && Array.isArray(caseData.procedures) && caseData.procedures.length > 0) {
      caseData.procedures.forEach(procedure => {
        html += `<li class="brag-book-gallery-case-card-procedures-list__item">${this.escapeHtml(procedure.name || 'Unknown Procedure')}</li>`;
      });
    } else {
      html += `<li class="brag-book-gallery-case-card-procedures-list__item">${this.escapeHtml(procedureTitle)}</li>`;
    }
    html += '</ul>';
    html += '</div>'; // Close details-content
    html += '</details>'; // Close details
    html += '</article>'; // Close article

    return html;
  }

  /**
   * Extract procedure slug from current URL
   */
  extractProcedureSlugFromUrl() {
    const pathSegments = window.location.pathname.split('/').filter(s => s);
    const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'gallery';

    // Find gallery slug position and get the next segment as procedure slug
    const galleryIndex = pathSegments.indexOf(gallerySlug.replace(/^\/+/, ''));
    if (galleryIndex >= 0 && galleryIndex + 1 < pathSegments.length) {
      const procedureSlug = pathSegments[galleryIndex + 1];
      // Make sure it's not a case ID (numeric)
      if (!/^\d+$/.test(procedureSlug)) {
        return procedureSlug;
      }
    }
    return null;
  }

  /**
   * Generate procedure filters from DOM case cards
   * Reads data attributes from case cards already on the page
   */
  generateFiltersFromDOMCards() {
    // Find all case cards on the page
    const caseCards = document.querySelectorAll('.brag-book-gallery-case-card');
    if (caseCards.length === 0) {
      return '';
    }

    // Collect filter data from case card data attributes
    const filterData = {
      age: new Set(),
      gender: new Set(),
      ethnicity: new Set(),
      height: new Set(),
      weight: new Set(),
      procedureDetails: new Map() // Map of detail name -> Set of values
    };

    // Extract filter values from data attributes
    caseCards.forEach(card => {
      // Age
      const age = card.dataset.age;
      if (age) {
        const ageNum = parseInt(age);
        if (ageNum >= 18 && ageNum < 25) filterData.age.add('18-24');else if (ageNum >= 25 && ageNum < 35) filterData.age.add('25-34');else if (ageNum >= 35 && ageNum < 45) filterData.age.add('35-44');else if (ageNum >= 45 && ageNum < 55) filterData.age.add('45-54');else if (ageNum >= 55 && ageNum < 65) filterData.age.add('55-64');else if (ageNum >= 65) filterData.age.add('65+');
      }

      // Gender
      const gender = card.dataset.gender;
      if (gender) {
        filterData.gender.add(gender.toLowerCase());
      }

      // Ethnicity
      const ethnicity = card.dataset.ethnicity;
      if (ethnicity) {
        filterData.ethnicity.add(ethnicity);
      }

      // Height
      const height = card.dataset.height;
      if (height) {
        const heightNum = parseFloat(height);
        if (heightNum < 60) filterData.height.add('Under 5\'0"');else if (heightNum >= 60 && heightNum < 64) filterData.height.add('5\'0" - 5\'3"');else if (heightNum >= 64 && heightNum < 68) filterData.height.add('5\'4" - 5\'7"');else if (heightNum >= 68 && heightNum < 72) filterData.height.add('5\'8" - 5\'11"');else if (heightNum >= 72) filterData.height.add('6\'0" and above');
      }

      // Weight
      const weight = card.dataset.weight;
      if (weight) {
        const weightNum = parseFloat(weight);
        if (weightNum < 120) filterData.weight.add('Under 120 lbs');else if (weightNum >= 120 && weightNum < 150) filterData.weight.add('120-149 lbs');else if (weightNum >= 150 && weightNum < 180) filterData.weight.add('150-179 lbs');else if (weightNum >= 180 && weightNum < 210) filterData.weight.add('180-209 lbs');else if (weightNum >= 210) filterData.weight.add('210+ lbs');
      }

      // Procedure Details - extract all data-procedure-detail-* attributes
      const datasetKeys = Object.keys(card.dataset);
      datasetKeys.forEach(key => {
        if (key.startsWith('procedureDetail')) {
          // Convert camelCase to readable label (e.g., procedureDetailImplantType -> Implant Type)
          const labelKey = key.replace('procedureDetail', '');
          const label = labelKey.replace(/([A-Z])/g, ' $1').trim();
          const value = card.dataset[key];
          if (value) {
            // Handle comma-separated values (for array fields)
            const values = value.split(',').map(v => v.trim()).filter(v => v);
            values.forEach(v => {
              if (!filterData.procedureDetails.has(label)) {
                filterData.procedureDetails.set(label, new Set());
              }
              // Capitalize first letter of each word for display
              const displayValue = v.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
              filterData.procedureDetails.get(label).add(displayValue);
            });
          }
        }
      });
    });

    // Generate HTML for filter sections
    let html = '';

    // Age filter
    if (filterData.age.size > 0) {
      html += this.generateFilterSection('Age', 'age', Array.from(filterData.age).sort());
    }

    // Gender filter
    if (filterData.gender.size > 0) {
      const genderOptions = Array.from(filterData.gender).map(g => g === 'male' ? 'Male' : g === 'female' ? 'Female' : g);
      html += this.generateFilterSection('Gender', 'gender', genderOptions);
    }

    // Ethnicity filter
    if (filterData.ethnicity.size > 0) {
      html += this.generateFilterSection('Ethnicity', 'ethnicity', Array.from(filterData.ethnicity).sort());
    }

    // Height filter
    if (filterData.height.size > 0) {
      html += this.generateFilterSection('Height', 'height', Array.from(filterData.height));
    }

    // Weight filter
    if (filterData.weight.size > 0) {
      html += this.generateFilterSection('Weight', 'weight', Array.from(filterData.weight));
    }

    // Procedure Details filters
    if (filterData.procedureDetails.size > 0) {
      filterData.procedureDetails.forEach((values, label) => {
        if (values.size > 0) {
          // Convert label to attribute name for filter type
          const filterType = 'procedure_detail_' + label.toLowerCase().replace(/\s+/g, '_');
          html += this.generateFilterSection(label, filterType, Array.from(values).sort());
        }
      });
    }
    return html;
  }

  /**
   * Generate procedure filter sections based on case data
   * Creates the filter dropdowns matching PHP structure exactly
   */
  generateProcedureFilterSections(cases) {
    // Collect filter data from cases
    const filterData = {
      age: new Set(),
      gender: new Set(),
      ethnicity: new Set(),
      height: new Set(),
      weight: new Set(),
      procedureDetails: new Map() // Map of detail name -> Set of values
    };

    // Extract filter values from case data
    cases.forEach(caseData => {
      if (caseData.age) {
        const age = parseInt(caseData.age);
        if (age >= 18 && age < 25) filterData.age.add('18-24');else if (age >= 25 && age < 35) filterData.age.add('25-34');else if (age >= 35 && age < 45) filterData.age.add('35-44');else if (age >= 45 && age < 55) filterData.age.add('45-54');else if (age >= 55 && age < 65) filterData.age.add('55-64');else if (age >= 65) filterData.age.add('65+');
      }
      if (caseData.gender) {
        filterData.gender.add(caseData.gender.toLowerCase());
      }
      if (caseData.ethnicity) {
        filterData.ethnicity.add(caseData.ethnicity);
      }
      if (caseData.height) {
        const height = parseFloat(caseData.height);
        if (height < 60) filterData.height.add('Under 5\'0"');else if (height >= 60 && height < 64) filterData.height.add('5\'0" - 5\'3"');else if (height >= 64 && height < 68) filterData.height.add('5\'4" - 5\'7"');else if (height >= 68 && height < 72) filterData.height.add('5\'8" - 5\'11"');else if (height >= 72) filterData.height.add('6\'0" and above');
      }
      if (caseData.weight) {
        const weight = parseFloat(caseData.weight);
        if (weight < 120) filterData.weight.add('Under 120 lbs');else if (weight >= 120 && weight < 150) filterData.weight.add('120-149 lbs');else if (weight >= 150 && weight < 180) filterData.weight.add('150-179 lbs');else if (weight >= 180 && weight < 210) filterData.weight.add('180-209 lbs');else if (weight >= 210) filterData.weight.add('210+ lbs');
      }
    });

    // Extract procedure details from rendered DOM cards (since API data may not include them)
    const caseCards = document.querySelectorAll('.brag-book-gallery-case-card');
    caseCards.forEach(card => {
      const datasetKeys = Object.keys(card.dataset);
      datasetKeys.forEach(key => {
        if (key.startsWith('procedureDetail')) {
          // Convert camelCase to readable label (e.g., procedureDetailImplantType -> Implant Type)
          const labelKey = key.replace('procedureDetail', '');
          const label = labelKey.replace(/([A-Z])/g, ' $1').trim();
          const value = card.dataset[key];
          if (value) {
            // Handle comma-separated values (for array fields)
            const values = value.split(',').map(v => v.trim()).filter(v => v);
            values.forEach(v => {
              if (!filterData.procedureDetails.has(label)) {
                filterData.procedureDetails.set(label, new Set());
              }
              // Capitalize first letter of each word for display
              const displayValue = v.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
              filterData.procedureDetails.get(label).add(displayValue);
            });
          }
        }
      });
    });

    // Generate HTML for filter sections
    let html = '';

    // Age filter
    if (filterData.age.size > 0) {
      html += this.generateFilterSection('Age', 'age', Array.from(filterData.age).sort());
    }

    // Gender filter
    if (filterData.gender.size > 0) {
      const genderOptions = Array.from(filterData.gender).map(g => g === 'male' ? 'Male' : g === 'female' ? 'Female' : g);
      html += this.generateFilterSection('Gender', 'gender', genderOptions);
    }

    // Ethnicity filter
    if (filterData.ethnicity.size > 0) {
      html += this.generateFilterSection('Ethnicity', 'ethnicity', Array.from(filterData.ethnicity).sort());
    }

    // Height filter
    if (filterData.height.size > 0) {
      html += this.generateFilterSection('Height', 'height', Array.from(filterData.height));
    }

    // Weight filter
    if (filterData.weight.size > 0) {
      html += this.generateFilterSection('Weight', 'weight', Array.from(filterData.weight));
    }

    // Procedure Details filters
    if (filterData.procedureDetails.size > 0) {
      filterData.procedureDetails.forEach((values, label) => {
        if (values.size > 0) {
          // Convert label to attribute name for filter type
          const filterType = 'procedure_detail_' + label.toLowerCase().replace(/\s+/g, '_');
          html += this.generateFilterSection(label, filterType, Array.from(values).sort());
        }
      });
    }
    return html;
  }

  /**
   * Generate individual filter section HTML
   */
  generateFilterSection(title, type, options) {
    let html = '<details class="brag-book-gallery-filter">';
    html += '<summary class="brag-book-gallery-filter-label">';
    html += `<span class="brag-book-gallery-filter-label__name">${this.escapeHtml(title)}</span>`;
    html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
    html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>';
    html += '</svg>';
    html += '</summary>';
    html += '<ul class="brag-book-gallery-filter-options">';
    options.forEach(option => {
      const id = `procedure-filter-${type}-${option.replace(/[^a-zA-Z0-9]/g, '-')}`;
      // Only convert to lowercase for gender and ethnicity filters
      const value = type === 'gender' || type === 'ethnicity' ? option.toLowerCase() : option;
      html += '<li class="brag-book-gallery-filter-option">';
      html += `<input type="checkbox" id="${id}" value="${this.escapeHtml(value)}" data-filter-type="${type}">`;
      html += `<label for="${id}">${this.escapeHtml(option)}</label>`;
      html += '</li>';
    });
    html += '</ul>';
    html += '</details>';
    return html;
  }

  /**
   * Generate SEO data for filtered gallery
   * @param {string} procedureName - Display name for procedure
   * @returns {Object} SEO data object
   */
  generateSEOData(procedureName) {
    const seo = {};
    if (procedureName) {
      const displayName = this.formatProcedureDisplayName(procedureName);
      const siteName = document.title.split(' | ').pop() || 'BRAGBook Gallery';
      seo.title = `${displayName} Before & After Gallery | ${siteName}`;
      seo.description = `View before and after photos of ${displayName} procedures. Browse our gallery to see real patient results.`;
    }
    return seo;
  }

  /**
   * Format procedure display name (capitalize words, etc.)
   * @param {string} name - Raw procedure name
   * @returns {string} Formatted display name
   */
  formatProcedureDisplayName(name) {
    if (!name) return '';
    return name.split(/[\s-_]+/).map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
  }

  /**
   * Escape HTML characters for safe output
   * @param {string} text - Text to escape
   * @returns {string} Escaped text
   */
  escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    // Also escape quotes for use in HTML attributes
    return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /**
   * Update filter badges display based on active filters
   * DISABLED: Only demographic filters should create badges now
   */
  updateFilterBadges() {
    // This method is disabled - only demographic filters create badges now
    // All badge management is handled by the global updateFilterBadges() function
    return;
  }

  /**
   * Create a filter badge element
   */
  createFilterBadge(category, procedure, filterKey) {
    const badge = document.createElement('div');
    badge.className = 'brag-book-gallery-filter-badge';
    badge.setAttribute('data-filter-key', filterKey);

    // Format the display text based on category
    let displayText = '';
    switch (category) {
      case 'age':
        displayText = `Age: ${procedure}`;
        break;
      case 'gender':
        displayText = `Gender: ${procedure}`;
        break;
      case 'ethnicity':
        displayText = `Ethnicity: ${procedure}`;
        break;
      case 'procedure':
        displayText = procedure;
        break;
      default:
        displayText = `${category}: ${procedure}`;
    }
    badge.innerHTML = `
			<span class="brag-book-gallery-badge-text">${displayText}</span>
			<button class="brag-book-gallery-badge-remove" aria-label="Remove ${displayText} filter">
				<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
					<path d="M13 1L1 13M1 1l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
				</svg>
			</button>
		`;

    // Add click handler to remove button
    const removeButton = badge.querySelector('.brag-book-gallery-badge-remove');
    removeButton.addEventListener('click', e => {
      e.preventDefault();
      this.removeFilterBadge(filterKey);
    });
    return badge;
  }

  /**
   * Remove a specific filter badge
   */
  removeFilterBadge(filterKey) {
    // Remove from active filters
    const filter = this.activeFilters.get(filterKey);
    if (filter) {
      this.activeFilters.delete(filterKey);

      // Remove active class from the corresponding filter link
      const filterLink = document.querySelector(`[data-category="${filter.category}"][data-procedure="${filter.procedure}"]`);
      if (filterLink) {
        filterLink.classList.remove('brag-book-gallery-active');
      }

      // Badge updates handled by demographic filters only
      // this.updateFilterBadges();

      // Trigger filter change event
      this.options.onFilterChange(this.activeFilters);
    }
  }

  /**
   * Clear all active filters
   */
  clearAllFilters() {
    // Remove active classes from all filter links
    const filterLinks = this.container?.querySelectorAll('.brag-book-gallery-nav-list-submenu-link');
    filterLinks?.forEach(link => {
      link.classList.remove('brag-book-gallery-active');
    });

    // Clear active filters
    this.activeFilters.clear();

    // Badge updates handled by demographic filters only
    // this.updateFilterBadges();

    // Trigger filter change event
    this.options.onFilterChange(this.activeFilters);

    // Reset URL to base
    window.history.pushState({}, '', window.location.pathname);
  }

  /**
   * Scroll to gallery wrapper for better user experience
   * Accounts for websites with hero sections that may hide the gallery
   */
  scrollToGalleryWrapper() {
    const wrapper = document.querySelector('.brag-book-gallery-wrapper');
    if (wrapper) {
      // Use smooth scrolling with some offset for better UX
      const offsetTop = wrapper.getBoundingClientRect().top + window.pageYOffset - 20;
      window.scrollTo({
        top: offsetTop,
        behavior: 'smooth'
      });
    }
  }
}
/* harmony default export */ __webpack_exports__["default"] = (FilterSystem);

/***/ }),

/***/ "./src/js/modules/gallery-selector.js":
/*!********************************************!*\
  !*** ./src/js/modules/gallery-selector.js ***!
  \********************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   initGallerySelector: function() { return /* binding */ initGallerySelector; }
/* harmony export */ });
/**
 * Gallery Selector Navigation
 *
 * Handles the two-level navigation for the gallery selector in tiles view:
 * - Parent categories that open subcategory panels
 * - Back button to return to parent categories
 *
 * @package BragBookGallery
 * @since 3.3.2
 */

/**
 * Initialize gallery selector navigation
 */
function initGallerySelector() {
  // Handle parent category clicks
  document.addEventListener('click', e => {
    const categoryLink = e.target.closest('[data-action="show-subcategories"]');
    if (categoryLink) {
      e.preventDefault();
      showSubcategories(categoryLink);
    }

    // Handle back button clicks
    const backButton = e.target.closest('[data-action="back-to-categories"]');
    if (backButton) {
      e.preventDefault();
      showParentCategories(backButton);
    }
  });
}

/**
 * Show subcategory panel for selected parent category
 *
 * @param {HTMLElement} categoryLink - The clicked category link button
 */
function showSubcategories(categoryLink) {
  const categoryId = categoryLink.dataset.categoryId;
  if (!categoryId) {
    return;
  }

  // Find the parent wrapper
  const wrapper = categoryLink.closest('.brag-book-gallery-category-nav-wrapper');
  if (!wrapper) {
    return;
  }

  // Hide parent category list
  const parentList = wrapper.querySelector('[data-level="parent"]');
  if (parentList) {
    parentList.style.display = 'none';
  }

  // Show the corresponding subcategory panel
  const panel = wrapper.querySelector(`.brag-book-gallery-subcategory-panel[data-category-id="${categoryId}"]`);
  if (panel) {
    panel.style.display = 'block';
  }
}

/**
 * Show parent categories list (hide subcategory panel)
 *
 * @param {HTMLElement} backButton - The clicked back button
 */
function showParentCategories(backButton) {
  // Find the parent wrapper
  const wrapper = backButton.closest('.brag-book-gallery-category-nav-wrapper');
  if (!wrapper) {
    return;
  }

  // Hide all subcategory panels
  const panels = wrapper.querySelectorAll('.brag-book-gallery-subcategory-panel');
  panels.forEach(panel => {
    panel.style.display = 'none';
  });

  // Show parent category list
  const parentList = wrapper.querySelector('[data-level="parent"]');
  if (parentList) {
    parentList.style.display = 'block';
  }
}

/***/ }),

/***/ "./src/js/modules/global-utilities.js":
/*!********************************************!*\
  !*** ./src/js/modules/global-utilities.js ***!
  \********************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _main_app_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./main-app.js */ "./src/js/modules/main-app.js");
/* harmony import */ var _carousel_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./carousel.js */ "./src/js/modules/carousel.js");
/* harmony import */ var _utilities_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utilities.js */ "./src/js/modules/utilities.js");




/**
 * Global utility functions for the BRAG book Gallery
 * Contains grid layout management, procedure filtering, case loading, and image handling
 */

/**
 * Procedure referrer tracking for combo procedures
 * Stores which procedure page the user came from to correctly handle navigation
 */

/**
 * Store the current procedure context when user clicks a case card
 * This allows combo procedures to navigate back to the correct procedure
 */
window.storeProcedureReferrer = function (procedureSlug, procedureName, procedureUrl, procedureId, termId, caseId, caseWpId) {
  if (!procedureSlug) return;
  const referrer = {
    'slug': procedureSlug,
    'name': procedureName || procedureSlug,
    'url': procedureUrl || window.location.href,
    'case-id': caseId || null,
    'case-wp-id': caseWpId || null,
    'procedure-id': procedureId || null,
    'term-id': termId || null,
    'timestamp': Date.now()
  };
  localStorage.setItem('brag-book-gallery-procedure-referrer', JSON.stringify(referrer));
};

/**
 * Get the stored procedure referrer
 * @returns {Object|null} Referrer object or null
 */
window.getProcedureReferrer = function () {
  try {
    const stored = localStorage.getItem('brag-book-gallery-procedure-referrer');
    if (!stored) return null;
    const referrer = JSON.parse(stored);

    // Clear referrer if older than 1 hour (stale data)
    const oneHour = 60 * 60 * 1000;
    if (Date.now() - referrer.timestamp > oneHour) {
      localStorage.removeItem('brag-book-gallery-procedure-referrer');
      return null;
    }
    return referrer;
  } catch (e) {
    console.error('Error reading procedure referrer:', e);
    return null;
  }
};

/**
 * Clear the procedure referrer from localStorage
 */
window.clearProcedureReferrer = function () {
  localStorage.removeItem('brag-book-gallery-procedure-referrer');
};

/**
 * Update navigation links based on stored procedure referrer
 * Called on case detail pages to update "Back to Gallery" and next/prev links
 */
window.updateNavigationFromReferrer = function () {
  const referrer = getProcedureReferrer();
  if (!referrer) return;

  // Update "Back to Gallery" button/link URL only (keep text the same)
  const backButton = document.querySelector('.brag-book-gallery-back-link, .brag-book-gallery-back-button, a[href*="/gallery/"][class*="back"]');
  if (backButton && referrer.url) {
    backButton.href = referrer.url;
  }

  // Update next/previous post navigation links
  updateAdjacentPostLinks(referrer.slug, referrer.termId);
};

/**
 * Update next/previous post links to navigate within the referrer procedure
 * @param {string} procedureSlug - The procedure slug to use for navigation
 * @param {number} termId - The WordPress term ID for the procedure
 */
function updateAdjacentPostLinks(procedureSlug, termId) {
  if (!procedureSlug) {
    console.warn('updateAdjacentPostLinks: No procedure slug provided');
    return;
  }

  // Find next/prev links using the correct selectors
  const nextLink = document.querySelector('.brag-book-gallery-nav-button--next, .brag-book-gallery-next-post, .nav-next a, a[rel="next"]');
  const prevLink = document.querySelector('.brag-book-gallery-nav-button--prev, .brag-book-gallery-prev-post, .nav-previous a, a[rel="prev"]');

  // Get current post ID from the page
  const currentPostId = getCurrentPostId();
  if (!currentPostId) {
    console.error('updateAdjacentPostLinks: Could not find current post ID');
    return;
  }

  // Fetch adjacent cases for this procedure via AJAX
  fetchAdjacentCases(procedureSlug, termId, currentPostId, adjacentCases => {
    // Update next link if we have a new URL
    if (adjacentCases.next && nextLink) {
      nextLink.href = adjacentCases.next;
      nextLink.style.display = '';
    }

    // Update prev link if we have a new URL
    if (adjacentCases.prev && prevLink) {
      prevLink.href = adjacentCases.prev;
      prevLink.style.display = '';
    }
  });
}

/**
 * Get the current post ID from the page
 * @returns {number|null} Post ID or null
 */
function getCurrentPostId() {
  // Try to get from body class (WordPress adds post-ID class)
  const bodyClasses = document.body.className.match(/postid-(\d+)/);
  if (bodyClasses) return parseInt(bodyClasses[1]);

  // Try to get from data attribute
  const postElement = document.querySelector('[data-post-id]');
  if (postElement) return parseInt(postElement.dataset.postId);

  // Try to get from global WordPress object
  if (window.bragBookGalleryConfig?.postId) {
    return parseInt(window.bragBookGalleryConfig.postId);
  }
  return null;
}

/**
 * Fetch adjacent cases for a specific procedure
 * @param {string} procedureSlug - Procedure slug
 * @param {number} termId - WordPress term ID for the procedure
 * @param {number} currentPostId - Current post ID
 * @param {Function} callback - Callback with adjacent cases data
 */
function fetchAdjacentCases(procedureSlug, termId, currentPostId, callback) {
  // Make AJAX call to WordPress admin-ajax.php
  const formData = new FormData();
  formData.append('action', 'brag_book_get_adjacent_cases');
  formData.append('procedure_slug', procedureSlug);
  if (termId) {
    formData.append('term_id', termId);
  }
  formData.append('post_id', currentPostId);
  const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
  fetch(ajaxUrl, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
  }).then(response => response.json()).then(data => {
    if (data.success && data.data) {
      callback(data.data);
    } else {
      console.error('Failed to fetch adjacent cases:', data);
      console.error('Error details:', data.data || data);
      callback({
        next: null,
        prev: null
      });
    }
  }).catch(error => {
    console.error('Error fetching adjacent cases:', error);
    callback({
      next: null,
      prev: null
    });
  });
}

/**
 * Initialize procedure referrer tracking
 * Sets up click handlers on procedure pages and updates navigation on case pages
 */
function initializeProcedureReferrerTracking() {
  const currentPath = window.location.pathname;

  // Check if we're on a case detail page (ends with numbers)
  if (currentPath.match(/\/\d+\/?$/)) {
    // Update navigation based on stored referrer
    updateNavigationFromReferrer();
    return;
  }

  // Get the gallery slug from config (e.g., 'gallery', 'before-after', etc.)
  const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'gallery';

  // Escape special regex characters in the gallery slug
  const escapedGallerySlug = gallerySlug.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  // Check if we're on a procedure archive page using the dynamic gallery slug
  const procedurePattern = new RegExp(`\\/${escapedGallerySlug}\\/([^\\/]+)\\/?$`);
  const procedureMatch = currentPath.match(procedurePattern);

  // Only proceed if we're actually on a procedure page
  if (!procedureMatch) {
    return;
  }
  const procedureSlug = procedureMatch[1];

  // Get procedure name from page title or heading
  const pageTitle = document.querySelector('.brag-book-gallery-content-title strong');
  const procedureName = pageTitle ? pageTitle.textContent.trim() : procedureSlug;

  // Get procedure ID and term ID from the navigation link that matches this procedure slug
  const procedureNavLink = document.querySelector(`[data-procedure-slug="${procedureSlug}"]`);
  const procedureId = procedureNavLink?.dataset.procedureId || null;
  const termId = procedureNavLink?.dataset.termId || null;

  // Add click handlers to all case card links
  const caseLinks = document.querySelectorAll('.brag-book-gallery-case-permalink, .brag-book-gallery-case-card a[href*="/gallery/"]');
  caseLinks.forEach(link => {
    link.addEventListener('click', e => {
      // Get procedure context from the case card
      const caseCard = link.closest('.brag-book-gallery-case-card');
      const cardProcedureId = caseCard?.dataset.currentProcedureId || procedureId;
      const cardTermId = caseCard?.dataset.currentTermId || termId;

      // Store the current procedure as referrer
      storeProcedureReferrer(procedureSlug, procedureName, window.location.href, cardProcedureId, cardTermId);
    });
  });
}

/**
 * Update the gallery grid column layout and save preference
 * @param {number} columns - Number of columns to display (1-4)
 */
window.updateGridLayout = function (columns) {
  const grid = document.querySelector('.brag-book-gallery-case-grid');
  if (!grid) return;

  // Only allow grid changes on desktop devices
  const isDesktop = window.innerWidth >= 1024;
  if (!isDesktop) return; // Mobile/tablet use responsive grid

  // Mark grid as initialized to prevent animation conflicts
  grid.classList.add('grid-initialized');

  // Update grid columns data attribute for CSS grid changes
  grid.setAttribute('data-columns', columns);

  // Update button active states to reflect current selection
  const buttons = document.querySelectorAll('.brag-book-gallery-grid-btn');
  buttons.forEach(btn => {
    const btnCols = parseInt(btn.dataset.columns);
    if (btnCols === columns) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });

  // Persist user preference across sessions
  localStorage.setItem('brag-book-gallery-grid-columns', columns);
};

/**
 * Global state for demographic procedure filters
 * Stores arrays of selected filter values for each category
 */
window.bragBookProcedureFilters = {
  age: [],
  // Age ranges like '18-24', '25-34', etc.
  gender: [],
  // Gender values
  ethnicity: [],
  // Ethnicity values
  height: [],
  // Height ranges
  weight: [] // Weight ranges
};

/**
 * Initialize demographic procedure filters
 * Called on page load and after AJAX content updates
 * Always regenerates filters based on current page content
 */
window.initializeProcedureFilters = function () {
  const details = document.getElementById('procedure-filters-details');
  if (details) {
    // Clean up any server-generated procedure badges first
    cleanupProcedureBadges();
    // Always regenerate filter options based on current page cards
    generateProcedureFilterOptions();
    // Mark as initialized but don't prevent regeneration
    details.dataset.initialized = 'true';
  }
};

/**
 * Force regenerate procedure filters (useful after AJAX content updates)
 * Clears the initialized flag and regenerates filters
 */
window.regenerateProcedureFilters = function () {
  const details = document.getElementById('procedure-filters-details');
  if (details) {
    // Clear the initialized flag to force regeneration
    details.dataset.initialized = 'false';
    // Clear any existing filter state
    window.bragBookProcedureFilters = {
      age: [],
      gender: [],
      ethnicity: [],
      height: [],
      weight: []
    };
    // Clean up any existing badges first
    cleanupActiveFiltersSection();
    // Regenerate filters
    initializeProcedureFilters();
  }
};

/**
 * Clean up the active filters section - remove all badges and hide section
 */
function cleanupActiveFiltersSection() {
  const activeFiltersSection = document.querySelector('.brag-book-gallery-active-filters');
  if (activeFiltersSection) {
    // Clear all badges
    const badgesContainers = activeFiltersSection.querySelectorAll('.brag-book-gallery-filter-badges');
    badgesContainers.forEach(container => {
      container.innerHTML = '';
    });

    // Hide the entire section
    activeFiltersSection.style.display = 'none';

    // Also remove any standalone badges
    const standaloneBadges = activeFiltersSection.querySelectorAll('.brag-book-gallery-filter-badge');
    standaloneBadges.forEach(badge => badge.remove());
  }
}

/**
 * Clean up any server-generated procedure badges
 * Removes badges that contain procedure names or data-filter-key attributes
 */
function cleanupProcedureBadges() {
  // Find all active filters sections
  const activeFiltersSections = document.querySelectorAll('.brag-book-gallery-active-filters');
  activeFiltersSections.forEach(section => {
    // Remove badges with procedure names (like "Non Surgical Skin Tightening")
    const procedureBadges = section.querySelectorAll('.brag-book-gallery-filter-badge');
    procedureBadges.forEach(badge => {
      // Check if it's a procedure badge by looking for data-filter-key or remove-filter onclick
      const hasFilterKey = badge.hasAttribute('data-filter-key');
      const hasRemoveFilter = badge.querySelector('button[onclick*="clearProcedureFilter"]');
      const hasSpanWithProcedureName = badge.querySelector('span') && !badge.querySelector('[data-filter-type]'); // Not a demographic filter

      if (hasFilterKey || hasRemoveFilter || hasSpanWithProcedureName) {
        badge.remove();
      }
    });

    // Hide the section if it has no remaining content
    const remainingBadges = section.querySelectorAll('.brag-book-gallery-filter-badge');
    if (remainingBadges.length === 0) {
      section.style.display = 'none';
    }
  });

  // Also clean up any Clear All buttons that might be showing
  const clearAllButtons = document.querySelectorAll('.brag-book-gallery-clear-all-filters');
  clearAllButtons.forEach(button => {
    // Only hide if there are no demographic filters active
    const activeCheckboxes = document.querySelectorAll('.brag-book-gallery-filter-option input[type="checkbox"]:checked');
    if (activeCheckboxes.length === 0) {
      button.style.display = 'none';
    }
  });
}

/**
 * Generate filter options HTML based on available case data
 * Uses either complete dataset from config or falls back to DOM scanning
 */
window.generateProcedureFilterOptions = function () {
  // Helper function to escape HTML attribute values
  const escapeAttr = text => {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  };

  // Try multiple possible filter container IDs/classes
  const container = document.getElementById('brag-book-gallery-filters') || document.querySelector('.brag-book-gallery-filter-content') || document.querySelector('.brag-book-gallery-filters');
  if (!container) {
    return;
  }

  // Always generate filters based on visible case cards on the page
  const cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
  const filterData = {
    age: new Set(),
    gender: new Set(),
    ethnicity: new Set(),
    height: new Set(),
    weight: new Set(),
    procedureDetails: new Map() // Map of detail name -> Set of values
  };

  // First pass: collect actual values to determine which ranges have data
  const ageValues = [];
  const heightValues = [];
  const weightValues = [];
  cards.forEach(card => {
    // Collect raw values
    const age = card.dataset.age;
    if (age) {
      ageValues.push(parseInt(age));
    }

    // Gender
    if (card.dataset.gender) {
      filterData.gender.add(card.dataset.gender);
    }

    // Ethnicity
    if (card.dataset.ethnicity) {
      filterData.ethnicity.add(card.dataset.ethnicity);
    }

    // Height
    const height = card.dataset.height;
    if (height) {
      const heightNum = parseInt(height);
      const unit = card.dataset.heightUnit || 'in';
      // Convert to inches for consistent comparison
      if (unit === 'cm') {
        heightValues.push(Math.round(heightNum / 2.54));
      } else {
        heightValues.push(heightNum);
      }
    }

    // Weight
    const weight = card.dataset.weight;
    if (weight) {
      const weightNum = parseInt(weight);
      const unit = card.dataset.weightUnit || 'lbs';
      // Convert to lbs for consistent comparison
      if (unit === 'kg') {
        weightValues.push(Math.round(weightNum * 2.205));
      } else {
        weightValues.push(weightNum);
      }
    }

    // Procedure Details - extract all data-procedure-detail-* attributes
    const datasetKeys = Object.keys(card.dataset);
    datasetKeys.forEach(key => {
      if (key.startsWith('procedureDetail')) {
        // Convert camelCase to readable label (e.g., procedureDetailImplantSize -> Implant Size)
        const labelKey = key.replace('procedureDetail', '');
        const label = labelKey.replace(/([A-Z])/g, ' $1').trim();
        const value = card.dataset[key];
        if (value) {
          // Handle comma-separated values (for array fields)
          const values = value.split(',').map(v => v.trim()).filter(v => v);
          values.forEach(v => {
            if (!filterData.procedureDetails.has(label)) {
              filterData.procedureDetails.set(label, new Set());
            }
            // Capitalize first letter of each word for display
            const displayValue = v.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
            filterData.procedureDetails.get(label).add(displayValue);
          });
        }
      }
    });
  });

  // Only add age ranges that have actual data
  if (ageValues.some(age => age >= 18 && age < 25)) filterData.age.add('18-24');
  if (ageValues.some(age => age >= 25 && age < 35)) filterData.age.add('25-34');
  if (ageValues.some(age => age >= 35 && age < 45)) filterData.age.add('35-44');
  if (ageValues.some(age => age >= 45 && age < 55)) filterData.age.add('45-54');
  if (ageValues.some(age => age >= 55 && age < 65)) filterData.age.add('55-64');
  if (ageValues.some(age => age >= 65)) filterData.age.add('65+');

  // Only add height ranges that have actual data (in inches)
  if (heightValues.some(h => h < 60)) filterData.height.add('Under 5\'0"');
  if (heightValues.some(h => h >= 60 && h < 64)) filterData.height.add('5\'0" - 5\'3"');
  if (heightValues.some(h => h >= 64 && h < 68)) filterData.height.add('5\'4" - 5\'7"');
  if (heightValues.some(h => h >= 68 && h < 72)) filterData.height.add('5\'8" - 5\'11"');
  if (heightValues.some(h => h >= 72)) filterData.height.add('6\'0" and above');

  // Only add weight ranges that have actual data (in lbs)
  if (weightValues.some(w => w < 120)) filterData.weight.add('Under 120 lbs');
  if (weightValues.some(w => w >= 120 && w < 150)) filterData.weight.add('120-149 lbs');
  if (weightValues.some(w => w >= 150 && w < 180)) filterData.weight.add('150-179 lbs');
  if (weightValues.some(w => w >= 180 && w < 210)) filterData.weight.add('180-209 lbs');
  if (weightValues.some(w => w >= 210)) filterData.weight.add('210+ lbs');

  // Generate the filter HTML
  generateFilterHTML(container, filterData);
};

/**
 * Generate the filter interface HTML from collected filter data
 * @param {HTMLElement} container - Container to insert filter HTML
 * @param {Object} filterData - Categorized filter options
 */
window.generateFilterHTML = function (container, filterData) {
  // Helper function to escape HTML attribute values
  const escapeAttr = text => {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  };

  // Build filter HTML
  let html = '';

  // Age filter
  if (filterData.age.size > 0) {
    html += '<details class="brag-book-gallery-filter">';
    html += '<summary class="brag-book-gallery-filter-label">';
    html += '<span class="brag-book-gallery-filter-label__name">Age</span>';
    html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
    html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
    html += '</svg>';
    html += '</summary>';
    html += '<ul class="brag-book-gallery-filter-options">';
    Array.from(filterData.age).sort().forEach(value => {
      const id = `procedure-filter-age-${value.replace(/\s+/g, '-')}`;
      html += `<li class="brag-book-gallery-filter-option">
				<input type="checkbox" id="${id}" value="${escapeAttr(value)}" data-filter-type="age">
				<label for="${id}">${escapeAttr(value)}</label>
			</li>`;
    });
    html += '</ul>';
    html += '</details>';
  }

  // Gender filter
  if (filterData.gender.size > 0) {
    html += '<details class="brag-book-gallery-filter">';
    html += '<summary class="brag-book-gallery-filter-label">';
    html += '<span class="brag-book-gallery-filter-label__name">Gender</span>';
    html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
    html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
    html += '</svg>';
    html += '</summary>';
    html += '<ul class="brag-book-gallery-filter-options">';
    Array.from(filterData.gender).sort().forEach(value => {
      const id = `procedure-filter-gender-${value}`;
      const displayValue = value.charAt(0).toUpperCase() + value.slice(1);
      html += `<li class="brag-book-gallery-filter-option">
				<input type="checkbox" id="${id}" value="${escapeAttr(value)}" data-filter-type="gender">
				<label for="${id}">${escapeAttr(displayValue)}</label>
			</li>`;
    });
    html += '</ul>';
    html += '</details>';
  }

  // Ethnicity filter
  if (filterData.ethnicity.size > 0) {
    html += '<details class="brag-book-gallery-filter">';
    html += '<summary class="brag-book-gallery-filter-label">';
    html += '<span class="brag-book-gallery-filter-label__name">Ethnicity</span>';
    html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
    html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
    html += '</svg>';
    html += '</summary>';
    html += '<ul class="brag-book-gallery-filter-options">';
    Array.from(filterData.ethnicity).sort().forEach(value => {
      const id = `procedure-filter-ethnicity-${value.replace(/\s+/g, '-')}`;
      const displayValue = value.charAt(0).toUpperCase() + value.slice(1);
      html += `<li class="brag-book-gallery-filter-option">
				<input type="checkbox" id="${id}" value="${escapeAttr(value)}" data-filter-type="ethnicity">
				<label for="${id}">${escapeAttr(displayValue)}</label>
			</li>`;
    });
    html += '</ul>';
    html += '</details>';
  }

  // Height filter
  if (filterData.height.size > 0) {
    html += '<details class="brag-book-gallery-filter">';
    html += '<summary class="brag-book-gallery-filter-label">';
    html += '<span class="brag-book-gallery-filter-label__name">Height</span>';
    html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
    html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
    html += '</svg>';
    html += '</summary>';
    html += '<ul class="brag-book-gallery-filter-options">';
    Array.from(filterData.height).sort().forEach(value => {
      const id = `procedure-filter-height-${value.replace(/\s+/g, '-')}`;
      html += `<li class="brag-book-gallery-filter-option">
				<input type="checkbox" id="${id}" value="${escapeAttr(value)}" data-filter-type="height">
				<label for="${id}">${escapeAttr(value)}</label>
			</li>`;
    });
    html += '</ul>';
    html += '</details>';
  }

  // Weight filter
  if (filterData.weight.size > 0) {
    html += '<details class="brag-book-gallery-filter">';
    html += '<summary class="brag-book-gallery-filter-label">';
    html += '<span class="brag-book-gallery-filter-label__name">Weight</span>';
    html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
    html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
    html += '</svg>';
    html += '</summary>';
    html += '<ul class="brag-book-gallery-filter-options">';
    Array.from(filterData.weight).sort().forEach(value => {
      const id = `procedure-filter-weight-${value.replace(/\s+/g, '-')}`;
      html += `<li class="brag-book-gallery-filter-option">
				<input type="checkbox" id="${id}" value="${escapeAttr(value)}" data-filter-type="weight">
				<label for="${id}">${escapeAttr(value)}</label>
			</li>`;
    });
    html += '</ul>';
    html += '</details>';
  }

  // Procedure Details filters
  if (filterData.procedureDetails && filterData.procedureDetails.size > 0) {
    filterData.procedureDetails.forEach((values, label) => {
      if (values.size > 0) {
        // Convert label to attribute name for filter type
        const filterType = 'procedure_detail_' + label.toLowerCase().replace(/\s+/g, '_');
        html += '<details class="brag-book-gallery-filter">';
        html += '<summary class="brag-book-gallery-filter-label">';
        html += `<span class="brag-book-gallery-filter-label__name">${escapeAttr(label)}</span>`;
        html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
        html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
        html += '</svg>';
        html += '</summary>';
        html += '<ul class="brag-book-gallery-filter-options">';
        Array.from(values).sort().forEach(value => {
          const id = `procedure-filter-${filterType}-${value.replace(/\s+/g, '-').toLowerCase()}`;
          const lowerValue = value.toLowerCase();
          html += `<li class="brag-book-gallery-filter-option">
						<input type="checkbox" id="${id}" value="${escapeAttr(lowerValue)}" data-filter-type="${escapeAttr(filterType)}">
						<label for="${id}">${escapeAttr(value)}</label>
					</li>`;
        });
        html += '</ul>';
        html += '</details>';
      }
    });
  }
  container.innerHTML = html || '<p>No filters available</p>';

  // Add event listeners to all checkboxes
  const filterCheckboxes = container.querySelectorAll('input[type="checkbox"]');
  filterCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function () {
      try {
        if (typeof window.applyProcedureFilters === 'function') {
          window.applyProcedureFilters();
        } else {
          console.error('window.applyProcedureFilters function not found, typeof:', typeof window.applyProcedureFilters);
        }
      } catch (error) {
        console.error('Error calling applyProcedureFilters:', error);
      }
    });
  });

  // Show/hide the details element based on whether filters exist
  const details = document.getElementById('procedure-filters-details');
  if (details) {
    const hasFilters = html && html !== '<p>No filters available</p>';
    if (hasFilters) {
      details.style.display = '';
    } else {
      details.style.display = 'none';
    }
  }
};

/**
 * Apply active demographic filters to case cards
 * Handles both complete dataset filtering and DOM-based filtering
 */
window.applyProcedureFilters = function () {
  const checkboxes = document.querySelectorAll('.brag-book-gallery-filter-option input:checked');

  // Reset filter state
  window.bragBookProcedureFilters = {
    age: [],
    gender: [],
    ethnicity: [],
    height: [],
    weight: [],
    procedureDetails: {} // Store procedure detail filters as object
  };

  // Collect selected filters
  checkboxes.forEach(checkbox => {
    const filterType = checkbox.dataset.filterType;
    const value = checkbox.value;

    // Check if this is a procedure detail filter
    if (filterType.startsWith('procedure_detail_')) {
      // Extract the detail name (e.g., 'implant_size' from 'procedure_detail_implant_size')
      const detailName = filterType.replace('procedure_detail_', '');
      if (!window.bragBookProcedureFilters.procedureDetails[detailName]) {
        window.bragBookProcedureFilters.procedureDetails[detailName] = [];
      }
      window.bragBookProcedureFilters.procedureDetails[detailName].push(value);
    } else if (window.bragBookProcedureFilters[filterType]) {
      window.bragBookProcedureFilters[filterType].push(value);
    }
  });

  // Check if any filters are selected
  const hasActiveFilters = Object.keys(window.bragBookProcedureFilters).some(key => {
    if (key === 'procedureDetails') {
      return Object.keys(window.bragBookProcedureFilters.procedureDetails).length > 0;
    }
    return window.bragBookProcedureFilters[key].length > 0;
  });

  // Update filter badges
  updateFilterBadges();

  // Always filter only the visible cards on the current page
  let cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');

  // Fallback selectors if the primary selector doesn't find cards
  if (cards.length === 0) {
    cards = document.querySelectorAll('.brag-book-gallery-case-grid .brag-book-gallery-case-card');
  }
  if (cards.length === 0) {
    cards = document.querySelectorAll('.brag-book-gallery-case-card');
  }

  // Debug: log the first card's data attributes
  if (cards.length > 0) {
    const firstCard = cards[0];
  }
  if (!hasActiveFilters) {
    // No filters selected, show all currently loaded cards
    cards.forEach(card => {
      card.style.display = '';
    });

    // Filter results element no longer used - removed

    // Show Load More button if it exists since no filters are active
    const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') || document.querySelector('.brag-book-gallery-load-more button');
    const loadMoreContainer = loadMoreBtn ? loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement : null;
    if (loadMoreContainer && loadMoreBtn.hasAttribute('data-start-page')) {
      loadMoreContainer.style.display = '';
    }
    return;
  }

  // Filter the visible cards based on their data attributes
  let visibleCount = 0;
  cards.forEach((card, index) => {
    let show = true;
    const caseId = card.dataset.caseId;

    // Check age filter
    if (window.bragBookProcedureFilters.age.length > 0) {
      const cardAge = parseInt(card.dataset.age);
      let ageMatch = false;
      window.bragBookProcedureFilters.age.forEach(range => {
        if (range === '18-24' && cardAge >= 18 && cardAge < 25) {
          ageMatch = true;
        } else if (range === '25-34' && cardAge >= 25 && cardAge < 35) {
          ageMatch = true;
        } else if (range === '35-44' && cardAge >= 35 && cardAge < 45) {
          ageMatch = true;
        } else if (range === '45-54' && cardAge >= 45 && cardAge < 55) {
          ageMatch = true;
        } else if (range === '55-64' && cardAge >= 55 && cardAge < 65) {
          ageMatch = true;
        } else if (range === '65+' && cardAge >= 65) {
          ageMatch = true;
        }
      });
      if (!ageMatch) {
        show = false;
      }
    }

    // Check gender filter
    if (show && window.bragBookProcedureFilters.gender.length > 0) {
      const cardGender = (card.dataset.gender || '').toLowerCase();
      const filterGenders = window.bragBookProcedureFilters.gender.map(g => g.toLowerCase());
      if (!filterGenders.includes(cardGender)) {
        show = false;
      }
    }

    // Check ethnicity filter
    if (show && window.bragBookProcedureFilters.ethnicity.length > 0) {
      const cardEthnicity = (card.dataset.ethnicity || '').toLowerCase();
      const filterEthnicities = window.bragBookProcedureFilters.ethnicity.map(e => e.toLowerCase());
      if (!filterEthnicities.includes(cardEthnicity)) {
        show = false;
      }
    }

    // Check height filter
    if (show && window.bragBookProcedureFilters.height.length > 0) {
      const cardHeight = parseInt(card.dataset.height);
      let heightMatch = false;
      window.bragBookProcedureFilters.height.forEach(range => {
        if (range === 'Under 5\'0"' && cardHeight < 60) {
          heightMatch = true;
        } else if (range === '5\'0" - 5\'3"' && cardHeight >= 60 && cardHeight < 64) {
          heightMatch = true;
        } else if (range === '5\'4" - 5\'7"' && cardHeight >= 64 && cardHeight < 68) {
          heightMatch = true;
        } else if (range === '5\'8" - 5\'11"' && cardHeight >= 68 && cardHeight < 72) {
          heightMatch = true;
        } else if (range === '6\'0" and above' && cardHeight >= 72) {
          heightMatch = true;
        }
      });
      if (!heightMatch) show = false;
    }

    // Check weight filter
    if (show && window.bragBookProcedureFilters.weight.length > 0) {
      const cardWeight = parseInt(card.dataset.weight);
      let weightMatch = false;
      window.bragBookProcedureFilters.weight.forEach(range => {
        // Assume weight is in lbs (matching the ranges we generate)
        if (range === 'Under 120 lbs' && cardWeight < 120) weightMatch = true;else if (range === '120-149 lbs' && cardWeight >= 120 && cardWeight < 150) weightMatch = true;else if (range === '150-179 lbs' && cardWeight >= 150 && cardWeight < 180) weightMatch = true;else if (range === '180-209 lbs' && cardWeight >= 180 && cardWeight < 210) weightMatch = true;else if (range === '210+ lbs' && cardWeight >= 210) weightMatch = true;
      });
      if (!weightMatch) show = false;
    }

    // Check procedure detail filters
    if (show && Object.keys(window.bragBookProcedureFilters.procedureDetails).length > 0) {
      // Check each procedure detail filter type
      for (const detailName in window.bragBookProcedureFilters.procedureDetails) {
        const filterValues = window.bragBookProcedureFilters.procedureDetails[detailName];
        if (filterValues.length > 0) {
          // Get the card's value for this procedure detail
          const dataAttrName = 'procedureDetail' + detailName.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join('');
          const cardValue = (card.dataset[dataAttrName] || '').toLowerCase();

          // Check if the card's value matches any of the selected filter values
          let detailMatch = false;
          if (cardValue) {
            // Handle comma-separated values (for array fields)
            const cardValues = cardValue.split(',').map(v => v.trim());
            filterValues.forEach(filterValue => {
              if (cardValues.includes(filterValue.toLowerCase())) {
                detailMatch = true;
              }
            });
          }
          if (!detailMatch) {
            show = false;
            break; // No need to check other details if this one doesn't match
          }
        }
      }
    }

    // Show/hide card
    card.style.display = show ? '' : 'none';
    if (show) visibleCount++;
  });

  // Filter results element no longer used - removed

  // Close the details after applying filters and update visual indicator
  const details = document.getElementById('procedure-filters-details');
  if (details) {
    details.open = false;
    // Add/remove visual indicator based on whether filters are active
    const toggle = details.querySelector('.brag-book-gallery-filter-dropdown__toggle');
    if (toggle) {
      if (hasActiveFilters) {
        toggle.classList.add('has-active-filters');
      } else {
        toggle.classList.remove('has-active-filters');
      }
    }
  }

  // Hide Load More button when filters are active, show when no filters
  const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') || document.querySelector('.brag-book-gallery-load-more button');
  const loadMoreContainer = loadMoreBtn ? loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement : null;
  if (loadMoreContainer) {
    loadMoreContainer.style.display = hasActiveFilters ? 'none' : '';
  }
};

/**
 * Load specific cases from server when filtering with complete dataset
 * @param {Array<string>} matchingCaseIds - Array of case IDs that match current filters
 */
window.loadFilteredCases = function (matchingCaseIds) {
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

  // If we need to load more cases, try optimized loading first
  if (needToLoad.length > 0) {
    // Show loading indicator
    const container = document.querySelector('.brag-book-gallery-case-grid') || document.querySelector('.brag-book-gallery-case-grid');
    if (container) {
      // Add loading message
      const loadingMsg = document.createElement('div');
      loadingMsg.className = 'filter-loading-message';
      loadingMsg.textContent = `Loading ${needToLoad.length} additional matching cases...`;
      loadingMsg.style.cssText = 'padding: 20px; text-align: center; font-style: italic;';
      container.appendChild(loadingMsg);

      // Try optimized frontend cache first, then fallback to AJAX
      loadFilteredCasesOptimized(needToLoad).then(result => {
        // Remove loading message
        const loadingMsg = container.querySelector('.filter-loading-message');
        if (loadingMsg) loadingMsg.remove();
        if (result.success && result.html) {
          // Add the new cards to the container
          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = result.html;

          // Append each new card
          const newCards = tempDiv.querySelectorAll('.brag-book-gallery-case-card');
          newCards.forEach(card => {
            container.appendChild(card);
            visibleCount++;
          });

          // Update the count
          updateFilteredCount(visibleCount, window.bragBookCompleteDataset.length);
        } else {
          // No cases found or error occurred
          updateFilteredCount(visibleCount, window.bragBookCompleteDataset.length);
        }
      }).catch(error => {
        console.error('Error loading filtered cases:', error);
        const loadingMsg = container.querySelector('.filter-loading-message');
        if (loadingMsg) {
          loadingMsg.textContent = 'Failed to load additional cases. Showing only currently loaded matches.';
          setTimeout(() => loadingMsg.remove(), 3000);
        }
        // Still update the count with what we have
        updateFilteredCount(visibleCount, window.bragBookCompleteDataset.length);
      });
    }
  } else {
    // All matching cases are already loaded, just update the count
    updateFilteredCount(visibleCount, window.bragBookCompleteDataset ? window.bragBookCompleteDataset.length : allCards.length);
  }

  // Hide Load More button when filters are active since we're showing all matching results
  const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') || document.querySelector('.brag-book-gallery-load-more button');
  const loadMoreContainer = loadMoreBtn ? loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement : null;
  if (loadMoreContainer) {
    loadMoreContainer.style.display = 'none';
  }
};

/**
 * Optimized loading for filtered cases with frontend cache and AJAX fallback
 * @param {Array<string>} caseIds - Array of case IDs to load
 * @returns {Promise} Promise that resolves with result object
 */
async function loadFilteredCasesOptimized(caseIds) {
  try {
    // First, try to find cases in frontend cache/dataset
    let foundCases = [];
    let missingCaseIds = [];

    // Check if we have the complete dataset available
    if (window.bragBookCompleteDataset && Array.isArray(window.bragBookCompleteDataset)) {
      caseIds.forEach(caseId => {
        const foundCase = window.bragBookCompleteDataset.find(caseData => String(caseData.id) === String(caseId));
        if (foundCase) {
          foundCases.push(foundCase);
        } else {
          missingCaseIds.push(caseId);
        }
      });
    } else {
      // No frontend cache, need to load all via AJAX
      missingCaseIds = [...caseIds];
    }
    let html = '';

    // Generate HTML for cases found in frontend cache
    if (foundCases.length > 0) {
      foundCases.forEach(caseData => {
        html += generateFilteredCaseHTML(caseData);
      });
    }

    // If we have missing cases, load them via AJAX fallback
    if (missingCaseIds.length > 0) {
      try {
        const ajaxResult = await loadFilteredCasesViaAjax(missingCaseIds);
        if (ajaxResult.success && ajaxResult.html) {
          html += ajaxResult.html;
        }
      } catch (ajaxError) {
        console.warn('AJAX fallback failed for filtered cases:', ajaxError);
        // Continue with what we have from frontend cache
      }
    }
    return {
      success: html.length > 0,
      html: html,
      casesFound: foundCases.length + (missingCaseIds.length > 0 ? 1 : 0),
      // Approximate
      fromCache: foundCases.length,
      fromAjax: missingCaseIds.length
    };
  } catch (error) {
    console.error('Error in optimized filtered cases loading:', error);
    // Fallback to AJAX for all cases
    return await loadFilteredCasesViaAjax(caseIds);
  }
}

/**
 * Fallback method using original AJAX approach for filtered cases
 * @param {Array<string>} caseIds - Array of case IDs to load
 * @returns {Promise} Promise that resolves with result object
 */
async function loadFilteredCasesViaAjax(caseIds) {
  try {
    const formData = new FormData();
    formData.append('action', 'brag_book_gallery_load_filtered_cases');
    formData.append('case_ids', caseIds.join(','));
    formData.append('nonce', typeof bragBookAjax !== 'undefined' ? bragBookAjax.nonce : '');
    const response = await fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    if (data.success && data.data) {
      return {
        success: true,
        html: data.data.html || '',
        casesFound: data.data.casesFound || 0,
        totalCount: data.data.totalCount || caseIds.length
      };
    } else {
      return {
        success: false,
        error: data.data?.message || 'Failed to load filtered cases'
      };
    }
  } catch (error) {
    console.error('AJAX error loading filtered cases:', error);
    return {
      success: false,
      error: error.message
    };
  }
}

/**
 * Generate HTML for a filtered case from frontend data
 * @param {Object} caseData - Case data object
 * @returns {string} Generated HTML for the case
 */
function generateFilteredCaseHTML(caseData) {
  // Transform case data to match expected format
  const caseId = caseData.id || '';

  // Extract main image from photoSets
  let mainImageUrl = '';
  if (caseData.photoSets && Array.isArray(caseData.photoSets) && caseData.photoSets.length > 0) {
    const firstPhotoset = caseData.photoSets[0];
    mainImageUrl = firstPhotoset.postProcessedImageLocation || firstPhotoset.beforeLocationUrl || firstPhotoset.afterLocationUrl1 || '';
  }

  // Extract procedure title
  let procedureTitle = 'Unknown Procedure';
  if (caseData.procedures && Array.isArray(caseData.procedures) && caseData.procedures.length > 0) {
    procedureTitle = caseData.procedures[0].name || procedureTitle;
  }

  // Generate case HTML (reusing the helper function from load more)
  let html = `<article class="brag-book-gallery-case-card" data-case-id="${escapeHtml(String(caseId))}" data-card="true">`;
  html += `<div class="brag-book-gallery-case-image-container" onclick="loadCaseDetails('${caseId}')">`;
  if (mainImageUrl) {
    html += `<img src="${escapeHtml(mainImageUrl)}" alt="${escapeHtml(procedureTitle)} case" loading="lazy">`;
  }

  // Add nudity warning only if the active procedure has data-nudity="true"
  const activeLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
  const shouldAddNudityWarning = activeLink && activeLink.dataset.nudity === 'true';
  if (shouldAddNudityWarning) {
    html += '<div class="brag-book-gallery-nudity-overlay">';
    html += '<div class="brag-book-gallery-nudity-warning">';
    html += '<div class="brag-book-gallery-nudity-warning-content">';
    html += '<p class="brag-book-gallery-nudity-warning-title">Nudity Warning</p>';
    html += '<p class="brag-book-gallery-nudity-warning-caption">Click to proceed if you wish to view.</p>';
    html += '<button class="brag-book-gallery-nudity-warning-button" type="button">Proceed</button>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
  }
  html += '<div class="brag-book-gallery-case-overlay">';
  html += '<span class="brag-book-gallery-case-view-text">View Case</span>';
  html += '</div>';
  html += '</div>';
  html += '<div class="brag-book-gallery-case-info">';
  html += `<h3>${escapeHtml(procedureTitle)}</h3>`;
  html += '</div>';
  html += '</article>';
  return html;
}

/**
 * Update the display of filtered results count
 * @param {number} shown - Number of cases currently visible
 * @param {number} total - Total number of cases available
 */
window.updateFilteredCount = function (shown, total) {
  // Update the count label
  const countLabel = document.querySelector('.brag-book-gallery-favorite-count-label') || document.querySelector('.cases-count');
  if (countLabel) {
    countLabel.textContent = `Showing ${shown} of ${total}`;
  }

  // Note: Filter results message is handled by applyProcedureFilters function
  // This function only updates the count label, not the filter results message
};

/**
 * Clear all active demographic filters and show all cases
 */
window.clearProcedureFilters = function () {
  // 1. Uncheck all filter checkboxes
  const checkboxes = document.querySelectorAll('.brag-book-gallery-filter-option input[type="checkbox"]');
  checkboxes.forEach(checkbox => {
    checkbox.checked = false;
  });

  // 2. Reset global filter state to empty arrays
  if (window.bragBookProcedureFilters) {
    window.bragBookProcedureFilters.age = [];
    window.bragBookProcedureFilters.gender = [];
    window.bragBookProcedureFilters.ethnicity = [];
    window.bragBookProcedureFilters.height = [];
    window.bragBookProcedureFilters.weight = [];
    window.bragBookProcedureFilters.procedureDetails = {};
  }

  // 3. Show all case cards
  const cards = document.querySelectorAll('.brag-book-gallery-case-card');
  cards.forEach(card => {
    card.style.display = '';
    card.style.visibility = '';
  });

  // 4. Remove has-active-filters class from wrapper
  const galleryWrapper = document.querySelector('.brag-book-gallery-wrapper');
  if (galleryWrapper && galleryWrapper.classList.contains('has-active-filters')) {
    galleryWrapper.classList.remove('has-active-filters');
  }

  // 5. Hide the active filters section and clear badges
  const activeFiltersSection = document.querySelector('.brag-book-gallery-active-filters');
  if (activeFiltersSection) {
    activeFiltersSection.style.display = 'none';
    activeFiltersSection.innerHTML = '';
  }

  // Filter results element no longer used - removed

  // 7. Show Load More button if it exists
  const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') || document.querySelector('.brag-book-gallery-load-more button');
  if (loadMoreBtn) {
    const loadMoreContainer = loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement;
    if (loadMoreContainer) {
      loadMoreContainer.style.display = '';
    }
  }

  // 8. Hide the Clear All button
  const clearAllButton = document.querySelector('.brag-book-gallery-clear-all-filters');
  if (clearAllButton) {
    clearAllButton.style.display = 'none';
  }

  // 9. Remove has-active-filters class from filter dropdown toggle
  const filterDropdown = document.getElementById('procedure-filters-details');
  if (filterDropdown) {
    filterDropdown.open = false;
    const summary = filterDropdown.querySelector('summary');
    if (summary && summary.classList.contains('has-active-filters')) {
      summary.classList.remove('has-active-filters');
    }
  }
};

/**
 * Synchronize image heights within a case card for consistent display
 * @param {HTMLImageElement} img - The image element that just loaded
 */
window.syncImageHeights = function (img) {
  // Hide skeleton loader and show image
  img.style.opacity = '1';
  // Find the skeleton loader in the image container (new structure with anchor wrapping picture)
  const container = img.closest('.brag-book-gallery-image-container');
  if (container) {
    const loader = container.querySelector('.brag-book-gallery-skeleton-loader');
    if (loader) loader.style.display = 'none';
  }

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
    container.style.paddingBottom = maxAspectRatio * 100 + '%';
  });
};

/**
 * Load case details into gallery content (backward compatibility wrapper)
 * @param {string} caseId - The case ID to load
 * @param {string} procedureId - The procedure ID
 * @param {string} procedureSlug - The procedure URL slug
 * @param {string} procedureIds - Comma-separated procedure IDs
 */
window.loadCaseDetails = function (caseId, procedureId, procedureSlug, procedureIds) {
  // For backwards compatibility, call the new function without procedure name
  window.loadCaseDetailsWithName(caseId, procedureId, procedureSlug, '', procedureIds);
};

/**
 * Load case details with full context including procedure name
 * @param {string} caseId - The case ID to load
 * @param {string} procedureId - The procedure ID
 * @param {string} procedureSlug - The procedure URL slug
 * @param {string} procedureName - Display name for the procedure
 * @param {string} procedureIds - Comma-separated procedure IDs
 */
window.loadCaseDetailsWithName = function (caseId, procedureId, procedureSlug, procedureName, procedureIds) {
  const galleryContent = document.getElementById('gallery-content');
  if (!galleryContent) {
    console.error('Gallery content container not found');
    return;
  }

  // If procedureIds not provided, try to get from the case card
  if (!procedureIds) {
    const caseCard = document.querySelector(`.brag-book-gallery-case-card[data-case-id="${caseId}"]`);
    if (caseCard && caseCard.dataset.procedureIds) {
      procedureIds = caseCard.dataset.procedureIds;
    }
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
    window.history.pushState({
      caseId: caseId,
      procedureId: procedureId,
      procedureSlug: procedureSlug
    }, '', newUrl);
  }

  // Prepare request parameters
  const requestParams = {
    action: 'brag_book_gallery_load_case_details',
    case_id: caseId,
    nonce: bragBookGalleryConfig.nonce
  };

  // Add procedure ID if provided
  if (procedureId) {
    requestParams.procedure_id = procedureId;
  }

  // Add procedure slug if provided (for display context)
  if (procedureSlug) {
    requestParams.procedure_slug = procedureSlug;
  }

  // Add procedure name if provided (for display)
  if (procedureName) {
    requestParams.procedure_name = procedureName;
  }

  // Add procedure IDs if provided (for API request)
  if (procedureIds) {
    requestParams.procedure_ids = procedureIds;
  }

  // Make AJAX request to load case details
  fetch(bragBookGalleryConfig.ajaxUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams(requestParams)
  }).then(response => response.json()).then(data => {
    if (data.success) {
      galleryContent.innerHTML = data.data.html;

      // Scroll to top of gallery content area smoothly after content loads
      const wrapper = document.querySelector('.brag-book-gallery-wrapper');
      if (wrapper) {
        wrapper.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      } else {
        // Fallback to scrolling to gallery content
        galleryContent.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    } else {
      let errorMessage = 'Unknown error';
      if (data.data) {
        if (typeof data.data === 'string') {
          errorMessage = data.data;
        } else if (data.data.message) {
          errorMessage = data.data.message;
        } else {
          errorMessage = JSON.stringify(data.data);
        }
      }
      galleryContent.innerHTML = '<div class="brag-book-gallery-error">Failed to load case details: ' + errorMessage + '</div>';
    }
  }).catch(error => {
    console.error('Error loading case details:', error);
    galleryContent.innerHTML = '<div class="brag-book-gallery-error">Error loading case details. Please try again.</div>';
  });
};

/**
 * Load more cases via direct API (optimized) with AJAX fallback
 * @param {HTMLElement} button - The Load More button element
 */
window.loadMoreCases = function (button) {
  // Disable button and show loading state
  button.disabled = true;
  const originalText = button.textContent;
  button.textContent = 'Loading...';

  // Get data from button attributes
  const startPage = button.getAttribute('data-start-page');
  const procedureIds = button.getAttribute('data-procedure-ids');
  const procedureName = button.getAttribute('data-procedure-name') || '';

  // Check if there's an active procedure filter with nudity
  let hasNudity = false;
  const activeFilterLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
  if (activeFilterLink && activeFilterLink.dataset.nudity === 'true') {
    hasNudity = true;
  }

  // Get all currently loaded case IDs to prevent duplicates
  const loadedCases = document.querySelectorAll('[data-case-id]');
  const loadedIds = Array.from(loadedCases).map(el => el.getAttribute('data-case-id')).filter(Boolean);

  // Try direct API first (optimized), then fallback to AJAX
  loadMoreCasesDirectly(startPage, procedureIds, procedureName, hasNudity, loadedIds).then(result => {
    if (result.success) {
      // Direct API succeeded, process the result
      processLoadMoreResult(result, button, originalText, startPage);
    } else {
      // Direct API failed, fallback to AJAX
      loadMoreCasesViaAjax(button, startPage, procedureIds, procedureName, hasNudity, loadedIds, originalText);
    }
  }).catch(error => {
    loadMoreCasesViaAjax(button, startPage, procedureIds, procedureName, hasNudity, loadedIds, originalText);
  });
};

/**
 * Load more cases directly from API (optimized method)
 */
async function loadMoreCasesDirectly(startPage, procedureIds, procedureName, hasNudity, loadedIds) {
  try {
    // Get API configuration
    const apiToken = window.bragBookGalleryConfig?.apiToken || '';
    const websitePropertyId = window.bragBookGalleryConfig?.websitePropertyId || '';
    const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.bragBookGalleryConfig?.nonce || '';
    if (!apiToken || !websitePropertyId || !ajaxUrl || !nonce) {
      return {
        success: false,
        error: 'Missing API configuration'
      };
    }

    // Build API request body similar to PHP implementation
    const requestBody = {
      apiTokens: [apiToken],
      websitePropertyIds: [parseInt(websitePropertyId)],
      count: parseInt(startPage)
    };

    // Add procedure IDs if provided
    if (procedureIds) {
      const procedureIdsArray = procedureIds.split(',').map(id => parseInt(id)).filter(id => !isNaN(id));
      if (procedureIdsArray.length > 0) {
        requestBody.procedureIds = procedureIdsArray;
      }
    }

    // Use WordPress AJAX proxy to avoid CORS issues
    const formData = new FormData();
    formData.append('action', 'brag_book_api_proxy');
    formData.append('nonce', nonce);
    formData.append('endpoint', '/api/plugin/combine/cases');
    formData.append('method', 'POST');
    formData.append('body', JSON.stringify(requestBody));
    formData.append('timeout', '8');
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

    const response = await fetch(ajaxUrl, {
      method: 'POST',
      body: formData,
      signal: controller.signal
    });
    clearTimeout(timeoutId);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    const result = await response.json();
    if (!result.success || !result.data || !result.data.data) {
      throw new Error(result.data?.message || 'API proxy request failed');
    }
    const pageData = result.data.data;
    if (!pageData || !pageData.data || !Array.isArray(pageData.data)) {
      return {
        success: false,
        error: 'Invalid API response'
      };
    }

    // Filter out already loaded cases to prevent duplicates
    const newCases = [];
    pageData.data.forEach(caseData => {
      const caseId = caseData.id ? String(caseData.id) : '';
      if (caseId && !loadedIds.includes(caseId)) {
        newCases.push(caseData);
      }
    });

    // Check if there are more pages
    let hasMore = false;
    const casesPerPage = 10; // Default from PHP implementation
    if (pageData.data.length >= casesPerPage) {
      // Check next page to see if it has data using proxy
      const nextRequestBody = {
        ...requestBody,
        count: parseInt(startPage) + 1
      };
      try {
        const nextFormData = new FormData();
        nextFormData.append('action', 'brag_book_api_proxy');
        nextFormData.append('nonce', nonce);
        nextFormData.append('endpoint', '/api/plugin/combine/cases');
        nextFormData.append('method', 'POST');
        nextFormData.append('body', JSON.stringify(nextRequestBody));
        nextFormData.append('timeout', '5');
        const nextResponse = await fetch(ajaxUrl, {
          method: 'POST',
          body: nextFormData,
          signal: AbortSignal.timeout(5000) // Shorter timeout for next page check
        });
        if (nextResponse.ok) {
          const nextResult = await nextResponse.json();
          if (nextResult.success && nextResult.data && nextResult.data.data && nextResult.data.data.data && Array.isArray(nextResult.data.data.data) && nextResult.data.data.data.length > 0) {
            hasMore = true;
          }
        }
      } catch (nextError) {
        // If checking next page fails, assume there might be more
        hasMore = pageData.data.length >= casesPerPage;
      }
    }

    // Generate HTML for the new cases
    const html = generateLoadMoreCasesHTML(newCases, procedureName, hasNudity);
    return {
      success: true,
      html: html,
      casesLoaded: newCases.length,
      hasMore: hasMore,
      nextPage: hasMore ? parseInt(startPage) + 1 : null
    };
  } catch (error) {
    console.error('Direct API error for load more:', error);
    return {
      success: false,
      error: error.message
    };
  }
}

/**
 * Fallback method using original AJAX approach for load more
 */
function loadMoreCasesViaAjax(button, startPage, procedureIds, procedureName, hasNudity, loadedIds, originalText) {
  // Get AJAX configuration
  const nonce = window.bragBookGalleryConfig?.nonce || '';
  const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';

  // Get current procedure context from active nav link
  const activeLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
  const currentProcedureId = activeLink?.dataset.procedureId || '';
  const currentTermId = activeLink?.dataset.termId || '';

  // Prepare AJAX data
  const formData = new FormData();
  formData.append('action', 'brag_book_gallery_load_more_cases');
  formData.append('nonce', nonce);
  formData.append('start_page', startPage);
  formData.append('procedure_ids', procedureIds);
  formData.append('procedure_name', procedureName);
  formData.append('has_nudity', hasNudity ? '1' : '0');
  formData.append('loaded_ids', loadedIds.join(','));
  formData.append('current_procedure_id', currentProcedureId);
  formData.append('current_term_id', currentTermId);

  // Make AJAX request
  fetch(ajaxUrl, {
    method: 'POST',
    body: formData
  }).then(response => response.json()).then(data => {
    if (data.success) {
      processLoadMoreResult(data, button, originalText, startPage);
    } else {
      console.error('Failed to load more cases:', data.data ? data.data.message : 'Unknown error');
      button.disabled = false;
      button.textContent = originalText;
      alert('Failed to load more cases. Please try again.');
    }
  }).catch(error => {
    console.error('AJAX fallback error loading more cases:', error);
    button.disabled = false;
    button.textContent = originalText;
    alert('Error loading more cases. Please check your connection and try again.');
  });
}

/**
 * Scroll to gallery wrapper for better user experience
 * Accounts for websites with hero sections that may hide the gallery
 */
function scrollToGalleryWrapper() {
  const wrapper = document.querySelector('.brag-book-gallery-wrapper');
  if (wrapper) {
    // Use smooth scrolling with some offset for better UX
    const offsetTop = wrapper.getBoundingClientRect().top + window.pageYOffset - 20;
    window.scrollTo({
      top: offsetTop,
      behavior: 'smooth'
    });
  }
}

/**
 * Process the result from either direct API or AJAX for load more cases
 */
function processLoadMoreResult(result, button, originalText, startPage) {
  const data = result.data || result; // Handle both AJAX response format and direct API format

  // Find the cases grid container (try multiple possible selectors)
  let container = document.querySelector('.brag-book-gallery-case-grid.masonry-layout'); // PHP standard (new)
  if (!container) {
    container = document.querySelector('.brag-book-gallery-cases-grid'); // Previous JS version
  }
  if (!container) {
    container = document.querySelector('.brag-book-gallery-case-grid'); // Legacy JS
  }
  if (!container) {
    container = document.querySelector('.brag-book-gallery-cases-container'); // Outer container
  }
  if (!container) {
    // Try nested structures
    container = document.querySelector('.brag-book-gallery-cases-container .brag-book-gallery-cases-grid');
  }
  if (!container) {
    container = document.querySelector('.brag-book-gallery-grid'); // Fallback
  }
  if (container) {
    if (data.html) {
      // Find the last case card in the grid
      const lastCard = container.querySelector('.brag-book-gallery-case-card:last-child');
      if (lastCard) {
        // Insert after the last card
        lastCard.insertAdjacentHTML('afterend', data.html);
      } else {
        // If no cards exist, add to the container
        container.insertAdjacentHTML('beforeend', data.html);
      }

      // Scroll to gallery wrapper after loading items
      scrollToGalleryWrapper();
    } else {
      console.error('No HTML received from server');
    }
  } else {
    console.error('Container not found - tried .brag-book-gallery-case-grid.masonry-layout, .brag-book-gallery-cases-grid, .brag-book-gallery-case-grid, .brag-book-gallery-cases-container, and .brag-book-gallery-grid');
  }

  // Update button for next load
  const newCasesLoaded = data.casesLoaded || 0;
  if (data.hasMore && newCasesLoaded > 0) {
    // Increment page by 1 since we load 1 page at a time
    button.setAttribute('data-start-page', parseInt(startPage) + 1);
    button.disabled = false;
    button.textContent = originalText;
  } else {
    const loadMoreContainer = button.closest('.brag-book-gallery-load-more-container') || button.parentElement;
    if (loadMoreContainer) {
      loadMoreContainer.style.display = 'none';
    }
  }

  // Update the count display - only if we found the container
  if (container) {
    // Try multiple possible selectors for the count label
    const countLabel = document.querySelector('.brag-book-gallery-favorite-count-label') || document.querySelector('.cases-count') || document.querySelector('[class*="count-label"]');
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

  // Regenerate filters since new cases were loaded
  if (newCasesLoaded > 0) {
    setTimeout(() => {
      regenerateProcedureFilters();
    }, 100);
  }

  // Note: Nudity warnings are now handled at render time based on data-nudity attribute
}

/**
 * Generate HTML for load more cases (frontend version of PHP implementation)
 */
function generateLoadMoreCasesHTML(cases, procedureName, hasNudity) {
  let html = '';
  cases.forEach(caseData => {
    // Transform case data to match expected format
    const transformedCase = {
      ...caseData
    };

    // Extract main image from photoSets
    transformedCase.mainImageUrl = '';
    if (caseData.photoSets && Array.isArray(caseData.photoSets) && caseData.photoSets.length > 0) {
      const firstPhotoset = caseData.photoSets[0];
      transformedCase.mainImageUrl = firstPhotoset.postProcessedImageLocation || firstPhotoset.beforeLocationUrl || firstPhotoset.afterLocationUrl1 || '';
    }

    // Extract procedure title
    transformedCase.procedureTitle = procedureName || 'Unknown Procedure';
    if (caseData.procedures && Array.isArray(caseData.procedures) && caseData.procedures.length > 0) {
      transformedCase.procedureTitle = caseData.procedures[0].name || transformedCase.procedureTitle;
    }

    // Determine if case has nudity (simplified check)
    const caseHasNudity = hasNudity || false;

    // Generate case HTML
    html += generateLoadMoreCaseHTML(transformedCase, caseHasNudity);
  });
  return html;
}

/**
 * Generate HTML for a single load more case (matches PHP render_case_card structure)
 */
function generateLoadMoreCaseHTML(caseData, hasNudity) {
  const caseId = caseData.id || '';
  const procedureTitle = caseData.procedureTitle || 'Unknown Procedure';

  // Prepare data attributes for filtering (matching PHP prepare_case_data_attributes)
  let dataAttrs = 'data-card="true"';
  if (caseData.age) {
    dataAttrs += ` data-age="${escapeHtml(caseData.age)}"`;
  }
  if (caseData.gender) {
    dataAttrs += ` data-gender="${escapeHtml(caseData.gender.toLowerCase())}"`;
  }
  if (caseData.ethnicity) {
    dataAttrs += ` data-ethnicity="${escapeHtml(caseData.ethnicity.toLowerCase())}"`;
  }

  // Get procedure IDs
  const procedureIds = caseData.procedureIds ? caseData.procedureIds.join(',') : '';

  // Build case URL (matching PHP get_case_url structure)
  const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'gallery';
  const procedureSlug = extractProcedureSlugFromUrl() || 'case';
  const seoSuffix = caseData.caseDetails && caseData.caseDetails[0] && caseData.caseDetails[0].seoSuffixUrl || caseId;
  const caseUrl = `/${gallerySlug}/${procedureSlug}/${seoSuffix}/`;

  // Start building HTML (matching PHP render_case_card structure)
  let html = `<article class="brag-book-gallery-case-card" ${dataAttrs} data-case-id="${escapeHtml(caseId)}" data-procedure-ids="${escapeHtml(procedureIds)}">`;

  // Add case link (matching PHP structure)
  html += `<a href="${escapeHtml(caseUrl)}" class="case-link" data-case-id="${escapeHtml(caseId)}" data-procedure-ids="${escapeHtml(procedureIds)}">`;

  // Add images (matching PHP image display logic)
  const imageDisplayMode = window.bragBookGalleryConfig?.imageDisplayMode || 'single';
  if (caseData.photoSets && Array.isArray(caseData.photoSets) && caseData.photoSets.length > 0) {
    const firstPhoto = caseData.photoSets[0];
    if (imageDisplayMode === 'before_after') {
      // Show both before and after images
      html += `<div class="brag-book-gallery-case-images before-after">`;
      if (firstPhoto.beforePhoto) {
        html += `<img src="${escapeHtml(firstPhoto.beforePhoto)}" alt="Before" class="before-image" />`;
      }
      if (firstPhoto.afterPhoto) {
        html += `<img src="${escapeHtml(firstPhoto.afterPhoto)}" alt="After" class="after-image" />`;
      }
      html += `</div>`;
    } else {
      // Show single image (after preferred, fallback to before)
      const imageUrl = firstPhoto.afterPhoto || firstPhoto.beforePhoto || '';
      if (imageUrl) {
        html += `<div class="brag-book-gallery-case-images"><img src="${escapeHtml(imageUrl)}" alt="Case Image" /></div>`;
      }
    }
  }

  // Add nudity warning only if the active procedure has data-nudity="true"
  const activeLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
  const shouldAddNudityWarning = activeLink && activeLink.dataset.nudity === 'true';
  if (shouldAddNudityWarning) {
    html += `<div class="brag-book-gallery-nudity-warning">`;
    html += `<div class="brag-book-gallery-nudity-warning-content">`;
    html += `<p class="brag-book-gallery-nudity-warning-title">Nudity Warning</p>`;
    html += `<p class="brag-book-gallery-nudity-warning-caption">Click to proceed if you wish to view.</p>`;
    html += `<button class="brag-book-gallery-nudity-warning-button" type="button">Proceed</button>`;
    html += `</div>`;
    html += `</div>`;
  }

  // Add case title if available (matching PHP seo_headline)
  const seoHeadline = caseData.caseDetails && caseData.caseDetails[0] && caseData.caseDetails[0].seoHeadline || procedureTitle;
  if (seoHeadline) {
    html += `<h3 class="case-title">${escapeHtml(seoHeadline)}</h3>`;
  }
  html += `</a>`; // Close case link
  html += `</article>`; // Close article

  return html;
}

/**
 * Extract procedure slug from current URL
 */
function extractProcedureSlugFromUrl() {
  const pathSegments = window.location.pathname.split('/').filter(s => s);
  const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'gallery';

  // Find gallery slug position and get the next segment as procedure slug
  const galleryIndex = pathSegments.indexOf(gallerySlug.replace(/^\/+/, ''));
  if (galleryIndex >= 0 && galleryIndex + 1 < pathSegments.length) {
    const procedureSlug = pathSegments[galleryIndex + 1];
    // Make sure it's not a case ID (numeric)
    if (!/^\d+$/.test(procedureSlug)) {
      return procedureSlug;
    }
  }
  return null;
}

/**
 * Clear procedure filter and reload gallery
 */
window.clearProcedureFilter = function () {
  // Reload the gallery to show all cases
  window.location.href = window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/') + '/';
};

// Filter results element no longer used - function removed

/**
 * Update filter badges based on active demographic filters only
 */
function updateFilterBadges() {
  // Remove any old/legacy filter badges container
  const oldBadgesContainer = document.querySelector('.brag-book-gallery-filter-badges');
  if (oldBadgesContainer) {
    oldBadgesContainer.remove();
  }

  // Find or create the active filters section
  const controlsLeft = document.querySelector('.brag-book-gallery-controls-left');
  const clearAllButton = controlsLeft ? controlsLeft.querySelector('.brag-book-gallery-clear-all-filters') : null;

  // Look for active filters section specifically within the controls left area
  let activeFiltersSection = controlsLeft ? controlsLeft.querySelector('.brag-book-gallery-active-filters') : null;
  if (!activeFiltersSection) {
    // Create it if it doesn't exist
    activeFiltersSection = document.createElement('div');
    activeFiltersSection.className = 'brag-book-gallery-active-filters';

    // Insert before the Clear All button inside controls-left
    if (clearAllButton && controlsLeft) {
      controlsLeft.insertBefore(activeFiltersSection, clearAllButton);
    } else if (controlsLeft) {
      // Fallback: append to controls left if no clear button found
      controlsLeft.appendChild(activeFiltersSection);
    }
  }

  // Always clear all existing badges first
  activeFiltersSection.innerHTML = '';

  // Get checked demographic filter checkboxes directly from the DOM
  const checkedFilters = document.querySelectorAll('.brag-book-gallery-filter-option input[type="checkbox"]:checked');
  if (checkedFilters.length === 0) {
    // Hide the active filters section when no filters are applied
    activeFiltersSection.style.display = 'none';

    // Remove the Clear All button if it exists
    const existingClearAllButton = document.querySelector('.brag-book-gallery-clear-all-filters');
    if (existingClearAllButton) {
      existingClearAllButton.remove();
    }
    return;
  }

  // Show the active filters section when filters are applied
  activeFiltersSection.style.display = 'flex';

  // Create badges for each checked demographic filter
  checkedFilters.forEach(checkbox => {
    const filterType = checkbox.dataset.filterType;
    const filterValue = checkbox.value;
    const label = checkbox.parentNode.querySelector('label');
    const displayValue = label ? label.textContent.trim() : filterValue;
    const badge = document.createElement('div');
    badge.className = 'brag-book-gallery-filter-badge';
    badge.setAttribute('data-filter-key', `${filterType}:${filterValue}`);
    badge.setAttribute('data-filter-type', filterType);
    badge.setAttribute('data-filter-value', filterValue);

    // Format the display type
    let displayType;
    if (filterType.startsWith('procedure_detail_')) {
      // For procedure details, extract and format the label
      // e.g., 'procedure_detail_implant_size' -> 'Implant Size'
      const detailName = filterType.replace('procedure_detail_', '');
      displayType = detailName.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    } else {
      // Capitalize standard filter type
      displayType = filterType.charAt(0).toUpperCase() + filterType.slice(1);
    }
    badge.innerHTML = `
			<span class="brag-book-gallery-badge-text">${displayType}: ${escapeHtml(displayValue)}</span>
			<button class="brag-book-gallery-badge-remove" aria-label="Remove ${displayType}: ${escapeHtml(displayValue)} filter">
				<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
					<path d="M13 1L1 13M1 1l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
				</svg>
			</button>
		`;
    activeFiltersSection.appendChild(badge);
  });

  // Add Clear All button after the active filters section if there are any filters
  if (checkedFilters.length > 0) {
    // Remove any existing Clear All button first
    const existingClearAllButton = document.querySelector('.brag-book-gallery-clear-all-filters');
    if (existingClearAllButton) {
      existingClearAllButton.remove();
    }
    const clearAllButton = document.createElement('button');
    clearAllButton.className = 'brag-book-gallery-clear-all-filters';
    clearAllButton.setAttribute('data-action', 'clear-filters');
    clearAllButton.textContent = 'Clear All';
    clearAllButton.onclick = function (event) {
      event.preventDefault();

      // Try multiple approaches to call the function
      try {
        // Method 1: Direct call
        if (typeof window.clearProcedureFilters === 'function') {
          window.clearProcedureFilters();
          return;
        }

        // Method 2: Try global scope
        if (typeof clearProcedureFilters === 'function') {
          clearProcedureFilters();
          return;
        }

        // Uncheck all filter checkboxes
        const checkboxes = document.querySelectorAll('.brag-book-gallery-filter-option input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
          checkbox.checked = false;
        });

        // Show all cards
        const cards = document.querySelectorAll('.brag-book-gallery-case-card');
        cards.forEach(card => {
          card.style.display = '';
        });

        // Hide active filters section
        const activeFiltersSection = document.querySelector('.brag-book-gallery-active-filters');
        if (activeFiltersSection) {
          activeFiltersSection.style.display = 'none';
          activeFiltersSection.innerHTML = '';
        }

        // Filter results element no longer used - removed

        // Remove has-active-filters class
        const toggle = document.querySelector('.brag-book-gallery-filter-dropdown__toggle');
        if (toggle) {
          toggle.classList.remove('has-active-filters');
        }
      } catch (error) {
        console.error('Error in Clear All button:', error);
      }
    };

    // Insert the Clear All button after the active filters section
    if (activeFiltersSection.parentNode) {
      activeFiltersSection.parentNode.insertBefore(clearAllButton, activeFiltersSection.nextSibling);
    }
  } else {
    // Remove Clear All button if no filters are active
    const existingClearAllButton = document.querySelector('.brag-book-gallery-clear-all-filters');
    if (existingClearAllButton) {
      existingClearAllButton.remove();
    }
  }
}

/**
 * Remove a specific filter badge and update the filtering
 * @param {string} filterType - The type of filter (age, gender, etc.)
 * @param {string} filterValue - The value of the filter to remove
 */
window.removeFilterBadge = function (filterType, filterValue) {
  // Find and uncheck the corresponding checkbox
  // We can't use querySelector with value attribute containing quotes, so iterate through checkboxes
  const checkboxes = document.querySelectorAll(`#brag-book-gallery-filters input[data-filter-type="${filterType}"]`);
  let foundCheckbox = null;
  checkboxes.forEach(cb => {
    if (cb.value === filterValue) {
      foundCheckbox = cb;
    }
  });
  if (foundCheckbox) {
    foundCheckbox.checked = false;
  }

  // Re-apply filters to update the display
  applyProcedureFilters();
};

/**
 * Escape HTML characters for safe output (helper function)
 */
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
;

/**
 * Calculate and apply image aspect ratio dynamically
 * @param {HTMLImageElement} img - The image element that has loaded
 */
window.bragBookSetImageAspectRatio = function (img) {
  // Get the natural dimensions of the image
  const naturalWidth = img.naturalWidth;
  const naturalHeight = img.naturalHeight;

  // Only proceed if we have valid dimensions
  if (naturalWidth && naturalHeight) {
    // Calculate aspect ratio as width/height
    const aspectRatio = naturalWidth + '/' + naturalHeight;

    // Find the parent container (either .brag-book-gallery-image-container or .brag-book-gallery-thumb-image)
    const container = img.closest('.brag-book-gallery-image-container, .brag-book-gallery-thumb-image');
    if (container) {
      // Apply the aspect ratio using CSS aspect-ratio property
      container.style.aspectRatio = aspectRatio;
      container.style.width = '100%';
      container.style.height = 'auto';
      container.style.minHeight = 'auto';
      container.style.maxHeight = 'none';

      // Ensure the image fills the container properly
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'contain';

      // Remove the loading indicator
      container.removeAttribute('data-image-loading');
    }
  }
};

/**
 * Initialize infinite scroll functionality for automatic content loading
 * Triggers load more when user scrolls near bottom of page
 */
window.initInfiniteScroll = function () {
  // Check if infinite scroll is enabled
  const infiniteScrollEnabled = window.bragBookGalleryConfig?.infiniteScroll === 'yes';
  if (!infiniteScrollEnabled) {
    return;
  }
  let isLoading = false;
  let scrollTimeout;
  const handleScroll = () => {
    // Clear previous timeout
    clearTimeout(scrollTimeout);

    // Debounce scroll events
    scrollTimeout = setTimeout(() => {
      // Don't trigger if already loading
      if (isLoading) return;

      // Find the Load More button - look for button with onclick="loadMoreCases(this)"
      const loadMoreButton = document.querySelector('button[onclick*="loadMoreCases"]') || document.querySelector('.brag-book-gallery-load-more button');
      if (!loadMoreButton || loadMoreButton.disabled || loadMoreButton.style.display === 'none') {
        return;
      }

      // Check if button's container is hidden
      const buttonContainer = loadMoreButton.closest('.brag-book-gallery-load-more-container');
      if (buttonContainer && buttonContainer.style.display === 'none') {
        return;
      }

      // Calculate scroll position
      const scrollPosition = window.innerHeight + window.scrollY;
      const documentHeight = document.documentElement.offsetHeight;
      const triggerPoint = documentHeight - 800; // Trigger 800px before bottom

      // Check if we've scrolled far enough
      if (scrollPosition >= triggerPoint) {
        isLoading = true;

        // Trigger the load more function
        loadMoreButton.click();

        // Reset loading flag after a delay
        setTimeout(() => {
          isLoading = false;
        }, 1000);
      }
    }, 100); // 100ms debounce
  };

  // Add scroll event listener
  window.addEventListener('scroll', handleScroll, {
    passive: true
  });

  // Also check on resize
  window.addEventListener('resize', handleScroll, {
    passive: true
  });

  // Store reference for cleanup if needed
  window.infiniteScrollHandler = handleScroll;
};

/**
 * Immediately hide filter results on page load (before DOMContentLoaded)
 * This runs as soon as the script loads to prevent flash of content
 */
(function () {
  // Hide filter results immediately if they exist
  const hideFilterResults = () => {
    const resultsEl = document.querySelector('.brag-book-gallery-filter-results');
    if (resultsEl) {
      resultsEl.style.display = 'none !important';
    }
  };

  // Run immediately
  hideFilterResults();

  // Also run when DOM is ready (in case element loads later)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hideFilterResults);
  } else {
    hideFilterResults();
  }

  // Watch for the element being added to the DOM
  const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      if (mutation.type === 'childList') {
        mutation.addedNodes.forEach(node => {
          if (node.nodeType === 1 && node.classList && node.classList.contains('brag-book-gallery-filter-results')) {
            node.style.display = 'none !important';
          }
        });
      }
    });
  });

  // Start observing
  observer.observe(document.body || document.documentElement, {
    childList: true,
    subtree: true
  });
})();

/**
 * Global instances for utility managers
 */
let nudityManager; // Nudity warning manager instance
let phoneFormatter; // Phone number formatter instance
let allCasesData = []; // Cache for all loaded cases data
let currentDisplayedCases = 0; // Track how many cases are currently displayed

/**
 * Initialize case pagination system - load all data via XHR and manage display
 */
function initializeCasePagination() {
  // Check if we're on a cases page that should use pagination
  const caseGrid = document.querySelector('.brag-book-gallery-case-grid, .brag-book-gallery-cases-grid');
  if (!caseGrid) return;

  // Check if there are already cases rendered server-side
  const existingCases = caseGrid.querySelectorAll('.brag-book-gallery-case-card');
  currentDisplayedCases = existingCases.length;

  // Load all cases via AJAX for the current procedure
  loadAllCasesForPagination();
}

/**
 * Load all cases for the current procedure via AJAX
 */
async function loadAllCasesForPagination() {
  const activeLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
  if (!activeLink) return;
  const procedureIds = activeLink.dataset.procedureIds;
  const procedureName = activeLink.textContent.trim().replace(/\(\d+\)$/, '').trim();
  if (!procedureIds) return;
  try {
    // Get AJAX configuration
    const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.bragBookGalleryConfig?.nonce || '';

    // Prepare request data to load ALL cases for this procedure
    const formData = new FormData();
    formData.append('action', 'brag_book_gallery_load_more_cases');
    formData.append('nonce', nonce);
    formData.append('procedure_ids', procedureIds);
    formData.append('procedure_name', procedureName);
    formData.append('load_all', '1'); // Signal to load all cases
    formData.append('has_nudity', activeLink.dataset.nudity === 'true' ? '1' : '0');
    const response = await fetch(ajaxUrl, {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    if (result.success && result.data) {
      // Store all cases data
      allCasesData = result.data;

      // Update load more button visibility
      updateLoadMoreButton();
    } else {
      console.error('Failed to load cases data:', result);
    }
  } catch (error) {
    console.error('Failed to load cases for pagination:', error);
  }
}

/**
 * Simplified load more function that uses server-side pagination
 */
window.loadMoreCasesFromCache = function (button) {
  // Disable button and show loading state
  button.disabled = true;
  const originalText = button.textContent;
  button.textContent = 'Loading...';

  // Get data from button attributes
  const startPage = button.getAttribute('data-start-page') || '2';
  const procedureIds = button.getAttribute('data-procedure-ids') || '';
  const procedureName = button.getAttribute('data-procedure-name') || '';

  // Get AJAX configuration
  const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
  const nonce = window.bragBookGalleryConfig?.nonce || '';

  // Get current procedure context from active nav link
  const activeLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
  const currentProcedureId = activeLink?.dataset.procedureId || '';
  const currentTermId = activeLink?.dataset.termId || '';

  // Prepare request data for server-side pagination
  const formData = new FormData();
  formData.append('action', 'brag_book_gallery_load_more_cases');
  formData.append('nonce', nonce);
  formData.append('start_page', startPage);
  formData.append('procedure_ids', procedureIds);
  formData.append('procedure_name', procedureName);
  formData.append('loaded_ids', ''); // Will be populated by server
  formData.append('current_procedure_id', currentProcedureId);
  formData.append('current_term_id', currentTermId);

  // Make AJAX request
  fetch(ajaxUrl, {
    method: 'POST',
    body: formData
  }).then(response => response.json()).then(result => {
    if (result.success && result.data && result.data.html) {
      // Find the cases grid container
      let container = document.querySelector('.brag-book-gallery-case-grid');
      if (!container) {
        container = document.querySelector('.brag-book-gallery-cases-grid');
      }
      if (container) {
        // Find the last case card and insert after it
        const lastCard = container.querySelector('.brag-book-gallery-case-card:last-child');
        if (lastCard) {
          lastCard.insertAdjacentHTML('afterend', result.data.html);
        } else {
          container.insertAdjacentHTML('beforeend', result.data.html);
        }

        // Scroll to gallery wrapper after loading items
        scrollToGalleryWrapper();

        // Update button for next page
        const nextPage = parseInt(startPage) + 1;
        button.setAttribute('data-start-page', nextPage.toString());

        // Check if there are more pages
        if (result.data.hasMore === false) {
          button.style.display = 'none';
        } else {
          button.disabled = false;
          button.textContent = originalText;
        }
      }
    } else {
      console.error('Load more failed:', result);
      button.disabled = false;
      button.textContent = originalText;
    }
  }).catch(error => {
    console.error('Load more error:', error);
    button.disabled = false;
    button.textContent = originalText;
  });
};

/**
 * Update load more button visibility and state
 */
function updateLoadMoreButton() {
  const loadMoreBtn = document.querySelector('.brag-book-gallery-button--load-more');
  if (!loadMoreBtn) return;
  if (allCasesData.length > currentDisplayedCases) {
    loadMoreBtn.style.display = '';
    // Update the onclick to use our new cached function
    loadMoreBtn.setAttribute('onclick', 'loadMoreCasesFromCache(this)');
  } else {
    loadMoreBtn.style.display = 'none';
  }
}
document.addEventListener('DOMContentLoaded', () => {
  // Clean up any server-generated procedure badges first
  cleanupProcedureBadges();

  // Filter results element no longer used - removed

  // Remove old/legacy filter badges container
  const oldBadgesContainer = document.querySelector('.brag-book-gallery-filter-badges');
  if (oldBadgesContainer) {
    oldBadgesContainer.remove();
  }
  new _main_app_js__WEBPACK_IMPORTED_MODULE_0__["default"]();
  nudityManager = new _utilities_js__WEBPACK_IMPORTED_MODULE_2__.NudityWarningManager();
  phoneFormatter = new _utilities_js__WEBPACK_IMPORTED_MODULE_2__.PhoneFormatter();

  // Initialize carousels
  const carouselElements = document.querySelectorAll('.brag-book-gallery-carousel-wrapper');
  if (carouselElements.length > 0) {
    new _carousel_js__WEBPACK_IMPORTED_MODULE_1__["default"]({});
  }

  // Initialize procedure referrer tracking for combo procedures
  initializeProcedureReferrerTracking();

  // Mark grid as initialized after initial load animations
  const grid = document.querySelector('.brag-book-gallery-case-grid');
  if (grid) {
    setTimeout(() => {
      grid.classList.add('grid-initialized');
    }, 1000); // Wait for initial animations to complete

    // Apply saved grid preference if available and on desktop
    // Skip for tiles view which has fixed 2-column layout
    const isTilesView = grid.classList.contains('brag-book-gallery-case-grid--tiles');
    const isDesktop = window.innerWidth >= 1024;
    if (isDesktop && !isTilesView) {
      const savedColumns = localStorage.getItem('brag-book-gallery-grid-columns');
      if (savedColumns) {
        const columns = parseInt(savedColumns);
        grid.setAttribute('data-columns', columns);

        // Update button states
        const buttons = document.querySelectorAll('.brag-book-gallery-grid-btn');
        buttons.forEach(btn => {
          const btnCols = parseInt(btn.dataset.columns);
          if (btnCols === columns) {
            btn.classList.add('active');
          } else {
            btn.classList.remove('active');
          }
        });
      } else {
        // Default to 3 columns on desktop
        grid.setAttribute('data-columns', '3');
      }
    }
  }

  // Initialize infinite scroll if enabled
  initInfiniteScroll();
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
  // Initialize filters after a brief delay to ensure DOM is ready
  setTimeout(function () {
    // Ensure the complete dataset is available
    if (!window.bragBookCompleteDataset && window.bragBookGalleryConfig && window.bragBookGalleryConfig.completeDataset) {
      window.bragBookCompleteDataset = window.bragBookGalleryConfig.completeDataset;
    }
    initializeProcedureFilters();

    // Case navigation is now handled with anchor links, no JavaScript needed

    // Check if we need to load a case on initial page load
    const wrapper = document.querySelector('.brag-book-gallery-wrapper');
    if (wrapper && wrapper.dataset.initialCaseId) {
      const caseId = wrapper.dataset.initialCaseId;
      // Extract procedure slug from URL - it's the segment before the case ID
      // URL format: /gallery/procedure-slug/case-id
      const pathSegments = window.location.pathname.split('/').filter(s => s);
      // Find the segment before the last one (case ID)
      const procedureSlug = pathSegments.length > 2 ? pathSegments[pathSegments.length - 2] : '';

      // Try to get the procedure name from the sidebar data
      let procedureName = '';

      // First try to get from active sidebar link (if it exists and is already marked active)
      const activeLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedureSlug}"]`);
      if (activeLink) {
        const label = activeLink.querySelector('.brag-book-gallery-filter-option-label');
        if (label) {
          procedureName = label.textContent.trim();
        }
      }

      // If not found in DOM, lookup in sidebar data
      if (!procedureName && window.bragBookGalleryConfig && window.bragBookGalleryConfig.sidebarData) {
        const sidebarData = window.bragBookGalleryConfig.sidebarData;
        // Search through categories for the procedure
        for (const category of Object.values(sidebarData)) {
          if (category.procedures) {
            for (const procedure of category.procedures) {
              if (procedure.slug === procedureSlug) {
                procedureName = procedure.name;
                break;
              }
            }
          }
          if (procedureName) break;
        }
      }

      // Try to find the case card to get procedure IDs
      let procedureIds = '';
      setTimeout(() => {
        const caseCard = document.querySelector(`.brag-book-gallery-case-card[data-case-id="${caseId}"]`);
        if (caseCard && caseCard.dataset.procedureIds) {
          procedureIds = caseCard.dataset.procedureIds;
        }
        window.loadCaseDetailsWithName(caseId, '', procedureSlug, procedureName, procedureIds);
      }, 200);
    }
  }, 100);
});

// Handle details toggle event
document.addEventListener('toggle', function (e) {
  if (e.target.id === 'procedure-filters-details') {
    const details = e.target;
    if (details.open && !details.dataset.initialized) {
      generateProcedureFilterOptions();
      details.dataset.initialized = 'true';
    }
  }
});

// Close details when clicking outside
document.addEventListener('click', function (e) {
  // Handle badge remove button clicks
  const badgeRemoveButton = e.target.closest('.brag-book-gallery-badge-remove');
  if (badgeRemoveButton) {
    e.preventDefault();
    const badge = badgeRemoveButton.closest('.brag-book-gallery-filter-badge');
    if (badge) {
      const filterType = badge.getAttribute('data-filter-type');
      const filterValue = badge.getAttribute('data-filter-value');
      if (filterType && filterValue) {
        removeFilterBadge(filterType, filterValue);
      }
    }
    return;
  }

  // Close filter dropdown when clicking outside
  const details = document.getElementById('procedure-filters-details');
  const panel = document.querySelector('.brag-book-gallery-filter-dropdown__panel');
  if (details && details.open && panel) {
    if (!details.contains(e.target) && !panel.contains(e.target)) {
      details.open = false;
    }
  }
});

/***/ }),

/***/ "./src/js/modules/main-app.js":
/*!************************************!*\
  !*** ./src/js/modules/main-app.js ***!
  \************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _dialog_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./dialog.js */ "./src/js/modules/dialog.js");
/* harmony import */ var _filter_system_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./filter-system.js */ "./src/js/modules/filter-system.js");
/* harmony import */ var _mobile_menu_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./mobile-menu.js */ "./src/js/modules/mobile-menu.js");
/* harmony import */ var _favorites_manager_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./favorites-manager.js */ "./src/js/modules/favorites-manager.js");
/* harmony import */ var _share_manager_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./share-manager.js */ "./src/js/modules/share-manager.js");
/* harmony import */ var _search_autocomplete_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./search-autocomplete.js */ "./src/js/modules/search-autocomplete.js");
/* harmony import */ var _utilities_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./utilities.js */ "./src/js/modules/utilities.js");
/* harmony import */ var _gallery_selector_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./gallery-selector.js */ "./src/js/modules/gallery-selector.js");









/**
 * Main Application Controller
 * Orchestrates all gallery components including carousels, filters, dialogs, and favorites
 * Manages global state and component communication
 */
class BRAGbookGalleryApp {
  /**
   * Initialize the main gallery application
   */
  constructor() {
    // Component storage for organized access
    this.components = {};
    // Store global reference for other modules to access
    window.bragBookGalleryApp = this;
    // Start initialization process
    this.init();
  }

  /**
   * Initialize all gallery components in sequence
   */
  async init() {
    // Track page view on load (case or procedure)
    this.trackPageView();

    // Check if this is a direct case URL first and handle it
    if (await this.handleDirectCaseUrl()) {
      // If we're loading a case directly, skip normal gallery initialization
      // but still initialize essential components
      this.initializeDialogs();
      this.initializeMobileMenu();
      this.initializeCaseLinks();
      this.initializeNudityWarning();
      this.initializeShareManager();
      this.initializeFavorites();
      this.initializeCasePreloading();
      this.initializeCaseCarouselPagination();
      return;
    }

    // Initialize core components for normal gallery view
    this.initializeDialogs();
    this.initializeFilters();
    this.initializeMobileMenu();
    this.initializeGallerySelector();
    this.initializeFavorites();
    this.initializeSearch();
    this.initializeShareManager();
    this.initializeConsultationForm();
    this.initializeCaseLinks();
    this.initializeNudityWarning();
    this.initializeCasePreloading();
    this.initializeCaseCarouselPagination();
    this.initializeProceduresLoadMore();

    // Auto-activate favorites view if on favorites page
    const galleryContent = document.getElementById('gallery-content');
    if (galleryContent && galleryContent.dataset.favoritesPage === 'true') {
      // Delay to ensure all components are initialized
      setTimeout(() => {
        this.showFavoritesOnly();
      }, 100);
    }
  }

  /**
   * Initialize dialog components for modals and popups
   */
  initializeDialogs() {
    // Initialize consultation request dialog
    this.components.consultationDialog = new _dialog_js__WEBPACK_IMPORTED_MODULE_0__["default"]('consultationDialog', {
      onOpen: () => {},
      onClose: () => {}
    });

    // Bind consultation buttons to dialog opening using event delegation
    // This works for buttons added dynamically (e.g., in tiles view)
    document.addEventListener('click', e => {
      const button = e.target.closest('[data-action="request-consultation"]');
      if (button && this.components.consultationDialog) {
        e.preventDefault();
        this.components.consultationDialog.open();
      }
    });
  }

  /**
   * Initialize the filter system for procedure and demographic filtering
   */
  initializeFilters() {
    const filterContainer = document.querySelector('.brag-book-gallery-nav');

    // Configure filter mode (javascript vs navigation)
    const mode = filterContainer?.dataset.filterMode || 'javascript';
    this.components.filterSystem = new _filter_system_js__WEBPACK_IMPORTED_MODULE_1__["default"](filterContainer, {
      mode: mode,
      baseUrl: '/gallery',
      // Customize as needed
      onFilterChange: activeFilters => {
        this.applyFilters(activeFilters);
      },
      onNavigate: url => {
        // Custom navigation handler if needed
        window.location.href = url;
      }
    });

    // Generate procedure filters from DOM case cards on page load
    this.initializeProcedureFilters();

    // Set up Clear All button
    this.initializeClearAllButton();

    // Initialize demographic filter badge integration
    this.initializeDemographicFilterBadges();
  }

  /**
   * Initialize procedure filters from case card data attributes
   */
  initializeProcedureFilters() {
    const filterSystem = this.components.filterSystem;
    if (!filterSystem) {
      return;
    }

    // Find the procedure filters container
    const procedureFiltersContainer = document.getElementById('brag-book-gallery-filters');
    if (!procedureFiltersContainer) {
      return;
    }

    // Generate filter HTML from DOM case cards
    const filterHTML = filterSystem.generateFiltersFromDOMCards();

    // Only populate if we have filter HTML
    if (filterHTML) {
      procedureFiltersContainer.innerHTML = filterHTML;

      // Show the procedure filters dropdown
      const procedureFiltersDetails = document.getElementById('procedure-filters-details');
      if (procedureFiltersDetails) {
        procedureFiltersDetails.style.display = '';
        procedureFiltersDetails.setAttribute('data-initialized', 'true');
      }

      // Bind event listeners to the new filter checkboxes
      this.bindProcedureFilterEvents();
    }
  }

  /**
   * Bind events to procedure filter checkboxes
   */
  bindProcedureFilterEvents() {
    const filterCheckboxes = document.querySelectorAll('#brag-book-gallery-filters input[type="checkbox"]');
    filterCheckboxes.forEach(checkbox => {
      checkbox.addEventListener('change', e => {
        this.applyProcedureFilters();
      });
    });
  }

  /**
   * Apply procedure filters to case cards
   */
  applyProcedureFilters() {
    // Collect active filters
    const activeFilters = {
      age: [],
      gender: [],
      ethnicity: [],
      height: [],
      weight: []
    };

    // Get all checked filter checkboxes
    const checkedFilters = document.querySelectorAll('#brag-book-gallery-filters input[type="checkbox"]:checked');
    checkedFilters.forEach(checkbox => {
      const filterType = checkbox.dataset.filterType;
      const value = checkbox.value;
      if (activeFilters[filterType]) {
        activeFilters[filterType].push(value);
      }
    });

    // Get all case cards
    const caseCards = document.querySelectorAll('.brag-book-gallery-case-card');

    // Filter case cards
    caseCards.forEach(card => {
      let show = true;

      // Check age filter
      if (activeFilters.age.length > 0) {
        const age = parseInt(card.dataset.age);
        let ageMatch = false;
        activeFilters.age.forEach(range => {
          if (range === '18-24' && age >= 18 && age < 25) ageMatch = true;else if (range === '25-34' && age >= 25 && age < 35) ageMatch = true;else if (range === '35-44' && age >= 35 && age < 45) ageMatch = true;else if (range === '45-54' && age >= 45 && age < 55) ageMatch = true;else if (range === '55-64' && age >= 55 && age < 65) ageMatch = true;else if (range === '65+' && age >= 65) ageMatch = true;
        });
        if (!ageMatch) show = false;
      }

      // Check gender filter
      if (activeFilters.gender.length > 0) {
        const gender = (card.dataset.gender || '').toLowerCase();
        if (!activeFilters.gender.includes(gender)) {
          show = false;
        }
      }

      // Check ethnicity filter
      if (activeFilters.ethnicity.length > 0) {
        const ethnicity = (card.dataset.ethnicity || '').toLowerCase();
        if (!activeFilters.ethnicity.some(e => e.toLowerCase() === ethnicity)) {
          show = false;
        }
      }

      // Check height filter
      if (activeFilters.height.length > 0) {
        const height = parseFloat(card.dataset.height);
        let heightMatch = false;
        activeFilters.height.forEach(range => {
          if (range === 'Under 5\'0"' && height < 60) heightMatch = true;else if (range === '5\'0" - 5\'3"' && height >= 60 && height < 64) heightMatch = true;else if (range === '5\'4" - 5\'7"' && height >= 64 && height < 68) heightMatch = true;else if (range === '5\'8" - 5\'11"' && height >= 68 && height < 72) heightMatch = true;else if (range === '6\'0" and above' && height >= 72) heightMatch = true;
        });
        if (!heightMatch) show = false;
      }

      // Check weight filter
      if (activeFilters.weight.length > 0) {
        const weight = parseFloat(card.dataset.weight);
        let weightMatch = false;
        activeFilters.weight.forEach(range => {
          if (range === 'Under 120 lbs' && weight < 120) weightMatch = true;else if (range === '120-149 lbs' && weight >= 120 && weight < 150) weightMatch = true;else if (range === '150-179 lbs' && weight >= 150 && weight < 180) weightMatch = true;else if (range === '180-209 lbs' && weight >= 180 && weight < 210) weightMatch = true;else if (range === '210+ lbs' && weight >= 210) weightMatch = true;
        });
        if (!weightMatch) show = false;
      }

      // Show or hide the card
      if (show) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  }

  /**
   * Initialize mobile navigation menu
   */
  initializeMobileMenu() {
    this.components.mobileMenu = new _mobile_menu_js__WEBPACK_IMPORTED_MODULE_2__["default"]();
  }

  /**
   * Initialize gallery selector navigation for tiles view
   */
  initializeGallerySelector() {
    (0,_gallery_selector_js__WEBPACK_IMPORTED_MODULE_7__.initGallerySelector)();
  }

  /**
   * Initialize favorites management system
   */
  initializeFavorites() {
    // Create favorites manager with count update callback
    this.components.favoritesManager = new _favorites_manager_js__WEBPACK_IMPORTED_MODULE_3__["default"]({
      onUpdate: favorites => {
        // Update UI elements that display favorite counts
        this.updateFavoritesCount(favorites.size);
      }
    });

    // Initialize the count on page load
    if (this.components.favoritesManager) {
      const initialCount = this.components.favoritesManager.getFavorites().size;
      this.updateFavoritesCount(initialCount);

      // Update heart button states based on localStorage favorites
      this.updateFavoriteHeartStates();
    }

    // Add click handler for My Favorites button
    this.initializeFavoritesButton();

    // Listen for favorites updates to refresh My Favorites page if it's active
    window.addEventListener('favoritesUpdated', event => {
      // Check if we're on the My Favorites page
      const currentPath = window.location.pathname;
      const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'before-after';
      const favoritesPath = `/${gallerySlug}/myfavorites/`;
      if (currentPath === favoritesPath || currentPath.includes('myfavorites')) {
        // Small delay to ensure localStorage is updated
        setTimeout(() => {
          this.showFavoritesOnly();
        }, 100);
      }
    });
  }

  /**
   * Update favorite heart button states based on localStorage favorites
   */
  updateFavoriteHeartStates() {
    // Get favorites from localStorage
    let favorites = [];
    try {
      const storedFavorites = localStorage.getItem('brag-book-favorites');
      if (storedFavorites) {
        favorites = JSON.parse(storedFavorites);
      }
    } catch (e) {
      console.error('Failed to load favorites from localStorage:', e);
      return;
    }
    if (!Array.isArray(favorites) || favorites.length === 0) {
      return; // No favorites to process
    }

    // Find all favorite buttons on the page
    const favoriteButtons = document.querySelectorAll('.brag-book-gallery-favorite-button');
    favoriteButtons.forEach(button => {
      // Get the case ID from the button's data attributes
      let caseIds = []; // Array of possible IDs to check

      // Get WordPress post ID from the case card (highest priority)
      const caseCard = button.closest('.brag-book-gallery-case-card');
      if (caseCard && caseCard.dataset.postId) {
        caseIds.push(caseCard.dataset.postId);
      }

      // Get API case ID from case card
      if (caseCard && caseCard.dataset.caseId) {
        caseIds.push(caseCard.dataset.caseId);
      }

      // Try different data attribute sources from button
      if (button.dataset.itemId) {
        // Add the full item ID
        caseIds.push(button.dataset.itemId);

        // Extract numeric ID from values like "case-12345"
        const matches = button.dataset.itemId.match(/(\d+)/);
        if (matches) {
          caseIds.push(matches[1]);
        }
      }
      if (button.dataset.caseId) {
        caseIds.push(button.dataset.caseId);
      }
      if (caseIds.length === 0) {
        return; // Skip if no case ID found
      }

      // Check if ANY of these case IDs is in the favorites
      const isFavorited = caseIds.some(id => favorites.includes(String(id)) || favorites.includes(id) || favorites.includes(`case-${id}`));
      if (isFavorited) {
        // Mark as favorited
        button.dataset.favorited = 'true';
        button.setAttribute('aria-label', 'Remove from favorites');
      } else {
        // Ensure it's marked as not favorited
        button.dataset.favorited = 'false';
        button.setAttribute('aria-label', 'Add to favorites');
      }
    });
  }

  /**
   * Initialize search autocomplete components for desktop and mobile
   */
  initializeSearch() {
    // Find all search wrapper elements (supports multiple instances)
    const searchWrappers = document.querySelectorAll('.brag-book-gallery-search-wrapper');
    this.components.searchAutocompletes = [];
    searchWrappers.forEach(searchWrapper => {
      // Create search instance with configuration
      const searchInstance = new _search_autocomplete_js__WEBPACK_IMPORTED_MODULE_5__["default"](searchWrapper, {
        minChars: 1,
        // Start searching after 1 character
        debounceDelay: 200,
        // 200ms delay for performance
        maxResults: 10,
        // Limit results shown
        onSelect: result => {
          // The checkbox is automatically checked by the SearchAutocomplete class
        }
      });
      this.components.searchAutocompletes.push(searchInstance);
    });
  }

  /**
   * Initialize social sharing manager if sharing is enabled
   */
  initializeShareManager() {
    // Only initialize if sharing is enabled in configuration
    if (typeof bragBookGalleryConfig !== 'undefined' && bragBookGalleryConfig.enableSharing === 'yes') {
      this.components.shareManager = new _share_manager_js__WEBPACK_IMPORTED_MODULE_4__["default"]({
        onShare: data => {}
      });
    }
  }
  initializeConsultationForm() {
    const form = document.querySelector('[data-form="consultation"]');
    if (form) {
      form.addEventListener('submit', e => {
        e.preventDefault();
        this.handleFormSubmit(e.target);
      });
    }

    // Clear messages when dialog is opened
    const consultationDialog = document.getElementById('consultationDialog');
    if (consultationDialog) {
      // Listen for when dialog is shown
      const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
          if (mutation.type === 'attributes' && mutation.attributeName === 'open') {
            if (consultationDialog.hasAttribute('open')) {
              // Dialog was opened, clear any previous messages
              this.hideModalMessage();
            }
          }
        });
      });
      observer.observe(consultationDialog, {
        attributes: true
      });
    }
  }

  /**
   * Store procedure referrer from case card data
   * Extracted to avoid code duplication
   * @param {HTMLElement} caseCard - The case card element
   */
  storeProcedureReferrerFromCard(caseCard) {
    if (!caseCard || typeof window.storeProcedureReferrer !== 'function') return;

    // Extract all IDs from case card data attributes
    const termId = caseCard.dataset.currentTermId;
    const procedureId = caseCard.dataset.currentProcedureId;
    const caseId = caseCard.dataset.caseId;
    const caseWpId = caseCard.dataset.postId;
    if (!termId) {
      console.warn('storeProcedureReferrerFromCard - No termId found');
      return;
    }

    // Extract procedure slug from URL
    // URL pattern: /{gallery-slug}/{procedure}/{case-slug}
    // Procedure is always the second path segment
    const urlPath = window.location.pathname;
    const pathSegments = urlPath.split('/').filter(segment => segment);
    const procedureSlug = pathSegments.length >= 2 ? pathSegments[1] : null;

    // Try to get procedure name from page title or heading
    const pageTitle = document.querySelector('h1.entry-title, h1.page-title, .brag-book-gallery-title');
    const procedureName = pageTitle ? pageTitle.textContent.trim() : procedureSlug;
    const procedureUrl = window.location.href;
    console.log('storeProcedureReferrerFromCard - Data:', {
      caseId,
      caseWpId,
      termId,
      procedureId,
      procedureSlug,
      procedureName
    });
    window.storeProcedureReferrer(procedureSlug, procedureName, procedureUrl, procedureId, termId, caseId, caseWpId);
  }

  /**
   * Store procedure referrer from carousel item data
   * Extracted to avoid code duplication
   * @param {HTMLElement} carouselItem - The carousel item element
   */
  storeProcedureReferrerFromCarousel(carouselItem) {
    if (!carouselItem || typeof window.storeProcedureReferrer !== 'function') return;
    const termId = carouselItem.dataset.currentTermId;
    if (!termId) return;

    // Extract case IDs from carousel item
    const caseId = carouselItem.dataset.caseId;
    const caseWpId = carouselItem.dataset.postId;

    // Try to get procedure info from active nav link
    const activeLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
    let procedureSlug = null;
    let procedureName = null;
    let procedureId = null;
    if (activeLink) {
      // Get from active nav link
      procedureSlug = activeLink.dataset.procedure;
      procedureName = activeLink.querySelector('.brag-book-gallery-filter-option-label')?.textContent?.trim();
      procedureId = activeLink.dataset.procedureId;
    } else {
      // Fallback: get from carousel wrapper
      const carouselWrapper = carouselItem.closest('.brag-book-gallery-carousel-wrapper');
      if (carouselWrapper) {
        procedureSlug = carouselWrapper.dataset.procedure;
        procedureId = carouselWrapper.dataset.currentProcedureId;
      }

      // If still no slug, try URL path
      if (!procedureSlug) {
        const urlPath = window.location.pathname;
        const pathMatch = urlPath.match(/\/([^\/]+)\/?$/);
        if (pathMatch) {
          procedureSlug = pathMatch[1];
        }
      }
    }
    const procedureUrl = window.location.href;

    // Store referrer with all IDs
    window.storeProcedureReferrer(procedureSlug, procedureName, procedureUrl, procedureId, termId, caseId, caseWpId);
  }

  /**
   * Get API token from config
   * Handles multiple possible locations for the token
   */
  getApiToken() {
    const config = window.bragBookGalleryConfig;
    if (!config) {
      console.log('BRAGBook: bragBookGalleryConfig not available');
      return null;
    }

    // Try different locations where the token might be stored
    const token = config.api_token || config.apiToken || config.api_config && config.api_config.default_token || null;
    if (token) {
      console.log('BRAGBook: API token found');
    } else {
      console.log('BRAGBook: API token not found in config. Available keys:', Object.keys(config));
      if (config.api_config) {
        console.log('BRAGBook: api_config keys:', Object.keys(config.api_config));
      }
    }
    return token;
  }

  /**
   * Get API endpoint from config
   */
  getApiEndpoint() {
    const config = window.bragBookGalleryConfig;
    if (!config) return 'https://app.bragbookgallery.com';
    return config.api_endpoint || config.apiEndpoint || config.apiBaseUrl || config.api_config && config.api_config.endpoint || 'https://app.bragbookgallery.com';
  }

  /**
   * Track page view on load - detects if this is a case or procedure page
   * and sends the appropriate view tracking request
   *
   * Priority:
   * 1. Case detail view (single case page) - track case view
   * 2. Procedure view (procedure listing page) - track procedure view
   * 3. Don't track case cards on procedure pages (those are just thumbnails)
   */
  trackPageView() {
    console.log('BRAGBook: trackPageView() called');

    // Look for specific page type indicators
    const caseDetailView = document.querySelector('.brag-book-gallery-case-detail-view');
    const procedureView = document.querySelector('[data-view="procedure"], [data-view="tiles"], .brag-book-gallery-procedure-template');
    console.log('BRAGBook: Found elements:', {
      caseDetailView: caseDetailView ? caseDetailView.tagName : null,
      procedureView: procedureView ? procedureView.tagName : null
    });

    // 1. Check for case detail view (single case page)
    if (caseDetailView) {
      const caseProcedureId = caseDetailView.dataset.procedureCaseId || caseDetailView.dataset.caseId;
      console.log('BRAGBook: Case detail view dataset:', caseDetailView.dataset);
      if (caseProcedureId) {
        console.log(`BRAGBook: Detected CASE page, tracking view for caseProcedureId: ${caseProcedureId}`);
        this.trackCaseView(caseProcedureId);
        return;
      }
    }

    // 2. Check for procedure view (procedure listing page with case cards)
    // This takes priority over individual case cards which are just thumbnails
    if (procedureView) {
      const procedureId = procedureView.dataset.procedureId || procedureView.dataset.apiProcedureId;
      console.log('BRAGBook: Procedure view dataset:', procedureView.dataset);
      if (procedureId) {
        console.log(`BRAGBook: Detected PROCEDURE page, tracking view for procedureId: ${procedureId}`);
        this.trackProcedureView(procedureId);
        return;
      }

      // Try to get procedure ID from config as fallback
      const config = window.bragBookGalleryConfig;
      if (config && config.procedure_id) {
        console.log(`BRAGBook: Detected PROCEDURE page (from config), tracking view for procedureId: ${config.procedure_id}`);
        this.trackProcedureView(config.procedure_id);
        return;
      }

      // Procedure view exists but no procedure ID available
      console.log('BRAGBook: Procedure page detected but no procedureId available - skipping view tracking');
      return;
    }

    // No trackable view detected
    console.log('BRAGBook: No case or procedure view detected on this page');
  }

  /**
   * Track procedure view via WordPress AJAX (avoids CORS issues)
   * @param {string|number} procedureId - The procedure ID from BragBook API
   */
  trackProcedureView(procedureId) {
    if (!procedureId) {
      console.warn('BRAGBook: No procedureId provided for procedure view tracking');
      return;
    }
    const config = window.bragBookGalleryConfig;
    if (!config || !config.ajaxUrl) {
      console.warn('BRAGBook: AJAX configuration not available for view tracking');
      return;
    }
    console.log(`BRAGBook: Sending procedure view tracking request for procedureId: ${procedureId}`);

    // Use WordPress AJAX to proxy the request (avoids CORS)
    const formData = new FormData();
    formData.append('action', 'brag_book_track_view');
    formData.append('nonce', config.nonce || '');
    formData.append('procedureId', procedureId);
    fetch(config.ajaxUrl, {
      method: 'POST',
      body: formData,
      keepalive: true
    }).then(response => response.json()).then(data => {
      if (data.success) {
        console.log(`BRAGBook: ✓ Procedure view registered successfully for procedureId ${procedureId}`);
      } else {
        console.warn(`BRAGBook: ✗ Procedure view tracking failed:`, data.data?.message || 'Unknown error');
      }
    }).catch(error => {
      console.warn('BRAGBook: ✗ Procedure view tracking error:', error);
    });
  }

  /**
   * Track case view via WordPress AJAX (avoids CORS issues)
   * @param {string} procedureCaseId - The procedure case ID (small API ID like 35, 36)
   */
  trackCaseView(procedureCaseId) {
    if (!procedureCaseId) {
      console.warn('BRAGBook: No procedureCaseId provided for view tracking');
      return;
    }
    const config = window.bragBookGalleryConfig;
    if (!config || !config.ajaxUrl) {
      console.warn('BRAGBook: AJAX configuration not available for view tracking');
      return;
    }
    console.log(`BRAGBook: Sending case view tracking request for caseProcedureId: ${procedureCaseId}`);

    // Use WordPress AJAX to proxy the request (avoids CORS)
    const formData = new FormData();
    formData.append('action', 'brag_book_track_view');
    formData.append('nonce', config.nonce || '');
    formData.append('caseProcedureId', procedureCaseId);
    fetch(config.ajaxUrl, {
      method: 'POST',
      body: formData,
      keepalive: true
    }).then(response => response.json()).then(data => {
      if (data.success) {
        console.log(`BRAGBook: ✓ Case view registered successfully for caseProcedureId ${procedureCaseId}`);
      } else {
        console.warn(`BRAGBook: ✗ Case view tracking failed:`, data.data?.message || 'Unknown error');
      }
    }).catch(error => {
      console.warn('BRAGBook: ✗ Case view tracking error:', error);
    });
  }

  /**
   * Track case view from a case card element
   * @param {HTMLElement} caseCard - The case card element
   */
  trackCaseViewFromCard(caseCard) {
    if (!caseCard) return;

    // Get the procedure case ID (small API ID) from data attribute
    const procedureCaseId = caseCard.dataset.procedureCaseId || caseCard.dataset.caseId;
    if (procedureCaseId) {
      console.log(`BRAGBook: Tracking view from card for procedureCaseId ${procedureCaseId}`);
      this.trackCaseView(procedureCaseId);
    }
  }

  /**
   * Track case view from a carousel item element
   * @param {HTMLElement} carouselItem - The carousel item element
   */
  trackCaseViewFromCarousel(carouselItem) {
    if (!carouselItem) return;

    // Get the procedure case ID (small API ID) from data attribute
    const procedureCaseId = carouselItem.dataset.procedureCaseId || carouselItem.dataset.caseId;
    if (procedureCaseId) {
      console.log(`BRAGBook: Tracking view from carousel for procedureCaseId ${procedureCaseId}`);
      this.trackCaseView(procedureCaseId);
    }
  }
  initializeCaseLinks() {
    // Handle clicks on case links - allow normal navigation instead of AJAX loading
    document.addEventListener('click', e => {
      // Check if click is on a carousel link
      const carouselLink = e.target.closest('.brag-book-gallery-carousel-link');
      if (carouselLink) {
        const carouselItem = carouselLink.closest('.brag-book-gallery-carousel-item');
        this.storeProcedureReferrerFromCarousel(carouselItem);
        // Track the view when clicking on carousel item
        this.trackCaseViewFromCarousel(carouselItem);
        return;
      }

      // Check if click is on a case link (supports both class names)
      const caseLink = e.target.closest('.brag-book-gallery-case-card-link, .brag-book-gallery-case-permalink');
      if (caseLink) {
        const caseCard = caseLink.closest('.brag-book-gallery-case-card');
        this.storeProcedureReferrerFromCard(caseCard);
        // Track the view when clicking on case card link
        this.trackCaseViewFromCard(caseCard);
        return;
      }

      // Check if click is on a case card but not on interactive elements (fallback for UX)
      const caseCard = e.target.closest('.brag-book-gallery-case-card');
      if (caseCard && !e.target.closest('button') && !e.target.closest('details')) {
        const caseLinkInCard = caseCard.querySelector('.brag-book-gallery-case-card-link, .brag-book-gallery-case-permalink');
        if (caseLinkInCard && caseLinkInCard.href) {
          this.storeProcedureReferrerFromCard(caseCard);
          // Track the view when clicking on case card
          this.trackCaseViewFromCard(caseCard);
          window.location.href = caseLinkInCard.href;
        }
      }

      // Check if click is on a navigation button (next/previous)
      // But exclude summary elements which also use .brag-book-gallery-nav-button
      const navButton = e.target.closest('.brag-book-gallery-nav-button');
      if (navButton && !navButton.closest('summary')) {
        // Allow normal navigation to server-rendered case pages
        return;
      }
    });

    // Initialize case detail view thumbnails
    this.initializeCaseDetailThumbnails();

    // Handle browser back/forward navigation
    window.addEventListener('popstate', e => {
      // With server-side rendering, let the browser handle navigation naturally
      // No need to load case details via AJAX
      console.log('Browser navigation handled by server-side rendering');
    });
  }

  /**
   * Check if current URL is a direct case URL and load it immediately
   * Returns true if a case was loaded, false otherwise
   */
  async handleDirectCaseUrl() {
    const currentPath = window.location.pathname;
    const pathSegments = currentPath.split('/').filter(s => s);
    console.log('BRAGBook: handleDirectCaseUrl checking path:', currentPath, 'segments:', pathSegments);

    // Check if this looks like a case URL: /gallery/procedure-slug/case-id
    // We need at least 3 segments and the last should be numeric
    if (pathSegments.length >= 3) {
      const lastSegment = pathSegments[pathSegments.length - 1];

      // Check if the last segment is a numeric case ID
      if (/^\d+$/.test(lastSegment)) {
        const galleryContent = document.getElementById('gallery-content');
        const caseId = lastSegment;
        console.log('BRAGBook: Detected case URL, caseId:', caseId);

        // Check if case is already server-rendered (has case detail view)
        const existingCaseView = galleryContent?.querySelector('.brag-book-gallery-case-detail-view');
        if (existingCaseView) {
          // Case already rendered and trackPageView() already tracked it
          console.log('BRAGBook: Case already server-rendered, view already tracked by trackPageView()');
          return true;
        }

        // Case not rendered yet - load via AJAX
        if (galleryContent) {
          console.log('BRAGBook: Loading case via AJAX');
          await this.loadCaseDetailsViaAjax(caseId, window.location.href, null);
          return true;
        }
        return false;
      }
    }
    return false;
  }
  async loadCaseDetails(caseId, url, updateHistory = true, procedureIds = null) {
    const galleryContent = document.getElementById('gallery-content');
    if (!galleryContent) {
      return;
    }

    // Debounce multiple case loads
    if (this.currentCaseLoad) {
      return;
    }
    this.currentCaseLoad = caseId;

    // If procedureIds not provided, try to get from the case card
    if (!procedureIds) {
      const caseCard = document.querySelector(`.brag-book-gallery-case-card[data-case-id="${caseId}"]`);
      if (caseCard && caseCard.dataset.procedureIds) {
        procedureIds = caseCard.dataset.procedureIds;
      }
    }

    // Update browser URL IMMEDIATELY to prevent showing procedure page
    if (updateHistory && window.history && window.history.pushState) {
      window.history.pushState({
        caseId: caseId
      }, '', url);
    }

    // Show skeleton loading for better perceived performance
    this.showCaseDetailSkeleton();

    // Scroll to top to show loading state
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
    try {
      // Check for config
      if (typeof bragBookGalleryConfig === 'undefined') {
        throw new Error('Configuration not loaded');
      }

      // Check preload cache first for instant loading
      if (this.casePreloadCache && this.casePreloadCache.has(caseId)) {
        const cachedData = this.casePreloadCache.get(caseId);
        if (cachedData && cachedData !== 'loading') {
          galleryContent.innerHTML = cachedData;

          // Set active state on sidebar
          this.setActiveSidebarForCase(caseId);

          // Scroll to top of gallery content area smoothly
          const wrapper = document.querySelector('.brag-book-gallery-wrapper');
          if (wrapper) {
            wrapper.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          } else {
            galleryContent.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          }
          // Clear debounce flag
          this.currentCaseLoad = null;
          return;
        }
      }

      // Load case details via AJAX (ensures PHP-generated HTML)
      await this.loadCaseDetailsViaAjax(caseId, url, procedureIds);
    } catch (error) {
      let errorMessage = 'Failed to load case details. Please try again.';

      // If we have a more specific error message, show it
      if (error.message) {
        errorMessage += '<br><small>' + error.message + '</small>';
      }
      galleryContent.innerHTML = '<div class="brag-book-gallery-error">' + errorMessage + '</div>';
    } finally {
      // Always clear debounce flag
      this.currentCaseLoad = null;
    }
  }

  /**
   * Load case details via server-side AJAX for consistent HTML rendering
   * @param {string} caseId - The case ID to load
   * @param {string} url - The case URL
   * @param {string} procedureIds - Comma-separated procedure IDs
   */
  async loadCaseDetailsViaAjax(caseId, url, procedureIds) {
    const galleryContent = document.getElementById('gallery-content');
    if (!galleryContent) {
      return;
    }
    try {
      // Extract procedure slug from URL
      const pathSegments = window.location.pathname.split('/').filter(s => s);
      const procedureSlug = pathSegments.length > 2 ? pathSegments[pathSegments.length - 2] : '';

      // Try to get the procedure name from the sidebar data
      let procedureName = '';

      // First try to get from active sidebar link (if it exists and is already marked active)
      const activeLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedureSlug}"]`);
      if (activeLink) {
        const label = activeLink.querySelector('.brag-book-gallery-filter-option-label');
        if (label) {
          procedureName = label.textContent.trim();
        }
      }

      // If not found in DOM, lookup in sidebar data
      if (!procedureName && window.bragBookGalleryConfig && window.bragBookGalleryConfig.sidebarData) {
        const sidebarData = window.bragBookGalleryConfig.sidebarData;
        // Search through categories for the procedure
        for (const category of Object.values(sidebarData)) {
          if (category.procedures) {
            for (const procedure of category.procedures) {
              if (procedure.slug === procedureSlug) {
                procedureName = procedure.name;
                break;
              }
            }
          }
          if (procedureName) break;
        }
      }

      // Prepare request parameters - use the HTML version
      const requestParams = {
        action: 'brag_book_gallery_load_case_details_html',
        case_id: caseId,
        procedure_slug: procedureSlug,
        procedure_name: procedureName,
        nonce: bragBookGalleryConfig.nonce || ''
      };

      // Add procedure IDs if available
      if (procedureIds) {
        requestParams.procedure_ids = procedureIds;
      } else {
        console.warn(`⚠️ AJAX call WITHOUT procedure context: case ${caseId} (no procedure IDs provided)`);
      }

      // Make AJAX request to load case details
      const response = await fetch(bragBookGalleryConfig.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams(requestParams)
      });
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const data = await response.json();
      if (data.success && data.data && data.data.html) {
        // Display the HTML directly from the server
        galleryContent.innerHTML = data.data.html;

        // Set active state on sidebar
        this.setActiveSidebarForCase(caseId);

        // Scroll to top of gallery content area smoothly
        const wrapper = document.querySelector('.brag-book-gallery-wrapper');
        if (wrapper) {
          wrapper.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        } else {
          // Fallback to scrolling to gallery content
          galleryContent.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }

        // Update page title and meta description if SEO data is provided
        if (data.data.seo) {
          if (data.data.seo.title) {
            document.title = data.data.seo.title;
          }
          if (data.data.seo.description) {
            let metaDescription = document.querySelector('meta[name="description"]');
            if (!metaDescription) {
              metaDescription = document.createElement('meta');
              metaDescription.name = 'description';
              document.head.appendChild(metaDescription);
            }
            metaDescription.content = data.data.seo.description;
          }
        }

        // Track case view via JavaScript after content loads
        // Get the procedure case ID from the newly loaded content
        const loadedCaseDetail = galleryContent.querySelector('.brag-book-gallery-case-detail-view, [data-case-id]');
        if (loadedCaseDetail) {
          const caseProcedureId = loadedCaseDetail.dataset.procedureCaseId || loadedCaseDetail.dataset.caseId;
          if (caseProcedureId) {
            console.log(`BRAGBook: Case loaded via AJAX, tracking view for caseProcedureId: ${caseProcedureId}`);
            this.trackCaseView(caseProcedureId);
          }
        }

        // Log view tracking information from server (if available)
        if (data.data.view_tracked) {
          console.log(`BRAGBook: Server-side view tracked for Case ID: ${data.data.case_id}`);
        } else if (data.data.view_tracked === false) {
          console.warn(`BRAGBook: Server-side view tracking failed for Case ID: ${data.data.case_id}`);

          // Show additional debug info if available
          if (data.data.debug) {
            console.group('View Tracking Debug Info:');
            if (data.data.debug.tracking_error) {
              console.error('Tracking error:', data.data.debug.tracking_error);
            }
            console.groupEnd();
          }
        }

        // Store successful result in preload cache for future use
        if (this.casePreloadCache && caseId) {
          this.casePreloadCache.set(caseId, data.data.html);
        }

        // Re-initialize any necessary event handlers for the new content
        this.initializeCaseDetailThumbnails();
      } else {
        throw new Error(data.data?.message || data.data || data.message || 'Failed to load case details');
      }
    } catch (error) {
      let errorMessage = 'Failed to load case details via AJAX. Please try again.';

      // If we have a more specific error message, show it
      if (error.message) {
        errorMessage += '<br><small>' + error.message + '</small>';
      }
      galleryContent.innerHTML = '<div class="brag-book-gallery-error">' + errorMessage + '</div>';
    } finally {
      // Always clear debounce flag
      this.currentCaseLoad = null;
    }
  }

  /**
   * Show skeleton loading for case detail view
   */
  showCaseDetailSkeleton() {
    const galleryContent = document.getElementById('gallery-content');
    if (!galleryContent) {
      console.warn('Gallery content container not found for skeleton');
      return;
    }

    // Create skeleton that matches exact case detail view structure
    const skeletonHTML = `
			<div class="brag-book-gallery-case-detail-view brag-book-gallery-case-detail-skeleton" data-case-id="loading">
				<!-- Progress Bar -->
				<div class="skeleton-progress-bar">
					<div class="skeleton-progress-fill"></div>
					<div class="skeleton-progress-text">Loading... 0%</div>
				</div>

				<!-- Case Header Section (matches render_case_header) -->
				<div class="brag-book-gallery-brag-book-gallery-case-header-section">
					<div class="brag-book-gallery-case-navigation">
						<div class="skeleton-back-link"></div>
					</div>
					<div class="brag-book-gallery-brag-book-gallery-case-header">
						<div class="skeleton-case-title"></div>
						<div class="skeleton-case-navigation-buttons">
							<div class="skeleton-nav-btn"></div>
							<div class="skeleton-nav-btn"></div>
						</div>
					</div>
				</div>

				<!-- Case Images Section (matches render_case_images) -->
				<div class="brag-book-gallery-brag-book-gallery-case-content">
					<div class="brag-book-gallery-case-images-section">
						<div class="brag-book-gallery-case-images-layout">
							<!-- Main Image Viewer -->
							<div class="brag-book-gallery-case-main-viewer">
								<div class="brag-book-gallery-main-image-container">
									<div class="skeleton-main-image"></div>
								</div>
							</div>
							<!-- Thumbnails -->
							<div class="brag-book-gallery-case-thumbnails">
								<div class="skeleton-thumbnail"></div>
								<div class="skeleton-thumbnail"></div>
								<div class="skeleton-thumbnail"></div>
								<div class="skeleton-thumbnail"></div>
							</div>
						</div>
					</div>
				</div>

				<!-- Case Details Cards Section (matches render_case_details_cards) -->
				<div class="brag-book-gallery-case-card-details-section">
					<div class="brag-book-gallery-case-card-details-grid">
						<!-- Procedures Card -->
						<div class="case-detail-card procedures-performed-card">
							<div class="card-header">
								<div class="skeleton-card-title"></div>
							</div>
							<div class="card-content">
								<div class="skeleton-procedure-badges">
									<div class="skeleton-badge"></div>
									<div class="skeleton-badge"></div>
								</div>
							</div>
						</div>

						<!-- Patient Details Card -->
						<div class="case-detail-card patient-details-card">
							<div class="card-header">
								<div class="skeleton-card-title"></div>
							</div>
							<div class="card-content">
								<div class="skeleton-patient-info">
									<div class="skeleton-info-item"></div>
									<div class="skeleton-info-item"></div>
									<div class="skeleton-info-item"></div>
								</div>
							</div>
						</div>

						<!-- Procedure Details Card -->
						<div class="case-detail-card procedure-details-card">
							<div class="card-header">
								<div class="skeleton-card-title"></div>
							</div>
							<div class="card-content">
								<div class="skeleton-procedure-details">
									<div class="skeleton-detail-row"></div>
									<div class="skeleton-detail-row"></div>
								</div>
							</div>
						</div>

						<!-- Case Notes Card (full width) -->
						<div class="case-detail-card case-notes-card">
							<div class="card-header">
								<div class="skeleton-card-title"></div>
							</div>
							<div class="card-content">
								<div class="skeleton-case-notes">
									<div class="skeleton-text-line"></div>
									<div class="skeleton-text-line short"></div>
									<div class="skeleton-text-line medium"></div>
									<div class="skeleton-text-line"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		`;
    galleryContent.innerHTML = skeletonHTML;

    // Start progress bar animation
    this.animateProgressBar();
  }

  /**
   * Animate progress bar from 0 to 100%
   */
  animateProgressBar() {
    const progressFill = document.querySelector('.skeleton-progress-fill');
    const progressText = document.querySelector('.skeleton-progress-text');
    if (!progressFill || !progressText) return;
    let progress = 0;
    const duration = 4000; // 4 seconds to match typical case load time
    const increment = 100 / (duration / 75); // Update every 75ms for smoother animation

    // Start at 0% and show immediately
    progressFill.style.width = '0%';
    progressText.textContent = 'Loading... 0%';
    const updateProgress = () => {
      if (progress < 100) {
        progress = Math.min(progress + increment + Math.random() * 2, 100);
        progressFill.style.width = `${progress}%`;
        progressText.textContent = `Loading... ${Math.floor(progress)}%`;

        // Slow down as we approach 100%
        const delay = progress > 80 ? 100 : progress > 60 ? 75 : 50;
        setTimeout(updateProgress, delay);
      }
    };
    updateProgress();
  }
  displayCaseDetails(caseData) {
    const galleryContent = document.getElementById('gallery-content');
    if (!galleryContent) return;

    // Build the case details HTML
    let html = '<div class="brag-book-gallery-case-card-details">';

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
      html += '<div class="brag-book-gallery-no-images">No images available for this case.</div>';
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
    galleryContent.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  }
  handleSearch(query) {
    const normalizedQuery = query.toLowerCase().trim();
    // Search implementation would go here
  }
  applyFilters(activeFilters) {
    // Filter implementation would go here
  }
  async handleFormSubmit(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Get submit button and disable it during submission
    const submitBtn = form.querySelector('[data-action="form-submit"]');
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
          'Content-Type': 'application/x-www-form-urlencoded'
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
      }
    } catch (error) {
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
  initializeCaseDetailThumbnails() {
    // Use event delegation for better reliability
    document.addEventListener('click', e => {
      const thumbnail = e.target.closest('.brag-book-gallery-thumbnail-item');
      if (!thumbnail) return;
      const mainContainer = document.querySelector('.brag-book-gallery-main-image-container');
      if (!mainContainer) return;
      const thumbnails = document.querySelectorAll('.brag-book-gallery-thumbnail-item');
      if (!thumbnails.length) return;

      // Remove active class from all thumbnails
      thumbnails.forEach(t => t.classList.remove('active'));
      // Add active class to clicked thumbnail
      thumbnail.classList.add('active');

      // Get image URL from thumbnail data attributes
      const processedUrl = thumbnail.dataset.processedUrl;
      const imageIndex = thumbnail.dataset.imageIndex;

      // Update main container data attribute
      mainContainer.dataset.imageIndex = imageIndex;

      // Build new HTML for main image
      let newContent = '';
      if (processedUrl) {
        const existingButton = mainContainer.querySelector('.brag-book-gallery-favorite-button');
        const caseId = existingButton ? existingButton.dataset.itemId.replace('_main', '') : '';
        newContent = `
					<div class="brag-book-gallery-main-single">
						<img src="${processedUrl}" alt="Case Image" loading="eager">
						<div class="brag-book-gallery-item-actions">
							<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="${caseId}_main" aria-label="Add to favorites">
								<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">
									<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
								</svg>
							</button>
				`;

        // Check if sharing is enabled
        if (typeof bragBookGalleryConfig !== 'undefined' && bragBookGalleryConfig.enableSharing === 'yes') {
          newContent += `
							<button class="brag-book-gallery-share-button" data-item-id="${caseId}_main" aria-label="Share this image">
								<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
									<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>
								</svg>
							</button>
					`;
        }
        newContent += `
						</div>
					</div>
				`;
      }

      // Update main container content
      mainContainer.innerHTML = newContent;

      // Re-initialize any event handlers for the new buttons
      if (window.bragBookGalleryApp && window.bragBookGalleryApp.components) {
        if (window.bragBookGalleryApp.components.favoritesManager) {
          window.bragBookGalleryApp.components.favoritesManager.initializeFavoriteButtons();
        }
        if (window.bragBookGalleryApp.components.shareManager) {
          window.bragBookGalleryApp.components.shareManager.initializeShareButtons();
        }
      }
    });
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
  initializeClearAllButton() {
    // Try multiple approaches to find and attach the clear all button
    const setupClearAllHandler = () => {
      const clearAllButton = document.querySelector('[data-action="clear-filters"]');
      if (clearAllButton) {
        // Remove any existing listeners
        clearAllButton.removeEventListener('click', this.handleClearAll);

        // Add new listener
        clearAllButton.addEventListener('click', this.handleClearAll.bind(this));
        return true;
      } else {
        return false;
      }
    };

    // Try immediately
    if (!setupClearAllHandler()) {
      // If not found, try again after a short delay (for AJAX loaded content)
      setTimeout(() => {
        setupClearAllHandler();
      }, 1000);
    }

    // Also set up a global click handler as backup
    document.addEventListener('click', e => {
      if (e.target && e.target.dataset.action === 'clear-filters') {
        e.preventDefault();
        this.handleClearAll(e);
      }
    });
  }
  handleClearAll(e) {
    e.preventDefault();
    this.clearDemographicFilters();
  }

  /**
   * Initialize demographic filter badge integration
   */
  initializeDemographicFilterBadges() {
    // Create a global function that demographic filters can call
    window.updateDemographicFilterBadges = activeFilters => {
      this.updateDemographicBadges(activeFilters);
    };

    // Monitor demographic filter changes if the system exists
    if (window.applyProcedureFilters) {}

    // Monitor demographic filter checkboxes for changes
    this.monitorDemographicFilters();

    // Add global delegated event handler for badge remove buttons
    document.addEventListener('click', e => {
      // Check if the clicked element is a remove button or inside one
      const removeButton = e.target.closest('.brag-book-gallery-badge-remove');
      if (removeButton) {
        e.preventDefault();
        e.stopPropagation();

        // Get the parent badge element
        const badge = removeButton.closest('.brag-book-gallery-filter-badge');
        if (badge) {
          const category = badge.getAttribute('data-filter-category');
          const value = badge.getAttribute('data-filter-value');
          if (category && value) {
            this.removeDemographicFilter(category, value);
          }
        }
      }
    });
  }

  /**
   * Monitor demographic filter checkboxes and update badges
   */
  monitorDemographicFilters() {
    // Also try direct checkbox monitoring
    document.addEventListener('change', e => {
      if (e.target.type === 'checkbox' && e.target.closest('.brag-book-gallery-filter-group')) {
        // Manually build activeFilters from checked checkboxes
        setTimeout(() => {
          const activeFilters = this.buildActiveFiltersFromDOM();
          this.updateDemographicBadges(activeFilters);
        }, 100);
      }
    });

    // Set up periodic check as backup
    let lastFilterState = '';
    setInterval(() => {
      const currentState = this.buildActiveFiltersFromDOM();
      const currentStateStr = JSON.stringify(currentState);
      if (currentStateStr !== lastFilterState) {
        this.updateDemographicBadges(currentState);
        lastFilterState = currentStateStr;
      }
    }, 1000);
  }

  /**
   * Build activeFilters object by examining DOM checkboxes
   */
  buildActiveFiltersFromDOM() {
    const activeFilters = {
      age: [],
      gender: [],
      ethnicity: [],
      height: [],
      weight: []
    };

    // Find all checked filter checkboxes
    const checkboxes = document.querySelectorAll('.brag-book-gallery-filter-group input[type="checkbox"]:checked');
    checkboxes.forEach(checkbox => {
      // Try to determine category from parent elements or data attributes
      const filterGroup = checkbox.closest('.brag-book-gallery-filter-group');
      const filterSummary = filterGroup?.querySelector('summary');
      const groupText = filterSummary?.textContent?.toLowerCase() || '';

      // Get the filter value from label text
      const label = checkbox.nextElementSibling;
      const filterValue = label?.textContent?.trim() || checkbox.value || '';

      // Categorize based on group text or filter value
      if (groupText.includes('age') || filterValue.includes('-')) {
        activeFilters.age.push(filterValue);
      } else if (groupText.includes('gender') || ['male', 'female'].some(g => filterValue.toLowerCase().includes(g))) {
        activeFilters.gender.push(filterValue);
      } else if (groupText.includes('ethnicity')) {
        activeFilters.ethnicity.push(filterValue);
      } else if (groupText.includes('height') || filterValue.includes('ft') || filterValue.includes("'")) {
        activeFilters.height.push(filterValue);
      } else if (groupText.includes('weight') || filterValue.includes('lbs') || filterValue.includes('kg')) {
        activeFilters.weight.push(filterValue);
      }
    });
    return activeFilters;
  }

  /**
   * Update badges for demographic filters
   */
  updateDemographicBadges(activeFilters) {
    const badgesContainer = document.querySelector('[data-action="filter-badges"]');
    const clearAllButton = document.querySelector('[data-action="clear-filters"]');
    if (!badgesContainer || !clearAllButton) return;

    // Clear existing badges
    badgesContainer.innerHTML = '';
    let hasActiveFilters = false;

    // Process demographic filters
    if (activeFilters) {
      Object.keys(activeFilters).forEach(category => {
        const filters = activeFilters[category];
        if (filters && filters.length > 0) {
          hasActiveFilters = true;
          filters.forEach(filterValue => {
            const badge = this.createDemographicBadge(category, filterValue);
            badgesContainer.appendChild(badge);
          });
        }
      });
    }

    // Note: Procedure filters are handled separately by the FilterSystem class
    // We only handle demographic filters (age, gender, etc.) in this method

    // Check if there are any active filters (demographic or procedure)
    const procedureBadges = badgesContainer.querySelectorAll('[data-filter-key]');
    const hasAnyActiveFilters = hasActiveFilters || procedureBadges.length > 0;

    // Show/hide clear all button based on any active filters
    clearAllButton.style.display = hasAnyActiveFilters ? 'inline-block' : 'none';
  }

  /**
   * Create a demographic filter badge
   */
  createDemographicBadge(category, value) {
    const badge = document.createElement('div');
    badge.className = 'brag-book-gallery-filter-badge';
    badge.setAttribute('data-filter-category', category);
    badge.setAttribute('data-filter-value', value);

    // Format display text - but store the original value
    let displayText = '';
    let originalValue = value; // Keep the original value for matching

    switch (category) {
      case 'age':
        displayText = `Age: ${value}`;
        break;
      case 'gender':
        displayText = `Gender: ${value}`;
        // Store just the gender value (Male/Female) without the prefix
        originalValue = value.replace(/^(Male|Female)$/i, match => {
          // Capitalize first letter
          return match.charAt(0).toUpperCase() + match.slice(1).toLowerCase();
        });
        break;
      case 'ethnicity':
        displayText = `Ethnicity: ${value}`;
        break;
      case 'height':
        displayText = `Height: ${value}`;
        break;
      case 'weight':
        displayText = `Weight: ${value}`;
        break;
      default:
        displayText = `${category}: ${value}`;
    }
    badge.innerHTML = `
			<span class="brag-book-gallery-badge-text">${displayText}</span>
			<button class="brag-book-gallery-badge-remove" aria-label="Remove ${displayText} filter">
				<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
					<path d="M13 1L1 13M1 1l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
				</svg>
			</button>
		`;

    // Add click handler to remove button
    const removeButton = badge.querySelector('.brag-book-gallery-badge-remove');
    removeButton.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
      this.removeDemographicFilter(category, value);
    });
    return badge;
  }

  /**
   * Create a procedure filter badge
   */
  createProcedureBadge(category, procedure, filterKey) {
    const badge = document.createElement('div');
    badge.className = 'brag-book-gallery-filter-badge';
    badge.setAttribute('data-filter-key', filterKey);
    let displayText = procedure; // Procedures just show the name

    badge.innerHTML = `
			<span class="brag-book-gallery-badge-text">${displayText}</span>
			<button class="brag-book-gallery-badge-remove" aria-label="Remove ${displayText} filter">
				<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
					<path d="M13 1L1 13M1 1l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
				</svg>
			</button>
		`;

    // Add click handler to remove button
    const removeButton = badge.querySelector('.brag-book-gallery-badge-remove');
    removeButton.addEventListener('click', e => {
      e.preventDefault();
      if (this.components.filterSystem) {
        this.components.filterSystem.removeFilterBadge(filterKey);
      }
    });
    return badge;
  }

  /**
   * Remove a demographic filter
   */
  removeDemographicFilter(category, value) {
    // Find the checkbox directly using the data-filter-type attribute and value
    let targetCheckbox = null;

    // Based on the HTML structure, checkboxes have data-filter-type attribute
    // and the value attribute matches what we're looking for
    const selector = `input[type="checkbox"][data-filter-type="${category}"][value="${value}"]`;
    targetCheckbox = document.querySelector(selector);

    // If not found, try without quotes or with different case
    if (!targetCheckbox) {
      // Try to find any checkbox with the matching value in the category
      const checkboxes = document.querySelectorAll(`input[type="checkbox"][data-filter-type="${category}"]`);
      checkboxes.forEach(checkbox => {
        const checkboxValue = checkbox.value;
        const label = checkbox.nextElementSibling;
        const labelText = label?.textContent?.trim() || '';

        // Match the value exactly or case-insensitively
        if (checkboxValue === value || checkboxValue.toLowerCase() === value.toLowerCase() || labelText === value || labelText.toLowerCase() === value.toLowerCase()) {
          targetCheckbox = checkbox;
        }
      });
    }

    // If still not found, try a broader search
    if (!targetCheckbox) {
      // Look for checkboxes by ID pattern (e.g., procedure-filter-age-18-24)
      const idPattern = `procedure-filter-${category}-${value}`.toLowerCase().replace(/\s+/g, '-');
      targetCheckbox = document.getElementById(idPattern);
    }
    if (targetCheckbox) {
      targetCheckbox.checked = false;

      // Trigger change event to update the filter system
      const changeEvent = new Event('change', {
        bubbles: true
      });
      targetCheckbox.dispatchEvent(changeEvent);

      // Also trigger input event as some handlers might listen to it
      const inputEvent = new Event('input', {
        bubbles: true
      });
      targetCheckbox.dispatchEvent(inputEvent);

      // Also manually trigger the filter update
      setTimeout(() => {
        const activeFilters = this.buildActiveFiltersFromDOM();
        this.updateDemographicBadges(activeFilters);

        // Trigger any global filter update functions
        if (typeof window.applyDemographicFilters === 'function') {
          window.applyDemographicFilters();
        }
      }, 100);

      // Remove the badge immediately from DOM
      const badge = document.querySelector(`.brag-book-gallery-filter-badge[data-filter-category="${category}"][data-filter-value="${value}"]`);
      if (badge) {
        badge.remove();
      }
    } else {
      console.warn(`Could not find checkbox for ${category}: ${value}`);

      // Log all available checkboxes for debugging.
      const allCheckboxes = document.querySelectorAll('input[type="checkbox"][data-filter-type]');
      allCheckboxes.forEach(cb => {
        console.log(`  - Type: ${cb.getAttribute('data-filter-type')}, Value: ${cb.value}, ID: ${cb.id}`);
      });
    }
  }

  /**
   * Clear all demographic filters
   */
  clearDemographicFilters() {
    // Find all checked checkboxes in filter groups with multiple selector patterns
    const selectors = ['.brag-book-gallery-filter-group input[type="checkbox"]:checked', 'input[type="checkbox"][data-filter-category]:checked', '.brag-book-gallery-filter-option input[type="checkbox"]:checked'];
    let totalCleared = 0;
    selectors.forEach(selector => {
      const checkboxes = document.querySelectorAll(selector);
      checkboxes.forEach(checkbox => {
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', {
          bubbles: true
        }));
        totalCleared++;
      });
    });

    // Also try to trigger any global filter clear functions
    if (window.clearProcedureFilters) {
      window.clearProcedureFilters();
    }

    // Force update badges to hide them
    setTimeout(() => {
      this.updateDemographicBadges({
        age: [],
        gender: [],
        ethnicity: [],
        height: [],
        weight: []
      });
    }, 100);
  }

  /**
   * Reload gallery content (clear all filters and show all cases)
   */
  reloadGalleryContent() {
    // Find the filtered gallery container
    const filteredGallery = document.querySelector('.brag-book-gallery-filtered-results');
    if (filteredGallery) {
      // Trigger AJAX reload with no filters
      const formData = new FormData();
      formData.append('action', 'brag_book_gallery_load_filtered_gallery');
      formData.append('nonce', window.bragBookGalleryAjax?.nonce || '');
      formData.append('procedure_ids', ''); // Empty procedure IDs = show all
      formData.append('has_nudity', document.body.classList.contains('nudity-accepted') ? '1' : '0');
      fetch(window.bragBookGalleryAjax?.ajax_url || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData
      }).then(response => response.json()).then(data => {
        if (data.success) {
          filteredGallery.innerHTML = data.data.html;
        } else {
          console.error('Failed to reload gallery:', data.data?.message);
        }
      }).catch(error => {
        console.error('Error reloading gallery:', error);
      });
    }
  }
  updateFavoritesCount(count) {
    // Update all favorites count elements
    const countElements = document.querySelectorAll('[data-favorites-count]');
    countElements.forEach(element => {
      // Check if this is in tiles view (no parentheses)
      const isTilesView = element.closest('.brag-book-gallery-favorites-link--tiles');
      element.textContent = isTilesView ? count : `(${count})`;
    });
  }
  initializeFavoritesButton() {
    // Handle all elements with data-action="show-favorites"
    const favoritesBtns = document.querySelectorAll('[data-action="show-favorites"]');
    if (!favoritesBtns.length) return;
    favoritesBtns.forEach(btn => {
      btn.addEventListener('click', e => {
        // If this is the favorites link in sidebar, let it navigate normally to the page
        if (btn.classList.contains('brag-book-gallery-favorites-link')) {
          // Allow normal navigation - don't prevent default
          return;
        }

        // For other buttons, prevent default and toggle the view
        e.preventDefault();
        e.stopPropagation();
        this.toggleFavoritesView();
      });
    });
  }
  showFavoritesView() {
    // Always show favorites (used by sidebar link)
    const favoritesBtns = document.querySelectorAll('[data-action="show-favorites"]');
    favoritesBtns.forEach(btn => btn.classList.add('active'));

    // Note: URL manipulation removed - we now navigate to actual myfavorites page

    this.showFavoritesOnly();
  }
  toggleFavoritesView() {
    const favoritesBtn = document.querySelector('[data-action="show-favorites"]:not(.brag-book-gallery-favorites-link)');
    const isActive = favoritesBtn?.classList.contains('active');
    if (isActive) {
      // Return to normal gallery view
      this.showAllCases();
      document.querySelectorAll('[data-action="show-favorites"]').forEach(btn => {
        btn.classList.remove('active');
      });
    } else {
      // Show only favorited cases
      this.showFavoritesOnly();
      document.querySelectorAll('[data-action="show-favorites"]').forEach(btn => {
        btn.classList.add('active');
      });
    }
  }
  showFavoritesOnly() {
    // On the dedicated favorites page, defer to initializeDedicatedFavoritesPage
    if (document.getElementById('brag-book-gallery-favorites')) {
      return;
    }
    const galleryContent = document.getElementById('gallery-content');
    if (!galleryContent) return;

    // Clear current content
    const sectionsContainer = galleryContent.querySelector('#gallery-sections');
    if (sectionsContainer) {
      sectionsContainer.style.display = 'none';
    }

    // Get user info from localStorage
    const userInfoKey = 'brag-book-user-info';
    let userInfo = null;
    try {
      const stored = localStorage.getItem(userInfoKey);
      if (stored) {
        userInfo = JSON.parse(stored);
      }
    } catch (e) {
      console.error('Failed to load user info:', e);
    }

    // If no user info, the email lookup form is now handled server-side
    // JavaScript just needs to show/hide the appropriate containers
    if (!userInfo || !userInfo.email) {
      // Show the server-side email capture form
      const emailCapture = document.getElementById('favoritesEmailCapture');
      if (emailCapture) {
        emailCapture.style.display = 'block';
      }

      // Hide the favorites grid container
      const gridContainer = document.getElementById('favoritesGridContainer');
      if (gridContainer) {
        gridContainer.style.display = 'none';
      }

      // Setup form handler for the server-rendered form
      const form = document.getElementById('favorites-email-form');
      if (form) {
        form.addEventListener('submit', e => {
          e.preventDefault();
          const formData = new FormData(form);
          const email = formData.get('email');

          // Save to localStorage
          const newUserInfo = {
            email: email
          };
          localStorage.setItem(userInfoKey, JSON.stringify(newUserInfo));

          // Reload favorites with the email
          this.showFavoritesOnly();
        });
      }
      return;
    }

    // Hide email capture form and show loading state
    const emailCapture = document.getElementById('favoritesEmailCapture');
    const loadingState = document.getElementById('favoritesLoading');
    const gridContainer = document.getElementById('favoritesGridContainer');
    if (emailCapture) {
      emailCapture.style.display = 'none';
    }
    if (loadingState) {
      loadingState.style.display = 'block';
    }
    if (gridContainer) {
      gridContainer.style.display = 'none';
    }

    // Make AJAX request with email from localStorage
    const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.bragBookGalleryConfig?.nonce || '';

    // Load from API
    fetch(ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({
        action: 'brag_book_lookup_favorites',
        email: userInfo.email,
        nonce: nonce
      })
    }).then(response => response.json()).then(data => {
      // Hide loading state
      if (loadingState) {
        loadingState.style.display = 'none';
      }
      if (data.success && data.data) {
        // Update user info from API response if it includes name and phone
        // The API returns 'user' not 'user_info'
        const apiUser = data.data.user || data.data.user_info;
        if (apiUser || data.data.name && data.data.phone) {
          const apiUserInfo = apiUser || {
            email: userInfo.email,
            name: data.data.name || '',
            phone: data.data.phone || ''
          };

          // Update localStorage with complete user info
          if (apiUserInfo.name || apiUserInfo.phone) {
            const updatedUserInfo = {
              email: userInfo.email,
              name: apiUserInfo.name || userInfo.name || '',
              phone: apiUserInfo.phone || userInfo.phone || ''
            };
            localStorage.setItem(userInfoKey, JSON.stringify(updatedUserInfo));

            // Update the local userInfo variable
            userInfo = updatedUserInfo;
          }
        }

        // Handle favorites — API response is the source of truth.
        // Extract junction IDs (caseProcedures[0].id) which match
        // data-procedure-case-id on cards and are used for add/remove calls.
        const favoritesData = data.data.favorites || {};
        const casesData = favoritesData.cases_data || {};
        const casesArray = Object.values(casesData);
        if (casesArray.length > 0) {
          // Extract junction IDs from caseProcedures — same logic as displayFavoritesGrid
          const junctionIds = casesArray.map(c => {
            if (c.caseProcedures && c.caseProcedures.length > 0) {
              return String(c.caseProcedures[0].id);
            }
            return String(c.id || '');
          }).filter(Boolean);

          // Replace localStorage entirely — API is authoritative
          localStorage.setItem('brag-book-favorites', JSON.stringify(junctionIds));

          // Update favorites manager if it exists
          if (this.components.favoritesManager) {
            this.components.favoritesManager.favorites = new Set(junctionIds);
            this.components.favoritesManager.updateUI();
          }

          // Update favorites count in navigation
          const countElements = document.querySelectorAll('[data-favorites-count]');
          countElements.forEach(element => {
            const format = element.dataset.favoritesFormat;
            if (format === 'text') {
              element.textContent = `${junctionIds.length} favorite${junctionIds.length !== 1 ? 's' : ''}`;
            } else {
              const isTilesView = element.closest('.brag-book-gallery-favorites-link--tiles');
              element.textContent = isTilesView ? junctionIds.length : `(${junctionIds.length})`;
            }
            if (element.style) {
              element.style.opacity = '1';
            }
          });
        } else {
          // No cases — replace localStorage with empty array
          localStorage.setItem('brag-book-favorites', JSON.stringify([]));

          // Update favorites manager if it exists
          if (this.components.favoritesManager) {
            this.components.favoritesManager.favorites = new Set();
            this.components.favoritesManager.updateUI();
          }

          // Update favorites count to 0
          const countElements = document.querySelectorAll('[data-favorites-count]');
          countElements.forEach(element => {
            const format = element.dataset.favoritesFormat;
            if (format === 'text') {
              element.textContent = '0 favorites';
            } else {
              const isTilesView = element.closest('.brag-book-gallery-favorites-link--tiles');
              element.textContent = isTilesView ? '0' : '(0)';
            }
            if (element.style) {
              element.style.opacity = '1';
            }
          });
        }

        // Check if we have cases data from the API lookup
        const hasCasesData = favoritesData.cases_data && Object.keys(favoritesData.cases_data).length > 0;
        if (hasCasesData) {
          // Render cards client-side using displayFavoritesGrid
          this.displayFavoritesGrid(favoritesData, gridContainer, loadingState);

          // Reinitialize components for the new content
          setTimeout(() => {
            this.reinitializeGalleryComponents();
          }, 200);
        } else if (data.data.html) {
          // Server-rendered HTML fallback (sanitized server-side by PHP)
          if (gridContainer) {
            gridContainer.style.display = 'block';
            const favoritesGrid = gridContainer.querySelector('#favoritesGrid');
            if (favoritesGrid) {
              // HTML is pre-sanitized by WordPress esc_html/esc_attr in PHP
              favoritesGrid.innerHTML = data.data.html; // phpcs:ignore -- server-sanitized
            }
            const favoritesActions = gridContainer.querySelector('#favoritesActions');
            if (favoritesActions) {
              favoritesActions.style.display = 'block';
            }
            const emptyState = gridContainer.querySelector('#favoritesEmpty');
            if (emptyState) {
              emptyState.style.display = 'none';
            }
          }
          this.reinitializeGalleryComponents();
        } else {
          // No favorites data - show empty state
          this.showEmptyFavoritesState(gridContainer, loadingState);
        }
      } else {
        // Show error message - ensure localStorage is initialized even on error
        if (!localStorage.getItem('brag-book-favorites')) {
          localStorage.setItem('brag-book-favorites', JSON.stringify([]));
        }

        // Display error message without generating HTML
        console.error('Failed to load favorites:', data.data?.message || 'Unknown error');

        // Show the email capture form again for retry
        if (emailCapture) {
          emailCapture.style.display = 'block';
        }
        if (gridContainer) {
          gridContainer.style.display = 'none';
        }
      }
    }).catch(error => {
      console.error('Error loading favorites:', error);

      // Hide loading state and show email capture form for retry
      if (loadingState) {
        loadingState.style.display = 'none';
      }
      if (emailCapture) {
        emailCapture.style.display = 'block';
      }
      if (gridContainer) {
        gridContainer.style.display = 'none';
      }
    });

    // Clear any active filters
    if (this.components.filterSystem) {
      this.components.filterSystem.clearAllFilters();
    }
  }
  showAllCases() {
    // Reload the gallery to show all cases
    const galleryContent = document.getElementById('gallery-content');
    const sectionsContainer = galleryContent?.querySelector('#gallery-sections');

    // Show carousel sections again
    if (sectionsContainer) {
      sectionsContainer.style.display = '';
    }

    // Remove favorites header if exists
    const favoritesHeader = galleryContent?.querySelector('.brag-book-gallery-favorites-header');
    if (favoritesHeader) {
      favoritesHeader.remove();
    }

    // Trigger a gallery reload
    if (this.components.filterSystem) {
      this.components.filterSystem.clearAllFilters();
      this.components.filterSystem.loadInitialCases();
    }
  }
  showFavoritesEmptyState() {
    const galleryContent = document.getElementById('gallery-content');
    if (!galleryContent) return;

    // Hide carousel sections
    const sectionsContainer = galleryContent.querySelector('#gallery-sections');
    if (sectionsContainer) {
      sectionsContainer.style.display = 'none';
    }

    // Clear cases grid
    const casesGrid = galleryContent.querySelector('.brag-book-gallery-cases-grid');
    if (casesGrid) {
      casesGrid.innerHTML = '';
      // Note: Empty state now handled server-side
    }
  }
  createCaseCard(caseData) {
    const caseId = caseData.id;
    const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'before-after';

    // Get the current procedure context from the URL or use case technique as fallback
    let procedureSlug = 'case';
    let procedureDisplayName = 'Case';

    // Try to get procedure from current URL pattern
    const currentPath = window.location.pathname;
    const galleryPattern = new RegExp(`/${gallerySlug}/([^/]+)`);
    const match = currentPath.match(galleryPattern);
    if (match && match[1]) {
      // Use the procedure from the URL (e.g., 'facelift' from /before-after/facelift/)
      procedureSlug = match[1];
      // Convert slug to display name (e.g., 'facelift' -> 'Facelift')
      procedureDisplayName = procedureSlug.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    } else if (caseData.technique) {
      // Fallback to case technique if no URL context
      procedureSlug = caseData.technique.toLowerCase().replace(/\s+/g, '-');
      procedureDisplayName = caseData.technique;
    }
    const caseUrl = '/' + gallerySlug + '/' + procedureSlug + '/' + caseId + '/';

    // Get the first processed image
    let imageUrl = '';
    if (caseData.photoSets && caseData.photoSets.length > 0) {
      imageUrl = caseData.photoSets[0].postProcessedImageLocation || '';
    }

    // Get procedure ID from active nav link for favorites
    const activeProcedureLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
    const currentProcedureId = activeProcedureLink?.dataset.procedureId || '';

    // Get procedure IDs from case data
    const procedureIds = caseData.procedureIds || [];
    const procedureIdsStr = procedureIds.join(',');

    // Use current procedure ID for favorites, fallback to first procedure ID, then case ID
    const favoriteItemId = currentProcedureId || (procedureIds.length > 0 ? procedureIds[0] : caseId);
    const isFavorited = this.components.favoritesManager.getFavorites().has(String(favoriteItemId));

    // Build data attributes
    let dataAttrs = `data-case-id="${caseId}"`;
    if (currentProcedureId) {
      dataAttrs += ` data-current-procedure-id="${currentProcedureId}"`;
    }
    if (procedureIdsStr) {
      dataAttrs += ` data-procedure-ids="${procedureIdsStr}"`;
    }
    return `
			<article class="brag-book-gallery-case-card" ${dataAttrs}>
				<div class="brag-book-gallery-image-container">
					<div class="brag-book-gallery-skeleton-loader" style="display:none;"></div>
					<div class="brag-book-gallery-item-actions">
						<button class="brag-book-gallery-favorite-button" data-favorited="${isFavorited}" data-item-id="${favoriteItemId}" aria-label="${isFavorited ? 'Remove from' : 'Add to'} favorites">
							<svg fill="${isFavorited ? 'red' : 'rgba(255, 255, 255, 0.5)'}" stroke="white" stroke-width="2" viewBox="0 0 24 24">
								<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
							</svg>
						</button>
					</div>
					<a href="${caseUrl}" class="brag-book-gallery-case-card-link" data-case-id="${caseId}">
						<picture class="brag-book-gallery-picture">
							<img src="${imageUrl}" alt="Case ${caseId}" loading="lazy" data-image-type="single">
						</picture>
					</a>
				</div>
				<div class="brag-book-gallery-case-card-summary">
					<div class="brag-book-gallery-case-card-summary-info">
						<span class="brag-book-gallery-case-card-summary-info__name">${procedureDisplayName}</span>
						<span class="brag-book-gallery-case-card-summary-info__case-number">Case #${caseId}</span>
					</div>
					<div class="brag-book-gallery-case-card-summary-details">
						${caseData.age ? `<span class="brag-book-gallery-age">${caseData.age} yrs</span>` : ''}
						${caseData.gender ? `<span class="brag-book-gallery-gender">${caseData.gender}</span>` : ''}
					</div>
				</div>
			</article>
		`;
  }
  reinitializeGalleryComponents() {
    // Reinitialize case links
    this.initializeCaseLinks();

    // Reinitialize favorites buttons
    if (this.components.favoritesManager) {
      // Re-setup event listeners for new favorite buttons
      document.querySelectorAll('[data-favorited]').forEach(button => {
        // The FavoritesManager already has event delegation, so we just need to ensure proper state
        const itemId = button.dataset.itemId;
        const isFavorited = this.components.favoritesManager.getFavorites().has(itemId);
        button.dataset.favorited = isFavorited.toString();
      });
    }
  }

  /**
   * Initialize nudity warning management
   */
  initializeNudityWarning() {
    this.components.nudityWarningManager = new _utilities_js__WEBPACK_IMPORTED_MODULE_6__.NudityWarningManager();
  }

  /**
   * Initialize case preloading for improved performance
   */
  initializeCasePreloading() {
    // Preload cache to store case data
    this.casePreloadCache = new Map();

    // Optimize image loading for visible cases
    this.optimizeImageLoading();

    // Add intersection observer for visible cases
    this.setupCasePreloadObserver();

    // Preload first few visible cases after a short delay
    setTimeout(() => {
      this.preloadVisibleCases();
    }, 1000);
  }

  /**
   * Initialize case carousel pagination (image dots within case cards)
   * Handles button clicks to navigate between images in a case carousel
   */
  initializeCaseCarouselPagination() {
    // Use event delegation for case carousel pagination
    document.addEventListener('click', e => {
      const dot = e.target.closest('.brag-book-gallery-case-carousel-dot');
      if (!dot) return;
      e.preventDefault();
      const slideIndex = parseInt(dot.dataset.slideIndex, 10);
      if (isNaN(slideIndex)) return;

      // Find the carousel container (parent of pagination)
      const pagination = dot.closest('.brag-book-gallery-case-carousel-pagination');
      if (!pagination) return;
      const imageContainer = pagination.closest('.brag-book-gallery-image-container');
      if (!imageContainer) return;
      const carousel = imageContainer.querySelector('.brag-book-gallery-case-carousel');
      if (!carousel) return;

      // Get the target image/picture element
      const pictures = carousel.querySelectorAll('picture');
      const targetPicture = pictures[slideIndex];
      if (!targetPicture) return;

      // Scroll the carousel to show the target image
      targetPicture.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest',
        inline: 'start'
      });

      // Update active states
      const allDots = pagination.querySelectorAll('.brag-book-gallery-case-carousel-dot');
      allDots.forEach((d, i) => {
        const isActive = i === slideIndex;
        d.classList.toggle('is-active', isActive);
        d.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
    });

    // Also handle scroll events to update active dot
    this.setupCaseCarouselScrollObserver();
  }

  /**
   * Set up scroll listeners to update active carousel dot on scroll/swipe
   */
  setupCaseCarouselScrollObserver() {
    this.caseCarouselScrollHandlers = [];
    document.querySelectorAll('.brag-book-gallery-case-carousel').forEach(carousel => {
      const pagination = carousel.querySelector('.brag-book-gallery-case-carousel-pagination');
      if (!pagination) return;

      // The scrollable element is the anchor containing all slides
      const slidesContainer = carousel.querySelector('.brag-book-gallery-carousel-slides');
      if (!slidesContainer) return;
      const pictures = slidesContainer.querySelectorAll('picture');
      if (pictures.length < 2) return;
      const updateActiveDot = () => {
        const scrollLeft = slidesContainer.scrollLeft;
        const containerWidth = slidesContainer.clientWidth;

        // Calculate the active index from scroll position
        const activeIndex = containerWidth > 0 ? Math.round(scrollLeft / containerWidth) : 0;
        const dots = pagination.querySelectorAll('.brag-book-gallery-case-carousel-dot');
        dots.forEach((dot, i) => {
          const isActive = i === activeIndex;
          dot.classList.toggle('is-active', isActive);
          dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
      };
      slidesContainer.addEventListener('scroll', updateActiveDot, {
        passive: true
      });
      this.caseCarouselScrollHandlers.push({
        carousel: slidesContainer,
        handler: updateActiveDot
      });
    });
  }

  /**
   * Initialize lazy loading for procedures shortcode
   * Handles "Load More" button click to fetch additional cases via AJAX
   */
  initializeProceduresLoadMore() {
    const wrapper = document.querySelector('.brag-book-gallery-procedures-wrapper');
    if (!wrapper) return;
    const loadMoreBtn = wrapper.querySelector('.brag-book-gallery-procedures-load-more-btn');
    if (!loadMoreBtn) return;
    loadMoreBtn.addEventListener('click', async () => {
      if (loadMoreBtn.dataset.loading === 'true') return;

      // Get current state from wrapper data attributes
      const currentPage = parseInt(wrapper.dataset.page, 10) || 1;
      const limit = parseInt(wrapper.dataset.limit, 10) || 20;
      const memberId = wrapper.dataset.memberId || '';
      const total = parseInt(wrapper.dataset.total, 10) || 0;

      // Set loading state
      loadMoreBtn.dataset.loading = 'true';
      loadMoreBtn.textContent = 'Loading...';
      loadMoreBtn.disabled = true;
      try {
        const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
        const nonce = window.bragBookGalleryConfig?.nonce || '';
        const formData = new FormData();
        formData.append('action', 'brag_book_load_more_procedures');
        formData.append('nonce', nonce);
        formData.append('page', currentPage + 1);
        formData.append('limit', limit);
        formData.append('member_id', memberId);
        const response = await fetch(ajaxUrl, {
          method: 'POST',
          body: formData
        });
        const data = await response.json();
        if (data.success && data.data.html) {
          // Append new cards to grid
          const grid = wrapper.querySelector('.brag-book-gallery-procedures-grid');
          if (grid) {
            grid.insertAdjacentHTML('beforeend', data.data.html);

            // Re-initialize case carousel scroll observers for new cards
            if (this.setupCaseCarouselScrollObserver) {
              this.setupCaseCarouselScrollObserver();
            }
          }

          // Update page counter
          wrapper.dataset.page = currentPage + 1;

          // Hide button if no more cases
          if (!data.data.hasMore) {
            const loadMoreContainer = wrapper.querySelector('.brag-book-gallery-procedures-load-more');
            if (loadMoreContainer) {
              loadMoreContainer.style.display = 'none';
            }
          }
        }
      } catch (error) {
        console.error('Error loading more procedures:', error);
      } finally {
        // Reset loading state
        loadMoreBtn.dataset.loading = 'false';
        loadMoreBtn.textContent = 'Load More';
        loadMoreBtn.disabled = false;
      }
    });
  }

  /**
   * Optimize image loading for better performance
   */
  optimizeImageLoading() {
    // Convert first 3 case images to eager loading with high priority
    const caseImages = document.querySelectorAll('.brag-book-gallery-case-card img');
    Array.from(caseImages).slice(0, 3).forEach((img, index) => {
      img.loading = 'eager';
      img.setAttribute('fetchpriority', 'high');

      // Add preload link for critical images
      if (index === 0) {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'image';
        link.href = img.src;
        link.fetchPriority = 'high';
        document.head.appendChild(link);
      }
    });

    // Add image loading optimization for new content
    this.setupImageLoadingOptimization();
  }

  /**
   * Set up automatic image loading optimization for dynamically loaded content
   */
  setupImageLoadingOptimization() {
    // Create a mutation observer to optimize images in new content
    this.imageOptimizationObserver = new MutationObserver(mutations => {
      mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
          if (node.nodeType === Node.ELEMENT_NODE) {
            // Find case images in the new content
            const newImages = node.querySelectorAll ? node.querySelectorAll('.brag-book-gallery-case-card img') : [];

            // Optimize first few images in new content
            Array.from(newImages).slice(0, 2).forEach(img => {
              img.loading = 'eager';
              img.setAttribute('fetchpriority', 'high');
            });
          }
        });
      });
    });

    // Observe the gallery content area for changes
    const galleryContent = document.getElementById('gallery-content');
    if (galleryContent) {
      this.imageOptimizationObserver.observe(galleryContent, {
        childList: true,
        subtree: true
      });
    }
  }

  /**
   * Set up intersection observer to preload cases as they become visible
   */
  setupCasePreloadObserver() {
    if (!window.IntersectionObserver) return;
    this.caseObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const caseCard = entry.target;
          const caseId = caseCard.dataset.caseId;
          const procedureIds = caseCard.dataset.procedureIds;
          if (caseId && !this.casePreloadCache.has(caseId)) {
            // Preload this case with high priority (visible soon)
            this.preloadCase(caseId, procedureIds, 'high');
          }
        }
      });
    }, {
      // Trigger when case is 25% visible for earlier preloading
      threshold: 0.25,
      // Start preloading 800px before the case becomes visible
      rootMargin: '800px'
    });

    // Observe all case cards
    document.querySelectorAll('.brag-book-gallery-case-card').forEach(card => {
      this.caseObserver.observe(card);

      // Add hover-based predictive preloading
      this.setupHoverPreloading(card);
    });
  }

  /**
   * Setup hover-based predictive preloading for a case card
   */
  setupHoverPreloading(card) {
    let hoverTimeout;
    card.addEventListener('mouseenter', () => {
      // Start preloading after 300ms hover (indicates user interest)
      hoverTimeout = setTimeout(() => {
        const caseId = card.dataset.caseId;
        const procedureIds = card.dataset.procedureIds;
        if (caseId && !this.casePreloadCache.has(caseId)) {
          this.preloadCase(caseId, procedureIds, 'hover');
        }
      }, 300);
    });
    card.addEventListener('mouseleave', () => {
      // Cancel preloading if user leaves quickly
      if (hoverTimeout) {
        clearTimeout(hoverTimeout);
      }
    });
  }

  /**
   * Preload visible cases for instant loading
   */
  preloadVisibleCases() {
    const visibleCases = document.querySelectorAll('.brag-book-gallery-case-card');

    // Preload first 3 visible cases
    Array.from(visibleCases).slice(0, 3).forEach(card => {
      const caseId = card.dataset.caseId;
      const procedureIds = card.dataset.procedureIds;
      if (caseId && !this.casePreloadCache.has(caseId)) {
        this.preloadCase(caseId, procedureIds);
      }
    });
  }

  /**
   * Preload a specific case in the background with priority support
   */
  async preloadCase(caseId, procedureIds, priority = 'normal') {
    if (this.casePreloadCache.has(caseId)) return;

    // Mark as being preloaded to avoid duplicates
    this.casePreloadCache.set(caseId, 'loading');

    // Add to priority queue for smart preloading order
    if (!this.preloadQueue) this.preloadQueue = [];
    const preloadTask = {
      caseId,
      procedureIds,
      priority,
      timestamp: Date.now()
    };

    // Insert based on priority (high > hover > normal)
    const priorityOrder = {
      high: 3,
      hover: 2,
      normal: 1
    };
    const insertIndex = this.preloadQueue.findIndex(task => priorityOrder[task.priority] < priorityOrder[priority]);
    if (insertIndex === -1) {
      this.preloadQueue.push(preloadTask);
    } else {
      this.preloadQueue.splice(insertIndex, 0, preloadTask);
    }

    // Process queue with controlled concurrency
    this.processPreloadQueue();
  }

  /**
   * Process preload queue with controlled concurrency
   */
  processPreloadQueue() {
    // Initialize concurrency control
    if (!this.activePreloads) {
      this.activePreloads = new Set();
    }

    // Maximum concurrent preloads
    const maxConcurrency = 3;

    // Sort queue by priority (high > hover > normal) and timestamp (newer first for hover)
    if (this.preloadQueue && this.preloadQueue.length > 0) {
      this.preloadQueue.sort((a, b) => {
        const priorityOrder = {
          high: 3,
          hover: 2,
          normal: 1
        };
        const priorityDiff = priorityOrder[b.priority] - priorityOrder[a.priority];

        // If same priority, newer timestamps first for hover (more recent user intent)
        if (priorityDiff === 0 && a.priority === 'hover') {
          return b.timestamp - a.timestamp;
        }
        return priorityDiff;
      });
    }

    // Process queue items up to concurrency limit
    while (this.activePreloads.size < maxConcurrency && this.preloadQueue && this.preloadQueue.length > 0) {
      const task = this.preloadQueue.shift();

      // Skip if already being processed or completed
      if (this.activePreloads.has(task.caseId) || this.casePreloadCache.has(task.caseId)) {
        continue;
      }

      // Add to active preloads
      this.activePreloads.add(task.caseId);

      // Execute preload asynchronously
      this.executePreloadTask(task).finally(() => {
        this.activePreloads.delete(task.caseId);
        // Process next items in queue
        this.processPreloadQueue();
      });
    }
  }

  /**
   * Execute individual preload task
   */
  async executePreloadTask(task) {
    try {
      const result = await this.preloadCaseViaAjax(task.caseId, task.procedureIds);
      if (result) {
        this.casePreloadCache.set(task.caseId, result);
        const priorityIcon = task.priority === 'high' ? '⚡' : task.priority === 'hover' ? '🖱️' : '📋';
        ;
      }
    } catch (error) {
      console.warn(`Queue failed to process case ${task.caseId}:`, error);
    }
  }

  /**
   * Preload case via AJAX (fallback method)
   */
  async preloadCaseViaAjax(caseId, procedureIds) {
    try {
      // Extract procedure slug from current location
      const pathSegments = window.location.pathname.split('/').filter(s => s);
      const procedureSlug = pathSegments.length > 1 ? pathSegments[pathSegments.length - 1] : '';

      // Get procedure name for context
      let procedureName = '';
      const activeLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedureSlug}"]`);
      if (activeLink) {
        const label = activeLink.querySelector('.brag-book-gallery-filter-option-label');
        if (label) {
          procedureName = label.textContent.trim();
        }
      }
      const requestParams = {
        action: 'brag_book_gallery_load_case_details_html',
        case_id: caseId,
        procedure_slug: procedureSlug,
        procedure_name: procedureName,
        nonce: bragBookGalleryConfig.nonce || ''
      };
      if (procedureIds) {
        requestParams.procedure_ids = procedureIds;
      }
      const response = await fetch(bragBookGalleryConfig.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams(requestParams)
      });
      if (!response.ok) return null;
      const data = await response.json();
      if (data.success && data.data && data.data.html) {
        return data.data.html;
      }
      return null;
    } catch (error) {
      console.warn('AJAX preload failed:', error);
      return null;
    }
  }

  /**
   * Generate HTML for case details from API data
   * Matches PHP HTML_Renderer::render_case_details_html() structure exactly
   */
  generateCaseDetailHTML(caseData) {
    const caseId = caseData.id || '';

    // Extract procedure data using method that matches PHP implementation
    const procedureData = this.extractProcedureDataForDetails(caseData);
    const seoData = this.extractSEOData(caseData);
    const navigationData = caseData.navigation || null;

    // Extract current procedure info from URL
    const pathSegments = window.location.pathname.split('/').filter(s => s);
    const procedureSlug = pathSegments.length > 2 ? pathSegments[pathSegments.length - 2] : '';
    const procedureName = procedureData.name || '';

    // Extract procedure IDs for data attributes (matching PHP implementation exactly)
    let procedureIdsAttr = '';
    if (caseData.procedureIds && Array.isArray(caseData.procedureIds)) {
      const procedureIdsClean = caseData.procedureIds.map(id => parseInt(id)).filter(id => !isNaN(id));
      procedureIdsAttr = ` data-procedure-ids="${this.escapeHtml(procedureIdsClean.join(','))}"`;
    }

    // Add procedure slug attribute if available
    const procedureSlugAttr = procedureSlug ? ` data-procedure="${this.escapeHtml(procedureSlug)}"` : '';

    // Build complete HTML structure matching PHP exactly (single line, no extra whitespace)
    return `<div class="brag-book-gallery-case-detail-view" data-case-id="${this.escapeHtml(caseId)}"${procedureIdsAttr}${procedureSlugAttr}>${this.renderCaseHeader(procedureData, seoData, caseId, procedureSlug, procedureName, navigationData)}${this.renderCaseImages(caseData, procedureData, caseId)}${this.renderCaseDetailsCards(caseData)}</div>`;
  }

  /**
   * Extract procedure data from case data for details view (matching PHP method exactly)
   */
  extractProcedureDataForDetails(caseData) {
    let procedureName = '';
    let procedureSlug = '';
    let procedureIds = [];

    // Check for procedures array with objects (matching PHP logic)
    if (caseData.procedures && Array.isArray(caseData.procedures) && caseData.procedures.length > 0) {
      const firstProcedure = caseData.procedures[0];
      if (firstProcedure.name) {
        const rawProcedureName = firstProcedure.name;
        procedureName = this.formatProcedureDisplayName(rawProcedureName);
        procedureSlug = this.sanitizeTitle(rawProcedureName);
      } else if (firstProcedure.id) {
        procedureIds.push(parseInt(firstProcedure.id));
      }
    } else if (caseData.procedureIds && Array.isArray(caseData.procedureIds)) {
      procedureIds = caseData.procedureIds.map(id => parseInt(id)).filter(id => !isNaN(id));
    }
    return {
      name: procedureName,
      slug: procedureSlug,
      ids: procedureIds,
      procedures: caseData.procedures || []
    };
  }

  /**
   * Format procedure display name (matching PHP method)
   */
  formatProcedureDisplayName(procedureName) {
    if (!procedureName) return '';
    return procedureName.trim();
  }

  /**
   * Sanitize title for URL-safe slug (matching PHP sanitize_title)
   */
  sanitizeTitle(title) {
    if (!title) return '';
    return title.toLowerCase().trim().replace(/[^a-z0-9\s-]/g, '') // Remove special characters
    .replace(/\s+/g, '-') // Replace spaces with hyphens
    .replace(/-+/g, '-') // Replace multiple hyphens with single
    .replace(/^-|-$/g, ''); // Remove leading/trailing hyphens
  }

  /**
   * Extract procedure data from case data (original method)
   */
  extractProcedureData(caseData) {
    let procedureName = '';
    let procedureSlug = '';
    let procedureIds = [];

    // Check for procedures array with objects
    if (caseData.procedures && Array.isArray(caseData.procedures) && caseData.procedures.length > 0) {
      const firstProcedure = caseData.procedures[0];
      if (firstProcedure.name) {
        procedureName = firstProcedure.name;
      }
      if (firstProcedure.slugName) {
        procedureSlug = firstProcedure.slugName;
      }

      // Collect all procedure IDs
      procedureIds = caseData.procedures.map(proc => proc.id).filter(id => id);
    }

    // Fallback: check for procedureIds array
    if (procedureIds.length === 0 && caseData.procedureIds && Array.isArray(caseData.procedureIds)) {
      procedureIds = caseData.procedureIds;
    }
    return {
      procedureName,
      procedureSlug,
      procedureIds,
      hasProcedures: procedureIds.length > 0
    };
  }

  /**
   * Extract SEO data from case data (matches PHP method)
   */
  extractSEOData(caseData) {
    const title = caseData.seoTitle || `Case ${caseData.id}`;
    const description = caseData.seoDescription || '';
    const keywords = caseData.seoKeywords || '';
    return {
      title,
      description,
      keywords
    };
  }

  /**
   * Render case header with navigation (matches PHP render_case_header)
   */
  renderCaseHeader(procedureData, seoData, caseId, procedureSlug, procedureName, navigationData) {
    const gallerySlug = this.getGallerySlug();
    const basePath = '/' + gallerySlug.replace(/^\/+/, '');

    // Build back URL and text
    let backUrl = basePath + '/';
    let backText = '← Back to Gallery';
    if (procedureSlug) {
      backUrl = basePath + '/' + procedureSlug + '/';
      if (procedureName) {
        backText = `← Back to ${procedureName}`;
      }
    }

    // Build navigation buttons
    const navigationButtons = this.buildNavigationButtons(navigationData, procedureSlug);

    // Build title content
    const titleContent = this.buildTitleContent(seoData, procedureData, caseId, procedureName);
    return `
			<div class="brag-book-gallery-case-detail-header">
				<div class="brag-book-gallery-case-navigation">
					<a href="${this.escapeHtml(backUrl)}" class="brag-book-gallery-back-button" rel="nofollow">
						${this.escapeHtml(backText)}
					</a>
					${navigationButtons}
				</div>
				${titleContent}
			</div>
		`;
  }

  /**
   * Build navigation buttons for previous/next cases
   */
  buildNavigationButtons(navigationData, procedureSlug) {
    if (!navigationData) {
      return '';
    }
    const gallerySlug = this.getGallerySlug();
    const basePath = '/' + gallerySlug.replace(/^\/+/, '');
    let html = '<div class="brag-book-gallery-case-nav-buttons">';

    // Previous case button
    if (navigationData.previous) {
      const prevCase = navigationData.previous;
      const prevUrl = procedureSlug ? `${basePath}/${procedureSlug}/${prevCase.id}/` : `${basePath}/case/${prevCase.id}/`;
      html += `
				<a href="${this.escapeHtml(prevUrl)}" class="brag-book-gallery-nav-button brag-book-gallery-prev-case" rel="prev nofollow">
					<span class="brag-book-gallery-nav-arrow">←</span>
					<span class="brag-book-gallery-nav-text">Previous Case</span>
				</a>
			`;
    }

    // Next case button
    if (navigationData.next) {
      const nextCase = navigationData.next;
      const nextUrl = procedureSlug ? `${basePath}/${procedureSlug}/${nextCase.id}/` : `${basePath}/case/${nextCase.id}/`;
      html += `
				<a href="${this.escapeHtml(nextUrl)}" class="brag-book-gallery-nav-button brag-book-gallery-next-case" rel="next nofollow">
					<span class="brag-book-gallery-nav-text">Next Case</span>
					<span class="brag-book-gallery-nav-arrow">→</span>
				</a>
			`;
    }
    return html + '</div>';
  }

  /**
   * Build title content section
   */
  buildTitleContent(seoData, procedureData, caseId, procedureName) {
    const displayTitle = procedureName || procedureData.procedureName || 'Case Study';
    return `
			<div class="brag-book-gallery-case-title-section">
				<h1 class="brag-book-gallery-case-title">${this.escapeHtml(displayTitle)}</h1>
				<div class="brag-book-gallery-case-subtitle">Case #${this.escapeHtml(caseId)}</div>
			</div>
		`;
  }

  /**
   * Render case images section (matches PHP render_case_images)
   */
  renderCaseImages(caseData, procedureData, caseId) {
    if (!caseData.photoSets || !Array.isArray(caseData.photoSets) || caseData.photoSets.length === 0) {
      return this.renderNoImagesSection();
    }
    const mainViewer = this.renderMainImageViewer(caseData.photoSets, procedureData, caseId);
    const thumbnails = caseData.photoSets.length > 1 ? this.renderThumbnails(caseData.photoSets) : '';
    return `
			<div class="brag-book-gallery-brag-book-gallery-case-content">
				<div class="brag-book-gallery-case-images-section">
					<div class="brag-book-gallery-case-images-layout">
						${mainViewer}
						${thumbnails}
					</div>
				</div>
		`;
  }

  /**
   * Render no images available section
   */
  renderNoImagesSection() {
    return `
			<div class="brag-book-gallery-case-images-section">
				<div class="brag-book-gallery-no-images">
					<p>No images available for this case.</p>
				</div>
			</div>
		`;
  }

  /**
   * Render main image viewer
   */
  renderMainImageViewer(photoSets, procedureData, caseId) {
    if (!photoSets || photoSets.length === 0) {
      return '';
    }
    const firstPhotoSet = photoSets[0];
    const beforeImage = firstPhotoSet.beforeLocationUrl || '';
    const afterImage = firstPhotoSet.afterLocationUrl1 || '';
    const processedImage = firstPhotoSet.postProcessedImageLocation || '';

    // Use processed image first, then before, then after
    const mainImage = processedImage || beforeImage || afterImage;
    if (!mainImage) {
      return this.renderNoImagesSection();
    }
    const procedureTitle = procedureData.procedureName || 'Case Study';
    return `
			<div class="brag-book-gallery-main-image-viewer">
				<div class="brag-book-gallery-main-image-container" data-photoset-index="0">
					<img src="${this.escapeHtml(mainImage)}"
						 alt="${this.escapeHtml(procedureTitle)} - Case ${this.escapeHtml(caseId)}"
						 class="brag-book-gallery-main-image"
						 loading="lazy">
				</div>
			</div>
		`;
  }

  /**
   * Render thumbnails for multiple photosets
   */
  renderThumbnails(photoSets) {
    if (!photoSets || photoSets.length <= 1) {
      return '';
    }
    const thumbnailsHTML = photoSets.map((photoSet, index) => {
      const thumbImage = photoSet.postProcessedImageLocation || photoSet.beforeLocationUrl || photoSet.afterLocationUrl1 || '';
      if (!thumbImage) {
        return '';
      }
      const isActive = index === 0 ? ' active' : '';
      return `
				<div class="brag-book-gallery-thumbnail${isActive}" data-photoset-index="${index}">
					<img src="${this.escapeHtml(thumbImage)}"
						 alt="Thumbnail ${index + 1}"
						 loading="lazy">
				</div>
			`;
    }).filter(html => html).join('');
    return `
			<div class="brag-book-gallery-thumbnails-section">
				<div class="brag-book-gallery-thumbnails-grid">
					${thumbnailsHTML}
				</div>
			</div>
		`;
  }

  /**
   * Render case details cards section (matches PHP render_case_details_cards)
   */
  renderCaseDetailsCards(caseData) {
    const html = `
			<div class="brag-book-gallery-case-card-details-section">
				<div class="brag-book-gallery-case-card-details-grid">
					${this.renderProceduresCard(caseData)}
					${this.renderPatientDetailsCard(caseData)}
					${this.renderProcedureDetailsCard(caseData)}
					${this.renderCaseNotesCard(caseData)}
				</div>
			</div>
		`;
    return html + '</div>'; // Close case-content
  }

  /**
   * Render procedures performed card
   */
  renderProceduresCard(caseData) {
    if (!caseData.procedures || !Array.isArray(caseData.procedures) || caseData.procedures.length === 0) {
      return '';
    }
    const proceduresList = caseData.procedures.map(procedure => `<li>${this.escapeHtml(procedure.name || 'Unknown Procedure')}</li>`).join('');
    return `
			<div class="brag-book-gallery-detail-card">
				<h3 class="brag-book-gallery-detail-card-title">Procedures Performed</h3>
				<div class="brag-book-gallery-detail-card-content">
					<ul class="brag-book-gallery-procedures-list">
						${proceduresList}
					</ul>
				</div>
			</div>
		`;
  }

  /**
   * Render patient details card
   */
  renderPatientDetailsCard(caseData) {
    const patientDetails = [];
    if (caseData.age) {
      patientDetails.push(`<div class="brag-book-gallery-detail-row"><span class="brag-book-gallery-detail-label">Age:</span> <span class="brag-book-gallery-detail-value">${this.escapeHtml(String(caseData.age))}</span></div>`);
    }
    if (caseData.gender) {
      patientDetails.push(`<div class="brag-book-gallery-detail-row"><span class="brag-book-gallery-detail-label">Gender:</span> <span class="brag-book-gallery-detail-value">${this.escapeHtml(caseData.gender)}</span></div>`);
    }
    if (caseData.ethnicity) {
      patientDetails.push(`<div class="brag-book-gallery-detail-row"><span class="brag-book-gallery-detail-label">Ethnicity:</span> <span class="brag-book-gallery-detail-value">${this.escapeHtml(caseData.ethnicity)}</span></div>`);
    }
    if (caseData.height) {
      patientDetails.push(`<div class="brag-book-gallery-detail-row"><span class="brag-book-gallery-detail-label">Height:</span> <span class="brag-book-gallery-detail-value">${this.escapeHtml(caseData.height)}</span></div>`);
    }
    if (caseData.weight) {
      patientDetails.push(`<div class="brag-book-gallery-detail-row"><span class="brag-book-gallery-detail-label">Weight:</span> <span class="brag-book-gallery-detail-value">${this.escapeHtml(caseData.weight)}</span></div>`);
    }
    if (patientDetails.length === 0) {
      return '';
    }
    return `
			<div class="brag-book-gallery-detail-card">
				<h3 class="brag-book-gallery-detail-card-title">Patient Information</h3>
				<div class="brag-book-gallery-detail-card-content">
					${patientDetails.join('')}
				</div>
			</div>
		`;
  }

  /**
   * Render procedure details card
   */
  renderProcedureDetailsCard(caseData) {
    const procedureDetails = [];
    if (caseData.surgeryDate) {
      procedureDetails.push(`<div class="brag-book-gallery-detail-row"><span class="brag-book-gallery-detail-label">Surgery Date:</span> <span class="brag-book-gallery-detail-value">${this.escapeHtml(caseData.surgeryDate)}</span></div>`);
    }
    if (caseData.followUpDate) {
      procedureDetails.push(`<div class="brag-book-gallery-detail-row"><span class="brag-book-gallery-detail-label">Follow-up Date:</span> <span class="brag-book-gallery-detail-value">${this.escapeHtml(caseData.followUpDate)}</span></div>`);
    }
    if (caseData.surgeon) {
      procedureDetails.push(`<div class="brag-book-gallery-detail-row"><span class="brag-book-gallery-detail-label">Surgeon:</span> <span class="brag-book-gallery-detail-value">${this.escapeHtml(caseData.surgeon)}</span></div>`);
    }
    if (caseData.location) {
      procedureDetails.push(`<div class="brag-book-gallery-detail-row"><span class="brag-book-gallery-detail-label">Location:</span> <span class="brag-book-gallery-detail-value">${this.escapeHtml(caseData.location)}</span></div>`);
    }
    if (procedureDetails.length === 0) {
      return '';
    }
    return `
			<div class="brag-book-gallery-detail-card">
				<h3 class="brag-book-gallery-detail-card-title">Procedure Details</h3>
				<div class="brag-book-gallery-detail-card-content">
					${procedureDetails.join('')}
				</div>
			</div>
		`;
  }

  /**
   * Render case notes card
   */
  renderCaseNotesCard(caseData) {
    const notes = caseData.notes || caseData.description || caseData.caseNotes || '';
    if (!notes) {
      return '';
    }
    return `
			<div class="brag-book-gallery-detail-card brag-book-gallery-case-notes-card">
				<h3 class="brag-book-gallery-detail-card-title">Case Notes</h3>
				<div class="brag-book-gallery-detail-card-content">
					<div class="brag-book-gallery-case-notes-content">
						${this.escapeHtml(notes).replace(/\n/g, '<br>')}
					</div>
				</div>
			</div>
		`;
  }

  /**
   * Get gallery slug from configuration or default
   */
  getGallerySlug() {
    const config = window.bragBookGalleryConfig || {};
    return config.gallerySlug || 'gallery';
  }

  /**
   * Escape HTML characters for safe output
   */
  escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Update SEO meta tags for case details
   */
  updateCaseDetailsSEO(caseData, procedureName) {
    if (!caseData) return;
    const caseId = caseData.id || '';
    const title = procedureName ? `${procedureName} Case ${caseId} - Before & After` : `Case ${caseId} - Before & After`;

    // Update page title
    document.title = title;

    // Update meta description
    let description = `View before and after photos for Case ${caseId}`;
    if (procedureName) {
      description = `${procedureName} before and after photos - Case ${caseId}. See real patient results.`;
    }
    this.updateMetaTag('description', description);

    // Update canonical URL
    const canonicalUrl = window.location.href;
    this.updateLinkTag('canonical', canonicalUrl);

    // Add structured data for case details
    this.addCaseStructuredData(caseData, procedureName);
  }

  /**
   * Update or create meta tag
   */
  updateMetaTag(name, content) {
    let metaTag = document.querySelector(`meta[name="${name}"]`);
    if (!metaTag) {
      metaTag = document.createElement('meta');
      metaTag.setAttribute('name', name);
      document.head.appendChild(metaTag);
    }
    metaTag.setAttribute('content', content);
  }

  /**
   * Update or create link tag
   */
  updateLinkTag(rel, href) {
    let linkTag = document.querySelector(`link[rel="${rel}"]`);
    if (!linkTag) {
      linkTag = document.createElement('link');
      linkTag.setAttribute('rel', rel);
      document.head.appendChild(linkTag);
    }
    linkTag.setAttribute('href', href);
  }

  /**
   * Add structured data for case details
   */
  addCaseStructuredData(caseData, procedureName) {
    const structuredData = {
      "@context": "https://schema.org",
      "@type": "MedicalProcedure",
      "name": procedureName || "Medical Procedure",
      "identifier": caseData.id,
      "image": [],
      "description": `Before and after case study for ${procedureName || 'medical procedure'}`
    };

    // Add images to structured data
    if (caseData.photoSets && Array.isArray(caseData.photoSets)) {
      caseData.photoSets.forEach(photoSet => {
        if (photoSet.postProcessedImageLocation) {
          structuredData.image.push(photoSet.postProcessedImageLocation);
        } else if (photoSet.beforeLocationUrl) {
          structuredData.image.push(photoSet.beforeLocationUrl);
        } else if (photoSet.afterLocationUrl1) {
          structuredData.image.push(photoSet.afterLocationUrl1);
        }
      });
    }

    // Remove existing structured data script
    const existingScript = document.querySelector('script[data-case-structured-data]');
    if (existingScript) {
      existingScript.remove();
    }

    // Add new structured data script
    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.setAttribute('data-case-structured-data', 'true');
    script.textContent = JSON.stringify(structuredData);
    document.head.appendChild(script);
  }

  /**
   * Initialize thumbnail navigation functionality
   */
  initializeThumbnailNavigation() {
    // Add click handlers for thumbnails
    const thumbnails = document.querySelectorAll('.brag-book-gallery-thumbnail');
    const mainImage = document.querySelector('.brag-book-gallery-main-image');
    if (!mainImage || thumbnails.length === 0) {
      return;
    }
    thumbnails.forEach((thumbnail, index) => {
      thumbnail.addEventListener('click', e => {
        e.preventDefault();

        // Remove active class from all thumbnails
        thumbnails.forEach(thumb => thumb.classList.remove('active'));

        // Add active class to clicked thumbnail
        thumbnail.classList.add('active');

        // Update main image
        const thumbnailImg = thumbnail.querySelector('img');
        if (thumbnailImg && thumbnailImg.src) {
          mainImage.src = thumbnailImg.src;
          mainImage.alt = thumbnailImg.alt || `Case image ${index + 1}`;

          // Update photoset index data attribute
          const mainContainer = document.querySelector('.brag-book-gallery-main-image-container');
          if (mainContainer) {
            mainContainer.setAttribute('data-photoset-index', index.toString());
          }
        }
      });
    });
  }

  /**
   * Get procedure IDs from procedure slug using sidebar data
   * @param {string} procedureSlug - The procedure slug to look up
   * @returns {string|null} - Comma-separated procedure IDs or null if not found
   */
  getProcedureIdsFromSlug(procedureSlug) {
    if (!procedureSlug) {
      return null;
    }

    // Try to get from sidebar data first
    if (window.bragBookGalleryConfig?.sidebarData) {
      const sidebarData = window.bragBookGalleryConfig.sidebarData;

      // Search through categories for the procedure
      for (const category of Object.values(sidebarData)) {
        if (category.procedures) {
          for (const procedure of category.procedures) {
            if (procedure.slug === procedureSlug) {
              // Return comma-separated IDs if available
              return procedure.ids ? procedure.ids.join(',') : null;
            }
          }
        }
      }
    }

    // First, check if we're on a case details page - look for the case detail view container
    const caseDetailView = document.querySelector('.brag-book-gallery-case-detail-view');
    if (caseDetailView && caseDetailView.dataset.procedureIds) {
      return caseDetailView.dataset.procedureIds;
    }

    // Check if there's a procedure link in the DOM that matches the slug
    const procedureLink = document.querySelector(`[data-procedure="${procedureSlug}"]`);
    if (procedureLink && procedureLink.dataset.procedureIds) {
      return procedureLink.dataset.procedureIds;
    }
    console.warn(`⚠️ Could not find procedure IDs for slug: ${procedureSlug}`);
    return null;
  }

  /**
   * Set active state on sidebar for the current case's procedure
   * @param {string} caseId - The case ID to identify procedure context from
   */
  setActiveSidebarForCase(caseId) {
    try {
      // Extract procedure slug from current URL
      const pathSegments = window.location.pathname.split('/').filter(s => s);
      const procedureSlug = pathSegments.length > 2 ? pathSegments[pathSegments.length - 2] : '';
      if (!procedureSlug) {
        return;
      }

      // Clear any existing active states
      const allNavLinks = document.querySelectorAll('.brag-book-gallery-nav-link');
      allNavLinks.forEach(link => {
        link.classList.remove('brag-book-gallery-active');
      });

      // Find and activate the matching procedure link
      const targetLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedureSlug}"]`);
      if (targetLink) {
        targetLink.classList.add('brag-book-gallery-active');

        // Also activate the parent item if it exists
        const parentItem = targetLink.closest('.brag-book-gallery-nav-list-submenu__item');
        if (parentItem) {
          parentItem.classList.add('brag-book-gallery-active');
        }
      } else {
        console.warn(`⚠️ Could not find sidebar link for procedure: ${procedureSlug}`);
      }
    } catch (error) {
      console.error('Error setting sidebar active state:', error);
    }
  }

  /**
   * Initialize favorites page functionality
   *
   * This function is called from the favorites handler to set up
   * the favorites page with proper user detection and display logic.
   */
  initializeFavoritesPage() {
    // Check if user info exists in localStorage - try multiple ways
    let userInfo = null;
    let existingFavorites = [];

    // First try favorites manager if available
    if (this.favoritesManager) {
      userInfo = this.favoritesManager.getUserInfo();
      existingFavorites = Array.from(this.favoritesManager.getFavorites() || []);
    }

    // Fallback: try loading directly from localStorage
    if (!userInfo) {
      try {
        const stored = localStorage.getItem('brag-book-user-info');
        if (stored) {
          userInfo = JSON.parse(stored);
        }
      } catch (e) {
        console.error('Failed to load user info from localStorage:', e);
      }
    }

    // Check for existing favorites in localStorage if not already loaded
    if (existingFavorites.length === 0) {
      try {
        const storedFavorites = localStorage.getItem('brag-book-favorites');
        if (storedFavorites) {
          existingFavorites = JSON.parse(storedFavorites);
        }
      } catch (e) {
        console.error('Failed to load favorites from localStorage:', e);
      }
    }

    // Check if we're on the dedicated favorites page (has favorites shortcode elements)
    const favoritesPage = document.getElementById('brag-book-gallery-favorites');
    if (favoritesPage) {
      // We're on the dedicated favorites page - handle that separately
      this.initializeDedicatedFavoritesPage(userInfo, existingFavorites);
      return;
    }

    // We're on the main gallery with favorites context - use existing showFavoritesOnly logic
    if (userInfo && userInfo.email) {
      // User has registered, show their favorites in the main gallery
      this.showFavoritesOnly();
    } else {
      // Show message to register or go to dedicated favorites page
      this.showFavoritesRegistrationPrompt();
    }
  }

  /**
   * Initialize the dedicated favorites page (from [brag_book_gallery_favorites] shortcode)
   */
  initializeDedicatedFavoritesPage(userInfo, existingFavorites = []) {
    // Get DOM elements from dedicated favorites page
    const emailCapture = document.getElementById('favoritesEmailCapture');
    const gridContainer = document.getElementById('favoritesGridContainer');
    const loadingEl = document.getElementById('favoritesLoading');

    // Check if user has complete info (email, name, phone) and has favorites
    const hasCompleteUserInfo = userInfo && userInfo.email && userInfo.name && userInfo.phone;
    const hasFavorites = existingFavorites && existingFavorites.length > 0;
    console.log('initializeDedicatedFavoritesPage:', {
      hasCompleteUserInfo,
      hasFavorites,
      favoritesCount: existingFavorites?.length || 0,
      userInfo: userInfo ? {
        email: userInfo.email,
        name: userInfo.name,
        phone: userInfo.phone
      } : null
    });

    // If user has favorites in localStorage but no complete user info,
    // we can still display them using WordPress post data
    if (hasFavorites && !hasCompleteUserInfo) {
      console.log('Has favorites but no complete user info - loading from WordPress');
      if (emailCapture) emailCapture.style.display = 'none';
      if (loadingEl) loadingEl.style.display = 'block';
      if (gridContainer) gridContainer.style.display = 'none';

      // Load favorites from WordPress using post IDs
      this.loadFavoritesFromWordPress(existingFavorites, gridContainer, loadingEl);
      return;
    }

    // No user info and no favorites - show email capture form
    if (!hasCompleteUserInfo && !hasFavorites) {
      if (emailCapture) emailCapture.style.display = 'block';
      if (loadingEl) loadingEl.style.display = 'none';
      if (gridContainer) gridContainer.style.display = 'none';
      return;
    }

    // User has info but no favorites - show empty state
    if (!hasFavorites) {
      if (emailCapture) emailCapture.style.display = 'none';
      this.showEmptyFavoritesState(gridContainer, loadingEl);
      return;
    }

    // User has complete info and favorites - fetch from API and display grid
    if (emailCapture) emailCapture.style.display = 'none';
    if (loadingEl) loadingEl.style.display = 'block';
    if (gridContainer) gridContainer.style.display = 'none';

    // Make AJAX call to fetch favorites from API
    this.fetchAndDisplayFavorites(userInfo, existingFavorites, gridContainer, loadingEl);
  }

  /**
   * Fetch favorites from the API and display them as cards in the grid
   */
  async fetchAndDisplayFavorites(userInfo, favoriteIds, gridContainer, loadingEl) {
    try {
      // Make WordPress AJAX request to lookup favorites
      const formData = new FormData();
      formData.append('action', 'brag_book_lookup_favorites');
      formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
      formData.append('email', userInfo.email);
      const response = await fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData
      });
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const result = await response.json();
      if (result.success && result.data && result.data.favorites) {
        this.displayFavoritesGrid(result.data.favorites, gridContainer, loadingEl);
      } else {
        console.warn('No favorites found or API error:', result);
        this.showEmptyFavoritesState(gridContainer, loadingEl);
      }
    } catch (error) {
      console.error('Error fetching favorites:', error);
      this.showEmptyFavoritesState(gridContainer, loadingEl);
    }
  }

  /**
   * Display favorites as cards in the grid
   */
  displayFavoritesGrid(favoritesData, gridContainer, loadingEl) {
    // Hide loading and email capture
    if (loadingEl) loadingEl.style.display = 'none';

    // IMPORTANT: Hide the email capture form when showing favorites grid
    const emailCapture = document.getElementById('favoritesEmailCapture');
    if (emailCapture) {
      emailCapture.style.display = 'none';
    }

    // Show grid container
    if (gridContainer) gridContainer.style.display = 'block';

    // Use the existing PHP-rendered grid element (query by class or ID as fallback)
    let grid = gridContainer.querySelector('.brag-book-gallery-favorites-grid') || gridContainer.querySelector('#favoritesGrid');
    if (!grid) {
      console.error('Expected .brag-book-gallery-favorites-grid element not found in container');
      return;
    }

    // Update the grid to use the proper CSS classes for masonry layout
    grid.className = 'brag-book-gallery-case-grid masonry-layout grid-initialized';

    // Get and hide the empty state element
    const emptyState = gridContainer.querySelector('.brag-book-gallery-favorites-empty');

    // Clear existing content
    grid.innerHTML = '';

    // Display cases if we have them
    if (favoritesData.cases_data && Object.keys(favoritesData.cases_data).length > 0) {
      // Sync API favorites into the FavoritesManager so counts stay accurate
      // Use junction IDs (caseProcedures[0].id) — these match data-procedure-case-id on cards
      if (this.components.favoritesManager) {
        const junctionIds = Object.values(favoritesData.cases_data).map(c => {
          if (c.caseProcedures && c.caseProcedures.length > 0) {
            return String(c.caseProcedures[0].id);
          }
          return String(c.id || c.case_id || '');
        }).filter(Boolean);
        this.components.favoritesManager.favorites = new Set(junctionIds);
        this.components.favoritesManager.saveToStorage();
      }

      // Hide empty state when we have content
      if (emptyState) {
        emptyState.style.display = 'none';
      }

      // Add title before the grid (only if not already present)
      const existingTitle = gridContainer.querySelector('.brag-book-gallery-content-title');
      if (!existingTitle) {
        const titleHtml = `
					<h1 class="brag-book-gallery-content-title">
						<strong>My</strong><span>Favorites</span>
					</h1>
				`;
        grid.insertAdjacentHTML('beforebegin', titleHtml);
      }

      // Add user info after the content title
      this.addUserInfoAfterTitle(favoritesData, gridContainer);

      // Add each case to the grid
      Object.values(favoritesData.cases_data).forEach(async caseData => {
        const cardHtml = await this.generateFavoriteCard(caseData);
        grid.insertAdjacentHTML('beforeend', cardHtml);
      });

      // Show the grid
      grid.style.display = 'grid';
    } else {
      grid.style.display = 'none';
      this.showEmptyFavoritesState(gridContainer, loadingEl);
    }
  }

  /**
   * Generate HTML for a favorite case card (matching exact procedure case format)
   * Uses WordPress post ID to fetch proper image, procedure name, and permalink
   */
  async generateFavoriteCard(caseData) {
    // Extract case ID and try to find corresponding WordPress post
    const apiCaseId = caseData.id || caseData.case_id || '';
    let wpPostData = null;

    // Try to find WordPress post by API case ID
    try {
      const formData = new FormData();
      formData.append('action', 'brag_book_get_case_by_api_id');
      formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
      formData.append('api_case_id', apiCaseId);
      const response = await fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData
      });
      if (response.ok) {
        const result = await response.json();
        if (result.success && result.data) {
          wpPostData = result.data;
        }
      }
    } catch (error) {
      console.warn('Could not fetch WordPress post data for case:', apiCaseId, error);
    }

    // Use WordPress data if available, fallback to API data
    let caseId, procedureTitle, procedureSlug, seoSuffix, imageUrl, postId;
    if (wpPostData) {
      // Use WordPress post data
      postId = wpPostData.ID;
      caseId = wpPostData.post_meta?.brag_book_gallery_api_id || apiCaseId;
      procedureTitle = wpPostData.procedure_name || 'Unknown Procedure';
      procedureSlug = wpPostData.procedure_slug || 'procedure';
      seoSuffix = wpPostData.post_name || wpPostData.post_meta?._case_seo_suffix_url || caseId;
      imageUrl = wpPostData.featured_image_url || '';
    } else {
      // Fallback to API data
      postId = caseData.post_id || '';
      caseId = apiCaseId;
      procedureTitle = caseData.procedure_name || 'Unknown Procedure';
      procedureSlug = caseData.procedure_slug || 'procedure';
      seoSuffix = caseData.seo_suffix || caseId;

      // Get image URL from API data (prefer after photo, fallback to before)
      imageUrl = '';
      if (caseData.images && Array.isArray(caseData.images)) {
        const afterImage = caseData.images.find(img => img.type === 'after');
        const beforeImage = caseData.images.find(img => img.type === 'before');
        imageUrl = afterImage && afterImage.url || beforeImage && beforeImage.url || '';
      }
    }

    // Get gallery slug for URL construction
    const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'gallery';
    const caseUrl = `/${gallerySlug}/${procedureSlug}/${seoSuffix}/`;

    // Get procedure ID - prefer WordPress data, fallback to API data
    let procedureId = '';
    if (wpPostData && wpPostData.procedure_id) {
      procedureId = wpPostData.procedure_id;
    } else if (wpPostData && wpPostData.post_meta) {
      // Try post meta fallbacks
      procedureId = wpPostData.post_meta._procedure_id || (wpPostData.post_meta.brag_book_gallery_procedure_ids ? wpPostData.post_meta.brag_book_gallery_procedure_ids.split(',')[0] : '') || '';
    }
    // Fallback to API data
    if (!procedureId) {
      procedureId = caseData.procedureId || caseData.procedure_id || '';
    }

    // Get the procedure case ID (junction ID) — prefer caseProcedures from API data
    // (the source of truth), fall back to WP post meta, then apiCaseId.
    // When using a junction ID from caseProcedures, always pair it with the matching
    // procedureId from the same record to keep the IDs consistent for the API.
    let procedureCaseId = '';
    if (caseData.caseProcedures && caseData.caseProcedures.length > 0) {
      procedureCaseId = String(caseData.caseProcedures[0].id);
      if (caseData.caseProcedures[0].procedureId) {
        procedureId = String(caseData.caseProcedures[0].procedureId);
      }
    }
    if (!procedureCaseId && wpPostData && wpPostData.post_meta) {
      procedureCaseId = wpPostData.post_meta.brag_book_gallery_procedure_case_id || wpPostData.post_meta.brag_book_gallery_original_case_id || '';
    }
    if (!procedureCaseId) {
      procedureCaseId = apiCaseId;
    }

    // Build data attributes
    const dataAttrs = [`data-case-id="${this.escapeHtml(caseId)}"`, `data-post-id="${postId}"`, `data-procedure-id="${procedureId}"`, `data-procedure-case-id="${this.escapeHtml(procedureCaseId)}"`, `data-age="${caseData.age || ''}"`, `data-gender="${caseData.gender || ''}"`, `data-ethnicity="${caseData.ethnicity || ''}"`, `data-procedure-ids="${procedureId}"`, `data-card="true"`, `data-favorited="true"`].join(' ');

    // Build HTML matching the v3 gallery card structure exactly
    const favoriteItemId = procedureCaseId || caseId;
    const escapedCaseId = this.escapeHtml(caseId);
    const escapedCaseUrl = this.escapeHtml(caseUrl);
    const escapedProcTitle = this.escapeHtml(procedureTitle);
    const escapedItemId = this.escapeHtml(favoriteItemId);
    const escapedImageUrl = this.escapeHtml(imageUrl);
    const escapedProcId = caseData.procedure_id || procedureId || '';
    let html = `<article class="brag-book-gallery-case-card brag-book-gallery-case-card--v3 brag-book-gallery-favorites-card" ${dataAttrs}>`;
    html += '<div class="brag-book-gallery-case-images single-image">';
    html += '<div class="brag-book-gallery-image-container">';
    html += '<div class="brag-book-gallery-skeleton-loader" style="display: none;"></div>';

    // Favorite button
    html += '<div class="brag-book-gallery-item-actions">';
    html += `<button class="brag-book-gallery-favorite-button" data-favorited="true" data-item-id="${escapedItemId}" aria-label="Remove from favorites">`;
    html += '<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">';
    html += '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>';
    html += '</svg>';
    html += '</button>';
    html += '</div>';

    // Carousel wrapper with image link (matching v3 gallery structure)
    html += '<div class="brag-book-gallery-case-carousel">';
    html += `<a href="${escapedCaseUrl}" class="brag-book-gallery-case-permalink brag-book-gallery-carousel-slides" data-case-id="${escapedCaseId}" data-procedure-ids="${escapedProcId}">`;
    if (imageUrl) {
      html += '<picture class="brag-book-gallery-picture">';
      html += `<img src="${escapedImageUrl}" alt="${escapedProcTitle} - Case ${escapedCaseId}" loading="eager" data-image-type="carousel" data-image-url="${escapedImageUrl}" onload="this.closest('.brag-book-gallery-image-container').querySelector('.brag-book-gallery-skeleton-loader').style.display='none';" fetchpriority="high">`;
      html += '</picture>';
    }
    html += '</a>';
    html += '</div>'; // Close carousel

    // Overlay with case name and arrow (matching v3 gallery structure)
    html += '<div class="brag-book-gallery-case-card-overlay">';
    html += '<div class="brag-book-gallery-case-card-overlay-content">';
    html += '<div class="brag-book-gallery-case-card-overlay-info">';
    html += `<span class="brag-book-gallery-case-card-overlay-title">${escapedProcTitle}</span>`;
    html += `<span class="brag-book-gallery-case-card-overlay-case-number">Case #${escapedCaseId}</span>`;
    html += '</div>';
    html += `<a href="${escapedCaseUrl}" class="brag-book-gallery-case-card-overlay-button" data-case-id="${escapedCaseId}" data-procedure-ids="${escapedProcId}" aria-label="View case details">`;
    html += '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
    html += '<path d="M504-480 348-636q-11-11-11-28t11-28q11-11 28-11t28 11l184 184q6 6 8.5 13t2.5 15q0 8-2.5 15t-8.5 13L404-268q-11 11-28 11t-28-11q-11-11-11-28t11-28l156-156Z"></path>';
    html += '</svg>';
    html += '</a>';
    html += '</div>';
    html += '</div>'; // Close overlay

    html += '</div>'; // Close image-container
    html += '</div>'; // Close case-images
    html += '</article>';
    return html;
  }

  /**
   * Escape HTML to prevent XSS attacks
   */
  escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') {
      return String(unsafe || '');
    }
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }

  /**
   * Load favorites from WordPress using post IDs (when user has favorites in localStorage but no user info)
   */
  async loadFavoritesFromWordPress(favoritePostIds, gridContainer, loadingEl) {
    console.log('loadFavoritesFromWordPress called with:', favoritePostIds);
    try {
      // Make AJAX call to load favorites grid
      const formData = new FormData();
      formData.append('action', 'brag_book_load_favorites_grid');
      formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
      formData.append('post_ids', JSON.stringify(favoritePostIds));
      const response = await fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData
      });
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const result = await response.json();
      console.log('loadFavoritesFromWordPress response:', result);
      if (loadingEl) loadingEl.style.display = 'none';
      if (result.success && result.data && result.data.html) {
        // We have HTML content to display
        if (gridContainer) {
          gridContainer.style.display = 'block';

          // Find the favorites grid
          const grid = gridContainer.querySelector('.brag-book-gallery-favorites-grid') || gridContainer.querySelector('#favoritesGrid');
          if (grid) {
            // Add title if not present
            const existingTitle = gridContainer.querySelector('.brag-book-gallery-content-title');
            if (!existingTitle) {
              const titleHtml = `
								<h1 class="brag-book-gallery-content-title">
									<strong>My</strong><span>Favorites</span>
								</h1>
							`;
              grid.insertAdjacentHTML('beforebegin', titleHtml);
            }

            // Set the grid content
            grid.innerHTML = result.data.html;
            grid.style.display = 'grid';

            // Hide empty state
            const emptyState = gridContainer.querySelector('.brag-book-gallery-favorites-empty');
            if (emptyState) {
              emptyState.style.display = 'none';
            }

            // Update UI for favorited state
            this.updateFavoritesUI();
          }
        }
      } else {
        // No content, show empty state
        this.showEmptyFavoritesState(gridContainer, loadingEl);
      }
    } catch (error) {
      console.error('Error loading favorites from WordPress:', error);
      if (loadingEl) loadingEl.style.display = 'none';
      this.showEmptyFavoritesState(gridContainer, loadingEl);
    }
  }

  /**
   * Show empty favorites state
   */
  showEmptyFavoritesState(gridContainer, loadingEl) {
    if (loadingEl) loadingEl.style.display = 'none';

    // Show the grid container to display empty state
    if (gridContainer) {
      gridContainer.style.display = 'block';

      // Hide the favorites grid (could be either class name depending on state)
      const favoritesGrid = gridContainer.querySelector('.brag-book-gallery-favorites-grid');
      const caseGrid = gridContainer.querySelector('.brag-book-gallery-case-grid');
      if (favoritesGrid) {
        favoritesGrid.style.display = 'none';
      }
      if (caseGrid) {
        caseGrid.style.display = 'none';
      }

      // Show the empty state (only when user has info but no favorites)
      const emptyState = gridContainer.querySelector('.brag-book-gallery-favorites-empty');
      if (emptyState) {
        emptyState.style.display = 'block';
      }
    }

    // Hide email capture - empty state should show instead
    const emailCapture = document.getElementById('favoritesEmailCapture');
    if (emailCapture) {
      emailCapture.style.display = 'none';
    }
  }

  /**
   * Add user email and favorites count information after the content title
   */
  addUserInfoAfterTitle(favoritesData, gridContainer) {
    // Find the content title
    const contentTitle = gridContainer.querySelector('.brag-book-gallery-content-title');
    if (!contentTitle) {
      console.warn('Content title not found');
      return;
    }

    // Check if user info already exists (avoid duplicates)
    const existingUserInfo = gridContainer.querySelector('.brag-book-gallery-user-info');
    if (existingUserInfo) {
      return;
    }

    // Get user information from localStorage
    let userInfo = null;
    try {
      const storedUserInfo = localStorage.getItem('brag-book-user-info');
      if (storedUserInfo) {
        userInfo = JSON.parse(storedUserInfo);
      }
    } catch (e) {
      console.error('Failed to parse user info from localStorage:', e);
    }

    // Get favorites count
    const favoritesCount = Object.keys(favoritesData.cases_data || {}).length;
    const userEmail = userInfo?.email || 'Unknown User';

    // Create the user info HTML
    const userInfoHtml = `
			<div class="brag-book-gallery-controls">
				<div class="brag-book-gallery-controls-left">
					<div class="user-email">
						<strong>Email:</strong>
						<span>${userEmail}</span>
					</div>
					<div class="favorites-count">
						<span data-favorites-count data-favorites-format="text">${favoritesCount} favorite${favoritesCount !== 1 ? 's' : ''}</span>
					</div>
				</div>
				<div class="brag-book-gallery-grid-selector">
					<span class="brag-book-gallery-grid-label">View:</span>
					<div class="brag-book-gallery-grid-buttons">
						<button class="brag-book-gallery-grid-btn" data-columns="2" onclick="updateGridLayout(2)" aria-label="View in 2 columns">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="6" height="6"></rect>
								<rect x="9" y="1" width="6" height="6"></rect>
								<rect x="1" y="9" width="6" height="6"></rect>
								<rect x="9" y="9" width="6" height="6"></rect>
							</svg>
							<span class="sr-only">2 Columns</span>
						</button>
						<button class="brag-book-gallery-grid-btn active" data-columns="3" onclick="updateGridLayout(3)" aria-label="View in 3 columns">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
								<rect x="1" y="1" width="4" height="4"></rect>
								<rect x="6" y="1" width="4" height="4"></rect>
								<rect x="11" y="1" width="4" height="4"></rect>
								<rect x="1" y="6" width="4" height="4"></rect>
								<rect x="6" y="6" width="4" height="4"></rect>
								<rect x="11" y="6" width="4" height="4"></rect>
								<rect x="1" y="11" width="4" height="4"></rect>
								<rect x="6" y="11" width="4" height="4"></rect>
								<rect x="11" y="11" width="4" height="4"></rect>
							</svg>
							<span class="sr-only">3 Columns</span>
						</button>
					</div>
				</div>
			</div>
		`;

    // Insert user info after the content title
    contentTitle.insertAdjacentHTML('afterend', userInfoHtml);
  }

  /**
   * Show a prompt for users to register for favorites
   */
  showFavoritesRegistrationPrompt() {
    const galleryContent = document.getElementById('gallery-content');
    if (!galleryContent) return;

    // Create a registration prompt
    const promptHtml = `
			<div class="brag-book-gallery-favorites-registration-prompt">
				<h2>My Favorites</h2>
				<p>To view your favorites, you need to register your email address first.</p>
				<p><a href="/gallery/myfavorites/" class="brag-book-gallery-button">Go to My Favorites Page</a></p>
			</div>
		`;
    galleryContent.innerHTML = promptHtml;
  }

  /**
   * Get favorites data for the given favorite IDs
   */
  async getFavoritesData(favoriteIds) {
    if (!favoriteIds || favoriteIds.length === 0) {
      return [];
    }

    // For now, return basic structure that matches what the PHP expects
    // In a full implementation, you'd fetch case details from the API
    return favoriteIds.map(caseId => ({
      id: caseId,
      images: [],
      // Will be populated from server if needed
      procedures: [],
      // Will be populated from server if needed
      age: '',
      gender: ''
    }));
  }

  /**
   * Load and display user favorites from localStorage and/or API
   */
  async loadUserFavorites() {
    if (!this.favoritesManager) return;
    const favoritesGrid = document.getElementById('favoritesGrid');
    const favoritesEmpty = document.getElementById('favoritesEmpty');
    const favoritesActions = document.getElementById('favoritesActions');
    const loadingEl = document.getElementById('favoritesLoading');
    if (!favoritesGrid || !favoritesEmpty || !favoritesActions) return;
    const favorites = this.favoritesManager.getFavorites();
    const userInfo = this.favoritesManager.getUserInfo();
    if (favorites.size === 0) {
      // Show empty state
      favoritesEmpty.style.display = 'block';
      favoritesGrid.style.display = 'none';
      favoritesActions.style.display = 'none';
      return;
    }

    // Show loading while fetching grid
    if (loadingEl) loadingEl.style.display = 'block';
    favoritesEmpty.style.display = 'none';
    favoritesGrid.style.display = 'none';
    favoritesActions.style.display = 'none';
    try {
      // Convert favorites set to array for API call
      const favoritesData = await this.getFavoritesData(Array.from(favorites));

      // Call AJAX endpoint to get favorites grid HTML
      const response = await this.callAjaxEndpoint('brag_book_load_favorites_grid', {
        favorites: favoritesData,
        userInfo: userInfo,
        columns: 3
      });

      // Hide loading
      if (loadingEl) loadingEl.style.display = 'none';
      if (response.success && response.data.html) {
        if (response.data.isEmpty) {
          // Server returned empty state
          favoritesEmpty.style.display = 'block';
          favoritesGrid.style.display = 'none';
          favoritesActions.style.display = 'none';
        } else {
          // Replace grid content with server-rendered HTML
          favoritesGrid.innerHTML = response.data.html;
          favoritesGrid.style.display = 'grid';
          favoritesActions.style.display = 'flex';
        }
      } else {
        // Error from server - show empty state with error message
        console.error('Failed to load favorites grid:', response.data?.message || 'Unknown error');
        favoritesEmpty.style.display = 'block';
        favoritesGrid.style.display = 'none';
        favoritesActions.style.display = 'none';
      }
    } catch (error) {
      // Network or other error - hide loading and show empty state
      console.error('Error loading favorites grid:', error);
      if (loadingEl) loadingEl.style.display = 'none';
      favoritesEmpty.style.display = 'block';
      favoritesGrid.style.display = 'none';
      favoritesActions.style.display = 'none';
    }
  }

  /**
   * Populate the favorites grid with case data
   */
  async populateFavoritesGrid(favoriteIds) {
    const favoritesGrid = document.getElementById('favoritesGrid');
    if (!favoritesGrid) return;

    // Clear existing content
    favoritesGrid.innerHTML = '';

    // For now, create placeholder cards for favorited cases
    // In a full implementation, you'd fetch case details from the API
    favoriteIds.forEach(caseId => {
      const card = document.createElement('div');
      card.className = 'brag-book-gallery-case-card';
      card.dataset.caseId = caseId;
      card.innerHTML = `
				<div class="brag-book-gallery-case-card-image">
					<img src="${window.bragBookGalleryConfig?.placeholderImage || '#'}"
						 alt="Case ${caseId}"
						 loading="lazy">
				</div>
				<div class="brag-book-gallery-case-card-content">
					<h3>Case ${caseId}</h3>
					<p>Favorited case details would be loaded here</p>
				</div>
				<div class="brag-book-gallery-item-actions">
					<button class="brag-book-gallery-favorite-button"
							data-favorited="true"
							data-item-id="${caseId}">
						<svg fill="red" stroke="red" stroke-width="2" viewBox="0 0 24 24">
							<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
						</svg>
					</button>
				</div>
			`;
      favoritesGrid.appendChild(card);
    });

    // Reinitialize favorite buttons
    if (this.favoritesManager) {
      this.favoritesManager.refreshEventListeners();
    }
  }
}

// Make initializeFavoritesPage available globally for the favorites handler
window.initializeFavoritesPage = function () {
  // Get the main app instance if it exists
  if (window.bragBookGalleryApp && typeof window.bragBookGalleryApp.initializeFavoritesPage === 'function') {
    window.bragBookGalleryApp.initializeFavoritesPage();
  } else {
    console.warn('BRAGbook Gallery App not yet initialized, retrying...');
    // Retry with increasing delays
    let attempts = 0;
    const maxAttempts = 10;
    const tryInit = () => {
      attempts++;
      if (window.bragBookGalleryApp && typeof window.bragBookGalleryApp.initializeFavoritesPage === 'function') {
        window.bragBookGalleryApp.initializeFavoritesPage();
      } else if (attempts < maxAttempts) {
        setTimeout(tryInit, attempts * 100); // Increasing delay
      } else {
        console.error('Failed to initialize favorites page after', maxAttempts, 'attempts');
      }
    };
    setTimeout(tryInit, 100);
  }
};
/* harmony default export */ __webpack_exports__["default"] = (BRAGbookGalleryApp);

/***/ }),

/***/ "./src/js/modules/mobile-menu.js":
/*!***************************************!*\
  !*** ./src/js/modules/mobile-menu.js ***!
  \***************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
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
      breakpoint: options.breakpoint || 1279,
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

    // Close menu when a procedure link is clicked on mobile/tablet
    if (this.sidebar) {
      this.sidebar.addEventListener('click', e => {
        // Check if clicked element is a procedure link
        const procedureLink = e.target.closest('.brag-book-gallery-nav-link');
        if (procedureLink && window.innerWidth < this.options.breakpoint) {
          // Small delay to allow the link to process
          setTimeout(() => {
            this.close();
          }, 100);
        }
      });
    }

    // Swipe gestures
    if (this.options.swipeToClose) {
      this.setupSwipeGestures();
    }

    // Escape key
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && this.isOpen()) {
        this.close();
      }
    });
  }
  setupSwipeGestures() {
    this.sidebar?.addEventListener('touchstart', e => {
      this.touchStartX = e.changedTouches[0].screenX;
    }, {
      passive: true
    });
    this.sidebar?.addEventListener('touchend', e => {
      this.touchEndX = e.changedTouches[0].screenX;
      this.handleSwipeGesture();
    }, {
      passive: true
    });
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
    const mobileHeader = document.querySelector('.brag-book-gallery-mobile-header');
    if (sidebarHeader) {
      sidebarHeader.style.display = isMobile ? 'flex' : 'none';
    }

    // Ensure mobile header is visible on mobile
    if (mobileHeader) {
      mobileHeader.style.display = isMobile ? 'flex' : 'none';
    }
    if (!isMobile) {
      this.close();
      // Remove mobile-specific classes
      document.body.classList.remove('brag-book-gallery-mobile-menu-open');
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
    document.body.classList.add('brag-book-gallery-mobile-menu-open');

    // Set high z-index for wrapper to ensure sidebar appears above everything
    const wrapper = document.querySelector('.brag-book-gallery-wrapper');
    if (wrapper) {
      wrapper.style.zIndex = '9999';
    }

    // Update menu icon to X
    const menuIcon = this.menuToggle.querySelector('svg');
    if (menuIcon) {
      menuIcon.innerHTML = '<path d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/>';
    }

    // Prevent body scroll
    document.body.style.overflow = 'hidden';

    // Focus management for accessibility
    this.previousFocus = document.activeElement;

    // Set focus to first focusable element in sidebar
    setTimeout(() => {
      const firstFocusable = this.sidebar.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (firstFocusable) {
        firstFocusable.focus();
      }
    }, 300);
  }
  close() {
    if (!this.sidebar || !this.menuToggle) return;
    this.menuToggle.dataset.menuOpen = 'false';
    this.menuToggle.setAttribute('aria-expanded', 'false');
    this.menuToggle.setAttribute('aria-label', 'Open navigation menu');
    this.sidebar.classList.remove('brag-book-gallery-active');
    this.overlay?.classList.remove('brag-book-gallery-active');
    document.body.classList.remove('brag-book-gallery-mobile-menu-open');

    // Reset z-index for wrapper
    const wrapper = document.querySelector('.brag-book-gallery-wrapper');
    if (wrapper) {
      wrapper.style.zIndex = '';
    }

    // Update menu icon back to hamburger
    const menuIcon = this.menuToggle.querySelector('svg');
    if (menuIcon) {
      menuIcon.innerHTML = '<path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/>';
    }

    // Restore body scroll
    document.body.style.overflow = '';

    // Restore focus to previous element
    if (this.previousFocus && this.previousFocus.focus) {
      this.previousFocus.focus();
    }
  }
  isOpen() {
    return this.menuToggle?.dataset.menuOpen === 'true';
  }
}
/* harmony default export */ __webpack_exports__["default"] = (MobileMenu);

/***/ }),

/***/ "./src/js/modules/search-autocomplete.js":
/*!***********************************************!*\
  !*** ./src/js/modules/search-autocomplete.js ***!
  \***********************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
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
    // Collect all procedures from rendered sidebar DOM or tiles view
    const procedureMap = new Map();
    const sidebarNav = document.querySelector('.brag-book-gallery-nav');
    const tilesNav = document.querySelector('.brag-book-gallery-tiles-view');

    // Check if we have either sidebar nav or tiles view
    if (!sidebarNav && !tilesNav) {
      console.warn('BRAGBook Gallery: No navigation found for search autocomplete');
      this.procedures = [];
      return;
    }

    // Extract procedures from sidebar navigation
    if (sidebarNav) {
      const procedureLinks = sidebarNav.querySelectorAll('.brag-book-gallery-nav-link[data-procedure-slug]');
      procedureLinks.forEach(link => {
        const procedureSlug = link.getAttribute('data-procedure-slug');
        const procedureId = link.getAttribute('data-procedure-id');
        const category = link.getAttribute('data-category');
        const nudity = link.getAttribute('data-nudity') === 'true';
        const url = link.href;

        // Extract name and count from link text
        const linkText = link.textContent.trim();
        const countMatch = linkText.match(/\((\d+)\)$/);
        const count = countMatch ? parseInt(countMatch[1], 10) : 0;
        const name = linkText.replace(/\s*\(\d+\)$/, '').trim();

        // Get category name from the parent details element
        const categoryDetails = link.closest('details[data-category]');
        const categoryName = categoryDetails ? categoryDetails.querySelector('.brag-book-gallery-nav-button__label span')?.textContent?.trim() || category : category;
        if (procedureSlug && name) {
          // Create unique key combining category and procedure slug
          const key = `${category}:${procedureSlug}`;
          if (!procedureMap.has(key)) {
            procedureMap.set(key, {
              id: procedureSlug,
              procedureId: procedureId,
              name: name,
              category: categoryName,
              categorySlug: category,
              count: count,
              searchText: name.toLowerCase(),
              url: url,
              nudity: nudity,
              fullName: `${name} (${count})`
            });
          }
        }
      });
    }

    // Extract procedures from tiles view navigation
    if (tilesNav) {
      const procedureLinks = tilesNav.querySelectorAll('.brag-book-gallery-procedure-link');
      procedureLinks.forEach(link => {
        const url = link.href;
        // Extract procedure slug from URL
        const urlParts = url.split('/');
        const procedureSlug = urlParts[urlParts.length - 2] || '';

        // Extract name and count from link text
        const nameSpan = link.querySelector('.brag-book-gallery-procedure-name');
        const countSpan = link.querySelector('.brag-book-gallery-procedure-count');
        if (!nameSpan) return;
        const name = nameSpan.textContent.trim();
        const countText = countSpan ? countSpan.textContent.trim() : '';
        const countMatch = countText.match(/\((\d+)\)/);
        const count = countMatch ? parseInt(countMatch[1], 10) : 0;

        // Get category from the parent panel
        const panel = link.closest('.brag-book-gallery-subcategory-panel');
        const categoryId = panel ? panel.getAttribute('data-category-id') : '';

        // Find the category button to get the category name
        const categoryButton = categoryId ? document.querySelector(`.brag-book-gallery-category-link[data-category-id="${categoryId}"]`) : null;
        const categoryName = categoryButton ? categoryButton.querySelector('.brag-book-gallery-category-name')?.textContent?.replace(/\s*\(\d+\)$/, '').trim() || '' : '';
        if (procedureSlug && name) {
          // Create unique key
          const key = `${categoryId}:${procedureSlug}`;
          if (!procedureMap.has(key)) {
            procedureMap.set(key, {
              id: procedureSlug,
              procedureId: '',
              name: name,
              category: categoryName,
              categorySlug: categoryId,
              count: count,
              searchText: name.toLowerCase(),
              url: url,
              nudity: false,
              fullName: `${name} (${count})`
            });
          }
        }
      });
    }
    this.procedures = Array.from(procedureMap.values());

    // Debug: Log first few procedures to verify data structure
    if (this.procedures.length > 0) {
      console.log('BRAGBook Gallery: First 3 procedures:', this.procedures.slice(0, 3));
    } else {
      console.warn('BRAGBook Gallery: No procedures loaded from sidebar DOM');
    }
  }
  setupEventListeners() {
    // Input events
    this.input.addEventListener('input', e => this.handleInput(e));
    this.input.addEventListener('focus', () => this.handleFocus());
    this.input.addEventListener('blur', e => this.handleBlur(e));

    // Keyboard navigation
    this.input.addEventListener('keydown', e => this.handleKeydown(e));

    // Click outside to close
    document.addEventListener('click', e => {
      if (!this.wrapper.contains(e.target)) {
        this.close();
      }
    });

    // Dropdown item clicks
    this.dropdown.addEventListener('click', e => {
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
    switch (e.key) {
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
    this.filteredResults = this.procedures.filter(proc => {
      return proc.searchText.includes(normalizedQuery) || proc.category.includes(normalizedQuery);
    }).slice(0, this.options.maxResults);

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
                     data-category="${proc.categorySlug}"
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
        item.scrollIntoView({
          block: 'nearest',
          behavior: 'smooth'
        });
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

    // Find the corresponding procedure data from our loaded procedures
    const procedureData = this.procedures.find(proc => proc.id === procedure && (proc.categorySlug === category || proc.category === category));

    // Trigger callback with full procedure data
    this.options.onSelect({
      procedure,
      category,
      name,
      url: procedureData?.url || '',
      data: procedureData
    });

    // Navigate using the URL from sidebar data
    if (procedureData && procedureData.url) {
      window.location.href = procedureData.url;
    } else {
      // Fallback: try to find corresponding filter link
      const filterLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedure}"]`);
      if (filterLink && filterLink.href) {
        window.location.href = filterLink.href;
      } else {
        console.warn(`BRAGBook Gallery: No URL found for procedure ${procedure} in category ${category}`);
      }
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

  /**
   * Refresh procedures data from sidebar
   * Call this if sidebar data is updated dynamically
   */
  refresh() {
    this.collectProcedures();
  }
}
/* harmony default export */ __webpack_exports__["default"] = (SearchAutocomplete);

/***/ }),

/***/ "./src/js/modules/share-manager.js":
/*!*****************************************!*\
  !*** ./src/js/modules/share-manager.js ***!
  \*****************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/**
 * Share Manager Component
 * Handles sharing functionality for carousel images
 */
class ShareManager {
  constructor(options = {}) {
    this.options = {
      shareMenuId: options.shareMenuId || 'shareMenu',
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
    // Share menus will be created per button, not globally
    // This method is kept for compatibility but doesn't create a global menu
  }
  setupEventListeners() {
    // Listen for share button clicks (delegated)
    document.addEventListener('click', e => {
      const shareButton = e.target.closest('.brag-book-gallery-share-button');
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
      const activeDropdowns = document.querySelectorAll('.brag-book-gallery-share-dropdown.active');
      activeDropdowns.forEach(dropdown => {
        if (!dropdown.contains(e.target) && !e.target.closest('.brag-book-gallery-share-button')) {
          dropdown.classList.remove('active');
          const button = dropdown.closest('.brag-book-gallery-share-button');
          if (button) {
            button.classList.remove('active');
          }
        }
      });
    });

    // Close on escape
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        const activeDropdowns = document.querySelectorAll('.brag-book-gallery-share-dropdown.active');
        activeDropdowns.forEach(dropdown => {
          dropdown.classList.remove('active');
          const button = dropdown.closest('.brag-book-gallery-share-button');
          if (button) {
            button.classList.remove('active');
          }
        });
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
    // Get the carousel item (slide)
    this.activeItem = button.closest('.brag-book-gallery-carousel-item');
    this.activeButton = button;

    // Check if button already has a dropdown
    let dropdown = button.querySelector('.brag-book-gallery-share-dropdown');

    // Create dropdown if it doesn't exist
    if (!dropdown) {
      const menuHtml = `
                <div class="brag-book-gallery-share-dropdown" role="menu">
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="link" role="menuitem">Copy Link</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="email" role="menuitem">Email</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="facebook" role="menuitem">Facebook</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="twitter" role="menuitem">Twitter</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="pinterest" role="menuitem">Pinterest</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="whatsapp" role="menuitem">WhatsApp</button>
                </div>
            `;
      button.insertAdjacentHTML('beforeend', menuHtml);
      dropdown = button.querySelector('.brag-book-gallery-share-dropdown');
    }
    this.shareMenu = dropdown;

    // Add active class to button
    button.classList.add('active');

    // Show dropdown (positioned via CSS)
    this.shareMenu.classList.add('active');
  }
  hideShareDropdown() {
    if (!this.shareMenu) return;

    // Remove active class from button
    if (this.activeButton) {
      this.activeButton.classList.remove('active');
    }
    this.shareMenu.classList.remove('active');
    this.activeButton = null;
    this.activeItem = null;
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
    switch (type) {
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
    this.options.onShare({
      type,
      url: shareUrl,
      text: shareText
    });
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
    window.open(url, `share-${platform}`, `width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no,scrollbars=yes,resizable=yes`);
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

    // Hide after 3 seconds
    setTimeout(() => {
      notification.classList.remove('active');
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
/* harmony default export */ __webpack_exports__["default"] = (ShareManager);

/***/ }),

/***/ "./src/js/modules/utilities.js":
/*!*************************************!*\
  !*** ./src/js/modules/utilities.js ***!
  \*************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   NudityWarningManager: function() { return /* binding */ NudityWarningManager; },
/* harmony export */   PhoneFormatter: function() { return /* binding */ PhoneFormatter; }
/* harmony export */ });
/**
 * Nudity Warning Manager
 * Handles nudity warnings and acceptance state
 */
class NudityWarningManager {
  constructor() {
    this.nudityAccepted = false;
    this.storageKey = 'brag-book-gallery-nudity-accepted';

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
    document.addEventListener('click', e => {
      if (e.target.matches('.brag-book-gallery-nudity-warning-button')) {
        this.handleProceedButtonClick(e.target);
      }
      // Prevent clicks on nudity warning overlay from bubbling to underlying elements
      else if (e.target.matches('.brag-book-gallery-nudity-warning') || e.target.closest('.brag-book-gallery-nudity-warning')) {
        // Only prevent if not clicking on the proceed button
        if (!e.target.matches('.brag-book-gallery-nudity-warning-button') && !e.target.closest('.brag-book-gallery-nudity-warning-button')) {
          e.stopPropagation();
          e.preventDefault();
        }
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
      nudityWarning.style.transition = 'opacity 0.5s ease-out';
      nudityWarning.style.opacity = '0';
      setTimeout(() => {
        nudityWarning.style.display = 'none';
      }, 500);
    });
    allBlurredImages.forEach(blurredImage => {
      blurredImage.style.transition = 'filter 0.5s ease-out';
      blurredImage.style.filter = 'blur(0px)';
    });
  }

  // Method to reset acceptance - call this from browser console
  resetAcceptance() {
    this.nudityAccepted = false;
    try {
      localStorage.removeItem(this.storageKey);
      document.body.classList.remove('nudity-accepted');
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
    input.addEventListener('input', e => {
      this.formatPhoneNumber(e.target);
    });

    // Handle paste
    input.addEventListener('paste', e => {
      setTimeout(() => {
        this.formatPhoneNumber(e.target);
      }, 0);
    });

    // Prevent non-numeric input except for formatting characters
    input.addEventListener('keypress', e => {
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


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
!function() {
/*!****************************!*\
  !*** ./src/js/frontend.js ***!
  \****************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BRAGbookGalleryApp: function() { return /* reexport safe */ _modules_main_app_js__WEBPACK_IMPORTED_MODULE_0__["default"]; },
/* harmony export */   Carousel: function() { return /* reexport safe */ _modules_carousel_js__WEBPACK_IMPORTED_MODULE_1__["default"]; },
/* harmony export */   Dialog: function() { return /* reexport safe */ _modules_dialog_js__WEBPACK_IMPORTED_MODULE_2__["default"]; },
/* harmony export */   FavoritesManager: function() { return /* reexport safe */ _modules_favorites_manager_js__WEBPACK_IMPORTED_MODULE_5__["default"]; },
/* harmony export */   FilterSystem: function() { return /* reexport safe */ _modules_filter_system_js__WEBPACK_IMPORTED_MODULE_3__["default"]; },
/* harmony export */   MobileMenu: function() { return /* reexport safe */ _modules_mobile_menu_js__WEBPACK_IMPORTED_MODULE_4__["default"]; },
/* harmony export */   NudityWarningManager: function() { return /* reexport safe */ _modules_utilities_js__WEBPACK_IMPORTED_MODULE_8__.NudityWarningManager; },
/* harmony export */   PhoneFormatter: function() { return /* reexport safe */ _modules_utilities_js__WEBPACK_IMPORTED_MODULE_8__.PhoneFormatter; },
/* harmony export */   SearchAutocomplete: function() { return /* reexport safe */ _modules_search_autocomplete_js__WEBPACK_IMPORTED_MODULE_7__["default"]; },
/* harmony export */   ShareManager: function() { return /* reexport safe */ _modules_share_manager_js__WEBPACK_IMPORTED_MODULE_6__["default"]; }
/* harmony export */ });
/* harmony import */ var _modules_main_app_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./modules/main-app.js */ "./src/js/modules/main-app.js");
/* harmony import */ var _modules_carousel_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./modules/carousel.js */ "./src/js/modules/carousel.js");
/* harmony import */ var _modules_dialog_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./modules/dialog.js */ "./src/js/modules/dialog.js");
/* harmony import */ var _modules_filter_system_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./modules/filter-system.js */ "./src/js/modules/filter-system.js");
/* harmony import */ var _modules_mobile_menu_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./modules/mobile-menu.js */ "./src/js/modules/mobile-menu.js");
/* harmony import */ var _modules_favorites_manager_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./modules/favorites-manager.js */ "./src/js/modules/favorites-manager.js");
/* harmony import */ var _modules_share_manager_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./modules/share-manager.js */ "./src/js/modules/share-manager.js");
/* harmony import */ var _modules_search_autocomplete_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./modules/search-autocomplete.js */ "./src/js/modules/search-autocomplete.js");
/* harmony import */ var _modules_utilities_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./modules/utilities.js */ "./src/js/modules/utilities.js");
/* harmony import */ var _modules_global_utilities_js__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./modules/global-utilities.js */ "./src/js/modules/global-utilities.js");
/**
 * BRAG book Gallery - Main Entry Point
 *
 * This file serves as the main entry point for the BRAG book Gallery application.
 * It imports and initializes all components in the correct order.
 */

// Import all components and utilities










// Import global utilities (this will execute the initialization code)


// Make components available on window for global access

// Export all components for external use if needed


// Initialize the main application (this happens automatically when global-utilities.js is imported)
}();
/******/ })()
;
//# sourceMappingURL=brag-book-gallery.js.map