/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/*!***********************************!*\
  !*** ./src/js/location-search.js ***!
  \***********************************/
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



(function () {
  const config = window.bragBookLocationSearch;
  if (!config || !config.ajaxUrl) {
    return;
  }
  const GRID_SELECTOR = '.brag-book-gallery-case-grid';
  const MAPS_POLL_INTERVAL = 200;
  const MAPS_POLL_MAX = 50;

  /**
   * Resolve the Google Maps Places library via the async importLibrary loader,
   * polling until the Maps script is present. Resolves null if it never loads.
   *
   * @return {Promise<object|null>}
   */
  function importPlaces() {
    return new Promise(resolve => {
      let attempts = 0;
      const check = () => {
        if (window.google && window.google.maps && typeof window.google.maps.importLibrary === 'function') {
          window.google.maps.importLibrary('places').then(resolve).catch(() => resolve(null));
          return;
        }
        if (++attempts >= MAPS_POLL_MAX) {
          resolve(null);
          return;
        }
        window.setTimeout(check, MAPS_POLL_INTERVAL);
      };
      check();
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
      grid.innerHTML = '<p class="brag-book-gallery-location-search__empty">' + 'No cases found near ' + escapeHtml(ctx.label) + '.</p>';
      ctx.status('No cases found near ' + ctx.label + '.');
      return;
    }
    grid.innerHTML = data.html;
    ctx.status('Showing ' + count + ' ' + (count === 1 ? 'case' : 'cases') + ' within ' + data.radius + ' miles of ' + ctx.label + '.');
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
  }

  /**
   * Escape a string for safe insertion into HTML text.
   *
   * @param {string} value Raw string.
   * @return {string}
   */
  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value);
    return div.innerHTML;
  }
  function init() {
    document.querySelectorAll('.brag-book-gallery-location-search').forEach(initWidget);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
/******/ })()
;
//# sourceMappingURL=brag-book-gallery-location-search.js.map