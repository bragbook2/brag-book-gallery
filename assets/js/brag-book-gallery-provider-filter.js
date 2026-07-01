/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/*!***********************************!*\
  !*** ./src/js/provider-filter.js ***!
  \***********************************/
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
      grid.innerHTML = '<p class="brag-book-gallery-provider-filter__empty">' + escapeHtml(config.emptyLabel || 'No cases found for this provider.') + '</p>';
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
    document.querySelectorAll('.brag-book-gallery-provider-filter').forEach(initWidget);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
/******/ })()
;
//# sourceMappingURL=brag-book-gallery-provider-filter.js.map