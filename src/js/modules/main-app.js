import Dialog from './dialog.js';
import FilterSystem from './filter-system.js';
import MobileMenu from './mobile-menu.js';
import FavoritesManager from './favorites-manager.js';
import ShareManager from './share-manager.js';
import SearchAutocomplete from './search-autocomplete.js';
import { NudityWarningManager, PhoneFormatter } from './utilities.js';
import { initGallerySelector } from './gallery-selector.js';

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
		this.components.consultationDialog = new Dialog('consultationDialog', {
			onOpen: () => {},
			onClose: () => {}
		});

		// Bind consultation buttons to dialog opening using event delegation
		// This works for buttons added dynamically (e.g., in tiles view)
		document.addEventListener('click', (e) => {
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

		this.components.filterSystem = new FilterSystem(filterContainer, {
			mode: mode,
			baseUrl: '/gallery', // Customize as needed
			onFilterChange: (activeFilters) => {
				this.applyFilters(activeFilters);
			},
			onNavigate: (url) => {
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
			checkbox.addEventListener('change', (e) => {
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
					if (range === '18-24' && age >= 18 && age < 25) ageMatch = true;
					else if (range === '25-34' && age >= 25 && age < 35) ageMatch = true;
					else if (range === '35-44' && age >= 35 && age < 45) ageMatch = true;
					else if (range === '45-54' && age >= 45 && age < 55) ageMatch = true;
					else if (range === '55-64' && age >= 55 && age < 65) ageMatch = true;
					else if (range === '65+' && age >= 65) ageMatch = true;
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
					if (range === 'Under 5\'0"' && height < 60) heightMatch = true;
					else if (range === '5\'0" - 5\'3"' && height >= 60 && height < 64) heightMatch = true;
					else if (range === '5\'4" - 5\'7"' && height >= 64 && height < 68) heightMatch = true;
					else if (range === '5\'8" - 5\'11"' && height >= 68 && height < 72) heightMatch = true;
					else if (range === '6\'0" and above' && height >= 72) heightMatch = true;
				});

				if (!heightMatch) show = false;
			}

			// Check weight filter
			if (activeFilters.weight.length > 0) {
				const weight = parseFloat(card.dataset.weight);
				let weightMatch = false;

				activeFilters.weight.forEach(range => {
					if (range === 'Under 120 lbs' && weight < 120) weightMatch = true;
					else if (range === '120-149 lbs' && weight >= 120 && weight < 150) weightMatch = true;
					else if (range === '150-179 lbs' && weight >= 150 && weight < 180) weightMatch = true;
					else if (range === '180-209 lbs' && weight >= 180 && weight < 210) weightMatch = true;
					else if (range === '210+ lbs' && weight >= 210) weightMatch = true;
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
		this.components.mobileMenu = new MobileMenu();
	}

	/**
	 * Initialize gallery selector navigation for tiles view
	 */
	initializeGallerySelector() {
		initGallerySelector();
	}

	/**
	 * Initialize favorites management system
	 */
	initializeFavorites() {
		// Create favorites manager with count update callback
		this.components.favoritesManager = new FavoritesManager({
			onUpdate: (favorites) => {
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
		window.addEventListener('favoritesUpdated', (event) => {
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
			const isFavorited = caseIds.some(id =>
				favorites.includes(String(id)) ||
				favorites.includes(id) ||
				favorites.includes(`case-${id}`)
			);

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

		searchWrappers.forEach((searchWrapper) => {
			// Create search instance with configuration
			const searchInstance = new SearchAutocomplete(searchWrapper, {
				minChars: 1,          // Start searching after 1 character
				debounceDelay: 200,   // 200ms delay for performance
				maxResults: 10,       // Limit results shown
				onSelect: (result) => {
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
		if (typeof bragBookGalleryConfig !== 'undefined' &&
		    bragBookGalleryConfig.enableSharing === 'yes') {
			this.components.shareManager = new ShareManager({
				onShare: (data) => {
				}
			});
		}
	}

	initializeConsultationForm() {
		const form = document.querySelector('[data-form="consultation"]');
		if (form) {
			form.addEventListener('submit', (e) => {
				e.preventDefault();
				this.handleFormSubmit(e.target);
			});
		}

		// Clear messages when dialog is opened
		const consultationDialog = document.getElementById('consultationDialog');
		if (consultationDialog) {
			// Listen for when dialog is shown
			const observer = new MutationObserver((mutations) => {
				mutations.forEach((mutation) => {
					if (mutation.type === 'attributes' && mutation.attributeName === 'open') {
						if (consultationDialog.hasAttribute('open')) {
							// Dialog was opened, clear any previous messages
							this.hideModalMessage();
						}
					}
				});
			});

			observer.observe(consultationDialog, { attributes: true });
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
		const token = config.api_token ||
			   config.apiToken ||
			   (config.api_config && config.api_config.default_token) ||
			   null;

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

		return config.api_endpoint ||
			   config.apiEndpoint ||
			   config.apiBaseUrl ||
			   (config.api_config && config.api_config.endpoint) ||
			   'https://app.bragbookgallery.com';
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
		})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					console.log(`BRAGBook: ✓ Procedure view registered successfully for procedureId ${procedureId}`);
				} else {
					console.warn(`BRAGBook: ✗ Procedure view tracking failed:`, data.data?.message || 'Unknown error');
				}
			})
			.catch(error => {
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
		})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					console.log(`BRAGBook: ✓ Case view registered successfully for caseProcedureId ${procedureCaseId}`);
				} else {
					console.warn(`BRAGBook: ✗ Case view tracking failed:`, data.data?.message || 'Unknown error');
				}
			})
			.catch(error => {
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
		document.addEventListener('click', (e) => {
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
		window.addEventListener('popstate', (e) => {
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
			window.history.pushState({ caseId: caseId }, '', url);
		}

		// Show skeleton loading for better perceived performance
		this.showCaseDetailSkeleton();

		// Scroll to top to show loading state
		window.scrollTo({ top: 0, behavior: 'smooth' });

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
						wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
					} else {
						galleryContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
					'Content-Type': 'application/x-www-form-urlencoded',
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
					wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
				} else {
					// Fallback to scrolling to gallery content
					galleryContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
		galleryContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
					'Content-Type': 'application/x-www-form-urlencoded',
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
		document.addEventListener('click', (e) => {
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
		document.addEventListener('click', (e) => {
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
		window.updateDemographicFilterBadges = (activeFilters) => {
				this.updateDemographicBadges(activeFilters);
		};

		// Monitor demographic filter changes if the system exists
		if (window.applyProcedureFilters) {
		}

		// Monitor demographic filter checkboxes for changes
		this.monitorDemographicFilters();

		// Add global delegated event handler for badge remove buttons
		document.addEventListener('click', (e) => {
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
		document.addEventListener('change', (e) => {
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

		switch(category) {
			case 'age':
				displayText = `Age: ${value}`;
				break;
			case 'gender':
				displayText = `Gender: ${value}`;
				// Store just the gender value (Male/Female) without the prefix
				originalValue = value.replace(/^(Male|Female)$/i, (match) => {
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
		removeButton.addEventListener('click', (e) => {
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
		removeButton.addEventListener('click', (e) => {
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
				if (checkboxValue === value ||
					checkboxValue.toLowerCase() === value.toLowerCase() ||
					labelText === value ||
					labelText.toLowerCase() === value.toLowerCase()) {
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
			const changeEvent = new Event('change', { bubbles: true });
			targetCheckbox.dispatchEvent(changeEvent);

			// Also trigger input event as some handlers might listen to it
			const inputEvent = new Event('input', { bubbles: true });
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
		const selectors = [
			'.brag-book-gallery-filter-group input[type="checkbox"]:checked',
			'input[type="checkbox"][data-filter-category]:checked',
			'.brag-book-gallery-filter-option input[type="checkbox"]:checked'
		];

		let totalCleared = 0;

		selectors.forEach(selector => {
			const checkboxes = document.querySelectorAll(selector);

			checkboxes.forEach((checkbox) => {
				checkbox.checked = false;
				checkbox.dispatchEvent(new Event('change', { bubbles: true }));
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
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					filteredGallery.innerHTML = data.data.html;
				} else {
					console.error('Failed to reload gallery:', data.data?.message);
				}
			})
			.catch(error => {
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
			btn.addEventListener('click', (e) => {
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
				form.addEventListener('submit', (e) => {
					e.preventDefault();
					const formData = new FormData(form);
					const email = formData.get('email');

					// Save to localStorage
					const newUserInfo = { email: email };
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
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'brag_book_lookup_favorites',
				email: userInfo.email,
				nonce: nonce
			})
		})
		.then(response => response.json())
		.then(data => {
			// Hide loading state
			if (loadingState) {
				loadingState.style.display = 'none';
			}

			if (data.success && data.data) {
				// Update user info from API response if it includes name and phone
				// The API returns 'user' not 'user_info'
				const apiUser = data.data.user || data.data.user_info;
				if (apiUser || (data.data.name && data.data.phone)) {
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

				// Handle favorites - NEVER delete localStorage, only add to it
				// The API returns 'favorites.case_ids' and 'favorites.cases_data'
				const favoritesData = data.data.favorites || {};
				const caseIds = favoritesData.case_ids || [];
				const casesData = favoritesData.cases_data || {};

				// Use case_ids array directly, or fall back to cases array
				const casesArray = data.data.cases || Object.values(casesData);
				if (caseIds.length > 0 || casesArray.length > 0) {
					// Check if brag-book-favorites exists in localStorage
					let existingFavorites = [];
					const storedFavorites = localStorage.getItem('brag-book-favorites');

					if (storedFavorites) {
						// If it exists, parse it
						try {
							existingFavorites = JSON.parse(storedFavorites);
						} catch (e) {
							console.error('Error parsing existing favorites:', e);
							existingFavorites = [];
						}
					}

					// Create a Set with existing favorites to avoid duplicates
					const favoritesSet = new Set(existingFavorites);

					// Add case IDs from API response to the Set
					// Use caseIds array first, then fall back to iterating casesArray
					if (caseIds.length > 0) {
						caseIds.forEach(id => {
							if (id) favoritesSet.add(String(id));
						});
					} else {
						casesArray.forEach(caseItem => {
							const caseId = caseItem.id || caseItem.caseId || '';
							if (caseId) {
								favoritesSet.add(String(caseId));
							}
						});
					}

					// Convert back to array and save to localStorage
					const updatedFavorites = Array.from(favoritesSet);
					localStorage.setItem('brag-book-favorites', JSON.stringify(updatedFavorites));

					// Update favorites manager if it exists
					if (this.favoritesManager) {
						this.favoritesManager.favorites = new Set(updatedFavorites);
						// Trigger UI update to reflect the synced favorites
						this.favoritesManager.updateUI();
					}

					// Update favorites count in navigation
					const countElements = document.querySelectorAll('[data-favorites-count]');
					countElements.forEach(element => {
						// Check if this is in tiles view (no parentheses)
						const isTilesView = element.closest('.brag-book-gallery-favorites-link--tiles');
						element.textContent = isTilesView ? updatedFavorites.length : `(${updatedFavorites.length})`;
						// Ensure it's visible
						if (element.style) {
							element.style.opacity = '1';
						}
					});
				} else {
					// No cases in response, but ensure localStorage has empty array if nothing exists
					if (!localStorage.getItem('brag-book-favorites')) {
						localStorage.setItem('brag-book-favorites', JSON.stringify([]));
					}

					// Update favorites count to 0
					const countElements = document.querySelectorAll('[data-favorites-count]');
					countElements.forEach(element => {
						// Check if this is in tiles view (no parentheses)
						const isTilesView = element.closest('.brag-book-gallery-favorites-link--tiles');
						element.textContent = isTilesView ? '0' : '(0)';
						if (element.style) {
							element.style.opacity = '1';
						}
					});

					// Update favorites manager if it exists
					if (this.favoritesManager) {
						this.favoritesManager.favorites = new Set();
						this.favoritesManager.updateUI();
					}
				}

				if (data.data.html) {
					// Show favorites grid container and populate with HTML
					if (gridContainer) {
						gridContainer.style.display = 'block';
						const favoritesGrid = gridContainer.querySelector('#favoritesGrid');
						if (favoritesGrid) {
							favoritesGrid.innerHTML = data.data.html;
						}

						// Show favorites actions
						const favoritesActions = gridContainer.querySelector('#favoritesActions');
						if (favoritesActions) {
							favoritesActions.style.display = 'block';
						}

						// Hide empty state
						const emptyState = gridContainer.querySelector('#favoritesEmpty');
						if (emptyState) {
							emptyState.style.display = 'none';
						}
					}

					// Reinitialize components for the new content first
					this.reinitializeGalleryComponents();

					// Update favorite button states after rendering - use ALL favorites from localStorage
					// Small delay to ensure DOM is ready
					setTimeout(() => {
						const allFavorites = localStorage.getItem('brag-book-favorites');
						if (allFavorites) {
							try {
								const favoriteIds = JSON.parse(allFavorites);

								favoriteIds.forEach(favId => {
									// Find all favorite buttons for this case and mark them as favorited
									// Try multiple selector patterns to catch all variations
									const selectors = [
										`[data-item-id="${favId}"]`,
										`[data-case-id="${favId}"]`,
										`[data-item-id="case-${favId}"]`,
										`[data-item-id="${favId}_main"]`
									];

									selectors.forEach(selector => {
										const buttons = document.querySelectorAll(selector);
										buttons.forEach(button => {
											if (button.dataset.favorited !== undefined) {
												button.dataset.favorited = 'true';
											}
										});
									});
								});
							} catch (e) {
								console.error('Error updating favorite button states:', e);
							}
						}
					}, 100);
				} else {
					// Empty or no cases - show the server-side empty state
					if (!localStorage.getItem('brag-book-favorites')) {
						localStorage.setItem('brag-book-favorites', JSON.stringify([]));
					}

					// Show favorites grid container with empty state
					if (gridContainer) {
						gridContainer.style.display = 'block';

						// Hide the favorites grid and actions
						const favoritesGrid = gridContainer.querySelector('#favoritesGrid');
						if (favoritesGrid) {
							favoritesGrid.innerHTML = '';
						}

						const favoritesActions = gridContainer.querySelector('#favoritesActions');
						if (favoritesActions) {
							favoritesActions.style.display = 'none';
						}

						// Show empty state
						const emptyState = gridContainer.querySelector('#favoritesEmpty');
						if (emptyState) {
							emptyState.style.display = 'block';
						}
					}
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
		})
		.catch(error => {
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
			procedureDisplayName = procedureSlug
				.split('-')
				.map(word => word.charAt(0).toUpperCase() + word.slice(1))
				.join(' ');
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
		this.components.nudityWarningManager = new NudityWarningManager();
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
		document.addEventListener('click', (e) => {
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
			const imageContainer = carousel.closest('.brag-book-gallery-image-container');
			if (!imageContainer) return;

			const pagination = imageContainer.querySelector('.brag-book-gallery-case-carousel-pagination');
			if (!pagination) return;

			const pictures = carousel.querySelectorAll('picture');
			if (pictures.length < 2) return;

			const updateActiveDot = () => {
				const scrollLeft = carousel.scrollLeft;
				const containerWidth = carousel.clientWidth;

				// Calculate the active index from scroll position
				const activeIndex = containerWidth > 0
					? Math.round(scrollLeft / containerWidth)
					: 0;

				const dots = pagination.querySelectorAll('.brag-book-gallery-case-carousel-dot');
				dots.forEach((dot, i) => {
					const isActive = i === activeIndex;
					dot.classList.toggle('is-active', isActive);
					dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
				});
			};

			carousel.addEventListener('scroll', updateActiveDot, { passive: true });
			this.caseCarouselScrollHandlers.push({ carousel, handler: updateActiveDot });
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
		this.imageOptimizationObserver = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				mutation.addedNodes.forEach((node) => {
					if (node.nodeType === Node.ELEMENT_NODE) {
						// Find case images in the new content
						const newImages = node.querySelectorAll ?
							node.querySelectorAll('.brag-book-gallery-case-card img') : [];

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

		this.caseObserver = new IntersectionObserver((entries) => {
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
		const priorityOrder = { high: 3, hover: 2, normal: 1 };
		const insertIndex = this.preloadQueue.findIndex(task =>
			priorityOrder[task.priority] < priorityOrder[priority]
		);

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
				const priorityOrder = { high: 3, hover: 2, normal: 1 };
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
				const priorityIcon = task.priority === 'high' ? '⚡' : task.priority === 'hover' ? '🖱️' : '📋';;
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
					'Content-Type': 'application/x-www-form-urlencoded',
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

		return title
			.toLowerCase()
			.trim()
			.replace(/[^a-z0-9\s-]/g, '') // Remove special characters
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
			const prevUrl = procedureSlug ?
				`${basePath}/${procedureSlug}/${prevCase.id}/` :
				`${basePath}/case/${prevCase.id}/`;

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
			const nextUrl = procedureSlug ?
				`${basePath}/${procedureSlug}/${nextCase.id}/` :
				`${basePath}/case/${nextCase.id}/`;

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
			const thumbImage = photoSet.postProcessedImageLocation ||
							  photoSet.beforeLocationUrl ||
							  photoSet.afterLocationUrl1 || '';

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

		const proceduresList = caseData.procedures.map(procedure =>
			`<li>${this.escapeHtml(procedure.name || 'Unknown Procedure')}</li>`
		).join('');

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
		const title = procedureName ?
			`${procedureName} Case ${caseId} - Before & After` :
			`Case ${caseId} - Before & After`;

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
			thumbnail.addEventListener('click', (e) => {
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
			userInfo: userInfo ? { email: userInfo.email, name: userInfo.name, phone: userInfo.phone } : null
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

		// Use the existing PHP-rendered grid element
		let grid = gridContainer.querySelector('.brag-book-gallery-favorites-grid');
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

			// Hide empty state when we have content
			if (emptyState) {
				emptyState.style.display = 'none';
			}

			// Add title before the grid (only if not already present)
			const existingTitle = gridContainer.querySelector('.brag-book-gallery-content-title');
			if (!existingTitle) {
				const titleHtml = `
					<h2 class="brag-book-gallery-content-title">
						<strong>My</strong><span>Favorites</span>
					</h2>
				`;
				grid.insertAdjacentHTML('beforebegin', titleHtml);
			}

			// Add user info after the content title
			this.addUserInfoAfterTitle(favoritesData, gridContainer);

			// Add each case to the grid
			Object.values(favoritesData.cases_data).forEach(async (caseData) => {
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
				imageUrl = (afterImage && afterImage.url) || (beforeImage && beforeImage.url) || '';
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
			procedureId = wpPostData.post_meta._procedure_id ||
				(wpPostData.post_meta.brag_book_gallery_procedure_ids ?
					wpPostData.post_meta.brag_book_gallery_procedure_ids.split(',')[0] : '') ||
				'';
		}
		// Fallback to API data
		if (!procedureId) {
			procedureId = caseData.procedureId || caseData.procedure_id || '';
		}

		// Build data attributes
		const dataAttrs = [
			`data-case-id="${this.escapeHtml(caseId)}"`,
			`data-post-id="${postId}"`,
			`data-procedure-id="${procedureId}"`,
			`data-age="${caseData.age || ''}"`,
			`data-gender="${caseData.gender || ''}"`,
			`data-ethnicity="${caseData.ethnicity || ''}"`,
			`data-procedure-ids="${procedureId}"`,
			`data-card="true"`,
			`data-favorited="true"`
		].join(' ');

		// Start building HTML (matching filter-system.js structure exactly)
		let html = `<article class="brag-book-gallery-case-card" ${dataAttrs}>`;

		// Case images section (matching PHP structure)
		html += '<div class="brag-book-gallery-case-images single-image">';
		html += '<div class="brag-book-gallery-image-container">';

		// Skeleton loader
		html += '<div class="brag-book-gallery-skeleton-loader" style="display: none;"></div>';

		// Favorites button (matching PHP structure)
		// Use procedure ID for favorites consistency, fallback to case ID
		const favoriteItemId = procedureId || caseId;
		html += '<div class="brag-book-gallery-item-actions">';
		html += `<button class="brag-book-gallery-favorite-button favorited" data-favorited="true" data-item-id="${this.escapeHtml(favoriteItemId)}" aria-label="Remove from favorites">`;
		html += '<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">';
		html += '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>';
		html += '</svg>';
		html += '</button>';
		html += '</div>';

		// Case link with image (matching exact PHP structure)
		html += `<a href="${this.escapeHtml(caseUrl)}" class="brag-book-gallery-case-permalink" data-case-id="${this.escapeHtml(caseId)}" data-procedure-ids="${caseData.procedure_id || ''}">`;

		if (imageUrl) {
			html += '<picture class="brag-book-gallery-picture">';
			html += `<img src="${this.escapeHtml(imageUrl)}" alt="${this.escapeHtml(procedureTitle)} - Case ${this.escapeHtml(caseId)}" loading="eager" data-image-type="single" data-image-url="${this.escapeHtml(imageUrl)}" onload="this.closest('.brag-book-gallery-image-container').querySelector('.brag-book-gallery-skeleton-loader').style.display='none';" fetchpriority="high">`;
			html += '</picture>';
		}

		html += '</a>'; // Close case link

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
		html += '<p class="brag-book-gallery-case-card-summary-details">';
		html += '<strong>More Details</strong>';
		html += '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
		html += '<path d="M444-288h72v-156h156v-72H516v-156h-72v156H288v72h156v156Zm36.28 192Q401-96 331-126t-122.5-82.5Q156-261 126-330.96t-30-149.5Q96-560 126-629.5q30-69.5 82.5-122T330.96-834q69.96-30 149.5-30t149.04 30q69.5 30 122 82.5T834-629.28q30 69.73 30 149Q864-401 834-331t-82.5 122.5Q699-156 629.28-126q-69.73 30-149 30Z"></path>';
		html += '</svg>';
		html += '</p>';
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
	 * Escape HTML to prevent XSS attacks
	 */
	escapeHtml(unsafe) {
		if (typeof unsafe !== 'string') {
			return String(unsafe || '');
		}
		return unsafe
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;")
			.replace(/'/g, "&#039;");
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
					const grid = gridContainer.querySelector('.brag-book-gallery-favorites-grid') ||
						gridContainer.querySelector('#favoritesGrid');

					if (grid) {
						// Add title if not present
						const existingTitle = gridContainer.querySelector('.brag-book-gallery-content-title');
						if (!existingTitle) {
							const titleHtml = `
								<h2 class="brag-book-gallery-content-title">
									<strong>My</strong><span>Favorites</span>
								</h2>
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
						<span>${favoritesCount} favorite${favoritesCount !== 1 ? 's' : ''}</span>
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
			images: [], // Will be populated from server if needed
			procedures: [], // Will be populated from server if needed
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
window.initializeFavoritesPage = function() {

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

export default BRAGbookGalleryApp;
