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

		// Handle favorites form submission
		const favoritesForm = document.querySelector('[data-form="favorites"]');
		if (favoritesForm) {
			favoritesForm.addEventListener('submit', (e) => {
				e.preventDefault();
				this.handleFavoritesFormSubmit(e.target);
			});
		}
	}

	toggleFavorite(button) {
		let itemId = button.dataset.itemId || button.dataset.caseId || '';
		const isFavorited = button.dataset.favorited === 'true';

		// Extract numeric ID from values like "case-12345" or "case_12345_main"
		if (itemId) {
			const matches = itemId.match(/(\d+)/);
			if (matches) {
				itemId = matches[1];
			}
		}

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
	 * Submit a favorite to the WordPress API
	 * @param {string} caseId - The case ID to add to favorites
	 */
	submitFavoriteToAPI(caseId) {
		// Prepare form data for WordPress AJAX endpoint
		const formData = new FormData();
		formData.append('action', 'brag_book_add_favorite');
		formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
		formData.append('case_id', caseId);
		formData.append('email', this.userInfo.email || '');
		formData.append('phone', this.userInfo.phone || '');
		formData.append('name', this.userInfo.name || '');

		// Submit to API
		fetch(window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData
		})
		.then(response => response.json())
		.then(response => {
			if (response.success) {
				// Show success notification
				this.showSuccessNotification('Added to favorites!');
			} else {
				// Show error notification
				console.error('Failed to save favorite:', response.data?.message);
				// You might want to show an error notification here
			}
		})
		.catch(error => {
			console.error('Error submitting favorite:', error);
		});
	}

	/**
	 * Handle submission of the user info form in the favorites dialog
	 * @param {HTMLFormElement} form - The form element that was submitted
	 */
	handleFavoritesFormSubmit(form) {
		const formData = new FormData(form);
		const data = Object.fromEntries(formData.entries());

		// Add case ID if we have a pending favorite
		if (this.lastAddedFavorite) {
			formData.append('case_id', this.lastAddedFavorite);
		}

		// Add action and nonce for WordPress AJAX
		formData.append('action', 'brag_book_add_favorite');
		formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');

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
				// Save user info locally AND to localStorage
				this.userInfo = data;
				if (this.options.persistToStorage) {
					this.saveUserInfo();
				}

				// Also save to localStorage for future sessions
				try {
					localStorage.setItem(this.options.userInfoKey, JSON.stringify(data));
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
	 * Display a success notification to the user
	 * @param {string} message - The message to display
	 */
	showSuccessNotification(message) {
		// Get or create notification element
		let notification = document.getElementById('favoritesNotification');
		if (!notification) {
			notification = document.createElement('div');
			notification.id = 'favoritesNotification';
			notification.className = 'brag-book-gallery-favorites-notification';
			document.body.appendChild(notification);
		}

		// Update message and show
		notification.textContent = message;
		notification.classList.add('active');

		// Animate if GSAP available
		if (typeof gsap !== 'undefined') {
			gsap.fromTo(notification,
				{ y: -20, opacity: 0 },
				{ y: 0, opacity: 1, duration: 0.3, ease: "back.out(1.7)" }
			);
		}

		// Hide after 3 seconds
		setTimeout(() => {
			if (typeof gsap !== 'undefined') {
				gsap.to(notification, {
					y: -20,
					opacity: 0,
					duration: 0.2,
					ease: "power2.in",
					onComplete: () => {
						notification.classList.remove('active');
					}
				});
			} else {
				notification.classList.remove('active');
			}
		}, 3000);
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
}

export default FavoritesManager;
