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
			backdropId: 'dialogBackdrop',
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
	}

	initializeMobileMenu() {
		this.components.mobileMenu = new MobileMenu();
	}

	initializeFavorites() {
		this.components.favoritesManager = new FavoritesManager({
			onUpdate: (favorites) => {
				console.log('Favorites updated:', favorites.size);
			}
		});
	}

	initializeSearch() {
		const searchWrapper = document.querySelector('.brag-book-gallery-search-wrapper');
		if (searchWrapper) {
			this.components.searchAutocomplete = new SearchAutocomplete(searchWrapper, {
				minChars: 1,
				debounceDelay: 200,
				maxResults: 10,
				onSelect: (result) => {
					console.log('Selected procedure:', result);
					// The checkbox is automatically checked by the SearchAutocomplete class
				}
			});
		}

		// Also initialize mobile search
		const mobileSearchWrapper = document.querySelector('.brag-book-gallery-search-wrapper');
		if (mobileSearchWrapper) {
			// Create a modified search autocomplete for mobile
			const mobileInput = mobileSearchWrapper.querySelector('.brag-book-gallery-search-input');
			const mobileDropdown = mobileSearchWrapper.querySelector('.brag-book-gallery-search-dropdown');

			// Update the wrapper to have the correct elements
			if (mobileInput && mobileDropdown) {
				// Temporarily rename the mobile elements to match what SearchAutocomplete expects
				mobileInput.classList.add('brag-book-gallery-search-input');
				mobileDropdown.classList.add('brag-book-gallery-search-dropdown');

				this.components.mobileSearchAutocomplete = new SearchAutocomplete(mobileSearchWrapper, {
					minChars: 1,
					debounceDelay: 200,
					maxResults: 10,
					onSelect: (result) => {
						console.log('Selected procedure (mobile):', result);
						// The checkbox is automatically checked by the SearchAutocomplete class
					}
				});
			}
		}
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
		// Add click handlers to case links to load content dynamically
		document.addEventListener('click', (e) => {
			const caseLink = e.target.closest('.brag-book-gallery-case-link');
			if (caseLink) {
				e.preventDefault();
				const caseId = caseLink.dataset.caseId;
				const url = caseLink.href;

				if (caseId) {
					this.loadCaseDetails(caseId, url);
				}
			}
		});

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

	async loadCaseDetails(caseId, url, updateHistory = true) {
		const galleryContent = document.getElementById('gallery-content');
		if (!galleryContent) {
			console.error('Gallery content container not found');
			return;
		}

		console.log('Loading case details for:', caseId);

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

			// Make AJAX request to load case details
			const response = await fetch(bragBookGalleryConfig.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'brag_book_gallery_load_case',
					case_id: caseId,
					nonce: bragBookGalleryConfig.nonce || ''
				})
			});

			console.log('Response status:', response.status);

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const data = await response.json();
			console.log('Response data:', data);

			if (data.success && data.data) {
				this.displayCaseDetails(data.data);
			} else {
				console.error('API Error:', data);
				throw new Error(data.data?.message || data.message || 'Failed to load case details');
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
}

export default BRAGbookGalleryApp;
