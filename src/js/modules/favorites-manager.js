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
			backdropId: 'dialogBackdrop',
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
		const itemId = button.dataset.itemId;
		const isFavorited = button.dataset.favorited === 'true';

		// If removing favorite, just remove it
		if (isFavorited) {
			this.removeFavorite(itemId, button);
			return;
		}

		// If adding favorite and no user info, show dialog first
		if (!this.userInfo && !this.hasShownDialog) {
			this.lastAddedFavorite = itemId;
			this.lastAddedButton = button;
			// Add the favorite first so it shows in the dialog
			this.addFavorite(itemId, button);
			this.favoritesDialog.open();
			this.hasShownDialog = true;
			return;
		}

		// Add favorite
		this.addFavorite(itemId, button);
	}

	addFavorite(itemId, button) {
		// Animate heart
		button.dataset.favorited = 'true';

		// Add to favorites set
		this.favorites.add(itemId);

		if (this.options.persistToStorage) {
			this.saveToStorage();
		}

		this.updateUI();
		this.options.onUpdate(this.favorites);
	}

	removeFavorite(itemId, button) {
		// Find button if not provided
		if (!button) {
			button = document.querySelector(`[data-item-id="${itemId}"][data-favorited="true"]`);
		}

		if (button) {
			button.dataset.favorited = 'false';
		}

		// Remove from favorites set
		this.favorites.delete(itemId);

		if (this.options.persistToStorage) {
			this.saveToStorage();
		}

		this.updateUI();
		this.options.onUpdate(this.favorites);
	}

	handleFavoritesFormSubmit(form) {
		const formData = new FormData(form);
		const data = Object.fromEntries(formData.entries());

		// Save user info
		this.userInfo = data;
		if (this.options.persistToStorage) {
			this.saveUserInfo();
		}

		// Add the pending favorite
		if (this.lastAddedFavorite && this.lastAddedButton) {
			this.addFavorite(this.lastAddedFavorite, this.lastAddedButton);
			this.lastAddedFavorite = null;
			this.lastAddedButton = null;
		}

		// Close dialog
		this.favoritesDialog.close();

		// Show success message
		this.showSuccessNotification('Your information has been saved. Keep adding favorites!');

		console.log('User info saved:', data);
	}

	showSuccessNotification(message) {
		// Create notification element if it doesn't exist
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

	updateUI() {
		// Update count
		const countElement = document.querySelector('[data-favorites-count]');
		const count = this.favorites.size;

		if (countElement) {
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
		}

		// Update favorites grid in sidebar
		this.updateFavoritesDisplay();
	}

	loadFromStorage() {
		try {
			const stored = localStorage.getItem(this.options.storageKey);
			if (stored) {
				const items = JSON.parse(stored);
				this.favorites = new Set(items);

				// Update button states
				items.forEach(itemId => {
					const button = document.querySelector(`[data-item-id="${itemId}"]`);
					if (button) {
						button.dataset.favorited = 'true';
					}
				});
			}
		} catch (e) {
			console.error('Failed to load favorites from storage:', e);
		}
	}

	saveToStorage() {
		try {
			localStorage.setItem(this.options.storageKey, JSON.stringify([...this.favorites]));
		} catch (e) {
			console.error('Failed to save favorites to storage:', e);
		}
	}

	loadUserInfo() {
		try {
			const stored = localStorage.getItem(this.options.userInfoKey);
			if (stored) {
				this.userInfo = JSON.parse(stored);
				this.hasShownDialog = true; // Don't show dialog again if we have user info
			}
		} catch (e) {
			console.error('Failed to load user info from storage:', e);
		}
	}

	saveUserInfo() {
		try {
			localStorage.setItem(this.options.userInfoKey, JSON.stringify(this.userInfo));
		} catch (e) {
			console.error('Failed to save user info to storage:', e);
		}
	}

	getFavorites() {
		return this.favorites;
	}

	clear() {
		this.favorites.clear();
		if (this.options.persistToStorage) {
			this.saveToStorage();
		}
		this.updateUI();
	}
}

export default FavoritesManager;
