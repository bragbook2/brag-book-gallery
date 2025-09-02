/**
 * Share Manager Component
 * Handles sharing functionality for carousel images
 */
class ShareManager {
	constructor(options = {}) {
		this.options = {
			shareMenuId: options.shareMenuId || 'shareMenu',
			animateWithGsap: typeof gsap !== 'undefined',
			onShare: options.onShare || (() => {}),
			...options
		};

		this.shareMenu = document.getElementById(this.options.shareMenuId);
		this.activeButton = null;
		this.activeItem = null;

		this.init();
	}

	init() {
		this.setupEventListeners();
		this.createShareMenu();
	}

	createShareMenu() {
		// Share menus will be created per button, not globally
		// This method is kept for compatibility but doesn't create a global menu
	}

	setupEventListeners() {
		// Listen for share button clicks (delegated)
		document.addEventListener('click', (e) => {
			const shareButton = e.target.closest('.brag-book-gallery-share-button');
			if (shareButton) {
				e.preventDefault();
				e.stopPropagation();
				this.toggleShareDropdown(shareButton);
			}

			// Handle dropdown item clicks
			const dropdownItem = e.target.closest('.brag-book-gallery-share-dropdown-item');
			if (dropdownItem) {
				e.preventDefault();
				const shareType = dropdownItem.dataset.shareType;
				if (shareType) {
					this.handleShare(shareType);
					this.hideShareDropdown();
				}
			}

			// Close dropdown when clicking outside
			const activeDropdowns = document.querySelectorAll('.brag-book-gallery-share-dropdown.active');
			activeDropdowns.forEach(dropdown => {
				if (!dropdown.contains(e.target) && 
				    !e.target.closest('.brag-book-gallery-share-button')) {
					dropdown.classList.remove('active');
					const button = dropdown.closest('.brag-book-gallery-share-button');
					if (button) {
						button.classList.remove('active');
					}
				}
			});
		});

		// Close on escape
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				const activeDropdowns = document.querySelectorAll('.brag-book-gallery-share-dropdown.active');
				activeDropdowns.forEach(dropdown => {
					dropdown.classList.remove('active');
					const button = dropdown.closest('.brag-book-gallery-share-button');
					if (button) {
						button.classList.remove('active');
					}
				});
			}
		});
	}

	toggleShareDropdown(button) {
		if (this.activeButton === button && this.shareMenu?.classList.contains('active')) {
			this.hideShareDropdown();
		} else {
			this.showShareDropdown(button);
		}
	}

	showShareDropdown(button) {
		// Get the carousel item (slide)
		this.activeItem = button.closest('.brag-book-gallery-carousel-item');
		this.activeButton = button;

		// Check if button already has a dropdown
		let dropdown = button.querySelector('.brag-book-gallery-share-dropdown');
		
		// Create dropdown if it doesn't exist
		if (!dropdown) {
			const menuHtml = `
                <div class="brag-book-gallery-share-dropdown" role="menu">
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="link" role="menuitem">Copy Link</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="email" role="menuitem">Email</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="facebook" role="menuitem">Facebook</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="twitter" role="menuitem">Twitter</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="pinterest" role="menuitem">Pinterest</button>
                    <button class="brag-book-gallery-share-dropdown-item" data-share-type="whatsapp" role="menuitem">WhatsApp</button>
                </div>
            `;
			button.insertAdjacentHTML('beforeend', menuHtml);
			dropdown = button.querySelector('.brag-book-gallery-share-dropdown');
		}

		this.shareMenu = dropdown;

		// Add active class to button
		button.classList.add('active');

		// Show dropdown (positioned via CSS)
		this.shareMenu.classList.add('active');

		// Animate if GSAP available
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			gsap.fromTo(this.shareMenu,
				{ y: -10, opacity: 0 },
				{ y: 0, opacity: 1, duration: 0.2, ease: "power2.out" }
			);
		}
	}

	hideShareDropdown() {
		if (!this.shareMenu) return;

		// Remove active class from button
		if (this.activeButton) {
			this.activeButton.classList.remove('active');
		}

		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			gsap.to(this.shareMenu, {
				y: -10,
				opacity: 0,
				duration: 0.15,
				ease: "power2.in",
				onComplete: () => {
					this.shareMenu.classList.remove('active');
					this.activeButton = null;
					this.activeItem = null;
				}
			});
		} else {
			this.shareMenu.classList.remove('active');
			this.activeButton = null;
			this.activeItem = null;
		}
	}

	handleShare(type) {
		if (!this.activeItem) return;

		// Get image data
		const img = this.activeItem.querySelector('img');
		const imageUrl = img?.src || '';
		const imageAlt = img?.alt || 'Medical procedure result';
		const slideId = this.activeItem.dataset.slide || '';

		// Build share URL (use current page URL with slide anchor)
		const baseUrl = window.location.origin + window.location.pathname;
		const shareUrl = `${baseUrl}#${slideId}`;
		const shareText = `Check out this ${imageAlt}`;

		switch(type) {
			case 'link':
				this.copyToClipboard(shareUrl);
				break;
			case 'email':
				this.shareViaEmail(shareUrl, shareText);
				break;
			case 'facebook':
				this.shareViaFacebook(shareUrl);
				break;
			case 'twitter':
				this.shareViaTwitter(shareUrl, shareText);
				break;
			case 'pinterest':
				this.shareViaPinterest(shareUrl, imageUrl, shareText);
				break;
			case 'whatsapp':
				this.shareViaWhatsApp(shareUrl, shareText);
				break;
		}

		// Dropdown is already hidden after selection

		// Callback
		this.options.onShare({ type, url: shareUrl, text: shareText });
	}

	copyToClipboard(text) {
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(() => {
				this.showNotification('Link copied to clipboard!');
			}).catch(() => {
				this.fallbackCopyToClipboard(text);
			});
		} else {
			this.fallbackCopyToClipboard(text);
		}
	}

	fallbackCopyToClipboard(text) {
		const textArea = document.createElement('textarea');
		textArea.value = text;
		textArea.style.position = 'fixed';
		textArea.style.left = '-999999px';
		document.body.appendChild(textArea);
		textArea.focus();
		textArea.select();

		try {
			document.execCommand('copy');
			this.showNotification('Link copied to clipboard!');
		} catch (err) {
			this.showNotification('Failed to copy link');
		}

		document.body.removeChild(textArea);
	}

	shareViaEmail(url, text) {
		const subject = encodeURIComponent('Check out this medical procedure result');
		const body = encodeURIComponent(`${text}\n\n${url}`);
		window.location.href = `mailto:?subject=${subject}&body=${body}`;
	}

	shareViaFacebook(url) {
		const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
		this.openShareWindow(shareUrl, 'facebook');
	}

	shareViaTwitter(url, text) {
		const shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
		this.openShareWindow(shareUrl, 'twitter');
	}

	shareViaPinterest(url, imageUrl, text) {
		const shareUrl = `https://pinterest.com/pin/create/button/?url=${encodeURIComponent(url)}&media=${encodeURIComponent(imageUrl)}&description=${encodeURIComponent(text)}`;
		this.openShareWindow(shareUrl, 'pinterest');
	}

	shareViaWhatsApp(url, text) {
		const shareUrl = `https://wa.me/?text=${encodeURIComponent(`${text} ${url}`)}`;
		this.openShareWindow(shareUrl, 'whatsapp');
	}

	openShareWindow(url, platform) {
		const width = 600;
		const height = 400;
		const left = (window.innerWidth - width) / 2;
		const top = (window.innerHeight - height) / 2;

		window.open(
			url,
			`share-${platform}`,
			`width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no,scrollbars=yes,resizable=yes`
		);
	}

	showNotification(message) {
		// Create notification element if it doesn't exist
		let notification = document.getElementById('shareNotification');
		if (!notification) {
			notification = document.createElement('div');
			notification.id = 'shareNotification';
			notification.className = 'brag-book-gallery-share-notification';
			document.body.appendChild(notification);
		}

		// Update message and show
		notification.textContent = message;
		notification.classList.add('active');

		// Animate if GSAP available
		if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
			gsap.fromTo(notification,
				{ y: -20, opacity: 0 },
				{ y: 0, opacity: 1, duration: 0.3, ease: "back.out(1.7)" }
			);
		}

		// Hide after 3 seconds
		setTimeout(() => {
			if (this.options.animateWithGsap && typeof gsap !== 'undefined') {
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

	// Web Share API support (for mobile)
	async shareNative(data) {
		if (navigator.share) {
			try {
				await navigator.share({
					title: data.title || 'Medical Procedure Result',
					text: data.text,
					url: data.url
				});
				return true;
			} catch (err) {
				if (err.name !== 'AbortError') {
					console.error('Share failed:', err);
				}
				return false;
			}
		}
		return false;
	}
}

export default ShareManager;
