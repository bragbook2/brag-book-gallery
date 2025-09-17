import Dialog from './dialog.js';

/**
 * Favorites Manager
 * Manages favorited items across the gallery
 */
class FavoritesManager {
	constructor(options = {}) {
		this.favorites = new Set();
		this.userInfo = null;
		this.hasShownDialog = false;
		this.options = {
			storageKey: options.storageKey || 'brag-book-favorites',
			userInfoKey: options.userInfoKey || 'brag-book-user-info',
			persistToStorage: options.persistToStorage !== false,
			onUpdate: options.onUpdate || (() => {}),
			...options
		};

		this.init();
	}

	init() {
		this.favoritesDialog = new Dialog('favoritesDialog', {
			onClose: () => {
				// If user closes without submitting, remove the just-added favorite
				if (!this.userInfo && this.lastAddedFavorite) {
					this.removeFavorite(this.lastAddedFavorite);
					this.lastAddedFavorite = null;
				}
			}
		});

		if (this.options.persistToStorage) {
			this.loadFromStorage();
			this.loadUserInfo();
		}
		this.setupEventListeners();
		this.updateUI();
	}

	setupEventListeners() {
		document.addEventListener('click', (e) => {
			const button = e.target.closest('[data-favorited]');
			if (button) {
				e.preventDefault();
				this.toggleFavorite(button);
			}

			// Handle close button for favorites dialog
			if (e.target.closest('[data-action="close-favorites-dialog"]')) {
				this.favoritesDialog.close();
			}
		});

		// Handle favorites form submission - check both selectors for compatibility
		const favoritesForm = document.querySelector('[data-form="favorites"], [data-form="favorites-email"]');
		if (favoritesForm) {
			favoritesForm.addEventListener('submit', (e) => {
				e.preventDefault();
				this.handleFavoritesFormSubmit(e.target);
			});
		}

		// Handle favorites lookup form submission
		const lookupForm = document.querySelector('.brag-book-gallery-favorites-lookup-form, #favorites-email-form');
		if (lookupForm) {
			lookupForm.addEventListener('submit', (e) => {
				e.preventDefault();
				this.handleFavoritesLookupSubmit(e.target);
			});
		}
	}

	toggleFavorite(button) {
		let itemId = '';
		const isFavorited = button.dataset.favorited === 'true';

		// Prioritize WordPress post ID from the case card data-post-id attribute
		const caseCard = button.closest('.brag-book-gallery-case-card');
		if (caseCard && caseCard.dataset.postId) {
			itemId = caseCard.dataset.postId;
		} else {
			// Fallback to button's own data attributes
			itemId = button.dataset.itemId || button.dataset.caseId || '';

			// Extract numeric ID from values like "case-12345" or "case_12345_main"
			if (itemId) {
				const matches = itemId.match(/(\d+)/);
				if (matches) {
					itemId = matches[1];
				}
			}
		}

		// Update hidden case ID field if it exists
		this.updateHiddenCaseIdField(itemId);

		// If removing favorite, just remove it
		if (isFavorited) {
			this.removeFavorite(itemId, button);
			return;
		}

		// If no item ID, can't add favorite
		if (!itemId) {
			console.error('No item ID found on button:', button);
			return;
		}

		// Check for user info in localStorage if we don't have it
		if (!this.userInfo || !this.userInfo.email) {
			this.loadUserInfo();
		}

		// If we have user info, submit to API directly
		if (this.userInfo && this.userInfo.email) {
			// Add to local favorites first
			this.addFavorite(itemId, button);
			// Submit to API
			this.submitFavoriteToAPI(itemId);
		} else {
			// No user info - show dialog to collect it
			this.lastAddedFavorite = itemId;
			this.lastAddedButton = button;
			// Add the favorite first so it shows in the dialog
			this.addFavorite(itemId, button);
			this.favoritesDialog.open();
			this.hasShownDialog = true;
		}
	}

	/**
	 * Update the hidden case ID field in the favorites form
	 * @param {string} caseId - The case ID to set
	 */
	updateHiddenCaseIdField(caseId) {
		const hiddenField = document.querySelector('input[name="fav-case-id"]');
		if (hiddenField && caseId) {
			hiddenField.value = caseId;
		}
	}

	addFavorite(itemId, button) {
		// Update the clicked button
		if (button) {
			button.dataset.favorited = 'true';
		}

		// Also update any other buttons for the same item
		const allButtons = document.querySelectorAll(`[data-item-id="${itemId}"], [data-case-id="${itemId}"]`);
		allButtons.forEach(btn => {
			if (btn.dataset.favorited !== undefined) {
				btn.dataset.favorited = 'true';
			}
		});

		// Add to internal favorites collection
		this.favorites.add(itemId);

		// Persist to localStorage if enabled
		if (this.options.persistToStorage) {
			this.saveToStorage();
		}

		this.updateUI();
		this.options.onUpdate(this.favorites);

		// Dispatch custom event for other components
		window.dispatchEvent(new CustomEvent('favoritesUpdated', {
			detail: { favorites: this.favorites }
		}));
	}

	/**
	 * Remove an item from favorites and update UI
	 * @param {string} itemId - The ID of the item to unfavorite
	 * @param {HTMLElement} button - The button that was clicked (optional)
	 */
	removeFavorite(itemId, button) {
		// Update button states - find all relevant buttons if none provided
		if (!button) {
			const buttons = document.querySelectorAll(`[data-item-id="${itemId}"][data-favorited="true"], [data-case-id="${itemId}"][data-favorited="true"]`);
			buttons.forEach(btn => {
				btn.dataset.favorited = 'false';
			});
		} else {
			// Update the specific button and find related buttons
			button.dataset.favorited = 'false';
			const otherButtons = document.querySelectorAll(`[data-item-id="${itemId}"], [data-case-id="${itemId}"]`);
			otherButtons.forEach(btn => {
				if (btn !== button && btn.dataset.favorited !== undefined) {
					btn.dataset.favorited = 'false';
				}
			});
		}

		// Remove from internal favorites collection
		this.favorites.delete(itemId);

		// Persist changes to localStorage if enabled
		if (this.options.persistToStorage) {
			this.saveToStorage();
		}

		// Update UI and notify listeners
		this.updateUI();
		this.options.onUpdate(this.favorites);

		// Dispatch global event for other components
		window.dispatchEvent(new CustomEvent('favoritesUpdated', {
			detail: { favorites: this.favorites }
		}));
	}

	/**
	 * Submit a favorite directly to the BRAGBook API (for users with existing info)
	 * @param {string} caseId - The case ID to add to favorites
	 */
	submitFavoriteToAPI(caseId) {
		// Use WordPress AJAX for secure API communication
		const formData = new FormData();
		formData.append('action', 'brag_book_add_favorite');
		formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
		formData.append('case_id', caseId);
		formData.append('email', this.userInfo.email || '');
		formData.append('phone', this.userInfo.phone || '');
		formData.append('name', this.userInfo.name || '');

		// Submit via WordPress AJAX (API tokens handled securely on server)
		fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData
		})
		.then(response => {
			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}
			return response.json();
		})
		.then(response => {
			if (response.success) {
				// Show success notification
				this.showSuccessNotification('Added to favorites!');
			} else {
				// Show error notification and remove from local favorites
				console.error('Failed to save favorite:', response.data?.message);
				this.removeFavorite(caseId);
				this.showErrorNotification('Failed to save favorite. Please try again.');
			}
		})
		.catch(error => {
			console.error('Error submitting favorite:', error);
			// Remove from local favorites if API call failed
			this.removeFavorite(caseId);
			this.showErrorNotification('Error saving favorite. Please try again.');
		});
	}

	/**
	 * Handle submission of the user info form in the favorites dialog
	 * @param {HTMLFormElement} form - The form element that was submitted
	 */
	handleFavoritesFormSubmit(form) {
		const formData = new FormData(form);

		// Get case ID from hidden field first, then fallback to other methods
		let caseId = formData.get('fav-case-id') || this.lastAddedFavorite;

		if (!caseId) {
			// Try to find the case ID from the current page context
			// Prioritize WordPress post ID over API case ID
			const articleElement = document.querySelector('.brag-book-gallery-case-card');
			if (articleElement) {
				caseId = articleElement.dataset.postId || articleElement.dataset.caseId;
			}
		}

		if (!caseId) {
			// If still no case ID, show error
			this.showFormError(form, 'Unable to determine case ID. Please try clicking the favorite button again.');
			return;
		}

		// Get form field values
		const email = formData.get('fav-email') || '';
		const name = formData.get('fav-name') || '';
		const phone = formData.get('fav-phone') || '';

		if (!email || !name || !phone) {
			this.showFormError(form, 'Please fill in all required fields.');
			return;
		}

		// Show loading state in form
		const submitButton = form.querySelector('button[type="submit"]');
		const originalText = submitButton ? submitButton.textContent : '';
		if (submitButton) {
			submitButton.disabled = true;
			submitButton.textContent = 'Submitting...';
		}

		// Clear any previous error messages
		const existingError = form.querySelector('.brag-book-gallery-form-error');
		const existingSuccess = form.querySelector('.brag-book-gallery-form-success');
		if (existingError) existingError.remove();
		if (existingSuccess) existingSuccess.remove();

		// Prepare WordPress AJAX request
		formData.append('action', 'brag_book_add_favorite');
		formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
		formData.append('case_id', caseId);
		formData.append('email', email);
		formData.append('phone', phone);
		formData.append('name', name);

		// Submit via WordPress AJAX (API tokens handled securely on server)
		fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData
		})
		.then(response => {
			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}
			return response.json();
		})
		.then(response => {
			if (response.success) {
				// Save user info locally
				const userInfo = { email, name, phone };
				this.userInfo = userInfo;

				// Save to localStorage for future sessions
				try {
					localStorage.setItem(this.options.userInfoKey, JSON.stringify(userInfo));
				} catch (e) {
					console.error('Failed to save user info to localStorage:', e);
				}

				// Show success message in form
				const successDiv = document.createElement('div');
				successDiv.className = 'brag-book-gallery-form-success';
				successDiv.textContent = response.data.message || 'Your information has been saved. Keep adding favorites!';
				form.appendChild(successDiv);

				// Close dialog after a short delay
				setTimeout(() => {
					this.favoritesDialog.close();
					// Clear the form
					form.reset();
					if (successDiv) successDiv.remove();
				}, 2000);

				// Show success notification
				this.showSuccessNotification(response.data.message || 'Your information has been saved. Keep adding favorites!');

				// Clear pending favorite
				this.lastAddedFavorite = null;
				this.lastAddedButton = null;
			} else {
				// Show error message in form
				const errorDiv = document.createElement('div');
				errorDiv.className = 'brag-book-gallery-form-error';
				errorDiv.textContent = response.data.message || 'Failed to save. Please try again.';
				form.appendChild(errorDiv);

				// If there was an error, remove the favorite that was added
				if (this.lastAddedFavorite && this.lastAddedButton) {
					this.removeFavorite(this.lastAddedFavorite, this.lastAddedButton);
				}
			}
		})
		.catch(error => {
			console.error('Error submitting favorites form:', error);

			// Show error message in form
			const errorDiv = document.createElement('div');
			errorDiv.className = 'brag-book-gallery-form-error';
			errorDiv.textContent = 'An error occurred. Please try again.';
			form.appendChild(errorDiv);

			// If there was an error, remove the favorite that was added
			if (this.lastAddedFavorite && this.lastAddedButton) {
				this.removeFavorite(this.lastAddedFavorite, this.lastAddedButton);
			}
		})
		.finally(() => {
			// Reset button state
			if (submitButton) {
				submitButton.disabled = false;
				submitButton.textContent = originalText;
			}
		});
	}

	/**
	 * Handle submission of the favorites lookup form
	 * @param {HTMLFormElement} form - The lookup form element that was submitted
	 */
	handleFavoritesLookupSubmit(form) {
		const formData = new FormData(form);
		const email = formData.get('email');

		if (!email) {
			this.showLookupError(form, 'Please enter an email address.');
			return;
		}

		// Show loading state
		const submitButton = form.querySelector('button[type="submit"]');
		const originalText = submitButton ? submitButton.textContent : '';
		if (submitButton) {
			submitButton.disabled = true;
			submitButton.textContent = 'Checking...';
		}

		// Clear any previous error messages
		this.clearLookupMessages(form);

		// First check localStorage for matching email
		const storedUserInfo = this.getUserInfo();
		if (storedUserInfo && storedUserInfo.email === email) {
			// Email matches localStorage, show favorites
			this.showLookupSuccess(form, email);
			return;
		}

		// If not found in localStorage, check with server
		// Add action and nonce for WordPress AJAX
		formData.append('action', 'brag_book_lookup_favorites');
		formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');

		// Submit via AJAX
		fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData
		})
		.then(response => {
			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}
			return response.json();
		})
		.then(response => {
			if (response.success) {
				// Email found, validate and save user data
				if (response.data && response.data.user) {
					const user = response.data.user;
					const favoritesData = response.data.favorites || {};

					// Only create user info if we have essential data (email at minimum)
					if (user.email && user.name && user.phone) {
						const userInfo = {
							email: user.email,
							name: user.name,
							first_name: user.first_name || '',
							last_name: user.last_name || '',
							phone: user.phone,
							id: user.id
						};

						// Save user info to localStorage only if all essential fields are present
						localStorage.setItem('brag-book-user-info', JSON.stringify(userInfo));
						this.userInfo = userInfo;

						// Save favorites data if available
						if (favoritesData.cases_data && Object.keys(favoritesData.cases_data).length > 0) {
							// Convert case IDs to strings for consistency with frontend
							const favoriteIds = (favoritesData.case_ids || []).map(id => String(id));

							// Save favorites to localStorage
							localStorage.setItem('brag-book-favorites', JSON.stringify(favoriteIds));

							// Update internal favorites
							this.favorites = new Set(favoriteIds);

							// Update UI to reflect loaded favorites
							favoriteIds.forEach(itemId => {
								const buttons = document.querySelectorAll(`[data-item-id="${itemId}"], [data-case-id="${itemId}"]`);
								buttons.forEach(button => {
									if (button.dataset.favorited !== undefined) {
										button.dataset.favorited = 'true';
									}
								});
							});
						}

						this.showLookupSuccess(form, email, userInfo, favoritesData);
					} else {
						// User found but incomplete data - don't create localStorage entries
						this.showLookupError(form, 'Account found but missing required information. Please contact support.');
					}
				} else {
					// No user data returned, but success - this shouldn't happen
					this.showLookupError(form, 'Account found but no details available. Please contact support.');
				}
			} else {
				// Email not found, show error
				this.showLookupError(form, response.data?.message || 'We were unable to locate account details for this email address.');
			}
		})
		.catch(error => {
			console.error('Error looking up favorites:', error);
			this.showLookupError(form, 'An error occurred while looking up your favorites. Please try again.');
		})
		.finally(() => {
			// Reset button state
			if (submitButton) {
				submitButton.disabled = false;
				submitButton.textContent = originalText;
			}
		});
	}

	/**
	 * Show lookup success and redirect to favorites view
	 */
	showLookupSuccess(form, email, userData = null, favoritesData = null) {
		// Only save user data if we have complete information
		if (userData && userData.email && userData.name && userData.phone) {
			this.userInfo = userData;
			this.saveUserInfo();
		} else {
			// Check if we have valid user info in localStorage
			const storedUserInfo = this.getUserInfo();
			if (storedUserInfo && storedUserInfo.email && storedUserInfo.name && storedUserInfo.phone) {
				this.userInfo = storedUserInfo;
			} else {
				// No valid user info - don't proceed
				this.showLookupError(form, 'Unable to load complete account information. Please try again or contact support.');
				return;
			}
		}

		// Update favorites count display
		if (favoritesData && favoritesData.total_count > 0) {
			this.updateUI();
		}

		// Show success message
		const successDiv = document.createElement('div');
		successDiv.className = 'brag-book-gallery-form-success';
		const favCount = favoritesData?.total_count || 0;
		successDiv.textContent = `Account found! Loading ${favCount} favorite${favCount !== 1 ? 's' : ''}...`;
		form.appendChild(successDiv);

		// Redirect to favorites view after a short delay
		setTimeout(() => {
			if (window.bragBookGalleryApp && typeof window.bragBookGalleryApp.initializeFavoritesPage === 'function') {
				window.bragBookGalleryApp.initializeFavoritesPage();
			}
		}, 1000);
	}

	/**
	 * Show lookup error message
	 */
	showLookupError(form, message) {
		const errorDiv = document.createElement('div');
		errorDiv.className = 'brag-book-gallery-form-error';
		errorDiv.textContent = message;
		form.appendChild(errorDiv);
	}

	/**
	 * Clear lookup messages
	 */
	clearLookupMessages(form) {
		const existingError = form.querySelector('.brag-book-gallery-form-error');
		const existingSuccess = form.querySelector('.brag-book-gallery-form-success');
		if (existingError) existingError.remove();
		if (existingSuccess) existingSuccess.remove();
	}

	/**
	 * Show error message in form
	 */
	showFormError(form, message) {
		// Clear existing messages
		this.clearLookupMessages(form);

		// Show error message in form
		const errorDiv = document.createElement('div');
		errorDiv.className = 'brag-book-gallery-form-error';
		errorDiv.textContent = message;
		form.appendChild(errorDiv);

		// Reset button state if needed
		const submitButton = form.querySelector('button[type="submit"]');
		if (submitButton) {
			submitButton.disabled = false;
		}
	}

	/**
	 * Display a success notification to the user
	 * @param {string} message - The message to display
	 */
	showSuccessNotification(message) {
		this.showNotification(message, 'success');
	}

	/**
	 * Display an error notification to the user
	 * @param {string} message - The message to display
	 */
	showErrorNotification(message) {
		this.showNotification(message, 'error');
	}

	/**
	 * Display a notification to the user
	 * @param {string} message - The message to display
	 * @param {string} type - The type of notification (success or error)
	 */
	showNotification(message, type = 'success') {
		// Get or create notification element
		let notification = document.getElementById('favoritesNotification');
		if (!notification) {
			notification = document.createElement('div');
			notification.id = 'favoritesNotification';
			notification.className = 'brag-book-gallery-favorites-notification';
			document.body.appendChild(notification);
		}

		// Update message, type, and show
		notification.textContent = message;
		notification.classList.remove('success', 'error');
		notification.classList.add('active', type);

		// Animate if GSAP available
		if (typeof gsap !== 'undefined') {
			gsap.fromTo(notification,
				{ y: -20, opacity: 0 },
				{ y: 0, opacity: 1, duration: 0.3, ease: "back.out(1.7)" }
			);
		}

		// Hide after 3 seconds (or 5 for errors)
		const hideDelay = type === 'error' ? 5000 : 3000;
		setTimeout(() => {
			if (typeof gsap !== 'undefined') {
				gsap.to(notification, {
					y: -20,
					opacity: 0,
					duration: 0.2,
					ease: "power2.in",
					onComplete: () => {
						notification.classList.remove('active', 'success', 'error');
					}
				});
			} else {
				notification.classList.remove('active', 'success', 'error');
			}
		}, hideDelay);
	}

	updateFavoritesDisplay() {
		const grid = document.getElementById('favorites-grid');
		const emptyMessage = document.getElementById('favorites-empty');

		if (!grid || !emptyMessage) return;

		// Clear existing items
		grid.innerHTML = '';

		if (this.favorites.size === 0) {
			grid.style.display = 'none';
			emptyMessage.style.display = 'block';
		} else {
			grid.style.display = 'grid';
			emptyMessage.style.display = 'none';

			// Add each favorite to the grid
			this.favorites.forEach(itemId => {
				// Find the original image
				const originalItem = document.querySelector(`[data-item-id="${itemId}"]`);
				if (!originalItem) return;

				const carouselItem = originalItem.closest('.brag-book-gallery-carousel-item');
				if (!carouselItem) return;

				const img = carouselItem.querySelector('img');
				if (!img) return;

				// Create favorite item
				const favoriteItem = document.createElement('div');
				favoriteItem.className = 'brag-book-gallery-favorites-item';
				favoriteItem.dataset.itemId = itemId;

				// Clone and add image
				const imgClone = img.cloneNode(true);
				favoriteItem.appendChild(imgClone);

				// Add remove button
				const removeBtn = document.createElement('button');
				removeBtn.className = 'brag-book-gallery-favorites-item-remove';
				removeBtn.innerHTML = '×';
				removeBtn.title = 'Remove from favorites';
				removeBtn.onclick = (e) => {
					e.stopPropagation();
					this.removeFavorite(itemId);
				};

				favoriteItem.appendChild(removeBtn);
				grid.appendChild(favoriteItem);
			});
		}
	}

	/**
	 * Update all UI elements that display favorites information
	 */
	updateUI() {
		// Update favorites count displays throughout the page
		const countElements = document.querySelectorAll('[data-favorites-count]');
		const count = this.favorites.size;

		countElements.forEach(countElement => {
			if (typeof gsap !== 'undefined') {
				gsap.to(countElement, {
					opacity: 0,
					duration: 0.1,
					onComplete: () => {
						countElement.textContent = `(${count})`;
						gsap.to(countElement, { opacity: 1, duration: 0.1 });
					}
				});
			} else {
				countElement.textContent = `(${count})`;
			}
		});

		// Update favorites grid in sidebar
		this.updateFavoritesDisplay();
	}

	/**
	 * Load favorites from localStorage and update button states
	 */
	loadFromStorage() {
		try {
			const stored = localStorage.getItem(this.options.storageKey);
			if (stored) {
				const items = JSON.parse(stored);
				this.favorites = new Set(items);

				// Update UI to reflect loaded favorites
				items.forEach(itemId => {
					// Find all buttons for this item and mark as favorited
					const buttons = document.querySelectorAll(`[data-item-id="${itemId}"], [data-case-id="${itemId}"]`);
					buttons.forEach(button => {
						if (button.dataset.favorited !== undefined) {
							button.dataset.favorited = 'true';
						}
					});
				});
			}
		} catch (e) {
			console.error('Failed to load favorites from storage:', e);
		}
	}

	/**
	 * Save favorites to localStorage
	 */
	saveToStorage() {
		try {
			localStorage.setItem(this.options.storageKey, JSON.stringify([...this.favorites]));
		} catch (e) {
			console.error('Failed to save favorites to storage:', e);
		}
	}

	/**
	 * Load user information from localStorage
	 */
	loadUserInfo() {
		try {
			const stored = localStorage.getItem(this.options.userInfoKey);
			if (stored) {
				this.userInfo = JSON.parse(stored);
				this.hasShownDialog = true; // Skip dialog if we already have user info
			}
		} catch (e) {
			console.error('Failed to load user info from storage:', e);
		}
	}

	/**
	 * Save user information to localStorage
	 */
	saveUserInfo() {
		// Only save if we have complete user information
		if (!this.userInfo || !this.userInfo.email || !this.userInfo.name || !this.userInfo.phone) {
			console.warn('Cannot save incomplete user info to localStorage:', this.userInfo);
			return;
		}

		try {
			localStorage.setItem(this.options.userInfoKey, JSON.stringify(this.userInfo));
		} catch (e) {
			console.error('Failed to save user info to storage:', e);
		}
	}

	/**
	 * Get the current favorites set
	 * @returns {Set<string>} Set of favorited item IDs
	 */
	getFavorites() {
		return this.favorites;
	}

	/**
	 * Clear all favorites and update UI
	 */
	clear() {
		this.favorites.clear();
		if (this.options.persistToStorage) {
			this.saveToStorage();
		}
		this.updateUI();
	}

	/**
	 * Get user information from storage
	 * @returns {Object|null} User information or null if not set
	 */
	getUserInfo() {
		if (this.userInfo) {
			return this.userInfo;
		}

		// Try to get from localStorage
		try {
			const stored = localStorage.getItem(this.options.userInfoKey);
			if (stored) {
				this.userInfo = JSON.parse(stored);
				return this.userInfo;
			}
		} catch (e) {
			console.error('Failed to retrieve user info from localStorage:', e);
		}

		return null;
	}

	/**
	 * Get current favorites set
	 * @returns {Set} Set of favorite IDs
	 */
	getFavorites() {
		return this.favorites;
	}

	/**
	 * Save user info to localStorage
	 */
	saveUserInfo() {
		// Only save if we have complete user information
		if (!this.userInfo || !this.userInfo.email || !this.userInfo.name || !this.userInfo.phone) {
			console.warn('Cannot save incomplete user info to localStorage:', this.userInfo);
			return;
		}

		try {
			localStorage.setItem(this.options.userInfoKey, JSON.stringify(this.userInfo));
		} catch (e) {
			console.error('Failed to save user info to localStorage:', e);
		}
	}

	/**
	 * Refresh event listeners for favorite buttons
	 * Useful after dynamically adding content
	 */
	refreshEventListeners() {
		// Re-scan for favorite buttons and set up event listeners
		const favoriteButtons = document.querySelectorAll('.brag-book-gallery-favorite-button');

		favoriteButtons.forEach(button => {
			// Remove existing listeners to avoid duplicates
			button.removeEventListener('click', this.handleFavoriteClick);

			// Add new listener
			button.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();
				this.toggleFavorite(button);
			});
		});
	}
}

export default FavoritesManager;
