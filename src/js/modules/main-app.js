import Carousel from './carousel.js';
import Dialog from './dialog.js';
import FilterSystem from './filter-system.js';
import MobileMenu from './mobile-menu.js';
import FavoritesManager from './favorites-manager.js';
import ShareManager from './share-manager.js';
import SearchAutocomplete from './search-autocomplete.js';
import { NudityWarningManager, PhoneFormatter } from './utilities.js';

/**
 * Main Application
 * Orchestrates all components
 */
class BRAGbookGalleryApp {
	constructor() {
		this.components = {};
		// Store a global reference to the app instance
		window.bragBookGalleryApp = this;
		this.init();
	}

	async init() {
		// Initialize components
		this.initializeCarousels();
		this.initializeDialogs();
		this.initializeFilters();
		this.initializeMobileMenu();
		this.initializeFavorites();
		this.initializeSearch();
		this.initializeShareManager();
		this.initializeConsultationForm();
		this.initializeCaseLinks();

		console.log("BRAG bookGallery initialized");
	}

	initializeCarousels() {
		const carousels = document.querySelectorAll('.brag-book-gallery-carousel-wrapper');
		this.components.carousels = [];

		carousels.forEach((carousel, index) => {
			const options = index === 0 ? {
				// First carousel: enable infinite loop and autoplay
				infinite: true,
				autoplay: true,
				autoplayDelay: 4000,
				pauseOnHover: true
			} : {
				// Second carousel: disable infinite loop and autoplay
				infinite: false,
				autoplay: false
			};

			this.components.carousels.push(new Carousel(carousel, options));
		});

		// Handle window resize for carousels
		let resizeTimer;
		window.addEventListener('resize', () => {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(() => {
				this.components.carousels.forEach(carousel => carousel.refresh());
			}, 250);
		});
	}

	initializeDialogs() {
		this.components.consultationDialog = new Dialog('consultationDialog', {
			onOpen: () => console.log('Consultation dialog opened'),
			onClose: () => console.log('Consultation dialog closed')
		});

		// Setup consultation button clicks
		document.querySelectorAll('[data-action="request-consultation"]').forEach(button => {
			button.addEventListener('click', () => {
				this.components.consultationDialog.open();
			});
		});
	}

	initializeFilters() {
		const filterContainer = document.querySelector('.brag-book-gallery-nav');

		// Determine mode from data attribute or default to 'javascript'
		const mode = filterContainer?.dataset.filterMode || 'javascript';

		this.components.filterSystem = new FilterSystem(filterContainer, {
			mode: mode,
			baseUrl: '/gallery', // Customize as needed
			onFilterChange: (activeFilters) => {
				console.log('Active filters:', Array.from(activeFilters.entries()));
				this.applyFilters(activeFilters);
			},
			onNavigate: (url) => {
				// Custom navigation handler if needed
				console.log('Navigating to:', url);
				window.location.href = url;
			}
		});

		// Set up Clear All button
		this.initializeClearAllButton();

		// Initialize demographic filter badge integration
		this.initializeDemographicFilterBadges();
	}

	initializeMobileMenu() {
		this.components.mobileMenu = new MobileMenu();
	}

	initializeFavorites() {
		this.components.favoritesManager = new FavoritesManager({
			onUpdate: (favorites) => {
				console.log('Favorites updated:', favorites.size);
				// Update the favorites count in the sidebar link
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
			const favoritesPath = `/${gallerySlug}/myfavorites`;

			if (currentPath === favoritesPath || currentPath.includes('myfavorites')) {
				console.log('Favorites updated while on My Favorites page, refreshing...');
				// Small delay to ensure localStorage is updated
				setTimeout(() => {
					this.showFavoritesOnly();
				}, 100);
			}
		});
	}

	initializeSearch() {
		// Initialize all search wrappers (both desktop and mobile)
		const searchWrappers = document.querySelectorAll('.brag-book-gallery-search-wrapper');
		this.components.searchAutocompletes = [];

		searchWrappers.forEach((searchWrapper) => {
			const searchInstance = new SearchAutocomplete(searchWrapper, {
				minChars: 1,
				debounceDelay: 200,
				maxResults: 10,
				onSelect: (result) => {
					console.log('Selected procedure:', result);
					// The checkbox is automatically checked by the SearchAutocomplete class
				}
			});
			this.components.searchAutocompletes.push(searchInstance);
		});
	}

	initializeShareManager() {
		// Only initialize ShareManager if sharing is enabled
		if (typeof bragBookGalleryConfig !== 'undefined' &&
		    bragBookGalleryConfig.enableSharing === 'yes') {
			this.components.shareManager = new ShareManager({
				onShare: (data) => {
					console.log('Shared:', data);
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
			const caseLink = e.target.closest('.brag-book-gallery-case-link');
			if (caseLink) {
				e.preventDefault();
				e.stopPropagation();

				// Get case ID and procedure IDs from the link
				const caseId = caseLink.dataset.caseId;
				const procedureIds = caseLink.dataset.procedureIds;

				if (caseId) {
					console.log('Loading case:', caseId, 'with procedure IDs:', procedureIds);
					// Load case details via AJAX
					this.loadCaseDetails(caseId, caseLink.href, true, procedureIds);
				}
				return;
			}

			// Check if click is on a case card but not on interactive elements (fallback)
			const caseCard = e.target.closest('.brag-book-gallery-case-card');
			if (caseCard && !e.target.closest('button') && !e.target.closest('details')) {
				// Find the case link within the card
				const caseLinkInCard = caseCard.querySelector('.brag-book-gallery-case-link');
				if (caseLinkInCard && caseLinkInCard.href) {
					e.preventDefault();
					const caseId = caseLinkInCard.dataset.caseId;
					const procedureIds = caseLinkInCard.dataset.procedureIds || caseCard.dataset.procedureIds;

					if (caseId) {
						console.log('Loading case from card:', caseId, 'with procedure IDs:', procedureIds);
						this.loadCaseDetails(caseId, caseLinkInCard.href, true, procedureIds);
					}
				}
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

	async loadCaseDetails(caseId, url, updateHistory = true, procedureIds = null) {
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
				console.log('Got procedure IDs from case card:', procedureIds);
			}
		}

		console.log('Loading case details for:', caseId, 'with procedure IDs:', procedureIds);

		// Show loading state
		galleryContent.innerHTML = '<div class="brag-book-gallery-loading">Loading case details...</div>';

		// Update browser URL without page reload (only if not coming from popstate)
		if (updateHistory && window.history && window.history.pushState) {
			window.history.pushState({ caseId: caseId }, '', url);
		}

		try {
			// Check for config
			if (typeof bragBookGalleryConfig === 'undefined') {
				console.error('bragBookGalleryConfig not defined');
				throw new Error('Configuration not loaded');
			}

			console.log('Making AJAX request to:', bragBookGalleryConfig.ajaxUrl);

			// Prepare request parameters - use the HTML version
			const requestParams = {
				action: 'brag_book_load_case_details_html',
				case_id: caseId,
				nonce: bragBookGalleryConfig.nonce || ''
			};

			// Add procedure IDs if available
			if (procedureIds) {
				requestParams.procedure_ids = procedureIds;
			}

			// Make AJAX request to load case details
			const response = await fetch(bragBookGalleryConfig.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams(requestParams)
			});

			console.log('Response status:', response.status);

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const data = await response.json();
			console.log('Response data:', data);

			if (data.success && data.data && data.data.html) {
				// Display the HTML directly from the server
				galleryContent.innerHTML = data.data.html;
				// Re-initialize any necessary event handlers for the new content
				this.initializeCaseDetailThumbnails();
			} else {
				console.error('API Error:', data);
				throw new Error(data.data?.message || data.data || data.message || 'Failed to load case details');
			}
		} catch (error) {
			console.error('Error loading case details:', error);
			let errorMessage = 'Failed to load case details. Please try again.';

			// If we have a more specific error message, show it
			if (error.message) {
				errorMessage += '<br><small>' + error.message + '</small>';
			}

			galleryContent.innerHTML = '<div class="brag-book-gallery-error">' + errorMessage + '</div>';
		}
	}

	displayCaseDetails(caseData) {
		const galleryContent = document.getElementById('gallery-content');
		if (!galleryContent) return;

		// Build the case details HTML
		let html = '<div class="brag-book-gallery-case-details">';

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
		console.log('Searching for:', normalizedQuery);
		// Search implementation would go here
	}

	applyFilters(activeFilters) {
		// Filter implementation would go here
		console.log('Applying filters...');
	}

	async handleFormSubmit(form) {
		const formData = new FormData(form);
		const data = Object.fromEntries(formData.entries());

		console.log('Form submitted:', data);

		// Get submit button and disable it during submission
		const submitBtn = form.querySelector('.brag-book-gallery-form-submit');
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
				console.error('Form submission error:', errorMessage);
			}
		} catch (error) {
			console.error('Error submitting form:', error);
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
			const clearAllButton = document.getElementById('brag-book-gallery-clear-all');
			console.log('Clear All button found:', clearAllButton);

			if (clearAllButton) {
				// Remove any existing listeners
				clearAllButton.removeEventListener('click', this.handleClearAll);

				// Add new listener
				clearAllButton.addEventListener('click', this.handleClearAll.bind(this));
				console.log('Clear All button event listener attached');
				return true;
			} else {
				console.log('Clear All button not found - ID: brag-book-gallery-clear-all');
				return false;
			}
		};

		// Try immediately
		if (!setupClearAllHandler()) {
			// If not found, try again after a short delay (for AJAX loaded content)
			setTimeout(() => {
				console.log('Retrying Clear All button setup...');
				setupClearAllHandler();
			}, 1000);
		}

		// Also set up a global click handler as backup
		document.addEventListener('click', (e) => {
			if (e.target && e.target.id === 'brag-book-gallery-clear-all') {
				console.log('Global click handler caught Clear All button');
				e.preventDefault();
				this.handleClearAll(e);
			}
		});
	}

	handleClearAll(e) {
		e.preventDefault();
		console.log('Clear All button clicked - handling...');

		// Clear demographic filter checkboxes
		console.log('Clearing demographic filter checkboxes');
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
				console.log('Global handler: Badge remove button clicked!');
				e.preventDefault();
				e.stopPropagation();

				// Get the parent badge element
				const badge = removeButton.closest('.brag-book-gallery-filter-badge');
				if (badge) {
					const category = badge.getAttribute('data-filter-category');
					const value = badge.getAttribute('data-filter-value');

					console.log('Global handler: Removing filter', { category, value });

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
				console.log('Checkbox changed - target:', e.target);

				// Manually build activeFilters from checked checkboxes
				setTimeout(() => {
					const activeFilters = this.buildActiveFiltersFromDOM();
					console.log('Built activeFilters from DOM:', activeFilters);
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
				console.log('Detected filter change via periodic check:', currentState);
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
		const badgesContainer = document.getElementById('brag-book-gallery-filter-badges');
		const clearAllButton = document.getElementById('brag-book-gallery-clear-all');

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
			console.log('Badge remove button clicked!', { category, value });
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
		console.log(`Removing demographic filter: ${category} = ${value}`);

		// Find the checkbox directly using the data-filter-type attribute and value
		let targetCheckbox = null;

		// Based on the HTML structure, checkboxes have data-filter-type attribute
		// and the value attribute matches what we're looking for
		const selector = `input[type="checkbox"][data-filter-type="${category}"][value="${value}"]`;
		console.log('Looking for checkbox with selector:', selector);

		targetCheckbox = document.querySelector(selector);

		// If not found, try without quotes or with different case
		if (!targetCheckbox) {
			// Try to find any checkbox with the matching value in the category
			const checkboxes = document.querySelectorAll(`input[type="checkbox"][data-filter-type="${category}"]`);
			console.log(`Found ${checkboxes.length} checkboxes with data-filter-type="${category}"`);

			checkboxes.forEach(checkbox => {
				const checkboxValue = checkbox.value;
				const label = checkbox.nextElementSibling;
				const labelText = label?.textContent?.trim() || '';

				console.log(`Checking: value="${checkboxValue}", label="${labelText}", looking for="${value}"`);

				// Match the value exactly or case-insensitively
				if (checkboxValue === value ||
					checkboxValue.toLowerCase() === value.toLowerCase() ||
					labelText === value ||
					labelText.toLowerCase() === value.toLowerCase()) {
					targetCheckbox = checkbox;
					console.log('Found matching checkbox!');
				}
			});
		}

		// If still not found, try a broader search
		if (!targetCheckbox) {
			console.log('Trying broader search...');
			// Look for checkboxes by ID pattern (e.g., procedure-filter-age-18-24)
			const idPattern = `procedure-filter-${category}-${value}`.toLowerCase().replace(/\s+/g, '-');
			targetCheckbox = document.getElementById(idPattern);

			if (targetCheckbox) {
				console.log('Found checkbox by ID pattern:', idPattern);
			}
		}

		if (targetCheckbox) {
			console.log('Unchecking checkbox:', targetCheckbox);
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
					console.log('Calling applyDemographicFilters');
					window.applyDemographicFilters();
				}
			}, 100);

			// Remove the badge immediately from DOM
			const badge = document.querySelector(`.brag-book-gallery-filter-badge[data-filter-category="${category}"][data-filter-value="${value}"]`);
			if (badge) {
				console.log('Removing badge from DOM');
				badge.remove();
			}
		} else {
			console.warn(`Could not find checkbox for ${category}: ${value}`);

			// Log all available checkboxes for debugging
			console.log('Available checkboxes with data-filter-type:');
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
		console.log('Starting to clear demographic filters...');

		// Find all checked checkboxes in filter groups with multiple selector patterns
		const selectors = [
			'.brag-book-gallery-filter-group input[type="checkbox"]:checked',
			'input[type="checkbox"][data-filter-category]:checked',
			'.brag-book-gallery-filter-option input[type="checkbox"]:checked'
		];

		let totalCleared = 0;

		selectors.forEach(selector => {
			const checkboxes = document.querySelectorAll(selector);
			console.log(`Found ${checkboxes.length} checked checkboxes with selector: ${selector}`);

			checkboxes.forEach((checkbox) => {
				console.log('Unchecking checkbox:', checkbox);
				checkbox.checked = false;
				checkbox.dispatchEvent(new Event('change', { bubbles: true }));
				totalCleared++;
			});
		});

		console.log(`Total checkboxes cleared: ${totalCleared}`);

		// Also try to trigger any global filter clear functions
		if (window.clearProcedureFilters) {
			console.log('Calling global clearProcedureFilters function');
			window.clearProcedureFilters();
		}

		// Force update badges to hide them
		setTimeout(() => {
			console.log('Updating badges to hide them');
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
			formData.append('action', 'brag_book_load_filtered_gallery');
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
			const favoritesUrl = `/${gallerySlug}/myfavorites`;
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
						<h1 class="brag-book-gallery-favorites-title">My Favorite Cases</h1>
						<div class="brag-book-gallery-favorites-form-wrapper">
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
									<button type="submit" class="brag-book-gallery-form-submit brag-book-gallery-button">
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

		console.log('Loading favorites for email:', userInfo.email);

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
			// Debug logging
			console.log('AJAX Response:', data);

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
						console.log('Updated user info from API:', updatedUserInfo);

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
							console.log('Existing localStorage favorites:', existingFavorites);
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
					console.log('Updated localStorage favorites (merged):', updatedFavorites);

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
						console.log('Initialized empty favorites in localStorage');
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
								console.log('Updating favorite button states for IDs:', favoriteIds);

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
												console.log('Marked as favorite:', selector);
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
						console.log('Initialized empty favorites in localStorage');
					}

					galleryContent.innerHTML = `
						<div class="brag-book-gallery-favorites-wrapper">
							<div class="brag-book-gallery-favorites-container">
								<div class="brag-book-filtered-results">
									<h2 class="brag-book-gallery-content-title"><strong>My Favorites</strong> Gallery</h2>
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
					console.log('Initialized empty favorites in localStorage on error');
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

		const caseUrl = '/' + gallerySlug + '/' + procedureSlug + '/' + caseId;

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
					<a href="${caseUrl}" class="brag-book-gallery-case-link" data-case-id="${caseId}">
						<picture class="brag-book-gallery-picture">
							<img src="${imageUrl}" alt="Case ${caseId}" loading="lazy" data-image-type="single">
						</picture>
					</a>
				</div>
				<div class="brag-book-gallery-case-summary">
					<div class="brag-book-gallery-case-summary-left">
						<span class="brag-book-gallery-procedure-name">${procedureDisplayName}</span>
						<span class="brag-book-gallery-case-number">Case #${caseId}</span>
					</div>
					<div class="brag-book-gallery-case-summary-right">
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
}

export default BRAGbookGalleryApp;
