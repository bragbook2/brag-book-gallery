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
		let procedureId = '';
		const isFavorited = button.dataset.favorited === 'true';

		// Get case container (card or detail view)
		const caseCard = button.closest('.brag-book-gallery-case-card');
		const caseDetailView = button.closest('.brag-book-gallery-case-detail-view');
		const carouselItem = button.closest('.brag-book-gallery-carousel-item');
		const caseContainer = caseCard || caseDetailView || carouselItem;

		// Prioritize case procedure ID (currentProcedureId) for favorites
		// This is the unique identifier for a case-procedure combination
		if (caseContainer) {
			// Try various procedure ID attributes in priority order
			procedureId = caseContainer.dataset.currentProcedureId ||  // Current procedure ID (taxonomy pages)
				caseContainer.dataset.procedureId ||                   // Single procedure ID (favorites cards)
				(caseContainer.dataset.procedureIds ? caseContainer.dataset.procedureIds.split(',')[0] : '');  // First from list

			// Use procedure ID as the item ID for favorites
			if (procedureId) {
				itemId = procedureId;
			} else {
				// Fallback to button's own data attributes only if no procedure ID
				itemId = button.dataset.itemId || '';

				// Extract numeric ID from values like "case-12345" or "case_12345_main"
				if (itemId) {
					const matches = itemId.match(/(\d+)/);
					if (matches) {
						itemId = matches[1];
					}
				}
			}
		} else {
			// No container found, use button's own data attributes
			itemId = button.dataset.itemId || '';

			// Extract numeric ID from values like "case-12345"
			if (itemId) {
				const matches = itemId.match(/(\d+)/);
				if (matches) {
					itemId = matches[1];
				}
			}
		}

		// Fallback to active nav link procedure ID if still no procedure ID
		if (!procedureId) {
			const activeNavLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
			procedureId = activeNavLink?.dataset.procedureId || '';
			// If we got a procedure ID from nav link, use it as item ID too
			if (procedureId && !itemId) {
				itemId = procedureId;
			}
		}

		// Update hidden case ID field if it exists
		this.updateHiddenCaseIdField(itemId);
		// Update hidden procedure ID field if it exists
		this.updateHiddenProcedureIdField(procedureId);

		// If removing favorite, just remove it
		if (isFavorited) {
			this.removeFavorite(itemId, button, procedureId);
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
			this.submitFavoriteToAPI(itemId, procedureId);
		} else {
			// No user info - show dialog to collect it
			this.lastAddedFavorite = itemId;
			this.lastAddedProcedureId = procedureId;
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

	/**
	 * Update the hidden procedure ID field in the favorites form
	 * @param {string} procedureId - The procedure ID to set
	 */
	updateHiddenProcedureIdField(procedureId) {
		const hiddenField = document.querySelector('input[name="fav-procedure-id"]');
		if (hiddenField && procedureId) {
			hiddenField.value = procedureId;
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
	 * @param {string} procedureId - The procedure ID (optional)
	 */
	removeFavorite(itemId, button, procedureId = '') {
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

		// If user is logged in, also remove from API
		if (this.userInfo && this.userInfo.email) {
			this.removeFavoriteFromAPI(itemId, procedureId);
		}
	}

	/**
	 * Remove a favorite from the BRAGBook API
	 * @param {string} caseId - The case procedure ID to remove from favorites
	 * @param {string} procedureId - The procedure ID
	 */
	removeFavoriteFromAPI(caseId, procedureId = '') {
		// Use WordPress AJAX for secure API communication
		const formData = new FormData();
		formData.append('action', 'brag_book_remove_favorite');
		formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
		formData.append('case_id', caseId);
		formData.append('procedure_id', procedureId);
		formData.append('email', this.userInfo.email || '');

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
				this.showSuccessNotification('Removed from favorites!');

				// Remove the card from the favorites grid on the favorites page
				this.removeCardFromFavoritesGrid(caseId);
			} else {
				// Show error notification
				console.error('Failed to remove favorite:', response.data?.message);
				this.showErrorNotification(response.data?.message || 'Failed to remove favorite. Please try again.');

				// Restore the favorite state since API call failed
				this.restoreFavoriteState(caseId);
			}
		})
		.catch(error => {
			console.error('Error removing favorite:', error);
			this.showErrorNotification('Error removing favorite. Please try again.');

			// Restore the favorite state since API call failed
			this.restoreFavoriteState(caseId);
		});
	}

	/**
	 * Remove a card from the favorites grid on the favorites page
	 * @param {string} caseId - The case ID to remove
	 */
	removeCardFromFavoritesGrid(caseId) {
		// Find the card element by various possible selectors
		const card = document.querySelector(
			`.brag-book-gallery-case-card[data-post-id="${caseId}"], ` +
			`.brag-book-gallery-case-card[data-case-id="${caseId}"], ` +
			`.brag-book-gallery-favorites-card[data-post-id="${caseId}"], ` +
			`.brag-book-gallery-favorites-card[data-case-id="${caseId}"]`
		);

		if (card) {
			// Add fade-out animation
			card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
			card.style.opacity = '0';
			card.style.transform = 'scale(0.9)';

			// Remove from DOM after animation
			setTimeout(() => {
				card.remove();

				// Check if favorites grid is now empty
				const favoritesGrid = document.querySelector('.brag-book-gallery-favorites-grid, .brag-book-gallery-case-grid');
				if (favoritesGrid && favoritesGrid.children.length === 0) {
					// Show empty state
					const emptyState = document.getElementById('favoritesEmpty');
					const gridContainer = document.getElementById('favoritesGridContainer');
					if (emptyState) {
						emptyState.style.display = 'block';
					}
					if (favoritesGrid) {
						favoritesGrid.style.display = 'none';
					}
				}
			}, 300);
		}
	}

	/**
	 * Restore favorite state when API call fails
	 * @param {string} caseId - The case ID to restore
	 */
	restoreFavoriteState(caseId) {
		// Re-add to internal favorites
		this.favorites.add(caseId);

		// Save to storage
		if (this.options.persistToStorage) {
			this.saveToStorage();
		}

		// Update all buttons back to favorited state
		const buttons = document.querySelectorAll(
			`[data-item-id="${caseId}"], [data-case-id="${caseId}"], ` +
			`.brag-book-gallery-case-card[data-post-id="${caseId}"] [data-favorited], ` +
			`.brag-book-gallery-case-card[data-case-id="${caseId}"] [data-favorited]`
		);
		buttons.forEach(btn => {
			if (btn.dataset.favorited !== undefined) {
				btn.dataset.favorited = 'true';
			}
		});

		// Update UI
		this.updateUI();
	}

	/**
	 * Submit a favorite directly to the BRAGBook API (for users with existing info)
	 * @param {string} caseId - The case procedure ID to add to favorites
	 * @param {string} procedureId - The procedure ID
	 */
	submitFavoriteToAPI(caseId, procedureId = '') {
		// Use WordPress AJAX for secure API communication
		const formData = new FormData();
		formData.append('action', 'brag_book_add_favorite');
		formData.append('nonce', window.bragBookGalleryConfig?.nonce || '');
		formData.append('case_id', caseId);
		formData.append('procedure_id', procedureId);
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
		// Get procedure ID from hidden field first, then fallback to stored value
		let procedureId = formData.get('fav-procedure-id') || this.lastAddedProcedureId || '';

		if (!caseId) {
			// Try to find the case ID from the current page context
			// Prioritize WordPress post ID over API case ID
			const articleElement = document.querySelector('.brag-book-gallery-case-card');
			if (articleElement) {
				caseId = articleElement.dataset.postId || articleElement.dataset.caseId;
				// Also try to get procedure ID if not already set
				if (!procedureId) {
					procedureId = articleElement.dataset.currentProcedureId ||
						(articleElement.dataset.procedureIds ? articleElement.dataset.procedureIds.split(',')[0] : '');
				}
			}
		}

		// Fallback to active nav link for procedure ID
		if (!procedureId) {
			const activeNavLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
			procedureId = activeNavLink?.dataset.procedureId || '';
		}

		if (!caseId) {
			// If still no case ID, show error
			this.showDetailedFormError(form, {
				title: 'Case Identification Error',
				message: 'Unable to determine which case to favorite.',
				details: [
					'This usually happens when the page hasn\'t fully loaded',
					'Try refreshing the page and clicking the favorite button again'
				]
			});
			return;
		}

		// Get form field values
		const email = formData.get('fav-email') || '';
		const name = formData.get('fav-name') || '';
		const phone = formData.get('fav-phone') || '';

		// Validate fields individually and collect errors
		const validationErrors = [];
		if (!name) validationErrors.push('Name is required');
		if (!email) validationErrors.push('Email is required');
		else if (!this.isValidEmail(email)) validationErrors.push('Email address is not valid');
		if (!phone) validationErrors.push('Phone number is required');

		if (validationErrors.length > 0) {
			this.showDetailedFormError(form, {
				title: 'Form Validation Error',
				message: 'Please correct the following issues:',
				details: validationErrors
			});
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
		formData.append('procedure_id', procedureId);
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
				// Parse and show detailed error
				this.parseAndShowDetailedError(form, response, 'save');

				// If there was an error, remove the favorite that was added
				if (this.lastAddedFavorite && this.lastAddedButton) {
					this.removeFavorite(this.lastAddedFavorite, this.lastAddedButton);
				}
			}
		})
		.catch(error => {
			console.error('Error submitting favorites form:', error);

			// Show detailed network error
			this.showDetailedFormError(form, {
				title: 'Connection Error',
				message: 'Unable to communicate with the server.',
				details: [
					'Check your internet connection',
					'The server may be temporarily unavailable',
					`Technical details: ${error.message}`
				]
			});

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
	 * Show detailed error message in form with structured information
	 * @param {HTMLFormElement} form - The form element
	 * @param {Object} errorInfo - Error information object
	 * @param {string} errorInfo.title - Error title
	 * @param {string} errorInfo.message - Main error message
	 * @param {Array<string>} errorInfo.details - Array of detail strings
	 */
	showDetailedFormError(form, errorInfo) {
		// Clear existing messages
		this.clearLookupMessages(form);

		// Create error container
		const errorDiv = document.createElement('div');
		errorDiv.className = 'brag-book-gallery-form-error brag-book-gallery-form-error--detailed';

		// Add title if provided
		if (errorInfo.title) {
			const titleElement = document.createElement('strong');
			titleElement.className = 'brag-book-gallery-form-error__title';
			titleElement.textContent = errorInfo.title;
			errorDiv.appendChild(titleElement);
		}

		// Add main message
		if (errorInfo.message) {
			const messageElement = document.createElement('p');
			messageElement.className = 'brag-book-gallery-form-error__message';
			messageElement.textContent = errorInfo.message;
			errorDiv.appendChild(messageElement);
		}

		// Add details list if provided
		if (errorInfo.details && errorInfo.details.length > 0) {
			const detailsList = document.createElement('ul');
			detailsList.className = 'brag-book-gallery-form-error__details';
			errorInfo.details.forEach(detail => {
				const listItem = document.createElement('li');
				listItem.textContent = detail;
				detailsList.appendChild(listItem);
			});
			errorDiv.appendChild(detailsList);
		}

		form.appendChild(errorDiv);

		// Reset button state if needed
		const submitButton = form.querySelector('button[type="submit"]');
		if (submitButton) {
			submitButton.disabled = false;
		}
	}

	/**
	 * Parse server error response and show detailed error
	 * @param {HTMLFormElement} form - The form element
	 * @param {Object} response - Server response object
	 * @param {string} action - Action being performed ('save', 'lookup', etc.)
	 */
	parseAndShowDetailedError(form, response, action = 'save') {
		const errorMessage = response.data?.message || response.message || 'Unknown error occurred';

		// Categorize error types and provide helpful context
		let errorInfo = {
			title: 'Error',
			message: errorMessage,
			details: []
		};

		// Check for specific error patterns and enhance the message
		const lowerMessage = errorMessage.toLowerCase();

		if (lowerMessage.includes('security') || lowerMessage.includes('verification')) {
			errorInfo.title = 'Security Verification Failed';
			errorInfo.details = [
				'Your session may have expired',
				'Try refreshing the page and submitting again',
				'If the problem persists, clear your browser cache'
			];
		} else if (lowerMessage.includes('required fields') || lowerMessage.includes('fill in')) {
			errorInfo.title = 'Form Validation Error';
			errorInfo.details = [
				'All fields (Name, Email, Phone) are required',
				'Make sure no fields are left empty'
			];
		} else if (lowerMessage.includes('valid email')) {
			errorInfo.title = 'Invalid Email Address';
			errorInfo.details = [
				'Please enter a valid email address',
				'Example: yourname@example.com'
			];
		} else if (lowerMessage.includes('case not found') || lowerMessage.includes('not available')) {
			errorInfo.title = 'Case Not Available';
			errorInfo.details = [
				'This case may have been removed or is no longer available',
				'The case might not be properly synced',
				'Contact support if you believe this is an error'
			];
		} else if (lowerMessage.includes('api') && (lowerMessage.includes('token') || lowerMessage.includes('configuration'))) {
			errorInfo.title = 'API Configuration Error';
			errorInfo.message = 'There is a problem with the site\'s API configuration.';
			errorInfo.details = [
				'This is not an issue with your submission',
				'Please contact the site administrator',
				'Technical detail: API authentication failed'
			];
		} else if (lowerMessage.includes('http') || lowerMessage.includes('status')) {
			errorInfo.title = 'Server Communication Error';
			errorInfo.details = [
				'The server returned an unexpected response',
				'This may be a temporary issue',
				'Try again in a few moments',
				`Technical detail: ${errorMessage}`
			];
		} else if (lowerMessage.includes('timeout') || lowerMessage.includes('network')) {
			errorInfo.title = 'Network Error';
			errorInfo.details = [
				'Unable to reach the server',
				'Check your internet connection',
				'The server may be experiencing high traffic'
			];
		} else {
			// Generic error - provide the message and general troubleshooting
			errorInfo.title = `Failed to ${action === 'save' ? 'Save Favorite' : 'Lookup Favorites'}`;
			errorInfo.details = [
				'If this problem continues, try:',
				'• Refreshing the page',
				'• Clearing your browser cache',
				'• Trying again in a few minutes',
				'• Contacting support if the issue persists'
			];
		}

		this.showDetailedFormError(form, errorInfo);
	}

	/**
	 * Validate email address format
	 * @param {string} email - Email address to validate
	 * @returns {boolean} - Whether the email is valid
	 */
	isValidEmail(email) {
		const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return emailRegex.test(email);
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

		// Hide after 3 seconds (or 5 for errors)
		const hideDelay = type === 'error' ? 5000 : 3000;
		setTimeout(() => {
			notification.classList.remove('active', 'success', 'error');
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
			// Check if this is in tiles view (no parentheses)
			const isTilesView = countElement.closest('.brag-book-gallery-favorites-link--tiles');
			countElement.textContent = isTilesView ? count : `(${count})`;
		});

		// Update all favorite button states (including dynamically loaded ones)
		this.updateAllButtonStates();

		// Update favorites grid in sidebar
		this.updateFavoritesDisplay();
	}

	/**
	 * Update all favorite button states to reflect current favorites
	 * This handles dynamically loaded content like carousels
	 */
	updateAllButtonStates() {
		// Get all favorite buttons on the page
		const allButtons = document.querySelectorAll('[data-favorited]');

		allButtons.forEach(button => {
			// Extract item ID from the button - prioritize procedure ID
			let itemId = '';

			// Check for procedure ID in parent container (card or carousel item)
			const caseCard = button.closest('.brag-book-gallery-case-card, .brag-book-gallery-carousel-item');
			if (caseCard) {
				// Prioritize procedure ID for favorites matching
				itemId = caseCard.dataset.currentProcedureId ||
					caseCard.dataset.procedureId ||
					(caseCard.dataset.procedureIds ? caseCard.dataset.procedureIds.split(',')[0] : '');
			}

			// Fallback to button's own data attributes
			if (!itemId) {
				itemId = button.dataset.itemId || '';

				// Extract numeric ID from values like "case-12345"
				if (itemId) {
					const matches = itemId.match(/(\d+)/);
					if (matches) {
						itemId = matches[1];
					}
				}
			}

			// Update button state based on whether item is favorited
			if (itemId && this.favorites.has(itemId)) {
				button.dataset.favorited = 'true';
			} else if (itemId) {
				button.dataset.favorited = 'false';
			}
		});
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
