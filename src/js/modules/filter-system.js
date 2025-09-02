/**
 * Filter System Component
 * Manages expandable filter groups with GSAP animations and URL routing
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
			animateWithGsap: typeof gsap !== 'undefined', // Use GSAP if available
			closeOthersOnOpen: options.closeOthersOnOpen !== false, // Accordion behavior
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

		// Initialize GSAP animation states if available
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			// Set initial collapsed state for filter submenus
			gsap.set(".brag-book-gallery-nav-list-submenu", { height: 0 });
		}
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
		// Filter header click handlers for expanding/collapsing filter groups
		this.filterHeaders?.forEach(header => {
			header.addEventListener('click', () => this.toggleFilter(header));
		});

		// Procedure filter link handlers - intercept clicks to load content via AJAX
		const filterLinks = this.container?.querySelectorAll('.brag-book-gallery-nav-link');
		filterLinks?.forEach(link => {
			link.addEventListener('click', (e) => {
				e.preventDefault(); // Prevent default navigation
				this.handleFilterClick(e.currentTarget);
			});
		});

		// Browser history navigation handler (back/forward buttons)
		window.addEventListener('popstate', (e) => {
			if (e.state && e.state.caseId) {
				// Navigate to/from case detail view
				window.loadCaseDetails(e.state.caseId, e.state.procedureId, e.state.procedureSlug);
			} else if (e.state && e.state.category && e.state.procedure) {
				// Restore filter state from browser history
				this.reactivateFilter(e.state.category, e.state.procedure);
				this.loadFilteredContent(e.state.category, e.state.procedure, e.state.procedureIds, e.state.hasNudity || false);
			} else {
				// Navigate back to base gallery page
				this.clearAll();
				window.location.reload();
			}
		});
	}

	/**
	 * Toggle expansion/collapse of a filter group with animation
	 * @param {HTMLElement} button - The filter header button that was clicked
	 */
	toggleFilter(button) {
		const isExpanded = button.dataset.expanded === 'true';
		const content = button.nextElementSibling; // The submenu content
		const group = button.closest('.brag-book-gallery-nav-list__item');

		// Implement accordion behavior if enabled
		if (!isExpanded && this.options.closeOthersOnOpen) {
			this.closeOtherFilters(button);
		}

		// Update expansion states
		button.dataset.expanded = !isExpanded;
		content.dataset.expanded = !isExpanded;
		group.dataset.expanded = !isExpanded;

		// Animate the expansion/collapse
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			if (!isExpanded) {
				// Expand with GSAP animation
				gsap.to(content, {
					height: "auto",
					opacity: 1,
					duration: 0.4,
					ease: "power2.out"
				});
			} else {
				// Collapse with GSAP animation
				gsap.to(content, {
					height: 0,
					opacity: 0,
					duration: 0.3,
					ease: "power2.in"
				});
			}
		} else {
			// Fallback CSS animation
			content.style.height = !isExpanded ? 'auto' : '0';
			content.style.opacity = !isExpanded ? '1' : '0';
		}
	}

	closeOtherFilters(currentButton) {
		this.filterHeaders?.forEach(header => {
			if (header !== currentButton && header.dataset.expanded === 'true') {
				const content = header.nextElementSibling;
				const group = header.closest('.brag-book-gallery-nav-list__item');

				header.dataset.expanded = 'false';
				content.dataset.expanded = 'false';
				group.dataset.expanded = 'false';

				if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
					gsap.to(content, {
						height: 0,
						opacity: 0,
						duration: 0.3,
						ease: "power2.in"
					});
				} else {
					content.style.height = '0';
					content.style.opacity = '0';
				}
			}
		});
	}

	closeAllFilters() {
		this.filterHeaders?.forEach(header => {
			if (header.dataset.expanded === 'true') {
				this.toggleFilter(header);
			}
		});
	}

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

		// Update filter badges.
		this.updateFilterBadges();

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

		// Animate the selection
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			gsap.to(link, {
				scale: 1.02,
				duration: 0.1,
				yoyo: true,
				repeat: 1,
				ease: "power2.inOut"
			});
		}
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

			// Update filter badges
			this.updateFilterBadges();
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
	 * Load filtered gallery content via AJAX
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

		// Get AJAX configuration
		const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
		const nonce = window.bragBookGalleryConfig?.nonce || '';

		// Get the proper procedure display name from the active link
		let procedureName = procedure;
		const activeLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedure}"]`);
		if (activeLink) {
			const label = activeLink.querySelector('.brag-book-gallery-filter-option-label');
			if (label) {
				procedureName = label.textContent.trim();
			}
		}

		// Prepare request data
		const formData = new FormData();
		formData.append('action', 'brag_book_load_filtered_gallery');
		formData.append('nonce', nonce);
		formData.append('procedure_name', procedureName); // Send display name for SEO
		formData.append('procedure_slug', procedure); // Send slug for filtering
		formData.append('procedure_ids', procedureIds || ''); // Send all procedure IDs
		formData.append('procedure_id', procedureIds?.split(',')[0] || ''); // Keep backward compatibility
		formData.append('has_nudity', hasNudity ? '1' : '0'); // Send nudity flag

		// Make AJAX request
		fetch(ajaxUrl, {
			method: 'POST',
			body: formData
		})
			.then(response => response.json())
			.then(result => {
				if (result.success && result.data?.html) {
					galleryContent.innerHTML = result.data.html;

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

					// Re-initialize carousels if present
					const carousels = galleryContent.querySelectorAll('.brag-book-gallery-carousel-wrapper');
					carousels.forEach(carousel => {
						new Carousel(carousel);
					});

					// Initialize procedure filters after AJAX content loads
					setTimeout(function() {
						initializeProcedureFilters();
					}, 100);

					// Comment out the problematic call for now
					// this.reinitializeCaseLinks();
				} else {
					galleryContent.innerHTML = '<div class="brag-book-gallery-error">No results found for the selected filter.</div>';
				}
			})
			.catch(error => {
				console.error('Filter loading error:', error);
				galleryContent.innerHTML = '<div class="brag-book-gallery-error">Failed to load filtered content. Please try again.</div>';
			});
	}

	/**
	 * Update filter badges display based on active filters
	 */
	updateFilterBadges() {
		const badgesContainer = document.querySelector('[data-action="filter-badges"]');
		const clearAllButton = document.querySelector('[data-action="clear-filters"]');

		if (!badgesContainer || !clearAllButton) return;

		// Clear existing procedure badges (but preserve demographic badges)
		const existingProcedureBadges = badgesContainer.querySelectorAll('[data-filter-key]');
		existingProcedureBadges.forEach(badge => badge.remove());

		// Check if there are active procedure filters
		const hasActiveProcedureFilters = this.activeFilters.size > 0;

		if (hasActiveProcedureFilters) {
			// Add procedure badges
			this.activeFilters.forEach((filter, key) => {
				const badge = this.createFilterBadge(filter.category, filter.procedure, key);
				badgesContainer.appendChild(badge);
			});
		}

		// Check if there are any active filters (demographic or procedure)
		const demographicBadges = badgesContainer.querySelectorAll('[data-filter-category]');
		const hasAnyActiveFilters = hasActiveProcedureFilters || demographicBadges.length > 0;

		// Show/hide clear all button based on any active filters
		clearAllButton.style.display = hasAnyActiveFilters ? 'inline-block' : 'none';
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

			// Update badges display
			this.updateFilterBadges();

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

		// Update badges display
		this.updateFilterBadges();

		// Trigger filter change event
		this.options.onFilterChange(this.activeFilters);

		// Reset URL to base
		window.history.pushState({}, '', window.location.pathname);
	}
}

export default FilterSystem;
