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
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
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
/*!*************************************!*\
  !*** ./src/js/carousel-frontend.js ***!
  \*************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _modules_carousel_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./modules/carousel.js */ "./src/js/modules/carousel.js");
/* harmony import */ var _modules_utilities_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./modules/utilities.js */ "./src/js/modules/utilities.js");
/**
 * BRAG book Gallery - Carousel-only Entry Point
 *
 * Used by the [brag_book_carousel] shortcode when it's the only BRAGbook
 * shortcode on the page (e.g., a homepage hero carousel). Bundles just the
 * Carousel class plus the small NudityWarningManager and PhoneFormatter
 * utilities — main-app.js, the four lazy modules, and global-utilities are
 * all skipped, dropping the JS payload from ~136 KB to ~30 KB.
 *
 * If the page also has [brag_book_gallery] / [brag_book_gallery_cases] /
 * [brag_book_gallery_favorites] / [brag_book_gallery_sidebar], the PHP
 * carousel handler enqueues the full brag-book-gallery.min.js bundle
 * instead, and this file is never loaded.
 */


document.addEventListener('DOMContentLoaded', function () {
  const carouselElements = document.querySelectorAll('.brag-book-gallery-carousel-wrapper');
  if (carouselElements.length > 0) {
    new _modules_carousel_js__WEBPACK_IMPORTED_MODULE_0__["default"]({});
  }
  new _modules_utilities_js__WEBPACK_IMPORTED_MODULE_1__.NudityWarningManager();
  new _modules_utilities_js__WEBPACK_IMPORTED_MODULE_1__.PhoneFormatter();
});
}();
/******/ })()
;
//# sourceMappingURL=brag-book-gallery-carousel.js.map