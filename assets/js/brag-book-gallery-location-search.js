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
  !*** ./src/js/location-search.js ***!
  \***********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _modules_utilities_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./modules/utilities.js */ "./src/js/modules/utilities.js");


/**
 * Location Search
 *
 * Inline, location-based gallery search rendered before the filter dropdown.
 * Resolves a typed query (Google Places Autocomplete) or the visitor's current
 * position to coordinates, then asks the server for the nearest cases and
 * replaces the case grid with the distance-ordered results.
 *
 * @package BRAGBookGallery
 * @since 4.7.0
 */

'use strict';
(function () {
  const config = window.bragBookLocationSearch;
  if (!config || !config.ajaxUrl) {
    return;
  }
  const GRID_SELECTOR = '.brag-book-gallery-case-grid';
  const MAPS_POLL_INTERVAL = 200;
  const MAPS_POLL_MAX = 50;
  const MAPS_SRC = 'maps.googleapis.com/maps/api/js';

  // Shared across every widget on the page so the Maps API is loaded at most
  // once, no matter how many search widgets initialise.
  let mapsReady = null;

  /**
   * Whether the Maps API is loaded and exposes the dynamic library loader.
   *
   * @return {boolean}
   */
  function mapsImportReady() {
    return !!(window.google && window.google.maps && typeof window.google.maps.importLibrary === 'function');
  }

  /**
   * Ensure the Google Maps JS API is present, without ever adding a second
   * loader. A page may already load Maps from its theme or another plugin, often
   * under a different API key; a second maps/api/js tag corrupts the shared API
   * and makes the Places web component throw. So reuse an existing load, wait for
   * one already in flight, and only inject our own when the page has none.
   *
   * @return {Promise<void>} Resolves once Maps is usable, or after the timeout.
   */
  function ensureGoogleMaps() {
    if (mapsReady) {
      return mapsReady;
    }
    mapsReady = new Promise(resolve => {
      if (mapsImportReady()) {
        resolve();
        return;
      }
      // Inject a loader only when the page has none. An existing tag — ours
      // from an earlier widget, or another plugin's — is left to finish.
      const hasLoader = !!document.querySelector('script[src*="' + MAPS_SRC + '"]');
      if (!hasLoader && config.apiKey) {
        const params = new URLSearchParams({
          key: config.apiKey,
          libraries: 'places',
          loading: 'async'
        });
        const script = document.createElement('script');
        script.src = 'https://' + MAPS_SRC + '?' + params.toString();
        script.async = true;
        document.head.appendChild(script);
      }
      // Wait for whichever loader (ours or the page's) to finish.
      let attempts = 0;
      const check = () => {
        if (mapsImportReady()) {
          resolve();
          return;
        }
        if (++attempts >= MAPS_POLL_MAX) {
          resolve(); // Give up; importPlaces() reports the widget unavailable.
          return;
        }
        window.setTimeout(check, MAPS_POLL_INTERVAL);
      };
      check();
    });
    return mapsReady;
  }

  /**
   * Resolve the Google Maps Places library, loading the Maps API first when
   * needed. Resolves null when Maps never becomes available.
   *
   * @return {Promise<object|null>}
   */
  function importPlaces() {
    return ensureGoogleMaps().then(() => {
      if (!mapsImportReady()) {
        return null;
      }
      return window.google.maps.importLibrary('places').catch(() => null);
    });
  }

  /**
   * Extract a usable {lat, lng, label} from a selected Place, fetching the
   * fields the new Places API requires before they can be read.
   *
   * @param {object} place A google.maps.places.Place instance.
   * @return {Promise<{lat:number,lng:number,label:string}|null>}
   */
  async function resolvePlace(place) {
    if (!place) {
      return null;
    }
    if (typeof place.fetchFields === 'function') {
      await place.fetchFields({
        fields: ['location', 'formattedAddress', 'displayName']
      });
    }
    const location = place.location;
    if (!location) {
      return null;
    }
    const lat = typeof location.lat === 'function' ? location.lat() : location.lat;
    const lng = typeof location.lng === 'function' ? location.lng() : location.lng;
    const displayName = typeof place.displayName === 'object' && place.displayName ? place.displayName.text : place.displayName;
    const label = place.formattedAddress || displayName || 'your search';
    return {
      lat,
      lng,
      label
    };
  }

  /**
   * Apply a placeholder to the Places autocomplete component, covering both the
   * reflected attribute and the inner input (which renders asynchronously).
   *
   * @param {HTMLElement} element The PlaceAutocompleteElement.
   * @param {string} text The placeholder text.
   */
  function setPlaceholder(element, text) {
    try {
      element.setAttribute('placeholder', text);
    } catch (error) {
      // Attribute not supported on this component version; the fallback below covers it.
    }
    const apply = () => {
      const input = element.shadowRoot && element.shadowRoot.querySelector('input') || element.querySelector('input');
      if (input) {
        input.placeholder = text;
        input.setAttribute('placeholder', text);
        // Force readable (black) text — inline style overrides the
        // component's shadow styles, which can render grey.
        const hostColor = window.getComputedStyle(element).color;
        if (hostColor) {
          input.style.color = hostColor;
        }
        return true;
      }
      return false;
    };
    if (apply()) {
      return;
    }
    let tries = 0;
    const timer = window.setInterval(() => {
      if (apply() || ++tries >= 20) {
        window.clearInterval(timer);
      }
    }, 100);
  }

  /**
   * Initialise a single location search widget.
   *
   * @param {HTMLElement} root The search container element.
   */
  function initWidget(root) {
    const mount = root.querySelector('.brag-book-gallery-location-search__autocomplete');
    const locateBtn = root.querySelector('[data-action="location-search-locate"]');
    // Prefer the page-level results banner above the title; fall back to the
    // inline status beside the field when the banner is not on the page.
    const status = document.getElementById('bbLocationSearchResults') || root.querySelector('.brag-book-gallery-location-search__status');
    const procedure = root.getAttribute('data-procedure-slug') || '';
    if (!mount) {
      return;
    }

    // Remembers the grid markup before the first search so clearing the input
    // (via the component's built-in clear) can restore it.
    const state = {
      originalGrid: null,
      busy: false,
      autocompleteEl: null
    };
    const setStatus = message => {
      if (status) {
        status.textContent = message || '';
      }
    };
    const runSearch = (lat, lng, label) => {
      search({
        status: setStatus,
        state,
        procedure,
        lat,
        lng,
        label
      });
    };

    // Mount the Google Places autocomplete web component. The widget stays
    // hidden (via the --loading class) until this succeeds, so it only ever
    // appears when Google Maps has loaded correctly.
    importPlaces().then(places => {
      if (!places || !places.PlaceAutocompleteElement) {
        return;
      }
      const autocompleteEl = new places.PlaceAutocompleteElement();
      autocompleteEl.id = 'bbLocationSearchInput';
      autocompleteEl.setAttribute('aria-label', 'Search cases by location');
      // Force the component's light theme (input + predictions dropdown) so it
      // stays on-brand regardless of the OS/browser dark-mode preference. The
      // inline color-scheme is the signal the web component honours; the SCSS
      // rule alone does not reliably reach its shadow DOM.
      autocompleteEl.style.colorScheme = 'light';
      setPlaceholder(autocompleteEl, config.placeholder || 'Enter location...');
      mount.appendChild(autocompleteEl);
      state.autocompleteEl = autocompleteEl;
      root.classList.remove('brag-book-gallery-location-search--loading');

      // New Places API: 'gmp-select' fires with a placePrediction to resolve.
      autocompleteEl.addEventListener('gmp-select', async event => {
        try {
          const place = event.placePrediction ? event.placePrediction.toPlace() : event.place;
          const resolved = await resolvePlace(place);
          if (!resolved) {
            setStatus('Please choose a location from the list.');
            return;
          }
          runSearch(resolved.lat, resolved.lng, resolved.label);
        } catch (error) {
          setStatus('Could not resolve that location.');
        }
      });

      // When the input is emptied (e.g. the component's built-in clear),
      // restore the original grid and clear the results banner.
      autocompleteEl.addEventListener('input', () => {
        if (state.originalGrid !== null && getComponentValue(autocompleteEl) === '') {
          restoreGrid(state);
          setStatus('');
        }
      });
    });
    if (locateBtn) {
      locateBtn.addEventListener('click', () => {
        geolocate(setStatus, (lat, lng) => runSearch(lat, lng, 'your location'));
      });
    }
  }

  /**
   * Read the current text value of the Places autocomplete component, covering
   * both the host property and the inner input.
   *
   * @param {HTMLElement} element The PlaceAutocompleteElement.
   * @return {string}
   */
  function getComponentValue(element) {
    if (element && typeof element.value === 'string') {
      return element.value.trim();
    }
    const input = element && element.shadowRoot && element.shadowRoot.querySelector('input') || element && element.querySelector('input');
    return input ? String(input.value).trim() : '';
  }

  /**
   * Request the visitor's current position.
   *
   * @param {function(string):void} setStatus  Status message setter.
   * @param {function(number,number):void} onSuccess Coordinate callback.
   */
  function geolocate(setStatus, onSuccess) {
    if (!navigator.geolocation) {
      setStatus('Location is not supported by this browser.');
      return;
    }
    if (!window.isSecureContext) {
      setStatus('Location requires a secure (HTTPS) connection.');
      return;
    }
    setStatus('Locating…');
    navigator.geolocation.getCurrentPosition(position => onSuccess(position.coords.latitude, position.coords.longitude), error => {
      const denied = error && error.code === error.PERMISSION_DENIED;
      setStatus(denied ? 'Location permission was denied.' : 'Could not determine your location.');
    }, {
      enableHighAccuracy: false,
      timeout: 10000,
      maximumAge: 300000
    });
  }

  /**
   * Perform the AJAX search and render the results into the case grid.
   *
   * @param {object} ctx Search context.
   */
  function search(ctx) {
    const grid = document.querySelector(GRID_SELECTOR);
    if (!grid || ctx.state.busy) {
      return;
    }
    if (ctx.state.originalGrid === null) {
      ctx.state.originalGrid = grid.innerHTML;
    }
    ctx.state.busy = true;
    ctx.status('Searching near ' + ctx.label + '…');
    const body = new URLSearchParams();
    body.set('action', config.action);
    body.set('nonce', config.nonce);
    body.set('lat', String(ctx.lat));
    body.set('lng', String(ctx.lng));
    body.set('radius', String(config.defaultRadius || 50));
    body.set('page', '1');
    if (ctx.procedure) {
      body.set('procedure', ctx.procedure);
    }
    fetch(config.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString()
    }).then(response => response.json()).then(payload => {
      if (!payload || !payload.success) {
        const message = payload && payload.data && payload.data.message ? payload.data.message : 'Search failed. Please try again.';
        ctx.status(message);
        return;
      }
      renderResults(grid, payload.data, ctx);
    }).catch(() => {
      ctx.status('Search failed. Please try again.');
    }).finally(() => {
      ctx.state.busy = false;
    });
  }

  /**
   * Render a successful search payload.
   *
   * @param {HTMLElement} grid The case grid element.
   * @param {object} data Response data.
   * @param {object} ctx Search context.
   */
  function renderResults(grid, data, ctx) {
    const count = data.count || 0;
    if (count === 0) {
      grid.innerHTML = '<p class="brag-book-gallery-location-search__empty">' + 'No cases found near ' + (0,_modules_utilities_js__WEBPACK_IMPORTED_MODULE_0__.escapeHtml)(ctx.label) + '.</p>';
      ctx.status('No cases found near ' + ctx.label + '.');
      if (typeof window.bragBookGalleryUpdateLoadMoreContext === 'function') {
        window.bragBookGalleryUpdateLoadMoreContext({
          lat: ctx.lat,
          lng: ctx.lng,
          procedureName: ctx.procedure || '',
          termId: ''
        }, false);
      }
      return;
    }
    grid.innerHTML = data.html;
    ctx.status('Showing ' + count + ' ' + (count === 1 ? 'case' : 'cases') + ' within ' + data.radius + ' miles of ' + ctx.label + '.');
    // Point Load More at this location so it paginates within the radius.
    if (typeof window.bragBookGalleryUpdateLoadMoreContext === 'function') {
      window.bragBookGalleryUpdateLoadMoreContext({
        lat: ctx.lat,
        lng: ctx.lng,
        procedureName: ctx.procedure || '',
        termId: ''
      }, !!data.hasMore);
    }
  }

  /**
   * Restore the case grid to its pre-search markup.
   *
   * @param {object} state Widget state holding the original grid HTML.
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

  /**
   * Initialise every location search widget currently in the DOM, skipping any
   * already wired up. Idempotent, so it is safe to call on every re-render.
   */
  function initAll() {
    document.querySelectorAll('.brag-book-gallery-location-search').forEach(root => {
      if (root.dataset.locationSearchReady === 'true') {
        return;
      }
      root.dataset.locationSearchReady = 'true';
      initWidget(root);
    });
  }

  /**
   * Re-initialise the widget when the gallery swaps its content. Client-side
   * navigation replaces the innerHTML of #gallery-content, discarding the old
   * widget (and its in-memory search state) and inserting a fresh, server-
   * rendered empty one. Re-initialising that new widget is what resets the
   * search when the visitor switches pages.
   */
  function observeReRenders() {
    const target = document.getElementById('gallery-content');
    if (!target) {
      return;
    }
    // childList only (not subtree): fires when the content area is swapped on
    // navigation, but not on the deep grid mutations a search itself makes.
    new MutationObserver(initAll).observe(target, {
      childList: true
    });
  }
  function init() {
    initAll();
    observeReRenders();
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
//# sourceMappingURL=brag-book-gallery-location-search.js.map