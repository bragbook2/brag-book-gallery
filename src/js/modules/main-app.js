import Carousel from './carousel.js';
import Dialog from './dialog.js';
import FilterSystem from './filter-system.js';
import MobileMenu from './mobile-menu.js';
import FavoritesManager from './favorites-manager.js';
import ShareManager from './share-manager.js';
import SearchAutocomplete from './search-autocomplete.js';
import { NudityWarningManager, PhoneFormatter } from './utilities.js';

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
			return;
		}

		// Initialize core components for normal gallery view
		this.initializeCarousels();
		this.initializeDialogs();
		this.initializeFilters();
		this.initializeMobileMenu();
		this.initializeFavorites();
		this.initializeSearch();
		this.initializeShareManager();
		this.initializeConsultationForm();
		this.initializeCaseLinks();
		this.initializeNudityWarning();
		this.initializeCasePreloading();

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
	 * Initialize all carousel components with specific configurations
	 */
	initializeCarousels() {
		const carousels = document.querySelectorAll('.brag-book-gallery-carousel-wrapper');
		this.components.carousels = [];

		// Configure each carousel with different behaviors
		carousels.forEach((carousel, index) => {
			const options = index === 0 ? {
				// First carousel: featured content with autoplay
				infinite: true,
				autoplay: true,
				autoplayDelay: 4000,
				pauseOnHover: true
			} : {
				// Additional carousels: manual navigation only
				infinite: false,
				autoplay: false
			};

			this.components.carousels.push(new Carousel(carousel, options));
		});

		// Handle responsive behavior with debounced resize events
		let resizeTimer;
		window.addEventListener('resize', () => {
			clearTimeout(resizeTimer);
			// Debounce resize events for performance
			resizeTimer = setTimeout(() => {
				this.components.carousels.forEach(carousel => carousel.refresh());
			}, 250);
		});
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

		// Bind consultation buttons to dialog opening
		document.querySelectorAll('[data-action="request-consultation"]').forEach(button => {
			button.addEventListener('click', () => {
				this.components.consultationDialog.open();
			});
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

		// Set up Clear All button
		this.initializeClearAllButton();

		// Initialize demographic filter badge integration
		this.initializeDemographicFilterBadges();
	}

	/**
	 * Initialize mobile navigation menu
	 */
	initializeMobileMenu() {
		this.components.mobileMenu = new MobileMenu();
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

	initializeCaseLinks() {
		// Handle clicks on case links with AJAX loading
		document.addEventListener('click', (e) => {
			// Check if click is on a case link
			const caseLink = e.target.closest('.brag-book-gallery-case-card-link');
			if (caseLink) {
				e.preventDefault();
				e.stopPropagation();

				// Get case ID and procedure IDs from the link
				const caseId = caseLink.dataset.caseId;
				const procedureIds = caseLink.dataset.procedureIds;

				if (caseId) {
					// Show immediate visual feedback on the clicked card
					const caseCard = caseLink.closest('.brag-book-gallery-case-card');
					if (caseCard) {
						caseCard.style.opacity = '0.6';
						caseCard.style.transform = 'scale(0.98)';
						caseCard.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
					}

					// Load case details via AJAX
					this.loadCaseDetails(caseId, caseLink.href, true, procedureIds);
				}
				return;
			}

			// Check if click is on a case card but not on interactive elements (fallback)
			const caseCard = e.target.closest('.brag-book-gallery-case-card');
			if (caseCard && !e.target.closest('button') && !e.target.closest('details')) {
				// Find the case link within the card
				const caseLinkInCard = caseCard.querySelector('.brag-book-gallery-case-card-link');
				if (caseLinkInCard && caseLinkInCard.href) {
					e.preventDefault();
					const caseId = caseLinkInCard.dataset.caseId;
					const procedureIds = caseLinkInCard.dataset.procedureIds || caseCard.dataset.procedureIds;

					if (caseId) {
						// Show immediate visual feedback on the clicked card
						caseCard.style.opacity = '0.6';
						caseCard.style.transform = 'scale(0.98)';
						caseCard.style.transition = 'opacity 0.2s ease, transform 0.2s ease';

						this.loadCaseDetails(caseId, caseLinkInCard.href, true, procedureIds);
					}
				}
			}

			// Check if click is on a navigation button (next/previous)
			// But exclude summary elements which also use .brag-book-gallery-nav-button
			const navButton = e.target.closest('.brag-book-gallery-nav-button');
			if (navButton && !navButton.closest('summary')) {
				e.preventDefault();
				e.stopPropagation();

				// Extract case ID from the URL
				const href = navButton.href;
				if (href) {
					const url = new URL(href);
					const pathSegments = url.pathname.split('/').filter(s => s);
					
					// URL format: /gallery/procedure-slug/case-id/
					if (pathSegments.length >= 3) {
						const caseId = pathSegments[pathSegments.length - 1];
						const procedureSlug = pathSegments[pathSegments.length - 2];
						
						// Try multiple methods to get procedure IDs for context preservation
						let procedureIds = '';
						
						// Method 1: Try to get from current case's data attributes
						const currentCaseElement = document.querySelector('[data-case-id]');
						if (currentCaseElement && currentCaseElement.dataset.procedureIds) {
							procedureIds = currentCaseElement.dataset.procedureIds;
							console.log('Using procedure IDs from current case element:', procedureIds);
						}
						
						// Method 2: Look up procedure IDs from sidebar data
						if (!procedureIds) {
							const currentSidebarData = window.bragBookGalleryConfig?.sidebarData;
							if (currentSidebarData && procedureSlug) {
								for (const category of Object.values(currentSidebarData)) {
									if (category.procedures) {
										for (const procedure of category.procedures) {
											if (procedure.slug === procedureSlug) {
												procedureIds = procedure.ids ? procedure.ids.join(',') : '';
												console.log('Using procedure IDs from sidebar data:', procedureIds);
												break;
											}
										}
									}
									if (procedureIds) break;
								}
							}
						}
						
						// Method 3: Try to extract from current page URL context
						if (!procedureIds) {
							const currentPathSegments = window.location.pathname.split('/').filter(s => s);
							if (currentPathSegments.length >= 2) {
								const currentProcedureSlug = currentPathSegments[currentPathSegments.length - 2];
								if (currentProcedureSlug === procedureSlug) {
									// Same procedure context, try to find any case with procedure IDs
									const anyCase = document.querySelector('[data-procedure-ids]');
									if (anyCase && anyCase.dataset.procedureIds) {
										procedureIds = anyCase.dataset.procedureIds;
										console.log('Using procedure IDs from any case element:', procedureIds);
									}
								}
							}
						}

						if (caseId) {
							// Show visual feedback on the navigation button
							navButton.style.opacity = '0.7';
							navButton.style.transition = 'opacity 0.2s ease';
							
							// Load case details via AJAX with preserved procedure context
							console.log(`Loading case ${caseId} with procedure context: ${procedureIds}`);
							this.loadCaseDetails(caseId, href, true, procedureIds);
						}
					}
				}
				return;
			}
		});

		// Initialize case detail view thumbnails
		this.initializeCaseDetailThumbnails();

		// Handle browser back/forward navigation
		window.addEventListener('popstate', (e) => {
			if (e.state && e.state.caseId) {
				// Load case details from history state
				this.loadCaseDetails(e.state.caseId, window.location.href, false);
			} else {
				// Reload the page to show gallery
				window.location.reload();
			}
		});
	}

	/**
	 * Check if current URL is a direct case URL and load it immediately
	 * Returns true if a case was loaded, false otherwise
	 */
	async handleDirectCaseUrl() {
		const currentPath = window.location.pathname;
		const pathSegments = currentPath.split('/').filter(s => s);
		
		// Check if this looks like a case URL: /gallery/procedure-slug/case-id
		// We need at least 3 segments and the last should be numeric
		if (pathSegments.length >= 3) {
			const lastSegment = pathSegments[pathSegments.length - 1];
			
			// Check if the last segment is a numeric case ID
			if (/^\d+$/.test(lastSegment)) {
				const caseId = lastSegment;
				const procedureSlug = pathSegments[pathSegments.length - 2]; // Get procedure slug from URL
				const galleryContent = document.getElementById('gallery-content');
				
				if (galleryContent) {
					try {
						// Show skeleton loading immediately for direct URL access
						this.showCaseDetailSkeleton();
						
						// Try to get procedure IDs from sidebar data
						let procedureIds = null;
						if (window.bragBookGalleryConfig?.sidebarData && procedureSlug) {
							procedureIds = this.getProcedureIdsFromSlug(procedureSlug);
							if (procedureIds) {
								console.log(`🔗 Direct case load with procedure context: case ${caseId}, procedure ${procedureSlug}, IDs: ${procedureIds}`);
							}
						}
						
						// Load case details directly without gallery initialization
						await this.loadCaseDetails(caseId, window.location.href, false, procedureIds);
						return true;
					} catch (error) {
						console.warn('Failed to load direct case URL:', error);
						// Fall back to normal gallery loading
						return false;
					}
				}
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
			console.log('Debouncing case load request');
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
					console.log(`⚡ Loading case ${caseId} from preload cache`);
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

			// Always use AJAX method for consistent server-side HTML rendering
			// This ensures consistent CSS, HTML structure, and WordPress integration
			console.log('🔄 Loading case details via server-side AJAX rendering');

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
				console.log(`🔗 AJAX call with procedure context: case ${caseId}, procedure IDs ${procedureIds}`);
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
				console.log(`✅ AJAX response received for case ${caseId}:`, {
					hasHtml: !!data.data.html,
					hasSeo: !!data.data.seo,
					hasNavigation: data.data.html.includes('brag-book-gallery-case-nav-buttons'),
					htmlLength: data.data.html.length
				});
				
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

				// Log view tracking information to console
				if (data.data.view_tracked) {
					console.log(`✅ Case view tracked successfully for Case ID: ${data.data.case_id}`);
				} else if (data.data.view_tracked === false) {
					console.warn(`⚠️ Case view tracking failed for Case ID: ${data.data.case_id}`);

					// Show additional debug info if available
					if (data.data.debug) {
						console.group('View Tracking Debug Info:');
						console.log('Tracking attempted:', data.data.debug.view_tracking_attempted);
						console.log('View tracked:', data.data.debug.view_tracked);
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
		console.log('🦴 Showing case detail skeleton');
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
		console.log('✅ Skeleton loaded into gallery content');
		
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

		// Override console.log to capture filter logs and extract data
		const originalConsoleLog = console.log;
		console.log = (...args) => {
			// Call original console.log first
			originalConsoleLog.apply(console, args);

			// Check if this is an "Active filters" log message
			if (args.length >= 2 && args[0] === 'Active filters:' && typeof args[1] === 'object') {
				this.updateDemographicBadges(args[1]);
			}
		};

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
			element.textContent = `(${count})`;
		});
	}

	initializeFavoritesButton() {
		// Handle all elements with data-action="show-favorites"
		const favoritesBtns = document.querySelectorAll('[data-action="show-favorites"]');
		if (!favoritesBtns.length) return;

		favoritesBtns.forEach(btn => {
			btn.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();

				// If this is the favorites link in sidebar, always show favorites (don't toggle)
				if (btn.classList.contains('brag-book-gallery-favorites-link')) {
					this.showFavoritesView();
				} else {
					// For other buttons, toggle the view
					this.toggleFavoritesView();
				}
			});
		});
	}

	showFavoritesView() {
		// Always show favorites (used by sidebar link)
		const favoritesBtns = document.querySelectorAll('[data-action="show-favorites"]');
		favoritesBtns.forEach(btn => btn.classList.add('active'));

		// Update URL to reflect favorites view
		if (window.history && window.history.pushState) {
			const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'before-after';
			const favoritesUrl = `/${gallerySlug}/myfavorites/`;
			window.history.pushState({ view: 'favorites' }, '', favoritesUrl);
		}

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

		// If no user info, show a form to enter email
		if (!userInfo || !userInfo.email) {
			galleryContent.innerHTML = `
				<div class="brag-book-gallery-favorites-wrapper">
					<div class="brag-book-gallery-favorites-container">
						<div class="brag-book-gallery-favorites-form-wrapper">
							<svg class="brag-book-gallery-favorites-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 180">
								<path fill="#ff595c" d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"></path>
								<path fill="#ff595c" d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"></path>
								<path fill="#121827" d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"></path>
								<path fill="#121827" d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"></path>
								<path fill="#121827" d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"></path>
								<path fill="#121827" d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6,10.9,7.1,19.3,7.1Z"></path>
								<path fill="#121827" d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"></path>
								<path fill="#121827" d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"></path>
								<path fill="#121827" d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"></path>
								<path fill="#121827" d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"></path>
								<path fill="#121827" d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"></path>
								<path fill="#121827" d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"></path>
								<path fill="#121827" d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"></path>
								<path fill="#ff595c" d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"></path>
							</svg>
							<p>Please enter your email to view your saved favorites:</p>
							<form class="brag-book-gallery-favorites-lookup-form" id="favorites-email-form">
								<div class="brag-book-gallery-form-group">
									<input
										type="email"
										name="email"
										class="brag-book-gallery-form-input"
										placeholder="Enter your email address"
										required
									>
									<button type="submit" class="brag-book-gallery-button brag-book-gallery-button--full" data-action="form-submit">
										View Favorites
									</button>
								</div>
							</form>
						</div>
					</div>
				</div>
			`;

			// Setup form handler
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

		// Show loading state
		galleryContent.innerHTML = `
			<div class="brag-book-gallery-loading">
				<div class="brag-book-gallery-spinner"></div>
				<p>Loading your favorites...</p>
			</div>
		`;

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
				action: 'brag_book_get_favorites_list',
				email: userInfo.email,
				nonce: nonce
			})
		})
		.then(response => response.json())
		.then(data => {

			if (data.success && data.data) {
				// Update user info from API response if it includes name and phone
				if (data.data.user_info || (data.data.name && data.data.phone)) {
					const apiUserInfo = data.data.user_info || {
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
				if (data.data.cases && Array.isArray(data.data.cases)) {
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
					data.data.cases.forEach(caseItem => {
						const caseId = caseItem.id || caseItem.caseId || '';
						if (caseId) {
							favoritesSet.add(String(caseId));
						}
					});

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
						element.textContent = `(${updatedFavorites.length})`;
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
						element.textContent = '(0)';
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
					// Display the favorites HTML
					galleryContent.innerHTML = `
						<div class="brag-book-gallery-favorites-wrapper">
							<div class="brag-book-gallery-favorites-container">
								<div class="brag-book-gallery-favorites-view">
									${data.data.html}
								</div>
							</div>
						</div>
					`;

					// Add user email to the rendered content
					const userEmailElement = galleryContent.querySelector('.brag-book-gallery-favorites-user');
					if (userEmailElement) {
						userEmailElement.textContent = `Showing favorites for: ${userInfo.email}`;
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
				} else if (typeof data.data === 'string') {
					// Direct HTML response
					galleryContent.innerHTML = data.data;
				} else {
					// Empty or no cases - ensure localStorage is initialized
					if (!localStorage.getItem('brag-book-favorites')) {
						localStorage.setItem('brag-book-favorites', JSON.stringify([]));
					}

					galleryContent.innerHTML = `
						<div class="brag-book-gallery-favorites-wrapper">
							<div class="brag-book-gallery-favorites-container">
								<div class="brag-book-filtered-results">
									<svg class="brag-book-gallery-favorites-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 180">
										<path fill="#ff595c" d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"></path>
										<path fill="#ff595c" d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"></path>
										<path fill="#121827" d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"></path>
										<path fill="#121827" d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"></path>
										<path fill="#121827" d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"></path>
										<path fill="#121827" d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6,10.9,7.1,19.3,7.1Z"></path>
										<path fill="#121827" d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"></path>
										<path fill="#121827" d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"></path>
										<path fill="#121827" d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"></path>
										<path fill="#121827" d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"></path>
										<path fill="#121827" d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"></path>
										<path fill="#121827" d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"></path>
										<path fill="#121827" d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"></path>
										<path fill="#ff595c" d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"></path>
									</svg>
									<p class="brag-book-gallery-favorites-user">Showing favorites for: ${userInfo.email}</p>
									<div class="brag-book-gallery-favorites-empty">
										<svg class="empty-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
										</svg>
										<h3>No favorites yet</h3>
										<p>Start browsing the gallery and click the heart icon on cases you love to save them here.</p>
									</div>
								</div>
							</div>
						</div>
					`;
				}
			} else {
				// Show error message - ensure localStorage is initialized even on error
				if (!localStorage.getItem('brag-book-favorites')) {
					localStorage.setItem('brag-book-favorites', JSON.stringify([]));
				}

				galleryContent.innerHTML = `
					<div class="brag-book-gallery-favorites-empty">
						<svg class="empty-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
						</svg>
						<h2>No favorites found</h2>
						<p>${data.data?.message || 'Unable to load favorites. Please try again.'}</p>
					</div>
				`;
			}
		})
		.catch(error => {
			console.error('Error loading favorites:', error);
			galleryContent.innerHTML = `
				<div class="brag-book-gallery-favorites-empty">
					<svg class="empty-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
					</svg>
					<h2>Error loading favorites</h2>
					<p>An error occurred while loading your favorites. Please try again.</p>
				</div>
			`;
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

			// Add empty state
			const emptyState = document.createElement('div');
			emptyState.className = 'brag-book-gallery-favorites-empty-state';
			emptyState.innerHTML = `
				<svg class="empty-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
				</svg>
				<h2>No favorites yet</h2>
				<p>Start browsing the gallery and click the heart icon on cases you love to save them here.</p>
				<button class="brag-book-gallery-button" onclick="document.querySelector('[data-action=\\'show-favorites\\']').click()">
					Browse Gallery
				</button>
			`;
			casesGrid.parentElement.insertBefore(emptyState, casesGrid);
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

		const isFavorited = this.components.favoritesManager.getFavorites().has(`case-${caseId}`);

		return `
			<article class="brag-book-gallery-case-card" data-case-id="${caseId}">
				<div class="brag-book-gallery-image-container brag-book-gallery-single-image">
					<div class="brag-book-gallery-skeleton-loader" style="display:none;"></div>
					<div class="brag-book-gallery-item-actions">
						<button class="brag-book-gallery-favorite-button" data-favorited="${isFavorited}" data-item-id="case-${caseId}" aria-label="${isFavorited ? 'Remove from' : 'Add to'} favorites">
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
					console.log(`🖱️ Hover preloading case ${caseId}`);
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
				const priorityIcon = task.priority === 'high' ? '⚡' : task.priority === 'hover' ? '🖱️' : '📋';
				console.log(`${priorityIcon} Queue processed case ${task.caseId} (${task.priority} priority)`);
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

		// Debug: Log procedure ID lookup
		console.log('🔍 Looking up procedure IDs for slug:', procedureSlug);

		// Try to get from sidebar data first
		if (window.bragBookGalleryConfig?.sidebarData) {
			const sidebarData = window.bragBookGalleryConfig.sidebarData;
			
			// Search through categories for the procedure
			for (const category of Object.values(sidebarData)) {
				if (category.procedures) {
					for (const procedure of category.procedures) {
						if (procedure.slug === procedureSlug) {
							console.log('✅ Found procedure IDs from sidebar data:', procedure.ids);
							// Return comma-separated IDs if available
							return procedure.ids ? procedure.ids.join(',') : null;
						}
					}
				}
			}
		}

		// Fallback: Look for procedure data in page elements
		console.log('🔍 Checking page elements for procedure context...');
		
		// First, check if we're on a case details page - look for the case detail view container
		const caseDetailView = document.querySelector('.brag-book-gallery-case-detail-view');
		if (caseDetailView && caseDetailView.dataset.procedureIds) {
			console.log('✅ Found procedure IDs from case detail view:', caseDetailView.dataset.procedureIds);
			return caseDetailView.dataset.procedureIds;
		}
		
		// Check if there's a procedure link in the DOM that matches the slug
		const procedureLink = document.querySelector(`[data-procedure="${procedureSlug}"]`);
		if (procedureLink && procedureLink.dataset.procedureIds) {
			console.log('✅ Found procedure IDs from DOM element:', procedureLink.dataset.procedureIds);
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
				console.log('No procedure slug found in URL, cannot set sidebar active state');
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
				
				console.log(`✅ Set sidebar active state for procedure: ${procedureSlug}`);
			} else {
				console.warn(`⚠️ Could not find sidebar link for procedure: ${procedureSlug}`);
			}
		} catch (error) {
			console.error('Error setting sidebar active state:', error);
		}
	}
}

export default BRAGbookGalleryApp;
