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
			mode: options.mode || 'javascript', // Filter mode
			baseUrl: options.baseUrl || '/gallery', // Base URL for navigation
			closeOthersOnOpen: options.closeOthersOnOpen === true, // Accordion behavior - disabled by default
			onFilterChange: options.onFilterChange || (() => {}), // Filter change callback
			onNavigate: options.onNavigate || ((url) => { window.location.href = url; }), // Navigation callback
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
		window.history.pushState(
			{ category, procedure, procedureIds, basePath, hasNudity },
			'',
			filterUrl
		);

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
		this.container?.querySelectorAll('.brag-book-gallery-nav-link').forEach(filterLink => {
			filterLink.classList.remove('brag-book-gallery-active');
		});

		// Find and activate the matching filter link (match by procedure primarily)
		const filterLink = document.querySelector(
			`.brag-book-gallery-nav-link[data-procedure="${procedure}"]`
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
			const filterLink = document.querySelector(
				`.brag-book-gallery-nav-link[data-procedure="${initialProcedure}"]`
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
				`.brag-book-gallery-nav-link[data-procedure="${procedure}"]`
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
		this.loadFilteredContentDirectly(procedure, procedureIds, hasNudity, procedureName)
			.then(result => {
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
					setTimeout(function() {
						regenerateProcedureFilters();
					}, 100);

					// Load more buttons are handled by global onclick handlers, no rebinding needed

					// Update URL if it's a procedure filter
					if (category === 'procedure' && procedure) {
						this.updateUrlForProcedure(procedure);
					}

					// Scroll to top of content
					galleryContent.scrollIntoView({ behavior: 'smooth', block: 'start' });

					// Badge updates handled by demographic filters only
					// this.updateFilterBadges();
				} else {
					// Direct API failed, fallback to AJAX
					this.loadFilteredContentViaAjax(category, procedure, procedureIds, hasNudity, procedureName, galleryContent);
				}
			})
			.catch(error => {
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
				return { success: false, error: 'Missing API configuration' };
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
			return { success: false, error: error.message };
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
		})
			.then(response => response.json())
			.then(result => {
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
					setTimeout(function() {
						regenerateProcedureFilters();
					}, 100);

					// Load more buttons are handled by global onclick handlers, no rebinding needed

					// Update URL if it's a procedure filter
					if (category === 'procedure' && procedure) {
						this.updateUrlForProcedure(procedure);
					}

					// Scroll to top of content
					galleryContent.scrollIntoView({ behavior: 'smooth', block: 'start' });

					// Badge updates handled by demographic filters only
					// this.updateFilterBadges();
				} else {
					galleryContent.innerHTML = '<div class="brag-book-gallery-error">No results found for the selected filter.</div>';
				}
			})
			.catch(error => {
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
			const transformedCase = { ...caseData };

			// Extract main image from photoSets
			transformedCase.mainImageUrl = '';
			if (caseData.photoSets && Array.isArray(caseData.photoSets) && caseData.photoSets.length > 0) {
				const firstPhotoset = caseData.photoSets[0];
				transformedCase.mainImageUrl = firstPhotoset.postProcessedImageLocation ||
											   firstPhotoset.beforeLocationUrl ||
											   firstPhotoset.afterLocationUrl1 || '';
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
		const seoSuffix = (caseData.caseDetails && caseData.caseDetails[0] && caseData.caseDetails[0].seoSuffixUrl) || caseId;
		const caseUrl = `/${gallerySlug}/${procedureSlug}/${seoSuffix}/`;

		// Get image URL (matching PHP logic)
		let imageUrl = '';
		if (caseData.photoSets && Array.isArray(caseData.photoSets) && caseData.photoSets.length > 0) {
			const firstPhoto = caseData.photoSets[0];
			imageUrl = firstPhoto.postProcessedImageLocation ||
					   firstPhoto.afterLocationUrl1 ||
					   firstPhoto.beforeLocationUrl ||
					   firstPhoto.afterPhoto ||
					   firstPhoto.beforePhoto || '';
		}

		// Start building HTML (matching PHP structure exactly)
		let html = `<article class="brag-book-gallery-case-card" ${dataAttrs}>`;

		// Case images section (matching PHP structure)
		html += '<div class="brag-book-gallery-case-images single-image">';
		html += '<div class="brag-book-gallery-image-container">';

		// Skeleton loader
		html += '<div class="brag-book-gallery-skeleton-loader" style="display: none;"></div>';

		// Favorites button (matching PHP structure)
		html += '<div class="brag-book-gallery-item-actions">';
		html += `<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="case-${this.escapeHtml(caseId)}" aria-label="Add to favorites">`;
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
				if (ageNum >= 18 && ageNum < 25) filterData.age.add('18-24');
				else if (ageNum >= 25 && ageNum < 35) filterData.age.add('25-34');
				else if (ageNum >= 35 && ageNum < 45) filterData.age.add('35-44');
				else if (ageNum >= 45 && ageNum < 55) filterData.age.add('45-54');
				else if (ageNum >= 55 && ageNum < 65) filterData.age.add('55-64');
				else if (ageNum >= 65) filterData.age.add('65+');
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
				if (heightNum < 60) filterData.height.add('Under 5\'0"');
				else if (heightNum >= 60 && heightNum < 64) filterData.height.add('5\'0" - 5\'3"');
				else if (heightNum >= 64 && heightNum < 68) filterData.height.add('5\'4" - 5\'7"');
				else if (heightNum >= 68 && heightNum < 72) filterData.height.add('5\'8" - 5\'11"');
				else if (heightNum >= 72) filterData.height.add('6\'0" and above');
			}

			// Weight
			const weight = card.dataset.weight;
			if (weight) {
				const weightNum = parseFloat(weight);
				if (weightNum < 120) filterData.weight.add('Under 120 lbs');
				else if (weightNum >= 120 && weightNum < 150) filterData.weight.add('120-149 lbs');
				else if (weightNum >= 150 && weightNum < 180) filterData.weight.add('150-179 lbs');
				else if (weightNum >= 180 && weightNum < 210) filterData.weight.add('180-209 lbs');
				else if (weightNum >= 210) filterData.weight.add('210+ lbs');
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
							const displayValue = v.split(' ').map(word =>
								word.charAt(0).toUpperCase() + word.slice(1)
							).join(' ');
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
			const genderOptions = Array.from(filterData.gender).map(g =>
				g === 'male' ? 'Male' : g === 'female' ? 'Female' : g
			);
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
				if (age >= 18 && age < 25) filterData.age.add('18-24');
				else if (age >= 25 && age < 35) filterData.age.add('25-34');
				else if (age >= 35 && age < 45) filterData.age.add('35-44');
				else if (age >= 45 && age < 55) filterData.age.add('45-54');
				else if (age >= 55 && age < 65) filterData.age.add('55-64');
				else if (age >= 65) filterData.age.add('65+');
			}

			if (caseData.gender) {
				filterData.gender.add(caseData.gender.toLowerCase());
			}

			if (caseData.ethnicity) {
				filterData.ethnicity.add(caseData.ethnicity);
			}

			if (caseData.height) {
				const height = parseFloat(caseData.height);
				if (height < 60) filterData.height.add('Under 5\'0"');
				else if (height >= 60 && height < 64) filterData.height.add('5\'0" - 5\'3"');
				else if (height >= 64 && height < 68) filterData.height.add('5\'4" - 5\'7"');
				else if (height >= 68 && height < 72) filterData.height.add('5\'8" - 5\'11"');
				else if (height >= 72) filterData.height.add('6\'0" and above');
			}

			if (caseData.weight) {
				const weight = parseFloat(caseData.weight);
				if (weight < 120) filterData.weight.add('Under 120 lbs');
				else if (weight >= 120 && weight < 150) filterData.weight.add('120-149 lbs');
				else if (weight >= 150 && weight < 180) filterData.weight.add('150-179 lbs');
				else if (weight >= 180 && weight < 210) filterData.weight.add('180-209 lbs');
				else if (weight >= 210) filterData.weight.add('210+ lbs');
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
							const displayValue = v.split(' ').map(word =>
								word.charAt(0).toUpperCase() + word.slice(1)
							).join(' ');
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
			const genderOptions = Array.from(filterData.gender).map(g =>
				g === 'male' ? 'Male' : g === 'female' ? 'Female' : g
			);
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
			const value = (type === 'gender' || type === 'ethnicity') ? option.toLowerCase() : option;

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

		return name.split(/[\s-_]+/)
			.map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
			.join(' ');
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
		switch(category) {
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
		removeButton.addEventListener('click', (e) => {
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
			const filterLink = document.querySelector(
				`[data-category="${filter.category}"][data-procedure="${filter.procedure}"]`
			);
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

export default FilterSystem;
