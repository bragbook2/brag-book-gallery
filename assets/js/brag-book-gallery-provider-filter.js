/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/js/modules/utilities.js":
/*!*************************************!*\
  !*** ./src/js/modules/utilities.js ***!
  \*************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   NudityWarningManager: function() { return /* binding */ NudityWarningManager; },
/* harmony export */   PhoneFormatter: function() { return /* binding */ PhoneFormatter; },
/* harmony export */   escapeHtml: function() { return /* binding */ escapeHtml; }
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
        // Only prevent if not clicking the proceed button or decline link
        if (!e.target.closest('.brag-book-gallery-nudity-warning-button') && !e.target.closest('.brag-book-gallery-nudity-warning-decline')) {
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

/**
 * Escape a value for interpolation into HTML.
 *
 * Escapes quotes as well as angle brackets, so the result is safe in a quoted
 * attribute value as well as in a text node. Several modules previously each
 * carried their own copy of this, and the textContent/innerHTML variants among
 * them left quotes intact — fine in a text node, but able to break out of an
 * attribute. Use this one everywhere.
 *
 * @param {*} value - Value to escape; non-strings are coerced.
 * @returns {string} Escaped text, or an empty string for null/undefined.
 */
function escapeHtml(value) {
  if (value === null || value === undefined) {
    return '';
  }
  return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
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
/*!***********************************!*\
  !*** ./src/js/provider-filter.js ***!
  \***********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _modules_utilities_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./modules/utilities.js */ "./src/js/modules/utilities.js");


/**
 * Provider Filter
 *
 * Dropdown filter listing each provider (doctor). Selecting a provider asks the
 * server for that provider's cases (scoped to the current procedure when one is
 * present) and replaces the case grid with the results. The toggle reflects the
 * selected provider's avatar and name. The "All Providers" option and the Reset
 * button both restore the original, unfiltered grid.
 *
 * @package BRAGBookGallery
 * @since 4.8.0
 */

'use strict';
(function () {
  const config = window.bragBookProviderFilter;
  if (!config || !config.ajaxUrl) {
    return;
  }
  const GRID_SELECTOR = '.brag-book-gallery-case-grid';
  const OPTION_SELECTOR = '.brag-book-gallery-provider-filter__option';
  const NAME_SELECTOR = '.brag-book-gallery-provider-filter__name';
  const AVATAR_SELECTOR = '.brag-book-gallery-provider-filter__avatar';
  const SEARCH_INPUT_SELECTOR = '.brag-book-gallery-provider-filter__search-input';
  const NO_MATCH_SELECTOR = '.brag-book-gallery-provider-filter__no-match';

  /**
   * Wire up a single provider filter widget.
   *
   * @param {HTMLElement} root The provider filter container.
   */
  function initWidget(root) {
    const procedure = root.getAttribute('data-procedure-slug') || '';
    const label = root.querySelector('.brag-book-gallery-provider-filter__label');
    const toggleIcon = root.querySelector('.brag-book-gallery-provider-filter__toggle-icon');
    const resetBtn = root.querySelector('[data-provider-reset]');
    const searchInput = root.querySelector(SEARCH_INPUT_SELECTOR);
    const noMatch = root.querySelector(NO_MATCH_SELECTOR);
    const options = Array.prototype.slice.call(root.querySelectorAll(OPTION_SELECTOR));
    // The "All Providers" option has no name to search against, so it's always shown.
    const searchableOptions = options.filter(option => option.hasAttribute('data-provider-name'));
    const ui = {
      label,
      toggleIcon,
      options,
      defaultLabel: label && label.getAttribute('data-default-label') || config.defaultLabel || 'Provider',
      defaultIcon: toggleIcon ? toggleIcon.innerHTML : ''
    };
    const state = {
      originalGrid: null,
      busy: false
    };
    options.forEach(option => {
      option.addEventListener('click', () => {
        if (state.busy) {
          return;
        }
        const slug = option.getAttribute('data-provider-slug') || '';
        closeDetails(root);
        if (slug === '') {
          resetFilter(state, ui);
          return;
        }
        setActive(options, option);
        updateToggle(ui, option, slug);
        filter(state, slug, procedure);
      });
    });
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        if (state.busy) {
          return;
        }
        closeDetails(root);
        resetFilter(state, ui);
      });
    }
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        filterOptionList(searchableOptions, noMatch, searchInput.value);
      });
      searchInput.addEventListener('click', event => {
        // Keep clicks in the search field from bubbling up to <summary> and
        // toggling the dropdown closed.
        event.stopPropagation();
      });
    }
    if (root.tagName === 'DETAILS') {
      root.addEventListener('toggle', () => {
        if (!root.open && searchInput) {
          // Start with a clean list each time the dropdown is reopened.
          searchInput.value = '';
          filterOptionList(searchableOptions, noMatch, '');
        }
      });
    }
  }

  /**
   * Show/hide provider options by name against a search query.
   *
   * @param {HTMLElement[]} searchableOptions Provider option buttons (excludes "All Providers").
   * @param {HTMLElement|null} noMatch The "no providers match" list item.
   * @param {string} query Raw search input value.
   */
  function filterOptionList(searchableOptions, noMatch, query) {
    const needle = query.trim().toLowerCase();
    let visibleCount = 0;
    searchableOptions.forEach(option => {
      const haystack = option.getAttribute('data-provider-name') || '';
      const isMatch = needle === '' || haystack.indexOf(needle) !== -1;
      const item = option.closest('li');
      if (item) {
        item.hidden = !isMatch;
      }
      if (isMatch) {
        visibleCount += 1;
      }
    });
    if (noMatch) {
      noMatch.hidden = visibleCount !== 0;
    }
  }

  /**
   * Clear the filter: select "All Providers", reset the toggle, restore grid.
   *
   * @param {object} state Widget state.
   * @param {object} ui Cached toggle/option references.
   */
  function resetFilter(state, ui) {
    const allOption = ui.options.find(option => (option.getAttribute('data-provider-slug') || '') === '');
    if (allOption) {
      setActive(ui.options, allOption);
    }
    if (ui.label) {
      ui.label.textContent = ui.defaultLabel;
    }
    if (ui.toggleIcon) {
      ui.toggleIcon.innerHTML = ui.defaultIcon;
    }
    restoreGrid(state);
  }

  /**
   * Mark the chosen option active and clear the others.
   *
   * @param {HTMLElement[]} options All option buttons.
   * @param {HTMLElement} active The selected option.
   */
  function setActive(options, active) {
    options.forEach(option => {
      option.classList.toggle('is-active', option === active);
    });
  }

  /**
   * Reflect the selected provider's avatar and name in the dropdown toggle.
   *
   * @param {object} ui Cached toggle references.
   * @param {HTMLElement} option The selected option.
   * @param {string} slug The selected provider slug.
   */
  function updateToggle(ui, option, slug) {
    if (ui.label) {
      const name = option.querySelector(NAME_SELECTOR);
      ui.label.textContent = name ? name.textContent.trim() : ui.defaultLabel;
    }
    if (ui.toggleIcon) {
      const avatar = option.querySelector(AVATAR_SELECTOR);
      ui.toggleIcon.innerHTML = avatar ? avatar.outerHTML : ui.defaultIcon;
    }
  }

  /**
   * Collapse the dropdown after a choice is made.
   *
   * @param {HTMLElement} root The provider filter container.
   */
  function closeDetails(root) {
    if (root.tagName === 'DETAILS') {
      root.open = false;
    }
  }

  /**
   * Request a provider's cases and render them into the grid.
   *
   * @param {object} state Widget state holding the original grid markup.
   * @param {string} provider Selected provider slug.
   * @param {string} procedure Current procedure slug, if any.
   */
  function filter(state, provider, procedure) {
    const grid = document.querySelector(GRID_SELECTOR);
    if (!grid) {
      return;
    }
    if (state.originalGrid === null) {
      state.originalGrid = grid.innerHTML;
    }
    state.busy = true;
    grid.setAttribute('aria-busy', 'true');
    const body = new URLSearchParams();
    body.set('action', config.action);
    body.set('nonce', config.nonce);
    body.set('provider', provider);
    body.set('page', '1');
    if (procedure) {
      body.set('procedure', procedure);
    }
    fetch(config.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString()
    }).then(response => response.json()).then(payload => {
      if (!payload || !payload.success) {
        restoreGrid(state);
        return;
      }
      renderResults(grid, payload.data);
      // Point Load More at this provider so it paginates within it.
      if (typeof window.bragBookGalleryUpdateLoadMoreContext === 'function') {
        window.bragBookGalleryUpdateLoadMoreContext({
          providerSlug: provider,
          procedureName: procedure || '',
          termId: ''
        }, !!(payload.data && payload.data.hasMore));
      }
    }).catch(() => {
      // Network/parse failure: restore the unfiltered grid so the view
      // stays usable rather than stuck on a busy/empty state.
      restoreGrid(state);
    }).finally(() => {
      state.busy = false;
      grid.removeAttribute('aria-busy');
    });
  }

  /**
   * Render a successful filter response into the grid.
   *
   * @param {HTMLElement} grid The case grid element.
   * @param {object} data Response data ({ html, count }).
   */
  function renderResults(grid, data) {
    if (!data || !data.count) {
      grid.innerHTML = '<p class="brag-book-gallery-provider-filter__empty">' + (0,_modules_utilities_js__WEBPACK_IMPORTED_MODULE_0__.escapeHtml)(config.emptyLabel || 'No cases found for this provider.') + '</p>';
      return;
    }
    grid.innerHTML = data.html;
  }

  /**
   * Restore the case grid to its pre-filter markup.
   *
   * @param {object} state Widget state holding the original grid markup.
   */
  function restoreGrid(state) {
    const grid = document.querySelector(GRID_SELECTOR);
    if (grid && state.originalGrid !== null) {
      grid.innerHTML = state.originalGrid;
    }
    // Restore Load More to the original, unfiltered context.
    if (typeof window.bragBookGalleryUpdateLoadMoreContext === 'function') {
      window.bragBookGalleryUpdateLoadMoreContext(null);
    }
  }
  function init() {
    document.querySelectorAll('.brag-book-gallery-provider-filter').forEach(initWidget);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
}();
/******/ })()
;
//# sourceMappingURL=brag-book-gallery-provider-filter.js.map